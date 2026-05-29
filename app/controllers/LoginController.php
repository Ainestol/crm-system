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

        // ── Trusted device cookie auto-login ────────────────────────────
        // Pokud má user platnou cookie z předchozího "důvěryhodného zařízení",
        // přeskočíme heslo. Pokud je vyžadován reverify (>7 dní od poslední 2FA),
        // pošleme rovnou na /login/two-factor (pouze 2FA, žádné heslo).
        $trusted = crm_trusted_device_validate($this->pdo);
        if ($trusted['ok'] === true && isset($trusted['user_id'])) {
            $userId = (int) $trusted['user_id'];
            // Načti usera ať ověříme že je aktivní
            $user = crm_auth_user_by_id($this->pdo, $userId);
            if ($user !== null && (int) ($user['aktivni'] ?? 0) === 1) {
                if (!empty($trusted['reverify_needed'])) {
                    // 2FA reverify potřebný — pošli na /login/two-factor jen s 2FA
                    crm_auth_start_two_factor($userId);
                    crm_redirect('/login/two-factor');
                }
                // Plný auto-login bez hesla i 2FA
                crm_auth_finish_login($this->pdo, $userId);
                $this->postLoginRoleHandling($user);
            }
            // User neaktivní / smazán → cookie zruš
            crm_trusted_device_revoke($this->pdo);
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
        // Login OK — pro multi-role usery zkontroluj cookie nebo redirect na select-role
        $this->postLoginRoleHandling((array) ($result['user'] ?? []));
    }

    /** Po úspěšném loginu: pokud je multi-role + nemá cookie preferred → select-role.
     *  Jinak rovnou dashboard. */
    private function postLoginRoleHandling(array $user): void
    {
        $allRoles = crm_user_all_roles($user);
        if (count($allRoles) <= 1) {
            // Single-role — žádný výběr
            crm_redirect('/dashboard');
        }
        // Multi-role — zkus cookie
        $cookieRole = (string) ($_COOKIE[CRM_PREFERRED_ROLE_COOKIE] ?? '');
        if ($cookieRole !== '' && in_array($cookieRole, $allRoles, true)) {
            crm_user_set_active_role($user, $cookieRole);
            crm_redirect('/dashboard');
        }
        // Žádná validní cookie → výběr role
        crm_redirect('/login/select-role');
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
        $remember = (int) ($_POST['remember_device'] ?? 0) === 1;

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
        // 2FA OK
        $user = (array) ($result['user'] ?? []);
        $userId = (int) ($user['id'] ?? 0);

        // ── Trusted device handling ──────────────────────────────────────
        // 1) Pokud měl user už trusted cookie (= reverify scenario), prodluž ji o 7 dní
        // 2) Pokud chce vystavit novou (zaškrtl "Důvěřovat zařízení 30 dní"), vystav
        if (crm_trusted_device_get_cookie_token() !== null) {
            // Existující cookie — uživatel právě zreverifikoval 2FA → prodlouž
            crm_trusted_device_mark_reverified($this->pdo);
        } elseif ($remember && $userId > 0) {
            crm_trusted_device_issue($this->pdo, $userId);
        }

        // multi-role flow stejně jako u password
        $this->postLoginRoleHandling($user);
    }

    // ════════════════════════════════════════════════════════════════
    //  GET /login/select-role — multi-role výběr role po loginu
    // ════════════════════════════════════════════════════════════════
    public function getSelectRole(): void
    {
        $user = crm_auth_current_user($this->pdo);
        if ($user === null) {
            crm_redirect('/login');
        }
        $allRoles = crm_user_all_roles($user);
        if (count($allRoles) <= 1) {
            crm_redirect('/dashboard');
        }

        $title = 'Vyberte roli';
        $csrf  = crm_csrf_token();
        $flash = crm_flash_take();
        // Aktuálně preferred role (z cookie nebo session)
        $preferred = (string) ($_COOKIE[CRM_PREFERRED_ROLE_COOKIE] ?? $user['role']);

        ob_start();
        require dirname(__DIR__) . '/views/login/select_role.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    // ════════════════════════════════════════════════════════════════
    //  POST /login/select-role — uloží volbu, případně cookie pro příště
    // ════════════════════════════════════════════════════════════════
    public function postSelectRole(): void
    {
        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/login/select-role');
        }
        $user = crm_auth_current_user($this->pdo);
        if ($user === null) {
            crm_redirect('/login');
        }
        $role     = (string) ($_POST['role'] ?? '');
        $remember = (int) ($_POST['remember'] ?? 0) === 1;

        if (!crm_user_set_active_role($user, $role)) {
            crm_flash_set('⚠ Tuto roli nemáte povolenou.');
            crm_redirect('/login/select-role');
        }

        // Cookie: 1 rok pokud remember=1, jinak smazat
        if ($remember) {
            setcookie(
                CRM_PREFERRED_ROLE_COOKIE, $role,
                [
                    'expires' => time() + 31536000, // 1 rok
                    'path'    => '/',
                    'samesite'=> 'Lax',
                    'httponly'=> true,
                ]
            );
        } else {
            // Pokud user odznačí remember, smažeme předchozí cookie
            setcookie(CRM_PREFERRED_ROLE_COOKIE, '', [
                'expires' => time() - 3600, 'path' => '/', 'samesite' => 'Lax', 'httponly' => true,
            ]);
        }

        crm_redirect('/dashboard');
    }

    // ════════════════════════════════════════════════════════════════
    //  FORGOT / RESET PASSWORD FLOW
    //  Bezpečnostní pravidla:
    //   - V DB ukládáme jen SHA-256 hash tokenu (plain je jen v emailu)
    //   - TTL 1 hodina, jednorázový (used_at NOT NULL = spotřebovaný)
    //   - Rate limit 5 requestů per IP per hodinu
    //   - Vždy stejná hláška "Pokud účet existuje, odkaz byl odeslán"
    //     (ochrana před user enumeration)
    //   - 2FA zůstává nedotčená — reset hesla NEobchází 2FA při dalším loginu
    // ════════════════════════════════════════════════════════════════

    /** GET /password/forgot — formulář pro zadání emailu */
    public function getForgotPassword(): void
    {
        if (crm_auth_user_id() !== null) {
            crm_redirect('/dashboard'); // už přihlášený
        }
        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();
        $title = 'Zapomenuté heslo';
        ob_start();
        require dirname(__DIR__) . '/views/login/forgot.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    /** POST /password/forgot — vygeneruje token a pošle email */
    public function postForgotPassword(): void
    {
        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/password/forgot');
        }

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $ip    = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $ua    = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

        // ── Rate limit: max 5 requestů z jedné IP za posledních 60 min ──
        if ($ip !== '') {
            try {
                $rlStmt = $this->pdo->prepare(
                    "SELECT COUNT(*) FROM password_reset_attempts
                     WHERE ip = ? AND attempted_at >= NOW() - INTERVAL 1 HOUR"
                );
                $rlStmt->execute([$ip]);
                if ((int) $rlStmt->fetchColumn() >= 5) {
                    crm_flash_set('⏰ Příliš mnoho pokusů z této IP. Zkuste to za hodinu.');
                    crm_redirect('/password/forgot');
                }
                // Zaznamenat tento pokus
                $this->pdo->prepare(
                    "INSERT INTO password_reset_attempts (ip) VALUES (?)"
                )->execute([$ip]);
                // Cleanup starých záznamů (lazy, jednou za request)
                $this->pdo->exec(
                    "DELETE FROM password_reset_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY"
                );
            } catch (\Throwable $e) {
                if (function_exists('crm_db_log_error')) crm_db_log_error($e, __METHOD__);
            }
        }

        // ── Generic message bez ohledu na výsledek (user enumeration ochrana) ──
        $genericMsg = '✓ Pokud účet s tímto emailem existuje, poslali jsme na něj odkaz pro reset hesla. Zkontrolujte schránku (i SPAM).';

        // Najdi aktivního uživatele
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            crm_flash_set($genericMsg);
            crm_redirect('/login');
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, jmeno, email FROM users WHERE LOWER(email) = ? AND aktivni = 1 LIMIT 1"
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $_) {
            $user = false;
        }

        if (!$user) {
            // Neexistující email — pošli stejnou hlášku
            crm_flash_set($genericMsg);
            crm_redirect('/login');
        }

        // Vygeneruj plain token (jen v emailu) + SHA-256 hash (do DB)
        try {
            $plainToken = bin2hex(random_bytes(32)); // 64 hex znaků
        } catch (\Throwable $_) {
            crm_flash_set('⚠ Chyba při generování tokenu. Zkuste to znovu.');
            crm_redirect('/password/forgot');
        }
        $tokenHash  = hash('sha256', $plainToken);

        try {
            $this->pdo->prepare(
                "INSERT INTO password_resets
                 (user_id, token_hash, expires_at, created_ip, user_agent)
                 VALUES (?, ?, NOW(3) + INTERVAL 1 HOUR, ?, ?)"
            )->execute([(int) $user['id'], $tokenHash, $ip, $ua]);
        } catch (\Throwable $e) {
            if (function_exists('crm_db_log_error')) crm_db_log_error($e, __METHOD__);
            crm_flash_set('⚠ Chyba při ukládání tokenu. Zkuste to znovu.');
            crm_redirect('/password/forgot');
        }

        // Pošli email s plain tokenem
        $sent = false;
        try {
            if (function_exists('crm_mail_password_reset_link')) {
                $sent = crm_mail_password_reset_link(
                    (string) $user['email'],
                    (string) ($user['jmeno'] ?? ''),
                    $plainToken
                );
            }
        } catch (\Throwable $e) {
            if (function_exists('crm_db_log_error')) crm_db_log_error($e, __METHOD__);
        }

        if (!$sent) {
            // Mailer selhal — log + stejná generická hláška (nedáváme info útočníkovi)
            error_log('[password/forgot] mailer failed for user_id=' . (int) $user['id']);
        }

        crm_flash_set($genericMsg);
        crm_redirect('/login');
    }

    /** GET /password/reset?token=XXX — formulář pro nové heslo */
    public function getResetPassword(): void
    {
        if (crm_auth_user_id() !== null) {
            crm_redirect('/dashboard');
        }

        $plainToken = (string) ($_GET['token'] ?? '');
        $tokenHash  = $plainToken !== '' ? hash('sha256', $plainToken) : '';

        $validToken = false;
        if ($tokenHash !== '') {
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT pr.id FROM password_resets pr
                     INNER JOIN users u ON u.id = pr.user_id AND u.aktivni = 1
                     WHERE pr.token_hash = ?
                       AND pr.used_at IS NULL
                       AND pr.expires_at >= NOW(3)
                     LIMIT 1"
                );
                $stmt->execute([$tokenHash]);
                $validToken = $stmt->fetch(PDO::FETCH_ASSOC) !== false;
            } catch (\Throwable $_) {}
        }

        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();
        $title = 'Nové heslo';
        ob_start();
        require dirname(__DIR__) . '/views/login/reset.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    /** POST /password/reset — uloží nové heslo */
    public function postResetPassword(): void
    {
        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/login');
        }

        $plainToken = (string) ($_POST['token'] ?? '');
        $pw1        = (string) ($_POST['password'] ?? '');
        $pw2        = (string) ($_POST['password_confirm'] ?? '');
        $tokenHash  = $plainToken !== '' ? hash('sha256', $plainToken) : '';

        if ($tokenHash === '') {
            crm_flash_set('⚠ Chybí token. Použijte odkaz z emailu.');
            crm_redirect('/login');
        }
        if ($pw1 === '' || $pw1 !== $pw2) {
            crm_flash_set('⚠ Hesla se neshodují, nebo jsou prázdná.');
            crm_redirect('/password/reset?token=' . urlencode($plainToken));
        }
        if (mb_strlen($pw1) < 8) {
            crm_flash_set('⚠ Heslo musí mít minimálně 8 znaků.');
            crm_redirect('/password/reset?token=' . urlencode($plainToken));
        }

        // Najdi platný token + user
        try {
            $stmt = $this->pdo->prepare(
                "SELECT pr.id AS pr_id, pr.user_id, u.email, u.jmeno
                 FROM password_resets pr
                 INNER JOIN users u ON u.id = pr.user_id AND u.aktivni = 1
                 WHERE pr.token_hash = ?
                   AND pr.used_at IS NULL
                   AND pr.expires_at >= NOW(3)
                 LIMIT 1"
            );
            $stmt->execute([$tokenHash]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $_) {
            $row = false;
        }

        if (!$row) {
            crm_flash_set('⚠ Token je neplatný, vypršel nebo byl už použit. Vyžádejte si nový odkaz.');
            crm_redirect('/password/forgot');
        }

        $userId = (int) $row['user_id'];
        $prId   = (int) $row['pr_id'];
        // Použijeme stejný hashing helper jako AccountController (= konzistence
        // s Argon2id/PASSWORD_DEFAULT podle konfigurace)
        $hash = function_exists('crm_auth_password_hash_new')
            ? crm_auth_password_hash_new($pw1)
            : password_hash($pw1, PASSWORD_DEFAULT);

        $this->pdo->beginTransaction();
        try {
            // 1) Aktualizuj heslo + zrušíme must_change_password (= user si ho právě nastavil)
            //    Sloupec se jmenuje `heslo_hash` (české), users nemá `updated_at`.
            $this->pdo->prepare(
                "UPDATE users SET heslo_hash = ?, must_change_password = 0 WHERE id = ?"
            )->execute([$hash, $userId]);

            // 2) Označ token jako použitý
            $this->pdo->prepare(
                "UPDATE password_resets SET used_at = NOW(3) WHERE id = ?"
            )->execute([$prId]);

            // 3) Invaliduj VŠECHNY ostatní pending tokeny tohoto uživatele
            //    (pokud si jich vygeneroval víc, ostatní teď nemají smysl)
            $this->pdo->prepare(
                "UPDATE password_resets SET used_at = NOW(3)
                 WHERE user_id = ? AND used_at IS NULL AND id <> ?"
            )->execute([$userId, $prId]);

            // 4) Audit log (kdo, kdy, jak)
            if (function_exists('crm_audit_log')) {
                try {
                    crm_audit_log($this->pdo, $userId, 'password.reset_via_email', 'user', $userId, [
                        'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                    ]);
                } catch (\Throwable $_) {}
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if (function_exists('crm_db_log_error')) crm_db_log_error($e, __METHOD__);
            crm_flash_set('⚠ Chyba při ukládání hesla. Zkuste to znovu.');
            crm_redirect('/password/forgot');
        }

        // 5) Invaliduj všechny session toho uživatele (pokud existuje pomocná funkce)
        //    Bez tohoto by útočník se starou session zůstal přihlášený.
        if (function_exists('crm_auth_invalidate_user_sessions')) {
            try { crm_auth_invalidate_user_sessions($this->pdo, $userId); } catch (\Throwable $_) {}
        }

        crm_flash_set('✓ Heslo bylo úspěšně změněno. Můžete se přihlásit. Pokud máte zapnuté 2FA, budete stále potřebovat ověřovací kód.');
        crm_redirect('/login');
    }
}
