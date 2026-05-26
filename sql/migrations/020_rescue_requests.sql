-- e:\Snecinatripu\sql\migrations\020_rescue_requests.sql
-- ════════════════════════════════════════════════════════════════════
-- ZÁCHRANA LEADU (rescue_requests)
--
-- Účel:
--   OZ má navolaný kontakt (CALLED_OK), zákazník ale nezvedá / nereaguje.
--   Místo aby OZ označil lead jako CHYBNY (a navolávačka kontaktu nedostala
--   ani standardní bonus), OZ ho předá zpět navolávačce na ZÁCHRANU:
--
--     • Navolávačka má 14 dní zachránit (znovu provolat, ujistit se o zájmu)
--     • Pokud zachrání → lead se vrátí OZ (původnímu nebo jinému)
--       → navolávačka dostane bonus = bmsl (= 1× měsíční hodnota smlouvy),
--         ALE až když OZ smlouvu skutečně podepíše (podpis_potvrzen=1)
--     • Pokud expiruje (14 dní bez akce) → záchrana se uzavře jako "expired"
--       → OZ NEZAPLATÍ ~200 Kč za původní navolávání
--       → navolávačka která lead původně dodala přijde o ten bonus (clawback)
--
-- Princip:
--   • UNIQUE contact_id — kontakt může jít na záchranu jen 1× v životě
--   • expires_at = requested_at + INTERVAL 14 DAY (auto-set v aplikaci)
--   • bonus_amount se vyplní AŽ při podpis_potvrzen=1 (hook v OzController)
--   • bonus_paid_at se vyplní manuálně adminem ("vyplaceno navolávačce")
--
-- Vztah k peněženkám:
--   • caller payout: + bonus_amount (sekce "Bonusy ze záchran")
--   • original_caller (kdo dodal lead OZ): - 200 Kč pokud rescue.outcome=expired
--   • OZ payout: - bonus_amount (sekce "Dlužné za záchrany") + úspora 200 Kč za expired
--
-- Spuštění lokálně:
--   mysql -u root crm < E:\Snecinatripu\sql\migrations\020_rescue_requests.sql
--
-- Spuštění na serveru:
--   sudo mariadb crm < /var/www/crm/sql/migrations/020_rescue_requests.sql
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `rescue_requests` (
  `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Kontakt, který je v záchraně (UNIQUE = max 1× v životě)
  `contact_id`           BIGINT UNSIGNED NOT NULL,

  -- Kdo poslal na záchranu (původní OZ — Markéta v příkladu)
  `original_sales_id`    BIGINT UNSIGNED NOT NULL,

  -- Kdo by měl dostat lead po úspěšné záchraně:
  --   NOT NULL = konkrétní OZ (vybral si někoho)
  --   NULL     = rotace mezi všemi (nebo vrátit původnímu — řeší prefer_original)
  `target_sales_id`      BIGINT UNSIGNED NULL,

  -- TRUE = primárně vrátit původnímu OZ (Markétě). Pokud target_sales_id NULL
  -- a prefer_original=1 → po záchraně lead jde k original_sales_id.
  -- prefer_original=0 + target_sales_id NULL = rotace mezi všemi OZ.
  `prefer_original`      TINYINT(1) NOT NULL DEFAULT 1,

  -- Důvod proč OZ posílá na záchranu (textarea v modalu)
  `reason`               VARCHAR(500) NOT NULL,

  -- Kdo lead původně dodal OZ (= caller co dostal standardní bonus za CALLED_OK).
  -- Pokud rescue expires, této navolávačce se v payout odečte ~200 Kč.
  `original_caller_id`   BIGINT UNSIGNED NULL,

  -- Časování
  `requested_at`         DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `expires_at`           DATETIME(3) NOT NULL,
  `rescued_at`           DATETIME(3) NULL,
  `expired_at`           DATETIME(3) NULL,

  -- Která navolávačka záchranu provedla
  `rescued_by_caller_id` BIGINT UNSIGNED NULL,

  -- Komu byl lead nakonec přidělen (= final assigned_sales_id po success)
  `final_sales_id`       BIGINT UNSIGNED NULL,

  -- Stav záchrany
  `outcome`              ENUM('pending','success','failed','expired') NOT NULL DEFAULT 'pending'
                         COMMENT 'pending=běží, success=zachráněno, failed=caller neuspěl (nezájem/nedovoláno), expired=14 dní uplynulo',

  -- Bonus pro zachraňující navolávačku — vyplněno až když OZ podepíše smlouvu
  `bonus_amount`         DECIMAL(10,2) NULL COMMENT '1× bmsl (měsíční hodnota smlouvy)',
  `bonus_locked_at`      DATETIME(3) NULL COMMENT 'kdy se bonus stal "earned" (= podpis_potvrzen)',
  `bonus_paid_at`        DATETIME(3) NULL COMMENT 'kdy navolávačka skutečně dostala vyplaceno (manual)',
  `bonus_paid_by`        BIGINT UNSIGNED NULL COMMENT 'majitel/admin který kliknul "vyplaceno"',

  -- Audit
  `notes`                TEXT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rr_contact`        (`contact_id`),
  KEY        `idx_rr_orig_sales`    (`original_sales_id`),
  KEY        `idx_rr_outcome`       (`outcome`),
  KEY        `idx_rr_expires`       (`expires_at`),
  KEY        `idx_rr_rescued_by`    (`rescued_by_caller_id`),
  KEY        `idx_rr_orig_caller`   (`original_caller_id`),
  KEY        `idx_rr_bonus_pending` (`outcome`, `bonus_amount`, `bonus_paid_at`),

  CONSTRAINT `fk_rr_contact`
    FOREIGN KEY (`contact_id`)           REFERENCES `contacts`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rr_original_sales`
    FOREIGN KEY (`original_sales_id`)    REFERENCES `users`(`id`)     ON DELETE RESTRICT,
  CONSTRAINT `fk_rr_target_sales`
    FOREIGN KEY (`target_sales_id`)      REFERENCES `users`(`id`)     ON DELETE SET NULL,
  CONSTRAINT `fk_rr_original_caller`
    FOREIGN KEY (`original_caller_id`)   REFERENCES `users`(`id`)     ON DELETE SET NULL,
  CONSTRAINT `fk_rr_rescued_by`
    FOREIGN KEY (`rescued_by_caller_id`) REFERENCES `users`(`id`)     ON DELETE SET NULL,
  CONSTRAINT `fk_rr_final_sales`
    FOREIGN KEY (`final_sales_id`)       REFERENCES `users`(`id`)     ON DELETE SET NULL,
  CONSTRAINT `fk_rr_bonus_paid_by`
    FOREIGN KEY (`bonus_paid_by`)        REFERENCES `users`(`id`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Záchrana leadu — OZ pošle nereagujícího zákazníka zpět navolávačce';

-- ────────────────────────────────────────────────────────────────────
-- POZN: nový stav `RESCUE_REQUESTED` v contacts.stav (VARCHAR(40))
--   Není potřeba ALTER — contacts.stav je VARCHAR a stačí ho začít používat.
--   Význam: OZ poslal lead na záchranu, čeká se na navolávačku.
--   OZ ho ve své práci VIDÍ (read-only, badge "🆘 v záchraně") ale needitovat.
-- ────────────────────────────────────────────────────────────────────
