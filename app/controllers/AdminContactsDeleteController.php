<?php
// e:\Snecinatripu\app\controllers\AdminContactsDeleteController.php
declare(strict_types=1);

/**
 * AdminContactsDeleteController
 *
 * Bezpečné mazání kontaktů s filtry. Default „plno pojistek":
 *   - Vyloučí kontakty s přiřazeným OZ
 *   - Vyloučí kontakty s uzavřenou smlouvou (workflow.stav = UZAVRENO)
 *   - Vyloučí kontakty s aktivním workflow stage (NOVE/ZPRACOVAVA/... atd.)
 *   - Vyloučí recyklované (recycle_count > 0)
 *
 * Pro override musí admin VĚDOMĚ zapnout checkbox + napsat „SMAZAT".
 *
 * Routes:
 *   GET  /admin/contacts/delete           — formulář + náhled
 *   POST /admin/contacts/delete/preview   — AJAX: vrátí JSON s počtem + sample
 *   POST /admin/contacts/delete/csv       — stáhne CSV backup matching kontaktů
 *   POST /admin/contacts/delete/execute   — provede DELETE
 */
final class AdminContactsDeleteController
{
    public function __construct(private PDO $pdo)
    {
    }

    /** Workflow stage stavy považované za „rozjednané" (ochrana) */
    private const ACTIVE_WORKFLOW_STAVS = [
        'NOVE', 'ZPRACOVAVA', 'NABIDKA', 'SCHUZKA', 'SANCE', 'CALLBACK',
        'BO_PREDANO', 'BO_VPRACI', 'BO_VRACENO', 'SMLOUVA',
    ];

    /** Sestaví WHERE clause z filtrů. Vrací [string, params[]]. */
    private function buildWhere(array $f): array
    {
        $where  = [];
        $params = [];

        // Stav kontaktu (multi)
        $stavs = (array) ($f['stav'] ?? []);
        $stavs = array_values(array_filter(array_map('strval', $stavs), fn($s) => $s !== ''));
        if ($stavs !== []) {
            $ph = implode(',', array_fill(0, count($stavs), '?'));
            $where[] = "c.stav IN ($ph)";
            $params = array_merge($params, $stavs);
        }

        // Kraje (multi)
        $regions = (array) ($f['region'] ?? []);
        $regions = array_values(array_filter(array_map('strval', $regions), fn($r) => $r !== ''));
        if ($regions !== []) {
            $ph = implode(',', array_fill(0, count($regions), '?'));
            $where[] = "c.region IN ($ph)";
            $params = array_merge($params, $regions);
        }

        // Subject type (multi)
        $stypes = (array) ($f['subject_type'] ?? []);
        $stypes = array_values(array_filter(array_map('strval', $stypes), fn($t) => in_array($t, ['firma','osvc','unknown'], true)));
        if ($stypes !== []) {
            $ph = implode(',', array_fill(0, count($stypes), '?'));
            $where[] = "c.subject_type IN ($ph)";
            $params = array_merge($params, $stypes);
        }

        // Datum vytvoření od
        $dateFrom = trim((string) ($f['date_from'] ?? ''));
        if ($dateFrom !== '' && strtotime($dateFrom) !== false) {
            $where[] = "DATE(c.created_at) >= ?";
            $params[] = $dateFrom;
        }
        // Datum vytvoření do
        $dateTo = trim((string) ($f['date_to'] ?? ''));
        if ($dateTo !== '' && strtotime($dateTo) !== false) {
            $where[] = "DATE(c.created_at) <= ?";
            $params[] = $dateTo;
        }

        // ── Pojistky (default ON, admin musí vypnout vědomě) ──
        $includeOz       = !empty($f['include_oz']);        // = i kontakty s OZ
        $includeContract = !empty($f['include_contract']);  // = i UZAVRENO
        $includeActive   = !empty($f['include_active']);    // = i NOVE/ZPRACOVAVA/...
        $includeRecycled = !empty($f['include_recycled']);  // = i recyklované

        if (!$includeOz) {
            $where[] = "c.assigned_sales_id IS NULL";
        }
        if (!$includeRecycled) {
            $where[] = "(c.recycle_count IS NULL OR c.recycle_count = 0)";
        }
        if (!$includeContract) {
            // Vyloučit kontakty s UZAVRENO workflow
            $where[] = "NOT EXISTS (SELECT 1 FROM oz_contact_workflow w
                                     WHERE w.contact_id = c.id AND w.stav = 'UZAVRENO')";
        }
        if (!$includeActive) {
            // Vyloučit kontakty s aktivním workflow stage
            $ph = implode(',', array_fill(0, count(self::ACTIVE_WORKFLOW_STAVS), '?'));
            $where[] = "NOT EXISTS (SELECT 1 FROM oz_contact_workflow w
                                     WHERE w.contact_id = c.id AND w.stav IN ($ph))";
            $params = array_merge($params, self::ACTIVE_WORKFLOW_STAVS);
        }

        // Vždy aplikovat AT LEAST jednu podmínku — pojistka proti DELETE FROM contacts
        // bez WHERE. Pokud admin nezadal vůbec nic, vrátíme 1=0 (= žádný řádek).
        $hasUserFilter = $stavs !== [] || $regions !== [] || $stypes !== []
            || $dateFrom !== '' || $dateTo !== '';
        if (!$hasUserFilter) {
            $where[] = '1=0'; // safety: bez explicitního filtru nemažeme nic
        }

        // Multi-tenant: VŽDY filtr na aktivní tenant
        // Bez tohoto by admin tenant 1 mohl smazat data tenant 2 přes formulář.
        $where[]  = 'c.tenant_id = ?';
        $params[] = crm_tenant_id();

        $whereSql = $where === [] ? '1=0' : implode(' AND ', $where);
        return [$whereSql, $params, $hasUserFilter];
    }

    /** GET /admin/contacts/delete */
    public function getIndex(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();
        $title = '🗑 Mazání kontaktů';

        // Výběr možností pro filtry
        $regionChoices = function_exists('crm_region_choices') ? crm_region_choices() : [];
        $contactStavs  = ['NEW', 'READY', 'VF_SKIP', 'CHYBNY_KONTAKT', 'EMAIL_READY',
                          'ASSIGNED', 'CALLBACK', 'NEDOVOLANO', 'CALLED_OK', 'CALLED_BAD',
                          'NEZAJEM', 'IZOLACE', 'FOR_SALES', 'DONE'];

        ob_start();
        require dirname(__DIR__) . '/views/admin/contacts/delete.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    /** POST /admin/contacts/delete/preview — AJAX: vrátí JSON s count + sample */
    public function postPreview(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Neplatný CSRF token.']);
            exit;
        }

        [$whereSql, $params, $hasUserFilter] = $this->buildWhere($_POST);

        try {
            // COUNT
            $cntStmt = $this->pdo->prepare("SELECT COUNT(*) FROM contacts c WHERE $whereSql");
            $cntStmt->execute($params);
            $total = (int) $cntStmt->fetchColumn();

            // Per-stav rozpad (pro info)
            $byStavStmt = $this->pdo->prepare(
                "SELECT c.stav, COUNT(*) AS cnt FROM contacts c WHERE $whereSql GROUP BY c.stav"
            );
            $byStavStmt->execute($params);
            $byStav = [];
            foreach ($byStavStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $byStav[(string) $r['stav']] = (int) $r['cnt'];
            }

            // Sample (max 15 řádků)
            $sampleStmt = $this->pdo->prepare(
                "SELECT c.id, c.firma, c.telefon, c.email, c.region, c.stav,
                        c.created_at, c.recycle_count,
                        COALESCE(u.jmeno, '') AS oz_name,
                        (SELECT w.stav FROM oz_contact_workflow w WHERE w.contact_id = c.id LIMIT 1) AS wf_stav
                 FROM contacts c
                 LEFT JOIN users u ON u.id = c.assigned_sales_id
                 WHERE $whereSql
                 ORDER BY c.created_at DESC
                 LIMIT 15"
            );
            $sampleStmt->execute($params);
            $sample = $sampleStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            if (function_exists('crm_db_log_error')) crm_db_log_error($e, __METHOD__);
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'DB chyba: ' . $e->getMessage()]);
            exit;
        }

        echo json_encode([
            'ok'             => true,
            'total'          => $total,
            'by_stav'        => $byStav,
            'sample'         => $sample,
            'has_user_filter'=> $hasUserFilter,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** POST /admin/contacts/delete/csv — stáhne CSV backup matching kontaktů */
    public function postCsv(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/contacts/delete');
        }

        [$whereSql, $params, $hasUserFilter] = $this->buildWhere($_POST);
        if (!$hasUserFilter) {
            crm_flash_set('⚠ Nastav alespoň jeden filtr před stažením CSV.');
            crm_redirect('/admin/contacts/delete');
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT c.id, c.firma, c.ico, c.telefon, c.email, c.adresa, c.mesto,
                        c.region, c.operator, c.prilez, c.poznamka, c.stav,
                        c.subject_type, c.created_at, c.updated_at,
                        c.narozeniny_majitele, c.vyrocni_smlouvy, c.sale_price,
                        c.activation_date, c.cancellation_date, c.recycle_count,
                        COALESCE(u.jmeno, '') AS oz_jmeno,
                        COALESCE(u.email, '') AS oz_email,
                        (SELECT w.stav FROM oz_contact_workflow w
                          WHERE w.contact_id = c.id LIMIT 1) AS workflow_stav
                 FROM contacts c
                 LEFT JOIN users u ON u.id = c.assigned_sales_id
                 WHERE $whereSql
                 ORDER BY c.id ASC"
            );
            $stmt->execute($params);
        } catch (\Throwable $e) {
            if (function_exists('crm_db_log_error')) crm_db_log_error($e, __METHOD__);
            crm_flash_set('⚠ Chyba při exportu: ' . $e->getMessage());
            crm_redirect('/admin/contacts/delete');
        }

        $filename = 'contacts_backup_' . date('Y-m-d_His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM pro Excel
        fwrite($out, "\xEF\xBB\xBF");
        // Hlavička
        fputcsv($out, [
            'id', 'firma', 'ico', 'telefon', 'email', 'adresa', 'mesto', 'kraj',
            'operator', 'prilez', 'poznamka', 'stav', 'subject_type', 'created_at',
            'updated_at', 'narozeniny', 'vyrocni_smlouvy', 'sale_price',
            'activation_date', 'cancellation_date', 'recycle_count',
            'oz_jmeno', 'oz_email', 'workflow_stav',
        ], ';');
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                $r['id'], $r['firma'], $r['ico'], $r['telefon'], $r['email'],
                $r['adresa'], $r['mesto'], $r['region'], $r['operator'], $r['prilez'],
                $r['poznamka'], $r['stav'], $r['subject_type'], $r['created_at'],
                $r['updated_at'], $r['narozeniny_majitele'], $r['vyrocni_smlouvy'],
                $r['sale_price'], $r['activation_date'], $r['cancellation_date'],
                $r['recycle_count'], $r['oz_jmeno'], $r['oz_email'], $r['workflow_stav'],
            ], ';');
        }
        fclose($out);
        exit;
    }

    /** POST /admin/contacts/delete/execute — provede skutečné DELETE */
    public function postExecute(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/contacts/delete');
        }

        // Anti-misclick: musí napsat "SMAZAT"
        $typed = trim((string) ($_POST['confirm_text'] ?? ''));
        if ($typed !== 'SMAZAT') {
            crm_flash_set('⚠ Pro smazání musíš napsat přesně „SMAZAT". Akce zrušena.');
            crm_redirect('/admin/contacts/delete');
        }

        [$whereSql, $params, $hasUserFilter] = $this->buildWhere($_POST);
        if (!$hasUserFilter) {
            crm_flash_set('⚠ Bez filtru nemažu nic. Nastav stav, kraj, nebo datum.');
            crm_redirect('/admin/contacts/delete');
        }

        $this->pdo->beginTransaction();
        try {
            // 1) Spočti kolik smažeme
            $cntStmt = $this->pdo->prepare("SELECT COUNT(*) FROM contacts c WHERE $whereSql");
            $cntStmt->execute($params);
            $toDelete = (int) $cntStmt->fetchColumn();

            if ($toDelete === 0) {
                $this->pdo->rollBack();
                crm_flash_set('ℹ Žádné kontakty neodpovídají filtru. Nic se nesmazalo.');
                crm_redirect('/admin/contacts/delete');
            }

            // 2) Načti IDs
            $idsStmt = $this->pdo->prepare("SELECT c.id FROM contacts c WHERE $whereSql");
            $idsStmt->execute($params);
            $ids = array_map('intval', $idsStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            if ($ids === []) {
                $this->pdo->rollBack();
                crm_flash_set('ℹ Žádné kontakty nenalezeny.');
                crm_redirect('/admin/contacts/delete');
            }

            // 3) DELETE z navazujících tabulek (FK CASCADE může nebo nemusí existovat,
            //    tak děláme manuálně pro jistotu). Batch po 500 IDs.
            $relatedTables = [
                'oz_contact_workflow', 'oz_contact_notes', 'oz_contact_actions',
                'contact_notes', 'workflow_log', 'contact_oz_flags',
                'contact_recycles', 'commissions', 'contact_quality_ratings',
            ];
            foreach (array_chunk($ids, 500) as $chunk) {
                $ph = implode(',', array_fill(0, count($chunk), '?'));
                foreach ($relatedTables as $t) {
                    try {
                        $this->pdo->prepare("DELETE FROM `{$t}` WHERE contact_id IN ($ph)")
                            ->execute($chunk);
                    } catch (\Throwable $_) {
                        // Tabulka nemusí existovat / může mít jiný sloupec — neselhat
                    }
                }
                // Konečně contacts — multi-tenant defense
                $this->pdo->prepare("DELETE FROM contacts WHERE id IN ($ph) AND tenant_id = ?")
                    ->execute(array_merge($chunk, [crm_tenant_id()]));
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if (function_exists('crm_db_log_error')) crm_db_log_error($e, __METHOD__);
            crm_flash_set('⚠ Chyba při mazání: ' . $e->getMessage());
            crm_redirect('/admin/contacts/delete');
        }

        // Audit log
        if (function_exists('crm_audit_log')) {
            try {
                crm_audit_log($this->pdo, (int) $user['id'], 'contacts.bulk_delete', 'contact', 0, [
                    'count'   => $toDelete,
                    'filters' => $_POST,
                    'ip'      => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                ]);
            } catch (\Throwable $_) {}
        }

        crm_flash_set('✓ Smazáno ' . $toDelete . ' kontaktů.');
        crm_redirect('/admin/contacts/delete');
    }
}
