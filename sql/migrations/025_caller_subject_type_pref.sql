-- e:\Snecinatripu\sql\migrations\025_caller_subject_type_pref.sql
-- ════════════════════════════════════════════════════════════════════
-- CALLER SUBJECT TYPE PREFERENCE — per-navolávačka filter firma/OSVČ
--
-- Účel:
--   Některé navolávačky chtějí volat jen firmy (s.r.o., a.s., …),
--   některé jen OSVČ (živnostníky). Tato preference se aplikuje
--   na queue výběr — navolávačka uvidí ve frontě jen kontakty
--   odpovídajícího typu.
--
-- Hodnoty:
--   'any'   = default, kontakty obou typů (i unknown)
--   'firma' = jen kontakty s subject_type = 'firma'
--   'osvc'  = jen kontakty s subject_type = 'osvc'
--
-- Aplikuje se POUZE na navolávačky (jiné role to nevyužijí).
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

ALTER TABLE `users`
    ADD COLUMN `subject_type_pref` ENUM('any','firma','osvc') NOT NULL DEFAULT 'any'
        COMMENT 'Preferenční typ subjektu pro navolávačku (filtr poolu)';
