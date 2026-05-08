<?php
// e:\Snecinatripu\app\controllers\PremiumCistickaController.php
declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'audit.php';

/**
 * Premium pipeline — pracovní plocha 2 čističky (druhé čištění).
 *
 * Stejné UX jako standardní /cisticka, ale pracuje s leady ze stavu
 * `READY` (už jednou pročištěnými) které jsou v `premium_lead_pool`
 * se status='pending' v rámci nějaké open objednávky.
 *
 * Workflow:
 *   1) GET /cisticka/premium
 *      → list všech otevřených objednávek (per OZ × region) s počty pending leadů.
 *      → před tím se zavolá PremiumOrderController::topUpOpenOrders pro lazy refill.
 *
 *   2) GET /cisticka/premium/order?id=X
 *      → detail objednávky. Tabulka pending leadů. U každého 2 tlačítka:
 *        ✅ Obchodovatelný | ❌ Neobchodovatelný
 *
 *   3) POST /cisticka/premium/verify
 *      → UPDATE premium_lead_pool SET cleaning_status, cleaner_id, cleaned_at.
 *      → Pokud tradeable a objednávka má preferred_caller_id, taky se nastaví
 *        contacts.assigned_caller_id (lead pak půjde jen do queue té navolávačky).
 *      → Pokud non_tradeable, nic na contacts se nemění (lead zůstává READY
 *        a půjde do queue všech navolávaček rotací). UNIQUE klíč v poolu zajistí,
 *        že už nikdy nepůjde do další premium objednávky.
 *
 *   4) POST /cisticka/premium/undo
 *      → vrátí zpět cleaning_status='pending', vyčistí cleaner_id a (pokud byl
 *        tradeable + měl preferred_caller_id) i contacts.assigned_caller_id.
 */
final class PremiumCistickaController
{
    public function __construct(private PDO $pdo)
    {
    }

    // ════════════════════════════════════════════════════════════════
    //  GET /cisticka/premium — přehled otevřených objednávek
    // ════════════════════════════════════════════════════════════════
    public function getIndex(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['cisticka', 'majitel', 'superadmin']);

        $cistickaId = (int) $user['id'];

        // Lazy refill — doplnit otevřené objednávky čerstvě vyčištěnými READY leady
        if (class_exists('PremiumOrderController')) {
            PremiumOrderController::topUpOpenOrders($this->pdo);
        }

        // Otevřené objednávky s alespoň 1 pending leadem.
        //   - Čistička vidí: své přijaté + nepřijaté (otevřené pro všechny).
        //   - Majitel/superadmin vidí všechny (i přijaté ostatními).
        $isAdmin = in_array((string) ($user['role'] ?? ''), ['majitel', 'superadmin'], true);

        $accessSql = $isAdmin
            ? ''
            : ' AND (po.accepted_by_cleaner_id IS NULL OR po.accepted_by_cleaner_id = :cid_acc)';

        $stmt = $this->pdo->prepare(
            "SELECT po.id              AS order_id,
                    po.oz_id,
                    u_oz.jmeno         AS oz_name,
                    po.year, po.month,
                    po.requested_count,
                    po.reserved_count,
                    po.price_per_lead,
                    po.caller_bonus_per_lead,
                    po.preferred_caller_id,
                    u_pc.jmeno         AS preferred_caller_name,
                    po.accepted_by_cleaner_id,
                    u_acc.jmeno        AS accepted_by_name,
                    po.accepted_at,
                    po.regions_json,
                    po.note,
                    po.created_at,
                    (SELECT COUNT(*) FROM premium_lead_pool p
                       WHERE p.order_id = po.id AND p.cleaning_status = 'pending') AS pending_count,
                    (SELECT COUNT(*) FROM premium_lead_pool p
                       WHERE p.order_id = po.id AND p.cleaning_status = 'tradeable') AS tradeable_count,
                    (SELECT COUNT(*) FROM premium_lead_pool p
                       WHERE p.order_id = po.id AND p.cleaning_status = 'non_tradeable') AS non_tradeable_count
             FROM premium_orders po
             JOIN users u_oz ON u_oz.id = po.oz_id
             LEFT JOIN users u_pc  ON u_pc.id  = po.preferred_caller_id
             LEFT JOIN users u_acc ON u_acc.id = po.accepted_by_cleaner_id
             WHERE po.status = 'open'
             $accessSql
             ORDER BY pending_count DESC, po.created_at ASC"
        );
        $params = [];
        if (!$isAdmin) $params['cid_acc'] = $cistickaId;
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // ── Hotové objednávky (closed/cancelled) na kterých čistička dělala ──
        // Z pohledu čističky: "co jsem dotahla, kolik dostanu, kdo už mi zaplatil"
        $closedStmt = $this->pdo->prepare(
            "SELECT po.id              AS order_id,
                    po.oz_id,
                    u_oz.jmeno         AS oz_name,
                    po.year, po.month,
                    po.price_per_lead,
                    po.status          AS order_status,
                    po.paid_to_cleaner_at,
                    (SELECT COUNT(*) FROM premium_lead_pool p
                       WHERE p.order_id = po.id
                         AND p.cleaner_id = :uid
                         AND p.cleaning_status IN ('tradeable','non_tradeable')) AS my_done,
                    (SELECT COUNT(*) FROM premium_lead_pool p
                       WHERE p.order_id = po.id
                         AND p.cleaner_id = :uid2
                         AND p.cleaning_status IN ('tradeable','non_tradeable')
                         AND p.flagged_for_refund = 0) AS my_payable,
                    (SELECT COUNT(*) FROM premium_lead_pool p
                       WHERE p.order_id = po.id
                         AND p.cleaner_id = :uid3
                         AND p.flagged_for_refund = 1) AS my_refund
             FROM premium_orders po
             JOIN users u_oz ON u_oz.id = po.oz_id
             WHERE po.status IN ('closed','cancelled')
               AND EXISTS (
                   SELECT 1 FROM premium_lead_pool p
                   WHERE p.order_id = po.id AND p.cleaner_id = :uid4
               )
             ORDER BY po.updated_at DESC, po.id DESC"
        );
        $closedStmt->execute([
            'uid'  => $cistickaId,
            'uid2' => $cistickaId,
            'uid3' => $cistickaId,
            'uid4' => $cistickaId,
        ]);
        $closedOrders = $closedStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Statistika dnešní práce čističky na premium leadech
        $todayStmt = $this->pdo->prepare(
            "SELECT
                SUM(CASE WHEN cleaning_status = 'tradeable'     THEN 1 ELSE 0 END) AS tradeable_today,
                SUM(CASE WHEN cleaning_status = 'non_tradeable' THEN 1 ELSE 0 END) AS non_tradeable_today,
                COUNT(*) AS total_today
             FROM premium_lead_pool
             WHERE cleaner_id = :uid AND DATE(cleaned_at) = CURDATE()"
        );
        $todayStmt->execute(['uid' => $cistickaId]);
        $todayStats = $todayStmt->fetch(PDO::FETCH_ASSOC)
            ?: ['tradeable_today' => 0, 'non_tradeable_today' => 0, 'total_today' => 0];

        // Měsíční výdělek čističky z premium pipeline (per order × cena)
        $year  = (int) date('Y');
        $month = (int) date('n');
        $earnStmt = $this->pdo->prepare(
            "SELECT SUM(po.price_per_lead) AS earned_czk
             FROM premium_lead_pool p
             JOIN premium_orders po ON po.id = p.order_id
             WHERE p.cleaner_id = :uid
               AND p.cleaning_status IN ('tradeable','non_tradeable')
               AND p.flagged_for_refund = 0
               AND YEAR(p.cleaned_at)  = :y
               AND MONTH(p.cleaned_at) = :m"
        );
        $earnStmt->execute(['uid' => $cistickaId, 'y' => $year, 'm' => $month]);
        $monthEarned = (float) ($earnStmt->fetchColumn() ?: 0);

        $title = '💎 Pracovní plocha 2 — Premium';
        $csrf  = crm_csrf_token();
        $flash = crm_flash_take();

        ob_start();
        require dirname(__DIR__) . '/views/cisticka/premium/index.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    // ════════════════════════════════════════════════════════════════
    //  POST /cisticka/premium/accept — čistička přijme objednávku
    //
    //  Po přijetí objednávky ji ostatní čističky už nevidí v seznamu
    //  (každá pracuje na své). Majitel/superadmin vidí všechny.
    // ════════════════════════════════════════════════════════════════
    public function postAccept(): void
    {
        $user = crm_require_user($this->pdo);
        // POZOR: účet pro převzetí objednávky musí být ROLE='cisticka'.
        // Majitel/superadmin sice mohou stránku vidět (kvůli kontrole),
        // ale NEMĚLI BY claimnout objednávky za sebe — to by korigovalo
        // evidenci kdo reálně provádí druhé čištění.
        crm_require_roles($user, ['cisticka', 'majitel', 'superadmin']);
        if (((string) ($user['role'] ?? '')) !== 'cisticka') {
            crm_flash_set('⚠ Objednávky může přijmout pouze čistička. Admin/majitel je vidí jen pro kontrolu.');
            crm_redirect('/cisticka/premium');
        }

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/cisticka/premium');
        }

        $cistickaId = (int) $user['id'];
        $orderId    = (int) ($_POST['order_id'] ?? 0);

        if ($orderId <= 0) {
            crm_flash_set('⚠ Neplatné ID objednávky.');
            crm_redirect('/cisticka/premium');
        }

        // Atomic claim: jen pokud ještě nikdo nepřijal (ošetříme race condition).
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE premium_orders
                 SET accepted_by_cleaner_id = :cid,
                     accepted_at            = NOW(3)
                 WHERE id = :id
                   AND status = 'open'
                   AND accepted_by_cleaner_id IS NULL"
            );
            $stmt->execute(['cid' => $cistickaId, 'id' => $orderId]);
            $accepted = $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
            crm_flash_set('⚠ Chyba při přijetí objednávky.');
            crm_redirect('/cisticka/premium');
        }

        if (!$accepted) {
            // Buď ji někdo už přijal, nebo už není open
            $checkStmt = $this->pdo->prepare(
                "SELECT po.accepted_by_cleaner_id, u.jmeno
                 FROM premium_orders po
                 LEFT JOIN users u ON u.id = po.accepted_by_cleaner_id
                 WHERE po.id = :id"
            );
            $checkStmt->execute(['id' => $orderId]);
            $row = $checkStmt->fetch(PDO::FETCH_ASSOC);
            $byOther = $row && (int) $row['accepted_by_cleaner_id'] !== $cistickaId;
            if ($byOther) {
                crm_flash_set(sprintf('⚠ Objednávku už přijala %s — pracuje na ní.', crm_h((string) $row['jmeno'])));
            } else {
                crm_flash_set('⚠ Objednávku nelze přijmout (není otevřená).');
            }
            crm_redirect('/cisticka/premium');
        }

        crm_audit_log(
            $this->pdo, $cistickaId,
            'premium_order_accept', 'premium_order', $orderId,
            ['cleaner_id' => $cistickaId]
        );

        crm_flash_set('✓ Objednávka přijata. Můžeš ji teď čistit.');
        crm_redirect('/cisticka/premium/order?id=' . $orderId);
    }

    // ════════════════════════════════════════════════════════════════
    //  GET /cisticka/premium/order?id=X — detail objednávky (čisticí UI)
    // ════════════════════════════════════════════════════════════════
    public function getOrder(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['cisticka', 'majitel', 'superadmin']);

        $orderId = (int) ($_GET['id'] ?? 0);
        if ($orderId <= 0) {
            crm_redirect('/cisticka/premium');
        }

        // Načíst hlavičku objednávky
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
            crm_redirect('/cisticka/premium');
        }

        // Authorization: čistička co nepřijala objednávku ji nemůže otevřít.
        // Majitel/superadmin vidí vše, čistička jen svou.
        $isAdmin = in_array((string) ($user['role'] ?? ''), ['majitel', 'superadmin'], true);
        $cistickaId  = (int) $user['id'];
        $acceptedBy  = (int) ($order['accepted_by_cleaner_id'] ?? 0);
        if (!$isAdmin && $acceptedBy > 0 && $acceptedBy !== $cistickaId) {
            crm_flash_set('⚠ Tato objednávka je přijata jinou čističkou.');
            crm_redirect('/cisticka/premium');
        }

        // Pending leady této objednávky
        $pStmt = $this->pdo->prepare(
            "SELECT p.id AS pool_id,
                    p.contact_id,
                    p.cleaning_status,
                    p.cleaned_at,
                    c.firma, c.telefon, c.email, c.region, c.operator, c.prilez
             FROM premium_lead_pool p
             JOIN contacts c ON c.id = p.contact_id
             WHERE p.order_id = :oid
             ORDER BY
                 CASE p.cleaning_status
                     WHEN 'pending'       THEN 1
                     WHEN 'tradeable'     THEN 2
                     WHEN 'non_tradeable' THEN 3
                 END,
                 c.id ASC"
        );
        $pStmt->execute(['oid' => $orderId]);
        $leads = $pStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $title = '💎 Premium objednávka #' . $orderId;
        $csrf  = crm_csrf_token();
        $flash = crm_flash_take();

        ob_start();
        require dirname(__DIR__) . '/views/cisticka/premium/order.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    // ════════════════════════════════════════════════════════════════
    //  POST /cisticka/premium/verify — označit lead tradeable / non_tradeable
    // ════════════════════════════════════════════════════════════════
    public function postVerify(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['cisticka', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/cisticka/premium');
        }

        $cistickaId = (int) $user['id'];
        $poolId     = (int) ($_POST['pool_id'] ?? 0);
        $action     = (string) ($_POST['action'] ?? ''); // tradeable | non_tradeable
        $orderId    = (int) ($_POST['order_id'] ?? 0);

        $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

        if ($poolId <= 0 || !in_array($action, ['tradeable', 'non_tradeable'], true)) {
            $msg = 'Neplatný požadavek.';
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => $msg]);
                exit;
            }
            crm_flash_set($msg);
            crm_redirect('/cisticka/premium');
        }

        // Načíst pool řádek + objednávku (kvůli preferred_caller_id)
        $stmt = $this->pdo->prepare(
            "SELECT p.id, p.contact_id, p.order_id, p.cleaning_status,
                    po.preferred_caller_id, po.status AS order_status
             FROM premium_lead_pool p
             JOIN premium_orders po ON po.id = p.order_id
             WHERE p.id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $poolId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $msg = 'Lead nenalezen.';
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => $msg]);
                exit;
            }
            crm_flash_set($msg);
            crm_redirect($orderId > 0 ? '/cisticka/premium/order?id=' . $orderId : '/cisticka/premium');
        }
        if ($row['order_status'] !== 'open') {
            $msg = 'Objednávka je uzavřená nebo zrušená.';
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => $msg]);
                exit;
            }
            crm_flash_set($msg);
            crm_redirect('/cisticka/premium/order?id=' . (int) $row['order_id']);
        }

        $contactId        = (int) $row['contact_id'];
        $preferredCaller  = (int) ($row['preferred_caller_id'] ?? 0);

        try {
            $this->pdo->beginTransaction();

            // 1) UPDATE premium_lead_pool
            $this->pdo->prepare(
                "UPDATE premium_lead_pool
                 SET cleaning_status = :st,
                     cleaner_id      = :uid,
                     cleaned_at      = NOW(3)
                 WHERE id = :id"
            )->execute(['st' => $action, 'uid' => $cistickaId, 'id' => $poolId]);

            // 2) Premium leady ZŮSTÁVAJÍ jen v `premium_lead_pool` — `contacts` neměníme.
            //    Navolávačka je vidí v separátní pracovní ploše /caller/premium
            //    (NE v standardní queue). Standardní queue se naopak musí naučit
            //    premium leady ignorovat (NOT EXISTS filter v CallerController).
            //
            //    Toto rozdělení záměrně oddělí dvě fakturace:
            //      • standardní (mzda navolávačky od majitele, base reward)
            //      • premium     (bonus od OZ + faktura čističce)

            $this->pdo->commit();
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            crm_db_log_error($e, __METHOD__);
            $msg = '⚠ Chyba při ukládání.';
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => $msg]);
                exit;
            }
            crm_flash_set($msg);
            crm_redirect('/cisticka/premium/order?id=' . (int) $row['order_id']);
        }

        crm_audit_log(
            $this->pdo, $cistickaId,
            'premium_lead_verify', 'premium_lead_pool', $poolId,
            [
                'order_id'   => (int) $row['order_id'],
                'contact_id' => $contactId,
                'action'     => $action,
                'caller_assigned' => $action === 'tradeable' && $preferredCaller > 0 ? $preferredCaller : null,
            ]
        );

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'cleaning_status' => $action]);
            exit;
        }

        crm_redirect('/cisticka/premium/order?id=' . (int) $row['order_id']);
    }

    // ════════════════════════════════════════════════════════════════
    //  POST /cisticka/premium/undo — vrátit lead zpět na pending
    // ════════════════════════════════════════════════════════════════
    public function postUndo(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['cisticka', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/cisticka/premium');
        }

        $cistickaId = (int) $user['id'];
        $poolId     = (int) ($_POST['pool_id'] ?? 0);

        $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

        if ($poolId <= 0) {
            $msg = 'Neplatný požadavek.';
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => $msg]);
                exit;
            }
            crm_flash_set($msg);
            crm_redirect('/cisticka/premium');
        }

        // Načíst pool řádek + preferred_caller objednávky
        $stmt = $this->pdo->prepare(
            "SELECT p.id, p.contact_id, p.order_id, p.cleaning_status,
                    po.preferred_caller_id
             FROM premium_lead_pool p
             JOIN premium_orders po ON po.id = p.order_id
             WHERE p.id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $poolId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || $row['cleaning_status'] === 'pending') {
            $msg = 'Lead nelze vrátit (už je pending nebo neexistuje).';
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => $msg]);
                exit;
            }
            crm_flash_set($msg);
            crm_redirect('/cisticka/premium');
        }

        $contactId       = (int) $row['contact_id'];
        $preferredCaller = (int) ($row['preferred_caller_id'] ?? 0);
        $wasTradeable    = ($row['cleaning_status'] === 'tradeable');

        try {
            $this->pdo->beginTransaction();

            // Reset poolu na pending
            $this->pdo->prepare(
                "UPDATE premium_lead_pool
                 SET cleaning_status = 'pending',
                     cleaner_id      = NULL,
                     cleaned_at      = NULL
                 WHERE id = :id"
            )->execute(['id' => $poolId]);

            // Premium leady neměníme v `contacts` — všechno je v poolu.
            // Undo tedy jen vrací `cleaning_status` zpět na pending,
            // žádné stav/assigned_caller_id na contacts měnit nemusíme.
            unset($wasTradeable, $preferredCaller); // už nepotřebné

            $this->pdo->commit();
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            crm_db_log_error($e, __METHOD__);
            $msg = '⚠ Chyba při vrácení.';
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => $msg]);
                exit;
            }
            crm_flash_set($msg);
            crm_redirect('/cisticka/premium/order?id=' . (int) $row['order_id']);
        }

        crm_audit_log(
            $this->pdo, $cistickaId,
            'premium_lead_undo', 'premium_lead_pool', $poolId,
            ['order_id' => (int) $row['order_id'], 'contact_id' => $contactId]
        );

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true]);
            exit;
        }

        crm_redirect('/cisticka/premium/order?id=' . (int) $row['order_id']);
    }

    // ════════════════════════════════════════════════════════════════
    //  GET /cisticka/premium/payout/print — PDF výplata premium
    //
    //  Standalone tisková stránka. Per OZ × per objednávka breakdown.
    //  Reklamované leady (flagged_for_refund=1) nezapočítány.
    //
    //  ?year=&month= — výchozí aktuální měsíc.
    //  ?cisticka_id=N — pro majitele/superadmina (volba konkrétní čističky).
    // ════════════════════════════════════════════════════════════════
    public function getPayoutPrint(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['cisticka', 'majitel', 'superadmin']);

        // Hard-lock: čistička vidí jen sebe; admin/majitel může přes ?cisticka_id
        if ((string) ($user['role'] ?? '') === 'cisticka') {
            $cistickaId = (int) $user['id'];
        } else {
            $cistickaId = (int) ($_GET['cisticka_id'] ?? $user['id']);
        }

        $year    = max(2024, min(2030, (int) ($_GET['year']  ?? date('Y'))));
        $month   = max(1,    min(12,   (int) ($_GET['month'] ?? date('n'))));
        $orderId = (int) ($_GET['order_id'] ?? 0);
        $singleOrder = $orderId > 0;

        // Info o čističce
        $uStmt = $this->pdo->prepare(
            "SELECT id, jmeno FROM users WHERE id = :id LIMIT 1"
        );
        $uStmt->execute(['id' => $cistickaId]);
        $cisticka = $uStmt->fetch(PDO::FETCH_ASSOC);
        if (!$cisticka) {
            http_response_code(404);
            echo 'Čistička nenalezena.';
            exit;
        }

        // Načíst data:
        //   single order — všechny vyčištěné leady té objednávky touto čističkou (bez ohledu na měsíc)
        //   jinak — všechny vyčištěné leady této čističky za daný měsíc
        $sql = "SELECT p.id            AS pool_id,
                       p.contact_id,
                       p.cleaning_status,
                       p.cleaned_at,
                       p.flagged_for_refund,
                       p.flag_reason,
                       po.id            AS order_id,
                       po.oz_id,
                       po.price_per_lead,
                       po.year          AS order_year,
                       po.month         AS order_month,
                       po.note          AS order_note,
                       po.status        AS order_status,
                       po.paid_to_cleaner_at,
                       u_oz.jmeno       AS oz_name,
                       c.firma, c.telefon, c.region, c.operator
                FROM premium_lead_pool p
                JOIN premium_orders po ON po.id = p.order_id
                JOIN users u_oz       ON u_oz.id = po.oz_id
                JOIN contacts c       ON c.id = p.contact_id
                WHERE p.cleaner_id = :uid
                  AND p.cleaning_status IN ('tradeable','non_tradeable')";
        $params = ['uid' => $cistickaId];
        if ($singleOrder) {
            $sql .= " AND po.id = :oid";
            $params['oid'] = $orderId;
        } else {
            $sql .= " AND YEAR(p.cleaned_at)  = :y
                      AND MONTH(p.cleaned_at) = :m";
            $params['y'] = $year;
            $params['m'] = $month;
        }
        $sql .= " ORDER BY po.id ASC, p.cleaned_at ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Group per-objednávka (stejná struktura jako u OZ — jednotná UX)
        $byOrder = [];
        $totalCount   = 0;
        $totalPayable = 0.0;     // bez reklamací
        $ozSet        = [];      // pro souhrn „pro kolik OZ jsem dělala"
        foreach ($events as $ev) {
            $oid     = (int) $ev['order_id'];
            $price   = (float) $ev['price_per_lead'];
            $isRefund= (int) $ev['flagged_for_refund'] === 1;
            $ozName  = (string) $ev['oz_name'];

            if (!isset($byOrder[$oid])) {
                $byOrder[$oid] = [
                    'order_id'           => $oid,
                    'oz_id'              => (int) $ev['oz_id'],
                    'oz_name'            => $ozName,
                    'price'              => $price,
                    'order_month'        => (int) $ev['order_month'],
                    'order_year'         => (int) $ev['order_year'],
                    'order_status'       => (string) $ev['order_status'],
                    'order_note'         => (string) ($ev['order_note'] ?? ''),
                    'paid_to_cleaner_at' => (string) ($ev['paid_to_cleaner_at'] ?? ''),
                    'events'             => [],
                    'count'              => 0,
                    'count_payable'      => 0,
                    'count_refund'       => 0,
                    'payout'             => 0.0,
                ];
            }
            $byOrder[$oid]['events'][] = $ev;
            $byOrder[$oid]['count']++;
            $totalCount++;
            $ozSet[$ozName] = true;

            if (!$isRefund) {
                $byOrder[$oid]['count_payable']++;
                $byOrder[$oid]['payout'] += $price;
                $totalPayable += $price;
            } else {
                $byOrder[$oid]['count_refund']++;
            }
        }
        $ozCount = count($ozSet);

        // Hlavička objednávky pro single mode (i když nejsou eventy)
        $singleOrderHeader = null;
        if ($singleOrder) {
            $oh = $this->pdo->prepare(
                "SELECT po.id, po.oz_id, po.year, po.month, po.requested_count,
                        po.reserved_count, po.price_per_lead, po.status, po.note,
                        po.paid_to_cleaner_at, po.created_at, po.updated_at,
                        u_oz.jmeno AS oz_name
                 FROM premium_orders po
                 JOIN users u_oz ON u_oz.id = po.oz_id
                 WHERE po.id = :id LIMIT 1"
            );
            $oh->execute(['id' => $orderId]);
            $singleOrderHeader = $oh->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$singleOrderHeader) {
                http_response_code(404);
                echo 'Objednávka nenalezena.';
                exit;
            }
        }

        header('Content-Type: text/html; charset=UTF-8');
        require dirname(__DIR__) . '/views/cisticka/premium/payout_print.php';
        exit;
    }
}
