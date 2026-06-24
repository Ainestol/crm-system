<?php
declare(strict_types=1);

/**
 * app/helpers/settings.php
 *
 * Generic key-value settings — admin-editable přes app_settings tabulku.
 * Použito pro mix ratio + případné další konfigurace.
 *
 * Funkce:
 *   crm_setting_get(string $key, mixed $default = null): mixed
 *   crm_setting_set(string $key, mixed $value, int $userId = 0): bool
 *   crm_setting_get_int(string $key, int $default = 0): int
 *   crm_setting_get_bool(string $key, bool $default = false): bool
 *
 * Cache: per-request static, ne cross-request. Stačí.
 */

if (!function_exists('crm_setting_get')) {

    /**
     * Per-request cache (refresh při setu).
     * VRACÍ BY REFERENCE — proto `function &_crm_settings_cache()`.
     * Bez `&` by `$cache = &_crm_settings_cache()` v get/set vyhazoval PHP notice.
     */
    function &_crm_settings_cache(): array
    {
        static $cache = [];
        return $cache;
    }

    /**
     * Načte hodnotu setting nebo vrátí default.
     */
    function crm_setting_get(string $key, $default = null)
    {
        global $pdo;
        $cache = &_crm_settings_cache();
        // Multi-tenant: cache key = "{tid}:{key}" (každý tenant svá data)
        $tid = function_exists('crm_tenant_id') ? crm_tenant_id() : 0;
        $cacheKey = $tid . ':' . $key;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }
        try {
            $pdoLocal = $GLOBALS['pdo'] ?? null;
            if (!$pdoLocal instanceof PDO) {
                return $default;
            }
            $stmt = $pdoLocal->prepare(
                "SELECT sval FROM app_settings WHERE skey = ? AND tenant_id = ? LIMIT 1"
            );
            $stmt->execute([$key, $tid]);
            $val = $stmt->fetchColumn();
            $cache[$cacheKey] = ($val === false) ? $default : (string) $val;
            return $cache[$cacheKey];
        } catch (\Throwable $_) {
            return $default;
        }
    }

    /**
     * Uloží hodnotu setting. Vrací true při úspěchu.
     */
    function crm_setting_set(string $key, $value, int $userId = 0): bool
    {
        try {
            $pdoLocal = $GLOBALS['pdo'] ?? null;
            if (!$pdoLocal instanceof PDO) {
                return false;
            }
            // INSERT — TenantAwarePDO auto-injektuje tenant_id.
            // Unique key v migraci 032 byl rozšířen na (tenant_id, skey).
            $stmt = $pdoLocal->prepare(
                "INSERT INTO app_settings (skey, sval, updated_by)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE sval = VALUES(sval), updated_by = VALUES(updated_by)"
            );
            $ok = $stmt->execute([$key, (string) $value, $userId > 0 ? $userId : null]);
            if ($ok) {
                $cache = &_crm_settings_cache();
                $tid = function_exists('crm_tenant_id') ? crm_tenant_id() : 0;
                $cache[$tid . ':' . $key] = (string) $value;
            }
            return $ok;
        } catch (\Throwable $e) {
            if (function_exists('crm_db_log_error')) crm_db_log_error($e, __FUNCTION__);
            return false;
        }
    }

    /** Integer convenience */
    function crm_setting_get_int(string $key, int $default = 0): int
    {
        $v = crm_setting_get($key, null);
        return ($v === null || $v === '') ? $default : (int) $v;
    }

    /** Boolean convenience: hodnoty '1', 'true', 'yes' → true */
    function crm_setting_get_bool(string $key, bool $default = false): bool
    {
        $v = crm_setting_get($key, null);
        if ($v === null) return $default;
        $vl = strtolower(trim((string) $v));
        return in_array($vl, ['1', 'true', 'yes', 'on'], true);
    }
}
