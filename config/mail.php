<?php
// e:\Snecinatripu\config\mail.php
declare(strict_types=1);

/**
 * SMTP nastavení pro odesílání e-mailů (nový zaměstnanec, reset hesla, reporty).
 * Heslo / token SMTP ukládejte šifrovaně v smtp_password_encrypted pomocí crm_encrypt().
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'constants.php';

return [
    'enabled' => filter_var(getenv('CRM_MAIL_ENABLED') ?: '1', FILTER_VALIDATE_BOOLEAN),

    'from_email' => (string) (getenv('CRM_MAIL_FROM') ?: 'noreply@example.local'),
    'from_name' => (string) (getenv('CRM_MAIL_FROM_NAME') ?: 'CRM'),

    'smtp_host' => (string) (getenv('CRM_SMTP_HOST') ?: '127.0.0.1'),
    'smtp_port' => (int) (getenv('CRM_SMTP_PORT') ?: 587),
    // tls | ssl | '' (žádné šifrování transportu – jen vývoj)
    'smtp_encryption' => (string) (getenv('CRM_SMTP_ENCRYPTION') ?: 'tls'),

    'smtp_username' => (string) (getenv('CRM_SMTP_USERNAME') ?: ''),

    /** Výstup crm_encrypt(plain_smtp_password) – prázdné = doplnit v produkci */
    'smtp_password_encrypted' => (string) (getenv('CRM_SMTP_PASSWORD_ENCRYPTED') ?: ''),

    'smtp_timeout' => (int) (getenv('CRM_SMTP_TIMEOUT') ?: 15),
];
