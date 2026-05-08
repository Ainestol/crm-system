-- e:\Snecinatripu\sql\migrations\010_premium_payment_tracking.sql
-- ════════════════════════════════════════════════════════════════════
-- PREMIUM PIPELINE — tracking plateb (zaplaceno?)
--
-- OZ si u objednávky zatrhne dvě věci:
--   1) Zaplaceno čističce (za vyčištěné leady — price_per_lead × count)
--   2) Zaplaceno navolávačce (bonus za úspěšný hovor — caller_bonus_per_lead × count)
--      → jen pokud caller_bonus_per_lead > 0, jinak je tato sekce neaktivní
--
-- Cílem NENÍ účetnictví — jen self-tracking pro OZ ("už jsem to poslal?").
-- Není to platební mechanismus, jen flag s datem. Lze odškrtnout (NULL=
-- nezaplaceno) a opět zaškrtnout (NOW(3) = zaplaceno teď).
--
-- Datum + audit (kdo to klikl): paid_to_*_at + paid_to_*_by.
--
-- Spuštění lokálně:
--   mysql -u root -p crm < E:\Snecinatripu\sql\migrations\010_premium_payment_tracking.sql
--
-- Spuštění na serveru:
--   sudo mariadb crm < /var/www/crm/sql/migrations/010_premium_payment_tracking.sql
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

ALTER TABLE `premium_orders`
    ADD COLUMN `paid_to_cleaner_at` DATETIME(3) NULL DEFAULT NULL
        COMMENT 'OZ označil že zaplatil čističce (NULL = nezaplaceno)',
    ADD COLUMN `paid_to_cleaner_by` BIGINT UNSIGNED NULL DEFAULT NULL
        COMMENT 'Kdo zaškrtl (audit)',
    ADD COLUMN `paid_to_caller_at`  DATETIME(3) NULL DEFAULT NULL
        COMMENT 'OZ označil že zaplatil navolávačce(ám) bonus',
    ADD COLUMN `paid_to_caller_by`  BIGINT UNSIGNED NULL DEFAULT NULL,
    ADD KEY `idx_po_paid_cleaner` (`paid_to_cleaner_at`),
    ADD KEY `idx_po_paid_caller`  (`paid_to_caller_at`),
    ADD CONSTRAINT `fk_po_paid_cleaner_by`
        FOREIGN KEY (`paid_to_cleaner_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_po_paid_caller_by`
        FOREIGN KEY (`paid_to_caller_by`)  REFERENCES `users`(`id`) ON DELETE SET NULL;
