<?php
// e:\Snecinatripu\app\helpers\users_admin.php
declare(strict_types=1);

/**
 * Pravidla pro správu uživatelů (majitel vs superadmin).
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'mail.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'url.php';

/** @return list<string> */
function crm_region_choices(): array
{
    return [
        'praha',
        'stredocesky',
        'jihocesky',
        'plzensky',
        'karlovarsky',
        'ustecky',
        'liberecky',
        'kralovehradecky',
        'pardubicky',
        'vysocina',
        'jihomoravsky',
        'olomoucky',
        'zlinsky',
        'moravskoslezsky',
    ];
}

/** Vrátí český název regionu pro zobrazení v UI. */
function crm_region_label(string $code): string
{
    static $labels = [
        'praha' => 'Hlavní město Praha',
        'stredocesky' => 'Středočeský kraj',
        'jihocesky' => 'Jihočeský kraj',
        'plzensky' => 'Plzeňský kraj',
        'karlovarsky' => 'Karlovarský kraj',
        'ustecky' => 'Ústecký kraj',
        'liberecky' => 'Liberecký kraj',
        'kralovehradecky' => 'Královéhradecký kraj',
        'pardubicky' => 'Pardubický kraj',
        'vysocina' => 'Kraj Vysočina',
        'jihomoravsky' => 'Jihomoravský kraj',
        'olomoucky' => 'Olomoucký kraj',
        'zlinsky' => 'Zlínský kraj',
        'moravskoslezsky' => 'Moravskoslezský kraj',
    ];
    return $labels[$code] ?? $code;
}

/** @return list<string> */
function crm_all_role_values(): array
{
    // 'cisticka' = role pro ověřovatelku kontaktů (viz CistickaController).
    // Pořadí v poli určuje pořadí v dropdownu admin/users formu.
    return ['superadmin', 'majitel', 'navolavacka', 'obchodak', 'cisticka', 'backoffice'];
}

function crm_users_actor_can_assign_role(string $actorRole, string $newRole): bool
{
    if (!in_array($newRole, crm_all_role_values(), true)) {
        return false;
    }
    if ($actorRole === 'superadmin') {
        return true;
    }
    if ($actorRole === 'majitel') {
        return $newRole !== 'superadmin';
    }
    return false;
}

function crm_users_actor_can_manage_target(string $actorRole, array $targetUser): bool
{
    if (!in_array($actorRole, ['superadmin', 'majitel'], true)) {
        return false;
    }
    if ($actorRole === 'majitel' && (($targetUser['role'] ?? '') === 'superadmin')) {
        return false;
    }
    return true;
}

/** @param list<string> $regions */
function crm_user_regions_replace(PDO $pdo, int $userId, array $regions): void
{
    $pdo->prepare('DELETE FROM user_regions WHERE user_id = :id')->execute(['id' => $userId]);
    $ins = $pdo->prepare('INSERT INTO user_regions (user_id, region) VALUES (:uid, :reg)');
    $seen = [];
    foreach ($regions as $raw) {
        if (!is_string($raw)) {
            continue;
        }
        $r = strtolower(trim($raw));
        if ($r === '' || isset($seen[$r])) {
            continue;
        }
        $seen[$r] = true;
        $ins->execute(['uid' => $userId, 'reg' => $r]);
    }
}

function crm_generate_temp_password(int $length = 14): string
{
    $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $out = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

function crm_mail_welcome_user(string $toEmail, string $jmeno, string $plainPassword): bool
{
    $loginUrl = CRM_APP_URL . crm_url('/login');
    $body = "Dobrý den, {$jmeno},\n\n"
        . "byl pro vás vytvořen účet v CRM.\n\n"
        . "Přihlášení: {$loginUrl}\n"
        . "E-mail: {$toEmail}\n"
        . "Dočasné heslo: {$plainPassword}\n\n"
        . "Po prvním přihlášení budete vyzváni ke změně hesla.\n\n"
        . "S pozdravem,\nCRM";

    return crm_mail_send([
        'to' => $toEmail,
        'subject' => 'Přístup do CRM',
        'body' => $body,
    ]);
}

function crm_mail_password_reset(string $toEmail, string $jmeno, string $plainPassword): bool
{
    $loginUrl = CRM_APP_URL . crm_url('/login');
    $body = "Dobrý den, {$jmeno},\n\n"
        . "administrátor resetoval vaše heslo v CRM.\n\n"
        . "Přihlášení: {$loginUrl}\n"
        . "E-mail: {$toEmail}\n"
        . "Nové heslo: {$plainPassword}\n\n"
        . "Po přihlášení si heslo změňte v profilu.\n\n"
        . "S pozdravem,\nCRM";

    return crm_mail_send([
        'to' => $toEmail,
        'subject' => 'Reset hesla – CRM',
        'body' => $body,
    ]);
}
