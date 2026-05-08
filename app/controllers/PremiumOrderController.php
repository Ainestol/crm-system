<?php
// e:\Snecinatripu\app\controllers\PremiumOrderController.php
declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'audit.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'users_admin.php';

/**
 * Premium pipeline — objednávky druhého čištění (OZ side).
 *
 * Workflow:
 *   1) OZ klikne v sidebaru "💎 Nová objednávka" → vyplní formulář
 *      (počet leadů, cena za kus pro čističku, bonus pro navolávačku,
 *       konkrétní navolávačka nebo rotace, regiony) → POST /oz/premium/create.
 *
 *   2) Systém v transakci:
 *      - INSERT premium_orders (status='open', reserved_count=0)
 *      - SELECT … FROM contacts WHERE stav='READY' AND assigned_caller_id IS NULL
 *        AND NOT EXISTS (premium_lead_pool match)  LIMIT :count FOR UPDATE
 *      - INSERT do premium_lead_pool (cleaning_status='pending')
 *      - UPDATE premium_orders.reserved_count
 *
 *   3) Pokud se nepovedlo zarezervovat všechno (málo READY leadů v poolu),
 *      objednávka má reserved_count < requested_count. Při dalším přístupu
 *      na /oz/premium nebo /cisticka/premium se zavolá topUpOpenOrders()
 *      která doplní z čerstvě vyčištěných READY leadů (lazy refill).
 *
 *   4) OZ může otevřenou objednávku zrušit (postCancel) → smažou se POUZE
 *      pending leady z poolu (vyčištěné tradeable/non_tradeable zůstávají
 *      jako historie, OZ je pořád musí zaplatit). Order status='cancelled'.
 *
 *   Lock leadů funguje přes UNIQUE KEY uq_pool_contact na premium_lead_pool —
 *   jeden contact může být v poolu jen jednou napříč celou historií.
 */
final class PremiumOrderController
{
    /** Max počet leadů v jedné objednávce (sanity, ne business limit). */
    private const MAX_LEADS_PER_ORDER = 10000;

    public function __construct(private PDO $pdo)
    {
    }

    // ════════════════════════════════════════════════════════════════
    //  Helpery
    // ════════════════════════════════════════════════════════════════

    /** Aktivní navolávačky (pro dropdown při výběru preferred_caller). */
    private function activeCallers(): array
    {
        $rows = $this->pdo->query(
            "SELECT id, jmeno FROM users
             WHERE role = 'navolavacka' AND aktivni = 1
             ORDER BY jmeno ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /**
     * Lazy refill open orders — zavolá se při GET /oz/premium a /cisticka/premium.
     * Pro každou open objednávku s reserved_count < requested_count zkusí dorezervovat.
     * Vrací počet doplněných leadů celkově.
     *
     * Public static aby ji mohl zavolat i CistickaController bez instance.
     */
    public static function topUpOpenOrders(PDO $pdo): int
    {
        try {
            $orders = $pdo->query(
                "SELECT id, oz_id, requested_count, reserved_count, regions_json
                 FROM premium_orders
                 WHERE status = 'open'
                   AND reserved_count < requested_count
                 ORDER BY created_at ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException) {
            return 0; // tabulka ještě nemusí existovat při prvním requestu
        }

        if (!is_array($orders) || $orders === []) {
            return 0;
        }

        $totalAdded = 0;
        foreach ($orders as $o) {
            $missing = (int) $o['requested_count'] - (int) $o['reserved_count'];
            if ($missing <= 0) continue;

            $regions = null;
            if (!empty($o['regions_json'])) {
                $decoded = json_decode((string) $o['regions_json'], true);
                if (is_array($decoded) && $decoded !== []) {
                    $regions = array_values(array_filter(array_map('strval', $decoded)));
                }
            }

            $added = self::reserveLeadsForOrder(
                $pdo,
                (int) $o['id'],
                (int) $o['oz_id'],
                $missing,
                $regions
            );
            $totalAdded += $added;
        }

        return $totalAdded;
    }

    /**
     * Atomicky vybere $count leadů ze stavu READY a vloží je do premium_lead_pool
     * pro danou objednávku. Aktualizuje premium_orders.reserved_count.
     *
     * Vrací reálně přidaný počet (může být < $count pokud není dost leadů).
     */
    private static function reserveLeadsForOrder(
        PDO $pdo,
        int $orderId,
        int $ozId,
        int $count,
        ?array $regions = null
    ): int {
        if ($count <= 0) return 0;

        $regionFilter = '';
        $params = [];
        if ($regions !== null && $regions !== []) {
            $ph = implode(',', array_fill(0, count($regions), '?'));
            $regionFilter = " AND c.region IN ($ph) ";
            foreach ($regions as $r) $params[] = $r;
        }
        $params[] = $count;

        try {
            $pdo->beginTransaction();

            // SELECT FOR UPDATE — zamkne řádky proti souběžným objednávkám
            $sql = "SELECT c.id
                    FROM contacts c
                    WHERE c.stav = 'READY'
                      AND c.assigned_caller_id IS NULL
                      AND NOT EXISTS (
                          SELECT 1 FROM premium_lead_pool p WHERE p.contact_id = c.id
                      )
                      $regionFilter
                    ORDER BY c.id ASC
                    LIMIT ?
                    FOR UPDATE";

            $stmt = $pdo->prepare($sql);
            // posledni param je LIMIT (int), ostatni jsou regiony (string)
            foreach ($params as $i => $v) {
                $stmt->bindValue($i + 1, $v, $i === count($params) - 1 ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

            if (!is_array($ids) || $ids === []) {
                $pdo->commit();
                return 0;
            }

            $insStmt = $pdo->prepare(
                "INSERT INTO premium_lead_pool (order_id, contact_id, oz_id)
                 VALUES (:oid, :cid, :ozid)"
            );
            $added = 0;
            foreach ($ids as $cid) {
                try {
                    $insStmt->execute([
                        'oid'  => $orderId,
                        'cid'  => (int) $cid,
                        'ozid' => $ozId,
                    ]);
                    $added++;
                } catch (\PDOException) {
                    // UNIQUE collision (race condition) — preskoc
                }
            }

            if ($added > 0) {
                $pdo->prepare(
                    "UPDATE premium_orders
                     SET reserved_count = reserved_count + :n
                     WHERE id = :id"
                )->execute(['n' => $added, 'id' => $orderId]);
            }

            $pdo->commit();
            return $added;
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            crm_db_log_error($e, __METHOD__);
            return 0;
        }
    }

    // ════════════════════════════════════════════════════════════════
    //  GET /oz/premium — list mých objednávek
    // ════════════════════════════════════════════════════════════════
    public function getIndex(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        // Lazy refill — doplnit otevřené objednávky čerstvě vyčištěnými leady
        self::topUpOpenOrders($this->pdo);

        $ozId = (int) $user['id'];

        // Pokud je to majitel/superadmin, vidí jen své objednávky (kdyby si testoval).
        // Plný admin přehled napříč všemi OZ je samostatná stránka /admin/premium-orders (Fáze 5).
        $stmt = $this->pdo->prepare(
            "SELECT po.*,
                    u_pc.jmeno AS preferred_caller_name,
                    (SELECT COUNT(*) FROM premium_lead_pool p
                       WHERE p.order_id = po.id) AS pool_total,
                    (SELECT COUNT(*) FROM premium_lead_pool p
                       WHERE p.order_id = po.id AND p.cleaning_status = 'pending') AS pool_pending,
                    (SELECT COUNT(*) FROM premium_lead_pool p
                       WHERE p.order_id = po.id AND p.cleaning_status = 'tradeable') AS pool_tradeable,
                    (SELECT COUNT(*) FROM premium_lead_pool p
                       WHERE p.order_id = po.id AND p.cleaning_status = 'non_tradeable') AS pool_non_tradeable,
                    (SELECT COUNT(*) FROM premium_lead_pool p
                       WHERE p.order_id = po.id AND p.call_status = 'success') AS pool_called_success,
                    (SELECT COUNT(*) FROM premium_lead_pool p
                       WHERE p.order_id = po.id AND p.cleaning_status IN ('tradeable','non_tradeable')
                       AND p.flagged_for_refund = 0) AS pool_payable_to_cleaner,
                    (SELECT COUNT(*) FROM premium_lead_pool p
                       WHERE p.order_id = po.id AND p.cleaning_status = 'tradeable'
                       AND p.call_status = 'success' AND p.flagged_for_refund = 0) AS pool_payable_to_caller
             FROM premium_orders po
             LEFT JOIN users u_pc ON u_pc.id = po.preferred_caller_id
             WHERE po.oz_id = :oz
             ORDER BY po.created_at DESC"
        );
        $stmt->execute(['oz' => $ozId]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $title = '💎 Premium objednávky';
        $csrf  = crm_csrf_token();
        $flash = crm_flash_take();

        ob_start();
        require dirname(__DIR__) . '/views/oz/premium/index.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    // ════════════════════════════════════════════════════════════════
    //  GET /oz/premium/new — formulář pro novou objednávku
    // ════════════════════════════════════════════════════════════════
    public function getNew(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        $callers = $this->activeCallers();
        $regions = crm_region_choices();

        // Kolik je momentálně k dispozici READY leadů (které nejsou v premium poolu)?
        $availStmt = $this->pdo->query(
            "SELECT COUNT(*) FROM contacts c
             WHERE c.stav = 'READY'
               AND c.assigned_caller_id IS NULL
               AND NOT EXISTS (SELECT 1 FROM premium_lead_pool p WHERE p.contact_id = c.id)"
        );
        $availableTotal = (int) ($availStmt ? $availStmt->fetchColumn() : 0);

        // Per-region breakdown — aby OZ věděl kde je nejvíc dostupných
        $availPerRegion = [];
        try {
            $rs = $this->pdo->query(
                "SELECT c.region, COUNT(*) AS cnt
                 FROM contacts c
                 WHERE c.stav = 'READY'
                   AND c.assigned_caller_id IS NULL
                   AND NOT EXISTS (SELECT 1 FROM premium_lead_pool p WHERE p.contact_id = c.id)
                 GROUP BY c.region
                 ORDER BY cnt DESC"
            );
            foreach ($rs ? $rs->fetchAll(PDO::FETCH_ASSOC) : [] as $r) {
                $availPerRegion[(string) $r['region']] = (int) $r['cnt'];
            }
        } catch (\PDOException) { /* ignore */ }

        $title = '💎 Nová premium objednávka';
        $csrf  = crm_csrf_token();
        $flash = crm_flash_take();

        // Re-fill po validační chybě
        $form = $_SESSION['premium_form_data'] ?? [];
        unset($_SESSION['premium_form_data']);

        ob_start();
        require dirname(__DIR__) . '/views/oz/premium/new.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    // ════════════════════════════════════════════════════════════════
    //  POST /oz/premium/create — uložení nové objednávky + rezervace leadů
    // ════════════════════════════════════════════════════════════════
    public function postCreate(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/oz/premium/new');
        }

        $ozId               = (int) $user['id'];
        $requestedCount     = (int) ($_POST['requested_count']     ?? 0);
        $pricePerLead       = (float) str_replace(',', '.', (string) ($_POST['price_per_lead'] ?? '0'));
        $callerBonus        = (float) str_replace(',', '.', (string) ($_POST['caller_bonus_per_lead'] ?? '0'));
        $preferredCallerId  = (int) ($_POST['preferred_caller_id'] ?? 0);
        $regions            = $_POST['regions']                    ?? [];
        $note               = trim((string) ($_POST['note']        ?? ''));

        // Re-fill bounce
        $bounce = function (string $msg) use (
            $requestedCount, $pricePerLead, $callerBonus, $preferredCallerId, $regions, $note
        ): void {
            $_SESSION['premium_form_data'] = [
                'requested_count'       => $requestedCount,
                'price_per_lead'        => $pricePerLead,
                'caller_bonus_per_lead' => $callerBonus,
                'preferred_caller_id'   => $preferredCallerId,
                'regions'               => $regions,
                'note'                  => $note,
            ];
            crm_flash_set($msg);
            crm_redirect('/oz/premium/new');
        };

        // ── Validace ──
        if ($requestedCount < 1) {
            $bounce('⚠ Počet leadů musí být alespoň 1.');
        }
        if ($requestedCount > self::MAX_LEADS_PER_ORDER) {
            $bounce(sprintf('⚠ Maximum je %d leadů v jedné objednávce.', self::MAX_LEADS_PER_ORDER));
        }
        if ($pricePerLead <= 0) {
            $bounce('⚠ Cena za lead pro čističku musí být kladná.');
        }
        if ($pricePerLead > 9999.99) {
            $bounce('⚠ Cena za lead je nereálně vysoká (max 9999.99 Kč).');
        }
        if ($callerBonus < 0 || $callerBonus > 9999.99) {
            $bounce('⚠ Bonus pro navolávačku musí být 0 nebo více (max 9999.99 Kč).');
        }

        // Validovat preferred_caller (pokud byl zvolen)
        if ($preferredCallerId > 0) {
            $check = $this->pdo->prepare(
                "SELECT 1 FROM users WHERE id = :id AND role = 'navolavacka' AND aktivni = 1 LIMIT 1"
            );
            $check->execute(['id' => $preferredCallerId]);
            if (!$check->fetchColumn()) {
                $preferredCallerId = 0;
            }
        }

        // Validovat regiony — povolené kódy z whitelistu
        $allowedRegions = crm_region_choices();
        $regionsClean   = [];
        if (is_array($regions)) {
            foreach ($regions as $r) {
                $r = strtolower(trim((string) $r));
                if ($r !== '' && in_array($r, $allowedRegions, true)) {
                    $regionsClean[] = $r;
                }
            }
            $regionsClean = array_values(array_unique($regionsClean));
        }
        $regionsJson = $regionsClean !== [] ? json_encode($regionsClean, JSON_UNESCAPED_UNICODE) : null;

        // Note (volitelné) — limit
        if (mb_strlen($note) > 1000) {
            $note = mb_substr($note, 0, 1000);
        }

        // ── INSERT objednávka ──
        $year  = (int) date('Y');
        $month = (int) date('n');

        try {
            $this->pdo->prepare(
                "INSERT INTO premium_orders
                   (oz_id, year, month, requested_count, reserved_count,
                    price_per_lead, caller_bonus_per_lead, preferred_caller_id,
                    regions_json, status, note)
                 VALUES
                   (:oz, :y, :m, :req, 0,
                    :price, :bonus, :pref,
                    :regions, 'open', :note)"
            )->execute([
                'oz'      => $ozId,
                'y'       => $year,
                'm'       => $month,
                'req'     => $requestedCount,
                'price'   => $pricePerLead,
                'bonus'   => $callerBonus,
                'pref'    => $preferredCallerId > 0 ? $preferredCallerId : null,
                'regions' => $regionsJson,
                'note'    => $note !== '' ? $note : null,
            ]);
            $orderId = (int) $this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
            $bounce('⚠ Chyba při vytváření objednávky. Zkuste to znovu.');
        }

        // ── Rezervace leadů ──
        $reserved = self::reserveLeadsForOrder(
            $this->pdo,
            $orderId,
            $ozId,
            $requestedCount,
            $regionsClean !== [] ? $regionsClean : null
        );

        crm_audit_log(
            $this->pdo, $ozId,
            'premium_order_create', 'premium_order', $orderId,
            [
                'requested'       => $requestedCount,
                'reserved'        => $reserved,
                'price_per_lead'  => $pricePerLead,
                'caller_bonus'    => $callerBonus,
                'preferred_caller'=> $preferredCallerId,
                'regions'         => $regionsClean,
            ]
        );

        if ($reserved < $requestedCount) {
            crm_flash_set(sprintf(
                '✓ Objednávka vytvořena. Zarezervováno %d/%d leadů — zbytek se doplní postupně, jak budou další leady k dispozici.',
                $reserved,
                $requestedCount
            ));
        } else {
            crm_flash_set(sprintf('✓ Objednávka vytvořena a všech %d leadů zarezervováno.', $reserved));
        }

        crm_redirect('/oz/premium');
    }

    // ════════════════════════════════════════════════════════════════
    //  POST /oz/premium/close — uzavřít objednávku (DOKONČENO úspěšně)
    //  Stejná logika jako cancel, jen status = 'closed' místo 'cancelled'.
    //
    //  Použití:
    //   - Čistička: dokončila co měla, klikne "Uzavřít" → OZ ví že je hotovo
    //   - OZ: prohlásí objednávku za uzavřenou, ať čistička nepokračuje
    //
    //  Kdo smí: OZ (vlastní), čistička (jakoukoliv), majitel/superadmin.
    //  Pending leady se uvolní zpět do poolu (nikdo za ně neplatí).
    //  Vyčištěné (tradeable + non_tradeable) zůstávají v poolu, OZ je platí.
    // ════════════════════════════════════════════════════════════════
    public function postClose(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'cisticka', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/oz/premium');
        }

        $orderId = (int) ($_POST['order_id'] ?? 0);
        $userId  = (int) $user['id'];
        $role    = (string) ($user['role'] ?? '');

        if ($orderId <= 0) {
            crm_flash_set('⚠ Neplatné ID objednávky.');
            crm_redirect($role === 'cisticka' ? '/cisticka/premium' : '/oz/premium');
        }

        // Ověř že objednávka existuje a je otevřená
        // Pro OZ: jen vlastní; pro čističku/majitele/superadmina: jakákoliv
        $sql = "SELECT id, oz_id, status FROM premium_orders WHERE id = :id";
        $params = ['id' => $orderId];
        if ($role === 'obchodak') {
            $sql .= " AND oz_id = :oz";
            $params['oz'] = $userId;
        }
        $sql .= " LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            crm_flash_set('⚠ Objednávka neexistuje nebo k ní nemáte přístup.');
            crm_redirect($role === 'cisticka' ? '/cisticka/premium' : '/oz/premium');
        }
        if ($order['status'] !== 'open') {
            crm_flash_set('⚠ Tuto objednávku už nelze uzavřít (není otevřená).');
            crm_redirect($role === 'cisticka' ? '/cisticka/premium' : '/oz/premium');
        }

        try {
            $this->pdo->beginTransaction();

            // Spočítat pending pro audit
            $cntStmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM premium_lead_pool
                 WHERE order_id = :id AND cleaning_status = 'pending'"
            );
            $cntStmt->execute(['id' => $orderId]);
            $pendingCnt = (int) $cntStmt->fetchColumn();

            // Smazat pending leady (uvolní contact_id)
            $this->pdo->prepare(
                "DELETE FROM premium_lead_pool
                 WHERE order_id = :id AND cleaning_status = 'pending'"
            )->execute(['id' => $orderId]);

            // Status → closed, snížit reserved_count
            $this->pdo->prepare(
                "UPDATE premium_orders
                 SET status = 'closed',
                     reserved_count = GREATEST(0, reserved_count - :n)
                 WHERE id = :id"
            )->execute(['n' => $pendingCnt, 'id' => $orderId]);

            $this->pdo->commit();
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            crm_db_log_error($e, __METHOD__);
            crm_flash_set('⚠ Chyba při uzavírání objednávky.');
            crm_redirect($role === 'cisticka' ? '/cisticka/premium' : '/oz/premium');
        }

        crm_audit_log(
            $this->pdo, $userId,
            'premium_order_close', 'premium_order', $orderId,
            ['released_pending' => $pendingCnt, 'closed_by_role' => $role]
        );

        crm_flash_set(sprintf(
            '🏁 Objednávka uzavřena. %s',
            $pendingCnt > 0
                ? sprintf('%d nezpracovaných leadů uvolněno zpět do poolu.', $pendingCnt)
                : 'Všechny leady byly zpracované.'
        ));
        crm_redirect($role === 'cisticka' ? '/cisticka/premium' : '/oz/premium');
    }

    // ════════════════════════════════════════════════════════════════
    //  POST /oz/premium/cancel — zrušit otevřenou objednávku
    //  Smaže POUZE pending leady z poolu (vyčištěné zůstávají, OZ je platí).
    // ════════════════════════════════════════════════════════════════
    public function postCancel(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/oz/premium');
        }

        $orderId = (int) ($_POST['order_id'] ?? 0);
        $ozId    = (int) $user['id'];

        if ($orderId <= 0) {
            crm_flash_set('⚠ Neplatné ID objednávky.');
            crm_redirect('/oz/premium');
        }

        // Ověř že objednávka patří tomuto OZ a je otevřená
        $stmt = $this->pdo->prepare(
            "SELECT id, status FROM premium_orders WHERE id = :id AND oz_id = :oz LIMIT 1"
        );
        $stmt->execute(['id' => $orderId, 'oz' => $ozId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            crm_flash_set('⚠ Objednávka neexistuje nebo k ní nemáte přístup.');
            crm_redirect('/oz/premium');
        }
        if ($order['status'] !== 'open') {
            crm_flash_set('⚠ Tuto objednávku už nelze zrušit (není otevřená).');
            crm_redirect('/oz/premium');
        }

        try {
            $this->pdo->beginTransaction();

            // Spočítat kolik pending leadů smažeme (pro audit)
            $cntStmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM premium_lead_pool
                 WHERE order_id = :id AND cleaning_status = 'pending'"
            );
            $cntStmt->execute(['id' => $orderId]);
            $pendingCnt = (int) $cntStmt->fetchColumn();

            // Smazat pending leady z poolu (uvolní contact_id přes UNIQUE klíč)
            $this->pdo->prepare(
                "DELETE FROM premium_lead_pool
                 WHERE order_id = :id AND cleaning_status = 'pending'"
            )->execute(['id' => $orderId]);

            // Update objednávky: status='cancelled', reserved_count -= pending
            $this->pdo->prepare(
                "UPDATE premium_orders
                 SET status = 'cancelled',
                     reserved_count = GREATEST(0, reserved_count - :n)
                 WHERE id = :id"
            )->execute(['n' => $pendingCnt, 'id' => $orderId]);

            $this->pdo->commit();
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            crm_db_log_error($e, __METHOD__);
            crm_flash_set('⚠ Chyba při rušení objednávky.');
            crm_redirect('/oz/premium');
        }

        crm_audit_log(
            $this->pdo, $ozId,
            'premium_order_cancel', 'premium_order', $orderId,
            ['released_pending' => $pendingCnt]
        );

        crm_flash_set(sprintf(
            '✓ Objednávka zrušena, %d nezpracovaných leadů uvolněno zpět do poolu.',
            $pendingCnt
        ));
        crm_redirect('/oz/premium');
    }

    // ════════════════════════════════════════════════════════════════
    //  POST /oz/premium/mark-paid — zatrhnout / odtrhnout „zaplaceno"
    //
    //  Body:
    //    order_id = X
    //    target   = cleaner | caller
    //    paid     = 1 (zaplaceno teď) | 0 (vrátit na nezaplaceno)
    //
    //  Pouze OZ vlastník objednávky + majitel/superadmin. Čistička
    //  to nesmí přepisovat (vidí jen status).
    // ════════════════════════════════════════════════════════════════
    public function postMarkPaid(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/oz/premium');
        }

        $orderId = (int) ($_POST['order_id'] ?? 0);
        $target  = (string) ($_POST['target']  ?? '');
        $paid    = (int) ($_POST['paid']      ?? 1) === 1;
        $userId  = (int) $user['id'];
        $role    = (string) ($user['role'] ?? '');

        if ($orderId <= 0 || !in_array($target, ['cleaner', 'caller'], true)) {
            crm_flash_set('⚠ Neplatný požadavek.');
            crm_redirect('/oz/premium');
        }

        // Ověřit oprávnění — OZ jen vlastní, majitel/superadmin cokoliv
        $sql = "SELECT id, oz_id, caller_bonus_per_lead FROM premium_orders WHERE id = :id";
        $params = ['id' => $orderId];
        if ($role === 'obchodak') {
            $sql .= " AND oz_id = :oz";
            $params['oz'] = $userId;
        }
        $sql .= " LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            crm_flash_set('⚠ Objednávka neexistuje nebo k ní nemáte přístup.');
            crm_redirect('/oz/premium');
        }

        // Pokud target=caller a bonus = 0, není co označovat
        if ($target === 'caller' && (float) $order['caller_bonus_per_lead'] <= 0) {
            crm_flash_set('⚠ Tato objednávka nemá bonus pro navolávačku.');
            crm_redirect('/oz/premium');
        }

        $col = $target === 'cleaner' ? 'paid_to_cleaner_at' : 'paid_to_caller_at';
        $byCol = $target === 'cleaner' ? 'paid_to_cleaner_by' : 'paid_to_caller_by';

        try {
            if ($paid) {
                $this->pdo->prepare(
                    "UPDATE premium_orders
                     SET `$col` = NOW(3), `$byCol` = :uid
                     WHERE id = :id"
                )->execute(['uid' => $userId, 'id' => $orderId]);
            } else {
                $this->pdo->prepare(
                    "UPDATE premium_orders
                     SET `$col` = NULL, `$byCol` = NULL
                     WHERE id = :id"
                )->execute(['id' => $orderId]);
            }
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
            crm_flash_set('⚠ Chyba při ukládání.');
            crm_redirect('/oz/premium');
        }

        crm_audit_log(
            $this->pdo, $userId,
            'premium_order_mark_paid', 'premium_order', $orderId,
            ['target' => $target, 'paid' => $paid ? 1 : 0]
        );

        crm_flash_set($paid
            ? sprintf('✓ Označeno jako zaplaceno %s.', $target === 'cleaner' ? 'čističce' : 'navolávačce')
            : '↩ Označeno jako nezaplaceno.'
        );
        crm_redirect('/oz/premium');
    }

    // ════════════════════════════════════════════════════════════════
    //  GET /oz/premium/payout/print — PDF faktura/výplata premium pro OZ
    //
    //  Zobrazuje kolik OZ dluží čističce.
    //  ?order_id=N → jen jedna objednávka (faktura)
    //  ?year=&month= → souhrn všech objednávek za měsíc
    //  Reklamované leady (flagged_for_refund=1) se nezapočítávají.
    // ════════════════════════════════════════════════════════════════
    public function getPayoutPrint(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        // Hard-lock: OZ vidí jen své; admin/majitel může přes ?oz_id=
        if ((string) ($user['role'] ?? '') === 'obchodak') {
            $ozId = (int) $user['id'];
        } else {
            $ozId = (int) ($_GET['oz_id'] ?? $user['id']);
        }

        $orderId = (int) ($_GET['order_id'] ?? 0);
        $year    = max(2024, min(2030, (int) ($_GET['year']  ?? date('Y'))));
        $month   = max(1,    min(12,   (int) ($_GET['month'] ?? date('n'))));
        $singleOrder = $orderId > 0;

        // Info o OZ
        $uStmt = $this->pdo->prepare(
            "SELECT id, jmeno FROM users WHERE id = :id LIMIT 1"
        );
        $uStmt->execute(['id' => $ozId]);
        $oz = $uStmt->fetch(PDO::FETCH_ASSOC);
        if (!$oz) {
            http_response_code(404);
            echo 'OZ nenalezen.';
            exit;
        }

        // Načíst data:
        //  - Pokud single order: všechny vyčištěné leady té objednávky (bez ohledu na měsíc)
        //  - Jinak: všechny vyčištěné leady ze všech objednávek tohoto OZ za daný měsíc
        $sql = "SELECT p.id            AS pool_id,
                       p.contact_id,
                       p.cleaning_status,
                       p.call_status,
                       p.cleaned_at,
                       p.called_at,
                       p.flagged_for_refund,
                       p.flag_reason,
                       po.id            AS order_id,
                       po.price_per_lead,
                       po.caller_bonus_per_lead,
                       po.year          AS order_year,
                       po.month         AS order_month,
                       po.note          AS order_note,
                       po.status        AS order_status,
                       po.paid_to_cleaner_at,
                       po.paid_to_caller_at,
                       u_c.jmeno        AS cleaner_name,
                       u_call.id        AS caller_id,
                       u_call.jmeno     AS caller_name,
                       c.firma, c.telefon, c.region, c.operator
                FROM premium_lead_pool p
                JOIN premium_orders po ON po.id = p.order_id
                JOIN contacts c       ON c.id = p.contact_id
                LEFT JOIN users u_c    ON u_c.id    = p.cleaner_id
                LEFT JOIN users u_call ON u_call.id = p.caller_id
                WHERE po.oz_id = :oz
                  AND p.cleaning_status IN ('tradeable','non_tradeable')";
        $params = ['oz' => $ozId];

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

        // Group per orderId — separátně cleaner výplata a caller bonus
        $byOrder = [];
        $totalCount         = 0;
        $totalPayable       = 0.0;        // pro čističku
        $totalCallerBonus   = 0.0;        // pro navolávačky (jen úspěšně navolané)
        $cleanerSet         = [];
        $byCallerBonus      = [];         // [caller_id => ['name'=>..., 'orders' => [oid => ['bonus_per_lead', 'count', 'amount']]]]
        foreach ($events as $ev) {
            $oid     = (int) $ev['order_id'];
            $price   = (float) $ev['price_per_lead'];
            $bonus   = (float) ($ev['caller_bonus_per_lead'] ?? 0);
            $isRefund= (int) $ev['flagged_for_refund'] === 1;
            $cln     = (string) ($ev['cleaner_name'] ?? '');
            $callId  = (int) ($ev['caller_id'] ?? 0);
            $callName= (string) ($ev['caller_name'] ?? '');
            $callOk  = (string) ($ev['call_status'] ?? '') === 'success';
            $isTradeable = (string) $ev['cleaning_status'] === 'tradeable';

            if (!isset($byOrder[$oid])) {
                $byOrder[$oid] = [
                    'order_id'           => $oid,
                    'price'              => $price,
                    'caller_bonus_per_lead' => $bonus,
                    'order_month'        => (int) $ev['order_month'],
                    'order_year'         => (int) $ev['order_year'],
                    'order_status'       => (string) $ev['order_status'],
                    'order_note'         => (string) ($ev['order_note'] ?? ''),
                    'paid_to_cleaner_at' => (string) ($ev['paid_to_cleaner_at'] ?? ''),
                    'paid_to_caller_at'  => (string) ($ev['paid_to_caller_at']  ?? ''),
                    'events'             => [],
                    'count'              => 0,
                    'count_payable'      => 0,    // pro čističku
                    'count_refund'       => 0,
                    'count_caller_paid'  => 0,    // pro bonus navolávačky
                    'payout'             => 0.0,
                    'caller_bonus_total' => 0.0,
                    'cleaners'           => [],
                ];
            }
            $byOrder[$oid]['events'][] = $ev;
            $byOrder[$oid]['count']++;
            $totalCount++;

            if ($cln !== '') {
                $byOrder[$oid]['cleaners'][$cln] = true;
                $cleanerSet[$cln] = true;
            }

            // Výplata pro čističku — všechny vyčištěné kromě reklamovaných
            if (!$isRefund) {
                $byOrder[$oid]['count_payable']++;
                $byOrder[$oid]['payout'] += $price;
                $totalPayable += $price;
            } else {
                $byOrder[$oid]['count_refund']++;
            }

            // Bonus pro navolávačku — jen tradeable + úspěšně navolané + bez reklamace + bonus > 0
            if ($isTradeable && $callOk && !$isRefund && $bonus > 0 && $callId > 0) {
                $byOrder[$oid]['count_caller_paid']++;
                $byOrder[$oid]['caller_bonus_total'] += $bonus;
                $totalCallerBonus += $bonus;

                if (!isset($byCallerBonus[$callId])) {
                    $byCallerBonus[$callId] = [
                        'caller_name' => $callName,
                        'orders'      => [],
                        'count_total' => 0,
                        'amount_total'=> 0.0,
                    ];
                }
                if (!isset($byCallerBonus[$callId]['orders'][$oid])) {
                    $byCallerBonus[$callId]['orders'][$oid] = [
                        'bonus_per_lead' => $bonus,
                        'count'          => 0,
                        'amount'         => 0.0,
                        'paid_to_caller_at' => (string) ($ev['paid_to_caller_at'] ?? ''),
                        'leads'          => [],
                    ];
                }
                $byCallerBonus[$callId]['orders'][$oid]['count']++;
                $byCallerBonus[$callId]['orders'][$oid]['amount'] += $bonus;
                $byCallerBonus[$callId]['orders'][$oid]['leads'][] = $ev; // detail leadu pro PDF
                $byCallerBonus[$callId]['count_total']++;
                $byCallerBonus[$callId]['amount_total'] += $bonus;
            }
        }

        // Hlavička objednávky pro single mode (i když nejsou vyčištěné leady)
        $singleOrderHeader = null;
        if ($singleOrder) {
            $oh = $this->pdo->prepare(
                "SELECT id, year, month, requested_count, reserved_count,
                        price_per_lead, status, note, created_at
                 FROM premium_orders WHERE id = :id AND oz_id = :oz LIMIT 1"
            );
            $oh->execute(['id' => $orderId, 'oz' => $ozId]);
            $singleOrderHeader = $oh->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$singleOrderHeader) {
                http_response_code(404);
                echo 'Objednávka nenalezena.';
                exit;
            }
        }

        $cleaners = array_keys($cleanerSet);

        // Funnel stats per objednávka — kolik bylo vyčištěno, tradeable, navoláno
        $orderIds = array_keys($byOrder);
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
            foreach ($byOrder as $oid => &$ord) {
                $ord['funnel'] = $funnelData[(int) $oid] ?? null;
            }
            unset($ord);
            // Stejný funnel doplníme i do bonus sekce (per OZ → orders → funnel)
            foreach ($byCallerBonus as &$cb) {
                foreach ($cb['orders'] as $oid => &$row) {
                    $row['funnel'] = $funnelData[(int) $oid] ?? null;
                }
                unset($row);
            }
            unset($cb);
        }

        header('Content-Type: text/html; charset=UTF-8');
        require dirname(__DIR__) . '/views/oz/premium/payout_print.php';
        exit;
    }
}
