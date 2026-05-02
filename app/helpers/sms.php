<?php
// e:\Snecinatripu\app\helpers\sms.php
declare(strict_types=1);

/**
 * SMS brána – rozhraní providera, továrna podle config/sms.php, dešifrování credentials JSON.
 * Konkrétní HTTP volání doplníte dle dokumentace SMSbrana.cz / SMS Manager / Twilio (krok 31).
 */

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'constants.php';

if (defined('CRM_HELPERS_SMS_LOADED')) {
    return;
}
define('CRM_HELPERS_SMS_LOADED', true);

interface SmsProviderInterface
{
    /**
     * @return array{ok:bool, status:string, response:?string}
     */
    public function send(string $phone, string $message): array;
}

final class SmsSendResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly string $status,
        public readonly ?string $response = null
    ) {
    }

    /** @return array{ok:bool, status:string, response:?string} */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'status' => $this->status,
            'response' => $this->response,
        ];
    }
}

/** Výchozí implementace bez HTTP (log / vývoj). */
final class SmsProviderStub implements SmsProviderInterface
{
    public function send(string $phone, string $message): array
    {
        $snippet = strlen($message) > 120 ? substr($message, 0, 120) . '…' : $message;
        $line = sprintf('[SMS stub] %s | %s', $phone, $snippet);
        $logFile = CRM_STORAGE_PATH . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'sms_stub.log';
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        @file_put_contents($logFile, date('c') . ' ' . $line . "\n", FILE_APPEND);
        return (new SmsSendResult(true, 'stub', $line))->toArray();
    }
}

/** @return array<string, mixed> */
function sms_config(): array
{
    return require CRM_CONFIG_PATH . DIRECTORY_SEPARATOR . 'sms.php';
}

/** @return array<string, mixed>|null */
function sms_decrypted_credentials(): ?array
{
    $cfg = sms_config();
    $enc = (string) ($cfg['credentials_encrypted'] ?? '');
    if ($enc === '') {
        return null;
    }
    $plain = crm_decrypt($enc);
    if ($plain === null || $plain === '') {
        return null;
    }
    $data = json_decode($plain, true);
    return is_array($data) ? $data : null;
}

function sms_factory(?array $cfg = null): SmsProviderInterface
{
    $cfg ??= sms_config();
    $provider = strtolower((string) ($cfg['provider'] ?? 'smsbrana'));
    return match ($provider) {
        'smsbrana', 'smsmanager', 'twilio' => new SmsProviderStub(),
        default => new SmsProviderStub(),
    };
}

/**
 * Odešle SMS pokud je v konfiguraci zapnuto; jinak vrátí ok=false, status=disabled.
 *
 * @return array{ok:bool, status:string, response:?string}
 */
function sms_send_if_enabled(string $phone, string $message): array
{
    $cfg = sms_config();
    if (empty($cfg['global_enabled'])) {
        return (new SmsSendResult(false, 'disabled', null))->toArray();
    }
    $impl = sms_factory($cfg);
    return $impl->send($phone, $message);
}
