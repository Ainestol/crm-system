<?php
// e:\Snecinatripu\app\controllers\AdminDailyGoalsController.php
declare(strict_types=1);

/**
 * Správa cílů a odměny navolávačky (denní + měsíční + bonusy).
 * Přístup: majitel, superadmin.
 */
final class AdminDailyGoalsController
{
    public function __construct(private PDO $pdo)
    {
    }

    /** GET /admin/daily-goals */
    public function getIndex(): void
    {
        $user  = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);
        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();

        // Auto-migrace: zajistí existenci tabulky + sloupce motiv_enabled
        $this->ensureMonthlyGoalsTable();

        // Aktuální základní odměna za výhru
        $rewardRow = $this->pdo->query(
            "SELECT id, amount_czk, valid_from FROM caller_rewards_config
             WHERE valid_to IS NULL ORDER BY id DESC LIMIT 1"
        );
        $reward = ($rewardRow ? $rewardRow->fetch(PDO::FETCH_ASSOC) : null)
                ?: ['id' => 0, 'amount_czk' => 0, 'valid_from' => date('Y-m-d')];

        // Aktuální měsíční cíl + bonusy (jeden aktivní záznam — valid_to IS NULL)
        $mgRow = $this->pdo->query(
            "SELECT id, target_wins, bonus1_at_pct, bonus1_pct, bonus2_at_pct, bonus2_pct, motiv_enabled
             FROM monthly_goals WHERE valid_to IS NULL ORDER BY id DESC LIMIT 1"
        );
        $monthlyGoal = ($mgRow ? $mgRow->fetch(PDO::FETCH_ASSOC) : null)
                     ?: [
                         'id' => 0, 'target_wins' => 150,
                         'bonus1_at_pct' => 100, 'bonus1_pct' => '5.00',
                         'bonus2_at_pct' => 120, 'bonus2_pct' => '5.00',
                         'motiv_enabled' => 1,
                     ];

        // Pracovní dny v aktuálním měsíci (Po–Pá)
        $workDays        = self::workingDaysInMonth((int) date('Y'), (int) date('n'));
        $derivedDailyWin = $workDays > 0 ? (int) ceil((int) $monthlyGoal['target_wins'] / $workDays) : 0;

        $title = 'Cíle a odměny navolávačky';
        ob_start();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'daily_goals.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'base.php';
    }

    /** POST /admin/daily-goals/save */
    public function postSave(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/daily-goals');
        }

        // ── Základní odměna za výhru ────────────────────────────────────────
        $amountCzk = max(0.0, (float) str_replace(',', '.', (string) ($_POST['amount_czk'] ?? '0')));
        if ($amountCzk > 0) {
            // Uzavřít aktivní záznamy (valid_to IS NULL) a vložit nový
            $this->pdo->prepare(
                "UPDATE caller_rewards_config SET valid_to = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                 WHERE valid_to IS NULL"
            )->execute();
            $this->pdo->prepare(
                "INSERT INTO caller_rewards_config (amount_czk, valid_from, valid_to)
                 VALUES (:amt, CURDATE(), NULL)"
            )->execute(['amt' => $amountCzk]);
        }

        // ── Měsíční cíl + bonusy + enable/disable ──────────────────────────
        $motivEnabled    = isset($_POST['motiv_enabled']) ? 1 : 0;
        $targetWinsMonth = max(1, (int) ($_POST['target_wins_month'] ?? 150));
        $bonus1AtPct     = max(100, min(999, (int) ($_POST['bonus1_at_pct'] ?? 100)));
        $bonus1Pct       = max(0.0, min(50.0, (float) str_replace(',', '.', (string) ($_POST['bonus1_pct'] ?? '5'))));
        $bonus2AtPct     = max($bonus1AtPct + 1, min(999, (int) ($_POST['bonus2_at_pct'] ?? 120)));
        $bonus2Pct       = max(0.0, min(50.0, (float) str_replace(',', '.', (string) ($_POST['bonus2_pct'] ?? '5'))));

        // Odvozený denní cíl
        $workDays   = self::workingDaysInMonth((int) date('Y'), (int) date('n'));
        $targetWins = $workDays > 0 ? (int) ceil($targetWinsMonth / $workDays) : 0;

        // Uzavřít aktivní monthly_goals a vložit nový
        $this->pdo->prepare(
            "UPDATE monthly_goals SET valid_to = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
             WHERE valid_to IS NULL"
        )->execute();
        $this->pdo->prepare(
            "INSERT INTO monthly_goals
               (target_wins, bonus1_at_pct, bonus1_pct, bonus2_at_pct, bonus2_pct, motiv_enabled, valid_from, valid_to)
             VALUES (:tw, :b1at, :b1pct, :b2at, :b2pct, :me, CURDATE(), NULL)"
        )->execute([
            'tw'    => $targetWinsMonth,
            'b1at'  => $bonus1AtPct,
            'b1pct' => $bonus1Pct,
            'b2at'  => $bonus2AtPct,
            'b2pct' => $bonus2Pct,
            'me'    => $motivEnabled,
        ]);

        // Uložit odvozený denní cíl
        $this->pdo->prepare(
            "INSERT INTO daily_goals (role, target_calls, target_wins)
             VALUES ('navolavacka', 0, :tw)
             ON DUPLICATE KEY UPDATE target_calls = 0, target_wins = :tw2"
        )->execute(['tw' => $targetWins, 'tw2' => $targetWins]);

        crm_flash_set('Nastavení uloženo.');
        crm_redirect('/admin/daily-goals');
    }

    /** Vytvoří tabulku a sloupce pokud neexistují (auto-migrace). */
    private function ensureMonthlyGoalsTable(): void
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `monthly_goals` (
          `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `target_wins`   INT UNSIGNED NOT NULL DEFAULT 150,
          `bonus1_at_pct` TINYINT UNSIGNED NOT NULL DEFAULT 100,
          `bonus1_pct`    DECIMAL(5,2) NOT NULL DEFAULT 5.00,
          `bonus2_at_pct` TINYINT UNSIGNED NOT NULL DEFAULT 120,
          `bonus2_pct`    DECIMAL(5,2) NOT NULL DEFAULT 5.00,
          `motiv_enabled` TINYINT(1) NOT NULL DEFAULT 1,
          `valid_from`    DATE NOT NULL,
          `valid_to`      DATE NULL DEFAULT NULL,
          `created_at`    DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
          PRIMARY KEY (`id`),
          KEY `idx_monthly_goals_valid` (`valid_from`, `valid_to`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Přidat sloupec motiv_enabled pokud ještě neexistuje
        $colCheck = $this->pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'monthly_goals' AND COLUMN_NAME = 'motiv_enabled'"
        );
        $colCheck->execute();
        if ((int) $colCheck->fetchColumn() === 0) {
            $this->pdo->exec(
                "ALTER TABLE monthly_goals ADD COLUMN `motiv_enabled` TINYINT(1) NOT NULL DEFAULT 1"
            );
        }

        // Výchozí záznam pokud tabulka prázdná
        $cnt = (int) $this->pdo->query('SELECT COUNT(*) FROM monthly_goals')->fetchColumn();
        if ($cnt === 0) {
            $this->pdo->exec(
                "INSERT INTO monthly_goals
                   (target_wins, bonus1_at_pct, bonus1_pct, bonus2_at_pct, bonus2_pct, motiv_enabled, valid_from, valid_to)
                 VALUES (150, 100, 5.00, 120, 5.00, 1, CURDATE(), NULL)"
            );
        }
    }

    /** Počet pracovních dní (Po–Pá) v daném měsíci. */
    public static function workingDaysInMonth(int $year, int $month): int
    {
        $count = 0;
        $days  = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
        for ($d = 1; $d <= $days; $d++) {
            if ((int) date('N', mktime(0, 0, 0, $month, $d, $year)) <= 5) {
                $count++;
            }
        }
        return $count;
    }
}
