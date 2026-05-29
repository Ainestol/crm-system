-- e:\Snecinatripu\sql\migrations\024_password_resets.sql
-- ════════════════════════════════════════════════════════════════════
-- PASSWORD RESETS — token-based reset hesla pro „zapomenuté heslo"
--
-- Účel:
--   Uživatel klikne na "Zapomenuté heslo?" na loginu → zadá email →
--   dostane email s odkazem `/password/reset?token=XXX` → zadá nové
--   heslo. Token je jednorázový, max 1 hodina platnost.
--
-- Bezpečnost:
--   - `token_hash` = SHA-256 hash plain tokenu (DB nikdy nezná plain).
--   - `expires_at` = 1 hodina od vytvoření (hard cap).
--   - `used_at` = NULL → nepoužitý; NOT NULL → spotřebovaný (jen 1×).
--   - FK CASCADE na users — pokud uživatel zmizí, token mizí taky.
--
-- Cleanup: stará pravidla expirují automaticky filterem expires_at.
-- Drobné riziko: tabulka roste donekonečna. Doporučení: cron nebo
-- ručně 1× za rok smazat řádky starší 90 dní (jen audit hodnota).
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `token_hash` VARCHAR(255) NOT NULL COMMENT 'SHA-256 hash tokenu z e-mailu',
  `expires_at` DATETIME(3) NOT NULL,
  `used_at`    DATETIME(3) NULL DEFAULT NULL,
  `created_ip` VARCHAR(45) NULL DEFAULT NULL COMMENT 'IP, ze které byl request',
  `user_agent` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `idx_pwreset_user` (`user_id`),
  KEY `idx_pwreset_expires` (`expires_at`),
  KEY `idx_pwreset_token` (`token_hash`),
  CONSTRAINT `fk_pwreset_user` FOREIGN KEY (`user_id`)
    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tokeny pro reset hesla via email (forgot password flow)';

-- Pomocná tabulka pro rate limiting — 5 requestů per IP per hodinu
CREATE TABLE IF NOT EXISTS `password_reset_attempts` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip`         VARCHAR(45) NOT NULL,
  `attempted_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Rate limit pro /password/forgot (max 5 / IP / 1h)';
