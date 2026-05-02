<?php
// Jednorázový standalone reset hesel
// Spustit: php reset_pw_standalone.php (z C:\laragon\www nebo odkudkoli)
// Po použití SMAZAT!

$host = '127.0.0.1';
$port = 3306;
$db   = 'crm';
$user = 'root';
$pass = '';  // laragon default – pokud máš jiné heslo, uprav zde

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo "Chyba pripojeni: " . $e->getMessage() . "\n";
    exit(1);
}

$users = $pdo->query('SELECT id, jmeno, email, role FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

if (!$users) { echo "Zadni uzivatele.\n"; exit; }

$stmt = $pdo->prepare('UPDATE users SET heslo_hash = :h WHERE id = :id');

echo str_repeat('-', 55) . "\n";
echo "Reset hesla na 'password' pro vsechny uzivatele:\n";
echo str_repeat('-', 55) . "\n";

foreach ($users as $u) {
    $hash = password_hash('password', PASSWORD_ARGON2ID);
    $stmt->execute(['h' => $hash, 'id' => $u['id']]);
    printf("  [%d] %s (%s)\n", $u['id'], $u['email'], $u['role']);
}

echo str_repeat('-', 55) . "\n";
echo "Hotovo – " . count($users) . " uzivatelu aktualizovano.\n";
echo "Login: email + heslo 'password'\n";
echo "\n!! TENTO SOUBOR SMAZ !!\n";
