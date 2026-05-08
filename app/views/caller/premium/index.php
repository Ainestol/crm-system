<?php
// e:\Snecinatripu\app\views\caller\premium\index.php
declare(strict_types=1);
/** @var array<string,mixed>             $user */
/** @var string                          $csrf */
/** @var ?string                         $flash */
/** @var string                          $tab          aktivní tab */
/** @var array<string,int>               $tabCounts    badge counts */
/** @var list<array<string,mixed>>       $orders       jen pro tab='objednavky' */
/** @var list<array<string,mixed>>       $leads        flat list pro state taby */
/** @var float                           $monthBonus */
/** @var int                             $monthBonusCount */
/** @var int                             $todayWins */

$_callerId = (int) ($user['id'] ?? 0);

$_czechMonth = static fn(int $m): string => [
    1=>'Leden',2=>'Únor',3=>'Březen',4=>'Duben',5=>'Květen',6=>'Červen',
    7=>'Červenec',8=>'Srpen',9=>'Září',10=>'Říjen',11=>'Listopad',12=>'Prosinec'
][$m] ?? (string)$m;

$_tabsList = [
    'objednavky' => ['label' => '📋 Objednávky',   'count' => $tabCounts['objednavky'] ?? 0],
    'k_volani'   => ['label' => '⏳ K volání',     'count' => $tabCounts['k_volani']   ?? 0],
    'callbacky'  => ['label' => '📅 Callbacky',    'count' => $tabCounts['callbacky']  ?? 0],
    'nedovolano' => ['label' => '📵 Nedovoláno',   'count' => $tabCounts['nedovolano'] ?? 0],
    'navolane'   => ['label' => '✅ Navolané',     'count' => $tabCounts['navolane']   ?? 0],
    'prohra'     => ['label' => '❌ Prohra',       'count' => $tabCounts['prohra']     ?? 0],
];

// helper — generate URL for tab switch
$_tabUrl = static fn(string $t): string => crm_url('/caller/premium' . ($t === 'objednavky' ? '' : '?tab=' . $t));
?>

<style>
.pn-header h1 { margin: 0 0 0.4rem; font-size: 1.4rem; }
.pn-header .lead {
    color: var(--color-text-muted);
    font-size: 0.85rem;
    margin-bottom: 1rem;
    line-height: 1.5;
    max-width: 720px;
}

.pn-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.6rem;
    margin-bottom: 1rem;
}
.pn-stat {
    background: linear-gradient(135deg,#7e3ff2 0%,#a056ff 100%);
    color: #fff;
    border-radius: 6px;
    padding: 0.7rem 1rem;
    box-shadow: 0 2px 6px rgba(126,63,242,0.2);
}
.pn-stat .label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.85; }
.pn-stat .value { font-size: 1.4rem; font-weight: 700; margin-top: 0.1rem; }
.pn-stat--alt {
    background: #fff;
    color: var(--color-text);
    border: 1px solid var(--color-border-strong);
    box-shadow: none;
}
.pn-stat--alt .value { color: #7e3ff2; }

/* Taby ve stylu /caller */
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

/* === OBJEDNÁVKY tab === */
.pn-order-card {
    background: #fff; border: 1px solid var(--color-border-strong);
    border-radius: 8px; padding: 0.9rem 1.1rem; margin-bottom: 0.6rem;
    display: grid; grid-template-columns: 1fr auto;
    gap: 0.8rem; align-items: center;
}
.pn-order-card--mine { border-left: 4px solid #7e3ff2; }
.pn-order-card--rotation { border-left: 4px solid #d97706; }
.pn-order-info { display: flex; flex-direction: column; gap: 0.25rem; }
.pn-order-info .top { display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: baseline; font-size: 0.95rem; }
.pn-order-info .top .oz { font-weight: 700; color: #4a2480; }
.pn-order-info .top .meta { color: var(--color-text-muted); font-size: 0.78rem; }
.pn-order-info .bonus-bar {
    display: inline-flex; align-items: center; gap: 0.4rem;
    background: linear-gradient(135deg,#fff5e6 0%,#ffe8c2 100%);
    border: 1px solid #ffd699; border-radius: 16px;
    padding: 3px 12px; font-size: 0.82rem; font-weight: 700;
    color: #92400e; width: fit-content;
}
.pn-order-info .no-bonus { color: var(--color-text-muted); font-size: 0.78rem; font-style: italic; }
.pn-order-info .stats { display: flex; gap: 0.8rem; flex-wrap: wrap; font-size: 0.78rem; color: var(--color-text-muted); }
.pn-order-info .stats strong { color: var(--color-text); }
.pn-tag {
    display: inline-block; font-size: 0.7rem; font-weight: 700;
    padding: 1px 7px; border-radius: 8px; text-transform: uppercase;
    letter-spacing: 0.05em;
}
.pn-tag-mine     { background: #7e3ff2; color: #fff; }
.pn-tag-rotation { background: #d97706; color: #fff; }
.pn-tag-closed   { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }
.pn-cta {
    background: linear-gradient(135deg,#7e3ff2 0%,#a056ff 100%);
    color: #fff; border: none; border-radius: 5px;
    padding: 0.55rem 1.1rem; font-size: 0.88rem; font-weight: 700;
    cursor: pointer; text-decoration: none;
    box-shadow: 0 2px 6px rgba(126,63,242,0.25);
}
.pn-cta:hover { filter: brightness(1.07); }

/* === STATE TABS — flat list of leads === */
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
.lead-from-order {
    font-size: 0.75rem; color: var(--color-text-muted); margin-bottom: 0.4rem;
}
.lead-from-order strong { color: #4a2480; }
.lead-from-order .bonus-pill {
    display: inline-block; background: #ffe8c2; color: #92400e;
    font-weight: 700; padding: 1px 7px; border-radius: 8px;
    margin-left: 4px;
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
    <div class="pn-header">
        <h1>💎 Premium navolávky</h1>
        <p class="lead">
            Premium objednávky od OZ — leady prošlé druhým čištěním. Můžeš dostat <strong>extra bonus</strong>
            za úspěšný hovor (od OZ, navíc k tvojí standardní mzdě). Standardní pracovní plocha v menu vlevo
            zůstává nezměněná pro běžné kontakty. Faktura za každou objednávku je dostupná u jednotlivých
            položek níže.
        </p>
    </div>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info" style="margin-bottom:1rem;"><?= crm_h($flash) ?></p>
    <?php } ?>

    <div class="pn-stats">
        <div class="pn-stat">
            <div class="label">Dnes úspěšně premium</div>
            <div class="value"><?= (int) $todayWins ?></div>
        </div>
        <div class="pn-stat pn-stat--alt">
            <div class="label">Tento měsíc úspěšně</div>
            <div class="value"><?= (int) $monthBonusCount ?></div>
        </div>
        <div class="pn-stat">
            <div class="label">💰 Bonus tento měsíc</div>
            <div class="value"><?= number_format($monthBonus, 2, ',', ' ') ?> Kč</div>
        </div>
    </div>

    <!-- Taby (jako /caller) -->
    <div class="pn-tabs">
        <?php foreach ($_tabsList as $tabKey => $tabInfo) { ?>
            <a href="<?= crm_h($_tabUrl($tabKey)) ?>"
               class="pn-tab<?= $tab === $tabKey ? ' is-active' : '' ?>">
                <?= crm_h($tabInfo['label']) ?>
                <span class="badge"><?= (int) $tabInfo['count'] ?></span>
            </a>
        <?php } ?>
    </div>

    <?php if ($tab === 'objednavky') { ?>
        <!-- ════════════ OBJEDNÁVKY tab ════════════ -->
        <?php if ($orders === []) { ?>
            <div class="pn-empty">
                <div class="big">💎</div>
                <p style="font-size:1rem;">Žádné premium objednávky pro tebe.</p>
                <p style="font-size:0.85rem;">
                    Až nějaký OZ objedná premium druhé čištění a buď tě konkrétně přidělí
                    nebo nechá rotaci, objednávka se objeví zde.
                </p>
            </div>
        <?php } else { ?>
            <?php foreach ($orders as $o) {
                $isMine    = (int) ($o['preferred_caller_id'] ?? 0) === $_callerId;
                $isShared  = $o['preferred_caller_id'] === null;
                $bonus     = (float) $o['caller_bonus_per_lead'];
                $callable  = (int) ($o['callable_count'] ?? 0);
                $myDone    = (int) ($o['my_done'] ?? 0);
                $regions   = (string) ($o['regions_json'] ?? '');
                $regionsArr= $regions !== '' ? (json_decode($regions, true) ?: []) : [];
                $cardClass = $isMine ? 'pn-order-card--mine' : ($isShared ? 'pn-order-card--rotation' : '');
            ?>
                <div class="pn-order-card <?= $cardClass ?>">
                    <div class="pn-order-info">
                        <div class="top">
                            <span class="oz">👔 <?= crm_h((string) $o['oz_name']) ?></span>
                            <?php if ($isMine) { ?>
                                <span class="pn-tag pn-tag-mine">Pro tebe</span>
                            <?php } elseif ($isShared) { ?>
                                <span class="pn-tag pn-tag-rotation">Rotace</span>
                            <?php } ?>
                            <?php if ((string) ($o['order_status'] ?? '') === 'closed') { ?>
                                <span class="pn-tag pn-tag-closed" title="OZ objednávku uzavřel — už se nečistí, ale tradeable leady ještě navolej">
                                    🏁 Uzavřená OZ
                                </span>
                            <?php } ?>
                            <span class="meta">
                                #<?= (int) $o['order_id'] ?> ·
                                <?= crm_h($_czechMonth((int)$o['month'])) ?> <?= (int) $o['year'] ?>
                            </span>
                        </div>

                        <?php if ($bonus > 0) { ?>
                            <div class="bonus-bar">
                                💰 +<?= number_format($bonus, 2, ',', ' ') ?> Kč bonus za každý úspěšný hovor
                            </div>
                        <?php } else { ?>
                            <div class="no-bonus">📞 Bez extra bonusu (jen tvoje standardní sazba)</div>
                        <?php } ?>

                        <div class="stats">
                            <span>📞 K volání: <strong><?= $callable ?></strong></span>
                            <?php if ($myDone > 0) { ?>
                                <span>✅ Tvoje úspěšné: <strong><?= $myDone ?></strong></span>
                            <?php } ?>
                            <?php if ($regionsArr !== []) { ?>
                                <span>📍 <?= crm_h(implode(', ', array_map('crm_region_label', $regionsArr))) ?></span>
                            <?php } ?>
                        </div>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:0.4rem; align-items:stretch;">
                        <a href="<?= crm_h(crm_url('/caller/premium/order?id=' . (int) $o['order_id'])) ?>" class="pn-cta">
                            📞 Otevřít objednávku
                        </a>
                        <?php if ((int) ($o['my_done'] ?? 0) > 0) { ?>
                            <a href="<?= crm_h(crm_url('/caller/premium/payout/print?order_id=' . (int) $o['order_id'])) ?>"
                               target="_blank"
                               style="background:#fff; color:var(--color-text); border:1px solid var(--color-border-strong); padding:0.4rem 0.9rem; border-radius:5px; text-decoration:none; font-size:0.78rem; font-weight:600; text-align:center;"
                               title="Faktura za moje úspěšně navolané z této objednávky">
                                🖨 Faktura
                            </a>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        <?php } ?>

    <?php } else {
        /* ════════════ STATE TABS — flat list ════════════ */
        $isCallable = in_array($tab, ['k_volani', 'callbacky', 'nedovolano'], true);
        if ($leads === []) { ?>
            <div class="pn-empty">
                <div class="big">📭</div>
                <p style="font-size:0.95rem;">Žádné leady v této záložce.</p>
            </div>
        <?php } else { ?>
            <?php foreach ($leads as $l) {
                $bonus      = (float) $l['caller_bonus_per_lead'];
                $cardClass  = 'pn-lead-card pn-lead-card--' . $tab;
                $stav       = (string) $l['contact_stav'];

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

                    <div class="lead-from-order">
                        Z objednávky <strong>#<?= (int) $l['order_id'] ?></strong>
                        — OZ <strong>👔 <?= crm_h((string) $l['oz_name']) ?></strong>
                        <?php if ((string) ($l['order_status'] ?? '') === 'closed') { ?>
                            <span class="pn-tag pn-tag-closed" style="margin-left:4px;">🏁 Uzavřená OZ</span>
                        <?php } ?>
                        <?php if ($bonus > 0) { ?>
                            <span class="bonus-pill">💰 +<?= number_format($bonus, 2, ',', ' ') ?> Kč bonus</span>
                        <?php } ?>
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
                            <input type="hidden" name="return_url" value="<?= crm_h('/caller/premium?tab=' . $tab) ?>">

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
    <?php } ?>
</section>
