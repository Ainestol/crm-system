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
        crm_redirect('/dashboard');
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
        crm_redirect('/dashboard');
    }
}
