-- =============================================================================
--  CRM RESET — TRUNCATE všech kontaktních tabulek pro nový import.
--  POZOR: NEZNÁVRATNÉ. Smaže VŠECHNY kontakty + závislé záznamy.
--          Uživatelé (users) a struktury (oz_targets, daily_goals atd.) ZŮSTÁVAJÍ.
--
--  Pořadí mazání respektuje cizí klíče (FK) i ručně vytvořené tabulky:
--    1) Tabulky s FK na contacts (CASCADE / RESTRICT) — nutno smazat ručně,
--       protože commissions má RESTRICT a blokuje TRUNCATE contacts.
--    2) Tabulky bez FK, ale logicky závislé (vytvořené migracemi) — smažeme z paměti.
--    3) Hlavní tabulka contacts.
--    4) import_log historie zůstává (DELETE bez TRUNCATE — drží auto_increment).
--
--  SPUŠTĚNÍ:
--    - Otevři HeidiSQL / Adminer / phpMyAdmin
--    - Připoj se k databázi CRM
--    - Ulož a spusť tento soubor
--    - NEBO z příkazové řádky:
--        mysql -u root -p crm_db < tools/truncate_contacts.sql
--
--  AUDIT: tato akce neprochází přes audit_log, protože jde mimo PHP aplikaci.
--          Pro auditovatelný reset použij:  /admin/import → tlačítko "🗑 Reset DB"
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── 1) Tabulky s explicitním FK na contacts ──────────────────────────
TRUNCATE TABLE `commissions`;                  -- RESTRICT FK → musí být první
TRUNCATE TABLE `contact_quality_ratings`;       -- CASCADE FK
TRUNCATE TABLE `contact_notes`;                 -- CASCADE FK
TRUNCATE TABLE `workflow_log`;                  -- CASCADE FK
TRUNCATE TABLE `assignment_log`;                -- CASCADE FK
TRUNCATE TABLE `sms_log`;                       -- SET NULL FK

-- ── 2) Tabulky vytvořené migracemi (bez FK, ale logicky závislé na contacts) ──
-- Některé mohou neexistovat (pokud projekt ještě neproběhl danou migraci) —
-- IF EXISTS ošetří chybu.
SET @sql = NULL;

SELECT IF(COUNT(*) > 0, 'TRUNCATE TABLE `oz_contact_workflow`', 'SELECT 1')
  INTO @sql FROM information_schema.tables
  WHERE table_schema = DATABASE() AND table_name = 'oz_contact_workflow';
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT IF(COUNT(*) > 0, 'TRUNCATE TABLE `oz_contact_notes`', 'SELECT 1')
  INTO @sql FROM information_schema.tables
  WHERE table_schema = DATABASE() AND table_name = 'oz_contact_notes';
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT IF(COUNT(*) > 0, 'TRUNCATE TABLE `oz_contact_actions`', 'SELECT 1')
  INTO @sql FROM information_schema.tables
  WHERE table_schema = DATABASE() AND table_name = 'oz_contact_actions';
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT IF(COUNT(*) > 0, 'TRUNCATE TABLE `contact_oz_flags`', 'SELECT 1')
  INTO @sql FROM information_schema.tables
  WHERE table_schema = DATABASE() AND table_name = 'contact_oz_flags';
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 3) Hlavní tabulka contacts (zde se uvolní auto_increment) ────────
TRUNCATE TABLE `contacts`;

-- ── 4) Historie importů — necháme strukturu, ale vyprázdníme záznamy ──
-- (Pokud bys chtěl ZACHOVAT historii importů, zakomentuj následující řádek.)
DELETE FROM `import_log`;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Kontrola: kolik řádků zůstalo (mělo by být 0 ve všech) ──────────
SELECT 'contacts'          AS table_name, COUNT(*) AS rows_left FROM `contacts`
UNION ALL SELECT 'workflow_log',          COUNT(*) FROM `workflow_log`
UNION ALL SELECT 'contact_notes',         COUNT(*) FROM `contact_notes`
UNION ALL SELECT 'commissions',           COUNT(*) FROM `commissions`;
