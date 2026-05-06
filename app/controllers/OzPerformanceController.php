<?php
// e:\Snecinatripu\app\controllers\OzPerformanceController.php
declare(strict_types=1);

/**
 * OZ Výkon týmu — tabulka smluv + BMSL per OZ + progress bar se stages.
 * Dostupné pro obchodak (vidí svůj tým) i admin.
 */
final class OzPerformanceController
{
    public function __construct(private PDO $pdo)
    {
    }

    // ────────────────────────────────────────────────────────────────
    //  GET /oz/performance
    // ────────────────────────────────────────────────────────────────

    public function getIndex(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();

        $year  = max(2024, min(2030, (int) ($_GET['year']  ?? date('Y'))));
        $month = max(1,    min(12,   (int) ($_GET['month'] ?? date('n'))));

        $this->ensureStagesTable();

        // ── Výkon každého OZ ─────────────────────────────────────────
        // Sjednoceno s OzController::getIndex (Moje kvóty). Výhra se počítá:
        //   1) podpis_potvrzen = 1 → měsíc se bere z podpis_potvrzen_at.
        //      Tohle zůstává platné i po přechodu stavu na UZAVRENO
        //      (BO klikl "Uzavřít smlouvu") — proto týmové počítadlo
        //      neztrácí smlouvu, jakmile ji BO finalizuje.
        //   2) Legacy fallback: stav = 'SMLOUVA' AND podpis_potvrzen = 0
        //      (historie před zavedením podpis_potvrzen flagu).
        //
        // Před touto opravou byl JOIN podmíněný JEN na stav = 'SMLOUVA' —
        // takže každá uzavřená smlouva mizela z této tabulky a OZ-ové
        // viděli nuly i když měli reálné výhry.
        $stmt = $this->pdo->prepare(
            "SELECT u.id, u.jmeno,
                    COUNT(w.id)                AS contracts,
                    COALESCE(SUM(w.bmsl), 0)   AS total_bmsl
             FROM users u
             LEFT JOIN oz_contact_workflow w
               ON  w.oz_id = u.id
               AND (
                 (w.podpis_potvrzen = 1
                  AND YEAR(w.podpis_potvrzen_at)  = :y
                  AND MONTH(w.podpis_potvrzen_at) = :m)
                 OR
                 (w.podpis_potvrzen = 0 AND w.stav = 'SMLOUVA'
                  AND YEAR(w.updated_at)  = :y2
                  AND MONTH(w.updated_at) = :m2)
               )
             WHERE u.role = 'obchodak' AND u.aktivni = 1
             GROUP BY u.id, u.jmeno
             ORDER BY total_bmsl DESC, u.jmeno ASC"
        );
        $stmt->execute(['y' => $year, 'm' => $month, 'y2' => $year, 'm2' => $month]);
        $ozRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $teamBmsl      = (int) array_sum(array_column($ozRows, 'total_bmsl'));
        $teamContracts = (int) array_sum(array_column($ozRows, 'contracts'));

        // ── Stages pro vybraný měsíc ──────────────────────────────────
        $sStmt = $this->pdo->prepare(
            'SELECT id, stage_number, label, target_bmsl
             FROM oz_team_stages
             WHERE year = :y AND month = :m
             ORDER BY target_bmsl ASC'
        );
        $sStmt->execute(['y' => $year, 'm' => $month]);
        $stages = $sStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $title = 'Výkon OZ týmu';
        ob_start();
        require dirname(__DIR__) . '/views/oz/performance.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    // ────────────────────────────────────────────────────────────────
    //  Helper
    // ────────────────────────────────────────────────────────────────

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
