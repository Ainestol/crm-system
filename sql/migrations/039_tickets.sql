-- e:\Snecinatripu\sql\migrations\039_tickets.sql
-- ════════════════════════════════════════════════════════════════════
-- TICKET SYSTÉM v1 — interní pomoc / požadavky
--
-- Účel:
--   Jakákoli role (čistička, navolávačka, OZ, BO…) může založit ticket
--   (nahlásit problém / požadavek). Stavy: open → in_progress → resolved
--   s časovými značkami. Ticket řeší jen majitel/superadmin.
--
--   - Každý uživatel vidí svoje tickety.
--   - Majitel vidí všechny tickety své firmy.
--   - Superadmin vidí všechny tickety napříč firmami (s filtrem na firmu).
--
-- Filozofie:
--   - Tenant-aware (tenant_id, TenantAwarePDO auto-inject ve whitelistu).
--   - Priorita low/medium/high.
--   - Žádné mazání — uzavřený ticket zůstává (resolved) pro historii.
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tickets` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`      INT UNSIGNED NOT NULL,
    `created_by`     BIGINT UNSIGNED NOT NULL
                     COMMENT 'Uživatel, který ticket založil',
    `creator_role`   VARCHAR(50) NULL
                     COMMENT 'Snapshot role zakladatele v okamžiku založení',
    `subject`        VARCHAR(200) NOT NULL,
    `body`           TEXT NULL
                     COMMENT 'Popis problému / požadavku',
    `priority`       ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    `status`         ENUM('open','in_progress','resolved') NOT NULL DEFAULT 'open',
    `assigned_to`    BIGINT UNSIGNED NULL
                     COMMENT 'Admin, který ticket převzal / řeší',
    `resolution`     TEXT NULL
                     COMMENT 'Poznámka admina při řešení / uzavření',
    `in_progress_at` DATETIME(3) NULL
                     COMMENT 'Kdy přešel do in_progress',
    `resolved_at`    DATETIME(3) NULL
                     COMMENT 'Kdy byl vyřešen',
    `created_at`     DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updated_at`     DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
                     ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (`id`),
    KEY `idx_tk_tenant_status`   (`tenant_id`, `status`, `created_at`),
    KEY `idx_tk_tenant_creator`  (`tenant_id`, `created_by`, `created_at`),
    KEY `idx_tk_tenant_priority` (`tenant_id`, `priority`, `created_at`),
    KEY `idx_tk_status_created`  (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- Přílohy ticketů — obrázky (výřezy printscreenu) vložené přes Ctrl+V.
-- Soubory leží PRIVÁTNĚ ve storage/tickets/<tenant>/, servírují se přes
-- PHP s kontrolou práv (ne přímo z webu).
--
-- ticket_id NULL = staged upload (nahráno během psaní, ještě bez ticketu);
-- po založení ticketu se spáruje přes upload_token + uploaded_by.
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ticket_attachments` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `ticket_id`    BIGINT UNSIGNED NULL,
    `upload_token` VARCHAR(64) NULL
                   COMMENT 'Páruje staged uploady k zakládanému ticketu',
    `uploaded_by`  BIGINT UNSIGNED NOT NULL,
    `filename`     VARCHAR(255) NOT NULL
                   COMMENT 'Jméno souboru na disku (storage/tickets/<tenant>/)',
    `orig_name`    VARCHAR(255) NULL,
    `mime`         VARCHAR(100) NOT NULL,
    `size_bytes`   INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`   DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (`id`),
    KEY `idx_ta_ticket` (`ticket_id`),
    KEY `idx_ta_token`  (`upload_token`),
    KEY `idx_ta_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
