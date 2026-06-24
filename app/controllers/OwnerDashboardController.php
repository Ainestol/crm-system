<?php
// e:\Snecinatripu\app\controllers\OwnerDashboardController.php
declare(strict_types=1);

/**
 * Owner Dashboard
 *
 * Cesta: GET /owner-dashboard
 * Pro: majitel + superadmin (= ti, kdo musí "během 20 sekund vidět co se děje")
 *
 * Filozofie:
 *   - Stávající /dashboard zůstává intact (pro ostatní role).
 *   - Tady JEN výsledky a body, žádná surveillance.
 *   - Data ze `activity_log` (vč. backfillu z migrace 035).
 *   - 3 sekce: DNES, POSLEDNÍCH 7 DNÍ, UŽIVATELÉ.
 */
final class OwnerDashboardController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getIndex(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        $tenantId = crm_tenant_id();
        if ($tenantId <= 0) {
            crm_flash_set('Tenant kontext chybí.');
            crm_redirect('/dashboard');
        }

        // Tenant info pro header
        $tenant = crm_tenant_get_full($this->pdo, $tenantId);

        // === DNES ===
        $stats          = crm_activity_today_stats($this->pdo, $tenantId);
        $topUsersToday  = crm_activity_top_users($this->pdo, $tenantId, 'today', 5);
        $roleBreakdown  = crm_activity_role_breakdown($this->pdo, $tenantId, 'today');

        // === POSLEDNÍCH 7 DNÍ ===
        $trend7d        = crm_activity_trend_days($this->pdo, $tenantId, 7);
        $maxTrend       = max(array_column($trend7d, 'count') ?: [1]);

        // === UŽIVATELÉ ===
        $usersTable     = crm_activity_users_table($this->pdo, $tenantId);

        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();
        $title = '📊 Výkon firmy — ' . (string) ($tenant['name'] ?? 'Dashboard');

        ob_start();
        require dirname(__DIR__) . '/views/owner/dashboard.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }
}
