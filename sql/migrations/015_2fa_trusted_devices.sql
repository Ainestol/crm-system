-- e:\Snecinatripu\sql\migrations\015_2fa_trusted_devices.sql
-- ════════════════════════════════════════════════════════════════════
-- Tabulka pro "důvěryhodná zařízení" (trusted device cookies).
--
-- Princip:
--   1. Po úspěšném loginu (heslo + 2FA) si user zaškrtne "Důvěřovat
--      tomuto zařízení 30 dní" → vygeneruje se token, hash uloží do DB,
--      plain-text se pošle do cookie `crm_trusted_device`.
--   2. Při dalším návratu (do 30 dní) → cookie token se hashe a porovná
--      s DB → pokud match, automatický login bez hesla i 2FA.
--   3. Po 7 dnech od posledního ověření 2FA → systém vyžaduje JEN 2FA
--      kód znovu (ne heslo). To jsou ty "občasné kontroly" pro jistotu.
--   4. Po 30 dnech od vytvoření cookie expiruje → musí plný login znovu.
--   5. Logout = okamžitě DELETE z DB (cookie taky smažeme).
--
-- Bezpečnost:
--   - Token v DB je SHA256 hash, ne plain-text. Krádež DB ≠ login na cizí účet.
--   - User-agent + IP uchováváme pro audit (admin uvidí "kdo se kde přihlásil").
--   - Při 2FA reverify selhání → DELETE token (= force re-login).
--
-- Žádné FK do contacts, jen do users (CASCADE — když smažu uživatele,
-- automaticky se invalidují všechna jeho zařízení).
-- ════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `auth_trusted_devices` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       BIGINT UNSIGNED NOT NULL,
  `token_hash`    VARCHAR(64)  NOT NULL COMMENT 'SHA256 hash plain-text tokenu z cookie',
  `user_agent`    VARCHAR(500) NULL DEFAULT NULL COMMENT 'Browser identifikace pro audit',
  `ip_address`    VARCHAR(45)  NULL DEFAULT NULL COMMENT 'IPv4 nebo IPv6 odkud byl token vystaven',
  `expires_at`    DATETIME(3)  NOT NULL COMMENT 'Po této době cookie zcela expiruje (default +30 dní)',
  `reverify_at`   DATETIME(3)  NOT NULL COMMENT 'Po této době vyžadovat 2FA znovu (default +7 dní)',
  `created_at`    DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `last_used_at`  DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3) COMMENT 'Aktualizuje se každým auto-loginem',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_token`     (`token_hash`),
  KEY        `idx_user`       (`user_id`),
  KEY        `idx_expires`    (`expires_at`),
  CONSTRAINT `fk_trusted_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Důvěryhodná zařízení — auto-login cookie tokeny pro účty s 2FA.';
