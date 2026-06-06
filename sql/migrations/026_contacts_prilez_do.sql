-- e:\Snecinatripu\sql\migrations\026_contacts_prilez_do.sql
-- ════════════════════════════════════════════════════════════════════
-- CONTACTS.PRILEZ_DO — datum „do kdy" má kontakt příležitost
--
-- Účel:
--   Příležitost je teď dvoustavová:
--     - prilez != ''  → MÁ příležitost (text = popis služby / produktu)
--     - prilez_do     → datum, do kdy je příležitost platná (volitelné)
--
--   Po prilez_do se příležitost neprodlouží automaticky — admin / OZ
--   ji buď znovu nastaví, nebo nechá vypršet.
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

ALTER TABLE `contacts`
    ADD COLUMN `prilez_do` DATE NULL DEFAULT NULL
        COMMENT 'Datum, do kdy je příležitost platná (NULL = bez termínu)'
        AFTER `prilez`;

-- Volitelný index pro budoucí queries typu "expirující příležitosti"
ALTER TABLE `contacts`
    ADD KEY `idx_contacts_prilez_do` (`prilez_do`);
