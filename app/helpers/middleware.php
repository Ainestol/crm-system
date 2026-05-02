<?php
// e:\Snecinatripu\app\helpers\middleware.php
declare(strict_types=1);

/**
 * Kontrola přihlášení a rolí pro webové routy (middleware).
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'url.php';

if (!function_exists('crm_require_user')) {
    /**
     * Vyžaduje přihlášeného uživatele. Jinak přesměruje na /login.
     *
     * Pokud má uživatel must_change_password=1, je přesměrován na
     * /account/password — výjimky tvoří jen samotný password endpoint
     * a /logout (aby se uživatel mohl odhlásit, kdyby si změnu rozmyslel).
     *
     * @return array<string, mixed>
     */
    function crm_require_user(PDO $pdo): array
    {
        $user = crm_auth_current_user($pdo);
        if ($user === null) {
            crm_flash_set('Nejste přihlášeni.');
            crm_redirect('/login');
        }

        // Vynucená změna hesla — povolíme jen password endpoint a logout.
        if ((int) ($user['must_change_password'] ?? 0) === 1) {
            $path   = strtolower((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/'));
            $exempt = ['/account/password', '/logout'];
            $isExempt = false;
            foreach ($exempt as $e) {
                if ($path === $e || str_ends_with($path, $e)) {
                    $isExempt = true;
                    break;
                }
            }
            if (!$isExempt) {
                crm_flash_set('Nejprve si prosím nastavte vlastní heslo.');
                crm_redirect('/account/password');
            }
        }

        return $user;
    }
}

if (!function_exists('crm_require_roles')) {
    /**
     * Vyžaduje jednu z uvedených rolí. Jinak 403.
     *
     * @param list<string> $roles
     */
    function crm_require_roles(array $user, array $roles): void
    {
        if ($roles === []) {
            return;
        }
        $role = (string) ($user['role'] ?? '');
        if (!in_array($role, $roles, true)) {
            http_response_code(403);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><html lang="cs"><head><meta charset="UTF-8"><title>Přístup odepřen</title></head><body><p>Nemáte oprávnění k této části systému.</p><p><a href="' . crm_h(crm_url('/dashboard')) . '">Zpět na dashboard</a></p></body></html>';
            exit;
        }
    }
}
