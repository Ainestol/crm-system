<?php
// e:\Snecinatripu\app\controllers\SuspendedController.php
declare(strict_types=1);

/**
 * SuspendedController
 *
 * Vlídná landing page pro tenanty s prošlým předplatným / trialem.
 * Zobrazí důvod, kontakt na podporu a odkaz na logout.
 *
 * Route: GET /suspended (no auth — řešíme stav uvnitř kontroleru)
 */
final class SuspendedController
{
    public function __construct(private PDO $pdo) {}

    public function getIndex(): void
    {
        // Zjistíme kontext: kdo jsme, jaký tenant, jaký stav.
        // Neredirectneme zpět na /login pokud user není přihlášen — necháme
        // stránku zobrazit jako veřejnou (= někdo poslal odkaz emailem).
        $user = function_exists('crm_auth_current_user') ? crm_auth_current_user($this->pdo) : null;
        $tenantId = function_exists('crm_tenant_id') ? crm_tenant_id() : 0;
        $isSuper  = function_exists('crm_tenant_is_super_admin') && crm_tenant_is_super_admin();

        $tenant = null;
        $lifecycle = null;
        if ($tenantId > 0 && function_exists('crm_tenant_get_full')) {
            $tenant = crm_tenant_get_full($this->pdo, $tenantId);
            if (function_exists('crm_tenant_lifecycle_state')) {
                $lifecycle = crm_tenant_lifecycle_state($this->pdo, $tenantId);
            }
        }

        // Super-admin co sem zabloudí náhodou → pošleme rovnou na /admin/tenants
        if ($isSuper) {
            crm_redirect('/admin/tenants');
        }

        // Stav má vlastní kopii (přátelská zpráva)
        $stateMessages = [
            'expired_paid'  => [
                'title' => '⏰ Vaše předplatné prošlo',
                'body'  => 'Měsíční předplatné vašeho účtu skončilo. Abyste mohli systém dál používat, prosím proveďte úhradu nebo nás kontaktujte.',
            ],
            'expired_trial' => [
                'title' => '🧪 Trial období skončilo',
                'body'  => 'Vyzkoušeli jste si Cloud CRM zdarma. Pokud chcete pokračovat, vyberte si placený plán nebo nás kontaktujte.',
            ],
            'suspended' => [
                'title' => '🚫 Účet je dočasně pozastaven',
                'body'  => 'Váš firemní účet je pozastaven. Pro reaktivaci nás prosím kontaktujte.',
            ],
        ];
        $stateKey = (string) ($lifecycle['state'] ?? 'suspended');
        $msg = $stateMessages[$stateKey] ?? $stateMessages['suspended'];

        $title = 'Účet pozastaven';

        // Renderujeme bez sidebaru — minimalistický layout
        ob_start();
        require dirname(__DIR__) . '/views/auth/suspended.php';
        $content = (string) ob_get_clean();

        // Použijeme zjednodušený layout (bez sidebaru) — base.php má hidden case
        // pro nepřihlášené, sem ale chceme i pro přihlášené minimal layout.
        // Nejjednodušší: vlastní HTML wrapper.
        echo $content;
        exit;
    }
}
