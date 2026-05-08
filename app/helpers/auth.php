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

if (!defined('CRM_SESSION_ACTIVE_ROLE')) {
    // Aktivní role v session — pro multi-role uživatele.
    // Když user má víc rolí (role + roles_extra), po výběru se uloží sem
    // a všechny views dále používají user['role'] = active role.
    define('CRM_SESSION_ACTIVE_ROLE', 'crm_active_role');
}

if (!defined('CRM_SESSION_PENDING_ROLE_SELECT_UID')) {
    // ID usera čekajícího na výběr role po loginu (multi-role flow).
    define('CRM_SESSION_PENDING_ROLE_SELECT_UID', 'crm_pending_role_select_uid');
}

if (!defined('CRM_PREFERRED_ROLE_COOKIE')) {
    define('CRM_PREFERRED_ROLE_COOKIE', 'crm_preferred_role');
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

if (!function_exists('crm_user_all_roles')) {
    /**
     * Vrátí seznam VŠECH rolí uživatele (primární + extra).
     * Primární role je vždy první v poli.
     *
     * @return list<string>
     */
    function crm_user_all_roles(array $user): array
    {
        // DŮLEŽITÉ: bereme PRIMÁRNÍ roli z DB, ne aktuální session role.
        // Když user přijde z crm_auth_current_user po přepnutí, $user['role']
        // už je AKTIVNÍ (např. "majitel" po přepnutí z obchodáka). Pokud bychom
        // brali tu, ztratíme původní DB primary a uživatel by ji nemohl přepnout zpět.
        $primary = (string) ($user['primary_role'] ?? $user['role'] ?? '');

        $extras = [];
        $rawExtra = $user['roles_extra'] ?? null;
        if (is_string($rawExtra) && $rawExtra !== '') {
            $decoded = json_decode($rawExtra, true);
            if (is_array($decoded)) {
                foreach ($decoded as $r) {
                    if (is_string($r) && $r !== '' && $r !== $primary) {
                        $extras[] = $r;
                    }
                }
            }
        } elseif (is_array($rawExtra)) {
            foreach ($rawExtra as $r) {
                if (is_string($r) && $r !== '' && $r !== $primary) {
                    $extras[] = $r;
                }
            }
        }

        return array_values(array_unique(array_merge([$primary], $extras)));
    }
}

if (!function_exists('crm_user_is_multirole')) {
    /** True pokud má víc než 1 roli (primární + alespoň 1 extra). */
    function crm_user_is_multirole(array $user): bool
    {
        return count(crm_user_all_roles($user)) > 1;
    }
}

if (!function_exists('crm_user_get_active_role')) {
    /**
     * Vrátí aktivní roli ze session (nebo primární pokud nic nezvoleno).
     */
    function crm_user_get_active_role(array $user): string
    {
        crm_session_start();
        $allRoles = crm_user_all_roles($user);
        $sessRole = (string) ($_SESSION[CRM_SESSION_ACTIVE_ROLE] ?? '');
        if ($sessRole !== '' && in_array($sessRole, $allRoles, true)) {
            return $sessRole;
        }
        return $allRoles[0] ?? (string) ($user['role'] ?? '');
    }
}

if (!function_exists('crm_user_set_active_role')) {
    /**
     * Nastaví aktivní roli v session (jen pokud user roli skutečně má).
     * Vrací true pokud OK, false pokud role není povolená.
     */
    function crm_user_set_active_role(array $user, string $role): bool
    {
        crm_session_start();
        $allRoles = crm_user_all_roles($user);
        if (!in_array($role, $allRoles, true)) {
            return false;
        }
        $_SESSION[CRM_SESSION_ACTIVE_ROLE] = $role;
        return true;
    }
}

if (!function_exists('crm_auth_current_user')) {
    /** Načte aktivního uživatele podle session (nebo null).
     *  Pokud má user víc rolí, do `$user['role']` se nastaví AKTIVNÍ role
     *  (z session), original primární je v `$user['primary_role']`. */
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
        $user = crm_auth_strip_sensitive($user);

        // Multi-role injekce: zachovat original a přepsat role na aktivní
        $user['primary_role'] = (string) ($user['role'] ?? '');
        $user['all_roles']    = crm_user_all_roles($user);
        $user['role']         = crm_user_get_active_role($user);
        return $user;
    }
}

if (!function_exists('crm_auth_logout')) {
    function crm_auth_logout(?PDO $pdo = null): void
    {
        // Při logout zneplatnit i trusted device cookie (pokud existuje)
        if ($pdo !== null && function_exists('crm_trusted_device_revoke')) {
            crm_trusted_device_revoke($pdo);
        }
        crm_session_start();
        if (function_exists('crm_region_clear_session')) {
            crm_region_clear_session();
        }
        unset(
            $_SESSION[CRM_SESSION_USER_ID],
            $_SESSION[CRM_SESSION_2FA_UID],
            $_SESSION[CRM_SESSION_ACTIVE_ROLE],
            $_SESSION[CRM_SESSION_PENDING_ROLE_SELECT_UID]
        );
        crm_session_regenerate_id();
        crm_session_destroy();
    }
}

// ════════════════════════════════════════════════════════════════════
//  Trusted Device cookie ("Důvěřovat tomuto zařízení 30 dní")
// ────────────────────────────────────────────────────────────────────
//  Tokenový auto-login pro uživatele s aktivním 2FA. Cookie obsahuje
//  plain-text token (64 hex znaků), v DB uložen jen SHA256 hash.
//  Krádež DB = útočník nezíská validní cookie.
//
//  Životní cyklus:
//    1. Po úspěšném loginu (heslo + 2FA) + zaškrtnuto "důvěřovat 30 dní":
//       crm_trusted_device_issue() → DB INSERT + setcookie()
//    2. Při dalším návratu (request middleware nebo crm_require_user):
//       crm_trusted_device_validate() → vrátí user_id nebo null
//    3. Pokud reverify_at v minulosti, ale expires_at v budoucnosti:
//       systém vyžádá JEN 2FA kód (ne heslo) → po úspěchu volá
//       crm_trusted_device_mark_reverified() → +7 dní
//    4. Pokud expires_at v minulosti → DELETE + cookie smazána → plný login
//    5. Logout → crm_trusted_device_revoke() (jen aktuální cookie)
//    6. Admin "odhlásit ze všech zařízení" → crm_trusted_device_revoke_all()
// ════════════════════════════════════════════════════════════════════

if (!defined('CRM_TRUSTED_COOKIE')) {
    define('CRM_TRUSTED_COOKIE', 'crm_trusted_device');
}
if (!defined('CRM_TRUSTED_DEFAULT_EXPIRES_DAYS')) {
    define('CRM_TRUSTED_DEFAULT_EXPIRES_DAYS', 30);
}
if (!defined('CRM_TRUSTED_DEFAULT_REVERIFY_DAYS')) {
    define('CRM_TRUSTED_DEFAULT_REVERIFY_DAYS', 7);
}

if (!function_exists('crm_trusted_device_hash')) {
    /** SHA256 hash plain-text tokenu pro DB storage. */
    function crm_trusted_device_hash(string $plain): string
    {
        return hash('sha256', $plain);
    }
}

if (!function_exists('crm_trusted_device_get_cookie_token')) {
    /** Plain-text token z cookie (64 hex znaků), nebo null pokud nenastaveno / nevalidní. */
    function crm_trusted_device_get_cookie_token(): ?string
    {
        $raw = $_COOKIE[CRM_TRUSTED_COOKIE] ?? null;
        if (!is_string($raw)) return null;
        $raw = trim($raw);
        // Bezpečnost: očekáváme 64 hex znaků (32 bytes)
        if (preg_match('/^[a-f0-9]{64}$/i', $raw) !== 1) return null;
        return strtolower($raw);
    }
}

if (!function_exists('crm_trusted_device_set_cookie')) {
    /** Nastaví trusted device cookie s nastaveným expires_at (Unix timestamp). */
    function crm_trusted_device_set_cookie(string $token, int $expiresUnix): void
    {
        // Secure flag: jen HTTPS pokud server běží přes HTTPS, jinak ne (DEV).
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie(CRM_TRUSTED_COOKIE, $token, [
            'expires'  => $expiresUnix,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('crm_trusted_device_clear_cookie')) {
    function crm_trusted_device_clear_cookie(): void
    {
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie(CRM_TRUSTED_COOKIE, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[CRM_TRUSTED_COOKIE]);
    }
}

if (!function_exists('crm_trusted_device_issue')) {
    /**
     * Vystaví nový trusted device token, uloží do DB, nastaví cookie.
     * Vrací plain-text token (pro debug / unit testy), v praxi se nepoužívá.
     */
    function crm_trusted_device_issue(
        PDO $pdo,
        int $userId,
        int $expiresInDays = CRM_TRUSTED_DEFAULT_EXPIRES_DAYS,
        int $reverifyInDays = CRM_TRUSTED_DEFAULT_REVERIFY_DAYS
    ): string {
        $plain = bin2hex(random_bytes(32));
        $hash  = crm_trusted_device_hash($plain);

        $userAgent = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
        $ipAddr    = mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

        $expiresAt  = (new DateTimeImmutable('+' . $expiresInDays . ' days'))->format('Y-m-d H:i:s');
        $reverifyAt = (new DateTimeImmutable('+' . $reverifyInDays . ' days'))->format('Y-m-d H:i:s');

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO auth_trusted_devices
                   (user_id, token_hash, user_agent, ip_address, expires_at, reverify_at)
                 VALUES (:uid, :th, :ua, :ip, :exp, :rev)'
            );
            $stmt->execute([
                'uid' => $userId,
                'th'  => $hash,
                'ua'  => $userAgent !== '' ? $userAgent : null,
                'ip'  => $ipAddr !== ''    ? $ipAddr    : null,
                'exp' => $expiresAt,
                'rev' => $reverifyAt,
            ]);
        } catch (\PDOException $e) {
            error_log('[CRM Trusted] issue failed: ' . $e->getMessage());
            return $plain;
        }

        $expiresUnix = (int) (new DateTimeImmutable($expiresAt))->format('U');
        crm_trusted_device_set_cookie($plain, $expiresUnix);

        return $plain;
    }
}

if (!function_exists('crm_trusted_device_validate')) {
    /**
     * Ověří trusted device cookie. Vrací:
     *   - ['ok' => true,  'user_id' => N, 'reverify_needed' => bool]    pokud cookie validní
     *   - ['ok' => false, 'reason' => 'no_cookie']                       pokud cookie chybí
     *   - ['ok' => false, 'reason' => 'expired'   /  'unknown' / 'invalid']
     *
     * Při 'expired' / 'unknown' / 'invalid' → cookie smažeme + DB cleanup.
     *
     * @return array{ok: bool, user_id?: int, reverify_needed?: bool, reason?: string}
     */
    function crm_trusted_device_validate(PDO $pdo): array
    {
        $token = crm_trusted_device_get_cookie_token();
        if ($token === null) {
            return ['ok' => false, 'reason' => 'no_cookie'];
        }

        $hash = crm_trusted_device_hash($token);
        try {
            $stmt = $pdo->prepare(
                'SELECT user_id, expires_at, reverify_at
                 FROM auth_trusted_devices
                 WHERE token_hash = :th
                 LIMIT 1'
            );
            $stmt->execute(['th' => $hash]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('[CRM Trusted] validate failed: ' . $e->getMessage());
            return ['ok' => false, 'reason' => 'invalid'];
        }

        if (!is_array($row)) {
            // Token v cookie, ale ne v DB → cookie zruš
            crm_trusted_device_clear_cookie();
            return ['ok' => false, 'reason' => 'unknown'];
        }

        $now = new DateTimeImmutable('now');
        $exp = new DateTimeImmutable((string) $row['expires_at']);
        if ($now > $exp) {
            // Token expiroval → DB cleanup + cookie smaž
            try {
                $pdo->prepare('DELETE FROM auth_trusted_devices WHERE token_hash = :th')->execute(['th' => $hash]);
            } catch (\PDOException) {}
            crm_trusted_device_clear_cookie();
            return ['ok' => false, 'reason' => 'expired'];
        }

        $rev = new DateTimeImmutable((string) $row['reverify_at']);
        $reverifyNeeded = $now > $rev;

        // Update last_used_at (best-effort)
        try {
            $pdo->prepare('UPDATE auth_trusted_devices SET last_used_at = NOW(3) WHERE token_hash = :th')
                ->execute(['th' => $hash]);
        } catch (\PDOException) {}

        return [
            'ok' => true,
            'user_id' => (int) $row['user_id'],
            'reverify_needed' => $reverifyNeeded,
        ];
    }
}

if (!function_exists('crm_trusted_device_mark_reverified')) {
    /** Po úspěšném 2FA reverify prodlouží reverify_at o N dní. */
    function crm_trusted_device_mark_reverified(
        PDO $pdo,
        int $reverifyInDays = CRM_TRUSTED_DEFAULT_REVERIFY_DAYS
    ): void {
        $token = crm_trusted_device_get_cookie_token();
        if ($token === null) return;
        $hash = crm_trusted_device_hash($token);
        $reverifyAt = (new DateTimeImmutable('+' . $reverifyInDays . ' days'))->format('Y-m-d H:i:s');
        try {
            $pdo->prepare(
                'UPDATE auth_trusted_devices SET reverify_at = :rev, last_used_at = NOW(3) WHERE token_hash = :th'
            )->execute(['rev' => $reverifyAt, 'th' => $hash]);
        } catch (\PDOException) {}
    }
}

if (!function_exists('crm_trusted_device_revoke')) {
    /** Zruší aktuální trusted device cookie + DB záznam. */
    function crm_trusted_device_revoke(PDO $pdo): void
    {
        $token = crm_trusted_device_get_cookie_token();
        if ($token === null) {
            crm_trusted_device_clear_cookie();
            return;
        }
        $hash = crm_trusted_device_hash($token);
        try {
            $pdo->prepare('DELETE FROM auth_trusted_devices WHERE token_hash = :th')->execute(['th' => $hash]);
        } catch (\PDOException) {}
        crm_trusted_device_clear_cookie();
    }
}

if (!function_exists('crm_trusted_device_revoke_all')) {
    /** Zruší VŠECHNA trusted device pro uživatele (admin "odhlásit ze všech zařízení"). */
    function crm_trusted_device_revoke_all(PDO $pdo, int $userId): int
    {
        try {
            $stmt = $pdo->prepare('DELETE FROM auth_trusted_devices WHERE user_id = :uid');
            $stmt->execute(['uid' => $userId]);
            return $stmt->rowCount();
        } catch (\PDOException $e) {
            error_log('[CRM Trusted] revoke_all failed: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('crm_trusted_device_list')) {
    /**
     * Seznam aktivních trusted devices uživatele (pro profil / admin).
     * @return list<array<string,mixed>>
     */
    function crm_trusted_device_list(PDO $pdo, int $userId): array
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT id, user_agent, ip_address, expires_at, reverify_at, created_at, last_used_at
                 FROM auth_trusted_devices
                 WHERE user_id = :uid AND expires_at > NOW(3)
                 ORDER BY last_used_at DESC'
            );
            $stmt->execute(['uid' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            error_log('[CRM Trusted] list failed: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('crm_trusted_device_cleanup_expired')) {
    /** Smaže expirovaná trusted devices (volat občas, není kritické pro provoz). */
    function crm_trusted_device_cleanup_expired(PDO $pdo): int
    {
        try {
            $stmt = $pdo->prepare('DELETE FROM auth_trusted_devices WHERE expires_at < NOW(3)');
            $stmt->execute();
            return $stmt->rowCount();
        } catch (\PDOException) {
            return 0;
        }
    }
}

// ════════════════════════════════════════════════════════════════════
//  TOTP setup helpery (generování secret + backup kódů)
// ════════════════════════════════════════════════════════════════════

if (!function_exists('crm_2fa_generate_secret')) {
    /** Vygeneruje 32-znakový Base32 secret pro nový 2FA setup. */
    function crm_2fa_generate_secret(): string
    {
        $alphabet = totp_base32_alphabet(); // 'ABCDEFGHIJKLMNOPQRSTUVW234567' (28 znaků - bez X, Y, Z)
        $alphaLen = strlen($alphabet);
        $secret = '';
        for ($i = 0; $i < 32; $i++) {
            $secret .= $alphabet[random_int(0, $alphaLen - 1)];
        }
        return $secret;
    }
}

if (!function_exists('crm_2fa_generate_backup_codes')) {
    /**
     * Vygeneruje N backup kódů (8 znaků hex každý). Vrací plain-text array.
     * Volající si je musí ihned zobrazit user-ovi a uložit hash do DB přes
     * crm_2fa_save_backup_codes().
     *
     * @return list<string> Plain-text kódy ve formátu "abcd-1234"
     */
    function crm_2fa_generate_backup_codes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $hex = bin2hex(random_bytes(4)); // 8 hex znaků
            $codes[] = substr($hex, 0, 4) . '-' . substr($hex, 4, 4); // "abcd-1234" pro lepší čitelnost
        }
        return $codes;
    }
}

if (!function_exists('crm_2fa_save_backup_codes')) {
    /**
     * Uloží backup kódy do `totp_backup_codes` (jako SHA256 hashe).
     * Nejdřív smaže staré nepoužité kódy (re-issue scenario).
     */
    function crm_2fa_save_backup_codes(PDO $pdo, int $userId, array $plainCodes): void
    {
        try {
            // Zaručit že tabulka má všechny sloupce (legacy DB instance)
            try { $pdo->exec('CREATE TABLE IF NOT EXISTS `totp_backup_codes` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `code_hash` VARCHAR(64) NOT NULL,
                `used_at` DATETIME(3) NULL DEFAULT NULL,
                `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                KEY `idx_user` (`user_id`),
                CONSTRAINT `fk_bc_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'); } catch (\PDOException) {}

            // Smaž staré nepoužité kódy (re-issue scenario — user generuje nové)
            $pdo->prepare('DELETE FROM totp_backup_codes WHERE user_id = :uid AND used_at IS NULL')
                ->execute(['uid' => $userId]);

            $stmt = $pdo->prepare(
                'INSERT INTO totp_backup_codes (user_id, code_hash) VALUES (:uid, :h)'
            );
            foreach ($plainCodes as $code) {
                $hash = hash('sha256', strtolower(trim($code)));
                $stmt->execute(['uid' => $userId, 'h' => $hash]);
            }
        } catch (\PDOException $e) {
            error_log('[CRM 2FA] save_backup_codes failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('crm_2fa_count_unused_backup_codes')) {
    function crm_2fa_count_unused_backup_codes(PDO $pdo, int $userId): int
    {
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM totp_backup_codes WHERE user_id = :uid AND used_at IS NULL');
            $stmt->execute(['uid' => $userId]);
            return (int) $stmt->fetchColumn();
        } catch (\PDOException) {
            return 0;
        }
    }
}
