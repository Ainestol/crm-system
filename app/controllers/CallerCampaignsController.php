<?php
// e:\Snecinatripu\app\controllers\CallerCampaignsController.php
declare(strict_types=1);

/**
 * CallerCampaignsController
 *
 * Záložka „🎯 Kampaně" pro navolávačky — speciální/sázkové leady mimo
 * anonymní pool /caller.
 *
 * Princip:
 *   - Navolávačka vidí JEN sázky, kde je explicitně přiřazena (bet_campaign_callers)
 *   - Pro každou sázku se zobrazí seznam call-type leadů (kontaktů z bet_campaign_leads
 *     s delivery_type='call'), které ještě nejsou zpracované (stav=READY)
 *   - OZ příjemce je u každého leadu fixně známý (z bet_campaign_recipients) —
 *     win panel ho zobrazí jako readonly
 *
 * Routes:
 *   GET  /caller/campaigns                  → seznam sázek + leadů
 *
 * Vlastní verify se NEDĚLÁ tady — používá se existující POST /caller/verify
 * v CallerController, který má enforcement OZ locku pro bet kontakty.
 */
final class CallerCampaignsController
{
    /** Stejná doba zámku jako v anonymním poolu */
    private const LOCK_MINUTES = 10;

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * POST /caller/campaigns/lock — zamkne sázkový kontakt pro tuto navolávačku
     * a přesměruje na hlavní /caller obrazovku, kde s ním může pracovat.
     */
    public function postLock(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['navolavacka', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/caller/campaigns');
        }

        $callerId  = (int) $user['id'];
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        if ($contactId <= 0) {
            crm_redirect('/caller/campaigns');
        }

        // Validace: kontakt musí být v sázce, READY, a navolávačka musí být přiřazena
        // k té sázce (bet_campaign_callers).
        // Multi-tenant filter
        $vStmt = $this->pdo->prepare(
            "SELECT c.id, c.locked_by, c.locked_until, bcl.campaign_id
             FROM contacts c
             JOIN bet_campaign_leads bcl ON bcl.contact_id = c.id
             JOIN bet_campaign_recipients bcr ON bcr.id = bcl.recipient_id
             JOIN bet_campaign_callers bcc
                 ON bcc.campaign_id = bcl.campaign_id AND bcc.caller_id = :uid
             WHERE c.id = :cid
               AND c.stav = 'READY'
               AND bcr.delivery_type = 'call'
               AND c.tenant_id = :tid
             LIMIT 1"
        );
        $vStmt->execute(['uid' => $callerId, 'cid' => $contactId, 'tid' => crm_tenant_id()]);
        $row = $vStmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            crm_flash_set('Kontakt nelze otevřít — buď nepatří do žádné vaší kampaně, není READY, nebo má delivery=email.');
            crm_redirect('/caller/campaigns');
        }

        // Pokud je zamknutý jinou navolávačkou (a ne expirovaný) → odmítnout
        $lockedBy    = (int) ($row['locked_by'] ?? 0);
        $lockedUntil = (string) ($row['locked_until'] ?? '');
        if ($lockedBy > 0 && $lockedBy !== $callerId
            && $lockedUntil !== '' && strtotime($lockedUntil) > time()) {
            crm_flash_set('Kontakt právě používá jiná navolávačka.');
            crm_redirect('/caller/campaigns');
        }

        // Zamknout (stejně jako anonymní pool — 10 min sliding)
        $this->pdo->prepare(
            "UPDATE contacts
             SET locked_by = :uid,
                 locked_until = NOW(3) + INTERVAL " . self::LOCK_MINUTES . " MINUTE,
                 updated_at = NOW(3)
             WHERE id = :cid AND tenant_id = :tid"
        )->execute(['uid' => $callerId, 'cid' => $contactId, 'tid' => crm_tenant_id()]);

        crm_flash_set('Kontakt zamčen na ' . self::LOCK_MINUTES . ' min. Otevírám pracovní plochu…');
        crm_redirect('/caller#contact-row-' . $contactId);
    }

    /** GET /caller/campaigns — sázky této navolávačky */
    public function getIndex(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['navolavacka', 'majitel', 'superadmin']);

        $callerId = (int) $user['id'];
        $flash    = crm_flash_take();
        $csrf     = crm_csrf_token();

        // 1. Sázky, kam je tahle navolávačka přiřazena (přes bet_campaign_callers).
        //    Bere open I closed — closed znamená pouze "cleaning hotov", ale call-leady
        //    můžou být pořád READY a čekat na provolání. Vynechá pouze 'cancelled'.
        //    Filtr v PHP pak vyřadí sázky, kde už není žádný call-typ ke zpracování.
        // Multi-tenant filter
        $campStmt = $this->pdo->prepare(
            "SELECT bc.id, bc.name, bc.region, bc.target_count, bc.cleaned_count, bc.note,
                    bc.created_at, bc.status
             FROM bet_campaigns bc
             JOIN bet_campaign_callers bcc ON bcc.campaign_id = bc.id
             WHERE bcc.caller_id = :uid
               AND bc.status IN ('open', 'closed')
               AND bc.tenant_id = :tid
             ORDER BY (bc.status = 'open') DESC, bc.created_at ASC"
        );
        $campStmt->execute(['uid' => $callerId, 'tid' => crm_tenant_id()]);
        $campaigns = $campStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = [];
        if ($campaigns !== []) {
            $campIds = array_map(fn($c) => (int) $c['id'], $campaigns);

            // 2. Pro každou sázku: recipients (oz + delivery_type)
            $phCamp = implode(',', array_fill(0, count($campIds), '?'));
            // Multi-tenant filter
            $recStmt = $this->pdo->prepare(
                "SELECT bcr.campaign_id, bcr.id AS recipient_id, bcr.oz_id, bcr.target_count,
                        bcr.received_count, bcr.delivery_type, bcr.sort_order,
                        u.jmeno AS oz_name
                 FROM bet_campaign_recipients bcr
                 LEFT JOIN users u ON u.id = bcr.oz_id
                 WHERE bcr.campaign_id IN ($phCamp) AND bcr.tenant_id = ?
                 ORDER BY bcr.campaign_id, bcr.sort_order ASC"
            );
            $recStmt->execute(array_merge($campIds, [crm_tenant_id()]));
            $allRecipients = $recStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $recByCamp = [];
            foreach ($allRecipients as $r) {
                $cid = (int) $r['campaign_id'];
                $recByCamp[$cid] = $recByCamp[$cid] ?? [];
                $recByCamp[$cid][] = $r;
            }

            // 3. Pro každou sázku: nezpracované call-type leady (stav=READY)
            //    JOIN přes bet_campaign_leads → bet_campaign_recipients (delivery_type='call')
            // Multi-tenant filter
            $leadStmt = $this->pdo->prepare(
                "SELECT bcl.campaign_id, bcl.position, bcl.recipient_id,
                        c.id, c.firma, c.telefon, c.operator, c.region, c.stav,
                        c.locked_by, c.locked_until, c.assigned_caller_id,
                        u.jmeno AS oz_name
                 FROM bet_campaign_leads bcl
                 JOIN contacts c ON c.id = bcl.contact_id
                 JOIN bet_campaign_recipients bcr ON bcr.id = bcl.recipient_id
                 LEFT JOIN users u ON u.id = bcr.oz_id
                 WHERE bcl.campaign_id IN ($phCamp)
                   AND bcr.delivery_type = 'call'
                   AND c.stav = 'READY'
                   AND c.tenant_id = ?
                 ORDER BY bcl.campaign_id, bcl.position ASC"
            );
            $leadStmt->execute(array_merge($campIds, [crm_tenant_id()]));
            $allLeads = $leadStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $leadsByCamp = [];
            foreach ($allLeads as $l) {
                $cid = (int) $l['campaign_id'];
                $leadsByCamp[$cid] = $leadsByCamp[$cid] ?? [];
                $leadsByCamp[$cid][] = $l;
            }

            // 4. Stat: kolik už call-typů této sázky bylo dotaženo (CALLED_OK)
            //    a kolik v progresu (NEDOVOLANO, CALLBACK, ASSIGNED)
            // Multi-tenant filter
            $statStmt = $this->pdo->prepare(
                "SELECT bcl.campaign_id,
                        SUM(CASE WHEN c.stav = 'READY'        THEN 1 ELSE 0 END) AS waiting,
                        SUM(CASE WHEN c.stav IN ('ASSIGNED','NEDOVOLANO','CALLBACK') THEN 1 ELSE 0 END) AS inflight,
                        SUM(CASE WHEN c.stav IN ('CALLED_OK','FOR_SALES') THEN 1 ELSE 0 END) AS won,
                        SUM(CASE WHEN c.stav IN ('CALLED_BAD','NEZAJEM','IZOLACE','CHYBNY_KONTAKT') THEN 1 ELSE 0 END) AS lost
                 FROM bet_campaign_leads bcl
                 JOIN contacts c ON c.id = bcl.contact_id
                 JOIN bet_campaign_recipients bcr ON bcr.id = bcl.recipient_id
                 WHERE bcl.campaign_id IN ($phCamp)
                   AND bcr.delivery_type = 'call'
                   AND c.tenant_id = ?
                 GROUP BY bcl.campaign_id"
            );
            $statStmt->execute(array_merge($campIds, [crm_tenant_id()]));
            $statsByCamp = [];
            foreach ($statStmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
                $statsByCamp[(int) $s['campaign_id']] = [
                    'waiting'  => (int) $s['waiting'],
                    'inflight' => (int) $s['inflight'],
                    'won'      => (int) $s['won'],
                    'lost'     => (int) $s['lost'],
                ];
            }

            foreach ($campaigns as $c) {
                $cid = (int) $c['id'];
                $items[] = [
                    'campaign'   => $c,
                    'recipients' => $recByCamp[$cid] ?? [],
                    'leads'      => $leadsByCamp[$cid] ?? [],
                    'stats'      => $statsByCamp[$cid] ?? [
                        'waiting' => 0, 'inflight' => 0, 'won' => 0, 'lost' => 0,
                    ],
                ];
            }
        }

        // OZ list pro Win panel (potřebujeme zobrazit jméno OZ — i když je readonly,
        // form pošle skrytý sales_id pro server-side validaci)
        $title = 'Kampaně';
        ob_start();
        require dirname(__DIR__) . '/views/caller/campaigns.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }
}
