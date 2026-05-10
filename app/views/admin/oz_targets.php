<?php
// e:\Snecinatripu\app\views\admin\oz_targets.php
declare(strict_types=1);
/** @var list<array<string, mixed>>        $ozList        Aktivní OZ */
/** @var array<int, list<string>>          $ozRegions     Regiony per OZ id */
/** @var array<int, array<string, int>>    $savedTargets  target per OZ per region */
/** @var array<int, array<string, int>>    $received      přijaté leady per OZ per region */
/** @var array<int, array<string, int>>    $flagged       reklamace per OZ per region */
/** @var int    $year */
/** @var int    $month */
/** @var string|null $flash */
/** @var string $csrf */

$monthNames = ['', 'Leden','Únor','Březen','Duben','Květen','Červen',
               'Červenec','Srpen','Září','Říjen','Listopad','Prosinec'];

// Všechny regiony přes všechny OZ (pro záhlaví)
$allRegions = [];
foreach ($ozRegions as $regions) {
    foreach ($regions as $reg) {
        $allRegions[$reg] = true;
    }
}
ksort($allRegions);
$allRegions = array_keys($allRegions);

// OZ bez nastavených regionů — pro warning banner nahoře
$ozWithoutRegions = [];
foreach ($ozList as $oz) {
    $regs = $ozRegions[(int) $oz['id']] ?? [];
    if ($regs === []) {
        $ozWithoutRegions[] = $oz;
    }
}
?>

<style>
.ozt-toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:0.5rem; margin-bottom:1.2rem; }
.ozt-month-nav { display:flex; align-items:center; gap:0.4rem; }
.ozt-month-label { font-weight:700; font-size:1rem; min-width:8rem; text-align:center; }

.ozt-table-wrap { overflow-x:auto; }
.ozt-table {
    width:100%; border-collapse:collapse; font-size:0.82rem;
    background:var(--card);
}
.ozt-table th, .ozt-table td {
    padding:0.45rem 0.6rem; border:1px solid rgba(0,0,0,0.07);
    text-align:center; white-space:nowrap;
}
.ozt-table th { background:rgba(0,0,0,0.05); font-weight:700; }
.ozt-table th.ozt-oz-col { text-align:left; min-width:130px; }
.ozt-oz-name { font-weight:600; text-align:left; }

/* Buňka s inputem a received */
.ozt-cell { display:flex; flex-direction:column; align-items:center; gap:0.15rem; }
.ozt-input {
    width:4rem; text-align:center; padding:0.2rem 0.3rem;
    background:var(--bg); color:var(--text);
    border:1px solid rgba(0,0,0,0.18); border-radius:4px;
    font-size:0.8rem;
}
.ozt-input:focus { outline:none; border-color:var(--primary); }
.ozt-received-row { display:flex; align-items:center; gap:0.3rem; }
.ozt-received {
    font-size:0.8rem; font-weight:700; color:#2ecc71;
}
.ozt-received--none { color:var(--muted); font-weight:400; }
.ozt-flagged { font-size:0.68rem; color:#e74c3c; }
.ozt-progress-bar {
    width:4rem; height:4px; background:rgba(0,0,0,0.1);
    border-radius:2px; overflow:hidden;
}
.ozt-progress-fill { height:100%; border-radius:2px; transition:width 0.3s; }
.ozt-progress-fill--ok   { background:#2ecc71; }
.ozt-progress-fill--warn { background:#f0a030; }
.ozt-progress-fill--over { background:#3498db; }

.ozt-no-region { color:var(--muted); font-size:0.72rem; }

/* Warning banner pro OZ bez regionů */
.ozt-warn {
    background: rgba(231,76,60,0.06);
    border: 1px solid rgba(231,76,60,0.3);
    border-left: 4px solid #e74c3c;
    border-radius: 0 8px 8px 0;
    padding: 0.85rem 1rem;
    margin-bottom: 1rem;
    line-height: 1.55;
}
.ozt-warn__title {
    font-weight: 700; color: #e74c3c; font-size: 0.92rem; margin-bottom: 0.4rem;
}
.ozt-warn__list { margin: 0.3rem 0; padding-left: 1.4rem; font-size: 0.85rem; }
.ozt-warn__list li { margin-bottom: 0.2rem; }
.ozt-warn__list a {
    color: #e74c3c; font-weight: 600; text-decoration: none;
    border-bottom: 1px dashed rgba(231,76,60,0.5);
}
.ozt-warn__list a:hover {
    color: #c0392b; border-bottom-style: solid;
}
.ozt-warn__hint { font-size: 0.78rem; color: var(--muted); margin-top: 0.4rem; }

/* Celkem sloupec */
.ozt-total-cell { display:flex; flex-direction:column; align-items:center; gap:0.3rem; }
.ozt-total-numbers { font-size:0.85rem; font-weight:700; }
.ozt-total-numbers .rec { color:#2ecc71; }
.ozt-total-numbers .sep { color:var(--muted); }
.ozt-total-numbers .tgt { color:var(--text); }
.ozt-detail-btn {
    font-size:0.68rem; padding:0.15rem 0.5rem;
    background:rgba(52,152,219,0.15); color:#3498db;
    border:1px solid rgba(52,152,219,0.3); border-radius:4px;
    text-decoration:none; white-space:nowrap;
    transition:background 0.15s;
}
.ozt-detail-btn:hover { background:rgba(52,152,219,0.28); }
</style>

<section class="card">
    <h1>Kvóty OZ</h1>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info"><?= crm_h($flash) ?></p>
    <?php } ?>

    <?php if ($ozWithoutRegions !== []) { ?>
        <div class="ozt-warn">
            <div class="ozt-warn__title">
                ⚠ <?= count($ozWithoutRegions) ?>
                <?= count($ozWithoutRegions) === 1 ? 'obchodník nemá' : 'obchodníků nemá' ?>
                přiřazené žádné regiony
            </div>
            <p style="margin:0 0 0.3rem;font-size:0.85rem;">
                Bez regionů nelze zadat per-region kvóty. Otevři profil OZ a zaškrtni
                <strong>„Povolené regiony"</strong> + nastav <strong>„Primární region"</strong>.
            </p>
            <ul class="ozt-warn__list">
                <?php foreach ($ozWithoutRegions as $oz) { ?>
                    <li>
                        <a href="<?= crm_h(crm_url('/admin/users/edit?id=' . (int) $oz['id'])) ?>">
                            🔧 <?= crm_h((string) $oz['jmeno']) ?> → nastavit regiony
                        </a>
                    </li>
                <?php } ?>
            </ul>
            <p class="ozt-warn__hint">
                💡 Po uložení regionů se vrať sem (F5) a uvidíš per-region sloupce kam se zadávají kvóty.
            </p>
        </div>
    <?php } ?>

    <!-- Navigace měsíc / rok -->
    <div class="ozt-toolbar">
        <?php
        $prevMonth = $month - 1; $prevYear = $year;
        if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
        $nextMonth = $month + 1; $nextYear = $year;
        if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
        ?>
        <div class="ozt-month-nav">
            <a href="<?= crm_h(crm_url('/admin/oz-targets?year=' . $prevYear . '&month=' . $prevMonth)) ?>"
               class="btn btn-secondary btn-sm">← Předchozí</a>
            <span class="ozt-month-label"><?= crm_h($monthNames[$month] . ' ' . $year) ?></span>
            <a href="<?= crm_h(crm_url('/admin/oz-targets?year=' . $nextYear . '&month=' . $nextMonth)) ?>"
               class="btn btn-secondary btn-sm">Další →</a>
        </div>
        <a href="<?= crm_h(crm_url('/admin/oz-targets?year=' . date('Y') . '&month=' . date('n'))) ?>"
           class="btn btn-secondary btn-sm">Aktuální měsíc</a>
        <a href="<?= crm_h(crm_url('/admin/users')) ?>" class="btn btn-secondary btn-sm">← Zpět</a>
    </div>

    <?php if ($ozList === []) { ?>
        <p class="muted">Žádní aktivní obchodní zástupci.</p>
    <?php } else { ?>

    <form method="post" action="<?= crm_h(crm_url('/admin/oz-targets/save')) ?>">
        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
        <input type="hidden" name="year"  value="<?= $year ?>">
        <input type="hidden" name="month" value="<?= $month ?>">

        <div class="ozt-table-wrap">
            <table class="ozt-table">
                <thead>
                    <tr>
                        <th class="ozt-oz-col">Obchodní zástupce</th>
                        <?php foreach ($allRegions as $reg) { ?>
                            <th><?= crm_h(crm_region_label($reg)) ?></th>
                        <?php } ?>
                        <th>Celkem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ozList as $oz) {
                        $uid        = (int) $oz['id'];
                        $ozRegs     = $ozRegions[$uid] ?? [];
                        $ozTotalTgt = 0;
                        $ozTotalRec = 0;
                        $ozTotalFlg = 0;
                    ?>
                    <tr>
                        <td class="ozt-oz-name"><?= crm_h((string) $oz['jmeno']) ?></td>

                        <?php foreach ($allRegions as $reg) {
                            $hasRegion = in_array($reg, $ozRegs, true);
                            $tgt = $savedTargets[$uid][$reg] ?? 0;
                            $rec = $received[$uid][$reg] ?? 0;
                            $flg = $flagged[$uid][$reg] ?? 0;
                            if ($hasRegion) {
                                $ozTotalTgt += $tgt;
                                $ozTotalRec += $rec;
                                $ozTotalFlg += $flg;
                                $pct = $tgt > 0 ? min(100, (int) round($rec / $tgt * 100)) : 0;
                                $barClass = match(true) {
                                    $tgt === 0       => 'ozt-progress-fill--warn',
                                    $rec >= $tgt     => 'ozt-progress-fill--over',
                                    $pct >= 60       => 'ozt-progress-fill--warn',
                                    default          => 'ozt-progress-fill--ok',
                                };
                            }
                        ?>
                        <td>
                            <?php if ($hasRegion) { ?>
                                <div class="ozt-cell">
                                    <input type="number" min="0" max="9999"
                                           name="targets[<?= $uid ?>][<?= crm_h($reg) ?>]"
                                           value="<?= $tgt ?>"
                                           class="ozt-input"
                                           title="Kvóta pro <?= crm_h(crm_region_label($reg)) ?>">
                                    <div class="ozt-received-row">
                                        <span class="ozt-received <?= $rec === 0 ? 'ozt-received--none' : '' ?>"
                                              title="Přijatých leadů">
                                            <?= $rec ?>
                                        </span>
                                        <span style="color:var(--muted);font-size:0.7rem;">/ <?= $tgt ?></span>
                                        <?php if ($flg > 0) { ?>
                                            <span class="ozt-flagged" title="Reklamací">⚠<?= $flg ?></span>
                                        <?php } ?>
                                    </div>
                                    <?php if ($tgt > 0) { ?>
                                    <div class="ozt-progress-bar">
                                        <div class="ozt-progress-fill <?= $barClass ?>"
                                             style="width:<?= $pct ?>%"></div>
                                    </div>
                                    <?php } ?>
                                </div>
                            <?php } else { ?>
                                <span class="ozt-no-region">—</span>
                            <?php } ?>
                        </td>
                        <?php } ?>

                        <!-- Celkem + odkaz na detail -->
                        <td>
                            <div class="ozt-total-cell">
                                <div class="ozt-total-numbers">
                                    <span class="rec"><?= $ozTotalRec ?></span>
                                    <span class="sep"> / </span>
                                    <span class="tgt"><?= $ozTotalTgt ?></span>
                                </div>
                                <?php if ($ozTotalFlg > 0) { ?>
                                    <span class="ozt-flagged">⚠ <?= $ozTotalFlg ?> rekl.</span>
                                <?php } ?>
                                <a href="<?= crm_h(crm_url('/admin/oz-targets/detail?oz_id=' . $uid . '&year=' . $year . '&month=' . $month)) ?>"
                                   class="ozt-detail-btn">Detail →</a>
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top:1rem;">
            <button type="submit" class="btn">💾 Uložit kvóty</button>
            <span class="muted" style="font-size:0.78rem;margin-left:0.7rem;">
                Zelené číslo = přijatých leadů tento měsíc. Zadejte cíl (kvótu) pro každý region.
            </span>
        </div>
    </form>

    <?php } ?>
</section>
