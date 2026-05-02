<?php
// e:\Snecinatripu\tools\diag_cisticka_progress.php
//
// Diagnostika: PROČ se vyčištěné kontakty nepočítají do progress baru?
//
// Spuštění z příkazové řádky:
//     cd C:\Users\Aines\... \Snecinatripu
//     php tools/diag_cisticka_progress.php
//
// (nebo jen `php tools/diag_cisticka_progress.php` z kořene projektu)
//
// Co skript ukáže:
//     1) Aktuální datum a měsíční rozsah, který se používá pro progress
//     2) Všechny čističky v DB (id, jméno, email)
//     3) Pro každou čističku:
//        - aktivní cíle (region → target → period_yyyymm → priority)
//        - počet workflow_log záznamů PO MĚSÍCÍCH (všechny historické)
//        - počet záznamů v aktuálním měsíci → kolik se započte do progress
//        - region distribuce (kontakty kterých krajů byly verifikovány)
//
// PO POUŽITÍ NEZAPOMEŇ SMAZAT (nebo jen necháš v tools/ — není routovaný).

declare(strict_types=1);

// Connect — stejné credentials jako reset_pw_standalone.php
$host = '127.0.0.1';
$port = 3306;
$db   = 'crm';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    echo "Chyba pripojeni: " . $e->getMessage() . "\n";
    exit(1);
}

// Helper pro ASCII tabulky
function row(string ...$cells): string {
    return '│ ' . implode(' │ ', $cells) . ' │' . PHP_EOL;
}
function hr(int $width = 100): string {
    return str_repeat('─', $width) . PHP_EOL;
}

// ─────────────────────────────────────────────────────────────────
// 1) Aktuální datum a měsíční rozsah
// ─────────────────────────────────────────────────────────────────

$phpNow      = date('Y-m-d H:i:s');
$currentYM   = (int) date('Ym');
$mStart      = date('Y-m-01') . ' 00:00:00.000';
$mEnd        = date('Y-m-t')  . ' 23:59:59.999';

$mysqlInfo = $pdo->query("SELECT NOW() AS mnow, CURDATE() AS mtoday, @@session.time_zone AS tz, @@global.time_zone AS gtz")->fetch();

echo PHP_EOL;
echo "═══════════════════════════════════════════════════════════════════════════" . PHP_EOL;
echo " DIAGNOSTIKA — proč se vyčištěné kontakty nepočítají do progress baru?" . PHP_EOL;
echo "═══════════════════════════════════════════════════════════════════════════" . PHP_EOL;
echo PHP_EOL;
echo "📅 ČAS:" . PHP_EOL;
echo "   PHP date('Y-m-d H:i:s'):  $phpNow" . PHP_EOL;
echo "   PHP date('Ym'):           $currentYM   (= period_yyyymm pro aktuální měsíc)" . PHP_EOL;
echo "   PHP timezone:             " . date_default_timezone_get() . PHP_EOL;
echo "   MySQL NOW():              " . $mysqlInfo['mnow'] . PHP_EOL;
echo "   MySQL CURDATE():          " . $mysqlInfo['mtoday'] . PHP_EOL;
echo "   MySQL @@session.tz:       " . $mysqlInfo['tz'] . PHP_EOL;
echo "   MySQL @@global.tz:        " . $mysqlInfo['gtz'] . PHP_EOL;
echo PHP_EOL;
echo "📆 MĚSÍČNÍ ROZSAH (používá se pro progress count):" . PHP_EOL;
echo "   start:  $mStart" . PHP_EOL;
echo "   end:    $mEnd" . PHP_EOL;
echo PHP_EOL;

// ─────────────────────────────────────────────────────────────────
// 2) Najdi všechny čističky
// ─────────────────────────────────────────────────────────────────

$cisticky = $pdo->query(
    "SELECT id, jmeno, email, role, aktivni
     FROM users
     WHERE role = 'cisticka' OR role = 'majitel' OR role = 'superadmin'
     ORDER BY role, jmeno"
)->fetchAll();

echo "👥 UŽIVATELÉ S PŘÍSTUPEM K /cisticka:" . PHP_EOL;
foreach ($cisticky as $u) {
    $aktiv = $u['aktivni'] ? 'aktivní' : 'NEAKTIVNÍ';
    echo "   #{$u['id']}  [{$u['role']}]  {$u['jmeno']} <{$u['email']}>  ($aktiv)" . PHP_EOL;
}
echo PHP_EOL;

// ─────────────────────────────────────────────────────────────────
// 3) Pro každou čističku: detailní rozbor
// ─────────────────────────────────────────────────────────────────

foreach ($cisticky as $u) {
    if ((int) $u['aktivni'] !== 1) continue;
    $uid = (int) $u['id'];

    echo hr();
    echo "  👤 ČISTIČKA #{$uid} — {$u['jmeno']} <{$u['email']}>" . PHP_EOL;
    echo hr();
    echo PHP_EOL;

    // 3-pre) user_regions (kraje, ke kterým má čistička přístup)
    $urStmt = $pdo->prepare("SELECT region FROM user_regions WHERE user_id = ? ORDER BY region");
    $urStmt->execute([$uid]);
    $urs = $urStmt->fetchAll(PDO::FETCH_COLUMN);
    echo "  📍 USER_REGIONS (přiřazené kraje této čističky):" . PHP_EOL;
    if (!$urs) {
        echo "     (žádné) → spadne na fallback (vidí všechny NEW kontakty)" . PHP_EOL;
    } else {
        echo "     " . implode(', ', $urs) . PHP_EOL;
    }
    echo PHP_EOL;

    // 3a) Aktuální cíle (s period_yyyymm)
    echo "  🎯 GOALS v cisticka_region_goals (VŠECHNY období):" . PHP_EOL;
    $allGoals = $pdo->query(
        "SELECT region, period_yyyymm, monthly_target, priority, set_by, updated_at
         FROM cisticka_region_goals
         WHERE monthly_target > 0
         ORDER BY period_yyyymm DESC, priority ASC, region ASC"
    )->fetchAll();
    if (!$allGoals) {
        echo "     (žádné cíle nikdy nebyly nastaveny)" . PHP_EOL;
    } else {
        echo "     " . str_pad('region', 18) . str_pad('period', 9) . str_pad('target', 8) . str_pad('prio', 6) . 'updated_at' . PHP_EOL;
        echo "     " . str_repeat('-', 70) . PHP_EOL;
        foreach ($allGoals as $g) {
            $marker = ((int) $g['period_yyyymm'] === $currentYM) ? ' ← AKTUÁLNÍ MĚSÍC' : '';
            echo "     "
               . str_pad((string) $g['region'], 18)
               . str_pad((string) $g['period_yyyymm'], 9)
               . str_pad((string) $g['monthly_target'], 8)
               . str_pad((string) $g['priority'], 6)
               . $g['updated_at']
               . $marker
               . PHP_EOL;
        }
    }
    echo PHP_EOL;

    // 3b) workflow_log — počty po měsících
    echo "  📜 WORKFLOW_LOG: počet ověření této čističky po měsících" . PHP_EOL;
    $monthlyStmt = $pdo->prepare(
        "SELECT YEAR(created_at) AS y, MONTH(created_at) AS m,
                COUNT(*) AS total,
                COUNT(DISTINCT contact_id) AS unique_contacts
         FROM workflow_log
         WHERE user_id = ?
           AND new_status IN ('READY','VF_SKIP')
         GROUP BY YEAR(created_at), MONTH(created_at)
         ORDER BY y DESC, m DESC"
    );
    $monthlyStmt->execute([$uid]);
    $monthly = $monthlyStmt->fetchAll();
    if (!$monthly) {
        echo "     (žádná ověření zatím)" . PHP_EOL;
    } else {
        echo "     " . str_pad('rok-měsíc', 12) . str_pad('záznamů', 10) . str_pad('uniq.kontaktů', 16) . 'pozn.' . PHP_EOL;
        echo "     " . str_repeat('-', 60) . PHP_EOL;
        foreach ($monthly as $m) {
            $ym = sprintf('%04d-%02d', $m['y'], $m['m']);
            $isCurrent = ((int) $m['y'] * 100 + (int) $m['m']) === $currentYM;
            $marker = $isCurrent ? ' ← TENTO MĚSÍC (počítá se do progress)' : '';
            echo "     "
               . str_pad($ym, 12)
               . str_pad((string) $m['total'], 10)
               . str_pad((string) $m['unique_contacts'], 16)
               . $marker
               . PHP_EOL;
        }
    }
    echo PHP_EOL;

    // 3c) Region distribuce (jen aktuální měsíc)
    echo "  🗺️  WORKFLOW_LOG v aktuálním měsíci ($mStart .. $mEnd) — region distribuce:" . PHP_EOL;
    $regStmt = $pdo->prepare(
        "SELECT c.region,
                COUNT(*) AS log_entries,
                COUNT(DISTINCT wl.contact_id) AS unique_contacts,
                MIN(wl.created_at) AS first_at,
                MAX(wl.created_at) AS last_at
         FROM workflow_log wl
         JOIN contacts c ON c.id = wl.contact_id
         WHERE wl.user_id = ?
           AND wl.new_status IN ('READY','VF_SKIP')
           AND wl.created_at BETWEEN ? AND ?
         GROUP BY c.region
         ORDER BY unique_contacts DESC"
    );
    $regStmt->execute([$uid, $mStart, $mEnd]);
    $regs = $regStmt->fetchAll();
    if (!$regs) {
        echo "     (v tomto měsíci žádná ověření) ← PROTO PROGRESS = 0" . PHP_EOL;
    } else {
        echo "     " . str_pad('region', 18) . str_pad('log entries', 13) . str_pad('uniq', 6) . str_pad('first_at', 22) . 'last_at' . PHP_EOL;
        echo "     " . str_repeat('-', 80) . PHP_EOL;
        foreach ($regs as $r) {
            echo "     "
               . str_pad((string) $r['region'], 18)
               . str_pad((string) $r['log_entries'], 13)
               . str_pad((string) $r['unique_contacts'], 6)
               . str_pad((string) $r['first_at'], 22)
               . $r['last_at']
               . PHP_EOL;
        }
    }
    echo PHP_EOL;

    // 3d) Region distribuce (všech časů — pro porovnání)
    echo "  🗺️  WORKFLOW_LOG všech časů — region distribuce (Zkontrolováno tab):" . PHP_EOL;
    $regAllStmt = $pdo->prepare(
        "SELECT c.region,
                COUNT(*) AS log_entries,
                COUNT(DISTINCT wl.contact_id) AS unique_contacts,
                MIN(wl.created_at) AS first_at,
                MAX(wl.created_at) AS last_at
         FROM workflow_log wl
         JOIN contacts c ON c.id = wl.contact_id
         WHERE wl.user_id = ?
           AND wl.new_status IN ('READY','VF_SKIP')
         GROUP BY c.region
         ORDER BY unique_contacts DESC"
    );
    $regAllStmt->execute([$uid]);
    $regsAll = $regAllStmt->fetchAll();
    if (!$regsAll) {
        echo "     (žádná ověření zatím)" . PHP_EOL;
    } else {
        echo "     " . str_pad('region', 18) . str_pad('log entries', 13) . str_pad('uniq', 6) . str_pad('first_at', 22) . 'last_at' . PHP_EOL;
        echo "     " . str_repeat('-', 80) . PHP_EOL;
        foreach ($regsAll as $r) {
            echo "     "
               . str_pad((string) $r['region'], 18)
               . str_pad((string) $r['log_entries'], 13)
               . str_pad((string) $r['unique_contacts'], 6)
               . str_pad((string) $r['first_at'], 22)
               . $r['last_at']
               . PHP_EOL;
        }
    }
    echo PHP_EOL;

    // 3d2) Aktuální stav posledních 30 verifikovaných kontaktů
    //      Důvod: ukázat, že po verifikaci kontakt OPUSTÍ K-ověření (stav už
    //      není 'NEW') a přesune se k navolavacce (stav 'READY' nebo 'VF_SKIP').
    //      Toto vysvětluje "kam ty kontakty zmizeli" — nikam, jen jsou v jiném stavu.
    echo "  📦 AKTUÁLNÍ STAV posledních 30 verifikovaných kontaktů (po klikání čističky):" . PHP_EOL;
    $stateStmt = $pdo->prepare(
        "SELECT c.id, c.firma, c.region, c.stav, c.operator,
                wl.created_at AS verified_at
         FROM contacts c
         JOIN workflow_log wl ON wl.contact_id = c.id
         INNER JOIN (
             SELECT contact_id, MAX(id) AS last_id
             FROM workflow_log
             WHERE user_id = ? AND new_status IN ('READY','VF_SKIP')
             GROUP BY contact_id
         ) latest ON latest.last_id = wl.id
         ORDER BY wl.created_at DESC
         LIMIT 30"
    );
    $stateStmt->execute([$uid]);
    $statesRows = $stateStmt->fetchAll();
    if (!$statesRows) {
        echo "     (žádná verifikace zatím)" . PHP_EOL;
    } else {
        echo "     " . str_pad('id', 8) . str_pad('region', 14) . str_pad('stav', 10) . str_pad('op', 5) . str_pad('verified_at', 24) . 'firma' . PHP_EOL;
        echo "     " . str_repeat('-', 100) . PHP_EOL;
        $stavCounts = ['NEW' => 0, 'READY' => 0, 'VF_SKIP' => 0, 'OTHER' => 0];
        foreach ($statesRows as $r) {
            $stav = (string) $r['stav'];
            $key = isset($stavCounts[$stav]) ? $stav : 'OTHER';
            $stavCounts[$key]++;
            echo "     "
               . str_pad('#' . $r['id'], 8)
               . str_pad((string) ($r['region'] ?? ''), 14)
               . str_pad($stav, 10)
               . str_pad((string) ($r['operator'] ?? '?'), 5)
               . str_pad((string) $r['verified_at'], 24)
               . substr((string) $r['firma'], 0, 40)
               . PHP_EOL;
        }
        echo "     " . str_repeat('-', 100) . PHP_EOL;
        echo "     SOUČTY (z " . count($statesRows) . " posledních): "
           . "NEW (vrácený undo): {$stavCounts['NEW']}, "
           . "READY (=čeká na navolavacku): {$stavCounts['READY']}, "
           . "VF_SKIP (mimo workflow): {$stavCounts['VF_SKIP']}"
           . PHP_EOL;
    }
    echo PHP_EOL;

    // 3e) Vypočítaný progress (přesně jako v controlleru)
    echo "  📊 VYPOČÍTANÝ PROGRESS (pro aktuální měsíc, podle goals):" . PHP_EOL;
    $goalsCurrent = $pdo->prepare(
        "SELECT region, monthly_target, priority FROM cisticka_region_goals
         WHERE monthly_target > 0 AND period_yyyymm = ?
         ORDER BY priority ASC, region ASC"
    );
    $goalsCurrent->execute([$currentYM]);
    $gc = $goalsCurrent->fetchAll();
    if (!$gc) {
        echo "     (pro aktuální měsíc nejsou žádné goals — viz výše)" . PHP_EOL;
    } else {
        $regions = array_map(static fn($r) => $r['region'], $gc);
        $ph      = implode(',', array_fill(0, count($regions), '?'));
        $progressStmt = $pdo->prepare(
            "SELECT c.region, COUNT(DISTINCT wl.contact_id) AS done
             FROM workflow_log wl
             JOIN contacts c ON c.id = wl.contact_id
             WHERE wl.user_id = ?
               AND wl.new_status IN ('READY','VF_SKIP')
               AND wl.created_at BETWEEN ? AND ?
               AND c.region IN ($ph)
             GROUP BY c.region"
        );
        $progressStmt->execute(array_merge([$uid, $mStart, $mEnd], $regions));
        $progressMap = [];
        foreach ($progressStmt->fetchAll() as $p) {
            $progressMap[(string) $p['region']] = (int) $p['done'];
        }

        echo "     " . str_pad('region', 18) . str_pad('target', 8) . str_pad('done', 8) . 'progress' . PHP_EOL;
        echo "     " . str_repeat('-', 50) . PHP_EOL;
        foreach ($gc as $g) {
            $reg    = (string) $g['region'];
            $target = (int) $g['monthly_target'];
            $done   = (int) ($progressMap[$reg] ?? 0);
            $pct    = $target > 0 ? min(100, (int) round($done / $target * 100)) : 0;
            echo "     "
               . str_pad($reg, 18)
               . str_pad((string) $target, 8)
               . str_pad((string) $done, 8)
               . "{$done}/{$target} = {$pct}%"
               . PHP_EOL;
        }
    }
    echo PHP_EOL;
}

echo hr();
echo PHP_EOL;
echo "💡 INTERPRETACE:" . PHP_EOL;
echo PHP_EOL;
echo "1️⃣  Pokud sekce 'WORKFLOW_LOG všech časů' obsahuje VÍC záznamů než" . PHP_EOL;
echo "    'WORKFLOW_LOG v aktuálním měsíci', pak vyčištěné kontakty existují" . PHP_EOL;
echo "    v DB, ale NEJSOU v aktuálním měsíci → progress = 0 je SPRÁVNÉ chování" . PHP_EOL;
echo "    (cíle jsou měsíční, počítají se jen v daném kalendářním měsíci)." . PHP_EOL;
echo PHP_EOL;
echo "2️⃣  AKTUÁLNÍ STAV posledních 30 verifikovaných ti řekne, kam kontakty" . PHP_EOL;
echo "    'zmizely':" . PHP_EOL;
echo "    - stav=NEW   → kontakt byl vrácen (undo) zpět do K-ověření fronty" . PHP_EOL;
echo "    - stav=READY → kontakt přešel k navolavacce (TM nebo O2 operator)" . PHP_EOL;
echo "    - stav=VF_SKIP → kontakt je mimo workflow (čistička klikla VF)" . PHP_EOL;
echo "    Všechny 3 stavy jsou vidět v Zkontrolováno tabu (čistička je vidí)." . PHP_EOL;
echo PHP_EOL;
echo "3️⃣  Pokud aktuální měsíc obsahuje záznamy, ale progress map nic nevrací," . PHP_EOL;
echo "    je problém v REGION shodě (case sensitivity nebo space)." . PHP_EOL;
echo PHP_EOL;
echo "4️⃣  Pokud goals jsou v jiném period_yyyymm než aktuální měsíc," . PHP_EOL;
echo "    migrace #3 nebyla správně aplikována — pošli mi výpis a opravím." . PHP_EOL;
echo PHP_EOL;
