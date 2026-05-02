<?php
// e:\Snecinatripu\app\controllers\CallerController.php
declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'audit.php';

/**
 * Pracovní obrazovka navolávačky.
 *
 * Stavy kontaktu:
 *   NEW, ASSIGNED, CALLBACK, CALLED_OK, CALLED_BAD, FOR_SALES,
 *   NEZAJEM, IZOLACE, CHYBNY_KONTAKT, NEDOVOLANO
 *
 * Callback logika:
 *   callback_at ≤ 30 dní → privátní (assigned_caller_id = caller)
 *   callback_at > 30 dní → sdílený (assigned_caller_id = NULL, vidí všechny navolávačky)
 */
final class CallerController
{
    /** Počet dní pro hranici sdíleného callbacku */
    private const SHARED_CALLBACK_DAYS = 30;

    /** Po kolika nedovoláních přejít automaticky na NEZAJEM */
    private const MAX_NEDOVOLANO = 3;

    /** Kontaktů na stránku v záložce K provolání */
    private const PAGE_SIZE = 20;

    public function __construct(private PDO $pdo)
    {
    }

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

    /** GET /caller – hlavní pracovní obrazovka */
    public function getIndex(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['navolavacka']);

        $callerId = (int) $user['id'];
        $flash    = crm_flash_take();
        $csrf     = crm_csrf_token();
        $validCallerTabs = ['aktivni', 'callback', 'nedovolano', 'navolane', 'prohra', 'izolace', 'chybny', 'chybne_oz', 'vykon'];
        $tab      = in_array((string) ($_GET['tab'] ?? 'aktivni'), $validCallerTabs, true)
                        ? (string) ($_GET['tab'] ?? 'aktivni') : 'aktivni';
        $page     = max(1, (int) ($_GET['page'] ?? 1));

        // ── Migrace: nové sloupce contact_oz_flags (běží vždy, bezpečně) ──────
        foreach ([
            "ALTER TABLE `contact_oz_flags` ADD COLUMN `caller_comment`   TEXT NULL DEFAULT NULL",
            "ALTER TABLE `contact_oz_flags` ADD COLUMN `caller_confirmed` TINYINT(1) NOT NULL DEFAULT 0",
            "ALTER TABLE `contact_oz_flags` ADD COLUMN `oz_comment`       TEXT NULL DEFAULT NULL",
            "ALTER TABLE `contact_oz_flags` ADD COLUMN `oz_confirmed`     TINYINT(1) NOT NULL DEFAULT 0",
        ] as $_migrSql) {
            try { $this->pdo->exec($_migrSql); } catch (\PDOException $e) { crm_db_log_error($e, __METHOD__); }
        }

        // ── Lazy midnight reset: NEDOVOLANO → ASSIGNED po změně dne ──────────
        $this->pdo->prepare(
            "UPDATE contacts
             SET stav = 'ASSIGNED', updated_at = NOW(3)
             WHERE stav = 'NEDOVOLANO'
               AND assigned_caller_id = :cid
               AND DATE(updated_at) < CURDATE()"
        )->execute(['cid' => $callerId]);

        // ── Regiony přiřazené navolávačce ─────────────────────────────────────
        $urStmt = $this->pdo->prepare(
            'SELECT region FROM user_regions WHERE user_id = :uid ORDER BY region'
        );
        $urStmt->execute(['uid' => $callerId]);
        $callerRegions    = $urStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $hasCallerRegions = $callerRegions !== [];

        // ── Region filtr (jen pro tab aktivni) ───────────────────────────────
        $selectedRegion   = trim((string) ($_GET['region'] ?? ''));
        $availableRegions = [];
        $regionCounts     = [];

        if ($tab === 'aktivni') {
            if ($hasCallerRegions) {
                $ph = implode(',', array_fill(0, count($callerRegions), '?'));
                $avStmt = $this->pdo->prepare(
                    "SELECT DISTINCT region FROM contacts
                     WHERE stav='READY' AND operator IN('TM','O2')
                       AND assigned_caller_id IS NULL AND region IN($ph) AND region!=''
                     ORDER BY region"
                );
                $avStmt->execute($callerRegions);
            } else {
                $avStmt = $this->pdo->query(
                    "SELECT DISTINCT region FROM contacts
                     WHERE stav='READY' AND operator IN('TM','O2')
                       AND assigned_caller_id IS NULL AND region!=''
                     ORDER BY region"
                );
            }
            $availableRegions = $avStmt ? ($avStmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];

            if ($selectedRegion !== '' && !in_array($selectedRegion, $availableRegions, true)) {
                $selectedRegion = '';
            }

            if ($availableRegions !== []) {
                $ph2 = implode(',', array_fill(0, count($availableRegions), '?'));
                // Počty: volné (unclaimed) + caller's vlastní zamčené v daném regionu
                $rcStmt = $this->pdo->prepare(
                    "SELECT region, COUNT(*) AS cnt FROM contacts
                     WHERE stav='READY' AND operator IN('TM','O2')
                       AND assigned_caller_id IS NULL AND region IN($ph2)
                       AND (locked_by IS NULL OR locked_until < NOW(3) OR locked_by = ?)
                     GROUP BY region"
                );
                $rcStmt->execute(array_merge($availableRegions, [$callerId]));
                foreach ($rcStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $regionCounts[(string) $row['region']] = (int) $row['cnt'];
                }
            }
        }

        // ── Měsíční filtr pro "rostoucí" taby (default = aktuální měsíc) ──
        // Aktivní taby (aktivni, callback, nedovolano, izolace, vykon) filtr nemají.
        $useMonthFilter   = in_array($tab, ['navolane', 'prohra', 'chybny', 'chybne_oz'], true);
        $tabMonthOptions  = [];
        $selectedMonthKey = '';
        $filterYear       = (int) date('Y');
        $filterMonth      = (int) date('n');
        if ($useMonthFilter) {
            [$filterYear, $filterMonth] = self::parseMonthKey(
                (string) ($_GET['month_key'] ?? ''),
                $filterYear,
                $filterMonth
            );
            $selectedMonthKey = sprintf('%04d-%02d', $filterYear, $filterMonth);
            $now = time();
            for ($i = 0; $i < 12; $i++) {
                $ts = strtotime("-{$i} months", $now);
                $tabMonthOptions[] = [
                    'key'   => date('Y-m', $ts),
                    'label' => self::czechMonthName((int) date('n', $ts)) . ' ' . date('Y', $ts),
                ];
            }
        }

        $totalCount = 0;
        $totalPages = 1;

        if ($tab === 'callback') {
            $stmt = $this->pdo->prepare(
                'SELECT c.*, u.jmeno AS sales_name
                 FROM contacts c
                 LEFT JOIN users u ON u.id = c.assigned_sales_id
                 WHERE c.stav = \'CALLBACK\'
                   AND (c.assigned_caller_id = :cid OR c.assigned_caller_id IS NULL)
                 ORDER BY c.callback_at ASC
                 LIMIT 200'
            );
            $stmt->execute(['cid' => $callerId]);
        } elseif ($tab === 'nedovolano') {
            $stmt = $this->pdo->prepare(
                'SELECT c.*, u.jmeno AS sales_name
                 FROM contacts c
                 LEFT JOIN users u ON u.id = c.assigned_sales_id
                 WHERE c.assigned_caller_id = :cid AND c.stav = \'NEDOVOLANO\'
                 ORDER BY c.updated_at DESC
                 LIMIT 200'
            );
            $stmt->execute(['cid' => $callerId]);
        } elseif ($tab === 'navolane') {
            // Vylučujeme kontakty s flagem od OZ — ty patří do tabu 'chybne_oz'.
            $stmt = $this->pdo->prepare(
                "SELECT c.*, u.jmeno AS sales_name
                 FROM contacts c
                 LEFT JOIN users u ON u.id = c.assigned_sales_id
                 WHERE c.assigned_caller_id = :cid
                   AND c.stav IN ('CALLED_OK', 'FOR_SALES')
                   AND YEAR(c.datum_volani)  = :y
                   AND MONTH(c.datum_volani) = :m
                   AND NOT EXISTS (
                       SELECT 1 FROM contact_oz_flags f WHERE f.contact_id = c.id
                   )
                 ORDER BY c.datum_volani DESC, c.updated_at DESC
                 LIMIT 100"
            );
            $stmt->execute(['cid' => $callerId, 'y' => $filterYear, 'm' => $filterMonth]);
        } elseif ($tab === 'prohra') {
            $stmt = $this->pdo->prepare(
                'SELECT c.*, u.jmeno AS sales_name
                 FROM contacts c
                 LEFT JOIN users u ON u.id = c.assigned_sales_id
                 WHERE c.assigned_caller_id = :cid
                   AND c.stav IN (\'CALLED_BAD\', \'NEZAJEM\')
                   AND YEAR(c.datum_volani)  = :y
                   AND MONTH(c.datum_volani) = :m
                 ORDER BY c.datum_volani DESC, c.updated_at DESC
                 LIMIT 100'
            );
            $stmt->execute(['cid' => $callerId, 'y' => $filterYear, 'm' => $filterMonth]);
        } elseif ($tab === 'izolace') {
            $stmt = $this->pdo->prepare(
                'SELECT c.*, u.jmeno AS sales_name
                 FROM contacts c
                 LEFT JOIN users u ON u.id = c.assigned_sales_id
                 WHERE c.assigned_caller_id = :cid AND c.stav = \'IZOLACE\'
                 ORDER BY c.updated_at DESC
                 LIMIT 100'
            );
            $stmt->execute(['cid' => $callerId]);
        } elseif ($tab === 'chybny') {
            $stmt = $this->pdo->prepare(
                'SELECT c.*, u.jmeno AS sales_name
                 FROM contacts c
                 LEFT JOIN users u ON u.id = c.assigned_sales_id
                 WHERE c.assigned_caller_id = :cid AND c.stav = \'CHYBNY_KONTAKT\'
                   AND YEAR(c.updated_at)  = :y
                   AND MONTH(c.updated_at) = :m
                 ORDER BY c.updated_at DESC
                 LIMIT 100'
            );
            $stmt->execute(['cid' => $callerId, 'y' => $filterYear, 'm' => $filterMonth]);
        } elseif ($tab === 'chybne_oz') {
            // ── Chybné leady nahlášené OZ — přes contact_oz_flags ──
            $stmt = $this->pdo->prepare(
                "SELECT c.id, c.firma, c.telefon, c.region, c.datum_volani,
                        f.reason              AS oz_reason,
                        f.flagged_at          AS oz_flagged_at,
                        f.caller_comment,
                        f.caller_confirmed,
                        f.oz_comment,
                        f.oz_confirmed,
                        COALESCE(ou.jmeno, '—') AS oz_name,
                        COALESCE(wu.stav, '')   AS oz_workflow_stav
                 FROM contact_oz_flags f
                 JOIN contacts c   ON c.id   = f.contact_id
                 JOIN users ou     ON ou.id  = f.oz_id
                 LEFT JOIN oz_contact_workflow wu
                        ON wu.contact_id = f.contact_id AND wu.oz_id = f.oz_id
                 WHERE c.assigned_caller_id = :cid
                   AND YEAR(f.flagged_at)  = :y
                   AND MONTH(f.flagged_at) = :m
                 ORDER BY (f.caller_confirmed + f.oz_confirmed) ASC, f.flagged_at DESC
                 LIMIT 100"
            );
            $stmt->execute(['cid' => $callerId, 'y' => $filterYear, 'm' => $filterMonth]);
        } else {
            // ── Aktivní: READY pool s exkluzivním claimem (locked_by) + vlastní ASSIGNED ──

            // 1. Uvolnit expirované zámky tohoto callera (READY kontakty co nestačil zpracovat)
            $this->pdo->prepare(
                "UPDATE contacts SET locked_by = NULL, locked_until = NULL
                 WHERE locked_by = :cid AND locked_until < NOW(3) AND stav = 'READY'"
            )->execute(['cid' => $callerId]);

            // 2. Prodloužit stávající platné zámky (každé načtení stránky = +30 min)
            $this->pdo->prepare(
                "UPDATE contacts SET locked_until = NOW(3) + INTERVAL 30 MINUTE
                 WHERE locked_by = :cid AND stav = 'READY' AND locked_until > NOW(3)"
            )->execute(['cid' => $callerId]);

            // 3. Kolik kontaktů má caller aktuálně zamčeno z poolu?
            $lockedCntStmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM contacts
                 WHERE locked_by = :cid AND stav = 'READY' AND locked_until > NOW(3)"
            );
            $lockedCntStmt->execute(['cid' => $callerId]);
            $lockedCount = (int) $lockedCntStmt->fetchColumn();

            // 4. Doklaiming: atomicky zabrat volné kontakty do PAGE_SIZE
            $needed = self::PAGE_SIZE - $lockedCount;
            if ($needed > 0) {
                $claimWhere  = "stav = 'READY' AND operator IN('TM','O2') AND assigned_caller_id IS NULL
                                AND (locked_by IS NULL OR locked_until < NOW(3))";
                $claimParams = [];

                if ($selectedRegion !== '') {
                    $claimWhere  .= ' AND region = ?';
                    $claimParams[] = $selectedRegion;
                } elseif ($hasCallerRegions) {
                    $ph = implode(',', array_fill(0, count($callerRegions), '?'));
                    $claimWhere  .= " AND region IN ($ph)";
                    $claimParams = $callerRegions;
                }

                $this->pdo->beginTransaction();
                try {
                    $idStmt = $this->pdo->prepare(
                        "SELECT id FROM contacts WHERE {$claimWhere}
                         ORDER BY created_at ASC LIMIT {$needed} FOR UPDATE"
                    );
                    $idStmt->execute($claimParams);
                    $ids = $idStmt->fetchAll(PDO::FETCH_COLUMN);

                    if ($ids !== []) {
                        $ph2 = implode(',', array_fill(0, count($ids), '?'));
                        $this->pdo->prepare(
                            "UPDATE contacts SET locked_by = ?, locked_until = NOW(3) + INTERVAL 30 MINUTE
                             WHERE id IN ($ph2)"
                        )->execute(array_merge([$callerId], $ids));
                    }
                    $this->pdo->commit();
                } catch (\Throwable $e) {
                    if ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                }
            }

            // 5. Načíst kontakty: caller's zamčené READY pool + vlastní ASSIGNED
            $regionExtraWhere = $selectedRegion !== '' ? 'AND c.region = :reg' : '';

            $sql = "SELECT c.*, u.jmeno AS sales_name,
                           CASE WHEN c.assigned_caller_id IS NULL THEN 1 ELSE 0 END AS is_pool
                    FROM contacts c
                    LEFT JOIN users u ON u.id = c.assigned_sales_id
                    WHERE (
                      (c.stav = 'READY' AND c.locked_by = :cid_pool AND c.locked_until > NOW(3)
                       AND c.assigned_caller_id IS NULL {$regionExtraWhere})
                      OR (c.assigned_caller_id = :cid_own AND c.stav = 'ASSIGNED')
                    )
                    ORDER BY is_pool ASC, c.created_at ASC";

            $mainParams = ['cid_pool' => $callerId, 'cid_own' => $callerId];
            if ($selectedRegion !== '') {
                $mainParams['reg'] = $selectedRegion;
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($mainParams);

            $totalCount = 0; // Paginace nahrazena exkluzivním claimem
            $totalPages = 1;
        }

        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($contacts)) {
            $contacts = [];
        }

        // ── Obohatit kontakty o info o sdílených telefonech ──────────────────
        // (operátorka vidí "tento telefon používá X dalších firem" + poslední volání)
        if ($contacts !== []) {
            $contacts = $this->enrichSharedPhoneInfo($contacts);
        }

        // ── Počty pro taby ────────────────────────────────────────────────────
        $counts = $this->pdo->prepare(
            'SELECT
                SUM(CASE WHEN stav IN (\'NEW\', \'ASSIGNED\') THEN 1 ELSE 0 END)          AS aktivni,
                SUM(CASE WHEN stav = \'CALLBACK\'
                          AND (assigned_caller_id = :cid1 OR assigned_caller_id IS NULL)
                          THEN 1 ELSE 0 END)                                               AS callback,
                SUM(CASE WHEN stav = \'NEDOVOLANO\' THEN 1 ELSE 0 END)                    AS nedovolano,
                SUM(CASE WHEN stav IN (\'CALLED_OK\', \'FOR_SALES\') THEN 1 ELSE 0 END)   AS navolane,
                SUM(CASE WHEN stav IN (\'CALLED_BAD\', \'NEZAJEM\') THEN 1 ELSE 0 END)    AS prohra,
                SUM(CASE WHEN stav = \'IZOLACE\' THEN 1 ELSE 0 END)                      AS izolace,
                SUM(CASE WHEN stav = \'CHYBNY_KONTAKT\' THEN 1 ELSE 0 END)               AS chybny
             FROM contacts
             WHERE assigned_caller_id = :cid2
               OR (stav = \'CALLBACK\' AND assigned_caller_id IS NULL)'
        );
        $counts->execute(['cid1' => $callerId, 'cid2' => $callerId]);
        $tabCounts = $counts->fetch(PDO::FETCH_ASSOC)
            ?: ['aktivni' => 0, 'callback' => 0, 'nedovolano' => 0, 'navolane' => 0, 'prohra' => 0, 'izolace' => 0, 'chybny' => 0];

        // Odečíst flagged kontakty z 'navolane' (patří jen do tabu 'chybne_oz')
        try {
            $flagInNavolaneStmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM contact_oz_flags f
                 JOIN contacts c ON c.id = f.contact_id
                 WHERE c.assigned_caller_id = :cid
                   AND c.stav IN ('CALLED_OK', 'FOR_SALES')"
            );
            $flagInNavolaneStmt->execute(['cid' => $callerId]);
            $flagInNavolaneCount = (int) $flagInNavolaneStmt->fetchColumn();
            $tabCounts['navolane'] = max(0, ((int) ($tabCounts['navolane'] ?? 0)) - $flagInNavolaneCount);
        } catch (\PDOException) {
            // contact_oz_flags neexistuje – necháváme původní count
        }

        // Počet chybných leadů od OZ (samostatný count přes contact_oz_flags)
        try {
            $chybneOzCount = $this->pdo->prepare(
                "SELECT COUNT(*) FROM contact_oz_flags f
                 JOIN contacts c ON c.id = f.contact_id
                 WHERE c.assigned_caller_id = :cid"
            );
            $chybneOzCount->execute(['cid' => $callerId]);
            $tabCounts['chybne_oz'] = (int) $chybneOzCount->fetchColumn();
        } catch (\PDOException) {
            $tabCounts['chybne_oz'] = 0;
        }

        // Přidat caller's zamčené READY kontakty do počtu tab "aktivní"
        $lockedForBadge = $this->pdo->prepare(
            "SELECT COUNT(*) FROM contacts
             WHERE locked_by = :cid AND stav = 'READY' AND locked_until > NOW(3)"
        );
        $lockedForBadge->execute(['cid' => $callerId]);
        $tabCounts['aktivni'] = (int) ($tabCounts['aktivni'] ?? 0) + (int) $lockedForBadge->fetchColumn();

        // Obchodáci seskupení dle regionu
        $salesStmt = $this->pdo->prepare(
            'SELECT u.id, u.jmeno, ur.region
             FROM users u
             JOIN user_regions ur ON ur.user_id = u.id
             WHERE u.role = \'obchodak\' AND u.aktivni = 1
             UNION
             SELECT u.id, u.jmeno, u.primary_region AS region
             FROM users u
             WHERE u.role = \'obchodak\' AND u.aktivni = 1
               AND u.primary_region IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM user_regions ur2 WHERE ur2.user_id = u.id)
             ORDER BY jmeno ASC'
        );
        $salesStmt->execute();
        $salesByRegion = [];
        $allSales = [];
        foreach ($salesStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $salesByRegion[(string) $row['region']][] = ['id' => (int) $row['id'], 'jmeno' => (string) $row['jmeno']];
            $allSales[(int) $row['id']] = (string) $row['jmeno'];
        }
        $allSalesList = [];
        foreach ($allSales as $sid => $sjmeno) {
            $allSalesList[] = ['id' => $sid, 'jmeno' => $sjmeno];
        }

        // Majitel jako výchozí OZ pro sdílené callbacky
        $majitelRow = $this->pdo->query(
            'SELECT id, jmeno FROM users WHERE role = \'majitel\' AND aktivni = 1 ORDER BY id ASC LIMIT 1'
        );
        $majitel = $majitelRow ? $majitelRow->fetch(PDO::FETCH_ASSOC) : null;

        // Výchozí OZ ze session
        $defaultSalesId = (int) ($_SESSION['crm_caller_def_sales_' . $callerId] ?? 0);

        // Dnešní statistiky
        $todayStmt = $this->pdo->prepare(
            'SELECT
                COUNT(*)                                               AS total_calls,
                SUM(CASE WHEN stav = \'CALLED_OK\' THEN 1 ELSE 0 END) AS wins_today
             FROM contacts
             WHERE assigned_caller_id = :cid
               AND datum_volani IS NOT NULL
               AND DATE(datum_volani) = CURDATE()'
        );
        $todayStmt->execute(['cid' => $callerId]);
        $todayStats = $todayStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_calls' => 0, 'wins_today' => 0];

        // Denní cíl
        $goalRow = $this->pdo->query(
            'SELECT target_calls, target_wins FROM daily_goals WHERE role = \'navolavacka\' LIMIT 1'
        );
        $dailyGoal = ($goalRow ? $goalRow->fetch(PDO::FETCH_ASSOC) : null)
            ?: ['target_calls' => 0, 'target_wins' => 0];

        // Odměna za výhru (základní sazba)
        $rewardRow = $this->pdo->query(
            'SELECT amount_czk FROM caller_rewards_config
             WHERE valid_from <= CURDATE() AND (valid_to IS NULL OR valid_to >= CURDATE())
             ORDER BY valid_from DESC LIMIT 1'
        );
        $rewardPerWin = $rewardRow ? (float) ($rewardRow->fetchColumn() ?: 0) : 0.0;

        // ── Měsíční statistiky + bonusový systém ─────────────────────────────
        $curYear  = (int) date('Y');
        $curMonth = (int) date('n');

        // Výhry tohoto callera v aktuálním měsíci (z workflow_log)
        $mWinsStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM workflow_log
             WHERE user_id = :cid AND new_status = 'CALLED_OK'
               AND YEAR(created_at) = :y AND MONTH(created_at) = :m"
        );
        $mWinsStmt->execute(['cid' => $callerId, 'y' => $curYear, 'm' => $curMonth]);
        $monthWins = (int) $mWinsStmt->fetchColumn();

        // Chybné leady tohoto měsíce (odečtou se z výplaty)
        $monthChybne = 0;
        try {
            $chybneStmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM contact_oz_flags f
                 JOIN contacts c ON c.id = f.contact_id
                 WHERE c.assigned_caller_id = :cid
                   AND YEAR(f.flagged_at) = :y AND MONTH(f.flagged_at) = :m"
            );
            $chybneStmt->execute(['cid' => $callerId, 'y' => $curYear, 'm' => $curMonth]);
            $monthChybne = (int) $chybneStmt->fetchColumn();
        } catch (\PDOException $e) { crm_db_log_error($e, __METHOD__); }
        // Čisté placené výhry = výhry − chybné leady (min. 0)
        $monthWinsPaid = max(0, $monthWins - $monthChybne);

        // Měsíční cíl + bonusy
        try {
            $mgRow = $this->pdo->query(
                "SELECT target_wins, bonus1_at_pct, bonus1_pct, bonus2_at_pct, bonus2_pct, motiv_enabled
                 FROM monthly_goals WHERE valid_to IS NULL ORDER BY id DESC LIMIT 1"
            );
            $monthlyGoal = ($mgRow ? $mgRow->fetch(PDO::FETCH_ASSOC) : null) ?: null;
        } catch (\PDOException $e) {
            $monthlyGoal = null; // Tabulka ještě neexistuje
        }
        $monthlyGoal ??= ['target_wins' => 150, 'bonus1_at_pct' => 100, 'bonus1_pct' => 5.00,
                          'bonus2_at_pct' => 120, 'bonus2_pct' => 5.00, 'motiv_enabled' => 0];

        // Prahové hodnoty výher
        $mTgt  = (int) $monthlyGoal['target_wins'];
        $mT1   = (int) round($mTgt * (int) $monthlyGoal['bonus1_at_pct'] / 100);
        $mT2   = (int) round($mTgt * (int) $monthlyGoal['bonus2_at_pct'] / 100);
        $mP1   = (float) $monthlyGoal['bonus1_pct'] / 100;
        $mP2   = (float) $monthlyGoal['bonus2_pct'] / 100;

        // Výdělek s bonusy (marginální pásy) — počítá se z čistých placených výher
        $baseWins  = min($monthWinsPaid, $mT1);
        $tier1Wins = max(0, min($monthWinsPaid, $mT2) - $mT1);
        $tier2Wins = max(0, $monthWinsPaid - $mT2);
        $monthEarnings = ($baseWins  * $rewardPerWin)
                       + ($tier1Wins * $rewardPerWin * (1 + $mP1))
                       + ($tier2Wins * $rewardPerWin * (1 + $mP1 + $mP2));

        // Pracovní dny měsíce (Po–Pá) pro progress odhad
        $workDaysTotal   = AdminDailyGoalsController::workingDaysInMonth($curYear, $curMonth);
        $workDaysPassed  = 0;
        $today           = (int) date('j');
        for ($d = 1; $d <= $today; $d++) {
            if ((int) date('N', mktime(0, 0, 0, $curMonth, $d, $curYear)) <= 5) {
                $workDaysPassed++;
            }
        }
        $workDaysLeft = max(0, $workDaysTotal - $workDaysPassed);
        // Tempo: výhry/odpracovaný den → odhadovaný počet na konci měsíce
        $pace          = $workDaysPassed > 0 ? $monthWins / $workDaysPassed : 0;
        $projectedWins = $workDaysTotal > 0 ? (int) round($pace * $workDaysTotal) : $monthWins;

        // ── Kvóty + plnění OZ pro tento měsíc (pro win dropdown) ───────────────
        $ozProgress = []; // ozProgress[oz_id][region] = ['received' => int, 'target' => int]
        try {
            $tgtStmt = $this->pdo->prepare(
                'SELECT user_id, region, target_count FROM oz_targets
                 WHERE year = :y AND month = :m'
            );
            $tgtStmt->execute(['y' => $curYear, 'm' => $curMonth]);
            $ozTargetData = [];
            foreach ($tgtStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $ozTargetData[(int) $row['user_id']][(string) $row['region']] = (int) $row['target_count'];
            }

            $recStmt = $this->pdo->prepare(
                "SELECT assigned_sales_id AS uid, region, COUNT(*) AS cnt
                 FROM contacts
                 WHERE stav IN ('CALLED_OK', 'FOR_SALES')
                   AND assigned_sales_id IS NOT NULL
                   AND YEAR(datum_volani) = :y
                   AND MONTH(datum_volani) = :m
                 GROUP BY assigned_sales_id, region"
            );
            $recStmt->execute(['y' => $curYear, 'm' => $curMonth]);
            $ozReceivedData = [];
            foreach ($recStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $ozReceivedData[(int) $row['uid']][(string) $row['region']] = (int) $row['cnt'];
            }

            // Sloučit targets + received do ozProgress
            foreach ($ozTargetData as $uid => $regions) {
                foreach ($regions as $reg => $tgt) {
                    $ozProgress[$uid][$reg] = [
                        'received' => $ozReceivedData[$uid][$reg] ?? 0,
                        'target'   => $tgt,
                    ];
                }
            }
            foreach ($ozReceivedData as $uid => $regions) {
                foreach ($regions as $reg => $cnt) {
                    if (!isset($ozProgress[$uid][$reg])) {
                        $ozProgress[$uid][$reg] = ['received' => $cnt, 'target' => 0];
                    }
                }
            }
        } catch (\PDOException) {
            // oz_targets tabulka neexistuje – prázdný progress
        }

        $title = 'Moje kontakty';
        ob_start();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'caller' . DIRECTORY_SEPARATOR . 'index.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'base.php';
    }

    /** POST /caller/status – změna stavu kontaktu */
    public function postStatus(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['navolavacka']);
        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/caller');
        }

        $callerId        = (int) $user['id'];
        $contactId       = (int) ($_POST['contact_id'] ?? 0);
        $newStatus       = (string) ($_POST['new_status'] ?? '');
        $poznamka        = trim((string) ($_POST['poznamka'] ?? ''));
        $callbackDate    = trim((string) ($_POST['callback_at'] ?? ''));
        $rejectionReason = trim((string) ($_POST['rejection_reason'] ?? ''));

        $allowed = ['CALLED_OK', 'CALLED_BAD', 'CALLBACK', 'NEZAJEM', 'IZOLACE', 'CHYBNY_KONTAKT', 'NEDOVOLANO'];
        if (!in_array($newStatus, $allowed, true)) {
            crm_flash_set('Neplatný stav.');
            crm_redirect('/caller');
        }

        // Poznámka povinná (kromě NEDOVOLANO)
        if ($poznamka === '' && $newStatus !== 'NEDOVOLANO') {
            crm_flash_set('Poznámka je povinná – bez ní nelze změnit stav.');
            crm_redirect('/caller');
        }
        if ($poznamka === '' && $newStatus === 'NEDOVOLANO') {
            $poznamka = 'Nedovoláno';
        }

        // Pro výhru musí být vybrán OZ
        $salesUser = null;
        if ($newStatus === 'CALLED_OK') {
            $salesId = (int) ($_POST['sales_id'] ?? 0);
            if ($salesId <= 0) {
                crm_flash_set('Výhra: je nutné vybrat obchodního zástupce.');
                crm_redirect('/caller');
            }
            $sc = $this->pdo->prepare(
                'SELECT id, jmeno FROM users WHERE id = :id AND role = \'obchodak\' AND aktivni = 1 LIMIT 1'
            );
            $sc->execute(['id' => $salesId]);
            $salesUser = $sc->fetch(PDO::FETCH_ASSOC);
            if (!$salesUser) {
                crm_flash_set('Vybraný obchodní zástupce neexistuje nebo není aktivní.');
                crm_redirect('/caller');
            }
        }

        // Ověření vlastnictví kontaktu:
        // - vlastní (assigned_caller_id = caller)
        // - sdílený callback (IS NULL AND stav=CALLBACK)
        // - READY z poolu (IS NULL AND stav=READY)
        $check = $this->pdo->prepare(
            'SELECT id, stav, nedovolano_count
             FROM contacts
             WHERE id = :id
               AND (
                 assigned_caller_id = :cid
                 OR (assigned_caller_id IS NULL AND stav IN (\'CALLBACK\', \'READY\'))
               )
             LIMIT 1'
        );
        $check->execute(['id' => $contactId, 'cid' => $callerId]);
        $contact = $check->fetch(PDO::FETCH_ASSOC);
        if (!$contact) {
            crm_flash_set('Kontakt nenalezen nebo vám nepatří.');
            crm_redirect('/caller');
        }

        $oldStatus = (string) $contact['stav'];
        $updates   = ['stav = :stav', 'datum_volani = NOW(3)', 'poznamka = :poz',
                      'assigned_caller_id = :cid_claim']; // Claim kontaktu
        $params    = ['stav' => $newStatus, 'id' => $contactId, 'poz' => $poznamka,
                      'cid_claim' => $callerId];

        // --- NEDOVOLANO: inkrementuj počet, po MAX_NEDOVOLANO přejdi na NEZAJEM ---
        if ($newStatus === 'NEDOVOLANO') {
            $currentCount = (int) ($contact['nedovolano_count'] ?? 0);
            $newCount     = $currentCount + 1;
            $updates[]    = 'nedovolano_count = :ndc';
            $params['ndc'] = $newCount;

            if ($newCount >= self::MAX_NEDOVOLANO) {
                // Automatický přechod na NEZAJEM
                $newStatus = 'NEZAJEM';
                $params['stav'] = 'NEZAJEM';
                $poznamka .= ' (auto: 3× nedovoláno → Nezájem)';
                $params['poz'] = $poznamka;
            }
        }

        // --- Rejection reason (Nezájem, Prohra) ---
        if (in_array($newStatus, ['NEZAJEM', 'CALLED_BAD'], true) && $rejectionReason !== '') {
            $validReasons = ['nezajem', 'cena', 'ma_smlouvu', 'spatny_kontakt', 'jine'];
            if (in_array($rejectionReason, $validReasons, true)) {
                $updates[]                    = 'rejection_reason = :rr';
                $params['rr'] = $rejectionReason;
            }
        }

        // --- CALLBACK: nastav datum, urči privátní vs sdílený ---
        if ($newStatus === 'CALLBACK' && $callbackDate !== '') {
            $updates[]    = 'callback_at = :cb';
            $params['cb'] = $callbackDate;

            $cbTs             = (int) strtotime($callbackDate);
            $sharedThreshold  = (int) strtotime('+' . self::SHARED_CALLBACK_DAYS . ' days');

            if ($cbTs > $sharedThreshold) {
                // Sdílený callback: zrušit přiřazení callera
                $updates[] = 'assigned_caller_id = NULL';
            }
            // Jinak zůstane assigned_caller_id = aktuální caller (privátní)
        }

        // --- Výhra: přiřadit OZ ---
        if ($newStatus === 'CALLED_OK' && $salesUser !== null) {
            $updates[] = 'assigned_sales_id = :sid';
            $updates[] = 'datum_predani = NOW(3)';
            $updates[] = 'assigned_caller_id = :cid_set';
            $params['sid']     = (int) $salesUser['id'];
            $params['cid_set'] = $callerId; // potvrdit kdo to uzavřel (pro sdílený callback)
        }

        // --- Izolace = zákaz kontaktování ---
        if ($newStatus === 'IZOLACE') {
            $updates[] = 'dnc_flag = 1';
        }

        // Pool zámek uvolnit — kontakt odchází z READY stavu
        $updates[] = 'locked_by = NULL';
        $updates[] = 'locked_until = NULL';

        $sql = 'UPDATE contacts SET ' . implode(', ', $updates) . ' WHERE id = :id';
        $this->pdo->prepare($sql)->execute($params);

        // Workflow log
        $this->pdo->prepare(
            'INSERT INTO workflow_log (contact_id, user_id, old_status, new_status, note, created_at)
             VALUES (:cid, :uid, :old, :new, :note, NOW(3))'
        )->execute([
            'cid'  => $contactId,
            'uid'  => $callerId,
            'old'  => $oldStatus,
            'new'  => $newStatus,
            'note' => $poznamka,
        ]);

        // Poznámka do contact_notes
        $this->pdo->prepare(
            'INSERT INTO contact_notes (contact_id, user_id, note, created_at)
             VALUES (:cid, :uid, :note, NOW(3))'
        )->execute(['cid' => $contactId, 'uid' => $callerId, 'note' => $poznamka]);

        $labels = [
            'CALLED_OK'      => 'Výhra' . ($salesUser !== null ? ' → předáno: ' . $salesUser['jmeno'] : ''),
            'CALLED_BAD'     => 'Prohra',
            'CALLBACK'       => 'Callback nastaven',
            'NEZAJEM'        => 'Nezájem',
            'IZOLACE'        => 'Izolace – kontakt zablokován',
            'CHYBNY_KONTAKT' => 'Chybný kontakt',
            'NEDOVOLANO'     => 'Nedovoláno',
        ];
        crm_flash_set(($labels[$newStatus] ?? $newStatus) . ': ' . $poznamka);
        crm_redirect('/caller');
    }

    /** GET /caller/calendar – přehled callbacků v kalendáři */
    public function getCalendar(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['navolavacka']);

        $callerId = (int) $user['id'];
        $csrf     = crm_csrf_token();
        $flash    = crm_flash_take();

        $year  = (int) ($_GET['y'] ?? date('Y'));
        $month = (int) ($_GET['m'] ?? date('n'));
        if ($month < 1)  { $month = 12; $year--; }
        if ($month > 12) { $month = 1;  $year++; }

        // Vlastní + sdílené callbacky
        $stmt = $this->pdo->prepare(
            'SELECT id, firma, telefon, callback_at
             FROM contacts
             WHERE stav = \'CALLBACK\'
               AND callback_at IS NOT NULL
               AND (assigned_caller_id = :cid OR assigned_caller_id IS NULL)
             ORDER BY callback_at ASC
             LIMIT 500'
        );
        $stmt->execute(['cid' => $callerId]);
        $allCallbacks = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $callbacksByDate = [];
        foreach ($allCallbacks as $cb) {
            $date = substr((string) $cb['callback_at'], 0, 10);
            $callbacksByDate[$date][] = $cb;
        }

        $upcoming = array_filter($allCallbacks, static function (array $cb): bool {
            return strtotime((string) $cb['callback_at']) >= time();
        });

        $title = 'Kalendář callbacků';
        ob_start();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'caller' . DIRECTORY_SEPARATOR . 'calendar.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'base.php';
    }

    /** GET /caller/callbacks.json – JSON nadcházejících callbacků (pro notifikace) */
    public function getCallbacksJson(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['navolavacka']);

        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');

        $callerId = (int) $user['id'];

        $stmt = $this->pdo->prepare(
            'SELECT id, firma, telefon, callback_at,
                    CASE WHEN assigned_caller_id IS NULL THEN 1 ELSE 0 END AS is_shared
             FROM contacts
             WHERE stav = \'CALLBACK\'
               AND callback_at BETWEEN DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                                   AND DATE_ADD(NOW(), INTERVAL 2 HOUR)
               AND (assigned_caller_id = :cid OR assigned_caller_id IS NULL)
             ORDER BY callback_at ASC'
        );
        $stmt->execute(['cid' => $callerId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        echo json_encode(['callbacks' => $rows, 'server_time' => date('Y-m-d H:i:s')]);
        exit;
    }

    /** GET /caller/pool-count.json – počet READY kontaktů pro badge (real-time polling) */
    public function getPoolCountJson(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['navolavacka']);

        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');

        $callerId = (int) $user['id'];

        // Zamčené READY kontakty (caller's exkluzivní sada z poolu)
        $lockedStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM contacts
             WHERE locked_by = :cid AND stav = 'READY' AND locked_until > NOW(3)"
        );
        $lockedStmt->execute(['cid' => $callerId]);
        $lockedCount = (int) $lockedStmt->fetchColumn();

        // Vlastní ASSIGNED kontakty
        $ownStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM contacts WHERE assigned_caller_id = :cid AND stav = 'ASSIGNED'"
        );
        $ownStmt->execute(['cid' => $callerId]);
        $ownCount = (int) $ownStmt->fetchColumn();

        echo json_encode(['count' => $lockedCount + $ownCount]);
        exit;
    }

    /** GET /caller/race.json – závod šneků: měsíční výhry všech navolávačů */
    public function getRaceJson(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['navolavacka']);

        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');

        $callerId = (int) $user['id'];

        try {
            // Měsíční cíl (vždy z monthly_goals, i když je motiv_enabled=0)
            $target = 150;
            try {
                $tRow = $this->pdo->query(
                    "SELECT target_wins FROM monthly_goals
                     WHERE valid_to IS NULL ORDER BY id DESC LIMIT 1"
                );
                if ($tRow) {
                    $target = max(1, (int) ($tRow->fetchColumn() ?: 150));
                }
            } catch (\PDOException $e) { /* tabulka ještě neexistuje → fallback 150 */ }

            // Výhry v aktuálním měsíci pro každou aktivní navolávačku
            $stmt = $this->pdo->query(
                "SELECT
                    u.id,
                    u.jmeno,
                    COALESCE(wm.wins_month, 0) AS wins
                 FROM users u
                 LEFT JOIN (
                     SELECT user_id, COUNT(*) AS wins_month
                     FROM workflow_log
                     WHERE new_status = 'CALLED_OK'
                       AND YEAR(created_at)  = YEAR(NOW())
                       AND MONTH(created_at) = MONTH(NOW())
                     GROUP BY user_id
                 ) wm ON wm.user_id = u.id
                 WHERE u.role = 'navolavacka' AND u.aktivni = 1
                 ORDER BY wins DESC, u.jmeno ASC"
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            $callers = [];
            foreach ($rows as $row) {
                $callers[] = [
                    'id'    => (int) $row['id'],
                    'name'  => (string) $row['jmeno'],
                    'is_me' => (int) $row['id'] === $callerId,
                    'wins'  => (int) $row['wins'],
                ];
            }

            echo json_encode([
                'ok'      => true,
                'target'  => $target,
                'callers' => $callers,
                'month'   => date('n') . '/' . date('Y'),
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode([
                'ok'      => false,
                'error'   => $e->getMessage(),
                'target'  => 150,
                'callers' => [],
                'month'   => date('n') . '/' . date('Y'),
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    /** POST /caller/flag-mismatch – navolávačka flaguje chybný operátor od čističky */
    public function postFlagMismatch(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['navolavacka']);

        header('Content-Type: application/json; charset=UTF-8');

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            echo json_encode(['ok' => false, 'error' => 'Neplatný CSRF token.']);
            exit;
        }

        $callerId  = (int) $user['id'];
        $contactId = (int) ($_POST['contact_id'] ?? 0);

        // Ověř přístup ke kontaktu
        $check = $this->pdo->prepare(
            "SELECT id, operator FROM contacts
             WHERE id=:id AND (assigned_caller_id=:cid OR stav='READY')
             LIMIT 1"
        );
        $check->execute(['id' => $contactId, 'cid' => $callerId]);
        $contact = $check->fetch(PDO::FETCH_ASSOC);

        if (!$contact) {
            echo json_encode(['ok' => false, 'error' => 'Kontakt nenalezen.']);
            exit;
        }

        // Najdi čističku která naposled nastavila READY status
        $cleanerStmt = $this->pdo->prepare(
            "SELECT user_id FROM workflow_log
             WHERE contact_id=:cid AND new_status='READY'
             ORDER BY created_at DESC LIMIT 1"
        );
        $cleanerStmt->execute(['cid' => $contactId]);
        $cleanerId = (int) ($cleanerStmt->fetchColumn() ?: 0);

        // Log do workflow_log (vidí majitel v admin přehledu)
        $note = 'Operátor nesedí: ' . ((string) ($contact['operator'] ?? '?'))
              . ' (flagováno navolávačkou #' . $callerId . ')'
              . ($cleanerId > 0 ? ' | čistička #' . $cleanerId : '');

        $this->pdo->prepare(
            "INSERT INTO workflow_log (contact_id, user_id, old_status, new_status, note, created_at)
             VALUES (:cid, :uid, :old, 'OPERATOR_MISMATCH', :note, NOW(3))"
        )->execute([
            'cid'  => $contactId,
            'uid'  => $callerId,
            'old'  => (string) ($contact['operator'] ?? ''),
            'note' => $note,
        ]);

        echo json_encode(['ok' => true]);
        exit;
    }

    /** POST /caller/set-default-sales – uloží výchozího OZ do session */
    public function postSetDefaultSales(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['navolavacka']);
        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/caller');
        }

        $callerId = (int) $user['id'];
        $salesId  = (int) ($_POST['sales_id'] ?? 0);

        if ($salesId > 0) {
            $sc = $this->pdo->prepare(
                'SELECT id FROM users WHERE id = :id AND role = \'obchodak\' AND aktivni = 1 LIMIT 1'
            );
            $sc->execute(['id' => $salesId]);
            if ($sc->fetch()) {
                $_SESSION['crm_caller_def_sales_' . $callerId] = $salesId;
            }
        } else {
            unset($_SESSION['crm_caller_def_sales_' . $callerId]);
        }

        crm_redirect('/caller');
    }

    /** POST /caller/contact/edit – inline editace pole kontaktu (JSON response) */
    public function postEditContact(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['navolavacka']);

        header('Content-Type: application/json; charset=UTF-8');

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            echo json_encode(['ok' => false, 'error' => 'Neplatný CSRF token.']);
            exit;
        }

        $callerId  = (int) $user['id'];
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        $field     = (string) ($_POST['field'] ?? '');
        $value     = trim((string) ($_POST['value'] ?? ''));

        $allowedFields = ['firma', 'telefon', 'email', 'ico', 'adresa', 'operator', 'prilez'];
        if (!in_array($field, $allowedFields, true)) {
            echo json_encode(['ok' => false, 'error' => 'Nepovolené pole.']);
            exit;
        }

        if ($field === 'firma' && $value === '') {
            echo json_encode(['ok' => false, 'error' => 'Název firmy nemůže být prázdný.']);
            exit;
        }

        // Navolávačka smí editovat: (a) svůj přidělený kontakt, (b) READY z poolu, nebo (c) sdílený callback
        $check = $this->pdo->prepare(
            "SELECT id FROM contacts
             WHERE id = :id
               AND (assigned_caller_id = :cid
                    OR (assigned_caller_id IS NULL AND stav IN ('READY', 'CALLBACK')))
             LIMIT 1"
        );
        $check->execute(['id' => $contactId, 'cid' => $callerId]);
        if (!$check->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'Přístup odepřen.']);
            exit;
        }

        $colMap = [
            'firma'    => 'firma',
            'telefon'  => 'telefon',
            'email'    => 'email',
            'ico'      => 'ico',
            'adresa'   => 'adresa',
            'operator' => 'operator',
            'prilez'   => 'prilez',
        ];
        $col = $colMap[$field];
        $this->pdo->prepare(
            "UPDATE contacts SET `{$col}` = :val, updated_at = NOW(3) WHERE id = :id"
        )->execute(['val' => ($value !== '' ? $value : null), 'id' => $contactId]);

        echo json_encode(['ok' => true, 'value' => $value]);
        exit;
    }

    /** GET /caller/search – vyhledávání v celé DB s možností změny stavu */
    public function getSearch(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['navolavacka']);

        $callerId = (int) $user['id'];
        $csrf     = crm_csrf_token();
        $flash    = crm_flash_take();
        $q        = trim((string) ($_GET['q'] ?? ''));
        $results  = [];

        if (strlen($q) >= 3) {
            // Hledá v celé DB — telefon nebo firma
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $stmt = $this->pdo->prepare(
                'SELECT c.id, c.firma, c.telefon, c.email, c.stav, c.operator,
                        c.region, c.poznamka, c.callback_at, c.assigned_caller_id,
                        c.nedovolano_count,
                        u.jmeno AS sales_name
                 FROM contacts c
                 LEFT JOIN users u ON u.id = c.assigned_sales_id
                 WHERE (c.firma LIKE :q1 OR c.telefon LIKE :q2)
                 ORDER BY c.firma ASC
                 LIMIT 50'
            );
            $stmt->execute(['q1' => $like, 'q2' => $like]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        // OZ pro akční formuláře v search výsledcích
        $salesStmt = $this->pdo->prepare(
            'SELECT u.id, u.jmeno, ur.region
             FROM users u
             JOIN user_regions ur ON ur.user_id = u.id
             WHERE u.role = \'obchodak\' AND u.aktivni = 1
             UNION
             SELECT u.id, u.jmeno, u.primary_region AS region
             FROM users u
             WHERE u.role = \'obchodak\' AND u.aktivni = 1
               AND u.primary_region IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM user_regions ur2 WHERE ur2.user_id = u.id)
             ORDER BY jmeno ASC'
        );
        $salesStmt->execute();
        $salesByRegion = [];
        $allSales = [];
        foreach ($salesStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $salesByRegion[(string) $row['region']][] = ['id' => (int) $row['id'], 'jmeno' => (string) $row['jmeno']];
            $allSales[(int) $row['id']] = (string) $row['jmeno'];
        }
        $allSalesList = [];
        foreach ($allSales as $sid => $sjmeno) {
            $allSalesList[] = ['id' => $sid, 'jmeno' => $sjmeno];
        }
        $defaultSalesId = (int) ($_SESSION['crm_caller_def_sales_' . $callerId] ?? 0);

        $title = 'Hledání kontaktu';
        ob_start();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'caller' . DIRECTORY_SEPARATOR . 'search.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'base.php';
    }

    /** GET /caller/stats – výkonnostní statistiky navolávačky */
    public function getStats(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['navolavacka']);

        $callerId = (int) $user['id'];
        $flash    = crm_flash_take();

        // Rok a měsíc — čteme month_key (YYYY-MM) z <select>
        [$year, $month] = self::parseMonthKey(
            (string) ($_GET['month_key'] ?? ''),
            (int) ($_GET['year'] ?? 0),
            (int) ($_GET['month'] ?? 0)
        );

        // Stavy které sledujeme
        $trackedStatuses = ['CALLED_OK', 'CALLED_BAD', 'CALLBACK', 'NEZAJEM',
                            'NEDOVOLANO', 'IZOLACE', 'CHYBNY_KONTAKT'];

        // ── Souhrnné počty za vybraný měsíc ──
        $sumStmt = $this->pdo->prepare(
            'SELECT new_status, COUNT(*) AS cnt
             FROM workflow_log
             WHERE user_id = :uid
               AND YEAR(created_at)  = :yr
               AND MONTH(created_at) = :mo
               AND new_status IN (\'CALLED_OK\',\'CALLED_BAD\',\'CALLBACK\',
                                  \'NEZAJEM\',\'NEDOVOLANO\',\'IZOLACE\',\'CHYBNY_KONTAKT\')
             GROUP BY new_status'
        );
        $sumStmt->execute(['uid' => $callerId, 'yr' => $year, 'mo' => $month]);
        $summaryRaw = $sumStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $summary = array_fill_keys($trackedStatuses, 0);
        foreach ($summaryRaw as $row) {
            $summary[(string) $row['new_status']] = (int) $row['cnt'];
        }

        // ── Denní breakdown (den → stav → počet) ──
        $dailyStmt = $this->pdo->prepare(
            'SELECT DAY(created_at) AS day, new_status, COUNT(*) AS cnt
             FROM workflow_log
             WHERE user_id = :uid
               AND YEAR(created_at)  = :yr
               AND MONTH(created_at) = :mo
               AND new_status IN (\'CALLED_OK\',\'CALLED_BAD\',\'CALLBACK\',
                                  \'NEZAJEM\',\'NEDOVOLANO\',\'IZOLACE\',\'CHYBNY_KONTAKT\')
             GROUP BY DAY(created_at), new_status
             ORDER BY DAY(created_at) ASC'
        );
        $dailyStmt->execute(['uid' => $callerId, 'yr' => $year, 'mo' => $month]);
        $dailyRaw = $dailyStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Sestavit pole [den => [stav => počet]]
        $daysInMonth = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
        $daily = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $daily[$d] = array_fill_keys($trackedStatuses, 0);
        }
        foreach ($dailyRaw as $row) {
            $d = (int) $row['day'];
            $s = (string) $row['new_status'];
            if (isset($daily[$d][$s])) {
                $daily[$d][$s] = (int) $row['cnt'];
            }
        }
        // Odfiltrovat dny bez jakékoliv aktivity
        $activeDays = array_filter($daily, static function (array $dayCounts): bool {
            return array_sum($dayCounts) > 0;
        });

        // ── Výběr měsíců: 1 dopředu + aktuální + 17 zpět ──
        $realMonthKey = date('Y') . '-' . date('m'); // skutečný aktuální měsíc pro zvýraznění
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

        $totalActions = array_sum($summary);
        $winRate = $totalActions > 0
            ? round($summary['CALLED_OK'] / $totalActions * 100, 1)
            : 0.0;

        $title = 'Můj výkon';
        ob_start();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'caller' . DIRECTORY_SEPARATOR . 'stats.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'base.php';
    }

    /** POST /caller/assign-sales – manuální předání obchodnímu zástupci */
    public function postAssignSales(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['navolavacka']);
        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/caller');
        }

        $callerId  = (int) $user['id'];
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        $salesId   = (int) ($_POST['sales_id'] ?? 0);

        if ($salesId <= 0) {
            crm_flash_set('Vyberte obchodního zástupce.');
            crm_redirect('/caller');
        }

        $check = $this->pdo->prepare(
            'SELECT id, stav FROM contacts WHERE id = :id AND assigned_caller_id = :cid LIMIT 1'
        );
        $check->execute(['id' => $contactId, 'cid' => $callerId]);
        $contact = $check->fetch(PDO::FETCH_ASSOC);
        if (!$contact) {
            crm_flash_set('Kontakt nenalezen nebo vám nepatří.');
            crm_redirect('/caller');
        }

        $salesCheck = $this->pdo->prepare(
            'SELECT id, jmeno FROM users WHERE id = :id AND role = \'obchodak\' AND aktivni = 1 LIMIT 1'
        );
        $salesCheck->execute(['id' => $salesId]);
        $salesUser = $salesCheck->fetch(PDO::FETCH_ASSOC);
        if (!$salesUser) {
            crm_flash_set('Vybraný obchodák neexistuje nebo není aktivní.');
            crm_redirect('/caller');
        }

        $oldStatus = (string) $contact['stav'];

        $this->pdo->prepare(
            'UPDATE contacts
             SET stav = \'FOR_SALES\', assigned_sales_id = :sid, datum_predani = NOW(3), updated_at = NOW(3)
             WHERE id = :id'
        )->execute(['sid' => $salesId, 'id' => $contactId]);

        $this->pdo->prepare(
            'INSERT INTO workflow_log (contact_id, user_id, old_status, new_status, note, created_at)
             VALUES (:cid, :uid, :old, \'FOR_SALES\', :note, NOW(3))'
        )->execute([
            'cid'  => $contactId,
            'uid'  => $callerId,
            'old'  => $oldStatus,
            'note' => 'Předáno obchodákovi: ' . $salesUser['jmeno'],
        ]);

        $this->pdo->prepare(
            'INSERT INTO assignment_log (contact_id, from_user_id, to_user_id, assignment_type, reason, created_at)
             VALUES (:cid, :from, :to, \'caller\', \'Manuální předání navolávačkou\', NOW(3))'
        )->execute([
            'cid'  => $contactId,
            'from' => $callerId,
            'to'   => $salesId,
        ]);

        crm_flash_set('Kontakt předán: ' . $salesUser['jmeno']);
        crm_redirect('/caller');
    }

    // ─────────────────────────────────────────────────────────────────
    //  POST /caller/chybny-objection
    //  Navolávačka oponuje chybnému leadu (napíše komentář) nebo přijme
    // ─────────────────────────────────────────────────────────────────

    public function postChybnyObjection(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['navolavacka']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/caller?tab=chybne_oz');
        }

        $callerId  = (int) $user['id'];
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        $comment   = trim((string) ($_POST['caller_comment'] ?? ''));
        $action    = (string) ($_POST['action'] ?? 'comment'); // 'comment' | 'accept'

        // Ověřit že flag patří kontaktu tohoto callera
        $fStmt = $this->pdo->prepare(
            "SELECT f.id, f.oz_id FROM contact_oz_flags f
             JOIN contacts c ON c.id = f.contact_id
             WHERE f.contact_id = :cid AND c.assigned_caller_id = :caller"
        );
        $fStmt->execute(['cid' => $contactId, 'caller' => $callerId]);
        $flag = $fStmt->fetch(PDO::FETCH_ASSOC);

        if (!$flag) {
            crm_flash_set('Záznam nenalezen.');
            crm_redirect('/caller?tab=chybne_oz');
        }

        if ($action === 'accept') {
            // Navolávačka přijímá — nastaví caller_confirmed = 1
            $this->pdo->prepare(
                'UPDATE contact_oz_flags
                 SET caller_confirmed = 1,
                     caller_comment   = CASE WHEN :has_comment THEN :comment ELSE caller_comment END
                 WHERE id = :id'
            )->execute([
                'id'          => (int) $flag['id'],
                'has_comment' => $comment !== '' ? 1 : 0,
                'comment'     => $comment !== '' ? $comment : '',
            ]);
            crm_flash_set('✅ Přijato — čeká se na uzavření ze strany OZ.');
        } else {
            // Navolávačka oponuje — uloží komentář (caller_confirmed = 0)
            if ($comment === '') {
                crm_flash_set('⚠ Zadejte komentář.');
                crm_redirect('/caller?tab=chybne_oz');
            }
            $this->pdo->prepare(
                'UPDATE contact_oz_flags
                 SET caller_comment = :comment, caller_confirmed = 0
                 WHERE id = :id'
            )->execute(['comment' => $comment, 'id' => (int) $flag['id']]);
            crm_flash_set('💬 Komentář odeslán OZ.');
        }

        crm_redirect('/caller?tab=chybne_oz');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Obohacení kontaktů o info o sdílených telefonech
    //
    //  Pro každý kontakt s telefonem:
    //   - phone_shared_count: kolik DALŠÍCH kontaktů má stejné číslo (digits-only)
    //   - phone_shared_firms: list (max 3) názvů těchto dalších firem
    //   - phone_last_status:  poslední záznam ve workflow_log od jakéhokoli kontaktu
    //                          se stejným číslem (mimo tento kontakt) — ['stav', 'when']
    //
    //  Operátorka pak v UI uvidí "⚠ Tento telefon používá X dalších firem,
    //   naposledy NEZÁJEM před 2 dny".
    //
    //  Implementace: 2 hromadné dotazy (žádný N+1 problem).
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * @param list<array<string,mixed>> $contacts
     * @return list<array<string,mixed>>
     */
    private function enrichSharedPhoneInfo(array $contacts): array
    {
        // Nejdřív posbíráme normalizované telefony z kontaktů
        $phoneByContact = [];   // contactId => phoneDigits
        $uniquePhones   = [];
        foreach ($contacts as $c) {
            $cid = (int) ($c['id'] ?? 0);
            $tel = (string) ($c['telefon'] ?? '');
            if ($cid <= 0 || $tel === '') continue;
            $digits = preg_replace('/\D+/', '', $tel) ?? '';
            if ($digits === '' || strlen($digits) < 6) continue; // ignoruj junk
            $phoneByContact[$cid] = $digits;
            $uniquePhones[$digits] = true;
        }
        if ($uniquePhones === []) return $contacts;
        $uniquePhones = array_keys($uniquePhones);

        // ── 1. Hromadný dotaz: kolik kontaktů má stejné číslo + kdo (firma) ──
        $shared = []; // phoneDigits => ['count' => int, 'firms' => [['id'=>x, 'firma'=>y], ...]]
        try {
            // Pro každý unikátní telefon zjistíme všechny kontakty se stejným number
            // (REGEXP_REPLACE pro normalizaci na digits-only).
            $placeholders = implode(',', array_fill(0, count($uniquePhones), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT id, firma, REGEXP_REPLACE(telefon, '[^0-9]+', '') AS phone_digits
                 FROM contacts
                 WHERE telefon IS NOT NULL AND TRIM(telefon) <> ''
                   AND REGEXP_REPLACE(telefon, '[^0-9]+', '') IN ($placeholders)"
            );
            $stmt->execute($uniquePhones);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $pd = (string) ($row['phone_digits'] ?? '');
                if ($pd === '') continue;
                if (!isset($shared[$pd])) $shared[$pd] = ['count' => 0, 'firms' => []];
                $shared[$pd]['count']++;
                if (count($shared[$pd]['firms']) < 6) {
                    $shared[$pd]['firms'][] = [
                        'id'    => (int) $row['id'],
                        'firma' => (string) ($row['firma'] ?? ''),
                    ];
                }
            }
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }

        // ── 2. Poslední workflow_log záznam pro každé sdílené číslo ──────────
        // Bere posledních N záznamů, vyfiltruje per-phone v PHP (jednodušší než SQL).
        $lastByPhone = []; // phoneDigits => ['stav' => x, 'when' => y, 'firma' => z, 'cid' => N]
        $sharedOnlyPhones = [];
        foreach ($shared as $pd => $info) {
            if ($info['count'] > 1) {
                $sharedOnlyPhones[] = $pd;
            }
        }
        if ($sharedOnlyPhones !== []) {
            try {
                $placeholders = implode(',', array_fill(0, count($sharedOnlyPhones), '?'));
                $stmt = $this->pdo->prepare(
                    "SELECT w.contact_id, w.new_status, w.created_at, c.firma,
                            REGEXP_REPLACE(c.telefon, '[^0-9]+', '') AS phone_digits
                     FROM workflow_log w
                     JOIN contacts c ON c.id = w.contact_id
                     WHERE c.telefon IS NOT NULL
                       AND REGEXP_REPLACE(c.telefon, '[^0-9]+', '') IN ($placeholders)
                       AND w.new_status IN ('CALLED_OK','CALLED_BAD','NEZAJEM','CALLBACK','NEDOVOLANO',
                                            'IZOLACE','CHYBNY_KONTAKT')
                     ORDER BY w.created_at DESC
                     LIMIT 5000"
                );
                $stmt->execute($sharedOnlyPhones);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $pd = (string) ($row['phone_digits'] ?? '');
                    if ($pd === '' || isset($lastByPhone[$pd])) continue; // bereme jen první (nejnovější)
                    $lastByPhone[$pd] = [
                        'cid'   => (int) $row['contact_id'],
                        'stav'  => (string) ($row['new_status'] ?? ''),
                        'when'  => (string) ($row['created_at'] ?? ''),
                        'firma' => (string) ($row['firma'] ?? ''),
                    ];
                }
            } catch (\PDOException $e) {
                crm_db_log_error($e, __METHOD__);
            }
        }

        // ── 3. Anotuj každý kontakt info o sdílení ──────────────────────────
        foreach ($contacts as &$c) {
            $cid = (int) ($c['id'] ?? 0);
            $pd  = $phoneByContact[$cid] ?? '';
            if ($pd === '' || !isset($shared[$pd])) {
                $c['phone_shared_count'] = 0;
                $c['phone_shared_firms'] = [];
                $c['phone_last_status']  = null;
                continue;
            }
            $info = $shared[$pd];
            $sharedCount = max(0, $info['count'] - 1); // mínus aktuální kontakt
            $c['phone_shared_count'] = $sharedCount;
            $c['phone_shared_firms'] = array_values(array_filter(
                $info['firms'],
                static fn ($f) => (int) $f['id'] !== $cid
            ));

            // Poslední call záznam — jen z JINÝCH kontaktů se stejným číslem
            $lastEntry = $lastByPhone[$pd] ?? null;
            if ($lastEntry !== null && $lastEntry['cid'] !== $cid) {
                $c['phone_last_status'] = [
                    'stav'  => $lastEntry['stav'],
                    'when'  => $lastEntry['when'],
                    'firma' => $lastEntry['firma'],
                ];
            } else {
                $c['phone_last_status'] = null;
            }
        }
        unset($c);

        return $contacts;
    }
}
