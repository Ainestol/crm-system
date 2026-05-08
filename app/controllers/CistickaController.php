<?php
// e:\Snecinatripu\app\controllers\CistickaController.php
declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'audit.php';

/**
 * Obrazovka čističky:
 *   - Ověřuje kontakty jeden po druhém (firma, telefon, operátor)
 *   - Klikne: VF_SKIP (nechceme) | READY_TM | READY_O2
 *   - TM a O2 → stav READY → viditelné navolávačkám
 *   - VF_SKIP → zmizí z fronty navolávačky, ale zůstane v přehledu čističky
 *   - Přehled "Zkontrolováno" ukazuje vše co udělala dnes (statistika)
 */
final class CistickaController
{
    private const PAGE_SIZE = 50;

    public function __construct(private PDO $pdo)
    {
    }

    /** GET /cisticka */
    public function getIndex(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['cisticka', 'majitel', 'superadmin']);

        $cistickaId = (int) $user['id'];
        $flash      = crm_flash_take();
        $csrf       = crm_csrf_token();
        $tab        = (string) ($_GET['tab'] ?? 'overit');
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $offset     = ($page - 1) * self::PAGE_SIZE;

        // ── Regiony přiřazené čističce (legacy/fallback filter) ──────────────
        $urStmt = $this->pdo->prepare(
            'SELECT region FROM user_regions WHERE user_id = :uid ORDER BY region'
        );
        $urStmt->execute([':uid' => $cistickaId]);
        $userRegions = $urStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $hasUserRegions = $userRegions !== [];

        // ── Měsíční cíle podle krajů (pro progress panel A taky pro filtr) ──
        // Tabulka se autovytvoří při prvním přístupu (CREATE IF NOT EXISTS).
        // Pořadí: ASC priority, ASC region (tie-break abecedně).
        //
        // Měsíční přepínač: ?month_key=YYYY-MM v GET URL umožňuje čističce
        // prohlédnout si historický (nebo budoucí) progress per měsíc.
        // DŮLEŽITÉ: Switcher OVLIVŇUJE POUZE TILES (cíle + progress display).
        // Filter K-ověření tabu (jaké NEW kontakty se zobrazí) se vždy řídí
        // CURRENT MONTH goals — switcher je čistě read-only přehled, ne
        // změna pracovního kontextu.
        $this->ensureRegionGoalsTable();
        $selectedPeriod   = $this->parsePeriodKey((string) ($_GET['month_key'] ?? ''));
        $currentPeriod    = $this->currentPeriodYyyymm();
        $isCurrentPeriod  = ($selectedPeriod === $currentPeriod);
        $isPastPeriod     = ($selectedPeriod < $currentPeriod);
        $isFuturePeriod   = ($selectedPeriod > $currentPeriod);
        $selectedMonthKey = $this->periodToKey($selectedPeriod);
        $monthOptions     = $this->monthOptionsForGoals();

        // Tiles & progress pro VYBRANÝ měsíc (může být minulý / budoucí).
        $regionGoals = $this->loadRegionGoalsWithProgress($cistickaId, $selectedPeriod);
        $monthLabel  = $this->monthLabelFromPeriod($selectedPeriod);

        // Filter pro K-ověření tab — VŽDY z CURRENT month goals.
        // Když čistička přepne na duben (read-only), K-ověření jí stále
        // nabídne kontakty pro KVĚTEN — protože v dubnu už se nic nečistí.
        if ($isCurrentPeriod) {
            // Optimalizace: nevolat znovu DB, použít už načtené $regionGoals
            $currentRegionGoals = $regionGoals;
        } else {
            $currentRegionGoals = $this->loadRegionGoalsWithProgress($cistickaId, $currentPeriod);
        }

        // Goal-driven regions: kraje, kde má čistička v AKTUÁLNÍM měsíci
        // nastavený cíl (target > 0). Tyto OVERRIDE user_regions filter
        // pro K-ověření tab (NIKOLI pro tiles — ty jsou ze switched-to období).
        //
        // ROZDĚLENO na DVĚ kategorie:
        //   $goalRegions     — AKTIVNÍ (target > 0 a JEŠTĚ NESPLNĚNÉ) → filtr K-ověření
        //   $goalRegionsAll  — VŠECHNY s cílem (vč. splněných)        → tile UI
        //   $completedRegions — splněné (done >= target)               → strict empty když user klikne
        //
        // Důsledek: jakmile čistička splní 10/10 v Praze, kontakty z Prahy se
        // přestanou objevovat ve fronte K ověření. Tile "Praha 10/10 ✓ Hotovo"
        // zůstává viditelný (pro přehled), ale klikem se nic nenahraje.
        /** @var list<string> $goalRegions */
        $goalRegions      = [];
        /** @var list<string> $goalRegionsAll */
        $goalRegionsAll   = [];
        /** @var list<string> $completedRegions */
        $completedRegions = [];
        foreach ($currentRegionGoals as $g) {
            $tgt = (int) ($g['target'] ?? 0);
            if ($tgt <= 0) continue;
            $reg = (string) $g['region'];
            $goalRegionsAll[] = $reg;
            if (!empty($g['completed'])) {
                $completedRegions[] = $reg;
            } else {
                $goalRegions[] = $reg;   // aktivní (= ještě nesplněné)
            }
        }
        $hasGoals = $goalRegions !== [];

        // Effective region filter — řídí, jaké kontakty se zobrazí.
        //
        // Zkontrolováno (historický pohled): VŽDY user_regions, NIKDY aktuální goals.
        //   Důvod: cisticka má vidět svou historii práce, i když admin přepsal
        //   goals (např. minulý měsíc měla goal pro Olomoucký, teď ne — záznamy
        //   musí zůstat viditelné).
        // K-ověření (aktuální fronta): goals → goalRegions, bez goals → STRICT empty.
        if ($tab === 'zkontrolovano') {
            $effectiveRegions = $userRegions; // historický pohled
        } elseif ($hasGoals) {
            $effectiveRegions = $goalRegions;
        } else {
            $effectiveRegions = []; // K-ověření strict mode bez goals
        }
        $hasEffectiveRegions = $effectiveRegions !== [];

        // Dostupné regiony pro filtr UI (tiles čističky a starý spodní filtr).
        // Když existují goals → goalRegionsAll (vč. splněných — tile "Praha 10/10 ✓"
        // musí být vidět i po splnění, aby čistička viděla, že kraj je hotový).
        // Jinak fallback: regiony s NEW kontaktem v rámci user_regions.
        if ($goalRegionsAll !== []) {
            $availableRegions = $goalRegionsAll;
        } elseif ($hasUserRegions) {
            $placeholders = implode(',', array_fill(0, count($userRegions), '?'));
            $avStmt = $this->pdo->prepare(
                "SELECT DISTINCT region FROM contacts
                 WHERE stav = 'NEW' AND region IN ($placeholders) AND region != ''
                 ORDER BY region"
            );
            $avStmt->execute($userRegions);
            /** @var list<string> $availableRegions */
            $availableRegions = $avStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } else {
            $avStmt = $this->pdo->query(
                "SELECT DISTINCT region FROM contacts WHERE stav = 'NEW' AND region != '' ORDER BY region"
            );
            $availableRegions = $avStmt ? ($avStmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];
        }

        // Vybraný region (GET param, validace)
        $selectedRegion = (string) ($_GET['region'] ?? '');
        if ($selectedRegion !== '' && !in_array($selectedRegion, $availableRegions, true)) {
            $selectedRegion = '';
        }

        // ── Počty kontaktů per region pro tile / filtr badges ────────────────
        // K ověření:    COUNT NEW per region
        // Zkontrolováno: COUNT kontaktů ověřených touto čističkou per region
        /** @var array<string,int> $regionCounts */
        $regionCounts = [];
        if ($availableRegions !== []) {
            $ph2 = implode(',', array_fill(0, count($availableRegions), '?'));
            if ($tab === 'zkontrolovano') {
                $rcStmt = $this->pdo->prepare(
                    "SELECT c.region, COUNT(DISTINCT c.id) AS cnt
                     FROM contacts c
                     JOIN workflow_log wl ON wl.contact_id = c.id
                     WHERE wl.user_id = ?
                       AND wl.new_status IN ('READY', 'VF_SKIP')
                       AND c.region IN ($ph2)
                     GROUP BY c.region"
                );
                $rcStmt->execute(array_merge([$cistickaId], $availableRegions));
            } else {
                $rcStmt = $this->pdo->prepare(
                    "SELECT region, COUNT(*) AS cnt
                     FROM contacts
                     WHERE stav = 'NEW' AND region IN ($ph2)
                     GROUP BY region"
                );
                $rcStmt->execute($availableRegions);
            }
            foreach ($rcStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $regionCounts[(string) $row['region']] = (int) $row['cnt'];
            }
        }

        // ── Helper: přidá AND region IN (?, ...) do WHERE ────────────────────
        // Použije effectiveRegions (goals > user_regions > nic).
        // Pokud je vybraný konkrétní region, ten má přednost (přepíše).
        $regionParams = $hasEffectiveRegions ? $effectiveRegions : [];
        if ($selectedRegion !== '') {
            $regionParams = [$selectedRegion];
        }
        $regionInSql = '';
        if ($regionParams !== []) {
            $ph          = implode(',', array_fill(0, count($regionParams), '?'));
            $regionInSql = "AND region IN ($ph)";
        }

        // Strict mode: prázdný list bez SQL query, když:
        //   1) K-ověření tab + žádné goals → bez goals čistička nemá co dělat
        //   2) K-ověření tab + user vybral SPLNĚNÝ region → kraj má 10/10, žádné
        //      další kontakty se neobjevují (byť ve frontě by ještě fyzicky byly).
        //
        // Tile "Praha 10/10 ✓" zůstává klikatelné, ale klik vrátí empty + hlášku.
        $clickedCompleted = ($tab === 'overit'
                             && $selectedRegion !== ''
                             && in_array($selectedRegion, $completedRegions, true));
        $strictEmpty = ($tab === 'overit' && !$hasGoals) || $clickedCompleted;

        if ($strictEmpty) {
            $contacts   = [];
            $totalCount = 0;
        } elseif ($tab === 'zkontrolovano') {
            // Bere POUZE poslední workflow_log záznam pro daného uživatele a kontakt,
            // takže když čistička přepnula stejný kontakt 3× (TM → O2 → VF),
            // v seznamu se objeví jen jednou s nejnovějším časem ověření.
            $regionFilter = $regionParams !== []
                ? 'AND c.region IN (' . implode(',', array_fill(0, count($regionParams), '?')) . ')'
                : '';
            $stmt = $this->pdo->prepare(
                'SELECT c.id, c.firma, c.telefon, c.operator, c.region, c.stav,
                        wl.created_at AS verified_at
                 FROM contacts c
                 JOIN workflow_log wl ON wl.contact_id = c.id
                 INNER JOIN (
                     SELECT contact_id, MAX(id) AS last_id
                     FROM workflow_log
                     WHERE user_id = ?
                       AND new_status IN (\'READY\', \'VF_SKIP\')
                     GROUP BY contact_id
                 ) latest ON latest.last_id = wl.id
                 WHERE 1=1 ' . $regionFilter . '
                 ORDER BY wl.created_at DESC
                 LIMIT ? OFFSET ?'
            );
            $stmt->execute(array_merge([$cistickaId], $regionParams, [self::PAGE_SIZE, $offset]));
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $cntStmt = $this->pdo->prepare(
                'SELECT COUNT(DISTINCT c.id) FROM contacts c
                 JOIN workflow_log wl ON wl.contact_id = c.id
                 WHERE wl.user_id = ?
                   AND wl.new_status IN (\'READY\', \'VF_SKIP\')
                   ' . ($regionParams !== [] ? 'AND c.region IN (' . implode(',', array_fill(0, count($regionParams), '?')) . ')' : '')
            );
            $cntStmt->execute(array_merge([$cistickaId], $regionParams));
            $totalCount = (int) $cntStmt->fetchColumn();
        } else {
            // K ověření: stav = NEW + region filtr (effectiveRegions / selectedRegion)
            $stmt = $this->pdo->prepare(
                "SELECT id, firma, telefon, operator, region
                 FROM contacts
                 WHERE stav = 'NEW'
                   $regionInSql
                 ORDER BY id ASC
                 LIMIT ? OFFSET ?"
            );
            $stmt->execute(array_merge($regionParams, [self::PAGE_SIZE, $offset]));
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $cntStmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM contacts WHERE stav = 'NEW' $regionInSql"
            );
            $cntStmt->execute($regionParams);
            $totalCount = (int) $cntStmt->fetchColumn();
        }

        $totalPages = max(1, (int) ceil($totalCount / self::PAGE_SIZE));

        // Dnešní statistika čističky — počítá UNIKÁTNÍ kontakty.
        // Když čistička přepne stejný kontakt 3× (TM → O2 → VF), počítá se jako 1×.
        // Per-status (ready/vf) bere POSLEDNÍ akci na daném kontaktu v rámci dne
        // (subquery najde pro každý contact_id maximální workflow_log id).
        $statsStmt = $this->pdo->prepare(
            "SELECT
                SUM(CASE WHEN wl.new_status = 'READY'   THEN 1 ELSE 0 END) AS ready_count,
                SUM(CASE WHEN wl.new_status = 'VF_SKIP' THEN 1 ELSE 0 END) AS vf_count,
                COUNT(*)                                                    AS total_today
             FROM workflow_log wl
             INNER JOIN (
                 SELECT contact_id, MAX(id) AS last_id
                 FROM workflow_log
                 WHERE user_id = :uid1
                   AND new_status IN ('READY', 'VF_SKIP')
                   AND DATE(created_at) = CURDATE()
                 GROUP BY contact_id
             ) last_per_contact ON last_per_contact.last_id = wl.id
             WHERE wl.user_id = :uid2"
        );
        $statsStmt->execute(['uid1' => $cistickaId, 'uid2' => $cistickaId]);
        $todayStats = $statsStmt->fetch(PDO::FETCH_ASSOC)
            ?: ['ready_count' => 0, 'vf_count' => 0, 'total_today' => 0];

        // Celkový počet NEW k ověření (pro badge K-ověření tabu).
        // STRICT MODE: bez nastavených goals nemá čistička co dělat → 0.
        // Když goals existují, počítá se přes goal regions (ignoruje selectedRegion).
        if (!$hasGoals) {
            $newCount = 0;
        } else {
            $basePh = 'AND region IN (' . implode(',', array_fill(0, count($goalRegions), '?')) . ')';
            $newCntStmt = $this->pdo->prepare("SELECT COUNT(*) FROM contacts WHERE stav = 'NEW' $basePh");
            $newCntStmt->execute($goalRegions);
            $newCount = (int) $newCntStmt->fetchColumn();
        }

        // Celkový počet všeho času zkontrolovaných (pro badge Zkontrolováno tabu).
        // PŘED touto opravou se zobrazoval $totalToday (jen dnešní), což bylo
        // matoucí — uživatelka viděla "Zkontrolováno 12" i když měla v listu
        // 30 historických záznamů. Teď badge odpovídá počtu řádků v listu
        // (DISTINCT contact_id, regardless of date — Zkontrolováno je all-time view).
        $zkontTotalStmt = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT contact_id) FROM workflow_log
             WHERE user_id = :uid AND new_status IN ('READY', 'VF_SKIP')"
        );
        $zkontTotalStmt->execute(['uid' => $cistickaId]);
        $zkontrolovaneTotal = (int) $zkontTotalStmt->fetchColumn();

        // ── Widget Moje výplata: měsíční počet ověření + sazba + Kč ──
        // URL parametry ?cw_year &cw_month umožňují navigaci v historii.
        // Default = aktuální měsíc. Po kliknutí ←/→ se přepočte query níže.
        $this->ensureRewardsTable();
        $cwCurYear  = (int) date('Y');
        $cwCurMonth = (int) date('n');
        $cwYear     = max(2024, min(2030, (int) ($_GET['cw_year']  ?? $cwCurYear)));
        $cwMonth    = max(1,    min(12,   (int) ($_GET['cw_month'] ?? $cwCurMonth)));
        $cwIsCurrent = ($cwYear === $cwCurYear && $cwMonth === $cwCurMonth);

        // Počet ověření této čističky za vybraný měsíc — DISTINCT contact_id
        // (kdyby čistička stejný kontakt ověřila víckrát, počítá se 1×).
        // Bere READY i VF_SKIP — obě se proplácí.
        $cwCntStmt = $this->pdo->prepare(
            "SELECT
                COUNT(DISTINCT contact_id)                                            AS total,
                COUNT(DISTINCT CASE WHEN new_status = 'READY'   THEN contact_id END)  AS ready_count,
                COUNT(DISTINCT CASE WHEN new_status = 'VF_SKIP' THEN contact_id END)  AS vf_count
             FROM workflow_log
             WHERE user_id = :uid
               AND new_status IN ('READY', 'VF_SKIP')
               AND YEAR(created_at)  = :y
               AND MONTH(created_at) = :m"
        );
        $cwCntStmt->execute(['uid' => $cistickaId, 'y' => $cwYear, 'm' => $cwMonth]);
        $cwCounts = $cwCntStmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'ready_count' => 0, 'vf_count' => 0];
        $cwTotalCount = (int) ($cwCounts['total'] ?? 0);
        $cwReadyCount = (int) ($cwCounts['ready_count'] ?? 0);
        $cwVfCount    = (int) ($cwCounts['vf_count']    ?? 0);

        // Sazba — current rate (může být null pokud žádná aktivní)
        $cwRate         = $this->currentRewardRate();
        $cwEarnings     = ($cwRate !== null) ? round($cwTotalCount * $cwRate, 2) : 0.0;

        $title = 'Ověřování kontaktů';
        ob_start();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'cisticka' . DIRECTORY_SEPARATOR . 'index.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'base.php';
    }

    /** POST /cisticka/verify – zpracování jednoho kontaktu */
    public function postVerify(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['cisticka', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/cisticka');
        }

        $cistickaId = (int) $user['id'];
        $contactId  = (int) ($_POST['contact_id'] ?? 0);
        $action     = (string) ($_POST['action'] ?? ''); // vf_skip | tm | o2

        if ($contactId <= 0 || !in_array($action, ['vf_skip', 'tm', 'o2'], true)) {
            crm_flash_set('Neplatný požadavek.');
            crm_redirect('/cisticka');
        }

        $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

        // Ověř existenci kontaktu ve stavu NEW
        $check = $this->pdo->prepare(
            'SELECT id, stav, operator FROM contacts WHERE id = :id AND stav = \'NEW\' LIMIT 1'
        );
        $check->execute([':id' => $contactId]);
        $contact = $check->fetch(PDO::FETCH_ASSOC);
        if (!$contact) {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'Kontakt nenalezen nebo již zpracován.']);
                exit;
            }
            crm_flash_set('Kontakt nenalezen nebo již zpracován.');
            crm_redirect('/cisticka');
        }

        // Určení nového stavu a operátora
        [$newStatus, $operatorVal, $label] = match ($action) {
            'vf_skip' => ['VF_SKIP', 'VF', 'VF – přeskočeno'],
            'tm'      => ['READY',   'TM', 'TM – připraveno pro navolávačku'],
            'o2'      => ['READY',   'O2', 'O2 – připraveno pro navolávačku'],
        };

        // Aktualizace kontaktu
        $this->pdo->prepare(
            'UPDATE contacts
             SET stav = :stav, operator = :op, updated_at = NOW(3)
             WHERE id = :id'
        )->execute([':stav' => $newStatus, ':op' => $operatorVal, ':id' => $contactId]);

        // Workflow log
        $this->pdo->prepare(
            'INSERT INTO workflow_log (contact_id, user_id, old_status, new_status, note, created_at)
             VALUES (:cid, :uid, \'NEW\', :new, :note, NOW(3))'
        )->execute([
            ':cid'  => $contactId,
            ':uid'  => $cistickaId,
            ':new'  => $newStatus,
            ':note' => 'Čistička: ' . $label,
        ]);

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'operator' => $operatorVal, 'status' => $newStatus]);
            exit;
        }

        crm_redirect('/cisticka');
    }

    /** POST /cisticka/verify-batch – zpracování celé stránky najednou (bulk) */
    public function postVerifyBatch(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['cisticka', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/cisticka');
        }

        $cistickaId = (int) $user['id'];
        $actions    = $_POST['actions'] ?? [];

        if (!is_array($actions) || $actions === []) {
            crm_flash_set('Žádné akce k provedení.');
            crm_redirect('/cisticka');
        }

        $processed = 0;
        $upd = $this->pdo->prepare(
            'UPDATE contacts SET stav = :stav, operator = :op, updated_at = NOW(3)
             WHERE id = :id AND stav = \'NEW\''
        );
        $wf = $this->pdo->prepare(
            'INSERT INTO workflow_log (contact_id, user_id, old_status, new_status, note, created_at)
             VALUES (:cid, :uid, \'NEW\', :new, :note, NOW(3))'
        );

        foreach ($actions as $contactIdStr => $action) {
            $contactId = (int) $contactIdStr;
            if ($contactId <= 0 || !in_array($action, ['vf_skip', 'tm', 'o2'], true)) {
                continue;
            }
            [$newStatus, $operatorVal, $label] = match ($action) {
                'vf_skip' => ['VF_SKIP', 'VF', 'Čistička: VF – přeskočeno'],
                'tm'      => ['READY',   'TM', 'Čistička: TM – připraveno'],
                'o2'      => ['READY',   'O2', 'Čistička: O2 – připraveno'],
            };
            $upd->execute([':stav' => $newStatus, ':op' => $operatorVal, ':id' => $contactId]);
            if ($upd->rowCount() > 0) {
                $wf->execute([':cid' => $contactId, ':uid' => $cistickaId, ':new' => $newStatus, ':note' => $label]);
                $processed++;
            }
        }

        crm_flash_set("Zpracováno {$processed} kontaktů.");
        crm_redirect('/cisticka');
    }

    /**
     * POST /cisticka/undo – vrátí kontakt zpět do stavu NEW (AJAX).
     * Povoleno kdykoli (bez časového omezení), provede ji jen ta čistička co to zpracovala.
     */
    public function postUndo(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['cisticka', 'majitel', 'superadmin']);

        header('Content-Type: application/json; charset=utf-8');

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            echo json_encode(['ok' => false, 'error' => 'Neplatný CSRF token.']);
            exit;
        }

        $cistickaId = (int) $user['id'];
        $contactId  = (int) ($_POST['contact_id'] ?? 0);

        if ($contactId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Neplatné ID kontaktu.']);
            exit;
        }

        // Kontakt musí být ve stavu READY nebo VF_SKIP
        $check = $this->pdo->prepare(
            "SELECT id, stav, operator FROM contacts WHERE id = ? AND stav IN ('READY','VF_SKIP') LIMIT 1"
        );
        $check->execute([$contactId]);
        $contact = $check->fetch(PDO::FETCH_ASSOC);

        if (!$contact) {
            echo json_encode(['ok' => false, 'error' => 'Kontakt nenalezen nebo není v stavu READY/VF_SKIP.']);
            exit;
        }

        $oldStav = (string) $contact['stav'];

        // Vrátit do NEW
        $this->pdo->prepare(
            "UPDATE contacts SET stav = 'NEW', operator = '', updated_at = NOW(3) WHERE id = ?"
        )->execute([$contactId]);

        // Workflow log
        $this->pdo->prepare(
            "INSERT INTO workflow_log (contact_id, user_id, old_status, new_status, note, created_at)
             VALUES (?, ?, ?, 'NEW', 'Čistička: vráceno zpět (undo)', NOW(3))"
        )->execute([$contactId, $cistickaId, $oldStav]);

        echo json_encode(['ok' => true]);
        exit;
    }

    /**
     * POST /cisticka/reclassify – překlasifikuje kontakt ve stavu READY/VF_SKIP na jiný operator (AJAX).
     * Použití: záložka "Zkontrolováno".
     */
    public function postReclassify(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['cisticka', 'majitel', 'superadmin']);

        header('Content-Type: application/json; charset=utf-8');

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            echo json_encode(['ok' => false, 'error' => 'Neplatný CSRF token.']);
            exit;
        }

        $cistickaId = (int) $user['id'];
        $contactId  = (int) ($_POST['contact_id'] ?? 0);
        $action     = (string) ($_POST['action'] ?? '');

        if ($contactId <= 0 || !in_array($action, ['vf_skip', 'tm', 'o2'], true)) {
            echo json_encode(['ok' => false, 'error' => 'Neplatný požadavek.']);
            exit;
        }

        $check = $this->pdo->prepare(
            "SELECT id, stav, operator FROM contacts WHERE id = ? AND stav IN ('READY','VF_SKIP') LIMIT 1"
        );
        $check->execute([$contactId]);
        $contact = $check->fetch(PDO::FETCH_ASSOC);

        if (!$contact) {
            echo json_encode(['ok' => false, 'error' => 'Kontakt nenalezen.']);
            exit;
        }

        [$newStatus, $operatorVal, $label] = match ($action) {
            'vf_skip' => ['VF_SKIP', 'VF', 'VF – přeskočeno'],
            'tm'      => ['READY',   'TM', 'TM – připraveno'],
            'o2'      => ['READY',   'O2', 'O2 – připraveno'],
        };

        $this->pdo->prepare(
            "UPDATE contacts SET stav = ?, operator = ?, updated_at = NOW(3) WHERE id = ?"
        )->execute([$newStatus, $operatorVal, $contactId]);

        $this->pdo->prepare(
            "INSERT INTO workflow_log (contact_id, user_id, old_status, new_status, note, created_at)
             VALUES (?, ?, ?, ?, ?, NOW(3))"
        )->execute([$contactId, $cistickaId, $contact['stav'], $newStatus, 'Čistička překlasifikace: ' . $label]);

        echo json_encode(['ok' => true, 'operator' => $operatorVal, 'status' => $newStatus]);
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Výkon čističky
    // ─────────────────────────────────────────────────────────────────────────

    /** Vrátí český název měsíce (1–12). */
    private static function czechMonthName(int $m): string
    {
        return [
            1 => 'Leden', 2 => 'Únor', 3 => 'Březen', 4 => 'Duben',
            5 => 'Květen', 6 => 'Červen', 7 => 'Červenec', 8 => 'Srpen',
            9 => 'Září', 10 => 'Říjen', 11 => 'Listopad', 12 => 'Prosinec',
        ][$m] ?? (string) $m;
    }

    /**
     * Parsuje month_key ve formátu "YYYY-MM" z <select>.
     * Jako fallback přijme oddělené year + month (pro přímé URL).
     * @return array{int, int}  [year, month]
     */
    private static function parseMonthKey(string $key, int $fallbackYear, int $fallbackMonth): array
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $key, $m)) {
            $y = (int) $m[1];
            $mo = (int) $m[2];
        } else {
            $y  = $fallbackYear  > 0 ? $fallbackYear  : (int) date('Y');
            $mo = $fallbackMonth > 0 ? $fallbackMonth : (int) date('n');
        }
        if ($mo < 1 || $mo > 12)  { $mo = (int) date('n'); }
        if ($y < 2020 || $y > 2100) { $y = (int) date('Y'); }
        return [$y, $mo];
    }

    /** GET /cisticka/stats */
    public function getStats(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['cisticka', 'majitel', 'superadmin']);

        $cistickaId = (int) $user['id'];
        $flash      = crm_flash_take();

        // Rok a měsíc — čteme month_key (YYYY-MM) z <select>
        [$year, $month] = self::parseMonthKey(
            (string) ($_GET['month_key'] ?? ''),
            (int) ($_GET['year'] ?? 0),
            (int) ($_GET['month'] ?? 0)
        );

        // ── Souhrnné počty za vybraný měsíc ──
        // Bere POUZE poslední workflow_log záznam pro daný kontakt v měsíci.
        // Tím se ignorují přepínání mezi TM/O2/VF u stejného kontaktu —
        // každý kontakt se započítá maximálně jednou (s finálním stavem).
        $sumStmt = $this->pdo->prepare(
            'SELECT
                wl.new_status,
                COALESCE(c.operator, \'?\') AS operator,
                COUNT(*)                    AS cnt
             FROM workflow_log wl
             JOIN contacts c ON c.id = wl.contact_id
             INNER JOIN (
                 SELECT contact_id, MAX(id) AS last_id
                 FROM workflow_log
                 WHERE user_id = :uid1
                   AND YEAR(created_at)  = :yr1
                   AND MONTH(created_at) = :mo1
                   AND new_status IN (\'READY\', \'VF_SKIP\')
                 GROUP BY contact_id
             ) latest ON latest.last_id = wl.id
             GROUP BY wl.new_status, c.operator'
        );
        $sumStmt->execute(['uid1' => $cistickaId, 'yr1' => $year, 'mo1' => $month]);
        $sumRaw = $sumStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $summary = ['TM' => 0, 'O2' => 0, 'VF_SKIP' => 0];
        foreach ($sumRaw as $row) {
            if ($row['new_status'] === 'VF_SKIP') {
                $summary['VF_SKIP'] += (int) $row['cnt'];
            } elseif ($row['new_status'] === 'READY') {
                $op = strtoupper((string) $row['operator']);
                if ($op === 'TM') { $summary['TM'] += (int) $row['cnt']; }
                elseif ($op === 'O2') { $summary['O2'] += (int) $row['cnt']; }
                else { $summary['TM'] += (int) $row['cnt']; } // fallback
            }
        }
        $totalActions = $summary['TM'] + $summary['O2'] + $summary['VF_SKIP'];

        // ── Denní breakdown ──
        // Stejný princip: poslední záznam per (den, kontakt). Když čistička
        // přepne kontakt 3× během dne, ten den se započítá jen jednou.
        $dailyStmt = $this->pdo->prepare(
            'SELECT
                DAY(wl.created_at)          AS day,
                wl.new_status,
                COALESCE(c.operator, \'?\') AS operator,
                COUNT(*)                    AS cnt
             FROM workflow_log wl
             JOIN contacts c ON c.id = wl.contact_id
             INNER JOIN (
                 SELECT DAY(created_at) AS d, contact_id, MAX(id) AS last_id
                 FROM workflow_log
                 WHERE user_id = :uid1
                   AND YEAR(created_at)  = :yr1
                   AND MONTH(created_at) = :mo1
                   AND new_status IN (\'READY\', \'VF_SKIP\')
                 GROUP BY DAY(created_at), contact_id
             ) latest ON latest.last_id = wl.id
             GROUP BY DAY(wl.created_at), wl.new_status, c.operator
             ORDER BY DAY(wl.created_at) ASC'
        );
        $dailyStmt->execute(['uid1' => $cistickaId, 'yr1' => $year, 'mo1' => $month]);
        $dailyRaw = $dailyStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $daysInMonth = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
        $daily = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $daily[$d] = ['TM' => 0, 'O2' => 0, 'VF_SKIP' => 0];
        }
        foreach ($dailyRaw as $row) {
            $d = (int) $row['day'];
            if ($row['new_status'] === 'VF_SKIP') {
                $daily[$d]['VF_SKIP'] += (int) $row['cnt'];
            } elseif ($row['new_status'] === 'READY') {
                $op = strtoupper((string) $row['operator']);
                if ($op === 'O2')       { $daily[$d]['O2'] += (int) $row['cnt']; }
                else                    { $daily[$d]['TM'] += (int) $row['cnt']; }
            }
        }
        $activeDays = array_filter($daily, static function (array $dc): bool {
            return array_sum($dc) > 0;
        });

        // Výběr měsíců: 1 dopředu + aktuální + 17 zpět
        $realMonthKey = date('Y') . '-' . date('m');
        $monthOptions = [];
        $now = time();
        for ($i = -1; $i < 17; $i++) {
            $ts = strtotime("-{$i} months", $now);
            $monthOptions[] = [
                'year'  => (int) date('Y', $ts),
                'month' => (int) date('n', $ts),
                'label' => self::czechMonthName((int) date('n', $ts)) . ' ' . date('Y', $ts),
            ];
        }

        $title = 'Můj výkon — čistička';
        ob_start();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'cisticka' . DIRECTORY_SEPARATOR . 'stats.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'base.php';
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Měsíční cíle podle krajů (region goals)
    //  Reset: 1. dne každého měsíce v 00:00 (kalendářní měsíc).
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Vrátí rozsah aktuálního kalendářního měsíce: ['Y-m-01 00:00:00.000', 'Y-m-t 23:59:59.999'].
     *
     * @return array{0:string,1:string}
     */
    private function currentMonthRange(): array
    {
        return $this->monthRangeFromPeriod($this->currentPeriodYyyymm());
    }

    /**
     * Lokalizovaný název aktuálního měsíce (pro UI), např. "květen 2026".
     */
    private function currentMonthLabel(): string
    {
        return $this->monthLabelFromPeriod($this->currentPeriodYyyymm());
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Period helpery (YYYYMM jako int — formát používaný v cisticka_region_goals)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Aktuální kalendářní měsíc jako YYYYMM (např. 202605).
     */
    private function currentPeriodYyyymm(): int
    {
        return (int) date('Ym');
    }

    /**
     * Parsuje "YYYY-MM" z <select>/GET a vrátí int YYYYMM.
     * Při neplatném vstupu vrací aktuální měsíc.
     * Validace rozsahu: rok 2020-2100, měsíc 1-12.
     */
    private function parsePeriodKey(string $key): int
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $key, $m)) {
            $y = (int) $m[1];
            $mo = (int) $m[2];
            if ($y >= 2020 && $y <= 2100 && $mo >= 1 && $mo <= 12) {
                return $y * 100 + $mo;
            }
        }
        return $this->currentPeriodYyyymm();
    }

    /**
     * Z YYYYMM int vyrobí "YYYY-MM" string (pro <option value="...">).
     */
    private function periodToKey(int $period): string
    {
        $y = intdiv($period, 100);
        $m = $period % 100;
        return sprintf('%04d-%02d', $y, $m);
    }

    /**
     * Lokalizovaný název měsíce z period int, např. 202605 → "květen 2026".
     */
    private function monthLabelFromPeriod(int $period): string
    {
        static $names = [
            1 => 'leden', 2 => 'únor', 3 => 'březen', 4 => 'duben',
            5 => 'květen', 6 => 'červen', 7 => 'červenec', 8 => 'srpen',
            9 => 'září', 10 => 'říjen', 11 => 'listopad', 12 => 'prosinec',
        ];
        $y = intdiv($period, 100);
        $m = $period % 100;
        return ($names[$m] ?? '?') . ' ' . $y;
    }

    /**
     * Vrátí datetime rozsah pro daný měsíc (period YYYYMM).
     * @return array{0:string,1:string}  ['Y-m-01 00:00:00.000', 'Y-m-t 23:59:59.999']
     */
    private function monthRangeFromPeriod(int $period): array
    {
        $y = intdiv($period, 100);
        $m = $period % 100;
        $ts = mktime(0, 0, 0, $m, 1, $y);
        if ($ts === false) {
            $ts = time();
        }
        $start = date('Y-m-01', $ts) . ' 00:00:00.000';
        $end   = date('Y-m-t',  $ts) . ' 23:59:59.999';
        return [$start, $end];
    }

    /**
     * Vygeneruje seznam možností pro <select> přepínače měsíců.
     * Rozsah: 12 měsíců zpět + aktuální + 2 měsíce dopředu.
     *
     * @return list<array{key:string,period:int,label:string}>
     */
    private function monthOptionsForGoals(): array
    {
        $out = [];
        // Anchor na 1. den aktuálního měsíce — eliminuje známou PHP "+N months"
        // anomálii na 29./30./31. (např. 31. ledna + 1 měsíc = 3. března).
        $anchor = mktime(0, 0, 0, (int) date('n'), 1, (int) date('Y'));
        if ($anchor === false) $anchor = time();

        // Iterujeme od nejstaršího k nejnovějšímu (chronologicky).
        for ($i = -12; $i <= 2; $i++) {
            if ($i === 0) {
                $ts = $anchor;
            } else {
                $rel = $i > 0 ? "+{$i} months" : "{$i} months";
                $ts  = strtotime($rel, $anchor);
                if ($ts === false) continue;
            }
            $y = (int) date('Y', $ts);
            $m = (int) date('n', $ts);
            $period = $y * 100 + $m;
            $out[] = [
                'key'    => $this->periodToKey($period),
                'period' => $period,
                'label'  => $this->monthLabelFromPeriod($period),
            ];
        }
        return $out;
    }

    /**
     * Vytvoří tabulku cisticka_region_goals + zajistí měsíční sémantiku.
     *
     * Schéma od verze "per-period":
     *   UNIQUE (region, period_yyyymm)  ←  každý kraj má vlastní záznam per měsíc
     *   period_yyyymm  =  YYYYMM jako int (např. 202605 pro květen 2026)
     *
     * Cíle jsou KALENDÁŘNĚ MĚSÍČNÍ — counter v progress baru se počítá z workflow_log
     * podle data, target se uchovává v DB per (region, period). Admin může nastavit
     * target dopředu (budoucí měsíc) i zpětně dohledat (minulé měsíce).
     *
     * Migrace jsou idempotentní — všechny ALTER/UPDATE jsou v try/catch a při
     * opakovaném volání se nestaly žádné škody.
     */
    /**
     * Auto-create cisticka_rewards_config (idempotentní).
     * Volá se před každou prací se sazbou — kdyby majitel ještě nepustil
     * migraci 008. Při prvním vytvoření vloží i seed 0.70 Kč.
     */
    private function ensureRewardsTable(): void
    {
        try {
            $this->pdo->exec(
                "CREATE TABLE IF NOT EXISTS `cisticka_rewards_config` (
                  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `amount_czk` DECIMAL(8,4) NOT NULL,
                  `valid_from` DATE NOT NULL,
                  `valid_to`   DATE NULL DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `idx_cisticka_rewards_valid` (`valid_from`, `valid_to`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            // Seed: pokud je tabulka prázdná, vlož default sazbu 0.70 Kč.
            $cnt = (int) $this->pdo->query('SELECT COUNT(*) FROM cisticka_rewards_config')->fetchColumn();
            if ($cnt === 0) {
                $this->pdo->exec(
                    "INSERT INTO cisticka_rewards_config (amount_czk, valid_from, valid_to)
                     VALUES (0.7000, CURDATE(), NULL)"
                );
            }
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }
    }

    /**
     * Vrátí aktuální sazbu (NULL pokud není žádná aktivní).
     * Aktivní = valid_from <= dnes a (valid_to IS NULL OR valid_to >= dnes).
     */
    private function currentRewardRate(): ?float
    {
        try {
            $row = $this->pdo->query(
                "SELECT amount_czk FROM cisticka_rewards_config
                 WHERE valid_from <= CURDATE() AND (valid_to IS NULL OR valid_to >= CURDATE())
                 ORDER BY valid_from DESC LIMIT 1"
            )->fetchColumn();
            return $row !== false ? (float) $row : null;
        } catch (\PDOException) {
            return null;
        }
    }

    /** Načte celou historii sazeb (pro audit panel). */
    private function rewardsHistory(): array
    {
        try {
            $rows = $this->pdo->query(
                "SELECT id, amount_czk, valid_from, valid_to
                 FROM cisticka_rewards_config
                 ORDER BY valid_from DESC, id DESC"
            )->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (\PDOException) {
            return [];
        }
    }

    private function ensureRegionGoalsTable(): void
    {
        try {
            // CREATE: nová instalace už má rovnou per-period schéma + priority.
            $this->pdo->exec(
                "CREATE TABLE IF NOT EXISTS `cisticka_region_goals` (
                  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `region`          VARCHAR(64) NOT NULL,
                  `period_yyyymm`   INT UNSIGNED NOT NULL DEFAULT 0
                                     COMMENT 'Měsíční perioda cíle ve formátu YYYYMM (např. 202605).',
                  `monthly_target`  INT UNSIGNED NOT NULL DEFAULT 0
                                     COMMENT 'Cíl počtu kontaktů za kalendářní měsíc.',
                  `priority`        TINYINT UNSIGNED NOT NULL DEFAULT 5
                                     COMMENT 'Priorita 1-10, kde 1 = nejvyšší. Řazení tiles čističky ASC.',
                  `goal_started_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
                                     COMMENT 'Audit: kdy byl cíl naposledy NASTAVEN/ZMĚNĚN.',
                  `set_by`          BIGINT UNSIGNED NULL DEFAULT NULL,
                  `created_at`      DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                  `updated_at`      DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `uk_region_period` (`region`, `period_yyyymm`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }

        // Migrace #1 (legacy): doplnění goal_started_at sloupce u starších inštalací.
        try {
            $this->pdo->exec(
                "ALTER TABLE `cisticka_region_goals`
                 ADD COLUMN `goal_started_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
                 AFTER `monthly_target`"
            );
            $this->pdo->exec(
                "UPDATE `cisticka_region_goals`
                 SET `goal_started_at` = '2000-01-01 00:00:00.000'"
            );
        } catch (\PDOException) {
            // Sloupec už existuje — pokračujeme
        }

        // Migrace #2: rename daily_target → monthly_target (pro stávající instalace).
        // Idempotentní: pokud sloupec už je přejmenovaný, ALTER selže a my to ignorujeme.
        try {
            $this->pdo->exec(
                "ALTER TABLE `cisticka_region_goals`
                 CHANGE COLUMN `daily_target` `monthly_target` INT UNSIGNED NOT NULL DEFAULT 0
                 COMMENT 'Cíl počtu kontaktů za kalendářní měsíc.'"
            );
        } catch (\PDOException) {
            // Sloupec už je přejmenovaný (nebo nikdy neexistoval) — žádná akce.
        }

        // Migrace #3: přidání period_yyyymm sloupce.
        // Existující řádky (single-target schéma) dostanou period = aktuální YYYYMM,
        // tj. cíl, co byl "live", se přiřadí aktuálnímu měsíci. Nic se neztratí.
        try {
            $this->pdo->exec(
                "ALTER TABLE `cisticka_region_goals`
                 ADD COLUMN `period_yyyymm` INT UNSIGNED NOT NULL DEFAULT 0
                 COMMENT 'Měsíční perioda cíle ve formátu YYYYMM (např. 202605).'
                 AFTER `region`"
            );
            // Backfill: existující záznamy → aktuální měsíc.
            $currentPeriod = (int) date('Ym');
            $this->pdo->prepare(
                "UPDATE `cisticka_region_goals`
                 SET `period_yyyymm` = :p
                 WHERE `period_yyyymm` = 0"
            )->execute(['p' => $currentPeriod]);
        } catch (\PDOException) {
            // Sloupec už existuje — pokračujeme
        }

        // Migrace #4: výměna UNIQUE klíče z (region) na (region, period_yyyymm).
        // Spustíme dva nezávislé pokusy — DROP a ADD — každý ignorují chybu pokud
        // už proběhly (idempotentní).
        try {
            $this->pdo->exec(
                "ALTER TABLE `cisticka_region_goals` DROP INDEX `uk_region`"
            );
        } catch (\PDOException) {
            // Index neexistuje (už byl odstraněn nebo CREATE TABLE rovnou
            // vytvořil nový schéma) — žádná akce.
        }
        try {
            $this->pdo->exec(
                "ALTER TABLE `cisticka_region_goals`
                 ADD UNIQUE KEY `uk_region_period` (`region`, `period_yyyymm`)"
            );
        } catch (\PDOException) {
            // Index už existuje — žádná akce.
        }

        // Migrace #5: přidání priority sloupce. Default 5 = neutrální priorita
        // (1 = nejvyšší, 10 = nejnižší). Existující řádky dostanou default 5.
        try {
            $this->pdo->exec(
                "ALTER TABLE `cisticka_region_goals`
                 ADD COLUMN `priority` TINYINT UNSIGNED NOT NULL DEFAULT 5
                 COMMENT 'Priorita 1-10, kde 1 = nejvyšší. Řazení tiles čističky ASC.'
                 AFTER `monthly_target`"
            );
        } catch (\PDOException) {
            // Sloupec už existuje — žádná akce.
        }
    }

    /**
     * Vrátí všechny kraje s nastaveným cílem + měsíční progress pro daný měsíc.
     *
     * Sémantika: counter = verified kontakty (DISTINCT contact_id) v daném kraji
     * pro tohoto uživatele, kde event NASTAL VE VYBRANÉM KALENDÁŘNÍM MĚSÍCI.
     * Reset: automaticky 1. dne každého měsíce v 00:00.
     *
     * Pořadí výsledku: ORDER BY priority ASC, region ASC.
     * Tj. priorita 1 nejdřív, při shodě priority abecedně.
     *
     * Pro každý kraj: ['region','label','target','done','percent','completed','priority']
     *
     * @param  int|null $period  YYYYMM int (např. 202605) — null = aktuální měsíc.
     * @return list<array<string,mixed>>
     */
    private function loadRegionGoalsWithProgress(int $cistickaId, ?int $period = null): array
    {
        $period = $period ?? $this->currentPeriodYyyymm();
        $out = [];
        try {
            // Načti targety POUZE pro vybraný měsíc, vč. priority.
            // Sort: priority ASC, region ASC (tie-break abecedně).
            $gStmt = $this->pdo->prepare(
                'SELECT region, monthly_target, priority
                 FROM cisticka_region_goals
                 WHERE monthly_target > 0 AND period_yyyymm = :p
                 ORDER BY priority ASC, region ASC'
            );
            $gStmt->execute(['p' => $period]);
            $goalRows = $gStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($goalRows) || $goalRows === []) return [];

            [$mStart, $mEnd] = $this->monthRangeFromPeriod($period);

            // SDÍLENÝ progress per kraj — počítá VŠECHNY čističky dohromady.
            // Cíle jsou sdílené (1000 leadů Praha = pro všechny čističky dohromady),
            // takže když 2 čističky pracují, obě vidí stejné celkové progress.
            // DISTINCT contact_id — kontakt ověřený 2× se počítá 1×.
            $regions = array_map(static fn($r) => (string) $r['region'], $goalRows);
            $ph = implode(',', array_fill(0, count($regions), '?'));
            $cStmt = $this->pdo->prepare(
                "SELECT c.region, COUNT(DISTINCT wl.contact_id) AS done
                 FROM workflow_log wl
                 JOIN contacts c ON c.id = wl.contact_id
                 WHERE wl.new_status IN ('READY','VF_SKIP')
                   AND wl.created_at BETWEEN ? AND ?
                   AND c.region IN ($ph)
                 GROUP BY c.region"
            );
            $cStmt->execute(array_merge([$mStart, $mEnd], $regions));
            $progress = $cStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

            // PER-USER progress (jen má část) — pro info "z toho ty: X"
            $myStmt = $this->pdo->prepare(
                "SELECT c.region, COUNT(DISTINCT wl.contact_id) AS my_done
                 FROM workflow_log wl
                 JOIN contacts c ON c.id = wl.contact_id
                 WHERE wl.user_id = ?
                   AND wl.new_status IN ('READY','VF_SKIP')
                   AND wl.created_at BETWEEN ? AND ?
                   AND c.region IN ($ph)
                 GROUP BY c.region"
            );
            $myStmt->execute(array_merge([$cistickaId, $mStart, $mEnd], $regions));
            $myProgress = $myStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

            foreach ($goalRows as $row) {
                $region = (string) $row['region'];
                $target = (int) $row['monthly_target'];
                $prio   = (int) ($row['priority'] ?? 5);
                $done   = (int) ($progress[$region] ?? 0);
                $myDone = (int) ($myProgress[$region] ?? 0);
                $pct    = $target > 0 ? min(100, (int) round($done / $target * 100)) : 0;
                $out[] = [
                    'region'    => $region,
                    'label'     => function_exists('crm_region_label')
                                   ? crm_region_label($region)
                                   : $region,
                    'target'    => $target,
                    'done'      => $done,         // SDÍLENÝ — všechny čističky dohromady
                    'my_done'   => $myDone,       // jen aktuální uživatel
                    'percent'   => $pct,
                    'completed' => $done >= $target,
                    'priority'  => $prio,
                ];
            }
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }
        return $out;
    }

    /**
     * Vrátí měsíční progress per kraj — pro VŠECHNY uživatele dohromady (admin view).
     * Používá se v admin panelu cílů: admin chce vidět celkový stav kraje, ne per-user.
     *
     * @param  list<string> $regions  Seznam krajů, pro které se má počítat (filtr).
     * @param  int|null     $period   YYYYMM int — null = aktuální měsíc.
     * @return array<string,int>      region → done count v daném měsíci
     */
    private function loadAdminMonthlyProgress(array $regions, ?int $period = null): array
    {
        if ($regions === []) return [];
        $period = $period ?? $this->currentPeriodYyyymm();
        try {
            [$mStart, $mEnd] = $this->monthRangeFromPeriod($period);
            $ph = implode(',', array_fill(0, count($regions), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT c.region, COUNT(DISTINCT wl.contact_id) AS done
                 FROM workflow_log wl
                 JOIN contacts c ON c.id = wl.contact_id
                 WHERE wl.new_status IN ('READY','VF_SKIP')
                   AND wl.created_at BETWEEN ? AND ?
                   AND c.region IN ($ph)
                 GROUP BY c.region"
            );
            $stmt->execute(array_merge([$mStart, $mEnd], $regions));
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
            return is_array($rows) ? array_map('intval', $rows) : [];
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
            return [];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Admin: nastavení cílů per kraj
    //  GET  /admin/cisticka-goals  — formulář
    //  POST /admin/cisticka-goals  — uložit
    // ─────────────────────────────────────────────────────────────────────────

    public function getAdminGoals(): void
    {
        $actor = crm_require_user($this->pdo);
        crm_require_roles($actor, ['majitel', 'superadmin']);
        $this->ensureRegionGoalsTable();
        $this->ensureRewardsTable();

        // ── Sazba odměny čističky za jedno ověření (READY i VF_SKIP) ──
        // Aktuální + historie pro audit (zobrazí se v <details> sekci nahoře view).
        $cwCurrentRate = $this->currentRewardRate();      // float nebo null
        $cwRateHistory = $this->rewardsHistory();         // list rows

        // ── Admin přehled: všechny čističky × měsíc (kolik mají splatné teď) ──
        // URL ?cw_year &cw_month přepíná měsíc; default = aktuální.
        $cwCurY = (int) date('Y');
        $cwCurM = (int) date('n');
        $cwAdminYear  = max(2024, min(2030, (int) ($_GET['cw_year']  ?? $cwCurY)));
        $cwAdminMonth = max(1,    min(12,   (int) ($_GET['cw_month'] ?? $cwCurM)));
        $cwAdminIsCurrent = ($cwAdminYear === $cwCurY && $cwAdminMonth === $cwCurM);

        // Najít všechny aktivní čističky a pro každou spočítat ověření v měsíci.
        // LEFT JOIN se subquery na DISTINCT contact_id → každý kontakt 1× per čistička.
        try {
            $cwAllStmt = $this->pdo->prepare(
                "SELECT u.id, u.jmeno,
                        COALESCE(t.total, 0)   AS total,
                        COALESCE(t.ready_n, 0) AS ready_count,
                        COALESCE(t.vf_n, 0)    AS vf_count
                 FROM users u
                 LEFT JOIN (
                     SELECT user_id,
                            COUNT(DISTINCT contact_id) AS total,
                            COUNT(DISTINCT CASE WHEN new_status = 'READY'   THEN contact_id END) AS ready_n,
                            COUNT(DISTINCT CASE WHEN new_status = 'VF_SKIP' THEN contact_id END) AS vf_n
                     FROM workflow_log
                     WHERE new_status IN ('READY','VF_SKIP')
                       AND YEAR(created_at)  = :y
                       AND MONTH(created_at) = :m
                     GROUP BY user_id
                 ) t ON t.user_id = u.id
                 WHERE u.role = 'cisticka' AND u.aktivni = 1
                 ORDER BY total DESC, u.jmeno ASC"
            );
            $cwAllStmt->execute(['y' => $cwAdminYear, 'm' => $cwAdminMonth]);
            $cwAllCisticky = $cwAllStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
            $cwAllCisticky = [];
        }
        // Spočítat agregát pro celý tým
        $cwTeamTotal    = (int) array_sum(array_column($cwAllCisticky, 'total'));
        $cwTeamEarnings = ($cwCurrentRate !== null) ? round($cwTeamTotal * $cwCurrentRate, 2) : 0.0;

        // Vybraný měsíc — z GET ?month_key=YYYY-MM, default = aktuální.
        $selectedPeriod = $this->parsePeriodKey((string) ($_GET['month_key'] ?? ''));
        $currentPeriod  = $this->currentPeriodYyyymm();

        // Načti existující cíle pro VYBRANÝ měsíc.
        // Vrátí dvě asociativní pole: region → monthly_target, region → priority.
        $gStmt = $this->pdo->prepare(
            'SELECT region, monthly_target, priority FROM cisticka_region_goals
             WHERE period_yyyymm = :p'
        );
        $gStmt->execute(['p' => $selectedPeriod]);
        $rows = $gStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $existing = [];
        $existingPriority = [];
        foreach ($rows as $r) {
            $reg = (string) $r['region'];
            $existing[$reg]         = (int) $r['monthly_target'];
            $existingPriority[$reg] = (int) ($r['priority'] ?? 5);
        }

        // Všechny dostupné regiony (z helperu)
        $allRegions = function_exists('crm_region_choices') ? crm_region_choices() : [];

        // Měsíční progress per kraj (pro VŠECHNY čističky dohromady — admin view).
        // Filtrujeme jen na kraje s nastaveným cílem (target > 0); ostatní progress
        // nepotřebujeme zobrazovat, ušetříme COUNT() práci.
        $regionsWithGoal = [];
        foreach ($allRegions as $r) {
            if ((int) ($existing[$r] ?? 0) > 0) $regionsWithGoal[] = $r;
        }
        $progress = $this->loadAdminMonthlyProgress($regionsWithGoal, $selectedPeriod);

        // UI metadata
        $monthLabel    = $this->monthLabelFromPeriod($selectedPeriod);
        $monthOptions  = $this->monthOptionsForGoals();
        $isCurrentMonth = ($selectedPeriod === $currentPeriod);
        $isFutureMonth  = ($selectedPeriod > $currentPeriod);
        $isPastMonth    = ($selectedPeriod < $currentPeriod);
        $selectedMonthKey = $this->periodToKey($selectedPeriod);

        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();
        $title = 'Cíle a sazba čističky';
        ob_start();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views'
              . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'cisticka_goals.php';
        $content = (string) ob_get_clean();
        $user = $actor; // alias pro layout/base.php (sidebar + topbar)
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views'
              . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'base.php';
    }

    public function postAdminGoals(): void
    {
        $actor = crm_require_user($this->pdo);
        crm_require_roles($actor, ['majitel', 'superadmin']);
        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/cisticka-goals');
        }
        $this->ensureRegionGoalsTable();

        // Period z hidden inputu (POST), default = aktuální měsíc.
        $period = $this->parsePeriodKey((string) ($_POST['period'] ?? ''));

        $goals      = $_POST['goal']     ?? [];
        $priorities = $_POST['priority'] ?? [];
        if (!is_array($goals))      $goals = [];
        if (!is_array($priorities)) $priorities = [];

        $allRegions = function_exists('crm_region_choices') ? crm_region_choices() : [];

        // UPSERT na (region, period_yyyymm) — UNIQUE klíč.
        // Změna v jednom měsíci NEOVLIVNÍ jiné měsíce. Counter (progress) je
        // odvozen z workflow_log podle data eventu, takže historický target
        // i data zůstávají konzistentní.
        $upsert = $this->pdo->prepare(
            'INSERT INTO cisticka_region_goals
                (region, period_yyyymm, monthly_target, priority, set_by)
             VALUES
                (:r, :p, :t, :prio, :uid)
             ON DUPLICATE KEY UPDATE
                monthly_target = :t2,
                priority       = :prio2,
                set_by         = :uid2,
                updated_at     = NOW(3)'
        );
        $changed = 0;
        foreach ($allRegions as $region) {
            $target = (int) ($goals[$region] ?? 0);
            if ($target < 0) $target = 0;
            // Priorita: clamp 1-10. Default 5 (neutrální).
            $prio = (int) ($priorities[$region] ?? 5);
            if ($prio < 1)  $prio = 1;
            if ($prio > 10) $prio = 10;
            try {
                $upsert->execute([
                    'r'     => $region,
                    'p'     => $period,
                    't'     => $target,
                    't2'    => $target,
                    'prio'  => $prio,
                    'prio2' => $prio,
                    'uid'   => (int) $actor['id'],
                    'uid2'  => (int) $actor['id'],
                ]);
                $changed++;
            } catch (\PDOException $e) {
                crm_db_log_error($e, __METHOD__);
            }
        }

        crm_flash_set('✓ Cíle uloženy pro ' . $this->monthLabelFromPeriod($period) . '.');
        // Redirect zpět na stejný měsíc, aby admin viděl uložené hodnoty.
        crm_redirect('/admin/cisticka-goals?month_key=' . $this->periodToKey($period));
    }

    // ════════════════════════════════════════════════════════════════
    //  POST /admin/cisticka-rewards/save
    //
    //  Změna sazby čističky za jedno ověření.
    //  Princip: zachovat historii — neUPDATE-ujeme starý záznam, místo
    //  toho ho uzavřeme (valid_to = včera) a vložíme nový (valid_from = dnes).
    //  Tak když se kdykoli ohlédneme zpět, víme jaká sazba platila.
    // ════════════════════════════════════════════════════════════════
    public function postAdminRewards(): void
    {
        $actor = crm_require_user($this->pdo);
        crm_require_roles($actor, ['majitel', 'superadmin']);
        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/cisticka-goals');
        }
        $this->ensureRewardsTable();

        $rateStr = trim((string) ($_POST['amount_czk'] ?? ''));
        // Akceptujeme čárku i tečku (CZ konvence)
        $rateStr = str_replace(',', '.', $rateStr);
        if (!is_numeric($rateStr)) {
            crm_flash_set('⚠ Sazba musí být číslo (např. 0,70).');
            crm_redirect('/admin/cisticka-goals#cisticka-rewards');
        }
        $rate = (float) $rateStr;
        if ($rate <= 0 || $rate > 100) {
            crm_flash_set('⚠ Sazba musí být mezi 0 a 100 Kč. Zadali jste ' . $rateStr . '.');
            crm_redirect('/admin/cisticka-goals#cisticka-rewards');
        }
        // Zaokrouhlit na 4 desetinná místa (DB column = DECIMAL(8,4))
        $rate = round($rate, 4);

        // Pokud sazba beze změny → nic nedělat (avoid duplicate history rows).
        $current = $this->currentRewardRate();
        if ($current !== null && abs($current - $rate) < 0.00005) {
            crm_flash_set('Sazba se nezměnila.');
            crm_redirect('/admin/cisticka-goals#cisticka-rewards');
        }

        try {
            $this->pdo->beginTransaction();
            // 1) Uzavřít všechny aktivně platící záznamy (valid_to NULL nebo budoucnost)
            //    → valid_to = včera, ať historie nepřekrývá nový záznam.
            $this->pdo->prepare(
                "UPDATE cisticka_rewards_config
                 SET valid_to = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                 WHERE valid_to IS NULL OR valid_to >= CURDATE()"
            )->execute();
            // 2) Vložit nový záznam s platností od dnes.
            $this->pdo->prepare(
                "INSERT INTO cisticka_rewards_config (amount_czk, valid_from, valid_to)
                 VALUES (:r, CURDATE(), NULL)"
            )->execute(['r' => $rate]);
            $this->pdo->commit();
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            crm_db_log_error($e, __METHOD__);
            crm_flash_set('⚠ Chyba při uložení sazby. Zkuste to prosím znovu.');
            crm_redirect('/admin/cisticka-goals#cisticka-rewards');
        }

        crm_audit_log(
            $this->pdo, (int) $actor['id'],
            'cisticka_reward_change', 'cisticka_rewards_config', null,
            ['old_rate' => $current, 'new_rate' => $rate]
        );

        crm_flash_set(sprintf('✓ Sazba změněna na %s Kč/ověření.', number_format($rate, 2, ',', ' ')));
        crm_redirect('/admin/cisticka-goals#cisticka-rewards');
    }

    // ════════════════════════════════════════════════════════════════
    //  GET /cisticka/payout/print
    //
    //  Standalone tisková stránka — kolik dostane čistička za měsíc.
    //  Hard-locked: čistička vidí jen sebe; admin/majitel může přes
    //  ?cisticka_id=N pro konkrétní čističku (analogie OZ self-print).
    // ════════════════════════════════════════════════════════════════
    public function getPayoutPrint(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['cisticka', 'majitel', 'superadmin']);
        $this->ensureRewardsTable();

        if ((string) ($user['role'] ?? '') === 'cisticka') {
            $cistickaId = (int) $user['id'];
        } else {
            $cistickaId = (int) ($_GET['cisticka_id'] ?? $user['id']);
        }

        $year  = max(2024, min(2030, (int) ($_GET['year']  ?? date('Y'))));
        $month = max(1,    min(12,   (int) ($_GET['month'] ?? date('n'))));

        // Info o čističce
        $uStmt = $this->pdo->prepare(
            "SELECT id, jmeno FROM users WHERE id = :id AND role = 'cisticka' LIMIT 1"
        );
        $uStmt->execute(['id' => $cistickaId]);
        $cisticka = $uStmt->fetch(PDO::FETCH_ASSOC);
        if (!$cisticka) {
            http_response_code(404);
            echo 'Čistička nenalezena.';
            exit;
        }

        // Všechna ověření této čističky za měsíc — DISTINCT contact_id, nejnovější
        // workflow_log řádek per kontakt (kdyby čistička přepnula stejný kontakt 2×,
        // bere se POSLEDNÍ ověření = jak je výsledný stav teď).
        $sqlEvents = $this->pdo->prepare(
            "SELECT c.id            AS contact_id,
                    c.firma,
                    c.telefon,
                    c.region,
                    c.operator,
                    wl.new_status,
                    wl.created_at  AS verified_at
             FROM workflow_log wl
             INNER JOIN (
                 SELECT contact_id, MAX(id) AS last_id
                 FROM workflow_log
                 WHERE user_id = :uid1
                   AND new_status IN ('READY', 'VF_SKIP')
                   AND YEAR(created_at)  = :y
                   AND MONTH(created_at) = :m
                 GROUP BY contact_id
             ) last_per_contact ON last_per_contact.last_id = wl.id
             INNER JOIN contacts c ON c.id = wl.contact_id
             WHERE wl.user_id = :uid2
             ORDER BY wl.created_at DESC, c.id DESC"
        );
        $sqlEvents->execute([
            'uid1' => $cistickaId, 'uid2' => $cistickaId,
            'y'    => $year,        'm'    => $month,
        ]);
        $events = $sqlEvents->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Seskupit per operator (O2 / Vodafone-skip / T-Mobile / jiný)
        // pro přehlednost a per-operator counts.
        $byOperator = [];
        foreach ($events as $ev) {
            $opRaw = (string) ($ev['operator'] ?? '');
            $opUpper = strtoupper(trim($opRaw));
            // Sjednotit varianty (VF / O2 / TM / atd.)
            $opKey = match (true) {
                $ev['new_status'] === 'VF_SKIP' || str_contains($opUpper, 'VODAFONE') || $opUpper === 'VF'
                    => 'Vodafone (skip)',
                str_contains($opUpper, 'O2')      => 'O2',
                str_contains($opUpper, 'T-MOBILE') || str_contains($opUpper, 'T MOBILE') || $opUpper === 'TM'
                    => 'T-Mobile',
                $opUpper === ''                   => 'Neznámý',
                default                           => $opUpper,
            };
            if (!isset($byOperator[$opKey])) {
                $byOperator[$opKey] = ['name' => $opKey, 'count' => 0, 'events' => []];
            }
            $byOperator[$opKey]['count']++;
            $byOperator[$opKey]['events'][] = $ev;
        }
        // Sort: podle count DESC
        uasort($byOperator, fn($a, $b) => $b['count'] - $a['count']);

        $rewardPerVerify = $this->currentRewardRate() ?? 0.0;

        header('Content-Type: text/html; charset=UTF-8');
        require dirname(__DIR__) . '/views/cisticka/payout_print.php';
        exit;
    }
}
