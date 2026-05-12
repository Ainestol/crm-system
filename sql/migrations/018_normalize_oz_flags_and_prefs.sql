-- e:\Snecinatripu\sql\migrations\018_normalize_oz_flags_and_prefs.sql
-- ════════════════════════════════════════════════════════════════════
-- NORMALIZACE SCHÉMATU: contact_oz_flags + oz_tab_prefs
--
-- Stejná logika jako migrace 017, ale pro další dvě tabulky které runtime
-- ALTER spamovaly log:
--   - OzController::ensureFlagsTable()    → contact_oz_flags  (4 sloupce)
--   - OzController::ensureTabPrefsTable() → oz_tab_prefs       (2 sloupce)
--
-- Bezpečnost:
--   - 100% idempotentní (procedure kontroluje information_schema)
--   - Žádný UPDATE / DELETE / DROP dat
--   - Na produkci kde sloupce už jsou → 0 affected rows
--
-- Kompatibilita: MySQL 5.7+, MariaDB 10.2+
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ────────────────────────────────────────────────────────────────────
-- Pomocná procedure (stejná jako v 017 — můžeme bezpečně přetvořit)
-- ────────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS `_crm_add_column_safe`;

DELIMITER $$
CREATE PROCEDURE `_crm_add_column_safe`(
    IN p_table  VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_def    TEXT
)
BEGIN
    DECLARE col_exists INT DEFAULT 0;

    SELECT COUNT(*) INTO col_exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = p_table
      AND COLUMN_NAME  = p_column;

    IF col_exists = 0 THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_def);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- ────────────────────────────────────────────────────────────────────
-- contact_oz_flags — sloupce dosud přidávané přes ensureFlagsTable()
-- ────────────────────────────────────────────────────────────────────
CALL `_crm_add_column_safe`('contact_oz_flags', 'caller_comment',   'TEXT NULL DEFAULT NULL');
CALL `_crm_add_column_safe`('contact_oz_flags', 'caller_confirmed', 'TINYINT(1) NOT NULL DEFAULT 0');
CALL `_crm_add_column_safe`('contact_oz_flags', 'oz_comment',       'TEXT NULL DEFAULT NULL');
CALL `_crm_add_column_safe`('contact_oz_flags', 'oz_confirmed',     'TINYINT(1) NOT NULL DEFAULT 0');

-- ────────────────────────────────────────────────────────────────────
-- oz_tab_prefs — sloupce dosud přidávané přes ensureTabPrefsTable()
-- ────────────────────────────────────────────────────────────────────
CALL `_crm_add_column_safe`('oz_tab_prefs', 'tab_order',     'TEXT NULL DEFAULT NULL');
CALL `_crm_add_column_safe`('oz_tab_prefs', 'sub_tab_order', 'TEXT NULL DEFAULT NULL');

-- ────────────────────────────────────────────────────────────────────
-- Cleanup
-- ────────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS `_crm_add_column_safe`;
