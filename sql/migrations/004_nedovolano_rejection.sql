-- Migration 004: stav NEDOVOLANO, rejection_reason, nedovolano_count
-- Spustit: mysql -u root crm < E:\Snecinatripu\sql\migrations\004_nedovolano_rejection.sql

ALTER TABLE `contacts`
  ADD COLUMN `rejection_reason` VARCHAR(100) NULL DEFAULT NULL
    COMMENT 'Strukturovaný důvod zamítnutí: nezajem|cena|ma_smlouvu|spatny_kontakt|jine'
    AFTER `poznamka`,
  ADD COLUMN `nedovolano_count` TINYINT UNSIGNED NOT NULL DEFAULT 0
    COMMENT 'Počet neúspěšných pokusů o dovolání (po 3x → NEZAJEM auto)'
    AFTER `rejection_reason`;

-- Index pro rychlé hledání nedovolaných
ALTER TABLE `contacts`
  ADD KEY `idx_contacts_nedovolano` (`stav`, `nedovolano_count`);
