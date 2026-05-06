-- e:\Snecinatripu\sql\migrations\008_cisticka_rewards.sql
-- ════════════════════════════════════════════════════════════════════
-- Tabulka konfigurace odměny čističky za jedno ověření.
--
-- Princip stejný jako `caller_rewards_config`:
--   • Jedna sazba platí pro VŠECHNY ověření (READY i VF_SKIP).
--   • Časově omezená — `valid_from` / `valid_to` (NULL = "platí dál").
--   • Při změně sazby se starý záznam uzavře (valid_to = včera) a založí
--     nový (valid_from = dnes) — historie se zachová pro audit.
--
-- amount_czk je DECIMAL(8,4) místo (12,2), protože sazby pod 1 Kč
-- (např. 0.7000) potřebují víc desetinných míst.
-- ════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `cisticka_rewards_config` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `amount_czk` DECIMAL(8,4) NOT NULL COMMENT 'Fixní odměna za jedno ověření (READY nebo VF_SKIP)',
  `valid_from` DATE NOT NULL,
  `valid_to`   DATE NULL DEFAULT NULL COMMENT 'NULL = platí dál',
  PRIMARY KEY (`id`),
  KEY `idx_cisticka_rewards_valid` (`valid_from`, `valid_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Konfigurace fixní odměny čističky za jedno ověření kontaktu';

-- Seed: výchozí sazba 0,70 Kč za ověření, platí od dneška.
INSERT INTO `cisticka_rewards_config` (`amount_czk`, `valid_from`, `valid_to`)
VALUES (0.7000, CURDATE(), NULL);
