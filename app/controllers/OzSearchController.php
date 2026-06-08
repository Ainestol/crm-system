<?php
// e:\Snecinatripu\app\controllers\OzSearchController.php
declare(strict_types=1);

/**
 * OzSearchController — vyhledávání kontaktů pro OZ.
 *
 * GET  /oz/search                  — search form + výsledky
 * GET  /oz/search/card?id=X        — detail karty kontaktu
 * POST /oz/search/note             — přidat poznámku k libovolnému kontaktu
 * POST /oz/search/takeover         — převzít kontakt (jen pokud nemá jiný OZ)
 *
 * Pravidla:
 *   - Vidět může cokoli (search napříč celou DB)
 *   - Poznámku přidat může komukoli (do contact_notes a oz_contact_notes pokud má caller_id)
 *   - PŘEVZÍT může jen pokud kontakt nemá jiný OZ NEBO má on sám (= safe takeover)
 */
final class OzSearchController
{
    private const MAX_RESULTS = 100;

    public function __construct(private PDO $pdo)
    {
    }

    /** GET /oz/search — formulář + výsledky */
    public function getIndex(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        $q = trim((string) ($_GET['q'] ?? ''));
        $results = [];
        $hasSearched = $q !== '';

        if ($hasSearched) {
            $results = $this->search($q);
        }

        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();
        $title = '🔍 Vyhledávání kontaktů';
        $ozId  = (int) $user['id'];

        ob_start();
        require dirname(__DIR__) . '/views/oz/search.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    /** GET /oz/search/card?id=X — detail kontaktu */
    public function getCard(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        $cid = (int) ($_GET['id'] ?? 0);
        if ($cid <= 0) {
            crm_flash_set('Chybí ID kontaktu.');
            crm_redirect('/oz/search');
        }

        $contact = $this->loadContact($cid);
        if (!$contact) {
            crm_flash_set('Kontakt nenalezen.');
            crm_redirect('/oz/search');
        }

        $timeline = $this->loadTimeline($cid);
        $statusLabel = $this->statusBadge($contact);

        // ── OZ poznámky pro prominentní box pod poznámkou navolávačky ──
        // Posledních 20 z oz_contact_notes — co OZ se zákazníkem reálně řešili.
        // Klíčový kontext pro nového převzímatele: hned vidí co řešili předchozí
        // OZ (změny stavu, povinné poznámky, reakce zákazníka).
        $ozNotesAll = [];
        try {
            $st = $this->pdo->prepare(
                "SELECT n.note, n.created_at,
                        COALESCE(u.jmeno, '?') AS oz_jmeno
                 FROM oz_contact_notes n
                 LEFT JOIN users u ON u.id = n.oz_id
                 WHERE n.contact_id = ?
                 ORDER BY n.created_at DESC
                 LIMIT 20"
            );
            $st->execute([$cid]);
            $ozNotesAll = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $_) {}

        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();
        $ozId  = (int) $user['id'];
        $canTakeover = $this->canTakeover($contact, $ozId);
        $title = '📋 ' . ($contact['firma'] ?: 'Kontakt #' . $cid);

        ob_start();
        require dirname(__DIR__) . '/views/oz/search_card.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    /** POST /oz/search/note — přidat poznámku */
    public function postNote(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/oz/search');
        }

        $cid = (int) ($_POST['contact_id'] ?? 0);
        $noteText = trim((string) ($_POST['note'] ?? ''));

        if ($cid <= 0 || $noteText === '') {
            crm_flash_set('⚠ Poznámka nemůže být prázdná.');
            crm_redirect('/oz/search/card?id=' . $cid);
        }
        if (mb_strlen($noteText) > 2000) {
            crm_flash_set('⚠ Poznámka příliš dlouhá (max 2000 znaků).');
            crm_redirect('/oz/search/card?id=' . $cid);
        }

        $ozId   = (int) $user['id'];
        $ozName = (string) ($user['jmeno'] ?? 'OZ');
        // Prefix podle role, aby ostatní viděli kdo to napsal
        $prefixed = '[OZ: ' . $ozName . '] ' . $noteText;

        try {
            // Global timeline (vidí všichni v datagridu)
            $this->pdo->prepare(
                "INSERT INTO contact_notes (contact_id, user_id, note, created_at)
                 VALUES (?, ?, ?, NOW(3))"
            )->execute([$cid, $ozId, $prefixed]);

            // OZ-specifická timeline (vidí OZ na kartě leadu) — jen pokud má OZ assigned
            $cs = $this->pdo->prepare(
                "SELECT assigned_sales_id FROM contacts WHERE id = ?"
            );
            $cs->execute([$cid]);
            $assignedOz = (int) ($cs->fetchColumn() ?: 0);
            if ($assignedOz > 0) {
                try {
                    $this->pdo->prepare(
                        "INSERT INTO oz_contact_notes (contact_id, oz_id, note, created_at)
                         VALUES (?, ?, ?, NOW(3))"
                    )->execute([$cid, $assignedOz, $prefixed]);
                } catch (\Throwable $_) {}
            }
        } catch (\Throwable $e) {
            if (function_exists('crm_db_log_error')) crm_db_log_error($e, __METHOD__);
            crm_flash_set('⚠ Chyba při ukládání: ' . $e->getMessage());
            crm_redirect('/oz/search/card?id=' . $cid);
        }

        crm_flash_set('✓ Poznámka přidána.');
        crm_redirect('/oz/search/card?id=' . $cid);
    }

    /** POST /oz/search/edit — uloží edit polí (firma/ico/tel/email/adresa/region/prilez/operator) */
    public function postEdit(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/oz/search');
        }

        $cid = (int) ($_POST['contact_id'] ?? 0);
        if ($cid <= 0) {
            crm_flash_set('⚠ Chybí ID kontaktu.');
            crm_redirect('/oz/search');
        }

        // Whitelist polí, která OZ smí editovat (= ne stav, ne přiřazení)
        $allowed = ['firma', 'ico', 'telefon', 'email', 'adresa', 'region', 'prilez', 'prilez_do', 'operator'];
        $regions = function_exists('crm_region_choices') ? crm_region_choices() : [];

        // Příležitost: pokud checkbox "has_prilez" nezaškrtnut, smaž obě hodnoty.
        // Pokud zaškrtnut ale text prázdný, uložíme sentinel "ano" (= "má, bez popisu").
        $hasPrilez = !empty($_POST['has_prilez']);
        if (!$hasPrilez) {
            $_POST['prilez']    = '';
            $_POST['prilez_do'] = '';
        } else {
            if (trim((string) ($_POST['prilez'] ?? '')) === '') {
                $_POST['prilez'] = 'ano';
            }
        }

        $sets = [];
        $params = [];
        $changed = [];
        foreach ($allowed as $field) {
            if (!array_key_exists($field, $_POST)) continue;
            $val = trim((string) $_POST[$field]);

            // Validace per typ
            if ($field === 'operator') {
                if (!in_array($val, ['', 'TM', 'O2', 'VF'], true)) continue;
            }
            if ($field === 'region') {
                if ($val !== '' && $regions !== [] && !in_array($val, $regions, true)) continue;
            }
            if ($field === 'prilez_do') {
                // Validace data YYYY-MM-DD
                if ($val !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                    $val = '';
                }
            }
            if (mb_strlen($val) > 500) continue;

            $sets[]   = "`$field` = ?";
            $params[] = $val === '' ? null : $val;
            $changed[] = $field;
        }

        if ($sets === []) {
            crm_flash_set('ℹ Žádná změna.');
            crm_redirect('/oz/search/card?id=' . $cid);
        }

        $params[] = $cid;
        try {
            $sql = "UPDATE contacts SET " . implode(', ', $sets) . ", updated_at = NOW(3) WHERE id = ?";
            $this->pdo->prepare($sql)->execute($params);
        } catch (\Throwable $e) {
            if (function_exists('crm_db_log_error')) crm_db_log_error($e, __METHOD__);
            crm_flash_set('⚠ Chyba při ukládání: ' . $e->getMessage());
            crm_redirect('/oz/search/card?id=' . $cid);
        }

        // Audit log
        if (function_exists('crm_audit_log')) {
            try {
                crm_audit_log($this->pdo, (int) $user['id'], 'oz.search_edit', 'contact', $cid, [
                    'changed' => $changed,
                ]);
            } catch (\Throwable $_) {}
        }

        crm_flash_set('✓ Uloženo: ' . implode(', ', $changed) . '.');
        crm_redirect('/oz/search/card?id=' . $cid);
    }

    /** POST /oz/search/takeover — převzít kontakt (jen pokud volný nebo můj) */
    public function postTakeover(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/oz/search');
        }

        $cid  = (int) ($_POST['contact_id'] ?? 0);
        $ozId = (int) $user['id'];

        if ($cid <= 0) {
            crm_flash_set('⚠ Chybí ID kontaktu.');
            crm_redirect('/oz/search');
        }

        $contact = $this->loadContact($cid);
        if (!$contact) {
            crm_flash_set('⚠ Kontakt nenalezen.');
            crm_redirect('/oz/search');
        }

        // POJISTKA: takeover možný jen pokud kontakt nemá jiný OZ (nebo má mě)
        if (!$this->canTakeover($contact, $ozId)) {
            crm_flash_set('⚠ Tento kontakt má jiný OZ — převzít ho můžeš jen přes admin.');
            crm_redirect('/oz/search/card?id=' . $cid);
        }

        $rawStavs = ['NEW', 'READY', 'VF_SKIP', 'ASSIGNED', 'NEDOVOLANO',
                     'EMAIL_READY', 'CHYBNY_KONTAKT', 'CALLED_BAD', 'NEZAJEM',
                     'FOR_SALES'];
        $oldStav = (string) ($contact['stav'] ?? 'NEW');

        $this->pdo->beginTransaction();
        try {
            // 1) Přiřaď OZ
            $this->pdo->prepare(
                "UPDATE contacts SET assigned_sales_id = ?, updated_at = NOW(3) WHERE id = ?"
            )->execute([$ozId, $cid]);

            // 2) Auto-promote: pokud byl v raw stavu → CALLED_OK
            if (in_array($oldStav, $rawStavs, true)) {
                $this->pdo->prepare(
                    "UPDATE contacts SET stav = 'CALLED_OK',
                                          datum_predani = COALESCE(datum_predani, NOW(3))
                     WHERE id = ?"
                )->execute([$cid]);
            }

            // 3) Workflow row (NOVE), pokud neexistuje
            $wfCheck = $this->pdo->prepare(
                "SELECT id FROM oz_contact_workflow WHERE contact_id = ? AND oz_id = ?"
            );
            $wfCheck->execute([$cid, $ozId]);
            if ($wfCheck->fetchColumn() === false) {
                try {
                    $this->pdo->prepare(
                        "INSERT INTO oz_contact_workflow
                         (contact_id, oz_id, stav, started_at, stav_changed_at, updated_at)
                         VALUES (?, ?, 'NOVE', NOW(3), NOW(3), NOW(3))"
                    )->execute([$cid, $ozId]);
                } catch (\Throwable $_) {}
            }

            // 4) Workflow log (audit)
            $this->pdo->prepare(
                "INSERT INTO workflow_log (contact_id, user_id, old_status, new_status, note, created_at)
                 VALUES (?, ?, ?, 'CALLED_OK', 'OZ search: převzato na sebe', NOW(3))"
            )->execute([$cid, $ozId, $oldStav]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if (function_exists('crm_db_log_error')) crm_db_log_error($e, __METHOD__);
            crm_flash_set('⚠ Chyba při převzetí: ' . $e->getMessage());
            crm_redirect('/oz/search/card?id=' . $cid);
        }

        if (function_exists('crm_audit_log')) {
            try {
                crm_audit_log($this->pdo, $ozId, 'oz.takeover', 'contact', $cid, [
                    'old_stav' => $oldStav,
                ]);
            } catch (\Throwable $_) {}
        }

        crm_flash_set('✓ Kontakt převzat — najdeš ho v Pracovní ploše → Rozpracované.');
        crm_redirect('/oz/leads');
    }

    // ════════════════════════════════════════════════════════════════
    //  HELPERS
    // ════════════════════════════════════════════════════════════════

    /** Plný-text search napříč firma/IČO/tel/email/adresa. */
    private function search(string $q): array
    {
        $qLower = mb_strtolower($q, 'UTF-8');
        $digits = preg_replace('/\D+/', '', $q);

        $whereParts = [];
        $params = [];

        // Text search (mesto sloupec nemusí existovat na všech DB schématech —
        // u nás je město součástí adresa, takže ho neřešíme zvlášť)
        $whereParts[] = "(LOWER(c.firma) LIKE ?
                       OR LOWER(c.email) LIKE ?
                       OR LOWER(c.adresa) LIKE ?)";
        $like = '%' . $qLower . '%';
        $params = array_merge($params, [$like, $like, $like]);

        // Číslo (telefon nebo IČO)
        if ($digits !== '' && mb_strlen($digits) >= 3) {
            $whereParts[] = "(c.ico LIKE ? OR REGEXP_REPLACE(c.telefon, '[^0-9]+', '') LIKE ?)";
            $params[] = '%' . $digits . '%';
            $params[] = '%' . $digits . '%';
        }

        $whereSql = '(' . implode(' OR ', $whereParts) . ')';

        try {
            $stmt = $this->pdo->prepare(
                "SELECT c.id, c.firma, c.ico, c.telefon, c.email, c.adresa, c.region,
                        c.prilez, c.operator,
                        c.stav AS contact_stav, c.assigned_sales_id, c.assigned_caller_id,
                        c.subject_type, c.dnc_flag,
                        COALESCE(usl.jmeno, '') AS sales_name,
                        COALESCE(ucl.jmeno, '') AS caller_name,
                        (SELECT w.stav FROM oz_contact_workflow w
                          WHERE w.contact_id = c.id ORDER BY w.updated_at DESC LIMIT 1) AS wf_stav
                 FROM contacts c
                 LEFT JOIN users usl ON usl.id = c.assigned_sales_id
                 LEFT JOIN users ucl ON ucl.id = c.assigned_caller_id
                 WHERE $whereSql
                 ORDER BY c.updated_at DESC
                 LIMIT " . self::MAX_RESULTS
            );
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            if (function_exists('crm_db_log_error')) crm_db_log_error($e, __METHOD__);
            return [];
        }
    }

    /** Načti kontakt + agregovaná data pro kartu. */
    private function loadContact(int $cid): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT c.*,
                        COALESCE(usl.jmeno, '') AS sales_name,
                        COALESCE(usl.email, '') AS sales_email,
                        COALESCE(ucl.jmeno, '') AS caller_name,
                        COALESCE(ucl.email, '') AS caller_email,
                        (SELECT w.stav FROM oz_contact_workflow w
                          WHERE w.contact_id = c.id ORDER BY w.updated_at DESC LIMIT 1) AS wf_stav,
                        (SELECT w.cislo_smlouvy FROM oz_contact_workflow w
                          WHERE w.contact_id = c.id ORDER BY w.updated_at DESC LIMIT 1) AS wf_cislo,
                        (SELECT w.datum_uzavreni FROM oz_contact_workflow w
                          WHERE w.contact_id = c.id ORDER BY w.updated_at DESC LIMIT 1) AS wf_datum
                 FROM contacts c
                 LEFT JOIN users usl ON usl.id = c.assigned_sales_id
                 LEFT JOIN users ucl ON ucl.id = c.assigned_caller_id
                 WHERE c.id = ? LIMIT 1"
            );
            $stmt->execute([$cid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $_) {
            return null;
        }
    }

    /**
     * Timeline: kompletní historie kontaktu — vidí to každý, kdo otevře search kartu.
     *
     * Zahrnuje 4 zdroje (dohromady ukázáno chronologicky DESC, max 80 položek):
     *   1) contact_notes      — globální (navolávačka, admin datagrid, rescue, premium)
     *   2) oz_contact_notes   — OZ-specifické poznámky z /oz/leads (povinné při změně stavu)
     *   3) workflow_log       — historie změn stavů (OZ + BO + rescue + admin)
     *   4) oz_contact_actions — sdílený pracovní deník OZ ↔ BO
     *
     * U každé položky se ukazuje "kdo + role + co" — nový převzímatel hned vidí
     * kontext (např. "[OZ: Šáša] zákazník chce 3× SIM" nebo "[Caller: Evička] …").
     */
    private function loadTimeline(int $cid): array
    {
        $events = [];

        // 1) Globální notes (navolávačka, admin, rescue, premium, search-karta)
        try {
            $st = $this->pdo->prepare(
                "SELECT cn.note AS msg, cn.created_at,
                        COALESCE(u.jmeno, '?') AS who,
                        COALESCE(u.role,  '')  AS role
                 FROM contact_notes cn
                 LEFT JOIN users u ON u.id = cn.user_id
                 WHERE cn.contact_id = ? ORDER BY cn.created_at DESC LIMIT 50"
            );
            $st->execute([$cid]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $events[] = [
                    'type' => 'note',
                    'when' => $r['created_at'],
                    'msg'  => $r['msg'],
                    'who'  => $r['who'],
                    'role' => (string) ($r['role'] ?? ''),
                ];
            }
        } catch (\Throwable $_) {}

        // 2) OZ-specifické notes — co OZ psal na své pracovní ploše /oz/leads
        //    (povinná poznámka při změně stavu, auto-log z workflow).
        //    Tohle je DŮLEŽITÉ pro nového převzímatele: bez toho by neviděl,
        //    co předchozí OZ se zákazníkem řešil.
        try {
            $st = $this->pdo->prepare(
                "SELECT ocn.note AS msg, ocn.created_at,
                        COALESCE(u.jmeno, '?') AS who,
                        COALESCE(u.role,  '')  AS role
                 FROM oz_contact_notes ocn
                 LEFT JOIN users u ON u.id = ocn.oz_id
                 WHERE ocn.contact_id = ? ORDER BY ocn.created_at DESC LIMIT 50"
            );
            $st->execute([$cid]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $events[] = [
                    'type' => 'oz_note',
                    'when' => $r['created_at'],
                    'msg'  => $r['msg'],
                    'who'  => $r['who'],
                    'role' => (string) ($r['role'] ?? ''),
                ];
            }
        } catch (\Throwable $_) {}

        // 3) Workflow log — změny stavů (OZ, BO, rescue, admin)
        try {
            $st = $this->pdo->prepare(
                "SELECT wl.old_status, wl.new_status, wl.note, wl.created_at,
                        COALESCE(u.jmeno, '?') AS who,
                        COALESCE(u.role,  '')  AS role
                 FROM workflow_log wl
                 LEFT JOIN users u ON u.id = wl.user_id
                 WHERE wl.contact_id = ? ORDER BY wl.created_at DESC LIMIT 50"
            );
            $st->execute([$cid]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $msg = ($r['old_status'] ?: '—') . ' → ' . ($r['new_status'] ?: '—');
                if (!empty($r['note'])) $msg .= ' · ' . $r['note'];
                $events[] = [
                    'type' => 'workflow',
                    'when' => $r['created_at'],
                    'msg'  => $msg,
                    'who'  => $r['who'],
                    'role' => (string) ($r['role'] ?? ''),
                ];
            }
        } catch (\Throwable $_) {}

        // 4) Actions (sdílený deník OZ/BO)
        try {
            $st = $this->pdo->prepare(
                "SELECT a.action_text AS msg, a.created_at,
                        COALESCE(u.jmeno, '?') AS who,
                        COALESCE(u.role,  '')  AS role
                 FROM oz_contact_actions a
                 LEFT JOIN users u ON u.id = a.oz_id
                 WHERE a.contact_id = ? ORDER BY a.created_at DESC LIMIT 50"
            );
            $st->execute([$cid]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $events[] = [
                    'type' => 'action',
                    'when' => $r['created_at'],
                    'msg'  => $r['msg'],
                    'who'  => $r['who'],
                    'role' => (string) ($r['role'] ?? ''),
                ];
            }
        } catch (\Throwable $_) {}

        // Sort by date DESC, vrátit max 100 (zvětšeno z 80 — máme teď 4 zdroje)
        usort($events, static fn($a, $b) => strcmp((string) $b['when'], (string) $a['when']));
        return array_slice($events, 0, 100);
    }

    /** Kdo s kontaktem pracuje? Vrátí ['label', 'color', 'icon']. */
    private function statusBadge(array $contact): array
    {
        $stav = (string) ($contact['stav'] ?? '');
        $wf   = (string) ($contact['wf_stav'] ?? '');
        $sales = (string) ($contact['sales_name'] ?? '');
        $caller = (string) ($contact['caller_name'] ?? '');

        if ((int) ($contact['dnc_flag'] ?? 0) === 1) {
            return ['label' => '🚫 DNC (zákaz volat)', 'color' => '#dc2626'];
        }
        if ($wf === 'UZAVRENO') {
            return ['label' => '✓ Uzavřená smlouva', 'color' => '#16a34a'];
        }
        if (in_array($wf, ['BO_PREDANO', 'BO_VPRACI', 'BO_VRACENO', 'SMLOUVA'], true)) {
            return ['label' => '🏢 Back-office: ' . $wf, 'color' => '#7c3aed'];
        }
        if ($wf !== '' && $wf !== '—' && $sales !== '') {
            return ['label' => '🎯 OZ: ' . $sales . ' · ' . $wf, 'color' => '#0e7490'];
        }
        if (in_array($stav, ['ASSIGNED', 'NEDOVOLANO', 'CALLBACK'], true) && $caller !== '') {
            return ['label' => '📞 Navolávačka: ' . $caller . ' · ' . $stav, 'color' => '#ea580c'];
        }
        if ($stav === 'CALLED_OK' && $sales !== '') {
            return ['label' => '🎯 OZ: ' . $sales . ' (převzal)', 'color' => '#0e7490'];
        }
        if ($stav === 'READY') {
            return ['label' => '📞 V poolu navolávačky', 'color' => '#3b82f6'];
        }
        if ($stav === 'NEW') {
            return ['label' => '🧹 Čeká čističku', 'color' => '#6b7280'];
        }
        if ($stav === 'NEZAJEM') {
            return ['label' => '❌ NEZAJEM (obvoláno)', 'color' => '#9ca3af'];
        }
        if (in_array($stav, ['CHYBNY_KONTAKT', 'VF_SKIP'], true)) {
            return ['label' => '⚠ ' . $stav, 'color' => '#9ca3af'];
        }
        return ['label' => $stav . ($wf !== '' && $wf !== '—' ? ' / ' . $wf : ''), 'color' => '#6b7280'];
    }

    /** Může OZ převzít kontakt? Jen pokud nemá jiný OZ (nebo má on sám). */
    private function canTakeover(array $contact, int $ozId): bool
    {
        $assignedOz = (int) ($contact['assigned_sales_id'] ?? 0);
        // Volný (0) nebo já = OK
        return $assignedOz === 0 || $assignedOz === $ozId;
    }
}
