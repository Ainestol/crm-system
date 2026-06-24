<?php
// e:\Snecinatripu\bin\fix-oz-notes-author.php
declare(strict_types=1);

/**
 * Jednorázová oprava: přidá sloupec `author_user_id` do `oz_contact_notes`
 * pokud chybí. Bezpečné spustit i opakovaně — ověřuje existenci sloupce.
 *
 * Použití:
 *   cd E:\Snecinatripu
 *   php bin/fix-oz-notes-author.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Spouštěj jen z CLI.\n");
    exit(1);
}

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';
$pdo = crm_pdo();

echo "════════════════════════════════════════════════════════\n";
echo "  Oprava sloupce oz_contact_notes.author_user_id\n";
echo "════════════════════════════════════════════════════════\n\n";

// 1) Existuje tabulka?
try {
    $t = $pdo->query("SELECT 1 FROM oz_contact_notes LIMIT 0");
    if (!$t) {
        echo "❌ Tabulka oz_contact_notes neexistuje. Nejdřív spusť migrace.\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "❌ Nelze otevřít tabulku oz_contact_notes: " . $e->getMessage() . "\n";
    exit(1);
}
echo "✓ Tabulka oz_contact_notes existuje.\n";

// 2) Existuje sloupec author_user_id?
$check = $pdo->prepare(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'oz_contact_notes'
       AND COLUMN_NAME = 'author_user_id'"
);
$check->execute();
$exists = (int) $check->fetchColumn() > 0;

if ($exists) {
    echo "✓ Sloupec author_user_id už existuje — nic není třeba dělat.\n";
    echo "\n  (Pokud aplikace pořád hlásí Unknown column, zkontroluj cache nebo restart server.)\n";
    exit(0);
}

echo "⚠ Sloupec author_user_id chybí. Přidávám…\n\n";

// 3) ADD COLUMN
try {
    $pdo->exec(
        "ALTER TABLE oz_contact_notes
         ADD COLUMN author_user_id BIGINT UNSIGNED NULL DEFAULT NULL
             COMMENT 'Kdo poznámku reálně napsal'
             AFTER oz_id"
    );
    echo "✓ Sloupec author_user_id přidán.\n";
} catch (\Throwable $e) {
    echo "❌ ALTER TABLE selhal: " . $e->getMessage() . "\n";
    exit(1);
}

// 4) ADD INDEX
try {
    $pdo->exec("ALTER TABLE oz_contact_notes ADD KEY idx_author_user_id (author_user_id)");
    echo "✓ Index idx_author_user_id přidán.\n";
} catch (\Throwable $e) {
    echo "⚠ Index nepřidán (možná už existuje): " . $e->getMessage() . "\n";
}

// 5) Backfill — zkopíruj oz_id do author_user_id pro existující řádky
try {
    $upd = $pdo->exec(
        "UPDATE oz_contact_notes
         SET author_user_id = oz_id
         WHERE author_user_id IS NULL"
    );
    echo sprintf("✓ Backfill: %d řádků dostalo author_user_id = oz_id (vlastník = autor).\n", (int) $upd);
} catch (\Throwable $e) {
    echo "⚠ Backfill přeskočen: " . $e->getMessage() . "\n";
}

echo "\n🎉 Hotovo! Workflow OZ teď funguje.\n";
echo "   Můžeš v UI zkusit Přijmout kontakt + Nabídka odeslána — projde.\n";
exit(0);
