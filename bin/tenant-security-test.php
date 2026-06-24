<?php
// e:\Snecinatripu\bin\tenant-security-test.php
declare(strict_types=1);

/**
 * ════════════════════════════════════════════════════════════════════
 *  TENANT SECURITY TEST — ověří izolaci dat mezi tenanty
 *
 *  Co dělá:
 *    1) Vytvoří dočasný 2. tenant "Test firma 2" (id=2, subdomain='test2')
 *    2) Vytvoří 1 testovacího usera "test2@example.com" v tenant 2
 *    3) Vloží 3 testovací kontakty s tenant_id=2
 *    4) Vyzkouší crm_tenant_where_sql() s tenant_id=1 a tenant_id=2
 *       — ověří že tenant 1 vidí jen své kontakty, tenant 2 jen své
 *    5) Vypíše výsledky
 *
 *  Co NEDĚLÁ:
 *    - Žádné UI změny
 *    - Žádné mazání existujících dat
 *    - Nepřihlašuje žádného usera
 *
 *  Cleanup (volitelný):
 *    php bin/tenant-security-test.php --cleanup
 *
 *  CLI usage:
 *    php bin/tenant-security-test.php           - vytvoří + otestuje
 *    php bin/tenant-security-test.php --cleanup - smaže test data
 *    php bin/tenant-security-test.php --only-test - jen test (musí už existovat)
 * ════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Spouštěj jen z CLI: php bin/tenant-security-test.php\n");
    exit(1);
}

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

$pdo = crm_pdo();

$cleanup = in_array('--cleanup', $argv ?? [], true);
$onlyTest = in_array('--only-test', $argv ?? [], true);

const TEST_TENANT_ID  = 2;
const TEST_SUBDOMAIN  = 'test2';
const TEST_EMAIL      = 'test2-tenant@example.com';
const TEST_MARKER     = '[TENANT-TEST]'; // marker pro identifikaci test dat v kontaktech

echo "════════════════════════════════════════════════════════\n";
echo "  TENANT SECURITY TEST\n";
echo "════════════════════════════════════════════════════════\n\n";

// ─────────────────────────────────────────────────────────────────────
//  CLEANUP módus
// ─────────────────────────────────────────────────────────────────────
if ($cleanup) {
    echo "🧹 Cleanup módus: mažu testovací data...\n\n";

    $deleted = $pdo->exec(
        "DELETE FROM contacts WHERE tenant_id = " . TEST_TENANT_ID
        . " AND firma LIKE '" . TEST_MARKER . "%'"
    );
    echo "  - smazané kontakty: " . (int) $deleted . "\n";

    // Smaž testového usera (user_tenants má FK, smaže se kaskádou)
    $stmt = $pdo->prepare('DELETE FROM users WHERE email = :e');
    $stmt->execute(['e' => TEST_EMAIL]);
    echo "  - smazaný user: " . $stmt->rowCount() . "\n";

    // Smaž testovací tenant (branding má FK, smaže se kaskádou)
    $stmt = $pdo->prepare('DELETE FROM tenants WHERE id = :id AND subdomain = :s');
    $stmt->execute(['id' => TEST_TENANT_ID, 's' => TEST_SUBDOMAIN]);
    echo "  - smazaný tenant: " . $stmt->rowCount() . "\n\n";

    echo "✅ Cleanup hotov.\n";
    exit(0);
}

// ─────────────────────────────────────────────────────────────────────
//  SETUP — vytvoření test tenanta + usera + dat
// ─────────────────────────────────────────────────────────────────────
if (!$onlyTest) {
    echo "📦 Setup: vytvářím 2. tenanta...\n";

    // Cleanup vlastních test fixtures před setupem (idempotentní opakované běhy)
    $prevCleanup = $pdo->exec(
        "DELETE FROM contacts WHERE firma LIKE '" . TEST_MARKER . "%'"
    );
    if ($prevCleanup > 0) {
        echo "  • Smazáno {$prevCleanup} test kontaktů z minulého běhu.\n";
    }

    // 1) Tenant 2
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO tenants
            (id, name, subdomain, plan_code, max_users, max_contacts, active)
         VALUES (:id, :n, :s, :p, :mu, :mc, 1)'
    );
    $stmt->execute([
        'id' => TEST_TENANT_ID,
        'n'  => 'Test firma 2',
        's'  => TEST_SUBDOMAIN,
        'p'  => 'basic',
        'mu' => 5,
        'mc' => 100,
    ]);
    echo "  ✓ tenants insert: " . ($stmt->rowCount() === 0 ? 'už existoval' : 'OK') . "\n";

    // 2) Tenant branding 2
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO tenant_branding
            (tenant_id, display_name, primary_color, accent_color)
         VALUES (:tid, :dn, :p, :a)'
    );
    $stmt->execute([
        'tid' => TEST_TENANT_ID,
        'dn'  => 'Test firma 2',
        'p'   => '#16a34a',
        'a'   => '#dc2626',
    ]);
    echo "  ✓ tenant_branding insert: " . ($stmt->rowCount() === 0 ? 'už existoval' : 'OK') . "\n";

    // 3) Test user
    $hash = password_hash('test123456', PASSWORD_ARGON2ID);
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO users (email, heslo_hash, role, jmeno, aktivni)
         VALUES (:e, :h, :r, :j, 1)'
    );
    $stmt->execute([
        'e' => TEST_EMAIL,
        'h' => $hash,
        'r' => 'majitel',
        'j' => 'Test Tenant2',
    ]);

    // Najít user_id
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
    $stmt->execute(['e' => TEST_EMAIL]);
    $testUserId = (int) $stmt->fetchColumn();
    if ($testUserId <= 0) {
        echo "  ❌ Test user se nepodařilo vytvořit.\n";
        exit(1);
    }
    echo "  ✓ test user id={$testUserId}\n";

    // 4) Mapping user_tenants
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO user_tenants (user_id, tenant_id, role, active)
         VALUES (:u, :t, :r, 1)'
    );
    $stmt->execute(['u' => $testUserId, 't' => TEST_TENANT_ID, 'r' => 'majitel']);
    echo "  ✓ user_tenants mapping OK\n";

    // 5) 3 testovací kontakty v tenant 2
    $stmt = $pdo->prepare(
        'INSERT INTO contacts (tenant_id, firma, telefon, region, stav)
         VALUES (:tid, :n, :t, :r, :s)'
    );
    $created = 0;
    foreach (['Alfa', 'Beta', 'Gamma'] as $i => $suffix) {
        $stmt->execute([
            'tid' => TEST_TENANT_ID,
            'n'   => TEST_MARKER . ' ' . $suffix,
            't'   => '+420 ' . (900000000 + $i),
            'r'   => 'praha',
            's'   => 'NEW',
        ]);
        $created++;
    }
    echo "  ✓ vloženy {$created} testovací kontakty s tenant_id=" . TEST_TENANT_ID . "\n\n";
}

// ─────────────────────────────────────────────────────────────────────
//  TEST — izolace přes crm_tenant_where_sql()
// ─────────────────────────────────────────────────────────────────────
echo "🧪 Test izolace tenantů přes crm_tenant_where_sql():\n\n";

$where = crm_tenant_where_sql('c');
$sqlIsolated = "SELECT COUNT(*) FROM contacts c WHERE 1=1 {$where}";
echo "   SQL:    {$sqlIsolated}\n\n";

$stmt = $pdo->prepare($sqlIsolated);

// Test 1: tenant_id = 1 (Moje firma)
$stmt->execute([':crm_tenant_id' => 1]);
$count1 = (int) $stmt->fetchColumn();

// Test 2: tenant_id = 2 (Test firma 2)
$stmt->execute([':crm_tenant_id' => TEST_TENANT_ID]);
$count2 = (int) $stmt->fetchColumn();

// Kontrola: total
$total = (int) $pdo->query('SELECT COUNT(*) FROM contacts')->fetchColumn();

printf("   Tenant 1 (Moje firma):     %5d kontaktů\n", $count1);
printf("   Tenant 2 (Test firma 2):   %5d kontaktů\n", $count2);
printf("   Total (bez filtru):        %5d kontaktů\n", $total);
echo "\n";

// ─────────────────────────────────────────────────────────────────────
//  ASSERTIONS — co MUSÍ platit
// ─────────────────────────────────────────────────────────────────────
echo "═══ Bezpečnostní kontroly ═══════════════════════════════════\n\n";

$ok = true;

// 1) Součet musí odpovídat totalu
if (($count1 + $count2) !== $total) {
    echo "❌ FAIL: Součet (tenant1 + tenant2) != total ($count1 + $count2 != $total)\n";
    echo "   → Některé kontakty mají jiný tenant_id! (NULL nebo >2)\n";
    $ok = false;
} else {
    echo "✓ Součet kontaktů per-tenant odpovídá totalu.\n";
}

// 2) Tenant 2 vidí přesně 3 NAŠE testovací kontakty s markerem [TENANT-TEST]
//    (jiné test fixtures z wrapper testů jsou OK, kontrolujeme jen naše)
$ourStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM contacts c
     WHERE c.firma LIKE :marker {$where}"
);
$ourStmt->execute([
    ':marker' => TEST_MARKER . '%',
    ':crm_tenant_id' => TEST_TENANT_ID,
]);
$ourCount = (int) $ourStmt->fetchColumn();
if ($ourCount !== 3) {
    echo "❌ FAIL: Tenant 2 by měl vidět 3 testovací [TENANT-TEST] kontakty, ale vidí {$ourCount}.\n";
    $ok = false;
} else {
    echo "✓ Tenant 2 vidí přesně 3 testovací [TENANT-TEST] kontakty.\n";
}

// 3) Tenant 1 NEMÁ vidět testovací kontakty z tenanta 2
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM contacts c
     WHERE c.firma LIKE :marker {$where}"
);
$stmt->execute([
    ':marker'        => TEST_MARKER . '%',
    ':crm_tenant_id' => 1,
]);
$leak = (int) $stmt->fetchColumn();
if ($leak > 0) {
    echo "❌ FAIL: Tenant 1 vidí {$leak} testovacích kontaktů (mělo by být 0)!\n";
    echo "   → DATA LEAK! Tenant filter selhal.\n";
    $ok = false;
} else {
    echo "✓ Tenant 1 nevidí žádné testovací kontakty z tenanta 2.\n";
}

// 4) Tenant 2 NEMÁ vidět žádné PRODUKČNÍ kontakty tenanta 1
// (= filtr na firma NOT LIKE '[%' vyhodí všechny test fixture data,
//  jako [TENANT-TEST], [WRAPPER-TEST] apod. — kontrolujeme jen reálná data.)
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM contacts c
     WHERE c.firma NOT LIKE '[%' {$where}"
);
$stmt->execute([
    ':crm_tenant_id' => TEST_TENANT_ID,
]);
$leak2 = (int) $stmt->fetchColumn();
if ($leak2 > 0) {
    echo "❌ FAIL: Tenant 2 vidí {$leak2} produkčních kontaktů z tenanta 1 (mělo by být 0)!\n";
    $ok = false;
} else {
    echo "✓ Tenant 2 nevidí žádné produkční kontakty z tenanta 1.\n";
}

// 5) user_tenants access check
$hasAccess1 = crm_tenant_user_has_access($pdo, 1, 1); // existující user → tenant 1
$noAccess   = crm_tenant_user_has_access($pdo, 1, TEST_TENANT_ID); // tenant 1 user → tenant 2
echo $hasAccess1
    ? "✓ user_id=1 má přístup do tenant_id=1.\n"
    : "❌ FAIL: user_id=1 nemá přístup do tenant_id=1!\n";
echo $noAccess
    ? "❌ FAIL: user_id=1 má přístup do tenant_id=2 (neměl by!)\n"
    : "✓ user_id=1 nemá přístup do tenant_id=2 (správně izolováno).\n";
if (!$hasAccess1 || $noAccess) {
    $ok = false;
}

echo "\n";

// ─────────────────────────────────────────────────────────────────────
//  VÝSLEDEK
// ─────────────────────────────────────────────────────────────────────
if ($ok) {
    echo "════════════════════════════════════════════════════════\n";
    echo "  ✅ VŠECHNY KONTROLY PROŠLY\n";
    echo "  Tenant izolace na úrovni DB filtru funguje.\n";
    echo "════════════════════════════════════════════════════════\n\n";
    echo "Test data zůstávají v DB. Pro vyčištění:\n";
    echo "  php bin/tenant-security-test.php --cleanup\n";
    exit(0);
} else {
    echo "════════════════════════════════════════════════════════\n";
    echo "  ❌ NĚKTERÉ KONTROLY SELHALY\n";
    echo "  Tenant izolace NENÍ bezpečná!\n";
    echo "════════════════════════════════════════════════════════\n";
    exit(1);
}
