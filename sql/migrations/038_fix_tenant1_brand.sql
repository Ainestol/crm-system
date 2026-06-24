-- e:\Snecinatripu\sql\migrations\038_fix_tenant1_brand.sql
-- ════════════════════════════════════════════════════════════════════
-- OPRAVA NÁZVU TENANT 1 — "Šněčí závody" → "Šneci na tripu"
--
-- Migrace 031 vložila do `tenant_branding` placeholder "Šněčí závody"
-- (interní vtípek). Pro produkční vzhled to nahradíme správným názvem.
--
-- Bezpečné: pokud už majitel ručně přepsal display_name v UI, ponecháme.
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

UPDATE `tenant_branding`
   SET `display_name` = 'Šneci na tripu',
       `updated_at` = NOW(3)
 WHERE `tenant_id` = 1
   AND `display_name` IN ('Šněčí závody', 'Šnečí závody');
