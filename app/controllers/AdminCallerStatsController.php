<?php
// app/controllers/AdminCallerStatsController.php
declare(strict_types=1);

/**
 * Výkonnostní přehled všech navolávačů pro majitele / superadmina.
 *
 * GET /admin/caller-stats
 *   ?year=2026&month=4   – filtr konkrétní měsíc
 */
final class AdminCallerStatsController
{
    public function __construct(private PDO $pdo)
    {
    }

    /** GET /admin/caller-stats */
    public function getIndex(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        $flash = crm_flash_take();

        // Rok a měsíc — čteme month_key (YYYY-MM) z <select>
        [$year, $month] = self::parseMonthKey(
            (string) ($_GET['month_key'] ?? ''),
            (int) ($_GET['year'] ?? 0),
            (int) ($_GET['month'] ?? 0)
        );

        $trackedStatuses = [
            'CALLED_OK', 'CALLED_BAD', 'CALLBACK',
            'NEZAJEM', 'NEDOVOLANO', 'IZOLACE', 'CHYBNY_KONTAKT',
        ];

        // ── Srovnávací pivot: všechny navolávačky × stavy ──
        $pivotStmt = $this->pdo->prepare(
            'SELECT
                u.id                                                                             AS user_id,
                u.jmeno                                                                          AS jmeno,
                SUM(CASE WHEN wl.new_status = \'CALLED_OK\'      THEN 1 ELSE 0 END)             AS called_ok,
                SUM(CASE WHEN wl.new_status = \'CALLED_BAD\'     THEN 1 ELSE 0 END)             AS called_bad,
                SUM(CASE WHEN wl.new_status = \'CALLBACK\'       THEN 1 ELSE 0 END)             AS callback_c,
                SUM(CASE WHEN wl.new_status = \'NEZAJEM\'        THEN 1 ELSE 0 END)             AS nezajem,
                SUM(CASE WHEN wl.new_status = \'NEDOVOLANO\'     THEN 1 ELSE 0 END)             AS nedovolano,
                SUM(CASE WHEN wl.new_status = \'IZOLACE\'        THEN 1 ELSE 0 END)             AS izolace,
                SUM(CASE WHEN wl.new_status = \'CHYBNY_KONTAKT\' THEN 1 ELSE 0 END)             AS chybny,
                COUNT(wl.id)                                                                     AS total_actions
             FROM users u
             LEFT JOIN workflow_log wl
                 ON  wl.user_id      = u.id
                 AND YEAR(wl.created_at)  = :yr
                 AND MONTH(wl.created_at) = :mo
                 AND wl.new_status IN (\'CALLED_OK\',\'CALLED_BAD\',\'CALLBACK\',
                                       \'NEZAJEM\',\'NEDOVOLANO\',\'IZOLACE\',\'CHYBNY_KONTAKT\')
             WHERE u.role = \'navolavacka\' AND u.aktivni = 1
             GROUP BY u.id, u.jmeno
             ORDER BY total_actions DESC, u.jmeno ASC'
        );
        $pivotStmt->execute(['yr' => $year, 'mo' => $month]);
        $callerRows = $pivotStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Celkové součty pro footer
        $colKeys = ['called_ok', 'called_bad', 'callback_c', 'nezajem', 'nedovolano', 'izolace', 'chybny', 'total_actions'];
        $totals  = array_fill_keys($colKeys, 0);
        foreach ($callerRows as $row) {
            foreach ($colKeys as $k) {
                $totals[$k] += (int) ($row[$k] ?? 0);
            }
        }

        // ── Výběr měsíců: 1 dopředu + aktuální + 17 zpět ──
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

        $title = 'Výkon navolávačů';
        ob_start();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'caller_stats.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'base.php';
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
