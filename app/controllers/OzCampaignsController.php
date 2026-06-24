<?php
// e:\Snecinatripu\app\controllers\OzCampaignsController.php
declare(strict_types=1);

/**
 * OzCampaignsController
 *
 * Dashboard pro OZ — přehled všech kampaní (sázek), do kterých byl OZ
 * zařazen jako recipient. Funguje pro call I email type.
 *
 * Per-kampaň statistiky:
 *   - Objednáno (target_count u recipients pro tento OZ)
 *   - Cleaned (received_count = kolik čistička dotáhla TM+O2)
 *   - Pro call-type leady:
 *       - Waiting (READY)
 *       - Inflight (ASSIGNED, NEDOVOLANO, CALLBACK)
 *       - Schůzky (CALLED_OK + workflow ve stavu SCHUZKA/NABIDKA/SANCE)
 *       - Smlouvy (workflow stav SMLOUVA / podpis potvrzen)
 *       - Prohry (NEZAJEM, CALLED_BAD, IZOLACE)
 *   - Pro email-type:
 *       - Jen seznam (EMAIL_READY) — bez "stages"
 *   - Úspěšnost:
 *       - % schůzek z cleaned (kolik z předaných leadů se OZ podařilo dostat na schůzku)
 *       - % smluv z cleaned
 *
 * Routes:
 *   GET /oz/campaigns         → seznam všech kampaní (overview)
 *   GET /oz/campaigns?id=N    → detail jedné kampaně (drill-down)
 */
final class OzCampaignsController
{
    public function __construct(private PDO $pdo)
    {
    }

    /** GET /oz/campaigns — seznam, případně detail (?id=N) */
    public function getIndex(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        $ozId    = (int) $user['id'];
        $detailId = (int) ($_GET['id'] ?? 0);
        $flash   = crm_flash_take();

        // ── 1) Všechny kampaně, kde je tento OZ recipient ──
        // INCLUSIVE: open + closed + cancelled (aby OZ viděl i historii)
        // Multi-tenant filter
        $stmt = $this->pdo->prepare(
            "SELECT bc.id, bc.name, bc.region, bc.target_count, bc.cleaned_count,
                    bc.status, bc.note, bc.created_at, bc.closed_at,
                    bcr.id AS recipient_id, bcr.target_count AS my_target,
                    bcr.received_count AS my_received, bcr.delivery_type
             FROM bet_campaigns bc
             JOIN bet_campaign_recipients bcr ON bcr.campaign_id = bc.id
             WHERE bcr.oz_id = :oz AND bc.tenant_id = :tid
             ORDER BY (bc.status = 'open') DESC, bc.created_at DESC"
        );
        $stmt->execute(['oz' => $ozId, 'tid' => crm_tenant_id()]);
        $campaignRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // ── 2) Pro každou kampaň: rozbor stavů call-type leadů ──
        // Email-type leady mají jednodušší stat: jen received_count + případně
        // přijaté do workflow (= OZ je zpracoval).
        $items = [];
        foreach ($campaignRows as $r) {
            $cId    = (int) $r['id'];
            $recId  = (int) $r['recipient_id'];
            $dType  = (string) $r['delivery_type'];
            $myTgt  = (int) $r['my_target'];
            $myRcv  = (int) $r['my_received'];

            // Stats z bet_campaign_leads JOIN contacts pro tohoto recipient
            // Multi-tenant filter
            $statsStmt = $this->pdo->prepare(
                "SELECT
                    SUM(CASE WHEN c.stav = 'READY'                                   THEN 1 ELSE 0 END) AS waiting,
                    SUM(CASE WHEN c.stav IN ('ASSIGNED','NEDOVOLANO','CALLBACK')     THEN 1 ELSE 0 END) AS inflight,
                    SUM(CASE WHEN c.stav IN ('CALLED_OK','FOR_SALES')                THEN 1 ELSE 0 END) AS won_call,
                    SUM(CASE WHEN c.stav IN ('NEZAJEM','CALLED_BAD','IZOLACE','CHYBNY_KONTAKT') THEN 1 ELSE 0 END) AS lost,
                    SUM(CASE WHEN c.stav = 'EMAIL_READY'                             THEN 1 ELSE 0 END) AS email_ready,
                    COUNT(*) AS total
                 FROM bet_campaign_leads bcl
                 JOIN contacts c ON c.id = bcl.contact_id
                 WHERE bcl.recipient_id = ? AND c.tenant_id = ?"
            );
            $statsStmt->execute([$recId, crm_tenant_id()]);
            $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            // Workflow stages: kolik z výhry se posunulo do SCHUZKA / SMLOUVA atd.
            // Včetně rozkladu pro tooltipy (callback, BO processing, uzavřené)
            $wfStmt = $this->pdo->prepare(
                "SELECT
                    SUM(CASE WHEN w.stav IN ('SCHUZKA','NABIDKA','SANCE') THEN 1 ELSE 0 END) AS schuzka,
                    SUM(CASE WHEN w.stav = 'CALLBACK'                     THEN 1 ELSE 0 END) AS wf_callback,
                    SUM(CASE WHEN w.stav IN ('NOVE','ZPRACOVAVA')         THEN 1 ELSE 0 END) AS in_processing,
                    SUM(CASE WHEN w.stav IN ('SMLOUVA','PODPIS')          THEN 1 ELSE 0 END) AS smlouva,
                    SUM(CASE WHEN w.stav = 'SMLOUVA' AND COALESCE(w.podpis_potvrzen,0) = 0 THEN 1 ELSE 0 END) AS bo_pending,
                    SUM(CASE WHEN COALESCE(w.podpis_potvrzen,0) = 1       THEN 1 ELSE 0 END) AS podepsano,
                    COUNT(*) AS in_workflow
                 FROM bet_campaign_leads bcl
                 JOIN oz_contact_workflow w ON w.contact_id = bcl.contact_id AND w.oz_id = ?
                 WHERE bcl.recipient_id = ?"
            );
            try {
                $wfStmt->execute([$ozId, $recId]);
                $wf = $wfStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            } catch (\PDOException $e) {
                $wf = [];
            }

            // Úspěšnost: ze cleaned (received) kolik je hotových výher (CALLED_OK + ve workflow)
            $wonTotal   = (int) ($stats['won_call'] ?? 0);
            $schuzka    = (int) ($wf['schuzka'] ?? 0);
            $wfCallback = (int) ($wf['wf_callback'] ?? 0);
            $inProc     = (int) ($wf['in_processing'] ?? 0);
            $smlouva    = (int) ($wf['smlouva'] ?? 0);
            $boPending  = (int) ($wf['bo_pending'] ?? 0);
            $podpis     = (int) ($wf['podepsano'] ?? 0);

            // Win rate = % CALLED_OK / received (cleaned to this OZ)
            $winRate = $myRcv > 0 ? round($wonTotal * 100 / $myRcv, 1) : 0.0;
            // Conv rate = % uzavřených smluv / cleaned
            $convRate = $myRcv > 0 ? round($podpis * 100 / $myRcv, 1) : 0.0;

            $items[] = [
                'id'            => $cId,
                'name'          => (string) $r['name'],
                'region'        => (string) $r['region'],
                'target_count'  => (int) $r['target_count'],
                'cleaned_count' => (int) $r['cleaned_count'],
                'status'        => (string) $r['status'],
                'note'          => (string) ($r['note'] ?? ''),
                'created_at'    => (string) $r['created_at'],
                'closed_at'     => (string) ($r['closed_at'] ?? ''),
                'recipient_id'  => $recId,
                'delivery_type' => $dType,
                'my_target'     => $myTgt,
                'my_received'   => $myRcv,
                'waiting'       => (int) ($stats['waiting'] ?? 0),
                'inflight'      => (int) ($stats['inflight'] ?? 0),
                'won_call'      => $wonTotal,
                'lost'          => (int) ($stats['lost'] ?? 0),
                'email_ready'   => (int) ($stats['email_ready'] ?? 0),
                'schuzka'       => $schuzka,
                'wf_callback'   => $wfCallback,
                'in_processing' => $inProc,
                'smlouva'       => $smlouva,
                'bo_pending'    => $boPending,
                'podpis'        => $podpis,
                'win_rate'      => $winRate,
                'conv_rate'     => $convRate,
            ];
        }

        // ── 3) Detail (pokud ?id=N) — seznam leadů této kampaně pro OZ ──
        $detailLeads = [];
        $detailItem  = null;
        if ($detailId > 0) {
            foreach ($items as $i) {
                if ($i['id'] === $detailId) {
                    $detailItem = $i;
                    break;
                }
            }
            if ($detailItem !== null) {
                // Multi-tenant filter
                $dlStmt = $this->pdo->prepare(
                    "SELECT bcl.position, bcl.cleaned_at,
                            c.id AS contact_id, c.firma, c.telefon, c.email, c.region,
                            c.operator, c.stav, c.datum_volani, c.datum_predani,
                            w.stav AS wf_stav
                     FROM bet_campaign_leads bcl
                     JOIN contacts c ON c.id = bcl.contact_id
                     LEFT JOIN oz_contact_workflow w ON w.contact_id = c.id AND w.oz_id = ?
                     WHERE bcl.recipient_id = ? AND c.tenant_id = ?
                     ORDER BY bcl.position ASC"
                );
                $dlStmt->execute([$ozId, $detailItem['recipient_id'], crm_tenant_id()]);
                $detailLeads = $dlStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        }

        $title = $detailItem ? ('Kampaň: ' . $detailItem['name']) : 'Moje kampaně';
        ob_start();
        require dirname(__DIR__) . '/views/oz/campaigns.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }
}
