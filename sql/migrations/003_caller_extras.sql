-- e:\Snecinatripu\sql\migrations\003_caller_extras.sql
-- Přidání sloupců operator a prilez do tabulky contacts
-- Spustit jednou: mysql -u USER -p DATABASE < 003_caller_extras.sql

ALTER TABLE `contacts`
  ADD COLUMN `operator`    VARCHAR(100)  NULL DEFAULT NULL COMMENT 'Telecom operátor zákazníka (O2, T-Mobile, Vodafone, …)' AFTER `email`,
  ADD COLUMN `prilez`      VARCHAR(255)  NULL DEFAULT NULL COMMENT 'Obchodní příležitost (volný popis)' AFTER `operator`;
