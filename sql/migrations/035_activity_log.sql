-- e:\Snecinatripu\sql\migrations\035_activity_log.sql
-- ════════════════════════════════════════════════════════════════════
-- ACTIVITY LOG + SCORING — výkonnostní evidence + konfigurovatelné body
--
-- Účel:
--   1. `activity_log` — centrální evidence VŠECH smysluplných akcí
--      uživatelů. Nesleduje login/logout/čas — jen výsledky.
--   2. `activity_score_rules` — per-tenant per-role katalog s body.
--      Majitel může v UI měnit a zapínat/vypínat.
--   3. Backfill: last 30 dní z workflow_log → activity_log (aby dashboard
--      hned něco viděl).
--
-- Filozofie:
--   - Tenant-aware (TenantAwarePDO auto-inject)
--   - Žádné hardcoded body — vše v tabulce
--   - Akce se zapíšou i když rule.active=0, jen s points_awarded=0
--   - Indexy na 4 typické queries: today, by_user, by_action, by_role
-- ════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ─────────────────────────────────────────────────────────────────
-- 1) `activity_log` — co se v systému stalo (read-only audit + body)
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `activity_log` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`      INT UNSIGNED NOT NULL,
    `user_id`        BIGINT UNSIGNED NULL
                     COMMENT 'NULL = system akce (cron, import)',
    `user_role`      VARCHAR(50) NULL
                     COMMENT 'Snapshot role uživatele v okamžiku akce',
    `action_type`    VARCHAR(80) NOT NULL
                     COMMENT 'Slug, např. "call.success", "sales.contract_signed"',
    `entity_type`    VARCHAR(50) NULL
                     COMMENT 'contact / campaign / order / user / payment',
    `entity_id`      BIGINT UNSIGNED NULL,
    `points_awarded` INT NOT NULL DEFAULT 0
                     COMMENT 'Snapshot bodů z activity_score_rules v okamžiku zápisu',
    `metadata`       JSON NULL
                     COMMENT 'Volné kontextové údaje: {"region":"praha","bmsl":15000}',
    `created_at`     DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (`id`),
    KEY `idx_al_tenant_created`  (`tenant_id`, `created_at` DESC),
    KEY `idx_al_tenant_user`     (`tenant_id`, `user_id`, `created_at` DESC),
    KEY `idx_al_tenant_action`   (`tenant_id`, `action_type`, `created_at`),
    KEY `idx_al_tenant_role`     (`tenant_id`, `user_role`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- 2) `activity_score_rules` — per-tenant per-role katalog akcí + body
-- ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `activity_score_rules` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `role`         VARCHAR(50) NOT NULL
                   COMMENT 'cisticka / navolavacka / obchodak / backoffice / majitel',
    `action_type`  VARCHAR(80) NOT NULL
                   COMMENT 'Shoda s activity_log.action_type',
    `action_label` VARCHAR(150) NOT NULL
                   COMMENT 'Lidský popis pro UI: "Úspěšný hovor (CALLED_OK)"',
    `points`       INT NOT NULL DEFAULT 0,
    `active`       TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order`   INT NOT NULL DEFAULT 0,
    `created_at`   DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updated_at`   DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_asr_rule` (`tenant_id`, `role`, `action_type`),
    KEY `idx_asr_tenant_active` (`tenant_id`, `active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────
-- 3) Seed default rules pro VŠECHNY existující tenanty
-- ─────────────────────────────────────────────────────────────────
-- Pomocí CROSS JOIN se seedy aplikují na každý tenant v `tenants`.
-- INSERT IGNORE zabrání duplicitám při opakovaném spuštění.

-- Čistička (5 akcí)
INSERT IGNORE INTO `activity_score_rules`
    (`tenant_id`, `role`, `action_type`, `action_label`, `points`, `active`, `sort_order`)
SELECT t.id, 'cisticka', 'cleaning.verified_tm',     'Ověřený TM kontakt',              1, 1, 10 FROM `tenants` t
UNION ALL SELECT t.id, 'cisticka', 'cleaning.verified_o2',     'Ověřený O2 kontakt',              1, 1, 20 FROM `tenants` t
UNION ALL SELECT t.id, 'cisticka', 'cleaning.vf_skip',         'Vodafone přeskočen',              0, 1, 30 FROM `tenants` t
UNION ALL SELECT t.id, 'cisticka', 'cleaning.bad_contact',     'Chybný kontakt označen',          1, 1, 40 FROM `tenants` t
UNION ALL SELECT t.id, 'cisticka', 'cleaning.premium_verified','Premium pool — vyčištěno',        2, 1, 50 FROM `tenants` t;

-- Navolávačka (10 akcí)
INSERT IGNORE INTO `activity_score_rules`
    (`tenant_id`, `role`, `action_type`, `action_label`, `points`, `active`, `sort_order`)
SELECT t.id, 'navolavacka', 'call.success',            'Úspěšný hovor (CALLED_OK)',         10, 1, 10 FROM `tenants` t
UNION ALL SELECT t.id, 'navolavacka', 'call.failed',             'Neúspěšný hovor / nezájem',          1, 1, 20 FROM `tenants` t
UNION ALL SELECT t.id, 'navolavacka', 'call.nedovolano',         'Nedovoláno',                         0, 1, 30 FROM `tenants` t
UNION ALL SELECT t.id, 'navolavacka', 'call.callback_scheduled', 'Domluven callback',                  2, 1, 40 FROM `tenants` t
UNION ALL SELECT t.id, 'navolavacka', 'call.izolace',            'Izolace',                            1, 1, 50 FROM `tenants` t
UNION ALL SELECT t.id, 'navolavacka', 'call.premium_success',    'Premium hovor — úspěch',            15, 1, 60 FROM `tenants` t
UNION ALL SELECT t.id, 'navolavacka', 'call.premium_failed',     'Premium hovor — neúspěch',           1, 1, 70 FROM `tenants` t
UNION ALL SELECT t.id, 'navolavacka', 'call.bet_success',        'Sázka — úspěšný hovor',             15, 1, 80 FROM `tenants` t
UNION ALL SELECT t.id, 'navolavacka', 'rescue.success',          'Záchrana úspěšná',                  25, 1, 90 FROM `tenants` t
UNION ALL SELECT t.id, 'navolavacka', 'rescue.failure',          'Záchrana neúspěšná',                 0, 1, 100 FROM `tenants` t;

-- Obchodák (13 akcí)
INSERT IGNORE INTO `activity_score_rules`
    (`tenant_id`, `role`, `action_type`, `action_label`, `points`, `active`, `sort_order`)
SELECT t.id, 'obchodak', 'sales.workflow_started',   'Začal zpracovávat lead',            1, 1, 10 FROM `tenants` t
UNION ALL SELECT t.id, 'obchodak', 'sales.meeting_scheduled',  'Domluvena schůzka',                  5, 1, 20 FROM `tenants` t
UNION ALL SELECT t.id, 'obchodak', 'sales.offer_made',         'Předložena nabídka',                 8, 1, 30 FROM `tenants` t
UNION ALL SELECT t.id, 'obchodak', 'sales.chance_won',         'Lead → šance',                      10, 1, 40 FROM `tenants` t
UNION ALL SELECT t.id, 'obchodak', 'sales.contract_drafted',   'Smlouva sepsána',                   20, 1, 50 FROM `tenants` t
UNION ALL SELECT t.id, 'obchodak', 'sales.contract_signed',    'Podpis potvrzen',                   50, 1, 60 FROM `tenants` t
UNION ALL SELECT t.id, 'obchodak', 'sales.contract_cancelled', 'Smlouva stornována',                 0, 1, 70 FROM `tenants` t
UNION ALL SELECT t.id, 'obchodak', 'sales.note_added',         'Přidaná poznámka',                   1, 1, 80 FROM `tenants` t
UNION ALL SELECT t.id, 'obchodak', 'sales.action_logged',      'Záznam do deníku',                   1, 1, 90 FROM `tenants` t
UNION ALL SELECT t.id, 'obchodak', 'sales.contact_taken',      'Převzal kontakt z vyhledávání',      2, 1, 100 FROM `tenants` t
UNION ALL SELECT t.id, 'obchodak', 'sales.contact_created',    'Vytvořen nový kontakt',              3, 1, 110 FROM `tenants` t
UNION ALL SELECT t.id, 'obchodak', 'sales.rescue_requested',   'Poslán na záchranu',                 0, 1, 120 FROM `tenants` t;

-- Backoffice (2 akce)
INSERT IGNORE INTO `activity_score_rules`
    (`tenant_id`, `role`, `action_type`, `action_label`, `points`, `active`, `sort_order`)
SELECT t.id, 'backoffice', 'bo.signature_confirmed', 'Potvrzen podpis',           3, 1, 10 FROM `tenants` t
UNION ALL SELECT t.id, 'backoffice', 'bo.contract_activated', 'Smlouva aktivována',         5, 1, 20 FROM `tenants` t;

-- Majitel (4 akce — body 0, jsou to admin akce, ne výkon)
INSERT IGNORE INTO `activity_score_rules`
    (`tenant_id`, `role`, `action_type`, `action_label`, `points`, `active`, `sort_order`)
SELECT t.id, 'majitel', 'admin.import_run',             'Import CSV',                    0, 1, 10 FROM `tenants` t
UNION ALL SELECT t.id, 'majitel', 'admin.campaign_created',       'Vytvořena sázka',              0, 1, 20 FROM `tenants` t
UNION ALL SELECT t.id, 'majitel', 'admin.premium_order_created',  'Vytvořena premium objednávka', 0, 1, 30 FROM `tenants` t
UNION ALL SELECT t.id, 'majitel', 'admin.user_created',           'Vytvořen uživatel',            0, 1, 40 FROM `tenants` t;

-- ─────────────────────────────────────────────────────────────────
-- 4) Backfill last 30 days from workflow_log → activity_log
-- ─────────────────────────────────────────────────────────────────
-- Mapuje workflow_log.new_status na action_type. Operator detail pro
-- TM/O2 nemáme retrospektivně (workflow_log neukládá operator), takže
-- backfill používá generic 'cleaning.verified' a denormalizuje do
-- metadata.operator z contacts.operator.
INSERT INTO `activity_log`
    (`tenant_id`, `user_id`, `user_role`, `action_type`,
     `entity_type`, `entity_id`, `points_awarded`, `metadata`, `created_at`)
SELECT
    wl.tenant_id,
    wl.user_id,
    u.role,
    CASE wl.new_status
        WHEN 'CALLED_OK'      THEN 'call.success'
        WHEN 'CALLED_BAD'     THEN 'call.failed'
        WHEN 'NEZAJEM'        THEN 'call.failed'
        WHEN 'NEDOVOLANO'     THEN 'call.nedovolano'
        WHEN 'CALLBACK'       THEN 'call.callback_scheduled'
        WHEN 'IZOLACE'        THEN 'call.izolace'
        WHEN 'READY'          THEN
            CASE WHEN UPPER(COALESCE(c.operator, '')) = 'O2'
                 THEN 'cleaning.verified_o2'
                 ELSE 'cleaning.verified_tm' END
        WHEN 'VF_SKIP'        THEN 'cleaning.vf_skip'
        WHEN 'CHYBNY_KONTAKT' THEN 'cleaning.bad_contact'
        ELSE NULL
    END AS action_type,
    'contact',
    wl.contact_id,
    -- body = snapshot z aktivního pravidla pro tenant+role+action
    COALESCE((
        SELECT r.points FROM activity_score_rules r
         WHERE r.tenant_id = wl.tenant_id
           AND r.role = u.role
           AND r.action_type = (
                CASE wl.new_status
                    WHEN 'CALLED_OK' THEN 'call.success'
                    WHEN 'CALLED_BAD' THEN 'call.failed'
                    WHEN 'NEZAJEM' THEN 'call.failed'
                    WHEN 'NEDOVOLANO' THEN 'call.nedovolano'
                    WHEN 'CALLBACK' THEN 'call.callback_scheduled'
                    WHEN 'IZOLACE' THEN 'call.izolace'
                    WHEN 'READY' THEN
                        CASE WHEN UPPER(COALESCE(c.operator, '')) = 'O2'
                             THEN 'cleaning.verified_o2'
                             ELSE 'cleaning.verified_tm' END
                    WHEN 'VF_SKIP' THEN 'cleaning.vf_skip'
                    WHEN 'CHYBNY_KONTAKT' THEN 'cleaning.bad_contact'
                END
           )
           AND r.active = 1
         LIMIT 1
    ), 0) AS points_awarded,
    JSON_OBJECT('operator', COALESCE(c.operator, ''), 'backfill', 1) AS metadata,
    wl.created_at
FROM `workflow_log` wl
JOIN `users` u    ON u.id = wl.user_id
LEFT JOIN `contacts` c ON c.id = wl.contact_id AND c.tenant_id = wl.tenant_id
WHERE wl.created_at >= (CURDATE() - INTERVAL 30 DAY)
  AND wl.new_status IN
      ('CALLED_OK','CALLED_BAD','NEZAJEM','NEDOVOLANO','CALLBACK','IZOLACE',
       'READY','VF_SKIP','CHYBNY_KONTAKT')
  AND wl.user_id IS NOT NULL;
