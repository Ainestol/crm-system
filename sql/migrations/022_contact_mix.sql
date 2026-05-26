-- e:\Snecinatripu\sql\migrations\022_contact_mix.sql
-- ════════════════════════════════════════════════════════════════════
-- MIX KONTAKTŮ — firma vs OSVČ v poměru 1:10
--
-- Účel:
--   Po importu nových kontaktů admin klikne "Namíchat" a systém prošpekuluje
--   fronty tak, aby navolávačka v sérii dostala 10× OSVČ + 1× firma (cyklicky).
--   Firmy se volají těžko (gatekeeper, IT oddělení atd.) — když by jich byla
--   řada hned za sebou, navolávačka se znechutí. Mix v poměru 1:10 je psychologicky
--   příjemnější + udržuje rytmus.
--
-- Princip:
--   • `subject_type` ENUM('firma','osvc','unknown') — auto-detekce z firma názvu
--     ('s.r.o.', 'a.s.', 'družstvo' atd. → firma, jinak osvc)
--   • `queue_mix_seq` BIGINT — pořadové číslo v namíchané frontě (1, 2, 3, ...).
--     NULL = ještě nezamícháno.
--   • Pool queries (čistička NEW + caller READY) řadí přes queue_mix_seq ASC,
--     takže mix se propaguje celým pipeline.
--   • Nové importy se appendují: MAX(queue_mix_seq) + 1, +2, +3, ...
--
-- Bezpečnost:
--   • Mix akce nemaže ani neruší žádné kontakty
--   • Idempotentní — admin může mix re-spustit kdykoli (nově importované se prostě připojí)
--
-- Spuštění:
--   mysql -u root crm < E:\Snecinatripu\sql\migrations\022_contact_mix.sql
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS `_crm_add_column_safe`;
DELIMITER $$
CREATE PROCEDURE `_crm_add_column_safe`(
    IN p_table VARCHAR(64), IN p_column VARCHAR(64), IN p_def TEXT
)
BEGIN
    DECLARE col_exists INT DEFAULT 0;
    SELECT COUNT(*) INTO col_exists FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column;
    IF col_exists = 0 THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_def);
        PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

DROP PROCEDURE IF EXISTS `_crm_add_index_safe`;
DELIMITER $$
CREATE PROCEDURE `_crm_add_index_safe`(
    IN p_table VARCHAR(64), IN p_index VARCHAR(64), IN p_cols VARCHAR(255)
)
BEGIN
    DECLARE idx_exists INT DEFAULT 0;
    SELECT COUNT(*) INTO idx_exists FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND INDEX_NAME = p_index;
    IF idx_exists = 0 THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD INDEX `', p_index, '` (', p_cols, ')');
        PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- subject_type: typ subjektu (firma / OSVČ / neznámý)
CALL `_crm_add_column_safe`('contacts', 'subject_type',
    "ENUM('firma','osvc','unknown') NOT NULL DEFAULT 'unknown'
     COMMENT 'firma=právnická osoba (s.r.o., a.s., ...), osvc=OSVČ/živnostník, unknown=ještě neurčeno'");

-- queue_mix_seq: pořadové číslo v namíchané frontě (1, 2, 3, ...)
CALL `_crm_add_column_safe`('contacts', 'queue_mix_seq',
    "BIGINT UNSIGNED NULL DEFAULT NULL
     COMMENT 'Pořadové číslo v 1:10 mixu (NULL = ještě nezamícháno)'");

-- Indexy pro rychlé řazení v pool queries + mix detekci
CALL `_crm_add_index_safe`('contacts', 'idx_queue_mix_seq', '`queue_mix_seq`');
CALL `_crm_add_index_safe`('contacts', 'idx_subject_type_stav', '`subject_type`, `stav`');

-- Cleanup
DROP PROCEDURE IF EXISTS `_crm_add_column_safe`;
DROP PROCEDURE IF EXISTS `_crm_add_index_safe`;
