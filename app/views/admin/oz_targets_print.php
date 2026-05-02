<?php
// e:\Snecinatripu\app\views\admin\oz_targets_print.php
// Standalone tisková stránka – bez base layoutu
declare(strict_types=1);
/** @var array<string, mixed>                $oz */
/** @var array<string, int>                  $targets */
/** @var list<array<string, mixed>>          $contacts */
/** @var array<int, array<string, mixed>>    $byCaller */
/** @var float                               $rewardPerWin */
/** @var int                                 $year */
/** @var int                                 $month */
/** @var int                                 $callerId     0 = všichni */

$monthNames = ['', 'Leden','Únor','Březen','Duben','Květen','Červen',
               'Červenec','Srpen','Září','Říjen','Listopad','Prosinec'];

$totalContacts = count($contacts);
$totalFlagged  = count(array_filter($contacts, fn($c) => (int)($c['flagged'] ?? 0) === 1));
$totalValid    = $totalContacts - $totalFlagged;
$totalTarget   = array_sum($targets);
$totalPayout   = $totalValid * $rewardPerWin;

// Název dokumentu pro záhlaví
$isSingleCaller = $callerId > 0 && count($byCaller) === 1;
$callerName = $isSingleCaller ? array_values($byCaller)[0]['name'] : null;
$docTitle = $isSingleCaller
    ? 'Výplata — ' . $callerName . ' — ' . $monthNames[$month] . ' ' . $year
    : 'Přehled leadů — ' . $oz['jmeno'] . ' — ' . $monthNames[$month] . ' ' . $year;
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($docTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        /* ── Základní reset a font ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 11pt;
            color: #1a1a1a;
            background: #fff;
            padding: 0;
        }

        /* ── Tlačítka (skryta při tisku) ── */
        .print-toolbar {
            display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;
            padding: 0.75rem 1.2rem;
            background: #f4f4f4; border-bottom: 1px solid #ddd;
        }
        .btn-print {
            background: #2563eb; color: #fff;
            border: none; border-radius: 5px;
            padding: 0.45rem 1.1rem; font-size: 0.88rem;
            cursor: pointer; font-weight: 600;
        }
        .btn-print:hover { background: #1d4ed8; }
        .btn-close {
            background: #e5e7eb; color: #374151;
            border: none; border-radius: 5px;
            padding: 0.45rem 0.85rem; font-size: 0.88rem;
            cursor: pointer;
        }
        .btn-close:hover { background: #d1d5db; }
        .toolbar-info { font-size: 0.78rem; color: #6b7280; margin-left: auto; }

        /* ── Dokument ── */
        .doc {
            max-width: 860px; margin: 0 auto;
            padding: 1.5cm 1.2cm 2cm;
        }

        /* ── Záhlaví ── */
        .doc-header { margin-bottom: 1.2rem; padding-bottom: 0.8rem; border-bottom: 2px solid #1a1a1a; }
        .doc-title { font-size: 16pt; font-weight: 700; margin-bottom: 0.15rem; }
        .doc-subtitle { font-size: 10pt; color: #555; }
        .doc-meta { font-size: 9pt; color: #888; margin-top: 0.25rem; }

        /* ── Souhrnná tabulka ── */
        .summary-row {
            display: flex; gap: 1rem; flex-wrap: wrap;
            margin-bottom: 1.2rem;
        }
        .summary-box {
            border: 1px solid #d1d5db; border-radius: 6px;
            padding: 0.5rem 0.9rem; flex: 1; min-width: 100px;
        }
        .summary-box__val { font-size: 15pt; font-weight: 700; }
        .summary-box__val--green { color: #16a34a; }
        .summary-box__val--red   { color: #dc2626; }
        .summary-box__val--blue  { color: #2563eb; }
        .summary-box__lbl { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-top: 0.1rem; }

        /* ── Sekce navolávačky ── */
        .caller-block { margin-bottom: 1.4rem; page-break-inside: avoid; }
        .caller-header {
            display: flex; align-items: center; justify-content: space-between;
            background: #f3f4f6; border: 1px solid #d1d5db;
            border-radius: 6px 6px 0 0; padding: 0.45rem 0.8rem;
        }
        .caller-name { font-weight: 700; font-size: 11pt; }
        .caller-meta { font-size: 9pt; color: #555; display: flex; gap: 1rem; }
        .caller-payout { font-weight: 700; color: #16a34a; }

        /* ── Region ── */
        .region-block { border: 1px solid #e5e7eb; border-top: none; }
        .region-block:last-child { border-radius: 0 0 6px 6px; }
        .region-header {
            display: flex; justify-content: space-between;
            background: #fafafa; padding: 0.25rem 0.8rem;
            font-size: 8pt; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.05em; color: #6b7280;
            border-bottom: 1px solid #e5e7eb;
        }
        .region-cnt { color: #16a34a; }

        /* ── Kontakty tabulka ── */
        table { width: 100%; border-collapse: collapse; font-size: 9pt; }
        table th {
            background: #f9fafb; font-weight: 600; font-size: 8pt;
            color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em;
            padding: 0.25rem 0.6rem; border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }
        table td {
            padding: 0.25rem 0.6rem;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
        }
        table tr:last-child td { border-bottom: none; }
        table tr.row-flagged td { background: #fef2f2; color: #9ca3af; }
        .td-num { color: #9ca3af; width: 1.8rem; }
        .td-firm { font-weight: 600; }
        .td-note { font-size: 8pt; color: #9ca3af; font-style: italic; }
        .td-phone { font-family: monospace; }
        .td-date { white-space: nowrap; color: #6b7280; }
        .badge-flag {
            display: inline-block; font-size: 7.5pt; padding: 0.1rem 0.35rem;
            background: #fee2e2; color: #dc2626; border-radius: 3px;
        }
        .badge-ok { color: #16a34a; font-size: 8pt; }
        .flag-reason { font-size: 8pt; color: #dc2626; font-style: italic; }

        /* ── Platební tabulka ── */
        .section-title {
            font-size: 10pt; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.06em; color: #374151;
            margin: 1.4rem 0 0.5rem; padding-bottom: 0.3rem;
            border-bottom: 1px solid #d1d5db;
        }
        .pay-table th { background: #f3f4f6; }
        .pay-table td { text-align: center; }
        .pay-table td:first-child { text-align: left; }
        .pay-table tfoot td {
            font-weight: 700; background: #f9fafb;
            border-top: 2px solid #d1d5db;
        }
        .col-green { color: #16a34a; font-weight: 600; }
        .col-red   { color: #dc2626; }
        .col-blue  { color: #2563eb; font-weight: 700; }

        /* ── Zápatí ── */
        .doc-footer {
            margin-top: 2rem; padding-top: 0.6rem;
            border-top: 1px solid #d1d5db;
            font-size: 8pt; color: #9ca3af;
            display: flex; justify-content: space-between;
        }

        /* ── Tisk ── */
        @media print {
            .print-toolbar { display: none !important; }
            body { padding: 0; }
            .doc { padding: 0.8cm 0.8cm 1.5cm; max-width: 100%; }
            .caller-block { page-break-inside: avoid; }
        }
        @page { margin: 1cm 1.2cm; }
    </style>
</head>
<body>

<!-- Toolbar (pouze na obrazovce) -->
<div class="print-toolbar">
    <button class="btn-print" onclick="window.print()">🖨 Tisk / Uložit jako PDF</button>
    <button class="btn-close" onclick="window.close()">✕ Zavřít</button>
    <span class="toolbar-info">
        Tip: V dialogu tisku zvolte <strong>Uložit jako PDF</strong> a vypněte záhlaví/zápatí prohlížeče.
    </span>
</div>

<!-- Obsah dokumentu -->
<div class="doc">

    <!-- Záhlaví -->
    <div class="doc-header">
        <div class="doc-title"><?= htmlspecialchars($docTitle, ENT_QUOTES, 'UTF-8') ?></div>
        <?php if (!$isSingleCaller) { ?>
            <div class="doc-subtitle">Obchodní zástupce: <?= htmlspecialchars((string)$oz['jmeno'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php } ?>
        <div class="doc-meta">Vygenerováno: <?= date('d.m.Y H:i') ?></div>
    </div>

    <!-- Souhrn -->
    <div class="summary-row">
        <div class="summary-box">
            <div class="summary-box__val summary-box__val--green"><?= $totalContacts ?></div>
            <div class="summary-box__lbl">Celkem leadů</div>
        </div>
        <?php if ($totalTarget > 0) { ?>
        <div class="summary-box">
            <div class="summary-box__val"><?= $totalTarget ?></div>
            <div class="summary-box__lbl">Kvóta měsíce</div>
        </div>
        <?php } ?>
        <?php if ($totalFlagged > 0) { ?>
        <div class="summary-box">
            <div class="summary-box__val summary-box__val--red"><?= $totalFlagged ?></div>
            <div class="summary-box__lbl">Reklamace</div>
        </div>
        <div class="summary-box">
            <div class="summary-box__val"><?= $totalValid ?></div>
            <div class="summary-box__lbl">Platných leadů</div>
        </div>
        <?php } ?>
        <?php if ($rewardPerWin > 0) { ?>
        <div class="summary-box">
            <div class="summary-box__val summary-box__val--blue">
                <?= number_format($totalPayout, 0, ',', ' ') ?> Kč
            </div>
            <div class="summary-box__lbl">K vyplacení celkem</div>
        </div>
        <?php } ?>
    </div>

    <!-- Kontakty per navolávačka -->
    <?php foreach ($byCaller as $cid => $callerData) {
        $ct = (int) $callerData['total'];
        $cf = (int) $callerData['flagged'];
        $cv = $ct - $cf;
        $cp = $cv * $rewardPerWin;
        $byRegion = $callerData['byRegion'];
        ksort($byRegion);
    ?>
    <div class="caller-block">
        <div class="caller-header">
            <span class="caller-name"><?= htmlspecialchars((string)$callerData['name'], ENT_QUOTES, 'UTF-8') ?></span>
            <div class="caller-meta">
                <span>Leadů: <strong><?= $ct ?></strong></span>
                <?php if ($cf > 0) { ?>
                    <span>Reklamace: <strong style="color:#dc2626;"><?= $cf ?></strong></span>
                    <span>Platných: <strong><?= $cv ?></strong></span>
                <?php } ?>
                <?php if ($rewardPerWin > 0) { ?>
                    <span class="caller-payout">💰 <?= number_format($cp, 0, ',', ' ') ?> Kč</span>
                <?php } ?>
            </div>
        </div>

        <?php foreach ($byRegion as $region => $regionContacts) {
            $rTotal   = count($regionContacts);
            $rFlagged = count(array_filter($regionContacts, fn($c) => (int)($c['flagged'] ?? 0) === 1));
        ?>
        <div class="region-block">
            <div class="region-header">
                <span><?= htmlspecialchars(crm_region_label($region), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="region-cnt">
                    <?= $rTotal ?> leadů
                    <?= $rFlagged > 0 ? ' · ' . $rFlagged . ' reklamace' : '' ?>
                </span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width:1.8rem;">#</th>
                        <th>Firma / zákazník</th>
                        <th>Telefon</th>
                        <th>Datum navolání</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($regionContacts as $i => $c) {
                        $isFlagged = (int)($c['flagged'] ?? 0) === 1;
                    ?>
                    <tr class="<?= $isFlagged ? 'row-flagged' : '' ?>">
                        <td class="td-num"><?= $i + 1 ?></td>
                        <td>
                            <span class="td-firm"><?= htmlspecialchars((string)($c['firma'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if (!empty($c['poznamka'])) { ?>
                                <div class="td-note"><?= htmlspecialchars((string)$c['poznamka'], ENT_QUOTES, 'UTF-8') ?></div>
                            <?php } ?>
                        </td>
                        <td class="td-phone"><?= htmlspecialchars((string)($c['telefon'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="td-date">
                            <?= !empty($c['datum_volani'])
                                ? htmlspecialchars(date('d.m.Y', strtotime((string)$c['datum_volani'])), ENT_QUOTES, 'UTF-8')
                                : '—' ?>
                        </td>
                        <td>
                            <?php if ($isFlagged) { ?>
                                <span class="badge-flag">⚠ Reklamace</span>
                                <?php if (!empty($c['flag_reason'])) { ?>
                                    <div class="flag-reason"><?= htmlspecialchars((string)$c['flag_reason'], ENT_QUOTES, 'UTF-8') ?></div>
                                <?php } ?>
                            <?php } else { ?>
                                <span class="badge-ok">✓ OK</span>
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

    <!-- Platební tabulka -->
    <?php if (count($byCaller) > 0 && $rewardPerWin > 0) { ?>
    <p class="section-title">Platební přehled</p>
    <table class="pay-table">
        <thead>
            <tr>
                <th>Navolávačka</th>
                <th>Leadů celkem</th>
                <th>Reklamace</th>
                <th>Platných</th>
                <th>Sazba / lead</th>
                <th>K vyplacení</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($byCaller as $cid => $callerData) {
                $ct = (int) $callerData['total'];
                $cf = (int) $callerData['flagged'];
                $cv = $ct - $cf;
                $cp = $cv * $rewardPerWin;
            ?>
            <tr>
                <td><?= htmlspecialchars((string)$callerData['name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= $ct ?></td>
                <td class="col-red"><?= $cf > 0 ? $cf : '—' ?></td>
                <td class="col-green"><?= $cv ?></td>
                <td><?= number_format($rewardPerWin, 0, ',', ' ') ?> Kč</td>
                <td class="col-blue"><?= number_format($cp, 0, ',', ' ') ?> Kč</td>
            </tr>
            <?php } ?>
        </tbody>
        <tfoot>
            <tr>
                <td>CELKEM</td>
                <td><?= $totalContacts ?></td>
                <td class="col-red"><?= $totalFlagged > 0 ? $totalFlagged : '—' ?></td>
                <td class="col-green"><?= $totalValid ?></td>
                <td>—</td>
                <td class="col-blue"><?= number_format($totalPayout, 0, ',', ' ') ?> Kč</td>
            </tr>
        </tfoot>
    </table>
    <?php } ?>

    <!-- Zápatí -->
    <div class="doc-footer">
        <span><?= htmlspecialchars($docTitle, ENT_QUOTES, 'UTF-8') ?></span>
        <span>Vytištěno: <?= date('d.m.Y H:i') ?></span>
    </div>

</div>
</body>
</html>
