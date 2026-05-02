<?php
// e:\Snecinatripu\app\helpers\csrf.php
declare(strict_types=1);

/**
 * CSRF ochrana formulářů – token v session, ověření při POST.
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'session.php';

if (!defined('CRM_CSRF_SESSION_KEY')) {
    define('CRM_CSRF_SESSION_KEY', 'crm_csrf_token');
}

if (!defined('CRM_CSRF_POST_FIELD')) {
    define('CRM_CSRF_POST_FIELD', 'csrf_token');
}

if (!function_exists('crm_csrf_token')) {
    /** Vrátí platný token (vygeneruje při prvním volání v rámci session). */
    function crm_csrf_token(): string
    {
        crm_session_start();
        if (empty($_SESSION[CRM_CSRF_SESSION_KEY]) || !is_string($_SESSION[CRM_CSRF_SESSION_KEY])) {
            $_SESSION[CRM_CSRF_SESSION_KEY] = bin2hex(random_bytes(CRM_CSRF_TOKEN_BYTES));
        }
        return $_SESSION[CRM_CSRF_SESSION_KEY];
    }
}

if (!function_exists('crm_csrf_validate')) {
    /**
     * Ověří token z pole (např. $_POST['csrf_token']).
     * Po úspěchu může volitelně token rotovat – zde rotace ne, aby šel formulář znovu odeslat řízeně z aplikace.
     */
    function crm_csrf_validate(?string $submitted): bool
    {
        crm_session_start();
        $expected = $_SESSION[CRM_CSRF_SESSION_KEY] ?? null;
        if (!is_string($expected) || $expected === '' || !is_string($submitted) || $submitted === '') {
            return false;
        }
        return hash_equals($expected, $submitted);
    }
}

if (!function_exists('crm_csrf_field_name')) {
    function crm_csrf_field_name(): string
    {
        return CRM_CSRF_POST_FIELD;
    }
}
