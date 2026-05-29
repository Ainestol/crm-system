-- ════════════════════════════════════════════════════════════════
-- VERIFY_FIXES.SQL — diagnostické SELECT dotazy pro 4 fixy z auditu
-- ════════════════════════════════════════════════════════════════
-- Spuštění: mariadb -u root -p crm_db < verify_fixes.sql
--           (nebo přes phpMyAdmin / Adminer / DBeaver)
--
-- BEZPEČNÉ: pouze SELECT, žádné UPDATE/INSERT/DELETE — nezmění data.
-- ════════════════════════════════════════════════════════════════


-- ────────────────────────────────────────────────────────────────
-- FIX #1: Import workflow stavy
-- Ověření: kolik kontaktů má jaký workflow stav v oz_contact_workflow
-- Před fixem: workflow tabulka byla skoro prázdná, vše šlo do FOR_SALES.
-- Po fixu: měli bys vidět distribuci přes NOVE/ZPRACOVAVA/SCHUZKA/SANCE/BO_*.
-- ────────────────────────────────────────────────────────────────
SELECT '=== FIX #1: Workflow stavy v DB ===' AS report;

SELECT
    w.stav AS workflow_stav,
    COUNT(*) AS pocet_kontaktu
FROM oz_contact_workflow w
GROUP BY w.stav
ORDER BY pocet_kontaktu DESC;

-- Bonus: které kontakty importu skončily s workflow stage NOVE/ZPRACOVAVA/...
SELECT
    c.id, c.firma, w.stav AS workflow_stav, w.started_at
FROM contacts c
INNER JOIN oz_contact_workflow w ON w.contact_id = c.id
WHERE w.stav IN ('NOVE','ZPRACOVAVA','NABIDKA','SCHUZKA','SANCE',
                 'BO_PREDANO','BO_VPRACI','BO_VRACENO','SMLOUVA','REKLAMACE')
ORDER BY w.started_at DESC
LIMIT 10;


-- ────────────────────────────────────────────────────────────────
-- FIX #2: Role validace v importu (oz_email musí být obchodak)
-- Ověření: kdo MŮŽE být v oz_email sloupci? Jen aktivní obchodáci.
-- ────────────────────────────────────────────────────────────────
SELECT '=== FIX #2: Aktivní obchodáci (povolené oz_email) ===' AS report;

SELECT
    id, jmeno, email, role,
    JSON_EXTRACT(IFNULL(roles_extra, '[]'), '$') AS roles_extra
FROM users
WHERE aktivni = 1
  AND (role = 'obchodak'
       OR JSON_CONTAINS(IFNULL(roles_extra, '[]'), '"obchodak"'))
ORDER BY jmeno;

-- Negative test: navolávačky NEMOHOU být oz_email
SELECT '=== Navolávačky (NESMÍ být v oz_email) ===' AS report;

SELECT
    id, jmeno, email, role
FROM users
WHERE aktivni = 1
  AND role = 'navolavacka'
  AND NOT JSON_CONTAINS(IFNULL(roles_extra, '[]'), '"obchodak"')
ORDER BY jmeno;


-- ────────────────────────────────────────────────────────────────
-- FIX #3: Rescue clawback (expired/failed záchrany)
-- Ověření: jsou nějaké expired/failed rescue requests? Kolik?
-- Když ano → odpovídajícím caller-ům se to bude odečítat ve výplatě.
-- ────────────────────────────────────────────────────────────────
SELECT '=== FIX #3: Rescue requests s outcome=expired/failed (90 dní) ===' AS report;

SELECT
    rr.id,
    rr.contact_id,
    c.firma,
    rr.original_caller_id,
    u.jmeno AS original_caller_name,
    rr.outcome,
    rr.created_at,
    rr.outcome_at
FROM rescue_requests rr
LEFT JOIN contacts c ON c.id = rr.contact_id
LEFT JOIN users u ON u.id = rr.original_caller_id
WHERE rr.outcome IN ('expired', 'failed')
  AND rr.created_at >= NOW() - INTERVAL 90 DAY
ORDER BY rr.created_at DESC
LIMIT 20;

-- Souhrn per caller: kolik clawback za posledních 90 dní
SELECT '=== Clawback souhrn per navolávačka ===' AS report;

SELECT
    u.jmeno AS navolavacka,
    SUM(CASE WHEN rr.outcome = 'expired' THEN 1 ELSE 0 END) AS expired_count,
    SUM(CASE WHEN rr.outcome = 'failed'  THEN 1 ELSE 0 END) AS failed_count,
    COUNT(*) AS total_clawback_count
FROM rescue_requests rr
INNER JOIN users u ON u.id = rr.original_caller_id
WHERE rr.outcome IN ('expired', 'failed')
  AND rr.created_at >= NOW() - INTERVAL 90 DAY
GROUP BY rr.original_caller_id, u.jmeno
ORDER BY total_clawback_count DESC;


-- ────────────────────────────────────────────────────────────────
-- FIX #4: Mix UPDATE batching (queue_mix_seq)
-- Ověření: jaký podíl kontaktů má queue_mix_seq vyplněné = mix proběhl
-- ────────────────────────────────────────────────────────────────
SELECT '=== FIX #4: Mix queue_mix_seq pokrytí ===' AS report;

SELECT
    COUNT(*)                                       AS total_contacts,
    SUM(CASE WHEN queue_mix_seq IS NOT NULL THEN 1 ELSE 0 END) AS with_seq,
    SUM(CASE WHEN queue_mix_seq IS NULL     THEN 1 ELSE 0 END) AS without_seq,
    ROUND(100.0 * SUM(CASE WHEN queue_mix_seq IS NOT NULL THEN 1 ELSE 0 END) / NULLIF(COUNT(*),0), 1)
        AS pct_mixed
FROM contacts;

-- Bonus: distribuce subject_type (firma vs osvc vs unknown)
SELECT '=== Distribuce subject_type (po backfill) ===' AS report;

SELECT
    subject_type,
    COUNT(*) AS pocet
FROM contacts
WHERE stav = 'NEW'
GROUP BY subject_type
ORDER BY pocet DESC;
