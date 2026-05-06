<?php
// e:\Snecinatripu\app\controllers\AdminDatagridController.php
declare(strict_types=1);

/**
 * Live datagrid — power-user pohled na celou DB kontaktů.
 *
 * Routes:
 *   GET /admin/datagrid              — HTML stránka (skeleton, načítá data přes JSON endpoint)
 *   GET /admin/datagrid/data         — JSON endpoint (volá se každých 10 s + při ručním refresh)
 *
 * Použití:
 *   - Sloupce: ID, firma, telefon, region, navolávačka, OZ, stav, smlouva (číslo+datum), výročí, posl. změna
 *   - Sortování / filtry / search řeší Grid.js v prohlížeči (na 1k-5k řádků pohodové)
 *   - Auto-refresh 10 s (volitelné), highlight změn od posledního pollu
 */
final class AdminDatagridController
{
    // Max řádků v jednom JSON payloadu pro client-side rendering.
    // 50k zvládne browser pohodlně; pro produkční 350k+ DB postavíme
    // server-side search/pagination až bude potřeba (přidáme ?q= endpoint).
    private const MAX_ROWS = 50_000;

    public function __construct(private PDO $pdo) {}

    public function getIndex(): void
    {
        $actor = crm_require_user($this->pdo);
        crm_require_roles($actor, ['majitel', 'superadmin']);

        $title = 'Live datagrid — power view';
        $csrf  = crm_csrf_token();
        $flash = crm_flash_take();

        ob_start();
        require dirname(__DIR__) . '/views/admin/datagrid/index.php';
        $content = (string) ob_get_clean();
        $user = $actor; // alias pro layout/base.php (sidebar + topbar)
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    /**
     * JSON endpoint — vrátí všechny kontakty (s limitem) v plochém formátu pro Grid.js.
     */
    public function getData(): void
    {
        $actor = crm_require_user($this->pdo);
        crm_require_roles($actor, ['majitel', 'superadmin']);

        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');

        // POZOR: workflow JOIN MUSÍ být omezený na aktuálního OZ (c.assigned_sales_id),
        // jinak pokud má kontakt v oz_contact_workflow víc řádků (= historie OZ-ů,
        // např. po přesunu kontaktu mezi OZ-y), vznikne kartézský součin a kontakt
        // se v datagridu zduplikuje (každý workflow řádek = jeden řádek navíc).
        $sql = "SELECT
                    c.id,
                    c.firma,
                    c.telefon,
                    c.email,
                    c.region,
                    c.stav AS contact_stav,
                    c.created_at,
                    c.updated_at,
                    c.vyrocni_smlouvy,
                    COALESCE(w.stav, '—')             AS workflow_stav,
                    w.stav_changed_at,
                    w.cislo_smlouvy,
                    w.datum_uzavreni,
                    COALESCE(w.smlouva_trvani_roky, 3) AS smlouva_trvani_roky,
                    COALESCE(u_oz.jmeno, '')           AS oz_name,
                    COALESCE(u_cl.jmeno, '')           AS caller_name,
                    (SELECT COUNT(*) FROM oz_contact_actions a WHERE a.contact_id = c.id) AS deník_count
                FROM contacts c
                LEFT JOIN oz_contact_workflow w
                       ON w.contact_id = c.id
                      AND w.oz_id      = c.assigned_sales_id
                LEFT JOIN users u_oz ON u_oz.id = c.assigned_sales_id
                LEFT JOIN users u_cl ON u_cl.id = c.assigned_caller_id
                ORDER BY COALESCE(w.stav_changed_at, c.updated_at) DESC, c.id DESC
                LIMIT " . self::MAX_ROWS;

        try {
            $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'DB chyba'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Spočítat celkový počet (i nad limit) pro info v hlavičce
        try {
            $total = (int) $this->pdo->query("SELECT COUNT(*) FROM contacts")->fetchColumn();
        } catch (\PDOException) {
            $total = count($rows);
        }

        // Připravit ploché řádky pro Grid.js — { id, firma, ..., elapsed, vyroci_in_days }
        $now = time();
        $out = [];
        foreach ($rows as $r) {
            $changedTs = !empty($r['stav_changed_at']) ? strtotime((string) $r['stav_changed_at']) : null;
            $elapsedSec = $changedTs ? max(0, $now - $changedTs) : null;

            $vyrocniDate  = (string) ($r['vyrocni_smlouvy'] ?? '');
            $vyrociInDays = ($vyrocniDate !== '' && $vyrocniDate !== '0000-00-00')
                ? (int) floor((strtotime($vyrocniDate) - $now) / 86400)
                : null;

            $out[] = [
                'id'                  => (int) $r['id'],
                'firma'               => (string) ($r['firma']            ?? ''),
                'telefon'             => (string) ($r['telefon']          ?? ''),
                'email'               => (string) ($r['email']            ?? ''),
                'region'              => (string) ($r['region']           ?? ''),
                'contact_stav'        => (string) ($r['contact_stav']     ?? ''),
                'workflow_stav'       => (string) ($r['workflow_stav']    ?? '—'),
                'oz_name'             => (string) ($r['oz_name']          ?? ''),
                'caller_name'         => (string) ($r['caller_name']      ?? ''),
                'cislo_smlouvy'       => (string) ($r['cislo_smlouvy']    ?? ''),
                'datum_uzavreni'      => (string) ($r['datum_uzavreni']   ?? ''),
                'smlouva_trvani_roky' => (int)    ($r['smlouva_trvani_roky'] ?? 3),
                'vyrocni_smlouvy'     => $vyrocniDate,
                'vyroci_in_days'      => $vyrociInDays,
                'elapsed_sec'         => $elapsedSec,
                'stav_changed_at'     => (string) ($r['stav_changed_at']  ?? ''),
                'denik_count'         => (int)    ($r['deník_count']      ?? 0),
                'created_at'          => (string) ($r['created_at']       ?? ''),
            ];
        }

        echo json_encode([
            'ok'         => true,
            'rows'       => $out,
            'total_db'   => $total,
            'returned'   => count($out),
            'truncated'  => $total > self::MAX_ROWS,
            'fetched_at' => date('c'),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Historie změn pro konkrétní kontakt — JSON timeline.
     * GET /admin/datagrid/contact-history?id=42
     */
    public function getContactHistory(): void
    {
        $actor = crm_require_user($this->pdo);
        crm_require_roles($actor, ['majitel', 'superadmin']);

        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');

        $contactId = (int) ($_GET['id'] ?? 0);
        if ($contactId <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Chybí parametr id'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Hlavička kontaktu
        try {
            $hStmt = $this->pdo->prepare(
                "SELECT c.id, c.firma, c.telefon, c.email, c.region,
                        c.created_at AS contact_created,
                        COALESCE(w.stav, '—') AS current_stav,
                        w.stav_changed_at,
                        w.cislo_smlouvy, w.datum_uzavreni
                 FROM contacts c
                 LEFT JOIN oz_contact_workflow w ON w.contact_id = c.id
                 WHERE c.id = :cid LIMIT 1"
            );
            $hStmt->execute(['cid' => $contactId]);
            $header = $hStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\PDOException) {
            $header = null;
        }
        if (!$header) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Kontakt nenalezen'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Historie z workflow_log (kdo, kdy, odkud → kam, proč)
        $history = crm_load_contact_history($this->pdo, $contactId);

        echo json_encode([
            'ok'      => true,
            'header'  => $header,
            'history' => $history,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Plnohodnotná activity-feed stránka.
     * GET /admin/feed — HTML view, který načítá data ze stejného JSON endpointu.
     */
    public function getFeedPage(): void
    {
        $actor = crm_require_user($this->pdo);
        crm_require_roles($actor, ['majitel', 'superadmin']);

        $title = 'Activity feed — co se právě děje';
        $csrf  = crm_csrf_token();
        $flash = crm_flash_take();

        ob_start();
        require dirname(__DIR__) . '/views/admin/feed/index.php';
        $content = (string) ob_get_clean();
        $user = $actor; // alias pro layout/base.php (sidebar + topbar)
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    /**
     * Activity feed — real-time changelog napříč CRM.
     * Sleduje změny v oz_contact_workflow + záznamy v pracovním deníku (oz_contact_actions).
     *
     * GET param ?since=<unixtime> — vrátí jen události od daného timestampu (pro polling).
     */
    public function getFeed(): void
    {
        $actor = crm_require_user($this->pdo);
        crm_require_roles($actor, ['majitel', 'superadmin']);

        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');

        $sinceParam = (string) ($_GET['since'] ?? '');
        $sinceWhere = '';
        if ($sinceParam !== '' && ctype_digit($sinceParam)) {
            $sinceTs = (int) $sinceParam;
            $sinceDt = date('Y-m-d H:i:s.v', $sinceTs);
            $sinceWhere = "WHERE event_ts > '" . $sinceDt . "'";
        }

        // UNION dvou zdrojů událostí + sjednoceně setřídíme od nejnovějšího
        $sql = "(
                  SELECT 'stav_change' AS kind,
                         w.contact_id,
                         w.stav_changed_at AS event_ts,
                         w.stav AS payload,
                         c.firma,
                         c.region,
                         COALESCE(u.jmeno, '—') AS actor_name,
                         w.stav AS extra
                  FROM oz_contact_workflow w
                  INNER JOIN contacts c ON c.id = w.contact_id
                  LEFT JOIN users u ON u.id = w.oz_id
                  WHERE w.stav_changed_at IS NOT NULL
                ) UNION ALL (
                  SELECT 'action' AS kind,
                         a.contact_id,
                         a.created_at AS event_ts,
                         LEFT(a.action_text, 120) AS payload,
                         c.firma,
                         c.region,
                         COALESCE(u.jmeno, '—') AS actor_name,
                         '' AS extra
                  FROM oz_contact_actions a
                  INNER JOIN contacts c ON c.id = a.contact_id
                  LEFT JOIN users u ON u.id = a.oz_id
                )";
        if ($sinceWhere !== '') {
            $sql = "SELECT * FROM ({$sql}) AS evt {$sinceWhere} ORDER BY event_ts DESC LIMIT 100";
        } else {
            $sql = "SELECT * FROM ({$sql}) AS evt ORDER BY event_ts DESC LIMIT 100";
        }

        $events = [];
        try {
            $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $now  = time();
            foreach ($rows as $r) {
                $ts = strtotime((string) $r['event_ts']);
                $events[] = [
                    'kind'         => (string) $r['kind'],
                    'contact_id'   => (int)    $r['contact_id'],
                    'firma'        => (string) ($r['firma']      ?? ''),
                    'region'       => (string) ($r['region']     ?? ''),
                    'actor_name'   => (string) ($r['actor_name'] ?? '—'),
                    'payload'      => (string) ($r['payload']    ?? ''),
                    'event_ts'     => (string) $r['event_ts'],
                    'event_unix'   => $ts,
                    'elapsed_sec'  => $ts ? max(0, $now - $ts) : null,
                ];
            }
        } catch (\PDOException $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'DB chyba'], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok'         => true,
            'events'     => $events,
            'fetched_at' => date('c'),
            'now_unix'   => time(),
        ], JSON_UNESCAPED_UNICODE);
    }
}
