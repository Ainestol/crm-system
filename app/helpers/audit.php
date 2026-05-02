<?php
// e:\Snecinatripu\app\helpers\audit.php
declare(strict_types=1);

/**
 * Zápis do audit_log (citlivé akce, správa uživatelů).
 */

if (!function_exists('crm_db_log_error')) {
    /**
     * Loguje DB výjimku do PHP error logu (typicky storage/logs/php_errors.log
     * nebo Apache error log). Použij místo prázdného `catch (\PDOException) {}`
     * v controllerech.
     *
     * @param \PDOException $e        Zachycená výjimka
     * @param string        $context  Volitelný kontext (např. "OzCtrl::getLeads/pendingByCaller")
     */
    function crm_db_log_error(\PDOException $e, string $context = ''): void
    {
        $line = '[CRM DB] '
              . ($context !== '' ? '[' . $context . '] ' : '')
              . $e->getMessage()
              . ' (SQLSTATE ' . (string) $e->getCode() . ')';
        error_log($line);
    }
}

if (!function_exists('crm_audit_log')) {
    /**
     * @param array<string, mixed>|null $details
     */
    function crm_audit_log(
        PDO $pdo,
        ?int $userId,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $details = null,
        string $source = 'web'
    ): void {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $stmt = $pdo->prepare(
            'INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address, source, created_at)
             VALUES (:uid, :act, :et, :eid, :det, :ip, :src, NOW(3))'
        );
        $stmt->execute([
            'uid' => $userId,
            'act' => $action,
            'et' => $entityType,
            'eid' => $entityId,
            'det' => $details === null ? null : json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'ip' => $ip !== '' ? $ip : null,
            'src' => $source,
        ]);
    }
}
