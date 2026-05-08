<?php
// app/views/admin/team_stats.php
declare(strict_types=1);
/** @var array<string,mixed>           $user */
/** @var string|null                   $flash */
/** @var string                        $role */
/** @var int                           $year */
/** @var int                           $month */
/** @var list<array<string,mixed>>     $callerRows */
/** @var array<string,int>             $totals */
/** @var list<array<string,mixed>>     $monthOptions */
/** @var array<string,array>           $allRoles    -- celá ROLE_CONFIG mapa */
/** @var array<string,mixed>           $cfg         -- konfigurace aktivní role */

$monthNames = [
    1 => 'Leden', 2 => 'Únor', 3 => 'Březen', 4 => 'Duben',
    5 => 'Květen', 6 => 'Červen', 7 => 'Červenec', 8 => 'Srpen',
    9 => 'Září', 10 => 'Říjen', 11 => 'Listopad', 12 => 'Prosinec',
];
$currentLabel = ($monthNames[$month] ?? $month) . ' ' . $year;

$winKey   = $cfg['win_key'] ?? null;
$columns  = $cfg['columns'] ?? [];
$colKeys  = $cfg['col_keys'] ?? [];

// Maximální "výhry" (pro progress bar)
$maxWins = 0;
foreach ($callerRows as $r) {
    if ($winKey && (int)($r[$winKey] ?? 0) > $maxWins) {
        $maxWins = (int) $r[$winKey];
    }
}
?>
<section class="card">
    <div class="stats-header">
        <div>
            <h1>Výkon týmu — <?= crm_h($cfg['label']) ?></h1>
            <p class="muted">Srovnání za vybrané období · měsíční filtr</p>
        </div>
    </div>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info"><?= crm_h($flash) ?></p>
    <?php } ?>

    <!-- ── Navigace mezi rolemi ── -->
    <div class="team-stats-nav">
        <?php foreach ($allRoles as $rKey => $rCfg) {
            $isActive = ($rKey === $role);
            $href = crm_url('/admin/team-stats?role=' . $rKey
                . '&year=' . $year . '&month=' . $month);
        ?>
        <a href="<?= crm_h($href) ?>"
           class="team-stats-nav-btn <?= $isActive ? 'team-stats-nav-btn--active' : '' ?>">
            <?= crm_h($rCfg['icon'] . ' ' . $rCfg['label']) ?>
        </a>
        <?php } ?>
        <a href="<?= crm_h(crm_url('/admin/caller-stats')) ?>"
           class="team-stats-nav-btn team-stats-nav-btn--legacy" title="Původní stránka navolávačky">
            ↗ Starý přehled navolávačky
        </a>
    </div>

    <!-- ── Filtr měsíce ── -->
    <div class="stats-filter-bar">
        <form method="get" action="<?= crm_h(crm_url('/admin/team-stats')) ?>" class="stats-filter-form">
            <input type="hidden" name="role" value="<?= crm_h($role) ?>">
            <label class="stats-filter-label">Zobrazit měsíc:</label>
            <select name="month_key" class="input-sm stats-month-select" onchange="this.form.submit()">
                <?php foreach ($monthOptions as $opt) {
                    $key    = $opt['year'] . '-' . str_pad((string)$opt['month'], 2, '0', STR_PAD_LEFT);
                    $curKey = $year . '-' . str_pad((string)$month, 2, '0', STR_PAD_LEFT);
                    $sel    = ($key === $curKey) ? ' selected' : '';
                    $isNow  = ($key === $realMonthKey);
                    $optStyle  = $isNow ? ' style="background:rgba(34,197,94,0.18);color:#22c55e;"' : '';
                    $nowSuffix = $isNow ? ' — nyní' : '';
                ?>
                    <option value="<?= crm_h($key) ?>"<?= $sel ?><?= $optStyle ?>><?= crm_h($opt['label'] . $nowSuffix) ?></option>
                <?php } ?>
            </select>
            <noscript><button type="submit" class="btn btn-secondary btn-sm">Zobrazit</button></noscript>
        </form>
    </div>

    <h2 class="stats-period-title"><?= crm_h($currentLabel) ?></h2>

    <?php if (count($callerRows) === 0) { ?>
        <p class="muted">Pro roli <strong><?= crm_h($cfg['label']) ?></strong> nejsou v systému žádní aktivní uživatelé.</p>
    <?php } else { ?>

    <div class="stats-table-wrap">
        <table class="stats-table admin-caller-table">
            <thead>
                <tr>
                    <th class="acol--name">Jméno</th>
                    <?php foreach ($columns as $col) { ?>
                        <th class="<?= crm_h($col['cls']) ?>"><?= crm_h($col['label']) ?></th>
                    <?php } ?>
                    <th class="acol--total" title="Suma všech akcí (NEDOVOLANO 3× = 3 akce)">Celkem akcí</th>
                    <th class="acol--total" title="Z kolika unikátních kontaktů reálně pracoval(a) v daném měsíci">Z kontaktů</th>
                    <?php if ($winKey) { ?><th class="acol--rate">Úspěšnost</th><?php } ?>
                    <?php if ($role === 'obchodak') { ?>
                        <th class="acol--total"
                            title="Kolik kontaktů mu navolávačka v měsíci předala (datum_predani v měsíci)">
                            📞 Z navolaných
                        </th>
                        <th class="acol--rate"
                            title="Konverze obchodu = uzavřené (DONE+ACTIVATED) / z navolaných">
                            💼 Konverze obchodu
                        </th>
                    <?php } ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($callerRows as $i => $row) {
                $wins        = $winKey ? (int) ($row[$winKey] ?? 0) : 0;
                $total       = (int) ($row['total_actions'] ?? 0);
                $unique      = (int) ($row['unique_contacts'] ?? 0);
                // Úspěšnost = win / DISTINCT contacts (smysluplnější než / total_actions)
                $denomForRate= $unique > 0 ? $unique : $total;
                $rate        = ($denomForRate > 0 && $winKey) ? round($wins / $denomForRate * 100, 1) : 0.0;
                $barW        = ($maxWins > 0 && $winKey) ? round($wins / $maxWins * 100) : 0;
                $rowCls      = ($i % 2 !== 0) ? ' stats-row--alt' : '';
            ?>
                <tr class="<?= $rowCls ?>">
                    <td class="acol--name">
                        <strong><?= crm_h((string) $row['jmeno']) ?></strong>
                        <?php if ($winKey && $wins > 0 && $wins === $maxWins && count($callerRows) > 1) { ?>
                            <span class="acol-badge" title="Nejvíce v této kategorii">🏆</span>
                        <?php } ?>
                    </td>
                    <?php foreach ($colKeys as $k) {
                        $v   = (int) ($row[$k] ?? 0);
                        $cls = $columns[$k]['cls'] ?? '';
                        $hl  = ($k === $winKey && $v > 0) ? ' acol-highlight--win' : '';
                    ?>
                        <td class="<?= crm_h($cls . $hl) ?>">
                            <?= $v > 0 ? ($k === $winKey ? "<strong>{$v}</strong>" : $v) : '—' ?>
                        </td>
                    <?php } ?>
                    <td class="acol--total"><strong><?= $total ?></strong></td>
                    <td class="acol--total"><strong><?= $unique ?></strong></td>
                    <?php if ($winKey) { ?>
                    <td class="acol--rate">
                        <?php if ($denomForRate > 0) { ?>
                        <div class="acol-rate-wrap">
                            <div class="acol-rate-bar">
                                <div class="acol-rate-fill <?= $rate >= 20 ? 'acol-rate-fill--good' : ($rate >= 10 ? 'acol-rate-fill--mid' : 'acol-rate-fill--low') ?>"
                                     style="width:<?= $barW ?>%"></div>
                            </div>
                            <span class="acol-rate-pct"><?= $rate ?> %</span>
                        </div>
                        <?php } else { ?>
                            <span class="muted">—</span>
                        <?php } ?>
                    </td>
                    <?php } ?>
                    <?php if ($role === 'obchodak') {
                        $handed = (int) ($row['handed_to_oz'] ?? 0);
                        $ozPct  = (float) ($row['oz_conversion_pct'] ?? 0);
                        $ozColor = $ozPct >= 30 ? '#16a34a' : ($ozPct >= 10 ? '#d97706' : ($ozPct > 0 ? '#dc2626' : '#9ca3af'));
                    ?>
                        <td class="acol--total"><strong><?= $handed ?></strong></td>
                        <td class="acol--rate">
                            <?php if ($handed > 0) { ?>
                                <span style="background:<?= $ozColor ?>20; color:<?= $ozColor ?>; font-weight:700; padding:3px 10px; border-radius:12px;">
                                    <?= number_format($ozPct, 1, ',', ' ') ?> %
                                </span>
                            <?php } else { ?>
                                <span class="muted">—</span>
                            <?php } ?>
                        </td>
                    <?php } ?>
                </tr>
            <?php } ?>
            </tbody>
            <tfoot>
                <tr class="stats-row--total">
                    <td><strong>Celkem tým</strong></td>
                    <?php foreach ($colKeys as $k) {
                        $cls = $columns[$k]['cls'] ?? '';
                    ?>
                        <td class="<?= crm_h($cls) ?>"><strong><?= $totals[$k] ?? 0 ?></strong></td>
                    <?php } ?>
                    <td class="acol--total"><strong><?= $totals['total_actions'] ?? 0 ?></strong></td>
                    <?php
                        $teamUnique = 0;
                        foreach ($callerRows as $r) { $teamUnique += (int) ($r['unique_contacts'] ?? 0); }
                    ?>
                    <td class="acol--total"><strong><?= $teamUnique ?></strong></td>
                    <?php if ($winKey) {
                        $teamWins  = $totals[$winKey] ?? 0;
                        $teamDenom = $teamUnique > 0 ? $teamUnique : ($totals['total_actions'] ?? 0);
                        $teamRate  = $teamDenom > 0 ? round($teamWins / $teamDenom * 100, 1) : 0.0;
                    ?>
                    <td class="acol--rate"><strong><?= $teamRate ?> %</strong></td>
                    <?php } ?>
                    <?php if ($role === 'obchodak') {
                        $teamHanded = 0; $teamClosed = 0;
                        foreach ($callerRows as $r) {
                            $teamHanded += (int) ($r['handed_to_oz'] ?? 0);
                            $teamClosed += (int) ($r['done'] ?? 0) + (int) ($r['activated'] ?? 0);
                        }
                        $teamOzPct = $teamHanded > 0 ? round($teamClosed / $teamHanded * 100, 1) : 0.0;
                    ?>
                        <td class="acol--total"><strong><?= $teamHanded ?></strong></td>
                        <td class="acol--rate"><strong><?= $teamOzPct ?> %</strong></td>
                    <?php } ?>
                </tr>
            </tfoot>
        </table>
    </div>

    <?php } ?>

    <!-- ════════ PREMIUM PIPELINE — sekce per role ════════ -->
    <?php if (!empty($premiumRows)) { ?>
    <div style="margin-top: 2rem; padding: 1rem; background: linear-gradient(135deg, #faf8fd 0%, #f5f0fc 100%);
                border: 1px solid #d8c5fa; border-left: 4px solid #7e3ff2; border-radius: 0 8px 8px 0;">
        <h2 style="margin: 0 0 0.4rem; font-size: 1.1rem; color: #4a2480;">
            💎 Premium pipeline — <?= htmlspecialchars($cfg['label'], ENT_QUOTES, 'UTF-8') ?>
        </h2>
        <p style="font-size: 0.82rem; color: var(--color-text-muted); margin-bottom: 0.8rem;">
            Aktivita v premium pipeline za vybrané období. Ukazuje co každý reálně vydělal/utratil za premium leady.
        </p>

        <table class="atable" style="width:100%; background:#fff; border:1px solid #d8c5fa; border-radius:6px; overflow:hidden;">
            <thead>
                <tr>
                    <th style="background:#f5f0fc; color:#4a2480;">Jméno</th>
                    <?php if ($role === 'cisticka') { ?>
                        <th style="background:#f5f0fc;">Vyčištěno celkem</th>
                        <th style="background:#f5f0fc;">✅ Obchodovatelné</th>
                        <th style="background:#f5f0fc;">❌ Neobchodovatelné</th>
                        <th style="background:#f5f0fc;">⚠ Reklamace</th>
                        <th style="background:#f5f0fc;" title="Procento obchodovatelných z vyčištěných (= kvalita pool)">📊 Konverze %</th>
                        <th style="background:#f5f0fc;">💰 Vyděláno (Kč)</th>
                    <?php } elseif ($role === 'navolavacka') { ?>
                        <th style="background:#f5f0fc;" title="Suma success + failed">📞 Hovorů celkem</th>
                        <th style="background:#f5f0fc;">✅ Úspěšně navoláno</th>
                        <th style="background:#f5f0fc;">❌ Neúspěšně</th>
                        <th style="background:#f5f0fc;" title="success / (success+failed) = úspěšnost premium hovorů">📊 Úspěšnost %</th>
                        <th style="background:#f5f0fc;">💰 Bonus celkem (Kč)</th>
                    <?php } elseif ($role === 'obchodak') { ?>
                        <th style="background:#f5f0fc;" title="Počet premium objednávek (= kolikrát si OZ objednal druhé čištění) v daném měsíci">📋 Objednávek</th>
                        <th style="background:#f5f0fc;" title="Počet leadů které čistička vyčistila ze všech objednávek tohoto OZ v daném měsíci">🧹 Vyčištěno leadů</th>
                        <th style="background:#f5f0fc;" title="Počet úspěšně navolaných premium leadů (call_status=success)">📞 Navoláno</th>
                        <th style="background:#f5f0fc;" title="Navoláno / vyčištěno = úspěšnost konverze do hovoru">📊 Konverze %</th>
                        <th style="background:#f5f0fc;" title="Z navolaných premium kontaktů kolik OZ dotáhl k uzavření (DONE/ACTIVATED)">💼 Uzavřeno</th>
                        <th style="background:#f5f0fc;" title="Uzavřeno / navoláno = obchodní úspěšnost OZ na premium kontaktech">📊 Konverze obchodu</th>
                        <th style="background:#f5f0fc;">💰 Dluh čističce (Kč)</th>
                        <th style="background:#f5f0fc;">💰 Dluh navolávačkám (Kč)</th>
                    <?php } else { ?>
                        <th style="background:#f5f0fc;">— pro tuto roli zatím premium statistiky nejsou —</th>
                    <?php } ?>
                </tr>
            </thead>
            <tbody>
            <?php
            // Souhrny pro footer
            $sumA = 0; $sumB = 0; $sumC = 0; $sumD = 0; $sumE = 0;
            foreach ($premiumRows as $pr) {
                $jmeno = (string) ($pr['jmeno'] ?? '—');
            ?>
                <tr>
                    <td><strong><?= htmlspecialchars($jmeno, ENT_QUOTES, 'UTF-8') ?></strong></td>
                    <?php
                    // Helper: barevná pilulka pro konverzi %
                    $pctPill = static function (float $p): string {
                        $color = $p >= 50 ? '#16a34a' : ($p >= 20 ? '#d97706' : ($p > 0 ? '#dc2626' : '#9ca3af'));
                        return '<span style="background:' . $color . '20; color:' . $color
                             . '; font-weight:700; padding:2px 8px; border-radius:10px; font-size:0.85em;">'
                             . number_format($p, 1, ',', ' ') . ' %</span>';
                    };
                    if ($role === 'cisticka') {
                        $a = (int) ($pr['total'] ?? 0);
                        $b = (int) ($pr['tradeable'] ?? 0);
                        $c = (int) ($pr['non_tradeable'] ?? 0);
                        $d = (int) ($pr['refund'] ?? 0);
                        $pct = (float) ($pr['conversion_pct'] ?? 0);
                        $e = (float) ($pr['earned_czk'] ?? 0);
                        $sumA += $a; $sumB += $b; $sumC += $c; $sumD += $d; $sumE += $e;
                    ?>
                        <td style="text-align:center;"><?= $a ?></td>
                        <td style="text-align:center; color:#16a34a; font-weight:600;"><?= $b ?></td>
                        <td style="text-align:center;"><?= $c ?></td>
                        <td style="text-align:center; color:<?= $d > 0 ? '#dc2626' : 'var(--color-text-muted)' ?>;"><?= $d ?></td>
                        <td style="text-align:center;"><?= $a > 0 ? $pctPill($pct) : '<span style="color:#9ca3af;">—</span>' ?></td>
                        <td style="text-align:right; color:#7e3ff2; font-weight:700;">
                            <?= number_format($e, 2, ',', ' ') ?>
                        </td>
                    <?php } elseif ($role === 'navolavacka') {
                        $totCalls = (int) ($pr['call_total'] ?? 0);
                        $a = (int) ($pr['success_cnt'] ?? 0);
                        $b = (int) ($pr['failed_cnt'] ?? 0);
                        $pct = (float) ($pr['conversion_pct'] ?? 0);
                        $c = (float) ($pr['bonus_czk'] ?? 0);
                        $sumA += $a; $sumB += $b; $sumC += $c;
                    ?>
                        <td style="text-align:center;"><strong><?= $totCalls ?></strong></td>
                        <td style="text-align:center; color:#16a34a; font-weight:600;"><?= $a ?></td>
                        <td style="text-align:center; color:#dc2626;"><?= $b ?></td>
                        <td style="text-align:center;"><?= $totCalls > 0 ? $pctPill($pct) : '<span style="color:#9ca3af;">—</span>' ?></td>
                        <td style="text-align:right; color:#d97706; font-weight:700;">
                            <?= number_format($c, 2, ',', ' ') ?>
                        </td>
                    <?php } elseif ($role === 'obchodak') {
                        $a = (int) ($pr['orders_cnt'] ?? 0);
                        $b = (int) ($pr['cleaned_cnt'] ?? 0);
                        $c = (int) ($pr['called_success'] ?? 0);
                        $closed = (int) ($pr['closed_cnt'] ?? 0);
                        $pct = (float) ($pr['conversion_pct'] ?? 0);
                        $bizPct = (float) ($pr['business_pct'] ?? 0);
                        $d = (float) ($pr['due_cleaner_czk'] ?? 0);
                        $e = (float) ($pr['due_caller_czk'] ?? 0);
                        $sumA += $a; $sumB += $b; $sumC += $c; $sumD += $d; $sumE += $e;
                        // Sumace pro footer — closed jako extra var (nelze do existujících sumA-E)
                        $sumClosed = ($sumClosed ?? 0) + $closed;
                    ?>
                        <td style="text-align:center;"><?= $a ?></td>
                        <td style="text-align:center;"><?= $b ?></td>
                        <td style="text-align:center; color:#7e3ff2; font-weight:600;"><?= $c ?></td>
                        <td style="text-align:center;"><?= $b > 0 ? $pctPill($pct) : '<span style="color:#9ca3af;">—</span>' ?></td>
                        <td style="text-align:center; color:#16a34a; font-weight:700;"><?= $closed ?></td>
                        <td style="text-align:center;"><?= $c > 0 ? $pctPill($bizPct) : '<span style="color:#9ca3af;">—</span>' ?></td>
                        <td style="text-align:right; color:#7e3ff2; font-weight:700;">
                            <?= number_format($d, 2, ',', ' ') ?>
                        </td>
                        <td style="text-align:right; color:#d97706; font-weight:700;">
                            <?= number_format($e, 2, ',', ' ') ?>
                        </td>
                    <?php } ?>
                </tr>
            <?php } ?>
            </tbody>
            <tfoot>
                <tr style="background:#f5f0fc; font-weight:700;">
                    <td>Celkem tým (premium)</td>
                    <?php if ($role === 'cisticka') {
                        $teamPct = $sumA > 0 ? round($sumB / $sumA * 100, 1) : 0.0;
                    ?>
                        <td style="text-align:center;"><?= $sumA ?></td>
                        <td style="text-align:center; color:#16a34a;"><?= $sumB ?></td>
                        <td style="text-align:center;"><?= $sumC ?></td>
                        <td style="text-align:center; color:<?= $sumD > 0 ? '#dc2626' : 'var(--color-text-muted)' ?>;"><?= $sumD ?></td>
                        <td style="text-align:center;"><?= $sumA > 0 ? $pctPill($teamPct) : '—' ?></td>
                        <td style="text-align:right; color:#7e3ff2;">
                            <?= number_format($sumE, 2, ',', ' ') ?> Kč
                        </td>
                    <?php } elseif ($role === 'navolavacka') {
                        $totCalls = $sumA + $sumB;
                        $teamPct = $totCalls > 0 ? round($sumA / $totCalls * 100, 1) : 0.0;
                    ?>
                        <td style="text-align:center;"><strong><?= $totCalls ?></strong></td>
                        <td style="text-align:center; color:#16a34a;"><?= $sumA ?></td>
                        <td style="text-align:center; color:#dc2626;"><?= $sumB ?></td>
                        <td style="text-align:center;"><?= $totCalls > 0 ? $pctPill($teamPct) : '—' ?></td>
                        <td style="text-align:right; color:#d97706;">
                            <?= number_format($sumC, 2, ',', ' ') ?> Kč
                        </td>
                    <?php } elseif ($role === 'obchodak') {
                        $teamPct    = $sumB > 0 ? round($sumC / $sumB * 100, 1) : 0.0;
                        $teamBiz    = $sumC > 0 ? round(($sumClosed ?? 0) / $sumC * 100, 1) : 0.0;
                    ?>
                        <td style="text-align:center;"><?= $sumA ?></td>
                        <td style="text-align:center;"><?= $sumB ?></td>
                        <td style="text-align:center; color:#7e3ff2;"><?= $sumC ?></td>
                        <td style="text-align:center;"><?= $sumB > 0 ? $pctPill($teamPct) : '—' ?></td>
                        <td style="text-align:center; color:#16a34a;"><?= ($sumClosed ?? 0) ?></td>
                        <td style="text-align:center;"><?= $sumC > 0 ? $pctPill($teamBiz) : '—' ?></td>
                        <td style="text-align:right; color:#7e3ff2;">
                            <?= number_format($sumD, 2, ',', ' ') ?> Kč
                        </td>
                        <td style="text-align:right; color:#d97706;">
                            <?= number_format($sumE, 2, ',', ' ') ?> Kč
                        </td>
                    <?php } ?>
                </tr>
            </tfoot>
        </table>
        <p style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 0.5rem;">
            💡 Pro detailní per-objednávka view použij
            <a href="<?= crm_h(crm_url('/admin/premium-overview')) ?>" style="color:#7e3ff2;">/admin/premium-overview</a>.
        </p>
    </div>
    <?php } ?>
</section>
