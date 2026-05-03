<?php
// e:\Snecinatripu\app\views\oz\performance.php
declare(strict_types=1);
/** @var array<string, mixed>          $user */
/** @var list<array<string, mixed>>    $ozRows        – id, jmeno, contracts, total_bmsl */
/** @var list<array<string, mixed>>    $stages        – id, stage_number, label, target_bmsl */
/** @var int                           $teamBmsl */
/** @var int                           $teamContracts */
/** @var int                           $year */
/** @var int                           $month */
/** @var string|null                   $flash */
/** @var string                        $csrf */

$czechMonths = ['','Leden','Únor','Březen','Duben','Květen','Červen',
                'Červenec','Srpen','Září','Říjen','Listopad','Prosinec'];

$isAdmin   = in_array((string)($user['role'] ?? ''), ['majitel', 'superadmin'], true);
$myId      = (int) $user['id'];

// ── Progress bar výpočet ──────────────────────────────────────────
$maxTarget = 0;
foreach ($stages as $s) {
    $t = (int) $s['target_bmsl'];
    if ($t > $maxTarget) $maxTarget = $t;
}

// Pokud je tým nad všemi stages, bar = 100 %
$barMax = $maxTarget > 0 ? max($maxTarget, $teamBmsl) : max($teamBmsl, 1);

// Kolik % je team BMSL z barMax (cap 100)
$barFill = $barMax > 0 ? min(100, (int) round($teamBmsl / $barMax * 100)) : 0;
?>

<style>
/* ══════════════════════════════════════════════════════════════
   OZ Performance — styly
══════════════════════════════════════════════════════════════ */

.perf-header {
    display: flex; align-items: center; flex-wrap: wrap;
    gap: 0.6rem; margin-bottom: 1.1rem;
}
.perf-header__title { font-size: 1.1rem; font-weight: 700; flex: 1; }
.perf-month-form    { display: flex; gap: 0.3rem; align-items: center; flex-wrap: wrap; }
.perf-month-select  {
    font-size: 0.8rem; padding: 0.25rem 0.45rem;
    background: var(--bg); color: var(--text);
    border: 1px solid rgba(0,0,0,0.15); border-radius: 5px;
}

/* ── Stage progress bar ── */
.oz-stage-wrap {
    background: var(--card); border: 1px solid rgba(0,0,0,0.07);
    border-radius: 10px; padding: 1rem 1.2rem; margin-bottom: 1.2rem;
}
.oz-stage-wrap__title {
    font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.06em;
    color: var(--muted); margin-bottom: 0.75rem;
}
.oz-stage-bar-outer {
    position: relative; height: 22px;
    background: rgba(0,0,0,0.07); border-radius: 12px;
    overflow: visible; margin-bottom: 2rem;
}
.oz-stage-bar-fill {
    height: 100%; border-radius: 12px;
    background: linear-gradient(90deg, rgba(155,89,182,0.6), rgba(155,89,182,1));
    transition: width 0.8s cubic-bezier(.4,0,.2,1);
    position: relative;
}
.oz-stage-bar-fill__label {
    position: absolute; right: 0.5rem; top: 50%;
    transform: translateY(-50%);
    font-size: 0.72rem; font-weight: 700; color: #fff;
    white-space: nowrap; pointer-events: none;
}
/* Stage marker (vertikální čára) */
.oz-stage-marker {
    position: absolute; top: -4px; bottom: -4px;
    width: 2px; border-radius: 1px;
    background: rgba(0,0,0,0.25);
    pointer-events: none;
}
.oz-stage-marker--done  { background: rgba(46,204,113,0.7); }
.oz-stage-marker__label {
    position: absolute; top: calc(100% + 8px); left: 50%;
    transform: translateX(-50%);
    white-space: nowrap; font-size: 0.68rem; color: var(--muted);
    text-align: center; line-height: 1.3;
}
.oz-stage-marker__label strong { color: var(--text); }
.oz-stage-marker--done .oz-stage-marker__label strong { color: #2ecc71; }

/* Stage chips pod barem */
.oz-stage-chips { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.4rem; }
.oz-stage-chip {
    font-size: 0.72rem; padding: 0.2rem 0.65rem; border-radius: 20px;
    border: 1px solid rgba(0,0,0,0.1);
    color: var(--muted);
}
.oz-stage-chip--done {
    background: rgba(46,204,113,0.12);
    border-color: rgba(46,204,113,0.35);
    color: #2ecc71; font-weight: 700;
}

.oz-stage-total {
    font-size: 0.82rem; color: var(--text); margin-top: 0.5rem;
    display: flex; align-items: center; gap: 0.5rem;
}
.oz-stage-total__val { font-size: 1.15rem; font-weight: 700; color: #9b59b6; }

/* ── Tabulka OZ ── */
.perf-table-wrap {
    background: var(--card); border: 1px solid rgba(0,0,0,0.07);
    border-radius: 10px; overflow: hidden;
}
.perf-table {
    width: 100%; border-collapse: collapse; font-size: 0.82rem;
}
.perf-table th {
    padding: 0.5rem 0.9rem; text-align: left;
    font-size: 0.67rem; text-transform: uppercase; letter-spacing: 0.05em;
    color: var(--muted); border-bottom: 1px solid rgba(0,0,0,0.07);
    white-space: nowrap;
}
.perf-table td {
    padding: 0.55rem 0.9rem;
    border-bottom: 1px solid rgba(0,0,0,0.04);
    vertical-align: middle;
}
.perf-table tbody tr:last-child td { border-bottom: none; }
.perf-table tbody tr:hover td { background: rgba(0,0,0,0.02); }
.perf-table__me td { background: rgba(155,89,182,0.06) !important; }
.perf-table__team td {
    border-top: 2px solid rgba(0,0,0,0.12);
    font-weight: 700; color: var(--text);
}
.perf-rank { font-size: 0.7rem; color: var(--muted); }
.perf-name-cell { display: flex; align-items: center; gap: 0.4rem; }
.perf-me-chip {
    font-size: 0.62rem; padding: 0.05rem 0.3rem;
    background: rgba(155,89,182,0.2); color: #9b59b6;
    border-radius: 3px; font-weight: 700;
}
.perf-bar-cell { min-width: 120px; }
.perf-mini-bar {
    height: 6px; border-radius: 3px;
    background: rgba(0,0,0,0.07);
    overflow: hidden;
}
.perf-mini-bar__fill {
    height: 100%; border-radius: 3px;
    background: #9b59b6;
    transition: width 0.6s ease;
}
.perf-bmsl-val { font-weight: 600; font-family: monospace; font-size: 0.85rem; }
.perf-contracts-val { font-weight: 700; color: #9b59b6; }
.perf-empty { color: var(--muted); font-size: 0.85rem; font-style: italic; }
</style>

<section class="card">

    <!-- Flash -->
    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info"><?= crm_h($flash) ?></p>
    <?php } ?>

    <!-- Záhlaví + výběr měsíce -->
    <div class="perf-header">
        <span class="perf-header__title">
            🏅 Výkon OZ týmu — <?= crm_h($czechMonths[$month] . ' ' . $year) ?>
        </span>

        <form method="get" action="<?= crm_h(crm_url('/oz/performance')) ?>" class="perf-month-form">
            <select name="month" class="perf-month-select" onchange="this.form.submit()">
                <?php for ($m = 1; $m <= 12; $m++) { ?>
                    <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>>
                        <?= crm_h($czechMonths[$m]) ?>
                    </option>
                <?php } ?>
            </select>
            <select name="year" class="perf-month-select" onchange="this.form.submit()">
                <?php for ($y = 2024; $y <= (int) date('Y') + 1; $y++) { ?>
                    <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
                <?php } ?>
            </select>
        </form>

        <?php if ($isAdmin) { ?>
        <a href="<?= crm_h(crm_url('/admin/oz-stages?year=' . $year . '&month=' . $month)) ?>"
           class="btn btn-secondary btn-sm">⚙ Nastavit stage cíle</a>
        <?php } ?>
        <a href="<?= crm_h(crm_url('/oz/leads')) ?>" class="btn btn-secondary btn-sm">← Zpět na plochu</a>
    </div>

    <!-- ── Progress bar se stages ── -->
    <div class="oz-stage-wrap">
        <div class="oz-stage-wrap__title">Celkový BMSL týmu</div>

        <?php if ($stages === []) { ?>
            <p style="font-size:0.8rem;color:var(--muted);margin-bottom:0.5rem;">
                Žádné stage cíle pro tento měsíc.
                <?php if ($isAdmin) { ?>
                    <a href="<?= crm_h(crm_url('/admin/oz-stages?year=' . $year . '&month=' . $month)) ?>"
                       style="color:#9b59b6;">Nastavit cíle →</a>
                <?php } ?>
            </p>
        <?php } else { ?>
        <!-- Bar -->
        <div class="oz-stage-bar-outer">
            <div class="oz-stage-bar-fill" style="width:<?= $barFill ?>%">
                <?php if ($teamBmsl > 0 && $barFill > 10) { ?>
                <span class="oz-stage-bar-fill__label">
                    <?= number_format($teamBmsl, 0, ',', ' ') ?> Kč
                </span>
                <?php } ?>
            </div>

            <?php foreach ($stages as $s) {
                $t    = (int) $s['target_bmsl'];
                $pct  = $barMax > 0 ? min(99, (int) round($t / $barMax * 100)) : 50;
                $done = $teamBmsl >= $t;
            ?>
            <div class="oz-stage-marker <?= $done ? 'oz-stage-marker--done' : '' ?>"
                 style="left:<?= $pct ?>%">
                <div class="oz-stage-marker__label">
                    <strong>
                        <?= $done ? '✓ ' : '' ?>Stage <?= (int) $s['stage_number'] ?>
                    </strong><br>
                    <?= crm_h((string) $s['label']) ?><br>
                    <?= number_format($t, 0, ',', ' ') ?> Kč
                </div>
            </div>
            <?php } ?>
        </div>

        <!-- Stage chips -->
        <div class="oz-stage-chips">
            <?php foreach ($stages as $s) {
                $done = $teamBmsl >= (int) $s['target_bmsl'];
            ?>
            <span class="oz-stage-chip <?= $done ? 'oz-stage-chip--done' : '' ?>">
                <?= $done ? '✓ ' : '' ?>Stage <?= (int) $s['stage_number'] ?>: <?= crm_h((string)$s['label']) ?>
                (<?= number_format((int) $s['target_bmsl'], 0, ',', ' ') ?> Kč)
            </span>
            <?php } ?>
        </div>
        <?php } ?>

        <!-- Celkové číslo -->
        <div class="oz-stage-total">
            <span class="oz-stage-total__val">
                <?= number_format($teamBmsl, 0, ',', ' ') ?> Kč
            </span>
            <span style="color:var(--muted);font-size:0.8rem;">
                celkový BMSL týmu — <?= $teamContracts ?> smluv
            </span>
        </div>
    </div>

    <!-- ── Tabulka OZ ── -->
    <div class="perf-table-wrap">
        <table class="perf-table">
            <thead>
                <tr>
                    <th style="width:2rem;">#</th>
                    <th>Obchodní zástupce</th>
                    <th style="text-align:right;">Smluv</th>
                    <th>BMSL (bez DPH)</th>
                    <th class="perf-bar-cell">Podíl z týmu</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($ozRows === []) { ?>
                <tr><td colspan="5" class="perf-empty" style="padding:1.2rem 0.9rem;">
                    Žádné podepsané smlouvy v tomto měsíci.
                </td></tr>
                <?php } ?>
                <?php foreach ($ozRows as $i => $row) {
                    $isMe  = (int) $row['id'] === $myId;
                    $bmsl  = (int) $row['total_bmsl'];
                    $cnt   = (int) $row['contracts'];
                    $share = $teamBmsl > 0 ? (int) round($bmsl / $teamBmsl * 100) : 0;
                ?>
                <tr class="<?= $isMe ? 'perf-table__me' : '' ?>">
                    <td class="perf-rank"><?= $i + 1 ?>.</td>
                    <td>
                        <div class="perf-name-cell">
                            <?= crm_h((string) $row['jmeno']) ?>
                            <?php if ($isMe) { ?><span class="perf-me-chip">JÁ</span><?php } ?>
                        </div>
                    </td>
                    <td style="text-align:right;">
                        <span class="perf-contracts-val"><?= $cnt ?></span>
                    </td>
                    <td>
                        <span class="perf-bmsl-val">
                            <?= $bmsl > 0 ? number_format($bmsl, 0, ',', ' ') . ' Kč' : '—' ?>
                        </span>
                    </td>
                    <td class="perf-bar-cell">
                        <div class="perf-mini-bar">
                            <div class="perf-mini-bar__fill" style="width:<?= $share ?>%"></div>
                        </div>
                        <span style="font-size:0.68rem;color:var(--muted);"><?= $share ?> %</span>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
            <?php if ($teamContracts > 0) { ?>
            <tfoot>
                <tr class="perf-table__team">
                    <td></td>
                    <td>TÝM CELKEM</td>
                    <td style="text-align:right;">
                        <span class="perf-contracts-val"><?= $teamContracts ?></span>
                    </td>
                    <td>
                        <span class="perf-bmsl-val">
                            <?= number_format($teamBmsl, 0, ',', ' ') ?> Kč
                        </span>
                    </td>
                    <td></td>
                </tr>
            </tfoot>
            <?php } ?>
        </table>
    </div>

</section>
