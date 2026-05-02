<?php
// e:\Snecinatripu\app\helpers\region.php
declare(strict_types=1);

/**
 * Aktivní region obchodáka v session (region switcher). Povolené regiony z user_regions.
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'session.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'url.php';

if (!defined('CRM_SESSION_ACTIVE_REGION')) {
    define('CRM_SESSION_ACTIVE_REGION', 'crm_active_region');
}

if (!function_exists('crm_regions_allowed')) {
    /**
     * @return list<string>
     */
    function crm_regions_allowed(PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare(
            'SELECT region FROM user_regions WHERE user_id = :id ORDER BY region ASC'
        );
        $stmt->execute(['id' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            if (is_string($r) && $r !== '') {
                $out[] = $r;
            }
        }
        return $out;
    }
}

if (!function_exists('crm_active_region_get')) {
    /**
     * Vrátí aktivní region pro obchodáka (nebo null u ostatních rolí / bez regionů).
     */
    function crm_active_region_get(PDO $pdo, array $user): ?string
    {
        if (($user['role'] ?? '') !== 'obchodak') {
            return null;
        }
        $id = (int) ($user['id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        $allowed = crm_regions_allowed($pdo, $id);
        if ($allowed === []) {
            return null;
        }
        crm_session_start();
        $cur = $_SESSION[CRM_SESSION_ACTIVE_REGION] ?? null;
        if (is_string($cur) && in_array($cur, $allowed, true)) {
            return $cur;
        }
        $primary = (string) ($user['primary_region'] ?? '');
        if ($primary !== '' && in_array($primary, $allowed, true)) {
            $_SESSION[CRM_SESSION_ACTIVE_REGION] = $primary;
            return $primary;
        }
        $_SESSION[CRM_SESSION_ACTIVE_REGION] = $allowed[0];
        return $allowed[0];
    }
}

if (!function_exists('crm_active_region_set')) {
    function crm_active_region_set(PDO $pdo, array $user, string $region): bool
    {
        if (($user['role'] ?? '') !== 'obchodak') {
            return false;
        }
        $region = strtolower(trim($region));
        if ($region === '') {
            return false;
        }
        $id = (int) ($user['id'] ?? 0);
        if ($id <= 0) {
            return false;
        }
        $allowed = crm_regions_allowed($pdo, $id);
        if (!in_array($region, $allowed, true)) {
            return false;
        }
        crm_session_start();
        $_SESSION[CRM_SESSION_ACTIVE_REGION] = $region;
        return true;
    }
}

if (!function_exists('crm_region_clear_session')) {
    function crm_region_clear_session(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        unset($_SESSION[CRM_SESSION_ACTIVE_REGION]);
    }
}
