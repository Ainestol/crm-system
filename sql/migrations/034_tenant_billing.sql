-- e:\Snecinatripu\sql\migrations\034_tenant_billing.sql
-- ════════════════════════════════════════════════════════════════════
-- TENANT BILLING & PLANS — předplatné, limity, platby
--
-- Účel:
--   Rozšíří tenant infrastrukturu o:
--     1. Katalog plánů (Free / Starter / Business / Enterprise)
--     2. Sledování plateb per tenant (kdo zaplatil kolik a do kdy)
--     3. Další business pole na tenants (email majitele, měsíční cena, poznámka)
--     4. Limit na premium objednávky/měsíc (kromě users a contacts)
--
-- Co tato migrace NEDĚLÁ:
--   - Nemění žádná stávající data
--   - Tenant 1 zůstává enterprise s NULL limity (unlimited)
--   - Tenant 2 (test firma) si limity zachová z migrace 031
--
-- Plán katalog:
--   free       — 1 user, 50 kontaktů, 0 premium, trial 14 dní, 0 Kč
--   starter    — 3 uživatelé, 500 kontaktů, 5 premium/měsíc, 290 Kč/m
--   business   — 6 uživatelů, 2000 kontaktů, 20 premium/měsíc, 590 Kč/m
--   enterprise — unlimited (NULL všude), individuální cena
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ─────────────────────────────────────────────────────────────────
-- 1) Rozšíření `tenants` o billing pole
-- ─────────────────────────────────────────────────────────────────
ALTER TABLE `tenants`
    ADD COLUMN `email_owner`                 VARCHAR(200) NULL DEFAULT NULL
        COMMENT 'Kontakt na majitele firmy (pro fakturaci/komunikaci)',
    ADD COLUMN `monthly_price_czk`           DECIMAL(10,2) NULL DEFAULT NULL
        COMMENT 'Skutečná měsíční cena (může se lišit od ceny v tenant_plans)',
    ADD COLUMN `paid_until`                  DATE NULL DEFAULT NULL
        COMMENT 'Datum do kterého má zaplaceno (NULL = neplatí / trial)',
    ADD COLUMN `max_premium_orders_per_month` INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'Limit premium objednávek za měsíc. NULL = unlimited',
    ADD COLUMN `admin_notes`                 TEXT NULL DEFAULT NULL
        COMMENT 'Interní poznámka super-admina (zákazník nevidí)';

-- ─────────────────────────────────────────────────────────────────
-- 2) `tenant_plans` — katalog předplatných plánů
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tenant_plans` (
    `id`                            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug`                          VARCHAR(50)  NOT NULL
                                    COMMENT 'Identifier (= tenants.plan_code), např. "starter"',
    `name`                          VARCHAR(100) NOT NULL
                                    COMMENT 'Zobrazované jméno (např. "Starter")',
    `description`                   VARCHAR(500) NULL DEFAULT NULL,
    `max_users`                     INT UNSIGNED NULL DEFAULT NULL
                                    COMMENT 'Default limit uživatelů (NULL = unlimited)',
    `max_contacts`                  INT UNSIGNED NULL DEFAULT NULL
                                    COMMENT 'Default limit kontaktů (NULL = unlimited)',
    `max_premium_orders_per_month`  INT UNSIGNED NULL DEFAULT NULL
                                    COMMENT 'Default limit premium objednávek za měsíc',
    `monthly_price_czk`             DECIMAL(10,2) NOT NULL DEFAULT 0
                                    COMMENT 'Standardní cena za měsíc (0 = free)',
    `trial_days`                    INT UNSIGNED NOT NULL DEFAULT 0
                                    COMMENT 'Délka trial období v dnech (0 = bez trialu)',
    `sort_order`                    INT NOT NULL DEFAULT 0
                                    COMMENT 'Pořadí v UI (od nejlevnějšího)',
    `active`                        TINYINT(1) NOT NULL DEFAULT 1
                                    COMMENT 'Pokud 0, plán se nezobrazuje v signup',
    `created_at`                    DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updated_at`                    DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_plans_slug` (`slug`),
    KEY `idx_plans_active_sort` (`active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed 4 standardních plánů (idempotentně přes INSERT IGNORE)
INSERT IGNORE INTO `tenant_plans`
    (`slug`, `name`, `description`, `max_users`, `max_contacts`, `max_premium_orders_per_month`, `monthly_price_czk`, `trial_days`, `sort_order`)
VALUES
    ('free',       'Free',       'Pro vyzkoušení — 14 dní zdarma',                    1,    50, 0,    0.00, 14, 1),
    ('starter',    'Starter',    'Pro malé týmy',                                     3,   500, 5,  290.00,  0, 2),
    ('business',   'Business',   'Pro rostoucí firmy',                                6,  2000, 20, 590.00,  0, 3),
    ('enterprise', 'Enterprise', 'Bez limitů — individuální cena (kontaktujte nás)', NULL, NULL, NULL, 0.00,  0, 4);

-- ─────────────────────────────────────────────────────────────────
-- 3) `tenant_payments` — historie plateb per tenant
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tenant_payments` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`       INT UNSIGNED NOT NULL,
    `amount_czk`      DECIMAL(10,2) NOT NULL,
    `paid_at`         DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
                      COMMENT 'Kdy platba reálně dorazila',
    `period_from`     DATE NOT NULL
                      COMMENT 'Od kdy platí (typicky paid_at den)',
    `period_until`    DATE NOT NULL
                      COMMENT 'Do kdy platí (typicky paid_at + 1 měsíc)',
    `invoice_number`  VARCHAR(100) NULL DEFAULT NULL
                      COMMENT 'Číslo faktury (volitelné, pro pohodlí)',
    `payment_method`  VARCHAR(50) NULL DEFAULT NULL
                      COMMENT 'bank_transfer / card / cash / other',
    `recorded_by`     BIGINT UNSIGNED NULL DEFAULT NULL
                      COMMENT 'Kdo platbu zapsal (NULL = automatika)',
    `notes`           TEXT NULL DEFAULT NULL,
    `created_at`      DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (`id`),
    KEY `idx_tp_tenant`    (`tenant_id`, `paid_at`),
    KEY `idx_tp_recorded`  (`recorded_by`),
    CONSTRAINT `fk_tp_tenant` FOREIGN KEY (`tenant_id`)
        REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tp_recorded_by` FOREIGN KEY (`recorded_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- 4) Backfill — tenant 1 je 'enterprise' bez limitu (jak má z 031),
--    tenant 2 (test firma) si nechá svoje limity, jen aktualizujeme plan_code
-- ─────────────────────────────────────────────────────────────────
-- Tenant 1 — enterprise (NULL limity = unlimited)
UPDATE `tenants`
   SET `plan_code` = 'enterprise',
       `max_users` = NULL,
       `max_contacts` = NULL,
       `max_premium_orders_per_month` = NULL
 WHERE `id` = 1;

-- Pro ostatní tenanty: pokud mají NULL plan_code, dej jim 'free'
UPDATE `tenants`
   SET `plan_code` = 'free'
 WHERE `plan_code` IS NULL OR `plan_code` = '';
