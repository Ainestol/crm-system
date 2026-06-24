<?php
// e:\Snecinatripu\bin\migrate.php
//
// CLI tool pro správu DB migrací.
//
// Použití:
//   php bin/migrate.php status              — výpis spuštěných + čekajících
//   php bin/migrate.php up                   — spustí všechny čekající
//   php bin/migrate.php up --to=035          — spustí jen po verzi 035 (včetně)
//   php bin/migrate.php mark-applied         — JEDNORÁZOVĚ označí 001-029 jako spuštěné
//                                              (bootstrap pro existující DB)
//   php bin/migrate.php new <name>           — vygeneruje nový soubor 0XX_<name>.sql
//
// Konfigurace přes ENV proměnné (nebo edituj defaulty níže):
//   DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS

declare(strict_types=1);

// ─── Konfigurace ──────────────────────────────────────────────────
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$db   = getenv('DB_NAME') ?: 'crm';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

// Cesta k migracím (relativně od bin/)
$migrationsDir = realpath(__DIR__ . '/../sql/migrations');
if ($migrationsDir === false) {
    fwrite(STDERR, "Chyba: složka sql/migrations neexistuje\n");
    exit(1);
}

// ─── DB connection ────────────────────────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
        $user, $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
        ]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "DB chyba: " . $e->getMessage() . "\n");
    exit(1);
}

// ─── Helper funkce ────────────────────────────────────────────────

/**
 * Načte všechny .sql soubory v migrations složce a vrátí seznam
 * seřazený podle verze (prefix souboru).
 *
 * @return list<array{version: string, name: string, path: string}>
 */
function loadMigrationFiles(string $dir): array {
    $files = glob($dir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
    $list = [];
    foreach ($files as $path) {
        $basename = basename($path, '.sql');
        // Version = celé jméno souboru bez .sql (např. "029_contact_phones")
        // Zachycujeme i případy s duplicitními prefixy (003_caller_extras + 003_monthly_goals)
        $list[] = [
            'version' => $basename,
            'name'    => $basename,
            'path'    => $path,
        ];
    }
    // Seřazení podle version stringu (přirozené pořadí)
    usort($list, static fn($a, $b) => strnatcmp($a['version'], $b['version']));
    return $list;
}

/**
 * Vrátí mapu version → true pro všechny už spuštěné migrace.
 *
 * @return array<string, true>
 */
function loadAppliedMigrations(PDO $pdo): array {
    try {
        $st = $pdo->query('SELECT version FROM schema_migrations');
        $applied = [];
        foreach ($st->fetchAll() as $r) {
            $applied[(string) $r['version']] = true;
        }
        return $applied;
    } catch (PDOException $e) {
        // Tabulka schema_migrations ještě neexistuje — vrátíme prázdné
        if (str_contains($e->getMessage(), 'schema_migrations')) {
            return [];
        }
        throw $e;
    }
}

/**
 * Zaznamenat úspěšné spuštění migrace do schema_migrations.
 */
function recordMigration(PDO $pdo, string $version, string $name, int $executionMs, string $checksum, string $appliedBy): void {
    $pdo->prepare(
        'INSERT INTO schema_migrations (version, name, applied_at, applied_by, execution_ms, checksum)
         VALUES (:v, :n, NOW(3), :ab, :ms, :cs)'
    )->execute([
        'v'  => $version,
        'n'  => $name,
        'ab' => $appliedBy,
        'ms' => $executionMs,
        'cs' => $checksum,
    ]);
}

/**
 * Spustit jednu migraci. Při chybě hodí výjimku.
 */
function runMigration(PDO $pdo, array $mig, string $appliedBy): int {
    $sql = file_get_contents($mig['path']);
    if ($sql === false) {
        throw new RuntimeException("Nelze přečíst soubor: " . $mig['path']);
    }
    $checksum = hash('sha256', $sql);
    $start = microtime(true);

    // Spustíme jako multi-statement (umožňuje víc SQL v jednom souboru)
    $pdo->exec($sql);

    $ms = (int) ((microtime(true) - $start) * 1000);
    recordMigration($pdo, $mig['version'], $mig['name'], $ms, $checksum, $appliedBy);
    return $ms;
}

function appliedBy(): string {
    $user = getenv('USER') ?: getenv('USERNAME') ?: 'unknown';
    $host = gethostname() ?: 'unknown';
    return $user . '@' . $host;
}

// ─── Příkazy ──────────────────────────────────────────────────────

$cmd = $argv[1] ?? 'status';

switch ($cmd) {
    case 'status': {
        $files = loadMigrationFiles($migrationsDir);
        $applied = loadAppliedMigrations($pdo);

        $appliedList = [];
        $pendingList = [];
        foreach ($files as $f) {
            if (isset($applied[$f['version']])) {
                $appliedList[] = $f;
            } else {
                $pendingList[] = $f;
            }
        }

        echo "\n═══ Migrace ═══\n\n";
        echo "✓ Spuštěné: " . count($appliedList) . "\n";
        foreach ($appliedList as $f) {
            echo "    ✓ " . $f['version'] . "\n";
        }
        echo "\n";
        echo "✗ Čekající: " . count($pendingList) . "\n";
        foreach ($pendingList as $f) {
            echo "    ✗ " . $f['version'] . "\n";
        }
        echo "\n";
        if (empty($pendingList)) {
            echo "DB je aktuální, nic ke spuštění.\n";
        } else {
            echo "Pro spuštění: php bin/migrate.php up\n";
        }
        echo "\n";
        break;
    }

    case 'up': {
        // Parse --to=N option
        $toVersion = null;
        for ($i = 2; $i < $argc; $i++) {
            if (preg_match('/^--to=(.+)$/', $argv[$i], $m)) {
                $toVersion = $m[1];
            }
        }

        $files = loadMigrationFiles($migrationsDir);
        $applied = loadAppliedMigrations($pdo);

        $toRun = [];
        foreach ($files as $f) {
            if (isset($applied[$f['version']])) continue;
            if ($toVersion !== null && strnatcmp($f['version'], $toVersion) > 0) break;
            $toRun[] = $f;
        }

        if (empty($toRun)) {
            echo "Nic ke spuštění. DB je aktuální.\n";
            exit(0);
        }

        echo "Spouštím " . count($toRun) . " migrací...\n\n";
        $appliedBy = appliedBy();
        $okCount = 0;
        foreach ($toRun as $f) {
            echo "  ▸ " . $f['version'] . "... ";
            try {
                $ms = runMigration($pdo, $f, $appliedBy);
                echo "✓ ({$ms} ms)\n";
                $okCount++;
            } catch (\Throwable $e) {
                echo "✗ CHYBA\n";
                fwrite(STDERR, "\n  Chyba v migraci " . $f['version'] . ":\n  " . $e->getMessage() . "\n\n");
                fwrite(STDERR, "  Migrace zastavená. Oprav SQL a spusť znovu (úspěšné migrace už nezpustím).\n");
                exit(2);
            }
        }
        echo "\nHotovo. {$okCount} migrací aplikováno.\n";
        break;
    }

    case 'mark-applied': {
        // Jednorázový bootstrap: označí všechny migrace v sql/migrations/
        // jako už spuštěné, bez reálného spuštění.
        // Použije se pouze JEDNOU při zavedení trackeru do existující DB.
        echo "\n⚠ POZOR: Tento příkaz označí VŠECHNY existující soubory v sql/migrations/\n";
        echo "  jako spuštěné, BEZ reálného běhu SQL.\n";
        echo "  Použij JEN pokud DB už obsahuje schémata 001-029 a chceš zavést tracker.\n";
        echo "  Pokud váháš, nepouštěj a kontaktuj devs.\n\n";
        echo "Pokračovat? (napiš 'ano'): ";
        $line = trim((string) fgets(STDIN));
        if ($line !== 'ano') {
            echo "Zrušeno.\n";
            exit(0);
        }

        // Nejdřív zajistit že schema_migrations existuje (= spustit 030)
        $bootstrapFile = $migrationsDir . DIRECTORY_SEPARATOR . '030_schema_migrations.sql';
        if (is_file($bootstrapFile)) {
            $sql = file_get_contents($bootstrapFile) ?: '';
            try {
                $pdo->exec($sql);
                echo "✓ Tabulka schema_migrations vytvořena.\n";
            } catch (\Throwable $e) {
                fwrite(STDERR, "Chyba při bootstrapu schema_migrations: " . $e->getMessage() . "\n");
                exit(2);
            }
        }

        $files   = loadMigrationFiles($migrationsDir);
        $applied = loadAppliedMigrations($pdo);
        $appliedBy = appliedBy() . ' (bootstrap)';
        $marked = 0;
        foreach ($files as $f) {
            if (isset($applied[$f['version']])) continue;
            $sql = file_get_contents($f['path']) ?: '';
            $checksum = hash('sha256', $sql);
            recordMigration($pdo, $f['version'], $f['name'], 0, $checksum, $appliedBy);
            echo "  ✓ " . $f['version'] . " označeno jako spuštěné\n";
            $marked++;
        }
        echo "\nHotovo. {$marked} migrací označeno.\n";
        break;
    }

    case 'new': {
        $name = $argv[2] ?? null;
        if (!$name) {
            fwrite(STDERR, "Použití: php bin/migrate.php new <name>\n");
            fwrite(STDERR, "Příklad: php bin/migrate.php new tenants_foundation\n");
            exit(1);
        }
        // Najít nejvyšší verzi v existujících souborech
        $files = loadMigrationFiles($migrationsDir);
        $maxNum = 0;
        foreach ($files as $f) {
            if (preg_match('/^(\d+)_/', $f['version'], $m)) {
                $n = (int) $m[1];
                if ($n > $maxNum) $maxNum = $n;
            }
        }
        $newNum = str_pad((string) ($maxNum + 1), 3, '0', STR_PAD_LEFT);
        $cleanName = preg_replace('/[^a-z0-9_]/', '', strtolower($name)) ?: $name;
        $newFile = $migrationsDir . DIRECTORY_SEPARATOR . $newNum . '_' . $cleanName . '.sql';

        $template = "-- " . str_replace('\\', '/', $newFile) . "\n";
        $template .= "-- ════════════════════════════════════════════════════════════════════\n";
        $template .= "-- " . strtoupper(str_replace('_', ' ', $cleanName)) . "\n";
        $template .= "--\n";
        $template .= "-- Účel:\n";
        $template .= "--   (TODO: popis co migrace dělá a proč)\n";
        $template .= "-- ════════════════════════════════════════════════════════════════════\n";
        $template .= "\n";
        $template .= "SET NAMES utf8mb4;\n";
        $template .= "\n";
        $template .= "-- TODO: SQL příkazy\n";

        if (file_put_contents($newFile, $template) === false) {
            fwrite(STDERR, "Nelze zapsat: $newFile\n");
            exit(1);
        }
        echo "Vytvořeno: $newFile\n";
        break;
    }

    case 'help':
    case '--help':
    case '-h':
    default: {
        echo "\nPoužití: php bin/migrate.php <příkaz>\n\n";
        echo "Příkazy:\n";
        echo "  status              Výpis spuštěných + čekajících migrací\n";
        echo "  up                  Spustí všechny čekající migrace\n";
        echo "  up --to=035         Spustí jen po verzi 035 (včetně)\n";
        echo "  mark-applied        Označí existující migrace jako spuštěné (jednorázový bootstrap)\n";
        echo "  new <name>          Vygeneruje nový soubor migrace s číslem +1\n";
        echo "  help                Tato zpráva\n\n";
        echo "Konfigurace (ENV):\n";
        echo "  DB_HOST=127.0.0.1  DB_PORT=3306  DB_NAME=crm  DB_USER=root  DB_PASS=...\n\n";
        echo "Příklady:\n";
        echo "  php bin/migrate.php status\n";
        echo "  php bin/migrate.php up\n";
        echo "  php bin/migrate.php new add_column_xyz\n\n";
        break;
    }
}
