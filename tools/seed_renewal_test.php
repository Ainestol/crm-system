<?php
// e:\Snecinatripu\tools\seed_renewal_test.php
declare(strict_types=1);

/**
 * Seed script pro testování renewal stacku v OZ pracovní ploše.
 *
 * Vytvoří 5 testovacích kontaktů a přiřadí je vybranému OZ:
 *   - 1× KRITICKÉ   (smlouva končí za ~10 dní → červená)
 *   - 2× BRZY       (za ~45 dní a ~80 dní → oranžová)
 *   - 2× v klidu    (za ~120 dní a ~165 dní → zelená)
 *
 * Test kontakty mají firma s prefixem "TEST_RENEWAL_" — snadná identifikace
 * a smazání pomocí --cleanup.
 *
 * SPUŠTĚNÍ:
 *   php tools/seed_renewal_test.php                  → výpis OZ uživatelů + nápověda
 *   php tools/seed_renewal_test.php --oz=5           → vytvoří 5 testovacích kontaktů pro OZ id 5
 *   php tools/seed_renewal_test.php --cleanup        → smaže VŠECHNY kontakty s prefixem TEST_RENEWAL_
 *   php tools/seed_renewal_test.php --oz=5 --reset   → nejdřív cleanup, pak nové seed
 *
 * BEZPEČNOST:
 *   - CLI-only (odmítne web)
 *   - Pracuje POUZE s firmami "TEST_RENEWAL_*" → nikdy nesahá na produkční data
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'Tento skript je dostupný pouze z příkazové řádky.';
    exit(1);
}

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'constants.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'auth.php';

const FIRMA_PREFIX = 'TEST_RENEWAL_';

$args = $argv ?? [];
$ozId    = null;
$cleanup = false;
$reset   = false;
foreach ($args as $a) {
    if ($a === '--cleanup') $cleanup = true;
    if ($a === '--reset')   $reset   = true;
    if (str_starts_with($a, '--oz=')) $ozId = (int) substr($a, 5);
}

$pdo = crm_pdo();

// ── Bez parametrů: ukáž seznam OZ uživatelů ──────────────────────────
if ($ozId === null && !$cleanup) {
    fwrite(STDOUT, "──────────────────────────────────────────\n");
    fwrite(STDOUT, "  Renewal stack — testovací seed\n");
    fwrite(STDOUT, "──────────────────────────────────────────\n\n");
    fwrite(STDOUT, "Dostupní OZ (role 'obchodak'):\n\n");

    $st = $pdo->query("SELECT id, jmeno, email, primary_region FROM users WHERE role = 'obchodak' AND aktivni = 1 ORDER BY id");
    $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    if ($rows === []) {
        fwrite(STDERR, "  ⚠ Žádný aktivní OZ ('obchodak') v DB.\n");
        fwrite(STDERR, "  Vytvořte si nejdřív aspoň jednoho přes /admin/users.\n\n");
        exit(2);
    }
    foreach ($rows as $r) {
        fwrite(STDOUT, sprintf("  id=%-3d  %-30s  %s  region=%s\n",
            (int) $r['id'],
            (string) ($r['jmeno'] ?? ''),
            (string) ($r['email'] ?? ''),
            (string) ($r['primary_region'] ?? '—')
        ));
    }
    fwrite(STDOUT, "\nPoužití:\n");
    fwrite(STDOUT, "  php tools/seed_renewal_test.php --oz=<id>          → seed 5 testovacích kontaktů pro daného OZ\n");
    fwrite(STDOUT, "  php tools/seed_renewal_test.php --cleanup          → smazat všechny TEST_RENEWAL_* kontakty\n");
    fwrite(STDOUT, "  php tools/seed_renewal_test.php --oz=<id> --reset  → cleanup + nový seed\n\n");
    exit(0);
}

// ── Cleanup mode ─────────────────────────────────────────────────────
if ($cleanup || $reset) {
    fwrite(STDOUT, "Mažu testovací kontakty s prefixem '" . FIRMA_PREFIX . "'...\n");
    try {
        $st = $pdo->prepare("SELECT id, firma FROM contacts WHERE firma LIKE :p");
        $st->execute(['p' => FIRMA_PREFIX . '%']);
        $toDel = $st->fetchAll(PDO::FETCH_ASSOC);

        if ($toDel === []) {
            fwrite(STDOUT, "  ✓ Žádné testovací kontakty k smazání.\n");
        } else {
            $ids = array_column($toDel, 'id');
            $ph  = implode(',', array_fill(0, count($ids), '?'));

            // Cascade-mazání závislých záznamů (kdyby na test kontaktech něco viselo)
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach (['workflow_log', 'oz_contact_workflow', 'oz_contact_notes',
                      'oz_contact_actions', 'contact_oz_flags',
                      'contact_notes', 'contact_quality_ratings',
                      'assignment_log', 'sms_log', 'commissions'] as $table) {
                if (!tableExists($pdo, $table)) continue;
                try {
                    $pdo->prepare("DELETE FROM `{$table}` WHERE contact_id IN ($ph)")
                        ->execute($ids);
                } catch (\PDOException) { /* tabulka neexistuje nebo nemá contact_id */ }
            }
            // Nakonec contacts
            $pdo->prepare("DELETE FROM contacts WHERE id IN ($ph)")->execute($ids);
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

            foreach ($toDel as $r) {
                fwrite(STDOUT, sprintf("  ✓ Smazáno: %s (id=%d)\n",
                    (string) $r['firma'], (int) $r['id']));
            }
            fwrite(STDOUT, sprintf("\n  Celkem smazáno: %d kontaktů.\n", count($toDel)));
        }
    } catch (\PDOException $e) {
        fwrite(STDERR, "[ERROR] Cleanup selhal: " . $e->getMessage() . "\n");
        exit(3);
    }

    if (!$reset) {
        exit(0);
    }
    fwrite(STDOUT, "\n");
}

// ── Seed mode ────────────────────────────────────────────────────────
if ($ozId === null) {
    fwrite(STDERR, "[ERROR] Po --reset musí následovat --oz=<id> pro nový seed.\n");
    exit(2);
}

// Validace OZ existuje a má roli obchodak
$st = $pdo->prepare("SELECT id, jmeno, primary_region FROM users WHERE id = :id AND role = 'obchodak' AND aktivni = 1");
$st->execute(['id' => $ozId]);
$oz = $st->fetch(PDO::FETCH_ASSOC);
if (!$oz) {
    fwrite(STDERR, "[ERROR] OZ s id={$ozId} neexistuje nebo není aktivní obchodak.\n");
    fwrite(STDERR, "Spusť skript bez parametrů pro výpis dostupných OZ.\n");
    exit(2);
}
$ozRegion = (string) ($oz['primary_region'] ?? 'jihomoravsky');
if ($ozRegion === '' || $ozRegion === '—') $ozRegion = 'jihomoravsky';

// Šablony testovacích kontaktů — různé urgency pásma
$today = new \DateTimeImmutable('today');
$contacts = [
    [
        'suffix' => 'kritcial_alert',
        'firma'  => FIRMA_PREFIX . 'KRITICKÉ - Albert s.r.o.',
        'days'   => 10,   // za 10 dní = kritické
        'ico'    => '99000001',
        'tel'    => '601000001',
        'email'  => 'test1+albert@example.cz',
    ],
    [
        'suffix' => 'high_1',
        'firma'  => FIRMA_PREFIX . 'BRZY - Beta Group',
        'days'   => 45,
        'ico'    => '99000002',
        'tel'    => '601000002',
        'email'  => 'test2+beta@example.cz',
    ],
    [
        'suffix' => 'high_2',
        'firma'  => FIRMA_PREFIX . 'BRZY - Gamma Pekárna',
        'days'   => 80,
        'ico'    => '99000003',
        'tel'    => '601000003',
        'email'  => 'test3+gamma@example.cz',
    ],
    [
        'suffix' => 'normal_1',
        'firma'  => FIRMA_PREFIX . 'V KLIDU - Delta Elektro',
        'days'   => 120,
        'ico'    => '99000004',
        'tel'    => '601000004',
        'email'  => 'test4+delta@example.cz',
    ],
    [
        'suffix' => 'normal_2',
        'firma'  => FIRMA_PREFIX . 'V KLIDU - Epsilon Servis',
        'days'   => 165,
        'ico'    => '99000005',
        'tel'    => '601000005',
        'email'  => 'test5+epsilon@example.cz',
    ],
];

fwrite(STDOUT, "──────────────────────────────────────────\n");
fwrite(STDOUT, "  Seed pro OZ: " . (string) $oz['jmeno'] . " (id=" . (int) $oz['id'] . ")\n");
fwrite(STDOUT, "  Region: " . $ozRegion . "\n");
fwrite(STDOUT, "──────────────────────────────────────────\n\n");

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "INSERT INTO contacts
           (ico, firma, telefon, email, region, stav,
            assigned_sales_id, vyrocni_smlouvy,
            datum_predani, created_at, updated_at)
         VALUES
           (:ico, :firma, :tel, :em, :reg, 'CALLED_OK',
            :ozid, :vyrocni,
            NOW(3), NOW(3), NOW(3))"
    );

    $count = 0;
    foreach ($contacts as $c) {
        $endDate = $today->modify('+' . $c['days'] . ' days')->format('Y-m-d');
        $stmt->execute([
            'ico'      => $c['ico'],
            'firma'    => $c['firma'],
            'tel'      => $c['tel'],
            'em'       => $c['email'],
            'reg'      => $ozRegion,
            'ozid'     => $ozId,
            'vyrocni'  => $endDate,
        ]);
        $newId = (int) $pdo->lastInsertId();

        $urgency = $c['days'] <= 30 ? '🔴 KRITICKÉ'
                 : ($c['days'] <= 90 ? '🟠 BRZY' : '🟢 v klidu');
        fwrite(STDOUT, sprintf("  ✓ %s  id=%-5d  expirace %s  (%s · za %d dní)\n",
            $urgency, $newId, $endDate, $c['firma'], $c['days']));
        $count++;
    }

    $pdo->commit();
    fwrite(STDOUT, sprintf("\n✓ Vytvořeno %d testovacích kontaktů pro OZ '%s'.\n",
        $count, (string) $oz['jmeno']));
    fwrite(STDOUT, "\nDalší kroky:\n");
    fwrite(STDOUT, "  1. Přihlas se jako '" . (string) ($oz['jmeno'] ?? '') . "' (nebo v akci jako majitel/superadmin)\n");
    fwrite(STDOUT, "  2. Otevři /oz/leads\n");
    fwrite(STDOUT, "  3. V levém sidebaru pod pending stackem (případně samotné) uvidíš zelenou věž\n");
    fwrite(STDOUT, "     '🔄 Renewal (5)'\n");
    fwrite(STDOUT, "  4. Klik → popover se 5 položkami seřazenými podle expirace\n\n");
    fwrite(STDOUT, "Pro úklid:  php tools/seed_renewal_test.php --cleanup\n");
} catch (\PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "[ERROR] Seed selhal: " . $e->getMessage() . "\n");
    exit(3);
}
exit(0);

// ── Helper ──
function tableExists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = :t'
    );
    $st->execute(['t' => $table]);
    return ((int) $st->fetchColumn()) > 0;
}
