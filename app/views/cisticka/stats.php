<?php
// app/views/cisticka/stats.php
declare(strict_types=1);
/** @var array<string,mixed>          $user */
/** @var string|null                  $flash */
/** @var int                          $year */
/** @var int                          $month */
/** @var array<string,int>            $summary       ['TM'=>n, 'O2'=>n, 'VF_SKIP'=>n] */
/** @var array<int,array<string,int>> $activeDays */
/** @var list<array<string,mixed>>    $monthOptions */
/** @var int                          $totalActions */
/** @var int                          $daysInMonth */

$monthNames = [
    1 => 'Leden', 2 => 'Únor', 3 => 'Březen', 4 => 'Duben',
    5 => 'Květen', 6 => 'Červen', 7 => 'Červenec', 8 => 'Srpen',
    9 => 'Září', 10 => 'Říjen', 11 => 'Listopad', 12 => 'Prosinec',
];
$currentMonthLabel = ($monthNames[$month] ?? $month) . ' ' . $year;
$readyTotal = $summary['TM'] + $summary['O2'];
$vfRate     = $totalActions > 0 ? round($summary['VF_SKIP'] / $totalActions * 100, 1) : 0.0;
?>
<section class="card">
    <div class="stats-header">
        <div>
            <h1>Můj výkon — čistička</h1>
            <p class="muted">Přihlášena: <strong><?= crm_h((string) ($user['jmeno'] ?? '')) ?></strong></p>
        </div>
        <a href="<?= crm_h(crm_url('/cisticka')) ?>" class="btn btn-secondary btn-sm">← Zpět na ověřování</a>
    </div>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info"><?= crm_h($flash) ?></p>
    <?php } ?>

    <!-- ── Filtr měsíce ── -->
    <div class="stats-filter-bar">
        <form method="get" action="<?= crm_h(crm_url('/cisticka/stats')) ?>" class="stats-filter-form">
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

    <h2 class="stats-period-title"><?= crm_h($currentMonthLabel) ?></h2>

    <!-- ── Souhrnné karty ── -->
    <div class="stat-cards-grid">
        <div class="stat-card" style="border-color:#ff69b455;background:rgba(255,105,180,0.08);">
            <span class="stat-card__num" style="color:#ff69b4;"><?= $summary['TM'] ?></span>
            <span class="stat-card__label">TM ověřeno</span>
        </div>
        <div class="stat-card" style="border-color:#3d8bfd55;background:rgba(61,139,253,0.08);">
            <span class="stat-card__num" style="color:#3d8bfd;"><?= $summary['O2'] ?></span>
            <span class="stat-card__label">O2 ověřeno</span>
        </div>
        <div class="stat-card" style="border-color:#22c55e44;background:rgba(34,197,94,0.07);">
            <span class="stat-card__num" style="color:#22c55e;"><?= $readyTotal ?></span>
            <span class="stat-card__label">TM + O2 celkem</span>
        </div>
        <div class="stat-card stat-card--bad">
            <span class="stat-card__num"><?= $summary['VF_SKIP'] ?></span>
            <span class="stat-card__label">VF přeskočeno</span>
        </div>
        <div class="stat-card stat-card--total">
            <span class="stat-card__num"><?= $totalActions ?></span>
            <span class="stat-card__label">Celkem zpracováno</span>
        </div>
        <div class="stat-card" style="border-color:rgba(255,255,255,0.12);">
            <span class="stat-card__num" style="color:var(--muted);"><?= $vfRate ?> %</span>
            <span class="stat-card__label">Podíl VF</span>
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
                    <th style="color:#ff69b4;">TM</th>
                    <th style="color:#3d8bfd;">O2</th>
                    <th style="color:#22c55e;">TM+O2</th>
                    <th style="color:#f06565;">VF skip</th>
                    <th>Celkem</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activeDays as $d => $dc) {
                    $dayTotal = array_sum($dc);
                    $dow = (int) date('N', mktime(0, 0, 0, $month, $d, $year));
                    $wkClass = $dow >= 6 ? ' stats-row--weekend' : '';
                    $ready = $dc['TM'] + $dc['O2'];
                ?>
                <tr class="<?= $wkClass ?>">
                    <td class="stats-td-day">
                        <?= $d ?>. <?= mb_substr($monthNames[$month] ?? '', 0, 3) ?>
                        <span class="stats-dow"><?= ['','Po','Út','St','Čt','Pá','So','Ne'][$dow] ?></span>
                    </td>
                    <td class="stats-td-num <?= $dc['TM'] > 0 ? 'stats-cell--ok' : '' ?>"
                        style="<?= $dc['TM'] > 0 ? 'color:#ff69b4;font-weight:700;' : '' ?>">
                        <?= $dc['TM'] > 0 ? $dc['TM'] : '—' ?>
                    </td>
                    <td class="stats-td-num <?= $dc['O2'] > 0 ? 'stats-cell--callback' : '' ?>">
                        <?= $dc['O2'] > 0 ? $dc['O2'] : '—' ?>
                    </td>
                    <td class="stats-td-num" style="<?= $ready > 0 ? 'color:#22c55e;font-weight:700;' : '' ?>">
                        <?= $ready > 0 ? $ready : '—' ?>
                    </td>
                    <td class="stats-td-num <?= $dc['VF_SKIP'] > 0 ? 'stats-cell--bad' : '' ?>">
                        <?= $dc['VF_SKIP'] > 0 ? $dc['VF_SKIP'] : '—' ?>
                    </td>
                    <td class="stats-td-total"><?= $dayTotal ?></td>
                </tr>
                <?php } ?>
            </tbody>
            <tfoot>
                <tr class="stats-row--total">
                    <td><strong>Celkem</strong></td>
                    <td style="color:#ff69b4;"><strong><?= $summary['TM'] ?></strong></td>
                    <td style="color:#3d8bfd;"><strong><?= $summary['O2'] ?></strong></td>
                    <td style="color:#22c55e;"><strong><?= $readyTotal ?></strong></td>
                    <td style="color:#f06565;"><strong><?= $summary['VF_SKIP'] ?></strong></td>
                    <td class="stats-td-total"><strong><?= $totalActions ?></strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php } ?>
</section>
