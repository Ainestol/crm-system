<?php
// e:\Snecinatripu\app\controllers\ProfileController.php
declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'totp.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'audit.php';

/**
 * Profil uživatele — primárně 2FA setup / disable.
 *
 * Routes:
 *   GET  /profile/2fa/setup           — wizard krok 1 (QR + první kód)
 *   POST /profile/2fa/setup           — ověří první kód, aktivuje 2FA
 *   GET  /profile/2fa/done            — backup kódy po aktivaci (jen jednou)
 *   GET  /profile/2fa/disable         — formulář pro vypnutí (heslo + 2FA kód)
 *   POST /profile/2fa/disable         — provede vypnutí
 *   GET  /profile/2fa/backup-codes    — re-generate backup kódy (vyžaduje 2FA)
 *   POST /profile/2fa/backup-codes    — provede re-generate
 *   POST /profile/2fa/revoke-device   — odhlásit konkrétní trusted device
 *   POST /profile/2fa/revoke-all      — odhlásit ze všech zařízení
 */
final class ProfileController
{
    /** Issuer name pro otpauth URI (zobrazí se v Google Authenticator). */
    private const TOTP_ISSUER = 'Šneci na tripu CRM';

    public function __construct(private PDO $pdo) {}

    // ─────────────────────────────────────────────────────────────────
    //  GET /profile/2fa/setup — wizard krok 1
    // ─────────────────────────────────────────────────────────────────
    public function getSetup(): void
    {
        $actor = crm_require_user($this->pdo);

        // Pokud už je 2FA aktivní, redirect na status (ne re-setup)
        if ((int) ($actor['totp_enabled'] ?? 0) === 1) {
            crm_flash_set('2FA už je aktivní. Pro vypnutí použijte tlačítko Vypnout.');
            crm_redirect('/profile/2fa/disable');
        }

        // Vygeneruj nový secret a ulož do session (zatím se nezapíše do DB)
        crm_session_start();
        if (empty($_SESSION['crm_2fa_setup_secret'])) {
            $_SESSION['crm_2fa_setup_secret'] = crm_2fa_generate_secret();
        }
        $secret = (string) $_SESSION['crm_2fa_setup_secret'];

        $email   = (string) ($actor['email'] ?? '');
        $otpUri  = totp_provisioning_uri(self::TOTP_ISSUER, $email, $secret);
        $flash   = crm_flash_take();
        $csrf    = crm_csrf_token();
        $title   = '🔐 Aktivace dvoufaktorového ověření';

        ob_start();
        require dirname(__DIR__) . '/views/profile/2fa_setup.php';
        $content = (string) ob_get_clean();
        $user = $actor;
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    // ─────────────────────────────────────────────────────────────────
    //  POST /profile/2fa/setup — ověření prvního kódu, aktivace
    // ─────────────────────────────────────────────────────────────────
    public function postSetup(): void
    {
        $actor = crm_require_user($this->pdo);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/profile/2fa/setup');
        }

        if ((int) ($actor['totp_enabled'] ?? 0) === 1) {
            crm_flash_set('2FA už je aktivní.');
            crm_redirect('/dashboard');
        }

        crm_session_start();
        $secret = (string) ($_SESSION['crm_2fa_setup_secret'] ?? '');
        if ($secret === '') {
            crm_flash_set('Setup vypršel. Začněte znovu.');
            crm_redirect('/profile/2fa/setup');
        }

        $code = preg_replace('/\s+/', '', (string) ($_POST['code'] ?? '')) ?? '';
        if (!totp_verify($secret, $code, 1)) {
            crm_flash_set('❌ Nesprávný ověřovací kód. Zkuste znovu (kód platí 30 sekund).');
            crm_redirect('/profile/2fa/setup');
        }

        // Kód OK → ulož secret + aktivuj 2FA + vygeneruj backup kódy
        $userId = (int) $actor['id'];
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE users SET totp_secret = :s, totp_enabled = 1 WHERE id = :id'
            );
            $stmt->execute(['s' => $secret, 'id' => $userId]);
        } catch (\PDOException $e) {
            error_log('[Profile 2FA] enable failed: ' . $e->getMessage());
            crm_flash_set('❌ Aktivace selhala. Zkuste znovu nebo kontaktujte admina.');
            crm_redirect('/profile/2fa/setup');
        }

        $backupCodes = crm_2fa_generate_backup_codes(8);
        crm_2fa_save_backup_codes($this->pdo, $userId, $backupCodes);

        // Audit log
        try {
            crm_audit_log($this->pdo, $userId, 'user.2fa_enabled', 'users', $userId, [
                'method' => 'totp',
                'backup_codes_count' => count($backupCodes),
            ], 'web');
        } catch (\PDOException) {}

        // Předat backup kódy do done view (přes session, aby se neztratily při refresh)
        $_SESSION['crm_2fa_done_codes'] = $backupCodes;
        unset($_SESSION['crm_2fa_setup_secret']);

        crm_redirect('/profile/2fa/done');
    }

    // ─────────────────────────────────────────────────────────────────
    //  GET /profile/2fa/done — zobrazení backup kódů
    // ─────────────────────────────────────────────────────────────────
    public function getDone(): void
    {
        $actor = crm_require_user($this->pdo);
        crm_session_start();

        $codes = (array) ($_SESSION['crm_2fa_done_codes'] ?? []);
        if ($codes === []) {
            // Backup kódy jsou v session jen jednou — pokud refresh, redirect
            crm_flash_set('Backup kódy už nejsou k dispozici. Re-generate v profilu.');
            crm_redirect('/dashboard');
        }

        $flash = crm_flash_take();
        $title = '✅ 2FA aktivováno — uložte si tyto kódy';

        ob_start();
        require dirname(__DIR__) . '/views/profile/2fa_done.php';
        $content = (string) ob_get_clean();

        // Smaž z session až po renderu (aby refresh nezopakoval, ale render projde)
        unset($_SESSION['crm_2fa_done_codes']);

        $user = $actor;
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    // ─────────────────────────────────────────────────────────────────
    //  GET /profile/2fa/disable — formulář pro vypnutí
    // ─────────────────────────────────────────────────────────────────
    public function getDisable(): void
    {
        $actor = crm_require_user($this->pdo);

        if ((int) ($actor['totp_enabled'] ?? 0) !== 1) {
            crm_flash_set('2FA není aktivní — není co vypínat.');
            crm_redirect('/profile/2fa/setup');
        }

        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();
        $title = '🔓 Vypnout dvoufaktorové ověření';

        // Seznam aktivních trusted devices pro info
        $devices = crm_trusted_device_list($this->pdo, (int) $actor['id']);
        $unusedBackupCount = crm_2fa_count_unused_backup_codes($this->pdo, (int) $actor['id']);

        ob_start();
        require dirname(__DIR__) . '/views/profile/2fa_disable.php';
        $content = (string) ob_get_clean();
        $user = $actor;
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    // ─────────────────────────────────────────────────────────────────
    //  POST /profile/2fa/disable — ověř heslo + 2FA kód, vypni
    // ─────────────────────────────────────────────────────────────────
    public function postDisable(): void
    {
        $actor = crm_require_user($this->pdo);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/profile/2fa/disable');
        }

        $userId = (int) $actor['id'];
        $password = (string) ($_POST['password'] ?? '');
        $code     = preg_replace('/\s+/', '', (string) ($_POST['code'] ?? '')) ?? '';

        // Verify heslo
        $hash = (string) ($actor['heslo_hash'] ?? '');
        if ($password === '' || !crm_auth_password_verify($password, $hash)) {
            crm_flash_set('❌ Špatné heslo.');
            crm_redirect('/profile/2fa/disable');
        }

        // Verify 2FA kód (přes secret z DB)
        $secret = crm_auth_totp_secret_string($actor['totp_secret'] ?? '');
        if ($code === '' || $secret === '' || !totp_verify($secret, $code, 1)) {
            // Zkus ještě backup kód
            if (!crm_auth_verify_backup_code($this->pdo, $userId, $code)) {
                crm_flash_set('❌ Špatný 2FA kód (ani jako backup kód).');
                crm_redirect('/profile/2fa/disable');
            }
        }

        // Vše OK → vypni 2FA + zruš všechna trusted devices + smaž backup kódy
        try {
            $this->pdo->prepare('UPDATE users SET totp_enabled = 0, totp_secret = NULL WHERE id = :id')
                ->execute(['id' => $userId]);
            $this->pdo->prepare('DELETE FROM totp_backup_codes WHERE user_id = :id')
                ->execute(['id' => $userId]);
            crm_trusted_device_revoke_all($this->pdo, $userId);
        } catch (\PDOException $e) {
            error_log('[Profile 2FA] disable failed: ' . $e->getMessage());
            crm_flash_set('❌ Vypnutí selhalo, zkuste znovu.');
            crm_redirect('/profile/2fa/disable');
        }

        try {
            crm_audit_log($this->pdo, $userId, 'user.2fa_disabled', 'users', $userId, [
                'method' => 'self_disable',
            ], 'web');
        } catch (\PDOException) {}

        crm_flash_set('✓ 2FA vypnuto. Pokud je to dočasné, doporučujeme znovu aktivovat.');
        crm_redirect('/dashboard');
    }

    // ─────────────────────────────────────────────────────────────────
    //  POST /profile/2fa/revoke-all — odhlásit ze všech zařízení
    // ─────────────────────────────────────────────────────────────────
    public function postRevokeAll(): void
    {
        $actor = crm_require_user($this->pdo);
        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/profile/2fa/disable');
        }

        $count = crm_trusted_device_revoke_all($this->pdo, (int) $actor['id']);

        try {
            crm_audit_log($this->pdo, (int) $actor['id'], 'user.trusted_devices_revoked_all', 'users',
                (int) $actor['id'], ['count' => $count], 'web');
        } catch (\PDOException) {}

        crm_flash_set(sprintf('✓ Odhlášeno z %d důvěryhodných zařízení.', $count));
        crm_redirect('/profile/2fa/disable');
    }
}
