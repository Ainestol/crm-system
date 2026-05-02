-- Migration 003: Měsíční cíle výher + bonusové pásy navolávačky
-- Spustit: mysql -u user -p crm_db < 003_monthly_goals.sql

CREATE TABLE IF NOT EXISTS `monthly_goals` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `target_wins`     INT UNSIGNED NOT NULL DEFAULT 150
    COMMENT 'Měsíční cíl počtu výher',
  `bonus1_at_pct`   TINYINT UNSIGNED NOT NULL DEFAULT 100
    COMMENT '% plnění cíle, při kterém se aktivuje 1. bonus (100 = přesně cíl)',
  `bonus1_pct`      DECIMAL(5,2) NOT NULL DEFAULT 5.00
    COMMENT 'Bonus % za každou výhru nad 1. threshold (marginální, ne retroaktivní)',
  `bonus2_at_pct`   TINYINT UNSIGNED NOT NULL DEFAULT 120
    COMMENT '% plnění cíle pro 2. bonus (120 = cíl + 20 %)',
  `bonus2_pct`      DECIMAL(5,2) NOT NULL DEFAULT 5.00
    COMMENT 'Druhý bonus % navíc (celkem bonus1_pct + bonus2_pct nad 2. threshold)',
  `valid_from`      DATE NOT NULL,
  `valid_to`        DATE NULL DEFAULT NULL
    COMMENT 'NULL = platí nadosmrti',
  `created_at`      DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `idx_monthly_goals_valid` (`valid_from`, `valid_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Měsíční cíle výher a bonusové pásy pro navolávačky';

-- Výchozí záznam: cíl 150 výher, +5 % nad cílem, +5 % nad 120 % cíle
INSERT INTO `monthly_goals` (target_wins, bonus1_at_pct, bonus1_pct, bonus2_at_pct, bonus2_pct, valid_from, valid_to)
VALUES (150, 100, 5.00, 120, 5.00, CURDATE(), NULL);

-- Smazat target_calls z daily_goals (nahrazeno měsíčním systémem)
-- ALTER TABLE `daily_goals` DROP COLUMN IF EXISTS `target_calls`;
-- (zakomentováno – spusťte ručně pokud chcete sloupec odstranit)
