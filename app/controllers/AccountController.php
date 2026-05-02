<?php
// e:\Snecinatripu\app\controllers\AccountController.php
declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'audit.php';

/**
 * Účet přihlášeného uživatele:
 *  - GET  /account/password — formulář na změnu hesla
 *  - POST /account/password — uloží nové heslo, smaže must_change_password flag
 *
 * Endpoint je přístupný všem rolím. Middleware ho explicitně povolí
 * uživateli s must_change_password=1, aby se mohl odemknout.
 */
final class AccountController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getChangePassword(): void
    {
        $user  = crm_require_user($this->pdo);
        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();
        $title = 'Změna hesla';

        // Příznak: vynucená změna (po prvním přihlášení nebo po resetu adminem)
        $forced = (int) ($user['must_change_password'] ?? 0) === 1;

        ob_start();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views'
              . DIRECTORY_SEPARATOR . 'account' . DIRECTORY_SEPARATOR . 'password.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views'
              . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'base.php';
    }

    public function postChangePassword(): void
    {
        $user = crm_require_user($this->pdo);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/account/password');
        }

        $current = (string) ($_POST['current_password'] ?? '');
        $new1    = (string) ($_POST['new_password']     ?? '');
        $new2    = (string) ($_POST['new_password2']    ?? '');

        // ── Validace ──
        if ($current === '' || $new1 === '' || $new2 === '') {
            crm_flash_set('Všechna pole jsou povinná.');
            crm_redirect('/account/password');
        }
        if ($new1 !== $new2) {
            crm_flash_set('Nová hesla se neshodují.');
            crm_redirect('/account/password');
        }
        // Min požadavek na sílu: 10 znaků, alespoň jedno písmeno + číslice
        if (strlen($new1) < 10 || !preg_match('/[A-Za-zÁ-ž]/u', $new1) || !preg_match('/\d/', $new1)) {
            crm_flash_set('Nové heslo musí mít alespoň 10 znaků a obsahovat písmeno i číslici.');
            crm_redirect('/account/password');
        }
        if (hash_equals($current, $new1)) {
            crm_flash_set('Nové heslo nesmí být stejné jako stávající.');
            crm_redirect('/account/password');
        }

        // ── Ověření aktuálního hesla ──
        $row = $this->pdo->prepare('SELECT heslo_hash FROM users WHERE id = :id LIMIT 1');
        $row->execute(['id' => (int) $user['id']]);
        $hash = (string) ($row->fetchColumn() ?: '');
        if ($hash === '' || !crm_auth_password_verify($current, $hash)) {
            crm_flash_set('Stávající heslo nesouhlasí.');
            crm_redirect('/account/password');
        }

        // ── Uložení nového hesla + smazání flagu ──
        $newHash = crm_auth_password_hash_new($new1);
        $upd = $this->pdo->prepare(
            'UPDATE users
             SET heslo_hash = :h,
                 must_change_password = 0
             WHERE id = :id'
        );
        $upd->execute(['h' => $newHash, 'id' => (int) $user['id']]);

        // Audit log (kdo si kdy heslo změnil)
        try {
            crm_audit_log(
                $this->pdo,
                (int) $user['id'],
                'password.changed',
                'users',
                (int) $user['id'],
                ['source' => 'self_service']
            );
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }

        // Pro jistotu regenerace session ID (neutralizuje fixaci)
        crm_session_regenerate_id();

        crm_flash_set('✓ Heslo bylo úspěšně změněno.');
        crm_redirect('/dashboard');
    }
}
