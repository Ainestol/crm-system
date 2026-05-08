<?php
// e:\Snecinatripu\app\views\oz\premium\index.php
declare(strict_types=1);
/** @var array<string,mixed>             $user */
/** @var string                          $csrf */
/** @var ?string                         $flash */
/** @var list<array<string,mixed>>       $orders */

$_statusLabels = [
    'open'      => '🟢 Otevřená',
    'cancelled' => '✖ Zrušená',
    'closed'    => '✓ Uzavřená',
];

$_czechMonth = static fn(int $m): string => [
    1=>'Leden',2=>'Únor',3=>'Březen',4=>'Duben',5=>'Květen',6=>'Červen',
    7=>'Červenec',8=>'Srpen',9=>'Září',10=>'Říjen',11=>'Listopad',12=>'Prosinec'
][$m] ?? (string)$m;
?>

<style>
.po-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}
.po-header h1 { margin: 0; font-size: 1.4rem; }
.po-cta {
    background: linear-gradient(135deg,#7e3ff2 0%,#a056ff 100%);
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 0.7rem 1.4rem;
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(126,63,242,0.3);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}
.po-cta:hover { filter: brightness(1.07); transform: translateY(-1px); transition: 0.15s; }

.po-empty {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--color-text-muted);
}
.po-empty .big { font-size: 3rem; margin-bottom: 0.5rem; }

.po-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border: 1px solid var(--color-border-strong);
    border-radius: 6px;
    overflow: hidden;
    font-size: 0.85rem;
}
.po-table th, .po-table td {
    padding: 0.5rem 0.7rem;
    text-align: left;
    border-bottom: 1px solid var(--color-border);
    vertical-align: top;
}
.po-table thead {
    background: #f8f6fb;
}
.po-table tbody tr:hover { background: #fafafa; }

.po-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.72rem;
    font-weight: 600;
}
.po-b-open      { background: #dcf2dd; color: #1d6e2c; }
.po-b-cancelled { background: #f4dada; color: #842424; }
.po-b-closed    { background: #e0e0e0; color: #555; }

.po-progress {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 140px;
}
.po-bar {
    height: 6px;
    background: #eee;
    border-radius: 3px;
    overflow: hidden;
}
.po-bar > span { display: block; height: 100%; background: #7e3ff2; }
.po-stats {
    font-size: 0.7rem;
    color: var(--color-text-muted);
    line-height: 1.4;
}
.po-stats strong { color: var(--color-text); }

.po-cancel-btn {
    background: transparent;
    border: 1px solid #c44;
    color: #c44;
    border-radius: 4px;
    padding: 0.25rem 0.55rem;
    font-size: 0.75rem;
    cursor: pointer;
}
.po-cancel-btn:hover { background: #fce6e6; }
.po-close-btn {
    background: #2e7d32;
    border: 1px solid #2e7d32;
    color: #fff;
    border-radius: 4px;
    padding: 0.25rem 0.55rem;
    font-size: 0.75rem;
    cursor: pointer;
    margin-bottom: 4px;
    width: 100%;
}
.po-close-btn:hover { background: #1b5e20; }
.po-print-btn {
    background: #fff;
    border: 1px solid var(--color-border-strong);
    color: var(--color-text);
    border-radius: 4px;
    padding: 0.3rem 0.7rem;
    font-size: 0.78rem;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.po-print-btn:hover { background: #f0f0f0; }

.po-summary-cell { font-size: 0.78rem; }
.po-summary-cell .small { color: var(--color-text-muted); font-size: 0.72rem; }

/* Faktura blok pro tracking plateb */
.po-pay-blocks {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    min-width: 180px;
    font-size: 0.78rem;
}
.po-pay-block {
    border: 1px solid var(--color-border);
    border-radius: 5px;
    padding: 0.4rem 0.55rem;
    background: #fff;
}
.po-pay-block--paid {
    background: #e6f7e9;
    border-color: #b7e0bf;
}
.po-pay-block .who {
    font-weight: 600;
    color: var(--color-text);
    margin-bottom: 2px;
}
.po-pay-block .amount {
    font-weight: 700;
    color: #7e3ff2;
}
.po-pay-block.po-pay-block--paid .amount { color: #2e7d32; }
.po-pay-block .meta {
    font-size: 0.7rem;
    color: var(--color-text-muted);
    margin-top: 2px;
}
.po-pay-block .pay-btn {
    margin-top: 4px;
    background: #7e3ff2;
    color: #fff;
    border: none;
    border-radius: 4px;
    padding: 0.2rem 0.55rem;
    font-size: 0.7rem;
    font-weight: 600;
    cursor: pointer;
    width: 100%;
}
.po-pay-block .pay-btn:hover { filter: brightness(1.07); }
.po-pay-block.po-pay-block--paid .pay-btn {
    background: transparent;
    color: var(--color-text-muted);
    border: 1px solid var(--color-border);
}
.po-pay-block.po-pay-block--paid .pay-btn:hover { background: #f0f0f0; }
.po-pay-block .empty-bonus {
    color: var(--color-text-muted);
    font-style: italic;
    font-size: 0.72rem;
}
</style>

<section class="card">
    <div class="po-header">
        <h1>💎 Premium objednávky</h1>
        <div style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
            <a href="<?= crm_h(crm_url('/oz/premium/payout/print?year=' . date('Y') . '&month=' . date('n'))) ?>"
               target="_blank"
               style="background:#fff; border:1px solid var(--color-border-strong); color:var(--color-text); padding:0.55rem 1rem; border-radius:5px; text-decoration:none; font-size:0.85rem; font-weight:600;"
               title="Tisková sestava — kolik dlužím čističce za tento měsíc">
                🖨 Měsíční výplata
            </a>
            <a href="<?= crm_h(crm_url('/oz/premium/new')) ?>" class="po-cta">
                ➕ Nová objednávka
            </a>
        </div>
    </div>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info" style="margin-bottom:1rem;"><?= crm_h($flash) ?></p>
    <?php } ?>

    <?php if ($orders === []) { ?>
        <div class="po-empty">
            <div class="big">💎</div>
            <p style="font-size:1.05rem; margin-bottom: 0.4rem;">
                Zatím žádné premium objednávky.
            </p>
            <p style="font-size:0.85rem;">
                Klikni na <strong>Nová objednávka</strong> a vyber si balíček leadů,
                který chceš nechat čističce projít druhým, důkladnějším čištěním.
            </p>
        </div>
    <?php } else { ?>
        <table class="po-table">
            <thead>
                <tr>
                    <th>Období</th>
                    <th>Stav · Pokrok</th>
                    <th>💰 Čističce</th>
                    <th>💰 Navolávačce</th>
                    <th>Akce</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $o) {
                $reserved   = (int) $o['reserved_count'];
                $requested  = (int) $o['requested_count'];
                $tradeable  = (int) ($o['pool_tradeable'] ?? 0);
                $nontrad    = (int) ($o['pool_non_tradeable'] ?? 0);
                $pending    = (int) ($o['pool_pending'] ?? 0);
                $called     = (int) ($o['pool_called_success'] ?? 0);
                $cleaned    = $tradeable + $nontrad;
                $pct        = $requested > 0 ? min(100, round($cleaned * 100 / $requested)) : 0;

                $price      = (float) $o['price_per_lead'];
                $bonus      = (float) $o['caller_bonus_per_lead'];
                $status     = (string) $o['status'];
                $statusClass= 'po-b-' . $status;

                $callerName = (string) ($o['preferred_caller_name'] ?? '');
                $regionsRaw = (string) ($o['regions_json'] ?? '');
                $regionsArr = $regionsRaw !== '' ? (json_decode($regionsRaw, true) ?: []) : [];

                // Fakturace — kolik se platí čističce a navolávačce
                $payableCleaner = (int) ($o['pool_payable_to_cleaner'] ?? 0);
                $payableCaller  = (int) ($o['pool_payable_to_caller'] ?? 0);
                $dueCleaner     = $payableCleaner * $price;
                $dueCaller      = $payableCaller * $bonus;

                $paidCleanerAt  = (string) ($o['paid_to_cleaner_at'] ?? '');
                $paidCallerAt   = (string) ($o['paid_to_caller_at'] ?? '');
                $isPaidCleaner  = $paidCleanerAt !== '';
                $isPaidCaller   = $paidCallerAt !== '';
            ?>
                <tr>
                    <!-- Období + ID -->
                    <td>
                        <strong><?= crm_h($_czechMonth((int)$o['month'])) ?> <?= (int) $o['year'] ?></strong>
                        <div class="po-stats" style="margin-top:2px;">
                            #<?= (int) $o['id'] ?> · <?= crm_h(date('j.n.Y H:i', strtotime((string) $o['created_at']))) ?>
                        </div>
                        <?php if ($callerName !== '') { ?>
                            <div class="po-stats">📞 <?= crm_h($callerName) ?></div>
                        <?php } else { ?>
                            <div class="po-stats"><em>📞 rotace navolávaček</em></div>
                        <?php } ?>
                        <?php if ($regionsArr !== []) { ?>
                            <div class="po-stats small">Kraje: <?= crm_h(implode(', ', array_map('crm_region_label', $regionsArr))) ?></div>
                        <?php } ?>
                    </td>

                    <!-- Stav + pokrok -->
                    <td>
                        <span class="po-badge <?= crm_h($statusClass) ?>">
                            <?= crm_h($_statusLabels[$status] ?? $status) ?>
                        </span>
                        <div class="po-progress" style="margin-top:6px;">
                            <div class="po-bar"><span style="width: <?= $pct ?>%;"></span></div>
                            <div class="po-stats">
                                Vyčištěno <strong><?= $cleaned ?>/<?= $requested ?></strong> (<?= $pct ?>%)
                            </div>
                            <?php if ($pending > 0) { ?>
                                <div class="po-stats">⏳ Čeká: <?= $pending ?></div>
                            <?php } ?>
                            <?php if ($reserved < $requested && $status === 'open') { ?>
                                <div class="po-stats" style="color:#a06800;">
                                    🔄 Doplňuje se <?= $reserved ?>/<?= $requested ?>
                                </div>
                            <?php } ?>
                            <?php if ($tradeable > 0 || $nontrad > 0) { ?>
                                <div class="po-stats">
                                    <?php if ($tradeable > 0) { ?>✅ <?= $tradeable ?><?php } ?>
                                    <?php if ($nontrad > 0) { ?>&nbsp;&nbsp;❌ <?= $nontrad ?><?php } ?>
                                    <?php if ($called > 0) { ?>&nbsp;&nbsp;📞 <?= $called ?><?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                    </td>

                    <!-- 💰 Čističce -->
                    <td>
                        <div class="po-pay-block <?= $isPaidCleaner ? 'po-pay-block--paid' : '' ?>">
                            <div class="who">
                                <?= number_format($price, 2, ',', ' ') ?> Kč × <?= $payableCleaner ?>
                            </div>
                            <div class="amount"><?= number_format($dueCleaner, 2, ',', ' ') ?> Kč</div>
                            <?php if ($isPaidCleaner) { ?>
                                <div class="meta">
                                    ✅ Zaplaceno <?= crm_h(date('j.n.Y', strtotime($paidCleanerAt))) ?>
                                </div>
                                <form method="post" action="<?= crm_h(crm_url('/oz/premium/mark-paid')) ?>" style="margin:0;"
                                      onsubmit="return confirm('Vrátit na nezaplaceno?');">
                                    <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                                    <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                                    <input type="hidden" name="target" value="cleaner">
                                    <input type="hidden" name="paid" value="0">
                                    <button type="submit" class="pay-btn">↩ Vrátit</button>
                                </form>
                            <?php } else if ($payableCleaner > 0) { ?>
                                <div class="meta">○ Nezaplaceno</div>
                                <form method="post" action="<?= crm_h(crm_url('/oz/premium/mark-paid')) ?>" style="margin:0;"
                                      onsubmit="return confirm('Označit jako zaplaceno čističce?\n\nČástka: <?= number_format($dueCleaner, 2, ',', ' ') ?> Kč');">
                                    <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                                    <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                                    <input type="hidden" name="target" value="cleaner">
                                    <input type="hidden" name="paid" value="1">
                                    <button type="submit" class="pay-btn">✓ Zaplaceno</button>
                                </form>
                            <?php } else { ?>
                                <div class="meta"><em>zatím nic k zaplacení</em></div>
                            <?php } ?>
                        </div>
                    </td>

                    <!-- 💰 Navolávačce -->
                    <td>
                        <?php if ($bonus <= 0) { ?>
                            <div class="po-pay-block">
                                <div class="empty-bonus">Bez bonusu</div>
                                <div class="meta">Navolávačka má jen základní sazbu od majitele</div>
                            </div>
                        <?php } else { ?>
                            <div class="po-pay-block <?= $isPaidCaller ? 'po-pay-block--paid' : '' ?>">
                                <div class="who">
                                    <?= number_format($bonus, 2, ',', ' ') ?> Kč × <?= $payableCaller ?>
                                </div>
                                <div class="amount"><?= number_format($dueCaller, 2, ',', ' ') ?> Kč</div>
                                <?php if ($isPaidCaller) { ?>
                                    <div class="meta">
                                        ✅ Zaplaceno <?= crm_h(date('j.n.Y', strtotime($paidCallerAt))) ?>
                                    </div>
                                    <form method="post" action="<?= crm_h(crm_url('/oz/premium/mark-paid')) ?>" style="margin:0;"
                                          onsubmit="return confirm('Vrátit na nezaplaceno?');">
                                        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                                        <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                                        <input type="hidden" name="target" value="caller">
                                        <input type="hidden" name="paid" value="0">
                                        <button type="submit" class="pay-btn">↩ Vrátit</button>
                                    </form>
                                <?php } else if ($payableCaller > 0) { ?>
                                    <div class="meta">○ Nezaplaceno</div>
                                    <form method="post" action="<?= crm_h(crm_url('/oz/premium/mark-paid')) ?>" style="margin:0;"
                                          onsubmit="return confirm('Označit jako zaplaceno navolávačce?\n\nBonus: <?= number_format($dueCaller, 2, ',', ' ') ?> Kč');">
                                        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                                        <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                                        <input type="hidden" name="target" value="caller">
                                        <input type="hidden" name="paid" value="1">
                                        <button type="submit" class="pay-btn">✓ Zaplaceno</button>
                                    </form>
                                <?php } else { ?>
                                    <div class="meta"><em>zatím nic navoláno</em></div>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </td>

                    <!-- Akce -->
                    <td>
                        <?php if ($status === 'open') { ?>
                            <form method="post" action="<?= crm_h(crm_url('/oz/premium/close')) ?>"
                                  onsubmit="return confirm('Uzavřít objednávku #<?= (int)$o['id'] ?> jako dokončenou?\n\nVyčištěné leady ve faktuře zůstávají, nezpracované se uvolní zpátky do poolu.');"
                                  style="margin:0;">
                                <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                                <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                                <button type="submit" class="po-close-btn">🏁 Uzavřít</button>
                            </form>
                            <form method="post" action="<?= crm_h(crm_url('/oz/premium/cancel')) ?>"
                                  onsubmit="return confirm('Opravdu zrušit objednávku #<?= (int)$o['id'] ?>?\n\nUvolní se jen ty leady, které čistička ještě nezpracovala. Za vyčištěné stále platíte.');"
                                  style="margin:0;">
                                <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                                <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                                <button type="submit" class="po-cancel-btn">Zrušit</button>
                            </form>
                        <?php } ?>
                        <a href="<?= crm_h(crm_url('/oz/premium/payout/print?order_id=' . (int) $o['id'])) ?>"
                           class="po-print-btn"
                           style="margin-top:6px;"
                           target="_blank"
                           title="Tisková sestava">
                            🖨 Faktura
                        </a>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</section>
