<?php
// e:\Snecinatripu\app\controllers\HomeController.php
declare(strict_types=1);

final class HomeController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getRoot(): void
    {
        if (crm_auth_user_id() !== null) {
            crm_redirect('/dashboard');
        }
        crm_redirect('/login');
    }
}
