-- e:\Snecinatripu\sql\migrations\016_bet_campaigns.sql
-- ════════════════════════════════════════════════════════════════════
-- SÁZKY — kampaně na cílený počet vyčištěných kontaktů + rozdělení mezi OZ
--
-- Princip:
--   Admin vytvoří sázku: "Vyčistit 300 použitelných (TM+O2) v Praze".
--   Z toho rozdělit chronologicky: prvních 100 OZ A (call),
--   dalších 200 OZ B (email).
--
--   Cisticka v dashboardu vidí progress (např. 47/300). STRICT MODE:
--   dokud je sázka aktivní v jejím kraji, vidí JEN kontakty z toho kraje.
--
--   Po každém TM/O2 verify:
--     - kontakt se zapíše do bet_campaign_leads s pořadovým číslem (position)
--     - chronologicky se mu přiřadí recipient podle target_count + sort_order
--     - pokud recipient = call:  assigned_sales_id = OZ A, stav zůstane READY
--     - pokud recipient = email: assigned_sales_id = OZ B, stav = 'EMAIL_READY'
--                                 (přeskočí caller pool, jde rovnou OZ)
--
--   Sázka se uzavře:
--     - automaticky když cleaned_count = target_count
--     - manuálně adminem dříve (částečné uzavření; recipienti dostanou
--       jen tolik, kolik se stihlo vyčistit, ale chronologicky korektně)
--
-- Tři tabulky (header + recipients + leads pattern):
--   1) bet_campaigns           = HLAVIČKA sázky
--   2) bet_campaign_recipients = příjemci (kdo dostane kolik leadů + typ)
--   3) bet_campaign_leads      = konkrétní vyčištěné kontakty + position
--
-- Spuštění lokálně:
--   mysql -u root -p crm < E:\Snecinatripu\sql\migrations\016_bet_campaigns.sql
--
-- Spuštění na serveru (po git pull):
--   sudo mariadb crm < /var/www/crm/sql/migrations/016_bet_campaigns.sql
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ────────────────────────────────────────────────────────────────────
-- 1) bet_campaigns — hlavička sázky
-- ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `bet_campaigns` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Pojmenování (např. "Sázka majitele 5/2026")
  `name`           VARCHAR(200) NOT NULL,

  -- Kraj, kde se sáčí (jeden kraj per sázka)
  `region`         VARCHAR(50) NOT NULL COMMENT 'Kód kraje (např. "praha", "stredocesky")',

  -- Cílový počet vyčištěných kontaktů (TM + O2 dohromady)
  `target_count`   INT UNSIGNED NOT NULL,

  -- Aktuálně dosažené (denormalizace pro rychlý progress bar)
  `cleaned_count`  INT UNSIGNED NOT NULL DEFAULT 0,

  -- Workflow stav sázky
  `status`         ENUM('open','closed','cancelled') NOT NULL DEFAULT 'open'
                   COMMENT 'open = běží; closed = dotaženo (auto/manual); cancelled = zrušil admin',

  -- Volitelná poznámka
  `note`           TEXT NULL,

  `created_by`     BIGINT UNSIGNED NULL,
  `created_at`     DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `closed_at`      DATETIME(3) NULL,

  PRIMARY KEY (`id`),
  KEY `idx_bc_region_status` (`region`, `status`),
  KEY `idx_bc_status`        (`status`),
  KEY `idx_bc_created`       (`created_at`),

  CONSTRAINT `fk_bc_creator`
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Sázky — kampaně na X vyčištěných kontaktů';


-- ────────────────────────────────────────────────────────────────────
-- 2) bet_campaign_recipients — příjemci sázky (kdo dostane kolik leadů + typ)
--
-- Příklad pro sázku 300:
--   row 1: oz_id=majitel, target_count=100, delivery_type=call,  sort_order=1
--   row 2: oz_id=druhy,   target_count=200, delivery_type=email, sort_order=2
--
-- UNIQUE (campaign_id, sort_order) — pořadí v rámci sázky je unikátní,
-- určuje chronologii: prvních N leadů jde sort_order=1, dalších M jde
-- sort_order=2, atd.
-- ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `bet_campaign_recipients` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  `campaign_id`    BIGINT UNSIGNED NOT NULL,
  `oz_id`          BIGINT UNSIGNED NOT NULL COMMENT 'Komu se kontakty přiřadí',

  -- Kolik tento příjemce dostane (součet všech recipients = target_count sázky)
  `target_count`   INT UNSIGNED NOT NULL,

  -- Kolik už dostal (denormalizace)
  `received_count` INT UNSIGNED NOT NULL DEFAULT 0,

  -- Typ doručení:
  --   call  = kontakt zůstane stav=READY, navolávačka volá, po Výhra → FOR_SALES
  --   email = kontakt přeskočí caller pool, stav=EMAIL_READY,
  --           OZ ho má v sekci /oz/email-leads (export do XLSX)
  `delivery_type`  ENUM('call','email') NOT NULL DEFAULT 'call',

  -- Pořadí v rámci sázky (1, 2, 3, ...) určuje chronologii přiřazení
  `sort_order`     SMALLINT UNSIGNED NOT NULL DEFAULT 1,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bcr_campaign_sort` (`campaign_id`, `sort_order`),
  KEY `idx_bcr_oz` (`oz_id`),

  CONSTRAINT `fk_bcr_campaign`
    FOREIGN KEY (`campaign_id`) REFERENCES `bet_campaigns`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bcr_oz`
    FOREIGN KEY (`oz_id`)       REFERENCES `users`(`id`)         ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Příjemci sázky — kdo dostane kolik leadů jakého typu';


-- ────────────────────────────────────────────────────────────────────
-- 3) bet_campaign_leads — vyčištěné kontakty zařazené do sázky
--
-- UNIQUE (contact_id) — kontakt může být zařazen do max 1 sázky napříč
-- celou historií (nedělej "double-counting").
--
-- position = chronologické pořadí v rámci sázky (1, 2, 3, ...). Použito
-- k určení, kterému recipient kontakt patří (kumulativní target_count).
-- ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `bet_campaign_leads` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  `campaign_id`   BIGINT UNSIGNED NOT NULL,
  `contact_id`    BIGINT UNSIGNED NOT NULL,
  `recipient_id`  BIGINT UNSIGNED NOT NULL COMMENT 'Komu kontakt patří (FK na recipients)',

  -- Chronologické pořadí: 1, 2, 3, ... v rámci sázky
  `position`      INT UNSIGNED NOT NULL,

  `cleaned_by`    BIGINT UNSIGNED NULL COMMENT 'Která čistička',
  `cleaned_at`    DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bcl_contact`             (`contact_id`),
  UNIQUE KEY `uq_bcl_campaign_position`   (`campaign_id`, `position`),
  KEY        `idx_bcl_campaign_recipient` (`campaign_id`, `recipient_id`),
  KEY        `idx_bcl_recipient_clean`    (`recipient_id`, `cleaned_at`),

  CONSTRAINT `fk_bcl_campaign`
    FOREIGN KEY (`campaign_id`)  REFERENCES `bet_campaigns`(`id`)            ON DELETE CASCADE,
  CONSTRAINT `fk_bcl_recipient`
    FOREIGN KEY (`recipient_id`) REFERENCES `bet_campaign_recipients`(`id`)  ON DELETE CASCADE,
  CONSTRAINT `fk_bcl_contact`
    FOREIGN KEY (`contact_id`)   REFERENCES `contacts`(`id`)                  ON DELETE CASCADE,
  CONSTRAINT `fk_bcl_cleaner`
    FOREIGN KEY (`cleaned_by`)   REFERENCES `users`(`id`)                     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Konkrétní kontakty v sázce + jejich chronologie';


-- ────────────────────────────────────────────────────────────────────
-- POZN: nový stav `EMAIL_READY` v contacts.stav (VARCHAR(40))
--   Není potřeba ALTER — stav je VARCHAR, jen ho budeme používat v queries.
--   Význam: kontakt přeskočil caller pool, OZ ho má v /oz/email-leads.
-- ────────────────────────────────────────────────────────────────────
