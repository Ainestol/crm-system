<?php
// e:\Snecinatripu\app\views\caller\index.php
declare(strict_types=1);
/** @var array<string, mixed>                         $user */
/** @var list<array<string, mixed>>                   $contacts */
/** @var array<string, int>                           $tabCounts */
/** @var string                                       $tab */
/** @var string|null                                  $flash */
/** @var string                                       $csrf */
/** @var array<string, list<array<string, mixed>>>    $salesByRegion */
/** @var list<array<string, mixed>>                   $allSalesList */
/** @var int                                          $defaultSalesId */
/** @var array<string, mixed>                         $todayStats */
/** @var array<string, mixed>                         $dailyGoal */
/** @var float                                        $rewardPerWin */
/** @var list<string>                                 $availableRegions */
/** @var string                                       $selectedRegion */
/** @var array<string, int>                           $regionCounts */
/** @var int                                          $totalCount */
/** @var int                                          $totalPages */
/** @var int                                          $page */
/** @var array<int, array<string, array{received:int,target:int}>> $ozProgress */

function callerPageUrl(string $tab, int $p, string $region): string {
    $q = ['tab' => $tab, 'page' => $p];
    if ($region !== '') $q['region'] = $region;
    return crm_url('/caller?' . http_build_query($q));
}

function callerPagination(int $page, int $totalPages, string $tab, string $selectedRegion): void {
    if ($totalPages <= 1) return;
    echo '<div class="cist-pagination" style="margin-top:1rem;">';
    if ($page > 1) {
        echo '<a href="' . crm_h(callerPageUrl($tab, $page - 1, $selectedRegion)) . '" class="btn btn-secondary btn-sm">← Předchozí</a>';
    } else {
        echo '<span class="cist-page-btn cist-page-disabled">←</span>';
    }
    $from = max(1, $page - 3); $to = min($totalPages, $page + 3);
    if ($from > 1) {
        echo '<a href="' . crm_h(callerPageUrl($tab, 1, $selectedRegion)) . '" class="cist-page-btn">1</a>';
        if ($from > 2) echo '<span class="cist-page-dots">…</span>';
    }
    for ($i = $from; $i <= $to; $i++) {
        if ($i === $page) echo '<span class="cist-page-btn cist-page-current">' . $i . '</span>';
        else echo '<a href="' . crm_h(callerPageUrl($tab, $i, $selectedRegion)) . '" class="cist-page-btn">' . $i . '</a>';
    }
    if ($to < $totalPages) {
        if ($to < $totalPages - 1) echo '<span class="cist-page-dots">…</span>';
        echo '<a href="' . crm_h(callerPageUrl($tab, $totalPages, $selectedRegion)) . '" class="cist-page-btn">' . $totalPages . '</a>';
    }
    if ($page < $totalPages) {
        echo '<a href="' . crm_h(callerPageUrl($tab, $page + 1, $selectedRegion)) . '" class="btn btn-secondary btn-sm">Další →</a>';
    } else {
        echo '<span class="cist-page-btn cist-page-disabled">→</span>';
    }
    echo '</div>';
}

$todayWins  = (int) ($todayStats['wins_today'] ?? 0);
$targetWins = (int) ($dailyGoal['target_wins'] ?? 0);
$dayPct     = $targetWins > 0 ? min(100, (int) round($todayWins / $targetWins * 100)) : 0;
$dayOver    = $targetWins > 0 && $todayWins >= $targetWins;
$dayExtra   = max(0, $todayWins - $targetWins);

// Měsíční proměnné (z controlleru)
$mWins     = (int) ($monthWins     ?? 0);
$mChybne   = (int) ($monthChybne   ?? 0);
$mWinsPaid = (int) ($monthWinsPaid ?? $mWins);
$mTgt      = (int) ($monthlyGoal['target_wins']    ?? 150);
$mT1       = (int) ($mT1           ?? $mTgt);
$mT2       = (int) ($mT2           ?? (int) round($mTgt * 1.2));
$mPct      = $mTgt > 0 ? min(100, (int) round($mWins / $mTgt * 100)) : 0;
$mEarnings = (float) ($monthEarnings ?? 0.0);
$mProjected= (int) ($projectedWins  ?? $mWins);
$mLeft     = (int) ($workDaysLeft   ?? 0);
?>
<section class="card">
    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info"><?= crm_h($flash) ?></p>
    <?php } ?>

    <!-- ── Závod šneků 🐌 (nad progress bary) ── -->
    <div class="snail-race" id="snail-race-box">
        <?php
        $raceMonths = ['', 'Leden','Únor','Březen','Duben','Květen','Červen',
                       'Červenec','Srpen','Září','Říjen','Listopad','Prosinec'];
        ?>
        <div class="snail-race__title">🐌 Šněčí závody &mdash; <?= crm_h($raceMonths[(int) date('n')] . ' ' . date('Y')) ?></div>
        <div id="snail-race-inner">
            <div class="snail-loading">Načítám závod…</div>
        </div>
    </div>

    <!-- ── Horní panel: Výchozí OZ + Progress bar ── -->
    <div class="caller-topbar">

        <!-- Výchozí OZ (uloží se do session) -->
        <?php if (!empty($allSalesList)) { ?>
        <div class="default-sales-panel">
            <span class="label-sm">Výchozí OZ:</span>
            <form method="post" action="<?= crm_h(crm_url('/caller/set-default-sales')) ?>"
                  class="default-sales-form">
                <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                <select name="sales_id" class="input-sales">
                    <option value="0"<?= $defaultSalesId === 0 ? ' selected' : '' ?>>— nevybráno —</option>
                    <?php foreach ($allSalesList as $s) {
                        $sel = ((int) $s['id'] === $defaultSalesId) ? ' selected' : '';
                    ?>
                        <option value="<?= (int) $s['id'] ?>"<?= $sel ?>><?= crm_h((string) $s['jmeno']) ?></option>
                    <?php } ?>
                </select>
                <button type="submit" class="btn btn-secondary btn-sm">Uložit</button>
            </form>
            <?php if ($defaultSalesId > 0) {
                $defName = '';
                foreach ($allSalesList as $s) {
                    if ((int) $s['id'] === $defaultSalesId) { $defName = $s['jmeno']; break; }
                }
            ?>
                <span class="default-sales-tag">📌 <?= crm_h($defName) ?></span>
            <?php } ?>
        </div>
        <?php } ?>

        <!-- Denní progress (výhry) -->
        <?php if ($targetWins > 0) { ?>
        <div class="progress-panel <?= $dayOver ? 'progress-panel--over' : '' ?>">
            <div class="progress-header">
                <?php if ($dayOver) { ?>
                    <span class="progress-title">🎉 Denní cíl splněn!</span>
                    <span class="progress-count">
                        <?= $todayWins ?> / <?= $targetWins ?> výher
                        <?php if ($dayExtra > 0) { ?><span class="progress-extra">+<?= $dayExtra ?> navíc</span><?php } ?>
                    </span>
                <?php } else { ?>
                    <span class="progress-title">Výhry dnes</span>
                    <span class="progress-count"><?= $todayWins ?> / <?= $targetWins ?></span>
                <?php } ?>
            </div>
            <div class="progress-track">
                <div class="progress-fill <?= $dayOver ? 'progress-fill--over' : '' ?>"
                     style="width:<?= $dayPct ?>%"></div>
                <?php if (!$dayOver) { ?>
                    <span class="progress-remain">ještě <?= $targetWins - $todayWins ?></span>
                <?php } ?>
            </div>
            <?php if ($rewardPerWin > 0) { ?>
            <div class="progress-footer">
                <span class="reward-info">
                    <?= number_format($rewardPerWin, 0, ',', ' ') ?> Kč / výhra
                </span>
                <span class="progress-wins">✓ <?= $todayWins ?> dnes</span>
            </div>
            <?php } ?>
        </div>
        <?php } ?>

        <!-- Měsíční progress + bonusy (jen pokud je motivační systém zapnutý) -->
        <?php if ($mTgt > 0 && (bool) ($monthlyGoal['motiv_enabled'] ?? false)) {
            $mBar1Pct = $mT1 > 0 ? min(100, (int) round($mT1 / $mTgt * 100)) : 100;
            $mBar2Pct = $mT2 > 0 ? min(100, (int) round($mT2 / $mTgt * 100)) : 100;
        ?>
        <div class="progress-panel progress-panel--month">
            <div class="progress-header">
                <span class="progress-title">📅 <?= date('F') ?></span>
                <span class="progress-count">
                    <?= $mWins ?> / <?= $mTgt ?> výher
                    <span class="muted" style="font-size:0.78rem;margin-left:0.4rem;">(<?= $mPct ?> %)</span>
                </span>
            </div>

            <!-- Progress bar s pásmy bonusů -->
            <div class="progress-track progress-track--month" style="position:relative;">
                <div class="progress-fill <?= $mWins >= $mTgt ? 'progress-fill--over' : '' ?>"
                     style="width:<?= $mPct ?>%"></div>
                <!-- Pásmo 1: marker -->
                <?php if ($mT1 <= $mTgt) { ?>
                <div class="month-tier-mark" style="left:<?= $mBar1Pct ?>%;"
                     title="1. bonus: <?= $mT1 ?> výher (+<?= number_format((float)($monthlyGoal['bonus1_pct'] ?? 5), 1) ?> %)">
                </div>
                <?php } ?>
                <!-- Pásmo 2: marker -->
                <?php if ($mT2 > $mTgt) { ?>
                <div class="month-tier-mark month-tier-mark--2" style="left:<?= $mBar2Pct ?>%;"
                     title="2. bonus: <?= $mT2 ?> výher (+<?= number_format((float)($monthlyGoal['bonus2_pct'] ?? 5), 1) ?> %)">
                </div>
                <?php } ?>
            </div>

            <!-- Info pásy -->
            <div class="month-tiers">
                <span class="month-tier <?= $mWins < $mT1 ? 'month-tier--active' : 'month-tier--done' ?>">
                    1–<?= $mT1 ?>: <?= number_format($rewardPerWin, 0, ',', ' ') ?> Kč
                </span>
                <span class="month-tier <?= ($mWins >= $mT1 && $mWins < $mT2) ? 'month-tier--active' : ($mWins >= $mT1 ? 'month-tier--done' : '') ?>">
                    <?= ($mT1+1) ?>–<?= $mT2 ?>: <?= number_format($rewardPerWin * (1 + (float)($monthlyGoal['bonus1_pct'] ?? 5)/100), 0, ',', ' ') ?> Kč
                    <span style="color:#f0a030;font-size:0.75rem;">+<?= number_format((float)($monthlyGoal['bonus1_pct'] ?? 5), 1) ?>%</span>
                </span>
                <span class="month-tier <?= $mWins >= $mT2 ? 'month-tier--active month-tier--gold' : '' ?>">
                    <?= ($mT2+1) ?>+: <?= number_format($rewardPerWin * (1 + (float)($monthlyGoal['bonus1_pct'] ?? 5)/100 + (float)($monthlyGoal['bonus2_pct'] ?? 5)/100), 0, ',', ' ') ?> Kč
                    <span style="color:#f0a030;font-size:0.75rem;">+<?= number_format((float)($monthlyGoal['bonus1_pct'] ?? 5) + (float)($monthlyGoal['bonus2_pct'] ?? 5), 1) ?>%</span>
                </span>
            </div>

            <div class="progress-footer" style="margin-top:0.5rem;flex-wrap:wrap;gap:0.4rem;">
                <?php if ($mEarnings > 0) { ?>
                <span class="reward-highlight">
                    💰 Výdělek měsíc: <strong><?= number_format($mEarnings, 0, ',', ' ') ?> Kč</strong>
                </span>
                <?php } ?>
                <?php if ($mChybne > 0) { ?>
                <span style="font-size:0.75rem;color:#f39c12;background:rgba(243,156,18,0.1);
                             border:1px solid rgba(243,156,18,0.3);border-radius:5px;
                             padding:0.15rem 0.5rem;display:inline-flex;align-items:center;gap:0.3rem;"
                      title="Výhry <?= $mWins ?> − chybné leady <?= $mChybne ?> = placené <?= $mWinsPaid ?>">
                    ⚠ <?= $mWins ?> výher − <?= $mChybne ?> chybných = <strong><?= $mWinsPaid ?> placených</strong>
                </span>
                <?php } ?>
                <?php if ($mLeft > 0 && $mProjected !== $mWins) { ?>
                <span class="reward-info" style="font-size:0.8rem;">
                    Odhad: ~<?= $mProjected ?> výher · zbývá <?= $mLeft ?> prac. dní
                </span>
                <?php } ?>
            </div>
        </div>
        <?php } ?>

    </div><!-- /.caller-topbar -->

    <!-- ══════════════════════════════════════════════════════════════
         WIDGET: Volné kvóty OZ × kraj  (read-only, sbalený default)

         Pomáhá navolávačce vidět "kde mám ještě navolávat" — kdo
         z OZ má v jakém kraji volné místo do naplnění měsíční kvóty.
         Žádné akce — jen přehled. Data se počítají v
         CallerController::index() jako $ozProgress[uid][region].
         Po přidání kontaktu se přepočte při dalším refreshi stránky.
    ══════════════════════════════════════════════════════════════ -->
    <?php
    // Měsíční navigace pro widget — proměnné nastavené v controlleru
    /** @var int  $cqYear */
    /** @var int  $cqMonth */
    /** @var bool $cqIsCurrent */
    $cqYear      = $cqYear      ?? (int) date('Y');
    $cqMonth     = $cqMonth     ?? (int) date('n');
    $cqIsCurrent = $cqIsCurrent ?? true;
    $cqMonthNames = ['', 'Leden','Únor','Březen','Duben','Květen','Červen',
                     'Červenec','Srpen','Září','Říjen','Listopad','Prosinec'];
    $cqPrevM = $cqMonth - 1; $cqPrevY = $cqYear;
    if ($cqPrevM < 1) { $cqPrevM = 12; $cqPrevY--; }
    $cqNextM = $cqMonth + 1; $cqNextY = $cqYear;
    if ($cqNextM > 12) { $cqNextM = 1; $cqNextY++; }
    // Zachovat aktuální tab/region/page při navigaci.
    // Anchor #cq-widget zajistí, že po reloadu browser scrollne přesně k widgetu —
    // bez něho stránka skáče na top a widget "uskakuje".
    $cqBaseQuery = ['tab' => $tab, 'page' => $page];
    if ($selectedRegion !== '') { $cqBaseQuery['region'] = $selectedRegion; }
    $cqMakeUrl = static fn(int $y, int $m): string =>
        crm_url('/caller?' . http_build_query($cqBaseQuery + ['cq_year' => $y, 'cq_month' => $m]))
        . '#cq-widget';
    ?>
    <?php
    // Spočítat OZ-y, kteří mají něco v $ozProgress, a seřadit je podle jména
    $cqOzList = [];
    foreach ($allSalesList as $s) {
        $sId = (int) ($s['id'] ?? 0);
        if (isset($ozProgress[$sId])) {
            $cqOzList[$sId] = (string) ($s['jmeno'] ?? '—');
        }
    }
    // Spočítat regiony, které se objevují u kteréhokoli OZ (sloupce tabulky)
    $cqRegionsSet = [];
    foreach ($ozProgress as $regs) {
        foreach ($regs as $r => $_) {
            $cqRegionsSet[(string) $r] = true;
        }
    }
    $cqRegions = array_keys($cqRegionsSet);
    sort($cqRegions);

    // Spočítat globální celkové ukazatele (kolik už je / kolik chybí)
    $cqTotalRec = 0; $cqTotalTgt = 0;
    foreach ($ozProgress as $regs) {
        foreach ($regs as $d) {
            $cqTotalRec += (int) ($d['received'] ?? 0);
            $cqTotalTgt += (int) ($d['target']   ?? 0);
        }
    }
    $cqTotalRem  = max(0, $cqTotalTgt - $cqTotalRec);
    $cqHasData   = $ozProgress !== [];
    // Widget se zobrazí VŽDY (i pro prázdné měsíce), ať uživatel může navigovat
    // zpět na aktuální měsíc. Empty state je uvnitř těla.
    ?>
    <details id="cq-widget" class="cq-widget">
        <summary class="cq-widget__summary">
            <span class="cq-widget__title">📊 Volné kvóty OZ</span>
            <span class="cq-widget__hint">
                — <?= crm_h($cqMonthNames[$cqMonth] . ' ' . $cqYear) ?>
                <?php if (!$cqIsCurrent) { ?>
                    <span style="color:#d97706;font-weight:600;">(historický pohled)</span>
                <?php } ?>
            </span>
            <?php if ($cqTotalTgt > 0) { ?>
                <span class="cq-widget__inline">
                    <strong><?= $cqTotalRec ?></strong> / <?= $cqTotalTgt ?>
                    <?php if ($cqTotalRem > 0) { ?>
                        · <span class="cq-widget__rem">zbývá <?= $cqTotalRem ?></span>
                    <?php } else { ?>
                        · <span style="color:#2ecc71;">✓ kvóty splněné</span>
                    <?php } ?>
                </span>
            <?php } elseif (!$cqHasData) { ?>
                <span class="cq-widget__inline" style="font-style:italic;color:var(--color-text-muted);">
                    žádná data pro tento měsíc
                </span>
            <?php } ?>
            <span class="cq-widget__chevron">▾</span>
        </summary>
        <div class="cq-widget__body">
            <!-- Měsíční navigace (← / měsíc / → / Aktuální) + PDF export výplaty -->
            <div class="cq-month-nav">
                <a href="<?= crm_h($cqMakeUrl($cqPrevY, $cqPrevM)) ?>"
                   class="cq-month-btn" title="Předchozí měsíc">←</a>
                <span class="cq-month-label">
                    <?= crm_h($cqMonthNames[$cqMonth] . ' ' . $cqYear) ?>
                </span>
                <a href="<?= crm_h($cqMakeUrl($cqNextY, $cqNextM)) ?>"
                   class="cq-month-btn" title="Další měsíc">→</a>
                <?php if (!$cqIsCurrent) { ?>
                    <a href="<?= crm_h($cqMakeUrl((int)date('Y'), (int)date('n'))) ?>"
                       class="cq-month-btn cq-month-btn--current">Aktuální měsíc</a>
                <?php } ?>
                <!-- PDF: kolik dostanu od kterého OZ za vybraný měsíc -->
                <a href="<?= crm_h(crm_url('/caller/payout/print?year=' . $cqYear . '&month=' . $cqMonth)) ?>"
                   target="_blank" rel="noopener"
                   class="cq-month-btn"
                   style="margin-left:auto;background:#d4f4dd;color:#1f7a3a;border-color:#9fdcb1;font-weight:700;"
                   title="Otevře tiskovou stránku s detaily kolik dostaneš od kterého OZ-a za <?= crm_h($cqMonthNames[$cqMonth] . ' ' . $cqYear) ?>">
                    💰 Moje výplata (PDF)
                </a>
            </div>
            <?php if (!$cqHasData) { ?>
                <!-- Empty state — žádné kvóty ani kontakty pro vybraný měsíc -->
                <div class="cq-widget__empty">
                    <div class="cq-widget__empty-icon">📭</div>
                    <div class="cq-widget__empty-hint">
                        <span class="cq-widget__empty-title">
                            V <?= crm_h($cqMonthNames[$cqMonth] . ' ' . $cqYear) ?> nejsou žádná data
                        </span>
                        — žádné kvóty ani navolané kontakty.
                        <?php if (!$cqIsCurrent) { ?>
                            <a href="<?= crm_h($cqMakeUrl((int)date('Y'), (int)date('n'))) ?>">
                                ← Zpět na aktuální měsíc
                            </a>
                        <?php } ?>
                    </div>
                </div>
            <?php } else { ?>
            <div class="cq-widget__lead">
                Tabulka ukazuje <strong>received / target</strong> per OZ × kraj za <strong><?= crm_h($cqMonthNames[$cqMonth] . ' ' . $cqYear) ?></strong>.
                Tmavší barva = víc chybí. Buňka „—" znamená, že OZ v tomto kraji nemá kvótu.
            </div>
            <div class="cq-table-wrap">
                <table class="cq-table">
                    <thead>
                        <tr>
                            <th>OZ</th>
                            <?php foreach ($cqRegions as $r) { ?>
                                <th title="<?= crm_h(crm_region_label($r)) ?>"><?= crm_h(ucfirst($r)) ?></th>
                            <?php } ?>
                            <th>Celkem</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($cqOzList as $oid => $oname) {
                        $rowRec = 0; $rowTgt = 0;
                    ?>
                        <tr>
                            <td class="cq-cell-name"><?= crm_h($oname) ?></td>
                            <?php foreach ($cqRegions as $r) {
                                $cell = $ozProgress[$oid][$r] ?? null;
                                if ($cell === null) { ?>
                                    <td class="cq-cell-empty">—</td>
                                <?php } else {
                                    $rec = (int) ($cell['received'] ?? 0);
                                    $tgt = (int) ($cell['target']   ?? 0);
                                    $rowRec += $rec;
                                    $rowTgt += $tgt;
                                    $rem = max(0, $tgt - $rec);
                                    // Barva podle naplnění (heatmap):
                                    //  ≥100% = zelená (splněno), 50-99% = žlutá, <50% = červená, 0% = nejtmavší
                                    $cls = 'cq-cell-fill cq-cell-fill--';
                                    if ($tgt === 0) {
                                        // OZ má příjem ale žádnou kvótu (overflow)
                                        $cls .= 'over';
                                    } elseif ($rec >= $tgt) {
                                        $cls .= 'done';
                                    } elseif ($rec === 0) {
                                        $cls .= 'zero';
                                    } else {
                                        $pct = $rec / $tgt;
                                        $cls .= $pct >= 0.5 ? 'mid' : 'low';
                                    }
                                ?>
                                    <td class="<?= $cls ?>"
                                        title="<?= crm_h(crm_region_label($r)) ?>: <?= $rec ?> z <?= $tgt ?> (<?= $rem ?> zbývá)">
                                        <strong><?= $rec ?></strong><span class="cq-cell-tgt">/<?= $tgt ?></span>
                                    </td>
                                <?php }
                            } ?>
                            <td class="cq-cell-total">
                                <?php if ($rowTgt > 0) { ?>
                                    <strong><?= $rowRec ?></strong>/<?= $rowTgt ?>
                                <?php } else { ?>
                                    <?= $rowRec ?>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
            <div class="cq-widget__legend">
                <span class="cq-cell-fill cq-cell-fill--zero">0&nbsp;</span> nikdo
                <span class="cq-cell-fill cq-cell-fill--low">&nbsp;&lt;50&nbsp;%&nbsp;</span> nízko
                <span class="cq-cell-fill cq-cell-fill--mid">&nbsp;50–99&nbsp;%&nbsp;</span> postup
                <span class="cq-cell-fill cq-cell-fill--done">&nbsp;✓&nbsp;</span> splněno
            </div>
            <?php } /* end cqHasData */ ?>
        </div>
    </details>
    <script>
    // Widget si pamatuje stav (open/closed) v localStorage — konzistentní napříč
    // přepínáním měsíců i reloady. Inline script ihned po elementu = aplikuje
    // se před prvním renderem, takže widget "neproblikne" v defaultním stavu.
    (function () {
        var KEY  = 'cq_widget_open';
        var el   = document.getElementById('cq-widget');
        if (!el) return;
        try {
            // Aplikovat uložený stav (default = sbalený, žádná hodnota = false)
            if (localStorage.getItem(KEY) === '1') {
                el.setAttribute('open', '');
            } else {
                el.removeAttribute('open');
            }
        } catch (e) { /* localStorage zakázané → nech default */ }

        // Uložit stav po každém toggle
        el.addEventListener('toggle', function () {
            try {
                localStorage.setItem(KEY, el.open ? '1' : '0');
            } catch (e) { /* nech být */ }
        });
    })();
    </script>

    <!-- Filtr krajů (jen pro tab K provolání) -->
    <?php if ($tab === 'aktivni' && $availableRegions !== []) { ?>
        <div class="cist-region-filter">
            <span class="cist-region-label">Kraj:</span>
            <a href="<?= crm_h(crm_url('/caller?' . http_build_query(['tab' => 'aktivni', 'page' => 1]))) ?>"
               class="cist-region-btn <?= $selectedRegion === '' ? 'cist-region-btn--active' : '' ?>">
                🗺️ Vše
            </a>
            <?php foreach ($availableRegions as $reg) {
                $cnt = $regionCounts[$reg] ?? 0;
            ?>
                <a href="<?= crm_h(crm_url('/caller?' . http_build_query(['tab' => 'aktivni', 'page' => 1, 'region' => $reg]))) ?>"
                   class="cist-region-btn <?= $selectedRegion === $reg ? 'cist-region-btn--active' : '' ?>">
                    <?= crm_h(crm_region_label($reg)) ?>
                    <span class="cist-region-cnt"><?= $cnt ?></span>
                </a>
            <?php } ?>
        </div>
    <?php } ?>

    <!-- Taby -->
    <div class="tabs">
        <a href="<?= crm_h(crm_url('/caller?tab=aktivni')) ?>"
           class="tab tab--aktivni <?= $tab === 'aktivni' ? 'tab--active' : '' ?>">
            K provolání <span class="badge badge--aktivni" id="badge-aktivni"><?= (int) ($tabCounts['aktivni'] ?? 0) ?></span>
        </a>
        <a href="<?= crm_h(crm_url('/caller?tab=callback')) ?>"
           class="tab tab--callback <?= $tab === 'callback' ? 'tab--active' : '' ?>">
            Callbacky <span class="badge badge--cb"><?= (int) ($tabCounts['callback'] ?? 0) ?></span>
        </a>
        <a href="<?= crm_h(crm_url('/caller/calendar')) ?>"
           class="tab tab--calendar" title="Kalendář callbacků">
            📅 Kalendář
        </a>
        <?php if (($tabCounts['nedovolano'] ?? 0) > 0 || $tab === 'nedovolano') { ?>
        <a href="<?= crm_h(crm_url('/caller?tab=nedovolano')) ?>"
           class="tab tab--nedovolano <?= $tab === 'nedovolano' ? 'tab--active' : '' ?>">
            Nedovoláno <span class="badge badge--nedovolano"><?= (int) ($tabCounts['nedovolano'] ?? 0) ?></span>
        </a>
        <?php } ?>
        <a href="<?= crm_h(crm_url('/caller?tab=navolane')) ?>"
           class="tab tab--win <?= $tab === 'navolane' ? 'tab--active' : '' ?>">
            Navolané <span class="badge badge--win"><?= (int) ($tabCounts['navolane'] ?? 0) ?></span>
        </a>
        <a href="<?= crm_h(crm_url('/caller?tab=prohra')) ?>"
           class="tab tab--loss <?= $tab === 'prohra' ? 'tab--active' : '' ?>">
            Prohra <span class="badge badge--loss"><?= (int) ($tabCounts['prohra'] ?? 0) ?></span>
        </a>
        <?php if (($tabCounts['izolace'] ?? 0) > 0 || $tab === 'izolace') { ?>
        <a href="<?= crm_h(crm_url('/caller?tab=izolace')) ?>"
           class="tab tab--izolace <?= $tab === 'izolace' ? 'tab--active' : '' ?>">
            Izolace <span class="badge badge--izolace"><?= (int) ($tabCounts['izolace'] ?? 0) ?></span>
        </a>
        <?php } ?>
        <?php if (($tabCounts['chybny'] ?? 0) > 0 || $tab === 'chybny') { ?>
        <a href="<?= crm_h(crm_url('/caller?tab=chybny')) ?>"
           class="tab tab--chybny <?= $tab === 'chybny' ? 'tab--active' : '' ?>">
            Chybné <span class="badge badge--chybny"><?= (int) ($tabCounts['chybny'] ?? 0) ?></span>
        </a>
        <?php } ?>
        <?php if (($tabCounts['chybne_oz'] ?? 0) > 0 || $tab === 'chybne_oz') { ?>
        <a href="<?= crm_h(crm_url('/caller?tab=chybne_oz')) ?>"
           class="tab tab--chybne-oz <?= $tab === 'chybne_oz' ? 'tab--active' : '' ?>">
            ⚠ Chybné od OZ <span class="badge badge--chybne-oz"><?= (int) ($tabCounts['chybne_oz'] ?? 0) ?></span>
        </a>
        <?php } ?>
        <a href="<?= crm_h(crm_url('/caller/search')) ?>" class="tab tab--search">🔍 Hledat</a>
        <a href="<?= crm_h(crm_url('/caller/stats')) ?>" class="tab tab--stats">📊 Výkon</a>
    </div>

    <?php
    // ── Měsíční filtr (jen pro "rostoucí" taby: navolane, prohra, chybny, chybne_oz) ──
    if (!empty($useMonthFilter) && !empty($tabMonthOptions)) { ?>
        <form method="get"
              style="margin:0.6rem 0 0.4rem;display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
            <input type="hidden" name="tab" value="<?= crm_h($tab) ?>">
            <label for="caller-month-filter"
                   style="font-size:0.78rem;color:var(--muted);">📅 Měsíc:</label>
            <select id="caller-month-filter" name="month_key"
                    onchange="this.form.submit()"
                    style="background:var(--bg);color:var(--text);
                           border:1px solid rgba(0,0,0,0.15);border-radius:6px;
                           padding:0.25rem 0.55rem;font-size:0.78rem;cursor:pointer;
                           font-family:inherit;">
                <?php foreach ($tabMonthOptions as $opt) { ?>
                    <option value="<?= crm_h($opt['key']) ?>"
                            <?= $opt['key'] === ($selectedMonthKey ?? '') ? 'selected' : '' ?>>
                        <?= crm_h($opt['label']) ?>
                    </option>
                <?php } ?>
            </select>
            <noscript>
                <button type="submit"
                        style="padding:0.25rem 0.6rem;font-size:0.75rem;
                               background:var(--accent);color:#fff;border:0;border-radius:6px;cursor:pointer;">
                    Zobrazit
                </button>
            </noscript>
        </form>
    <?php } ?>

    <?php if ($tab === 'chybne_oz') { ?>
        <!-- ══ Tab: Chybné leady od OZ ══ -->
        <?php if ($contacts === []) { ?>
            <p class="muted" style="margin-top:1rem;">✅ Žádné chybné leady od OZ — skvělá práce!</p>
        <?php } else { ?>
        <div style="margin-top:0.75rem;display:flex;flex-direction:column;gap:0.55rem;">
            <p style="font-size:0.78rem;color:var(--muted);margin-bottom:0.1rem;">
                Tyto leady nahlásil obchodák jako chybně navolané. Nebudou ti proplaceny.
            </p>
            <?php foreach ($contacts as $c) {
                $cId             = (int)($c['id'] ?? 0);
                $firma           = crm_h((string)($c['firma']             ?? '—'));
                $tel             = crm_h((string)($c['telefon']           ?? ''));
                $ozName          = crm_h((string)($c['oz_name']           ?? '—'));
                $reason          = crm_h((string)($c['oz_reason']         ?? ''));
                $myComment       = (string)($c['caller_comment']          ?? '');
                $ozComment       = (string)($c['oz_comment']              ?? '');
                $callerConfirmed = (int)($c['caller_confirmed']            ?? 0);
                $ozConfirmed     = (int)($c['oz_confirmed']                ?? 0);
                $bothClosed      = $callerConfirmed === 1 && $ozConfirmed === 1;
                $flaggedAt       = !empty($c['oz_flagged_at'])
                                   ? crm_h(date('d.m.Y H:i', strtotime((string)$c['oz_flagged_at'])))
                                   : '';
                $region          = !empty($c['region']) ? crm_h(crm_region_label((string)$c['region'])) : '';
                $datVolani       = !empty($c['datum_volani'])
                                   ? crm_h(date('d.m.Y', strtotime((string)$c['datum_volani'])))
                                   : '';
            ?>
            <div style="background:var(--card);
                        border:1px solid <?= $bothClosed ? 'rgba(46,204,113,0.3)' : 'rgba(243,156,18,0.35)' ?>;
                        border-left:4px solid <?= $bothClosed ? '#2ecc71' : '#f39c12' ?>;
                        border-radius:0 8px 8px 0;padding:0.65rem 0.9rem;
                        display:flex;flex-direction:column;gap:0.35rem;">

                <!-- Hlavička: firma + status badge -->
                <div style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap;">
                    <strong style="font-size:0.92rem;"><?= $firma ?></strong>
                    <?php if ($tel !== '') { ?>
                        <span style="font-size:0.78rem;color:var(--accent);"><?= $tel ?></span>
                    <?php } ?>
                    <?php if ($region !== '') { ?>
                        <span style="font-size:0.7rem;color:var(--muted);"><?= $region ?></span>
                    <?php } ?>
                    <?php if ($datVolani !== '') { ?>
                        <span style="font-size:0.7rem;color:var(--muted);">navolán <?= $datVolani ?></span>
                    <?php } ?>
                    <?php if ($bothClosed) { ?>
                        <span style="margin-left:auto;font-size:0.68rem;background:rgba(46,204,113,0.15);
                                     color:#2ecc71;border-radius:4px;padding:0.1rem 0.45rem;font-weight:700;">
                            ✅ Uzavřeno
                        </span>
                    <?php } elseif ($callerConfirmed) { ?>
                        <span style="margin-left:auto;font-size:0.68rem;background:rgba(243,156,18,0.12);
                                     color:#f39c12;border-radius:4px;padding:0.1rem 0.45rem;">
                            ⏳ Čeká na OZ
                        </span>
                    <?php } else { ?>
                        <span style="margin-left:auto;font-size:0.68rem;background:rgba(243,156,18,0.15);
                                     color:#f39c12;border-radius:4px;padding:0.1rem 0.45rem;font-weight:700;">
                            ⚠ Čeká na vyjádření
                        </span>
                    <?php } ?>
                </div>

                <!-- OZ a důvod -->
                <div style="font-size:0.78rem;display:flex;gap:0.5rem;flex-wrap:wrap;">
                    <span style="color:var(--muted);">OZ: <strong style="color:var(--text);"><?= $ozName ?></strong></span>
                    <?php if ($reason !== '') { ?>
                        <span style="color:var(--muted);">Důvod: <em style="color:var(--text);">„<?= $reason ?>"</em></span>
                    <?php } ?>
                    <?php if ($flaggedAt !== '') { ?>
                        <span style="color:var(--muted);margin-left:auto;font-size:0.68rem;"><?= $flaggedAt ?></span>
                    <?php } ?>
                </div>

                <!-- Můj existující komentář -->
                <?php if ($myComment !== '') { ?>
                <div style="background:rgba(0,0,0,0.04);border-radius:5px;
                            padding:0.3rem 0.55rem;font-size:0.75rem;">
                    <span style="color:var(--muted);font-size:0.65rem;">Můj komentář: </span>
                    <em style="color:var(--text);"><?= crm_h($myComment) ?></em>
                </div>
                <?php } ?>

                <!-- Odpověď OZ (reakce obchodáka na námitku navolávačky) -->
                <?php if ($ozComment !== '') { ?>
                <div style="background:rgba(52,152,219,0.10);border-left:3px solid #3498db;
                            border-radius:0 5px 5px 0;padding:0.35rem 0.6rem;font-size:0.78rem;">
                    <div style="color:#3498db;font-size:0.65rem;font-weight:700;
                                text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.15rem;">
                        💬 Odpověď OZ (<?= $ozName ?>)
                    </div>
                    <span style="color:var(--text);white-space:pre-wrap;"><?= crm_h($ozComment) ?></span>
                </div>
                <?php } ?>

                <!-- Akce: komentář / přijetí / uzavřeno -->
                <?php if (!$bothClosed) { ?>
                <div style="margin-top:0.2rem;display:flex;flex-direction:column;gap:0.4rem;">
                    <!-- Formulář pro komentář nebo přijetí -->
                    <form method="post" action="<?= crm_h(crm_url('/caller/chybny-objection')) ?>">
                        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                        <input type="hidden" name="contact_id" value="<?= $cId ?>">
                        <textarea name="caller_comment"
                                  placeholder="Napište komentář — proč nesouhlasíte nebo jak to bylo…"
                                  style="width:100%;box-sizing:border-box;font-size:0.78rem;
                                         background:var(--bg);color:var(--text);
                                         border:1px solid rgba(243,156,18,0.35);border-radius:6px;
                                         padding:0.35rem 0.55rem;resize:vertical;min-height:52px;
                                         font-family:inherit;"
                                  rows="2"></textarea>
                        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.3rem;">
                            <button type="submit" name="action" value="comment"
                                    style="padding:0.3rem 0.75rem;font-size:0.75rem;
                                           background:rgba(243,156,18,0.12);color:#f39c12;
                                           border:1px solid rgba(243,156,18,0.35);border-radius:6px;cursor:pointer;">
                                💬 Odeslat komentář
                            </button>
                            <?php if (!$callerConfirmed) { ?>
                            <button type="submit" name="action" value="accept"
                                    onclick="return confirm('Přijmout a uzavřít z vaší strany?')"
                                    style="padding:0.3rem 0.75rem;font-size:0.75rem;
                                           background:rgba(46,204,113,0.12);color:#2ecc71;
                                           border:1px solid rgba(46,204,113,0.3);border-radius:6px;cursor:pointer;">
                                ✅ Přijímám — uzavřít z mé strany
                            </button>
                            <?php } ?>
                        </div>
                    </form>
                </div>
                <?php } ?>
            </div>
            <?php } ?>
        </div>
        <?php } ?>
    <?php } elseif ($contacts === []) { ?>
        <p class="muted" style="margin-top:1rem;">
            Žádné kontakty v této záložce.
            <?php if ($tab === 'aktivni') { ?>Požádejte majitele o přidělení kontaktů.<?php } ?>
        </p>
    <?php } else { ?>
        <div class="contact-list">
            <?php foreach ($contacts as $c) {
                $stav       = (string) ($c['stav'] ?? '');
                $region     = (string) ($c['region'] ?? '');
                $stavClass  = match ($stav) {
                    'CALLED_OK'      => 'status--win',
                    'CALLED_BAD'     => 'status--loss',
                    'NEZAJEM'        => 'status--loss',
                    'CALLBACK'       => 'status--callback',
                    'IZOLACE'        => 'status--izolace',
                    'CHYBNY_KONTAKT' => 'status--chybny',
                    'FOR_SALES'      => 'status--forsales',
                    'NEDOVOLANO'     => 'status--nedovolano',
                    default          => 'status--new',
                };
                $callbackAt     = (string) ($c['callback_at'] ?? '');
                $isOverdue      = $stav === 'CALLBACK' && $callbackAt !== '' && strtotime($callbackAt) <= time();
                $isSharedCb     = $stav === 'CALLBACK' && empty($c['assigned_caller_id']);
                $nedovolanoCount = (int) ($c['nedovolano_count'] ?? 0);
                // Akční tlačítka: vlastní kontakt, READY z poolu, nebo sdílený callback (assigned_caller_id IS NULL)
                $viewCallerId   = (int) ($user['id'] ?? 0);
                $isUnassigned   = ($c['assigned_caller_id'] ?? null) === null;
                $canAct         = ((int) ($c['assigned_caller_id'] ?? -1) === $viewCallerId)
                               || ($isUnassigned && in_array($stav, ['READY', 'CALLBACK'], true));

                // Operátorský badge (TM / O2 / VF)
                $opRaw        = strtoupper(trim((string) ($c['operator'] ?? '')));
                $opBadgeClass = match ($opRaw) {
                    'TM'    => 'op-tm',
                    'O2'    => 'op-o2',
                    'VF'    => 'op-vf',
                    default => '',
                };

                // OZ pro tuto oblast (s fallbackem)
                $regionSales  = $salesByRegion[$region] ?? [];
                $salesList    = $regionSales !== [] ? $regionSales : $allSalesList;
                $noSales      = $salesList === [];

                // Předvolba OZ: session nebo první v seznamu
                $preselect = $defaultSalesId > 0 ? $defaultSalesId : 0;
                $cId = (int) $c['id'];
            ?>
                <div class="contact-row <?= $stavClass ?> <?= $isOverdue ? 'contact-row--overdue' : '' ?>"
                     id="contact-row-<?= $cId ?>"
                     data-cid="<?= $cId ?>">
                    <div class="contact-info">

                        <!-- Název firmy (vždy editovatelný) -->
                        <div class="contact-name">
                            <span class="editable-val" id="val-firma-<?= $cId ?>"><?= crm_h((string) ($c['firma'] ?? '—')) ?></span>
                            <button type="button" class="btn-edit-field"
                                    onclick="crmEdit(<?= $cId ?>, 'firma', this)"
                                    title="Upravit název">✎</button>
                            <?php if ($opBadgeClass !== '') { ?>
                                <span class="cist-op-badge <?= $opBadgeClass ?>" style="font-size:0.68rem;padding:0.1rem 0.35rem;margin-left:0.4rem;opacity:0.7;vertical-align:middle;"><?= $opRaw ?></span>
                                <button type="button" class="btn-mismatch"
                                        onclick="crmFlagMismatch(<?= $cId ?>, this)"
                                        title="Operátor nesedí — nahlásit majiteli">⚡ nesedí</button>
                            <?php } ?>
                        </div>

                        <!-- Kontaktní údaje (vždy editovatelné) -->
                        <div class="contact-details">
                            <span class="editable-group">
                                <?php if (!empty($c['telefon'])) { ?>
                                    <a href="tel:<?= crm_h((string) $c['telefon']) ?>"
                                       class="contact-phone editable-val" id="val-telefon-<?= $cId ?>"><?= crm_h((string) $c['telefon']) ?></a>
                                <?php } else { ?>
                                    <span class="editable-val contact-muted" id="val-telefon-<?= $cId ?>">—</span>
                                <?php } ?>
                                <button type="button" class="btn-edit-field"
                                        onclick="crmEdit(<?= $cId ?>, 'telefon', this)"
                                        title="Upravit telefon">✎</button>
                            </span>

                            <span class="editable-group">
                                <span class="editable-val contact-email" id="val-email-<?= $cId ?>"><?= crm_h((string) ($c['email'] ?? '—')) ?></span>
                                <button type="button" class="btn-edit-field"
                                        onclick="crmEdit(<?= $cId ?>, 'email', this)"
                                        title="Upravit e-mail">✎</button>
                            </span>

                            <span class="editable-group">
                                <span class="label-sm">IČO:</span>
                                <span class="editable-val contact-muted" id="val-ico-<?= $cId ?>"><?= crm_h((string) ($c['ico'] ?? '—')) ?></span>
                                <button type="button" class="btn-edit-field"
                                        onclick="crmEdit(<?= $cId ?>, 'ico', this)"
                                        title="Upravit IČO">✎</button>
                            </span>

                            <span class="editable-group">
                                <span class="label-sm">Adresa:</span>
                                <span class="editable-val contact-city" id="val-adresa-<?= $cId ?>"><?= crm_h((string) ($c['adresa'] ?? '—')) ?></span>
                                <button type="button" class="btn-edit-field"
                                        onclick="crmEdit(<?= $cId ?>, 'adresa', this)"
                                        title="Upravit adresu / město">✎</button>
                            </span>
                        </div>

                        <!-- Kraj (read-only) -->
                        <?php if (!empty($c['region'])) { ?>
                        <div class="contact-details" style="margin-top:0.2rem;">
                            <span class="label-sm">Kraj:</span>
                            <span class="contact-muted"><?= crm_h(crm_region_label((string) $c['region'])) ?></span>
                        </div>
                        <?php } ?>

                        <?php if (!empty($c['poznamka'])) { ?>
                            <div class="contact-note"><?= crm_h((string) $c['poznamka']) ?></div>
                        <?php } ?>
                        <?php if ($stav === 'CALLBACK' && $callbackAt !== '') { ?>
                            <div class="contact-callback <?= $isOverdue ? 'cb-overdue' : '' ?>">
                                <?= $isSharedCb ? '🌐 Sdílený callback: ' : 'Callback: ' ?>
                                <?= crm_h(date('d.m.Y H:i', strtotime($callbackAt))) ?>
                                <?= $isOverdue ? ' — PROŠLÝ!' : '' ?>
                            </div>
                        <?php } ?>
                        <?php if ($stav === 'NEDOVOLANO' && $nedovolanoCount > 0) { ?>
                            <div class="contact-nedovolano-count">
                                📵 Nedovolání: <?= $nedovolanoCount ?> / 3
                                <?= $nedovolanoCount >= 2 ? ' — <strong>poslední pokus!</strong>' : '' ?>
                            </div>
                        <?php } ?>
                        <?php if (!empty($c['sales_name'])) { ?>
                            <div class="contact-sales">Obchodák: <?= crm_h((string) $c['sales_name']) ?></div>
                        <?php } ?>
                        <?php if ((int)($c['oz_flagged'] ?? 0) === 1) { ?>
                            <div style="margin-top:0.25rem;display:inline-flex;align-items:center;gap:0.4rem;
                                        background:rgba(243,156,18,0.1);border:1px solid rgba(243,156,18,0.35);
                                        border-left:3px solid #f39c12;border-radius:0 6px 6px 0;
                                        padding:0.2rem 0.55rem;font-size:0.72rem;">
                                <span style="color:#f39c12;font-weight:700;">⚠ Chybný lead</span>
                                <?php if (!empty($c['oz_flag_reason'])) { ?>
                                    <span style="color:var(--muted);">— <?= crm_h((string)$c['oz_flag_reason']) ?></span>
                                <?php } ?>
                                <?php if (!empty($c['oz_flag_by'])) { ?>
                                    <span style="color:var(--muted);font-size:0.65rem;">(OZ: <?= crm_h((string)$c['oz_flag_by']) ?>)</span>
                                <?php } ?>
                                <span style="color:var(--muted);font-size:0.65rem;">· nebude proplacen</span>
                            </div>
                        <?php } ?>

                        <?php
                        // ══ Sdílený telefon — varování operátorce ═══════════
                        // Když je telefon používán u víc firem, ukáž panel s:
                        //   - Počtem firem se stejným číslem
                        //   - Jmény (max 3) + "+ X dalších"
                        //   - Posledním stavem volání u jiného kontaktu se stejným číslem
                        $phShared = (int) ($c['phone_shared_count'] ?? 0);
                        if ($phShared > 0) {
                            $phFirms = (array) ($c['phone_shared_firms'] ?? []);
                            $phLast  = $c['phone_last_status'] ?? null;
                            // Slovní popis "naposledy volán..."
                            $lastLabel = '';
                            $lastClass = '';
                            if (is_array($phLast) && !empty($phLast['stav'])) {
                                $when = (string) ($phLast['when'] ?? '');
                                $whenAgo = '';
                                if ($when !== '') {
                                    $diff = time() - strtotime($when);
                                    if ($diff < 3600)        $whenAgo = 'před ' . max(1, (int)($diff/60)) . ' min';
                                    elseif ($diff < 86400)   $whenAgo = 'před ' . (int)($diff/3600) . ' h';
                                    elseif ($diff < 30*86400) $whenAgo = 'před ' . (int)($diff/86400) . ' dny';
                                    else                      $whenAgo = date('d.m.Y', strtotime($when));
                                }
                                $stavCz = match ((string) $phLast['stav']) {
                                    'CALLED_OK'      => '✓ ÚSPĚCH',
                                    'CALLED_BAD'     => '✗ NEÚSPĚCH',
                                    'NEZAJEM'        => '✗ NEZÁJEM',
                                    'CALLBACK'       => '↻ CALLBACK',
                                    'NEDOVOLANO'     => '📵 NEDOVOLÁNO',
                                    'IZOLACE'        => '🚫 IZOLACE',
                                    'CHYBNY_KONTAKT' => '⚠ CHYBNÝ KONTAKT',
                                    default          => (string) $phLast['stav'],
                                };
                                $lastLabel = $stavCz . ($whenAgo !== '' ? ' · ' . $whenAgo : '');
                                $lastClass = match ((string) $phLast['stav']) {
                                    'CALLED_OK', 'CALLBACK' => 'green',
                                    'NEZAJEM', 'IZOLACE', 'CHYBNY_KONTAKT', 'CALLED_BAD' => 'red',
                                    default => 'amber',
                                };
                            }
                            $bgColor = $lastClass === 'red'   ? 'rgba(231,76,60,0.10)'
                                     : ($lastClass === 'green' ? 'rgba(46,204,113,0.08)'
                                                                : 'rgba(241,196,15,0.10)');
                            $brColor = $lastClass === 'red'   ? '#e74c3c'
                                     : ($lastClass === 'green' ? '#2ecc71' : '#f1c40f');
                        ?>
                            <div style="margin-top:0.35rem;display:flex;flex-direction:column;gap:0.2rem;
                                        background:<?= $bgColor ?>;border:1px solid <?= $brColor ?>;
                                        border-left:3px solid <?= $brColor ?>;border-radius:0 6px 6px 0;
                                        padding:0.4rem 0.6rem;font-size:0.74rem;">
                                <div style="display:flex;align-items:center;gap:0.4rem;flex-wrap:wrap;">
                                    <span style="color:<?= $brColor ?>;font-weight:700;">
                                        ⚠ Sdílený telefon
                                    </span>
                                    <span style="color:var(--text);">
                                        — toto číslo používá <strong><?= $phShared ?></strong>
                                        dalších <?= $phShared === 1 ? 'firma' : ($phShared < 5 ? 'firmy' : 'firem') ?>
                                    </span>
                                </div>

                                <?php if ($phFirms !== []) {
                                    $names = array_slice($phFirms, 0, 3);
                                    $extra = max(0, count($phFirms) - 3);
                                ?>
                                    <div style="font-size:0.7rem;color:var(--muted);">
                                        <?= crm_h(implode(' · ', array_map(fn ($f) => (string) $f['firma'], $names))) ?>
                                        <?php if ($extra > 0) { ?>
                                            <span style="font-style:italic;">+ <?= $extra ?> dalších</span>
                                        <?php } ?>
                                    </div>
                                <?php } ?>

                                <?php if ($lastLabel !== '') { ?>
                                    <div style="font-size:0.72rem;color:var(--text);
                                                border-top:1px dashed rgba(0,0,0,0.08);padding-top:0.25rem;">
                                        <span style="color:var(--muted);">Naposledy:</span>
                                        <strong style="color:<?= $brColor ?>;"><?= crm_h($lastLabel) ?></strong>
                                        <?php if (is_array($phLast) && !empty($phLast['firma'])) { ?>
                                            <span style="color:var(--muted);font-size:0.65rem;">
                                                (u: <?= crm_h((string) $phLast['firma']) ?>)
                                            </span>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>

                    <?php if ($canAct) { ?>
                    <div class="contact-actions">
                        <form method="post" action="<?= crm_h(crm_url('/caller/status')) ?>"
                              class="action-form" onsubmit="return callerValidate(this)">
                            <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                            <input type="hidden" name="contact_id" value="<?= $cId ?>">

                            <div class="action-note">
                                <input type="text" name="poznamka" placeholder="Poznámka (povinná)..."
                                       class="input-note" autocomplete="off">
                            </div>

                            <div class="action-buttons">
                                <button type="button" class="btn-status btn-win"
                                        title="Výhra – vyberte obchodního zástupce"
                                        onclick="crmShowWinPanel(this)">✓</button>
                                <button type="button" class="btn-status btn-loss"
                                        title="Prohra – vyberte důvod"
                                        onclick="crmShowLossMenu(this)">✗</button>
                                <button type="button" class="btn-status btn-cb"
                                        title="Callback – zavolat později"
                                        onclick="crmHideOthers(this, '.callback-fields')">↻</button>
                                <!-- Nedovoláno: odeslat bez povinné poznámky -->
                                <button type="submit" name="new_status" value="NEDOVOLANO"
                                        class="btn-status btn-nedovolano"
                                        title="Nedovoláno – po 3× přejde na Nezájem"
                                        onclick="return crmNedovolano(this)">📵</button>
                            </div>

                            <!-- Win panel: výběr OZ (povinný) -->
                            <div class="win-panel hidden">
                                <?php if ($noSales) { ?>
                                    <span class="loss-menu-label" style="color:#e74c3c;">
                                        ⚠ Žádní obchodáci v tomto kraji.
                                    </span>
                                <?php } else { ?>
                                    <span class="loss-menu-label">Předat výhru:</span>
                                    <?php
                                    // Pro sdílený callback dát jako výchozí majitele
                                    $winSalesList = $salesList;
                                    $winPreselect = $preselect;
                                    if ($isSharedCb && !empty($majitel) && $winPreselect === 0) {
                                        $winPreselect = (int) $majitel['id'];
                                    }
                                    ?>
                                    <select name="sales_id" class="input-sales">
                                        <?php foreach ($winSalesList as $s) {
                                            $sel   = ((int)$s['id'] === $winPreselect) ? ' selected' : '';
                                            $sId   = (int) $s['id'];
                                            $label = (string) $s['jmeno'];
                                            // Přidat info o plnění kvóty OZ v tomto regionu
                                            if ($region !== '' && isset($ozProgress[$sId][$region])) {
                                                $sRec = $ozProgress[$sId][$region]['received'];
                                                $sTgt = $ozProgress[$sId][$region]['target'];
                                                $label .= ' (' . crm_region_label($region) . ' · ' . $sRec . '/' . $sTgt . ')';
                                            } elseif ($region === '' || !isset($ozProgress[$sId][$region])) {
                                                // Fallback: celkem přes všechny regiony
                                                $sTotalRec = 0; $sTotalTgt = 0;
                                                foreach ($ozProgress[$sId] ?? [] as $pReg => $pData) {
                                                    $sTotalRec += $pData['received'];
                                                    $sTotalTgt += $pData['target'];
                                                }
                                                if ($sTotalTgt > 0 || $sTotalRec > 0) {
                                                    $label .= ' (celkem · ' . $sTotalRec . '/' . $sTotalTgt . ')';
                                                }
                                            }
                                        ?>
                                            <option value="<?= $sId ?>"<?= $sel ?>><?= crm_h($label) ?></option>
                                        <?php } ?>
                                    </select>
                                    <?php if ($isSharedCb) { ?>
                                        <span class="label-sm" style="color:#f0a030;">🌐 Sdílený callback — výchozí: majitel</span>
                                    <?php } elseif ($regionSales === [] && $allSalesList !== []) { ?>
                                        <span class="label-sm" style="color:#f0a030;">(mimo region)</span>
                                    <?php } ?>
                                    <button type="submit" name="new_status" value="CALLED_OK"
                                            class="btn-win-confirm">✓ Potvrdit výhru</button>
                                    <button type="button"
                                            onclick="this.closest('.win-panel').classList.add('hidden')"
                                            class="btn-loss-zpet">← Zpět</button>
                                <?php } ?>
                            </div>

                            <!-- Submenu prohry -->
                            <div class="loss-menu hidden">
                                <span class="loss-menu-label">Důvod zamítnutí:</span>
                                <div class="loss-btn-row">
                                    <!-- Nezájem: otevře sub-panel s roletkou důvodu -->
                                    <button type="button"
                                            class="btn-loss-sub"
                                            onclick="crmShowNezajemPanel(this)">Nezájem</button>
                                    <button type="submit" name="new_status" value="IZOLACE"
                                            class="btn-loss-sub btn-loss-izolace"
                                            title="Nechce být kontaktován">🚫 Izolace</button>
                                    <button type="submit" name="new_status" value="CHYBNY_KONTAKT"
                                            class="btn-loss-sub btn-loss-chybny">✗ Chybný kontakt</button>
                                    <button type="button"
                                            onclick="this.closest('.loss-menu').classList.add('hidden')"
                                            class="btn-loss-sub btn-loss-zpet">← Zpět</button>
                                </div>

                                <!-- Sub-panel Nezájem: roletka + potvrzení (skrytý) -->
                                <div class="nezajem-panel hidden">
                                    <span class="loss-menu-label">Upřesni důvod nezájmu:</span>
                                    <select name="rejection_reason" class="input-rejection">
                                        <option value="">— vyberte —</option>
                                        <option value="nezajem">Obecný nezájem</option>
                                        <option value="cena">Cena</option>
                                        <option value="ma_smlouvu">Má smlouvu jinde</option>
                                        <option value="spatny_kontakt">Špatný kontakt</option>
                                        <option value="jine">Jiné</option>
                                    </select>
                                    <button type="submit" name="new_status" value="NEZAJEM"
                                            class="btn-loss-sub">✓ Potvrdit nezájem</button>
                                    <button type="button"
                                            onclick="this.closest('.nezajem-panel').classList.add('hidden')"
                                            class="btn-loss-sub btn-loss-zpet">← Zpět</button>
                                </div>
                            </div>

                            <!-- Callback pole -->
                            <div class="callback-fields hidden">
                                <label class="label-sm">Zavolat zpět:</label>
                                <input type="datetime-local" name="callback_at" class="input-cb">
                                <span class="label-sm" style="color:#aaa;">
                                    do 30 dní = jen tvůj, po 30 dnech = sdílený pro všechny
                                </span>
                                <button type="submit" name="new_status" value="CALLBACK"
                                        class="btn btn-secondary btn-sm">Nastavit callback</button>
                            </div>
                        </form>
                    </div>

                    <?php } else { ?>
                    <div class="contact-status-label">
                        <?= match ($stav) {
                            'CALLED_OK'      => '<span class="status-tag tag-win">✓ Výhra</span>',
                            'CALLED_BAD'     => '<span class="status-tag tag-loss">Prohra</span>',
                            'NEZAJEM'        => '<span class="status-tag tag-loss">Nezájem</span>',
                            'IZOLACE'        => '<span class="status-tag tag-izolace">🚫 Izolace</span>',
                            'CHYBNY_KONTAKT' => '<span class="status-tag tag-chybny">Chybný kontakt</span>',
                            'FOR_SALES'      => '<span class="status-tag tag-forsales">Předáno OZ</span>',
                            'NEDOVOLANO'     => '<span class="status-tag tag-nedovolano">📵 Nedovoláno</span>',
                            default          => crm_h($stav),
                        } ?>
                    </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>

        <?php if ($tab === 'aktivni' && $totalPages > 1) { ?>
            <div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap; margin-top:0.75rem;">
                <span class="muted" style="font-size:0.85rem;">Strana <?= $page ?> / <?= $totalPages ?> · <?= $totalCount ?> kontaktů</span>
                <?php callerPagination($page, $totalPages, $tab, $selectedRegion); ?>
            </div>
        <?php } ?>
    <?php } ?>

    <div style="margin-top:1.5rem; display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
        <a href="<?= crm_h(crm_url('/caller/search')) ?>" class="btn btn-secondary">🔍 Hledat kontakt</a>
        <a href="<?= crm_h(crm_url('/dashboard')) ?>" class="btn btn-secondary">Dashboard</a>
        <form method="post" action="<?= crm_h(crm_url('/logout')) ?>" style="margin:0;">
            <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
            <button type="submit" class="btn btn-secondary">Odhlásit se</button>
        </form>
    </div>
</section>

<script>
var CRM_CSRF     = <?= json_encode($csrf) ?>;
var CRM_CSRF_KEY = <?= json_encode(crm_csrf_field_name()) ?>;

/* ── Validace poznámky ── */
function callerValidate(form) {
    var note = form.querySelector('input[name="poznamka"]');
    if (!note || !note.value.trim()) {
        if (note) { note.style.borderColor = '#e74c3c'; note.placeholder = 'POZNÁMKA JE POVINNÁ!'; note.focus(); }
        return false;
    }
    return true;
}

/* ── Skryje všechny panely v actions, pak toggle target ── */
function crmHideOthers(btn, targetSel) {
    var actions = btn.closest('.contact-actions');
    ['.win-panel','.loss-menu','.callback-fields'].forEach(function(s) {
        var el = actions.querySelector(s);
        if (el) el.classList.add('hidden');
    });
    var t = actions.querySelector(targetSel);
    if (t) t.classList.toggle('hidden');
}

/* ── Win panel: validuje poznámku, pak zobrazí ── */
function crmShowWinPanel(btn) {
    var form = btn.closest('.action-form');
    var note = form.querySelector('input[name="poznamka"]');
    if (!note.value.trim()) {
        note.style.borderColor = '#e74c3c'; note.placeholder = 'POZNÁMKA JE POVINNÁ!'; note.focus(); return;
    }
    crmHideOthers(btn, '.win-panel');
}

/* ── Loss menu: validuje poznámku, pak zobrazí ── */
function crmShowLossMenu(btn) {
    var form = btn.closest('.action-form');
    var note = form.querySelector('input[name="poznamka"]');
    if (!note.value.trim()) {
        note.style.borderColor = '#e74c3c'; note.placeholder = 'POZNÁMKA JE POVINNÁ!'; note.focus(); return;
    }
    crmHideOthers(btn, '.loss-menu');
}

/* ── Nezájem sub-panel: toggle roletky důvodu ── */
function crmShowNezajemPanel(btn) {
    var lossMenu = btn.closest('.loss-menu');
    var panel = lossMenu.querySelector('.nezajem-panel');
    if (panel) panel.classList.toggle('hidden');
}

/* ── Nedovoláno: bez povinné poznámky, jen potvrzení ── */
function crmNedovolano(btn) {
    var form = btn.closest('.action-form');
    var note = form.querySelector('input[name="poznamka"]');
    if (note && note.value.trim() === '') {
        note.value = 'Nedovoláno';
    }
    return true;
}

/* ── Inline editace pole kontaktu ── */
function crmEdit(contactId, field, editBtn) {
    var spanId  = 'val-' + field + '-' + contactId;
    var span    = document.getElementById(spanId);
    if (!span || span.dataset.editing) return;
    span.dataset.editing = '1';

    var original = span.textContent === '—' ? '' : span.textContent;
    var wrapper  = span.closest('.editable-group') || span.parentNode;

    var input = document.createElement('input');
    input.type      = 'text';
    input.value     = original;
    input.className = 'input-inline-edit';

    var btnSave   = document.createElement('button');
    btnSave.type  = 'button';
    btnSave.textContent = '✓';
    btnSave.className   = 'btn-inline-save';

    var btnCancel   = document.createElement('button');
    btnCancel.type  = 'button';
    btnCancel.textContent = '✕';
    btnCancel.className   = 'btn-inline-cancel';

    span.style.display    = 'none';
    editBtn.style.display = 'none';
    wrapper.appendChild(input);
    wrapper.appendChild(btnSave);
    wrapper.appendChild(btnCancel);
    input.focus(); input.select();

    function restore() {
        input.remove(); btnSave.remove(); btnCancel.remove();
        span.style.display = '';
        editBtn.style.display = '';
        delete span.dataset.editing;
    }

    btnCancel.onclick = restore;

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter')  { e.preventDefault(); btnSave.click(); }
        if (e.key === 'Escape') { restore(); }
    });

    btnSave.onclick = async function() {
        var newVal = input.value.trim();
        btnSave.disabled = true;
        try {
            var body = new URLSearchParams();
            body.set('contact_id', contactId);
            body.set('field', field);
            body.set('value', newVal);
            body.set(CRM_CSRF_KEY, CRM_CSRF);

            var resp = await fetch('<?= crm_h(crm_url('/caller/contact/edit')) ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: body.toString()
            });
            var data = await resp.json();
            if (data.ok) {
                span.textContent = newVal || '—';
                // Pokud je to telefon, aktualizuj i href
                if (field === 'telefon' && span.tagName === 'A') {
                    span.href = newVal ? 'tel:' + newVal : '#';
                }
                restore();
            } else {
                alert(data.error || 'Chyba při ukládání.');
                btnSave.disabled = false;
            }
        } catch(e) {
            alert('Chyba sítě – zkuste znovu.');
            btnSave.disabled = false;
        }
    };
}

/* ── Flag operátorský mismatch (nahlásit čističce přes majitele) ── */
function crmFlagMismatch(contactId, btn) {
    if (btn.dataset.flagged) return;
    if (!confirm('Nahlásit majiteli, že operátor nesedí u tohoto kontaktu?')) return;
    btn.disabled = true;
    var body = new URLSearchParams();
    body.set('contact_id', contactId);
    body.set(CRM_CSRF_KEY, CRM_CSRF);
    fetch('<?= crm_h(crm_url('/caller/flag-mismatch')) ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: body.toString()
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.ok) {
            btn.textContent = '✓ Nahlášeno';
            btn.style.color = '#e67e22';
            btn.dataset.flagged = '1';
        } else {
            btn.disabled = false;
            alert(d.error || 'Chyba.');
        }
    })
    .catch(function() { btn.disabled = false; alert('Chyba sítě.'); });
}

/* ── Real-time badge "K provolání" – polling každých 30 s ── */
(function callerPoolPoll() {
    var aktivniBadge = document.getElementById('badge-aktivni');
    if (!aktivniBadge) return;

    setInterval(function() {
        fetch('<?= crm_h(crm_url('/caller/pool-count.json')) ?>', {credentials:'same-origin'})
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (typeof d.count === 'number') {
                aktivniBadge.textContent = d.count;
            }
        })
        .catch(function() {});
    }, 30000);
})();

/* ── Šněčí závody 🐌 ── */
(function snailRace() {
    var RACE_URL = <?= json_encode(crm_url('/caller/race.json')) ?>;
    var inner    = document.getElementById('snail-race-inner');
    if (!inner) return;

    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function render(data) {
        var target  = data.target || 150;
        var callers = data.callers || [];

        if (!callers.length) {
            inner.innerHTML = '<div class="snail-loading">Žádné navolávačky k zobrazení.</div>';
            return;
        }

        // Seřadit: více výher = výše
        callers = callers.slice().sort(function(a, b) { return b.wins - a.wins; });

        // Sestavit tratě
        var lanesHtml = '';
        callers.forEach(function(c) {
            var pct       = target > 0 ? Math.min(98, Math.max(1, Math.round(c.wins / target * 100))) : 1;
            var firstName = esc(c.name.split(' ')[0]);
            var meClass   = c.is_me ? ' race-snail--me' : '';
            var titleTxt  = esc(c.name) + ': ' + c.wins + ' výher (' + pct + ' %)';

            lanesHtml +=
                '<div class="race-lane">' +
                    '<div class="race-snail' + meClass + '" style="left:' + pct + '%" title="' + titleTxt + '">' +
                        '<span class="race-snail__emoji">🐌</span>' +
                        '<span class="race-snail__name">' + firstName + '</span>' +
                    '</div>' +
                '</div>';
        });

        inner.innerHTML =
            '<div class="race-tracks">' +
                '<div class="race-side">Start</div>' +
                '<div class="race-lanes">' + lanesHtml + '</div>' +
                '<div class="race-side race-side--end">Cíl</div>' +
            '</div>';
    }

    function load() {
        fetch(RACE_URL, {credentials: 'same-origin'})
            .then(function(r) { return r.ok ? r.json() : Promise.reject('HTTP ' + r.status); })
            .then(function(d) {
                if (d && d.callers !== undefined) {
                    render(d);
                } else {
                    inner.innerHTML = '<div class="snail-loading" style="color:#e74c3c;">Chyba: neplatná odpověď serveru.</div>';
                }
            })
            .catch(function(err) {
                inner.innerHTML = '<div class="snail-loading" style="color:#e74c3c;">Chyba načtení závodu.</div>';
            });
    }

    load();
    setInterval(load, 30000); // refresh každých 30 s
})();

/* ─────────────────────────────────────────────────────────────────────
   AKTIVNÍ ŘÁDEK (UX focus)
   - 1. .contact-row v listu je defaultně AKTIVNÍ (vizuálně dominantní)
   - Klik kdekoli na řádek (mimo button/input/link) ho aktivuje
   - Klávesy ↑/↓ (nebo j/k) přepínají aktivní řádek
   - Klávesy ignorovány, pokud uživatel píše do inputu/textarea
   - Po form submitu se stránka reloaduje a první řádek je opět aktivní
   ───────────────────────────────────────────────────────────────────── */
(function () {
    function getRows() {
        return document.querySelectorAll('.contact-row[data-cid]');
    }
    function getActive() {
        return document.querySelector('.contact-row--active');
    }
    function setActive(row) {
        if (!row) return;
        document.querySelectorAll('.contact-row--active').forEach(function (r) {
            r.classList.remove('contact-row--active');
        });
        row.classList.add('contact-row--active');
        // Plynulý scroll do středu, jen pokud řádek není ve viewportu
        var rect = row.getBoundingClientRect();
        var inView = rect.top >= 0 && rect.bottom <= window.innerHeight;
        if (!inView) {
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    // Init: aktivovat 1. řádek po načtení
    document.addEventListener('DOMContentLoaded', function () {
        var rows = getRows();
        if (rows.length > 0) setActive(rows[0]);
    });

    // Klik kdekoli na řádek → aktivovat ho. Vyloučit kliky na buttony/inputy/linky,
    // aby se nezasáhlo s existujícími UI prvky (✓ ✗ ↻ 📵, ✎ edit, callback dropdown atd.).
    document.addEventListener('click', function (e) {
        var t = e.target;
        // Klik uvnitř buttonu/inputu/textarea/selectu/linku → ignorovat (UI element).
        if (t.closest('button, input, textarea, select, a')) return;
        var row = t.closest('.contact-row[data-cid]');
        if (!row) return;
        setActive(row);
    });

    // Klávesy ↑/↓ (nebo j/k) — přepínat aktivní řádek. Ignorovat když píše do inputu.
    document.addEventListener('keydown', function (e) {
        var t = e.target;
        if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable)) return;
        if (e.ctrlKey || e.metaKey || e.altKey) return;

        var key = e.key;
        if (key !== 'ArrowDown' && key !== 'ArrowUp' && key !== 'j' && key !== 'k') return;

        var rows = Array.prototype.slice.call(getRows());
        if (rows.length === 0) return;

        var active = getActive();
        var idx = active ? rows.indexOf(active) : -1;

        if (key === 'ArrowDown' || key === 'j') {
            e.preventDefault();
            var next = (idx >= 0 && idx < rows.length - 1) ? rows[idx + 1] : rows[0];
            setActive(next);
        } else if (key === 'ArrowUp' || key === 'k') {
            e.preventDefault();
            var prev = (idx > 0) ? rows[idx - 1] : rows[rows.length - 1];
            setActive(prev);
        }
    });
})();
</script>

<?php require __DIR__ . '/_notifications.php'; ?>
