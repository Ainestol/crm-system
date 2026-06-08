-- e:\Snecinatripu\sql\migrations\028_oz_contact_notes_author.sql
-- ════════════════════════════════════════════════════════════════════
-- OZ_CONTACT_NOTES.AUTHOR_USER_ID — kdo POZNÁMKU REÁLNĚ napsal
--
-- Účel:
--   Doteď byl v oz_contact_notes jen sloupec `oz_id`, který znamenal:
--   "ke kterému OZ poznámka patří" (= vlastník kontaktu). Když ale admin,
--   majitel nebo BO zapsal poznámku přes datagrid, hodnota `oz_id` byla
--   pořád OZ-vlastník kontaktu, NE autor. Proto se do textu lepil prefix
--   "[ADMIN: jméno]" — aby OZ na své pracovní ploše viděl, kdo psal.
--
--   Tento přístup byl křehký: UI strippuje prefixy pro čistotu, ale tím
--   ztrácí info o skutečném autorovi. Plus se prefix lepil ručně, takže
--   různé controllery psaly různé varianty.
--
--   Nové schéma:
--     oz_id           = ke kterému OZ poznámka patří (filtr v jeho views)
--     author_user_id  = kdo to opravdu napsal (zobrazení role-badge + jméno)
--
--   Tj. autor a vlastník můžou být různí lidé. Pro existující záznamy
--   backfillujeme author_user_id = oz_id (vlastník == autor v 99 % případů
--   předtím; jen admin/BO poznámky se objeví jako "od OZ", což je akceptovatelný
--   kompromis dokud někdo nepřepsane historii).
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

ALTER TABLE `oz_contact_notes`
    ADD COLUMN `author_user_id` BIGINT UNSIGNED NULL DEFAULT NULL
        COMMENT 'Kdo poznámku reálně napsal (autor != oz_id když to píše admin / BO / majitel)'
        AFTER `oz_id`;

ALTER TABLE `oz_contact_notes`
    ADD KEY `idx_ocn_author` (`author_user_id`);

-- Backfill: pro stávající záznamy předpokládáme autor = oz_id (vlastník).
-- Pro nově psané poznámky se vyplní z controllerů.
UPDATE `oz_contact_notes`
SET `author_user_id` = `oz_id`
WHERE `author_user_id` IS NULL;
