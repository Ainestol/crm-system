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
                    <th class="acol--total">Celkem</th>
                    <?php if ($winKey) { ?><th class="acol--rate">Úspěšnost</th><?php } ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($callerRows as $i => $row) {
                $wins  = $winKey ? (int) ($row[$winKey] ?? 0) : 0;
                $total = (int) ($row['total_actions'] ?? 0);
                $rate  = ($total > 0 && $winKey) ? round($wins / $total * 100, 1) : 0.0;
                $barW  = ($maxWins > 0 && $winKey) ? round($wins / $maxWins * 100) : 0;
                $rowCls = ($i % 2 !== 0) ? ' stats-row--alt' : '';
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
                    <?php if ($winKey) { ?>
                    <td class="acol--rate">
                        <?php if ($total > 0) { ?>
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
                    <?php if ($winKey) {
                        $teamTotal = $totals['total_actions'] ?? 0;
                        $teamWins  = $totals[$winKey] ?? 0;
                        $teamRate  = $teamTotal > 0 ? round($teamWins / $teamTotal * 100, 1) : 0.0;
                    ?>
                    <td class="acol--rate"><strong><?= $teamRate ?> %</strong></td>
                    <?php } ?>
                </tr>
            </tfoot>
        </table>
    </div>

    <?php } ?>
</section>
