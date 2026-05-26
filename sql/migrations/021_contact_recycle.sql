-- e:\Snecinatripu\sql\migrations\021_contact_recycle.sql
-- ════════════════════════════════════════════════════════════════════
-- RECYKLACE KONTAKTŮ (lead recycling)
--
-- Účel:
--   Admin/majitel vrací starší kontakty zpátky do oběhu:
--     • VF_SKIP   — Vodafone klienti, kterým se po 2-5 letech mohlo
--                    změnit operátora → vrátit do čističky na re-check
--     • NEZAJEM   — někdo odmítl před rokem, situace se mohla změnit
--     • NEDOVOLANO — 3× nedovoláno, po čase zkusit znovu
--
-- Princip:
--   • Kontakt si zachová PŮVODNÍ id (= veškerá historie zůstává)
--   • Recyklace nastaví contacts.stav = 'NEW' nebo 'READY' (admin volí)
--   • last_recycled_at = NOW → pool queries to použijí pro řazení
--     (COALESCE(last_recycled_at, created_at) → recyklované na konec fronty)
--   • Audit v contact_recycles + workflow_log
--
-- Bezpečnost:
--   • Nikdy nelze recyklovat IZOLACE (DNC flag — GDPR risk)
--   • Cool-down: minimálně 7 dní od posledního workflow_log záznamu
--   • Recycle_count chrání proti nekonečnému kolování
--
-- Spuštění:
--   mysql -u root crm < E:\Snecinatripu\sql\migrations\021_contact_recycle.sql
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ────────────────────────────────────────────────────────────────────
-- Pomocná procedure pro idempotent ADD COLUMN
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
-- Nové sloupce v contacts pro recyklaci
-- ────────────────────────────────────────────────────────────────────
CALL `_crm_add_column_safe`('contacts', 'recycle_count',
    'TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT "Kolikrát byl kontakt už recyklován zpět do oběhu"');

CALL `_crm_add_column_safe`('contacts', 'last_recycled_at',
    'DATETIME(3) NULL DEFAULT NULL COMMENT "Datum poslední recyklace (NULL = nikdy). Použito v pool queries pro řazení."');

CALL `_crm_add_column_safe`('contacts', 'last_recycled_by',
    'BIGINT UNSIGNED NULL DEFAULT NULL COMMENT "User ID admina, který recyklaci spustil"');

-- Index pro filtrování v admin recycle pohledu (stav + updated_at)
DROP PROCEDURE IF EXISTS `_crm_add_index_safe`;
DELIMITER $$
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

CALL `_crm_add_index_safe`('contacts', 'idx_recycle_filter', '`stav`, `updated_at`');

-- ────────────────────────────────────────────────────────────────────
-- Audit tabulka pro každou recyklační akci
-- ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `contact_recycles` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  `contact_id`     BIGINT UNSIGNED NOT NULL,
  `recycled_at`    DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `recycled_by`    BIGINT UNSIGNED NOT NULL COMMENT 'admin user ID',

  -- Stav PŘED recyklací (NEZAJEM/VF_SKIP/atd.)
  `previous_stav`  VARCHAR(40) NOT NULL,
  -- Stav PO recyklaci (NEW nebo READY)
  `new_stav`       VARCHAR(40) NOT NULL,

  `note`           TEXT NULL COMMENT 'Důvod recyklace (volitelně)',

  PRIMARY KEY (`id`),
  KEY `idx_cr_contact`     (`contact_id`),
  KEY `idx_cr_recycled_at` (`recycled_at`),
  KEY `idx_cr_recycled_by` (`recycled_by`),

  CONSTRAINT `fk_cr_contact`     FOREIGN KEY (`contact_id`)  REFERENCES `contacts`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cr_recycled_by` FOREIGN KEY (`recycled_by`) REFERENCES `users`(`id`)    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Audit log recyklačních akcí — kdo, kdy, který kontakt, z jakého stavu do jakého';

-- ────────────────────────────────────────────────────────────────────
-- Cleanup
-- ────────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS `_crm_add_column_safe`;
DROP PROCEDURE IF EXISTS `_crm_add_index_safe`;
