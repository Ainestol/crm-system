<?php
declare(strict_types=1);

/**
 * OzEmailLeadsController
 *
 * Sekce pro OZ — kontakty získané ze sázek s delivery_type='email'.
 * Tyto kontakty přeskočily caller pool (stav = EMAIL_READY) a OZ je má
 * k dispozici pro email kampaň (export do CSV/XLSX).
 *
 * Routes:
 *   GET  /oz/email-leads           → seznam vlastních email leadů
 *   GET  /oz/email-leads/export    → CSV download (pro emailing nástroj)
 *
 * Role: obchodak, majitel, superadmin.
 */
final class OzEmailLeadsController
{
    public function __construct(private PDO $pdo)
    {
    }

    /** GET /oz/email-leads — seznam */
    public function getIndex(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        $ozId = (int) $user['id'];

        // Načti kontakty stav=EMAIL_READY přiřazené tomuto OZ
        $stmt = $this->pdo->prepare(
            "SELECT c.id, c.firma, c.ico, c.telefon, c.email, c.adresa,
                    c.region, c.operator, c.updated_at,
                    bc.name AS campaign_name, bc.id AS campaign_id
             FROM contacts c
             LEFT JOIN bet_campaign_leads bcl ON bcl.contact_id = c.id
             LEFT JOIN bet_campaigns bc ON bc.id = bcl.campaign_id
             WHERE c.stav = 'EMAIL_READY'
               AND c.assigned_sales_id = :oz
             ORDER BY c.updated_at DESC
             LIMIT 5000"
        );
        $stmt->execute(['oz' => $ozId]);
        $leads = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();
        $title = 'Email leady';
        ob_start();
        require dirname(__DIR__) . '/views/oz/email_leads.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    /** GET /oz/email-leads/export — XLSX download */
    public function getExport(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        $ozId = (int) $user['id'];

        $stmt = $this->pdo->prepare(
            "SELECT c.firma, c.ico, c.email, c.telefon, c.adresa, c.region,
                    c.operator, c.updated_at,
                    bc.name AS campaign_name
             FROM contacts c
             LEFT JOIN bet_campaign_leads bcl ON bcl.contact_id = c.id
             LEFT JOIN bet_campaigns bc ON bc.id = bcl.campaign_id
             WHERE c.stav = 'EMAIL_READY'
               AND c.assigned_sales_id = :oz
             ORDER BY c.updated_at DESC"
        );
        $stmt->execute(['oz' => $ozId]);

        // Lazy generátor řádků — šetří paměť při velkém datasetu
        $rowsGenerator = (function () use ($stmt) {
            while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                yield [
                    (string) ($row['firma'] ?? ''),
                    (string) ($row['ico'] ?? ''),
                    (string) ($row['email'] ?? ''),
                    (string) ($row['telefon'] ?? ''),
                    (string) ($row['adresa'] ?? ''),
                    function_exists('crm_region_label')
                        ? crm_region_label((string) ($row['region'] ?? ''))
                        : (string) ($row['region'] ?? ''),
                    (string) ($row['operator'] ?? ''),
                    (string) ($row['campaign_name'] ?? ''),
                    !empty($row['updated_at']) ? date('d.m.Y H:i', strtotime((string) $row['updated_at'])) : '',
                ];
            }
        })();

        $filename = 'email_leady_' . date('Y-m-d_His') . '.xlsx';
        crm_xlsx_send_download(
            $filename,
            ['Firma', 'IČO', 'Email', 'Telefon', 'Adresa', 'Kraj', 'Operátor', 'Kampaň', 'Přiřazeno'],
            $rowsGenerator
        );
        // crm_xlsx_send_download sám volá exit
    }
}
