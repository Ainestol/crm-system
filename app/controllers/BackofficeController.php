<?php
// e:\Snecinatripu\app\controllers\BackofficeController.php
declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'audit.php';

/**
 * Back-office workspace.
 *
 * Pracovní plocha pro role 'backoffice' (+ majitel/superadmin pro audit).
 * BO vidí kontakty po předání od OZ. Filtrování per tab je čistě podle workflow stavu
 * (deník není v této úvaze relevantní — viz $tabWhere v getIndex()):
 *   k_priprave  — stav IN ('BO_PREDANO','SMLOUVA')   (čerstvě předáno OZ-em)
 *   v_praci     — stav = 'BO_VPRACI'                 (BO převzal "Začít zpracovávat")
 *   vraceno_oz  — stav = 'BO_VRACENO'                (BO vrátil OZ k opravě)
 *   uzavreno    — stav = 'UZAVRENO'                  (smlouva ready / aktivní → provize)
 *   nezajem_vse — stav = 'NEZAJEM'                   (od všech OZ, grouped per OZ)
 *
 * Akce:
 *   postReturnToOz  — vrátit OZ s povinným důvodem (zápis do deníku, BO_VRACENO)
 *   postClose       — uzavřít smlouvu (zápis do deníku, UZAVRENO)
 *   postActionAdd   — BO přidá záznam do sdíleného Pracovního deníku
 *   postActionDelete — BO smaže svůj záznam
 */
final class BackofficeController
{
    public function __construct(private PDO $pdo)
    {
    }

    // ────────────────────────────────────────────────────────────────
    //  GET /bo  –  Hlavní pracovní plocha BO
    // ────────────────────────────────────────────────────────────────
    public function getIndex(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['backoffice', 'majitel', 'superadmin']);

        // Spustit migrace nových sloupců — kdyby BO otevřel stránku jako první
        // (před tím, než kdokoli šel na /oz/leads, kde běží OzController::ensureWorkflowTable()).
        $this->ensureWorkflowMigration();

        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();

        $validTabs = ['k_priprave', 'v_praci', 'vraceno_oz', 'uzavreno', 'nezajem_vse'];
        $tab = (string) ($_GET['tab'] ?? 'k_priprave');
        $tab = in_array($tab, $validTabs, true) ? $tab : 'k_priprave';

        // Where klauzule per tab (čistě podle workflow stavu)
        // K přípravě   = BO_PREDANO (nově předáno OZ-em)
        // V práci      = BO_VPRACI  (BO převzal a zpracovává)
        // Vráceno OZ   = BO_VRACENO (BO vrátil OZ k opravě)
        // Uzavřeno     = UZAVRENO   (smlouva ready / aktivní)
        // Nezájem všeOZ = NEZAJEM   (kontakty od všech OZ, grouped per OZ)
        $tabWhere = match ($tab) {
            'k_priprave'   => "w.stav IN ('BO_PREDANO','SMLOUVA')",
            'v_praci'      => "w.stav = 'BO_VPRACI'",
            'vraceno_oz'   => "w.stav = 'BO_VRACENO'",
            'uzavreno'     => "w.stav = 'UZAVRENO'",
            'nezajem_vse'  => "w.stav = 'NEZAJEM'",
        };

        // Sortování — uživatel může přepnout z URL ?sort=oldest|newest.
        //
        // Default direction:
        //   • Pracovní taby (k_priprave, v_praci, vraceno_oz) → 'oldest'
        //     (FIFO — kdo čeká nejdéle, je nahoře; rozpracovaná karta neskáče).
        //   • Uzavřeno + Nezájem → 'newest' (nejčerstvější aktivita nahoře).
        //
        // Použijeme stav_changed_at (NE updated_at). Důvod: updated_at se posune
        // při každé editaci (poznámka, checkbox), což by karty „přemísťovalo".
        // stav_changed_at se posune POUZE při změně stavu — stabilní pořadí.
        $defaultSort = in_array($tab, ['uzavreno', 'nezajem_vse'], true) ? 'newest' : 'oldest';
        $sort        = (string) ($_GET['sort'] ?? $defaultSort);
        if (!in_array($sort, ['oldest', 'newest'], true)) {
            $sort = $defaultSort;
        }
        $sortDir = $sort === 'newest' ? 'DESC' : 'ASC';

        $orderClause = match ($tab) {
            'nezajem_vse' => "ORDER BY u_oz.jmeno ASC, w.stav_changed_at $sortDir",
            default       => "ORDER BY w.stav_changed_at $sortDir, w.id $sortDir",
        };
        $sql = "SELECT c.id, c.firma, c.telefon, c.email, c.ico, c.adresa, c.region, c.operator,
                       c.poznamka                  AS caller_poznamka,
                       c.assigned_caller_id, c.assigned_sales_id,
                       w.stav                      AS oz_stav,
                       w.stav_changed_at           AS oz_stav_changed_at,
                       w.poznamka                  AS workflow_poznamka,
                       w.bmsl                      AS oz_bmsl,
                       w.smlouva_date              AS oz_smlouva_date,
                       w.nabidka_id                AS oz_nabidka_id,
                       w.callback_at               AS oz_callback_at,
                       w.schuzka_at                AS oz_schuzka_at,
                       w.updated_at                AS workflow_updated,
                       COALESCE(w.priprava_smlouvy,0) AS cb_priprava,
                       COALESCE(w.datovka_odeslana,0) AS cb_datovka,
                       COALESCE(w.podpis_potvrzen,0)  AS cb_podpis,
                       w.podpis_potvrzen_at          AS cb_podpis_at,
                       w.podpis_potvrzen_by          AS cb_podpis_by,
                       COALESCE(w.ubotem_zpracovano,0) AS cb_ubotem,
                       w.cislo_smlouvy             AS cislo_smlouvy,
                       w.datum_uzavreni            AS datum_uzavreni,
                       COALESCE(w.smlouva_trvani_roky, 3) AS smlouva_trvani_roky,
                       COALESCE(u_oz.jmeno, '—')   AS oz_name,
                       COALESCE(u_oz.id, 0)        AS oz_user_id,
                       COALESCE(u_caller.jmeno, '—') AS caller_name
                FROM contacts c
                INNER JOIN oz_contact_workflow w ON w.contact_id = c.id
                LEFT JOIN users u_oz     ON u_oz.id     = c.assigned_sales_id
                LEFT JOIN users u_caller ON u_caller.id = c.assigned_caller_id
                WHERE {$tabWhere}
                {$orderClause}
                LIMIT 500";

        $contacts = [];
        try {
            $stmt = $this->pdo->query($sql);
            $contacts = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (\PDOException) {
            // Tabulka workflow ještě neexistuje (čerstvá DB) — necháme prázdné
        }

        // ── Poznámky OZ (oz_contact_notes — auto-log z workflow změn) ─
        // BO musí vidět, co OZ k zákazníkovi napsal (povinné poznámky při změně stavu).
        $notesByContact = [];
        try {
            $nStmt = $this->pdo->query(
                "SELECT n.contact_id, n.note, n.created_at,
                        COALESCE(u.jmeno, '—') AS author_name
                 FROM oz_contact_notes n
                 INNER JOIN contacts c ON c.id = n.contact_id
                 INNER JOIN oz_contact_workflow w ON w.contact_id = c.id
                 LEFT JOIN users u ON u.id = n.oz_id
                 WHERE w.stav IN ('SMLOUVA','BO_PREDANO','BO_VPRACI','BO_VRACENO','UZAVRENO')
                 ORDER BY n.contact_id ASC, n.created_at ASC"
            );
            if ($nStmt) {
                foreach ($nStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $n) {
                    $notesByContact[(int) $n['contact_id']][] = $n;
                }
            }
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }

        // ── Pracovní deník akcí pro BO kontakty (sdílený s OZ) ────────
        $actionsByContact = [];
        try {
            $aStmt = $this->pdo->query(
                "SELECT a.id, a.contact_id, a.action_date, a.action_text, a.created_at,
                        a.oz_id                   AS author_id,
                        COALESCE(u.jmeno, '—')    AS author_name,
                        COALESCE(u.role, '')      AS author_role
                 FROM oz_contact_actions a
                 INNER JOIN contacts c ON c.id = a.contact_id
                 INNER JOIN oz_contact_workflow w ON w.contact_id = c.id
                 LEFT JOIN users u ON u.id = a.oz_id
                 WHERE w.stav IN ('BO_PREDANO', 'BO_VPRACI', 'BO_VRACENO', 'UZAVRENO', 'NEZAJEM', 'SMLOUVA')
                 ORDER BY a.contact_id ASC, a.action_date DESC, a.created_at DESC"
            );
            if ($aStmt) {
                foreach ($aStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $a) {
                    $actionsByContact[(int) $a['contact_id']][] = $a;
                }
            }
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }

        // ── Počty pro taby (zobrazení badge) ──────────────────────────
        $tabCounts = [
            'k_priprave'  => 0,
            'v_praci'     => 0,
            'vraceno_oz'  => 0,
            'uzavreno'    => 0,
            'nezajem_vse' => 0,
        ];
        try {
            $cStmt = $this->pdo->query(
                "SELECT
                    SUM(CASE WHEN w.stav IN ('BO_PREDANO','SMLOUVA') THEN 1 ELSE 0 END) AS k_priprave,
                    SUM(CASE WHEN w.stav = 'BO_VPRACI'               THEN 1 ELSE 0 END) AS v_praci,
                    SUM(CASE WHEN w.stav = 'BO_VRACENO'              THEN 1 ELSE 0 END) AS vraceno_oz,
                    SUM(CASE WHEN w.stav = 'UZAVRENO'                THEN 1 ELSE 0 END) AS uzavreno,
                    SUM(CASE WHEN w.stav = 'NEZAJEM'                 THEN 1 ELSE 0 END) AS nezajem_vse
                 FROM contacts c
                 INNER JOIN oz_contact_workflow w ON w.contact_id = c.id"
            );
            if ($cStmt) {
                $row = $cStmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $tabCounts = [
                        'k_priprave'  => (int) ($row['k_priprave']  ?? 0),
                        'v_praci'     => (int) ($row['v_praci']     ?? 0),
                        'vraceno_oz'  => (int) ($row['vraceno_oz']  ?? 0),
                        'uzavreno'    => (int) ($row['uzavreno']    ?? 0),
                        'nezajem_vse' => (int) ($row['nezajem_vse'] ?? 0),
                    ];
                }
            }
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }

        // Pro tab "nezajem_vse" — seskupit kontakty podle OZ (jen pokud jsme na něm)
        $contactsByOz = [];
        if ($tab === 'nezajem_vse') {
            foreach ($contacts as $c) {
                $key = (int) ($c['oz_user_id'] ?? 0);
                $name = (string) ($c['oz_name'] ?? '—');
                if (!isset($contactsByOz[$key])) {
                    $contactsByOz[$key] = ['oz_name' => $name, 'oz_id' => $key, 'contacts' => []];
                }
                $contactsByOz[$key]['contacts'][] = $c;
            }
            $contactsByOz = array_values($contactsByOz);
        }

        $title = 'Back-office';
        ob_start();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'backoffice' . DIRECTORY_SEPARATOR . 'index.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'base.php';
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /bo/return-oz  –  Vrátit OZ k opravě (s povinným důvodem)
    // ────────────────────────────────────────────────────────────────
    public function postReturnToOz(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['backoffice', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/bo');
        }

        $bouserId  = (int) $user['id'];
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        $tab       = (string) ($_POST['tab'] ?? 'k_priprave');
        $reason    = trim((string) ($_POST['reason'] ?? ''));

        if ($reason === '') {
            crm_flash_set('⚠ Důvod vrácení je povinný.');
            crm_redirect('/bo?tab=' . urlencode($tab) . '#c-' . $contactId);
        }
        if (mb_strlen($reason) > 1000) {
            $reason = mb_substr($reason, 0, 1000);
        }

        // Ověřit, že kontakt je v BO ke zpracování (BO_PREDANO, BO_VPRACI, SMLOUVA)
        $stmt = $this->pdo->prepare(
            "SELECT w.contact_id, w.oz_id, w.stav
             FROM oz_contact_workflow w
             WHERE w.contact_id = :cid AND w.stav IN ('BO_PREDANO','BO_VPRACI','SMLOUVA')
             LIMIT 1"
        );
        $stmt->execute(['cid' => $contactId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            crm_flash_set('⚠ Kontakt není v BO ke zpracování.');
            crm_redirect('/bo?tab=' . urlencode($tab));
        }
        $oldStav = (string) $row['stav'];

        // Update workflow stav → BO_VRACENO (zaznamenat stav_changed_at)
        $this->pdo->prepare(
            "UPDATE oz_contact_workflow
             SET stav_changed_at = CASE WHEN stav <> 'BO_VRACENO' THEN NOW(3) ELSE stav_changed_at END,
                 stav = 'BO_VRACENO',
                 updated_at = NOW(3)
             WHERE contact_id = :cid"
        )->execute(['cid' => $contactId]);

        // Audit log
        crm_log_workflow_change($this->pdo, $contactId, $bouserId, $oldStav, 'BO_VRACENO',
            'Vráceno OZ: ' . $reason);

        // Záznam do sdíleného deníku ("BO vrací OZ: <důvod>")
        $this->pdo->prepare(
            "INSERT INTO oz_contact_actions
               (contact_id, oz_id, action_date, action_text, created_at)
             VALUES (:cid, :uid, CURDATE(), :txt, NOW(3))"
        )->execute([
            'cid' => $contactId,
            'uid' => $bouserId,
            'txt' => '↩ Vráceno OZ: ' . $reason,
        ]);

        crm_flash_set('✓ Kontakt vrácen OZ.');
        crm_redirect('/bo?tab=' . urlencode($tab));
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /bo/start-work  –  BO převezme z K přípravě → V práci
    //  (BO_PREDANO / SMLOUVA → BO_VPRACI)
    // ────────────────────────────────────────────────────────────────
    public function postStartWork(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['backoffice', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/bo');
        }

        $bouserId  = (int) $user['id'];
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        $tab       = (string) ($_POST['tab'] ?? 'k_priprave');

        // Načti starý stav před UPDATE (kvůli auditnímu logu)
        $stmt = $this->pdo->prepare(
            "SELECT w.contact_id, w.stav
             FROM oz_contact_workflow w
             WHERE w.contact_id = :cid AND w.stav IN ('BO_PREDANO','SMLOUVA')
             LIMIT 1"
        );
        $stmt->execute(['cid' => $contactId]);
        $oldRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$oldRow) {
            crm_flash_set('⚠ Kontakt není v K přípravě.');
            crm_redirect('/bo?tab=' . urlencode($tab));
        }
        $oldStav = (string) $oldRow['stav'];

        $this->pdo->prepare(
            "UPDATE oz_contact_workflow
             SET stav_changed_at = CASE WHEN stav <> 'BO_VPRACI' THEN NOW(3) ELSE stav_changed_at END,
                 stav = 'BO_VPRACI',
                 updated_at = NOW(3)
             WHERE contact_id = :cid"
        )->execute(['cid' => $contactId]);

        // Audit log — kdo, kdy, odkud → kam
        crm_log_workflow_change($this->pdo, $contactId, $bouserId, $oldStav, 'BO_VPRACI', 'BO převzal — zpracování zahájeno');

        $this->pdo->prepare(
            "INSERT INTO oz_contact_actions
               (contact_id, oz_id, action_date, action_text, created_at)
             VALUES (:cid, :uid, CURDATE(), :txt, NOW(3))"
        )->execute([
            'cid' => $contactId,
            'uid' => $bouserId,
            'txt' => '🔧 BO převzal — zpracování zahájeno',
        ]);

        crm_flash_set('✓ Kontakt přesunut do V práci.');
        crm_redirect('/bo?tab=v_praci#c-' . $contactId);
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /bo/reopen  –  Otevřít znovu uzavřený kontrakt
    //  (UZAVRENO → BO_VPRACI)
    // ────────────────────────────────────────────────────────────────
    public function postReopen(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['backoffice', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/bo');
        }

        $bouserId  = (int) $user['id'];
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        $tab       = (string) ($_POST['tab'] ?? 'uzavreno');
        $reason    = trim((string) ($_POST['reason'] ?? ''));

        $stmt = $this->pdo->prepare(
            "SELECT w.contact_id
             FROM oz_contact_workflow w
             WHERE w.contact_id = :cid AND w.stav = 'UZAVRENO'
             LIMIT 1"
        );
        $stmt->execute(['cid' => $contactId]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            crm_flash_set('⚠ Kontakt není uzavřen.');
            crm_redirect('/bo?tab=' . urlencode($tab));
        }

        $this->pdo->prepare(
            "UPDATE oz_contact_workflow
             SET stav_changed_at = CASE WHEN stav <> 'BO_VPRACI' THEN NOW(3) ELSE stav_changed_at END,
                 stav = 'BO_VPRACI',
                 closed_at = NULL,
                 updated_at = NOW(3)
             WHERE contact_id = :cid"
        )->execute(['cid' => $contactId]);

        crm_log_workflow_change($this->pdo, $contactId, $bouserId, 'UZAVRENO', 'BO_VPRACI',
            'Znovu otevřeno z Uzavřeno' . ($reason !== '' ? ': ' . $reason : ''));

        $logText = '🔄 Otevřeno znovu z Uzavřeno' . ($reason !== '' ? ': ' . $reason : '');
        $this->pdo->prepare(
            "INSERT INTO oz_contact_actions
               (contact_id, oz_id, action_date, action_text, created_at)
             VALUES (:cid, :uid, CURDATE(), :txt, NOW(3))"
        )->execute([
            'cid' => $contactId,
            'uid' => $bouserId,
            'txt' => mb_substr($logText, 0, 1000),
        ]);

        crm_flash_set('✓ Kontakt znovu otevřen.');
        crm_redirect('/bo?tab=v_praci#c-' . $contactId);
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /bo/close  –  Uzavřít kontrakt (UZAVRENO)
    //  Vyžaduje: cislo_smlouvy, datum_uzavreni, smlouva_trvani_roky
    //  Auto-set: contacts.vyrocni_smlouvy = datum_uzavreni + trvani let
    // ────────────────────────────────────────────────────────────────
    public function postClose(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['backoffice', 'majitel', 'superadmin']);
        $this->ensureWorkflowMigration();

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/bo');
        }

        $bouserId    = (int) $user['id'];
        $contactId   = (int) ($_POST['contact_id'] ?? 0);
        $tab         = (string) ($_POST['tab'] ?? 'v_praci');
        $note        = trim((string) ($_POST['note'] ?? ''));
        $cisloSml    = trim((string) ($_POST['cislo_smlouvy'] ?? ''));
        $datumUzav   = trim((string) ($_POST['datum_uzavreni'] ?? ''));
        $trvaniRoky  = (int) ($_POST['smlouva_trvani_roky'] ?? 3);

        $redirectBack = '/bo?tab=' . urlencode($tab) . '#c-' . $contactId;

        // ── Validace povinných polí ──
        if ($cisloSml === '') {
            crm_flash_set('⚠ Zadejte číslo smlouvy.');
            crm_redirect($redirectBack);
        }
        if (mb_strlen($cisloSml) > 50) {
            crm_flash_set('⚠ Číslo smlouvy max 50 znaků.');
            crm_redirect($redirectBack);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datumUzav) || strtotime($datumUzav) === false) {
            crm_flash_set('⚠ Zadejte platné datum uzavření (YYYY-MM-DD).');
            crm_redirect($redirectBack);
        }
        $datumTs = strtotime($datumUzav);
        if ($datumTs > time()) {
            crm_flash_set('⚠ Datum uzavření nemůže být v budoucnosti.');
            crm_redirect($redirectBack);
        }
        if ($datumTs < strtotime('-2 years')) {
            crm_flash_set('⚠ Datum uzavření je staré více než 2 roky — opravte prosím.');
            crm_redirect($redirectBack);
        }
        if ($trvaniRoky < 1 || $trvaniRoky > 10) {
            $trvaniRoky = 3;
        }

        // Anti-duplicita: stejné číslo smlouvy nesmí existovat na jiném kontaktu
        $dupStmt = $this->pdo->prepare(
            "SELECT contact_id FROM oz_contact_workflow
             WHERE cislo_smlouvy = :cn AND contact_id <> :cid LIMIT 1"
        );
        $dupStmt->execute(['cn' => $cisloSml, 'cid' => $contactId]);
        if ($dupStmt->fetch(PDO::FETCH_ASSOC)) {
            crm_flash_set('⚠ Číslo smlouvy „' . $cisloSml . '" již existuje u jiného kontaktu.');
            crm_redirect($redirectBack);
        }

        // ── Ověřit, že kontakt je v BO ke zpracování + zkontrolovat checkbox "Podpis potvrzen" ──
        $stmt = $this->pdo->prepare(
            "SELECT w.contact_id, w.stav, COALESCE(w.podpis_potvrzen, 0) AS podpis
             FROM oz_contact_workflow w
             WHERE w.contact_id = :cid AND w.stav IN ('BO_PREDANO','BO_VPRACI','SMLOUVA')
             LIMIT 1"
        );
        $stmt->execute(['cid' => $contactId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            crm_flash_set('⚠ Kontakt nelze uzavřít (není v BO).');
            crm_redirect('/bo?tab=' . urlencode($tab));
        }
        // Blokace: nelze uzavřít smlouvu bez potvrzení podpisu
        if ((int) $row['podpis'] !== 1) {
            crm_flash_set('⚠ Nelze uzavřít — nejprve zaškrtněte checkbox „Podpis potvrzen".');
            crm_redirect($redirectBack);
        }

        // ── Uzavřít workflow + uložit detaily smlouvy ──
        $oldStav = (string) ($row['stav'] ?? 'BO_VPRACI');
        $this->pdo->prepare(
            "UPDATE oz_contact_workflow
             SET stav_changed_at      = CASE WHEN stav <> 'UZAVRENO' THEN NOW(3) ELSE stav_changed_at END,
                 stav                 = 'UZAVRENO',
                 cislo_smlouvy        = :cn,
                 datum_uzavreni       = :du,
                 smlouva_trvani_roky  = :tr,
                 closed_at            = NOW(3),
                 updated_at           = NOW(3)
             WHERE contact_id = :cid"
        )->execute([
            'cn'  => $cisloSml,
            'du'  => $datumUzav,
            'tr'  => $trvaniRoky,
            'cid' => $contactId,
        ]);

        // Audit log — uzavření smlouvy
        crm_log_workflow_change($this->pdo, $contactId, $bouserId, $oldStav, 'UZAVRENO',
            sprintf('Smlouva uzavřena · č. %s · podpis %s · trvání %d let%s',
                $cisloSml, $datumUzav, $trvaniRoky, $note !== '' ? ' · ' . $note : ''));

        // ── Auto-set vyrocni_smlouvy v contacts (datum_uzavreni + trvani let) ──
        // Použito pro renewals widget v OZ + admin dashboard
        $this->pdo->prepare(
            "UPDATE contacts
             SET vyrocni_smlouvy = DATE_ADD(:du, INTERVAL :tr YEAR),
                 updated_at      = NOW(3)
             WHERE id = :cid"
        )->execute([
            'du'  => $datumUzav,
            'tr'  => $trvaniRoky,
            'cid' => $contactId,
        ]);

        // ── Záznam do sdíleného deníku ──
        $logText = sprintf(
            '✅ Smlouva uzavřena BO · č. %s · podpis %s · trvání %d let%s',
            $cisloSml,
            $datumUzav,
            $trvaniRoky,
            $note !== '' ? ' · ' . $note : ''
        );
        $this->pdo->prepare(
            "INSERT INTO oz_contact_actions
               (contact_id, oz_id, action_date, action_text, created_at)
             VALUES (:cid, :uid, CURDATE(), :txt, NOW(3))"
        )->execute([
            'cid' => $contactId,
            'uid' => $bouserId,
            'txt' => mb_substr($logText, 0, 1000),
        ]);

        crm_flash_set('✓ Smlouva uzavřena · č. ' . $cisloSml);
        crm_redirect('/bo?tab=' . urlencode($tab));
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /bo/action/add  –  BO přidá záznam do Pracovního deníku
    // ────────────────────────────────────────────────────────────────
    public function postActionAdd(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['backoffice', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/bo');
        }

        $bouserId   = (int) $user['id'];
        $contactId  = (int) ($_POST['contact_id'] ?? 0);
        $tab        = (string) ($_POST['tab'] ?? 'v_praci');
        $actionDate = trim((string) ($_POST['action_date'] ?? ''));
        $actionText = trim((string) ($_POST['action_text'] ?? ''));

        // Validace, že kontakt je v BO workspace stavu
        $stmt = $this->pdo->prepare(
            "SELECT w.contact_id
             FROM oz_contact_workflow w
             WHERE w.contact_id = :cid AND w.stav IN ('BO_PREDANO','BO_VPRACI','BO_VRACENO','UZAVRENO','SMLOUVA')
             LIMIT 1"
        );
        $stmt->execute(['cid' => $contactId]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            crm_flash_set('⚠ Kontakt nenalezen.');
            crm_redirect('/bo?tab=' . urlencode($tab));
        }

        // Validace data
        if ($actionDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $actionDate)) {
            $actionDate = date('Y-m-d');
        }
        if (strtotime($actionDate) === false) {
            $actionDate = date('Y-m-d');
        }

        if ($actionText === '') {
            crm_flash_set('⚠ Zadejte popis úkonu.');
            crm_redirect('/bo?tab=' . urlencode($tab) . '#c-' . $contactId);
        }
        if (mb_strlen($actionText) > 1000) {
            $actionText = mb_substr($actionText, 0, 1000);
        }

        $this->pdo->prepare(
            "INSERT INTO oz_contact_actions
               (contact_id, oz_id, action_date, action_text, created_at)
             VALUES (:cid, :uid, :dt, :txt, NOW(3))"
        )->execute([
            'cid' => $contactId,
            'uid' => $bouserId,
            'dt'  => $actionDate,
            'txt' => $actionText,
        ]);

        crm_flash_set('✓ Úkon zaznamenán.');
        crm_redirect('/bo?tab=' . urlencode($tab) . '#c-' . $contactId);
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /bo/action/delete  –  BO smaže svůj záznam z deníku
    // ────────────────────────────────────────────────────────────────
    public function postActionDelete(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['backoffice', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/bo');
        }

        $bouserId = (int) $user['id'];
        $actionId = (int) ($_POST['action_id'] ?? 0);
        $tab      = (string) ($_POST['tab'] ?? 'v_praci');

        // Smazat lze JEN svůj záznam (autor = aktuální uživatel)
        $stmt = $this->pdo->prepare(
            "SELECT a.contact_id
             FROM oz_contact_actions a
             WHERE a.id = :aid AND a.oz_id = :uid
             LIMIT 1"
        );
        $stmt->execute(['aid' => $actionId, 'uid' => $bouserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            crm_flash_set('⚠ Záznam nenalezen nebo nelze smazat cizí.');
            crm_redirect('/bo?tab=' . urlencode($tab));
        }
        $contactId = (int) $row['contact_id'];

        $this->pdo->prepare(
            "DELETE FROM oz_contact_actions WHERE id = :aid"
        )->execute(['aid' => $actionId]);

        crm_flash_set('🗑 Úkon smazán.');
        crm_redirect('/bo?tab=' . urlencode($tab) . '#c-' . $contactId);
    }

    // ────────────────────────────────────────────────────────────────
    //  Idempotentní migrace nových sloupců do oz_contact_workflow.
    //  Duplikát logiky z OzController::ensureWorkflowTable() — BO může otevřít
    //  /bo jako první uživatel a sloupce ještě nemusí existovat.
    // ────────────────────────────────────────────────────────────────
    private function ensureWorkflowMigration(): void
    {
        try { $this->pdo->exec('ALTER TABLE `oz_contact_workflow` ADD COLUMN `stav_changed_at` DATETIME(3) NULL DEFAULT NULL'); } catch (\PDOException $e) { crm_db_log_error($e, __METHOD__); }
        try { $this->pdo->exec('ALTER TABLE `oz_contact_workflow` ADD COLUMN `priprava_smlouvy` TINYINT(1) NOT NULL DEFAULT 0'); } catch (\PDOException $e) { crm_db_log_error($e, __METHOD__); }
        try { $this->pdo->exec('ALTER TABLE `oz_contact_workflow` ADD COLUMN `datovka_odeslana` TINYINT(1) NOT NULL DEFAULT 0'); } catch (\PDOException $e) { crm_db_log_error($e, __METHOD__); }
        try { $this->pdo->exec('ALTER TABLE `oz_contact_workflow` ADD COLUMN `podpis_potvrzen` TINYINT(1) NOT NULL DEFAULT 0'); } catch (\PDOException $e) { crm_db_log_error($e, __METHOD__); }
        try { $this->pdo->exec('ALTER TABLE `oz_contact_workflow` ADD COLUMN `podpis_potvrzen_at` DATETIME(3) NULL DEFAULT NULL'); } catch (\PDOException $e) { crm_db_log_error($e, __METHOD__); }
        try { $this->pdo->exec('ALTER TABLE `oz_contact_workflow` ADD COLUMN `podpis_potvrzen_by` INT UNSIGNED NULL DEFAULT NULL'); } catch (\PDOException $e) { crm_db_log_error($e, __METHOD__); }
        try { $this->pdo->exec('ALTER TABLE `oz_contact_workflow` ADD COLUMN `ubotem_zpracovano` TINYINT(1) NOT NULL DEFAULT 0'); } catch (\PDOException $e) { crm_db_log_error($e, __METHOD__); }
        // Sloupce pro uzavření smlouvy (číslo, skutečné datum podpisu, trvání)
        try { $this->pdo->exec('ALTER TABLE `oz_contact_workflow` ADD COLUMN `cislo_smlouvy` VARCHAR(50) NULL DEFAULT NULL'); } catch (\PDOException $e) { crm_db_log_error($e, __METHOD__); }
        try { $this->pdo->exec('ALTER TABLE `oz_contact_workflow` ADD COLUMN `datum_uzavreni` DATE NULL DEFAULT NULL'); } catch (\PDOException $e) { crm_db_log_error($e, __METHOD__); }
        try { $this->pdo->exec('ALTER TABLE `oz_contact_workflow` ADD COLUMN `smlouva_trvani_roky` TINYINT UNSIGNED NULL DEFAULT 3'); } catch (\PDOException $e) { crm_db_log_error($e, __METHOD__); }
        try { $this->pdo->exec('ALTER TABLE `oz_contact_workflow` ADD INDEX `idx_cislo_smlouvy` (`cislo_smlouvy`)'); } catch (\PDOException $e) { crm_db_log_error($e, __METHOD__); }
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /bo/checkbox-toggle  –  BO přepne libovolný progress checkbox
    //  POST: contact_id, field, checked, tab
    //  Pole: priprava_smlouvy, datovka_odeslana, podpis_potvrzen, ubotem_zpracovano
    // ────────────────────────────────────────────────────────────────
    public function postCheckboxToggle(): void
    {
        $isAjax = (str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json'))
               || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['backoffice', 'majitel', 'superadmin']);

        $jsonError = function (string $msg) use ($isAjax): never {
            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
                exit;
            }
            crm_flash_set($msg);
            crm_redirect('/bo');
        };

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            $jsonError('Neplatný CSRF token.');
        }

        $bouserId  = (int) $user['id'];
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        $tab       = (string) ($_POST['tab'] ?? 'k_priprave');
        $field     = (string) ($_POST['field'] ?? '');
        $checked   = !empty($_POST['checked']);

        $allowed = ['priprava_smlouvy','datovka_odeslana','podpis_potvrzen','ubotem_zpracovano'];
        if (!in_array($field, $allowed, true)) {
            $jsonError('⚠ Neznámý checkbox.');
        }

        // Ověřit existenci workflow řádku ve stavu kde má smysl progress
        $check = $this->pdo->prepare(
            "SELECT w.id, w.stav
             FROM oz_contact_workflow w
             WHERE w.contact_id = :cid AND w.stav IN ('BO_PREDANO','BO_VPRACI','BO_VRACENO','SMLOUVA','UZAVRENO')
             LIMIT 1"
        );
        $check->execute(['cid' => $contactId]);
        if (!$check->fetch(PDO::FETCH_ASSOC)) {
            $jsonError('⚠ Kontakt není v BO ke zpracování.');
        }

        // Speciální handling pro podpis_potvrzen (timestamp + autor)
        if ($field === 'podpis_potvrzen') {
            if ($checked) {
                $this->pdo->prepare(
                    "UPDATE oz_contact_workflow
                     SET podpis_potvrzen    = 1,
                         podpis_potvrzen_at = NOW(3),
                         podpis_potvrzen_by = :uid,
                         updated_at         = NOW(3)
                     WHERE contact_id = :cid"
                )->execute(['uid' => $bouserId, 'cid' => $contactId]);
            } else {
                $this->pdo->prepare(
                    "UPDATE oz_contact_workflow
                     SET podpis_potvrzen    = 0,
                         podpis_potvrzen_at = NULL,
                         podpis_potvrzen_by = NULL,
                         updated_at         = NOW(3)
                     WHERE contact_id = :cid"
                )->execute(['cid' => $contactId]);
            }
        } else {
            // Ostatní 3 sloupce — prosté toggle
            $this->pdo->prepare(
                "UPDATE oz_contact_workflow
                 SET `$field` = :val, updated_at = NOW(3)
                 WHERE contact_id = :cid"
            )->execute(['val' => $checked ? 1 : 0, 'cid' => $contactId]);
        }

        if ($isAjax) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => true, 'field' => $field, 'checked' => $checked ? 1 : 0], JSON_UNESCAPED_UNICODE);
            exit;
        }
        crm_flash_set($checked ? '✓ Zaškrtnuto.' : 'Zrušeno.');
        crm_redirect('/bo?tab=' . urlencode($tab) . '#c-' . $contactId);
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /bo/nezajem  –  BO označí kontakt jako nezájem (zákazník odmítl)
    //  Povinný důvod, zápis do Pracovního deníku, stav → NEZAJEM.
    //  Kontakt zůstává přiřazen svému OZ — zobrazí se v jeho Nezájem tabu.
    // ────────────────────────────────────────────────────────────────
    public function postNezajem(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['backoffice', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/bo');
        }

        $bouserId  = (int) $user['id'];
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        $tab       = (string) ($_POST['tab'] ?? 'k_priprave');
        $reason    = trim((string) ($_POST['reason'] ?? ''));

        if ($reason === '') {
            crm_flash_set('⚠ Důvod nezájmu je povinný.');
            crm_redirect('/bo?tab=' . urlencode($tab) . '#c-' . $contactId);
        }
        if (mb_strlen($reason) > 1000) { $reason = mb_substr($reason, 0, 1000); }

        $stmt = $this->pdo->prepare(
            "SELECT w.contact_id, w.stav
             FROM oz_contact_workflow w
             WHERE w.contact_id = :cid AND w.stav IN ('BO_PREDANO','BO_VPRACI','BO_VRACENO','SMLOUVA')
             LIMIT 1"
        );
        $stmt->execute(['cid' => $contactId]);
        $oldRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$oldRow) {
            crm_flash_set('⚠ Kontakt nelze označit jako nezájem (není v BO).');
            crm_redirect('/bo?tab=' . urlencode($tab));
        }
        $oldStav = (string) $oldRow['stav'];

        $this->pdo->prepare(
            "UPDATE oz_contact_workflow
             SET stav_changed_at = CASE WHEN stav <> 'NEZAJEM' THEN NOW(3) ELSE stav_changed_at END,
                 stav = 'NEZAJEM',
                 closed_at = NULL,
                 updated_at = NOW(3)
             WHERE contact_id = :cid"
        )->execute(['cid' => $contactId]);

        // Audit log
        crm_log_workflow_change($this->pdo, $contactId, $bouserId, $oldStav, 'NEZAJEM',
            'Nezájem (BO): ' . $reason);

        $this->pdo->prepare(
            "INSERT INTO oz_contact_actions
               (contact_id, oz_id, action_date, action_text, created_at)
             VALUES (:cid, :uid, CURDATE(), :txt, NOW(3))"
        )->execute([
            'cid' => $contactId,
            'uid' => $bouserId,
            'txt' => '✗ Nezájem (BO): ' . $reason,
        ]);

        crm_flash_set('✓ Kontakt označen jako nezájem — vrácen OZ do tabu Nezájem.');
        crm_redirect('/bo?tab=' . urlencode($tab));
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /bo/contact/edit  –  BO upraví údaje kontaktu
    //  (firma, telefon, email, IČO, adresa — NE region/operator)
    //
    //  UX-shodné s OZ::postContactEdit. Rozdíl:
    //    • role check: backoffice/majitel/superadmin
    //    • ověření vlastnictví: kontakt musí mít workflow řádek
    //      ve stavech, které BO řeší (BO_PREDANO/BO_VPRACI/BO_VRACENO/
    //      SMLOUVA/UZAVRENO) — aby BO needitoval kontakty,
    //      které mu nikdo nepředal.
    // ────────────────────────────────────────────────────────────────

    public function postContactEdit(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['backoffice', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/bo');
        }

        $contactId = (int) ($_POST['contact_id'] ?? 0);
        $tab       = (string) ($_POST['tab'] ?? 'k_priprave');
        $firma     = trim((string) ($_POST['firma'] ?? ''));
        $telefon   = trim((string) ($_POST['telefon'] ?? ''));
        $email     = trim((string) ($_POST['email'] ?? ''));
        $ico       = trim((string) ($_POST['ico'] ?? ''));
        $adresa    = trim((string) ($_POST['adresa'] ?? ''));

        // Kontakt musí být v některém BO stavu, jinak BO nemá důvod ho editovat.
        $check = $this->pdo->prepare(
            "SELECT 1 FROM oz_contact_workflow
             WHERE contact_id = :cid
               AND stav IN ('BO_PREDANO','BO_VPRACI','BO_VRACENO','SMLOUVA','UZAVRENO')
             LIMIT 1"
        );
        $check->execute(['cid' => $contactId]);
        if (!$check->fetchColumn()) {
            crm_flash_set('⚠ Kontakt nenalezen, nebo není ve stavu pro úpravu BO-em.');
            crm_redirect('/bo?tab=' . urlencode($tab));
        }

        // Validace — stejná pravidla jako u OZ::postContactEdit.
        if ($firma === '') {
            crm_flash_set('⚠ Název firmy nemůže být prázdný.');
            crm_redirect('/bo?tab=' . urlencode($tab) . '#c-' . $contactId);
        }
        if (mb_strlen($firma)   > 200) { $firma   = mb_substr($firma,   0, 200); }
        if (mb_strlen($telefon) > 50)  { $telefon = mb_substr($telefon, 0, 50);  }
        if (mb_strlen($email)   > 200) { $email   = mb_substr($email,   0, 200); }
        if (mb_strlen($adresa)  > 300) { $adresa  = mb_substr($adresa,  0, 300); }
        $ico = crm_normalize_ico($ico);
        if (mb_strlen($ico)     > 20)  { $ico     = mb_substr($ico,     0, 20);  }

        $this->pdo->prepare(
            "UPDATE contacts
             SET firma      = :firma,
                 telefon    = :telefon,
                 email      = :email,
                 ico        = :ico,
                 adresa     = :adresa,
                 updated_at = NOW(3)
             WHERE id = :cid"
        )->execute([
            'firma'   => $firma,
            'telefon' => $telefon === '' ? null : $telefon,
            'email'   => $email   === '' ? null : $email,
            'ico'     => $ico     === '' ? null : $ico,
            'adresa'  => $adresa  === '' ? null : $adresa,
            'cid'     => $contactId,
        ]);

        crm_flash_set('✓ Údaje kontaktu uloženy.');
        crm_redirect('/bo?tab=' . urlencode($tab) . '#c-' . $contactId);
    }
}
