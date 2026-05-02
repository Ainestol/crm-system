<?php
// e:\Snecinatripu\tools\reset_contacts.php
declare(strict_types=1);

/**
 * RESET KONTAKTŮ — CLI verze.
 * Smaže všechny kontakty a závislé tabulky. Uživatelé a struktury (kvóty,
 * stages, milníky) zůstávají.
 *
 * SPUŠTĚNÍ:
 *   php tools/reset_contacts.php --dry-run
 *       → ukáže kolik se SMAŽE, ale nic nemaže
 *
 *   php tools/reset_contacts.php --confirm
 *       → skutečně smaže (potřeba explicitní flag, jinak se nic nestane)
 *
 * BEZPEČNOST:
 *   - Bez --confirm jen ukáže náhled
 *   - Odmítne se spustit přes web (PHP_SAPI === 'cli' guard)
 *   - Audit log se NEUKLÁDÁ (žádný user kontext z CLI) — pro auditovatelnou
 *     verzi použij /admin/import → tlačítko "🗑 Reset DB"
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'Tento skript je dostupný pouze z příkazové řádky.';
    exit(1);
}

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'constants.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'auth.php';

$args     = $argv ?? [];
$dryRun   = in_array('--dry-run', $args, true);
$confirm  = in_array('--confirm', $args, true);

if (!$dryRun && !$confirm) {
    fwrite(STDERR, "[CHYBA] Použijte --dry-run (náhled) nebo --confirm (skutečně smazat).\n");
    fwrite(STDERR, "Příklad:\n  php tools/reset_contacts.php --dry-run\n  php tools/reset_contacts.php --confirm\n");
    exit(2);
}

$pdo = crm_pdo();

// ── Tabulky k vyčištění ──────────────────────────────────────────────
// Pořadí respektuje FK a logické závislosti.
$truncateOrder = [
    // S explicitním FK na contacts (CASCADE / RESTRICT)
    'commissions',                  // RESTRICT FK — musí být první
    'contact_quality_ratings',      // CASCADE
    'contact_notes',                // CASCADE
    'workflow_log',                 // CASCADE
    'assignment_log',               // CASCADE
    'sms_log',                      // SET NULL FK
    // Bez FK, vytvořené migracemi (mohou neexistovat)
    'oz_contact_workflow',
    'oz_contact_notes',
    'oz_contact_actions',
    'contact_oz_flags',
    // Hlavní tabulka
    'contacts',
];
$cleanupAlso = [
    'import_log',                   // DELETE (zachová auto_increment)
];

// ── Náhled: kolik řádků se smaže ─────────────────────────────────────
fwrite(STDOUT, "──────────────────────────────────────────\n");
fwrite(STDOUT, "  CRM RESET — náhled\n");
fwrite(STDOUT, "──────────────────────────────────────────\n");

$totalRows = 0;
foreach (array_merge($truncateOrder, $cleanupAlso) as $table) {
    if (!tableExists($pdo, $table)) {
        fwrite(STDOUT, sprintf("  %-30s  (tabulka neexistuje, přeskočeno)\n", $table));
        continue;
    }
    $cnt = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    fwrite(STDOUT, sprintf("  %-30s  %s řádků\n", $table, number_format($cnt, 0, ',', ' ')));
    $totalRows += $cnt;
}
fwrite(STDOUT, sprintf("\n  CELKEM: %s řádků k smazání\n", number_format($totalRows, 0, ',', ' ')));
fwrite(STDOUT, "──────────────────────────────────────────\n");

if ($dryRun) {
    fwrite(STDOUT, "\nDRY-RUN — žádná data nebyla smazána.\n");
    fwrite(STDOUT, "Pro skutečný reset spusťte:\n  php tools/reset_contacts.php --confirm\n");
    exit(0);
}

// ── Explicitní textové potvrzení ─────────────────────────────────────
fwrite(STDOUT, "\n⚠ POZOR: TOHLE JE NEVRATNÉ.\n");
fwrite(STDOUT, "Pro pokračování napište přesně 'RESET' a stiskněte Enter: ");
$input = trim((string) fgets(STDIN));
if ($input !== 'RESET') {
    fwrite(STDOUT, "\nZrušeno (zadání nebylo 'RESET').\n");
    exit(0);
}

// ── Skutečný reset ──────────────────────────────────────────────────
fwrite(STDOUT, "\nMažu...\n");
$start = microtime(true);

try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($truncateOrder as $table) {
        if (!tableExists($pdo, $table)) continue;
        $pdo->exec("TRUNCATE TABLE `{$table}`");
        fwrite(STDOUT, "  ✓ TRUNCATE {$table}\n");
    }
    foreach ($cleanupAlso as $table) {
        if (!tableExists($pdo, $table)) continue;
        $pdo->exec("DELETE FROM `{$table}`");
        fwrite(STDOUT, "  ✓ DELETE {$table}\n");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
} catch (\PDOException $e) {
    fwrite(STDERR, "\n[ERROR] " . $e->getMessage() . "\n");
    fwrite(STDERR, "Některé tabulky možná zůstaly nesmazané. Zkontrolujte ručně.\n");
    exit(3);
}

$elapsed = microtime(true) - $start;
fwrite(STDOUT, sprintf("\nHotovo za %.2f s. Smazáno %s řádků.\n",
    $elapsed, number_format($totalRows, 0, ',', ' ')));
fwrite(STDOUT, "Databáze je připravena na nový import.\n");
exit(0);

// ── Pomocná funkce ──────────────────────────────────────────────────
function tableExists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = :t'
    );
    $st->execute(['t' => $table]);
    return ((int) $st->fetchColumn()) > 0;
}
