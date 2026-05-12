-- e:\Snecinatripu\sql\migrations\017_normalize_oz_contact_workflow.sql
-- ════════════════════════════════════════════════════════════════════
-- NORMALIZACE SCHÉMATU `oz_contact_workflow`
--
-- Účel:
--   Zafixovat všechny sloupce, které dosud přidával runtime kód:
--     - OzController::ensureWorkflowTable()
--     - BackofficeController::ensureWorkflowMigration()
--   ... do deklarativní migrace. Po této migraci ensure* funkce zůstávají
--   v kódu jako prázdné no-op (zachovaná zpětná kompatibilita pro 14 call
--   sites), ale neudělají nic = konec "Duplicate column" log spamu.
--
-- Bezpečnost:
--   - Migrace je 100% IDEMPOTENTNÍ — používá stored procedure, která kontroluje
--     information_schema.COLUMNS a sloupec přidá JEN POKUD ještě neexistuje.
--   - Žádný UPDATE, žádný DROP, žádný MODIFY. Existující data nedotčená.
--
-- Kompatibilita:
--   - Funguje na MySQL 5.7+, MariaDB 10.2+ (procedural approach, ne nativní
--     `ADD COLUMN IF NOT EXISTS` syntax z MariaDB 10.5+).
--
-- Spuštění lokálně:
--   mysql -u root crm < E:\Snecinatripu\sql\migrations\017_normalize_oz_contact_workflow.sql
--
-- Spuštění na serveru (po git pull):
--   sudo mariadb crm < /var/www/crm/sql/migrations/017_normalize_oz_contact_workflow.sql
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ────────────────────────────────────────────────────────────────────
-- Pomocná stored procedure: přidá sloupec, jen pokud ještě neexistuje.
-- Pracuje proti information_schema → bezpečné napříč MySQL / MariaDB verzemi.
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

-- Stejný princip pro indexy
DROP PROCEDURE IF EXISTS `_crm_add_index_safe`$$

CREATE PROCEDURE `_crm_add_index_safe`(
    IN p_table  VARCHAR(64),
    IN p_index  VARCHAR(64),
    IN p_cols   VARCHAR(255)
)
BEGIN
    DECLARE idx_exists INT DEFAULT 0;

    SELECT COUNT(*) INTO idx_exists
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = p_table
      AND INDEX_NAME   = p_index;

    IF idx_exists = 0 THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD INDEX `', p_index, '` (', p_cols, ')');
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- ────────────────────────────────────────────────────────────────────
-- Sloupce dosud přidávané runtime přes OzController::ensureWorkflowTable
-- ────────────────────────────────────────────────────────────────────
CALL `_crm_add_column_safe`('oz_contact_workflow', 'schuzka_at',           'DATETIME(3) NULL DEFAULT NULL');
CALL `_crm_add_column_safe`('oz_contact_workflow', 'schuzka_acknowledged', 'TINYINT(1) NOT NULL DEFAULT 0');
CALL `_crm_add_column_safe`('oz_contact_workflow', 'bmsl',                 'DECIMAL(10,2) NULL DEFAULT NULL');
CALL `_crm_add_column_safe`('oz_contact_workflow', 'smlouva_date',         'DATE NULL DEFAULT NULL');
CALL `_crm_add_column_safe`('oz_contact_workflow', 'nabidka_id',           'VARCHAR(50) NULL DEFAULT NULL');
CALL `_crm_add_column_safe`('oz_contact_workflow', 'install_internet',     'TINYINT(1) NOT NULL DEFAULT 0');
CALL `_crm_add_column_safe`('oz_contact_workflow', 'install_ulice',        'VARCHAR(200) NULL DEFAULT NULL');
CALL `_crm_add_column_safe`('oz_contact_workflow', 'install_mesto',        'VARCHAR(100) NULL DEFAULT NULL');
CALL `_crm_add_column_safe`('oz_contact_workflow', 'install_psc',          'VARCHAR(10) NULL DEFAULT NULL');
CALL `_crm_add_column_safe`('oz_contact_workflow', 'install_byt',          'VARCHAR(50) NULL DEFAULT NULL');
CALL `_crm_add_column_safe`('oz_contact_workflow', 'install_adresy',       'TEXT NULL DEFAULT NULL');
CALL `_crm_add_column_safe`('oz_contact_workflow', 'closed_at',            'DATETIME(3) NULL DEFAULT NULL');
CALL `_crm_add_column_safe`('oz_contact_workflow', 'stav_changed_at',      'DATETIME(3) NULL DEFAULT NULL');
CALL `_crm_add_column_safe`('oz_contact_workflow', 'priprava_smlouvy',     'TINYINT(1) NOT NULL DEFAULT 0');
CALL `_crm_add_column_safe`('oz_contact_workflow', 'datovka_odeslana',     'TINYINT(1) NOT NULL DEFAULT 0');
CALL `_crm_add_column_safe`('oz_contact_workflow', 'podpis_potvrzen',      'TINYINT(1) NOT NULL DEFAULT 0');
CALL `_crm_add_column_safe`('oz_contact_workflow', 'podpis_potvrzen_at',   'DATETIME(3) NULL DEFAULT NULL');
CALL `_crm_add_column_safe`('oz_contact_workflow', 'podpis_potvrzen_by',   'INT UNSIGNED NULL DEFAULT NULL');
CALL `_crm_add_column_safe`('oz_contact_workflow', 'ubotem_zpracovano',    'TINYINT(1) NOT NULL DEFAULT 0');

-- ────────────────────────────────────────────────────────────────────
-- Sloupce dosud přidávané runtime přes BackofficeController::ensureWorkflowMigration
-- ────────────────────────────────────────────────────────────────────
CALL `_crm_add_column_safe`('oz_contact_workflow', 'cislo_smlouvy',        'VARCHAR(50) NULL DEFAULT NULL');
CALL `_crm_add_column_safe`('oz_contact_workflow', 'datum_uzavreni',       'DATE NULL DEFAULT NULL');
CALL `_crm_add_column_safe`('oz_contact_workflow', 'smlouva_trvani_roky',  'TINYINT UNSIGNED NULL DEFAULT 3');

CALL `_crm_add_index_safe` ('oz_contact_workflow', 'idx_cislo_smlouvy',    '`cislo_smlouvy`');

-- ────────────────────────────────────────────────────────────────────
-- Cleanup: pomocné procedury uklidíme, ať neznečišťují DB
-- ────────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS `_crm_add_column_safe`;
DROP PROCEDURE IF EXISTS `_crm_add_index_safe`;
