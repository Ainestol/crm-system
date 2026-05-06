<?php
// e:\Snecinatripu\app\controllers\OzController.php
declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'audit.php';

/**
 * Obchodní zástupce (OZ):
 *  - Pracovní plocha s navolanými leady (/oz/leads)
 *  - Dashboard kvót a statistik (/oz)
 *  - Reklamace špatně navolaných kontaktů (/oz/flag)
 *  - Šněčí závody – výhry po podpisu smlouvy (/oz/race.json)
 *  - Potvrzení schůzky (/oz/acknowledge-meeting)
 */
final class OzController
{
    public function __construct(private PDO $pdo)
    {
    }

    // ────────────────────────────────────────────────────────────────
    //  GET /oz/leads  –  Hlavní pracovní plocha
    // ────────────────────────────────────────────────────────────────

    public function getLeads(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        $ozId  = (int) $user['id'];
        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();
        $tab   = (string) ($_GET['tab'] ?? 'nove');

        $this->ensureWorkflowTable();
        $this->ensureFlagsTable();
        $this->ensureNotesTable();
        $this->ensureOfferedServicesTables();
        $this->ensureActionsTable();
        $this->ensureTabPrefsTable();

        // Skryté taby OZ — per-user prefs (default: nic skrytého)
        $hiddenTabs = $this->getHiddenTabs($ozId);
        // Aktivní tab nelze skrýt — kdyby byl ve skrytých, dočasně ho odeber
        $hiddenTabs = array_values(array_filter($hiddenTabs, static fn($t) => $t !== $tab));
        // Per-user pořadí tabů (default: prázdné = standardní)
        $tabOrder    = $this->getTabOrder($ozId);
        // Per-user pořadí sub-tabů uvnitř super-tabů (Plán, BO)
        $subTabOrder = $this->getSubTabOrder($ozId);

        // Tab "smlouva" + "obvolano" zrušeny — stavy se přelévají:
        //   'nove'        tab teď zobrazuje NOVE + OBVOLANO + ZPRACOVAVA (workflow stavy odpracované)
        //   'bo_predano'  Předáno BO (SMLOUVA + BO_PREDANO + BO_VPRACI) — sub-tab BO super-tabu
        //   'bo_vraceno'  Vráceno z BO (BO_VRACENO) — sub-tab BO super-tabu
        //   'dokonceno'   UZAVRENO (BO finalizoval) — sub-tab BO super-tabu, zelený, s filtrem měsíců
        //   Legacy 'bo'   ponecháno jako alias (mapuje se na 'bo_predano').
        $validTabs = ['nove', 'nabidka', 'schuzka', 'callback', 'sance', 'bo', 'bo_predano', 'bo_vraceno', 'dokonceno', 'nezajem', 'reklamace'];
        $tab = in_array($tab, $validTabs, true) ? $tab : 'nove';
        // Backward compat: starý 'bo' tab → 'bo_predano' (default sub-tab BO super-tabu)
        if ($tab === 'bo') { $tab = 'bo_predano'; }

        // Měsíční filtr pro tab "dokonceno" (statistika podpisů)
        $doneYear  = (int) ($_GET['y'] ?? date('Y'));
        $doneMonth = (int) ($_GET['m'] ?? date('n'));
        if ($doneMonth < 1 || $doneMonth > 12) { $doneMonth = (int) date('n'); }
        if ($doneYear < 2000 || $doneYear > 2100) { $doneYear = (int) date('Y'); }

        $tabWhere = match ($tab) {
            'nabidka'    => "w.stav = 'NABIDKA'",
            'schuzka'    => "w.stav = 'SCHUZKA'",
            'callback'   => "w.stav = 'CALLBACK'",
            'sance'      => "w.stav = 'SANCE'",
            'bo_predano' => "w.stav IN ('SMLOUVA','BO_PREDANO','BO_VPRACI')",
            'bo_vraceno' => "w.stav = 'BO_VRACENO'",
            'dokonceno'  => "w.stav = 'UZAVRENO'
                            AND YEAR(COALESCE(w.closed_at, w.updated_at))  = " . $doneYear . "
                            AND MONTH(COALESCE(w.closed_at, w.updated_at)) = " . $doneMonth,
            'nezajem'    => "w.stav IN ('NEZAJEM','NERELEVANTNI')",
            'reklamace'  => "w.stav = 'REKLAMACE'",
            // Tab "Nové" — jen kontakty které OZ aktivně přijal (workflow řádek existuje).
            // Pending leady (w.stav IS NULL) zůstávají JEN v levém sidebaru,
            // dokud OZ neklikne "Přijmout" nebo "Přijmout vše".
            default      => "w.stav IN ('NOVE','OBVOLANO','ZPRACOVAVA')",
        };

        // Stabilní řazení — karty po update poznámky/stavu neskáčou nahoru.
        // Default: podle started_at (kdy OZ převzal) nebo c.created_at jako fallback.
        $orderBy = match ($tab) {
            'callback'  => 'ORDER BY w.callback_at IS NULL, w.callback_at ASC, c.id ASC',
            'schuzka'   => 'ORDER BY w.schuzka_at ASC, c.id ASC',
            'dokonceno' => 'ORDER BY COALESCE(w.closed_at, w.updated_at) DESC, c.id DESC',
            default     => 'ORDER BY COALESCE(w.started_at, c.created_at) ASC, c.id ASC',
        };

        // ── Hlavní dotaz kontaktů ─────────────────────────────────────
        $sql = "SELECT c.id, c.firma, c.telefon, c.email, c.ico, c.adresa, c.region,
                       c.poznamka AS caller_poznamka, c.datum_volani, c.operator,
                       COALESCE(cu.jmeno, '—')           AS caller_name,
                       COALESCE(w.stav, 'NOVE')           AS oz_stav,
                       w.started_at,
                       w.stav_changed_at                  AS oz_stav_changed_at,
                       COALESCE(w.poznamka, '')           AS oz_poznamka,
                       w.callback_at                      AS oz_callback_at,
                       w.schuzka_at                       AS oz_schuzka_at,
                       COALESCE(w.schuzka_acknowledged,0) AS oz_schuzka_ack,
                       w.updated_at                       AS oz_updated_at,
                       w.bmsl                             AS oz_bmsl,
                       w.smlouva_date                     AS oz_smlouva_date,
                       w.nabidka_id                       AS oz_nabidka_id,
                       COALESCE(w.priprava_smlouvy,0)     AS cb_priprava,
                       COALESCE(w.datovka_odeslana,0)     AS cb_datovka,
                       COALESCE(w.podpis_potvrzen,0)      AS cb_podpis,
                       w.podpis_potvrzen_at               AS cb_podpis_at,
                       w.podpis_potvrzen_by               AS cb_podpis_by,
                       COALESCE(w.ubotem_zpracovano,0)    AS cb_ubotem,
                       COALESCE(w.install_internet, 0)    AS oz_install_internet,
                       w.install_adresy                   AS oz_install_adresy,
                       CASE WHEN f.id IS NOT NULL THEN 1 ELSE 0 END AS flagged,
                       COALESCE(f.reason, '')           AS flag_reason,
                       COALESCE(f.caller_comment, '')   AS flag_caller_comment,
                       COALESCE(f.caller_confirmed, 0)  AS flag_caller_confirmed,
                       COALESCE(f.oz_comment, '')       AS flag_oz_comment,
                       COALESCE(f.oz_confirmed, 0)      AS flag_oz_confirmed
                FROM contacts c
                LEFT JOIN users cu ON cu.id = c.assigned_caller_id
                LEFT JOIN oz_contact_workflow w
                       ON w.contact_id = c.id AND w.oz_id = :ozid1
                LEFT JOIN contact_oz_flags f
                       ON f.contact_id = c.id AND f.oz_id = :ozid2
                WHERE c.assigned_sales_id = :ozid3
                  AND c.stav = 'CALLED_OK'
                  AND {$tabWhere}
                {$orderBy}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['ozid1' => $ozId, 'ozid2' => $ozId, 'ozid3' => $ozId]);
        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // ── Historie poznámek (seskupené per kontakt) ─────────────────
        $notesByContact = [];
        try {
            $nStmt = $this->pdo->prepare(
                'SELECT cn.contact_id, cn.note, cn.created_at
                 FROM oz_contact_notes cn
                 INNER JOIN contacts c ON c.id = cn.contact_id
                 WHERE cn.oz_id = :ozid AND c.assigned_sales_id = :ozid2
                 ORDER BY cn.contact_id ASC, cn.created_at ASC'
            );
            $nStmt->execute(['ozid' => $ozId, 'ozid2' => $ozId]);
            foreach ($nStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $n) {
                $notesByContact[(int) $n['contact_id']][] = $n;
            }
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }

        // ── Pracovní deník akcí (sdílený OZ + BO) ─────────────────────
        // Zobrazujeme všechny záznamy ke kontaktům, kde je tento OZ přiřazený,
        // bez ohledu na autora (autor může být OZ i BO). Autor se ukáže ve view.
        $actionsByContact = [];
        try {
            $aStmt = $this->pdo->prepare(
                "SELECT a.id, a.contact_id, a.action_date, a.action_text, a.created_at,
                        a.oz_id                   AS author_id,
                        COALESCE(u.jmeno, '—')    AS author_name,
                        COALESCE(u.role, '')      AS author_role
                 FROM oz_contact_actions a
                 INNER JOIN contacts c ON c.id = a.contact_id
                 LEFT JOIN users u ON u.id = a.oz_id
                 WHERE c.assigned_sales_id = :ozid
                 ORDER BY a.contact_id ASC, a.action_date DESC, a.created_at DESC"
            );
            $aStmt->execute(['ozid' => $ozId]);
            foreach ($aStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $a) {
                $actionsByContact[(int) $a['contact_id']][] = $a;
            }
        } catch (\PDOException) {
            // Tabulka ještě neexistuje – necháme prázdné
        }

        // ── Nabídnuté služby (Fáze 1 — read-only zobrazení) ───────────
        // Struktura: $offeredServicesByContact[contact_id][] = ['service' => [...], 'items' => [...]]
        $offeredServicesByContact = [];
        try {
            $sStmt = $this->pdo->prepare(
                "SELECT s.id, s.contact_id, s.service_type, s.service_label,
                        s.modem_label, s.price_monthly, s.note,
                        s.created_at, s.updated_at
                 FROM oz_contact_offered_services s
                 INNER JOIN contacts c ON c.id = s.contact_id
                 WHERE s.oz_id = :ozid AND c.assigned_sales_id = :ozid2
                 ORDER BY s.contact_id ASC, s.created_at ASC"
            );
            $sStmt->execute(['ozid' => $ozId, 'ozid2' => $ozId]);
            $servicesRows = $sStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $itemsByService = [];
            if ($servicesRows !== []) {
                $serviceIds = array_map(static fn($r) => (int) $r['id'], $servicesRows);
                $ph = implode(',', array_fill(0, count($serviceIds), '?'));
                $iStmt = $this->pdo->prepare(
                    "SELECT id, service_id, identifier, oku_code, oku_filled_at
                     FROM oz_contact_offered_service_items
                     WHERE service_id IN ($ph)
                     ORDER BY service_id ASC, created_at ASC"
                );
                $iStmt->execute($serviceIds);
                foreach ($iStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $item) {
                    $itemsByService[(int) $item['service_id']][] = $item;
                }
            }

            foreach ($servicesRows as $s) {
                $cId = (int) $s['contact_id'];
                $sId = (int) $s['id'];
                $offeredServicesByContact[$cId][] = [
                    'service' => $s,
                    'items'   => $itemsByService[$sId] ?? [],
                ];
            }
        } catch (\PDOException) {
            // Tabulky ještě neexistují (první load) — necháme prázdné
        }

        // ── Notifikace nadcházejících schůzek (neodkliknuté) ──────────
        $meetingNotifications = [];
        try {
            $meetStmt = $this->pdo->prepare(
                "SELECT c.id, c.firma, w.schuzka_at
                 FROM contacts c
                 JOIN oz_contact_workflow w ON w.contact_id = c.id AND w.oz_id = :ozid
                 WHERE c.assigned_sales_id = :ozid2
                   AND w.stav = 'SCHUZKA'
                   AND COALESCE(w.schuzka_acknowledged, 0) = 0
                   AND w.schuzka_at BETWEEN NOW() - INTERVAL 2 HOUR AND NOW() + INTERVAL 26 HOUR
                 ORDER BY w.schuzka_at ASC"
            );
            $meetStmt->execute(['ozid' => $ozId, 'ozid2' => $ozId]);
            $meetingNotifications = $meetStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }

        // ── Počty pro taby ────────────────────────────────────────────
        // Sub-taby BO super-tabu:
        //   bo_predano  = SMLOUVA + BO_PREDANO + BO_VPRACI (u BO ke zpracování)
        //   bo_vraceno  = BO_VRACENO (BO vrátil, OZ má doplnit)
        //   dokonceno   = UZAVRENO (s filtrem měsíce)
        // Sub-taby Plán super-tabu: callback, schuzka.
        // Virtuální agregáty 'bo' a 'plan' jsou dopočítané pod selectem.
        $countStmt = $this->pdo->prepare(
            "SELECT
                -- Tab Nové: jen aktivně přijaté (workflow stav NOVE/OBVOLANO/ZPRACOVAVA).
                -- Pending leady (bez workflow řádku) jsou v levém sidebaru, ne tady.
                SUM(CASE WHEN w.stav IN ('NOVE','OBVOLANO','ZPRACOVAVA')                     THEN 1 ELSE 0 END) AS nove,
                SUM(CASE WHEN w.stav = 'NABIDKA'                                             THEN 1 ELSE 0 END) AS nabidka,
                SUM(CASE WHEN w.stav = 'SCHUZKA'                                             THEN 1 ELSE 0 END) AS schuzka,
                SUM(CASE WHEN w.stav = 'CALLBACK'                                            THEN 1 ELSE 0 END) AS callback,
                SUM(CASE WHEN w.stav = 'SANCE'                                               THEN 1 ELSE 0 END) AS sance,
                SUM(CASE WHEN w.stav IN ('SMLOUVA','BO_PREDANO','BO_VPRACI')                 THEN 1 ELSE 0 END) AS bo_predano,
                SUM(CASE WHEN w.stav = 'BO_VRACENO'                                          THEN 1 ELSE 0 END) AS bo_vraceno,
                SUM(CASE WHEN w.stav = 'UZAVRENO'
                          AND YEAR(COALESCE(w.closed_at, w.updated_at))  = :doneY
                          AND MONTH(COALESCE(w.closed_at, w.updated_at)) = :doneM                THEN 1 ELSE 0 END) AS dokonceno,
                SUM(CASE WHEN w.stav IN ('NEZAJEM','NERELEVANTNI')                           THEN 1 ELSE 0 END) AS nezajem,
                SUM(CASE WHEN w.stav = 'REKLAMACE'                                           THEN 1 ELSE 0 END) AS reklamace
             FROM contacts c
             LEFT JOIN oz_contact_workflow w ON w.contact_id = c.id AND w.oz_id = :ozid
             WHERE c.assigned_sales_id = :ozid2 AND c.stav = 'CALLED_OK'"
        );
        $countStmt->execute(['ozid' => $ozId, 'ozid2' => $ozId, 'doneY' => $doneYear, 'doneM' => $doneMonth]);
        $tabCounts = $countStmt->fetch(PDO::FETCH_ASSOC)
            ?: ['nove' => 0, 'nabidka' => 0, 'schuzka' => 0, 'callback' => 0, 'sance' => 0,
                'bo_predano' => 0, 'bo_vraceno' => 0, 'dokonceno' => 0, 'nezajem' => 0, 'reklamace' => 0];

        // Virtuální agregáty pro super-taby (badge na parent zobrazí součet dětí)
        $tabCounts['bo']   = (int) ($tabCounts['bo_predano'] ?? 0)
                           + (int) ($tabCounts['bo_vraceno'] ?? 0)
                           + (int) ($tabCounts['dokonceno']  ?? 0);
        $tabCounts['plan'] = (int) ($tabCounts['callback']   ?? 0)
                           + (int) ($tabCounts['schuzka']    ?? 0);

        // ── Výhry a BMSL tento měsíc ──────────────────────────────────
        // Počítají se kontakty se zaškrtnutým "Podpis potvrzen" v tomto měsíci.
        // Legacy fallback: stav = 'SMLOUVA' bez podpis_potvrzen — historie před touto změnou.
        $curYear  = (int) date('Y');
        $curMonth = (int) date('n');
        $winsStmt = $this->pdo->prepare(
            "SELECT COUNT(*), COALESCE(SUM(bmsl), 0)
             FROM oz_contact_workflow
             WHERE oz_id = :ozid
               AND (
                 (podpis_potvrzen = 1
                  AND YEAR(podpis_potvrzen_at)  = :y
                  AND MONTH(podpis_potvrzen_at) = :m)
                 OR
                 (podpis_potvrzen = 0 AND stav = 'SMLOUVA'
                  AND YEAR(updated_at) = :y2 AND MONTH(updated_at) = :m2)
               )"
        );
        $winsStmt->execute(['ozid' => $ozId, 'y' => $curYear, 'm' => $curMonth, 'y2' => $curYear, 'm2' => $curMonth]);
        [$monthWins, $monthBmsl] = $winsStmt->fetch(PDO::FETCH_NUM) ?: [0, 0];
        $monthWins = (int) $monthWins;
        $monthBmsl = (int) $monthBmsl;

        // ── Tým celkem tento měsíc ────────────────────────────────────
        $teamStats     = ['contracts' => 0, 'bmsl' => 0];
        $teamStages    = [];
        try {
            $tStmt = $this->pdo->prepare(
                "SELECT COUNT(w.id) AS contracts, COALESCE(SUM(w.bmsl), 0) AS bmsl
                 FROM oz_contact_workflow w
                 INNER JOIN users u ON u.id = w.oz_id AND u.role = 'obchodak' AND u.aktivni = 1
                 WHERE (
                   (w.podpis_potvrzen = 1
                    AND YEAR(w.podpis_potvrzen_at)  = :y
                    AND MONTH(w.podpis_potvrzen_at) = :m)
                   OR
                   (w.podpis_potvrzen = 0 AND w.stav = 'SMLOUVA'
                    AND YEAR(w.updated_at) = :y2 AND MONTH(w.updated_at) = :m2)
                 )"
            );
            $tStmt->execute(['y' => $curYear, 'm' => $curMonth, 'y2' => $curYear, 'm2' => $curMonth]);
            $teamStats = $tStmt->fetch(PDO::FETCH_ASSOC) ?: $teamStats;

            $this->ensureStagesTable();
            $sgStmt = $this->pdo->prepare(
                'SELECT stage_number, label, target_bmsl
                 FROM oz_team_stages
                 WHERE year = :y AND month = :m
                 ORDER BY target_bmsl ASC'
            );
            $sgStmt->execute(['y' => $curYear, 'm' => $curMonth]);
            $teamStages = $sgStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }

        // ── Osobní milníky OZ ─────────────────────────────────────────
        $personalMilestones = [];
        try {
            $this->ensurePersonalMilestonesTable();
            $pmStmt = $this->pdo->prepare(
                'SELECT id, label, target_bmsl, reward_note
                 FROM oz_personal_milestones
                 WHERE oz_id = :ozid AND year = :y AND month = :m
                 ORDER BY target_bmsl ASC'
            );
            $pmStmt->execute(['ozid' => $ozId, 'y' => $curYear, 'm' => $curMonth]);
            $personalMilestones = $pmStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }

        // ── Čekající leady (bez workflow záznamu = ještě nepřijaté OZ) ──
        $pendingByCaller = [];
        try {
            $pStmt = $this->pdo->prepare(
                "SELECT c.id, c.firma, c.region, c.datum_volani,
                        COALESCE(cu.jmeno, '—')              AS caller_name,
                        COALESCE(c.assigned_caller_id, 0)    AS caller_id
                 FROM contacts c
                 LEFT JOIN users cu ON cu.id = c.assigned_caller_id
                 LEFT JOIN oz_contact_workflow w
                        ON w.contact_id = c.id AND w.oz_id = :ozid
                 WHERE c.assigned_sales_id = :ozid2
                   AND c.stav = 'CALLED_OK'
                   AND w.id IS NULL
                 ORDER BY c.datum_volani DESC"
            );
            $pStmt->execute(['ozid' => $ozId, 'ozid2' => $ozId]);
            $pendingMap = [];
            foreach ($pStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $p) {
                $key = (int) $p['caller_id'];
                if (!isset($pendingMap[$key])) {
                    $pendingMap[$key] = [
                        'caller_id'   => (int) $p['caller_id'],
                        'caller_name' => (string) $p['caller_name'],
                        'contacts'    => [],
                    ];
                }
                $pendingMap[$key]['contacts'][] = $p;
            }
            $pendingByCaller = array_values($pendingMap);
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }

        // ── BO vráceno — kontakty vrácené zpět od BO (pravý sidebar) ──
        $boReturned = [];
        try {
            $brStmt = $this->pdo->prepare(
                "SELECT c.id, c.firma, w.updated_at AS bo_vraceno_at
                 FROM contacts c
                 JOIN oz_contact_workflow w ON w.contact_id = c.id AND w.oz_id = :ozid
                 WHERE c.assigned_sales_id = :ozid2
                   AND c.stav = 'CALLED_OK'
                   AND w.stav = 'BO_VRACENO'
                 ORDER BY w.updated_at DESC"
            );
            $brStmt->execute(['ozid' => $ozId, 'ozid2' => $ozId]);
            $boReturned = $brStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }

        // ── Renewal alerts — kontakty s blížícím se koncem smlouvy (zelený stack vlevo dole) ──
        // Bere kontakty:
        //   - patřící tomuto OZ (assigned_sales_id)
        //   - s vyplněným vyrocni_smlouvy (DATE)
        //   - vyrocni_smlouvy v rozmezí dnes až dnes+180 dní
        // Setříděno ASC (nejbližší expirace první). UI zvýrazní per-položku podle urgency.
        $renewalsForOz = [];
        try {
            $rnStmt = $this->pdo->prepare(
                "SELECT id, firma, vyrocni_smlouvy,
                        DATEDIFF(vyrocni_smlouvy, CURDATE()) AS days_until
                 FROM contacts
                 WHERE assigned_sales_id = :ozid
                   AND vyrocni_smlouvy IS NOT NULL
                   AND vyrocni_smlouvy >= CURDATE()
                   AND vyrocni_smlouvy <= CURDATE() + INTERVAL 180 DAY
                 ORDER BY vyrocni_smlouvy ASC
                 LIMIT 100"
            );
            $rnStmt->execute(['ozid' => $ozId]);
            $renewalsForOz = $rnStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }

        $title = 'Pracovní plocha';
        ob_start();
        require dirname(__DIR__) . '/views/oz/leads.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /oz/lead-status  –  Aktualizace stavu + uložení poznámky
    // ────────────────────────────────────────────────────────────────

    public function postLeadStatus(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/oz/leads');
        }

        $this->ensureWorkflowTable();
        $this->ensureNotesTable();

        $ozId       = (int) $user['id'];
        $contactId  = (int) ($_POST['contact_id'] ?? 0);
        $newStav    = (string) ($_POST['oz_stav'] ?? '');
        $poznamka   = trim((string) ($_POST['oz_poznamka'] ?? ''));
        $callbackAt = trim((string) ($_POST['callback_at'] ?? ''));
        $schuzkaAt  = trim((string) ($_POST['schuzka_at'] ?? ''));
        $tab        = in_array((string) ($_POST['tab'] ?? ''), ['nove','obvolano','nabidka','schuzka','callback','sance','bo','bo_predano','bo_vraceno','dokonceno','nezajem','reklamace'], true)
                        ? (string) ($_POST['tab'] ?? 'nove') : 'nove';
        // Backward compat: legacy 'bo' tab → 'bo_predano' v redirectu
        if ($tab === 'bo') { $tab = 'bo_predano'; }

        $allowed = ['OBVOLANO', 'NABIDKA', 'ZPRACOVAVA', 'SCHUZKA', 'CALLBACK', 'SANCE', 'SMLOUVA', 'NEZAJEM', 'NERELEVANTNI', 'NOTE_ONLY', 'BO_PREDANO'];
        if (!in_array($newStav, $allowed, true)) {
            crm_flash_set('Neplatný stav.');
            crm_redirect('/oz/leads?tab=' . $tab);
        }

        // Ověřit že kontakt patří tomuto OZ
        $cStmt = $this->pdo->prepare(
            "SELECT id, firma FROM contacts WHERE id = :cid AND assigned_sales_id = :ozid AND stav = 'CALLED_OK'"
        );
        $cStmt->execute(['cid' => $contactId, 'ozid' => $ozId]);
        $contact = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$contact) {
            crm_flash_set('Kontakt nenalezen.');
            crm_redirect('/oz/leads?tab=' . $tab);
        }
        $firma      = (string) ($contact['firma'] ?? '');
        $firmaLabel = $firma !== '' ? ' — ' . $firma : '';

        // Poznámka je povinná pro všechny akce — VÝJIMKA: BO_VRACENO → BO_PREDANO
        // U vrácené karty z BO je hlavní kanál komunikace Pracovní deník.
        // Poznámka je tam volitelná i na frontendu (textarea má data-optional="1"),
        // proto ji nesmíme vyžadovat ani na serveru, jinak Předat BO neprojde.
        $noteOptionalReturn = false;
        try {
            $exStmt = $this->pdo->prepare(
                'SELECT stav FROM oz_contact_workflow
                 WHERE contact_id = :cid AND oz_id = :oid
                 LIMIT 1'
            );
            $exStmt->execute(['cid' => $contactId, 'oid' => $ozId]);
            $exRow = $exStmt->fetch(PDO::FETCH_ASSOC);
            $existingStav = $exRow ? (string) ($exRow['stav'] ?? '') : '';
            $noteOptionalReturn = ($existingStav === 'BO_VRACENO' && $newStav === 'BO_PREDANO');
        } catch (\PDOException) {
            // Když dotaz selže, raději vyžaduj poznámku (bezpečnější default)
        }

        if ($poznamka === '' && !$noteOptionalReturn) {
            crm_flash_set('⚠ Nejdříve vyplňte poznámku.');
            crm_redirect('/oz/leads?tab=' . $tab . '#c-' . $contactId);
        }

        // Uložit poznámku do historie (jen pokud něco je — prázdnou nezakládáme)
        if ($poznamka !== '') {
            $this->pdo->prepare(
                'INSERT INTO oz_contact_notes (contact_id, oz_id, note)
                 VALUES (:cid, :oid, :note)'
            )->execute(['cid' => $contactId, 'oid' => $ozId, 'note' => $poznamka]);
        }

        // ── NOTE_ONLY: jen poznámka, stav beze změny ─────────────────
        if ($newStav === 'NOTE_ONLY') {
            $this->pdo->prepare(
                "INSERT INTO oz_contact_workflow
                   (contact_id, oz_id, stav, started_at, poznamka, updated_at)
                 VALUES (:cid, :oid, 'ZPRACOVAVA', NOW(3), :poz, NOW(3))
                 ON DUPLICATE KEY UPDATE
                   started_at = COALESCE(started_at, NOW(3)),
                   poznamka   = :poz2,
                   updated_at = NOW(3)"
            )->execute(['cid' => $contactId, 'oid' => $ozId, 'poz' => $poznamka, 'poz2' => $poznamka]);
            crm_flash_set('✓ Poznámka uložena' . $firmaLabel . '.');
            crm_redirect('/oz/leads?tab=' . $tab . '#c-' . $contactId);
        }

        // ── Předat BO: ID nabídky + BMSL musí být v DB (jinak odmítnout) ──
        // Frontend vyžaduje obě pole přes Předat BO dialog (postSetOfferId);
        // tato větev je server-side pojistka pro případ, že někdo obejde UI.
        if ($newStav === 'BO_PREDANO') {
            $wfStmt = $this->pdo->prepare(
                "SELECT bmsl, nabidka_id FROM oz_contact_workflow
                 WHERE contact_id = :cid AND oz_id = :oid LIMIT 1"
            );
            $wfStmt->execute(['cid' => $contactId, 'oid' => $ozId]);
            $wfRow = $wfStmt->fetch(PDO::FETCH_ASSOC);
            $existingBmsl    = $wfRow ? (int) ($wfRow['bmsl'] ?? 0) : 0;
            $existingNabidka = $wfRow ? trim((string) ($wfRow['nabidka_id'] ?? '')) : '';

            if ($existingNabidka === '') {
                crm_flash_set('⚠ Pro předání BO je nutné vyplnit ID nabídky.');
                crm_redirect('/oz/leads?tab=' . $tab . '#c-' . $contactId);
            }
            if ($existingBmsl <= 0) {
                crm_flash_set('⚠ Pro předání BO je nutné vyplnit BMSL částku.');
                crm_redirect('/oz/leads?tab=' . $tab . '#c-' . $contactId);
            }
        }

        // ── Callback: datum volitelné (může být prázdné = "kdykoli později") ──
        $cbVal = null;
        if ($newStav === 'CALLBACK' && $callbackAt !== '') {
            $cbVal = $callbackAt;
        }

        // ── Schůzka: datum povinné ────────────────────────────────────
        $saVal = null;
        if ($newStav === 'SCHUZKA') {
            if ($schuzkaAt === '') {
                crm_flash_set('Zadejte datum a čas schůzky.');
                crm_redirect('/oz/leads?tab=' . $tab . '#c-' . $contactId);
            }
            $saVal = $schuzkaAt;
        }

        // ── Smlouva: BMSL + datum podpisu + ID nabídky povinné ───────
        $bmslVal     = null;
        $smlouvaDate = null;
        $nabidkaId   = null;
        if ($newStav === 'SMLOUVA') {
            $bmslRaw        = trim((string) ($_POST['bmsl'] ?? ''));
            $smlouvaDateRaw = trim((string) ($_POST['smlouva_date'] ?? ''));
            $nabidkaIdRaw   = trim((string) ($_POST['nabidka_id'] ?? ''));
            if ($bmslRaw === '' || !is_numeric($bmslRaw) || (float) $bmslRaw <= 0) {
                crm_flash_set('⚠ Zadejte BMSL částku (bez DPH, kladné číslo).');
                crm_redirect('/oz/leads?tab=' . $tab . '#c-' . $contactId);
            }
            if ($smlouvaDateRaw === '') {
                crm_flash_set('⚠ Zadejte datum podpisu smlouvy.');
                crm_redirect('/oz/leads?tab=' . $tab . '#c-' . $contactId);
            }
            if ($nabidkaIdRaw === '' || !ctype_digit($nabidkaIdRaw)) {
                crm_flash_set('⚠ Zadejte ID nabídky (pouze číslice).');
                crm_redirect('/oz/leads?tab=' . $tab . '#c-' . $contactId);
            }
            // Zaokrouhlit dolů na celé stovky (1199 → 1100, 2550 → 2500)
            $bmslVal     = (int) (floor((float) $bmslRaw / 100) * 100);
            $smlouvaDate = $smlouvaDateRaw;
            $nabidkaId   = $nabidkaIdRaw;
        }

        // ── Instalační adresy (JSON pole, povinné pokud zaškrtnuto) ──
        $installInternet = (int) isset($_POST['install_internet']);
        $installAdresyJson = null;

        if ($newStav === 'SMLOUVA' && $installInternet === 1) {
            $uliceArr = array_map('trim', (array) ($_POST['install_ulice'] ?? []));
            $mestoArr = array_map('trim', (array) ($_POST['install_mesto'] ?? []));
            $pscArr   = array_map('trim', (array) ($_POST['install_psc']   ?? []));
            $bytArr   = array_map('trim', (array) ($_POST['install_byt']   ?? []));

            $addresses = [];
            foreach ($uliceArr as $i => $ulice) {
                $mesto = $mestoArr[$i] ?? '';
                $psc   = $pscArr[$i]   ?? '';
                $byt   = $bytArr[$i]   ?? '';
                if ($ulice === '' || $mesto === '' || $psc === '') {
                    crm_flash_set('⚠ Vyplňte ulici, město a PSČ u každé instalační adresy.');
                    crm_redirect('/oz/leads?tab=' . $tab . '#c-' . $contactId);
                }
                $addresses[] = [
                    'ulice' => $ulice,
                    'mesto' => $mesto,
                    'psc'   => $psc,
                    'byt'   => $byt,
                ];
            }

            if ($addresses === []) {
                crm_flash_set('⚠ Přidejte alespoň jednu instalační adresu.');
                crm_redirect('/oz/leads?tab=' . $tab . '#c-' . $contactId);
            }

            $installAdresyJson = json_encode($addresses, JSON_UNESCAPED_UNICODE);
        }

        // ── Uložit workflow ───────────────────────────────────────────
        // POZNÁMKA: stav_changed_at se aktualizuje JEN při skutečné změně stavu
        // (porovnání starý vs nový). Musí být v UPDATE před `stav = :stav2`,
        // aby reference na `stav` ukazovala na starou hodnotu.
        $this->pdo->prepare(
            'INSERT INTO oz_contact_workflow
               (contact_id, oz_id, stav, stav_changed_at, started_at, poznamka,
                callback_at, schuzka_at, schuzka_acknowledged,
                bmsl, smlouva_date, nabidka_id,
                install_internet, install_adresy,
                updated_at)
             VALUES
               (:cid, :oid, :stav, NOW(3), NOW(3), :poz,
                :cb, :sa, 0,
                :bmsl, :sdate, :nid,
                :inst, :iadresy,
                NOW(3))
             ON DUPLICATE KEY UPDATE
               stav_changed_at      = CASE WHEN stav <> :stavNew THEN NOW(3) ELSE stav_changed_at END,
               stav                 = :stav2,
               started_at           = COALESCE(started_at, NOW(3)),
               poznamka             = :poz2,
               callback_at          = :cb2,
               schuzka_at           = CASE WHEN :stav3 = \'SCHUZKA\' THEN :sa2 ELSE schuzka_at END,
               schuzka_acknowledged = CASE WHEN :stav4 = \'SCHUZKA\' THEN 0 ELSE schuzka_acknowledged END,
               bmsl                 = CASE WHEN :stav5 = \'SMLOUVA\' THEN :bmsl2 ELSE bmsl END,
               smlouva_date         = CASE WHEN :stav6 = \'SMLOUVA\' THEN :sdate2 ELSE smlouva_date END,
               nabidka_id           = CASE WHEN :stav7 = \'SMLOUVA\' THEN :nid2 ELSE nabidka_id END,
               install_internet     = CASE WHEN :stav8 = \'SMLOUVA\' THEN :inst2 ELSE install_internet END,
               install_adresy       = CASE WHEN :stav9 = \'SMLOUVA\' THEN :iadresy2 ELSE install_adresy END,
               closed_at            = CASE WHEN :stav10 IN (\'BO_PREDANO\',\'BO_VPRACI\',\'BO_VRACENO\',\'NABIDKA\',\'SCHUZKA\',\'SANCE\',\'CALLBACK\',\'OBVOLANO\',\'ZPRACOVAVA\',\'NOVE\') THEN NULL ELSE closed_at END,
               updated_at           = NOW(3)'
        )->execute([
            'cid'     => $contactId, 'oid'      => $ozId,
            'stav'    => $newStav,   'poz'      => $poznamka,
            'cb'      => $cbVal,     'sa'       => $saVal,
            'bmsl'    => $bmslVal,   'sdate'    => $smlouvaDate,
            'nid'     => $nabidkaId,
            'inst'    => $installInternet, 'iadresy'  => $installAdresyJson,
            'stavNew' => $newStav,
            'stav2'   => $newStav,   'poz2'     => $poznamka,
            'cb2'     => $cbVal,
            'stav3'   => $newStav,   'sa2'      => $saVal,
            'stav4'   => $newStav,
            'stav5'   => $newStav,   'bmsl2'    => $bmslVal,
            'stav6'   => $newStav,   'sdate2'   => $smlouvaDate,
            'stav7'   => $newStav,   'nid2'     => $nabidkaId,
            'stav8'   => $newStav,   'inst2'    => $installInternet,
            'stav9'   => $newStav,   'iadresy2' => $installAdresyJson,
            'stav10'  => $newStav,
        ]);

        $msg = match ($newStav) {
            'SMLOUVA'      => '🏆 Výhra! Smlouva podepsána' . $firmaLabel . '.',
            'SCHUZKA'      => '📅 Schůzka naplánována' . $firmaLabel . '.',
            'CALLBACK'     => '📞 Callback nastaven' . $firmaLabel . '.',
            'NEZAJEM'      => 'Označeno jako nezájem' . $firmaLabel . '.',
            'NERELEVANTNI' => 'Označeno jako nerelevantní' . $firmaLabel . '.',
            'OBVOLANO'     => '📞 Obvoláno' . $firmaLabel . ' · poznámka uložena.',
            'NABIDKA'      => '📨 Nabídka odeslána' . $firmaLabel . '.',
            'BO_PREDANO'   => '📤 Předáno BO' . $firmaLabel . '.',
            'ZPRACOVAVA'   => '▶ Zpracovávám' . $firmaLabel . ' · poznámka uložena.',
            default        => 'Stav aktualizován' . $firmaLabel . '.',
        };
        crm_flash_set($msg);

        // Po změně stavu zůstaneme na původním tabu, kde OZ pracoval —
        // kontakt se z něho jen přesune (vidíme to podle změny počítadel).
        crm_redirect('/oz/leads?tab=' . $tab . '#c-' . $contactId);
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /oz/accept-lead  –  Přijetí jednoho čekajícího leadu
    // ────────────────────────────────────────────────────────────────

    public function postAcceptLead(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/oz/leads');
        }

        $this->ensureWorkflowTable();
        $ozId      = (int) $user['id'];
        $contactId = (int) ($_POST['contact_id'] ?? 0);

        $cStmt = $this->pdo->prepare(
            "SELECT id, firma FROM contacts
             WHERE id = :cid AND assigned_sales_id = :ozid AND stav = 'CALLED_OK'"
        );
        $cStmt->execute(['cid' => $contactId, 'ozid' => $ozId]);
        $contact = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$contact) {
            crm_flash_set('Kontakt nenalezen.');
            crm_redirect('/oz/leads');
        }

        $this->pdo->prepare(
            "INSERT INTO oz_contact_workflow
               (contact_id, oz_id, stav, started_at, updated_at)
             VALUES (:cid, :oid, 'NOVE', NOW(3), NOW(3))
             ON DUPLICATE KEY UPDATE
               stav       = IF(stav IS NULL, 'NOVE', stav),
               started_at = COALESCE(started_at, NOW(3)),
               updated_at = NOW(3)"
        )->execute(['cid' => $contactId, 'oid' => $ozId]);

        crm_flash_set('✓ Lead přijat — ' . (string)($contact['firma'] ?? ''));
        crm_redirect('/oz/leads?tab=nove');
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /oz/accept-all-leads  –  Přijetí všech leadů od jedné navolávačky
    // ────────────────────────────────────────────────────────────────

    public function postAcceptAllLeads(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/oz/leads');
        }

        $this->ensureWorkflowTable();
        $ozId     = (int) $user['id'];
        $callerId = (int) ($_POST['caller_id'] ?? 0);

        $pStmt = $this->pdo->prepare(
            "SELECT c.id
             FROM contacts c
             LEFT JOIN oz_contact_workflow w ON w.contact_id = c.id AND w.oz_id = :ozid
             WHERE c.assigned_sales_id = :ozid2
               AND c.stav = 'CALLED_OK'
               AND c.assigned_caller_id = :callerid
               AND w.id IS NULL"
        );
        $pStmt->execute(['ozid' => $ozId, 'ozid2' => $ozId, 'callerid' => $callerId]);
        $pending = $pStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        if ($pending !== []) {
            $ins = $this->pdo->prepare(
                "INSERT INTO oz_contact_workflow
                   (contact_id, oz_id, stav, started_at, updated_at)
                 VALUES (:cid, :oid, 'NOVE', NOW(3), NOW(3))
                 ON DUPLICATE KEY UPDATE
                   stav       = IF(stav IS NULL, 'NOVE', stav),
                   started_at = COALESCE(started_at, NOW(3)),
                   updated_at = NOW(3)"
            );
            foreach ($pending as $cid) {
                $ins->execute(['cid' => (int) $cid, 'oid' => $ozId]);
            }
        }

        crm_flash_set('✓ Přijato ' . count($pending) . ' leadů.');
        crm_redirect('/oz/leads?tab=nove');
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /oz/reklamace  –  Reklamace špatně navolaného leadu
    //  Nastaví stav REKLAMACE + vytvoří flag viditelný navolávačce
    // ────────────────────────────────────────────────────────────────

    public function postReklamace(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/oz/leads');
        }

        $this->ensureWorkflowTable();
        $this->ensureFlagsTable();
        $this->ensureNotesTable();

        $ozId      = (int) $user['id'];
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        $reason    = trim((string) ($_POST['reklamace_reason'] ?? ''));
        $tab       = in_array((string) ($_POST['tab'] ?? ''), ['nove','obvolano','nabidka','schuzka','callback','sance','smlouva','bo','bo_predano','bo_vraceno','dokonceno','nezajem','reklamace'], true)
                        ? (string) ($_POST['tab'] ?? 'nove') : 'nove';

        if ($reason === '') {
            crm_flash_set('⚠ Zadejte důvod chybného leadu.');
            crm_redirect('/oz/leads?tab=' . $tab . '#c-' . $contactId);
        }

        // Ověřit že kontakt patří tomuto OZ
        $cStmt = $this->pdo->prepare(
            "SELECT id, firma FROM contacts WHERE id = :cid AND assigned_sales_id = :ozid AND stav = 'CALLED_OK'"
        );
        $cStmt->execute(['cid' => $contactId, 'ozid' => $ozId]);
        $contact = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$contact) {
            crm_flash_set('Kontakt nenalezen.');
            crm_redirect('/oz/leads?tab=' . $tab);
        }
        $firma = (string) ($contact['firma'] ?? '');

        // 1. Uložit poznámku do historie
        $this->pdo->prepare(
            'INSERT INTO oz_contact_notes (contact_id, oz_id, note)
             VALUES (:cid, :oid, :note)'
        )->execute(['cid' => $contactId, 'oid' => $ozId, 'note' => '[REKLAMACE] ' . $reason]);

        // Načti starý stav PŘED UPDATE pro audit log
        $oldStavStmt = $this->pdo->prepare(
            "SELECT stav FROM oz_contact_workflow WHERE contact_id = :cid AND oz_id = :oid LIMIT 1"
        );
        $oldStavStmt->execute(['cid' => $contactId, 'oid' => $ozId]);
        $oldStav = (string) ($oldStavStmt->fetchColumn() ?: '');

        // 2. Nastavit workflow stav na REKLAMACE
        $this->pdo->prepare(
            "INSERT INTO oz_contact_workflow
               (contact_id, oz_id, stav, started_at, poznamka, updated_at)
             VALUES (:cid, :oid, 'REKLAMACE', NOW(3), :poz, NOW(3))
             ON DUPLICATE KEY UPDATE
               stav       = 'REKLAMACE',
               started_at = COALESCE(started_at, NOW(3)),
               poznamka   = :poz2,
               updated_at = NOW(3)"
        )->execute(['cid' => $contactId, 'oid' => $ozId, 'poz' => $reason, 'poz2' => $reason]);

        // Audit log — kdo, kdy, odkud → REKLAMACE, proč
        if ($oldStav !== 'REKLAMACE') {
            crm_log_workflow_change($this->pdo, $contactId, $ozId,
                $oldStav !== '' ? $oldStav : null, 'REKLAMACE',
                'Chybný lead nahlášen OZ: ' . $reason);
        }

        // 3. Vytvořit flag — viditelný navolávačce v její statistice
        $this->pdo->prepare(
            'INSERT INTO contact_oz_flags (contact_id, oz_id, reason)
             VALUES (:cid, :oid, :reason)
             ON DUPLICATE KEY UPDATE reason = :reason2, flagged_at = NOW(3)'
        )->execute([
            'cid'     => $contactId,
            'oid'     => $ozId,
            'reason'  => $reason,
            'reason2' => $reason,
        ]);

        crm_flash_set('⚠ Chybný lead nahlášen — ' . $firma . '. Navolávačka bude upozorněna.');
        crm_redirect('/oz/leads?tab=reklamace');
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /oz/chybny-close  –  OZ uzavře případ chybného leadu
    //  (oba musí uzavřít — teprve pak je lead evidován jako uzavřený)
    // ────────────────────────────────────────────────────────────────

    // ────────────────────────────────────────────────────────────────
    //  POST /oz/chybny-comment  –  OZ napíše odpověď navolávačce
    // ────────────────────────────────────────────────────────────────

    public function postChybnyComment(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/oz/leads?tab=reklamace');
        }

        $this->ensureFlagsTable();

        $ozId      = (int) $user['id'];
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        $comment   = trim((string) ($_POST['oz_comment'] ?? ''));

        if ($comment === '') {
            crm_flash_set('⚠ Zadejte text odpovědi.');
            crm_redirect('/oz/leads?tab=reklamace#c-' . $contactId);
        }

        $fStmt = $this->pdo->prepare(
            'SELECT id FROM contact_oz_flags WHERE contact_id = :cid AND oz_id = :oid'
        );
        $fStmt->execute(['cid' => $contactId, 'oid' => $ozId]);
        $flag = $fStmt->fetch(PDO::FETCH_ASSOC);

        if (!$flag) {
            crm_flash_set('Záznam nenalezen.');
            crm_redirect('/oz/leads?tab=reklamace');
        }

        $this->pdo->prepare(
            'UPDATE contact_oz_flags SET oz_comment = :comment WHERE id = :id'
        )->execute(['comment' => $comment, 'id' => (int) $flag['id']]);

        crm_flash_set('💬 Odpověď odeslána navolávačce.');
        crm_redirect('/oz/leads?tab=reklamace#c-' . $contactId);
    }

    public function postChybnyClose(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/oz/leads?tab=reklamace');
        }

        $this->ensureFlagsTable();

        $ozId      = (int) $user['id'];
        $contactId = (int) ($_POST['contact_id'] ?? 0);

        // Ověřit flag patří tomuto OZ
        $fStmt = $this->pdo->prepare(
            'SELECT id, caller_confirmed FROM contact_oz_flags
             WHERE contact_id = :cid AND oz_id = :oid'
        );
        $fStmt->execute(['cid' => $contactId, 'oid' => $ozId]);
        $flag = $fStmt->fetch(PDO::FETCH_ASSOC);

        if (!$flag) {
            crm_flash_set('Záznam nenalezen.');
            crm_redirect('/oz/leads?tab=reklamace');
        }

        $this->pdo->prepare(
            'UPDATE contact_oz_flags SET oz_confirmed = 1 WHERE id = :id'
        )->execute(['id' => (int) $flag['id']]);

        $bothConfirmed = (int) $flag['caller_confirmed'] === 1;
        $msg = $bothConfirmed
            ? '✅ Případ uzavřen — oba potvrdili. Lead evidován jako chybný (nepočítá se do výplaty).'
            : '✅ Uzavřeno z vaší strany. Čeká se na potvrzení navolávačky.';

        crm_flash_set($msg);
        crm_redirect('/oz/leads?tab=reklamace');
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /oz/offered-service/add  –  OZ přidá novou nabídnutou službu
    // ────────────────────────────────────────────────────────────────

    public function postOfferedServiceAdd(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/oz/leads');
        }

        $this->ensureOfferedServicesTables();

        $ozId      = (int) $user['id'];
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        $tab       = (string) ($_POST['tab'] ?? 'nove');
        $type      = (string) ($_POST['service_type'] ?? '');
        $label     = trim((string) ($_POST['service_label'] ?? ''));
        $modem     = trim((string) ($_POST['modem_label'] ?? ''));
        $priceRaw  = trim((string) ($_POST['price_monthly'] ?? ''));
        $idsRaw    = (string) ($_POST['identifiers'] ?? '');
        $note      = trim((string) ($_POST['note'] ?? ''));

        // Validace vlastnictví kontaktu
        $check = $this->pdo->prepare(
            "SELECT id FROM contacts WHERE id = :cid AND assigned_sales_id = :ozid LIMIT 1"
        );
        $check->execute(['cid' => $contactId, 'ozid' => $ozId]);
        if (!$check->fetch(PDO::FETCH_ASSOC)) {
            crm_flash_set('⚠ Kontakt nenalezen.');
            crm_redirect('/oz/leads?tab=' . urlencode($tab));
        }

        // Validace katalogu
        if (!in_array($type, crm_offered_services_types(), true)) {
            crm_flash_set('⚠ Vyber typ služby.');
            crm_redirect('/oz/leads?tab=' . urlencode($tab) . '#c-' . $contactId);
        }
        if ($label === '' || !crm_offered_services_is_valid($type, $label)) {
            crm_flash_set('⚠ Vyber tarif z nabídky.');
            crm_redirect('/oz/leads?tab=' . urlencode($tab) . '#c-' . $contactId);
        }
        if (!crm_offered_services_is_valid_modem($modem === '' ? null : $modem)) {
            crm_flash_set('⚠ Neznámý modem.');
            crm_redirect('/oz/leads?tab=' . urlencode($tab) . '#c-' . $contactId);
        }
        // Modem je relevantní jen pro Pevný internet — u ostatních typů ignoruj
        if ($type !== 'internet') {
            $modem = '';
        }

        // Cena: parsovat (povolit "1 234", "1234,50", "1234.50")
        $price = null;
        if ($priceRaw !== '') {
            $normalized = (float) str_replace([' ', ','], ['', '.'], $priceRaw);
            if ($normalized >= 0 && $normalized <= 999999) {
                $price = $normalized;
            }
        }

        // Identifikátory: jeden na řádek, prázdné vynechat
        $items = [];
        foreach (preg_split('/\r\n|\r|\n/', $idsRaw) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $items[] = $line;
            }
        }

        // Insert (transaction)
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare(
                "INSERT INTO oz_contact_offered_services
                   (contact_id, oz_id, service_type, service_label,
                    modem_label, price_monthly, note,
                    created_at, updated_at)
                 VALUES
                   (:cid, :oid, :type, :label,
                    :modem, :price, :note,
                    NOW(3), NOW(3))"
            )->execute([
                'cid'   => $contactId,
                'oid'   => $ozId,
                'type'  => $type,
                'label' => $label,
                'modem' => $modem === '' ? null : $modem,
                'price' => $price,
                'note'  => $note === '' ? null : $note,
            ]);
            $serviceId = (int) $this->pdo->lastInsertId();

            if ($items !== []) {
                $itemStmt = $this->pdo->prepare(
                    "INSERT INTO oz_contact_offered_service_items
                       (service_id, identifier, created_at, updated_at)
                     VALUES (:sid, :id, NOW(3), NOW(3))"
                );
                foreach ($items as $identifier) {
                    $itemStmt->execute(['sid' => $serviceId, 'id' => $identifier]);
                }
            }

            $this->pdo->commit();
        } catch (\Throwable) {
            $this->pdo->rollBack();
            crm_flash_set('⚠ Chyba při ukládání služby.');
            crm_redirect('/oz/leads?tab=' . urlencode($tab) . '#c-' . $contactId);
        }

        $itemCount = count($items);
        $msg = '✓ Služba přidána';
        if ($itemCount > 0) {
            $msg .= ' (' . $itemCount . ' ' . ($itemCount === 1 ? 'položka' : ($itemCount < 5 ? 'položky' : 'položek')) . ')';
        }
        $msg .= '.';
        crm_flash_set($msg);
        crm_redirect('/oz/leads?tab=' . urlencode($tab) . '#c-' . $contactId);
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /oz/offered-service/delete  –  OZ smaže nabídnutou službu
    // ────────────────────────────────────────────────────────────────

    public function postOfferedServiceDelete(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/oz/leads');
        }

        $this->ensureOfferedServicesTables();

        $ozId      = (int) $user['id'];
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $tab       = (string) ($_POST['tab'] ?? 'nove');

        // Ověřit, že služba patří tomuto OZ a kontakt mu je přiřazen
        $stmt = $this->pdo->prepare(
            "SELECT s.contact_id
             FROM oz_contact_offered_services s
             JOIN contacts c ON c.id = s.contact_id
             WHERE s.id = :sid AND s.oz_id = :ozid AND c.assigned_sales_id = :ozid2
             LIMIT 1"
        );
        $stmt->execute(['sid' => $serviceId, 'ozid' => $ozId, 'ozid2' => $ozId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            crm_flash_set('⚠ Služba nenalezena.');
            crm_redirect('/oz/leads?tab=' . urlencode($tab));
        }
        $contactId = (int) $row['contact_id'];

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare(
                "DELETE FROM oz_contact_offered_service_items WHERE service_id = :sid"
            )->execute(['sid' => $serviceId]);
            $this->pdo->prepare(
                "DELETE FROM oz_contact_offered_services WHERE id = :sid"
            )->execute(['sid' => $serviceId]);
            $this->pdo->commit();
        } catch (\Throwable) {
            $this->pdo->rollBack();
            crm_flash_set('⚠ Chyba při mazání.');
            crm_redirect('/oz/leads?tab=' . urlencode($tab) . '#c-' . $contactId);
        }

        crm_flash_set('🗑 Služba smazána.');
        crm_redirect('/oz/leads?tab=' . urlencode($tab) . '#c-' . $contactId);
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /oz/offered-service-item/oku  –  OZ doplní/změní/smaže OKU kód
    //  Prázdný vstup = vymazat OKU.
    // ────────────────────────────────────────────────────────────────

    public function postOfferedServiceItemOku(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/oz/leads');
        }

        $this->ensureOfferedServicesTables();

        $ozId    = (int) $user['id'];
        $itemId  = (int) ($_POST['item_id'] ?? 0);
        $tab     = (string) ($_POST['tab'] ?? 'nove');
        $okuRaw  = trim((string) ($_POST['oku_code'] ?? ''));

        // Ověřit, že položka patří službě tohoto OZ a kontakt mu je přiřazen
        $stmt = $this->pdo->prepare(
            "SELECT i.id, s.contact_id
             FROM oz_contact_offered_service_items i
             JOIN oz_contact_offered_services s ON s.id = i.service_id
             JOIN contacts c ON c.id = s.contact_id
             WHERE i.id = :iid AND s.oz_id = :ozid AND c.assigned_sales_id = :ozid2
             LIMIT 1"
        );
        $stmt->execute(['iid' => $itemId, 'ozid' => $ozId, 'ozid2' => $ozId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            crm_flash_set('⚠ Položka nenalezena.');
            crm_redirect('/oz/leads?tab=' . urlencode($tab));
        }
        $contactId = (int) $row['contact_id'];

        // Validace OKU: max 64 znaků, povolit prázdný (= vymazat)
        if (mb_strlen($okuRaw) > 64) {
            crm_flash_set('⚠ OKU kód je příliš dlouhý (max 64 znaků).');
            crm_redirect('/oz/leads?tab=' . urlencode($tab) . '#c-' . $contactId);
        }

        if ($okuRaw === '') {
            // Vymazat OKU
            $this->pdo->prepare(
                "UPDATE oz_contact_offered_service_items
                 SET oku_code = NULL, oku_filled_at = NULL, updated_at = NOW(3)
                 WHERE id = :iid"
            )->execute(['iid' => $itemId]);
            crm_flash_set('OKU kód odstraněn.');
        } else {
            // Doplnit / přepsat OKU
            $this->pdo->prepare(
                "UPDATE oz_contact_offered_service_items
                 SET oku_code = :oku, oku_filled_at = NOW(3), updated_at = NOW(3)
                 WHERE id = :iid"
            )->execute(['oku' => $okuRaw, 'iid' => $itemId]);
            crm_flash_set('✓ OKU kód uložen.');
        }

        crm_redirect('/oz/leads?tab=' . urlencode($tab) . '#c-' . $contactId);
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /oz/offered-service/edit  –  OZ upraví hlavní pole služby
    //  (typ, tarif, modem, cena, poznámka — ne identifikátory)
    // ────────────────────────────────────────────────────────────────

    public function postOfferedServiceEdit(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/oz/leads');
        }

        $this->ensureOfferedServicesTables();

        $ozId      = (int) $user['id'];
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $tab       = (string) ($_POST['tab'] ?? 'nove');
        $type      = (string) ($_POST['service_type'] ?? '');
        $label     = trim((string) ($_POST['service_label'] ?? ''));
        $modem     = trim((string) ($_POST['modem_label'] ?? ''));
        $priceRaw  = trim((string) ($_POST['price_monthly'] ?? ''));
        $note      = trim((string) ($_POST['note'] ?? ''));

        // Ověřit, že služba patří tomuto OZ a kontakt mu je přiřazen
        $stmt = $this->pdo->prepare(
            "SELECT s.contact_id
             FROM oz_contact_offered_services s
             JOIN contacts c ON c.id = s.contact_id
             WHERE s.id = :sid AND s.oz_id = :ozid AND c.assigned_sales_id = :ozid2
             LIMIT 1"
        );
        $stmt->execute(['sid' => $serviceId, 'ozid' => $ozId, 'ozid2' => $ozId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            crm_flash_set('⚠ Služba nenalezena.');
            crm_redirect('/oz/leads?tab=' . urlencode($tab));
        }
        $contactId = (int) $row['contact_id'];

        // Validace katalogu
        if (!in_array($type, crm_offered_services_types(), true)) {
            crm_flash_set('⚠ Vyber typ služby.');
            crm_redirect('/oz/leads?tab=' . urlencode($tab) . '#c-' . $contactId);
        }
        if ($label === '' || !crm_offered_services_is_valid($type, $label)) {
            crm_flash_set('⚠ Vyber tarif z nabídky.');
            crm_redirect('/oz/leads?tab=' . urlencode($tab) . '#c-' . $contactId);
        }
        if (!crm_offered_services_is_valid_modem($modem === '' ? null : $modem)) {
            crm_flash_set('⚠ Neznámý modem.');
            crm_redirect('/oz/leads?tab=' . urlencode($tab) . '#c-' . $contactId);
        }
        // Modem je relevantní jen pro Pevný internet
        if ($type !== 'internet') {
            $modem = '';
        }

        // Cena: parsovat (povolit "1 234", "1234,50", "1234.50")
        $price = null;
        if ($priceRaw !== '') {
            $normalized = (float) str_replace([' ', ','], ['', '.'], $priceRaw);
            if ($normalized >= 0 && $normalized <= 999999) {
                $price = $normalized;
            }
        }

        $this->pdo->prepare(
            "UPDATE oz_contact_offered_services
             SET service_type  = :type,
                 service_label = :label,
                 modem_label   = :modem,
                 price_monthly = :price,
                 note          = :note,
                 updated_at    = NOW(3)
             WHERE id = :sid"
        )->execute([
            'type'  => $type,
            'label' => $label,
            'modem' => $modem === '' ? null : $modem,
            'price' => $price,
            'note'  => $note === '' ? null : $note,
            'sid'   => $serviceId,
        ]);

        crm_flash_set('✓ Služba upravena.');
        crm_redirect('/oz/leads?tab=' . urlencode($tab) . '#c-' . $contactId);
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /oz/set-offer-id  –  OZ uloží/změní/smaže ID nabídky z OT
    //  Recykluje stávající sloupec oz_contact_workflow.nabidka_id.
    //  Pokud je v POSTu "then_predat=1" a ID není prázdné, kontakt se
    //  rovnou předá BO (workflow stav → BO_PREDANO).
    // ────────────────────────────────────────────────────────────────

    public function postSetOfferId(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/oz/leads');
        }

        $this->ensureWorkflowTable();

        $ozId       = (int) $user['id'];
        $contactId  = (int) ($_POST['contact_id'] ?? 0);
        $tab        = (string) ($_POST['tab'] ?? 'nove');
        $offerId    = trim((string) ($_POST['offer_id'] ?? ''));
        $bmslRaw    = trim((string) ($_POST['bmsl'] ?? ''));
        $thenPredat = !empty($_POST['then_predat']);

        // Validace vlastnictví kontaktu
        $check = $this->pdo->prepare(
            "SELECT id FROM contacts WHERE id = :cid AND assigned_sales_id = :ozid LIMIT 1"
        );
        $check->execute(['cid' => $contactId, 'ozid' => $ozId]);
        if (!$check->fetch(PDO::FETCH_ASSOC)) {
            crm_flash_set('⚠ Kontakt nenalezen.');
            crm_redirect('/oz/leads?tab=' . urlencode($tab));
        }

        if (mb_strlen($offerId) > 80) {
            crm_flash_set('⚠ ID nabídky je příliš dlouhé (max 80 znaků).');
            crm_redirect('/oz/leads?tab=' . urlencode($tab) . '#c-' . $contactId);
        }

        // BMSL parsing — pokud zadáno, musí být kladné číslo; zaokrouhlí se dolů na stokoruny.
        // (1199 → 1100, 2550 → 2500). Prázdná hodnota = neměnit existující BMSL.
        $bmslVal = null; // null = nesahat na sloupec
        if ($bmslRaw !== '') {
            $bmslNumeric = (float) str_replace([' ', ','], ['', '.'], $bmslRaw);
            if (!is_finite($bmslNumeric) || $bmslNumeric < 100) {
                crm_flash_set('⚠ BMSL musí být kladné číslo (alespoň 100 Kč bez DPH).');
                crm_redirect('/oz/leads?tab=' . urlencode($tab) . '#c-' . $contactId);
            }
            $bmslVal = (int) (floor($bmslNumeric / 100) * 100);
        }

        // Pokud uživatel chce rovnou předat BO — musíme mít ID i BMSL (z POSTu nebo z DB).
        if ($thenPredat) {
            // ID nabídky: buď z POSTu, nebo už v DB
            $existingNabidka = '';
            $existingBmsl    = 0;
            $wfStmt = $this->pdo->prepare(
                "SELECT bmsl, nabidka_id FROM oz_contact_workflow
                 WHERE contact_id = :cid AND oz_id = :oid LIMIT 1"
            );
            $wfStmt->execute(['cid' => $contactId, 'oid' => $ozId]);
            if ($wfRow = $wfStmt->fetch(PDO::FETCH_ASSOC)) {
                $existingBmsl    = (int) ($wfRow['bmsl'] ?? 0);
                $existingNabidka = trim((string) ($wfRow['nabidka_id'] ?? ''));
            }

            $finalNabidka = $offerId !== '' ? $offerId : $existingNabidka;
            $finalBmsl    = $bmslVal !== null ? $bmslVal : $existingBmsl;

            if ($finalNabidka === '') {
                crm_flash_set('⚠ Pro předání BO je nutné vyplnit ID nabídky.');
                crm_redirect('/oz/leads?tab=' . urlencode($tab) . '#c-' . $contactId);
            }
            if ($finalBmsl <= 0) {
                crm_flash_set('⚠ Pro předání BO je nutné vyplnit BMSL částku (Kč bez DPH, zaokrouhleno dolů na stokoruny).');
                crm_redirect('/oz/leads?tab=' . urlencode($tab) . '#c-' . $contactId);
            }
        }

        // Workflow řádek nemusí ještě existovat — INSERT … ON DUPLICATE KEY UPDATE
        // Pokud "then_predat", uložíme rovnou stav BO_PREDANO.
        $targetStav = $thenPredat ? 'BO_PREDANO' : 'NOVE';
        // stav_changed_at v UPDATE musí být PŘED `stav` aby porovnání bralo starý stav.
        $this->pdo->prepare(
            "INSERT INTO oz_contact_workflow
               (contact_id, oz_id, stav, stav_changed_at, nabidka_id, bmsl, started_at, updated_at)
             VALUES
               (:cid, :oid, :st, NOW(3), :nid, :bmsl, NOW(3), NOW(3))
             ON DUPLICATE KEY UPDATE
               stav_changed_at = CASE WHEN :predat = 1 AND stav <> 'BO_PREDANO' THEN NOW(3) ELSE stav_changed_at END,
               stav            = CASE WHEN :predat2 = 1 THEN 'BO_PREDANO' ELSE stav END,
               nabidka_id      = :nid2,
               bmsl            = CASE WHEN :bmsl3 IS NOT NULL THEN :bmsl2 ELSE bmsl END,
               updated_at      = NOW(3)"
        )->execute([
            'cid'     => $contactId,
            'oid'     => $ozId,
            'st'      => $targetStav,
            'nid'     => $offerId === '' ? null : $offerId,
            'nid2'    => $offerId === '' ? null : $offerId,
            'bmsl'    => $bmslVal,
            'bmsl2'   => $bmslVal,
            'bmsl3'   => $bmslVal,
            'predat'  => $thenPredat ? 1 : 0,
            'predat2' => $thenPredat ? 1 : 0,
        ]);

        if ($thenPredat) {
            $bmslMsg = $bmslVal !== null ? ' · BMSL ' . number_format($bmslVal, 0, ',', ' ') . ' Kč' : '';
            crm_flash_set('✓ ID nabídky uloženo (' . $offerId . ')' . $bmslMsg . ' — kontakt předán BO.');
        } elseif ($offerId === '' && $bmslVal === null) {
            crm_flash_set('ID nabídky odstraněno.');
        } else {
            $parts = [];
            if ($offerId !== '') { $parts[] = 'ID nabídky ' . $offerId; }
            if ($bmslVal !== null) { $parts[] = 'BMSL ' . number_format($bmslVal, 0, ',', ' ') . ' Kč'; }
            crm_flash_set('✓ Uloženo: ' . implode(' · ', $parts));
        }
        crm_redirect('/oz/leads?tab=' . urlencode($tab) . '#c-' . $contactId);
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /oz/checkbox-toggle  –  OZ přepne checkbox progress (jen "podpis_potvrzen")
    // ────────────────────────────────────────────────────────────────
    public function postCheckboxToggle(): void
    {
        $isAjax = (str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json'))
               || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        $jsonError = function (string $msg) use ($isAjax): never {
            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
                exit;
            }
            crm_flash_set($msg);
            crm_redirect('/oz/leads');
        };

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            $jsonError('Neplatný CSRF token.');
        }

        $this->ensureWorkflowTable();

        $ozId      = (int) $user['id'];
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        $tab       = (string) ($_POST['tab'] ?? 'bo_predano');
        // OZ smí přepínat POUZE 'podpis_potvrzen'. Ostatní jsou výsadou BO.
        $field     = (string) ($_POST['field'] ?? '');
        if ($field !== 'podpis_potvrzen') {
            $jsonError('⚠ OZ nemůže přepínat tento checkbox.');
        }
        $checked = !empty($_POST['checked']);

        // Ověřit, že kontakt patří tomuto OZ a workflow existuje
        $check = $this->pdo->prepare(
            "SELECT w.id, w.stav, w.podpis_potvrzen
             FROM oz_contact_workflow w
             INNER JOIN contacts c ON c.id = w.contact_id
             WHERE w.contact_id = :cid AND w.oz_id = :oid AND c.assigned_sales_id = :oid2
             LIMIT 1"
        );
        $check->execute(['cid' => $contactId, 'oid' => $ozId, 'oid2' => $ozId]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $jsonError('⚠ Kontakt nenalezen.');
        }
        // Smí jen ve stavech, kde je BO progress relevantní
        if (!in_array((string) $row['stav'], ['BO_PREDANO','BO_VPRACI','BO_VRACENO','SMLOUVA'], true)) {
            $jsonError('⚠ Checkbox lze přepínat jen u kontaktů u BO.');
        }

        // Při zaškrtnutí nastav timestamp + autor; při odškrtnutí vynuluj
        if ($checked) {
            $this->pdo->prepare(
                "UPDATE oz_contact_workflow
                 SET podpis_potvrzen    = 1,
                     podpis_potvrzen_at = NOW(3),
                     podpis_potvrzen_by = :uid,
                     updated_at         = NOW(3)
                 WHERE contact_id = :cid AND oz_id = :oid"
            )->execute(['uid' => $ozId, 'cid' => $contactId, 'oid' => $ozId]);
        } else {
            $this->pdo->prepare(
                "UPDATE oz_contact_workflow
                 SET podpis_potvrzen    = 0,
                     podpis_potvrzen_at = NULL,
                     podpis_potvrzen_by = NULL,
                     updated_at         = NOW(3)
                 WHERE contact_id = :cid AND oz_id = :oid"
            )->execute(['cid' => $contactId, 'oid' => $ozId]);
        }

        if ($isAjax) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => true, 'checked' => $checked ? 1 : 0], JSON_UNESCAPED_UNICODE);
            exit;
        }
        crm_flash_set($checked ? '✓ Podpis potvrzen.' : 'Zrušeno potvrzení podpisu.');
        crm_redirect('/oz/leads?tab=' . urlencode($tab) . '#c-' . $contactId);
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /oz/action/add  –  OZ přidá záznam do Pracovního deníku
    // ────────────────────────────────────────────────────────────────

    public function postActionAdd(): void
    {
        $isAjax = (str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json'))
               || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        $jsonError = function (string $msg) use ($isAjax): never {
            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => $msg]);
                exit;
            }
            crm_flash_set($msg);
            crm_redirect('/oz/leads');
        };

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            $jsonError('Neplatný CSRF token.');
        }

        $this->ensureActionsTable();

        $ozId       = (int) $user['id'];
        $contactId  = (int) ($_POST['contact_id'] ?? 0);
        $tab        = (string) ($_POST['tab'] ?? 'nove');
        $actionDate = trim((string) ($_POST['action_date'] ?? ''));
        $actionText = trim((string) ($_POST['action_text'] ?? ''));

        $check = $this->pdo->prepare(
            "SELECT id FROM contacts WHERE id = :cid AND assigned_sales_id = :ozid LIMIT 1"
        );
        $check->execute(['cid' => $contactId, 'ozid' => $ozId]);
        if (!$check->fetch(PDO::FETCH_ASSOC)) {
            $jsonError('⚠ Kontakt nenalezen.');
        }

        if ($actionDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $actionDate)) {
            $actionDate = date('Y-m-d');
        }
        if (strtotime($actionDate) === false) {
            $actionDate = date('Y-m-d');
        }

        if ($actionText === '') {
            $jsonError('⚠ Zadejte popis úkonu.');
        }
        if (mb_strlen($actionText) > 1000) {
            $actionText = mb_substr($actionText, 0, 1000);
        }

        $this->pdo->prepare(
            "INSERT INTO oz_contact_actions
               (contact_id, oz_id, action_date, action_text, created_at)
             VALUES (:cid, :oid, :dt, :txt, NOW(3))"
        )->execute([
            'cid' => $contactId,
            'oid' => $ozId,
            'dt'  => $actionDate,
            'txt' => $actionText,
        ]);
        $newId = (int) $this->pdo->lastInsertId();

        if ($isAjax) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'ok'           => true,
                'id'           => $newId,
                'contact_id'   => $contactId,
                'action_date'  => $actionDate,
                'action_text'  => $actionText,
                'date_fmt'     => date('d.m.Y', strtotime($actionDate)),
                'author_name'  => (string) ($user['jmeno'] ?? '—'),
                'author_role'  => (string) ($user['role'] ?? ''),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        crm_flash_set('✓ Úkon zaznamenán.');
        crm_redirect('/oz/leads?tab=' . urlencode($tab) . '#c-' . $contactId);
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /oz/action/delete  –  OZ smaže záznam z Pracovního deníku
    // ────────────────────────────────────────────────────────────────

    public function postActionDelete(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/oz/leads');
        }

        $this->ensureActionsTable();

        $ozId     = (int) $user['id'];
        $actionId = (int) ($_POST['action_id'] ?? 0);
        $tab      = (string) ($_POST['tab'] ?? 'nove');

        // Ověřit vlastnictví záznamu
        $stmt = $this->pdo->prepare(
            "SELECT a.contact_id
             FROM oz_contact_actions a
             JOIN contacts c ON c.id = a.contact_id
             WHERE a.id = :aid AND a.oz_id = :ozid AND c.assigned_sales_id = :ozid2
             LIMIT 1"
        );
        $stmt->execute(['aid' => $actionId, 'ozid' => $ozId, 'ozid2' => $ozId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            crm_flash_set('⚠ Záznam nenalezen.');
            crm_redirect('/oz/leads?tab=' . urlencode($tab));
        }
        $contactId = (int) $row['contact_id'];

        $this->pdo->prepare(
            "DELETE FROM oz_contact_actions WHERE id = :aid"
        )->execute(['aid' => $actionId]);

        crm_flash_set('🗑 Úkon smazán.');
        crm_redirect('/oz/leads?tab=' . urlencode($tab) . '#c-' . $contactId);
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /oz/tab/hide  –  Schovat záložku z menu
    // ────────────────────────────────────────────────────────────────
    public function postTabHide(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/oz/leads');
        }

        $userId  = (int) $user['id'];
        $tabKey  = (string) ($_POST['tab_key'] ?? '');
        $current = (string) ($_POST['current_tab'] ?? 'nove');

        // "nove" je default landing, nelze skrýt
        if ($tabKey === 'nove' || $tabKey === '') {
            crm_redirect('/oz/leads?tab=' . urlencode($current));
        }

        // Super-tab? Schovej všechny jeho děti najednou.
        $superTabChildren = [
            'plan' => ['callback', 'schuzka'],
            'bo'   => ['bo_predano', 'bo_vraceno', 'dokonceno'],
        ];
        $toHide = isset($superTabChildren[$tabKey])
            ? $superTabChildren[$tabKey]
            : [$tabKey];

        $hidden = $this->getHiddenTabs($userId);
        foreach ($toHide as $h) {
            if (!in_array($h, $hidden, true)) { $hidden[] = $h; }
        }
        $this->setHiddenTabs($userId, $hidden);

        // Pokud OZ schoval právě otevřený tab (nebo jeho parent), přepnout na "nove"
        $redirectTab = in_array($current, $toHide, true) ? 'nove' : $current;
        crm_redirect('/oz/leads?tab=' . urlencode($redirectTab));
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /oz/tab/reorder  –  Uložit nové pořadí tabů (drag & drop)
    //  POST:
    //    order[]            = top-level pořadí (atomické taby + super-taby 'plan','bo')
    //    sub_order_plan[]   = pořadí dětí super-tabu Plán (callback, schuzka)
    //    sub_order_bo[]     = pořadí dětí super-tabu BO (bo_predano, bo_vraceno, dokonceno)
    //  Vrací JSON {ok: bool}
    // ────────────────────────────────────────────────────────────────
    public function postTabReorder(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            echo json_encode(['ok' => false, 'error' => 'CSRF']);
            exit;
        }

        $userId = (int) $user['id'];
        $order  = (array) ($_POST['order'] ?? []);

        $this->setTabOrder($userId, $order);

        // Sub-tab pořadí — volitelné, jen pokud klient pošle
        $subPlan = $_POST['sub_order_plan'] ?? null;
        $subBo   = $_POST['sub_order_bo']   ?? null;
        if ($subPlan !== null || $subBo !== null) {
            $this->setSubTabOrder($userId, [
                'plan' => is_array($subPlan) ? $subPlan : [],
                'bo'   => is_array($subBo)   ? $subBo   : [],
            ]);
        }

        echo json_encode(['ok' => true]);
        exit;
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /oz/tab/show  –  Připnout záložku zpět do menu
    // ────────────────────────────────────────────────────────────────
    public function postTabShow(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/oz/leads');
        }

        $userId  = (int) $user['id'];
        $tabKey  = (string) ($_POST['tab_key'] ?? '');
        $current = (string) ($_POST['current_tab'] ?? '');

        // Super-tab? Vrátíme všechny jeho děti najednou.
        $superTabChildren = [
            'plan' => ['callback', 'schuzka'],
            'bo'   => ['bo_predano', 'bo_vraceno', 'dokonceno'],
        ];
        $toShow = isset($superTabChildren[$tabKey])
            ? $superTabChildren[$tabKey]
            : [$tabKey];

        $hidden = $this->getHiddenTabs($userId);
        $hidden = array_values(array_filter($hidden, static fn($t) => !in_array($t, $toShow, true)));
        $this->setHiddenTabs($userId, $hidden);

        // Zůstaneme na aktuálním tabu — připnutí jen vrátí záložku do menu,
        // kontext práce OZ se nemění.
        $redirectTo = $current !== '' ? $current : 'nove';
        crm_redirect('/oz/leads?tab=' . urlencode($redirectTo));
    }

    // ────────────────────────────────────────────────────────────────
    //  GET /oz/ares-lookup?ico=12345678  –  Proxy na ARES JSON API
    //  Vrací JSON {ok: bool, firma?, adresa?, error?}
    // ────────────────────────────────────────────────────────────────
    public function getAresLookup(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin', 'backoffice']);

        $ico = crm_normalize_ico((string) ($_GET['ico'] ?? ''));
        if ($ico === '' || strlen($ico) !== 8) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Neplatné IČO (musí mít 1–8 číslic, doplníme zleva nulami).']);
            exit;
        }

        $url = 'https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty/' . $ico;

        // cURL fetch s timeoutem
        if (!function_exists('curl_init')) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'cURL není k dispozici.']);
            exit;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'User-Agent: Snecinatripu-CRM/1.0',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($body === false || $err !== '') {
            http_response_code(502);
            echo json_encode(['ok' => false, 'error' => 'ARES nedostupný: ' . $err]);
            exit;
        }
        if ($status === 404) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'IČO ' . $ico . ' nebylo v ARES nalezeno.']);
            exit;
        }
        if ($status !== 200) {
            http_response_code(502);
            echo json_encode(['ok' => false, 'error' => 'ARES vrátil HTTP ' . $status . '.']);
            exit;
        }

        $data = json_decode((string) $body, true);
        if (!is_array($data)) {
            http_response_code(502);
            echo json_encode(['ok' => false, 'error' => 'Neplatná odpověď ARES.']);
            exit;
        }

        // Extrakce: obchodní jméno + textová adresa sídla
        $firma  = (string) ($data['obchodniJmeno'] ?? '');
        $adresa = (string) ($data['sidlo']['textovaAdresa'] ?? '');

        echo json_encode([
            'ok'     => true,
            'ico'    => $ico,
            'firma'  => $firma,
            'adresa' => $adresa,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /oz/contact/edit  –  OZ upraví údaje kontaktu
    //  (firma, telefon, email, IČO, adresa — NE region/operator)
    // ────────────────────────────────────────────────────────────────

    public function postContactEdit(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/oz/leads');
        }

        $ozId      = (int) $user['id'];
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        $tab       = (string) ($_POST['tab'] ?? 'nove');
        $firma     = trim((string) ($_POST['firma'] ?? ''));
        $telefon   = trim((string) ($_POST['telefon'] ?? ''));
        $email     = trim((string) ($_POST['email'] ?? ''));
        $ico       = trim((string) ($_POST['ico'] ?? ''));
        $adresa    = trim((string) ($_POST['adresa'] ?? ''));

        // Ověřit, že kontakt patří OZ (resp. byl mu přiřazen)
        $check = $this->pdo->prepare(
            "SELECT id FROM contacts WHERE id = :cid AND assigned_sales_id = :ozid LIMIT 1"
        );
        $check->execute(['cid' => $contactId, 'ozid' => $ozId]);
        if (!$check->fetch(PDO::FETCH_ASSOC)) {
            crm_flash_set('⚠ Kontakt nenalezen.');
            crm_redirect('/oz/leads?tab=' . urlencode($tab));
        }

        // Validace
        if ($firma === '') {
            crm_flash_set('⚠ Název firmy nemůže být prázdný.');
            crm_redirect('/oz/leads?tab=' . urlencode($tab) . '#c-' . $contactId);
        }
        if (mb_strlen($firma) > 200)   { $firma   = mb_substr($firma, 0, 200); }
        if (mb_strlen($telefon) > 50)  { $telefon = mb_substr($telefon, 0, 50); }
        if (mb_strlen($email) > 200)   { $email   = mb_substr($email, 0, 200); }
        if (mb_strlen($adresa) > 300)  { $adresa  = mb_substr($adresa, 0, 300); }
        // IČO — odstranit nečíselné znaky a doplnit nuly zleva na 8
        $ico = crm_normalize_ico($ico);
        if (mb_strlen($ico) > 20)      { $ico     = mb_substr($ico, 0, 20); }

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
            'email'   => $email === '' ? null : $email,
            'ico'     => $ico === '' ? null : $ico,
            'adresa'  => $adresa === '' ? null : $adresa,
            'cid'     => $contactId,
        ]);

        crm_flash_set('✓ Údaje kontaktu uloženy.');
        crm_redirect('/oz/leads?tab=' . urlencode($tab) . '#c-' . $contactId);
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /oz/acknowledge-meeting  –  OZ potvrdí schůzku
    // ────────────────────────────────────────────────────────────────

    public function postAcknowledgeMeeting(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/oz/leads');
        }

        $ozId      = (int) $user['id'];
        $contactId = (int) ($_POST['contact_id'] ?? 0);

        try {
            $this->pdo->prepare(
                'UPDATE oz_contact_workflow
                 SET schuzka_acknowledged = 1
                 WHERE contact_id = :cid AND oz_id = :oid'
            )->execute(['cid' => $contactId, 'oid' => $ozId]);
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }

        crm_redirect('/oz/leads');
    }

    // ────────────────────────────────────────────────────────────────
    //  GET /oz/race.json  –  Šněčí závody OZ (výhry = smlouvy)
    // ────────────────────────────────────────────────────────────────

    public function getRaceJson(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $user = crm_require_user($this->pdo);
            $myId = (int) $user['id'];

            $curYear  = (int) date('Y');
            $curMonth = (int) date('n');

            try {
                $this->ensureWorkflowTable();
                $stmt = $this->pdo->prepare(
                    "SELECT u.id, u.jmeno, COUNT(w.id) AS wins
                     FROM users u
                     LEFT JOIN oz_contact_workflow w ON w.oz_id = u.id
                       AND w.stav = 'SMLOUVA'
                       AND YEAR(w.updated_at) = :y
                       AND MONTH(w.updated_at) = :m
                     WHERE u.role = 'obchodak' AND u.aktivni = 1
                     GROUP BY u.id, u.jmeno
                     ORDER BY wins DESC, u.jmeno ASC"
                );
                $stmt->execute(['y' => $curYear, 'm' => $curMonth]);
                $ozRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\PDOException) {
                $ozRows = [];
            }

            $maxWins = 0;
            foreach ($ozRows as $row) {
                $maxWins = max($maxWins, (int) $row['wins']);
            }

            $ozList = [];
            foreach ($ozRows as $row) {
                $wins    = (int) $row['wins'];
                $pct     = $maxWins > 0 ? (int) round($wins / $maxWins * 95) : 0;
                $ozList[] = [
                    'id'   => (int) $row['id'],
                    'name' => (string) $row['jmeno'],
                    'wins' => $wins,
                    'pct'  => max($pct, $wins > 0 ? 2 : 0),
                    'me'   => (int) $row['id'] === $myId,
                ];
            }

            echo json_encode([
                'ok'      => true,
                'oz'      => $ozList,
                'month'   => $curMonth,
                'year'    => $curYear,
                'maxWins' => $maxWins,
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'oz' => []]);
        }
        exit;
    }

    // ────────────────────────────────────────────────────────────────
    //  GET /oz  –  Statistiky & kvóty OZ
    // ────────────────────────────────────────────────────────────────

    public function getIndex(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        $ozId  = (int) $user['id'];
        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();

        $year  = max(2024, min(2030, (int) ($_GET['year']  ?? date('Y'))));
        $month = max(1,    min(12,   (int) ($_GET['month'] ?? date('n'))));

        $this->ensureFlagsTable();

        $rs = $this->pdo->prepare(
            'SELECT region FROM user_regions WHERE user_id = :uid ORDER BY region'
        );
        $rs->execute(['uid' => $ozId]);
        $myRegions = $rs->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $targets = [];
        try {
            $tStmt = $this->pdo->prepare(
                'SELECT region, target_count FROM oz_targets
                 WHERE user_id = :uid AND year = :y AND month = :m'
            );
            $tStmt->execute(['uid' => $ozId, 'y' => $year, 'm' => $month]);
            foreach ($tStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $targets[(string) $row['region']] = (int) $row['target_count'];
            }
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }

        $this->ensureFlagsTable();
        $contactStmt = $this->pdo->prepare(
            "SELECT c.id, c.firma, c.telefon, c.region, c.datum_volani, c.poznamka,
                    COALESCE(u.jmeno, '—') AS caller_name,
                    CASE WHEN f.id IS NOT NULL THEN 1 ELSE 0 END AS flagged,
                    COALESCE(f.reason, '') AS flag_reason,
                    f.flagged_at
             FROM contacts c
             LEFT JOIN users u ON u.id = c.assigned_caller_id
             LEFT JOIN contact_oz_flags f ON f.contact_id = c.id AND f.oz_id = :oz_id
             WHERE c.stav = 'CALLED_OK'
               AND c.assigned_sales_id = :oz_id2
               AND YEAR(c.datum_volani) = :y
               AND MONTH(c.datum_volani) = :m
             ORDER BY c.region ASC, c.datum_volani DESC"
        );
        $contactStmt->execute(['oz_id' => $ozId, 'oz_id2' => $ozId, 'y' => $year, 'm' => $month]);
        $myContacts = $contactStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $contactsByRegion = [];
        foreach ($myContacts as $c) {
            $contactsByRegion[(string) $c['region']][] = $c;
        }

        $received = [];
        foreach ($myContacts as $c) {
            $reg = (string) $c['region'];
            $received[$reg] = ($received[$reg] ?? 0) + 1;
        }

        $totalReceived = count($myContacts);
        $totalFlagged  = count(array_filter($myContacts, fn($c) => (int)($c['flagged'] ?? 0) === 1));
        $totalTarget   = array_sum($targets);

        $allRegions = array_unique(array_merge($myRegions, array_keys($received)));
        sort($allRegions);

        $isCurrentMonth = ($year === (int) date('Y') && $month === (int) date('n'));

        // ════════════════════════════════════════════════════════════════
        //  Refactor (Krok 4): přesun stages / milestones / team stats
        //  z /oz/leads sem na dashboard. Reuse existujících SQL patternů.
        //  Pozor: tady používáme $year/$month (uživatel může brouzdat),
        //  zatímco /oz/leads vždy používá curYear/curMonth.
        // ════════════════════════════════════════════════════════════════

        // ── Měsíční win-ratio tohoto OZ (pro Osobní KPI) ──
        $monthWins = 0;
        $monthBmsl = 0;
        try {
            $winsStmt = $this->pdo->prepare(
                "SELECT COUNT(*), COALESCE(SUM(bmsl), 0)
                 FROM oz_contact_workflow
                 WHERE oz_id = :ozid
                   AND (
                     (podpis_potvrzen = 1
                      AND YEAR(podpis_potvrzen_at)  = :y
                      AND MONTH(podpis_potvrzen_at) = :m)
                     OR
                     (podpis_potvrzen = 0 AND stav = 'SMLOUVA'
                      AND YEAR(updated_at) = :y2 AND MONTH(updated_at) = :m2)
                   )"
            );
            $winsStmt->execute(['ozid' => $ozId, 'y' => $year, 'm' => $month, 'y2' => $year, 'm2' => $month]);
            [$monthWins, $monthBmsl] = $winsStmt->fetch(PDO::FETCH_NUM) ?: [0, 0];
            $monthWins = (int) $monthWins;
            $monthBmsl = (int) $monthBmsl;
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }

        // ── Tým celkem (contracts + bmsl) ──
        $teamStats  = ['contracts' => 0, 'bmsl' => 0];
        $teamStages = [];
        try {
            $tStmt = $this->pdo->prepare(
                "SELECT COUNT(w.id) AS contracts, COALESCE(SUM(w.bmsl), 0) AS bmsl
                 FROM oz_contact_workflow w
                 INNER JOIN users u ON u.id = w.oz_id AND u.role = 'obchodak' AND u.aktivni = 1
                 WHERE (
                   (w.podpis_potvrzen = 1
                    AND YEAR(w.podpis_potvrzen_at)  = :y
                    AND MONTH(w.podpis_potvrzen_at) = :m)
                   OR
                   (w.podpis_potvrzen = 0 AND w.stav = 'SMLOUVA'
                    AND YEAR(w.updated_at) = :y2 AND MONTH(w.updated_at) = :m2)
                 )"
            );
            $tStmt->execute(['y' => $year, 'm' => $month, 'y2' => $year, 'm2' => $month]);
            $teamStats = $tStmt->fetch(PDO::FETCH_ASSOC) ?: $teamStats;

            $this->ensureStagesTable();
            $sgStmt = $this->pdo->prepare(
                'SELECT stage_number, label, target_bmsl
                 FROM oz_team_stages
                 WHERE year = :y AND month = :m
                 ORDER BY target_bmsl ASC'
            );
            $sgStmt->execute(['y' => $year, 'm' => $month]);
            $teamStages = $sgStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }

        // ── Osobní milníky OZ ──
        $personalMilestones = [];
        try {
            $this->ensurePersonalMilestonesTable();
            $pmStmt = $this->pdo->prepare(
                'SELECT id, label, target_bmsl, reward_note
                 FROM oz_personal_milestones
                 WHERE oz_id = :ozid AND year = :y AND month = :m
                 ORDER BY target_bmsl ASC'
            );
            $pmStmt->execute(['ozid' => $ozId, 'y' => $year, 'm' => $month]);
            $personalMilestones = $pmStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }

        $title = 'Můj měsíc';
        ob_start();
        require dirname(__DIR__) . '/views/oz/index.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /oz/flag  –  Reklamace špatně navolaného kontaktu
    // ────────────────────────────────────────────────────────────────

    public function postFlag(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/oz');
        }

        $this->ensureFlagsTable();

        $ozId      = (int) $user['id'];
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        $reason    = trim((string) ($_POST['reason'] ?? ''));
        $action    = (string) ($_POST['action'] ?? 'flag');
        $year      = max(2024, min(2030, (int) ($_POST['year']  ?? date('Y'))));
        $month     = max(1,    min(12,   (int) ($_POST['month'] ?? date('n'))));

        $cStmt = $this->pdo->prepare(
            "SELECT id FROM contacts WHERE id = :cid AND assigned_sales_id = :ozid AND stav = 'CALLED_OK'"
        );
        $cStmt->execute(['cid' => $contactId, 'ozid' => $ozId]);
        if (!$cStmt->fetch()) {
            crm_flash_set('Kontakt nenalezen nebo nespadá do vašich leadů.');
            crm_redirect('/oz?year=' . $year . '&month=' . $month);
        }

        if ($action === 'unflag') {
            $this->pdo->prepare(
                'DELETE FROM contact_oz_flags WHERE contact_id = :cid AND oz_id = :oid'
            )->execute(['cid' => $contactId, 'oid' => $ozId]);
            crm_flash_set('Označení chybného leadu bylo staženo.');
        } else {
            if ($reason === '') {
                crm_flash_set('Zadejte prosím důvod chybného leadu.');
                crm_redirect('/oz?year=' . $year . '&month=' . $month);
            }
            $this->pdo->prepare(
                'INSERT INTO contact_oz_flags (contact_id, oz_id, reason)
                 VALUES (:cid, :oid, :reason)
                 ON DUPLICATE KEY UPDATE reason = :reason2, flagged_at = NOW(3)'
            )->execute([
                'cid' => $contactId, 'oid' => $ozId,
                'reason' => $reason,  'reason2' => $reason,
            ]);
            crm_flash_set('Chybný lead byl podán. Majitel ho uvidí v detailu.');
        }

        crm_redirect('/oz?year=' . $year . '&month=' . $month);
    }

    // ────────────────────────────────────────────────────────────────
    //  Helpers
    // ────────────────────────────────────────────────────────────────

    private function ensureWorkflowTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS `oz_contact_workflow` (
              `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `contact_id`           BIGINT UNSIGNED NOT NULL,
              `oz_id`                INT UNSIGNED NOT NULL,
              `stav`                 VARCHAR(20) NOT NULL DEFAULT 'NOVE',
              `started_at`           DATETIME(3) NULL DEFAULT NULL,
              `poznamka`             TEXT NULL,
              `callback_at`          DATETIME(3) NULL DEFAULT NULL,
              `schuzka_at`           DATETIME(3) NULL DEFAULT NULL,
              `schuzka_acknowledged` TINYINT(1) NOT NULL DEFAULT 0,
              `updated_at`           DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
                                     ON UPDATE CURRENT_TIMESTAMP(3),
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_oz_workflow` (`contact_id`, `oz_id`),
              KEY `idx_oz_stav` (`oz_id`, `stav`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        // Opravit starší tabulky bez nových sloupců
        try {
            $this->pdo->exec(
                'ALTER TABLE `oz_contact_workflow`
                 MODIFY COLUMN `poznamka` TEXT NULL'
            );
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }
        try {
            $this->pdo->exec(
                'ALTER TABLE `oz_contact_workflow`
                 ADD COLUMN `schuzka_at` DATETIME(3) NULL DEFAULT NULL'
            );
        } catch (\PDOException) {
            // Sloupec již existuje — ignorovat
        }
        try {
            $this->pdo->exec(
                'ALTER TABLE `oz_contact_workflow`
                 ADD COLUMN `schuzka_acknowledged` TINYINT(1) NOT NULL DEFAULT 0'
            );
        } catch (\PDOException) {
            // Sloupec již existuje — ignorovat
        }
        try {
            $this->pdo->exec(
                'ALTER TABLE `oz_contact_workflow`
                 ADD COLUMN `bmsl` DECIMAL(10,2) NULL DEFAULT NULL'
            );
        } catch (\PDOException) {
            // Sloupec již existuje — ignorovat
        }
        try {
            $this->pdo->exec(
                'ALTER TABLE `oz_contact_workflow`
                 ADD COLUMN `smlouva_date` DATE NULL DEFAULT NULL'
            );
        } catch (\PDOException) {
            // Sloupec již existuje — ignorovat
        }
        try {
            $this->pdo->exec(
                'ALTER TABLE `oz_contact_workflow`
                 ADD COLUMN `nabidka_id` VARCHAR(50) NULL DEFAULT NULL'
            );
        } catch (\PDOException) {
            // Sloupec již existuje — ignorovat
        }
        try {
            $this->pdo->exec(
                'ALTER TABLE `oz_contact_workflow`
                 ADD COLUMN `install_internet` TINYINT(1) NOT NULL DEFAULT 0'
            );
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }
        try {
            $this->pdo->exec(
                'ALTER TABLE `oz_contact_workflow`
                 ADD COLUMN `install_ulice` VARCHAR(200) NULL DEFAULT NULL'
            );
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }
        try {
            $this->pdo->exec(
                'ALTER TABLE `oz_contact_workflow`
                 ADD COLUMN `install_mesto` VARCHAR(100) NULL DEFAULT NULL'
            );
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }
        try {
            $this->pdo->exec(
                'ALTER TABLE `oz_contact_workflow`
                 ADD COLUMN `install_psc` VARCHAR(10) NULL DEFAULT NULL'
            );
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }
        try {
            $this->pdo->exec(
                'ALTER TABLE `oz_contact_workflow`
                 ADD COLUMN `install_byt` VARCHAR(50) NULL DEFAULT NULL'
            );
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }
        try {
            $this->pdo->exec(
                'ALTER TABLE `oz_contact_workflow`
                 ADD COLUMN `install_adresy` TEXT NULL DEFAULT NULL'
            );
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }
        // closed_at — kdy BO uzavřel kontrakt (UZAVRENO). Slouží pro filtr měsíců v "Dokončené".
        try {
            $this->pdo->exec(
                'ALTER TABLE `oz_contact_workflow`
                 ADD COLUMN `closed_at` DATETIME(3) NULL DEFAULT NULL'
            );
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }
        // stav_changed_at — kdy se naposledy změnil workflow stav (pro UX badge "v této záložce: před X")
        try { $this->pdo->exec('ALTER TABLE `oz_contact_workflow` ADD COLUMN `stav_changed_at` DATETIME(3) NULL DEFAULT NULL'); } catch (\PDOException $e) { crm_db_log_error($e, __METHOD__); }
        // BO progress checkboxy
        try { $this->pdo->exec('ALTER TABLE `oz_contact_workflow` ADD COLUMN `priprava_smlouvy` TINYINT(1) NOT NULL DEFAULT 0'); } catch (\PDOException $e) { crm_db_log_error($e, __METHOD__); }
        try { $this->pdo->exec('ALTER TABLE `oz_contact_workflow` ADD COLUMN `datovka_odeslana` TINYINT(1) NOT NULL DEFAULT 0'); } catch (\PDOException $e) { crm_db_log_error($e, __METHOD__); }
        try { $this->pdo->exec('ALTER TABLE `oz_contact_workflow` ADD COLUMN `podpis_potvrzen` TINYINT(1) NOT NULL DEFAULT 0'); } catch (\PDOException $e) { crm_db_log_error($e, __METHOD__); }
        try { $this->pdo->exec('ALTER TABLE `oz_contact_workflow` ADD COLUMN `podpis_potvrzen_at` DATETIME(3) NULL DEFAULT NULL'); } catch (\PDOException $e) { crm_db_log_error($e, __METHOD__); }
        try { $this->pdo->exec('ALTER TABLE `oz_contact_workflow` ADD COLUMN `podpis_potvrzen_by` INT UNSIGNED NULL DEFAULT NULL'); } catch (\PDOException $e) { crm_db_log_error($e, __METHOD__); }
        try { $this->pdo->exec('ALTER TABLE `oz_contact_workflow` ADD COLUMN `ubotem_zpracovano` TINYINT(1) NOT NULL DEFAULT 0'); } catch (\PDOException $e) { crm_db_log_error($e, __METHOD__); }
    }

    private function ensureFlagsTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS `contact_oz_flags` (
              `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `contact_id`       BIGINT UNSIGNED NOT NULL,
              `oz_id`            INT UNSIGNED NOT NULL,
              `reason`           VARCHAR(500) NOT NULL DEFAULT '',
              `flagged_at`       DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
              `caller_comment`   TEXT NULL DEFAULT NULL,
              `caller_confirmed` TINYINT(1) NOT NULL DEFAULT 0,
              `oz_comment`       TEXT NULL DEFAULT NULL,
              `oz_confirmed`     TINYINT(1) NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_flag` (`contact_id`, `oz_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        // Migrace: přidat nové sloupce pokud tabulka již existuje bez nich
        foreach ([
            "ALTER TABLE `contact_oz_flags` ADD COLUMN `caller_comment`   TEXT NULL DEFAULT NULL",
            "ALTER TABLE `contact_oz_flags` ADD COLUMN `caller_confirmed` TINYINT(1) NOT NULL DEFAULT 0",
            "ALTER TABLE `contact_oz_flags` ADD COLUMN `oz_comment`       TEXT NULL DEFAULT NULL",
            "ALTER TABLE `contact_oz_flags` ADD COLUMN `oz_confirmed`     TINYINT(1) NOT NULL DEFAULT 0",
        ] as $sql) {
            try { $this->pdo->exec($sql); } catch (\PDOException $e) { crm_db_log_error($e, __METHOD__); }
        }
    }

    private function ensureNotesTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS `oz_contact_notes` (
              `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `contact_id` BIGINT UNSIGNED NOT NULL,
              `oz_id`      INT UNSIGNED NOT NULL,
              `note`       TEXT NOT NULL,
              `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
              PRIMARY KEY (`id`),
              KEY `idx_oz_notes` (`contact_id`, `oz_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * Tabulka per-user prefs pro OZ taby — skryté záložky + pořadí.
     */
    private function ensureTabPrefsTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS `oz_tab_prefs` (
              `user_id`       INT UNSIGNED NOT NULL,
              `hidden_tabs`   TEXT NOT NULL DEFAULT ('[]'),
              `tab_order`     TEXT NULL DEFAULT NULL,
              `sub_tab_order` TEXT NULL DEFAULT NULL,
              `updated_at`    DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
              PRIMARY KEY (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        // Migrace: přidat sloupec tab_order pokud chybí (idempotentně)
        try {
            $this->pdo->exec("ALTER TABLE `oz_tab_prefs` ADD COLUMN `tab_order` TEXT NULL DEFAULT NULL");
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }
        // Migrace: přidat sloupec sub_tab_order (super-tab refactor 2026-04-29)
        try {
            $this->pdo->exec("ALTER TABLE `oz_tab_prefs` ADD COLUMN `sub_tab_order` TEXT NULL DEFAULT NULL");
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }
    }

    /**
     * Vrátí seznam skrytých tabů pro daného OZ uživatele.
     * Default (kdyby řádek neexistoval): [] (vše viditelné).
     *
     * Migrace pro super-tab refactor:
     *   - legacy 'bo' → expanduje na 'bo_predano' + 'bo_vraceno'
     *     (dokonceno bylo dříve samostatné, takže ho nepřidáváme)
     *
     * @return list<string>
     */
    private function getHiddenTabs(int $userId): array
    {
        $this->ensureTabPrefsTable();
        $stmt = $this->pdo->prepare(
            "SELECT hidden_tabs FROM oz_tab_prefs WHERE user_id = :uid LIMIT 1"
        );
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return [];
        }
        $decoded = json_decode((string) $row['hidden_tabs'], true);
        if (!is_array($decoded)) { return []; }

        $hidden = array_values(array_unique(array_map('strval', $decoded)));
        // Migrace: legacy 'bo' → 'bo_predano' + 'bo_vraceno'
        if (in_array('bo', $hidden, true)) {
            $hidden = array_values(array_filter($hidden, static fn($t) => $t !== 'bo'));
            foreach (['bo_predano', 'bo_vraceno'] as $sub) {
                if (!in_array($sub, $hidden, true)) { $hidden[] = $sub; }
            }
        }
        return $hidden;
    }

    /**
     * Vrátí pořadí tabů pro daného OZ uživatele.
     * Default (NULL): standardní pořadí (vrátíme prázdné pole).
     *
     * Pořadí je top-level (atomické taby + super-taby 'plan', 'bo').
     * Migrace pro super-tab refactor:
     *   - legacy plain array akceptujeme dál jako top-level pořadí
     *   - 'callback' / 'schuzka' samostatně → po prvním výskytu vložíme 'plan'
     *   - 'bo' (legacy) zůstává — je to super-tab BO
     *   - 'dokonceno' samostatně → bylo top-level, teď je sub-tab BO super-tabu;
     *     po prvním výskytu vložíme 'bo' (pokud tam ještě není)
     *
     * @return list<string>
     */
    private function getTabOrder(int $userId): array
    {
        $this->ensureTabPrefsTable();
        $stmt = $this->pdo->prepare(
            "SELECT tab_order FROM oz_tab_prefs WHERE user_id = :uid LIMIT 1"
        );
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['tab_order'] === null) {
            return [];
        }
        $decoded = json_decode((string) $row['tab_order'], true);
        if (!is_array($decoded)) { return []; }

        $raw = array_values(array_map('strval', $decoded));
        // Migrace: callback/schuzka samostatně → vložit 'plan' tam, kde poprvé byly
        //          dokonceno samostatně (legacy) → vložit 'bo' tam, kde poprvé bylo
        $seen = [];
        $out  = [];
        foreach ($raw as $tk) {
            if ($tk === 'callback' || $tk === 'schuzka') {
                if (!in_array('plan', $seen, true)) { $out[] = 'plan'; $seen[] = 'plan'; }
                continue; // sub-tab pořadí řešíme zvlášť (sub_order)
            }
            if ($tk === 'bo_predano' || $tk === 'bo_vraceno' || $tk === 'dokonceno') {
                if (!in_array('bo', $seen, true)) { $out[] = 'bo'; $seen[] = 'bo'; }
                continue;
            }
            if (in_array($tk, $seen, true)) { continue; }
            $seen[] = $tk;
            $out[]  = $tk;
        }
        return $out;
    }

    /**
     * Vrátí per-user pořadí sub-tabů uvnitř super-tabů.
     * Vrací: ['plan' => ['callback','schuzka'], 'bo' => ['bo_predano','bo_vraceno','dokonceno']]
     *
     * @return array<string, list<string>>
     */
    private function getSubTabOrder(int $userId): array
    {
        $this->ensureTabPrefsTable();
        $stmt = $this->pdo->prepare(
            "SELECT sub_tab_order FROM oz_tab_prefs WHERE user_id = :uid LIMIT 1"
        );
        try {
            $stmt->execute(['uid' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException) {
            return [];
        }
        if (!$row || empty($row['sub_tab_order'])) { return []; }
        $decoded = json_decode((string) $row['sub_tab_order'], true);
        if (!is_array($decoded)) { return []; }
        $out = [];
        foreach ($decoded as $group => $items) {
            if (!is_array($items)) { continue; }
            $out[(string)$group] = array_values(array_map('strval', $items));
        }
        return $out;
    }

    /**
     * Uloží seznam skrytých tabů pro daného OZ uživatele.
     */
    private function setHiddenTabs(int $userId, array $hidden): void
    {
        $this->ensureTabPrefsTable();
        $allowed = ['nabidka', 'callback', 'schuzka', 'sance',
                    'bo_predano', 'bo_vraceno', 'dokonceno',
                    'reklamace', 'nezajem'];
        $clean   = array_values(array_intersect(
            $allowed,
            array_unique(array_map('strval', $hidden))
        ));
        $json = json_encode($clean, JSON_UNESCAPED_UNICODE) ?: '[]';
        $this->pdo->prepare(
            "INSERT INTO oz_tab_prefs (user_id, hidden_tabs)
             VALUES (:uid, :json)
             ON DUPLICATE KEY UPDATE hidden_tabs = :json2, updated_at = NOW(3)"
        )->execute(['uid' => $userId, 'json' => $json, 'json2' => $json]);
    }

    /**
     * Uloží pořadí tabů pro daného OZ uživatele (top-level — atomické + super-taby).
     */
    private function setTabOrder(int $userId, array $order): void
    {
        $this->ensureTabPrefsTable();
        $allowed = ['nove', 'nabidka', 'plan', 'callback', 'schuzka', 'sance',
                    'bo', 'bo_predano', 'bo_vraceno', 'dokonceno',
                    'reklamace', 'nezajem'];
        $clean   = array_values(array_intersect(
            array_unique(array_map('strval', $order)),
            $allowed
        ));
        $json = json_encode($clean, JSON_UNESCAPED_UNICODE) ?: '[]';
        $this->pdo->prepare(
            "INSERT INTO oz_tab_prefs (user_id, hidden_tabs, tab_order)
             VALUES (:uid, '[]', :json)
             ON DUPLICATE KEY UPDATE tab_order = :json2, updated_at = NOW(3)"
        )->execute(['uid' => $userId, 'json' => $json, 'json2' => $json]);
    }

    /**
     * Uloží per-user pořadí sub-tabů uvnitř super-tabů.
     * Akceptuje strukturu: ['plan' => [...], 'bo' => [...]]
     */
    private function setSubTabOrder(int $userId, array $subs): void
    {
        $this->ensureTabPrefsTable();
        $whitelist = [
            'plan' => ['callback', 'schuzka'],
            'bo'   => ['bo_predano', 'bo_vraceno', 'dokonceno'],
        ];
        $clean = [];
        foreach ($whitelist as $group => $allowed) {
            $given = isset($subs[$group]) && is_array($subs[$group]) ? $subs[$group] : [];
            $given = array_values(array_intersect(
                array_unique(array_map('strval', $given)),
                $allowed
            ));
            // Doplnit chybějící v default pořadí (kdyby JS poslal neúplný seznam)
            foreach ($allowed as $sub) {
                if (!in_array($sub, $given, true)) { $given[] = $sub; }
            }
            $clean[$group] = $given;
        }
        $json = json_encode($clean, JSON_UNESCAPED_UNICODE) ?: '{}';
        $this->pdo->prepare(
            "INSERT INTO oz_tab_prefs (user_id, hidden_tabs, sub_tab_order)
             VALUES (:uid, '[]', :json)
             ON DUPLICATE KEY UPDATE sub_tab_order = :json2, updated_at = NOW(3)"
        )->execute(['uid' => $userId, 'json' => $json, 'json2' => $json]);
    }

    /**
     * Tabulka pro Pracovní deník akcí OZ (BO informace o postupu zakázky).
     * Každý záznam = datum + krátký popis (co se kdy udělalo).
     */
    private function ensureActionsTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS `oz_contact_actions` (
              `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `contact_id`  BIGINT UNSIGNED NOT NULL,
              `oz_id`       INT UNSIGNED NOT NULL,
              `action_date` DATE NOT NULL,
              `action_text` TEXT NOT NULL,
              `created_at`  DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
              PRIMARY KEY (`id`),
              KEY `idx_actions_contact` (`contact_id`, `action_date`),
              KEY `idx_actions_oz_contact` (`oz_id`, `contact_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * Tabulky pro nabídnuté služby (Fáze 1 — read-only).
     * services        — hlavní řádek nabídky (typ, tarif, modem, cena)
     * service_items   — konkrétní telefonní čísla / adresy s OKU kódem
     */
    private function ensureOfferedServicesTables(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS `oz_contact_offered_services` (
              `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `contact_id`    BIGINT UNSIGNED NOT NULL,
              `oz_id`         INT UNSIGNED NOT NULL,
              `service_type`  ENUM('mobil','internet','tv','data') NOT NULL,
              `service_label` VARCHAR(160) NOT NULL,
              `modem_label`   VARCHAR(160) NULL DEFAULT NULL,
              `price_monthly` DECIMAL(10,2) NULL DEFAULT NULL,
              `note`          TEXT NULL DEFAULT NULL,
              `created_at`    DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
              `updated_at`    DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
              PRIMARY KEY (`id`),
              KEY `idx_offered_contact` (`contact_id`),
              KEY `idx_offered_oz_contact` (`oz_id`, `contact_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS `oz_contact_offered_service_items` (
              `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `service_id`     BIGINT UNSIGNED NOT NULL,
              `identifier`     VARCHAR(255) NOT NULL,
              `oku_code`       VARCHAR(64) NULL DEFAULT NULL,
              `oku_filled_at`  DATETIME(3) NULL DEFAULT NULL,
              `created_at`     DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
              `updated_at`     DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
              PRIMARY KEY (`id`),
              KEY `idx_offered_item_service` (`service_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function ensureStagesTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS `oz_team_stages` (
              `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `year`         SMALLINT UNSIGNED NOT NULL,
              `month`        TINYINT UNSIGNED NOT NULL,
              `stage_number` TINYINT UNSIGNED NOT NULL,
              `label`        VARCHAR(100) NOT NULL DEFAULT '',
              `target_bmsl`  DECIMAL(12,2) NOT NULL,
              `created_at`   DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_oz_stage` (`year`, `month`, `stage_number`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function ensurePersonalMilestonesTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS `oz_personal_milestones` (
              `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `oz_id`       INT UNSIGNED NOT NULL,
              `year`        SMALLINT UNSIGNED NOT NULL,
              `month`       TINYINT UNSIGNED NOT NULL,
              `label`       VARCHAR(100) NOT NULL DEFAULT '',
              `target_bmsl` DECIMAL(12,2) NOT NULL,
              `reward_note` VARCHAR(200) NOT NULL DEFAULT '',
              `created_at`  DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
              PRIMARY KEY (`id`),
              KEY `idx_oz_pm` (`oz_id`, `year`, `month`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function czechMonthName(int $m): string
    {
        return [
            1 => 'Leden', 2 => 'Únor', 3 => 'Březen', 4 => 'Duben',
            5 => 'Květen', 6 => 'Červen', 7 => 'Červenec', 8 => 'Srpen',
            9 => 'Září', 10 => 'Říjen', 11 => 'Listopad', 12 => 'Prosinec',
        ][$m] ?? (string) $m;
    }

    // ════════════════════════════════════════════════════════════════
    //  REFACTOR — nová queue obrazovka /oz/queue (Krok 1)
    //
    //  Filozofie: oddělit "incoming queue" od "active work" (call screen).
    //  Tato obrazovka je jen příjem leadů + akce Přijmout, NIC víc.
    //  Stará /oz/leads zůstává funkční až do dokončení migrace (Krok 5).
    //
    //  POZN: Žádné DB změny, žádné nové tabulky. Reuse stávajících queries.
    // ════════════════════════════════════════════════════════════════

    /**
     * GET /oz/queue
     *
     * Načte 3 sekce:
     *   1) Renewal alerty (smlouvy končící do 180 dní)
     *   2) Pending leady od navolávaček (přijímání)
     *
     * Žádné statistiky, žádný závod, žádné taby — to je v /oz dashboardu.
     */
    public function getQueue(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        $ozId  = (int) $user['id'];
        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();

        $this->ensureWorkflowTable();

        // ── 1) Pending leady (čekají na přijetí OZ) ──
        // Stejný princip jako v getLeads(): assigned_sales_id = OZ,
        // stav='CALLED_OK', BEZ záznamu v oz_contact_workflow.
        // Po načtení sgrupujeme do $pendingByCaller (sekce per navolávačka)
        // pro lepší přehled — OZ vidí "Od koho" agregovaně.
        $pendingLeads = [];
        try {
            $pStmt = $this->pdo->prepare(
                "SELECT c.id, c.firma, c.region, c.telefon, c.email, c.ico,
                        c.adresa, c.datum_volani,
                        COALESCE(cu.jmeno, '—')              AS caller_name,
                        COALESCE(c.assigned_caller_id, 0)    AS caller_id,
                        cn.note                              AS caller_note
                 FROM contacts c
                 LEFT JOIN users cu ON cu.id = c.assigned_caller_id
                 LEFT JOIN oz_contact_workflow w
                        ON w.contact_id = c.id AND w.oz_id = :ozid
                 LEFT JOIN (
                     SELECT contact_id, note
                     FROM (
                         SELECT contact_id, note,
                                ROW_NUMBER() OVER (PARTITION BY contact_id ORDER BY created_at DESC) AS rn
                         FROM contact_notes
                     ) t WHERE rn = 1
                 ) cn ON cn.contact_id = c.id
                 WHERE c.assigned_sales_id = :ozid2
                   AND c.stav = 'CALLED_OK'
                   AND w.id IS NULL
                 ORDER BY c.datum_volani DESC
                 LIMIT 50"
            );
            $pStmt->execute(['ozid' => $ozId, 'ozid2' => $ozId]);
            $pendingLeads = $pStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            // Pokud window function ROW_NUMBER nebo contact_notes neexistuje,
            // fallback na jednodušší dotaz bez poznámky.
            crm_db_log_error($e, __METHOD__);
            try {
                $pStmt = $this->pdo->prepare(
                    "SELECT c.id, c.firma, c.region, c.telefon, c.email, c.ico,
                            c.adresa, c.datum_volani,
                            COALESCE(cu.jmeno, '—')              AS caller_name,
                            COALESCE(c.assigned_caller_id, 0)    AS caller_id,
                            NULL                                  AS caller_note
                     FROM contacts c
                     LEFT JOIN users cu ON cu.id = c.assigned_caller_id
                     LEFT JOIN oz_contact_workflow w
                            ON w.contact_id = c.id AND w.oz_id = :ozid
                     WHERE c.assigned_sales_id = :ozid2
                       AND c.stav = 'CALLED_OK'
                       AND w.id IS NULL
                     ORDER BY c.datum_volani DESC
                     LIMIT 50"
                );
                $pStmt->execute(['ozid' => $ozId, 'ozid2' => $ozId]);
                $pendingLeads = $pStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\PDOException $e2) {
                crm_db_log_error($e2, __METHOD__);
            }
        }

        // ── 1b) Region filter (klikatelný v UI, URL ?region=...) ──
        // Souhrn krajů (regionCounts) se ZACHOVÁVÁ z ALL pending — UI tak ukáže
        // všechny dostupné kraje pro filtr i když je některý filtrovaný pryč.
        $regionCountsAll = [];
        foreach ($pendingLeads as $p) {
            $reg = (string) ($p['region'] ?? '');
            if ($reg === '') continue;
            if (!isset($regionCountsAll[$reg])) $regionCountsAll[$reg] = 0;
            $regionCountsAll[$reg]++;
        }
        arsort($regionCountsAll);

        // Aplikovat region filter pokud je v GET
        $selectedRegion = (string) ($_GET['region'] ?? '');
        if ($selectedRegion !== '' && !isset($regionCountsAll[$selectedRegion])) {
            $selectedRegion = ''; // neplatný region → ignorovat
        }
        if ($selectedRegion !== '') {
            $pendingLeads = array_values(array_filter(
                $pendingLeads,
                static fn($p) => (string) ($p['region'] ?? '') === $selectedRegion
            ));
        }

        // ── 1c) Sgrupovat pending podle navolávačky — sekce per caller ──
        // Struktura: [['caller_id', 'caller_name', 'is_manual', 'contacts' => [...], 'count'], ...]
        // Pořadí: navolávačky podle počtu leadů DESC (nejvíc aktivní nahoře),
        // tie-break podle abecedy. Manuální (caller_id = 0) jdou vždy nakonec.
        //
        // Manuální = kontakt vznikl s assigned_caller_id NULL → buď ho přidal
        // přímo majitel/admin přes "+ Nový kontakt", nebo byl schválený z návrhu.
        // V queue se zobrazí jako samostatná skupina "Přidáno přímo", aby OZ
        // nepřehlédl, že nejde o klasický lead od navolávačky.
        $pendingByCaller = [];
        if ($pendingLeads !== []) {
            $byCaller = [];
            foreach ($pendingLeads as $p) {
                $key      = (int) $p['caller_id'];
                $isManual = ($key === 0);
                if (!isset($byCaller[$key])) {
                    $byCaller[$key] = [
                        'caller_id'   => $key,
                        'caller_name' => $isManual
                            ? 'Přidáno přímo (majitel / schválený návrh)'
                            : (string) ($p['caller_name'] ?? '—'),
                        'is_manual'   => $isManual,
                        'contacts'    => [],
                        'count'       => 0,
                    ];
                }
                $byCaller[$key]['contacts'][] = $p;
                $byCaller[$key]['count']++;
            }
            // Sort: manuální vždy poslední, jinak count DESC, then name ASC
            usort($byCaller, static function ($a, $b) {
                if ($a['is_manual'] !== $b['is_manual']) {
                    return $a['is_manual'] ? 1 : -1; // manuální → nakonec
                }
                if ($a['count'] !== $b['count']) return $b['count'] - $a['count'];
                return strcmp($a['caller_name'], $b['caller_name']);
            });
            $pendingByCaller = array_values($byCaller);
        }

        // ── 1d) Souhrn podle krajů pro filtr UI ──
        // Pro filtr UI použijeme regionCountsAll (všechny pending kraje, bez filtru),
        // aby uživatel viděl i kraje, ze kterých si nic nevybral. Pokud je filter
        // aktivní, $regionCounts (filtered) se v UI nepoužije, ale necháváme pro
        // možnou budoucí potřebu (např. zobrazení "X po filtru").
        $regionCounts = $regionCountsAll;

        // ── 2) Renewal alerty (kontakty s blížícím se koncem smlouvy) ──
        // Stejný query jako v getLeads(), ale limit 20 (queue přehled).
        $renewals = [];
        try {
            $rnStmt = $this->pdo->prepare(
                "SELECT id, firma, vyrocni_smlouvy,
                        DATEDIFF(vyrocni_smlouvy, CURDATE()) AS days_until
                 FROM contacts
                 WHERE assigned_sales_id = :ozid
                   AND vyrocni_smlouvy IS NOT NULL
                   AND vyrocni_smlouvy >= CURDATE()
                   AND vyrocni_smlouvy <= CURDATE() + INTERVAL 30 DAY
                 ORDER BY vyrocni_smlouvy ASC
                 LIMIT 20"
            );
            $rnStmt->execute(['ozid' => $ozId]);
            $renewals = $rnStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }

        // ── 3) Počet rozpracovaných leadů (pro empty state hint) ──
        // = leady které OZ už přijal (mají workflow záznam) a stav je
        // aktivní (NOVE, ZPRACOVAVA, NABIDKA, SCHUZKA, CALLBACK, SANCE).
        // Když pending je 0, chceme uživateli ukázat "máš X rozpracovaných".
        $inProgressCount = 0;
        try {
            $ipStmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM contacts c
                 JOIN oz_contact_workflow w ON w.contact_id = c.id AND w.oz_id = :ozid
                 WHERE c.assigned_sales_id = :ozid2
                   AND w.stav IN ('NOVE','ZPRACOVAVA','NABIDKA','SCHUZKA','CALLBACK','SANCE')"
            );
            $ipStmt->execute(['ozid' => $ozId, 'ozid2' => $ozId]);
            $inProgressCount = (int) $ipStmt->fetchColumn();
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }

        $title = 'Příchozí leady';
        ob_start();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views'
              . DIRECTORY_SEPARATOR . 'oz' . DIRECTORY_SEPARATOR . 'queue.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views'
              . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'base.php';
    }

    /**
     * POST /oz/queue/accept
     *
     * Stejná logika jako postAcceptLead(), JEN s redirektem zpět na /oz/queue
     * místo /oz/leads. Důvod separace: stará /oz/leads flow zůstává netknutá.
     * Až bude hotová /oz/work?id=X obrazovka (Krok 2), redirect se přepne tam.
     */
    public function postQueueAcceptLead(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/oz/queue');
        }

        $this->ensureWorkflowTable();
        $ozId      = (int) $user['id'];
        $contactId = (int) ($_POST['contact_id'] ?? 0);

        $cStmt = $this->pdo->prepare(
            "SELECT id, firma FROM contacts
             WHERE id = :cid AND assigned_sales_id = :ozid AND stav = 'CALLED_OK'"
        );
        $cStmt->execute(['cid' => $contactId, 'ozid' => $ozId]);
        $contact = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$contact) {
            crm_flash_set('Kontakt nenalezen nebo již byl zpracován.');
            crm_redirect('/oz/queue');
        }

        $this->pdo->prepare(
            "INSERT INTO oz_contact_workflow
               (contact_id, oz_id, stav, started_at, updated_at)
             VALUES (:cid, :oid, 'NOVE', NOW(3), NOW(3))
             ON DUPLICATE KEY UPDATE
               stav       = IF(stav IS NULL, 'NOVE', stav),
               started_at = COALESCE(started_at, NOW(3)),
               updated_at = NOW(3)"
        )->execute(['cid' => $contactId, 'oid' => $ozId]);

        crm_flash_set('✓ Lead přijat — ' . (string)($contact['firma'] ?? ''));

        // Krok 2: po přijetí leadu otevřít call screen (focused work mode).
        crm_redirect('/oz/work?id=' . $contactId);
    }

    // ════════════════════════════════════════════════════════════════
    //  REFACTOR — call screen /oz/work?id=X (Krok 2)
    //
    //  Filozofie: jeden lead na obrazovce, NIC víc. Žádné statistiky,
    //  závody, milníky, taby. Po akci se OZ vrátí do /oz/queue
    //  pro další lead (nebo zůstane na current contact).
    //
    //  Pro komplexní stavy (BO_PREDANO s nabídkou, SMLOUVA s instalačními
    //  adresami, SANCE s BMSL) je k dispozici link "Plná pracovní plocha"
    //  → /oz/leads, kde existuje stávající (komplexní) UI.
    // ════════════════════════════════════════════════════════════════

    /**
     * GET /oz/work?id=X
     *
     * Call screen pro 1 lead. Načte:
     *   - kontakt (firma, tel, email, IČO, adresa, region)
     *   - aktuální oz_contact_workflow stav
     *   - poslední poznámku (pro continuity zobrazení)
     *
     * Validace: contact_id musí patřit tomuto OZ A musí mít workflow záznam
     * (= byl přijat). Pokud ne → redirect na /oz/queue.
     */
    public function getWork(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        $ozId      = (int) $user['id'];
        $contactId = (int) ($_GET['id'] ?? 0);
        $flash     = crm_flash_take();
        $csrf      = crm_csrf_token();

        if ($contactId <= 0) {
            crm_redirect('/oz/queue');
        }

        $this->ensureWorkflowTable();
        $this->ensureNotesTable();

        // Načíst kontakt + workflow stav. Lead musí patřit OZ a mít workflow
        // záznam (= byl přijat z queue).
        // POZN: sloupec contacts.poznamka = poznámka od navolávačky (caller_poznamka).
        $stmt = $this->pdo->prepare(
            "SELECT c.id, c.firma, c.region, c.telefon, c.email, c.ico,
                    c.adresa, c.datum_volani,
                    c.poznamka                AS caller_poznamka,
                    w.stav                    AS oz_stav,
                    w.callback_at,
                    w.schuzka_at,
                    w.poznamka                AS last_poznamka,
                    w.updated_at              AS oz_updated_at
             FROM contacts c
             JOIN oz_contact_workflow w
                  ON w.contact_id = c.id AND w.oz_id = :ozid
             WHERE c.id = :cid AND c.assigned_sales_id = :ozid2
             LIMIT 1"
        );
        $stmt->execute(['cid' => $contactId, 'ozid' => $ozId, 'ozid2' => $ozId]);
        $contact = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$contact) {
            crm_flash_set('Lead nenalezen nebo nebyl přijat.');
            crm_redirect('/oz/queue');
        }

        // Recent notes (posledních 5) — kontext pro OZ aby viděl co tu naposled napsal
        $recentNotes = [];
        try {
            $nStmt = $this->pdo->prepare(
                "SELECT note, created_at
                 FROM oz_contact_notes
                 WHERE contact_id = :cid AND oz_id = :oid
                 ORDER BY created_at DESC
                 LIMIT 5"
            );
            $nStmt->execute(['cid' => $contactId, 'oid' => $ozId]);
            $recentNotes = $nStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }

        // Počet zbývajících pending leadů — pro „Další lead" tlačítko
        $remainingPending = 0;
        try {
            $rStmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM contacts c
                 LEFT JOIN oz_contact_workflow w
                        ON w.contact_id = c.id AND w.oz_id = :ozid
                 WHERE c.assigned_sales_id = :ozid2
                   AND c.stav = 'CALLED_OK'
                   AND w.id IS NULL"
            );
            $rStmt->execute(['ozid' => $ozId, 'ozid2' => $ozId]);
            $remainingPending = (int) $rStmt->fetchColumn();
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }

        $title = 'Práce — ' . (string)($contact['firma'] ?? '');
        ob_start();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views'
              . DIRECTORY_SEPARATOR . 'oz' . DIRECTORY_SEPARATOR . 'work.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views'
              . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'base.php';
    }

    /**
     * POST /oz/work/quick-status
     *
     * Rychlé akce na call screenu — JEN jednoduché stavy:
     *   - NABIDKA, SCHUZKA, CALLBACK, NEZAJEM, NOTE_ONLY
     *
     * Pro komplexní stavy (BO_PREDANO, SMLOUVA, SANCE) se použije link
     * "Plná pracovní plocha" → /oz/leads (existující UI).
     *
     * Po akci redirect:
     *   - return_to=queue (default): /oz/queue (další lead)
     *   - return_to=stay: /oz/work?id=X (zůstat na current contact)
     */
    public function postWorkQuickStatus(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/oz/queue');
        }

        $this->ensureWorkflowTable();
        $this->ensureNotesTable();
        $this->ensureFlagsTable(); // pro REKLAMACE — vytváříme flag pro navolávačku

        $ozId       = (int) $user['id'];
        $contactId  = (int) ($_POST['contact_id'] ?? 0);
        $newStav    = (string) ($_POST['oz_stav'] ?? '');
        $poznamka   = trim((string) ($_POST['oz_poznamka'] ?? ''));
        $callbackAt = trim((string) ($_POST['callback_at'] ?? ''));
        $schuzkaAt  = trim((string) ($_POST['schuzka_at'] ?? ''));
        $bmslRaw    = trim((string) ($_POST['bmsl'] ?? ''));
        $nabidkaId  = trim((string) ($_POST['nabidka_id'] ?? ''));
        $returnTo   = (string) ($_POST['return_to'] ?? 'queue');

        // POVOLENÉ stavy pro quick-status (subset z postLeadStatus):
        $allowedQuick = ['NABIDKA', 'SCHUZKA', 'CALLBACK', 'NEZAJEM', 'NOTE_ONLY', 'SANCE', 'BO_PREDANO', 'REKLAMACE'];
        if (!in_array($newStav, $allowedQuick, true)) {
            crm_flash_set('Tato akce vyžaduje plnou pracovní plochu.');
            crm_redirect('/oz/work?id=' . $contactId);
        }

        // Ověření že kontakt patří tomuto OZ a má workflow záznam (byl přijat).
        $cStmt = $this->pdo->prepare(
            "SELECT c.id, c.firma
             FROM contacts c
             JOIN oz_contact_workflow w ON w.contact_id = c.id AND w.oz_id = :oid
             WHERE c.id = :cid AND c.assigned_sales_id = :oid2
             LIMIT 1"
        );
        $cStmt->execute(['cid' => $contactId, 'oid' => $ozId, 'oid2' => $ozId]);
        $contact = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$contact) {
            crm_flash_set('Lead nenalezen.');
            crm_redirect('/oz/queue');
        }
        $firma = (string)($contact['firma'] ?? '');
        $firmaLabel = $firma !== '' ? ' — ' . $firma : '';

        // Načti starý workflow stav PRO audit log (před UPDATE)
        $oldStavStmt = $this->pdo->prepare(
            "SELECT stav FROM oz_contact_workflow WHERE contact_id = :cid AND oz_id = :oid LIMIT 1"
        );
        $oldStavStmt->execute(['cid' => $contactId, 'oid' => $ozId]);
        $oldStav = (string) ($oldStavStmt->fetchColumn() ?: '');

        // Poznámka je povinná u všech stavů (kromě explicit prázdné NOTE_ONLY).
        if ($poznamka === '' && $newStav !== 'NOTE_ONLY') {
            crm_flash_set('⚠ Nejdříve vyplňte poznámku.');
            crm_redirect('/oz/work?id=' . $contactId);
        }
        if ($newStav === 'NOTE_ONLY' && $poznamka === '') {
            crm_flash_set('⚠ Poznámka nesmí být prázdná.');
            crm_redirect('/oz/work?id=' . $contactId);
        }

        // Validace datumů u CALLBACK / SCHUZKA
        $cbVal = null;
        if ($newStav === 'CALLBACK') {
            if ($callbackAt === '') {
                crm_flash_set('⚠ Vyplňte datum a čas callbacku.');
                crm_redirect('/oz/work?id=' . $contactId);
            }
            $cbVal = $callbackAt;
        }
        $saVal = null;
        if ($newStav === 'SCHUZKA') {
            if ($schuzkaAt === '') {
                crm_flash_set('⚠ Vyplňte datum a čas schůzky.');
                crm_redirect('/oz/work?id=' . $contactId);
            }
            $saVal = $schuzkaAt;
        }

        // Validace BMSL pro SANCE a BO_PREDANO (parsuje "1199" → 1100, zaokrouhlí
        // na stokoruny dolů). Stejná logika jako v postLeadStatus / postSetOfferId.
        $bmslVal = null;
        if ($newStav === 'SANCE' || $newStav === 'BO_PREDANO') {
            $bmslNumeric = (float) str_replace([' ', ','], ['', '.'], $bmslRaw);
            if ($bmslRaw === '' || !is_finite($bmslNumeric) || $bmslNumeric < 100) {
                crm_flash_set('⚠ Vyplň BMSL (Kč bez DPH, alespoň 100).');
                crm_redirect('/oz/work?id=' . $contactId);
            }
            $bmslVal = (int) (floor($bmslNumeric / 100) * 100);
        }
        // Validace nabidka_id pro BO_PREDANO (povinné)
        if ($newStav === 'BO_PREDANO') {
            if ($nabidkaId === '') {
                crm_flash_set('⚠ Pro předání BO je nutné vyplnit ID nabídky.');
                crm_redirect('/oz/work?id=' . $contactId);
            }
            if (mb_strlen($nabidkaId) > 80) {
                crm_flash_set('⚠ ID nabídky je příliš dlouhé (max 80 znaků).');
                crm_redirect('/oz/work?id=' . $contactId);
            }
        }

        // Uložit poznámku do historie (pokud něco je)
        if ($poznamka !== '') {
            $this->pdo->prepare(
                'INSERT INTO oz_contact_notes (contact_id, oz_id, note)
                 VALUES (:cid, :oid, :note)'
            )->execute(['cid' => $contactId, 'oid' => $ozId, 'note' => $poznamka]);
        }

        // NOTE_ONLY: jen poznámka, stav beze změny (zůstane ZPRACOVAVA)
        if ($newStav === 'NOTE_ONLY') {
            $this->pdo->prepare(
                "INSERT INTO oz_contact_workflow
                   (contact_id, oz_id, stav, started_at, poznamka, updated_at)
                 VALUES (:cid, :oid, 'ZPRACOVAVA', NOW(3), :poz, NOW(3))
                 ON DUPLICATE KEY UPDATE
                   started_at = COALESCE(started_at, NOW(3)),
                   poznamka   = :poz2,
                   updated_at = NOW(3)"
            )->execute(['cid' => $contactId, 'oid' => $ozId, 'poz' => $poznamka, 'poz2' => $poznamka]);
            crm_flash_set('✓ Poznámka uložena' . $firmaLabel . '.');
            crm_redirect('/oz/work?id=' . $contactId);
        }

        if ($newStav === 'SANCE' || $newStav === 'BO_PREDANO') {
            // Větev pro stavy s bmsl a nabidka_id polém.
            // POZN: Pro BO_PREDANO ukládáme nabidka_id i bmsl. Pro SANCE jen bmsl.
            $this->pdo->prepare(
                'INSERT INTO oz_contact_workflow
                   (contact_id, oz_id, stav, stav_changed_at, started_at, poznamka,
                    bmsl, nabidka_id, updated_at)
                 VALUES
                   (:cid, :oid, :stav, NOW(3), NOW(3), :poz,
                    :bmsl, :nid, NOW(3))
                 ON DUPLICATE KEY UPDATE
                   stav_changed_at = CASE WHEN stav <> :stavNew THEN NOW(3) ELSE stav_changed_at END,
                   stav            = :stav2,
                   started_at      = COALESCE(started_at, NOW(3)),
                   poznamka        = :poz2,
                   bmsl            = :bmsl2,
                   nabidka_id      = CASE WHEN :stav3 = \'BO_PREDANO\' THEN :nid2 ELSE nabidka_id END,
                   updated_at      = NOW(3)'
            )->execute([
                'cid'     => $contactId, 'oid'    => $ozId,
                'stav'    => $newStav,   'poz'    => $poznamka,
                'bmsl'    => $bmslVal,
                'nid'     => $newStav === 'BO_PREDANO' ? $nabidkaId : null,
                'stavNew' => $newStav,
                'stav2'   => $newStav,   'poz2'   => $poznamka,
                'bmsl2'   => $bmslVal,
                'stav3'   => $newStav,
                'nid2'    => $newStav === 'BO_PREDANO' ? $nabidkaId : null,
            ]);
        } elseif ($newStav === 'REKLAMACE') {
            // Větev pro REKLAMACE (chybný lead) — stejná logika jako postReklamace().
            // 1) Workflow stav → REKLAMACE
            $this->pdo->prepare(
                "INSERT INTO oz_contact_workflow
                   (contact_id, oz_id, stav, stav_changed_at, started_at, poznamka, updated_at)
                 VALUES (:cid, :oid, 'REKLAMACE', NOW(3), NOW(3), :poz, NOW(3))
                 ON DUPLICATE KEY UPDATE
                   stav_changed_at = CASE WHEN stav <> 'REKLAMACE' THEN NOW(3) ELSE stav_changed_at END,
                   stav            = 'REKLAMACE',
                   started_at      = COALESCE(started_at, NOW(3)),
                   poznamka        = :poz2,
                   updated_at      = NOW(3)"
            )->execute(['cid' => $contactId, 'oid' => $ozId, 'poz' => $poznamka, 'poz2' => $poznamka]);

            // 2) Flag — viditelný navolávačce (zobrazí se v její stat panelu)
            $this->pdo->prepare(
                'INSERT INTO contact_oz_flags (contact_id, oz_id, reason)
                 VALUES (:cid, :oid, :reason)
                 ON DUPLICATE KEY UPDATE reason = :reason2, flagged_at = NOW(3)'
            )->execute([
                'cid'     => $contactId,
                'oid'     => $ozId,
                'reason'  => $poznamka,
                'reason2' => $poznamka,
            ]);
        } else {
            // Standardní state change pro NABIDKA / SCHUZKA / CALLBACK / NEZAJEM
            $this->pdo->prepare(
                'INSERT INTO oz_contact_workflow
                   (contact_id, oz_id, stav, stav_changed_at, started_at, poznamka,
                    callback_at, schuzka_at, schuzka_acknowledged, updated_at)
                 VALUES
                   (:cid, :oid, :stav, NOW(3), NOW(3), :poz,
                    :cb, :sa, 0, NOW(3))
                 ON DUPLICATE KEY UPDATE
                   stav_changed_at      = CASE WHEN stav <> :stavNew THEN NOW(3) ELSE stav_changed_at END,
                   stav                 = :stav2,
                   started_at           = COALESCE(started_at, NOW(3)),
                   poznamka             = :poz2,
                   callback_at          = :cb2,
                   schuzka_at           = CASE WHEN :stav3 = \'SCHUZKA\' THEN :sa2 ELSE schuzka_at END,
                   schuzka_acknowledged = CASE WHEN :stav4 = \'SCHUZKA\' THEN 0 ELSE schuzka_acknowledged END,
                   updated_at           = NOW(3)'
            )->execute([
                'cid'     => $contactId, 'oid'      => $ozId,
                'stav'    => $newStav,   'poz'      => $poznamka,
                'cb'      => $cbVal,     'sa'       => $saVal,
                'stavNew' => $newStav,
                'stav2'   => $newStav,   'poz2'     => $poznamka,
                'cb2'     => $cbVal,
                'stav3'   => $newStav,   'sa2'      => $saVal,
                'stav4'   => $newStav,
            ]);
        }

        // Audit log workflow změny — zachytí všechny stavy kromě NOTE_ONLY (nezmění stav)
        if ($newStav !== 'NOTE_ONLY' && $oldStav !== $newStav) {
            $logNote = match ($newStav) {
                'NABIDKA'    => 'Nabídka odeslána',
                'SCHUZKA'    => 'Schůzka naplánována' . ($saVal ? ' (' . $saVal . ')' : ''),
                'CALLBACK'   => 'Callback nastaven' . ($cbVal ? ' (' . $cbVal . ')' : ''),
                'NEZAJEM'    => 'Nezájem (OZ)',
                'SANCE'      => 'Šance · BMSL ' . number_format((float)$bmslVal, 0, ',', ' ') . ' Kč',
                'BO_PREDANO' => 'Předáno BO · nabídka ' . $nabidkaId . ' · BMSL ' . number_format((float)$bmslVal, 0, ',', ' ') . ' Kč',
                'REKLAMACE'  => 'Chybný lead (REKLAMACE)',
                default      => 'Stav aktualizován',
            };
            if ($poznamka !== '') {
                $logNote .= ' · ' . $poznamka;
            }
            crm_log_workflow_change($this->pdo, $contactId, $ozId, $oldStav !== '' ? $oldStav : null, $newStav, $logNote);
        }

        $msg = match ($newStav) {
            'NABIDKA'    => '📨 Nabídka odeslána' . $firmaLabel . '.',
            'SCHUZKA'    => '📅 Schůzka naplánována' . $firmaLabel . '.',
            'CALLBACK'   => '📞 Callback nastaven' . $firmaLabel . '.',
            'NEZAJEM'    => 'Označeno jako nezájem' . $firmaLabel . '.',
            'SANCE'      => '⭐ Šance · BMSL ' . number_format((float)$bmslVal, 0, ',', ' ') . ' Kč' . $firmaLabel . '.',
            'BO_PREDANO' => '📤 Předáno BO · ' . $nabidkaId . $firmaLabel . '.',
            'REKLAMACE'  => '⚠ Chybný lead nahlášen' . $firmaLabel . '. Navolávačka bude upozorněna.',
            default      => 'Stav aktualizován' . $firmaLabel . '.',
        };
        crm_flash_set($msg);

        // Redirect podle return_to
        if ($returnTo === 'stay') {
            crm_redirect('/oz/work?id=' . $contactId);
        } else {
            crm_redirect('/oz/queue');
        }
    }
}
