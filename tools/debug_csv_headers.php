<?php
// Diagnostický skript – ukáže jaké sloupce CSV vidí PHP
// Spustit: php tools/debug_csv_headers.php cesta/k/souboru.csv
// Po použití smazat!

$file = $argv[1] ?? null;
if (!$file || !file_exists($file)) {
    echo "Pouziti: php tools/debug_csv_headers.php cesta/k/souboru.csv\n";
    exit(1);
}

$fh = fopen($file, 'rb');
$row = fgetcsv($fh, 0, ',', '"', '\\');
fclose($fh);

if (!$row) {
    // Zkus středník jako oddělovač (Excel CZ export)
    $fh = fopen($file, 'rb');
    $row = fgetcsv($fh, 0, ';', '"', '\\');
    fclose($fh);
    if ($row) echo "[INFO] Soubor pouziva strednik jako oddelovac!\n\n";
}

if (!$row) {
    echo "CSV je prazdne nebo neni platny CSV soubor.\n";
    exit(1);
}

echo "=== Nalezene sloupce ===\n";
foreach ($row as $i => $name) {
    // Stripnout BOM
    if ($i === 0) $name = preg_replace('/^\xEF\xBB\xBF/', '', $name);
    $raw = $name;
    // Normalizace (stejná logika jako v controlleru)
    $norm = mb_strtolower(trim($name), 'UTF-8');
    $from = ['á','č','ď','é','ě','í','ň','ó','ř','š','ť','ú','ů','ý','ž'];
    $to   = ['a','c','d','e','e','i','n','o','r','s','t','u','u','y','z'];
    $norm = str_replace($from, $to, $norm);
    $norm = (string) preg_replace('/[\s.\-\/\\\\]+/', '_', $norm);
    $norm = (string) preg_replace('/[^a-z0-9_]/', '', $norm);
    $norm = trim($norm, '_');
    printf("  [%d] raw='%s'  →  normalized='%s'\n", $i, $raw, $norm);
}
echo "\n";

// Klíčové sloupce
$normalized = [];
foreach ($row as $i => $name) {
    if ($i === 0) $name = preg_replace('/^\xEF\xBB\xBF/', '', $name);
    $norm = mb_strtolower(trim($name), 'UTF-8');
    $from = ['á','č','ď','é','ě','í','ň','ó','ř','š','ť','ú','ů','ý','ž'];
    $to   = ['a','c','d','e','e','i','n','o','r','s','t','u','u','y','z'];
    $norm = str_replace($from, $to, $norm);
    $norm = (string) preg_replace('/[\s.\-\/\\\\]+/', '_', $norm);
    $norm = (string) preg_replace('/[^a-z0-9_]/', '', $norm);
    $normalized[] = trim($norm, '_');
}

$checks = [
    'firma'        => ['firma', 'nazev_firmy', 'nazev firmy', 'spolecnost', 'company'],
    'kraj/region'  => ['kraj', 'region', 'mesto', 'city'],
    'ico'          => ['ico', 'ic', 'ic_'],
    'telefon'      => ['telefon', 'tel', 'mobil', 'phone'],
    'email'        => ['email', 'e_mail', 'mail'],
    'operator'     => ['operator'],
    'prilezitost'  => ['prilezitost', 'prilez', 'opportunity', 'produkt'],
];

echo "=== Kontrola povinnych/dulezitych sloupcu ===\n";
foreach ($checks as $label => $variants) {
    $found = null;
    foreach ($variants as $v) {
        if (in_array($v, $normalized, true)) { $found = $v; break; }
    }
    printf("  %-15s %s\n", $label.':', $found ? "OK  (='$found')" : "CHYBI!");
}
echo "\n";
