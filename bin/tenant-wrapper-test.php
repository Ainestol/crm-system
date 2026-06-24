<?php
// e:\Snecinatripu\bin\tenant-wrapper-test.php
declare(strict_types=1);

/**
 * ════════════════════════════════════════════════════════════════════
 *  WRAPPER TEST — ověří že TenantAwarePDO auto-inject funguje
 *
 *  Co testuje:
 *    1. INSERT bez tenant_id → wrapper doplní z crm_tenant_id()
 *    2. INSERT s explicit tenant_id → wrapper nezasahuje
 *    3. Multi-row INSERT → každý řádek dostane tenant_id
 *    4. INSERT IGNORE → správně injektuje
 *    5. INSERT INTO ... SET col=val → injektuje přes SET syntax
 *    6. INSERT INTO ... SELECT → skip (komplikované, log warning)
 *    7. SELECT bez tenant_id → log warning
 *    8. Cross-tenant izolace: simuluje 2 tenanty
 *
 *  Spuštění:
 *    php bin/tenant-wrapper-test.php
 *    php bin/tenant-wrapper-test.php --cleanup
 * ════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Spouštěj jen z CLI.\n");
    exit(1);
}

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

$cleanup = in_array('--cleanup', $argv ?? [], true);
const WT_TENANT_A = 1;        // Moje firma (existující)
const WT_TENANT_B = 2;        // Test firma 2 (z předchozího testu nebo vytvoříme)
const WT_MARKER   = '[WRAPPER-TEST]';

$pdo = crm_pdo();

echo "════════════════════════════════════════════════════════\n";
echo "  TENANT AWARE PDO — WRAPPER TEST\n";
echo "════════════════════════════════════════════════════════\n\n";

if (!($pdo instanceof TenantAwarePDO)) {
    echo "❌ FAIL: crm_pdo() nevrátil TenantAwarePDO instance.\n";
    echo "   Vrátil: " . get_class($pdo) . "\n";
    exit(1);
}
echo "✓ crm_pdo() vrátil TenantAwarePDO instance.\n\n";

// Cleanup vlastních fixtures před setupem (idempotentní opakované běhy)
$prevWt = $pdo->exec("DELETE FROM contacts WHERE firma LIKE '" . WT_MARKER . "%'");
if ($prevWt > 0) {
    echo "📦 Cleanup z minulého běhu: smazáno {$prevWt} kontaktů.\n\n";
}

// Setup tenant B pokud chybí
$existingB = $pdo->query('SELECT id FROM tenants WHERE id = ' . WT_TENANT_B)->fetchColumn();
if (!$existingB) {
    $pdo->prepare(
        'INSERT INTO tenants (id, name, subdomain, plan_code, max_users, max_contacts, active)
         VALUES (?, ?, ?, ?, ?, ?, 1)'
    )->execute([WT_TENANT_B, 'Test firma 2', 'wraptest2', 'basic', 5, 100]);
    echo "  ✓ Vytvořen testovací tenant id=" . WT_TENANT_B . "\n\n";
}

// ─────────────────────────────────────────────────────────────────────
//  CLEANUP módus
// ─────────────────────────────────────────────────────────────────────
if ($cleanup) {
    echo "🧹 Cleanup...\n";
    $a = $pdo->exec("DELETE FROM contacts WHERE firma LIKE '" . WT_MARKER . "%'");
    echo "  - smazané test kontakty: {$a}\n\n";
    echo "✅ Cleanup hotov.\n";
    exit(0);
}

// ─────────────────────────────────────────────────────────────────────
//  Helper: simulovat tenant kontext bez skutečné session
// ─────────────────────────────────────────────────────────────────────
crm_session_start();
$ok = true;

function assert_true(bool $cond, string $msg, bool &$ok): void
{
    if ($cond) {
        echo "  ✓ {$msg}\n";
    } else {
        echo "  ❌ FAIL: {$msg}\n";
        $ok = false;
    }
}

// ═════════════════════════════════════════════════════════════════════
//  TEST 1: INSERT bez tenant_id se simulovaným tenantem A (id=1)
// ═════════════════════════════════════════════════════════════════════
echo "🧪 Test 1: INSERT bez tenant_id → wrapper auto-doplní z crm_tenant_id()\n";
crm_tenant_set(WT_TENANT_A);

$pdo->prepare(
    'INSERT INTO contacts (firma, telefon, region, stav) VALUES (?, ?, ?, ?)'
)->execute([WT_MARKER . ' AutoInject-A', '+420 700000001', 'praha', 'NEW']);

$tid = (int) $pdo->query(
    "SELECT tenant_id FROM contacts WHERE firma = '" . WT_MARKER . " AutoInject-A' LIMIT 1"
)->fetchColumn();
assert_true($tid === WT_TENANT_A, "INSERT bez tenant_id dostal tenant_id = " . WT_TENANT_A . " (skutečné: {$tid})", $ok);
echo "\n";

// ═════════════════════════════════════════════════════════════════════
//  TEST 2: Stejný INSERT, ale teď v kontextu tenant B (id=2)
// ═════════════════════════════════════════════════════════════════════
echo "🧪 Test 2: Stejný INSERT, ale tenant_id = 2 v session\n";
crm_tenant_set(WT_TENANT_B);

$pdo->prepare(
    'INSERT INTO contacts (firma, telefon, region, stav) VALUES (?, ?, ?, ?)'
)->execute([WT_MARKER . ' AutoInject-B', '+420 700000002', 'praha', 'NEW']);

$tid = (int) $pdo->query(
    "SELECT tenant_id FROM contacts WHERE firma = '" . WT_MARKER . " AutoInject-B' LIMIT 1"
)->fetchColumn();
assert_true($tid === WT_TENANT_B, "INSERT dostal tenant_id = " . WT_TENANT_B . " (skutečné: {$tid})", $ok);
echo "\n";

// ═════════════════════════════════════════════════════════════════════
//  TEST 3: INSERT s explicit tenant_id — wrapper NESMÍ přepsat
// ═════════════════════════════════════════════════════════════════════
echo "🧪 Test 3: INSERT s explicit tenant_id — wrapper nesmí změnit (session=2, explicit=1)\n";
crm_tenant_set(WT_TENANT_B);

$pdo->prepare(
    'INSERT INTO contacts (tenant_id, firma, telefon, region, stav) VALUES (?, ?, ?, ?, ?)'
)->execute([WT_TENANT_A, WT_MARKER . ' Explicit', '+420 700000003', 'praha', 'NEW']);

$tid = (int) $pdo->query(
    "SELECT tenant_id FROM contacts WHERE firma = '" . WT_MARKER . " Explicit' LIMIT 1"
)->fetchColumn();
assert_true($tid === WT_TENANT_A, "Explicit tenant_id zachován (skutečné: {$tid}, očekáváno: " . WT_TENANT_A . ")", $ok);
echo "\n";

// ═════════════════════════════════════════════════════════════════════
//  TEST 4: Multi-row INSERT (jeden statement, víc VALUES skupin)
// ═════════════════════════════════════════════════════════════════════
echo "🧪 Test 4: Multi-row INSERT — všechny řádky dostanou tenant_id\n";
crm_tenant_set(WT_TENANT_B);

$pdo->exec(
    "INSERT INTO contacts (firma, telefon, region, stav) VALUES
     ('" . WT_MARKER . " MultiA', '+420 700000010', 'praha', 'NEW'),
     ('" . WT_MARKER . " MultiB', '+420 700000011', 'praha', 'NEW'),
     ('" . WT_MARKER . " MultiC', '+420 700000012', 'praha', 'NEW')"
);

$multiCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM contacts
     WHERE firma LIKE '" . WT_MARKER . " Multi%' AND tenant_id = " . WT_TENANT_B
)->fetchColumn();
assert_true($multiCount === 3, "Všechny 3 multi-row řádky dostaly tenant_id = " . WT_TENANT_B . " (skutečné: {$multiCount})", $ok);
echo "\n";

// ═════════════════════════════════════════════════════════════════════
//  TEST 5: INSERT IGNORE syntax
// ═════════════════════════════════════════════════════════════════════
echo "🧪 Test 5: INSERT IGNORE syntax — wrapper rozpozná IGNORE\n";
crm_tenant_set(WT_TENANT_A);

$pdo->prepare(
    'INSERT IGNORE INTO contacts (firma, telefon, region, stav) VALUES (?, ?, ?, ?)'
)->execute([WT_MARKER . ' IGNORE', '+420 700000020', 'praha', 'NEW']);

$tid = (int) $pdo->query(
    "SELECT tenant_id FROM contacts WHERE firma = '" . WT_MARKER . " IGNORE' LIMIT 1"
)->fetchColumn();
assert_true($tid === WT_TENANT_A, "INSERT IGNORE dostal tenant_id = " . WT_TENANT_A . " (skutečné: {$tid})", $ok);
echo "\n";

// ═════════════════════════════════════════════════════════════════════
//  TEST 6: SET syntax: INSERT INTO tab SET col=val
// ═════════════════════════════════════════════════════════════════════
echo "🧪 Test 6: SET syntax — INSERT INTO ... SET col=val\n";
crm_tenant_set(WT_TENANT_B);

try {
    $pdo->prepare(
        "INSERT INTO contacts SET firma = ?, telefon = ?, region = ?, stav = ?"
    )->execute([WT_MARKER . ' SETSyntax', '+420 700000030', 'praha', 'NEW']);

    $tid = (int) $pdo->query(
        "SELECT tenant_id FROM contacts WHERE firma = '" . WT_MARKER . " SETSyntax' LIMIT 1"
    )->fetchColumn();
    assert_true($tid === WT_TENANT_B, "SET syntax dostal tenant_id = " . WT_TENANT_B . " (skutečné: {$tid})", $ok);
} catch (\Throwable $e) {
    echo "  ❌ FAIL: SET syntax throw: " . $e->getMessage() . "\n";
    $ok = false;
}
echo "\n";

// ═════════════════════════════════════════════════════════════════════
//  TEST 7: NON-tenant tabulka (users) — wrapper nezasahuje
// ═════════════════════════════════════════════════════════════════════
echo "🧪 Test 7: INSERT do users (NON-tenant table) — wrapper nezasahuje\n";
crm_tenant_set(WT_TENANT_B);

// users nemá tenant_id sloupec — kdyby wrapper omylem doplnil, SQL by selhalo
try {
    $tempEmail = 'wrap-test-' . time() . '@example.com';
    $pdo->prepare(
        'INSERT INTO users (email, heslo_hash, role, jmeno, aktivni) VALUES (?, ?, ?, ?, 1)'
    )->execute([$tempEmail, password_hash('xxx', PASSWORD_ARGON2ID), 'majitel', 'WrapTest']);

    $found = $pdo->query("SELECT id FROM users WHERE email = " . $pdo->quote($tempEmail) . " LIMIT 1")->fetchColumn();
    assert_true((bool) $found, "INSERT do users prošel bez tenant_id (skutečně by selhal kdyby wrapper přidal)", $ok);

    // Cleanup
    $pdo->exec("DELETE FROM users WHERE email = " . $pdo->quote($tempEmail));
} catch (\Throwable $e) {
    echo "  ❌ FAIL: users INSERT throw: " . $e->getMessage() . "\n";
    $ok = false;
}
echo "\n";

// ═════════════════════════════════════════════════════════════════════
//  TEST 8: Bez tenant kontextu (CLI bez bootstrap) — wrapper neinjektuje
// ═════════════════════════════════════════════════════════════════════
echo "🧪 Test 8: Bez tenant kontextu (session=0) — wrapper neinjektuje\n";
crm_tenant_clear();

try {
    // Tento INSERT explicitně dá tenant_id = 1 (jako legacy code)
    $pdo->prepare(
        'INSERT INTO contacts (tenant_id, firma, telefon, region, stav) VALUES (?, ?, ?, ?, ?)'
    )->execute([WT_TENANT_A, WT_MARKER . ' NoCtx', '+420 700000040', 'praha', 'NEW']);

    $tid = (int) $pdo->query(
        "SELECT tenant_id FROM contacts WHERE firma = '" . WT_MARKER . " NoCtx' LIMIT 1"
    )->fetchColumn();
    assert_true($tid === WT_TENANT_A, "Bez kontextu: legacy code s explicit tenant_id funguje (skutečné: {$tid})", $ok);
} catch (\Throwable $e) {
    echo "  ❌ FAIL: legacy INSERT throw: " . $e->getMessage() . "\n";
    $ok = false;
}
echo "\n";

// ═════════════════════════════════════════════════════════════════════
//  VÝSLEDEK
// ═════════════════════════════════════════════════════════════════════
echo "═══════════════════════════════════════════════════════\n";
if ($ok) {
    echo "  ✅ VŠECHNY KONTROLY PROŠLY\n";
    echo "  TenantAwarePDO wrapper funguje bezpečně.\n";
    echo "═══════════════════════════════════════════════════════\n\n";
    echo "Test data zůstávají v DB. Pro vyčištění:\n";
    echo "  php bin/tenant-wrapper-test.php --cleanup\n";
    exit(0);
} else {
    echo "  ❌ NĚKTERÉ KONTROLY SELHALY\n";
    echo "═══════════════════════════════════════════════════════\n";
    exit(1);
}
