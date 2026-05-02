<?php
// e:\Snecinatripu\app\helpers\session.php
declare(strict_types=1);

/**
 * Správa PHP session: jméno, cookie parametry, nečinnost, regenerace ID po přihlášení.
 */

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'constants.php';

if (!defined('CRM_SESSION_LAST_ACTIVITY')) {
    define('CRM_SESSION_LAST_ACTIVITY', 'crm_last_activity');
}

if (!function_exists('crm_session_is_https')) {
    function crm_session_is_https(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        return ((int) ($_SERVER['SERVER_PORT'] ?? 0)) === 443;
    }
}

if (!function_exists('crm_session_start')) {
    function crm_session_start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = crm_session_is_https();
        $params = [
            'lifetime' => CRM_SESSION_LIFETIME,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params($params);
        } else {
            session_set_cookie_params(
                $params['lifetime'],
                $params['path'] . '; samesite=' . $params['samesite'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_name(CRM_SESSION_NAME);
        session_start();
        crm_session_touch();
    }
}

if (!function_exists('crm_session_touch')) {
    function crm_session_touch(): void
    {
        $_SESSION[CRM_SESSION_LAST_ACTIVITY] = time();
    }
}

if (!function_exists('crm_session_idle_seconds')) {
    function crm_session_idle_seconds(): int
    {
        $t = $_SESSION[CRM_SESSION_LAST_ACTIVITY] ?? 0;
        return time() - (int) $t;
    }
}

if (!function_exists('crm_session_check_idle')) {
    /** Vrátí true, pokud session vypršela nečinností (2 h dle konstant). */
    function crm_session_check_idle(): bool
    {
        return crm_session_idle_seconds() > CRM_SESSION_LIFETIME;
    }
}

if (!function_exists('crm_session_regenerate_id')) {
    /** Regenerace session ID (po úspěšném přihlášení). */
    function crm_session_regenerate_id(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}

if (!function_exists('crm_session_destroy')) {
    function crm_session_destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            }
            session_destroy();
        }
    }
}
