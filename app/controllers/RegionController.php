<?php
// e:\Snecinatripu\app\controllers\RegionController.php
declare(strict_types=1);

final class RegionController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function postSwitch(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/dashboard');
        }

        $region = (string) ($_POST['region'] ?? '');
        if (!crm_active_region_set($this->pdo, $user, $region)) {
            crm_flash_set('Nelze přepnout na zvolený region.');
            crm_redirect('/dashboard');
        }

        crm_flash_set('Aktivní region byl změněn.');
        crm_redirect('/dashboard');
    }
}
