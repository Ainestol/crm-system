-- e:\Snecinatripu\sql\migrations\013_hotfix_missing_columns.sql
-- ════════════════════════════════════════════════════════════════════
-- HOTFIX: doplnění sloupců a indexů, které z historických důvodů
--          chybí na produkčním serveru (migrace 003, 004, 006 nikdy
--          neproběhly).
--
-- Tato migrace je IDEMPOTENTNÍ — bezpečné spustit i opakovaně.
-- Používá `ADD COLUMN IF NOT EXISTS` a `ADD KEY IF NOT EXISTS`,
-- které MariaDB 10.5+ podporuje. Na MariaDB 10.11 (prod) to funguje.
--
-- Co doplní:
--   1) contacts.operator         (z 003_caller_extras)        — TM/O2/Vodafone…
--   2) contacts.prilez           (z 003_caller_extras)        — text pro obchodní příležitost
--   3) contacts.rejection_reason (z 004_nedovolano_rejection) — důvod NEZAJEM
--   4) contacts.nedovolano_count (z 004_nedovolano_rejection) — počet pokusů o dovolání
--   5) INDEX idx_contacts_nedovolano       (z 004) — rychlé hledání nedovolaných
--   6) INDEX idx_contacts_ready_operator   (z 005) — caller queue filtr
--   7) INDEX idx_workflow_user_status_created (z 006) — výkonnost workflow_log
--
-- Spustit: sudo mariadb crm < sql/migrations/013_hotfix_missing_columns.sql
-- ════════════════════════════════════════════════════════════════════

-- ── 1) Telecom operator (caller queue filtruje WHERE operator IN('TM','O2')) ──
ALTER TABLE `contacts`
  ADD COLUMN IF NOT EXISTS `operator` VARCHAR(100) NULL DEFAULT NULL
    COMMENT 'Telecom operátor zákazníka (TM/O2/Vodafone…)' AFTER `email`;

-- ── 2) Obchodní příležitost (volný popis) ──────────────────────────
ALTER TABLE `contacts`
  ADD COLUMN IF NOT EXISTS `prilez` VARCHAR(255) NULL DEFAULT NULL
    COMMENT 'Obchodní příležitost (volný popis)' AFTER `operator`;

-- ── 3) Strukturovaný důvod zamítnutí ───────────────────────────────
ALTER TABLE `contacts`
  ADD COLUMN IF NOT EXISTS `rejection_reason` VARCHAR(100) NULL DEFAULT NULL
    COMMENT 'Strukturovaný důvod zamítnutí: nezajem|cena|ma_smlouvu|spatny_kontakt|jine'
    AFTER `poznamka`;

-- ── 4) Počítadlo neúspěšných pokusů o dovolání ─────────────────────
ALTER TABLE `contacts`
  ADD COLUMN IF NOT EXISTS `nedovolano_count` TINYINT UNSIGNED NOT NULL DEFAULT 0
    COMMENT 'Počet neúspěšných pokusů o dovolání (po 3x → NEZAJEM auto)'
    AFTER `rejection_reason`;

-- ── 5) Index pro rychlé hledání nedovolaných ────────────────────────
ALTER TABLE `contacts`
  ADD KEY IF NOT EXISTS `idx_contacts_nedovolano` (`stav`, `nedovolano_count`);

-- ── 6) Index pro caller queue (READY × operator × region) ──────────
ALTER TABLE `contacts`
  ADD KEY IF NOT EXISTS `idx_contacts_ready_operator` (`stav`, `operator`, `region`);

-- ── 7) Performance index pro workflow_log (statistiky navolávačky) ─
ALTER TABLE `workflow_log`
  ADD KEY IF NOT EXISTS `idx_workflow_user_status_created` (`user_id`, `new_status`, `created_at`);
