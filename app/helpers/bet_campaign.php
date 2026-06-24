<?php
declare(strict_types=1);

/**
 * app/helpers/bet_campaign.php
 *
 * Helper funkce pro „sázky" — kampaně na vyčištění X kontaktů v daném kraji
 * s následným chronologickým rozdělením mezi více OZ (call / email mix).
 *
 * Pohled cističky:
 *   - Strict mode: dokud běží sázka v jejím kraji, vidí jen kontakty z toho kraje.
 *   - Po každém TM/O2 verify se kontakt automaticky zařadí (přes bet_assign_lead).
 *
 * Pohled administrátora:
 *   - /admin/bet — list, /admin/bet/new — form, /admin/bet/show?id=N — detail + uzavření.
 *
 * Workflow s contacts.stav:
 *   - delivery_type='call'  → kontakt zůstane READY, jen se nastaví assigned_sales_id;
 *                              navolávačka ho normálně volá, po Výhra → FOR_SALES.
 *   - delivery_type='email' → kontakt přejde do stavu 'EMAIL_READY', přeskočí caller
 *                              pool; OZ ho vidí v /oz/email-leads (export do XLSX).
 */


/**
 * Najde JEDNU aktivní sázku pro daný kraj (status='open', cleaned < target).
 * Pokud jich je víc, vrátí nejstarší.
 *
 * @return array{id:int,name:string,region:string,target_count:int,cleaned_count:int}|null
 */
function bet_get_active_campaign_for_region(PDO $pdo, string $region): ?array
{
    if ($region === '') {
        return null;
    }
    // Multi-tenant filter
    $stmt = $pdo->prepare(
        "SELECT id, name, region, target_count, cleaned_count
         FROM bet_campaigns
         WHERE region = :reg
           AND status = 'open'
           AND cleaned_count < target_count
           AND tenant_id = :tid
         ORDER BY created_at ASC
         LIMIT 1"
    );
    $stmt->execute(['reg' => $region, 'tid' => crm_tenant_id()]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    return [
        'id'            => (int) $row['id'],
        'name'          => (string) $row['name'],
        'region'        => (string) $row['region'],
        'target_count'  => (int) $row['target_count'],
        'cleaned_count' => (int) $row['cleaned_count'],
    ];
}


/**
 * Vrátí seznam regionů, kde běží aktivní sázka, omezený na zadané regiony čističky.
 *
 * @param list<string> $regions Regiony, ke kterým má čistička přístup (nebo prázdné = všechny)
 * @return list<string> Regiony, kde běží aktivní sázka (subset $regions)
 */
function bet_get_active_regions(PDO $pdo, array $regions = []): array
{
    // Multi-tenant filter
    $sql = "SELECT DISTINCT region
            FROM bet_campaigns
            WHERE status = 'open'
              AND cleaned_count < target_count
              AND tenant_id = ?";
    $params = [crm_tenant_id()];

    if ($regions !== []) {
        $ph = implode(',', array_fill(0, count($regions), '?'));
        $sql .= " AND region IN ($ph)";
        $params = array_merge($params, $regions);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    return array_map('strval', $rows);
}


/**
 * Najde recipient, kterému patří kontakt na zadané pozici (1-based).
 *
 * Recipients jsou seřazení podle sort_order ASC. Kumulativně se sčítá
 * target_count. Pozice 47 v sázce s [100, 200] → patří 1. recipient.
 * Pozice 142 → 2. recipient (100+42).
 *
 * @return array{id:int,oz_id:int,delivery_type:string,target_count:int}|null
 */
function bet_find_recipient_for_position(PDO $pdo, int $campaignId, int $position): ?array
{
    // Multi-tenant filter
    $stmt = $pdo->prepare(
        "SELECT id, oz_id, target_count, delivery_type, sort_order
         FROM bet_campaign_recipients
         WHERE campaign_id = :cid AND tenant_id = :tid
         ORDER BY sort_order ASC"
    );
    $stmt->execute(['cid' => $campaignId, 'tid' => crm_tenant_id()]);
    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $cumulative = 0;
    foreach ($recipients as $r) {
        $tc = (int) $r['target_count'];
        if ($position <= $cumulative + $tc) {
            return [
                'id'            => (int) $r['id'],
                'oz_id'         => (int) $r['oz_id'],
                'delivery_type' => (string) $r['delivery_type'],
                'target_count'  => $tc,
            ];
        }
        $cumulative += $tc;
    }
    return null; // mimo cíl
}


/**
 * Hlavní hook — volaný z CistickaController::postVerify po úspěšném TM/O2 verify.
 *
 * Zařadí kontakt do aktivní sázky daného regionu (pokud existuje), přiřadí ho
 * chronologickému recipient a aktualizuje contacts.assigned_sales_id (a případně
 * stav na EMAIL_READY pro delivery=email).
 *
 * Vrací:
 *   - null  → žádná aktivní sázka v tomto regionu (nic se nedělo)
 *   - array → kontakt byl přiřazen:
 *       ['campaign_id' => 1, 'recipient_id' => 2, 'oz_id' => 5,
 *        'delivery_type' => 'email', 'position' => 47, 'closed' => false]
 *
 * Idempotentní: pokud kontakt už v sázce je (uq_bcl_contact), neudělá nic.
 *
 * @param int $contactId Kontakt po verify (musí být ve stavu READY, operator TM/O2)
 * @param int $cleanerId Která čistička právě verify provedla
 * @param string $region Kraj kontaktu
 *
 * @return array<string,mixed>|null
 */
function bet_assign_lead(PDO $pdo, int $contactId, int $cleanerId, string $region): ?array
{
    $campaign = bet_get_active_campaign_for_region($pdo, $region);
    if ($campaign === null) {
        return null;
    }

    $campaignId = $campaign['id'];

    // Idempotence — pokud kontakt už v jakékoli sázce je, neudělej nic
    // Multi-tenant filter
    $check = $pdo->prepare("SELECT id FROM bet_campaign_leads WHERE contact_id = ? AND tenant_id = ? LIMIT 1");
    $check->execute([$contactId, crm_tenant_id()]);
    if ($check->fetchColumn() !== false) {
        return null;
    }

    $pdo->beginTransaction();
    try {
        // Atomicky: zamknout řádek sázky, načíst aktuální cleaned_count, určit position
        // Multi-tenant filter
        $lockStmt = $pdo->prepare(
            "SELECT cleaned_count, target_count FROM bet_campaigns
             WHERE id = :cid AND status = 'open' AND tenant_id = :tid FOR UPDATE"
        );
        $lockStmt->execute(['cid' => $campaignId, 'tid' => crm_tenant_id()]);
        $cur = $lockStmt->fetch(PDO::FETCH_ASSOC);
        if (!$cur) {
            $pdo->rollBack();
            return null; // mezitím zavřena
        }

        $currentCleaned = (int) $cur['cleaned_count'];
        $targetCount    = (int) $cur['target_count'];

        if ($currentCleaned >= $targetCount) {
            $pdo->rollBack();
            return null; // už plno
        }

        $position = $currentCleaned + 1;

        $recipient = bet_find_recipient_for_position($pdo, $campaignId, $position);
        if ($recipient === null) {
            $pdo->rollBack();
            return null; // sázka nemá nakonfigurované recipients pro tuto pozici
        }

        // INSERT do leads
        $pdo->prepare(
            "INSERT INTO bet_campaign_leads
             (campaign_id, contact_id, recipient_id, position, cleaned_by, cleaned_at)
             VALUES (:cid, :ct, :rid, :pos, :cb, NOW(3))"
        )->execute([
            'cid' => $campaignId,
            'ct'  => $contactId,
            'rid' => $recipient['id'],
            'pos' => $position,
            'cb'  => $cleanerId,
        ]);

        // Multi-tenant: tenant filter ve všech UPDATEs
        $tid = crm_tenant_id();
        $pdo->prepare(
            "UPDATE bet_campaigns SET cleaned_count = cleaned_count + 1 WHERE id = :cid AND tenant_id = :tid"
        )->execute(['cid' => $campaignId, 'tid' => $tid]);

        $pdo->prepare(
            "UPDATE bet_campaign_recipients SET received_count = received_count + 1 WHERE id = :rid AND tenant_id = :tid"
        )->execute(['rid' => $recipient['id'], 'tid' => $tid]);

        // Aplikace na contacts:
        //   - assigned_sales_id vždy = oz_id recipient
        //   - delivery=email → stav přejde na EMAIL_READY (přeskočí caller pool)
        //   - delivery=call  → stav zůstane READY (navolávačka volá normálně)
        if ($recipient['delivery_type'] === 'email') {
            $pdo->prepare(
                "UPDATE contacts SET assigned_sales_id = :oz, stav = 'EMAIL_READY',
                    locked_by = NULL, locked_until = NULL, updated_at = NOW(3)
                 WHERE id = :id AND tenant_id = :tid"
            )->execute(['oz' => $recipient['oz_id'], 'id' => $contactId, 'tid' => $tid]);
        } else {
            $pdo->prepare(
                "UPDATE contacts SET assigned_sales_id = :oz, updated_at = NOW(3)
                 WHERE id = :id AND tenant_id = :tid"
            )->execute(['oz' => $recipient['oz_id'], 'id' => $contactId, 'tid' => $tid]);
        }

        // Auto-close pokud dosaženo
        $closed = false;
        if ($position >= $targetCount) {
            $pdo->prepare(
                "UPDATE bet_campaigns SET status = 'closed', closed_at = NOW(3)
                 WHERE id = :cid AND status = 'open' AND tenant_id = :tid"
            )->execute(['cid' => $campaignId, 'tid' => $tid]);
            $closed = true;
        }

        $pdo->commit();

        return [
            'campaign_id'   => $campaignId,
            'recipient_id'  => $recipient['id'],
            'oz_id'         => $recipient['oz_id'],
            'delivery_type' => $recipient['delivery_type'],
            'position'      => $position,
            'closed'        => $closed,
        ];
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Loggování ponecháme na volajícím (crm_db_log_error)
        throw $e;
    }
}


/**
 * Reverze: odstranit kontakt ze sázky (undo / reclassify v čističce).
 *
 * Zavolá se vždy, když kontakt přestává být validní lead (vrácen do NEW,
 * překlasifikován na VF_SKIP nebo CHYBNY_KONTAKT). Záznam v bet_campaign_leads
 * se smaže, counts se decrementují, assigned_sales_id se uvolní.
 *
 * Idempotentní: pokud kontakt v sázce není, vrátí null a nic neudělá.
 *
 * Pozn.: NESNAŽÍ se "rolovat pozice" ostatních leadů. Pozice 47 se prostě
 * vynechá — chronologie zůstává, jen jeden slot je prázdný. Příští verify
 * dostane pozici cleaned_count + 1 (= ne 47, ale aktuální cleaned_count + 1).
 *
 * Pokud byla sázka auto-closed (cleaned_count = target_count), undo ji znovu
 * otevře (pokud po decrementu cleaned_count < target_count).
 *
 * @return array{campaign_id:int,recipient_id:int,oz_id:int,position:int}|null
 */
function bet_unassign_lead(PDO $pdo, int $contactId): ?array
{
    $pdo->beginTransaction();
    try {
        // 1. Najdi lead záznam (s lockem)
        // Multi-tenant filter
        $leadStmt = $pdo->prepare(
            "SELECT id, campaign_id, recipient_id, position
             FROM bet_campaign_leads
             WHERE contact_id = ? AND tenant_id = ?
             FOR UPDATE"
        );
        $leadStmt->execute([$contactId, crm_tenant_id()]);
        $lead = $leadStmt->fetch(PDO::FETCH_ASSOC);
        if (!$lead) {
            $pdo->rollBack();
            return null; // kontakt v žádné sázce
        }

        $leadId      = (int) $lead['id'];
        $campaignId  = (int) $lead['campaign_id'];
        $recipientId = (int) $lead['recipient_id'];
        $position    = (int) $lead['position'];

        // 2. Najdi oz_id pro vrácený údaj (kvůli logu / fronendovi)
        // Multi-tenant: tenant filter ve všech queries
        $tid = crm_tenant_id();
        $rStmt = $pdo->prepare("SELECT oz_id FROM bet_campaign_recipients WHERE id = ? AND tenant_id = ?");
        $rStmt->execute([$recipientId, $tid]);
        $ozId = (int) ($rStmt->fetchColumn() ?: 0);

        // 3. DELETE lead
        $pdo->prepare("DELETE FROM bet_campaign_leads WHERE id = ? AND tenant_id = ?")->execute([$leadId, $tid]);

        // 4. Decrement counts (s ochranou proti záporným hodnotám)
        $pdo->prepare(
            "UPDATE bet_campaigns
             SET cleaned_count = GREATEST(cleaned_count - 1, 0)
             WHERE id = ? AND tenant_id = ?"
        )->execute([$campaignId, $tid]);

        $pdo->prepare(
            "UPDATE bet_campaign_recipients
             SET received_count = GREATEST(received_count - 1, 0)
             WHERE id = ? AND tenant_id = ?"
        )->execute([$recipientId, $tid]);

        // 5. Uvolnit assigned_sales_id na contacts (ať nezůstane dangling pointer)
        $pdo->prepare(
            "UPDATE contacts
             SET assigned_sales_id = NULL, updated_at = NOW(3)
             WHERE id = ? AND tenant_id = ?"
        )->execute([$contactId, $tid]);

        // 6. Reopen sázky, pokud byla auto-closed a teď je opět pod cílem
        $pdo->prepare(
            "UPDATE bet_campaigns
             SET status = 'open', closed_at = NULL
             WHERE id = ?
               AND tenant_id = ?
               AND status = 'closed'
               AND closed_at IS NOT NULL
               AND cleaned_count < target_count"
        )->execute([$campaignId, $tid]);

        $pdo->commit();

        return [
            'campaign_id'  => $campaignId,
            'recipient_id' => $recipientId,
            'oz_id'        => $ozId,
            'position'     => $position,
        ];
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}


/**
 * Souhrn pro top kartu cističky — pro každou aktivní sázku v regionech čističky
 * vrátí progress data.
 *
 * @param list<string> $regions Regiony čističky
 * @return list<array{id:int,name:string,region:string,target_count:int,cleaned_count:int,recipients:list}>
 */
function bet_get_active_for_dashboard(PDO $pdo, array $regions = []): array
{
    // Multi-tenant
    $sql = "SELECT id, name, region, target_count, cleaned_count
            FROM bet_campaigns
            WHERE status = 'open' AND tenant_id = ?";
    $params = [crm_tenant_id()];

    if ($regions !== []) {
        $ph = implode(',', array_fill(0, count($regions), '?'));
        $sql .= " AND region IN ($ph)";
        $params = array_merge($params, $regions);
    }
    $sql .= " ORDER BY created_at ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if ($campaigns === []) {
        return [];
    }

    // Načti recipients pro všechny sázky najednou — multi-tenant
    $ids = array_map(fn($r) => (int) $r['id'], $campaigns);
    $ph2 = implode(',', array_fill(0, count($ids), '?'));
    $rStmt = $pdo->prepare(
        "SELECT r.campaign_id, r.id, r.oz_id, r.target_count, r.received_count,
                r.delivery_type, r.sort_order, u.jmeno
         FROM bet_campaign_recipients r
         LEFT JOIN users u ON u.id = r.oz_id
         WHERE r.campaign_id IN ($ph2) AND r.tenant_id = ?
         ORDER BY r.campaign_id, r.sort_order ASC"
    );
    $rStmt->execute(array_merge($ids, [crm_tenant_id()]));
    $allRecipients = $rStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $byCampaign = [];
    foreach ($allRecipients as $r) {
        $cid = (int) $r['campaign_id'];
        $byCampaign[$cid] = $byCampaign[$cid] ?? [];
        $byCampaign[$cid][] = $r;
    }

    return array_map(function (array $c) use ($byCampaign): array {
        return [
            'id'            => (int) $c['id'],
            'name'          => (string) $c['name'],
            'region'        => (string) $c['region'],
            'target_count'  => (int) $c['target_count'],
            'cleaned_count' => (int) $c['cleaned_count'],
            'recipients'    => $byCampaign[(int) $c['id']] ?? [],
        ];
    }, $campaigns);
}
