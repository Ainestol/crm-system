-- e:\Snecinatripu\sql\migrations\033_tenant_unique_keys.sql
-- ════════════════════════════════════════════════════════════════════
-- TENANT MULTI-TENANT UNIQUE KEYS FIX
--
-- Účel:
--   Migrace 032 přidala sloupec `tenant_id` na 40 business tabulek, ale
--   NEROZŠÍŘILA existující UNIQUE / PRIMARY klíče. To znamená, že 3 tabulky
--   stále vyžadují celosvětově unikátní hodnoty, takže dvě firmy nemůžou
--   mít stejné setting/cíl/stage:
--
--   1. app_settings        — PRIMARY KEY (skey)         → (tenant_id, skey)
--   2. daily_goals         — UNIQUE (role)              → (tenant_id, role)
--   3. oz_team_stages      — UNIQUE (year,m,stage_num)  → (tenant_id, year, month, stage_number)
--
--   Po této migraci může každá firma mít vlastní `dark_mode = true`,
--   vlastní cíle pro roli `navolavacka`, vlastní stage_number=1 pro
--   leden 2026 atd.
--
-- Bezpečnost:
--   - Žádná data se nemažou
--   - DROP + ADD index je atomická operace (MySQL 8 / MariaDB 10.11)
--   - Existující řádky zůstávají s tenant_id=1 (z migrace 032)
--   - Při běžícím provozu může INSERT během migrace selhat duplikátně —
--     spouštět v údržbovém okně
--
-- Reverzibilita:
--   Pokud potřebuješ rollback, zde je opačná verze:
--     ALTER TABLE app_settings
--         DROP PRIMARY KEY,
--         ADD PRIMARY KEY (skey);
--     ALTER TABLE daily_goals
--         DROP INDEX uk_daily_goals_role,
--         ADD UNIQUE KEY uk_daily_goals_role (role);
--     ALTER TABLE oz_team_stages
--         DROP INDEX uq_oz_stage,
--         ADD UNIQUE KEY uq_oz_stage (year, month, stage_number);
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ─────────────────────────────────────────────────────────────────
-- 1. app_settings — PRIMARY KEY (skey) → (tenant_id, skey)
-- ─────────────────────────────────────────────────────────────────
ALTER TABLE `app_settings`
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (`tenant_id`, `skey`);

-- ─────────────────────────────────────────────────────────────────
-- 2. daily_goals — UNIQUE (role) → (tenant_id, role)
-- ─────────────────────────────────────────────────────────────────
ALTER TABLE `daily_goals`
    DROP INDEX `uk_daily_goals_role`,
    ADD UNIQUE KEY `uk_daily_goals_role` (`tenant_id`, `role`);

-- ─────────────────────────────────────────────────────────────────
-- 3. oz_team_stages — UNIQUE (year, month, stage_number)
--    → (tenant_id, year, month, stage_number)
-- ─────────────────────────────────────────────────────────────────
ALTER TABLE `oz_team_stages`
    DROP INDEX `uq_oz_stage`,
    ADD UNIQUE KEY `uq_oz_stage` (`tenant_id`, `year`, `month`, `stage_number`);
