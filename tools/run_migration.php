<?php
// e:\Snecinatripu\tools\run_migration.php
// JEDNORÁZOVÝ migrace runner – spustit v prohlížeči JEDNOU, pak smazat.
// Přístup: jen z localhostu nebo s heslem.
declare(strict_types=1);

// Základní ochrana: jen localhost nebo správné heslo
$allowedIps = ['127.0.0.1', '::1'];
$secret     = 'migrace2026'; // změňte nebo odstraňte na produkci

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$pw = (string) ($_GET['key'] ?? '');

if (!in_array($ip, $allowedIps, true) && $pw !== $secret) {
    http_response_code(403);
    exit('Přístup odepřen. Přidejte ?key=migrace2026 do URL.');
}

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'constants.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php';

$pdo = crm_pdo();

$migrations = [
    '003_monthly_goals' => "
        CREATE TABLE IF NOT EXISTS `monthly_goals` (
          `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `target_wins`     INT UNSIGNED NOT NULL DEFAULT 150
            COMMENT 'Měsíční cíl počtu výher',
          `bonus1_at_pct`   TINYINT UNSIGNED NOT NULL DEFAULT 100
            COMMENT '% plnění cíle pro 1. bonus (100 = přesně cíl)',
          `bonus1_pct`      DECIMAL(5,2) NOT NULL DEFAULT 5.00
            COMMENT 'Bonus % za výhru nad 1. threshold (marginální)',
          `bonus2_at_pct`   TINYINT UNSIGNED NOT NULL DEFAULT 120
            COMMENT '% plnění cíle pro 2. bonus (120 = cíl + 20 %)',
          `bonus2_pct`      DECIMAL(5,2) NOT NULL DEFAULT 5.00
            COMMENT 'Druhý bonus % navíc',
          `valid_from`      DATE NOT NULL,
          `valid_to`        DATE NULL DEFAULT NULL,
          `created_at`      DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
          PRIMARY KEY (`id`),
          KEY `idx_monthly_goals_valid` (`valid_from`, `valid_to`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Měsíční cíle výher a bonusové pásy pro navolávačky'
    ",
];

$insertDefault = "
    INSERT INTO `monthly_goals` (target_wins, bonus1_at_pct, bonus1_pct, bonus2_at_pct, bonus2_pct, valid_from, valid_to)
    SELECT 150, 100, 5.00, 120, 5.00, CURDATE(), NULL
    FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM `monthly_goals` LIMIT 1)
";

echo '<pre style="font-family:monospace;font-size:14px;padding:20px;">';
echo "=== CRM Migration Runner ===\n\n";

$allOk = true;
foreach ($migrations as $name => $sql) {
    try {
        $pdo->exec(trim($sql));
        echo "✅ $name — OK\n";
    } catch (PDOException $e) {
        echo "❌ $name — CHYBA: " . $e->getMessage() . "\n";
        $allOk = false;
    }
}

// Vložit výchozí řádek pokud tabulka prázdná
try {
    $pdo->exec($insertDefault);
    $cnt = (int) $pdo->query('SELECT COUNT(*) FROM monthly_goals')->fetchColumn();
    echo "✅ Výchozí záznam — monthly_goals má $cnt řádek(ů)\n";
} catch (PDOException $e) {
    echo "⚠️  Výchozí insert — " . $e->getMessage() . "\n";
}

echo "\n";
if ($allOk) {
    echo "✅ Všechny migrace proběhly úspěšně.\n";
    echo "👉 Tento soubor nyní smažte nebo odstraňte z webroot.\n";
} else {
    echo "⚠️  Některé migrace selhaly — zkontrolujte chyby výše.\n";
}
echo '</pre>';
