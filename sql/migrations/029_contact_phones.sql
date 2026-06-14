-- e:\Snecinatripu\sql\migrations\029_contact_phones.sql
-- ════════════════════════════════════════════════════════════════════
-- CONTACT_PHONES — per-telefon evidence + operátor + ověření čističkou
--
-- Důvod:
--   Kontakt může mít víc telefonů (např. "777111, 602222"). Doteď se
--   ukládaly do contacts.telefon jako jeden řetězec, čistička ověřila
--   1× operátora pro celý kontakt. Nově:
--     - každý telefon má vlastní řádek v contact_phones
--     - každý telefon má vlastního operátora (TM/O2/VF/CHYBNY)
--     - čistička ověřuje POSTUPNĚ telefon po telefonu
--     - kontakt se vyhodnotí až po ověření VŠECH telefonů
--
-- Pravidla vyhodnocení:
--   - Aspoň 1 telefon NE-VF a NE-CHYBNY → kontakt jde READY (do navolávačky)
--   - Všechny telefony VF → kontakt jde VF_SKIP
--   - Všechny telefony CHYBNY → kontakt jde CHYBNY_KONTAKT
--
-- Backfill:
--   Pro stávající kontakty rozparsuje contacts.telefon (split podle čárky)
--   na jednotlivé řádky. Operátor se zkopíruje z contacts.operator pokud
--   už byl ověřen (stav != NEW), jinak NULL (čeká čističku).
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `contact_phones` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `contact_id`    INT UNSIGNED NOT NULL,
    `phone`         VARCHAR(50)  NOT NULL,
    `phone_digits`  VARCHAR(20)  NOT NULL COMMENT 'Jen číslice pro dedup/lookup',
    `operator`      VARCHAR(20)  NULL DEFAULT NULL
                    COMMENT 'TM / O2 / VF / CHYBNY — NULL = čeká ověření',
    `verified_at`   DATETIME(3)  NULL DEFAULT NULL,
    `verified_by`   INT UNSIGNED NULL DEFAULT NULL
                    COMMENT 'user_id čističky, která telefon ověřila',
    `position`      TINYINT UNSIGNED NOT NULL DEFAULT 0
                    COMMENT '0 = primární (zobrazení), 1+ = sekundární',
    `created_at`    DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (`id`),
    KEY `idx_phones_contact`  (`contact_id`),
    KEY `idx_phones_digits`   (`phone_digits`),
    KEY `idx_phones_operator` (`operator`),
    KEY `idx_phones_verified` (`verified_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Backfill: rozparsovat contacts.telefon (split podle čárky) ──
-- Krok A: telefonu, který obsahuje čárku → druhá část se naparsuje pozdějším skriptem
-- Pro 99 % případů (kontakt s 1 telefonem) stačí tento jednoduchý INSERT.
INSERT INTO contact_phones (contact_id, phone, phone_digits, operator, verified_at, verified_by, position, created_at)
SELECT
    c.id,
    TRIM(SUBSTRING_INDEX(c.telefon, ',', 1))                                  AS phone,
    REGEXP_REPLACE(SUBSTRING_INDEX(c.telefon, ',', 1), '[^0-9]', '')          AS phone_digits,
    CASE
        WHEN c.stav = 'NEW' THEN NULL
        WHEN c.operator IS NULL OR c.operator = '' THEN NULL
        ELSE UPPER(c.operator)
    END                                                                       AS operator,
    CASE
        WHEN c.stav = 'NEW' THEN NULL
        ELSE c.updated_at
    END                                                                       AS verified_at,
    NULL                                                                      AS verified_by,
    0                                                                         AS position,
    NOW(3)                                                                    AS created_at
FROM contacts c
WHERE c.telefon IS NOT NULL
  AND TRIM(c.telefon) <> ''
  AND NOT EXISTS (SELECT 1 FROM contact_phones cp WHERE cp.contact_id = c.id);

-- Krok B: pro kontakty se 2 telefony (1 čárka) přidat i druhý telefon
INSERT INTO contact_phones (contact_id, phone, phone_digits, operator, verified_at, verified_by, position, created_at)
SELECT
    c.id,
    TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(c.telefon, ',', 2), ',', -1))         AS phone,
    REGEXP_REPLACE(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(c.telefon, ',', 2), ',', -1)), '[^0-9]', '') AS phone_digits,
    NULL                                                                      AS operator,
    NULL                                                                      AS verified_at,
    NULL                                                                      AS verified_by,
    1                                                                         AS position,
    NOW(3)                                                                    AS created_at
FROM contacts c
WHERE c.telefon IS NOT NULL
  AND c.telefon LIKE '%,%'
  AND TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(c.telefon, ',', 2), ',', -1)) <> ''
  AND NOT EXISTS (
      SELECT 1 FROM contact_phones cp
      WHERE cp.contact_id = c.id AND cp.position = 1
  );

-- Krok C: vyčistit zjevně neplatné telefony (méně než 5 číslic)
DELETE FROM contact_phones WHERE LENGTH(phone_digits) < 5;
