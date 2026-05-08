<?php
// e:\Snecinatripu\app\views\caller\premium\order.php
declare(strict_types=1);
/** @var array<string,mixed>           $user */
/** @var string                        $csrf */
/** @var ?string                       $flash */
/** @var array<string,mixed>           $order  hlavička objednávky */
/** @var string                        $tab */
/** @var array<string,int>             $tabCounts */
/** @var list<array<string,mixed>>     $leads */

$_czechMonth = static fn(int $m): string => [
    1=>'Leden',2=>'Únor',3=>'Březen',4=>'Duben',5=>'Květen',6=>'Červen',
    7=>'Červenec',8=>'Srpen',9=>'Září',10=>'Říjen',11=>'Listopad',12=>'Prosinec'
][$m] ?? (string)$m;

$bonus = (float) $order['caller_bonus_per_lead'];

$_tabsList = [
    'k_volani'   => ['label' => '⏳ K volání',   'count' => $tabCounts['k_volani']   ?? 0],
    'callbacky'  => ['label' => '📅 Callbacky',  'count' => $tabCounts['callbacky']  ?? 0],
    'nedovolano' => ['label' => '📵 Nedovoláno', 'count' => $tabCounts['nedovolano'] ?? 0],
    'navolane'   => ['label' => '✅ Navolané',   'count' => $tabCounts['navolane']   ?? 0],
    'prohra'     => ['label' => '❌ Prohra',     'count' => $tabCounts['prohra']     ?? 0],
];

$_tabUrl = static fn(string $t): string =>
    crm_url('/caller/premium/order?id=' . (int) $order['id'] . '&tab=' . $t);
?>

<style>
.po-detail-header {
    display: flex; align-items: center; justify-content: space-between;
    gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap;
}
.po-detail-header h1 { margin: 0; font-size: 1.3rem; color: #4a2480; }
.po-back {
    text-decoration: none;
    color: var(--color-text-muted);
    font-size: 0.85rem;
    border: 1px solid var(--color-border);
    padding: 0.4rem 0.8rem;
    border-radius: 5px;
    background: #fff;
}
.po-back:hover { background: #fafafa; }

.po-meta-bar {
    background: #f5f0fc;
    border: 1px solid #d8c5fa;
    border-left: 4px solid #7e3ff2;
    border-radius: 0 6px 6px 0;
    padding: 0.7rem 1rem;
    margin-bottom: 0.8rem;
    display: flex; flex-wrap: wrap; gap: 1.2rem;
    font-size: 0.85rem;
}
.po-meta-bar > div { line-height: 1.5; }
.po-meta-bar strong { color: #4a2480; }
.po-bonus-highlight {
    background: linear-gradient(135deg,#fff5e6 0%,#ffe8c2 100%);
    border-left-color: #d97706 !important;
    color: #92400e !important;
    border-color: #ffd699;
}

/* Progress bar: zpracováno X/Y */
.po-progress-card {
    background: #fff;
    border: 1px solid #d8c5fa;
    border-radius: 6px;
    padding: 0.8rem 1rem;
    margin-bottom: 0.9rem;
}
.po-progress-card .label-row {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 0.45rem; flex-wrap: wrap; gap: 0.5rem;
}
.po-progress-card .title {
    font-weight: 700; color: #4a2480; font-size: 0.95rem;
}
.po-progress-card .title .nums { color: #7e3ff2; }
.po-progress-card .pct {
    background: linear-gradient(135deg,#7e3ff2 0%,#a056ff 100%);
    color: #fff; padding: 3px 10px; border-radius: 12px;
    font-weight: 700; font-size: 0.85rem;
}
.po-progress-card .bar {
    height: 10px; background: #f3f4f6; border-radius: 5px; overflow: hidden;
    position: relative;
}
.po-progress-card .bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #7e3ff2 0%, #a056ff 100%);
    transition: width 0.3s ease;
}
.po-progress-card .breakdown {
    display: flex; gap: 0.7rem; flex-wrap: wrap;
    margin-top: 0.55rem;
    font-size: 0.8rem;
    color: var(--color-text-muted);
    align-items: center;
}
.po-progress-card .breakdown .pill {
    padding: 2px 9px; border-radius: 10px;
    font-weight: 600;
}
.po-progress-card .breakdown .pill.done    { background: #d1fae5; color: #065f46; }
.po-progress-card .breakdown .pill.lost    { background: #fee2e2; color: #991b1b; }
.po-progress-card .breakdown .pill.pending { background: #ede9fe; color: #5b21b6; }
.po-progress-card .breakdown .pill.cb      { background: #dbeafe; color: #1e40af; }
.po-progress-card .breakdown .pill.nedo    { background: #fef3c7; color: #92400e; }
.po-progress-card .footnote {
    font-size: 0.72rem; color: var(--color-text-muted);
    margin-top: 0.4rem; line-height: 1.4;
}

.pn-tabs {
    display: flex; gap: 0; border-bottom: 2px solid var(--color-border);
    margin-bottom: 1rem; flex-wrap: wrap;
}
.pn-tab {
    padding: 0.55rem 0.9rem;
    text-decoration: none;
    color: var(--color-text-muted);
    font-size: 0.88rem;
    font-weight: 600;
    border-bottom: 3px solid transparent;
    margin-bottom: -2px;
    display: inline-flex; align-items: center; gap: 0.3rem;
}
.pn-tab:hover { color: var(--color-text); background: #f9f7fd; }
.pn-tab.is-active {
    color: #7e3ff2;
    border-bottom-color: #7e3ff2;
    background: #faf8fd;
}
.pn-tab .badge {
    display: inline-block;
    background: #ede9fe;
    color: #5b21b6;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 1px 7px;
    border-radius: 10px;
    min-width: 1.4rem;
    text-align: center;
}
.pn-tab.is-active .badge { background: #7e3ff2; color: #fff; }

.pn-lead-card {
    background: #fff; border: 1px solid var(--color-border);
    border-left: 4px solid #7e3ff2;
    border-radius: 6px; padding: 0.8rem 1rem; margin-bottom: 0.5rem;
}
.pn-lead-card--callbacky  { border-left-color: #2563eb; background: #eff6ff; }
.pn-lead-card--nedovolano { border-left-color: #f59e0b; background: #fffbeb; }
.pn-lead-card--navolane   { border-left-color: #16a34a; background: #ecfdf5; }
.pn-lead-card--prohra     { border-left-color: #dc2626; background: #fef2f2; }

.lead-row1 { display: flex; gap: 0.6rem; flex-wrap: wrap; align-items: baseline; margin-bottom: 0.3rem; }
.lead-row1 .firm { font-weight: 700; font-size: 1.05rem; }
.lead-row1 .phone {
    font-family: monospace; color: #2563eb; font-size: 0.95rem; font-weight: 600;
    background: #eff6ff; padding: 2px 8px; border-radius: 4px;
    text-decoration: none;
}
.lead-row1 .badge-status {
    font-size: 0.72rem; padding: 2px 7px; border-radius: 10px; font-weight: 700;
}
.lead-row-meta {
    display: flex; flex-wrap: wrap; gap: 0.6rem;
    font-size: 0.78rem; color: var(--color-text-muted); margin-bottom: 0.5rem;
}
.lead-row-meta strong { color: var(--color-text); }
.lead-prilez {
    background: #fef9c3; padding: 0.4rem 0.6rem; border-radius: 4px;
    font-size: 0.85rem; margin-bottom: 0.5rem;
    border-left: 3px solid #ca8a04;
}
.lead-actions { display: flex; gap: 0.4rem; flex-wrap: wrap; margin-top: 0.5rem; }
.lead-actions form { margin: 0; }
.btn-action {
    border: none; border-radius: 4px;
    padding: 0.4rem 0.8rem; font-size: 0.82rem; font-weight: 700; cursor: pointer;
}
.btn-success { background: #16a34a; color: #fff; }
.btn-success:hover { background: #15803d; }
.btn-callback { background: #2563eb; color: #fff; }
.btn-callback:hover { background: #1d4ed8; }
.btn-warning { background: #f59e0b; color: #fff; }
.btn-warning:hover { background: #d97706; }
.btn-danger { background: #dc2626; color: #fff; }
.btn-danger:hover { background: #b91c1c; }
.note-input {
    width: 100%; border: 1px solid var(--color-border-strong);
    border-radius: 4px; padding: 0.35rem 0.55rem;
    font-size: 0.85rem; margin-bottom: 0.4rem;
}
.callback-input {
    border: 1px solid var(--color-border-strong);
    border-radius: 4px; padding: 0.3rem 0.55rem; font-size: 0.8rem;
}
.pn-empty { text-align: center; padding: 2.5rem 1rem; color: var(--color-text-muted); }
.pn-empty .big { font-size: 2.5rem; margin-bottom: 0.5rem; }
</style>

<section class="card">
    <div class="po-detail-header">
        <h1>💎 Premium objednávka #<?= (int) $order['id'] ?></h1>
        <a href="<?= crm_h(crm_url('/caller/premium')) ?>" class="po-back">← Zpět na seznam</a>
    </div>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info" style="margin-bottom:1rem;"><?= crm_h($flash) ?></p>
    <?php } ?>

    <div class="po-meta-bar">
        <div>👔 OZ: <strong><?= crm_h((string) $order['oz_name']) ?></strong></div>
        <div>📅 <strong><?= crm_h($_czechMonth((int)$order['month'])) ?> <?= (int) $order['year'] ?></strong></div>
        <?php if ((string) ($order['status'] ?? '') === 'closed') { ?>
            <div>🏁 <strong>Uzavřená OZ</strong> — dotahej rozdělané leady</div>
        <?php } ?>
        <?php if (!empty($order['note'])) { ?>
            <div>📝 <?= crm_h((string) $order['note']) ?></div>
        <?php } ?>
    </div>

    <?php if ($bonus > 0) { ?>
        <div class="po-meta-bar po-bonus-highlight">
            <div style="font-size:1.05rem;">
                💰 <strong>+<?= number_format($bonus, 2, ',', ' ') ?> Kč BONUS</strong>
                za každý úspěšně navolaný lead z této objednávky.
            </div>
        </div>
    <?php } ?>

    <!-- Progress: Zpracováno X/Y (jasný konečný stav: Navoláno + Prohra) -->
    <?php
    $tcK   = (int) ($tabCounts['k_volani']  ?? 0);
    $tcCb  = (int) ($tabCounts['callbacky'] ?? 0);
    $tcNed = (int) ($tabCounts['nedovolano']?? 0);
    $tcNav = (int) ($tabCounts['navolane']  ?? 0);
    $tcPro = (int) ($tabCounts['prohra']    ?? 0);
    $totalLeads = $tcK + $tcCb + $tcNed + $tcNav + $tcPro;
    $doneFinal  = $tcNav + $tcPro;
    $pct        = $totalLeads > 0 ? (int) round($doneFinal * 100 / $totalLeads) : 0;
    ?>
    <?php if ($totalLeads > 0) { ?>
        <div class="po-progress-card">
            <div class="label-row">
                <span class="title">
                    📊 Zpracováno <span class="nums"><?= $doneFinal ?>/<?= $totalLeads ?></span>
                </span>
                <span class="pct"><?= $pct ?>%</span>
            </div>
            <div class="bar"><div class="bar-fill" style="width: <?= $pct ?>%;"></div></div>
            <div class="breakdown">
                <?php if ($tcNav > 0) { ?>
                    <span class="pill done">✅ <?= $tcNav ?> navoláno</span>
                <?php } ?>
                <?php if ($tcPro > 0) { ?>
                    <span class="pill lost">❌ <?= $tcPro ?> prohra</span>
                <?php } ?>
                <?php if ($tcK > 0) { ?>
                    <span class="pill pending">⏳ <?= $tcK ?> k volání</span>
                <?php } ?>
                <?php if ($tcCb > 0) { ?>
                    <span class="pill cb">📅 <?= $tcCb ?> callback</span>
                <?php } ?>
                <?php if ($tcNed > 0) { ?>
                    <span class="pill nedo">📵 <?= $tcNed ?> nedovoláno (max 3×)</span>
                <?php } ?>
            </div>
            <div class="footnote">
                <strong>Zpracováno</strong> = lead s <em>jasným konečným stavem</em> (✅ Navoláno nebo ❌ Prohra).
                Callback se ještě může otočit, Nedovoláno se točí 3× než spadne na NEZAJEM.
            </div>
        </div>
    <?php } ?>

    <!-- Taby — stejný styl jako /caller/premium top-level -->
    <div class="pn-tabs">
        <?php foreach ($_tabsList as $tabKey => $tabInfo) { ?>
            <a href="<?= crm_h($_tabUrl($tabKey)) ?>"
               class="pn-tab<?= $tab === $tabKey ? ' is-active' : '' ?>">
                <?= crm_h($tabInfo['label']) ?>
                <span class="badge"><?= (int) $tabInfo['count'] ?></span>
            </a>
        <?php } ?>
    </div>

    <?php
    $isCallable = in_array($tab, ['k_volani', 'callbacky', 'nedovolano'], true);
    if ($leads === []) { ?>
        <div class="pn-empty">
            <div class="big">📭</div>
            <p style="font-size:0.95rem;">Žádné leady v této záložce.</p>
        </div>
    <?php } else { ?>
        <?php foreach ($leads as $l) {
            $cardClass = 'pn-lead-card pn-lead-card--' . $tab;
            $stav = (string) $l['contact_stav'];

            $statusLabel = match ($stav) {
                'READY'      => '⏳ Čeká na volání',
                'CALLBACK'   => '📅 Callback ' . (!empty($l['callback_at']) ? date('j.n.Y H:i', strtotime((string) $l['callback_at'])) : ''),
                'NEDOVOLANO' => '📵 Nedovoláno (' . (int) ($l['nedovolano_count'] ?? 0) . '×)',
                'CALLED_OK', 'FOR_SALES' => '✅ Předáno OZ',
                'NEZAJEM'    => '😐 Nezájem',
                'CALLED_BAD' => '⛔ Bad call',
                default      => $stav,
            };
            $statusBg = match ($stav) {
                'READY'      => '#ede9fe; color:#5b21b6',
                'CALLBACK'   => '#dbeafe; color:#1e40af',
                'NEDOVOLANO' => '#fef3c7; color:#92400e',
                'CALLED_OK', 'FOR_SALES' => '#d1fae5; color:#065f46',
                'NEZAJEM',    'CALLED_BAD' => '#fee2e2; color:#991b1b',
                default      => '#e5e7eb; color:#374151',
            };
        ?>
            <div class="<?= crm_h($cardClass) ?>">
                <div class="lead-row1">
                    <span class="firm"><?= crm_h((string) $l['firma']) ?></span>
                    <a class="phone" href="tel:<?= crm_h((string)($l['telefon'] ?? '')) ?>">
                        📞 <?= crm_h((string)($l['telefon'] ?? '—')) ?>
                    </a>
                    <span class="badge-status" style="background:<?= $statusBg ?>;">
                        <?= crm_h($statusLabel) ?>
                    </span>
                </div>

                <div class="lead-row-meta">
                    <span>📍 <?= crm_h(crm_region_label((string) $l['region'])) ?></span>
                    <span>📡 <?= crm_h((string)($l['operator'] ?? '—')) ?></span>
                    <?php if (!empty($l['ico'])) { ?>
                        <span>🏢 IČO <strong><?= crm_h((string) $l['ico']) ?></strong></span>
                    <?php } ?>
                    <?php if (!empty($l['adresa'])) { ?>
                        <span>📍 <?= crm_h((string) $l['adresa']) ?></span>
                    <?php } ?>
                    <?php if (!empty($l['email'])) { ?>
                        <span>✉ <?= crm_h((string) $l['email']) ?></span>
                    <?php } ?>
                    <?php if (!empty($l['datum_volani']) && in_array($tab, ['navolane','prohra','nedovolano'], true)) { ?>
                        <span>🕒 Posl. volání: <?= crm_h(date('j.n.Y H:i', strtotime((string) $l['datum_volani']))) ?></span>
                    <?php } ?>
                </div>

                <?php if (!empty($l['prilez'])) { ?>
                    <div class="lead-prilez">
                        💡 <strong>Příležitost:</strong> <?= crm_h((string) $l['prilez']) ?>
                    </div>
                <?php } ?>

                <?php if ($isCallable) { ?>
                    <form method="post" action="<?= crm_h(crm_url('/caller/premium/status')) ?>">
                        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                        <input type="hidden" name="pool_id" value="<?= (int) $l['pool_id'] ?>">
                        <input type="hidden" name="contact_id" value="<?= (int) $l['contact_id'] ?>">
                        <input type="hidden" name="return_url" value="<?= crm_h('/caller/premium/order?id=' . (int) $order['id'] . '&tab=' . $tab) ?>">

                        <input type="text" name="poznamka" class="note-input"
                               placeholder="Poznámka (povinná u nezájem / nedovoláno / bad call)…"
                               maxlength="500">

                        <div class="lead-actions">
                            <button type="submit" name="action" value="success" class="btn-action btn-success">
                                ✅ Úspěšně
                            </button>
                            <button type="submit" name="action" value="callback" class="btn-action btn-callback">
                                📅 Callback
                            </button>
                            <input type="datetime-local" name="callback_at" class="callback-input"
                                   title="Datum a čas callbacku">
                            <button type="submit" name="action" value="nezajem" class="btn-action btn-warning">
                                😐 Nezájem
                            </button>
                            <button type="submit" name="action" value="nedovolano" class="btn-action btn-warning">
                                📵 Nedovoláno
                            </button>
                            <button type="submit" name="action" value="called_bad" class="btn-action btn-danger">
                                ⛔ Bad call
                            </button>
                        </div>
                    </form>
                <?php } else { ?>
                    <div style="font-size:0.78rem; color:var(--color-text-muted); margin-top:0.4rem;">
                        <?php if (!empty($l['caller_name'])) { ?>
                            Volala: <strong><?= crm_h((string) $l['caller_name']) ?></strong>
                        <?php } ?>
                        <?php if (!empty($l['called_at'])) { ?>
                            · <?= crm_h(date('j.n.Y H:i', strtotime((string) $l['called_at']))) ?>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    <?php } ?>
</section>
