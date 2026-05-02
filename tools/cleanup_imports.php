<?php
// e:\Snecinatripu\tools\cleanup_imports.php
declare(strict_types=1);

/**
 * Cleanup script: smaže CSV soubory v storage/imports/ starší než RETENTION_DAYS.
 *
 * SPUŠTĚNÍ:
 *   php tools/cleanup_imports.php           — boží run (z příkazové řádky)
 *   php tools/cleanup_imports.php --dry-run — jen vypíše, co by smazal, nic nemaže
 *
 * NASAZENÍ NA CRON (Linux/Laragon Scheduled Tasks):
 *   Denně ve 3:00 ráno:
 *     0 3 * * *  /usr/bin/php /path/to/Snecinatripu/tools/cleanup_imports.php
 *
 * BEZPEČNOST:
 *   - Skript je CLI-only — odmítne se spustit přes web (kontrola PHP_SAPI).
 *   - Maže POUZE soubory, které matchují prefix import skriptu (imp_*.csv) —
 *     nikdy nesahá na soubory s jiným jménem.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'Tento skript je dostupný pouze z příkazové řádky.';
    exit(1);
}

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'constants.php';

// ── Konfigurace ──────────────────────────────────────────────────────
const RETENTION_DAYS = 30;        // Soubory starší než X dní se smažou
const FILE_PREFIX    = 'imp_';    // Maže pouze soubory s tímto prefixem
const ALLOWED_EXT    = ['csv'];   // Povolené přípony

$dryRun = in_array('--dry-run', $argv ?? [], true);

$importsDir = CRM_STORAGE_PATH . DIRECTORY_SEPARATOR . 'imports';
if (!is_dir($importsDir)) {
    fwrite(STDOUT, "[INFO] Adresář $importsDir neexistuje, není co mazat.\n");
    exit(0);
}

$cutoff = time() - (RETENTION_DAYS * 86400);
$deleted = 0;
$skipped = 0;
$totalBytes = 0;

$entries = scandir($importsDir);
if ($entries === false) {
    fwrite(STDERR, "[ERROR] Nelze přečíst $importsDir\n");
    exit(2);
}

foreach ($entries as $name) {
    if ($name === '.' || $name === '..') {
        continue;
    }
    $path = $importsDir . DIRECTORY_SEPARATOR . $name;
    if (!is_file($path)) {
        continue;
    }
    // Bezpečnostní filtr: prefix + povolená přípona
    if (!str_starts_with($name, FILE_PREFIX)) {
        $skipped++;
        continue;
    }
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXT, true)) {
        $skipped++;
        continue;
    }

    $mtime = filemtime($path);
    if ($mtime === false || $mtime > $cutoff) {
        continue; // mladší než limit
    }

    $size = filesize($path) ?: 0;
    $age  = (int) ((time() - $mtime) / 86400);

    if ($dryRun) {
        fwrite(STDOUT, sprintf("[DRY-RUN] Smazal bych: %s (%.1f KB, stáří %d dní)\n",
            $name, $size / 1024, $age));
    } else {
        if (@unlink($path)) {
            fwrite(STDOUT, sprintf("[OK] Smazáno: %s (%.1f KB, stáří %d dní)\n",
                $name, $size / 1024, $age));
            $deleted++;
            $totalBytes += $size;
        } else {
            fwrite(STDERR, "[ERROR] Nelze smazat: $name\n");
        }
    }
}

fwrite(STDOUT, sprintf("\nHotovo. Smazáno: %d souborů (%.1f KB). Přeskočeno: %d.\n",
    $deleted, $totalBytes / 1024, $skipped));
exit(0);
