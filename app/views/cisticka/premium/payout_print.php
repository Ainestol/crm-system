<?php
// e:\Snecinatripu\app\views\cisticka\premium\payout_print.php
// Standalone tisková stránka — premium výplata čističky.
// Per-objednávka view (jako OZ má) — z pohledu čističky:
//   "Co jsem komu vyčistila, kolik dostanu, kdo už mi zaplatil"
declare(strict_types=1);
/** @var array<string,mixed>           $cisticka     id, jmeno */
/** @var int                           $year */
/** @var int                           $month */
/** @var bool                          $singleOrder */
/** @var int                           $orderId */
/** @var ?array<string,mixed>          $singleOrderHeader */
/** @var array<int, array<string,mixed>> $byOrder    grouped per objednávka */
/** @var int                           $totalCount  všechna ověření vč. reklamací */
/** @var float                         $totalPayable */
/** @var int                           $ozCount     pro kolik OZ jsem dělala */

$monthNames = ['', 'Leden','Únor','Březen','Duben','Květen','Červen',
               'Červenec','Srpen','Září','Říjen','Listopad','Prosinec'];

$_czechMonth = static fn(int $m): string => $monthNames[$m] ?? (string) $m;

$docTitle = $singleOrder
    ? 'Premium faktura — Objednávka #' . $orderId . ' — ' . (string)($cisticka['jmeno'] ?? '')
    : 'Premium výplata — ' . (string)($cisticka['jmeno'] ?? '') . ' — ' . $monthNames[$month] . ' ' . $year;
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
            background: #7e3ff2; color: #fff; border: none; border-radius: 5px;
            padding: 0.45rem 1.1rem; font-size: 0.88rem; cursor: pointer; font-weight: 600;
        }
        .btn-close {
            background: #e5e7eb; color: #374151; border: none; border-radius: 5px;
            padding: 0.45rem 0.85rem; font-size: 0.88rem; cursor: pointer;
        }
        .toolbar-info { font-size: 0.78rem; color: #6b7280; margin-left: auto; }

        .doc { max-width: 860px; margin: 0 auto; padding: 1.5cm 1.2cm 2cm; }
        .doc-header { margin-bottom: 1.2rem; padding-bottom: 0.8rem; border-bottom: 2px solid #7e3ff2; }
        .doc-title { font-size: 16pt; font-weight: 700; margin-bottom: 0.15rem; color: #4a2480; }
        .doc-subtitle { font-size: 10pt; color: #555; }
        .doc-meta { font-size: 9pt; color: #888; margin-top: 0.25rem; }
        .doc-badge {
            display: inline-block; background: linear-gradient(135deg,#7e3ff2 0%,#a056ff 100%);
            color: #fff; font-size: 8pt; padding: 2px 7px; border-radius: 10px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.05em; margin-left: 0.4rem;
        }

        .summary-row { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.2rem; }
        .summary-box {
            border: 1px solid #d1d5db; border-radius: 6px;
            padding: 0.5rem 0.9rem; flex: 1; min-width: 100px;
        }
        .summary-box__val { font-size: 15pt; font-weight: 700; }
        .summary-box__val--green { color: #16a34a; }
        .summary-box__val--purple { color: #7e3ff2; }
        .summary-box__val--red { color: #dc2626; }
        .summary-box__lbl { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-top: 0.1rem; }

        .order-block { margin-bottom: 1.5rem; page-break-inside: avoid; }
        .order-header {
            background: #f5f0fc; border: 1px solid #d8c5fa; border-bottom: none;
            border-radius: 6px 6px 0 0; padding: 0.5rem 0.85rem;
            display: flex; justify-content: space-between; align-items: center;
            font-size: 10pt;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .order-header strong { color: #4a2480; }
        .order-payout { font-weight: 700; color: #7e3ff2; font-size: 11pt;
            display:flex; align-items:center; gap:0.5rem; }
        .pay-status {
            font-size: 8pt; font-weight: 700; padding: 2px 7px; border-radius: 10px;
        }
        .pay-status--paid { background: #d1fae5; color: #065f46; }
        .pay-status--unpaid { background: #fef3c7; color: #92400e; }

        table { width: 100%; border-collapse: collapse; font-size: 9pt;
                border: 1px solid #e6d9fb; border-top: none; }
        table th {
            background: #faf8fd; font-weight: 600; font-size: 8pt;
            color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em;
            padding: 0.25rem 0.6rem; border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }
        table td { padding: 0.25rem 0.6rem; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        table tr:last-child td { border-bottom: none; }
        .td-num { color: #9ca3af; width: 1.8rem; }
        .td-firm { font-weight: 600; }
        .td-phone { font-family: monospace; font-size: 8.5pt; }
        .td-date { white-space: nowrap; color: #6b7280; font-size: 8.5pt; }
        .td-status {
            font-size: 8pt; padding: 0.05rem 0.4rem; border-radius: 3px;
            display: inline-block; font-weight: 600;
        }
        .st-tradeable     { background: #d1fae5; color: #065f46; }
        .st-non_tradeable { background: #fef3c7; color: #92400e; }
        .st-refund        { background: #fee2e2; color: #991b1b; }
        .price-pill { font-weight: 700; color: #16a34a; }
        .price-pill--refund { color: #dc2626; text-decoration: line-through; }

        .total-line {
            display: flex; justify-content: space-between; align-items: center;
            padding: 0.6rem 0.85rem; background: #fff;
            border: 2px solid #7e3ff2; border-radius: 6px;
            margin-top: 1rem;
            font-size: 12pt;
        }
        .total-line .label { font-weight: 700; color: #4a2480; text-transform: uppercase; letter-spacing: 0.04em; font-size: 10pt; }
        .total-line .val { font-weight: 800; color: #7e3ff2; font-size: 16pt; }

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
            .order-block { page-break-inside: avoid; }
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

    <div class="doc-header">
        <div class="doc-title">
            💎
            <?= $singleOrder ? 'Faktura — Objednávka #' . (int)$orderId : 'Premium výplata' ?>
            <span class="doc-badge"><?= $singleOrder ? 'Faktura' : 'Měsíční souhrn' ?></span>
        </div>
        <div class="doc-subtitle">
            Čistička: <strong><?= htmlspecialchars((string)($cisticka['jmeno'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
            <?php if ($singleOrder && $singleOrderHeader) { ?>
                · OZ: <strong><?= htmlspecialchars((string)$singleOrderHeader['oz_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                · Objednávka z <strong><?= htmlspecialchars($_czechMonth((int)$singleOrderHeader['month']) . ' ' . (int)$singleOrderHeader['year'], ENT_QUOTES, 'UTF-8') ?></strong>
                · Stav: <strong><?= htmlspecialchars((string)$singleOrderHeader['status'], ENT_QUOTES, 'UTF-8') ?></strong>
            <?php } else { ?>
                · Období: <strong><?= htmlspecialchars($_czechMonth($month) . ' ' . $year, ENT_QUOTES, 'UTF-8') ?></strong>
            <?php } ?>
        </div>
        <div class="doc-meta">Vygenerováno: <?= date('d.m.Y H:i') ?></div>
    </div>

    <!-- Souhrn -->
    <div class="summary-row">
        <div class="summary-box">
            <div class="summary-box__val"><?= (int) $totalCount ?></div>
            <div class="summary-box__lbl">Vyčištěno celkem</div>
        </div>
        <?php if (!$singleOrder) { ?>
            <div class="summary-box">
                <div class="summary-box__val summary-box__val--green"><?= (int) $ozCount ?></div>
                <div class="summary-box__lbl">Pro kolik OZ</div>
            </div>
        <?php } ?>
        <?php
        $refundCount = 0;
        foreach ($byOrder as $ord) { $refundCount += (int) $ord['count_refund']; }
        if ($refundCount > 0) { ?>
            <div class="summary-box">
                <div class="summary-box__val summary-box__val--red"><?= $refundCount ?></div>
                <div class="summary-box__lbl">Reklamované</div>
            </div>
        <?php } ?>
        <div class="summary-box">
            <div class="summary-box__val summary-box__val--purple">
                <?= number_format((float)$totalPayable, 2, ',', ' ') ?> Kč
            </div>
            <div class="summary-box__lbl">K vyplacení mně</div>
        </div>
    </div>

    <?php if ($totalCount === 0) { ?>
        <div class="empty-msg">
            <?php if ($singleOrder && $singleOrderHeader) { ?>
                V této objednávce jsem ještě nic nevyčistila.
            <?php } else { ?>
                V <?= htmlspecialchars($_czechMonth($month) . ' ' . $year, ENT_QUOTES, 'UTF-8') ?>
                jsem nevyčistila žádné premium leady.
            <?php } ?>
        </div>
    <?php } ?>

    <!-- Per objednávka -->
    <?php foreach ($byOrder as $oid => $ord) {
        $paidAt = (string) ($ord['paid_to_cleaner_at'] ?? '');
        $isPaid = $paidAt !== '';
    ?>
    <div class="order-block">
        <div class="order-header">
            <span>
                Objednávka <strong>#<?= (int)$oid ?></strong>
                · OZ <strong><?= htmlspecialchars((string)$ord['oz_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                · <?= htmlspecialchars($_czechMonth((int)$ord['order_month']) . ' ' . (int)$ord['order_year'], ENT_QUOTES, 'UTF-8') ?>
                · stav <strong><?= htmlspecialchars((string)$ord['order_status'], ENT_QUOTES, 'UTF-8') ?></strong>
                · <strong><?= number_format((float)$ord['price'], 2, ',', ' ') ?> Kč</strong>/lead
                <?php if ($ord['count_refund'] > 0) { ?>
                    · <span style="color:#dc2626;">⚠ Reklamace: <?= (int)$ord['count_refund'] ?></span>
                <?php } ?>
            </span>
            <span class="order-payout">
                <?php if ($isPaid) { ?>
                    <span class="pay-status pay-status--paid">
                        ✅ Zaplaceno <?= htmlspecialchars(date('j.n.Y', strtotime($paidAt)), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                <?php } else { ?>
                    <span class="pay-status pay-status--unpaid">○ Nezaplaceno</span>
                <?php } ?>
                <?= number_format((float)$ord['payout'], 2, ',', ' ') ?> Kč
            </span>
        </div>
        <table>
            <thead>
                <tr>
                    <th style="width:1.8rem;">#</th>
                    <th>Firma</th>
                    <th>Telefon</th>
                    <th>Kraj</th>
                    <th>Operátor</th>
                    <th>Vyčištěno</th>
                    <th>Výsledek</th>
                    <th style="text-align:right;">Kč</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ord['events'] as $i => $ev) {
                    $cs       = (string) $ev['cleaning_status'];
                    $isRefund = (int) $ev['flagged_for_refund'] === 1;
                    $statusCl = $isRefund ? 'st-refund' : ($cs === 'tradeable' ? 'st-tradeable' : 'st-non_tradeable');
                    $statusLb = $isRefund
                        ? '⚠ Reklamace'
                        : ($cs === 'tradeable' ? '✅ Obchod.' : '❌ Neobch.');
                ?>
                <tr>
                    <td class="td-num"><?= $i + 1 ?></td>
                    <td class="td-firm"><?= htmlspecialchars((string)($ev['firma'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="td-phone"><?= htmlspecialchars((string)($ev['telefon'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(crm_region_label((string)($ev['region'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)($ev['operator'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="td-date">
                        <?= !empty($ev['cleaned_at'])
                            ? htmlspecialchars(date('d.m.Y H:i', strtotime((string)$ev['cleaned_at'])), ENT_QUOTES, 'UTF-8')
                            : '—' ?>
                    </td>
                    <td><span class="td-status <?= $statusCl ?>"><?= $statusLb ?></span></td>
                    <td style="text-align:right;">
                        <span class="price-pill <?= $isRefund ? 'price-pill--refund' : '' ?>">
                            <?= number_format((float)$ord['price'], 2, ',', ' ') ?>
                        </span>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php } ?>

    <?php if ($totalCount > 0) { ?>
        <div class="total-line">
            <span class="label">💰 Celkem k vyplacení mně</span>
            <span class="val"><?= number_format((float)$totalPayable, 2, ',', ' ') ?> Kč</span>
        </div>
    <?php } ?>

    <div class="doc-footer">
        <span>Premium výplata · pole „Reklamace" se nezapočítává do součtu</span>
        <span>Strana 1</span>
    </div>
</div>
</body>
</html>
