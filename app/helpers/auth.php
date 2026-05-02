<?php
// e:\Snecinatripu\app\helpers\auth.php
declare(strict_types=1);

/**
 * Přihlášení uživatele (PDO), ověření hesla (ARGON2ID / bcrypt ze seedu),
 * session proměnné, rate limiting přihlášení (5/15 min, zámek 30 min).
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'session.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'encryption.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'totp.php';

if (!defined('CRM_SESSION_USER_ID')) {
    define('CRM_SESSION_USER_ID', 'crm_user_id');
}

if (!defined('CRM_SESSION_2FA_UID')) {
    define('CRM_SESSION_2FA_UID', 'crm_2fa_pending_uid');
}

if (!function_exists('crm_pdo')) {
    function crm_pdo(): PDO
    {
        static $pdo = null;
        if ($pdo instanceof PDO) {
            return $pdo;
        }
        /** @var array $cfg */
        $cfg = require CRM_CONFIG_PATH . DIRECTORY_SEPARATOR . 'db.php';
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['database'],
            $cfg['charset']
        );
        $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], $cfg['options']);
        return $pdo;
    }
}

if (!function_exists('crm_auth_strip_sensitive')) {
    /** Odstraní heslo a TOTP secret z pole uživatele z DB. */
    function crm_auth_strip_sensitive(array $user): array
    {
        unset($user['heslo_hash'], $user['totp_secret']);
        return $user;
    }
}

if (!function_exists('crm_auth_user_by_id')) {
    function crm_auth_user_by_id(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM users WHERE id = :id AND aktivni = 1 LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }
}

if (!function_exists('crm_auth_user_by_email')) {
    function crm_auth_user_by_email(PDO $pdo, string $email): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM users WHERE email = :email AND aktivni = 1 LIMIT 1'
        );
        $stmt->execute(['email' => strtolower(trim($email))]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }
}

if (!function_exists('crm_auth_password_hash_new')) {
    /** Nové heslo – vždy ARGON2ID dle specifikace. */
    function crm_auth_password_hash_new(string $plain): string
    {
        return password_hash($plain, PASSWORD_ARGON2ID);
    }
}

if (!function_exists('crm_auth_password_verify')) {
    function crm_auth_password_verify(string $plain, string $storedHash): bool
    {
        return password_verify($plain, $storedHash);
    }
}

if (!function_exists('crm_auth_rate_state_path')) {
    function crm_auth_rate_state_path(string $ip, string $bucket): string
    {
        $dir = CRM_STORAGE_PATH . DIRECTORY_SEPARATOR . 'ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $key = hash('sha256', $ip . '|' . $bucket);
        return $dir . DIRECTORY_SEPARATOR . $key . '.json';
    }
}

if (!function_exists('crm_auth_rate_load')) {
    /** @return array{failures: list<int>, locked_until: int} */
    function crm_auth_rate_load(string $path): array
    {
        if (!is_readable($path)) {
            return ['failures' => [], 'locked_until' => 0];
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return ['failures' => [], 'locked_until' => 0];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return ['failures' => [], 'locked_until' => 0];
        }
        $failures = $data['failures'] ?? [];
        if (!is_array($failures)) {
            $failures = [];
        }
        $failures = array_values(array_filter($failures, 'is_int'));
        $locked = (int) ($data['locked_until'] ?? 0);
        return ['failures' => $failures, 'locked_until' => $locked];
    }
}

if (!function_exists('crm_auth_rate_save')) {
    /** @param array{failures: list<int>, locked_until: int} $state */
    function crm_auth_rate_save(string $path, array $state): void
    {
        file_put_contents(
            $path,
            json_encode($state, JSON_THROW_ON_ERROR),
            LOCK_EX
        );
    }
}

if (!function_exists('crm_auth_rate_prune')) {
    /** @param list<int> $failures */
    function crm_auth_rate_prune(array $failures, int $now): array
    {
        $cutoff = $now - CRM_LOGIN_WINDOW_SECONDS;
        return array_values(array_filter($failures, static fn (int $t): bool => $t >= $cutoff));
    }
}

if (!function_exists('crm_auth_rate_allowed')) {
    /** False = příliš mnoho pokusů nebo aktivní zámek (bucket: login | twofa). */
    function crm_auth_rate_allowed(string $ip, string $bucket): bool
    {
        $path = crm_auth_rate_state_path($ip, $bucket);
        $state = crm_auth_rate_load($path);
        $now = time();
        if ($state['locked_until'] > $now) {
            return false;
        }
        $failures = crm_auth_rate_prune($state['failures'], $now);
        return count($failures) < CRM_LOGIN_MAX_ATTEMPTS;
    }
}

if (!function_exists('crm_auth_rate_register_failure')) {
    function crm_auth_rate_register_failure(string $ip, string $bucket): void
    {
        $path = crm_auth_rate_state_path($ip, $bucket);
        $state = crm_auth_rate_load($path);
        $now = time();
        if ($state['locked_until'] > $now) {
            return;
        }
        $failures = crm_auth_rate_prune($state['failures'], $now);
        $failures[] = $now;
        $lockedUntil = 0;
        if (count($failures) >= CRM_LOGIN_MAX_ATTEMPTS) {
            $lockedUntil = $now + CRM_LOGIN_LOCK_SECONDS;
            $failures = [];
        }
        crm_auth_rate_save($path, ['failures' => $failures, 'locked_until' => $lockedUntil]);
    }
}

if (!function_exists('crm_auth_rate_clear')) {
    function crm_auth_rate_clear(string $ip, string $bucket): void
    {
        $path = crm_auth_rate_state_path($ip, $bucket);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

if (!function_exists('crm_auth_login_allowed')) {
    function crm_auth_login_allowed(string $ip): bool
    {
        return crm_auth_rate_allowed($ip, 'login');
    }
}

if (!function_exists('crm_auth_login_register_failure')) {
    function crm_auth_login_register_failure(string $ip): void
    {
        crm_auth_rate_register_failure($ip, 'login');
    }
}

if (!function_exists('crm_auth_login_clear_failures')) {
    function crm_auth_login_clear_failures(string $ip): void
    {
        crm_auth_rate_clear($ip, 'login');
    }
}

if (!function_exists('crm_auth_client_ip')) {
    function crm_auth_client_ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}

if (!function_exists('crm_auth_needs_two_factor')) {
    function crm_auth_needs_two_factor(array $user): bool
    {
        if ((int) ($user['totp_enabled'] ?? 0) !== 1) {
            return false;
        }
        $s = $user['totp_secret'] ?? null;
        if ($s === null) {
            return false;
        }
        if (is_string($s) && trim($s) === '') {
            return false;
        }
        return true;
    }
}

if (!function_exists('crm_auth_totp_secret_string')) {
    /** Secret z DB (VARBINARY / řetězec) na Base32 pro totp_verify. */
    function crm_auth_totp_secret_string(mixed $secret): string
    {
        if ($secret === null) {
            return '';
        }
        $s = is_string($secret) ? $secret : (string) $secret;
        return strtoupper(preg_replace('/\s+/', '', $s) ?? '');
    }
}

if (!function_exists('crm_auth_start_two_factor')) {
    function crm_auth_start_two_factor(int $userId): void
    {
        crm_session_start();
        crm_session_regenerate_id();
        unset($_SESSION[CRM_SESSION_USER_ID]);
        $_SESSION[CRM_SESSION_2FA_UID] = $userId;
        crm_session_touch();
    }
}

if (!function_exists('crm_auth_two_factor_pending_id')) {
    function crm_auth_two_factor_pending_id(): ?int
    {
        crm_session_start();
        if (!isset($_SESSION[CRM_SESSION_2FA_UID])) {
            return null;
        }
        $id = (int) $_SESSION[CRM_SESSION_2FA_UID];
        return $id > 0 ? $id : null;
    }
}

if (!function_exists('crm_auth_cancel_two_factor')) {
    function crm_auth_cancel_two_factor(): void
    {
        crm_session_start();
        unset($_SESSION[CRM_SESSION_2FA_UID]);
    }
}

if (!function_exists('crm_auth_finish_login')) {
    /** Dokončí přihlášení: zruší 2FA pending, nastaví user_id, regeneruje session. */
    function crm_auth_finish_login(PDO $pdo, int $userId): void
    {
        $user = crm_auth_user_by_id($pdo, $userId);
        if ($user === null) {
            return;
        }
        crm_session_start();
        crm_session_regenerate_id();
        unset($_SESSION[CRM_SESSION_2FA_UID]);
        $_SESSION[CRM_SESSION_USER_ID] = $userId;
        crm_session_touch();
    }
}

if (!function_exists('crm_auth_verify_backup_code')) {
    function crm_auth_verify_backup_code(PDO $pdo, int $userId, string $code): bool
    {
        $code = trim($code);
        if ($code === '') {
            return false;
        }
        $stmt = $pdo->prepare(
            'SELECT id, code_hash FROM totp_backup_codes WHERE user_id = :u AND used_at IS NULL'
        );
        $stmt->execute(['u' => $userId]);
        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return false;
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['code_hash'])) {
                continue;
            }
            if (password_verify($code, (string) $row['code_hash'])) {
                $u = $pdo->prepare('UPDATE totp_backup_codes SET used_at = NOW(3) WHERE id = :id');
                $u->execute(['id' => (int) $row['id']]);
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('crm_auth_try_password')) {
    /**
     * Krok 1: heslo. Vrací typ výsledku pro controller.
     *
     * @return array{type:'locked'}|array{type:'bad_credentials'}|array{type:'twofa_required'}|array{type:'ok', user:array}
     */
    function crm_auth_try_password(PDO $pdo, string $email, string $password): array
    {
        $ip = crm_auth_client_ip();
        if (!crm_auth_rate_allowed($ip, 'login')) {
            return ['type' => 'locked'];
        }

        $user = crm_auth_user_by_email($pdo, $email);
        if ($user === null) {
            crm_auth_rate_register_failure($ip, 'login');
            return ['type' => 'bad_credentials'];
        }

        if (!crm_auth_password_verify($password, (string) $user['heslo_hash'])) {
            crm_auth_rate_register_failure($ip, 'login');
            return ['type' => 'bad_credentials'];
        }

        crm_auth_rate_clear($ip, 'login');

        if (crm_auth_needs_two_factor($user)) {
            crm_auth_start_two_factor((int) $user['id']);
            return ['type' => 'twofa_required'];
        }

        crm_auth_finish_login($pdo, (int) $user['id']);
        $fresh = crm_auth_user_by_id($pdo, (int) $user['id']);
        return ['type' => 'ok', 'user' => crm_auth_strip_sensitive($fresh ?? $user)];
    }
}

if (!function_exists('crm_auth_try_two_factor')) {
    /**
     * Krok 2: TOTP nebo záložní kód.
     *
     * @return array{type:'locked'}|array{type:'bad_code'}|array{type:'bad_session'}|array{type:'ok', user:array}
     */
    function crm_auth_try_two_factor(PDO $pdo, string $code): array
    {
        $ip = crm_auth_client_ip();
        if (!crm_auth_rate_allowed($ip, 'twofa')) {
            return ['type' => 'locked'];
        }

        $pending = crm_auth_two_factor_pending_id();
        if ($pending === null) {
            return ['type' => 'bad_session'];
        }

        $user = crm_auth_user_by_id($pdo, $pending);
        if ($user === null) {
            crm_auth_cancel_two_factor();
            return ['type' => 'bad_session'];
        }

        $codeTrim = preg_replace('/\s+/', '', $code) ?? '';
        $secret = crm_auth_totp_secret_string($user['totp_secret'] ?? '');
        $valid = false;
        if ($secret !== '' && totp_verify($secret, $codeTrim, 1)) {
            $valid = true;
        }
        if (!$valid && crm_auth_verify_backup_code($pdo, $pending, $codeTrim)) {
            $valid = true;
        }

        if (!$valid) {
            crm_auth_rate_register_failure($ip, 'twofa');
            return ['type' => 'bad_code'];
        }

        crm_auth_rate_clear($ip, 'twofa');
        crm_auth_finish_login($pdo, $pending);
        $out = crm_auth_user_by_id($pdo, $pending);
        return ['type' => 'ok', 'user' => crm_auth_strip_sensitive($out ?? [])];
    }
}

if (!function_exists('crm_auth_user_id')) {
    function crm_auth_user_id(): ?int
    {
        crm_session_start();
        if (!isset($_SESSION[CRM_SESSION_USER_ID])) {
            return null;
        }
        $id = (int) $_SESSION[CRM_SESSION_USER_ID];
        return $id > 0 ? $id : null;
    }
}

if (!function_exists('crm_auth_current_user')) {
    /** Načte aktivního uživatele podle session (nebo null). */
    function crm_auth_current_user(PDO $pdo): ?array
    {
        $id = crm_auth_user_id();
        if ($id === null) {
            return null;
        }
        if (crm_session_check_idle()) {
            crm_auth_logout();
            return null;
        }
        $user = crm_auth_user_by_id($pdo, $id);
        if ($user === null) {
            crm_auth_logout();
            return null;
        }
        crm_session_touch();
        return crm_auth_strip_sensitive($user);
    }
}

if (!function_exists('crm_auth_logout')) {
    function crm_auth_logout(): void
    {
        crm_session_start();
        if (function_exists('crm_region_clear_session')) {
            crm_region_clear_session();
        }
        unset($_SESSION[CRM_SESSION_USER_ID], $_SESSION[CRM_SESSION_2FA_UID]);
        crm_session_regenerate_id();
        crm_session_destroy();
    }
}
