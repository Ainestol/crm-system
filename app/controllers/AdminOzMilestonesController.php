<?php
// e:\Snecinatripu\app\controllers\AdminOzMilestonesController.php
declare(strict_types=1);

/**
 * Admin: osobní milníky OZ per měsíc.
 * Majitel nastaví každému OZ libovolný počet milníků (label + target_bmsl + reward_note).
 * Při překročení milníku OZ dostane jednorázovou odměnu.
 */
final class AdminOzMilestonesController
{
    public function __construct(private PDO $pdo)
    {
    }

    // ────────────────────────────────────────────────────────────────
    //  GET /admin/oz-milestones
    // ────────────────────────────────────────────────────────────────

    public function getIndex(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();

        $year  = max(2024, min(2030, (int) ($_GET['year']  ?? date('Y'))));
        $month = max(1,    min(12,   (int) ($_GET['month'] ?? date('n'))));

        $this->ensureTable();

        // Všichni aktivní OZ
        $uStmt = $this->pdo->query(
            "SELECT id, jmeno FROM users WHERE role = 'obchodak' AND aktivni = 1 ORDER BY jmeno ASC"
        );
        $ozUsers = $uStmt ? ($uStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

        // Vybraný OZ
        $selectedOzId = (int) ($_GET['oz_id'] ?? ($ozUsers[0]['id'] ?? 0));

        // Milníky pro vybraného OZ
        $milestones = [];
        if ($selectedOzId > 0) {
            $mStmt = $this->pdo->prepare(
                'SELECT id, label, target_bmsl, reward_note
                 FROM oz_personal_milestones
                 WHERE oz_id = :oid AND year = :y AND month = :m
                 ORDER BY target_bmsl ASC'
            );
            $mStmt->execute(['oid' => $selectedOzId, 'y' => $year, 'm' => $month]);
            $milestones = $mStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $title = 'Osobní milníky OZ';
        ob_start();
        require dirname(__DIR__) . '/views/admin/oz_milestones.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /admin/oz-milestones/save
    // ────────────────────────────────────────────────────────────────

    public function postSave(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/oz-milestones');
        }

        $this->ensureTable();

        $year       = max(2024, min(2030, (int) ($_POST['year']  ?? date('Y'))));
        $month      = max(1,    min(12,   (int) ($_POST['month'] ?? date('n'))));
        $ozId       = (int) ($_POST['oz_id'] ?? 0);
        $label      = trim((string) ($_POST['label'] ?? ''));
        $target     = trim((string) ($_POST['target_bmsl'] ?? ''));
        $rewardNote = trim((string) ($_POST['reward_note'] ?? ''));

        if ($ozId <= 0) {
            crm_flash_set('Vyberte OZ.');
            crm_redirect('/admin/oz-milestones?year=' . $year . '&month=' . $month . '&oz_id=' . $ozId);
        }
        if ($label === '') {
            crm_flash_set('Vyplňte popisek milníku.');
            crm_redirect('/admin/oz-milestones?year=' . $year . '&month=' . $month . '&oz_id=' . $ozId);
        }
        if ($target === '' || !is_numeric($target) || (float) $target < 100) {
            crm_flash_set('Zadejte BMSL cíl (min. 100 Kč).');
            crm_redirect('/admin/oz-milestones?year=' . $year . '&month=' . $month . '&oz_id=' . $ozId);
        }

        $targetVal = (int) (floor((float) $target / 100) * 100);

        // Duplicitní cíl?
        $dupStmt = $this->pdo->prepare(
            'SELECT id FROM oz_personal_milestones
             WHERE oz_id = :oid AND year = :y AND month = :m AND target_bmsl = :t'
        );
        $dupStmt->execute(['oid' => $ozId, 'y' => $year, 'm' => $month, 't' => $targetVal]);
        if ($dupStmt->fetch()) {
            crm_flash_set('Milník s touto hodnotou již existuje.');
            crm_redirect('/admin/oz-milestones?year=' . $year . '&month=' . $month . '&oz_id=' . $ozId);
        }

        $this->pdo->prepare(
            'INSERT INTO oz_personal_milestones (oz_id, year, month, label, target_bmsl, reward_note)
             VALUES (:oid, :y, :m, :lbl, :tgt, :rw)'
        )->execute([
            'oid' => $ozId, 'y' => $year, 'm' => $month,
            'lbl' => $label, 'tgt' => $targetVal, 'rw' => $rewardNote,
        ]);

        crm_flash_set('✓ Milník přidán: ' . $label . ' (' . number_format($targetVal, 0, ',', ' ') . ' Kč).');
        crm_redirect('/admin/oz-milestones?year=' . $year . '&month=' . $month . '&oz_id=' . $ozId);
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /admin/oz-milestones/delete
    // ────────────────────────────────────────────────────────────────

    public function postDelete(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/oz-milestones');
        }

        $this->ensureTable();

        $year  = max(2024, min(2030, (int) ($_POST['year']  ?? date('Y'))));
        $month = max(1,    min(12,   (int) ($_POST['month'] ?? date('n'))));
        $ozId  = (int) ($_POST['oz_id'] ?? 0);
        $msId  = (int) ($_POST['milestone_id'] ?? 0);

        $this->pdo->prepare(
            'DELETE FROM oz_personal_milestones WHERE id = :id'
        )->execute(['id' => $msId]);

        crm_flash_set('Milník smazán.');
        crm_redirect('/admin/oz-milestones?year=' . $year . '&month=' . $month . '&oz_id=' . $ozId);
    }

    // ────────────────────────────────────────────────────────────────
    //  Helper
    // ────────────────────────────────────────────────────────────────

    private function ensureTable(): void
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
}
