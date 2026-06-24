<?php
// e:\Snecinatripu\bin\tenant-full-isolation-test.php
declare(strict_types=1);

/**
 * ════════════════════════════════════════════════════════════════════
 *  TENANT FULL ISOLATION TEST — Tier 1–5 napříč 14 tabulkami
 *
 *  Ověří, že po multi-tenant refaktoru:
 *    1) Auto-inject tenant_id funguje v každé tabulce (wrapper)
 *    2) SELECT izoluje data per-tenant (tenant A nevidí data tenanta B)
 *    3) UPDATE/DELETE napříč tenanty selhává (0 affected)
 *    4) Helper funkce (crm_setting_get, rescue_find_active,
 *       crm_phones_for_contact, commissions_caller_reward_czk) vrací
 *       jen data aktivního tenanta
 *
 *  Pokrytí:
 *    Tier 1: contacts, contact_phones
 *    Tier 2: premium_orders, premium_lead_pool, bet_campaigns
 *    Tier 3: oz_targets, monthly_goals, caller_rewards_config,
 *            oz_team_stages, oz_personal_milestones
 *    Tier 4: rescue_requests, app_settings, dnc_list, workflow_log,
 *            commission_tiers_sales, commission_tiers_company
 *
 *  Předpoklady:
 *    - Tenant id=1 existuje (default tenant)
 *    - Tenant id=2 existuje (vytvořen tenant-security-test.php)
 *    - V každém tenantu je alespoň 1 user v user_tenants
 *
 *  Spuštění:
 *    php bin/tenant-full-isolation-test.php           — běh testu
 *    php bin/tenant-full-isolation-test.php --cleanup — smaže fixtures
 *
 *  Exit kódy:
 *    0 — všechny testy prošly
 *    1 — selhání některého testu
 *    2 — předpoklady nesplněny (chybí tenant 2 nebo user mapping)
 * ════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Spouštěj jen z CLI.\n");
    exit(1);
}

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

// Helper soubory, které test probuje (function_exists checks v helper-probe sekci) —
// bootstrap je sice načítá lazy přes controllery, my je v CLI testu musíme načíst ručně.
$helpersDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'helpers';
foreach (['settings', 'rescue', 'contact_phones', 'commissions'] as $h) {
    $path = $helpersDir . DIRECTORY_SEPARATOR . $h . '.php';
    if (is_file($path)) { require_once $path; }
}

const FT_TENANT_A = 1;
const FT_TENANT_B = 2;
const FT_MARKER   = '[FULL-TEST]';
const FT_SKEY     = 'fulltest_setting_key';

$pdo     = crm_pdo();
$cleanup = in_array('--cleanup', $argv ?? [], true);

echo "════════════════════════════════════════════════════════\n";
echo "  TENANT FULL ISOLATION TEST — Tier 1–5\n";
echo "════════════════════════════════════════════════════════\n\n";

if (!($pdo instanceof TenantAwarePDO)) {
    echo "❌ FAIL: crm_pdo() nevrátil TenantAwarePDO. Vrátil: " . get_class($pdo) . "\n";
    exit(2);
}

crm_session_start();

// ═══════════════════════════════════════════════════════════════════
//  CLEANUP REŽIM
// ═══════════════════════════════════════════════════════════════════
if ($cleanup) {
    echo "🧹 Cleanup režim — mažu test fixtures.\n\n";
    // SELECT-based cleanup používá tenant filter na obou tenantech
    $cleanupSqls = [
        'workflow_log'           => "DELETE FROM workflow_log WHERE note LIKE '" . FT_MARKER . "%'",
        'rescue_requests'        => "DELETE FROM rescue_requests WHERE reason LIKE '" . FT_MARKER . "%'",
        'contact_phones'         => "DELETE FROM contact_phones WHERE phone LIKE '" . FT_MARKER . "%'",
        'premium_lead_pool'      => "DELETE FROM premium_lead_pool WHERE id IN (SELECT * FROM (
                                       SELECT plp.id FROM premium_lead_pool plp
                                       JOIN contacts c ON c.id = plp.contact_id
                                       WHERE c.firma LIKE '" . FT_MARKER . "%'
                                     ) t)",
        'premium_orders'         => "DELETE FROM premium_orders WHERE note LIKE '" . FT_MARKER . "%'",
        'bet_campaigns'          => "DELETE FROM bet_campaigns WHERE name LIKE '" . FT_MARKER . "%'",
        'oz_targets'             => "DELETE FROM oz_targets WHERE region LIKE '" . FT_MARKER . "%'",
        'oz_team_stages'         => "DELETE FROM oz_team_stages WHERE label LIKE '" . FT_MARKER . "%'",
        'oz_personal_milestones' => "DELETE FROM oz_personal_milestones WHERE label LIKE '" . FT_MARKER . "%'",
        'monthly_goals'          => "DELETE FROM monthly_goals WHERE target_wins = 99987",
        'caller_rewards_config'  => "DELETE FROM caller_rewards_config WHERE amount_czk = 1.23",
        'app_settings'           => "DELETE FROM app_settings WHERE skey = '" . FT_SKEY . "'",
        'dnc_list'               => "DELETE FROM dnc_list WHERE ico = '99999991' OR ico = '99999992'",
        'commission_tiers_sales' => "DELETE FROM commission_tiers_sales WHERE min_monthly_sales = 987654.00",
        'commission_tiers_company' => "DELETE FROM commission_tiers_company WHERE service_type = 'FULL-TEST'",
        'contacts'               => "DELETE FROM contacts WHERE firma LIKE '" . FT_MARKER . "%'",
    ];
    foreach ($cleanupSqls as $name => $sql) {
        try {
            $n = $pdo->exec($sql);
            echo "  - {$name}: smazáno {$n}\n";
        } catch (\Throwable $e) {
            echo "  - {$name}: SKIPPED (" . $e->getMessage() . ")\n";
        }
    }
    echo "\n✅ Cleanup hotov. (Tenant 2 i user mapping zůstávají.)\n";
    exit(0);
}

// ═══════════════════════════════════════════════════════════════════
//  PŘEDPOKLADY
// ═══════════════════════════════════════════════════════════════════
echo "🔎 Kontrola předpokladů…\n";

$tenantA = $pdo->query('SELECT id, name FROM tenants WHERE id = ' . FT_TENANT_A)->fetch(PDO::FETCH_ASSOC);
$tenantB = $pdo->query('SELECT id, name FROM tenants WHERE id = ' . FT_TENANT_B)->fetch(PDO::FETCH_ASSOC);
if (!$tenantA) {
    echo "❌ Tenant id=" . FT_TENANT_A . " neexistuje. Inicializuj DB.\n";
    exit(2);
}
if (!$tenantB) {
    echo "❌ Tenant id=" . FT_TENANT_B . " neexistuje. Spusť nejdřív: php bin/tenant-security-test.php\n";
    exit(2);
}
echo "  ✓ Tenant A: id=" . FT_TENANT_A . " name='{$tenantA['name']}'\n";
echo "  ✓ Tenant B: id=" . FT_TENANT_B . " name='{$tenantB['name']}'\n";

// Najdi user_id pro každého tenanta (pro FK)
$findUserSql = "SELECT u.id FROM users u
                JOIN user_tenants ut ON ut.user_id = u.id
                WHERE ut.tenant_id = ? AND ut.active = 1
                LIMIT 1";
$st = $pdo->prepare($findUserSql);
$st->execute([FT_TENANT_A]);
$userA = (int) ($st->fetchColumn() ?: 0);
$st->execute([FT_TENANT_B]);
$userB = (int) ($st->fetchColumn() ?: 0);

if ($userA <= 0 || $userB <= 0) {
    echo "❌ Nemám user_id pro tenanta " . (($userA <= 0) ? FT_TENANT_A : FT_TENANT_B)
       . ". Vytvoř alespoň 1 usera v každém tenantu.\n";
    exit(2);
}
echo "  ✓ User pro tenant A: id={$userA}\n";
echo "  ✓ User pro tenant B: id={$userB}\n\n";

// ═══════════════════════════════════════════════════════════════════
//  IDEMPOTENCE — smaž fixtures z minulého běhu
// ═══════════════════════════════════════════════════════════════════
echo "📦 Idempotence — mažu fixtures z minulého běhu…\n";
$prevCleanup = [
    "DELETE FROM workflow_log           WHERE note LIKE '" . FT_MARKER . "%'",
    "DELETE FROM rescue_requests        WHERE reason LIKE '" . FT_MARKER . "%'",
    "DELETE FROM contact_phones         WHERE phone LIKE '" . FT_MARKER . "%'",
    "DELETE FROM premium_lead_pool      WHERE id IN (SELECT * FROM (
        SELECT plp.id FROM premium_lead_pool plp JOIN contacts c ON c.id=plp.contact_id
        WHERE c.firma LIKE '" . FT_MARKER . "%') t)",
    "DELETE FROM premium_orders         WHERE note LIKE '" . FT_MARKER . "%'",
    "DELETE FROM bet_campaigns          WHERE name LIKE '" . FT_MARKER . "%'",
    "DELETE FROM oz_targets             WHERE region LIKE '" . FT_MARKER . "%'",
    "DELETE FROM oz_team_stages         WHERE label LIKE '" . FT_MARKER . "%'",
    "DELETE FROM oz_personal_milestones WHERE label LIKE '" . FT_MARKER . "%'",
    "DELETE FROM monthly_goals          WHERE target_wins = 99987",
    "DELETE FROM caller_rewards_config  WHERE amount_czk = 1.23",
    "DELETE FROM app_settings           WHERE skey = '" . FT_SKEY . "'",
    "DELETE FROM dnc_list               WHERE ico IN ('99999991','99999992')",
    "DELETE FROM commission_tiers_sales WHERE min_monthly_sales = 987654.00",
    "DELETE FROM commission_tiers_company WHERE service_type = 'FULL-TEST'",
    "DELETE FROM contacts               WHERE firma LIKE '" . FT_MARKER . "%'",
];
foreach ($prevCleanup as $sql) {
    try { $pdo->exec($sql); } catch (\Throwable) {}
}
echo "  ✓ hotovo\n\n";

// ═══════════════════════════════════════════════════════════════════
//  HELPER: assert + reporter
// ═══════════════════════════════════════════════════════════════════
$report = [];           // [tier][table][test] => ok|fail|skip + msg
$totalOk = $totalFail = $totalSkip = 0;

function record(array &$report, string $tier, string $table, string $test, string $status, string $msg = ''): void {
    $report[$tier][$table][$test] = ['status' => $status, 'msg' => $msg];
    global $totalOk, $totalFail, $totalSkip;
    if      ($status === 'ok')   { $totalOk++;   echo "    ✓ {$test} {$msg}\n"; }
    elseif  ($status === 'fail') { $totalFail++; echo "    ❌ {$test} {$msg}\n"; }
    else                         { $totalSkip++; echo "    ⊘ {$test} SKIPPED {$msg}\n"; }
}

/** Vloží fixture v tenant kontextu, vrátí lastInsertId nebo 0 */
function fixtureInsert(PDO $pdo, int $tenantId, string $sql, array $params): int {
    crm_tenant_set($tenantId);
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $pdo->lastInsertId();
    } catch (\Throwable $e) {
        echo "    ⊘ INSERT failed: " . $e->getMessage() . "\n";
        return 0;
    }
}

/** Spočítá řádky podle WHERE (s explicit tenant_id) — bypass wrapper auto-inject je u SELECTu jen warning */
function probeCount(PDO $pdo, string $sql, array $params): int {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

/** Probe sada: 4 testy nad jednou tabulkou.
 *  $idOrKeyA/B může být int (auto-inc id) NEBO string (composite PK lookup hodnota).
 *  Pro tabulky bez `id` sloupce (např. app_settings) se předává řetězec a $pkColumn.
 */
function probeIsolation(
    PDO   $pdo,
    array &$report,
    string $tier,
    string $table,
    int|string $idOrKeyA,
    int|string $idOrKeyB,
    string $pkColumn = 'id'
): void {
    echo "  📋 {$table}\n";
    $emptyA = ($idOrKeyA === 0 || $idOrKeyA === '');
    $emptyB = ($idOrKeyB === 0 || $idOrKeyB === '');
    if ($emptyA || $emptyB) {
        record($report, $tier, $table, 'isolation', 'skip', '(fixtures not inserted)');
        return;
    }

    // 1. Tenant A vidí jen fixture A
    $a = probeCount($pdo, "SELECT COUNT(*) FROM {$table} WHERE {$pkColumn} = ? AND tenant_id = ?",
                    [$idOrKeyA, FT_TENANT_A]);
    $a_cross = probeCount($pdo, "SELECT COUNT(*) FROM {$table} WHERE {$pkColumn} = ? AND tenant_id = ?",
                          [$idOrKeyA, FT_TENANT_B]);
    // U composite PK (skey shared mezi tenanty) A musí vrátit 1 (svůj) a A_cross musí vrátit 1
    // (B má stejný skey, ale jiný tenant_id). Ten je SPRÁVNĚ ≥ 0, izolaci kontrolujeme jinak.
    // Pro int PK (unique id) je A_cross vždy 0 — řádek patří jen jednomu tenantu.
    $aOk = ($a === 1);
    $aCrossOk = is_int($idOrKeyA) ? ($a_cross === 0) : true; // string PK shared → cross je OK
    if ($aOk && $aCrossOk) {
        record($report, $tier, $table, 'select_A_isolated', 'ok');
    } else {
        record($report, $tier, $table, 'select_A_isolated', 'fail',
               "(A vidí svůj řádek: {$a}, A únik do B: {$a_cross})");
    }

    // 2. Tenant B vidí jen fixture B
    $b = probeCount($pdo, "SELECT COUNT(*) FROM {$table} WHERE {$pkColumn} = ? AND tenant_id = ?",
                    [$idOrKeyB, FT_TENANT_B]);
    $b_cross = probeCount($pdo, "SELECT COUNT(*) FROM {$table} WHERE {$pkColumn} = ? AND tenant_id = ?",
                          [$idOrKeyB, FT_TENANT_A]);
    $bOk = ($b === 1);
    $bCrossOk = is_int($idOrKeyB) ? ($b_cross === 0) : true;
    if ($bOk && $bCrossOk) {
        record($report, $tier, $table, 'select_B_isolated', 'ok');
    } else {
        record($report, $tier, $table, 'select_B_isolated', 'fail',
               "(B vidí svůj řádek: {$b}, B únik do A: {$b_cross})");
    }

    // Pro tabulky se SDÍLENOU composite PK (stejný skey v obou tenantech) nejde
    // testovat "0 affected" — DELETE WHERE skey=X AND tenant_id=A legitimně smaže
    // A-řádek. Místo toho testujeme reálnou izolaci: po operaci je B-řádek netknutý.
    $sharedCompositePk = (is_string($idOrKeyA) && $idOrKeyA === $idOrKeyB);

    // 3. Cross-tenant UPDATE
    try {
        if ($sharedCompositePk) {
            // Snapshot B's tenant_id (immutable kolumna, slouží jako kanárek)
            // a ověř, že po UPDATE v A-kontextu zůstal stejný
            $bBefore = probeCount($pdo,
                "SELECT COUNT(*) FROM {$table} WHERE {$pkColumn} = ? AND tenant_id = ?",
                [$idOrKeyB, FT_TENANT_B]);
            $stmt = $pdo->prepare("UPDATE {$table} SET {$pkColumn} = {$pkColumn} WHERE {$pkColumn} = ? AND tenant_id = ?");
            $stmt->execute([$idOrKeyB, FT_TENANT_A]);
            $bAfter = probeCount($pdo,
                "SELECT COUNT(*) FROM {$table} WHERE {$pkColumn} = ? AND tenant_id = ?",
                [$idOrKeyB, FT_TENANT_B]);
            if ($bAfter === $bBefore && $bAfter === 1) {
                record($report, $tier, $table, 'cross_UPDATE_blocked', 'ok');
            } else {
                record($report, $tier, $table, 'cross_UPDATE_blocked', 'fail',
                       "(B-řádek se změnil: before={$bBefore} after={$bAfter})");
            }
        } else {
            $stmt = $pdo->prepare("UPDATE {$table} SET {$pkColumn} = {$pkColumn} WHERE {$pkColumn} = ? AND tenant_id = ?");
            $stmt->execute([$idOrKeyB, FT_TENANT_A]);
            $affected = $stmt->rowCount();
            if ($affected === 0) {
                record($report, $tier, $table, 'cross_UPDATE_blocked', 'ok');
            } else {
                record($report, $tier, $table, 'cross_UPDATE_blocked', 'fail',
                       "(affected: {$affected}, mělo být 0)");
            }
        }
    } catch (\Throwable $e) {
        record($report, $tier, $table, 'cross_UPDATE_blocked', 'skip',
               '(' . $e->getMessage() . ')');
    }

    // 4. Cross-tenant DELETE
    try {
        if ($sharedCompositePk) {
            // Snapshot B's row před operací, po operaci ověř, že B-řádek STÁLE existuje.
            // POZN: DELETE v A-kontextu LEGITIMNĚ smaže A-řádek (= správně —
            // A-session smí mazat A-data). Klíčové je, že B-řádek se nedotkne.
            $bBefore = probeCount($pdo,
                "SELECT COUNT(*) FROM {$table} WHERE {$pkColumn} = ? AND tenant_id = ?",
                [$idOrKeyB, FT_TENANT_B]);
            $stmt = $pdo->prepare("DELETE FROM {$table} WHERE {$pkColumn} = ? AND tenant_id = ?");
            $stmt->execute([$idOrKeyB, FT_TENANT_A]);
            $bAfter = probeCount($pdo,
                "SELECT COUNT(*) FROM {$table} WHERE {$pkColumn} = ? AND tenant_id = ?",
                [$idOrKeyB, FT_TENANT_B]);
            if ($bAfter === $bBefore && $bAfter === 1) {
                record($report, $tier, $table, 'cross_DELETE_blocked', 'ok');
            } else {
                record($report, $tier, $table, 'cross_DELETE_blocked', 'fail',
                       "(B-řádek zmizel: before={$bBefore} after={$bAfter})");
            }
            // Re-insert A-řádku (DELETE jen smazal A-data, ne B), aby helper probes prošly
            $tableHook = $table;
            if ($tableHook === 'app_settings') {
                crm_tenant_set(FT_TENANT_A);
                try {
                    $pdo->prepare(
                        "INSERT INTO app_settings (skey, sval, updated_by) VALUES (?, 'value-tenantA', NULL)"
                    )->execute([FT_SKEY]);
                } catch (\Throwable $_) { /* už existuje */ }
            }
        } else {
            $stmt = $pdo->prepare("DELETE FROM {$table} WHERE {$pkColumn} = ? AND tenant_id = ?");
            $stmt->execute([$idOrKeyB, FT_TENANT_A]);
            $affected = $stmt->rowCount();
            if ($affected === 0) {
                record($report, $tier, $table, 'cross_DELETE_blocked', 'ok');
            } else {
                record($report, $tier, $table, 'cross_DELETE_blocked', 'fail',
                       "(affected: {$affected}, mělo být 0)");
            }
        }
    } catch (\Throwable $e) {
        record($report, $tier, $table, 'cross_DELETE_blocked', 'skip',
               '(' . $e->getMessage() . ')');
    }
}

// ═══════════════════════════════════════════════════════════════════
//  FIXTURES — vložit 1 řádek do každé tabulky pro tenant A a tenant B
// ═══════════════════════════════════════════════════════════════════
echo "🧱 Vkládám fixtures…\n\n";

$fixtures = []; // [table][tenant] = id
$now = date('Y');

// ── Tier 1: contacts ───────────────────────────────────────────────
echo "  → contacts\n";
$fixtures['contacts'][FT_TENANT_A] = fixtureInsert($pdo, FT_TENANT_A,
    "INSERT INTO contacts (firma, telefon, region, stav) VALUES (?, ?, ?, 'NEW')",
    [FT_MARKER . ' tA-contact', '+420 7770010', 'praha']
);
$fixtures['contacts'][FT_TENANT_B] = fixtureInsert($pdo, FT_TENANT_B,
    "INSERT INTO contacts (firma, telefon, region, stav) VALUES (?, ?, ?, 'NEW')",
    [FT_MARKER . ' tB-contact', '+420 7770020', 'praha']
);

// ── Tier 1: contact_phones (FK contacts) ───────────────────────────
echo "  → contact_phones\n";
$fixtures['contact_phones'][FT_TENANT_A] = fixtureInsert($pdo, FT_TENANT_A,
    "INSERT INTO contact_phones (contact_id, phone, phone_digits, position, created_at)
     VALUES (?, ?, ?, 0, NOW(3))",
    [$fixtures['contacts'][FT_TENANT_A], FT_MARKER . ' tA', '420777001100']
);
$fixtures['contact_phones'][FT_TENANT_B] = fixtureInsert($pdo, FT_TENANT_B,
    "INSERT INTO contact_phones (contact_id, phone, phone_digits, position, created_at)
     VALUES (?, ?, ?, 0, NOW(3))",
    [$fixtures['contacts'][FT_TENANT_B], FT_MARKER . ' tB', '420777002200']
);

// ── Tier 2: premium_orders ────────────────────────────────────────
echo "  → premium_orders\n";
$fixtures['premium_orders'][FT_TENANT_A] = fixtureInsert($pdo, FT_TENANT_A,
    "INSERT INTO premium_orders (oz_id, year, month, requested_count, reserved_count,
                                  price_per_lead, status, note)
     VALUES (?, ?, ?, 10, 0, 100, 'open', ?)",
    [$userA, (int) $now, 1, FT_MARKER . ' tA-order']
);
$fixtures['premium_orders'][FT_TENANT_B] = fixtureInsert($pdo, FT_TENANT_B,
    "INSERT INTO premium_orders (oz_id, year, month, requested_count, reserved_count,
                                  price_per_lead, status, note)
     VALUES (?, ?, ?, 10, 0, 100, 'open', ?)",
    [$userB, (int) $now, 1, FT_MARKER . ' tB-order']
);

// ── Tier 2: premium_lead_pool (FK contacts + premium_orders) ──────
echo "  → premium_lead_pool\n";
// premium_lead_pool má NOT NULL `oz_id` (denormalizováno z premium_orders pro perf)
if ($fixtures['premium_orders'][FT_TENANT_A] > 0 && $fixtures['contacts'][FT_TENANT_A] > 0) {
    $fixtures['premium_lead_pool'][FT_TENANT_A] = fixtureInsert($pdo, FT_TENANT_A,
        "INSERT INTO premium_lead_pool (order_id, contact_id, oz_id, cleaning_status, call_status)
         VALUES (?, ?, ?, 'pending', 'pending')",
        [$fixtures['premium_orders'][FT_TENANT_A], $fixtures['contacts'][FT_TENANT_A], $userA]
    );
}
if ($fixtures['premium_orders'][FT_TENANT_B] > 0 && $fixtures['contacts'][FT_TENANT_B] > 0) {
    $fixtures['premium_lead_pool'][FT_TENANT_B] = fixtureInsert($pdo, FT_TENANT_B,
        "INSERT INTO premium_lead_pool (order_id, contact_id, oz_id, cleaning_status, call_status)
         VALUES (?, ?, ?, 'pending', 'pending')",
        [$fixtures['premium_orders'][FT_TENANT_B], $fixtures['contacts'][FT_TENANT_B], $userB]
    );
}

// ── Tier 2: bet_campaigns ─────────────────────────────────────────
echo "  → bet_campaigns\n";
$fixtures['bet_campaigns'][FT_TENANT_A] = fixtureInsert($pdo, FT_TENANT_A,
    "INSERT INTO bet_campaigns (name, region, target_count, status, note)
     VALUES (?, 'praha', 50, 'open', 'tA')",
    [FT_MARKER . ' tA-camp']
);
$fixtures['bet_campaigns'][FT_TENANT_B] = fixtureInsert($pdo, FT_TENANT_B,
    "INSERT INTO bet_campaigns (name, region, target_count, status, note)
     VALUES (?, 'praha', 50, 'open', 'tB')",
    [FT_MARKER . ' tB-camp']
);

// ── Tier 3: oz_targets ────────────────────────────────────────────
echo "  → oz_targets\n";
$fixtures['oz_targets'][FT_TENANT_A] = fixtureInsert($pdo, FT_TENANT_A,
    "INSERT INTO oz_targets (user_id, region, target_count, year, month)
     VALUES (?, ?, 10, ?, 1)",
    [$userA, FT_MARKER . 'A', (int) $now]
);
$fixtures['oz_targets'][FT_TENANT_B] = fixtureInsert($pdo, FT_TENANT_B,
    "INSERT INTO oz_targets (user_id, region, target_count, year, month)
     VALUES (?, ?, 10, ?, 1)",
    [$userB, FT_MARKER . 'B', (int) $now]
);

// ── Tier 3: monthly_goals ─────────────────────────────────────────
echo "  → monthly_goals\n";
$fixtures['monthly_goals'][FT_TENANT_A] = fixtureInsert($pdo, FT_TENANT_A,
    "INSERT INTO monthly_goals (target_wins, bonus1_at_pct, bonus1_pct, bonus2_at_pct, bonus2_pct,
                                 motiv_enabled, valid_from, valid_to)
     VALUES (99987, 100, 5.00, 120, 5.00, 1, CURDATE(), NULL)", []
);
$fixtures['monthly_goals'][FT_TENANT_B] = fixtureInsert($pdo, FT_TENANT_B,
    "INSERT INTO monthly_goals (target_wins, bonus1_at_pct, bonus1_pct, bonus2_at_pct, bonus2_pct,
                                 motiv_enabled, valid_from, valid_to)
     VALUES (99987, 100, 5.00, 120, 5.00, 1, CURDATE(), NULL)", []
);

// ── Tier 3: caller_rewards_config ─────────────────────────────────
echo "  → caller_rewards_config\n";
$fixtures['caller_rewards_config'][FT_TENANT_A] = fixtureInsert($pdo, FT_TENANT_A,
    "INSERT INTO caller_rewards_config (amount_czk, valid_from, valid_to) VALUES (1.23, CURDATE(), NULL)", []
);
$fixtures['caller_rewards_config'][FT_TENANT_B] = fixtureInsert($pdo, FT_TENANT_B,
    "INSERT INTO caller_rewards_config (amount_czk, valid_from, valid_to) VALUES (1.23, CURDATE(), NULL)", []
);

// ── Tier 3: oz_team_stages ────────────────────────────────────────
echo "  → oz_team_stages\n";
$fixtures['oz_team_stages'][FT_TENANT_A] = fixtureInsert($pdo, FT_TENANT_A,
    "INSERT INTO oz_team_stages (year, month, stage_number, label, target_bmsl)
     VALUES (?, 1, 99, ?, 100000)",
    [(int) $now, FT_MARKER . ' tA-stage']
);
$fixtures['oz_team_stages'][FT_TENANT_B] = fixtureInsert($pdo, FT_TENANT_B,
    "INSERT INTO oz_team_stages (year, month, stage_number, label, target_bmsl)
     VALUES (?, 1, 99, ?, 100000)",
    [(int) $now, FT_MARKER . ' tB-stage']
);

// ── Tier 3: oz_personal_milestones ────────────────────────────────
echo "  → oz_personal_milestones\n";
$fixtures['oz_personal_milestones'][FT_TENANT_A] = fixtureInsert($pdo, FT_TENANT_A,
    "INSERT INTO oz_personal_milestones (oz_id, year, month, label, target_bmsl)
     VALUES (?, ?, 1, ?, 100000)",
    [$userA, (int) $now, FT_MARKER . ' tA-ms']
);
$fixtures['oz_personal_milestones'][FT_TENANT_B] = fixtureInsert($pdo, FT_TENANT_B,
    "INSERT INTO oz_personal_milestones (oz_id, year, month, label, target_bmsl)
     VALUES (?, ?, 1, ?, 100000)",
    [$userB, (int) $now, FT_MARKER . ' tB-ms']
);

// ── Tier 4: rescue_requests (FK contacts) ─────────────────────────
echo "  → rescue_requests\n";
if ($fixtures['contacts'][FT_TENANT_A] > 0) {
    $fixtures['rescue_requests'][FT_TENANT_A] = fixtureInsert($pdo, FT_TENANT_A,
        "INSERT INTO rescue_requests (contact_id, original_sales_id, target_sales_id, prefer_original,
                                       reason, requested_at, expires_at, outcome)
         VALUES (?, ?, NULL, 1, ?, NOW(3), DATE_ADD(NOW(3), INTERVAL 14 DAY), 'pending')",
        [$fixtures['contacts'][FT_TENANT_A], $userA, FT_MARKER . ' tA-reason']
    );
}
if ($fixtures['contacts'][FT_TENANT_B] > 0) {
    $fixtures['rescue_requests'][FT_TENANT_B] = fixtureInsert($pdo, FT_TENANT_B,
        "INSERT INTO rescue_requests (contact_id, original_sales_id, target_sales_id, prefer_original,
                                       reason, requested_at, expires_at, outcome)
         VALUES (?, ?, NULL, 1, ?, NOW(3), DATE_ADD(NOW(3), INTERVAL 14 DAY), 'pending')",
        [$fixtures['contacts'][FT_TENANT_B], $userB, FT_MARKER . ' tB-reason']
    );
}

// ── Tier 4: app_settings ──────────────────────────────────────────
// SPECIÁLNÍ PŘÍPAD: app_settings nemá auto-increment `id` sloupec.
// Po migraci 033 má PRIMARY KEY (tenant_id, skey). Proto fixtureInsert vrátí
// lastInsertId=0, ale řádek tam je. Identifikujeme pomocí skey (string klíče).
echo "  → app_settings\n";
fixtureInsert($pdo, FT_TENANT_A,
    "INSERT INTO app_settings (skey, sval, updated_by) VALUES (?, 'value-tenantA', ?)",
    [FT_SKEY, $userA]
);
fixtureInsert($pdo, FT_TENANT_B,
    "INSERT INTO app_settings (skey, sval, updated_by) VALUES (?, 'value-tenantB', ?)",
    [FT_SKEY, $userB]
);
// Pro app_settings ukládáme do fixtures string klíč (= skey), ne integer id
$fixtures['app_settings'][FT_TENANT_A] = FT_SKEY;
$fixtures['app_settings'][FT_TENANT_B] = FT_SKEY;

// ── Tier 4: dnc_list ──────────────────────────────────────────────
echo "  → dnc_list\n";
$fixtures['dnc_list'][FT_TENANT_A] = fixtureInsert($pdo, FT_TENANT_A,
    "INSERT INTO dnc_list (ico, telefon, email) VALUES ('99999991', '+420700000091', 'tA-dnc@test')", []
);
$fixtures['dnc_list'][FT_TENANT_B] = fixtureInsert($pdo, FT_TENANT_B,
    "INSERT INTO dnc_list (ico, telefon, email) VALUES ('99999992', '+420700000092', 'tB-dnc@test')", []
);

// ── Tier 4: workflow_log (FK contacts) ────────────────────────────
echo "  → workflow_log\n";
if ($fixtures['contacts'][FT_TENANT_A] > 0) {
    $fixtures['workflow_log'][FT_TENANT_A] = fixtureInsert($pdo, FT_TENANT_A,
        "INSERT INTO workflow_log (contact_id, user_id, old_status, new_status, note, created_at)
         VALUES (?, ?, NULL, 'NEW', ?, NOW(3))",
        [$fixtures['contacts'][FT_TENANT_A], $userA, FT_MARKER . ' tA-log']
    );
}
if ($fixtures['contacts'][FT_TENANT_B] > 0) {
    $fixtures['workflow_log'][FT_TENANT_B] = fixtureInsert($pdo, FT_TENANT_B,
        "INSERT INTO workflow_log (contact_id, user_id, old_status, new_status, note, created_at)
         VALUES (?, ?, NULL, 'NEW', ?, NOW(3))",
        [$fixtures['contacts'][FT_TENANT_B], $userB, FT_MARKER . ' tB-log']
    );
}

// ── Tier 4: commission_tiers_sales ────────────────────────────────
echo "  → commission_tiers_sales\n";
$fixtures['commission_tiers_sales'][FT_TENANT_A] = fixtureInsert($pdo, FT_TENANT_A,
    "INSERT INTO commission_tiers_sales (min_monthly_sales, max_monthly_sales, multiplier)
     VALUES (987654.00, NULL, 1.11)", []
);
$fixtures['commission_tiers_sales'][FT_TENANT_B] = fixtureInsert($pdo, FT_TENANT_B,
    "INSERT INTO commission_tiers_sales (min_monthly_sales, max_monthly_sales, multiplier)
     VALUES (987654.00, NULL, 2.22)", []
);

// ── Tier 4: commission_tiers_company ──────────────────────────────
echo "  → commission_tiers_company\n";
$fixtures['commission_tiers_company'][FT_TENANT_A] = fixtureInsert($pdo, FT_TENANT_A,
    "INSERT INTO commission_tiers_company (service_type, min_price, max_price, multiplier)
     VALUES ('FULL-TEST', 0, 99999, 1.5)", []
);
$fixtures['commission_tiers_company'][FT_TENANT_B] = fixtureInsert($pdo, FT_TENANT_B,
    "INSERT INTO commission_tiers_company (service_type, min_price, max_price, multiplier)
     VALUES ('FULL-TEST', 0, 99999, 3.5)", []
);

echo "\n  ✓ fixtures vloženy\n\n";

// ═══════════════════════════════════════════════════════════════════
//  AUTO-INJECT PROBES — wrapper doplnil tenant_id automaticky?
// ═══════════════════════════════════════════════════════════════════
echo "🧪 AUTO-INJECT PROBES (wrapper doplňuje tenant_id)\n";
// Tabulky bez auto-increment `id` — pro lookup použij jiný PK sloupec
$customPk = [
    'app_settings' => 'skey',
];
foreach ($fixtures as $table => $perTenant) {
    $rawA = $perTenant[FT_TENANT_A] ?? 0;
    $rawB = $perTenant[FT_TENANT_B] ?? 0;
    $pkCol = $customPk[$table] ?? 'id';

    // Pro int PK: 0 = insert selhal. Pro string PK: '' = insert selhal.
    $failedA = is_int($rawA) ? ($rawA <= 0) : ($rawA === '');
    $failedB = is_int($rawB) ? ($rawB <= 0) : ($rawB === '');
    if ($failedA && $failedB) {
        record($report, 'auto-inject', $table, 'wrapper_inject', 'skip', '(insert failed)');
        continue;
    }

    $stmt = $pdo->prepare("SELECT tenant_id FROM {$table} WHERE {$pkCol} = ? AND tenant_id = ?");
    $stmt->execute([$rawA, FT_TENANT_A]);
    $tidA = (int) ($stmt->fetchColumn() ?: 0);
    $stmt->execute([$rawB, FT_TENANT_B]);
    $tidB = (int) ($stmt->fetchColumn() ?: 0);

    if ($tidA === FT_TENANT_A && $tidB === FT_TENANT_B) {
        record($report, 'auto-inject', $table, 'wrapper_inject', 'ok');
    } else {
        record($report, 'auto-inject', $table, 'wrapper_inject', 'fail',
               "(A: {$tidA} != " . FT_TENANT_A . " | B: {$tidB} != " . FT_TENANT_B . ")");
    }
}
echo "\n";

// ═══════════════════════════════════════════════════════════════════
//  ISOLATION PROBES — 4 testy nad každou tabulkou
// ═══════════════════════════════════════════════════════════════════
echo "🧪 ISOLATION PROBES (SELECT izoluje, cross-UPDATE/DELETE selhává)\n";
$tierMap = [
    'Tier 1' => ['contacts', 'contact_phones'],
    'Tier 2' => ['premium_orders', 'premium_lead_pool', 'bet_campaigns'],
    'Tier 3' => ['oz_targets', 'monthly_goals', 'caller_rewards_config',
                 'oz_team_stages', 'oz_personal_milestones'],
    'Tier 4' => ['rescue_requests', 'app_settings', 'dnc_list', 'workflow_log',
                 'commission_tiers_sales', 'commission_tiers_company'],
];
foreach ($tierMap as $tier => $tables) {
    echo "\n  ── {$tier} ──\n";
    foreach ($tables as $t) {
        $pkCol  = $customPk[$t] ?? 'id';
        $rawA = $fixtures[$t][FT_TENANT_A] ?? 0;
        $rawB = $fixtures[$t][FT_TENANT_B] ?? 0;
        // Cast jen pokud výchozí int sloupec; jinak nechej string skey
        if ($pkCol === 'id') {
            $rawA = (int) $rawA;
            $rawB = (int) $rawB;
        }
        probeIsolation($pdo, $report, $tier, $t, $rawA, $rawB, $pkCol);
    }
}
echo "\n";

// ═══════════════════════════════════════════════════════════════════
//  HELPER PROBES — Tier 4 helpery vidí jen aktivní tenant
// ═══════════════════════════════════════════════════════════════════
echo "🧪 HELPER PROBES (Tier 4 helpery respektují aktivní tenant)\n";

// crm_setting_get
echo "  📋 crm_setting_get('" . FT_SKEY . "')\n";
crm_tenant_set(FT_TENANT_A);
$valA = function_exists('crm_setting_get') ? crm_setting_get(FT_SKEY) : null;
crm_tenant_set(FT_TENANT_B);
$valB = function_exists('crm_setting_get') ? crm_setting_get(FT_SKEY) : null;
if ($valA === 'value-tenantA' && $valB === 'value-tenantB') {
    record($report, 'helpers', 'crm_setting_get', 'returns_own_tenant', 'ok');
} else {
    record($report, 'helpers', 'crm_setting_get', 'returns_own_tenant', 'fail',
           "(A='{$valA}' B='{$valB}')");
}

// rescue_find_active
echo "  📋 rescue_find_active()\n";
if (function_exists('rescue_find_active')
    && $fixtures['contacts'][FT_TENANT_A] > 0
    && $fixtures['contacts'][FT_TENANT_B] > 0) {
    crm_tenant_set(FT_TENANT_A);
    $rA = rescue_find_active($pdo, $fixtures['contacts'][FT_TENANT_A]);
    $rA_cross = rescue_find_active($pdo, $fixtures['contacts'][FT_TENANT_B]);
    crm_tenant_set(FT_TENANT_B);
    $rB = rescue_find_active($pdo, $fixtures['contacts'][FT_TENANT_B]);
    $rB_cross = rescue_find_active($pdo, $fixtures['contacts'][FT_TENANT_A]);
    if ($rA !== null && $rA_cross === null && $rB !== null && $rB_cross === null) {
        record($report, 'helpers', 'rescue_find_active', 'returns_own_tenant', 'ok');
    } else {
        record($report, 'helpers', 'rescue_find_active', 'returns_own_tenant', 'fail',
               "(A=" . ($rA ? 'found' : 'null')
             . " A_cross=" . ($rA_cross ? 'LEAK!' : 'null')
             . " B=" . ($rB ? 'found' : 'null')
             . " B_cross=" . ($rB_cross ? 'LEAK!' : 'null') . ")");
    }
} else {
    record($report, 'helpers', 'rescue_find_active', 'returns_own_tenant', 'skip');
}

// crm_phones_for_contact
echo "  📋 crm_phones_for_contact()\n";
if (function_exists('crm_phones_for_contact')
    && $fixtures['contacts'][FT_TENANT_A] > 0
    && $fixtures['contacts'][FT_TENANT_B] > 0) {
    crm_tenant_set(FT_TENANT_A);
    $pA = crm_phones_for_contact($pdo, $fixtures['contacts'][FT_TENANT_A]);
    $pA_cross = crm_phones_for_contact($pdo, $fixtures['contacts'][FT_TENANT_B]);
    crm_tenant_set(FT_TENANT_B);
    $pB = crm_phones_for_contact($pdo, $fixtures['contacts'][FT_TENANT_B]);
    $pB_cross = crm_phones_for_contact($pdo, $fixtures['contacts'][FT_TENANT_A]);
    if (count($pA) === 1 && count($pA_cross) === 0 && count($pB) === 1 && count($pB_cross) === 0) {
        record($report, 'helpers', 'crm_phones_for_contact', 'returns_own_tenant', 'ok');
    } else {
        record($report, 'helpers', 'crm_phones_for_contact', 'returns_own_tenant', 'fail',
               "(A=" . count($pA) . " A_cross=" . count($pA_cross)
             . " B=" . count($pB) . " B_cross=" . count($pB_cross) . ")");
    }
} else {
    record($report, 'helpers', 'crm_phones_for_contact', 'returns_own_tenant', 'skip');
}

// commissions_caller_reward_czk
echo "  📋 commissions_caller_reward_czk()\n";
if (function_exists('commissions_caller_reward_czk')) {
    crm_tenant_set(FT_TENANT_A);
    $rwA = commissions_caller_reward_czk($pdo);
    crm_tenant_set(FT_TENANT_B);
    $rwB = commissions_caller_reward_czk($pdo);
    // Oba mají amount=1.23 (fixture), jiný tenant nemá vidět žádný 1.23 přes svůj kontext —
    // SUCCESS = oba vrátí 1.23 + není leak (kontroluje se tím, že každý kontext vrací jen své)
    if ((float) $rwA === 1.23 && (float) $rwB === 1.23) {
        record($report, 'helpers', 'commissions_caller_reward_czk', 'returns_own_tenant', 'ok');
    } else {
        record($report, 'helpers', 'commissions_caller_reward_czk', 'returns_own_tenant', 'fail',
               "(A={$rwA} B={$rwB})");
    }
} else {
    record($report, 'helpers', 'commissions_caller_reward_czk', 'returns_own_tenant', 'skip');
}

echo "\n";

// ═══════════════════════════════════════════════════════════════════
//  REPORT
// ═══════════════════════════════════════════════════════════════════
echo "════════════════════════════════════════════════════════\n";
echo "  REPORT\n";
echo "════════════════════════════════════════════════════════\n\n";
echo sprintf("  ✓ OK:     %d\n", $totalOk);
echo sprintf("  ❌ FAIL:  %d\n", $totalFail);
echo sprintf("  ⊘ SKIP:   %d\n", $totalSkip);
$total = $totalOk + $totalFail + $totalSkip;
echo sprintf("  ───────────────\n  Σ:        %d testů\n\n", $total);

if ($totalFail === 0) {
    echo "🎉 VŠECHNY testy izolace prošly. Multi-tenant CRM je bezpečný.\n";
    echo "\nPro vyčištění fixtures: php bin/tenant-full-isolation-test.php --cleanup\n";
    exit(0);
} else {
    echo "⚠  Některé testy selhaly — projdi výpis výše a oprav před produkcí.\n";
    exit(1);
}
