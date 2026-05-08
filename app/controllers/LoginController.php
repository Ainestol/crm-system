<?php
// e:\Snecinatripu\app\controllers\LoginController.php
declare(strict_types=1);

final class LoginController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getLogin(): void
    {
        if (crm_auth_user_id() !== null) {
            crm_redirect('/dashboard');
        }

        // ── Trusted device cookie auto-login ────────────────────────────
        // Pokud má user platnou cookie z předchozího "důvěryhodného zařízení",
        // přeskočíme heslo. Pokud je vyžadován reverify (>7 dní od poslední 2FA),
        // pošleme rovnou na /login/two-factor (pouze 2FA, žádné heslo).
        $trusted = crm_trusted_device_validate($this->pdo);
        if ($trusted['ok'] === true && isset($trusted['user_id'])) {
            $userId = (int) $trusted['user_id'];
            // Načti usera ať ověříme že je aktivní
            $user = crm_auth_user_by_id($this->pdo, $userId);
            if ($user !== null && (int) ($user['aktivni'] ?? 0) === 1) {
                if (!empty($trusted['reverify_needed'])) {
                    // 2FA reverify potřebný — pošli na /login/two-factor jen s 2FA
                    crm_auth_start_two_factor($userId);
                    crm_redirect('/login/two-factor');
                }
                // Plný auto-login bez hesla i 2FA
                crm_auth_finish_login($this->pdo, $userId);
                $this->postLoginRoleHandling($user);
            }
            // User neaktivní / smazán → cookie zruš
            crm_trusted_device_revoke($this->pdo);
        }

        // Návrat na přihlášení ruší rozpracované 2FA (uživatel zadá znovu heslo).
        crm_auth_cancel_two_factor();
        $flash = crm_flash_take();
        $title = 'Přihlášení';
        $csrf = crm_csrf_token();
        ob_start();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'login' . DIRECTORY_SEPARATOR . 'form.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'base.php';
    }

    public function postLogin(): void
    {
        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/login');
        }
        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $result = crm_auth_try_password($this->pdo, $email, $password);
        if ($result['type'] === 'locked') {
            crm_flash_set('Účet dočasně zablokován – příliš mnoho pokusů. Zkuste to později.');
            crm_redirect('/login');
        }
        if ($result['type'] === 'bad_credentials') {
            crm_flash_set('Neplatné přihlašovací údaje.');
            crm_redirect('/login');
        }
        if ($result['type'] === 'twofa_required') {
            crm_redirect('/login/two-factor');
        }
        // Login OK — pro multi-role usery zkontroluj cookie nebo redirect na select-role
        $this->postLoginRoleHandling((array) ($result['user'] ?? []));
    }

    /** Po úspěšném loginu: pokud je multi-role + nemá cookie preferred → select-role.
     *  Jinak rovnou dashboard. */
    private function postLoginRoleHandling(array $user): void
    {
        $allRoles = crm_user_all_roles($user);
        if (count($allRoles) <= 1) {
            // Single-role — žádný výběr
            crm_redirect('/dashboard');
        }
        // Multi-role — zkus cookie
        $cookieRole = (string) ($_COOKIE[CRM_PREFERRED_ROLE_COOKIE] ?? '');
        if ($cookieRole !== '' && in_array($cookieRole, $allRoles, true)) {
            crm_user_set_active_role($user, $cookieRole);
            crm_redirect('/dashboard');
        }
        // Žádná validní cookie → výběr role
        crm_redirect('/login/select-role');
    }

    public function getTwoFactor(): void
    {
        if (crm_auth_user_id() !== null) {
            crm_redirect('/dashboard');
        }
        if (crm_auth_two_factor_pending_id() === null) {
            crm_redirect('/login');
        }
        $flash = crm_flash_take();
        $title = 'Dvoufaktorové ověření';
        $csrf = crm_csrf_token();
        ob_start();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'login' . DIRECTORY_SEPARATOR . 'two_factor.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'base.php';
    }

    public function postTwoFactor(): void
    {
        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/login/two-factor');
        }
        $code = (string) ($_POST['code'] ?? '');
        $remember = (int) ($_POST['remember_device'] ?? 0) === 1;

        $result = crm_auth_try_two_factor($this->pdo, $code);
        if ($result['type'] === 'locked') {
            crm_flash_set('2FA dočasně zablokováno – příliš mnoho pokusů.');
            crm_redirect('/login/two-factor');
        }
        if ($result['type'] === 'bad_session') {
            crm_flash_set('Relace vypršela. Přihlaste se znovu.');
            crm_redirect('/login');
        }
        if ($result['type'] === 'bad_code') {
            crm_flash_set('Neplatný ověřovací kód.');
            crm_redirect('/login/two-factor');
        }
        // 2FA OK
        $user = (array) ($result['user'] ?? []);
        $userId = (int) ($user['id'] ?? 0);

        // ── Trusted device handling ──────────────────────────────────────
        // 1) Pokud měl user už trusted cookie (= reverify scenario), prodluž ji o 7 dní
        // 2) Pokud chce vystavit novou (zaškrtl "Důvěřovat zařízení 30 dní"), vystav
        if (crm_trusted_device_get_cookie_token() !== null) {
            // Existující cookie — uživatel právě zreverifikoval 2FA → prodlouž
            crm_trusted_device_mark_reverified($this->pdo);
        } elseif ($remember && $userId > 0) {
            crm_trusted_device_issue($this->pdo, $userId);
        }

        // multi-role flow stejně jako u password
        $this->postLoginRoleHandling($user);
    }

    // ════════════════════════════════════════════════════════════════
    //  GET /login/select-role — multi-role výběr role po loginu
    // ════════════════════════════════════════════════════════════════
    public function getSelectRole(): void
    {
        $user = crm_auth_current_user($this->pdo);
        if ($user === null) {
            crm_redirect('/login');
        }
        $allRoles = crm_user_all_roles($user);
        if (count($allRoles) <= 1) {
            crm_redirect('/dashboard');
        }

        $title = 'Vyberte roli';
        $csrf  = crm_csrf_token();
        $flash = crm_flash_take();
        // Aktuálně preferred role (z cookie nebo session)
        $preferred = (string) ($_COOKIE[CRM_PREFERRED_ROLE_COOKIE] ?? $user['role']);

        ob_start();
        require dirname(__DIR__) . '/views/login/select_role.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    // ════════════════════════════════════════════════════════════════
    //  POST /login/select-role — uloží volbu, případně cookie pro příště
    // ════════════════════════════════════════════════════════════════
    public function postSelectRole(): void
    {
        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/login/select-role');
        }
        $user = crm_auth_current_user($this->pdo);
        if ($user === null) {
            crm_redirect('/login');
        }
        $role     = (string) ($_POST['role'] ?? '');
        $remember = (int) ($_POST['remember'] ?? 0) === 1;

        if (!crm_user_set_active_role($user, $role)) {
            crm_flash_set('⚠ Tuto roli nemáte povolenou.');
            crm_redirect('/login/select-role');
        }

        // Cookie: 1 rok pokud remember=1, jinak smazat
        if ($remember) {
            setcookie(
                CRM_PREFERRED_ROLE_COOKIE, $role,
                [
                    'expires' => time() + 31536000, // 1 rok
                    'path'    => '/',
                    'samesite'=> 'Lax',
                    'httponly'=> true,
                ]
            );
        } else {
            // Pokud user odznačí remember, smažeme předchozí cookie
            setcookie(CRM_PREFERRED_ROLE_COOKIE, '', [
                'expires' => time() - 3600, 'path' => '/', 'samesite' => 'Lax', 'httponly' => true,
            ]);
        }

        crm_redirect('/dashboard');
    }
}
