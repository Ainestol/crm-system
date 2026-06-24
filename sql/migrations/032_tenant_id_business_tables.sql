-- e:\Snecinatripu\sql\migrations\032_tenant_id_business_tables.sql
-- ════════════════════════════════════════════════════════════════════
-- TENANT_ID NA BUSINESS TABULKÁCH — multi-tenant foundation step 2
--
-- Účel:
--   Přidá sloupec `tenant_id` na všech 40 business tabulkách + index.
--   Backfill: všechna stávající data dostanou tenant_id = 1 (Moje firma).
--
-- Pravidla:
--   - ADD COLUMN tenant_id INT UNSIGNED NOT NULL DEFAULT 1
--   - ADD INDEX idx_xxx_tenant (tenant_id)
--   - Pro nejčastější queries i composite index (tenant_id, stav) apod.
--
-- Kompatibilita:
--   - Funguje na MySQL 8.4 i MariaDB 10.11
--   - Sloupec se přidá na konec (bez AFTER) — bezpečné pro všechny tabulky
--     (i ty bez `id` sloupce s composite PK)
--   - Žádné IF NOT EXISTS — runner zajistí jednorázové spuštění přes tracker
--
-- Po této migraci:
--   - Všechna data jsou označená tenant_id=1
--   - Schéma připravené pro multi-tenant
--   - CRM UI BĚŽÍ STÁLE STEJNĚ (žádný PHP middleware ještě nepoužívá tenant_id)
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ─────────────────────────────────────────────────────────────────
-- Kontakty (core) — 7 tabulek
-- ─────────────────────────────────────────────────────────────────
ALTER TABLE `contacts`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_contacts_tenant` (`tenant_id`),
    ADD INDEX `idx_contacts_tenant_stav` (`tenant_id`, `stav`),
    ADD INDEX `idx_contacts_tenant_region` (`tenant_id`, `region`);

ALTER TABLE `contact_phones`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_phones_tenant` (`tenant_id`);

ALTER TABLE `contact_notes`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_cn_tenant` (`tenant_id`);

ALTER TABLE `contact_proposals`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_cp_tenant` (`tenant_id`);

ALTER TABLE `contact_oz_flags`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_cof_tenant` (`tenant_id`);

ALTER TABLE `contact_quality_ratings`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_cqr_tenant` (`tenant_id`);

ALTER TABLE `contact_recycles`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_cr_tenant` (`tenant_id`);

-- ─────────────────────────────────────────────────────────────────
-- OZ workflow — 5 tabulek
-- ─────────────────────────────────────────────────────────────────
ALTER TABLE `oz_contact_actions`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_oca_tenant` (`tenant_id`);

ALTER TABLE `oz_contact_notes`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_ocn_tenant` (`tenant_id`);

ALTER TABLE `oz_contact_offered_services`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_ocos_tenant` (`tenant_id`);

ALTER TABLE `oz_contact_offered_service_items`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_ocosi_tenant` (`tenant_id`);

ALTER TABLE `oz_contact_workflow`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_ocw_tenant` (`tenant_id`),
    ADD INDEX `idx_ocw_tenant_oz` (`tenant_id`, `oz_id`);

-- ─────────────────────────────────────────────────────────────────
-- Audit / log — 5 tabulek
-- ─────────────────────────────────────────────────────────────────
ALTER TABLE `workflow_log`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_wl_tenant` (`tenant_id`);

ALTER TABLE `audit_log`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_al_tenant` (`tenant_id`);

ALTER TABLE `import_log`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_il_tenant` (`tenant_id`);

ALTER TABLE `assignment_log`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_asl_tenant` (`tenant_id`);

ALTER TABLE `sms_log`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_sl_tenant` (`tenant_id`);

-- ─────────────────────────────────────────────────────────────────
-- Cíle / odměny — 12 tabulek
-- ─────────────────────────────────────────────────────────────────
ALTER TABLE `monthly_goals`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_mg_tenant` (`tenant_id`);

ALTER TABLE `daily_goals`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_dg_tenant` (`tenant_id`);

ALTER TABLE `cisticka_region_goals`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_crg_tenant` (`tenant_id`);

ALTER TABLE `cisticka_rewards_config`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_crc_tenant` (`tenant_id`);

ALTER TABLE `caller_rewards_config`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_carc_tenant` (`tenant_id`);

ALTER TABLE `commissions`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_com_tenant` (`tenant_id`);

ALTER TABLE `commission_tiers_company`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_ctc_tenant` (`tenant_id`);

ALTER TABLE `commission_tiers_sales`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_cts_tenant` (`tenant_id`);

ALTER TABLE `monthly_salaries`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_ms_tenant` (`tenant_id`);

ALTER TABLE `oz_targets`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_ot_tenant` (`tenant_id`);

ALTER TABLE `oz_team_stages`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_ots_tenant` (`tenant_id`);

ALTER TABLE `oz_personal_milestones`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_opm_tenant` (`tenant_id`);

-- ─────────────────────────────────────────────────────────────────
-- Kampaně / premium / rescue — 7 tabulek
-- ─────────────────────────────────────────────────────────────────
ALTER TABLE `bet_campaigns`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_bc_tenant` (`tenant_id`);

ALTER TABLE `bet_campaign_recipients`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_bcr_tenant` (`tenant_id`);

ALTER TABLE `bet_campaign_callers`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_bcc_tenant` (`tenant_id`);

ALTER TABLE `bet_campaign_leads`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_bcl_tenant` (`tenant_id`);

ALTER TABLE `premium_orders`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_po_tenant` (`tenant_id`);

ALTER TABLE `premium_lead_pool`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_plp_tenant` (`tenant_id`);

ALTER TABLE `rescue_requests`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_rr_tenant` (`tenant_id`);

-- ─────────────────────────────────────────────────────────────────
-- Settings / per-firma konfigurace — 7 tabulek
-- ─────────────────────────────────────────────────────────────────
ALTER TABLE `app_settings`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_as_tenant` (`tenant_id`);

ALTER TABLE `note_templates`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_nt_tenant` (`tenant_id`);

ALTER TABLE `dnc_list`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_dnc_tenant` (`tenant_id`);

ALTER TABLE `alerts`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_alerts_tenant` (`tenant_id`);

ALTER TABLE `announcements`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_ann_tenant` (`tenant_id`);

ALTER TABLE `team_records`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_tr_tenant` (`tenant_id`);

ALTER TABLE `oz_tab_prefs`
    ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
    ADD INDEX `idx_otp_tenant` (`tenant_id`);
