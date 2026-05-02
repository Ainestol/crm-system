<?php
// e:\Snecinatripu\config\constants.php
declare(strict_types=1);

/**
 * Globální konstanty a pomocné funkce CRM (časové pásmo, cesty, šifrování konfigurace).
 * Načítejte jako první před sms.php / mail.php / db.php, pokud potřebujete šifrování nebo CRM_BASE_PATH.
 */

if (!defined('CRM_BASE_PATH')) {
    define('CRM_BASE_PATH', dirname(__DIR__));
}

// ── .env loader ──────────────────────────────────────────────────────────────
// Načte .env ze kořene projektu a nastaví proměnné prostředí.
// Reálné env proměnné serveru (Apache SetEnv, cPanel) mají přednost.
(static function (): void {
    $envFile = CRM_BASE_PATH . DIRECTORY_SEPARATOR . '.env';
    if (!is_readable($envFile)) {
        return;
    }
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue; // přeskočit komentáře
        }
        $eqPos = strpos($line, '=');
        if ($eqPos === false) {
            continue;
        }
        $key   = trim(substr($line, 0, $eqPos));
        $value = trim(substr($line, $eqPos + 1));
        // Odstraň uvozovky pokud jsou: "hodnota" nebo 'hodnota'
        if (strlen($value) >= 2
            && (($value[0] === '"' && $value[-1] === '"')
                || ($value[0] === "'" && $value[-1] === "'"))) {
            $value = substr($value, 1, -1);
        }
        // Nenastavuj pokud proměnná už existuje (server env má přednost)
        if (getenv($key) === false) {
            putenv("{$key}={$value}");
        }
    }
})();

if (!defined('CRM_CONFIG_PATH')) {
    define('CRM_CONFIG_PATH', __DIR__);
}

if (!defined('CRM_PUBLIC_PATH')) {
    define('CRM_PUBLIC_PATH', CRM_BASE_PATH . DIRECTORY_SEPARATOR . 'public');
}

if (!defined('CRM_STORAGE_PATH')) {
    define('CRM_STORAGE_PATH', CRM_BASE_PATH . DIRECTORY_SEPARATOR . 'storage');
}

// Produkční URL aplikace (odkazy v e-mailu, absolutní cesty)
if (!defined('CRM_APP_URL')) {
    define('CRM_APP_URL', rtrim((string) (getenv('CRM_APP_URL') ?: 'https://crm.example.local'), '/'));
}

if (!defined('CRM_APP_ENV')) {
    define('CRM_APP_ENV', (string) (getenv('CRM_APP_ENV') ?: 'local'));
}

if (!defined('CRM_APP_DEBUG')) {
    define('CRM_APP_DEBUG', filter_var(getenv('CRM_APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOLEAN));
}

// Session: timeout 2 h neaktivity (sekundy)
if (!defined('CRM_SESSION_LIFETIME')) {
    define('CRM_SESSION_LIFETIME', (int) (getenv('CRM_SESSION_LIFETIME') ?: 7200));
}

if (!defined('CRM_SESSION_NAME')) {
    define('CRM_SESSION_NAME', (string) (getenv('CRM_SESSION_NAME') ?: 'crm_sid'));
}

// Přihlášení / 2FA rate limiting (dle specifikace)
if (!defined('CRM_LOGIN_MAX_ATTEMPTS')) {
    define('CRM_LOGIN_MAX_ATTEMPTS', 5);
}

if (!defined('CRM_LOGIN_WINDOW_SECONDS')) {
    define('CRM_LOGIN_WINDOW_SECONDS', 15 * 60);
}

if (!defined('CRM_LOGIN_LOCK_SECONDS')) {
    define('CRM_LOGIN_LOCK_SECONDS', 30 * 60);
}

if (!defined('CRM_API_RATE_LIMIT_PER_MINUTE')) {
    define('CRM_API_RATE_LIMIT_PER_MINUTE', 60);
}

// CSRF názvy / délka tokenu (bajty náhodných dat před hex/base64)
if (!defined('CRM_CSRF_TOKEN_BYTES')) {
    define('CRM_CSRF_TOKEN_BYTES', 32);
}

// Databázové šifrování konfigurace.
// PRIMÁRNÍ: AES-256-GCM (autentizovaná šifra; chrání proti padding oracle útokům).
// LEGACY:   AES-256-CBC bez HMAC — používá se POUZE pro dešifrování starých dat
//           uložených před přechodem na GCM (zpětná kompatibilita).
if (!defined('CRM_CIPHER_METHOD')) {
    define('CRM_CIPHER_METHOD', 'aes-256-gcm');         // nová šifra
}
if (!defined('CRM_CIPHER_METHOD_LEGACY')) {
    define('CRM_CIPHER_METHOD_LEGACY', 'AES-256-CBC');  // starý formát, jen decrypt
}
// Prefix oddělující nový formát (GCM) od starého (CBC).
// Nový výstup: "v2:" . base64(IV 12 B || ciphertext || TAG 16 B)
// Starý výstup: base64(IV 16 B || ciphertext)   — bez prefixu
if (!defined('CRM_CIPHER_PREFIX_V2')) {
    define('CRM_CIPHER_PREFIX_V2', 'v2:');
}

if (!function_exists('crm_cipher_key_binary')) {
    /**
     * Binární klíč 32 B pro AES-256.
     * Nastavte proměnnou prostředí CRM_ENCRYPTION_KEY:
     *   - 64 hex znaků (32 B), nebo
     *   - libovolný řetězec (bude zahashován SHA-256 na 32 B).
     */
    function crm_cipher_key_binary(): string
    {
        $env = getenv('CRM_ENCRYPTION_KEY');
        if ($env !== false && $env !== '') {
            if (strlen($env) === 64 && ctype_xdigit($env)) {
                $bin = hex2bin($env);
                if ($bin !== false && strlen($bin) === 32) {
                    return $bin;
                }
            }
            return hash('sha256', $env, true);
        }
        // Pouze vývoj: v produkci CRM_ENCRYPTION_KEY povinně nastavte.
        if (CRM_APP_ENV === 'production') {
            throw new RuntimeException('Chybí CRM_ENCRYPTION_KEY v produkčním prostředí.');
        }
        return hash('sha256', 'DEV_ONLY_CHANGE_CRM_ENCRYPTION_KEY', true);
    }
}

if (!function_exists('crm_encrypt')) {
    /**
     * Šifruje citlivý řetězec (SMS/SMTP hesla, API klíče, TOTP secrets).
     * Vždy AES-256-GCM (autentizovaná šifra). Výstup s prefixem "v2:".
     */
    function crm_encrypt(?string $plaintext): ?string
    {
        if ($plaintext === null || $plaintext === '') {
            return null;
        }
        $key = crm_cipher_key_binary();
        $iv  = random_bytes(12);   // GCM doporučuje 12 B IV
        $tag = '';
        $raw = openssl_encrypt(
            $plaintext,
            CRM_CIPHER_METHOD,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );
        if ($raw === false) {
            throw new RuntimeException('openssl_encrypt (GCM) selhalo.');
        }
        return CRM_CIPHER_PREFIX_V2 . base64_encode($iv . $raw . $tag);
    }
}

if (!function_exists('crm_decrypt')) {
    /**
     * Dešifruje řetězec uložený přes crm_encrypt().
     * Rozpozná "v2:" (GCM) i bezprefixový starý formát (CBC, legacy).
     * Při chybě nebo neplatných datech vrací null.
     */
    function crm_decrypt(?string $encoded): ?string
    {
        if ($encoded === null || $encoded === '') {
            return null;
        }
        $key = crm_cipher_key_binary();

        // ── Nový formát (GCM, prefix "v2:") ──────────────────────────
        if (str_starts_with($encoded, CRM_CIPHER_PREFIX_V2)) {
            $raw = base64_decode(substr($encoded, strlen(CRM_CIPHER_PREFIX_V2)), true);
            if ($raw === false || strlen($raw) < (12 + 16)) {
                return null;
            }
            $iv     = substr($raw, 0, 12);
            $tag    = substr($raw, -16);
            $cipher = substr($raw, 12, -16);
            $plain  = openssl_decrypt(
                $cipher,
                CRM_CIPHER_METHOD,
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );
            return $plain === false ? null : $plain;
        }

        // ── Starý formát (CBC, bez prefixu) — backward compatibility ─
        $bin = base64_decode($encoded, true);
        if ($bin === false || $bin === '') {
            return null;
        }
        $ivLen = openssl_cipher_iv_length(CRM_CIPHER_METHOD_LEGACY);
        if ($ivLen === false || strlen($bin) <= $ivLen) {
            return null;
        }
        $iv     = substr($bin, 0, $ivLen);
        $cipher = substr($bin, $ivLen);
        $plain  = openssl_decrypt($cipher, CRM_CIPHER_METHOD_LEGACY, $key, OPENSSL_RAW_DATA, $iv);
        return $plain === false ? null : $plain;
    }
}

date_default_timezone_set((string) (getenv('CRM_TIMEZONE') ?: 'Europe/Prague'));
