<?php
declare(strict_types=1);

/**
 * AdminBetController
 *
 * Admin tool pro správu sázek (bet_campaigns).
 *
 * Routes:
 *   GET  /admin/bet              → seznam sázek (list)
 *   GET  /admin/bet/new          → form nové sázky
 *   POST /admin/bet/create       → uloží sázku + recipients
 *   GET  /admin/bet/show?id=N    → detail sázky + progress + tlačítko Uzavřít
 *   POST /admin/bet/close        → uzavře sázku (manuálně, i částečně)
 *   POST /admin/bet/cancel       → zruší sázku (status=cancelled)
 *
 * Pouze role: majitel, superadmin.
 */
final class AdminBetController
{
    public function __construct(private PDO $pdo)
    {
    }

    /** GET /admin/bet — seznam všech sázek (otevřené + uzavřené) */
    public function getIndex(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        $stmt = $this->pdo->query(
            "SELECT bc.id, bc.name, bc.region, bc.target_count, bc.cleaned_count,
                    bc.status, bc.created_at, bc.closed_at,
                    u.jmeno AS creator_name
             FROM bet_campaigns bc
             LEFT JOIN users u ON u.id = bc.created_by
             ORDER BY bc.status = 'open' DESC, bc.created_at DESC
             LIMIT 200"
        );
        $campaigns = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

        $flash = crm_flash_take();
        $title = 'Sázky';
        ob_start();
        require dirname(__DIR__) . '/views/admin/bet/index.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    /** GET /admin/bet/new — form nové sázky */
    public function getNew(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        // Načti OZ pro <select> (multi-role aware)
        $ozList = $this->pdo->query(
            "SELECT id, jmeno, email
             FROM users
             WHERE aktivni = 1 AND (
                 role = 'obchodak'
                 OR JSON_CONTAINS(IFNULL(roles_extra, '[]'), '\"obchodak\"')
             )
             ORDER BY jmeno ASC"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $regionChoices = function_exists('crm_region_choices') ? crm_region_choices() : [];

        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();
        $title = 'Nová sázka';
        ob_start();
        require dirname(__DIR__) . '/views/admin/bet/new.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    /** POST /admin/bet/create — uloží sázku + recipients */
    public function postCreate(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/bet');
        }

        $name        = trim((string) ($_POST['name'] ?? ''));
        $region      = trim((string) ($_POST['region'] ?? ''));
        $targetCount = max(1, (int) ($_POST['target_count'] ?? 0));
        $note        = trim((string) ($_POST['note'] ?? ''));

        // Recipients — pole pod indexy 0, 1, ...
        // POST: recipients[0][oz_id], recipients[0][target], recipients[0][delivery]
        $recipientsRaw = (array) ($_POST['recipients'] ?? []);

        $recipients = [];
        $sumRecip   = 0;
        foreach ($recipientsRaw as $sortOrder => $r) {
            $ozId = (int) ($r['oz_id'] ?? 0);
            $tgt  = (int) ($r['target'] ?? 0);
            $del  = (string) ($r['delivery'] ?? 'call');
            if ($ozId <= 0 || $tgt <= 0) {
                continue;
            }
            if (!in_array($del, ['call', 'email'], true)) {
                $del = 'call';
            }
            $recipients[] = [
                'oz_id'      => $ozId,
                'target'     => $tgt,
                'delivery'   => $del,
                'sort_order' => count($recipients) + 1, // přečíslujeme 1..N
            ];
            $sumRecip += $tgt;
        }

        // Validace
        if ($name === '' || $region === '' || $targetCount <= 0 || $recipients === []) {
            crm_flash_set('Vyplňte název, kraj, cíl a alespoň jednoho příjemce.');
            crm_redirect('/admin/bet/new');
        }
        if ($sumRecip !== $targetCount) {
            crm_flash_set("Součet target_count u příjemců ($sumRecip) se musí rovnat cíli sázky ($targetCount).");
            crm_redirect('/admin/bet/new');
        }

        // Kontrola, že v tomto kraji už neběží jiná sázka (jedna otevřená per kraj)
        $existCheck = $this->pdo->prepare(
            "SELECT id FROM bet_campaigns WHERE region = ? AND status = 'open' LIMIT 1"
        );
        $existCheck->execute([$region]);
        if ($existCheck->fetchColumn() !== false) {
            crm_flash_set("V kraji '$region' už běží jiná aktivní sázka. Nejprve ji uzavřete.");
            crm_redirect('/admin/bet');
        }

        // INSERT transakčně
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare(
                "INSERT INTO bet_campaigns (name, region, target_count, note, created_by, created_at)
                 VALUES (:name, :region, :target, :note, :uid, NOW(3))"
            )->execute([
                'name'   => $name,
                'region' => $region,
                'target' => $targetCount,
                'note'   => $note !== '' ? $note : null,
                'uid'    => (int) $user['id'],
            ]);
            $campaignId = (int) $this->pdo->lastInsertId();

            $rStmt = $this->pdo->prepare(
                "INSERT INTO bet_campaign_recipients
                 (campaign_id, oz_id, target_count, delivery_type, sort_order)
                 VALUES (:cid, :oz, :tgt, :del, :sort)"
            );
            foreach ($recipients as $r) {
                $rStmt->execute([
                    'cid'  => $campaignId,
                    'oz'   => $r['oz_id'],
                    'tgt'  => $r['target'],
                    'del'  => $r['delivery'],
                    'sort' => $r['sort_order'],
                ]);
            }
            $this->pdo->commit();

            crm_flash_set("Sázka '$name' vytvořena (ID $campaignId).");
            crm_redirect('/admin/bet/show?id=' . $campaignId);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            crm_flash_set('Chyba při ukládání: ' . $e->getMessage());
            crm_redirect('/admin/bet/new');
        }
    }

    /** GET /admin/bet/show?id=N — detail sázky */
    public function getShow(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            crm_redirect('/admin/bet');
        }

        $stmt = $this->pdo->prepare(
            "SELECT bc.*, u.jmeno AS creator_name
             FROM bet_campaigns bc
             LEFT JOIN users u ON u.id = bc.created_by
             WHERE bc.id = ?"
        );
        $stmt->execute([$id]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$campaign) {
            crm_flash_set('Sázka nenalezena.');
            crm_redirect('/admin/bet');
        }

        // Recipients
        $rStmt = $this->pdo->prepare(
            "SELECT r.*, u.jmeno AS oz_name
             FROM bet_campaign_recipients r
             LEFT JOIN users u ON u.id = r.oz_id
             WHERE r.campaign_id = ?
             ORDER BY r.sort_order ASC"
        );
        $rStmt->execute([$id]);
        $recipients = $rStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Sample leads (posledních 50 — pro náhled)
        $lStmt = $this->pdo->prepare(
            "SELECT l.position, l.cleaned_at, c.firma, c.region, c.stav, c.operator,
                    r.delivery_type, ru.jmeno AS recipient_name
             FROM bet_campaign_leads l
             LEFT JOIN contacts c ON c.id = l.contact_id
             LEFT JOIN bet_campaign_recipients r ON r.id = l.recipient_id
             LEFT JOIN users ru ON ru.id = r.oz_id
             WHERE l.campaign_id = ?
             ORDER BY l.position DESC
             LIMIT 50"
        );
        $lStmt->execute([$id]);
        $sampleLeads = $lStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();
        $title = 'Sázka: ' . (string) $campaign['name'];
        ob_start();
        require dirname(__DIR__) . '/views/admin/bet/show.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    /** POST /admin/bet/close — uzavře sázku manuálně (i částečně) */
    public function postClose(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/bet');
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            crm_redirect('/admin/bet');
        }

        $stmt = $this->pdo->prepare(
            "UPDATE bet_campaigns SET status = 'closed', closed_at = NOW(3)
             WHERE id = ? AND status = 'open'"
        );
        $stmt->execute([$id]);

        crm_flash_set('Sázka uzavřena.');
        crm_redirect('/admin/bet/show?id=' . $id);
    }

    /** POST /admin/bet/cancel — zruší sázku (status=cancelled) */
    public function postCancel(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/bet');
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            crm_redirect('/admin/bet');
        }

        $stmt = $this->pdo->prepare(
            "UPDATE bet_campaigns SET status = 'cancelled', closed_at = NOW(3)
             WHERE id = ? AND status = 'open'"
        );
        $stmt->execute([$id]);

        crm_flash_set('Sázka zrušena.');
        crm_redirect('/admin/bet');
    }
}
