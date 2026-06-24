-- e:\Snecinatripu\sql\migrations\031_tenants_foundation.sql
-- ════════════════════════════════════════════════════════════════════
-- TENANTS FOUNDATION — multi-tenant SaaS základna
--
-- Účel:
--   Vytvoří základní tabulky pro multi-tenant architekturu. Žádné
--   existující tabulky se v této migraci NEUPRAVUJÍ — to udělá až
--   migrace 032+ (`tenant_id` na business tabulkách).
--
-- Co tato migrace dělá:
--   1. CREATE TABLE `tenants` — master tabulka firem (každý zákazník = 1 řádek)
--   2. CREATE TABLE `tenant_branding` — per-firma vizuál (logo, barvy, název)
--   3. CREATE TABLE `user_tenants` — M:N mapping user × tenant + role per firma
--      (Honza může pracovat pro 2 firmy = 2 řádky)
--   4. CREATE TABLE `super_admins` — flag pro super-adminy (Aines = vidí všechno)
--   5. Backfill: vytvoří první tenant "Moje firma" (id=1) a přiřadí všechny
--      stávající users do něj se zachováním jejich rolí
--   6. Backfill: kdokoli má teď roli 'superadmin' se přidá do `super_admins`
--
-- Co tato migrace NEDĚLÁ:
--   - Nezasahuje do existujících tabulek (contacts, users, ...)
--   - Nemění UI (CRM funguje úplně stejně)
--   - Neaktivuje žádný tenant scope middleware
--
-- Bezpečnost:
--   - Po této migraci může DB stále fungovat v "single-tenant" módu
--   - Tenant scope middleware se aktivuje až po migraci 032+ a code change
--
-- Příští kroky:
--   - Migrace 032+: ADD COLUMN tenant_id na všech business tabulkách
--   - PHP: Tenant scope middleware (auto-filter na všech queries)
--   - PHP: Subdomain detection v login flow
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ─────────────────────────────────────────────────────────────────
-- 1) `tenants` — master tabulka firem
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tenants` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(200) NOT NULL
                    COMMENT 'Plný název firmy ("Pepova akviziční s.r.o.")',
    `subdomain`     VARCHAR(100) NOT NULL
                    COMMENT 'Subdoménový slug, např. "pepa" → pepa.snecinatripu.eu',
    `plan_code`     VARCHAR(50) NULL DEFAULT 'basic'
                    COMMENT 'Slug plánu (basic / pro / enterprise) — limity v PHP',
    `max_users`     INT UNSIGNED NULL DEFAULT 5
                    COMMENT 'Max uživatelů (limit z plánu). NULL = unlimited.',
    `max_contacts`  INT UNSIGNED NULL DEFAULT 1000
                    COMMENT 'Max kontaktů (limit z plánu). NULL = unlimited.',
    `active`        TINYINT(1) NOT NULL DEFAULT 1
                    COMMENT 'Pokud 0, tenant je suspendovaný (např. neplacený)',
    `trial_ends_at` DATETIME NULL DEFAULT NULL
                    COMMENT 'Konec trial období (NULL = bez trialu / placený)',
    `created_at`    DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updated_at`    DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tenants_subdomain` (`subdomain`),
    KEY `idx_tenants_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- 2) `tenant_branding` — vizuální skin per firma
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tenant_branding` (
    `tenant_id`       INT UNSIGNED NOT NULL,
    `display_name`    VARCHAR(200) NULL DEFAULT NULL
                      COMMENT 'Jméno ve UI (může se lišit od tenants.name)',
    `logo_url`        VARCHAR(500) NULL DEFAULT NULL,
    `primary_color`   VARCHAR(7)   NULL DEFAULT '#3b82f6'
                      COMMENT 'Hex barva (#RRGGBB) — primární barva CRM',
    `accent_color`    VARCHAR(7)   NULL DEFAULT '#0e7490',
    `email_signature` TEXT NULL DEFAULT NULL
                      COMMENT 'Patička pro odchozí e-maily (volitelné)',
    `updated_at`      DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (`tenant_id`),
    CONSTRAINT `fk_branding_tenant` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- 3) `user_tenants` — M:N mapping user × tenant + role per firma
-- ─────────────────────────────────────────────────────────────────
-- Honza může pracovat pro Firmu A i Firmu B — 2 řádky.
-- V každé firmě má vlastní roli (např. v A je 'majitel', v B 'obchodak').
CREATE TABLE IF NOT EXISTS `user_tenants` (
    `user_id`     BIGINT UNSIGNED NOT NULL,
    `tenant_id`   INT UNSIGNED NOT NULL,
    `role`        VARCHAR(50) NOT NULL
                  COMMENT 'Primární role v rámci tenanta (majitel/obchodak/...)',
    `roles_extra` JSON NULL DEFAULT NULL
                  COMMENT 'Sekundární role (např. ["navolavacka","cisticka"])',
    `active`      TINYINT(1) NOT NULL DEFAULT 1
                  COMMENT 'Pokud 0, user je deaktivovaný v této firmě',
    `joined_at`   DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updated_at`  DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (`user_id`, `tenant_id`),
    KEY `idx_ut_tenant`     (`tenant_id`, `active`),
    KEY `idx_ut_user_active` (`user_id`, `active`),
    CONSTRAINT `fk_ut_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ut_tenant` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- 4) `super_admins` — flag pro super-adminy (vidí do všech firem)
-- ─────────────────────────────────────────────────────────────────
-- Super-admin obejde tenant scope filter a má root přístup nad celým
-- SaaS systémem. Typicky majitel platformy (Aines) + případně partneři.
CREATE TABLE IF NOT EXISTS `super_admins` (
    `user_id`     BIGINT UNSIGNED NOT NULL,
    `granted_at`  DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `granted_by`  BIGINT UNSIGNED NULL DEFAULT NULL
                  COMMENT 'Kdo super-admin práva udělil (NULL = bootstrap)',
    `notes`       VARCHAR(500) NULL DEFAULT NULL,
    PRIMARY KEY (`user_id`),
    CONSTRAINT `fk_sa_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- 5) BACKFILL — první tenant "Moje firma" (id=1)
-- ─────────────────────────────────────────────────────────────────
-- Subdoména 'app' = výchozí (app.snecinatripu.eu). Až bude self-service
-- registrace, další firmy dostanou vlastní subdomény (pepa, karel, ...).
INSERT IGNORE INTO `tenants` (`id`, `name`, `subdomain`, `plan_code`, `max_users`, `max_contacts`, `active`)
VALUES (1, 'Moje firma', 'app', 'enterprise', NULL, NULL, 1);

INSERT IGNORE INTO `tenant_branding` (`tenant_id`, `display_name`, `primary_color`, `accent_color`)
VALUES (1, 'Šněčí závody', '#3b82f6', '#0e7490');

-- ─────────────────────────────────────────────────────────────────
-- 6) BACKFILL — všichni existující users → tenant 1
-- ─────────────────────────────────────────────────────────────────
-- Každý uživatel se přiřadí do tenant_id=1 se zachováním aktuální role
-- z users.role + roles_extra. Tj. po této migraci se NIC nezmění z pohledu
-- uživatele — Honza pořád vidí to samé co dřív, jen technicky je teď
-- "Honza @ Moje firma".
INSERT IGNORE INTO `user_tenants` (`user_id`, `tenant_id`, `role`, `roles_extra`, `active`, `joined_at`)
SELECT
    u.`id`,
    1 AS tenant_id,
    u.`role`,
    u.`roles_extra`,
    COALESCE(u.`aktivni`, 1) AS active,
    COALESCE(u.`created_at`, NOW(3)) AS joined_at
FROM `users` u
WHERE NOT EXISTS (
    SELECT 1 FROM `user_tenants` ut
    WHERE ut.`user_id` = u.`id` AND ut.`tenant_id` = 1
);

-- ─────────────────────────────────────────────────────────────────
-- 7) BACKFILL — kdokoli má roli 'superadmin' → do super_admins
-- ─────────────────────────────────────────────────────────────────
-- Tj. user co je teď superadmin se po této migraci stane super-adminem
-- (globální přístup). Jeho role 'superadmin' v users zůstává — slouží
-- jako role v rámci tenanta. Globální flag je v `super_admins`.
INSERT IGNORE INTO `super_admins` (`user_id`, `granted_at`, `notes`)
SELECT
    u.`id`,
    COALESCE(u.`created_at`, NOW(3)),
    'Auto-promoted z users.role=superadmin při migraci 031'
FROM `users` u
WHERE u.`role` = 'superadmin';
