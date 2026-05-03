<?php
// e:\Snecinatripu\app\views\oz\index.php
declare(strict_types=1);
/** @var array<string, mixed>                              $user */
/** @var list<string>                                      $myRegions       Regiony OZ z user_regions */
/** @var list<string>                                      $allRegions      Regiony + kde byly leady */
/** @var array<string, int>                               $targets         region → kvóta */
/** @var array<string, int>                               $received        region → počet leadů */
/** @var array<string, list<array<string, mixed>>>        $contactsByRegion */
/** @var list<array<string, mixed>>                        $myContacts      všechny kontakty tohoto OZ */
/** @var int                                               $totalReceived */
/** @var int                                               $totalFlagged */
/** @var int                                               $totalTarget */
/** @var int                                               $year */
/** @var int                                               $month */
/** @var bool                                              $isCurrentMonth */
/** @var int                                               $monthWins */
/** @var int                                               $monthBmsl */
/** @var array<string,mixed>                               $teamStats */
/** @var list<array<string,mixed>>                         $teamStages */
/** @var list<array<string,mixed>>                         $personalMilestones */
/** @var string|null                                       $flash */
/** @var string                                            $csrf */
$monthWins          = $monthWins          ?? 0;
$monthBmsl          = $monthBmsl          ?? 0;
$teamStats          = $teamStats          ?? ['contracts' => 0, 'bmsl' => 0];
$teamStages         = $teamStages         ?? [];
$personalMilestones = $personalMilestones ?? [];

$monthNames = ['', 'Leden','Únor','Březen','Duben','Květen','Červen',
               'Červenec','Srpen','Září','Říjen','Listopad','Prosinec'];
$totalValid = $totalReceived - $totalFlagged;
?>

<style>
/* ── OZ Dashboard ── */
.oz-header { margin-bottom:1rem; }
.oz-header h1 { margin-bottom:0.1rem; }
.oz-subtitle { color:var(--muted); font-size:0.82rem; }

.oz-month-nav { display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap; margin-bottom:1.2rem; }
.oz-month-label { font-weight:700; font-size:0.95rem; }

/* Souhrn */
.oz-summary { display:flex; gap:0.75rem; flex-wrap:wrap; margin-bottom:1.2rem; }
.oz-scard {
    background:var(--card); border:1px solid rgba(0,0,0,0.08);
    border-radius:10px; padding:0.65rem 1rem; flex:1; min-width:110px;
}
.oz-scard__val { font-size:1.45rem; font-weight:700; line-height:1; }
.oz-scard__val--green { color:#2ecc71; }
.oz-scard__val--red   { color:#e74c3c; }
.oz-scard__val--blue  { color:#3498db; }
.oz-scard__lbl { font-size:0.68rem; text-transform:uppercase; letter-spacing:0.05em; color:var(--muted); margin-top:0.2rem; }

/* Region karta */
.oz-region-card {
    background:var(--card); border:1px solid rgba(0,0,0,0.07);
    border-radius:10px; overflow:hidden; margin-bottom:0.85rem;
}
.oz-region-card--no-target { opacity:0.75; }
.oz-region-top {
    display:flex; align-items:center; justify-content:space-between;
    padding:0.65rem 1rem; gap:0.5rem; flex-wrap:wrap; cursor:pointer;
    user-select:none;
}
.oz-region-top:hover { background:rgba(0,0,0,0.02); }
.oz-region-name { font-weight:700; font-size:0.9rem; }
.oz-region-right { display:flex; align-items:center; gap:0.7rem; flex-wrap:wrap; }
.oz-region-count { font-size:0.88rem; font-weight:600; }
.oz-region-count--over  { color:#3498db; }
.oz-region-count--done  { color:#2ecc71; }
.oz-region-count--part  { color:#f0a030; }
.oz-region-count--empty { color:var(--muted); }
.oz-region-toggle { font-size:0.72rem; color:var(--muted); }

.oz-bar-track { height:6px; background:rgba(0,0,0,0.08); margin:0 1rem 0.7rem; border-radius:3px; }
.oz-bar-fill { height:100%; border-radius:3px; transition:width 0.5s ease; }
.oz-bar-fill--ok    { background:linear-gradient(90deg,#2ecc71,#27ae60); }
.oz-bar-fill--warn  { background:linear-gradient(90deg,#f0a030,#e67e22); }
.oz-bar-fill--over  { background:linear-gradient(90deg,#3498db,#2980b9); }
.oz-bar-fill--empty { background:rgba(0,0,0,0.15); }

/* Kontakty v regionu */
.oz-contact-list { display:none; border-top:1px solid rgba(0,0,0,0.06); }
.oz-contact-list.open { display:block; }

.oz-contact-table {
    width:100%; border-collapse:collapse; font-size:0.78rem;
}
.oz-contact-table th,
.oz-contact-table td {
    padding:0.3rem 1rem; border-bottom:1px solid rgba(0,0,0,0.04);
    text-align:left; vertical-align:top;
}
.oz-contact-table th {
    font-size:0.68rem; text-transform:uppercase; letter-spacing:0.05em;
    color:var(--muted); background:rgba(0,0,0,0.02);
    font-weight:600; padding:0.4rem 1rem;
}
.oz-contact-table tr:last-child td { border-bottom:none; }
.oz-contact-table tr.flagged-row td { opacity:0.6; background:rgba(231,76,60,0.04); }
.oz-contact-date { color:var(--muted); font-size:0.7rem; }
.oz-contact-caller { font-size:0.7rem; color:var(--muted); }
.oz-flag-badge {
    display:inline-block; font-size:0.62rem; padding:0.1rem 0.35rem;
    border-radius:4px; background:rgba(231,76,60,0.18); color:#e74c3c;
}

/* Reklamace formulář */
.oz-flag-form { display:inline-flex; gap:0.3rem; align-items:center; flex-wrap:wrap; }
.oz-flag-input {
    font-size:0.72rem; padding:0.2rem 0.4rem;
    background:var(--bg); color:var(--text);
    border:1px solid rgba(0,0,0,0.15); border-radius:4px;
    width:180px;
}
.oz-flag-input:focus { outline:none; border-color:#e74c3c; }
.btn-flag {
    font-size:0.7rem; padding:0.15rem 0.45rem;
    background:rgba(231,76,60,0.15); color:#e74c3c;
    border:1px solid rgba(231,76,60,0.3); border-radius:4px;
    cursor:pointer; white-space:nowrap;
}
.btn-flag:hover { background:rgba(231,76,60,0.28); }
.btn-unflag {
    font-size:0.7rem; padding:0.15rem 0.45rem;
    background:rgba(0,0,0,0.06); color:var(--muted);
    border:1px solid rgba(0,0,0,0.12); border-radius:4px;
    cursor:pointer;
}
.btn-unflag:hover { background:rgba(0,0,0,0.12); }
.oz-flag-reason-text { font-size:0.7rem; color:#e74c3c; font-style:italic; }

.oz-no-contacts { color:var(--muted); padding:0.6rem 1rem; font-size:0.78rem; }

/* ── Refactor (Krok 3): clean toolbar + sbalitelný souhrn ── */
.oz-toolbar {
    display:flex; align-items:center; gap:0.7rem;
    flex-wrap:wrap; margin-bottom:1.2rem;
    padding-bottom:0.85rem; border-bottom:1px solid rgba(0,0,0,0.08);
}
.oz-toolbar__month {
    display:flex; align-items:center; gap:0.4rem;
    flex:0 0 auto;
}
.oz-toolbar__month .oz-month-label {
    font-weight:700; font-size:1rem; padding:0 0.4rem;
}
.oz-toolbar__spacer { flex:1; }
.oz-toolbar__actions {
    display:flex; align-items:center; gap:0.4rem;
    flex:0 0 auto; flex-wrap:wrap;
}

/* Sbalitelný stats panel */
.oz-stats-collapse {
    margin:0 0 1rem;
    border:1px solid rgba(0,0,0,0.08);
    border-radius:8px;
    overflow:hidden;
}
.oz-stats-collapse > summary {
    list-style:none; cursor:pointer; user-select:none;
    padding:0.55rem 0.85rem;
    font-size:0.85rem; color:var(--muted);
    display:grid; grid-template-columns: 1fr auto 1fr;
    align-items:center; gap:0.6rem;
    transition: background 0.15s, color 0.15s;
}
.oz-stats-collapse > summary > :nth-child(1) {
    text-align:center;
    grid-column:2;
    font-weight:600;
}
.oz-stats-collapse > summary > :nth-child(2) {
    grid-column:3;
    text-align:right;
}
.oz-stats-collapse > summary::-webkit-details-marker { display:none; }
.oz-stats-collapse > summary:hover { background:rgba(0,0,0,0.04); color:var(--text); }
.oz-stats-collapse > summary:before {
    content:"▸"; transition: transform 0.15s;
    grid-column:1; justify-self:start;
    color:var(--muted);
}
.oz-stats-collapse[open] > summary:before { content:"▾"; }
.oz-stats-collapse[open] > summary { border-bottom:1px solid rgba(0,0,0,0.06); color:var(--text); }
.oz-stats-collapse__inline {
    font-size:0.78rem; color:var(--muted); font-weight:500;
}
.oz-stats-collapse__inline strong { color:#2ecc71; font-weight:700; }
.oz-stats-collapse__inner { padding:0.85rem; }
</style>

<section class="card" style="max-width:1100px;margin:0 auto;">

    <div class="oz-header">
        <h1>Moje leady</h1>
        <p class="oz-subtitle">Obchodní zástupce: <strong><?= crm_h((string) ($user['jmeno'] ?? '')) ?></strong></p>
    </div>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info"><?= crm_h($flash) ?></p>
    <?php } ?>

    <!-- ── Toolbar: měsíční nav vlevo, akce vpravo (1 primary CTA) ── -->
    <div class="oz-toolbar">
        <?php
        $prevMonth = $month - 1; $prevYear = $year;
        if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
        $nextMonth = $month + 1; $nextYear = $year;
        if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
        ?>
        <div class="oz-toolbar__month">
            <a href="<?= crm_h(crm_url('/oz?year=' . $prevYear . '&month=' . $prevMonth)) ?>"
               class="btn btn-secondary btn-sm" title="Předchozí měsíc">←</a>
            <span class="oz-month-label"><?= crm_h($monthNames[$month] . ' ' . $year) ?></span>
            <a href="<?= crm_h(crm_url('/oz?year=' . $nextYear . '&month=' . $nextMonth)) ?>"
               class="btn btn-secondary btn-sm" title="Další měsíc">→</a>
            <?php if (!$isCurrentMonth) { ?>
                <a href="<?= crm_h(crm_url('/oz')) ?>" class="btn btn-secondary btn-sm">Aktuální</a>
            <?php } ?>
        </div>

        <div class="oz-toolbar__spacer"></div>

        <div class="oz-toolbar__actions">
            <!-- 1 primární CTA: nová UI -->
            <a href="<?= crm_h(crm_url('/oz/queue')) ?>"
               class="btn"
               style="background:#2ecc71;border:1px solid #2ecc71;color:#fff;font-weight:600;">
                📋 Příchozí leady
            </a>
            <!-- secondary: legacy plná pracovní plocha -->
            <a href="<?= crm_h(crm_url('/oz/leads')) ?>" class="btn btn-secondary btn-sm">
                💼 Plná pracovní plocha
            </a>
            <a href="<?= crm_h(crm_url('/dashboard')) ?>" class="btn btn-secondary btn-sm">← Dashboard</a>
        </div>
    </div>

    <!-- ── Souhrn měsíce — sbalený default. OZ to potřebuje 1× za den, ne pořád. ── -->
    <?php
    $pctTotal     = $totalTarget > 0 ? min(100, (int) round($totalReceived / $totalTarget * 100)) : null;
    $stillNeeded  = $totalTarget > 0 ? max(0, $totalTarget - $totalReceived) : 0;
    // Inline label v summary — i ve sbaleném stavu vidíš to nejdůležitější:
    $inlineSummary = '';
    if ($totalTarget > 0) {
        $inlineSummary = $totalReceived . ' / ' . $totalTarget;
        if ($pctTotal !== null) {
            $inlineSummary .= ' · ' . $pctTotal . ' %';
        }
    } else {
        $inlineSummary = $totalReceived . ' přijatých · bez kvóty';
    }
    ?>
    <details class="oz-stats-collapse">
        <summary>
            <span>📊 Souhrn měsíce</span>
            <span class="oz-stats-collapse__inline">
                <strong><?= crm_h($inlineSummary) ?></strong>
                <?php if ($totalFlagged > 0) { ?>
                    · <span style="color:#e74c3c;">⚠ <?= $totalFlagged ?> chybné</span>
                <?php } ?>
            </span>
        </summary>
        <div class="oz-stats-collapse__inner">
            <div class="oz-summary">
                <div class="oz-scard">
                    <div class="oz-scard__val oz-scard__val--green"><?= $totalReceived ?></div>
                    <div class="oz-scard__lbl">Přijatých leadů</div>
                </div>
                <div class="oz-scard">
                    <div class="oz-scard__val"><?= $totalTarget > 0 ? $totalTarget : '—' ?></div>
                    <div class="oz-scard__lbl">Kvóta měsíce</div>
                </div>
                <?php if ($totalTarget > 0) { ?>
                <div class="oz-scard">
                    <div class="oz-scard__val <?= $totalReceived >= $totalTarget ? 'oz-scard__val--green' : '' ?>"><?= $pctTotal ?> %</div>
                    <div class="oz-scard__lbl">Plnění</div>
                </div>
                <?php } ?>
                <?php if ($totalFlagged > 0) { ?>
                <div class="oz-scard">
                    <div class="oz-scard__val oz-scard__val--red"><?= $totalFlagged ?></div>
                    <div class="oz-scard__lbl">Chybné leady</div>
                </div>
                <?php } ?>
                <?php if ($totalTarget > 0) { ?>
                <div class="oz-scard" title="Kolik leadů ještě musí navolávačky dodat, aby byla splněna kvóta">
                    <div class="oz-scard__val <?= $stillNeeded === 0 ? 'oz-scard__val--green' : 'oz-scard__val--blue' ?>">
                        <?= $stillNeeded ?>
                    </div>
                    <div class="oz-scard__lbl">Čeká od navolávaček</div>
                </div>
                <?php } ?>
            </div>
        </div>
    </details>

    <!-- ── Výkon (osobní + týmový) — sbalený default. Stejný princip jako Souhrn. ── -->
    <?php
    // Inline summary v summary řádku — i ve sbaleném vidíš podstatu
    $perfInline = $monthWins . ' smluv';
    if ($monthBmsl > 0) {
        $perfInline .= ' · ' . number_format($monthBmsl, 0, ',', ' ') . ' Kč BMSL';
    }
    // Spočítej kolik milníků/stages je splněno (pro "X / Y dosaženo")
    $msDone = 0; $msTotal = count($personalMilestones);
    foreach ($personalMilestones as $pm) {
        if ($monthBmsl >= (int) $pm['target_bmsl']) $msDone++;
    }
    $teamBmslInt = (int) ($teamStats['bmsl'] ?? 0);
    $stDone = 0; $stTotal = count($teamStages);
    foreach ($teamStages as $ts) {
        if ($teamBmslInt >= (int) $ts['target_bmsl']) $stDone++;
    }
    ?>
    <details class="oz-stats-collapse">
        <summary>
            <span>🎯 Výkon &amp; milníky</span>
            <span class="oz-stats-collapse__inline">
                <strong><?= crm_h($perfInline) ?></strong>
                <?php if ($msTotal > 0) { ?>
                    · 🏅 <?= $msDone ?>/<?= $msTotal ?>
                <?php } ?>
                <?php if ($stTotal > 0) { ?>
                    · ⬆ stage <?= $stDone ?>/<?= $stTotal ?>
                <?php } ?>
            </span>
        </summary>
        <div class="oz-stats-collapse__inner">

            <!-- Osobní KPI -->
            <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;">
                <div style="flex:1;min-width:140px;background:rgba(46,204,113,0.05);
                            border:1px solid rgba(46,204,113,0.2);border-radius:8px;padding:0.65rem 1rem;">
                    <div style="font-size:1.45rem;font-weight:700;color:#2ecc71;line-height:1;">
                        <?= $monthWins ?>
                    </div>
                    <div style="font-size:0.68rem;text-transform:uppercase;letter-spacing:0.05em;
                                color:var(--muted);margin-top:0.25rem;">
                        smluv tento měsíc
                    </div>
                </div>
                <div style="flex:1;min-width:140px;background:rgba(46,204,113,0.05);
                            border:1px solid rgba(46,204,113,0.2);border-radius:8px;padding:0.65rem 1rem;">
                    <div style="font-size:1.45rem;font-weight:700;color:#2ecc71;line-height:1;">
                        <?= $monthBmsl > 0 ? number_format($monthBmsl, 0, ',', ' ') . ' Kč' : '—' ?>
                    </div>
                    <div style="font-size:0.68rem;text-transform:uppercase;letter-spacing:0.05em;
                                color:var(--muted);margin-top:0.25rem;">
                        BMSL bez DPH
                    </div>
                </div>
                <div style="flex:1;min-width:140px;background:rgba(52,152,219,0.05);
                            border:1px solid rgba(52,152,219,0.2);border-radius:8px;padding:0.65rem 1rem;">
                    <div style="font-size:1.45rem;font-weight:700;color:#3498db;line-height:1;">
                        <?= number_format((int)($teamStats['bmsl'] ?? 0), 0, ',', ' ') ?> Kč
                    </div>
                    <div style="font-size:0.68rem;text-transform:uppercase;letter-spacing:0.05em;
                                color:var(--muted);margin-top:0.25rem;">
                        BMSL týmu (<?= (int)($teamStats['contracts'] ?? 0) ?> smluv)
                    </div>
                </div>
            </div>

            <!-- Osobní milníky -->
            <?php if ($personalMilestones !== []) { ?>
            <div style="margin-bottom:1rem;">
                <div style="font-size:0.78rem;font-weight:700;color:var(--text);margin-bottom:0.4rem;">
                    👤 Osobní milníky
                </div>
                <?php foreach ($personalMilestones as $pm) {
                    $pmTarget = (int) $pm['target_bmsl'];
                    $pmDone   = $monthBmsl >= $pmTarget;
                    $pmPct    = $pmTarget > 0 ? min(100, (int) round($monthBmsl / $pmTarget * 100)) : 0;
                ?>
                <div style="margin-bottom:0.5rem;">
                    <div style="display:flex;justify-content:space-between;font-size:0.78rem;margin-bottom:0.2rem;">
                        <span style="<?= $pmDone ? 'color:#2ecc71;font-weight:600;' : 'color:var(--text);' ?>">
                            <?= $pmDone ? '🏅' : '🎯' ?> <?= crm_h((string) $pm['label']) ?>
                            (<?= number_format($pmTarget, 0, ',', ' ') ?> Kč)
                        </span>
                        <span style="font-weight:700;<?= $pmDone ? 'color:#2ecc71;' : 'color:var(--muted);' ?>">
                            <?= $pmPct ?> %
                        </span>
                    </div>
                    <div style="height:6px;background:rgba(0,0,0,0.08);border-radius:3px;overflow:hidden;">
                        <div style="height:100%;width:<?= $pmPct ?>%;
                                    background:<?= $pmDone ? '#2ecc71' : 'linear-gradient(90deg, #f1c40f, #f39c12)' ?>;
                                    transition:width 0.5s;"></div>
                    </div>
                    <?php if (!empty($pm['reward_note'])) { ?>
                    <div style="font-size:0.67rem;color:var(--muted);margin-top:0.15rem;">
                        🎁 <?= crm_h((string) $pm['reward_note']) ?>
                    </div>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>
            <?php } ?>

            <!-- Týmové stage cíle -->
            <?php if ($teamStages !== []) { ?>
            <div>
                <div style="font-size:0.78rem;font-weight:700;color:var(--text);margin-bottom:0.4rem;">
                    👥 Týmové stage cíle
                </div>
                <?php
                // Najít aktuální (nedosažený) stage
                $nextStage = null; $prevStage = null;
                foreach ($teamStages as $ts) {
                    if ($teamBmslInt < (int) $ts['target_bmsl']) { $nextStage = $ts; break; }
                    $prevStage = $ts;
                }
                $allDone = ($nextStage === null);
                ?>
                <?php foreach ($teamStages as $ts) {
                    $stTarget = (int) $ts['target_bmsl'];
                    $stDone1  = $teamBmslInt >= $stTarget;
                    $stPct    = $stTarget > 0 ? min(100, (int) round($teamBmslInt / $stTarget * 100)) : 0;
                    $isNext   = !$stDone1 && $nextStage && (int)$nextStage['stage_number'] === (int)$ts['stage_number'];
                ?>
                <div style="margin-bottom:0.4rem;<?= $isNext ? 'padding:0.4rem;border:1px solid rgba(52,152,219,0.3);border-radius:6px;background:rgba(52,152,219,0.06);' : '' ?>">
                    <div style="display:flex;justify-content:space-between;font-size:0.76rem;margin-bottom:0.15rem;">
                        <span style="<?= $stDone1 ? 'color:#2ecc71;font-weight:600;' : ($isNext ? 'color:#3498db;font-weight:600;' : 'color:var(--muted);') ?>">
                            <?= $stDone1 ? '✓' : ($isNext ? '⬆' : '○') ?>
                            Stage <?= (int) $ts['stage_number'] ?>: <?= crm_h((string) $ts['label']) ?>
                            (<?= number_format($stTarget, 0, ',', ' ') ?> Kč)
                        </span>
                        <span style="font-weight:700;<?= $stDone1 ? 'color:#2ecc71;' : ($isNext ? 'color:#3498db;' : 'color:var(--muted);') ?>">
                            <?= $stPct ?> %
                        </span>
                    </div>
                    <?php if ($isNext || $stDone1) { ?>
                    <div style="height:5px;background:rgba(0,0,0,0.08);border-radius:3px;overflow:hidden;">
                        <div style="height:100%;width:<?= $stPct ?>%;
                                    background:<?= $stDone1 ? '#2ecc71' : '#3498db' ?>;
                                    transition:width 0.5s;"></div>
                    </div>
                    <?php if ($isNext) { ?>
                    <div style="font-size:0.67rem;color:var(--muted);margin-top:0.15rem;">
                        chybí <?= number_format($stTarget - $teamBmslInt, 0, ',', ' ') ?> Kč
                    </div>
                    <?php } ?>
                    <?php } ?>
                </div>
                <?php } ?>
                <?php if ($allDone) { ?>
                <div style="font-size:0.75rem;color:#2ecc71;font-weight:600;margin-top:0.4rem;">
                    🏆 Všechny stages splněny — gratulace týmu!
                </div>
                <?php } ?>
            </div>
            <?php } ?>

            <?php if ($personalMilestones === [] && $teamStages === []) { ?>
            <div style="font-size:0.82rem;color:var(--muted);font-style:italic;padding:0.5rem 0;">
                Bez nastavených milníků pro tento měsíc.
                <?php if (in_array((string)($user['role'] ?? ''), ['majitel','superadmin'], true)) { ?>
                    <a href="<?= crm_h(crm_url('/admin/oz-stages')) ?>" style="color:#3498db;">Nastavit stage cíle týmu →</a>
                    ·
                    <a href="<?= crm_h(crm_url('/admin/oz-milestones')) ?>" style="color:#3498db;">Nastavit osobní milníky →</a>
                <?php } else { ?>
                    Majitel/superadmin je může nastavit.
                <?php } ?>
            </div>
            <?php } ?>

        </div>
    </details>

    <!-- Regiony -->
    <?php if ($allRegions === []) { ?>
        <p style="color:var(--muted);padding:0.5rem 0;">
            Žádné přijaté leady ani přiřazené regiony pro <?= crm_h($monthNames[$month] . ' ' . $year) ?>.
        </p>
    <?php } else { ?>

        <?php foreach ($allRegions as $reg) {
            $rec        = $received[$reg] ?? 0;
            $tgt        = $targets[$reg] ?? 0;
            $pct        = $tgt > 0 ? min(100, (int) round($rec / $tgt * 100)) : 0;
            $hasTarget  = $tgt > 0;
            $done       = $hasTarget && $rec >= $tgt;
            $over       = $hasTarget && $rec > $tgt;
            $regContacts = $contactsByRegion[$reg] ?? [];
            $flaggedCnt  = count(array_filter($regContacts, fn($c) => (int)($c['flagged'] ?? 0) === 1));

            $barClass = match(true) {
                !$hasTarget  => 'oz-bar-fill--empty',
                $over        => 'oz-bar-fill--over',
                $pct >= 60   => 'oz-bar-fill--warn',
                default      => 'oz-bar-fill--ok',
            };
            $countClass = match(true) {
                !$hasTarget  => 'oz-region-count--empty',
                $over        => 'oz-region-count--over',
                $done        => 'oz-region-count--done',
                default      => 'oz-region-count--part',
            };
            $cardId = 'oz-reg-' . preg_replace('/[^a-z0-9]/i', '-', $reg);
        ?>
        <div class="oz-region-card <?= !$hasTarget ? 'oz-region-card--no-target' : '' ?>">
            <div class="oz-region-top" onclick="ozToggle('<?= $cardId ?>')">
                <span class="oz-region-name"><?= crm_h(crm_region_label($reg)) ?></span>
                <div class="oz-region-right">
                    <?php if ($flaggedCnt > 0) { ?>
                        <span style="font-size:0.72rem;color:#e74c3c;">⚠ <?= $flaggedCnt ?> rekl.</span>
                    <?php } ?>
                    <span class="oz-region-count <?= $countClass ?>">
                        <?php if ($hasTarget) { ?>
                            <?= $rec ?> / <?= $tgt ?>
                            <?= $over ? ' ✅' : ($done ? ' ✅' : '') ?>
                        <?php } else { ?>
                            <?= $rec ?> leadů <span class="oz-region-toggle">(bez kvóty)</span>
                        <?php } ?>
                    </span>
                    <span class="oz-region-toggle" id="<?= $cardId ?>-arrow">▸ zobrazit</span>
                </div>
            </div>

            <?php if ($hasTarget) { ?>
            <div class="oz-bar-track">
                <div class="oz-bar-fill <?= $barClass ?>" style="width:<?= $pct ?>%"></div>
            </div>
            <?php } ?>

            <!-- Kontakty tohoto regionu -->
            <div class="oz-contact-list" id="<?= $cardId ?>">
                <?php if ($regContacts === []) { ?>
                    <p class="oz-no-contacts">Žádné navolané kontakty v tomto regionu tento měsíc.</p>
                <?php } else { ?>
                <table class="oz-contact-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Firma / zákazník</th>
                            <th>Telefon</th>
                            <th>Navolal/a</th>
                            <th>Datum</th>
                            <th>Chybný lead</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($regContacts as $i => $c) {
                            $isFlagged = (int) ($c['flagged'] ?? 0) === 1;
                            $cId = (int) $c['id'];
                        ?>
                        <tr class="<?= $isFlagged ? 'flagged-row' : '' ?>">
                            <td style="color:var(--muted);width:2rem;"><?= $i + 1 ?></td>
                            <td>
                                <strong><?= crm_h((string) ($c['firma'] ?? '—')) ?></strong>
                                <?php if (!empty($c['poznamka'])) { ?>
                                    <div style="font-size:0.67rem;color:var(--muted);"><?= crm_h((string) $c['poznamka']) ?></div>
                                <?php } ?>
                            </td>
                            <td>
                                <?= !empty($c['telefon']) ? crm_h((string) $c['telefon']) : '—' ?>
                            </td>
                            <td class="oz-contact-caller"><?= crm_h((string) $c['caller_name']) ?></td>
                            <td class="oz-contact-date">
                                <?= !empty($c['datum_volani'])
                                    ? crm_h(date('d.m.Y', strtotime((string) $c['datum_volani'])))
                                    : '—' ?>
                            </td>
                            <td>
                                <?php if ($isFlagged) { ?>
                                    <span class="oz-flag-badge">⚠ Chybný lead</span>
                                    <?php if (!empty($c['flag_reason'])) { ?>
                                        <div class="oz-flag-reason-text"><?= crm_h((string) $c['flag_reason']) ?></div>
                                    <?php } ?>
                                    <!-- Stáhnout chybný lead -->
                                    <form method="post" action="<?= crm_h(crm_url('/oz/flag')) ?>"
                                          class="oz-flag-form" style="margin-top:0.25rem;">
                                        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                                        <input type="hidden" name="contact_id" value="<?= $cId ?>">
                                        <input type="hidden" name="action"     value="unflag">
                                        <input type="hidden" name="year"       value="<?= $year ?>">
                                        <input type="hidden" name="month"      value="<?= $month ?>">
                                        <button type="submit" class="btn-unflag"
                                                onclick="return confirm('Stáhnout označení chybného leadu?')">
                                            ✕ Stáhnout
                                        </button>
                                    </form>
                                <?php } else { ?>
                                    <span style="color:var(--muted);font-size:0.72rem;">—</span>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <?php } ?>
            </div>
        </div>
        <?php } ?>

    <?php } ?>

</section>

<script>
function ozToggle(id) {
    const list = document.getElementById(id);
    const arrow = document.getElementById(id + '-arrow');
    if (!list) return;
    const open = list.classList.toggle('open');
    if (arrow) arrow.textContent = open ? '▾ skrýt' : '▸ zobrazit';
}

function validateFlag(btn) {
    const form = btn.closest('form');
    const input = form.querySelector('input[name="reason"]');
    if (!input || input.value.trim() === '') {
        input.focus();
        input.style.borderColor = '#e74c3c';
        setTimeout(() => input.style.borderColor = '', 1500);
        return false;
    }
    return confirm('Podat reklamaci na tento kontakt?');
}
</script>
