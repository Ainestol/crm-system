-- e:\Snecinatripu\sql\migrations\014_widen_telefon_for_merge.sql
-- ════════════════════════════════════════════════════════════════════
-- Zvětšení contacts.telefon z VARCHAR(50) na VARCHAR(200).
--
-- Důvod: Import kontaktů podporuje akci "merge" — když přijde duplicita
-- podle IČO, slučují se telefony do jednoho pole oddělené "; ".
-- Stávající VARCHAR(50) se zaplní po 2-3 telefonech.
-- 200 znaků pojme až ~6 telefonů ve formátu "+420 605 580 813; …".
--
-- email zůstává VARCHAR(255) — již dostatečně velký pro až 6 emailů.
--
-- Operace je BEZPEČNÁ:
--   • MODIFY COLUMN se 200 ≥ 50 (rozšíření, ne zúžení)
--   • žádná data se neztratí
--   • idempotentní (opakované spuštění nic nemění)
--
-- Spustit: sudo mariadb crm < sql/migrations/014_widen_telefon_for_merge.sql
-- ════════════════════════════════════════════════════════════════════

ALTER TABLE `contacts`
  MODIFY COLUMN `telefon` VARCHAR(200) NULL DEFAULT NULL
    COMMENT 'Telefon — při importu mergem oddělené "; ", max 6 čísel';
