<?php
// e:\Snecinatripu\app\controllers\AdminOzStagesController.php
declare(strict_types=1);

/**
 * Admin: správa stage cílů OZ týmu (BMSL thresholdy per měsíc).
 * Dostupné pro majitel / superadmin.
 */
final class AdminOzStagesController
{
    public function __construct(private PDO $pdo)
    {
    }

    // ────────────────────────────────────────────────────────────────
    //  GET /admin/oz-stages
    // ────────────────────────────────────────────────────────────────

    public function getIndex(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();

        $year  = max(2024, min(2030, (int) ($_GET['year']  ?? date('Y'))));
        $month = max(1,    min(12,   (int) ($_GET['month'] ?? date('n'))));

        $this->ensureStagesTable();

        $stmt = $this->pdo->prepare(
            'SELECT id, stage_number, label, target_bmsl
             FROM oz_team_stages
             WHERE year = :y AND month = :m
             ORDER BY target_bmsl ASC'
        );
        $stmt->execute(['y' => $year, 'm' => $month]);
        $stages = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $title = 'Správa OZ stage cílů';
        ob_start();
        require dirname(__DIR__) . '/views/admin/oz_stages.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /admin/oz-stages/save  –  Přidat nový stage
    // ────────────────────────────────────────────────────────────────

    public function postSave(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/oz-stages');
        }

        $this->ensureStagesTable();

        $year   = max(2024, min(2030, (int) ($_POST['year']  ?? date('Y'))));
        $month  = max(1,    min(12,   (int) ($_POST['month'] ?? date('n'))));
        $label  = trim((string) ($_POST['label'] ?? ''));
        $target = trim((string) ($_POST['target_bmsl'] ?? ''));

        if ($label === '') {
            crm_flash_set('Vyplňte popisek stage.');
            crm_redirect('/admin/oz-stages?year=' . $year . '&month=' . $month);
        }
        if ($target === '' || !is_numeric($target) || (float) $target <= 0) {
            crm_flash_set('Zadejte kladnou BMSL hodnotu.');
            crm_redirect('/admin/oz-stages?year=' . $year . '&month=' . $month);
        }

        $targetVal = (int) round((float) $target);

        // Zkontrolovat duplicitní hodnotu
        $dupStmt = $this->pdo->prepare(
            'SELECT id FROM oz_team_stages
             WHERE year = :y AND month = :m AND target_bmsl = :t'
        );
        $dupStmt->execute(['y' => $year, 'm' => $month, 't' => $targetVal]);
        if ($dupStmt->fetch()) {
            crm_flash_set('Stage s touto BMSL hodnotou již existuje pro daný měsíc.');
            crm_redirect('/admin/oz-stages?year=' . $year . '&month=' . $month);
        }

        // Číslo stage = pořadí dle výše (přečíslujeme po insertu)
        $this->pdo->prepare(
            'INSERT INTO oz_team_stages (year, month, stage_number, label, target_bmsl)
             VALUES (:y, :m, 0, :lbl, :tgt)'
        )->execute([
            'y' => $year, 'm' => $month,
            'lbl' => $label, 'tgt' => $targetVal,
        ]);

        $this->renumberStages($year, $month);

        crm_flash_set('✓ Stage přidán: ' . $label . ' (' . number_format($targetVal, 0, ',', ' ') . ' Kč).');
        crm_redirect('/admin/oz-stages?year=' . $year . '&month=' . $month);
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /admin/oz-stages/delete  –  Smazat stage
    // ────────────────────────────────────────────────────────────────

    public function postDelete(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/oz-stages');
        }

        $this->ensureStagesTable();

        $year    = max(2024, min(2030, (int) ($_POST['year']  ?? date('Y'))));
        $month   = max(1,    min(12,   (int) ($_POST['month'] ?? date('n'))));
        $stageId = (int) ($_POST['stage_id'] ?? 0);

        $this->pdo->prepare(
            'DELETE FROM oz_team_stages WHERE id = :id'
        )->execute(['id' => $stageId]);

        $this->renumberStages($year, $month);

        crm_flash_set('Stage smazán.');
        crm_redirect('/admin/oz-stages?year=' . $year . '&month=' . $month);
    }

    // ────────────────────────────────────────────────────────────────
    //  Helpers
    // ────────────────────────────────────────────────────────────────

    /** Přečísluje stages 1..N seřazené dle target_bmsl ASC. */
    private function renumberStages(int $year, int $month): void
    {
        $sStmt = $this->pdo->prepare(
            'SELECT id FROM oz_team_stages
             WHERE year = :y AND month = :m
             ORDER BY target_bmsl ASC'
        );
        $sStmt->execute(['y' => $year, 'm' => $month]);
        $ids = $sStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $upd = $this->pdo->prepare(
            'UPDATE oz_team_stages SET stage_number = :n WHERE id = :id'
        );
        foreach ($ids as $i => $id) {
            $upd->execute(['n' => $i + 1, 'id' => $id]);
        }
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
}
