<?php
// e:\Snecinatripu\app\controllers\PremiumCallerController.php
declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'audit.php';

/**
 * Premium pipeline — pracovní plocha 2 navolávačky.
 *
 * Princip:
 *   - Standardní `/caller` = pool (claim, kraje, base reward od majitele)
 *   - `/caller/premium`    = konkrétní objednávky OZ s bonusem za úspěšný hovor
 *
 * Premium leady jsou plně tracked v `premium_lead_pool` — `contacts`
 * je netknutá až do okamžiku úspěšného navolání (tehdy se nastaví
 * assigned_caller_id, assigned_sales_id, stav=CALLED_OK).
 *
 * Co navolávačka vidí v /caller/premium:
 *   - Objednávky kde je explicitně přidělená (preferred_caller_id = ja)
 *   - Plus objednávky bez přiděleného caller (rotace mezi všemi)
 *   - Jen takové, které mají alespoň 1 tradeable+pending lead
 *
 * Co se stane při úspěšném/neúspěšném navolání:
 *   tradeable+success → contacts: stav=CALLED_OK, assigned_sales_id=order.oz_id,
 *                                  assigned_caller_id=caller, datum_volani=now
 *                      pool:    call_status=success, caller_id=caller, called_at=now
 *   tradeable+failed  → contacts: stav podle důvodu (NEZAJEM/CALLED_BAD/NEDOVOLANO)
 *                      pool:    call_status=failed, caller_id, called_at
 *
 *   Po prvním navolání lead VYPADNE z premium queue, protože filter je
 *   call_status='pending' AND cleaning_status='tradeable'.
 */
final class PremiumCallerController
{
    /** Po kolika nedovoláních přejít automaticky na NEZAJEM (stejně jako /caller). */
    private const MAX_NEDOVOLANO = 3;

    /** Validní taby v /caller/premium (analogie /caller). */
    private const VALID_TABS = ['objednavky', 'k_volani', 'callbacky', 'nedovolano', 'navolane', 'prohra'];

    public function __construct(private PDO $pdo)
    {
    }

    // ════════════════════════════════════════════════════════════════
    //  GET /caller/premium — list dostupných premium objednávek
    // ════════════════════════════════════════════════════════════════
    public function getIndex(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['navolavacka', 'majitel', 'superadmin']);

        // Lazy refill — doplnit otevřené objednávky čerstvě vyčištěnými READY leady
        if (class_exists('PremiumOrderController')) {
            PremiumOrderController::topUpOpenOrders($this->pdo);
        }

        $callerId = (int) $user['id'];
        $isAdmin  = in_array((string) ($user['role'] ?? ''), ['majitel', 'superadmin'], true);

        $tab = (string) ($_GET['tab'] ?? 'objednavky');
        if (!in_array($tab, self::VALID_TABS, true)) $tab = 'objednavky';

        // Auto-promote NEDOVOLANO ≥ 3× → NEZAJEM (stejně jako /caller)
        try {
            $this->pdo->exec(
                "UPDATE contacts c
                 JOIN premium_lead_pool p ON p.contact_id = c.id
                 SET c.stav = 'NEZAJEM', c.updated_at = NOW(3)
                 WHERE c.stav = 'NEDOVOLANO'
                   AND c.nedovolano_count >= " . self::MAX_NEDOVOLANO . "
                   AND p.cleaning_status = 'tradeable'"
            );
        } catch (\PDOException $e) { crm_db_log_error($e, __METHOD__); }

        // Tab badges
        $tabCounts = $this->computeTabCounts($callerId, $isAdmin);

        // Data per active tab
        $orders = ($tab === 'objednavky') ? $this->loadOrders($callerId, $isAdmin) : [];
        $leads  = ($tab !== 'objednavky') ? $this->loadLeadsForTab($tab, $callerId, $isAdmin) : [];

        // Souhrnné statistiky bonusu pro caller
        $year  = (int) date('Y');
        $month = (int) date('n');
        $bonusStmt = $this->pdo->prepare(
            "SELECT SUM(po.caller_bonus_per_lead) AS bonus_czk, COUNT(*) AS bonus_count
             FROM premium_lead_pool p
             JOIN premium_orders po ON po.id = p.order_id
             WHERE p.caller_id = :cid AND p.call_status = 'success'
               AND p.cleaning_status = 'tradeable' AND p.flagged_for_refund = 0
               AND po.caller_bonus_per_lead > 0
               AND YEAR(p.called_at) = :y AND MONTH(p.called_at) = :m"
        );
        $bonusStmt->execute(['cid' => $callerId, 'y' => $year, 'm' => $month]);
        $bonusRow = $bonusStmt->fetch(PDO::FETCH_ASSOC);
        $monthBonus      = (float) ($bonusRow['bonus_czk'] ?? 0);
        $monthBonusCount = (int)   ($bonusRow['bonus_count'] ?? 0);

        $todayStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM premium_lead_pool
             WHERE caller_id = :cid AND call_status = 'success' AND DATE(called_at) = CURDATE()"
        );
        $todayStmt->execute(['cid' => $callerId]);
        $todayWins = (int) $todayStmt->fetchColumn();

        $title = '💎 Premium navolávky';
        $csrf  = crm_csrf_token();
        $flash = crm_flash_take();

        ob_start();
        require dirname(__DIR__) . '/views/caller/premium/index.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    // ════════════════════════════════════════════════════════════════
    //  Helpery — taby, queries
    // ════════════════════════════════════════════════════════════════

    /**
     * Mapování tab → SQL WHERE pro contacts.stav.
     * @return array{0:string, 1:array<string,mixed>}
     */
    private function tabFilter(string $tab): array
    {
        return match ($tab) {
            'k_volani'   => ["c.stav = 'READY'", []],
            'callbacky'  => ["c.stav = 'CALLBACK'", []],
            'nedovolano' => ["c.stav = 'NEDOVOLANO' AND c.nedovolano_count < :max_nedo",
                             ['max_nedo' => self::MAX_NEDOVOLANO]],
            'navolane'   => ["c.stav IN ('CALLED_OK', 'FOR_SALES')", []],
            'prohra'     => ["c.stav IN ('NEZAJEM', 'CALLED_BAD')", []],
            default      => ["1=0", []],
        };
    }

    /**
     * Authorization filter — preferred_caller=ja NEBO rotace; admin vše.
     * @return array{0:string, 1:array<string,mixed>}
     */
    private function accessFilter(int $callerId, bool $isAdmin): array
    {
        if ($isAdmin) return ['', []];
        return [
            "AND (po.preferred_caller_id = :cid_acc OR po.preferred_caller_id IS NULL)",
            ['cid_acc' => $callerId],
        ];
    }

    /**
     * Spočítat tradeable leady per state pro tab badges.
     * @return array<string,int>
     */
    private function computeTabCounts(int $callerId, bool $isAdmin): array
    {
        [$accSql, $accParams] = $this->accessFilter($callerId, $isAdmin);

        $sql = "SELECT
                  SUM(CASE WHEN c.stav = 'READY' THEN 1 ELSE 0 END)                       AS k_volani,
                  SUM(CASE WHEN c.stav = 'CALLBACK' THEN 1 ELSE 0 END)                    AS callbacky,
                  SUM(CASE WHEN c.stav = 'NEDOVOLANO' AND c.nedovolano_count < :max_nedo
                            THEN 1 ELSE 0 END)                                             AS nedovolano,
                  SUM(CASE WHEN c.stav IN ('CALLED_OK', 'FOR_SALES') THEN 1 ELSE 0 END)   AS navolane,
                  SUM(CASE WHEN c.stav IN ('NEZAJEM', 'CALLED_BAD') THEN 1 ELSE 0 END)    AS prohra
                FROM premium_lead_pool p
                JOIN premium_orders po ON po.id = p.order_id
                JOIN contacts c        ON c.id  = p.contact_id
                WHERE p.cleaning_status = 'tradeable'
                  AND po.status IN ('open', 'closed')
                  $accSql";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge(['max_nedo' => self::MAX_NEDOVOLANO], $accParams));
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Objednavky badge: počet objednávek s alespoň 1 callable lead
        $objStmt = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT po.id)
             FROM premium_lead_pool p
             JOIN premium_orders po ON po.id = p.order_id
             JOIN contacts c        ON c.id  = p.contact_id
             WHERE p.cleaning_status = 'tradeable'
               AND po.status IN ('open', 'closed')
               AND (c.stav = 'READY' OR c.stav = 'CALLBACK'
                    OR (c.stav = 'NEDOVOLANO' AND c.nedovolano_count < :max_nedo3))
               $accSql"
        );
        $objStmt->execute(array_merge(['max_nedo3' => self::MAX_NEDOVOLANO], $accParams));
        $row['objednavky'] = (int) $objStmt->fetchColumn();

        return array_map('intval', $row);
    }

    /**
     * Načíst objednávky pro tab 'objednavky'.
     * @return list<array<string,mixed>>
     */
    private function loadOrders(int $callerId, bool $isAdmin): array
    {
        [$accSql, $accParams] = $this->accessFilter($callerId, $isAdmin);
        $maxN = self::MAX_NEDOVOLANO;

        $sql = "SELECT po.id              AS order_id,
                       po.oz_id,
                       u_oz.jmeno         AS oz_name,
                       po.year, po.month,
                       po.price_per_lead,
                       po.caller_bonus_per_lead,
                       po.preferred_caller_id,
                       u_pc.jmeno         AS preferred_caller_name,
                       po.regions_json,
                       po.note,
                       po.created_at,
                       po.status          AS order_status,
                       (SELECT COUNT(*) FROM premium_lead_pool p
                          JOIN contacts c ON c.id = p.contact_id
                          WHERE p.order_id = po.id AND p.cleaning_status = 'tradeable'
                            AND (c.stav = 'READY' OR c.stav = 'CALLBACK'
                                 OR (c.stav = 'NEDOVOLANO' AND c.nedovolano_count < $maxN))
                       ) AS callable_count,
                       (SELECT COUNT(*) FROM premium_lead_pool p
                          WHERE p.order_id = po.id AND p.cleaning_status = 'tradeable'
                            AND p.call_status = 'success' AND p.caller_id = :cid_done) AS my_done
                FROM premium_orders po
                JOIN users u_oz ON u_oz.id = po.oz_id
                LEFT JOIN users u_pc ON u_pc.id = po.preferred_caller_id
                WHERE po.status IN ('open', 'closed')
                  AND EXISTS (
                      SELECT 1 FROM premium_lead_pool p
                      JOIN contacts c ON c.id = p.contact_id
                      WHERE p.order_id = po.id AND p.cleaning_status = 'tradeable'
                        AND (c.stav = 'READY' OR c.stav = 'CALLBACK'
                             OR (c.stav = 'NEDOVOLANO' AND c.nedovolano_count < $maxN))
                  )
                  $accSql
                ORDER BY (po.preferred_caller_id = :cid_priority) DESC,
                         callable_count DESC,
                         po.created_at ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge(
            ['cid_done' => $callerId, 'cid_priority' => $callerId],
            $accParams
        ));
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Načíst leady pro state tab — flat list across všech objednávek.
     * @return list<array<string,mixed>>
     */
    private function loadLeadsForTab(string $tab, int $callerId, bool $isAdmin): array
    {
        [$tabSql, $tabParams] = $this->tabFilter($tab);
        [$accSql, $accParams] = $this->accessFilter($callerId, $isAdmin);

        $orderBy = match ($tab) {
            'callbacky'         => 'ORDER BY c.callback_at ASC, c.id ASC',
            'nedovolano'        => 'ORDER BY c.updated_at DESC, c.id ASC',
            'navolane', 'prohra'=> 'ORDER BY c.datum_volani DESC, c.updated_at DESC',
            default             => 'ORDER BY c.id ASC',
        };

        $sql = "SELECT p.id AS pool_id, p.contact_id, p.call_status, p.caller_id, p.called_at,
                       po.id AS order_id, po.oz_id, po.caller_bonus_per_lead, po.status AS order_status,
                       u_oz.jmeno AS oz_name,
                       u_call.jmeno AS caller_name,
                       c.firma, c.telefon, c.email, c.region, c.operator, c.prilez,
                       c.adresa, c.ico, c.poznamka, c.stav AS contact_stav,
                       c.callback_at, c.nedovolano_count, c.datum_volani
                FROM premium_lead_pool p
                JOIN premium_orders po ON po.id = p.order_id
                JOIN contacts c        ON c.id  = p.contact_id
                JOIN users u_oz        ON u_oz.id = po.oz_id
                LEFT JOIN users u_call ON u_call.id = p.caller_id
                WHERE p.cleaning_status = 'tradeable'
                  AND po.status IN ('open', 'closed')
                  AND ($tabSql)
                  $accSql
                $orderBy
                LIMIT 200";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($tabParams, $accParams));
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ════════════════════════════════════════════════════════════════
    //  GET /caller/premium/order?id=X — detail objednávky, leady k volání
    // ════════════════════════════════════════════════════════════════
    public function getOrder(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['navolavacka', 'majitel', 'superadmin']);

        $callerId = (int) $user['id'];
        $orderId  = (int) ($_GET['id'] ?? 0);
        if ($orderId <= 0) crm_redirect('/caller/premium');

        $tab = (string) ($_GET['tab'] ?? 'k_volani');
        $orderTabs = ['k_volani', 'callbacky', 'nedovolano', 'navolane', 'prohra'];
        if (!in_array($tab, $orderTabs, true)) $tab = 'k_volani';

        // Auto-promote NEDOVOLANO ≥ 3× → NEZAJEM (jen pro tuto objednávku)
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE contacts c
                 JOIN premium_lead_pool p ON p.contact_id = c.id
                 SET c.stav = 'NEZAJEM', c.updated_at = NOW(3)
                 WHERE c.stav = 'NEDOVOLANO'
                   AND c.nedovolano_count >= " . self::MAX_NEDOVOLANO . "
                   AND p.cleaning_status = 'tradeable'
                   AND p.order_id = :oid"
            );
            $stmt->execute(['oid' => $orderId]);
        } catch (\PDOException $e) { crm_db_log_error($e, __METHOD__); }

        // Hlavička objednávky
        $oStmt = $this->pdo->prepare(
            "SELECT po.*, u_oz.jmeno AS oz_name, u_pc.jmeno AS preferred_caller_name
             FROM premium_orders po
             JOIN users u_oz ON u_oz.id = po.oz_id
             LEFT JOIN users u_pc ON u_pc.id = po.preferred_caller_id
             WHERE po.id = :id LIMIT 1"
        );
        $oStmt->execute(['id' => $orderId]);
        $order = $oStmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            crm_flash_set('⚠ Objednávka nenalezena.');
            crm_redirect('/caller/premium');
        }

        // Authorization: preferred_caller, NULL=rotace, admin
        $isAdmin    = in_array((string) ($user['role'] ?? ''), ['majitel', 'superadmin'], true);
        $isMineNull = ($order['preferred_caller_id'] === null);
        $isMine     = ((int) ($order['preferred_caller_id'] ?? 0) === $callerId);
        if (!$isAdmin && !$isMineNull && !$isMine) {
            crm_flash_set('⚠ K této objednávce nemáte přístup (patří jiné navolávačce).');
            crm_redirect('/caller/premium');
        }

        // Spočítat counts per tab (jen pro tuto objednávku)
        $tabCounts = $this->computeOrderTabCounts($orderId);

        // Načíst leady pro aktivní tab
        $leads = $this->loadOrderLeadsForTab($orderId, $tab);

        $title = '💎 Premium objednávka #' . $orderId;
        $csrf  = crm_csrf_token();
        $flash = crm_flash_take();

        ob_start();
        require dirname(__DIR__) . '/views/caller/premium/order.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    /**
     * Tab counts pro jednu objednávku.
     * @return array<string,int>
     */
    private function computeOrderTabCounts(int $orderId): array
    {
        $sql = "SELECT
                  SUM(CASE WHEN c.stav = 'READY' THEN 1 ELSE 0 END)                       AS k_volani,
                  SUM(CASE WHEN c.stav = 'CALLBACK' THEN 1 ELSE 0 END)                    AS callbacky,
                  SUM(CASE WHEN c.stav = 'NEDOVOLANO' AND c.nedovolano_count < :max_nedo
                            THEN 1 ELSE 0 END)                                             AS nedovolano,
                  SUM(CASE WHEN c.stav IN ('CALLED_OK', 'FOR_SALES') THEN 1 ELSE 0 END)   AS navolane,
                  SUM(CASE WHEN c.stav IN ('NEZAJEM', 'CALLED_BAD') THEN 1 ELSE 0 END)    AS prohra
                FROM premium_lead_pool p
                JOIN contacts c ON c.id = p.contact_id
                WHERE p.order_id = :oid AND p.cleaning_status = 'tradeable'";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['oid' => $orderId, 'max_nedo' => self::MAX_NEDOVOLANO]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return array_map('intval', $row);
    }

    /**
     * Leady pro tab v rámci jedné objednávky.
     * @return list<array<string,mixed>>
     */
    private function loadOrderLeadsForTab(int $orderId, string $tab): array
    {
        [$tabSql, $tabParams] = $this->tabFilter($tab);

        $orderBy = match ($tab) {
            'callbacky'         => 'ORDER BY c.callback_at ASC, c.id ASC',
            'nedovolano'        => 'ORDER BY c.updated_at DESC, c.id ASC',
            'navolane', 'prohra'=> 'ORDER BY c.datum_volani DESC, c.updated_at DESC',
            default             => 'ORDER BY c.id ASC',
        };

        $sql = "SELECT p.id AS pool_id, p.contact_id, p.call_status, p.caller_id, p.called_at,
                       u_call.jmeno AS caller_name,
                       c.firma, c.telefon, c.email, c.region, c.operator, c.prilez,
                       c.adresa, c.ico, c.poznamka, c.stav AS contact_stav,
                       c.callback_at, c.nedovolano_count, c.datum_volani
                FROM premium_lead_pool p
                JOIN contacts c ON c.id = p.contact_id
                LEFT JOIN users u_call ON u_call.id = p.caller_id
                WHERE p.order_id = :oid AND p.cleaning_status = 'tradeable'
                  AND ($tabSql)
                $orderBy
                LIMIT 200";

        $params = array_merge(['oid' => $orderId], $tabParams);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ════════════════════════════════════════════════════════════════
    //  POST /caller/premium/status — výsledek hovoru
    //
    //  action: success | nezajem | nedovolano | callback | called_bad
    //  pool_id, contact_id, poznamka (povinná u failed stavů)
    // ════════════════════════════════════════════════════════════════
    public function postStatus(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['navolavacka', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/caller/premium');
        }

        $callerId  = (int) $user['id'];
        $poolId    = (int) ($_POST['pool_id']    ?? 0);
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        $action    = (string) ($_POST['action']  ?? '');
        $poznamka  = trim((string) ($_POST['poznamka'] ?? ''));

        // Návratová URL — form pošle tu kterou viděla (index nebo order detail s tabem).
        // Bezpečnostní guard: jen relativní URL začínající /caller/premium.
        $returnUrlRaw = (string) ($_POST['return_url'] ?? '/caller/premium');
        $returnUrl = (str_starts_with($returnUrlRaw, '/caller/premium'))
            ? $returnUrlRaw
            : '/caller/premium';

        $allowedActions = ['success', 'nezajem', 'nedovolano', 'callback', 'called_bad'];
        if ($poolId <= 0 || $contactId <= 0 || !in_array($action, $allowedActions, true)) {
            crm_flash_set('⚠ Neplatný požadavek.');
            crm_redirect('/caller/premium');
        }

        // Načíst pool řádek + ověřit oprávnění
        $stmt = $this->pdo->prepare(
            "SELECT p.id, p.contact_id, p.order_id, p.cleaning_status, p.call_status,
                    po.oz_id, po.preferred_caller_id, po.status AS order_status,
                    po.caller_bonus_per_lead
             FROM premium_lead_pool p
             JOIN premium_orders po ON po.id = p.order_id
             WHERE p.id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $poolId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || (int) $row['contact_id'] !== $contactId) {
            crm_flash_set('⚠ Lead nenalezen nebo nesedí ID.');
            crm_redirect('/caller/premium');
        }
        if ($row['cleaning_status'] !== 'tradeable') {
            crm_flash_set('⚠ Lead není obchodovatelný — nelze volat.');
            crm_redirect('/caller/premium');
        }
        // Cancelled objednávka — stop. Closed je OK (caller pořád musí dokončit tradeable+pending).
        if ($row['order_status'] === 'cancelled') {
            crm_flash_set('⚠ Objednávka byla zrušena, leady nelze volat.');
            crm_redirect('/caller/premium');
        }

        // Authorization
        $isAdmin    = in_array((string) ($user['role'] ?? ''), ['majitel', 'superadmin'], true);
        $isMineNull = ($row['preferred_caller_id'] === null);
        $isMine     = ((int) ($row['preferred_caller_id'] ?? 0) === $callerId);
        if (!$isAdmin && !$isMineNull && !$isMine) {
            crm_flash_set('⚠ Tato objednávka patří jiné navolávačce.');
            crm_redirect('/caller/premium');
        }

        // Validace — pro failed akce vyžaduj poznámku
        if (in_array($action, ['nezajem', 'nedovolano', 'called_bad'], true) && mb_strlen($poznamka) < 2) {
            crm_flash_set('⚠ U neúspěšných hovorů je poznámka povinná.');
            crm_redirect('/caller/premium/order?id=' . (int) $row['order_id']);
        }
        if ($action === 'callback') {
            $cbDate = trim((string) ($_POST['callback_at'] ?? ''));
            if ($cbDate === '' || !strtotime($cbDate)) {
                crm_flash_set('⚠ Pro callback je povinné datum a čas.');
                crm_redirect('/caller/premium/order?id=' . (int) $row['order_id']);
            }
        }

        $orderId   = (int) $row['order_id'];
        $ozId      = (int) $row['oz_id'];

        // Mapování akce → stav contactu + call_status v poolu
        // POZN: NEDOVOLANO + CALLBACK pool.call_status zůstává 'pending' aby šlo
        // znovu volat. Final 'failed' jen pro NEZAJEM/CALLED_BAD (mrtvé leady).
        [$contactStav, $callStatus, $logNote] = match ($action) {
            'success'    => ['CALLED_OK',  'success', 'Premium: úspěšně navoláno'],
            'nezajem'    => ['NEZAJEM',    'failed',  'Premium: nezájem — ' . $poznamka],
            'nedovolano' => ['NEDOVOLANO', 'pending', 'Premium: nedovoláno — ' . $poznamka],
            'called_bad' => ['CALLED_BAD', 'failed',  'Premium: ' . $poznamka],
            'callback'   => ['CALLBACK',   'pending', 'Premium: callback domluven'],
        };

        try {
            $this->pdo->beginTransaction();

            // 1) UPDATE contacts
            $contactSql = "UPDATE contacts
                           SET stav               = :stav,
                               assigned_caller_id = :caller,
                               datum_volani       = NOW(3),
                               updated_at         = NOW(3)";
            $contactParams = ['stav' => $contactStav, 'caller' => $callerId];

            if ($action === 'success') {
                // Úspěšný hovor → přiřadit OZ ze objednávky + reset nedovolano counter
                $contactSql .= ", assigned_sales_id = :sales, datum_predani = NOW(3), nedovolano_count = 0";
                $contactParams['sales'] = $ozId;
            } elseif ($action === 'callback') {
                // Callback → callback_at, reset nedovolano counter
                $contactSql .= ", callback_at = :cb_at, nedovolano_count = 0";
                $contactParams['cb_at'] = date('Y-m-d H:i:s', strtotime((string) $_POST['callback_at']));
            } elseif ($action === 'nedovolano') {
                // Nedovoláno → +1 counter (auto-promote na NEZAJEM po MAX_NEDOVOLANO se řeší v getIndex)
                $contactSql .= ", nedovolano_count = nedovolano_count + 1";
            } elseif (in_array($action, ['nezajem', 'called_bad'], true)) {
                // Final loss — counter můžeme resetovat (lead je definitivně mrtvý)
                $contactSql .= ", nedovolano_count = 0";
            }
            $contactSql .= " WHERE id = :id";
            $contactParams['id'] = $contactId;

            $this->pdo->prepare($contactSql)->execute($contactParams);

            // 2) UPDATE pool
            // Pro callback ponecháme call_status='pending' aby zůstal v queue
            // (lead se v premium queue znovu objeví, jen ho navolávačka volá pozdějšě).
            if ($action !== 'callback') {
                $this->pdo->prepare(
                    "UPDATE premium_lead_pool
                     SET call_status = :st,
                         caller_id   = :cid,
                         called_at   = NOW(3)
                     WHERE id = :id"
                )->execute(['st' => $callStatus, 'cid' => $callerId, 'id' => $poolId]);
            } else {
                // Callback — jen nastavit caller_id (kdo volal naposled), called_at, ale status zustává pending
                $this->pdo->prepare(
                    "UPDATE premium_lead_pool
                     SET caller_id = :cid,
                         called_at = NOW(3)
                     WHERE id = :id"
                )->execute(['cid' => $callerId, 'id' => $poolId]);
            }

            // 3) Workflow log
            $this->pdo->prepare(
                "INSERT INTO workflow_log (contact_id, user_id, old_status, new_status, note, created_at)
                 VALUES (:cid, :uid, 'READY', :stav, :note, NOW(3))"
            )->execute([
                'cid'  => $contactId,
                'uid'  => $callerId,
                'stav' => $contactStav,
                'note' => $logNote,
            ]);

            // 4) Volitelná poznámka do contact_notes
            if ($poznamka !== '') {
                $this->pdo->prepare(
                    "INSERT INTO contact_notes (contact_id, user_id, note, created_at)
                     VALUES (:cid, :uid, :note, NOW(3))"
                )->execute(['cid' => $contactId, 'uid' => $callerId, 'note' => '[Premium] ' . $poznamka]);
            }

            $this->pdo->commit();
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            crm_db_log_error($e, __METHOD__);
            crm_flash_set('⚠ Chyba při ukládání. Zkuste to znovu.');
            crm_redirect($returnUrl);
        }

        crm_audit_log(
            $this->pdo, $callerId,
            'premium_call_status', 'premium_lead_pool', $poolId,
            [
                'order_id'  => $orderId,
                'contact_id'=> $contactId,
                'action'    => $action,
                'new_stav'  => $contactStav,
                'oz_id'     => $action === 'success' ? $ozId : null,
            ]
        );

        $msg = match ($action) {
            'success'    => '🎉 Úspěšně navoláno! Lead předán OZ.',
            'callback'   => '📅 Callback domluven.',
            'nezajem'    => '✓ Označeno: nezájem.',
            'nedovolano' => '✓ Označeno: nedovoláno.',
            'called_bad' => '✓ Označeno: bad call.',
        };
        crm_flash_set($msg);
        crm_redirect($returnUrl);
    }

    // ════════════════════════════════════════════════════════════════
    //  GET /caller/premium/payout/print — PDF výplata navolávačky
    //
    //  Standalone tisková stránka. Skládá se z:
    //   1) Standardní sazba (od majitele) × počet úspěšných premium hovorů
    //   2) Bonus od OZ — per OZ × per objednávka × per lead
    //
    //  ?year=&month= — výchozí aktuální měsíc.
    //  ?caller_id=N — pro majitele/superadmina (volba konkrétní navolávačky).
    // ════════════════════════════════════════════════════════════════
    public function getPayoutPrint(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['navolavacka', 'majitel', 'superadmin']);

        // Hard-lock: navolávačka jen sebe; admin/majitel přes ?caller_id=
        if ((string) ($user['role'] ?? '') === 'navolavacka') {
            $callerId = (int) $user['id'];
        } else {
            $callerId = (int) ($_GET['caller_id'] ?? $user['id']);
        }

        $year    = max(2024, min(2030, (int) ($_GET['year']  ?? date('Y'))));
        $month   = max(1,    min(12,   (int) ($_GET['month'] ?? date('n'))));
        $orderId = (int) ($_GET['order_id'] ?? 0);
        $singleOrder = $orderId > 0;

        // Info o navolávačce
        $uStmt = $this->pdo->prepare(
            "SELECT id, jmeno FROM users WHERE id = :id LIMIT 1"
        );
        $uStmt->execute(['id' => $callerId]);
        $caller = $uStmt->fetch(PDO::FETCH_ASSOC);
        if (!$caller) {
            http_response_code(404);
            echo 'Navolávačka nenalezena.';
            exit;
        }

        // Hlavička objednávky pro single-order mode
        $singleOrderHeader = null;
        if ($singleOrder) {
            $oStmt = $this->pdo->prepare(
                "SELECT po.id, po.year, po.month, po.requested_count, po.reserved_count,
                        po.caller_bonus_per_lead, po.status, po.note,
                        po.paid_to_caller_at, po.created_at, po.updated_at,
                        u_oz.jmeno AS oz_name
                 FROM premium_orders po
                 JOIN users u_oz ON u_oz.id = po.oz_id
                 WHERE po.id = :id LIMIT 1"
            );
            $oStmt->execute(['id' => $orderId]);
            $singleOrderHeader = $oStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$singleOrderHeader) {
                http_response_code(404);
                echo 'Objednávka nenalezena.';
                exit;
            }
        }

        // Standardní sazba — pro single order bereme sazbu k datu posledního navolaného leadu;
        // pro měsíční souhrn k poslednímu dni měsíce.
        $rewardRefDate = $singleOrder
            ? date('Y-m-d') // single — současná sazba (kdyby šlo o historickou objednávku, lze upravit)
            : sprintf('%04d-%02d-%02d', $year, $month, (int) date('t', strtotime("$year-$month-01")));

        $rewardStmt = $this->pdo->prepare(
            "SELECT amount_czk FROM caller_rewards_config
             WHERE valid_from <= :ref AND (valid_to IS NULL OR valid_to >= :ref2)
             ORDER BY valid_from DESC LIMIT 1"
        );
        $rewardStmt->execute(['ref' => $rewardRefDate, 'ref2' => $rewardRefDate]);
        $standardReward = (float) ($rewardStmt->fetchColumn() ?: 0);

        // Načíst úspěšně navolané leady:
        //   single-order — jen z dané objednávky (bez ohledu na měsíc)
        //   jinak — všechny za měsíc
        $sql = "SELECT p.id            AS pool_id,
                       p.contact_id,
                       p.called_at,
                       p.flagged_for_refund,
                       po.id            AS order_id,
                       po.oz_id,
                       po.caller_bonus_per_lead,
                       po.year          AS order_year,
                       po.month         AS order_month,
                       po.note          AS order_note,
                       po.status        AS order_status,
                       po.paid_to_caller_at,
                       u_oz.jmeno       AS oz_name,
                       c.firma, c.telefon, c.region, c.operator
                FROM premium_lead_pool p
                JOIN premium_orders po ON po.id = p.order_id
                JOIN users u_oz       ON u_oz.id = po.oz_id
                JOIN contacts c       ON c.id = p.contact_id
                WHERE p.caller_id = :uid
                  AND p.call_status = 'success'
                  AND p.cleaning_status = 'tradeable'";
        $params = ['uid' => $callerId];
        if ($singleOrder) {
            $sql .= " AND po.id = :oid";
            $params['oid'] = $orderId;
        } else {
            $sql .= " AND YEAR(p.called_at) = :y AND MONTH(p.called_at) = :m";
            $params['y'] = $year;
            $params['m'] = $month;
        }
        $sql .= " ORDER BY u_oz.jmeno ASC, po.id ASC, p.called_at ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Group: byOz[oz_id] -> orders[order_id] -> [bonus_per_lead, leads, count, ...]
        $byOz = [];
        $totalCount         = 0;       // všechny úspěšné premium hovory
        $totalCountPayable  = 0;       // bez reklamovaných (pro standard reward)
        $totalCountBonus    = 0;       // s bonusem > 0, bez reklamovaných (pro bonus)
        $totalBonus         = 0.0;     // suma bonusů
        foreach ($events as $ev) {
            $ozId    = (int) $ev['oz_id'];
            $oid     = (int) $ev['order_id'];
            $bonus   = (float) ($ev['caller_bonus_per_lead'] ?? 0);
            $isRefund= (int) $ev['flagged_for_refund'] === 1;

            if (!isset($byOz[$ozId])) {
                $byOz[$ozId] = [
                    'oz_name'      => (string) $ev['oz_name'],
                    'orders'       => [],
                    'count_total'  => 0,
                    'bonus_total'  => 0.0,
                ];
            }
            if (!isset($byOz[$ozId]['orders'][$oid])) {
                $byOz[$ozId]['orders'][$oid] = [
                    'order_id'           => $oid,
                    'bonus_per_lead'     => $bonus,
                    'order_month'        => (int) $ev['order_month'],
                    'order_year'         => (int) $ev['order_year'],
                    'order_status'       => (string) $ev['order_status'],
                    'paid_to_caller_at'  => (string) ($ev['paid_to_caller_at'] ?? ''),
                    'leads'              => [],
                    'count'              => 0,
                    'count_refund'       => 0,
                    'bonus_total'        => 0.0,
                ];
            }
            $byOz[$ozId]['orders'][$oid]['leads'][] = $ev;
            $byOz[$ozId]['orders'][$oid]['count']++;
            $byOz[$ozId]['count_total']++;
            $totalCount++;

            if (!$isRefund) {
                $totalCountPayable++;
                if ($bonus > 0) {
                    $byOz[$ozId]['orders'][$oid]['bonus_total'] += $bonus;
                    $byOz[$ozId]['bonus_total'] += $bonus;
                    $totalCountBonus++;
                    $totalBonus += $bonus;
                }
            } else {
                $byOz[$ozId]['orders'][$oid]['count_refund']++;
            }
        }

        // Standard reward total
        $standardTotal = $totalCountPayable * $standardReward;

        // ── Funnel stats per objednávka — kolik vyčistila čistička, kolik tradeable, atd.
        // Dává komplet obrázek "objednáno X → vyčištěno Y → tradeable Z → navoláno W".
        $orderIds = [];
        foreach ($byOz as $oz) {
            foreach ($oz['orders'] as $oid => $_) $orderIds[] = (int) $oid;
        }
        $orderIds = array_values(array_unique($orderIds));

        if ($orderIds !== []) {
            $ph = implode(',', array_fill(0, count($orderIds), '?'));
            $funnelStmt = $this->pdo->prepare(
                "SELECT po.id,
                        po.requested_count,
                        po.reserved_count,
                        (SELECT COUNT(*) FROM premium_lead_pool p
                           WHERE p.order_id = po.id
                             AND p.cleaning_status IN ('tradeable','non_tradeable')) AS cleaned_total,
                        (SELECT COUNT(*) FROM premium_lead_pool p
                           WHERE p.order_id = po.id
                             AND p.cleaning_status = 'tradeable') AS tradeable_total,
                        (SELECT COUNT(*) FROM premium_lead_pool p
                           WHERE p.order_id = po.id
                             AND p.cleaning_status = 'tradeable'
                             AND p.call_status = 'success') AS called_success
                 FROM premium_orders po
                 WHERE po.id IN ($ph)"
            );
            $funnelStmt->execute($orderIds);
            $funnelData = [];
            foreach ($funnelStmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
                $funnelData[(int) $f['id']] = $f;
            }
            foreach ($byOz as $ozId => &$oz) {
                foreach ($oz['orders'] as $oid => &$row) {
                    $row['funnel'] = $funnelData[(int) $oid] ?? null;
                }
            }
            unset($oz, $row);
        }

        header('Content-Type: text/html; charset=UTF-8');
        require dirname(__DIR__) . '/views/caller/premium/payout_print.php';
        exit;
    }
}
