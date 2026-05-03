<?php
// e:\Snecinatripu\app\views\admin\oz_targets_detail.php
declare(strict_types=1);
/** @var array<string, mixed>                                        $oz           OZ user row */
/** @var array<string, int>                                          $targets      region → kvóta */
/** @var array<int, array<string, mixed>>                            $byCaller     caller_id → data */
/** @var list<array<string, mixed>>                                  $contacts     všechny kontakty */
/** @var float                                                       $rewardPerWin */
/** @var int                                                         $year */
/** @var int                                                         $month */
/** @var string|null                                                 $flash */
/** @var string                                                      $csrf */

$monthNames = ['', 'Leden','Únor','Březen','Duben','Květen','Červen',
               'Červenec','Srpen','Září','Říjen','Listopad','Prosinec'];

$totalContacts = count($contacts);
$totalFlagged  = count(array_filter($contacts, fn($c) => (int)($c['flagged'] ?? 0) === 1));
$totalValid    = $totalContacts - $totalFlagged;
$totalTarget   = array_sum($targets);
$totalPayout   = $totalValid * $rewardPerWin;
?>

<style>
/* ── OZ Detail ── */
.ozd-header { margin-bottom:1.2rem; }
.ozd-header h1 { margin:0 0 0.15rem; }
.ozd-subtitle { color:var(--muted); font-size:0.82rem; }

.ozd-toolbar { display:flex; gap:0.5rem; flex-wrap:wrap; margin-bottom:1.2rem; }

/* Souhrnné karty */
.ozd-summary { display:flex; gap:0.75rem; flex-wrap:wrap; margin-bottom:1.4rem; }
.ozd-scard {
    background:var(--card); border:1px solid rgba(0,0,0,0.08);
    border-radius:10px; padding:0.65rem 1rem; flex:1; min-width:110px;
}
.ozd-scard__val { font-size:1.4rem; font-weight:700; line-height:1; color:var(--text); }
.ozd-scard__val--green { color:#2ecc71; }
.ozd-scard__val--red   { color:#e74c3c; }
.ozd-scard__val--blue  { color:#3498db; }
.ozd-scard__lbl { font-size:0.68rem; text-transform:uppercase; letter-spacing:0.05em; color:var(--muted); margin-top:0.2rem; }

/* Navolávačka sekce */
.ozd-caller { margin-bottom:1.4rem; }
.ozd-caller__header {
    display:flex; align-items:center; justify-content:space-between;
    flex-wrap:wrap; gap:0.5rem;
    background:rgba(0,0,0,0.04); border:1px solid rgba(0,0,0,0.08);
    border-radius:8px 8px 0 0; padding:0.6rem 0.9rem;
    font-weight:700;
}
.ozd-caller__name { font-size:0.95rem; }
.ozd-caller__meta { display:flex; gap:0.9rem; font-size:0.78rem; font-weight:400; color:var(--muted); }
.ozd-caller__meta span { white-space:nowrap; }
.ozd-caller__meta .green { color:#2ecc71; font-weight:700; }
.ozd-caller__meta .red   { color:#e74c3c; font-weight:700; }
.ozd-caller__meta .blue  { color:#3498db; font-weight:700; }
.ozd-caller__payout {
    font-size:0.82rem; font-weight:700;
    background:rgba(46,204,113,0.12); color:#2ecc71;
    border-radius:6px; padding:0.2rem 0.55rem;
}

/* Region skupina */
.ozd-region { border:1px solid rgba(0,0,0,0.06); border-top:none; }
.ozd-region:last-child { border-radius:0 0 8px 8px; }
.ozd-region__header {
    display:flex; align-items:center; justify-content:space-between;
    background:rgba(0,0,0,0.025); padding:0.35rem 0.9rem;
    font-size:0.72rem; font-weight:700; text-transform:uppercase;
    letter-spacing:0.05em; color:var(--muted);
}
.ozd-region__cnt { font-size:0.75rem; font-weight:700; color:#2ecc71; }

/* Tabulka kontaktů */
.ozd-contacts-table {
    width:100%; border-collapse:collapse; font-size:0.78rem;
}
.ozd-contacts-table th,
.ozd-contacts-table td {
    padding:0.3rem 0.9rem; border-bottom:1px solid rgba(0,0,0,0.04);
    text-align:left; vertical-align:middle;
}
.ozd-contacts-table th { color:var(--muted); font-weight:600; font-size:0.7rem; }
.ozd-contacts-table tr:last-child td { border-bottom:none; }
.ozd-contacts-table tr:hover td { background:rgba(0,0,0,0.02); }

.ozd-flag-badge {
    display:inline-block; font-size:0.65rem; padding:0.1rem 0.4rem;
    border-radius:4px; background:rgba(231,76,60,0.18); color:#e74c3c;
    white-space:nowrap;
}
.ozd-flag-reason {
    font-size:0.7rem; color:#e74c3c; margin-top:0.15rem;
    font-style:italic;
}
.ozd-date { color:var(--muted); font-size:0.72rem; }

/* Platební souhrn */
.ozd-payout-table {
    width:100%; border-collapse:collapse; font-size:0.82rem;
    margin-top:1.4rem;
}
.ozd-payout-table th,
.ozd-payout-table td {
    padding:0.4rem 0.7rem; border:1px solid rgba(0,0,0,0.07);
    text-align:center;
}
.ozd-payout-table th { background:rgba(0,0,0,0.05); font-weight:700; font-size:0.75rem; }
.ozd-payout-table th:first-child,
.ozd-payout-table td:first-child { text-align:left; }
.ozd-payout-table tfoot td { font-weight:700; background:rgba(0,0,0,0.04); }
.ozd-payout-table .green { color:#2ecc71; font-weight:700; }
.ozd-payout-table .red   { color:#e74c3c; }
.ozd-payout-table .payout{ color:#3498db; font-weight:700; }

.ozd-no-contacts { color:var(--muted); padding:1rem 0.9rem; font-size:0.82rem; }
</style>

<section class="card">

    <div class="ozd-header">
        <h1>Detail leadů — <?= crm_h((string) $oz['jmeno']) ?></h1>
        <p class="ozd-subtitle"><?= crm_h($monthNames[$month] . ' ' . $year) ?></p>
    </div>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info"><?= crm_h($flash) ?></p>
    <?php } ?>

    <div class="ozd-toolbar">
        <?php
        $prevMonth = $month - 1; $prevYear = $year;
        if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
        $nextMonth = $month + 1; $nextYear = $year;
        if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
        $detailBase = '/admin/oz-targets/detail?oz_id=' . (int)$oz['id'];
        ?>
        <a href="<?= crm_h(crm_url($detailBase . '&year=' . $prevYear . '&month=' . $prevMonth)) ?>"
           class="btn btn-secondary btn-sm">← Předchozí</a>
        <strong style="align-self:center;"><?= crm_h($monthNames[$month] . ' ' . $year) ?></strong>
        <a href="<?= crm_h(crm_url($detailBase . '&year=' . $nextYear . '&month=' . $nextMonth)) ?>"
           class="btn btn-secondary btn-sm">Další →</a>
        <a href="<?= crm_h(crm_url('/admin/oz-targets?year=' . $year . '&month=' . $month)) ?>"
           class="btn btn-secondary btn-sm">← Zpět na kvóty</a>

        <!-- PDF / tisk -->
        <?php
        $printBase = crm_url('/admin/oz-targets/print?oz_id=' . (int)$oz['id'] . '&year=' . $year . '&month=' . $month);
        ?>
        <div style="margin-left:auto;display:flex;gap:0.4rem;flex-wrap:wrap;align-items:center;">
            <span style="font-size:0.72rem;color:var(--muted);">Stáhnout PDF:</span>
            <!-- Celý přehled -->
            <a href="<?= crm_h($printBase) ?>"
               target="_blank"
               class="btn btn-secondary btn-sm" style="background:rgba(52,152,219,0.15);border-color:rgba(52,152,219,0.3);color:#3498db;">
                📄 Celý přehled
            </a>
            <!-- Per navolávačka -->
            <?php foreach ($byCaller as $callerId => $callerData) { ?>
                <a href="<?= crm_h($printBase . '&caller_id=' . $callerId) ?>"
                   target="_blank"
                   class="btn btn-secondary btn-sm" style="background:rgba(46,204,113,0.1);border-color:rgba(46,204,113,0.3);color:#2ecc71;">
                    📄 <?= crm_h((string) $callerData['name']) ?>
                </a>
            <?php } ?>
        </div>
    </div>

    <!-- Souhrn -->
    <div class="ozd-summary">
        <div class="ozd-scard">
            <div class="ozd-scard__val ozd-scard__val--green"><?= $totalContacts ?></div>
            <div class="ozd-scard__lbl">Celkem leadů</div>
        </div>
        <div class="ozd-scard">
            <div class="ozd-scard__val"><?= $totalTarget > 0 ? $totalTarget : '—' ?></div>
            <div class="ozd-scard__lbl">Kvóta měsíce</div>
        </div>
        <?php if ($totalFlagged > 0) { ?>
        <div class="ozd-scard">
            <div class="ozd-scard__val ozd-scard__val--red"><?= $totalFlagged ?></div>
            <div class="ozd-scard__lbl">Reklamace OZ</div>
        </div>
        <div class="ozd-scard">
            <div class="ozd-scard__val"><?= $totalValid ?></div>
            <div class="ozd-scard__lbl">Platných leadů</div>
        </div>
        <?php } ?>
        <?php if ($rewardPerWin > 0) { ?>
        <div class="ozd-scard">
            <div class="ozd-scard__val ozd-scard__val--blue"><?= number_format($totalPayout, 0, ',', ' ') ?> Kč</div>
            <div class="ozd-scard__lbl">Celkem k vyplacení</div>
        </div>
        <?php } ?>
        <div class="ozd-scard">
            <div class="ozd-scard__val"><?= count($byCaller) ?></div>
            <div class="ozd-scard__lbl">Navolávačky</div>
        </div>
    </div>

    <!-- Per navolávačka -->
    <?php if ($byCaller === []) { ?>
        <p class="ozd-no-contacts">Žádné navolané leady pro <?= crm_h($monthNames[$month] . ' ' . $year) ?>.</p>
    <?php } else { ?>

        <?php foreach ($byCaller as $callerId => $callerData) {
            $callerTotal   = (int) $callerData['total'];
            $callerFlagged = (int) $callerData['flagged'];
            $callerValid   = $callerTotal - $callerFlagged;
            $callerPayout  = $callerValid * $rewardPerWin;
        ?>
        <div class="ozd-caller">
            <div class="ozd-caller__header">
                <span class="ozd-caller__name">📞 <?= crm_h((string) $callerData['name']) ?></span>
                <div class="ozd-caller__meta">
                    <span>Celkem: <span class="green"><?= $callerTotal ?></span></span>
                    <?php if ($callerFlagged > 0) { ?>
                        <span>Reklamace: <span class="red"><?= $callerFlagged ?></span></span>
                        <span>Platných: <span class="green"><?= $callerValid ?></span></span>
                    <?php } ?>
                    <?php if ($rewardPerWin > 0) { ?>
                        <span class="ozd-caller__payout">💰 <?= number_format($callerPayout, 0, ',', ' ') ?> Kč</span>
                    <?php } ?>
                </div>
            </div>

            <?php
            $byRegion = $callerData['byRegion'];
            ksort($byRegion);
            foreach ($byRegion as $region => $regionContacts) {
                $regionTotal   = count($regionContacts);
                $regionFlagged = count(array_filter($regionContacts, fn($c) => (int)($c['flagged'] ?? 0) === 1));
            ?>
            <div class="ozd-region">
                <div class="ozd-region__header">
                    <span><?= crm_h(crm_region_label($region)) ?></span>
                    <span class="ozd-region__cnt">
                        <?= $regionTotal ?> leadů
                        <?php if ($regionFlagged > 0) { ?>
                            · <span style="color:#e74c3c;"><?= $regionFlagged ?> reklamace</span>
                        <?php } ?>
                    </span>
                </div>
                <table class="ozd-contacts-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Firma / zákazník</th>
                            <th>Telefon</th>
                            <th>Datum navolání</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($regionContacts as $i => $c) {
                            $isFlagged = (int) ($c['flagged'] ?? 0) === 1;
                        ?>
                        <tr <?= $isFlagged ? 'style="opacity:0.65;"' : '' ?>>
                            <td style="color:var(--muted);"><?= $i + 1 ?></td>
                            <td>
                                <strong><?= crm_h((string) ($c['firma'] ?? '—')) ?></strong>
                                <?php if (!empty($c['poznamka'])) { ?>
                                    <div style="font-size:0.68rem;color:var(--muted);margin-top:0.1rem;"><?= crm_h((string) $c['poznamka']) ?></div>
                                <?php } ?>
                            </td>
                            <td>
                                <?= !empty($c['telefon']) ? crm_h((string) $c['telefon']) : '<span style="color:var(--muted);">—</span>' ?>
                            </td>
                            <td class="ozd-date">
                                <?= !empty($c['datum_volani'])
                                    ? crm_h(date('d.m.Y', strtotime((string) $c['datum_volani'])))
                                    : '—' ?>
                            </td>
                            <td>
                                <?php if ($isFlagged) { ?>
                                    <span class="ozd-flag-badge">⚠ Reklamace</span>
                                    <?php if (!empty($c['flag_reason'])) { ?>
                                        <div class="ozd-flag-reason"><?= crm_h((string) $c['flag_reason']) ?></div>
                                    <?php } ?>
                                <?php } else { ?>
                                    <span style="color:#2ecc71;font-size:0.72rem;">✓ OK</span>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php } ?>
        </div>
        <?php } ?>

    <?php } ?>

    <!-- Platební přehled navolávačky -->
    <?php if ($byCaller !== [] && $rewardPerWin > 0) { ?>
    <h2 style="margin-top:1.5rem;margin-bottom:0.5rem;font-size:0.9rem;text-transform:uppercase;letter-spacing:0.06em;color:var(--muted);">
        💰 Platební přehled navolávačky
    </h2>
    <div style="overflow-x:auto;">
        <table class="ozd-payout-table">
            <thead>
                <tr>
                    <th>Navolávačka</th>
                    <th>Leadů celkem</th>
                    <th>Reklamace</th>
                    <th>Platných leadů</th>
                    <th>Sazba / lead</th>
                    <th>K vyplacení</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($byCaller as $callerId => $callerData) {
                    $ct  = (int) $callerData['total'];
                    $cf  = (int) $callerData['flagged'];
                    $cv  = $ct - $cf;
                    $cp  = $cv * $rewardPerWin;
                ?>
                <tr>
                    <td><?= crm_h((string) $callerData['name']) ?></td>
                    <td><?= $ct ?></td>
                    <td class="red"><?= $cf > 0 ? $cf : '—' ?></td>
                    <td class="green"><?= $cv ?></td>
                    <td><?= number_format($rewardPerWin, 0, ',', ' ') ?> Kč</td>
                    <td class="payout"><?= number_format($cp, 0, ',', ' ') ?> Kč</td>
                </tr>
                <?php } ?>
            </tbody>
            <tfoot>
                <tr>
                    <td>CELKEM</td>
                    <td><?= $totalContacts ?></td>
                    <td class="red"><?= $totalFlagged > 0 ? $totalFlagged : '—' ?></td>
                    <td class="green"><?= $totalValid ?></td>
                    <td>—</td>
                    <td class="payout"><?= number_format($totalPayout, 0, ',', ' ') ?> Kč</td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php } ?>

</section>
