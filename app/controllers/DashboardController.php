<?php
// e:\Snecinatripu\app\controllers\DashboardController.php
declare(strict_types=1);

final class DashboardController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getIndex(): void
    {
        $user = crm_require_user($this->pdo);
        $allowedRegions = [];
        $activeRegion = null;
        if (($user['role'] ?? '') === 'obchodak') {
            $allowedRegions = crm_regions_allowed($this->pdo, (int) $user['id']);
            $activeRegion = crm_active_region_get($this->pdo, $user);
        }

        // ── Widget „Blíží se výročí" — pro majitele / superadmin ──
        // Zobrazí top 10 smluv s výročím v příštích 180 dnech, sortováno od nejbližšího
        $upcomingAnniversaries = [];
        $anniversaryStats = ['days_30' => 0, 'days_60' => 0, 'days_90' => 0, 'days_180' => 0];
        if (in_array(($user['role'] ?? ''), ['majitel', 'superadmin'], true)) {
            try {
                $stmt = $this->pdo->query(
                    "SELECT c.id, c.firma, c.telefon, c.region,
                            c.vyrocni_smlouvy,
                            DATEDIFF(c.vyrocni_smlouvy, CURDATE()) AS days_until,
                            COALESCE(u_oz.jmeno, '—') AS oz_name,
                            w.cislo_smlouvy
                     FROM contacts c
                     LEFT JOIN oz_contact_workflow w ON w.contact_id = c.id
                     LEFT JOIN users u_oz ON u_oz.id = c.assigned_sales_id
                     WHERE c.vyrocni_smlouvy IS NOT NULL
                       AND c.vyrocni_smlouvy >= CURDATE()
                       AND c.vyrocni_smlouvy <= CURDATE() + INTERVAL 180 DAY
                     ORDER BY c.vyrocni_smlouvy ASC
                     LIMIT 10"
                );
                if ($stmt) {
                    $upcomingAnniversaries = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                }
                $statStmt = $this->pdo->query(
                    "SELECT
                        SUM(CASE WHEN vyrocni_smlouvy <= CURDATE() + INTERVAL 30 DAY  THEN 1 ELSE 0 END) AS days_30,
                        SUM(CASE WHEN vyrocni_smlouvy <= CURDATE() + INTERVAL 60 DAY  THEN 1 ELSE 0 END) AS days_60,
                        SUM(CASE WHEN vyrocni_smlouvy <= CURDATE() + INTERVAL 90 DAY  THEN 1 ELSE 0 END) AS days_90,
                        SUM(CASE WHEN vyrocni_smlouvy <= CURDATE() + INTERVAL 180 DAY THEN 1 ELSE 0 END) AS days_180
                     FROM contacts
                     WHERE vyrocni_smlouvy IS NOT NULL AND vyrocni_smlouvy >= CURDATE()"
                );
                if ($statStmt) {
                    $row = $statStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                    $anniversaryStats = [
                        'days_30'  => (int) ($row['days_30']  ?? 0),
                        'days_60'  => (int) ($row['days_60']  ?? 0),
                        'days_90'  => (int) ($row['days_90']  ?? 0),
                        'days_180' => (int) ($row['days_180'] ?? 0),
                    ];
                }
            } catch (\PDOException) {
                // Ignoruj — sloupec/tabulka může chybět na čerstvé instanci
            }
        }

        $flash = crm_flash_take();
        $title = 'Dashboard';
        $csrf = crm_csrf_token();
        ob_start();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'dashboard' . DIRECTORY_SEPARATOR . 'index.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'base.php';
    }

    public function postLogout(): void
    {
        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/dashboard');
        }
        crm_auth_logout();
        crm_flash_set('Byli jste odhlášeni.');
        crm_redirect('/login');
    }
}
