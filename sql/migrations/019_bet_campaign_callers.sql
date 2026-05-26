-- e:\Snecinatripu\sql\migrations\019_bet_campaign_callers.sql
-- ════════════════════════════════════════════════════════════════════
-- SÁZKY — přiřazení navolávaček (NEW)
--
-- Účel:
--   Doplnit do flow sázky možnost admin/majitele EXPLICITNĚ vybrat,
--   kteří navolávači pracují na konkrétní sázce.
--
--   Bez tohoto opt-in: sázkový kontakt by se objevil v anonymním poolu
--   /caller — jakákoliv navolávačka by ho mohla zamknout. To není žádoucí —
--   admin chce mít kontrolu nad tím, kdo na sázce dělá.
--
--   S touto tabulkou:
--     - Admin při zakládání sázky vybere konkrétní navolávačky
--     - Pouze ty navolávačky uvidí sázku v /caller/campaigns
--     - Sázkový kontakt JE VYJMUTÝ z anonymního poolu /caller (zařízeno
--       SQL exkluzí v CallerController)
--
-- Stejný princip jako recipients (OZ), ale bez target_count — navolávači
-- nejsou „kvótováni", jen oprávněni.
--
-- Bezpečnost:
--   - 100% idempotentní (CREATE TABLE IF NOT EXISTS)
--   - Žádný UPDATE / DELETE / DROP dat
--   - Existující sázky bez recipients-callers normálně poběží (ošetříme v controlleru:
--     pokud bet_campaign_callers nemá záznam pro sázku → caller pool exkluze platí,
--     ale nikdo nevidí sázku v /caller/campaigns. Admin musí přidat zpětně.)
--
-- Spuštění lokálně:
--   mysql -u root -p crm < E:\Snecinatripu\sql\migrations\019_bet_campaign_callers.sql
--
-- Spuštění na serveru (po git pull):
--   sudo mariadb crm < /var/www/crm/sql/migrations/019_bet_campaign_callers.sql
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `bet_campaign_callers` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  `campaign_id` BIGINT UNSIGNED NOT NULL,
  `caller_id`   BIGINT UNSIGNED NOT NULL COMMENT 'users.id (role=caller)',

  `assigned_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bcc_campaign_caller` (`campaign_id`, `caller_id`),
  KEY        `idx_bcc_caller`         (`caller_id`),

  CONSTRAINT `fk_bcc_campaign`
    FOREIGN KEY (`campaign_id`) REFERENCES `bet_campaigns`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bcc_caller`
    FOREIGN KEY (`caller_id`)   REFERENCES `users`(`id`)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Sázky — navolávači oprávnění pracovat na call-type leadech kampaně';
