<?php
// e:\Snecinatripu\app\helpers\encryption.php
declare(strict_types=1);

/**
 * Doplňkové kryptografické pomůcky (API tokeny, konstantní čas porovnání).
 * Symetrické šifrování konfigurace zůstává v config/constants.php (crm_encrypt / crm_decrypt).
 */

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'constants.php';

if (!function_exists('crm_safe_string_equals')) {
    /** Konstantní čas porovnání dvou řetězců stejné délky (tokeny, hashe). */
    function crm_safe_string_equals(string $known, string $user): bool
    {
        if (strlen($known) !== strlen($user)) {
            return false;
        }
        return hash_equals($known, $user);
    }
}

if (!function_exists('crm_random_hex')) {
    function crm_random_hex(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }
}

if (!function_exists('crm_hash_api_token')) {
    /** SHA-256 hex z Bearer tokenu před uložením do api_tokens.token_hash. */
    function crm_hash_api_token(string $plainToken): string
    {
        return hash('sha256', $plainToken, false);
    }
}

if (!function_exists('crm_generate_api_token_plain')) {
    /**
     * Vygeneruje náhodný Bearer token (plain) k jednorázovému zobrazení klientovi.
     * Uložte pouze crm_hash_api_token($plain).
     */
    function crm_generate_api_token_plain(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
