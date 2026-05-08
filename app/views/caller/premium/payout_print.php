<?php
// e:\Snecinatripu\app\views\caller\premium\payout_print.php
// Standalone tisková stránka — premium výplata navolávačky.
// Zobrazuje:
//   1) Standardní sazba (od majitele) × počet úspěšných premium hovorů
//   2) Bonus od OZ — per OZ × per objednávka × per lead
declare(strict_types=1);
/** @var array<string,mixed>           $caller       id, jmeno */
/** @var int                           $year */
/** @var int                           $month */
/** @var bool                          $singleOrder */
/** @var int                           $orderId */
/** @var ?array<string,mixed>          $singleOrderHeader */
/** @var float                         $standardReward  Kč/úspěšný hovor (od majitele) */
/** @var float                         $standardTotal   = standardReward * count_payable */
/** @var float                         $totalBonus      bonusy od OZ */
/** @var int                           $totalCount      všechny úspěšné premium hovory vč. reklamovaných */
/** @var int                           $totalCountPayable */
/** @var int                           $totalCountBonus */
/** @var array<int, array<string,mixed>> $byOz */

$monthNames = ['', 'Leden','Únor','Březen','Duben','Květen','Červen',
               'Červenec','Srpen','Září','Říjen','Listopad','Prosinec'];
$_czechMonth = static fn(int $m): string => $monthNames[$m] ?? (string) $m;

$totalAll = $standardTotal + $totalBonus;

$docTitle = $singleOrder
    ? 'Premium faktura — Objednávka #' . $orderId . ' — ' . (string)($caller['jmeno'] ?? '')
    : 'Premium výplata — ' . (string)($caller['jmeno'] ?? '') . ' — ' . $monthNames[$month] . ' ' . $year;
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
            padding: 0.75rem 1.2rem; background: #f4f4f4; border-bottom: 1px solid #ddd;
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
            padding: 0.5rem 0.9rem; flex: 1; min-width: 120px;
        }
        .summary-box__val { font-size: 14pt; font-weight: 700; }
        .summary-box__val--blue   { color: #2563eb; }
        .summary-box__val--orange { color: #d97706; }
        .summary-box__val--purple { color: #7e3ff2; }
        .summary-box__lbl {
            font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.05em;
            color: #6b7280; margin-top: 0.1rem;
        }

        h3.section-h {
            margin: 1.4rem 0 0.6rem; font-size: 11pt; color: #4a2480;
            text-transform: uppercase; letter-spacing: 0.05em;
            border-bottom: 1px solid #d8c5fa; padding-bottom: 0.3rem;
        }

        .standard-box {
            background: #eff6ff; border: 1px solid #bfdbfe;
            border-left: 4px solid #2563eb; border-radius: 0 6px 6px 0;
            padding: 0.7rem 1rem; margin-bottom: 1rem;
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 0.5rem;
        }
        .standard-box .label { font-size: 9.5pt; color: #1e3a8a; }
        .standard-box .label strong { color: #1d4ed8; }
        .standard-box .val {
            font-size: 14pt; font-weight: 700; color: #2563eb;
        }

        .order-block { margin-bottom: 1rem; page-break-inside: avoid; }
        .order-header {
            background: #fff5e6; border: 1px solid #ffd699; border-bottom: none;
            border-radius: 6px 6px 0 0; padding: 0.5rem 0.85rem;
            display: flex; justify-content: space-between; align-items: center;
            font-size: 10pt; flex-wrap: wrap; gap: 0.5rem;
        }
        .order-header strong { color: #92400e; }
        .order-payout { font-weight: 700; color: #d97706; font-size: 11pt; }

        details.order-block { display: block; }
        details.order-block > summary { list-style: none; }
        details.order-block > summary::-webkit-details-marker { display: none; }
        details.order-block > summary::before {
            content: "▶"; display: inline-block; margin-right: 0.4rem;
            color: #d97706; font-size: 8pt;
        }
        details.order-block[open] > summary::before { content: "▼"; }
        details.order-block:hover > summary { background: #ffe8c2; }

        table { width: 100%; border-collapse: collapse; font-size: 9pt;
                border: 1px solid #ffd699; border-top: none; }
        table th {
            background: #fffaf0; font-weight: 600; font-size: 8pt;
            color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em;
            padding: 0.25rem 0.6rem; border-bottom: 1px solid #fde68a;
            text-align: left;
        }
        table td { padding: 0.25rem 0.6rem; border-bottom: 1px solid #fef3c7; vertical-align: top; }
        table tr:last-child td { border-bottom: none; }
        .td-num { color: #9ca3af; width: 1.8rem; }
        .td-firm { font-weight: 600; }
        .td-phone { font-family: monospace; font-size: 8.5pt; }
        .td-date { white-space: nowrap; color: #6b7280; font-size: 8.5pt; }
        .td-status {
            font-size: 8pt; padding: 0.05rem 0.4rem; border-radius: 3px;
            display: inline-block; font-weight: 600;
        }
        .st-paid   { background: #d1fae5; color: #065f46; }
        .st-unpaid { background: #fef3c7; color: #92400e; }
        .st-refund { background: #fee2e2; color: #991b1b; }

        .total-line {
            display: flex; justify-content: space-between; align-items: center;
            padding: 0.6rem 0.85rem; background: #fff;
            border: 2px solid #d97706; border-radius: 6px;
            margin-top: 0.6rem;
            font-size: 12pt;
        }
        .total-line .label {
            font-weight: 700; color: #92400e;
            text-transform: uppercase; letter-spacing: 0.04em; font-size: 10pt;
        }
        .total-line .val { font-weight: 800; color: #d97706; font-size: 16pt; }

        .grand-total {
            margin-top: 1.5rem; padding: 0.9rem 1.1rem;
            background: linear-gradient(135deg,#7e3ff2 0%,#a056ff 100%);
            color: #fff; border-radius: 8px;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 2px 8px rgba(126,63,242,0.3);
        }
        .grand-total .label {
            font-size: 11pt; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .grand-total .val { font-size: 20pt; font-weight: 800; }

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
    <script>
        // Tisk: rozbalí všechna sbalená details, pak vrátí.
        window.addEventListener('beforeprint', () => {
            document.querySelectorAll('details').forEach(d => {
                d.dataset.wasOpen = d.open ? '1' : '0';
                d.open = true;
            });
        });
        window.addEventListener('afterprint', () => {
            document.querySelectorAll('details').forEach(d => {
                if (d.dataset.wasOpen === '0') d.open = false;
                delete d.dataset.wasOpen;
            });
        });
    </script>
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
            <?= $singleOrder
                ? 'Premium faktura — Objednávka #' . (int)$orderId
                : 'Premium výplata' ?>
            <span class="doc-badge"><?= $singleOrder ? 'Faktura' : 'Měsíční souhrn' ?></span>
        </div>
        <div class="doc-subtitle">
            Navolávačka: <strong><?= htmlspecialchars((string)($caller['jmeno'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
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
            <div class="summary-box__lbl">Úspěšně navoláno</div>
        </div>
        <div class="summary-box">
            <div class="summary-box__val summary-box__val--blue">
                <?= number_format((float) $standardTotal, 2, ',', ' ') ?> Kč
            </div>
            <div class="summary-box__lbl">Standard od majitele</div>
        </div>
        <div class="summary-box">
            <div class="summary-box__val summary-box__val--orange">
                <?= number_format((float) $totalBonus, 2, ',', ' ') ?> Kč
            </div>
            <div class="summary-box__lbl">Bonus od OZ</div>
        </div>
        <div class="summary-box">
            <div class="summary-box__val summary-box__val--purple">
                <?= number_format((float) $totalAll, 2, ',', ' ') ?> Kč
            </div>
            <div class="summary-box__lbl">Celkem</div>
        </div>
    </div>

    <?php if ($totalCount === 0) { ?>
        <div class="empty-msg">
            V <?= htmlspecialchars($_czechMonth($month) . ' ' . $year, ENT_QUOTES, 'UTF-8') ?>
            jsi nenavolala žádný premium lead.
        </div>
    <?php } ?>

    <!-- ════════ STANDARDNÍ SAZBA (od majitele) ════════ -->
    <?php if ($totalCountPayable > 0) { ?>
        <h3 class="section-h">💼 Standardní sazba — od majitele</h3>
        <div class="standard-box">
            <div class="label">
                Sazba <strong><?= number_format((float) $standardReward, 2, ',', ' ') ?> Kč</strong>
                × <strong><?= (int) $totalCountPayable ?></strong> úspěšných premium hovorů
                <?php if ($totalCount > $totalCountPayable) { ?>
                    <br><small style="color:#dc2626;">
                        ⚠ Z toho <?= $totalCount - $totalCountPayable ?> reklamovaných (neplatí se)
                    </small>
                <?php } ?>
            </div>
            <div class="val">
                = <?= number_format((float) $standardTotal, 2, ',', ' ') ?> Kč
            </div>
        </div>
        <p style="font-size: 8.5pt; color: #6b7280; margin-bottom: 0.6rem;">
            Standardní sazba (<?= number_format((float) $standardReward, 2, ',', ' ') ?> Kč/úspěšný hovor)
            ti přísluší od majitele za každý CALLED_OK lead — i z premium pipeline.
            Platí stejně jako u běžných hovorů.
        </p>
    <?php } ?>

    <!-- ════════ BONUS OD OZ ════════ -->
    <?php if ($byOz !== []) { ?>
        <h3 class="section-h">💰 Bonus od OZ — premium navolávky</h3>
        <p style="font-size: 8.5pt; color: #6b7280; margin-bottom: 0.6rem; line-height:1.5;">
            Extra bonus za úspěšný hovor, který si OZ nastavil u objednávky.
            Klik na ➕ vedle objednávky pro detail vyvolaných kontaktů.
        </p>
        <?php foreach ($byOz as $ozId => $oz) { ?>
            <?php $bonusOnly = (float) $oz['bonus_total'];
                  $hasBonus = $bonusOnly > 0; ?>
            <details class="order-block" open>
                <summary class="order-header" style="list-style:none; cursor:pointer;">
                    <span>👔 OZ <strong><?= htmlspecialchars((string) $oz['oz_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                          · <?= (int) $oz['count_total'] ?> úspěšně navolaných</span>
                    <span class="order-payout">
                        <?php if ($hasBonus) { ?>
                            <?= number_format($bonusOnly, 2, ',', ' ') ?> Kč
                        <?php } else { ?>
                            <span style="color:#6b7280; font-weight:400; font-size:9pt;">bez bonusu</span>
                        <?php } ?>
                    </span>
                </summary>
                <?php foreach ($oz['orders'] as $oid => $row) {
                    $paidAt = (string) ($row['paid_to_caller_at'] ?? '');
                    $isPaid = $paidAt !== '';
                    $bpl    = (float) $row['bonus_per_lead'];
                ?>
                    <?php $f = $row['funnel'] ?? null; ?>
                    <details class="order-block" style="margin: 0;">
                        <summary class="order-header" style="background:#fffbeb; border-color:#fde68a; cursor:pointer; flex-direction:column; align-items:stretch; gap:0.3rem;">
                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; width:100%;">
                                <span>
                                    Objednávka <strong>#<?= (int)$oid ?></strong>
                                    · <?= htmlspecialchars($_czechMonth((int)$row['order_month']) . ' ' . (int)$row['order_year'], ENT_QUOTES, 'UTF-8') ?>
                                    · stav <strong><?= htmlspecialchars((string)$row['order_status'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    · bonus <strong><?= number_format($bpl, 2, ',', ' ') ?> Kč</strong>/lead
                                    · <strong><?= (int) $row['count'] ?>×</strong>
                                    <?php if ($row['count_refund'] > 0) { ?>
                                        · <span style="color:#dc2626;">⚠ Reklamace: <?= (int)$row['count_refund'] ?></span>
                                    <?php } ?>
                                </span>
                                <span style="font-weight:700; color:#d97706; font-size:11pt; display:flex; align-items:center; gap:0.5rem;">
                                    <?php if ($isPaid) { ?>
                                        <span class="td-status st-paid">
                                            ✅ <?= htmlspecialchars(date('j.n.Y', strtotime($paidAt)), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php } else if ($bpl > 0) { ?>
                                        <span class="td-status st-unpaid">○ Nezaplaceno</span>
                                    <?php } ?>
                                    <?= number_format((float) $row['bonus_total'], 2, ',', ' ') ?> Kč
                                </span>
                            </div>
                            <?php if ($f) { ?>
                                <div style="font-size: 8.5pt; color: #6b7280; padding: 0.2rem 0; line-height: 1.4;">
                                    📊 <strong style="color:#92400e;">Funnel objednávky:</strong>
                                    objednáno <strong><?= (int) $f['requested_count'] ?></strong>
                                    → zarezervováno <strong><?= (int) $f['reserved_count'] ?></strong>
                                    → vyčištěno <strong><?= (int) $f['cleaned_total'] ?></strong>
                                    → z toho obchodovatelných <strong style="color:#16a34a;"><?= (int) $f['tradeable_total'] ?></strong>
                                    → úspěšně navoláno <strong style="color:#7e3ff2;"><?= (int) $f['called_success'] ?></strong>
                                </div>
                            <?php } ?>
                        </summary>
                        <table style="margin-top: 0;">
                            <thead>
                                <tr>
                                    <th style="width:1.8rem;">#</th>
                                    <th>Firma</th>
                                    <th>Telefon</th>
                                    <th>Kraj</th>
                                    <th>Operátor</th>
                                    <th>Datum hovoru</th>
                                    <th style="text-align:right;">Bonus Kč</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($row['leads'] ?? []) as $i => $ev) {
                                    $isRefund = (int) ($ev['flagged_for_refund'] ?? 0) === 1;
                                ?>
                                    <tr<?= $isRefund ? ' style="background:#fef2f2;"' : '' ?>>
                                        <td class="td-num"><?= $i + 1 ?></td>
                                        <td class="td-firm">
                                            <?= htmlspecialchars((string)($ev['firma'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                                            <?php if ($isRefund) { ?>
                                                <span class="td-status st-refund" style="margin-left:4px;">⚠ Reklamace</span>
                                            <?php } ?>
                                        </td>
                                        <td class="td-phone"><?= htmlspecialchars((string)($ev['telefon'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars(crm_region_label((string)($ev['region'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string)($ev['operator'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="td-date">
                                            <?= !empty($ev['called_at'])
                                                ? htmlspecialchars(date('d.m.Y H:i', strtotime((string)$ev['called_at'])), ENT_QUOTES, 'UTF-8')
                                                : '—' ?>
                                        </td>
                                        <td style="text-align:right; font-weight:700; color:#d97706;">
                                            <?php if ($isRefund) { ?>
                                                <span style="color:#dc2626; text-decoration:line-through;">
                                                    <?= number_format($bpl, 2, ',', ' ') ?>
                                                </span>
                                            <?php } else { ?>
                                                <?= number_format($bpl, 2, ',', ' ') ?>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </details>
                <?php } ?>
            </details>
        <?php } ?>
        <div class="total-line">
            <span class="label">💰 Celkem bonus od OZ</span>
            <span class="val"><?= number_format((float) $totalBonus, 2, ',', ' ') ?> Kč</span>
        </div>
    <?php } ?>

    <!-- ════════ FINÁLNÍ SOUČET ════════ -->
    <?php if ($totalCountPayable > 0 || $totalBonus > 0) { ?>
        <div class="grand-total">
            <span class="label">📊 K vyplacení celkem</span>
            <span class="val"><?= number_format((float) $totalAll, 2, ',', ' ') ?> Kč</span>
        </div>
        <p style="font-size: 8pt; color: #6b7280; margin-top: 0.4rem; text-align: center;">
            Standard od majitele (<?= number_format((float) $standardTotal, 2, ',', ' ') ?> Kč) +
            bonus od OZ (<?= number_format((float) $totalBonus, 2, ',', ' ') ?> Kč)
            = <strong><?= number_format((float) $totalAll, 2, ',', ' ') ?> Kč</strong>
        </p>
    <?php } ?>

    <div class="doc-footer">
        <span>Premium výplata · reklamované leady (⚠) se nezapočítávají</span>
        <span>Strana 1</span>
    </div>
</div>
</body>
</html>
