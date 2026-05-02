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
    /** Konfigurace sledovaných stavů a popisků pro každou roli */
    private const ROLE_CONFIG = [
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

        $role = (string) ($_GET['role'] ?? 'navolavacka');
        if (!array_key_exists($role, self::ROLE_CONFIG)) { $role = 'navolavacka'; }

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
        ob_start();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'team_stats.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'base.php';
    }

    // ── Private helpers ────────────────────────────────────────────────────

    /** Generic pivot: GROUP BY user + new_status (pro navolavacka, obchodak, backoffice). */
    private function queryGenericStats(string $role, array $cfg, int $year, int $month): array
    {
        $statusList = $cfg['statuses'];
        $placeholders = implode(',', array_fill(0, count($statusList), '?'));

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
                $users[$uid]['total_actions'] = 0;
            }
            if ($row['new_status'] !== null) {
                $colKey = $this->statusToColKey($role, (string) $row['new_status']);
                if ($colKey !== null && isset($users[$uid][$colKey])) {
                    $users[$uid][$colKey] += (int) $row['cnt'];
                    $users[$uid]['total_actions'] += (int) $row['cnt'];
                }
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
                    $users[$uid] = ['user_id' => $uid, 'jmeno' => $u['jmeno'], 'total_actions' => 0];
                    foreach ($cfg['col_keys'] as $k) { $users[$uid][$k] = 0; }
                }
            }
        }

        usort($users, static fn($a, $b) => $b['total_actions'] <=> $a['total_actions']);
        return array_values($users);
    }

    /** Speciální pivot pro čističky — potřebuje JOIN contacts pro TM/O2 rozlišení. */
    private function queryCistickaStats(int $year, int $month): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                u.id                              AS user_id,
                u.jmeno                           AS jmeno,
                wl.new_status                     AS new_status,
                COALESCE(c.operator, \'?\')       AS operator,
                COUNT(*)                          AS cnt
             FROM users u
             LEFT JOIN workflow_log wl
                 ON  wl.user_id      = u.id
                 AND YEAR(wl.created_at)  = :yr
                 AND MONTH(wl.created_at) = :mo
                 AND wl.new_status IN (\'READY\', \'VF_SKIP\')
             LEFT JOIN contacts c ON c.id = wl.contact_id
             WHERE u.role = \'cisticka\' AND u.aktivni = 1
             GROUP BY u.id, u.jmeno, wl.new_status, c.operator
             ORDER BY u.jmeno ASC'
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
                'vf_skip'  => 0, 'total_actions' => 0,
            ];
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
}
