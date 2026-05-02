<?php
// e:\Snecinatripu\tools\reset_passwords.php
// Jednorázový skript: nastaví všem uživatelům heslo "password" (Argon2id)
// Spuštění: php tools/reset_passwords.php
// NIKDY nenechávat na produkci přístupné přes web!

declare(strict_types=1);

$cfg = require dirname(__DIR__) . '/config/db.php';

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $cfg['host'],
    $cfg['port'],
    $cfg['database'],
    $cfg['charset']
);

try {
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], $cfg['options']);
} catch (PDOException $e) {
    echo "Chyba připojení: " . $e->getMessage() . "\n";
    exit(1);
}

// Argon2id hash hesla "password" — generuje se zde, aby byl unikátní salt
$hash = password_hash('password', PASSWORD_ARGON2ID);

// Načti všechny uživatele před změnou
$users = $pdo->query('SELECT id, jmeno, email, role FROM users ORDER BY id ASC')
             ->fetchAll(PDO::FETCH_ASSOC);

if (empty($users)) {
    echo "Žádní uživatelé nenalezeni.\n";
    exit(0);
}

echo str_repeat('-', 60) . "\n";
echo "Aktualizuji hesla – nové heslo: 'password'\n";
echo str_repeat('-', 60) . "\n";

// Hromadný UPDATE (všichni dostanou stejný hash)
$stmt = $pdo->prepare('UPDATE users SET heslo_hash = :hash WHERE id = :id');

foreach ($users as $u) {
    // Každý dostane vlastní hash (jiný salt, stejné heslo)
    $userHash = password_hash('password', PASSWORD_ARGON2ID);
    $stmt->execute(['hash' => $userHash, 'id' => $u['id']]);
    printf("  [%3d] %-30s %-35s (%s)\n", $u['id'], $u['jmeno'], $u['email'], $u['role']);
}

echo str_repeat('-', 60) . "\n";
echo "Hotovo – aktualizováno " . count($users) . " uživatelů.\n";
echo "Přihlašovací údaje: e-mail + heslo 'password'\n";
