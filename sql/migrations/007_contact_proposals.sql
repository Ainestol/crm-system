-- e:\Snecinatripu\sql\migrations\007_contact_proposals.sql
-- ════════════════════════════════════════════════════════════════════
-- Tabulka pro NÁVRHY nových kontaktů.
--
-- Princip: kdokoliv (s rolí) může navrhnout kontakt, ale teprve po
-- schválení majitelem/superadminem se z něj vytvoří řádek v `contacts`.
-- Tato tabulka je tedy jen "čekárna" — zachovává návrh, autora,
-- review historii. Po schválení se řádek v `contacts` chová jako každý
-- jiný kontakt (žádné speciální flagy).
--
-- Stejné sloupce kontaktu jako `contacts` (firma/email/telefon/ico/
-- adresa/region/operator/poznamka) — aby se daly 1:1 zkopírovat
-- při schválení.
-- ════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `contact_proposals` (
  `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Kdo návrh podal (auto z $user['id'] při POST /contacts/new)
  `proposed_by_user_id`   BIGINT UNSIGNED NOT NULL,

  -- Data kontaktu — stejné názvy a typy jako v `contacts`
  `firma`                 VARCHAR(500) NOT NULL DEFAULT '',
  `email`                 VARCHAR(255) NULL DEFAULT NULL,
  `telefon`               VARCHAR(50)  NULL DEFAULT NULL,
  `ico`                   VARCHAR(20)  NULL DEFAULT NULL,
  `adresa`                VARCHAR(500) NULL DEFAULT NULL,
  `region`                VARCHAR(64)  NOT NULL,
  `operator`              VARCHAR(100) NULL DEFAULT NULL,
  `poznamka`              TEXT         NULL,

  -- Návrh, kterému OZ-ovi by měl být po schválení přiřazen
  -- (volitelné — majitel může změnit při schválení)
  `suggested_oz_id`       BIGINT UNSIGNED NULL DEFAULT NULL,

  -- Workflow stav návrhu
  `status`                ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',

  -- Audit schválení/zamítnutí
  `reviewed_by_user_id`   BIGINT UNSIGNED NULL DEFAULT NULL,
  `reviewed_at`           DATETIME(3)     NULL DEFAULT NULL,
  `review_note`           TEXT            NULL COMMENT 'Důvod zamítnutí / poznámka schvalovatele',

  -- Pokud schváleno: ID řádku v `contacts`, do kterého se to přepsalo
  -- (audit link proposal ↔ contact)
  `converted_contact_id`  BIGINT UNSIGNED NULL DEFAULT NULL,

  `created_at`            DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `updated_at`            DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),

  PRIMARY KEY (`id`),
  KEY `idx_cp_status`        (`status`),
  KEY `idx_cp_proposed_by`   (`proposed_by_user_id`),
  KEY `idx_cp_suggested_oz`  (`suggested_oz_id`),
  KEY `idx_cp_converted`     (`converted_contact_id`),

  CONSTRAINT `fk_cp_proposed_by`
    FOREIGN KEY (`proposed_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cp_reviewed_by`
    FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cp_suggested_oz`
    FOREIGN KEY (`suggested_oz_id`)     REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cp_converted_contact`
    FOREIGN KEY (`converted_contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Návrhy kontaktů ke schválení majitelem (manual hot leads).';
