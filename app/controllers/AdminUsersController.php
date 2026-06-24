<?php
// e:\Snecinatripu\app\controllers\AdminUsersController.php
declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'users_admin.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'audit.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'api_auth.php';

final class AdminUsersController
{
    public function __construct(private PDO $pdo)
    {
    }

    private function actor(): array
    {
        return crm_require_user($this->pdo);
    }

    public function getIndex(): void
    {
        $actor = $this->actor();

        // Multi-tenant: zobrazit jen uživatele aktuálního tenantu (přes user_tenants mapping).
        // Super-admin se přepíná dropdownem v topbaru a vidí JEN tenant, na kterém je právě.
        // Uživatelé z jiných firem jsou skrytí.
        $tid = crm_tenant_id();

        $sql = "SELECT u.id, u.jmeno, u.email, u.role, u.aktivni, u.primary_region, u.created_at, u.deactivated_at,
                       ut.role AS tenant_role
                FROM users u
                INNER JOIN user_tenants ut ON ut.user_id = u.id AND ut.tenant_id = :tid AND ut.active = 1";
        if (($actor['role'] ?? '') === 'majitel') {
            $sql .= " WHERE u.role <> 'superadmin'";
        }
        $sql .= ' ORDER BY u.aktivni DESC, u.jmeno ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['tid' => $tid]);
        $users = $stmt->fetchAll();
        if (!is_array($users)) {
            $users = [];
        }

        // Per-tenant: callers + salesmen pro select boxy (přiřazení regionu/atd.)
        $callersStmt = $this->pdo->prepare(
            "SELECT u.id, u.jmeno FROM users u
             INNER JOIN user_tenants ut ON ut.user_id = u.id AND ut.tenant_id = :tid AND ut.active = 1
             WHERE u.aktivni = 1 AND u.role = 'navolavacka'
             ORDER BY u.jmeno ASC"
        );
        $callersStmt->execute(['tid' => $tid]);
        $callers = $callersStmt->fetchAll();

        $salesmenStmt = $this->pdo->prepare(
            "SELECT u.id, u.jmeno FROM users u
             INNER JOIN user_tenants ut ON ut.user_id = u.id AND ut.tenant_id = :tid AND ut.active = 1
             WHERE u.aktivni = 1 AND u.role = 'obchodak'
             ORDER BY u.jmeno ASC"
        );
        $salesmenStmt->execute(['tid' => $tid]);
        $salesmen = $salesmenStmt->fetchAll();
        if (!is_array($callers)) {
            $callers = [];
        }
        if (!is_array($salesmen)) {
            $salesmen = [];
        }

        $flash = crm_flash_take();
        $title = 'Správa uživatelů';
        $csrf = crm_csrf_token();
        ob_start();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'users' . DIRECTORY_SEPARATOR . 'index.php';
        $content = (string) ob_get_clean();
        $user = $actor; // alias pro layout/base.php (sidebar + topbar)
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'base.php';
    }

    public function getNew(): void
    {
        $actor = $this->actor();
        crm_require_roles($actor, ['majitel', 'superadmin']);
        $editUser = null;
        $userRegions = [];
        $flash = crm_flash_take();
        $title = 'Nový uživatel';
        $csrf = crm_csrf_token();
        $roleOptions = $this->roleOptionsForActor((string) $actor['role']);
        ob_start();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'users' . DIRECTORY_SEPARATOR . 'form.php';
        $content = (string) ob_get_clean();
        $user = $actor; // alias pro layout/base.php (sidebar + topbar)
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'base.php';
    }

    public function postNew(): void
    {
        $actor = $this->actor();
        crm_require_roles($actor, ['majitel', 'superadmin']);
        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/users/new');
        }

        $jmeno = trim((string) ($_POST['jmeno'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $role = (string) ($_POST['role'] ?? '');
        $primary = trim((string) ($_POST['primary_region'] ?? ''));
        $primary = $primary === '' ? null : strtolower($primary);
        $regions = isset($_POST['regions']) && is_array($_POST['regions']) ? $_POST['regions'] : [];

        // Multi-role: další role z checkboxů
        $extraRoles = isset($_POST['roles_extra']) && is_array($_POST['roles_extra']) ? $_POST['roles_extra'] : [];
        $allowedRoles = ['superadmin','majitel','navolavacka','obchodak','backoffice','cisticka'];
        $extraRoles = array_values(array_filter(
            array_map(static fn($r) => strtolower(trim((string) $r)), $extraRoles),
            static fn($r) => $r !== '' && in_array($r, $allowedRoles, true)
        ));
        $extraRoles = array_values(array_filter($extraRoles, static fn($r) => $r !== $role));
        $extraRoles = array_values(array_unique($extraRoles));
        $rolesExtraJson = $extraRoles !== [] ? json_encode($extraRoles, JSON_UNESCAPED_UNICODE) : null;

        if ($jmeno === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            crm_flash_set('Vyplňte jméno a platný e-mail.');
            crm_redirect('/admin/users/new');
        }
        if (!crm_users_actor_can_assign_role((string) $actor['role'], $role)) {
            crm_flash_set('Tuto roli nemůžete zvolit.');
            crm_redirect('/admin/users/new');
        }

        $chk = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :e');
        $chk->execute(['e' => $email]);
        if ((int) $chk->fetchColumn() > 0) {
            crm_flash_set('E-mail je již použit.');
            crm_redirect('/admin/users/new');
        }

        $plain = crm_generate_temp_password();
        $hash = crm_auth_password_hash_new($plain);
        $actorId = (int) $actor['id'];

        $this->pdo->beginTransaction();
        try {
            $ins = $this->pdo->prepare(
                'INSERT INTO users (jmeno, email, heslo_hash, role, roles_extra, primary_region, aktivni, totp_secret, totp_enabled, must_change_password, created_at, deactivated_at, created_by)
                 VALUES (:jm, :em, :hh, :rl, :re, :pr, 1, NULL, 0, 1, NOW(3), NULL, :cb)'
            );
            $ins->execute([
                'jm' => $jmeno,
                'em' => $email,
                'hh' => $hash,
                'rl' => $role,
                're' => $rolesExtraJson,
                'pr' => $primary,
                'cb' => $actorId,
            ]);
            $newId = (int) $this->pdo->lastInsertId();
            $this->syncRegions($newId, $regions);

            // Multi-tenant: automaticky mapuj nového uživatele do aktuálního tenantu.
            // Bez tohoto by user nemohl projít loginem (crm_auth_finish_login vyžaduje
            // záznam v user_tenants pro daný tenant).
            $tidNew = crm_tenant_id();
            if ($tidNew > 0) {
                $mapStmt = $this->pdo->prepare(
                    'INSERT IGNORE INTO user_tenants (user_id, tenant_id, role, roles_extra, active)
                     VALUES (:u, :t, :r, :re, 1)'
                );
                $mapStmt->execute([
                    'u'  => $newId,
                    't'  => $tidNew,
                    'r'  => $role,
                    're' => $rolesExtraJson,
                ]);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            error_log('[AdminUsers::postNew] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
            crm_flash_set('Uložení se nezdařilo. Detail: ' . $e->getMessage());
            crm_redirect('/admin/users/new');
        }

        crm_audit_log($this->pdo, $actorId, 'user_create', 'user', $newId, ['email' => $email, 'role' => $role]);

        // Activity log — vytvořen nový uživatel
        crm_activity_log_record(
            $this->pdo, $actorId, 'admin.user_created', 'user', $newId,
            ['email' => $email, 'role' => $role]
        );

        $mailOk = crm_mail_welcome_user($email, $jmeno, $plain);
        if (!$mailOk) {
            // SMTP zatím nenastaveno — ukaž heslo adminovi, ať ho může předat ručně
            crm_flash_set('✓ Uživatel vytvořen. SMTP není nastaveno → předej heslo ručně:'
                . "\n📧 Email: " . $email
                . "\n🔑 Dočasné heslo: " . $plain
                . "\n⚠ Při prvním přihlášení si uživatel musí heslo změnit.");
        } else {
            crm_flash_set('Uživatel byl vytvořen a přihlašovací údaje odeslány e-mailem.');
        }
        crm_redirect('/admin/users');
    }

    /** Role povolené pro testovací účet (tester může mít všechny krom superadmin).
     *  Majitel je teď v seznamu — pro testování oprávnění majitele/admin přehledů. */
    private const TEST_ACCOUNT_ROLES = ['majitel', 'obchodak', 'navolavacka', 'cisticka', 'backoffice'];

    public function getNewTest(): void
    {
        $actor = $this->actor();
        crm_require_roles($actor, ['superadmin']);
        $flash = crm_flash_take();
        $title = 'Nový testovací účet';
        $csrf = crm_csrf_token();
        $roleOptions = self::TEST_ACCOUNT_ROLES;
        $testDomain = CRM_TEST_ACCOUNT_DOMAIN;
        ob_start();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'users' . DIRECTORY_SEPARATOR . 'new_test.php';
        $content = (string) ob_get_clean();
        $user = $actor; // alias pro layout/base.php (sidebar + topbar)
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'base.php';
    }

    public function postNewTest(): void
    {
        $actor = $this->actor();
        crm_require_roles($actor, ['superadmin']);
        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/users/new-test');
        }

        $username = strtolower(trim((string) ($_POST['username'] ?? '')));
        $jmeno = trim((string) ($_POST['jmeno'] ?? ''));
        $role = (string) ($_POST['role'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        // Multi-role: další role z checkboxů (jen z povoleného setu pro test účty)
        $extraRoles = isset($_POST['roles_extra']) && is_array($_POST['roles_extra']) ? $_POST['roles_extra'] : [];
        $extraRoles = array_values(array_filter(
            array_map(static fn($r) => strtolower(trim((string) $r)), $extraRoles),
            static fn($r) => $r !== '' && in_array($r, self::TEST_ACCOUNT_ROLES, true)
        ));
        $extraRoles = array_values(array_filter($extraRoles, static fn($r) => $r !== $role));
        $extraRoles = array_values(array_unique($extraRoles));
        $rolesExtraJson = $extraRoles !== [] ? json_encode($extraRoles, JSON_UNESCAPED_UNICODE) : null;

        if ($username === '' || !preg_match('/^[a-z0-9][a-z0-9._-]{1,31}$/', $username)) {
            crm_flash_set('Přihlašovací jméno: 2–32 znaků, povolené a–z, 0–9, tečka, podtržítko, pomlčka.');
            crm_redirect('/admin/users/new-test');
        }
        if ($jmeno === '') {
            $jmeno = $username;
        }
        if (!in_array($role, self::TEST_ACCOUNT_ROLES, true)) {
            crm_flash_set('Vyberte platnou roli pro testovací účet.');
            crm_redirect('/admin/users/new-test');
        }
        if (strlen($password) < 6) {
            crm_flash_set('Heslo musí mít alespoň 6 znaků.');
            crm_redirect('/admin/users/new-test');
        }
        if ($password !== $passwordConfirm) {
            crm_flash_set('Hesla se neshodují.');
            crm_redirect('/admin/users/new-test');
        }

        $email = $username . '@' . CRM_TEST_ACCOUNT_DOMAIN;

        $chk = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :e');
        $chk->execute(['e' => $email]);
        if ((int) $chk->fetchColumn() > 0) {
            crm_flash_set('Testovací účet s tímto jménem již existuje.');
            crm_redirect('/admin/users/new-test');
        }

        $hash = crm_auth_password_hash_new($password);
        $actorId = (int) $actor['id'];

        try {
            $ins = $this->pdo->prepare(
                'INSERT INTO users (jmeno, email, heslo_hash, role, roles_extra, primary_region, aktivni, totp_secret, totp_enabled, must_change_password, created_at, deactivated_at, created_by)
                 VALUES (:jm, :em, :hh, :rl, :re, NULL, 1, NULL, 0, 0, NOW(3), NULL, :cb)'
            );
            $ins->execute([
                'jm' => $jmeno,
                'em' => $email,
                'hh' => $hash,
                'rl' => $role,
                're' => $rolesExtraJson,
                'cb' => $actorId,
            ]);
            $newId = (int) $this->pdo->lastInsertId();

            // Multi-tenant: mapuj testovací účet do aktuálního tenantu (= ten, do kterého
            // jsi přepnutý jako super-admin). Test účet patří kontextové firmě.
            $tidTest = crm_tenant_id();
            if ($tidTest > 0) {
                $this->pdo->prepare(
                    'INSERT IGNORE INTO user_tenants (user_id, tenant_id, role, roles_extra, active)
                     VALUES (:u, :t, :r, :re, 1)'
                )->execute([
                    'u'  => $newId,
                    't'  => $tidTest,
                    'r'  => $role,
                    're' => $rolesExtraJson,
                ]);
            }
        } catch (Throwable $e) {
            error_log('[AdminUsers::postNewTest] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
            crm_flash_set('Vytvoření testovacího účtu se nezdařilo. Detail: ' . $e->getMessage());
            crm_redirect('/admin/users/new-test');
        }

        crm_audit_log($this->pdo, $actorId, 'user_create_test', 'user', $newId, [
            'email' => $email, 'role' => $role, 'roles_extra' => $extraRoles,
        ]);

        $rolesNote = $role . ($extraRoles ? ' (+ ' . implode(', ', $extraRoles) . ')' : '');
        crm_flash_set('✓ Testovací účet vytvořen — předej testerovi:'
            . "\n👤 Login: " . $email
            . "\n🔑 Heslo: " . $password
            . "\n🎭 Role: " . $rolesNote);
        crm_redirect('/admin/users');
    }

    public function getEdit(): void
    {
        $actor = $this->actor();
        crm_require_roles($actor, ['majitel', 'superadmin']);
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            crm_redirect('/admin/users');
        }
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $editUser = $stmt->fetch();
        if (!is_array($editUser)) {
            crm_flash_set('Uživatel nenalezen.');
            crm_redirect('/admin/users');
        }
        if (!crm_users_actor_can_manage_target((string) $actor['role'], $editUser)) {
            http_response_code(403);
            echo 'Přístup odepřen.';
            exit;
        }
        unset($editUser['heslo_hash'], $editUser['totp_secret']);

        $regStmt = $this->pdo->prepare('SELECT region FROM user_regions WHERE user_id = :id ORDER BY region');
        $regStmt->execute(['id' => $id]);
        $userRegions = $regStmt->fetchAll(PDO::FETCH_COLUMN, 0);
        if (!is_array($userRegions)) {
            $userRegions = [];
        }

        $flash = crm_flash_take();
        $title = 'Upravit uživatele';
        $csrf = crm_csrf_token();
        $roleOptions = $this->roleOptionsForActor((string) $actor['role'], (string) ($editUser['role'] ?? ''));
        ob_start();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'users' . DIRECTORY_SEPARATOR . 'form.php';
        $content = (string) ob_get_clean();
        $user = $actor; // alias pro layout/base.php (sidebar + topbar)
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'base.php';
    }

    public function postSave(): void
    {
        $actor = $this->actor();
        crm_require_roles($actor, ['majitel', 'superadmin']);
        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/users');
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            crm_redirect('/admin/users');
        }
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $target = $stmt->fetch();
        if (!is_array($target)) {
            crm_flash_set('Uživatel nenalezen.');
            crm_redirect('/admin/users');
        }
        if (!crm_users_actor_can_manage_target((string) $actor['role'], $target)) {
            crm_flash_set('Tento účet upravovat nemůžete.');
            crm_redirect('/admin/users');
        }

        $jmeno = trim((string) ($_POST['jmeno'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $role = (string) ($_POST['role'] ?? '');
        $primary = trim((string) ($_POST['primary_region'] ?? ''));
        $primary = $primary === '' ? null : strtolower($primary);
        $regions = isset($_POST['regions']) && is_array($_POST['regions']) ? $_POST['regions'] : [];
        $disable2fa = !empty($_POST['disable_2fa']);

        // Subject type pref (per-navolávačka filter firma/OSVČ; default 'any')
        $subjectPref = strtolower(trim((string) ($_POST['subject_type_pref'] ?? 'any')));
        if (!in_array($subjectPref, ['any', 'firma', 'osvc'], true)) {
            $subjectPref = 'any';
        }

        // Multi-role: další role z checkboxů (mimo primární)
        $extraRoles = isset($_POST['roles_extra']) && is_array($_POST['roles_extra']) ? $_POST['roles_extra'] : [];
        $allowedRoles = ['superadmin','majitel','navolavacka','obchodak','backoffice','cisticka'];
        $extraRoles = array_values(array_filter(
            array_map(static fn($r) => strtolower(trim((string) $r)), $extraRoles),
            static fn($r) => $r !== '' && in_array($r, $allowedRoles, true)
        ));
        $extraRoles = array_values(array_filter($extraRoles, static fn($r) => $r !== $role));
        $extraRoles = array_values(array_unique($extraRoles));
        $rolesExtraJson = $extraRoles !== [] ? json_encode($extraRoles, JSON_UNESCAPED_UNICODE) : null;

        if ($jmeno === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            crm_flash_set('Vyplňte jméno a platný e-mail.');
            crm_redirect('/admin/users/edit?id=' . $id);
        }
        if (!crm_users_actor_can_assign_role((string) $actor['role'], $role)) {
            crm_flash_set('Tuto roli nemůžete zvolit.');
            crm_redirect('/admin/users/edit?id=' . $id);
        }

        $chk = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :e AND id <> :id');
        $chk->execute(['e' => $email, 'id' => $id]);
        if ((int) $chk->fetchColumn() > 0) {
            crm_flash_set('E-mail je již použit jiným účtem.');
            crm_redirect('/admin/users/edit?id=' . $id);
        }

        $this->pdo->beginTransaction();
        try {
            $upd = $this->pdo->prepare(
                'UPDATE users SET jmeno = :jm, email = :em, role = :rl, roles_extra = :re,
                                  primary_region = :pr, subject_type_pref = :stp
                 WHERE id = :id'
            );
            $upd->execute([
                'jm'  => $jmeno,
                'em'  => $email,
                'rl'  => $role,
                're'  => $rolesExtraJson,
                'pr'  => $primary,
                'stp' => $subjectPref,
                'id'  => $id,
            ]);
            $this->syncRegions($id, $regions);
            if ($disable2fa) {
                $this->pdo->prepare('UPDATE users SET totp_enabled = 0, totp_secret = NULL WHERE id = :id')->execute(['id' => $id]);
                $this->pdo->prepare('DELETE FROM totp_backup_codes WHERE user_id = :id')->execute(['id' => $id]);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            crm_flash_set('Uložení se nezdařilo.');
            crm_redirect('/admin/users/edit?id=' . $id);
        }

        crm_audit_log($this->pdo, (int) $actor['id'], 'user_update', 'user', $id, [
            'email' => $email,
            'role' => $role,
            'disable_2fa' => $disable2fa,
        ]);
        if ($disable2fa) {
            crm_audit_log($this->pdo, (int) $actor['id'], 'user_2fa_disabled', 'user', $id, []);
        }
        crm_flash_set('Uživatel byl uložen.');
        crm_redirect('/admin/users');
    }

    public function postDeactivate(): void
    {
        $actor = $this->actor();
        crm_require_roles($actor, ['majitel', 'superadmin']);
        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/users');
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0 || $id === (int) $actor['id']) {
            crm_flash_set('Neplatný cíl deaktivace.');
            crm_redirect('/admin/users');
        }

        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $target = $stmt->fetch();
        if (!is_array($target) || (int) ($target['aktivni'] ?? 0) !== 1) {
            crm_flash_set('Uživatel není aktivní nebo neexistuje.');
            crm_redirect('/admin/users');
        }
        if (!crm_users_actor_can_manage_target((string) $actor['role'], $target)) {
            crm_flash_set('Tento účet deaktivovat nemůžete.');
            crm_redirect('/admin/users');
        }

        $reCaller = (int) ($_POST['reassign_caller_to'] ?? 0);
        $reSales = (int) ($_POST['reassign_sales_to'] ?? 0);

        $ozTransferStats = null;
        $this->pdo->beginTransaction();
        try {
            if ($reCaller > 0) {
                $this->assertActiveUserRole($reCaller, 'navolavacka');
                $q = $this->pdo->prepare('UPDATE contacts SET assigned_caller_id = :to WHERE assigned_caller_id = :from');
                $q->execute(['to' => $reCaller, 'from' => $id]);
            }
            if ($reSales > 0) {
                $this->assertActiveUserRole($reSales, 'obchodak');
                // 1) Přepsat assigned_sales_id v contacts (= komu kontakt patří).
                $q = $this->pdo->prepare('UPDATE contacts SET assigned_sales_id = :to WHERE assigned_sales_id = :from');
                $q->execute(['to' => $reSales, 'from' => $id]);
                // 2) Přesunout WORKFLOW data — stav kontaktu (NABIDKA, SCHUZKA,
                //    BO_PREDANO, …), poznámky, pracovní deník, flags chybných
                //    leadů a nabídnuté služby. Bez tohoto kroku by nový OZ
                //    viděl kontakty v sidebaru, ale prázdné taby (Nabídka,
                //    Schůzka, BO, …) — protože workflow řádky drží starý oz_id.
                $ozTransferStats = $this->transferOzWorkflowData($id, $reSales);
            }

            $this->pdo->prepare(
                'UPDATE users SET aktivni = 0, deactivated_at = NOW(3) WHERE id = :id'
            )->execute(['id' => $id]);
            api_auth_invalidate_user_tokens($this->pdo, $id);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            crm_flash_set('Deaktivace se nezdařila.');
            crm_redirect('/admin/users');
        }

        crm_audit_log($this->pdo, (int) $actor['id'], 'user_deactivate', 'user', $id, [
            'reassign_caller_to' => $reCaller ?: null,
            'reassign_sales_to'  => $reSales ?: null,
            'oz_transfer'        => $ozTransferStats, // null pokud reSales=0
        ]);

        // Pokud byly nějaké kolize při převodu workflow, přidej info do flashe
        $msg = 'Uživatel byl deaktivován a API tokeny zneplatněny.';
        if ($ozTransferStats !== null) {
            $msg .= sprintf(
                ' Přesunuto na nového OZ: %d workflow, %d poznámek, %d záznamů deníku, %d nabídek. Označeno %d kontaktů jako "převzato".',
                (int) ($ozTransferStats['workflow_moved']      ?? 0),
                (int) ($ozTransferStats['notes_moved']         ?? 0),
                (int) ($ozTransferStats['actions_moved']       ?? 0),
                (int) ($ozTransferStats['offers_moved']        ?? 0),
                (int) ($ozTransferStats['marker_notes_added']  ?? 0)
            );
            $coll = (int) ($ozTransferStats['workflow_collisions'] ?? 0);
            if ($coll > 0) {
                $msg .= sprintf(
                    ' ⚠ %d kontaktů mělo již vlastní workflow u nového OZ — staré workflow byly smazány (nový OZ má přednost).',
                    $coll
                );
            }
        }
        crm_flash_set($msg);
        crm_redirect('/admin/users');
    }

    /**
     * Převede VŠECHNA per-OZ data ze starého OZ na nového při deaktivaci.
     *
     * Tabulky:
     *   - oz_contact_workflow      (UNIQUE contact_id+oz_id → IGNORE + DELETE kolize)
     *   - oz_contact_notes         (no UNIQUE → safe UPDATE)
     *   - oz_contact_actions       (no UNIQUE → safe UPDATE)
     *   - contact_oz_flags         (UNIQUE contact_id+oz_id → IGNORE + DELETE kolize)
     *   - oz_contact_offered_services (no UNIQUE → safe UPDATE)
     *
     * Kolize: pokud nový OZ už má workflow/flag pro stejný contact, jeho
     * záznam má přednost (UPDATE IGNORE preserves new OZ's record), starý
     * záznam se pak smaže. To zachovává poslední rozhodnutí nového OZ-a.
     *
     * @return array{workflow_moved:int, workflow_collisions:int, notes_moved:int, actions_moved:int, flags_moved:int, flags_collisions:int, offers_moved:int}
     */
    private function transferOzWorkflowData(int $oldOzId, int $newOzId): array
    {
        $stats = [
            'workflow_moved' => 0, 'workflow_collisions' => 0,
            'notes_moved'    => 0,
            'actions_moved'  => 0,
            'flags_moved'    => 0, 'flags_collisions'    => 0,
            'offers_moved'   => 0,
            'marker_notes_added' => 0,
        ];

        // Načíst jméno starého OZ pro hezkou marker poznámku.
        $oStmt = $this->pdo->prepare('SELECT jmeno FROM users WHERE id = :id LIMIT 1');
        $oStmt->execute(['id' => $oldOzId]);
        $oldOzName = (string) ($oStmt->fetchColumn() ?: '#' . $oldOzId);

        // Najít VŠECHNY contact_id, které mají u starého OZ jakákoliv data
        // (workflow nebo notes nebo actions). To je seznam kontaktů, které
        // se převádí na nového OZ. Pro každý z nich pak vložíme jednu novou
        // marker poznámku „📌 převzato po deaktivaci".
        // Děláme to PŘED UPDATE, protože po UPDATE už oz_id = $oldOzId neexistuje.
        $cidsStmt = $this->pdo->prepare("
            SELECT DISTINCT contact_id FROM (
                SELECT contact_id FROM oz_contact_workflow WHERE oz_id = :a
                UNION
                SELECT contact_id FROM oz_contact_notes    WHERE oz_id = :b
                UNION
                SELECT contact_id FROM oz_contact_actions  WHERE oz_id = :c
            ) AS all_contacts
        ");
        $cidsStmt->execute(['a' => $oldOzId, 'b' => $oldOzId, 'c' => $oldOzId]);
        $transferredContactIds = $cidsStmt->fetchAll(PDO::FETCH_COLUMN);

        // ── 1) oz_contact_workflow — má UNIQUE (contact_id, oz_id) ──
        //    Strategie: UPDATE IGNORE převede co může (= contacts kde nový
        //    OZ ještě nemá vlastní workflow). Zbylé jsou kolize → smazat
        //    starý záznam, nechat nového (předpoklad: nový OZ má rozhodnutí).
        $upd = $this->pdo->prepare(
            'UPDATE IGNORE oz_contact_workflow SET oz_id = :new WHERE oz_id = :old'
        );
        $upd->execute(['new' => $newOzId, 'old' => $oldOzId]);
        $stats['workflow_moved'] = $upd->rowCount();

        $collStmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM oz_contact_workflow WHERE oz_id = :old'
        );
        $collStmt->execute(['old' => $oldOzId]);
        $stats['workflow_collisions'] = (int) $collStmt->fetchColumn();
        if ($stats['workflow_collisions'] > 0) {
            $this->pdo->prepare(
                'DELETE FROM oz_contact_workflow WHERE oz_id = :old'
            )->execute(['old' => $oldOzId]);
        }

        // ── 2) oz_contact_notes — no UNIQUE → safe UPDATE ──
        $upd = $this->pdo->prepare(
            'UPDATE oz_contact_notes SET oz_id = :new WHERE oz_id = :old'
        );
        $upd->execute(['new' => $newOzId, 'old' => $oldOzId]);
        $stats['notes_moved'] = $upd->rowCount();

        // ── 3) oz_contact_actions — pracovní deník, no UNIQUE → safe UPDATE ──
        $upd = $this->pdo->prepare(
            'UPDATE oz_contact_actions SET oz_id = :new WHERE oz_id = :old'
        );
        $upd->execute(['new' => $newOzId, 'old' => $oldOzId]);
        $stats['actions_moved'] = $upd->rowCount();

        // ── 4) contact_oz_flags — UNIQUE (contact_id, oz_id) → IGNORE + DELETE ──
        try {
            $upd = $this->pdo->prepare(
                'UPDATE IGNORE contact_oz_flags SET oz_id = :new WHERE oz_id = :old'
            );
            $upd->execute(['new' => $newOzId, 'old' => $oldOzId]);
            $stats['flags_moved'] = $upd->rowCount();

            $collStmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM contact_oz_flags WHERE oz_id = :old'
            );
            $collStmt->execute(['old' => $oldOzId]);
            $stats['flags_collisions'] = (int) $collStmt->fetchColumn();
            if ($stats['flags_collisions'] > 0) {
                $this->pdo->prepare(
                    'DELETE FROM contact_oz_flags WHERE oz_id = :old'
                )->execute(['old' => $oldOzId]);
            }
        } catch (\PDOException $e) {
            // Tabulka nemusí existovat na čerstvé instalaci — graceful skip
            crm_db_log_error($e, __METHOD__);
        }

        // ── 5) oz_contact_offered_services — no UNIQUE → safe UPDATE ──
        try {
            $upd = $this->pdo->prepare(
                'UPDATE oz_contact_offered_services SET oz_id = :new WHERE oz_id = :old'
            );
            $upd->execute(['new' => $newOzId, 'old' => $oldOzId]);
            $stats['offers_moved'] = $upd->rowCount();
        } catch (\PDOException $e) {
            // Tabulka nemusí existovat — graceful skip
            crm_db_log_error($e, __METHOD__);
        }

        // ── 6) MARKER POZNÁMKY — pro každý převzatý kontakt přidat info ──
        //    "📌 Tento kontakt byl převzat po deaktivaci OZ X dne Y."
        //    Cíl: nový OZ na první pohled vidí, že tento kontakt zdědil,
        //    není to jeho vlastní práce. Existující poznámky starého OZ
        //    zůstávají (jen byly převedeny pod nového oz_id v kroku 2).
        if ($transferredContactIds !== []) {
            $markerText = sprintf(
                '📌 Tento kontakt byl převzat po deaktivaci OZ %s dne %s.',
                $oldOzName,
                date('d.m.Y')
            );
            $insertNote = $this->pdo->prepare(
                'INSERT INTO oz_contact_notes (contact_id, oz_id, note, created_at)
                 VALUES (:cid, :oid, :note, NOW(3))'
            );
            foreach ($transferredContactIds as $cid) {
                try {
                    $insertNote->execute([
                        'cid'  => (int) $cid,
                        'oid'  => $newOzId,
                        'note' => $markerText,
                    ]);
                    $stats['marker_notes_added']++;
                } catch (\PDOException $e) {
                    // Pokud INSERT selže (nepravděpodobné), pokračuj dál
                    crm_db_log_error($e, __METHOD__);
                }
            }
        }

        return $stats;
    }

    /**
     * HARD DELETE — fyzicky smaže uživatele z DB.
     * Pouze pro superadmina, nelze smazat sebe sama.
     * FK: workflow_log.user_id → SET NULL, audit_log podobně.
     * Před smazáním ošetřit assigned_caller_id / assigned_sales_id v contacts (SET NULL).
     */
    public function postDelete(): void
    {
        $actor = $this->actor();
        // Hard delete povolen JEN pro superadmin
        crm_require_roles($actor, ['superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/users');
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            crm_flash_set('Neplatný cíl smazání.');
            crm_redirect('/admin/users');
        }
        if ($id === (int) $actor['id']) {
            crm_flash_set('⚠ Sebe sama smazat nemůžeš.');
            crm_redirect('/admin/users');
        }

        $stmt = $this->pdo->prepare('SELECT id, jmeno, email, role FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $target = $stmt->fetch();
        if (!is_array($target)) {
            crm_flash_set('Uživatel neexistuje.');
            crm_redirect('/admin/users');
        }

        $this->pdo->beginTransaction();
        try {
            // 1) Vyčistit FK reference v contacts (caller / sales) → SET NULL
            $this->pdo->prepare(
                'UPDATE contacts SET assigned_caller_id = NULL WHERE assigned_caller_id = :id'
            )->execute(['id' => $id]);
            $this->pdo->prepare(
                'UPDATE contacts SET assigned_sales_id = NULL WHERE assigned_sales_id = :id'
            )->execute(['id' => $id]);

            // 2) Smazat user_regions záznamy
            try {
                $this->pdo->prepare('DELETE FROM user_regions WHERE user_id = :id')->execute(['id' => $id]);
            } catch (\PDOException) { /* tabulka může chybět */ }

            // 3) Zneplatnit API tokeny
            api_auth_invalidate_user_tokens($this->pdo, $id);

            // 4) Smazat samotného uživatele (workflow_log a audit_log mají FK SET NULL)
            $this->pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $id]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            error_log('[AdminUsers::postDelete] ' . $e->getMessage());
            crm_flash_set('Smazání selhalo: ' . $e->getMessage());
            crm_redirect('/admin/users');
        }

        crm_audit_log($this->pdo, (int) $actor['id'], 'user_hard_delete', 'user', $id, [
            'jmeno' => (string) ($target['jmeno'] ?? ''),
            'email' => (string) ($target['email'] ?? ''),
            'role'  => (string) ($target['role']  ?? ''),
        ]);
        crm_flash_set('🗑 Uživatel "' . ($target['jmeno'] ?? '') . '" byl trvale smazán.');
        crm_redirect('/admin/users');
    }

    public function postResetPassword(): void
    {
        $actor = $this->actor();
        crm_require_roles($actor, ['majitel', 'superadmin']);
        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/users');
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0 || $id === (int) $actor['id']) {
            crm_redirect('/admin/users');
        }
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $target = $stmt->fetch();
        if (!is_array($target) || (int) ($target['aktivni'] ?? 0) !== 1) {
            crm_flash_set('Uživatel není aktivní nebo neexistuje.');
            crm_redirect('/admin/users');
        }
        if (!crm_users_actor_can_manage_target((string) $actor['role'], $target)) {
            crm_flash_set('Reset hesla pro tento účet není povolen.');
            crm_redirect('/admin/users');
        }

        // Custom password — pokud admin zadal vlastní, použij to. Jinak generuj.
        // POZOR: pokud admin zadal vlastní, NESTAVÍME must_change_password=1,
        // protože admin si může chtít přihlásit jako uživatel a debugovat.
        $customPassword = (string) ($_POST['custom_password'] ?? '');
        if ($customPassword !== '') {
            if (strlen($customPassword) < 6) {
                crm_flash_set('⚠ Vlastní heslo musí mít alespoň 6 znaků.');
                crm_redirect('/admin/users');
            }
            $plain = $customPassword;
            $mustChange = 0; // admin nastavil heslo schválně, nenutíme změnu
            $action = 'user_password_set_custom';
            $flashMsg = '✓ Heslo pro ' . (string) $target['jmeno'] . ' nastaveno na: ' . $plain
                      . ' — uživatel se přihlásí tímto heslem (nebude vyzván ke změně).';
        } else {
            $plain = crm_generate_temp_password();
            $mustChange = 1; // generic temp — uživatel si změní
            $action = 'user_password_reset';
            $flashMsg = 'Nové dočasné heslo pro ' . (string) $target['jmeno'] . ': ' . $plain
                      . ' — sdělte ho uživateli osobně nebo přes zabezpečený kanál. Při příštím přihlášení bude vyzván ke změně.';
        }

        $hash = crm_auth_password_hash_new($plain);
        $this->pdo->prepare(
            'UPDATE users SET heslo_hash = :h, must_change_password = :mc WHERE id = :id'
        )->execute(['h' => $hash, 'mc' => $mustChange, 'id' => $id]);

        crm_audit_log($this->pdo, (int) $actor['id'], $action, 'user', $id, []);

        crm_flash_set($flashMsg);
        crm_redirect('/admin/users');
    }

    /**
     * POST /admin/users/impersonate — admin se "přepne" do účtu jiného uživatele.
     * Skutečné ID admina zůstane v session pod klíčem 'impersonator_id',
     * takže `crm_is_impersonating()` to detekuje a top bar nabízí "← Zpět".
     *
     * Pouze superadmin/majitel může impersonate. Sám sebe NE.
     */
    public function postImpersonate(): void
    {
        $actor = $this->actor();
        crm_require_roles($actor, ['majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/users');
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0 || $id === (int) $actor['id']) {
            crm_flash_set('Sám sebe impersonovat nemůžeš.');
            crm_redirect('/admin/users');
        }
        // Kdyby uživatel byl už impersonovaný (= další úroveň), zablokovat
        if (!empty($_SESSION['impersonator_id'])) {
            crm_flash_set('Už jsi v cizím účtu — nejprve se vrať zpět.');
            crm_redirect('/admin/users');
        }

        $stmt = $this->pdo->prepare('SELECT id, jmeno, aktivni, role FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $target = $stmt->fetch();
        if (!is_array($target) || (int) ($target['aktivni'] ?? 0) !== 1) {
            crm_flash_set('Uživatel není aktivní nebo neexistuje.');
            crm_redirect('/admin/users');
        }
        if (!crm_users_actor_can_manage_target((string) $actor['role'], $target)) {
            crm_flash_set('Tento účet nemůžeš impersonovat.');
            crm_redirect('/admin/users');
        }
        // Bezpečnost: nikdy nelze impersonovat superadmina (i jiným superadminem ne)
        if ((string) $target['role'] === 'superadmin') {
            crm_flash_set('Superadmina nelze impersonovat.');
            crm_redirect('/admin/users');
        }

        crm_audit_log(
            $this->pdo,
            (int) $actor['id'],
            'user_impersonate_start',
            'user',
            $id,
            ['target_name' => (string) $target['jmeno']]
        );

        // Uložíme skutečné ID admina a přepneme session na target
        crm_session_start();
        $_SESSION['impersonator_id']     = (int) $actor['id'];
        $_SESSION['impersonator_name']   = (string) ($actor['jmeno'] ?? '');
        $_SESSION[CRM_SESSION_USER_ID]   = (int) $target['id'];
        // Reset multi-role active role (případně použije primární)
        if (defined('CRM_SESSION_ACTIVE_ROLE')) {
            unset($_SESSION[CRM_SESSION_ACTIVE_ROLE]);
        }
        crm_session_regenerate_id();

        crm_flash_set('🎭 Jsi přepnut do účtu „' . (string) $target['jmeno'] . '". Vrať se zpět vpravo nahoře.');
        crm_redirect('/dashboard');
    }

    /**
     * GET /admin/users/impersonate-stop — návrat zpět do admin účtu.
     * Aktivace přes top-bar widget "← Zpět do admin".
     */
    public function getImpersonateStop(): void
    {
        crm_session_start();
        if (empty($_SESSION['impersonator_id'])) {
            crm_redirect('/dashboard');
        }
        $impersonatorId = (int) $_SESSION['impersonator_id'];
        $impersonatedId = (int) ($_SESSION[CRM_SESSION_USER_ID] ?? 0);

        crm_audit_log(
            $this->pdo,
            $impersonatorId,
            'user_impersonate_stop',
            'user',
            $impersonatedId,
            []
        );

        // Vrátit session na admina
        $_SESSION[CRM_SESSION_USER_ID] = $impersonatorId;
        unset($_SESSION['impersonator_id'], $_SESSION['impersonator_name']);
        if (defined('CRM_SESSION_ACTIVE_ROLE')) {
            unset($_SESSION[CRM_SESSION_ACTIVE_ROLE]);
        }
        crm_session_regenerate_id();

        crm_flash_set('✓ Vráceno do admin účtu.');
        crm_redirect('/admin/users');
    }

    /** @return list<string> */
    private function roleOptionsForActor(string $actorRole, ?string $currentTargetRole = null): array
    {
        $all = crm_all_role_values();
        $out = [];
        foreach ($all as $r) {
            if (crm_users_actor_can_assign_role($actorRole, $r)) {
                $out[] = $r;
            }
        }
        if ($currentTargetRole !== null
            && !in_array($currentTargetRole, $out, true)
            && in_array($currentTargetRole, $all, true)
        ) {
            $out[] = $currentTargetRole;
        }
        return $out;
    }

    /** @param array<int, mixed> $regions */
    private function syncRegions(int $userId, array $regions): void
    {
        $allowed = crm_region_choices();
        $filtered = [];
        foreach ($regions as $r) {
            if (!is_string($r)) {
                continue;
            }
            $r = strtolower(trim($r));
            if (in_array($r, $allowed, true)) {
                $filtered[] = $r;
            }
        }
        crm_user_regions_replace($this->pdo, $userId, $filtered);
    }

    private function assertActiveUserRole(int $userId, string $expectedRole): void
    {
        $s = $this->pdo->prepare('SELECT id, role, aktivni FROM users WHERE id = :id LIMIT 1');
        $s->execute(['id' => $userId]);
        $u = $s->fetch();
        if (!is_array($u) || (int) ($u['aktivni'] ?? 0) !== 1 || ($u['role'] ?? '') !== $expectedRole) {
            throw new RuntimeException('Neplatný cíl přeřazení.');
        }
    }
}
