<?php
// app/views/caller/stats.php
declare(strict_types=1);
/** @var array<string, mixed>        $user */
/** @var string|null                 $flash */
/** @var int                         $year */
/** @var int                         $month */
/** @var array<string, int>          $summary */
/** @var array<int, array<string, int>> $daily */
/** @var array<int, array<string, int>> $activeDays */
/** @var list<array<string, mixed>>  $monthOptions */
/** @var int                         $totalActions */
/** @var float                       $winRate */
/** @var list<string>                $trackedStatuses */
/** @var int                         $daysInMonth */

$monthNames = [
    1 => 'Leden', 2 => 'Únor', 3 => 'Březen', 4 => 'Duben',
    5 => 'Květen', 6 => 'Červen', 7 => 'Červenec', 8 => 'Srpen',
    9 => 'Září', 10 => 'Říjen', 11 => 'Listopad', 12 => 'Prosinec',
];

$statusLabels = [
    'CALLED_OK'      => 'Výhry',
    'CALLED_BAD'     => 'Prohry',
    'CALLBACK'       => 'Callback',
    'NEZAJEM'        => 'Nezájem',
    'NEDOVOLANO'     => 'Nedovoláno',
    'IZOLACE'        => 'Izolace',
    'CHYBNY_KONTAKT' => 'Chybný k.',
];
$statusColors = [
    'CALLED_OK'      => 'stat-card--win',
    'CALLED_BAD'     => 'stat-card--bad',
    'CALLBACK'       => 'stat-card--callback',
    'NEZAJEM'        => 'stat-card--nezajem',
    'NEDOVOLANO'     => 'stat-card--nedovolano',
    'IZOLACE'        => 'stat-card--izolace',
    'CHYBNY_KONTAKT' => 'stat-card--chybny',
];

$currentMonthLabel = ($monthNames[$month] ?? $month) . ' ' . $year;
?>
<section class="card">
    <div class="stats-header">
        <div>
            <h1>Můj výkon</h1>
            <p class="muted">Přihlášena: <strong><?= crm_h((string) ($user['jmeno'] ?? '')) ?></strong></p>
        </div>
        <a href="<?= crm_h(crm_url('/caller')) ?>" class="btn btn-secondary btn-sm">← Zpět na kontakty</a>
    </div>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info"><?= crm_h($flash) ?></p>
    <?php } ?>

    <!-- ── Filtr měsíce ── -->
    <div class="stats-filter-bar">
        <form method="get" action="<?= crm_h(crm_url('/caller/stats')) ?>" class="stats-filter-form">
            <label class="stats-filter-label">Zobrazit měsíc:</label>
            <select name="month_key" class="input-sm stats-month-select" onchange="this.form.submit()">
                <?php foreach ($monthOptions as $opt) {
                    $key    = $opt['year'] . '-' . str_pad((string)$opt['month'], 2, '0', STR_PAD_LEFT);
                    $curKey = $year . '-' . str_pad((string)$month, 2, '0', STR_PAD_LEFT);
                    $sel    = ($key === $curKey) ? ' selected' : '';
                    $lbl    = ($monthNames[(int)$opt['month']] ?? $opt['month']) . ' ' . $opt['year'];
                    $isNow  = ($key === $realMonthKey);
                    $optStyle = $isNow ? ' style="background:rgba(34,197,94,0.18);color:#22c55e;"' : '';
                    $nowSuffix = $isNow ? ' — nyní' : '';
                ?>
                    <option value="<?= crm_h($key) ?>"<?= $sel ?><?= $optStyle ?>><?= crm_h($lbl . $nowSuffix) ?></option>
                <?php } ?>
            </select>
            <noscript><button type="submit" class="btn btn-secondary btn-sm">Zobrazit</button></noscript>
        </form>
    </div>

    <h2 class="stats-period-title"><?= crm_h($currentMonthLabel) ?></h2>

    <!-- ── Souhrnné karty ── -->
    <div class="stat-cards-grid">
        <?php foreach ($trackedStatuses as $s) { ?>
        <div class="stat-card <?= $statusColors[$s] ?? '' ?>">
            <span class="stat-card__num"><?= $summary[$s] ?></span>
            <span class="stat-card__label"><?= crm_h($statusLabels[$s] ?? $s) ?></span>
        </div>
        <?php } ?>
        <div class="stat-card stat-card--total">
            <span class="stat-card__num"><?= $totalActions ?></span>
            <span class="stat-card__label">Celkem akcí</span>
        </div>
        <div class="stat-card stat-card--rate">
            <span class="stat-card__num"><?= $winRate ?> %</span>
            <span class="stat-card__label">Úspěšnost (výhry/vše)</span>
        </div>
    </div>

    <!-- ── Denní tabulka ── -->
    <?php if (count($activeDays) === 0) { ?>
        <p class="muted" style="margin-top:1.5rem;">
            Za <?= crm_h($currentMonthLabel) ?> zatím žádná aktivita.
        </p>
    <?php } else { ?>
    <div class="stats-table-wrap">
        <table class="stats-table">
            <thead>
                <tr>
                    <th class="stats-th-day">Den</th>
                    <?php foreach ($trackedStatuses as $s) { ?>
                        <th title="<?= crm_h($s) ?>"><?= crm_h($statusLabels[$s] ?? $s) ?></th>
                    <?php } ?>
                    <th>Celkem</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activeDays as $d => $dayCounts) {
                    $dayTotal = array_sum($dayCounts);
                    $dow = date('N', mktime(0, 0, 0, $month, $d, $year)); // 6=sat,7=sun
                    $weekendClass = ((int)$dow >= 6) ? ' stats-row--weekend' : '';
                ?>
                <tr class="<?= $weekendClass ?>">
                    <td class="stats-td-day">
                        <?= $d ?>. <?= mb_substr($monthNames[$month] ?? '', 0, 3) ?>
                        <span class="stats-dow"><?= ['', 'Po', 'Út', 'St', 'Čt', 'Pá', 'So', 'Ne'][(int)$dow] ?></span>
                    </td>
                    <?php foreach ($trackedStatuses as $s) {
                        $cnt = $dayCounts[$s] ?? 0;
                        $cellClass = $cnt > 0 ? (' stats-cell--' . strtolower(str_replace(['_', 'CALLED_'], ['', ''], $s))) : '';
                    ?>
                        <td class="stats-td-num<?= $cellClass ?>"><?= $cnt > 0 ? $cnt : '—' ?></td>
                    <?php } ?>
                    <td class="stats-td-total"><?= $dayTotal ?></td>
                </tr>
                <?php } ?>
            </tbody>
            <tfoot>
                <tr class="stats-row--total">
                    <td><strong>Celkem</strong></td>
                    <?php foreach ($trackedStatuses as $s) { ?>
                        <td class="stats-td-num"><strong><?= $summary[$s] ?></strong></td>
                    <?php } ?>
                    <td class="stats-td-total"><strong><?= $totalActions ?></strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php } ?>
</section>
