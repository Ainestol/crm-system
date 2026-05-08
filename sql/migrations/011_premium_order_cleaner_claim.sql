-- e:\Snecinatripu\sql\migrations\011_premium_order_cleaner_claim.sql
-- ════════════════════════════════════════════════════════════════════
-- PREMIUM PIPELINE — claim objednávky čističkou
--
-- Když OZ vytvoří premium objednávku, je viditelná všem čističkám.
-- Jakmile jedna z nich klikne "Přijímám objednávku", ostatní ji už
-- neuvidí v seznamu (pracuje na ní jiná čistička).
--
-- Sloupec `accepted_by_cleaner_id`:
--   NULL  = objednávka je open pro všechny čističky
--   N     = ID konkrétní čističky která ji přijala
--
-- Spuštění lokálně:
--   mysql -u root -p crm < E:\Snecinatripu\sql\migrations\011_premium_order_cleaner_claim.sql
--
-- Spuštění na serveru:
--   sudo mariadb crm < /var/www/crm/sql/migrations/011_premium_order_cleaner_claim.sql
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

ALTER TABLE `premium_orders`
    ADD COLUMN `accepted_by_cleaner_id` BIGINT UNSIGNED NULL DEFAULT NULL
        COMMENT 'NULL = otevřená pro všechny čističky; N = přijata konkrétní čističkou' AFTER `preferred_caller_id`,
    ADD COLUMN `accepted_at` DATETIME(3) NULL DEFAULT NULL
        COMMENT 'Kdy čistička objednávku přijala' AFTER `accepted_by_cleaner_id`,
    ADD KEY `idx_po_accepted_cleaner` (`accepted_by_cleaner_id`),
    ADD CONSTRAINT `fk_po_accepted_cleaner`
        FOREIGN KEY (`accepted_by_cleaner_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;

-- Doplnit historicky existující objednávky kde už jsou cleaner_id záznamy v poolu —
-- označíme jako přijaté tou čističkou co tam udělala práci. Pokud na objednávce
-- pracovalo víc čističek (extreme edge case), bere se ta s nejvíc leady.
UPDATE `premium_orders` po
SET po.accepted_by_cleaner_id = (
    SELECT p.cleaner_id
    FROM premium_lead_pool p
    WHERE p.order_id = po.id AND p.cleaner_id IS NOT NULL
    GROUP BY p.cleaner_id
    ORDER BY COUNT(*) DESC
    LIMIT 1
),
    po.accepted_at = (
    SELECT MIN(p.cleaned_at)
    FROM premium_lead_pool p
    WHERE p.order_id = po.id AND p.cleaner_id IS NOT NULL
)
WHERE po.accepted_by_cleaner_id IS NULL
  AND EXISTS (
      SELECT 1 FROM premium_lead_pool p
      WHERE p.order_id = po.id AND p.cleaner_id IS NOT NULL
  );
