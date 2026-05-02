<?php
// e:\Snecinatripu\app\helpers\flash.php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'session.php';

if (!function_exists('crm_flash_set')) {
    function crm_flash_set(string $message): void
    {
        crm_session_start();
        $_SESSION['crm_flash_message'] = $message;
    }
}

if (!function_exists('crm_flash_take')) {
    function crm_flash_take(): ?string
    {
        crm_session_start();
        if (!isset($_SESSION['crm_flash_message'])) {
            return null;
        }
        $m = $_SESSION['crm_flash_message'];
        unset($_SESSION['crm_flash_message']);
        return is_string($m) ? $m : null;
    }
}
