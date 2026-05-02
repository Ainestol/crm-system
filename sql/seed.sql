-- e:\Snecinatripu\sql\seed.sql
-- Vývojová seed data – spusťte na prázdné databázi PO načtení schema.sql
--
-- Výchozí přihlašovací heslo všech seed účtů: password
-- Hash je bcrypt $2y$10$… (PHP password_verify je přijme; nová hesla ukládejte jako ARGON2ID dle specifikace).
-- Po prvním přihlášení aplikace může vynutit změnu hesla (must_change_password = 1).

SET NAMES utf8mb4;
SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- Sjednocený hash hesla „password“ (Laravel / PHPUnit fixture – běžně používaný pro vývoj)
SET @pwd_hash := '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

-- ---------------------------------------------------------------------------
-- Uživatelé (fixní ID pro snadné odkazy ve vývoji)
-- 1 superadmin, 1 majitel, 2 navolávačky, 2 obchodáci, 1 backoffice
-- ---------------------------------------------------------------------------

INSERT INTO `users` (
  `id`, `jmeno`, `email`, `heslo_hash`, `role`, `primary_region`, `aktivni`,
  `totp_secret`, `totp_enabled`, `must_change_password`, `created_at`, `deactivated_at`, `created_by`
) VALUES
(1, 'Super Admin', 'superadmin@crm.local', @pwd_hash, 'superadmin', NULL, 1, NULL, 0, 0, NOW(3), NULL, NULL),
(2, 'Jan Novák (majitel)', 'majitel@crm.local', @pwd_hash, 'majitel', 'praha', 1, NULL, 0, 0, NOW(3), NULL, 1),
(3, 'Jana Nováková', 'jana.navolavacka@crm.local', @pwd_hash, 'navolavacka', 'praha', 1, NULL, 0, 0, NOW(3), NULL, 2),
(4, 'Petra Svobodová', 'petra.navolavacka@crm.local', @pwd_hash, 'navolavacka', 'brno', 1, NULL, 0, 0, NOW(3), NULL, 2),
(5, 'Tomáš Dvořák', 'tomas.obchodak@crm.local', @pwd_hash, 'obchodak', 'praha', 1, NULL, 0, 0, NOW(3), NULL, 2),
(6, 'Martin Král', 'martin.obchodak@crm.local', @pwd_hash, 'obchodak', 'jihomoravsky', 1, NULL, 0, 0, NOW(3), NULL, 2),
(7, 'Eva Backoffice', 'backoffice@crm.local', @pwd_hash, 'backoffice', NULL, 1, NULL, 0, 0, NOW(3), NULL, 2);

-- AUTO_INCREMENT pokračuje za seed ID
ALTER TABLE `users` AUTO_INCREMENT = 8;

-- ---------------------------------------------------------------------------
-- Regiony (obchodáci: oba regiony + primary; navolávačky: dle působnosti)
-- ---------------------------------------------------------------------------

-- Navolávačky: všechny kraje
INSERT INTO `user_regions` (`user_id`, `region`) VALUES
(3, 'praha'),
(3, 'stredocesky'),
(3, 'jihocesky'),
(3, 'plzensky'),
(3, 'karlovarsky'),
(3, 'ustecky'),
(3, 'liberecky'),
(4, 'kralovehradecky'),
(4, 'pardubicky'),
(4, 'vysocina'),
(4, 'jihomoravsky'),
(4, 'olomoucky'),
(4, 'zlinsky'),
(4, 'moravskoslezsky'),
-- Obchodáci: všechny kraje
(5, 'praha'),
(5, 'stredocesky'),
(5, 'jihocesky'),
(5, 'plzensky'),
(5, 'karlovarsky'),
(5, 'ustecky'),
(5, 'liberecky'),
(5, 'kralovehradecky'),
(5, 'pardubicky'),
(5, 'vysocina'),
(5, 'jihomoravsky'),
(5, 'olomoucky'),
(5, 'zlinsky'),
(5, 'moravskoslezsky'),
(6, 'praha'),
(6, 'stredocesky'),
(6, 'jihocesky'),
(6, 'plzensky'),
(6, 'karlovarsky'),
(6, 'ustecky'),
(6, 'liberecky'),
(6, 'kralovehradecky'),
(6, 'pardubicky'),
(6, 'vysocina'),
(6, 'jihomoravsky'),
(6, 'olomoucky'),
(6, 'zlinsky'),
(6, 'moravskoslezsky');

-- ---------------------------------------------------------------------------
-- Šablony poznámek (3 ks, vytvořil majitel)
-- ---------------------------------------------------------------------------

INSERT INTO `note_templates` (`label`, `text`, `created_by`, `created_at`) VALUES
('Nedovolat', 'Klient požádal o volání v jiný čas – domluvit callback.', 2, NOW(3)),
('Špatný kontakt', 'Telefon nepatří firmě / špatná osoba.', 2, NOW(3)),
('Zájem o schůzku', 'Projevil zájem o osobní schůzku, domluvit termín.', 2, NOW(3));

-- ---------------------------------------------------------------------------
-- Denní cíle podle role (ukázkové hodnoty)
-- ---------------------------------------------------------------------------

INSERT INTO `daily_goals` (`role`, `target_calls`, `target_wins`) VALUES
('navolavacka', 40, 8),
('obchodak', 6, 2),
('backoffice', 15, 5),
('majitel', 0, 0);

-- ---------------------------------------------------------------------------
-- Tabulka násobků obchodáka (měsíční výkon v Kč → násobek ceny obchodu)
-- ---------------------------------------------------------------------------

INSERT INTO `commission_tiers_sales` (`min_monthly_sales`, `max_monthly_sales`, `multiplier`) VALUES
(0.00, 300000.00, 5.000),
(300000.01, 600000.00, 5.500),
(600000.01, NULL, 6.300);

-- ---------------------------------------------------------------------------
-- Tabulka násobků od velké firmy (typ služby + pásmo ceny bez DPH → násobek)
-- ---------------------------------------------------------------------------

INSERT INTO `commission_tiers_company` (`service_type`, `min_price`, `max_price`, `multiplier`) VALUES
('Standard', 0.00, 4999.99, 8.000),
('Standard', 5000.00, NULL, 9.000),
('Premium', 0.00, 7999.99, 9.000),
('Premium', 8000.00, NULL, 10.000),
('Enterprise', 0.00, NULL, 12.000);

-- ---------------------------------------------------------------------------
-- Fixní odměna navolávačky za CALLED_OK
-- ---------------------------------------------------------------------------

INSERT INTO `caller_rewards_config` (`amount_czk`, `valid_from`, `valid_to`) VALUES
(200.00, '2026-01-01', NULL);
