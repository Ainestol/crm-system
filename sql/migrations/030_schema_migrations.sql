-- e:\Snecinatripu\sql\migrations\030_schema_migrations.sql
-- ════════════════════════════════════════════════════════════════════
-- SCHEMA_MIGRATIONS — tracker spuštěných migrací
--
-- Účel:
--   Doteď jsme spouštěli migrace ručně (`mariadb crm < 029_xxx.sql`) a
--   museli si pamatovat, co kde proběhlo. Po této migraci máme DB tabulku
--   která eviduje:
--     - která migrace už proběhla
--     - kdy (datetime)
--     - kdo (user@host kdo spustil)
--     - jak dlouho trvala (ms)
--     - hash souboru (kontrola, že soubor se po deployi nezměnil)
--
--   CLI runner `bin/migrate.php` pak umí:
--     php bin/migrate.php status   - kolik už proběhlo, co čeká
--     php bin/migrate.php up        - spustí všechny čekající
--     php bin/migrate.php new <name> - vygeneruje prázdný soubor s číslem +1
--
--   Bootstrap (jednorázově): příkaz `mark-applied` označí všechny
--   migrace 001–029 jako už spuštěné (žádné je nepřespustí, jen je zapíše).
--
-- Schéma:
--   version: identifikátor migrace (= jméno souboru bez .sql), např. "029_contact_phones"
--   name:    lidsky čitelné jméno (může být stejné jako version)
--   applied_at: kdy proběhla
--   applied_by: kdo spustil (např. "root@deploy-server" nebo "manual")
--   execution_ms: jak dlouho trvala (užitečné pro detekci pomalých migrací)
--   checksum: SHA-256 hash souboru v okamžiku spuštění
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `schema_migrations` (
    `version`       VARCHAR(100) NOT NULL,
    `name`          VARCHAR(255) NOT NULL,
    `applied_at`    DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `applied_by`    VARCHAR(100) NULL DEFAULT NULL
                    COMMENT 'user@host nebo "manual" pro ruční bootstrap',
    `execution_ms`  INT UNSIGNED NULL DEFAULT 0
                    COMMENT 'Doba spuštění v milisekundách',
    `checksum`      VARCHAR(64)  NULL DEFAULT NULL
                    COMMENT 'SHA-256 souboru — detekce úprav po deployi',
    PRIMARY KEY (`version`),
    KEY `idx_applied_at` (`applied_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
