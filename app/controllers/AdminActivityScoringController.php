<?php
// e:\Snecinatripu\app\controllers\AdminActivityScoringController.php
declare(strict_types=1);

/**
 * Admin Activity Scoring — konfigurace bodování per role.
 *
 * Cesty:
 *   GET  /admin/activity-scoring         — formulář (default: čistička)
 *   POST /admin/activity-scoring/save    — uložit změny
 *
 * Pro: majitel + superadmin
 *
 * Filozofie:
 *   - Změny jsou per-tenant (každá firma si nastaví vlastní bodování).
 *   - Žádné body v kódu — vše v `activity_score_rules`.
 *   - Lze zapínat/vypínat akce (active flag) i měnit body.
 */
final class AdminActivityScoringController
{
    private const ROLES = ['cisticka', 'navolavacka', 'obchodak', 'backoffice', 'majitel'];

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

        $role = (string) ($_GET['role'] ?? 'navolavacka');
        if (!in_array($role, self::ROLES, true)) $role = 'navolavacka';

        $rules = $this->loadRules($tenantId, $role);

        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();
        $title = '⚙️ Bodování aktivit';

        ob_start();
        require dirname(__DIR__) . '/views/admin/activity_scoring/index.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    public function postSave(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/activity-scoring');
        }

        $tenantId = crm_tenant_id();
        $role = (string) ($_POST['role'] ?? 'navolavacka');
        if (!in_array($role, self::ROLES, true)) {
            crm_redirect('/admin/activity-scoring');
        }

        // Per-rule: 'points_<id>' + checkbox 'active_<id>'
        $rules = $this->loadRules($tenantId, $role);

        $upd = $this->pdo->prepare(
            'UPDATE activity_score_rules
             SET points = :pts, active = :active, updated_at = NOW(3)
             WHERE id = :id AND tenant_id = :tid AND role = :role'
        );

        $changed = 0;
        foreach ($rules as $r) {
            $id = (int) $r['id'];
            $pts = (int) ($_POST['points_' . $id] ?? $r['points']);
            // Clamp 0..1000 (záporné body nepovolíme)
            $pts = max(0, min(1000, $pts));
            $active = isset($_POST['active_' . $id]) ? 1 : 0;

            if ($pts !== (int) $r['points'] || $active !== (int) $r['active']) {
                $upd->execute([
                    'pts' => $pts, 'active' => $active,
                    'id'  => $id, 'tid' => $tenantId, 'role' => $role,
                ]);
                $changed++;
            }
        }

        crm_flash_set($changed > 0
            ? '✓ Uloženo ' . $changed . ' změn.'
            : 'Žádné změny.');
        crm_redirect('/admin/activity-scoring?role=' . urlencode($role));
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function loadRules(int $tenantId, string $role): array
    {
        $st = $this->pdo->prepare(
            'SELECT id, action_type, action_label, points, active, sort_order
             FROM activity_score_rules
             WHERE tenant_id = :tid AND role = :role
             ORDER BY sort_order ASC, action_label ASC'
        );
        $st->execute(['tid' => $tenantId, 'role' => $role]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
