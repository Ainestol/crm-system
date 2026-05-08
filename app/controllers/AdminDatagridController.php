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
                    c.ico,
                    c.telefon,
                    c.email,
                    c.adresa,
                    c.region,
                    c.operator,
                    c.prilez,
                    c.poznamka,
                    c.stav AS contact_stav,
                    c.rejection_reason,
                    c.nedovolano_count,
                    c.callback_at,
                    c.datum_volani,
                    c.datum_predani,
                    c.dnc_flag,
                    c.narozeniny_majitele,
                    c.sale_price,
                    c.activation_date,
                    c.cancellation_date,
                    c.created_at,
                    c.updated_at,
                    c.vyrocni_smlouvy,
                    COALESCE(w.stav, '—')             AS workflow_stav,
                    w.stav_changed_at,
                    w.cislo_smlouvy,
                    w.datum_uzavreni,
                    w.schuzka_at,
                    COALESCE(w.smlouva_trvani_roky, 3) AS smlouva_trvani_roky,
                    COALESCE(u_oz.jmeno, '')           AS oz_name,
                    COALESCE(u_cl.jmeno, '')           AS caller_name,
                    (SELECT COUNT(*) FROM oz_contact_actions a WHERE a.contact_id = c.id) AS denik_count,
                    (SELECT p.cleaning_status FROM premium_lead_pool p WHERE p.contact_id = c.id LIMIT 1) AS premium_clean,
                    (SELECT p.call_status     FROM premium_lead_pool p WHERE p.contact_id = c.id LIMIT 1) AS premium_call,
                    (SELECT p.order_id        FROM premium_lead_pool p WHERE p.contact_id = c.id LIMIT 1) AS premium_order
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
                'ico'                 => (string) ($r['ico']              ?? ''),
                'telefon'             => (string) ($r['telefon']          ?? ''),
                'email'               => (string) ($r['email']            ?? ''),
                'adresa'              => (string) ($r['adresa']           ?? ''),
                'region'              => (string) ($r['region']           ?? ''),
                'operator'            => (string) ($r['operator']         ?? ''),
                'prilez'              => (string) ($r['prilez']           ?? ''),
                'poznamka'            => (string) ($r['poznamka']         ?? ''),
                'contact_stav'        => (string) ($r['contact_stav']     ?? ''),
                'rejection_reason'    => (string) ($r['rejection_reason'] ?? ''),
                'nedovolano_count'    => (int)    ($r['nedovolano_count'] ?? 0),
                'callback_at'         => (string) ($r['callback_at']      ?? ''),
                'datum_volani'        => (string) ($r['datum_volani']     ?? ''),
                'datum_predani'       => (string) ($r['datum_predani']    ?? ''),
                'dnc_flag'            => (int)    ($r['dnc_flag']         ?? 0),
                'narozeniny_majitele' => (string) ($r['narozeniny_majitele'] ?? ''),
                'sale_price'          => (string) ($r['sale_price']       ?? ''),
                'activation_date'     => (string) ($r['activation_date']  ?? ''),
                'cancellation_date'   => (string) ($r['cancellation_date'] ?? ''),
                'workflow_stav'       => (string) ($r['workflow_stav']    ?? '—'),
                'oz_name'             => (string) ($r['oz_name']          ?? ''),
                'caller_name'         => (string) ($r['caller_name']      ?? ''),
                'cislo_smlouvy'       => (string) ($r['cislo_smlouvy']    ?? ''),
                'datum_uzavreni'      => (string) ($r['datum_uzavreni']   ?? ''),
                'schuzka_at'          => (string) ($r['schuzka_at']       ?? ''),
                'smlouva_trvani_roky' => (int)    ($r['smlouva_trvani_roky'] ?? 3),
                'vyrocni_smlouvy'     => $vyrocniDate,
                'vyroci_in_days'      => $vyrociInDays,
                'elapsed_sec'         => $elapsedSec,
                'stav_changed_at'     => (string) ($r['stav_changed_at']  ?? ''),
                'denik_count'         => (int)    ($r['denik_count']      ?? 0),
                'created_at'          => (string) ($r['created_at']       ?? ''),
                'updated_at'          => (string) ($r['updated_at']       ?? ''),
                'premium_clean'       => (string) ($r['premium_clean']    ?? ''),
                'premium_call'        => (string) ($r['premium_call']     ?? ''),
                'premium_order'       => (string) ($r['premium_order']    ?? ''),
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

        // Stránkování — ?page=N (default 1). 100 záznamů per stránka.
        // POZN: Při polling (since param) stránkování nemá smysl — to vrací jen nové.
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $page = min($page, 1000); // sanity max 100k záznamů
        $offset = ($page - 1) * 100;

        // UNION 3 zdrojů událostí — sjednoceně setřídíme od nejnovějšího:
        //   1) workflow_log     = KOMPLETNÍ HISTORIE změn stavu (navolávačky, čističky, OZ, BO, …)
        //   2) oz_contact_actions = záznamy v pracovním deníku OZ
        //   3) audit_log (premium) = premium pipeline akce (objednávka, čištění, navolávání)
        $sql = "(
                  SELECT 'stav_change' AS kind,
                         w.contact_id,
                         w.created_at  AS event_ts,
                         w.new_status  AS payload,
                         c.firma,
                         c.region,
                         COALESCE(u.jmeno, '—') AS actor_name,
                         IFNULL(w.note, '') AS extra
                  FROM workflow_log w
                  INNER JOIN contacts c ON c.id = w.contact_id
                  LEFT JOIN users u ON u.id = w.user_id
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
                ) UNION ALL (
                  SELECT 'premium' AS kind,
                         COALESCE(
                             (SELECT p.contact_id FROM premium_lead_pool p
                                WHERE p.id = al.entity_id AND al.entity_type = 'premium_lead_pool'
                                LIMIT 1),
                             0
                         ) AS contact_id,
                         al.created_at AS event_ts,
                         al.action AS payload,
                         COALESCE(
                             (SELECT c2.firma FROM contacts c2
                                JOIN premium_lead_pool p2 ON p2.contact_id = c2.id
                                WHERE p2.id = al.entity_id AND al.entity_type = 'premium_lead_pool'
                                LIMIT 1),
                             ''
                         ) AS firma,
                         '' AS region,
                         COALESCE(u.jmeno, '—') AS actor_name,
                         IFNULL(CAST(al.details AS CHAR), '') AS extra
                  FROM audit_log al
                  LEFT JOIN users u ON u.id = al.user_id
                  WHERE al.action LIKE 'premium%'
                )";
        if ($sinceWhere !== '') {
            // Polling mode — jen nové od posledního pollu, bez stránkování (offset by zničil polling)
            $sql = "SELECT * FROM ({$sql}) AS evt {$sinceWhere} ORDER BY event_ts DESC LIMIT 100";
        } else {
            // Normal mode — stránkování přes ?page=N
            $sql = "SELECT * FROM ({$sql}) AS evt ORDER BY event_ts DESC LIMIT 100 OFFSET {$offset}";
        }

        $events = [];
        try {
            $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            // Fallback: pokud selže UNION s premium (např. premium_lead_pool nebo audit_log
            // má jinou strukturu na produkci), vrátíme aspoň workflow + actions bez premium.
            crm_db_log_error($e, __METHOD__);
            try {
                $fallbackSql = "(
                                  SELECT 'stav_change' AS kind, w.contact_id, w.created_at AS event_ts,
                                         w.new_status AS payload, c.firma, c.region,
                                         COALESCE(u.jmeno, '—') AS actor_name, IFNULL(w.note, '') AS extra
                                  FROM workflow_log w
                                  INNER JOIN contacts c ON c.id = w.contact_id
                                  LEFT JOIN users u ON u.id = w.user_id
                                ) UNION ALL (
                                  SELECT 'action' AS kind, a.contact_id, a.created_at AS event_ts,
                                         LEFT(a.action_text, 120) AS payload, c.firma, c.region,
                                         COALESCE(u.jmeno, '—') AS actor_name, '' AS extra
                                  FROM oz_contact_actions a
                                  INNER JOIN contacts c ON c.id = a.contact_id
                                  LEFT JOIN users u ON u.id = a.oz_id
                                )";
                if ($sinceWhere !== '') {
                    $fallbackSql = "SELECT * FROM ({$fallbackSql}) AS evt {$sinceWhere} ORDER BY event_ts DESC LIMIT 100";
                } else {
                    $fallbackSql = "SELECT * FROM ({$fallbackSql}) AS evt ORDER BY event_ts DESC LIMIT 100 OFFSET {$offset}";
                }
                $rows = $this->pdo->query($fallbackSql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\PDOException $e2) {
                http_response_code(500);
                echo json_encode(['ok' => false, 'error' => 'DB chyba'], JSON_UNESCAPED_UNICODE);
                return;
            }
        }

        try {
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
                    'extra'        => (string) ($r['extra']      ?? ''),
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

        // Total events napříč všemi 3 zdroji — pro výpočet počtu stránek.
        // Polling mode (since param) tohle nepotřebuje, ušetříme query.
        $totalEvents = 0;
        $totalPages  = 1;
        if ($sinceWhere === '') {
            try {
                $tw = (int) ($this->pdo->query("SELECT COUNT(*) FROM workflow_log")->fetchColumn() ?: 0);
                $ta = (int) ($this->pdo->query("SELECT COUNT(*) FROM oz_contact_actions")->fetchColumn() ?: 0);
                $tp = 0;
                try {
                    $tp = (int) ($this->pdo->query("SELECT COUNT(*) FROM audit_log WHERE action LIKE 'premium%'")->fetchColumn() ?: 0);
                } catch (\PDOException) { /* fallback bez premium */ }
                $totalEvents = $tw + $ta + $tp;
                $totalPages  = max(1, (int) ceil($totalEvents / 100));
            } catch (\PDOException $e) {
                crm_db_log_error($e, __METHOD__);
            }
        }

        echo json_encode([
            'ok'           => true,
            'events'       => $events,
            'page'         => $page,
            'total_pages'  => $totalPages,
            'total_events' => $totalEvents,
            'has_more'     => count($events) === 100,
            'fetched_at'   => date('c'),
            'now_unix'     => time(),
        ], JSON_UNESCAPED_UNICODE);
    }
}
