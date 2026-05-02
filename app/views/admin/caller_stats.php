<?php
// app/views/admin/caller_stats.php
declare(strict_types=1);
/** @var array<string, mixed>              $user */
/** @var string|null                       $flash */
/** @var int                               $year */
/** @var int                               $month */
/** @var list<array<string, mixed>>        $callerRows */
/** @var array<string, int>                $totals */
/** @var list<array<string, mixed>>        $monthOptions */

$monthNames = [
    1 => 'Leden', 2 => 'Únor', 3 => 'Březen', 4 => 'Duben',
    5 => 'Květen', 6 => 'Červen', 7 => 'Červenec', 8 => 'Srpen',
    9 => 'Září', 10 => 'Říjen', 11 => 'Listopad', 12 => 'Prosinec',
];

$columns = [
    'called_ok'    => ['label' => 'Výhry',      'cls' => 'acol--win'],
    'called_bad'   => ['label' => 'Prohry',     'cls' => 'acol--bad'],
    'callback_c'   => ['label' => 'Callback',   'cls' => 'acol--cb'],
    'nezajem'      => ['label' => 'Nezájem',    'cls' => 'acol--nz'],
    'nedovolano'   => ['label' => 'Nedov.',     'cls' => 'acol--nd'],
    'izolace'      => ['label' => 'Izolace',    'cls' => 'acol--iz'],
    'chybny'       => ['label' => 'Chybný k.',  'cls' => 'acol--ch'],
    'total_actions'=> ['label' => 'Celkem',     'cls' => 'acol--total'],
];

$currentLabel = ($monthNames[$month] ?? $month) . ' ' . $year;

// Maximální výhry pro vizualizaci poměru (progress bar v řádku)
$maxWins = 0;
foreach ($callerRows as $r) {
    if ((int)$r['called_ok'] > $maxWins) $maxWins = (int)$r['called_ok'];
}
?>
<section class="card">
    <div class="stats-header">
        <div>
            <h1>Výkon navolávačů</h1>
            <p class="muted">Srovnání všech navolávačů za vybrané období</p>
        </div>
    </div>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info"><?= crm_h($flash) ?></p>
    <?php } ?>

    <!-- ── Filtr měsíce ── -->
    <div class="stats-filter-bar">
        <form method="get" action="<?= crm_h(crm_url('/admin/caller-stats')) ?>" class="stats-filter-form">
            <label class="stats-filter-label">Zobrazit měsíc:</label>
            <select name="month_key" class="input-sm stats-month-select" onchange="this.form.submit()">
                <?php foreach ($monthOptions as $opt) {
                    $key    = $opt['year'] . '-' . str_pad((string)$opt['month'], 2, '0', STR_PAD_LEFT);
                    $curKey = $year . '-' . str_pad((string)$month, 2, '0', STR_PAD_LEFT);
                    $sel    = ($key === $curKey) ? ' selected' : '';
                    $lbl    = ($opt['label'] ?? (($monthNames[(int)$opt['month']] ?? '') . ' ' . $opt['year']));
                    $isNow  = ($key === $realMonthKey);
                    $optStyle  = $isNow ? ' style="background:rgba(34,197,94,0.18);color:#22c55e;"' : '';
                    $nowSuffix = $isNow ? ' — nyní' : '';
                ?>
                    <option value="<?= crm_h($key) ?>"<?= $sel ?><?= $optStyle ?>><?= crm_h($lbl . $nowSuffix) ?></option>
                <?php } ?>
            </select>
            <noscript><button type="submit" class="btn btn-secondary btn-sm">Zobrazit</button></noscript>
        </form>
    </div>

    <h2 class="stats-period-title"><?= crm_h($currentLabel) ?></h2>

    <?php if (count($callerRows) === 0) { ?>
        <p class="muted">Žádné navolávačky nejsou v systému.</p>
    <?php } else { ?>

    <!-- ── Srovnávací tabulka ── -->
    <div class="stats-table-wrap">
        <table class="stats-table admin-caller-table">
            <thead>
                <tr>
                    <th class="acol--name">Navolávačka</th>
                    <?php foreach ($columns as $col) { ?>
                        <th class="<?= crm_h($col['cls']) ?>"><?= crm_h($col['label']) ?></th>
                    <?php } ?>
                    <th class="acol--rate">Úspěšnost</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($callerRows as $i => $row) {
                $wins  = (int) $row['called_ok'];
                $total = (int) $row['total_actions'];
                $rate  = $total > 0 ? round($wins / $total * 100, 1) : 0.0;
                $barW  = $maxWins > 0 ? round($wins / $maxWins * 100) : 0;
                $rowClass = ($i % 2 === 0) ? '' : ' stats-row--alt';
            ?>
                <tr class="<?= $rowClass ?>">
                    <td class="acol--name">
                        <strong><?= crm_h((string) $row['jmeno']) ?></strong>
                        <?php if ($wins > 0 && $wins === $maxWins && count($callerRows) > 1) { ?>
                            <span class="acol-badge acol-badge--top" title="Nejvíce výher">🏆</span>
                        <?php } ?>
                    </td>
                    <td class="acol--win <?= $wins > 0 ? 'acol-highlight--win' : '' ?>">
                        <?= $wins > 0 ? "<strong>{$wins}</strong>" : '—' ?>
                    </td>
                    <td class="acol--bad"><?= $row['called_bad'] > 0 ? (int)$row['called_bad'] : '—' ?></td>
                    <td class="acol--cb"><?= $row['callback_c'] > 0 ? (int)$row['callback_c'] : '—' ?></td>
                    <td class="acol--nz"><?= $row['nezajem'] > 0 ? (int)$row['nezajem'] : '—' ?></td>
                    <td class="acol--nd"><?= $row['nedovolano'] > 0 ? (int)$row['nedovolano'] : '—' ?></td>
                    <td class="acol--iz"><?= $row['izolace'] > 0 ? (int)$row['izolace'] : '—' ?></td>
                    <td class="acol--ch"><?= $row['chybny'] > 0 ? (int)$row['chybny'] : '—' ?></td>
                    <td class="acol--total"><strong><?= $total ?></strong></td>
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
                </tr>
            <?php } ?>
            </tbody>
            <tfoot>
                <tr class="stats-row--total">
                    <td><strong>Celkem tým</strong></td>
                    <td class="acol--win"><strong><?= $totals['called_ok'] ?></strong></td>
                    <td class="acol--bad"><strong><?= $totals['called_bad'] ?></strong></td>
                    <td class="acol--cb"><strong><?= $totals['callback_c'] ?></strong></td>
                    <td class="acol--nz"><strong><?= $totals['nezajem'] ?></strong></td>
                    <td class="acol--nd"><strong><?= $totals['nedovolano'] ?></strong></td>
                    <td class="acol--iz"><strong><?= $totals['izolace'] ?></strong></td>
                    <td class="acol--ch"><strong><?= $totals['chybny'] ?></strong></td>
                    <td class="acol--total"><strong><?= $totals['total_actions'] ?></strong></td>
                    <td class="acol--rate">
                        <?php
                        $teamRate = $totals['total_actions'] > 0
                            ? round($totals['called_ok'] / $totals['total_actions'] * 100, 1)
                            : 0.0;
                        ?>
                        <strong><?= $teamRate ?> %</strong>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- ── Legendy ── -->
    <div class="stats-legend">
        <span class="acol--win stats-legend-dot">■</span> Výhry (CALLED_OK) &nbsp;
        <span class="acol--bad stats-legend-dot">■</span> Prohry (CALLED_BAD) &nbsp;
        <span class="acol--cb stats-legend-dot">■</span> Callback &nbsp;
        <span class="acol--nz stats-legend-dot">■</span> Nezájem &nbsp;
        <span class="stats-legend-note">Úspěšnost = Výhry / Celkem akcí</span>
    </div>

    <?php } ?>
</section>
