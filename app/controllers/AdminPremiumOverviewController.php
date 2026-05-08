<?php
// e:\Snecinatripu\app\controllers\AdminPremiumOverviewController.php
declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'audit.php';

/**
 * Globální přehled premium pipeline pro majitele / superadmina.
 *
 * Routes:
 *   GET /admin/premium-overview — tabulka všech objednávek napříč všemi OZ
 *                                  + souhrnné statistiky (kolik vyčištěno,
 *                                  kolik navoláno, kolik se platilo komu).
 *
 * Vlastní akce na objednávkách (close, cancel, mark-paid) admin nedělá zde —
 * řeší je v běžných premium kontrolerech (admin tam má roli, takže funkce
 * fungují stejně jako u OZ vlastníka).
 */
final class AdminPremiumOverviewController
{
    public function __construct(private PDO $pdo) {}

    public function getIndex(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        // Lazy refill — ať čerstvě dorezervované leady jsou hned vidět
        if (class_exists('PremiumOrderController')) {
            PremiumOrderController::topUpOpenOrders($this->pdo);
        }

        // ── Filtr: ?status=open|closed|cancelled|all (default all) ──
        $statusFilter = (string) ($_GET['status'] ?? 'all');
        if (!in_array($statusFilter, ['all', 'open', 'closed', 'cancelled'], true)) {
            $statusFilter = 'all';
        }

        // ── Filtr: ?oz_id=N (default all) ──
        $ozFilter = (int) ($_GET['oz_id'] ?? 0);

        $where  = ['1=1'];
        $params = [];
        if ($statusFilter !== 'all') {
            $where[] = 'po.status = :status';
            $params['status'] = $statusFilter;
        }
        if ($ozFilter > 0) {
            $where[] = 'po.oz_id = :oz_id';
            $params['oz_id'] = $ozFilter;
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        // ── Hlavní tabulka objednávek ──
        $sql = "SELECT po.*,
                       u_oz.jmeno    AS oz_name,
                       u_pc.jmeno    AS preferred_caller_name,
                       u_pcb.jmeno   AS paid_cleaner_by_name,
                       u_pcr.jmeno   AS paid_caller_by_name,
                       (SELECT COUNT(*) FROM premium_lead_pool p WHERE p.order_id = po.id) AS pool_total,
                       (SELECT COUNT(*) FROM premium_lead_pool p WHERE p.order_id = po.id AND p.cleaning_status = 'pending') AS pool_pending,
                       (SELECT COUNT(*) FROM premium_lead_pool p WHERE p.order_id = po.id AND p.cleaning_status = 'tradeable') AS pool_tradeable,
                       (SELECT COUNT(*) FROM premium_lead_pool p WHERE p.order_id = po.id AND p.cleaning_status = 'non_tradeable') AS pool_non_tradeable,
                       (SELECT COUNT(*) FROM premium_lead_pool p WHERE p.order_id = po.id AND p.call_status = 'success') AS pool_called_success,
                       (SELECT COUNT(*) FROM premium_lead_pool p WHERE p.order_id = po.id AND p.cleaning_status IN ('tradeable','non_tradeable') AND p.flagged_for_refund = 0) AS payable_to_cleaner,
                       (SELECT COUNT(*) FROM premium_lead_pool p WHERE p.order_id = po.id AND p.cleaning_status = 'tradeable' AND p.call_status = 'success' AND p.flagged_for_refund = 0) AS payable_to_caller
                FROM premium_orders po
                JOIN users u_oz       ON u_oz.id = po.oz_id
                LEFT JOIN users u_pc  ON u_pc.id  = po.preferred_caller_id
                LEFT JOIN users u_pcb ON u_pcb.id = po.paid_to_cleaner_by
                LEFT JOIN users u_pcr ON u_pcr.id = po.paid_to_caller_by
                $whereSql
                ORDER BY po.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // ── Globální statistiky (přes celou DB, nezávisle na filtru) ──
        $statsStmt = $this->pdo->query(
            "SELECT
                COUNT(*) AS orders_total,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS orders_open,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS orders_closed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS orders_cancelled,
                SUM(requested_count) AS leads_ordered_total,
                SUM(reserved_count)  AS leads_reserved_total
             FROM premium_orders"
        );
        $stats = $statsStmt ? $statsStmt->fetch(PDO::FETCH_ASSOC) : [];

        $poolStatsStmt = $this->pdo->query(
            "SELECT
                COUNT(*) AS pool_total,
                SUM(cleaning_status = 'pending') AS cleaning_pending,
                SUM(cleaning_status = 'tradeable') AS cleaning_tradeable,
                SUM(cleaning_status = 'non_tradeable') AS cleaning_non_tradeable,
                SUM(call_status = 'success') AS call_success,
                SUM(call_status = 'failed') AS call_failed,
                SUM(flagged_for_refund = 1) AS flagged_refund
             FROM premium_lead_pool"
        );
        $poolStats = $poolStatsStmt ? $poolStatsStmt->fetch(PDO::FETCH_ASSOC) : [];

        // ── Sumy plateb (kdo komu kolik dluží/zaplatil) ──
        $moneyStmt = $this->pdo->query(
            "SELECT
                SUM(po.price_per_lead *
                    (SELECT COUNT(*) FROM premium_lead_pool p
                       WHERE p.order_id = po.id
                         AND p.cleaning_status IN ('tradeable','non_tradeable')
                         AND p.flagged_for_refund = 0)
                ) AS due_to_cleaner_total,
                SUM(po.caller_bonus_per_lead *
                    (SELECT COUNT(*) FROM premium_lead_pool p
                       WHERE p.order_id = po.id
                         AND p.cleaning_status = 'tradeable'
                         AND p.call_status = 'success'
                         AND p.flagged_for_refund = 0)
                ) AS due_to_caller_total,
                SUM(CASE WHEN po.paid_to_cleaner_at IS NOT NULL
                         THEN po.price_per_lead *
                              (SELECT COUNT(*) FROM premium_lead_pool p
                                 WHERE p.order_id = po.id
                                   AND p.cleaning_status IN ('tradeable','non_tradeable')
                                   AND p.flagged_for_refund = 0)
                         ELSE 0 END) AS paid_to_cleaner_total,
                SUM(CASE WHEN po.paid_to_caller_at IS NOT NULL
                         THEN po.caller_bonus_per_lead *
                              (SELECT COUNT(*) FROM premium_lead_pool p
                                 WHERE p.order_id = po.id
                                   AND p.cleaning_status = 'tradeable'
                                   AND p.call_status = 'success'
                                   AND p.flagged_for_refund = 0)
                         ELSE 0 END) AS paid_to_caller_total
             FROM premium_orders po"
        );
        $money = $moneyStmt ? $moneyStmt->fetch(PDO::FETCH_ASSOC) : [];

        // ── OZ list pro filter dropdown ──
        $ozListStmt = $this->pdo->query(
            "SELECT DISTINCT u.id, u.jmeno
             FROM premium_orders po JOIN users u ON u.id = po.oz_id
             ORDER BY u.jmeno ASC"
        );
        $ozList = $ozListStmt ? $ozListStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $title = '💎 Premium pipeline — admin přehled';
        $csrf  = crm_csrf_token();
        $flash = crm_flash_take();

        ob_start();
        require dirname(__DIR__) . '/views/admin/premium/overview.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }
}
