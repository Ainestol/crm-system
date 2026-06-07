-- e:\Snecinatripu\sql\migrations\027_contacts_created_by.sql
-- ════════════════════════════════════════════════════════════════════
-- CONTACTS.CREATED_BY_USER_ID — kdo přidal kontakt
--
-- Účel:
--   Po zrušení schvalovacího flow (proposals) potřebujeme vědět,
--   kdo a kdy konkrétní kontakt vytvořil. Sloupec se vyplňuje při
--   každém novém INSERTu z formuláře /contacts/new.
--
--   Stávající řádky → NULL (nevíme — vznikly před tímto trackingem).
--
-- Source pro:
--   - admin přehled /admin/contacts/added
--   - audit kdo přidal hot leady mimo navolávačku
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

ALTER TABLE `contacts`
    ADD COLUMN `created_by_user_id` BIGINT UNSIGNED NULL DEFAULT NULL
        COMMENT 'User.id toho, kdo kontakt přidal přes /contacts/new (NULL = legacy, neznámý zdroj)'
        AFTER `assigned_caller_id`;

ALTER TABLE `contacts`
    ADD KEY `idx_contacts_created_by` (`created_by_user_id`);
