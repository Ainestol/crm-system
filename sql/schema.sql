-- e:\Snecinatripu\sql\schema.sql
-- CRM B2B: MariaDB 10.6+ / InnoDB / utf8mb4
-- Schéma databáze – tabulky, indexy, cizí klíče, komentáře (krok 1 specifikace)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Mazání v pořadí závislostí (děti před rodiči)
DROP TABLE IF EXISTS `totp_backup_codes`;
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `api_tokens`;
DROP TABLE IF EXISTS `commissions`;
DROP TABLE IF EXISTS `monthly_salaries`;
DROP TABLE IF EXISTS `contact_quality_ratings`;
DROP TABLE IF EXISTS `contact_notes`;
DROP TABLE IF EXISTS `workflow_log`;
DROP TABLE IF EXISTS `sms_log`;
DROP TABLE IF EXISTS `assignment_log`;
DROP TABLE IF EXISTS `contacts`;
DROP TABLE IF EXISTS `import_log`;
DROP TABLE IF EXISTS `audit_log`;
DROP TABLE IF EXISTS `alerts`;
DROP TABLE IF EXISTS `announcements`;
DROP TABLE IF EXISTS `team_records`;
DROP TABLE IF EXISTS `note_templates`;
DROP TABLE IF EXISTS `daily_goals`;
DROP TABLE IF EXISTS `caller_rewards_config`;
DROP TABLE IF EXISTS `commission_tiers_sales`;
DROP TABLE IF EXISTS `commission_tiers_company`;
DROP TABLE IF EXISTS `user_regions`;
DROP TABLE IF EXISTS `dnc_list`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- Uživatelé
-- ---------------------------------------------------------------------------

CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `jmeno` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `heslo_hash` VARCHAR(255) NOT NULL COMMENT 'password_hash(..., PASSWORD_ARGON2ID)',
  `role` ENUM('superadmin','majitel','navolavacka','obchodak','backoffice') NOT NULL,
  `primary_region` VARCHAR(64) NULL DEFAULT NULL COMMENT 'Preferovaný region obchodáka',
  `aktivni` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 aktivní, 0 deaktivovaný účet',
  `totp_secret` VARBINARY(512) NULL DEFAULT NULL COMMENT 'Secret TOTP (doporučeno šifrovat v aplikaci)',
  `totp_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Nucená změna hesla při prvním přihlášení',
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `deactivated_at` DATETIME(3) NULL DEFAULT NULL,
  `created_by` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Kdo uživatele založil',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_email` (`email`),
  KEY `idx_users_role_aktivni` (`role`, `aktivni`),
  KEY `idx_users_primary_region` (`primary_region`),
  KEY `idx_users_created_by` (`created_by`),
  CONSTRAINT `fk_users_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Interní a externí uživatelé CRM';

CREATE TABLE `totp_backup_codes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'Vlastník záložního kódu',
  `code_hash` VARCHAR(255) NOT NULL COMMENT 'Hash kódu (nikdy plain text)',
  `used_at` DATETIME(3) NULL DEFAULT NULL COMMENT 'Čas uplatnění kódu',
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `idx_totp_backup_user_unused` (`user_id`, `used_at`),
  CONSTRAINT `fk_totp_backup_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Záložní kódy 2FA (až 8 ks, hashované)';

CREATE TABLE `password_resets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `token_hash` VARCHAR(255) NOT NULL COMMENT 'Hash tokenu z odkazu v e-mailu',
  `expires_at` DATETIME(3) NOT NULL,
  `used_at` DATETIME(3) NULL DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `idx_pwreset_user` (`user_id`),
  KEY `idx_pwreset_expires` (`expires_at`),
  CONSTRAINT `fk_pwreset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tokeny pro reset hesla';

CREATE TABLE `user_regions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `region` VARCHAR(64) NOT NULL COMMENT 'Kód regionu (shodný s contacts.region)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_region` (`user_id`, `region`),
  KEY `idx_user_regions_region` (`region`),
  CONSTRAINT `fk_user_regions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Povolené regiony pro obchodáky a navolávačky';

CREATE TABLE `api_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL COMMENT 'SHA-256 hex hash Bearer tokenu',
  `device_name` VARCHAR(128) NULL DEFAULT NULL,
  `last_used_at` DATETIME(3) NULL DEFAULT NULL,
  `expires_at` DATETIME(3) NOT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_api_tokens_hash` (`token_hash`),
  KEY `idx_api_tokens_user_expires` (`user_id`, `expires_at`),
  CONSTRAINT `fk_api_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='REST API Bearer tokeny (hash, expirace)';

-- ---------------------------------------------------------------------------
-- Kontakty a workflow
-- ---------------------------------------------------------------------------

CREATE TABLE `contacts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ico` VARCHAR(20) NULL DEFAULT NULL,
  `firma` VARCHAR(500) NOT NULL DEFAULT '',
  `adresa` VARCHAR(500) NULL DEFAULT NULL COMMENT 'Adresa sídla firmy',
  `telefon` VARCHAR(50) NULL DEFAULT NULL,
  `email` VARCHAR(255) NULL DEFAULT NULL,
  `region` VARCHAR(64) NOT NULL COMMENT 'Region kontaktu (přidělení, filtry)',
  `stav` VARCHAR(40) NOT NULL DEFAULT 'NEW' COMMENT 'NEW,ASSIGNED,CALLBACK,CALLED_OK,CALLED_BAD,FOR_SALES,APPROVED_BY_SALES,REJECTED_BY_SALES,BACKOFFICE,DONE,ACTIVATED,CANCELLED',
  `poznamka` TEXT NULL COMMENT 'Krátká interní poznámka ke kontaktu (volitelné)',
  `assigned_caller_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `assigned_sales_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `callback_at` DATETIME(3) NULL DEFAULT NULL,
  `callback_sms_sent` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Příznak odeslané SMS připomínky',
  `datum_volani` DATETIME(3) NULL DEFAULT NULL COMMENT 'Datum a čas posledního volání',
  `datum_predani` DATETIME(3) NULL DEFAULT NULL COMMENT 'Datum předání obchodákovi',
  `oznaceno` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Příznak – záložka / hvězdička',
  `narozeniny_majitele` DATE NULL DEFAULT NULL COMMENT 'Datum narození majitele firmy (pro SMS přání)',
  `vyrocni_smlouvy` DATE NULL DEFAULT NULL COMMENT 'Datum výročí smlouvy (pro backoffice notifikace)',
  `locked_by` BIGINT UNSIGNED NULL DEFAULT NULL,
  `locked_until` DATETIME(3) NULL DEFAULT NULL,
  `dnc_flag` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Do Not Call – zákaz kontaktování',
  `sale_price` DECIMAL(14,2) NULL DEFAULT NULL COMMENT 'Cena obchodu bez DPH (backoffice)',
  `activation_date` DATE NULL DEFAULT NULL COMMENT 'Datum aktivace služby u velké firmy',
  `cancellation_date` DATE NULL DEFAULT NULL,
  `cancellation_ratio` DECIMAL(7,4) NULL DEFAULT NULL COMMENT '0–1 poměr vrácení od velké firmy',
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `idx_contacts_stav` (`stav`),
  KEY `idx_contacts_region` (`region`),
  KEY `idx_contacts_region_stav` (`region`, `stav`),
  KEY `idx_contacts_assigned_caller` (`assigned_caller_id`),
  KEY `idx_contacts_assigned_sales` (`assigned_sales_id`),
  KEY `idx_contacts_callback` (`callback_at`, `stav`),
  KEY `idx_contacts_locked_until` (`locked_until`),
  KEY `idx_contacts_created` (`created_at`),
  KEY `idx_contacts_ico` (`ico`),
  KEY `idx_contacts_narozeniny` (`narozeniny_majitele`),
  KEY `idx_contacts_vyrocni` (`vyrocni_smlouvy`),
  KEY `idx_contacts_oznaceno` (`oznaceno`),
  FULLTEXT KEY `ft_contacts_search` (`ico`,`firma`,`telefon`,`email`),
  CONSTRAINT `fk_contacts_assigned_caller` FOREIGN KEY (`assigned_caller_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contacts_assigned_sales` FOREIGN KEY (`assigned_sales_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contacts_locked_by` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='B2B kontakty a stav ve workflow';

CREATE TABLE `workflow_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `contact_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Kdo změnu provedl (NULL = systém)',
  `old_status` VARCHAR(40) NULL DEFAULT NULL,
  `new_status` VARCHAR(40) NOT NULL,
  `note` TEXT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `idx_workflow_contact_created` (`contact_id`, `created_at`),
  KEY `idx_workflow_user` (`user_id`),
  CONSTRAINT `fk_workflow_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_workflow_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Historie přechodů stavů kontaktu';

CREATE TABLE `contact_notes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `contact_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `note` TEXT NOT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `edited_at` DATETIME(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notes_contact_created` (`contact_id`, `created_at`),
  KEY `idx_notes_user` (`user_id`),
  CONSTRAINT `fk_notes_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Neomezené poznámky k kontaktu (timeline)';

CREATE TABLE `contact_quality_ratings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `contact_id` BIGINT UNSIGNED NOT NULL,
  `caller_id` BIGINT UNSIGNED NOT NULL COMMENT 'Navolávačka (assigned_caller)',
  `sales_id` BIGINT UNSIGNED NOT NULL COMMENT 'Obchodák hodnotící',
  `rating` ENUM('thumbs_up','thumbs_down','star') NOT NULL,
  `feedback_note` TEXT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_rating_contact_sales` (`contact_id`, `sales_id`),
  KEY `idx_rating_caller` (`caller_id`),
  CONSTRAINT `fk_rating_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rating_caller` FOREIGN KEY (`caller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rating_sales` FOREIGN KEY (`sales_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Hodnocení kvality kontaktu obchodákem pro navolávačku';

-- ---------------------------------------------------------------------------
-- Import, audit, přidělení, DNC, šablony
-- ---------------------------------------------------------------------------

CREATE TABLE `import_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` BIGINT UNSIGNED NOT NULL,
  `filename` VARCHAR(500) NOT NULL,
  `total_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `imported` INT UNSIGNED NOT NULL DEFAULT 0,
  `skipped_duplicates` INT UNSIGNED NOT NULL DEFAULT 0,
  `skipped_dnc` INT UNSIGNED NOT NULL DEFAULT 0,
  `errors` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Počet řádků s chybou',
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `idx_import_log_admin` (`admin_id`),
  KEY `idx_import_log_created` (`created_at`),
  CONSTRAINT `fk_import_log_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Log CSV importů kontaktů';

CREATE TABLE `audit_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `action` VARCHAR(128) NOT NULL,
  `entity_type` VARCHAR(64) NULL DEFAULT NULL,
  `entity_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `details` JSON NULL COMMENT 'Strukturovaný detail akce',
  `ip_address` VARCHAR(45) NULL DEFAULT NULL COMMENT 'IPv4 / IPv6 textově',
  `source` ENUM('web','api','cron','cli') NOT NULL DEFAULT 'web' COMMENT 'Zdroj události (API dle spec)',
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `idx_audit_user_created` (`user_id`, `created_at`),
  KEY `idx_audit_entity` (`entity_type`, `entity_id`),
  KEY `idx_audit_source` (`source`, `created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Audit citlivých akcí a exportů';

CREATE TABLE `assignment_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `contact_id` BIGINT UNSIGNED NOT NULL,
  `from_user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `to_user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `assignment_type` VARCHAR(64) NOT NULL COMMENT 'caller|sales|auto|bulk|admin',
  `reason` VARCHAR(500) NULL DEFAULT NULL,
  `admin_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `idx_assignment_contact` (`contact_id`, `created_at`),
  KEY `idx_assignment_to_user` (`to_user_id`),
  CONSTRAINT `fk_assignment_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assignment_from` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_assignment_to` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_assignment_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Historie ručního a automatického přidělování kontaktů';

CREATE TABLE `dnc_list` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ico` VARCHAR(20) NULL DEFAULT NULL,
  `telefon` VARCHAR(50) NULL DEFAULT NULL,
  `email` VARCHAR(255) NULL DEFAULT NULL,
  `reason` VARCHAR(500) NULL DEFAULT NULL,
  `blocked_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `idx_dnc_ico` (`ico`),
  KEY `idx_dnc_telefon` (`telefon`),
  KEY `idx_dnc_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Globální seznam zákazu kontaktování (přežije smazání kontaktu)';

CREATE TABLE `note_templates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `label` VARCHAR(255) NOT NULL,
  `text` TEXT NOT NULL,
  `created_by` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `idx_note_templates_created_by` (`created_by`),
  CONSTRAINT `fk_note_templates_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Šablony rychlých poznámek';

CREATE TABLE `daily_goals` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role` ENUM('navolavacka','obchodak','backoffice','majitel') NOT NULL,
  `target_calls` INT UNSIGNED NOT NULL DEFAULT 0,
  `target_wins` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_daily_goals_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Výchozí denní cíle podle role (dashboard)';

-- ---------------------------------------------------------------------------
-- SMS a provize
-- ---------------------------------------------------------------------------

CREATE TABLE `sms_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `contact_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `phone` VARCHAR(50) NOT NULL,
  `message` TEXT NOT NULL,
  `status` VARCHAR(64) NOT NULL DEFAULT 'queued' COMMENT 'queued|sent|failed|...',
  `provider_response` TEXT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `idx_sms_user_created` (`user_id`, `created_at`),
  KEY `idx_sms_contact` (`contact_id`),
  KEY `idx_sms_created` (`created_at`),
  CONSTRAINT `fk_sms_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sms_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Odeslané SMS včetně callback připomínek';

CREATE TABLE `commission_tiers_sales` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `min_monthly_sales` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT 'Spodní hranice měsíčního obratu obchodáka (Kč)',
  `max_monthly_sales` DECIMAL(14,2) NULL DEFAULT NULL COMMENT 'NULL = neomezeno',
  `multiplier` DECIMAL(6,3) NOT NULL COMMENT 'Násobek ceny obchodu pro výpočet provize',
  PRIMARY KEY (`id`),
  KEY `idx_ct_sales_range` (`min_monthly_sales`, `max_monthly_sales`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Měsíční výkon obchodáka → násobek provize';

CREATE TABLE `commission_tiers_company` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_type` VARCHAR(64) NOT NULL COMMENT 'Typ služby u velké firmy (např. Premium)',
  `min_price` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `max_price` DECIMAL(14,2) NULL DEFAULT NULL COMMENT 'NULL = neomezeno',
  `multiplier` DECIMAL(8,3) NOT NULL COMMENT 'Kolik× ceny obchodu platí velká firma',
  PRIMARY KEY (`id`),
  KEY `idx_ct_company_type_price` (`service_type`, `min_price`, `max_price`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Typ služby a cena → násobek od velké firmy';

CREATE TABLE `caller_rewards_config` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `amount_czk` DECIMAL(12,2) NOT NULL COMMENT 'Fixní odměna za CALLED_OK',
  `valid_from` DATE NOT NULL,
  `valid_to` DATE NULL DEFAULT NULL COMMENT 'NULL = platí nadosmrti',
  PRIMARY KEY (`id`),
  KEY `idx_caller_rewards_valid` (`valid_from`, `valid_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Konfigurace fixní odměny navolávačky za úspěšný hovor';

CREATE TABLE `commissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `contact_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `role` ENUM('obchodak','navolavacka','majitel','other') NOT NULL DEFAULT 'obchodak',
  `base_amount` DECIMAL(14,2) NOT NULL COMMENT 'Základní částka před násobkem',
  `multiplier` DECIMAL(8,3) NOT NULL DEFAULT 1.000,
  `final_amount` DECIMAL(14,2) NOT NULL COMMENT 'Výsledná provize v Kč',
  `status` ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
  `activation_date` DATE NULL DEFAULT NULL,
  `payment_month` TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'Měsíc výplaty 1–12 (rok odvozit z activation_date / výplatního období v aplikaci)',
  `cancellation_deduction` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT 'Srážka při stornu (záporná logika v aplikaci / výplatě)',
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `idx_commissions_contact` (`contact_id`),
  KEY `idx_commissions_user_status` (`user_id`, `status`),
  KEY `idx_commissions_payment_month` (`payment_month`),
  CONSTRAINT `fk_commissions_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_commissions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Vypočítané provize za obchody';

CREATE TABLE `monthly_salaries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `month` TINYINT UNSIGNED NOT NULL,
  `year` SMALLINT UNSIGNED NOT NULL,
  `base_sum` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `deductions` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `final_sum` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `details_json` JSON NULL COMMENT 'Rozpis položek výplaty',
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_salary_user_period` (`user_id`, `year`, `month`),
  KEY `idx_salary_period` (`year`, `month`),
  CONSTRAINT `fk_salary_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Měsíční výplatní sestavy';

-- ---------------------------------------------------------------------------
-- Alerty, nástěnka, týmové rekordy
-- ---------------------------------------------------------------------------

CREATE TABLE `alerts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` VARCHAR(64) NOT NULL,
  `severity` ENUM('info','warning','critical') NOT NULL DEFAULT 'warning',
  `message` TEXT NOT NULL,
  `entity_type` VARCHAR(64) NULL DEFAULT NULL,
  `entity_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `resolved` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `idx_alerts_resolved_created` (`resolved`, `created_at`),
  KEY `idx_alerts_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Automatické alerty pro majitele (cron)';

CREATE TABLE `announcements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `visible_from` DATETIME(3) NOT NULL,
  `visible_until` DATETIME(3) NULL DEFAULT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `idx_announcements_visible` (`visible_from`, `visible_until`),
  KEY `idx_announcements_created_by` (`created_by`),
  CONSTRAINT `fk_announcements_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Nástěnka / zpráva dne';

CREATE TABLE `team_records` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `record_type` VARCHAR(64) NOT NULL COMMENT 'streak_wins|best_day_calls|...',
  `value` INT NOT NULL DEFAULT 0,
  `achieved_at` DATE NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_team_records_user_type` (`user_id`, `record_type`),
  KEY `idx_team_records_achieved` (`achieved_at`),
  CONSTRAINT `fk_team_records_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Osobní rekordy a streaks (týmový pulz)';
