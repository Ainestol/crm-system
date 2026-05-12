<?php
// e:\Snecinatripu\app\views\cisticka\payout_print.php
// Standalone tisková stránka — výplata čističky za měsíc.
declare(strict_types=1);
/** @var array<string,mixed>             $cisticka     id, jmeno */
/** @var list<array<string,mixed>>       $events       jednotlivá ověření v měsíci */
/** @var array<string, array{name:string,count:int,events:list<array<string,mixed>>}> $byOperator */
/** @var float                           $rewardPerVerify */
/** @var int                             $year */
/** @var int                             $month */
/** @var bool                            $maskSensitive true = telefon/firma maskovány (cisticka role) */

$monthNames = ['', 'Leden','Únor','Březen','Duben','Květen','Červen',
               'Červenec','Srpen','Září','Říjen','Listopad','Prosinec'];

$totalCount    = count($events);
$totalEarnings = round($totalCount * $rewardPerVerify, 2);

$docTitle = 'Výplata čističky — ' . $cisticka['jmeno'] . ' — ' . $monthNames[$month] . ' ' . $year;
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($docTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 11pt; color: #1a1a1a; background: #fff; padding: 0;
        }
        .print-toolbar {
            display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;
            padding: 0.75rem 1.2rem;
            background: #f4f4f4; border-bottom: 1px solid #ddd;
        }
        .btn-print {
            background: #2563eb; color: #fff; border: none; border-radius: 5px;
            padding: 0.45rem 1.1rem; font-size: 0.88rem; cursor: pointer; font-weight: 600;
        }
        .btn-print:hover { background: #1d4ed8; }
        .btn-close {
            background: #e5e7eb; color: #374151; border: none; border-radius: 5px;
            padding: 0.45rem 0.85rem; font-size: 0.88rem; cursor: pointer;
        }
        .btn-close:hover { background: #d1d5db; }
        .toolbar-info { font-size: 0.78rem; color: #6b7280; margin-left: auto; }

        .doc { max-width: 860px; margin: 0 auto; padding: 1.5cm 1.2cm 2cm; }

        .doc-header { margin-bottom: 1.2rem; padding-bottom: 0.8rem; border-bottom: 2px solid #1a1a1a; }
        .doc-title { font-size: 16pt; font-weight: 700; margin-bottom: 0.15rem; }
        .doc-subtitle { font-size: 10pt; color: #555; }
        .doc-meta { font-size: 9pt; color: #888; margin-top: 0.25rem; }

        .summary-row { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.2rem; }
        .summary-box {
            border: 1px solid #d1d5db; border-radius: 6px;
            padding: 0.5rem 0.9rem; flex: 1; min-width: 100px;
        }
        .summary-box__val { font-size: 15pt; font-weight: 700; }
        .summary-box__val--green { color: #16a34a; }
        .summary-box__val--blue  { color: #2563eb; }
        .summary-box__lbl { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-top: 0.1rem; }

        .op-block { margin-bottom: 1.2rem; page-break-inside: avoid; }
        .op-header {
            display: flex; align-items: center; justify-content: space-between;
            background: #f3f4f6; border: 1px solid #d1d5db;
            border-radius: 6px 6px 0 0; padding: 0.45rem 0.8rem;
        }
        .op-name { font-weight: 700; font-size: 11pt; }
        .op-meta { font-size: 9pt; color: #555; display: flex; gap: 1rem; }
        .op-payout { font-weight: 700; color: #16a34a; }

        table { width: 100%; border-collapse: collapse; font-size: 9pt;
                border: 1px solid #d1d5db; border-top: none; border-radius: 0 0 6px 6px; }
        table th {
            background: #f9fafb; font-weight: 600; font-size: 8pt;
            color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em;
            padding: 0.25rem 0.6rem; border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }
        table td { padding: 0.25rem 0.6rem; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        table tr:last-child td { border-bottom: none; }
        .td-num { color: #9ca3af; width: 1.8rem; }
        .td-firm { font-weight: 600; }
        .td-phone { font-family: monospace; }
        .td-date { white-space: nowrap; color: #6b7280; }
        .td-status {
            font-size: 8pt; padding: 0.05rem 0.4rem; border-radius: 3px;
            display: inline-block;
        }
        .td-status--ready  { background: #d1fae5; color: #065f46; }
        .td-status--vf     { background: #fee2e2; color: #991b1b; }
        .td-status--chybny { background: #fef3c7; color: #92400e; }

        /* Sbalitelné detaily per operátor — default zavřené, klik rozbalí */
        details.op-block { margin-bottom: 0.6rem; page-break-inside: avoid; }
        details.op-block > summary {
            list-style: none;
            cursor: pointer;
            display: flex; align-items: center; justify-content: space-between;
            background: #f3f4f6; border: 1px solid #d1d5db;
            border-radius: 6px; padding: 0.45rem 0.8rem;
            transition: background 0.15s;
        }
        details.op-block > summary::-webkit-details-marker { display: none; }
        details.op-block > summary:hover { background: #e5e7eb; }
        details.op-block[open] > summary { border-radius: 6px 6px 0 0; }
        .op-toggle {
            font-size: 9pt; color: #6b7280; margin-right: 0.5rem;
            transition: transform 0.15s;
        }
        details.op-block[open] .op-toggle { transform: rotate(90deg); }
        details.op-block > table { border-top: none; }

        .section-title {
            font-size: 10pt; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.06em; color: #374151;
            margin: 1.4rem 0 0.5rem; padding-bottom: 0.3rem;
            border-bottom: 1px solid #d1d5db;
        }
        .pay-table { border-radius: 6px; }
        .pay-table th { background: #f3f4f6; }
        .pay-table td { text-align: center; }
        .pay-table td:first-child { text-align: left; }
        .pay-table tfoot td {
            font-weight: 700; background: #f9fafb;
            border-top: 2px solid #d1d5db;
        }
        .col-green { color: #16a34a; font-weight: 600; }
        .col-blue  { color: #2563eb; font-weight: 700; }

        .doc-footer {
            margin-top: 2rem; padding-top: 0.6rem;
            border-top: 1px solid #d1d5db;
            font-size: 8pt; color: #9ca3af;
            display: flex; justify-content: space-between;
        }
        .empty-msg {
            text-align: center; padding: 2.5rem 1rem;
            color: #9ca3af; font-style: italic;
            border: 1px dashed #d1d5db; border-radius: 6px;
        }

        @media print {
            .print-toolbar { display: none !important; }
            body { padding: 0; }
            .doc { padding: 0.8cm 0.8cm 1.5cm; max-width: 100%; }
            .op-block { page-break-inside: avoid; }
        }
        @page { margin: 1cm 1.2cm; }
    </style>
</head>
<body>

<div class="print-toolbar">
    <button class="btn-print" onclick="window.print()">🖨 Tisk / Uložit jako PDF</button>
    <button class="btn-close" onclick="window.close()">✕ Zavřít</button>
    <span class="toolbar-info">
        Tip: V dialogu tisku zvolte <strong>Uložit jako PDF</strong> a vypněte záhlaví/zápatí prohlížeče.
    </span>
</div>

<div class="doc">

    <!-- Záhlaví -->
    <div class="doc-header">
        <div class="doc-title"><?= htmlspecialchars($docTitle, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="doc-subtitle">Čistička: <strong><?= htmlspecialchars((string)$cisticka['jmeno'], ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div class="doc-meta">Vygenerováno: <?= date('d.m.Y H:i') ?></div>
    </div>

    <!-- Souhrn -->
    <div class="summary-row">
        <div class="summary-box">
            <div class="summary-box__val summary-box__val--green"><?= $totalCount ?></div>
            <div class="summary-box__lbl">Ověření celkem</div>
        </div>
        <div class="summary-box">
            <div class="summary-box__val">
                <?= number_format($rewardPerVerify, 2, ',', ' ') ?> Kč
            </div>
            <div class="summary-box__lbl">Sazba / ověření</div>
        </div>
        <div class="summary-box">
            <div class="summary-box__val summary-box__val--blue">
                <?= number_format($totalEarnings, 2, ',', ' ') ?> Kč
            </div>
            <div class="summary-box__lbl">K vyplacení celkem</div>
        </div>
    </div>

    <?php if ($totalCount === 0) { ?>
        <div class="empty-msg">
            V <?= crm_h($monthNames[$month] . ' ' . $year) ?> nebyly žádná ověření.
        </div>
    <?php } ?>

    <!-- Per operátor — sbalitelné (default zavřené); klikem rozbalíš detail kontaktů -->
    <?php foreach ($byOperator as $op) {
        $oc = (int) $op['count'];
        $op_payout = round($oc * $rewardPerVerify, 2);
    ?>
    <details class="op-block">
        <summary>
            <span class="op-name">
                <span class="op-toggle">▸</span>
                📞 <?= htmlspecialchars((string)$op['name'], ENT_QUOTES, 'UTF-8') ?>
            </span>
            <div class="op-meta">
                <span>Ověření: <strong><?= $oc ?></strong></span>
                <span class="op-payout">💰 <?= number_format($op_payout, 2, ',', ' ') ?> Kč</span>
            </div>
        </summary>
        <table>
            <thead>
                <tr>
                    <th style="width:1.8rem;">#</th>
                    <th>Firma</th>
                    <th>Telefon</th>
                    <th>Kraj</th>
                    <th>Datum ověření</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($op['events'] as $i => $ev) {
                    $status = (string) $ev['new_status'];
                    // 3-way status label podle skutečného stavu ve workflow_log
                    if ($status === 'VF_SKIP') {
                        $statusClass = 'td-status--vf';
                        $statusLabel = 'VF skip';
                    } elseif ($status === 'CHYBNY_KONTAKT') {
                        $statusClass = 'td-status--chybny';
                        $statusLabel = 'Chybný kontakt';
                    } else {
                        $statusClass = 'td-status--ready';
                        $statusLabel = 'OVĚŘENO';
                    }
                ?>
                <?php
                    $firmaRaw = (string) ($ev['firma'] ?? '');
                    $phoneRaw = (string) ($ev['telefon'] ?? '');
                    $firmaOut = $maskSensitive ? crm_mask_firma($firmaRaw) : ($firmaRaw !== '' ? $firmaRaw : '—');
                    $phoneOut = $maskSensitive ? crm_mask_phone($phoneRaw) : ($phoneRaw !== '' ? $phoneRaw : '—');
                ?>
                <tr>
                    <td class="td-num"><?= $i + 1 ?></td>
                    <td class="td-firm"><?= htmlspecialchars($firmaOut, ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="td-phone"><?= htmlspecialchars($phoneOut, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(crm_region_label((string)($ev['region'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="td-date">
                        <?= !empty($ev['verified_at'])
                            ? htmlspecialchars(date('d.m.Y H:i', strtotime((string)$ev['verified_at'])), ENT_QUOTES, 'UTF-8')
                            : '—' ?>
                    </td>
                    <td>
                        <span class="td-status <?= $statusClass ?>"><?= $statusLabel ?></span>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </details>
    <?php } ?>

    <!-- Platební tabulka -->
    <?php if ($totalCount > 0) { ?>
    <p class="section-title">Platební přehled</p>
    <table class="pay-table">
        <thead>
            <tr>
                <th>Operátor</th>
                <th>Počet ověření</th>
                <th>Sazba / ověření</th>
                <th>K vyplacení</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($byOperator as $op) {
                $oc = (int) $op['count'];
                $op_payout = round($oc * $rewardPerVerify, 2);
            ?>
            <tr>
                <td><?= htmlspecialchars((string)$op['name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= $oc ?></td>
                <td><?= number_format($rewardPerVerify, 2, ',', ' ') ?> Kč</td>
                <td class="col-blue"><?= number_format($op_payout, 2, ',', ' ') ?> Kč</td>
            </tr>
            <?php } ?>
        </tbody>
        <tfoot>
            <tr>
                <td>CELKEM</td>
                <td class="col-green"><?= $totalCount ?></td>
                <td>—</td>
                <td class="col-blue"><?= number_format($totalEarnings, 2, ',', ' ') ?> Kč</td>
            </tr>
        </tfoot>
    </table>
    <?php } ?>

    <!-- Zápatí -->
    <div class="doc-footer">
        <span>Clockwork Man CRM · 🐌 Šneci na tripu</span>
        <span>Strana <span class="page-num"></span></span>
    </div>
</div>
</body>
</html>
