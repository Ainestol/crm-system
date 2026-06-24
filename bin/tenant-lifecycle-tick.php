<?php
// e:\Snecinatripu\bin\tenant-lifecycle-tick.php
declare(strict_types=1);

/**
 * ════════════════════════════════════════════════════════════════════
 *  TENANT LIFECYCLE TICK
 *
 *  Cron / Task Scheduler job — volat 1× denně.
 *
 *  Co dělá:
 *    1. Iteruje všechny tenanty
 *    2. Spočítá lifecycle stav (crm_tenant_lifecycle_state)
 *    3. Tenanti ve stavu expired_paid/expired_trial s active=1 → auto-suspend (active=0)
 *    4. Tenanti se SUSPENDED + nově paid_until > NOW → auto-reactivate (active=1)
 *    5. Vše zaloguje do audit_log a vypíše na stdout
 *
 *  Bezpečné na opakované spuštění — žádné side-effekty mimo SUSPEND/UNSUSPEND.
 *
 *  Použití:
 *    php bin/tenant-lifecycle-tick.php           — spustí změny
 *    php bin/tenant-lifecycle-tick.php --dry-run — jen vypíše, nic nemění
 *
 *  Cron příklad (Linux):
 *    0 3 * * * /usr/bin/php /var/www/crm/bin/tenant-lifecycle-tick.php >> /var/log/crm-lifecycle.log 2>&1
 *
 *  Task Scheduler (Windows):
 *    daily 03:00 → php.exe E:\Snecinatripu\bin\tenant-lifecycle-tick.php
 * ════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Spouštěj jen z CLI.\n");
    exit(1);
}

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

$pdo    = crm_pdo();
$dryRun = in_array('--dry-run', $argv ?? [], true);

echo "════════════════════════════════════════════════════════\n";
echo "  TENANT LIFECYCLE TICK " . ($dryRun ? '(DRY RUN)' : '') . "\n";
echo "  " . date('Y-m-d H:i:s') . "\n";
echo "════════════════════════════════════════════════════════\n\n";

$tenants = $pdo->query('SELECT id, name, active, paid_until, trial_ends_at FROM tenants ORDER BY id')
    ->fetchAll(PDO::FETCH_ASSOC) ?: [];

if ($tenants === []) {
    echo "Žádní tenanti — nic na práci.\n";
    exit(0);
}

$suspended = 0;
$reactivated = 0;
$unchanged = 0;

foreach ($tenants as $t) {
    $tid    = (int) $t['id'];
    $name   = (string) $t['name'];
    $state  = crm_tenant_lifecycle_state($pdo, $tid);
    $stateStr = (string) $state['state'];
    $days   = $state['days_until_expiry'];

    $line = sprintf(
        "  #%-3d %-30s  [%s%s]",
        $tid,
        mb_substr($name, 0, 30),
        $stateStr,
        $days !== null ? ' ' . $days . 'd' : ''
    );

    // Auto-suspend: aktivní tenant, kterému prošlo placení/trial
    if (crm_tenant_should_auto_suspend($state)) {
        if (!$dryRun) {
            try {
                $pdo->prepare('UPDATE tenants SET active = 0, updated_at = NOW(3) WHERE id = ?')
                    ->execute([$tid]);
                if (function_exists('crm_audit_log')) {
                    crm_audit_log($pdo, null, 'tenant_auto_suspend', 'tenant', $tid,
                        ['reason' => $stateStr, 'paid_until' => $t['paid_until'], 'trial_ends_at' => $t['trial_ends_at']],
                        'cron');
                }
            } catch (\PDOException $e) {
                crm_db_log_error($e, 'tenant-lifecycle-tick:suspend');
                echo $line . "  ❌ DB chyba při suspendu: " . $e->getMessage() . "\n";
                continue;
            }
        }
        echo $line . "  → 🚫 SUSPEND\n";
        $suspended++;
        continue;
    }

    // Auto-reactivate: suspendovaný tenant s paid_until v budoucnosti
    // (= došla platba, super-admin ji zapsal přes /admin/tenants/log-payment)
    if ((int) $t['active'] === 0
        && !empty($t['paid_until'])
        && strtotime((string) $t['paid_until']) > time()
    ) {
        if (!$dryRun) {
            try {
                $pdo->prepare('UPDATE tenants SET active = 1, updated_at = NOW(3) WHERE id = ?')
                    ->execute([$tid]);
                if (function_exists('crm_audit_log')) {
                    crm_audit_log($pdo, null, 'tenant_auto_reactivate', 'tenant', $tid,
                        ['paid_until' => $t['paid_until']],
                        'cron');
                }
            } catch (\PDOException $e) {
                crm_db_log_error($e, 'tenant-lifecycle-tick:reactivate');
                echo $line . "  ❌ DB chyba při reaktivaci: " . $e->getMessage() . "\n";
                continue;
            }
        }
        echo $line . "  → ✅ REACTIVATE\n";
        $reactivated++;
        continue;
    }

    // Beze změny — info badge podle stavu
    $badge = match ($stateStr) {
        'unlimited' => '∞',
        'trial'     => '🧪',
        'active'    => '✓',
        'grace'     => '⚠ grace',
        'suspended' => '🚫',
        default     => '?',
    };
    echo $line . "  " . $badge . "\n";
    $unchanged++;
}

echo "\n════════════════════════════════════════════════════════\n";
echo "  HOTOVO\n";
echo "════════════════════════════════════════════════════════\n";
echo sprintf("  🚫 suspended:   %d\n", $suspended);
echo sprintf("  ✅ reactivated: %d\n", $reactivated);
echo sprintf("  · beze změny:   %d\n", $unchanged);
if ($dryRun) {
    echo "\n⚠ DRY RUN — žádné změny nezapsány. Pusť bez --dry-run pro skutečnou změnu.\n";
}
exit(0);
