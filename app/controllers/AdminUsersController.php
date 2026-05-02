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

        $sql = 'SELECT id, jmeno, email, role, aktivni, primary_region, created_at, deactivated_at FROM users';
        if (($actor['role'] ?? '') === 'majitel') {
            $sql .= " WHERE role <> 'superadmin'";
        }
        $sql .= ' ORDER BY aktivni DESC, jmeno ASC';
        $users = $this->pdo->query($sql)->fetchAll();
        if (!is_array($users)) {
            $users = [];
        }

        $callers = $this->pdo->query(
            "SELECT id, jmeno FROM users WHERE aktivni = 1 AND role = 'navolavacka' ORDER BY jmeno ASC"
        )->fetchAll();
        $salesmen = $this->pdo->query(
            "SELECT id, jmeno FROM users WHERE aktivni = 1 AND role = 'obchodak' ORDER BY jmeno ASC"
        )->fetchAll();
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
                'INSERT INTO users (jmeno, email, heslo_hash, role, primary_region, aktivni, totp_secret, totp_enabled, must_change_password, created_at, deactivated_at, created_by)
                 VALUES (:jm, :em, :hh, :rl, :pr, 1, NULL, 0, 1, NOW(3), NULL, :cb)'
            );
            $ins->execute([
                'jm' => $jmeno,
                'em' => $email,
                'hh' => $hash,
                'rl' => $role,
                'pr' => $primary,
                'cb' => $actorId,
            ]);
            $newId = (int) $this->pdo->lastInsertId();
            $this->syncRegions($newId, $regions);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            crm_flash_set('Uložení se nezdařilo.');
            crm_redirect('/admin/users/new');
        }

        crm_audit_log($this->pdo, $actorId, 'user_create', 'user', $newId, ['email' => $email, 'role' => $role]);
        $mailOk = crm_mail_welcome_user($email, $jmeno, $plain);
        if (!$mailOk) {
            crm_flash_set('Uživatel byl vytvořen, ale e-mail se nepodařilo odeslat (zkontrolujte SMTP).');
        } else {
            crm_flash_set('Uživatel byl vytvořen a přihlašovací údaje odeslány e-mailem.');
        }
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
                'UPDATE users SET jmeno = :jm, email = :em, role = :rl, primary_region = :pr WHERE id = :id'
            );
            $upd->execute([
                'jm' => $jmeno,
                'em' => $email,
                'rl' => $role,
                'pr' => $primary,
                'id' => $id,
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

        $this->pdo->beginTransaction();
        try {
            if ($reCaller > 0) {
                $this->assertActiveUserRole($reCaller, 'navolavacka');
                $q = $this->pdo->prepare('UPDATE contacts SET assigned_caller_id = :to WHERE assigned_caller_id = :from');
                $q->execute(['to' => $reCaller, 'from' => $id]);
            }
            if ($reSales > 0) {
                $this->assertActiveUserRole($reSales, 'obchodak');
                $q = $this->pdo->prepare('UPDATE contacts SET assigned_sales_id = :to WHERE assigned_sales_id = :from');
                $q->execute(['to' => $reSales, 'from' => $id]);
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
            'reassign_sales_to' => $reSales ?: null,
        ]);
        crm_flash_set('Uživatel byl deaktivován a API tokeny zneplatněny.');
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

        $plain = crm_generate_temp_password();
        $hash = crm_auth_password_hash_new($plain);
        $this->pdo->prepare(
            'UPDATE users SET heslo_hash = :h, must_change_password = 1 WHERE id = :id'
        )->execute(['h' => $hash, 'id' => $id]);

        crm_audit_log($this->pdo, (int) $actor['id'], 'user_password_reset', 'user', $id, []);

        // Nové heslo zobrazíme adminovi přímo — bez emailu
        crm_flash_set(
            'Nové dočasné heslo pro ' . (string) $target['jmeno'] . ': ' . $plain .
            ' — sdělte ho uživateli osobně nebo přes zabezpečený kanál. Při příštím přihlášení bude vyzván ke změně.'
        );
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
