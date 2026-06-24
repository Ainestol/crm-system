<?php
// e:\Snecinatripu\app\controllers\AdminTenantsController.php
declare(strict_types=1);

/**
 * ════════════════════════════════════════════════════════════════════
 *  ADMIN TENANTS — super-admin přepínání mezi firmami
 *
 *  Endpoints:
 *    POST /admin/tenants/switch  — super-admin přepne aktivní tenant v session
 *
 *  Bezpečnost:
 *    - Vyžaduje super-admin status (záznam v super_admins tabulce)
 *    - Cíl tenant musí existovat a být aktivní
 *    - CSRF token validace
 *    - V produkci se super-admin musí přepínat skrz subdoménu, nikoli session
 *      (po přepnutí redirect na /dashboard, kde bootstrap detekuje
 *      cross-tenant pokud je subdoména jiná)
 *
 *  POZN: V dev módu (?tenant=X v URL) přepínání funguje i bez tohoto
 *  controlleru. Tento controller je hlavně pro UI dropdown v topbaru.
 * ════════════════════════════════════════════════════════════════════
 */
final class AdminTenantsController
{
    public function __construct(private PDO $pdo) {}

    /**
     * GET /debug/tenant
     *
     * Diagnostic endpoint pro vývoj a debugging. Ukáže:
     *   - aktuální tenant_id v session
     *   - is_super_admin flag v session
     *   - host + subdoména parsed
     *   - resolved tenant_id z requestu
     *   - kontakty per tenant (počet)
     *   - existující tenanty
     *
     * Vidí jen super-admin (= bezpečné v produkci).
     */
    public function getDebug(): void
    {
        $actor = crm_require_user($this->pdo);
        if (!crm_tenant_user_is_super_admin($this->pdo, (int) $actor['id'])) {
            http_response_code(403);
            crm_flash_set('Jen pro super-admina.');
            crm_redirect('/dashboard');
        }

        // Pokud klient chce raw JSON (pro debugging skripty), pošle ?format=json
        $wantsJson = (string) ($_GET['format'] ?? '') === 'json';

        $sessTid = crm_tenant_id();
        $sessSA  = crm_tenant_is_super_admin();
        $host    = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $sub     = crm_tenant_extract_subdomain($host);
        $resolved = crm_tenant_resolve_from_request($this->pdo);

        // Kontakty per tenant
        $tenantCounts = [];
        try {
            $rows = $this->pdo->query(
                'SELECT tenant_id, COUNT(*) AS cnt FROM contacts GROUP BY tenant_id ORDER BY tenant_id'
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $r) {
                $tenantCounts[(int) $r['tenant_id']] = (int) $r['cnt'];
            }
        } catch (\Throwable $_) {}

        // Tenants (vč. paid_until, trial_ends_at pro úplný přehled)
        $tenants = $this->pdo->query(
            'SELECT id, name, subdomain, plan_code, active, paid_until, trial_ends_at, created_at
             FROM tenants ORDER BY id'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // user_tenants pro actor — kde je všude přihlášený
        $ut = $this->pdo->prepare(
            'SELECT ut.tenant_id, t.name AS tenant_name, ut.role, ut.active
             FROM user_tenants ut
             LEFT JOIN tenants t ON t.id = ut.tenant_id
             WHERE ut.user_id = :u
             ORDER BY ut.tenant_id'
        );
        $ut->execute(['u' => (int) $actor['id']]);
        $userTenants = $ut->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $testStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM contacts WHERE stav = 'NEW' AND tenant_id = :tid"
        );
        $testStmt->execute(['tid' => $sessTid]);
        $newCountForSession = (int) $testStmt->fetchColumn();

        $payload = [
            'session' => [
                'user_id'           => (int) $actor['id'],
                'user_email'        => (string) ($actor['email'] ?? ''),
                'tenant_id'         => $sessTid,
                'is_super_admin'    => $sessSA,
            ],
            'request' => [
                'host'         => $host,
                'subdomain'    => $sub,
                'app_env'      => defined('CRM_APP_ENV') ? CRM_APP_ENV : 'unknown',
                'is_dev_mode'  => crm_tenant_is_dev(),
                'resolved_tid' => $resolved,
            ],
            'data' => [
                'contacts_per_tenant'   => $tenantCounts,
                'new_count_for_session' => $newCountForSession,
                'tenants'               => $tenants,
                'user_tenants'          => $userTenants,
            ],
            'note' => $sessTid === $resolved
                ? 'OK: session a resolved jsou shodné.'
                : 'POZOR: session_tid != resolved_tid → bootstrap může přepsat session!',
        ];

        if ($wantsJson) {
            while (ob_get_level() > 0) { ob_end_clean(); }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }

        // HTML render přes base layout (krásnější varianta)
        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();
        $title = '🔍 Debug tenant';
        $user  = $actor;
        ob_start();
        require dirname(__DIR__) . '/views/admin/tenants/debug.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    /**
     * POST /admin/tenants/switch
     *
     * Body params:
     *   tenant_id — cílový tenant (int)
     *   csrf      — CSRF token
     *
     * Po úspěšném přepnutí redirect na /dashboard.
     * Při chybě (chybí super-admin, neplatný tenant) → flash + redirect zpět.
     */
    public function postSwitch(): void
    {
        $actor = crm_require_user($this->pdo);

        // Bezpečnost: jen super-admin (z super_admins tabulky)
        if (!crm_tenant_user_is_super_admin($this->pdo, (int) $actor['id'])) {
            crm_flash_set('Tato akce je dostupná jen super-adminům.');
            crm_redirect('/dashboard');
        }

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/dashboard');
        }

        $targetTenantId = (int) ($_POST['tenant_id'] ?? 0);
        if ($targetTenantId <= 0) {
            crm_flash_set('Chybí cílová firma.');
            crm_redirect('/dashboard');
        }

        // Ověř, že tenant existuje a je aktivní
        $target = crm_tenant_lookup_by_id($this->pdo, $targetTenantId);
        if ($target === null) {
            crm_flash_set('Firma neexistuje nebo není aktivní.');
            crm_redirect('/dashboard');
        }

        // Přepni session
        crm_tenant_set($targetTenantId, true); // true = super-admin
        crm_flash_set('Přepnuto na firmu: ' . $target['name']);

        // Audit log (best-effort)
        if (function_exists('crm_audit_log')) {
            try {
                crm_audit_log(
                    $this->pdo,
                    (int) $actor['id'],
                    'tenant_switch',
                    'tenant',
                    $targetTenantId,
                    ['target_subdomain' => $target['subdomain']]
                );
            } catch (\Throwable $_) {}
        }

        crm_redirect('/dashboard');
    }

    // ════════════════════════════════════════════════════════════════
    //  ADMIN UI — seznam firem, edit, sledování plateb
    //  Vše dostupné JEN super-adminům.
    // ════════════════════════════════════════════════════════════════

    /** Bezpečnostní guard pro všechny admin metody. */
    private function requireSuperAdmin(): array
    {
        $actor = crm_require_user($this->pdo);
        if (!crm_tenant_user_is_super_admin($this->pdo, (int) $actor['id'])) {
            http_response_code(403);
            crm_flash_set('Tato sekce je dostupná jen super-adminům.');
            crm_redirect('/dashboard');
        }
        return $actor;
    }

    /** GET /admin/tenants — seznam všech firem s usage statistikami */
    public function getIndex(): void
    {
        $actor = $this->requireSuperAdmin();
        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();

        // Seznam firem + plán + denormalizované info pro UI.
        // Sub-queries na tenant_payments / tenant_plans jsou wrapnuté v try/catch
        // pokud migrace 034 ještě neproběhla, ať se stránka nezhroutí.
        try {
            $rows = $this->pdo->query(
                "SELECT t.*,
                        tp.name AS plan_name,
                        (SELECT COUNT(*) FROM user_tenants ut
                           JOIN users u ON u.id = ut.user_id
                          WHERE ut.tenant_id = t.id AND ut.active = 1 AND u.aktivni = 1) AS users_count,
                        (SELECT COUNT(*) FROM contacts c
                          WHERE c.tenant_id = t.id) AS contacts_count,
                        (SELECT COALESCE(SUM(p.amount_czk), 0) FROM tenant_payments p
                          WHERE p.tenant_id = t.id) AS lifetime_paid
                 FROM tenants t
                 LEFT JOIN tenant_plans tp ON tp.slug = t.plan_code
                 ORDER BY t.id ASC"
            );
            $tenants = $rows ? ($rows->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (\PDOException $e) {
            // Fallback: chybí tenant_payments nebo tenant_plans (migrace 034 nepuštěná)
            // — načti aspoň základní info z tenants
            crm_db_log_error($e, __METHOD__);
            crm_flash_set('⚠ Spusť: php bin/migrate.php up (chybí tabulky z migrace 034)');
            $rows = $this->pdo->query(
                'SELECT t.*,
                        (SELECT COUNT(*) FROM user_tenants ut
                           JOIN users u ON u.id = ut.user_id
                          WHERE ut.tenant_id = t.id AND ut.active = 1 AND u.aktivni = 1) AS users_count,
                        (SELECT COUNT(*) FROM contacts c
                          WHERE c.tenant_id = t.id) AS contacts_count,
                        0 AS lifetime_paid,
                        NULL AS plan_name
                 FROM tenants t ORDER BY t.id ASC'
            );
            $tenants = $rows ? ($rows->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        }

        // Pro každou firmu spočítej percent využití + lifecycle stav
        foreach ($tenants as &$t) {
            $usage = crm_tenant_get_usage($this->pdo, (int) $t['id']);
            $t['usage']     = $usage;
            $t['pct_users'] = $usage['users_max'] ? min(100, (int) round($usage['users_active'] * 100 / $usage['users_max'])) : null;
            $t['pct_contacts'] = $usage['contacts_max'] ? min(100, (int) round($usage['contacts_total'] * 100 / $usage['contacts_max'])) : null;
            $t['lifecycle'] = crm_tenant_lifecycle_state($this->pdo, (int) $t['id']);
            // Indikátor zaplaceno
            $t['paid_status'] = 'unknown';
            if (!empty($t['paid_until'])) {
                $daysLeft = (int) ((strtotime((string) $t['paid_until']) - time()) / 86400);
                if ($daysLeft < 0)       $t['paid_status'] = 'expired';
                elseif ($daysLeft <= 7)  $t['paid_status'] = 'expiring';
                else                     $t['paid_status'] = 'active';
                $t['paid_days_left'] = $daysLeft;
            }
        }
        unset($t);

        $plans = crm_tenant_plans_active($this->pdo);

        $title = '🏢 Správa firem';
        $user  = $actor;
        ob_start();
        require dirname(__DIR__) . '/views/admin/tenants/index.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    /** GET /admin/tenants/edit?id=N — detail/edit jedné firmy */
    public function getEdit(): void
    {
        $actor = $this->requireSuperAdmin();
        $tenantId = (int) ($_GET['id'] ?? 0);
        if ($tenantId <= 0) crm_redirect('/admin/tenants');

        $tenant = crm_tenant_get_full($this->pdo, $tenantId);
        if ($tenant === null) {
            crm_flash_set('Firma neexistuje.');
            crm_redirect('/admin/tenants');
        }

        $usage    = crm_tenant_get_usage($this->pdo, $tenantId);
        $plans    = crm_tenant_plans_active($this->pdo);
        $branding = crm_tenant_branding($this->pdo, $tenantId);

        // Historie plateb
        $pStmt = $this->pdo->prepare(
            'SELECT p.*, u.jmeno AS recorded_by_name
             FROM tenant_payments p
             LEFT JOIN users u ON u.id = p.recorded_by
             WHERE p.tenant_id = :id
             ORDER BY p.paid_at DESC LIMIT 100'
        );
        $pStmt->execute(['id' => $tenantId]);
        $payments = $pStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Aktivní users ve firmě (pro info)
        $uStmt = $this->pdo->prepare(
            "SELECT u.id, u.jmeno, u.email, ut.role, ut.active, ut.joined_at
             FROM user_tenants ut
             JOIN users u ON u.id = ut.user_id
             WHERE ut.tenant_id = :id
             ORDER BY ut.active DESC, ut.joined_at DESC"
        );
        $uStmt->execute(['id' => $tenantId]);
        $users = $uStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();
        $title = 'Firma: ' . (string) $tenant['name'];
        $user  = $actor;
        ob_start();
        require dirname(__DIR__) . '/views/admin/tenants/edit.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    /** POST /admin/tenants/save — uložit změny firmy */
    public function postSave(): void
    {
        $actor = $this->requireSuperAdmin();
        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/tenants');
        }

        $tenantId = (int) ($_POST['tenant_id'] ?? 0);
        if ($tenantId <= 0) crm_redirect('/admin/tenants');

        $name        = trim((string) ($_POST['name'] ?? ''));
        $emailOwner  = trim((string) ($_POST['email_owner'] ?? ''));
        $planCode    = trim((string) ($_POST['plan_code'] ?? ''));
        $active      = isset($_POST['active']) ? 1 : 0;

        // Limity (NULL pro prázdné = unlimited)
        $maxUsers    = ($_POST['max_users']    ?? '') === '' ? null : (int) $_POST['max_users'];
        $maxContacts = ($_POST['max_contacts'] ?? '') === '' ? null : (int) $_POST['max_contacts'];
        $maxPremium  = ($_POST['max_premium_orders_per_month'] ?? '') === '' ? null : (int) $_POST['max_premium_orders_per_month'];

        $monthlyPrice = ($_POST['monthly_price_czk'] ?? '') === '' ? null
                      : (float) str_replace(',', '.', (string) $_POST['monthly_price_czk']);

        $paidUntil = trim((string) ($_POST['paid_until'] ?? ''));
        if ($paidUntil === '' || !strtotime($paidUntil)) $paidUntil = null;
        else $paidUntil = date('Y-m-d', strtotime($paidUntil));

        $notes = trim((string) ($_POST['admin_notes'] ?? ''));
        if ($notes === '') $notes = null;

        if ($name === '') {
            crm_flash_set('Jméno firmy je povinné.');
            crm_redirect('/admin/tenants/edit?id=' . $tenantId);
        }

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE tenants
                 SET name = :name,
                     email_owner = :email,
                     plan_code = :plan,
                     active = :active,
                     max_users = :mu,
                     max_contacts = :mc,
                     max_premium_orders_per_month = :mp,
                     monthly_price_czk = :price,
                     paid_until = :paid,
                     admin_notes = :notes,
                     updated_at = NOW(3)
                 WHERE id = :id'
            );
            $stmt->execute([
                'name'  => $name,
                'email' => $emailOwner !== '' ? $emailOwner : null,
                'plan'  => $planCode !== '' ? $planCode : 'free',
                'active'=> $active,
                'mu'    => $maxUsers,
                'mc'    => $maxContacts,
                'mp'    => $maxPremium,
                'price' => $monthlyPrice,
                'paid'  => $paidUntil,
                'notes' => $notes,
                'id'    => $tenantId,
            ]);

            if (function_exists('crm_audit_log')) {
                crm_audit_log($this->pdo, (int) $actor['id'], 'tenant_update', 'tenant', $tenantId,
                    ['plan' => $planCode, 'active' => $active]);
            }
            crm_flash_set('✓ Změny uloženy.');
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
            crm_flash_set('⚠ Chyba při uložení: ' . $e->getMessage());
        }

        crm_redirect('/admin/tenants/edit?id=' . $tenantId);
    }

    /** POST /admin/tenants/apply-plan — aplikovat plán (overwrite limitů z katalogu) */
    public function postApplyPlan(): void
    {
        $actor = $this->requireSuperAdmin();
        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/tenants');
        }
        $tenantId = (int) ($_POST['tenant_id'] ?? 0);
        $slug     = trim((string) ($_POST['plan_slug'] ?? ''));
        if ($tenantId <= 0 || $slug === '') {
            crm_redirect('/admin/tenants');
        }
        $ok = crm_tenant_apply_plan($this->pdo, $tenantId, $slug);
        crm_flash_set($ok ? '✓ Plán aplikován (přepsány limity).' : '⚠ Plán se nepodařilo aplikovat.');
        crm_redirect('/admin/tenants/edit?id=' . $tenantId);
    }

    /** POST /admin/tenants/save-branding — upravit logo + brand barvy */
    public function postSaveBranding(): void
    {
        $actor = $this->requireSuperAdmin();
        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/tenants');
        }
        $tenantId = (int) ($_POST['tenant_id'] ?? 0);
        if ($tenantId <= 0) crm_redirect('/admin/tenants');

        $displayName  = trim((string) ($_POST['display_name'] ?? ''));
        $logoUrlInput = trim((string) ($_POST['logo_url'] ?? ''));
        $primaryColor = trim((string) ($_POST['primary_color'] ?? '#2563eb'));
        $accentColor  = trim((string) ($_POST['accent_color'] ?? '#7c3aed'));

        // Validace hex barev
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $primaryColor)) $primaryColor = '#2563eb';
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $accentColor))  $accentColor  = '#7c3aed';

        // Priorita: pokud user nahrál soubor, použij ho. Jinak URL pole.
        $finalLogoUrl = $logoUrlInput !== '' ? $logoUrlInput : null;
        if (!empty($_FILES['logo_file']['name'] ?? '')) {
            $uploaded = crm_tenant_logo_upload($tenantId, $_FILES['logo_file']);
            if ($uploaded !== null) {
                $finalLogoUrl = $uploaded;
            } else {
                crm_flash_set('⚠ Logo se nepodařilo nahrát (formát/velikost). Povolené: JPG/PNG/SVG/WebP, max 500 KB.');
                crm_redirect('/admin/tenants/edit?id=' . $tenantId);
            }
        }

        $ok = crm_tenant_branding_save(
            $this->pdo,
            $tenantId,
            $displayName !== '' ? $displayName : null,
            $finalLogoUrl,
            $primaryColor,
            $accentColor
        );

        if (function_exists('crm_audit_log')) {
            crm_audit_log($this->pdo, (int) $actor['id'], 'tenant_branding_update', 'tenant', $tenantId,
                ['logo' => $finalLogoUrl, 'primary' => $primaryColor]);
        }

        crm_flash_set($ok ? '✓ Vzhled firmy uložen.' : '⚠ Uložení selhalo.');
        crm_redirect('/admin/tenants/edit?id=' . $tenantId);
    }

    /** POST /admin/tenants/log-payment — zaznamenat platbu */
    public function postLogPayment(): void
    {
        $actor = $this->requireSuperAdmin();
        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/tenants');
        }

        $tenantId   = (int) ($_POST['tenant_id'] ?? 0);
        $amount     = (float) str_replace(',', '.', (string) ($_POST['amount_czk'] ?? '0'));
        $paidAt     = trim((string) ($_POST['paid_at'] ?? ''));
        $periodFrom = trim((string) ($_POST['period_from'] ?? ''));
        $periodTo   = trim((string) ($_POST['period_until'] ?? ''));
        $invoice    = trim((string) ($_POST['invoice_number'] ?? ''));
        $method     = trim((string) ($_POST['payment_method'] ?? ''));
        $notes      = trim((string) ($_POST['notes'] ?? ''));

        if ($tenantId <= 0 || $amount <= 0) {
            crm_flash_set('⚠ Chybí tenant nebo částka.');
            crm_redirect('/admin/tenants');
        }

        if ($paidAt === ''     || !strtotime($paidAt))     $paidAt     = date('Y-m-d H:i:s');
        if ($periodFrom === '' || !strtotime($periodFrom)) $periodFrom = date('Y-m-d');
        if ($periodTo === ''   || !strtotime($periodTo))   $periodTo   = date('Y-m-d', strtotime('+1 month'));

        try {
            $this->pdo->beginTransaction();
            $this->pdo->prepare(
                'INSERT INTO tenant_payments
                   (tenant_id, amount_czk, paid_at, period_from, period_until,
                    invoice_number, payment_method, recorded_by, notes)
                 VALUES (:tid, :amt, :pat, :pf, :pu, :inv, :pm, :rb, :n)'
            )->execute([
                'tid' => $tenantId,
                'amt' => $amount,
                'pat' => date('Y-m-d H:i:s', strtotime($paidAt)),
                'pf'  => date('Y-m-d', strtotime($periodFrom)),
                'pu'  => date('Y-m-d', strtotime($periodTo)),
                'inv' => $invoice !== '' ? $invoice : null,
                'pm'  => $method !== '' ? $method : null,
                'rb'  => (int) $actor['id'],
                'n'   => $notes !== '' ? $notes : null,
            ]);

            // Automaticky posuň paid_until na max(stávající, period_until)
            // + auto-reaktivace pokud period_until je v budoucnu a tenant byl suspendovaný.
            $newPaidUntilTs = strtotime($periodTo);
            $autoReactivate = $newPaidUntilTs > time() ? 1 : 0;
            $this->pdo->prepare(
                'UPDATE tenants
                 SET paid_until = GREATEST(IFNULL(paid_until, :pu1), :pu2),
                     active = CASE WHEN :ar = 1 THEN 1 ELSE active END,
                     updated_at = NOW(3)
                 WHERE id = :id'
            )->execute([
                'pu1' => $periodTo,
                'pu2' => $periodTo,
                'ar'  => $autoReactivate,
                'id'  => $tenantId,
            ]);

            $this->pdo->commit();
            crm_flash_set($autoReactivate
                ? '✓ Platba zaznamenána, paid_until aktualizován, firma reaktivována.'
                : '✓ Platba zaznamenána a paid_until aktualizován.');
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            crm_db_log_error($e, __METHOD__);
            crm_flash_set('⚠ Chyba při uložení platby.');
        }
        crm_redirect('/admin/tenants/edit?id=' . $tenantId);
    }
}
