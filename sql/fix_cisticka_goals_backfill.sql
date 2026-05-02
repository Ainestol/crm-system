-- =============================================================================
--  ONE-TIME FIX: Backfill goal_started_at na dávnou minulost
--
--  Pokud byla tabulka cisticka_region_goals vytvořena s goal_started_at = NOW
--  (před opravou migrace), starší workflow_log záznamy se nepočítaly do progressu.
--  Tento SQL nastaví goal_started_at na "dávno" pro VŠECHNY existující řádky,
--  takže veškerá historická aktivita se začne počítat.
--
--  POZOR: Tohle vynuluje effect "změna targetu = reset counteru" pro existující
--  cíle. Jakmile admin příště změní target, goal_started_at se nastaví na NOW
--  a counter začne od 0 (správné chování).
--
--  Spuštění (HeidiSQL / Adminer / mysql cli):
--    SOURCE E:/Snecinatripu/sql/fix_cisticka_goals_backfill.sql;
-- =============================================================================

UPDATE `cisticka_region_goals`
SET    `goal_started_at` = '2000-01-01 00:00:00.000'
WHERE  `goal_started_at` >= DATE_SUB(NOW(), INTERVAL 1 DAY);

-- Kontrola: zobraz aktuální stav
SELECT id, region, daily_target, goal_started_at, updated_at
FROM   `cisticka_region_goals`
ORDER BY region;
