<?php
// app/controllers/AdminTeamStatsController.php
declare(strict_types=1);

/**
 * Výkonnostní přehled celého týmu pro majitele / superadmina.
 *
 * GET /admin/team-stats?role=navolavacka&year=2026&month=4
 *
 * Podporované role: navolavacka | cisticka | obchodak | backoffice
 */
final class AdminTeamStatsController
{
    /** Konfigurace sledovaných stavů a popisků pro každou roli.
     *  POŘADÍ ZÁLOŽEK ve view: cisticka → navolavacka → obchodak → backoffice
     *  (chronologie pipeline — kontakt prochází tímto směrem). */
    private const ROLE_CONFIG = [
        'cisticka' => [
            'label'    => 'Čističky',
            'icon'     => '🧹',
            'statuses' => ['READY', 'VF_SKIP'],
            'col_keys' => ['ready_tm', 'ready_o2', 'ready_total', 'vf_skip'],
            'columns'  => [
                'ready_tm'    => ['label' => 'TM',         'cls' => 'acol--tm'],
                'ready_o2'    => ['label' => 'O2',         'cls' => 'acol--o2'],
                'ready_total' => ['label' => 'TM+O2',      'cls' => 'acol--win'],
                'vf_skip'     => ['label' => 'VF skip',    'cls' => 'acol--bad'],
            ],
            'win_key' => 'ready_total',
        ],
        'navolavacka' => [
            'label'    => 'Navolávačky',
            'icon'     => '📞',
            'statuses' => ['CALLED_OK', 'CALLED_BAD', 'CALLBACK', 'NEZAJEM', 'NEDOVOLANO', 'IZOLACE', 'CHYBNY_KONTAKT'],
            'col_keys' => ['called_ok', 'called_bad', 'callback_c', 'nezajem', 'nedovolano', 'izolace', 'chybny'],
            'columns'  => [
                'called_ok'   => ['label' => 'Výhry',     'cls' => 'acol--win'],
                'called_bad'  => ['label' => 'Prohry',    'cls' => 'acol--bad'],
                'callback_c'  => ['label' => 'Callback',  'cls' => 'acol--cb'],
                'nezajem'     => ['label' => 'Nezájem',   'cls' => 'acol--nz'],
                'nedovolano'  => ['label' => 'Nedov.',    'cls' => 'acol--nd'],
                'izolace'     => ['label' => 'Izolace',   'cls' => 'acol--iz'],
                'chybny'      => ['label' => 'Chybný k.', 'cls' => 'acol--ch'],
            ],
            'win_key' => 'called_ok',
        ],
        'obchodak' => [
            'label'    => 'Obchodáci',
            'icon'     => '💼',
            'statuses' => ['FOR_SALES', 'APPROVED_BY_SALES', 'REJECTED_BY_SALES', 'DONE', 'ACTIVATED', 'CANCELLED'],
            'col_keys' => ['for_sales', 'approved', 'rejected', 'done', 'activated', 'cancelled'],
            'columns'  => [
                'for_sales'  => ['label' => 'Přijato',    'cls' => 'acol--cb'],
                'approved'   => ['label' => 'Schváleno',  'cls' => 'acol--win'],
                'rejected'   => ['label' => 'Zamítnuto',  'cls' => 'acol--bad'],
                'done'       => ['label' => 'Hotovo',     'cls' => 'acol--win'],
                'activated'  => ['label' => 'Aktivováno', 'cls' => 'acol--win'],
                'cancelled'  => ['label' => 'Storno',     'cls' => 'acol--bad'],
            ],
            'win_key' => 'approved',
        ],
        'backoffice' => [
            'label'    => 'Backoffice',
            'icon'     => '🗂️',
            'statuses' => ['BACKOFFICE', 'DONE', 'ACTIVATED', 'CANCELLED'],
            'col_keys' => ['backoffice', 'done', 'activated', 'cancelled'],
            'columns'  => [
                'backoffice' => ['label' => 'Přijato',    'cls' => 'acol--cb'],
                'done'       => ['label' => 'Hotovo',     'cls' => 'acol--win'],
                'activated'  => ['label' => 'Aktivováno', 'cls' => 'acol--win'],
                'cancelled'  => ['label' => 'Storno',     'cls' => 'acol--bad'],
            ],
            'win_key' => 'done',
        ],
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /** GET /admin/team-stats */
    public function getIndex(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        $flash = crm_flash_take();

        $role = (string) ($_GET['role'] ?? 'cisticka');
        if (!array_key_exists($role, self::ROLE_CONFIG)) { $role = 'cisticka'; }

        // Rok a měsíc — čteme month_key (YYYY-MM) z <select>
        [$year, $month] = self::parseMonthKey(
            (string) ($_GET['month_key'] ?? ''),
            (int) ($_GET['year'] ?? 0),
            (int) ($_GET['month'] ?? 0)
        );

        $cfg = self::ROLE_CONFIG[$role];

        // ── Pivot query — čistička potřebuje JOIN pro rozlišení TM/O2 ──
        if ($role === 'cisticka') {
            $callerRows = $this->queryCistickaStats($year, $month);
        } else {
            $callerRows = $this->queryGenericStats($role, $cfg, $year, $month);
        }

        // Celkové součty pro footer
        $totals = array_fill_keys($cfg['col_keys'], 0);
        $totals['total_actions'] = 0;
        foreach ($callerRows as $row) {
            foreach ($cfg['col_keys'] as $k) {
                $totals[$k] += (int) ($row[$k] ?? 0);
            }
            $totals['total_actions'] += (int) ($row['total_actions'] ?? 0);
        }

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

        $allRoles   = self::ROLE_CONFIG;
        $title      = 'Výkon týmu — ' . $cfg['label'];

        // ── Premium pipeline per role — extra sekce v dolní části view ──
        // Šetří query: jen pokud user vybral roli kde to dává smysl
        $premiumRows = $this->queryPremiumStats($role, $year, $month);

        ob_start();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'team_stats.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'base.php';
    }

    // ── Private helpers ────────────────────────────────────────────────────

    /** Generic pivot: GROUP BY user + new_status (pro navolavacka, obchodak, backoffice).
     *  POZN: pro navolavacka VYLUČUJEME premium leady (mají vlastní sekci dole),
     *  aby hlavní tabulka ukazovala jen standardní pipeline. */
    private function queryGenericStats(string $role, array $cfg, int $year, int $month): array
    {
        $statusList = $cfg['statuses'];
        $placeholders = implode(',', array_fill(0, count($statusList), '?'));

        // Pro navolávačku vyloučit premium kontakty — ty jsou ve spodní premium sekci
        $premiumExclude = $role === 'navolavacka'
            ? "AND NOT EXISTS (
                   SELECT 1 FROM premium_lead_pool plp
                   WHERE plp.contact_id = wl.contact_id
                     AND plp.cleaning_status = 'tradeable'
               )"
            : '';

        $sql = "SELECT
                    u.id                  AS user_id,
                    u.jmeno               AS jmeno,
                    wl.new_status         AS new_status,
                    COUNT(*)              AS cnt
                FROM users u
                LEFT JOIN workflow_log wl
                    ON  wl.user_id      = u.id
                    AND YEAR(wl.created_at)  = ?
                    AND MONTH(wl.created_at) = ?
                    AND wl.new_status IN ({$placeholders})
                    {$premiumExclude}
                WHERE u.role = ? AND u.aktivni = 1
                GROUP BY u.id, u.jmeno, wl.new_status
                ORDER BY u.jmeno ASC";

        $params = array_merge([$year, $month], $statusList, [$role]);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Pivot: [user_id => row]
        $users = [];
        foreach ($rows as $row) {
            $uid = (int) $row['user_id'];
            if (!isset($users[$uid])) {
                $users[$uid] = ['user_id' => $uid, 'jmeno' => $row['jmeno']];
                foreach ($cfg['col_keys'] as $k) { $users[$uid][$k] = 0; }
                $users[$uid]['total_actions']      = 0;
                $users[$uid]['unique_contacts']    = 0;
            }
            if ($row['new_status'] !== null) {
                $colKey = $this->statusToColKey($role, (string) $row['new_status']);
                if ($colKey !== null && isset($users[$uid][$colKey])) {
                    $users[$uid][$colKey] += (int) $row['cnt'];
                    $users[$uid]['total_actions'] += (int) $row['cnt'];
                }
            }
        }

        // Doplnit DISTINCT contact_id per user (= z kolika kontaktů celkem pracoval(a) v měsíci)
        // To je smysluplnější metrika než suma akcí (NEDOVOLANO 3× = 1 kontakt, ne 3).
        // POZN: stejné premium vyloučení jako výše — nemůžeme dvakrát počítat.
        $unqStmt = $this->pdo->prepare(
            "SELECT wl.user_id, COUNT(DISTINCT wl.contact_id) AS unique_contacts
             FROM workflow_log wl
             JOIN users u ON u.id = wl.user_id
             WHERE u.role = ? AND u.aktivni = 1
               AND YEAR(wl.created_at) = ? AND MONTH(wl.created_at) = ?
               AND wl.new_status IN ({$placeholders})
               {$premiumExclude}
             GROUP BY wl.user_id"
        );
        $unqStmt->execute(array_merge([$role, $year, $month], $statusList));
        foreach ($unqStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $uid = (int) $r['user_id'];
            if (isset($users[$uid])) {
                $users[$uid]['unique_contacts'] = (int) $r['unique_contacts'];
            }
        }

        // Zahrnout uživatele bez záznamů v daném měsíci
        if ($rows === [] || count($users) === 0) {
            $empStmt = $this->pdo->prepare(
                'SELECT id, jmeno FROM users WHERE role = ? AND aktivni = 1 ORDER BY jmeno ASC'
            );
            $empStmt->execute([$role]);
            foreach ($empStmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
                $uid = (int) $u['id'];
                if (!isset($users[$uid])) {
                    $users[$uid] = [
                        'user_id' => $uid, 'jmeno' => $u['jmeno'],
                        'total_actions' => 0, 'unique_contacts' => 0,
                    ];
                    foreach ($cfg['col_keys'] as $k) { $users[$uid][$k] = 0; }
                }
            }
        }

        // Pro OZ přidat speciální metriku "z navolaných" (= kolik mu navolávačka dohodila)
        // a konverze "uzavřeno / navoláno" — v hlavní stat tabulce.
        if ($role === 'obchodak') {
            $ozStmt = $this->pdo->prepare(
                "SELECT c.assigned_sales_id AS user_id,
                        COUNT(DISTINCT c.id) AS handed_to_oz
                 FROM contacts c
                 WHERE c.assigned_sales_id IS NOT NULL
                   AND c.datum_predani IS NOT NULL
                   AND YEAR(c.datum_predani)  = ?
                   AND MONTH(c.datum_predani) = ?
                 GROUP BY c.assigned_sales_id"
            );
            $ozStmt->execute([$year, $month]);
            $handedMap = [];
            foreach ($ozStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $handedMap[(int) $r['user_id']] = (int) $r['handed_to_oz'];
            }
            foreach ($users as &$u) {
                $uid = (int) $u['user_id'];
                $u['handed_to_oz'] = $handedMap[$uid] ?? 0;
                // Konverze = done (uzavřeno + aktivace) / handed_to_oz
                $closed = (int) ($u['done'] ?? 0) + (int) ($u['activated'] ?? 0);
                $u['oz_conversion_pct'] = $u['handed_to_oz'] > 0
                    ? round($closed / $u['handed_to_oz'] * 100, 1)
                    : 0.0;
            }
            unset($u);
        }

        usort($users, static fn($a, $b) => $b['total_actions'] <=> $a['total_actions']);
        return array_values($users);
    }

    /** Speciální pivot pro čističky — potřebuje JOIN contacts pro TM/O2 rozlišení. */
    private function queryCistickaStats(int $year, int $month): array
    {
        // POZN: vyloučíme premium kontakty — ty mají vlastní statistiky v premium sekci dole.
        // Hlavní tabulka tak ukazuje JEN standardní (první) čištění, ne druhé čištění z premium objednávek.
        $stmt = $this->pdo->prepare(
            "SELECT
                u.id                              AS user_id,
                u.jmeno                           AS jmeno,
                wl.new_status                     AS new_status,
                COALESCE(c.operator, '?')         AS operator,
                COUNT(*)                          AS cnt
             FROM users u
             LEFT JOIN workflow_log wl
                 ON  wl.user_id      = u.id
                 AND YEAR(wl.created_at)  = :yr
                 AND MONTH(wl.created_at) = :mo
                 AND wl.new_status IN ('READY', 'VF_SKIP')
                 AND NOT EXISTS (
                     SELECT 1 FROM premium_lead_pool plp
                     WHERE plp.contact_id = wl.contact_id
                       AND plp.cleaning_status IN ('tradeable','non_tradeable')
                 )
             LEFT JOIN contacts c ON c.id = wl.contact_id
             WHERE u.role = 'cisticka' AND u.aktivni = 1
             GROUP BY u.id, u.jmeno, wl.new_status, c.operator
             ORDER BY u.jmeno ASC"
        );
        $stmt->execute(['yr' => $year, 'mo' => $month]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $users = [];
        // Init: načti všechny čističky
        $empStmt = $this->pdo->prepare(
            'SELECT id, jmeno FROM users WHERE role = \'cisticka\' AND aktivni = 1 ORDER BY jmeno ASC'
        );
        $empStmt->execute();
        foreach ($empStmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $uid = (int) $u['id'];
            $users[$uid] = [
                'user_id' => $uid, 'jmeno' => $u['jmeno'],
                'ready_tm' => 0, 'ready_o2' => 0, 'ready_total' => 0,
                'vf_skip'  => 0, 'total_actions' => 0, 'unique_contacts' => 0,
            ];
        }

        // DISTINCT contact_id per čistička (z kolika kontaktů reálně pracovala — 1 lead 2× zpracovaný = 1)
        // Stejný premium exclude jako výše — premium druhé čištění se počítá v premium sekci.
        $unqStmt = $this->pdo->prepare(
            "SELECT wl.user_id, COUNT(DISTINCT wl.contact_id) AS unique_contacts
             FROM workflow_log wl
             JOIN users u ON u.id = wl.user_id
             WHERE u.role = 'cisticka' AND u.aktivni = 1
               AND YEAR(wl.created_at) = ? AND MONTH(wl.created_at) = ?
               AND wl.new_status IN ('READY','VF_SKIP')
               AND NOT EXISTS (
                   SELECT 1 FROM premium_lead_pool plp
                   WHERE plp.contact_id = wl.contact_id
                     AND plp.cleaning_status IN ('tradeable','non_tradeable')
               )
             GROUP BY wl.user_id"
        );
        $unqStmt->execute([$year, $month]);
        foreach ($unqStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $uid = (int) $r['user_id'];
            if (isset($users[$uid])) {
                $users[$uid]['unique_contacts'] = (int) $r['unique_contacts'];
            }
        }

        foreach ($rows as $row) {
            $uid = (int) $row['user_id'];
            if (!isset($users[$uid])) continue;
            if ($row['new_status'] === 'VF_SKIP') {
                $users[$uid]['vf_skip']       += (int) $row['cnt'];
                $users[$uid]['total_actions'] += (int) $row['cnt'];
            } elseif ($row['new_status'] === 'READY') {
                $op = strtoupper((string) $row['operator']);
                if ($op === 'O2') { $users[$uid]['ready_o2'] += (int) $row['cnt']; }
                else              { $users[$uid]['ready_tm'] += (int) $row['cnt']; }
                $users[$uid]['ready_total']   += (int) $row['cnt'];
                $users[$uid]['total_actions'] += (int) $row['cnt'];
            }
        }

        usort($users, static fn($a, $b) => $b['total_actions'] <=> $a['total_actions']);
        return array_values($users);
    }

    /** Mapuje new_status → col_key pro danou roli. */
    private function statusToColKey(string $role, string $status): ?string
    {
        $map = [
            'navolavacka' => [
                'CALLED_OK'      => 'called_ok',
                'CALLED_BAD'     => 'called_bad',
                'CALLBACK'       => 'callback_c',
                'NEZAJEM'        => 'nezajem',
                'NEDOVOLANO'     => 'nedovolano',
                'IZOLACE'        => 'izolace',
                'CHYBNY_KONTAKT' => 'chybny',
            ],
            'obchodak' => [
                'FOR_SALES'          => 'for_sales',
                'APPROVED_BY_SALES'  => 'approved',
                'REJECTED_BY_SALES'  => 'rejected',
                'DONE'               => 'done',
                'ACTIVATED'          => 'activated',
                'CANCELLED'          => 'cancelled',
            ],
            'backoffice' => [
                'BACKOFFICE' => 'backoffice',
                'DONE'       => 'done',
                'ACTIVATED'  => 'activated',
                'CANCELLED'  => 'cancelled',
            ],
        ];
        return $map[$role][$status] ?? null;
    }

    /** @return array{int, int} */
    private static function parseMonthKey(string $key, int $fallbackYear, int $fallbackMonth): array
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $key, $m)) {
            $y = (int) $m[1]; $mo = (int) $m[2];
        } else {
            $y  = $fallbackYear  > 0 ? $fallbackYear  : (int) date('Y');
            $mo = $fallbackMonth > 0 ? $fallbackMonth : (int) date('n');
        }
        if ($mo < 1 || $mo > 12)    { $mo = (int) date('n'); }
        if ($y < 2020 || $y > 2100) { $y  = (int) date('Y'); }
        return [$y, $mo];
    }

    private static function czechMonthName(int $m): string
    {
        return [
            1 => 'Leden', 2 => 'Únor', 3 => 'Březen', 4 => 'Duben',
            5 => 'Květen', 6 => 'Červen', 7 => 'Červenec', 8 => 'Srpen',
            9 => 'Září', 10 => 'Říjen', 11 => 'Listopad', 12 => 'Prosinec',
        ][$m] ?? (string) $m;
    }

    // ════════════════════════════════════════════════════════════════
    //  Premium pipeline statistiky per role
    //  Vrací list per uživatel s metrikami které dávají smysl pro tu roli.
    // ════════════════════════════════════════════════════════════════
    /**
     * @return list<array<string,mixed>>
     */
    private function queryPremiumStats(string $role, int $year, int $month): array
    {
        try {
            if ($role === 'cisticka') {
                // Per čistička: kolik vyčistila tradeable + non_tradeable + reklamace + Kč
                // Plus konverze % = obchodovatelných / vyčištěných celkem
                $stmt = $this->pdo->prepare(
                    "SELECT u.id   AS user_id,
                            u.jmeno AS jmeno,
                            COUNT(p.id) AS total,
                            SUM(CASE WHEN p.cleaning_status = 'tradeable'     THEN 1 ELSE 0 END) AS tradeable,
                            SUM(CASE WHEN p.cleaning_status = 'non_tradeable' THEN 1 ELSE 0 END) AS non_tradeable,
                            SUM(CASE WHEN p.flagged_for_refund = 1 THEN 1 ELSE 0 END)            AS refund,
                            COALESCE(SUM(CASE WHEN p.flagged_for_refund = 0 THEN po.price_per_lead ELSE 0 END), 0) AS earned_czk
                     FROM users u
                     LEFT JOIN premium_lead_pool p ON p.cleaner_id = u.id
                         AND p.cleaning_status IN ('tradeable','non_tradeable')
                         AND YEAR(p.cleaned_at)  = ?
                         AND MONTH(p.cleaned_at) = ?
                     LEFT JOIN premium_orders po ON po.id = p.order_id
                     WHERE u.role = 'cisticka' AND u.aktivni = 1
                     GROUP BY u.id, u.jmeno
                     ORDER BY u.jmeno ASC"
                );
                $stmt->execute([$year, $month]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                // Doplnit konverzi %
                foreach ($rows as &$r) {
                    $tot = (int) ($r['total'] ?? 0);
                    $tr  = (int) ($r['tradeable'] ?? 0);
                    $r['conversion_pct'] = $tot > 0 ? round($tr / $tot * 100, 1) : 0.0;
                }
                unset($r);
                return $rows;
            }

            if ($role === 'navolavacka') {
                // Per navolávačka: kolik premium navoláno (success/failed) + bonus celkem.
                // Konverze % = úspěšně / (úspěšně + neúspěšně) = úspěšnost premium hovorů.
                $stmt = $this->pdo->prepare(
                    "SELECT u.id    AS user_id,
                            u.jmeno  AS jmeno,
                            SUM(CASE WHEN p.call_status = 'success' THEN 1 ELSE 0 END) AS success_cnt,
                            SUM(CASE WHEN p.call_status = 'failed'  THEN 1 ELSE 0 END) AS failed_cnt,
                            COALESCE(SUM(CASE WHEN p.call_status = 'success' AND p.flagged_for_refund = 0
                                              THEN po.caller_bonus_per_lead ELSE 0 END), 0) AS bonus_czk
                     FROM users u
                     LEFT JOIN premium_lead_pool p ON p.caller_id = u.id
                         AND p.cleaning_status = 'tradeable'
                         AND YEAR(p.called_at)  = ?
                         AND MONTH(p.called_at) = ?
                     LEFT JOIN premium_orders po ON po.id = p.order_id
                     WHERE u.role = 'navolavacka' AND u.aktivni = 1
                     GROUP BY u.id, u.jmeno
                     ORDER BY u.jmeno ASC"
                );
                $stmt->execute([$year, $month]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($rows as &$r) {
                    $s = (int) ($r['success_cnt'] ?? 0);
                    $f = (int) ($r['failed_cnt']  ?? 0);
                    $r['call_total']     = $s + $f;
                    $r['conversion_pct'] = ($s + $f) > 0 ? round($s / ($s + $f) * 100, 1) : 0.0;
                }
                unset($r);
                return $rows;
            }

            if ($role === 'obchodak') {
                // Per OZ: počet objednávek + počty pool, dluh čističce + dluh navolávačkám + jak úspěšně se navoláno
                $stmt = $this->pdo->prepare(
                    "SELECT u.id   AS user_id,
                            u.jmeno AS jmeno,
                            (SELECT COUNT(*) FROM premium_orders po
                              WHERE po.oz_id = u.id
                                AND YEAR(po.created_at)  = ?
                                AND MONTH(po.created_at) = ?) AS orders_cnt,
                            (SELECT COUNT(*) FROM premium_lead_pool p
                              JOIN premium_orders po ON po.id = p.order_id
                              WHERE po.oz_id = u.id
                                AND p.cleaning_status IN ('tradeable','non_tradeable')
                                AND YEAR(p.cleaned_at) = ?
                                AND MONTH(p.cleaned_at) = ?) AS cleaned_cnt,
                            (SELECT COUNT(*) FROM premium_lead_pool p
                              JOIN premium_orders po ON po.id = p.order_id
                              WHERE po.oz_id = u.id
                                AND p.cleaning_status = 'tradeable'
                                AND p.call_status = 'success'
                                AND YEAR(p.called_at) = ?
                                AND MONTH(p.called_at) = ?) AS called_success,
                            (SELECT COUNT(DISTINCT c.id) FROM premium_lead_pool p
                              JOIN premium_orders po ON po.id = p.order_id
                              JOIN contacts c ON c.id = p.contact_id
                              WHERE po.oz_id = u.id
                                AND p.cleaning_status = 'tradeable'
                                AND p.call_status = 'success'
                                AND c.stav IN ('DONE','ACTIVATED')) AS closed_cnt,
                            COALESCE((SELECT SUM(po.price_per_lead *
                                (SELECT COUNT(*) FROM premium_lead_pool p
                                   WHERE p.order_id = po.id
                                     AND p.cleaning_status IN ('tradeable','non_tradeable')
                                     AND p.flagged_for_refund = 0))
                              FROM premium_orders po
                              WHERE po.oz_id = u.id
                                AND YEAR(po.created_at)  = ?
                                AND MONTH(po.created_at) = ?), 0) AS due_cleaner_czk,
                            COALESCE((SELECT SUM(po.caller_bonus_per_lead *
                                (SELECT COUNT(*) FROM premium_lead_pool p
                                   WHERE p.order_id = po.id
                                     AND p.cleaning_status = 'tradeable'
                                     AND p.call_status = 'success'
                                     AND p.flagged_for_refund = 0))
                              FROM premium_orders po
                              WHERE po.oz_id = u.id
                                AND YEAR(po.created_at)  = ?
                                AND MONTH(po.created_at) = ?), 0) AS due_caller_czk
                     FROM users u
                     WHERE u.role = 'obchodak' AND u.aktivni = 1
                     GROUP BY u.id, u.jmeno
                     ORDER BY u.jmeno ASC"
                );
                $stmt->execute([$year, $month, $year, $month, $year, $month, $year, $month, $year, $month]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                // OZ premium konverze:
                //   conversion_pct = úspěšně navolaných / vyčištěných (kvalita pool → hovor)
                //   business_pct   = uzavřených / úspěšně navolaných (úspěšnost OZ při uzavírání)
                foreach ($rows as &$r) {
                    $cleaned = (int) ($r['cleaned_cnt']    ?? 0);
                    $called  = (int) ($r['called_success'] ?? 0);
                    $closed  = (int) ($r['closed_cnt']     ?? 0);
                    $r['conversion_pct'] = $cleaned > 0 ? round($called / $cleaned * 100, 1) : 0.0;
                    $r['business_pct']   = $called  > 0 ? round($closed / $called  * 100, 1) : 0.0;
                }
                unset($r);
                return $rows;
            }
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }
        return [];
    }
}
