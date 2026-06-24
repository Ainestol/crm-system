<?php
// e:\Snecinatripu\app\controllers\AdminContactRecycleController.php
declare(strict_types=1);

/**
 * AdminContactRecycleController
 *
 * Admin tool pro recyklaci starších kontaktů zpět do oběhu.
 *
 * Use cases:
 *   - Staré VF_SKIP kontakty (Vodafone klienti) po 2-5 letech když změnili operátora
 *   - NEZAJEM kontakty po čase (zákazník mohl změnit názor)
 *   - NEDOVOLANO kontakty (po čase zkusit znovu)
 *
 * Workflow:
 *   1. GET /admin/contacts/recycle — filtr + náhled výsledků
 *   2. POST /admin/contacts/recycle — bulk akce, vrátí vybrané kontakty do oběhu
 */
final class AdminContactRecycleController
{
    /** Stavy které lze recyklovat. IZOLACE NIKDY (DNC flag = GDPR risk). */
    private const RECYCLABLE_STAVS = [
        'VF_SKIP'        => '🔴 VF — přeskočeno čističkou',
        'NEZAJEM'        => '❌ Nezájem',
        'CALLED_BAD'     => '⛔ Bad call',
        'NEDOVOLANO'     => '📵 Nedovoláno (3×)',
        'CHYBNY_KONTAKT' => '🚫 Chybný kontakt',
    ];

    /** Cool-down — minimální doba od poslední změny stavu. */
    private const MIN_COOLDOWN_DAYS = 7;

    public function __construct(private PDO $pdo)
    {
    }

    /** GET /admin/contacts/recycle — filtr + náhled */
    public function getIndex(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        // Filtry z GET
        $stavs = (array) ($_GET['stav'] ?? []);
        $stavs = array_values(array_filter(array_map('strval', $stavs),
            fn($s) => isset(self::RECYCLABLE_STAVS[$s])));

        $dateFrom = (string) ($_GET['date_from'] ?? '');
        $dateTo   = (string) ($_GET['date_to'] ?? '');
        $region   = (string) ($_GET['region'] ?? '');
        $operator = (string) ($_GET['operator'] ?? '');
        $hasFilter = $stavs !== [] || $dateFrom !== '' || $dateTo !== '' || $region !== '' || $operator !== '';

        $contacts    = [];
        $totalCount  = 0;
        $stavCounts  = [];

        if ($hasFilter) {
            $where  = [];
            $params = [];

            // Stav filter (POVINNÝ — bez stav by mohl admin omylem recyklovat NOVÉ)
            if ($stavs !== []) {
                $ph = implode(',', array_fill(0, count($stavs), '?'));
                $where[] = "c.stav IN ($ph)";
                $params = array_merge($params, $stavs);
            } else {
                // Bez stav filtru — zobrazíme všechny recyklovatelné
                $allKeys = array_keys(self::RECYCLABLE_STAVS);
                $ph = implode(',', array_fill(0, count($allKeys), '?'));
                $where[] = "c.stav IN ($ph)";
                $params = array_merge($params, $allKeys);
            }

            if ($dateFrom !== '' && strtotime($dateFrom) !== false) {
                $where[] = "DATE(c.updated_at) >= ?";
                $params[] = $dateFrom;
            }
            if ($dateTo !== '' && strtotime($dateTo) !== false) {
                $where[] = "DATE(c.updated_at) <= ?";
                $params[] = $dateTo;
            }
            if ($region !== '') {
                $where[] = "c.region = ?";
                $params[] = $region;
            }
            if ($operator !== '') {
                if ($operator === 'empty') {
                    $where[] = "(c.operator IS NULL OR c.operator = '')";
                } else {
                    $where[] = "c.operator = ?";
                    $params[] = $operator;
                }
            }

            // Multi-tenant: tenant filter ke všem queries
            $where[]  = 'c.tenant_id = ?';
            $params[] = crm_tenant_id();

            $whereSql = implode(' AND ', $where);

            // COUNT
            $cntStmt = $this->pdo->prepare("SELECT COUNT(*) FROM contacts c WHERE $whereSql");
            $cntStmt->execute($params);
            $totalCount = (int) $cntStmt->fetchColumn();

            // Per-stav rozpad
            $bySumStmt = $this->pdo->prepare(
                "SELECT c.stav, COUNT(*) AS cnt FROM contacts c WHERE $whereSql GROUP BY c.stav"
            );
            $bySumStmt->execute($params);
            foreach ($bySumStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $stavCounts[(string) $row['stav']] = (int) $row['cnt'];
            }

            // Náhled prvních 200 kontaktů (víc nedává smysl pro UI)
            $listStmt = $this->pdo->prepare(
                "SELECT c.id, c.firma, c.telefon, c.region, c.operator, c.stav,
                        c.updated_at, c.created_at,
                        c.recycle_count, c.last_recycled_at, c.rejection_reason,
                        u.jmeno AS oz_name
                 FROM contacts c
                 LEFT JOIN users u ON u.id = c.assigned_sales_id
                 WHERE $whereSql
                 ORDER BY c.updated_at ASC
                 LIMIT 200"
            );
            $listStmt->execute($params);
            $contacts = $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        // Region choices pro filter
        $regionChoices = function_exists('crm_region_choices') ? crm_region_choices() : [];

        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();
        $title = '♻ Recyklace kontaktů';
        $recyclableStavs = self::RECYCLABLE_STAVS;
        ob_start();
        require dirname(__DIR__) . '/views/admin/contacts/recycle.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    /** POST /admin/contacts/recycle — execute bulk recycle */
    public function postExecute(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/contacts/recycle');
        }

        $contactIds = (array) ($_POST['contact_ids'] ?? []);
        $contactIds = array_values(array_filter(array_map('intval', $contactIds), fn($v) => $v > 0));

        if ($contactIds === []) {
            crm_flash_set('Nevybral(a) jsi žádné kontakty.');
            crm_redirect('/admin/contacts/recycle');
        }

        $targetMode = (string) ($_POST['target_mode'] ?? 'auto');
        $note       = trim((string) ($_POST['note'] ?? ''));
        $adminId    = (int) $user['id'];

        $processed = 0;
        $skipped   = 0;
        $skipReasons = [];

        // Multi-tenant: jen kontakty z aktivního tenantu
        $ph = implode(',', array_fill(0, count($contactIds), '?'));
        $fetchStmt = $this->pdo->prepare(
            "SELECT id, stav, operator, recycle_count, updated_at
             FROM contacts WHERE id IN ($ph) AND tenant_id = ?"
        );
        $fetchStmt->execute(array_merge($contactIds, [crm_tenant_id()]));
        $contacts = $fetchStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($contacts as $c) {
            $cid = (int) $c['id'];
            $oldStav = (string) $c['stav'];

            // Validace 1: nesmí být IZOLACE
            if ($oldStav === 'IZOLACE') {
                $skipped++;
                $skipReasons[] = "#$cid: IZOLACE (DNC) — nelze recyklovat";
                continue;
            }
            // Validace 2: musí být v recyklovatelném stavu
            if (!isset(self::RECYCLABLE_STAVS[$oldStav])) {
                $skipped++;
                $skipReasons[] = "#$cid: stav '$oldStav' není recyklovatelný";
                continue;
            }
            // Validace 3: cool-down 7 dní od posledního update
            $updatedAt = strtotime((string) $c['updated_at']) ?: 0;
            $cooldownMin = self::MIN_COOLDOWN_DAYS * 86400;
            if ($updatedAt > 0 && (time() - $updatedAt) < $cooldownMin) {
                $skipped++;
                $skipReasons[] = "#$cid: cool-down (zatím méně než " . self::MIN_COOLDOWN_DAYS . " dní)";
                continue;
            }

            // Cíl: 'auto' → dle operatora (VF/empty → NEW, TM/O2 → READY); 'new' / 'ready' → explicit
            $op = (string) ($c['operator'] ?? '');
            if ($targetMode === 'new') {
                $newStav = 'NEW';
            } elseif ($targetMode === 'ready') {
                $newStav = 'READY';
            } else {
                $newStav = (in_array($op, ['TM', 'O2'], true) && $oldStav !== 'VF_SKIP')
                    ? 'READY'
                    : 'NEW';
            }
            // U NEW resetujeme operator (čistička ho znovu nastaví)
            $resetOperator = ($newStav === 'NEW');

            $this->pdo->beginTransaction();
            try {
                // Reset queue_mix_seq: kontakt projde novým mixem a dostane pozici
                // na konci aktuální fronty. Bez tohohle by zůstal "viset" v původní
                // pozici (= ne v navolávačské frontě, protože jeho stav byl mimo NEW).
                $updSql = "UPDATE contacts
                           SET stav = :stav,
                               recycle_count = recycle_count + 1,
                               last_recycled_at = NOW(3),
                               last_recycled_by = :uid,
                               locked_by = NULL,
                               locked_until = NULL,
                               assigned_caller_id = NULL,
                               assigned_sales_id = NULL,
                               nedovolano_count = 0,
                               rejection_reason = NULL,
                               queue_mix_seq = NULL,
                               updated_at = NOW(3)";
                if ($resetOperator) {
                    $updSql .= ", operator = ''";
                }
                $updSql .= " WHERE id = :id AND tenant_id = :tid";

                $this->pdo->prepare($updSql)->execute([
                    'stav' => $newStav,
                    'uid'  => $adminId,
                    'id'   => $cid,
                    'tid'  => crm_tenant_id(),
                ]);

                // Audit log
                $this->pdo->prepare(
                    "INSERT INTO contact_recycles
                     (contact_id, recycled_by, previous_stav, new_stav, note)
                     VALUES (?, ?, ?, ?, ?)"
                )->execute([
                    $cid, $adminId, $oldStav, $newStav,
                    $note !== '' ? $note : null,
                ]);

                // Workflow log
                $this->pdo->prepare(
                    "INSERT INTO workflow_log (contact_id, user_id, old_status, new_status, note, created_at)
                     VALUES (?, ?, ?, ?, ?, NOW(3))"
                )->execute([
                    $cid, $adminId, $oldStav, $newStav,
                    'Recyklace adminem' . ($note !== '' ? ': ' . $note : ''),
                ]);

                $this->pdo->commit();
                $processed++;
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                $skipped++;
                $skipReasons[] = "#$cid: " . $e->getMessage();
                if (function_exists('crm_db_log_error')) {
                    crm_db_log_error($e, __METHOD__);
                }
            }
        }

        $msg = "♻ Recyklováno: $processed kontaktů.";
        if ($skipped > 0) {
            $msg .= " Přeskočeno: $skipped (" . implode('; ', array_slice($skipReasons, 0, 5)) . ').';
        }
        crm_flash_set($msg);
        crm_redirect('/admin/contacts/recycle');
    }
}
