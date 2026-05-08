<?php
// e:\Snecinatripu\app\views\oz\premium\payout_print.php
// Standalone tisková stránka — kolik OZ dluží čističce za premium objednávku/měsíc.
declare(strict_types=1);
/** @var array<string,mixed>           $oz           id, jmeno */
/** @var int                           $year */
/** @var int                           $month */
/** @var bool                          $singleOrder  pokud true → faktura jedné objednávky */
/** @var int                           $orderId */
/** @var ?array<string,mixed>          $singleOrderHeader */
/** @var array<int, array<string,mixed>> $byOrder */
/** @var int                           $totalCount */
/** @var float                         $totalPayable */
/** @var float                         $totalCallerBonus */
/** @var array<int, array<string,mixed>> $byCallerBonus */
/** @var list<string>                  $cleaners     unikátní čističky co na tom dělaly */

$monthNames = ['', 'Leden','Únor','Březen','Duben','Květen','Červen',
               'Červenec','Srpen','Září','Říjen','Listopad','Prosinec'];

$_czechMonth = static fn(int $m): string => $monthNames[$m] ?? (string) $m;

$docTitle = $singleOrder
    ? 'Premium faktura — Objednávka #' . $orderId . ' — ' . (string)($oz['jmeno'] ?? '')
    : 'Premium výplata čističce — ' . (string)($oz['jmeno'] ?? '') . ' — ' . $monthNames[$month] . ' ' . $year;
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
        .summary-box__val--purple { color: #7e3ff2; }
        .summary-box__val--red { color: #dc2626; }
        .summary-box__lbl { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-top: 0.1rem; }

        .order-block { margin-bottom: 1.5rem; page-break-inside: avoid; }
        .order-header {
            background: #f5f0fc; border: 1px solid #d8c5fa; border-bottom: none;
            border-radius: 6px 6px 0 0; padding: 0.5rem 0.85rem;
            display: flex; justify-content: space-between; align-items: center;
            font-size: 10pt;
        }
        .order-header strong { color: #4a2480; }
        .order-payout { font-weight: 700; color: #7e3ff2; font-size: 11pt; }

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

        /* Sbalitelná struktura (details/summary) — vlastní šipka + hover */
        details.order-block { display: block; }
        details.order-block > summary { list-style: none; }
        details.order-block > summary::-webkit-details-marker { display: none; }
        details.order-block > summary::before {
            content: "▶";
            display: inline-block;
            margin-right: 0.4rem;
            color: #7e3ff2;
            font-size: 8pt;
            transition: transform 0.15s;
        }
        details.order-block[open] > summary::before { content: "▼"; }
        details.order-block:hover > summary { background: #ebe4f9; }

        @media print {
            .print-toolbar { display: none !important; }
            body { padding: 0; }
            .doc { padding: 0.8cm 0.8cm 1.5cm; max-width: 100%; }
            .order-block { page-break-inside: avoid; }
            /* Force všechna details open v tisku — JS handler to ošetří */
        }
        @page { margin: 1cm 1.2cm; }
    </style>
    <script>
        // Tisk: všechna sbalená details rozbalíme. Po tisku vrátíme původní stav.
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
            <?= $singleOrder ? 'Premium faktura — Objednávka #' . (int)$orderId : 'Premium výplata čističce' ?>
            <span class="doc-badge"><?= $singleOrder ? 'Faktura' : 'Měsíční souhrn' ?></span>
        </div>
        <div class="doc-subtitle">
            Obchodní zástupce: <strong><?= htmlspecialchars((string)$oz['jmeno'], ENT_QUOTES, 'UTF-8') ?></strong>
            <?php if ($singleOrder && $singleOrderHeader) { ?>
                · Objednávka z <strong><?= htmlspecialchars($_czechMonth((int)$singleOrderHeader['month']) . ' ' . (int)$singleOrderHeader['year'], ENT_QUOTES, 'UTF-8') ?></strong>
                · Stav: <strong><?= htmlspecialchars((string)$singleOrderHeader['status'], ENT_QUOTES, 'UTF-8') ?></strong>
            <?php } else { ?>
                · Období: <strong><?= htmlspecialchars($_czechMonth($month) . ' ' . $year, ENT_QUOTES, 'UTF-8') ?></strong>
            <?php } ?>
        </div>
        <?php if ($cleaners !== []) { ?>
            <div class="doc-subtitle" style="margin-top:0.3rem;">
                Čistička/y: <strong><?= htmlspecialchars(implode(', ', $cleaners), ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
        <?php } ?>
        <div class="doc-meta">Vygenerováno: <?= date('d.m.Y H:i') ?></div>
    </div>

    <!-- Souhrn -->
    <div class="summary-row">
        <div class="summary-box">
            <div class="summary-box__val"><?= (int) $totalCount ?></div>
            <div class="summary-box__lbl">Vyčištěno celkem</div>
        </div>
        <?php
        $refundCount = 0;
        foreach ($byOrder as $ord) { $refundCount += (int) $ord['count_refund']; }
        if ($refundCount > 0) { ?>
            <div class="summary-box">
                <div class="summary-box__val summary-box__val--red"><?= $refundCount ?></div>
                <div class="summary-box__lbl">Reklamované (neplatí se)</div>
            </div>
        <?php } ?>
        <div class="summary-box">
            <div class="summary-box__val summary-box__val--purple">
                <?= number_format((float)$totalPayable, 2, ',', ' ') ?> Kč
            </div>
            <div class="summary-box__lbl">K vyplacení čističce</div>
        </div>
    </div>

    <?php if ($totalCount === 0) { ?>
        <div class="empty-msg">
            <?php if ($singleOrder && $singleOrderHeader) { ?>
                V této objednávce zatím nebyl vyčištěn žádný lead.
            <?php } else { ?>
                V <?= htmlspecialchars($_czechMonth($month) . ' ' . $year, ENT_QUOTES, 'UTF-8') ?>
                ještě žádná premium objednávka nedoběhla.
            <?php } ?>
        </div>
    <?php } ?>

    <!-- Per objednávka — VÝPLATA ČISTIČCE -->
    <h3 style="margin: 1.2rem 0 0.6rem; font-size: 11pt; color: #4a2480; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #d8c5fa; padding-bottom: 0.3rem;">
        🧹 Pro čističku — vyčištěné leady
    </h3>
    <p style="font-size: 8.5pt; color: #6b7280; margin-bottom: 0.6rem; line-height:1.5;">
        Klik na hlavičku objednávky pro rozbalení/sbalení detailu kontaktů.
    </p>
    <?php foreach ($byOrder as $oid => $ord) {
        $paidCleanerAt = (string) ($ord['paid_to_cleaner_at'] ?? '');
        $isPaidCleaner = $paidCleanerAt !== '';
        $f = $ord['funnel'] ?? null;
    ?>
    <details class="order-block" open>
        <summary class="order-header" style="list-style:none; cursor:pointer; flex-direction:column; align-items:stretch; gap:0.3rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; width:100%;">
                <span>
                    Objednávka <strong>#<?= (int)$oid ?></strong>
                    · <?= htmlspecialchars($_czechMonth((int)$ord['order_month']) . ' ' . (int)$ord['order_year'], ENT_QUOTES, 'UTF-8') ?>
                    · stav <strong><?= htmlspecialchars((string)$ord['order_status'], ENT_QUOTES, 'UTF-8') ?></strong>
                    · cena <strong><?= number_format((float)$ord['price'], 2, ',', ' ') ?> Kč</strong>/lead
                    · <?= (int)$ord['count_payable'] ?> leadů
                    <?php if ($ord['count_refund'] > 0) { ?>
                        · <span style="color:#dc2626;">⚠ Reklamace: <?= (int)$ord['count_refund'] ?></span>
                    <?php } ?>
                </span>
                <span class="order-payout" style="display:flex; align-items:center; gap:0.5rem;">
                    <?php if ($isPaidCleaner) { ?>
                        <span style="background:#d1fae5; color:#065f46; font-size:8pt; font-weight:700; padding:2px 7px; border-radius:10px;">
                            ✅ Zaplaceno <?= htmlspecialchars(date('j.n.Y', strtotime($paidCleanerAt)), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    <?php } else { ?>
                        <span style="background:#fef3c7; color:#92400e; font-size:8pt; font-weight:700; padding:2px 7px; border-radius:10px;">
                            ○ Nezaplaceno
                        </span>
                    <?php } ?>
                    <?= number_format((float)$ord['payout'], 2, ',', ' ') ?> Kč
                </span>
            </div>
            <?php if ($f) { ?>
                <div style="font-size: 8.5pt; color: #6b7280; padding: 0.2rem 0; line-height: 1.4;">
                    📊 <strong style="color:#4a2480;">Funnel objednávky:</strong>
                    objednáno <strong><?= (int) $f['requested_count'] ?></strong>
                    → zarezervováno <strong><?= (int) $f['reserved_count'] ?></strong>
                    → vyčištěno <strong><?= (int) $f['cleaned_total'] ?></strong>
                    → z toho obchodovatelných <strong style="color:#16a34a;"><?= (int) $f['tradeable_total'] ?></strong>
                    → úspěšně navoláno <strong style="color:#7e3ff2;"><?= (int) $f['called_success'] ?></strong>
                </div>
            <?php } ?>
        </summary>
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
    </details>
    <?php } ?>

    <?php if ($totalCount > 0) { ?>
        <div class="total-line">
            <span class="label">💰 Celkem k vyplacení čističce</span>
            <span class="val"><?= number_format((float)$totalPayable, 2, ',', ' ') ?> Kč</span>
        </div>
    <?php } ?>

    <!-- Sekce: Bonus pro navolávačky (jen pokud se něco navolalo s bonusem > 0) -->
    <?php if ($byCallerBonus !== []) { ?>
        <h3 style="margin: 1.6rem 0 0.6rem; font-size: 11pt; color: #4a2480; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #d8c5fa; padding-bottom: 0.3rem;">
            📞 Pro navolávačky — bonus za úspěšný hovor
        </h3>
        <p style="font-size: 8.5pt; color: #6b7280; margin-bottom: 0.6rem; line-height:1.5;">
            Bonus se počítá jen z <strong>úspěšně navolaných</strong> tradeable leadů (bez reklamací).
            Standardní sazba navolávačky (od majitele) tu není — jen tvůj premium bonus.
            Klikni na ➕ vedle objednávky pro detail vyvolaných leadů.
        </p>
        <?php foreach ($byCallerBonus as $callerId => $cb) { ?>
            <div class="order-block">
                <div class="order-header" style="background:#fff5e6; border-color:#ffd699;">
                    <span>
                        Navolávačka: <strong><?= htmlspecialchars((string) $cb['caller_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                        · <?= (int) $cb['count_total'] ?> úspěšně navolaných premium leadů
                    </span>
                    <span style="font-weight:700; color:#d97706; font-size:11pt;">
                        <?= number_format((float) $cb['amount_total'], 2, ',', ' ') ?> Kč
                    </span>
                </div>

                <?php foreach ($cb['orders'] as $oid => $row) {
                    $paidAt = (string) ($row['paid_to_caller_at'] ?? '');
                    $isPaid = $paidAt !== '';
                    $f = $row['funnel'] ?? null;
                ?>
                    <details style="border:1px solid #ffd699; border-top:none; padding: 0.4rem 0.7rem; background:#fffdf5;">
                        <summary style="cursor:pointer; font-size:9.5pt; flex-direction:column; gap:0.3rem;">
                            <div style="display:flex; justify-content:space-between; gap:0.5rem; align-items:center; flex-wrap:wrap;">
                                <span>
                                    <strong>#<?= (int) $oid ?></strong>
                                    · bonus <strong><?= number_format((float) $row['bonus_per_lead'], 2, ',', ' ') ?> Kč</strong>/lead
                                    · <strong><?= (int) $row['count'] ?></strong> úspěšně
                                    · <strong style="color:#d97706;"><?= number_format((float) $row['amount'], 2, ',', ' ') ?> Kč</strong>
                                    <?php if ($isPaid) { ?>
                                        <span class="td-status st-tradeable" style="margin-left:0.4rem;">
                                            ✅ Zaplaceno <?= htmlspecialchars(date('j.n.Y', strtotime($paidAt)), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php } else { ?>
                                        <span class="td-status st-non_tradeable" style="margin-left:0.4rem;">○ Nezaplaceno</span>
                                    <?php } ?>
                                </span>
                                <span style="font-size:8pt; color:#6b7280;">▼ klik pro detail kontaktů</span>
                            </div>
                            <?php if ($f) { ?>
                                <div style="font-size: 8pt; color: #6b7280; padding: 0.2rem 0; line-height: 1.4;">
                                    📊 <strong style="color:#92400e;">Funnel:</strong>
                                    objednáno <strong><?= (int) $f['requested_count'] ?></strong>
                                    → vyčištěno <strong><?= (int) $f['cleaned_total'] ?></strong>
                                    → obchodovatelných <strong style="color:#16a34a;"><?= (int) $f['tradeable_total'] ?></strong>
                                    → úspěšně navoláno <strong style="color:#7e3ff2;"><?= (int) $f['called_success'] ?></strong>
                                </div>
                            <?php } ?>
                        </summary>

                        <table style="margin-top: 0.4rem;">
                            <thead>
                                <tr>
                                    <th style="width:1.8rem;">#</th>
                                    <th>Firma</th>
                                    <th>Telefon</th>
                                    <th>Kraj</th>
                                    <th>Operátor</th>
                                    <th>Datum hovoru</th>
                                    <th style="text-align:right;">Bonus</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($row['leads'] ?? []) as $i => $ev) { ?>
                                    <tr>
                                        <td class="td-num"><?= $i + 1 ?></td>
                                        <td class="td-firm"><?= htmlspecialchars((string)($ev['firma'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="td-phone"><?= htmlspecialchars((string)($ev['telefon'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars(crm_region_label((string)($ev['region'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string)($ev['operator'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="td-date">
                                            <?= !empty($ev['called_at'])
                                                ? htmlspecialchars(date('d.m.Y H:i', strtotime((string)$ev['called_at'])), ENT_QUOTES, 'UTF-8')
                                                : '—' ?>
                                        </td>
                                        <td style="text-align:right; font-weight:700; color:#d97706;">
                                            <?= number_format((float) $row['bonus_per_lead'], 2, ',', ' ') ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </details>
                <?php } ?>
            </div>
        <?php } ?>

        <div class="total-line" style="border-color:#d97706;">
            <span class="label" style="color:#92400e;">💰 Celkem bonus pro navolávačky</span>
            <span class="val" style="color:#d97706;">
                <?= number_format((float) $totalCallerBonus, 2, ',', ' ') ?> Kč
            </span>
        </div>
    <?php } else if ($totalCount > 0) { ?>
        <p style="margin-top: 1.5rem; padding: 0.6rem 0.85rem; background:#f9fafb; border-radius:6px; font-size:9pt; color:#6b7280;">
            <strong>Bonus pro navolávačky:</strong> 0 Kč (zatím nikdo úspěšně nenavolal,
            nebo objednávka neměla nastavený bonus).
        </p>
    <?php } ?>

    <!-- VELKÝ FINÁLNÍ SOUČET (čistička + navolávačka) -->
    <?php if ($totalCount > 0) { ?>
        <div class="total-line" style="margin-top: 1rem; background: linear-gradient(135deg,#7e3ff2 0%,#a056ff 100%); border-color: transparent;">
            <span class="label" style="color: rgba(255,255,255,0.85);">
                📊 Premium náklady celkem (čistička + navolávačky)
            </span>
            <span class="val" style="color: #fff;">
                <?= number_format((float)$totalPayable + (float)$totalCallerBonus, 2, ',', ' ') ?> Kč
            </span>
        </div>
    <?php } ?>

    <div class="doc-footer">
        <span>Premium faktura · pole „Reklamace" se nezapočítává do součtu</span>
        <span>Strana 1</span>
    </div>
</div>
</body>
</html>
