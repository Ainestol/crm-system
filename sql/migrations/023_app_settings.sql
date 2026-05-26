-- e:\Snecinatripu\sql\migrations\023_app_settings.sql
-- ════════════════════════════════════════════════════════════════════
-- APP SETTINGS — generic key-value config tabulka
--
-- Účel:
--   Místo hardcoded konstant v PHP nebo .env mít admin-editovatelné
--   nastavení v DB. Aktuální use case: mix poměr (firma:OSVČ).
--
-- Schéma:
--   skey  VARCHAR(100) PK  — klíč (např. 'mix_ratio_firma')
--   sval  TEXT             — hodnota (string, parsuje se v helperu)
--   updated_at, updated_by — kdo a kdy poslední změnil
--
-- Použití (PHP):
--   $val = crm_setting_get('mix_ratio_firma', 1);
--   crm_setting_set('mix_ratio_firma', 1, $adminId);
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `app_settings` (
  `skey`       VARCHAR(100) NOT NULL,
  `sval`       TEXT NULL,
  `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  `updated_by` BIGINT UNSIGNED NULL,
  PRIMARY KEY (`skey`),
  KEY `idx_updated_at` (`updated_at`),
  CONSTRAINT `fk_settings_updated_by`
    FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Generic key-value app settings (admin-editable)';

-- Default values pro mix
INSERT IGNORE INTO `app_settings` (`skey`, `sval`) VALUES
  ('mix_ratio_firma', '1'),
  ('mix_ratio_osvc',  '9'),
  ('mix_auto_after_import', '1');
