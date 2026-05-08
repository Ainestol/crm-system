-- e:\Snecinatripu\sql\migrations\009_premium_pipeline.sql
-- ════════════════════════════════════════════════════════════════════
-- PREMIUM PIPELINE — objednávky druhého čištění
--
-- Princip: OZ si u čističky objedná „druhé kolo" čištění už jednou
-- pročištěných leadů (stav READY). Systém leady zarezervuje (zamkne)
-- pro daného OZ — ostatní OZ ani navolávačky je nevidí, dokud čistička
-- neoznačí výsledek.
--
-- Dvě tabulky (header + lines pattern):
--   1) premium_orders   = HLAVIČKA objednávky (kdo, kdy, za kolik, kolik)
--   2) premium_lead_pool = LINES — konkrétní contact_id v dané objednávce
--                          + jejich cleaning/call status
--
-- Spuštění lokálně:
--   mysql -u root -p crm < E:\Snecinatripu\sql\migrations\009_premium_pipeline.sql
--
-- Spuštění na serveru (po git pull):
--   sudo mariadb crm < /var/www/crm/sql/migrations/009_premium_pipeline.sql
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ────────────────────────────────────────────────────────────────────
-- 1) premium_orders — hlavička objednávky
-- ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `premium_orders` (
  `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Kdo objednal
  `oz_id`                 BIGINT UNSIGNED NOT NULL COMMENT 'Objednavatel (role obchodak)',

  -- Období objednávky (na který měsíc)
  `year`                  SMALLINT UNSIGNED NOT NULL,
  `month`                 TINYINT UNSIGNED NOT NULL,

  -- Kolik OZ chce vs. kolik se reálně podařilo zarezervovat
  `requested_count`       INT UNSIGNED NOT NULL COMMENT 'Kolik leadů OZ objednal',
  `reserved_count`        INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Kolik se reálně zarezervovalo (může být < requested, dorezervuje se postupně)',

  -- Cena za druhé čištění (platí OZ čističce)
  `price_per_lead`        DECIMAL(8,2) NOT NULL COMMENT 'Kolik Kč OZ platí čističce za jeden vyčištěný lead',

  -- Bonus pro navolávačku za úspěšný hovor (0 = bez bonusu, OZ platí jen základní sazbu majitele)
  `caller_bonus_per_lead` DECIMAL(8,2) NOT NULL DEFAULT 0 COMMENT 'Bonus pro navolávačku za úspěšný hovor (0 = nic navíc, navolávačka pak nic nevidí)',

  -- Která navolávačka má premium leady volat (NULL = rotace mezi všemi)
  `preferred_caller_id`   BIGINT UNSIGNED NULL COMMENT 'NULL = rotace; jinak jen tato navolávačka',

  -- Filtr regionů (NULL = všechny regiony, jinak JSON array kódů)
  `regions_json`          JSON NULL COMMENT 'NULL = všechny; jinak ["PHA","STC",...]',

  -- Workflow stav objednávky
  `status`                ENUM('open','cancelled','closed') NOT NULL DEFAULT 'open'
                          COMMENT 'open = běží; cancelled = zrušil OZ; closed = uzavřena (vše dotaženo)',

  -- Volitelná poznámka (např. „rychlovka pro výběrko v Praze")
  `note`                  TEXT NULL,

  `created_at`            DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at`            DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),

  PRIMARY KEY (`id`),
  KEY `idx_po_oz_period`     (`oz_id`, `year`, `month`),
  KEY `idx_po_status`         (`status`),
  KEY `idx_po_caller`         (`preferred_caller_id`),
  KEY `idx_po_created`        (`created_at`),

  CONSTRAINT `fk_po_oz`
    FOREIGN KEY (`oz_id`)               REFERENCES `users`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_po_preferred_caller`
    FOREIGN KEY (`preferred_caller_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Premium objednávky druhého čištění — HEADER';


-- ────────────────────────────────────────────────────────────────────
-- 2) premium_lead_pool — leady v objednávce (lines)
--
-- UNIQUE (contact_id) — jeden contact může být v premium pipeline jen
-- jednou napříč celou historií. Když je označen non_tradeable, řádek
-- tam zůstává jako historický zákaz (nelze ho znovu objednat do
-- premium kola).
-- ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `premium_lead_pool` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Vazba na objednávku
  `order_id`          BIGINT UNSIGNED NOT NULL,
  `contact_id`        BIGINT UNSIGNED NOT NULL,

  -- Duplikát z premium_orders.oz_id pro rychlé filtry (bez JOINu)
  `oz_id`             BIGINT UNSIGNED NOT NULL,

  -- Kdo lead čistil (po druhém čištění) / volal (po hovoru)
  `cleaner_id`        BIGINT UNSIGNED NULL,
  `caller_id`         BIGINT UNSIGNED NULL,

  -- Stav druhého čištění
  --   pending       = čeká na čističku
  --   tradeable     = obchodovatelný (jde do queue navolávačky)
  --   non_tradeable = neobchodovatelný (vrací se do běžného poolu, ale už ne do premium)
  `cleaning_status`   ENUM('pending','tradeable','non_tradeable') NOT NULL DEFAULT 'pending',

  -- Stav hovoru navolávačky (pouze pro tradeable leady)
  --   pending = čeká na hovor
  --   success = úspěšně navoláno (CALLED_OK na contact)
  --   failed  = neúspěch (NEZAJEM, NEDOVOLANO, atd. — viz contacts.stav)
  `call_status`       ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',

  -- Reklamace OZ — když OZ označí lead jako vadný, čistička za něj nedostane
  `flagged_for_refund` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'OZ reklamoval — neplatí čističce',
  `flag_reason`       VARCHAR(500) NULL,
  `flagged_at`        DATETIME(3) NULL,

  -- Časové stopy
  `reserved_at`       DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `cleaned_at`        DATETIME(3) NULL COMMENT 'Kdy čistička označila tradeable/non_tradeable',
  `called_at`         DATETIME(3) NULL COMMENT 'Kdy navolávačka uzavřela call_status',

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pool_contact`         (`contact_id`),
  KEY        `idx_pool_order_clean`    (`order_id`, `cleaning_status`),
  KEY        `idx_pool_oz_call`        (`oz_id`, `call_status`),
  KEY        `idx_pool_cleaner_month`  (`cleaner_id`, `cleaned_at`),
  KEY        `idx_pool_caller_month`   (`caller_id`, `called_at`),

  CONSTRAINT `fk_pool_order`
    FOREIGN KEY (`order_id`)   REFERENCES `premium_orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pool_contact`
    FOREIGN KEY (`contact_id`) REFERENCES `contacts`(`id`)        ON DELETE CASCADE,
  CONSTRAINT `fk_pool_oz`
    FOREIGN KEY (`oz_id`)      REFERENCES `users`(`id`)           ON DELETE RESTRICT,
  CONSTRAINT `fk_pool_cleaner`
    FOREIGN KEY (`cleaner_id`) REFERENCES `users`(`id`)           ON DELETE SET NULL,
  CONSTRAINT `fk_pool_caller`
    FOREIGN KEY (`caller_id`)  REFERENCES `users`(`id`)           ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Premium pipeline LINES — leady v objednávce + jejich stav';
