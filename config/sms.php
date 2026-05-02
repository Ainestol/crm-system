<?php
// e:\Snecinatripu\config\sms.php
declare(strict_types=1);

/**
 * Konfigurace SMS brány (provider, odesílatel, interval připomínky callbacku).
 * API klíče a tajemství ukládejte šifrovaně: crm_encrypt('plain_text') z constants.php,
 * sem vložte pouze výsledný řetězec (nebo načítejte z DB v administraci).
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'constants.php';

return [
    // Globální zapnutí/vypnutí odesílání SMS (admin UI může přepsat přes DB později)
    'global_enabled' => filter_var(getenv('CRM_SMS_ENABLED') ?: '1', FILTER_VALIDATE_BOOLEAN),

    // smsbrana | smsmanager | twilio (mapuje se na implementaci SmsProvider)
    'provider' => (string) (getenv('CRM_SMS_PROVIDER') ?: 'smsbrana'),

    'sender_id' => (string) (getenv('CRM_SMS_SENDER_ID') ?: ''),

    // Minuty před callback_at pro cron připomínku (výchozí 30 dle specifikace)
    'callback_reminder_minutes' => (int) (getenv('CRM_SMS_CALLBACK_REMINDER_MINUTES') ?: 30),

    /**
     * Šifrované údaje providera (openssl_encrypt přes crm_encrypt).
     * Doporučený JSON před šifrováním např.:
     *   {"api_key":"...","api_secret":"...","username":"..."}
     * Pro SMSbrana.cz / SMS Manager doplňte pole dle dokumentace konkrétního API.
     */
    'credentials_encrypted' => (string) (getenv('CRM_SMS_CREDENTIALS_ENCRYPTED') ?: ''),

    // Volitelné nešifrované endpoint URL, pokud provider vyžaduje (jinak prázdné)
    'api_base_url' => (string) (getenv('CRM_SMS_API_BASE_URL') ?: ''),
];
