<?php
// e:\Snecinatripu\app\views\cisticka\premium\index.php
declare(strict_types=1);
/** @var array<string,mixed>             $user */
/** @var string                          $csrf */
/** @var ?string                         $flash */
/** @var list<array<string,mixed>>       $orders   otevřené objednávky s pending leady */
/** @var array<string,int|float>         $todayStats  ['tradeable_today','non_tradeable_today','total_today'] */
/** @var float                           $monthEarned */
/** @var list<array<string,mixed>>       $closedOrders  uzavřené/zrušené objednávky kde čistička dělala */

$_czechMonth = static fn(int $m): string => [
    1=>'Leden',2=>'Únor',3=>'Březen',4=>'Duben',5=>'Květen',6=>'Červen',
    7=>'Červenec',8=>'Srpen',9=>'Září',10=>'Říjen',11=>'Listopad',12=>'Prosinec'
][$m] ?? (string)$m;
?>

<style>
.pc-header h1 { margin: 0 0 0.4rem; font-size: 1.4rem; }
.pc-header .lead {
    color: var(--color-text-muted);
    font-size: 0.85rem;
    margin-bottom: 1.2rem;
    line-height: 1.5;
    max-width: 720px;
}

.pc-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.6rem;
    margin-bottom: 1.2rem;
}
.pc-stat {
    background: linear-gradient(135deg,#7e3ff2 0%,#a056ff 100%);
    color: #fff;
    border-radius: 6px;
    padding: 0.85rem 1rem;
    box-shadow: 0 2px 6px rgba(126,63,242,0.2);
}
.pc-stat .label {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    opacity: 0.85;
}
.pc-stat .value {
    font-size: 1.55rem;
    font-weight: 700;
    margin-top: 0.15rem;
}
.pc-stat--alt {
    background: #fff;
    color: var(--color-text);
    border: 1px solid var(--color-border-strong);
    box-shadow: none;
}
.pc-stat--alt .value { color: #7e3ff2; }

.pc-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border: 1px solid var(--color-border-strong);
    border-radius: 6px;
    overflow: hidden;
    font-size: 0.9rem;
}
.pc-table th, .pc-table td {
    padding: 0.6rem 0.8rem;
    text-align: left;
    border-bottom: 1px solid var(--color-border);
    vertical-align: middle;
}
.pc-table thead { background: #f5f0fc; }
.pc-table tbody tr:hover { background: #faf8fd; }

.pc-badge-pending {
    background: #ffe6c7;
    color: #8b4500;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.78rem;
    font-weight: 700;
}

.pc-cta-accept {
    background: linear-gradient(135deg,#7e3ff2 0%,#a056ff 100%);
    color: #fff;
    border: none;
    border-radius: 5px;
    padding: 0.4rem 0.95rem;
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}
.pc-cta-accept:hover { filter: brightness(1.07); }

.pc-empty {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--color-text-muted);
}
.pc-empty .big { font-size: 3rem; margin-bottom: 0.5rem; }

.pc-meta { font-size: 0.75rem; color: var(--color-text-muted); }
.pc-meta strong { color: var(--color-text); }
</style>

<section class="card">
    <div class="pc-header">
        <h1>💎 Pracovní plocha 2 — Premium</h1>
        <p class="lead">
            Druhé čištění už jednou pročištěných leadů na <strong>objednávku obchoďáků</strong>.
            U každého leadu označíš <strong>obchodovatelný</strong> (✅ jde dál do volání)
            nebo <strong>neobchodovatelný</strong> (❌ vrátí se do běžného poolu).
            OZ ti zaplatí podle ceny, kterou si u objednávky stanovili.
            Faktury si stáhneš dole u jednotlivých <strong>Hotových objednávek</strong>.
        </p>
    </div>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info" style="margin-bottom:1rem;"><?= crm_h($flash) ?></p>
    <?php } ?>

    <!-- Statistika dnešní práce + měsíční výdělek -->
    <div class="pc-stats">
        <div class="pc-stat">
            <div class="label">Dnes vyčištěno</div>
            <div class="value"><?= (int) ($todayStats['total_today'] ?? 0) ?></div>
        </div>
        <div class="pc-stat pc-stat--alt">
            <div class="label">✅ Obchodovatelné dnes</div>
            <div class="value"><?= (int) ($todayStats['tradeable_today'] ?? 0) ?></div>
        </div>
        <div class="pc-stat pc-stat--alt">
            <div class="label">❌ Neobchodovatelné dnes</div>
            <div class="value"><?= (int) ($todayStats['non_tradeable_today'] ?? 0) ?></div>
        </div>
        <div class="pc-stat">
            <div class="label">💰 Tento měsíc na premium</div>
            <div class="value"><?= number_format($monthEarned, 2, ',', ' ') ?> Kč</div>
        </div>
    </div>

    <?php if ($orders === []) { ?>
        <div class="pc-empty">
            <div class="big">💎</div>
            <p style="font-size:1.05rem;">Momentálně žádné otevřené premium objednávky.</p>
            <p style="font-size:0.85rem;">
                Až nějaký OZ objedná druhé čištění, objeví se zde s tlačítkem
                „Přijmout objednávku".
            </p>
        </div>
    <?php } else { ?>
        <table class="pc-table">
            <thead>
                <tr>
                    <th>Obchoďák / objednávka</th>
                    <th>Pending</th>
                    <th>Cena za lead</th>
                    <th>Pokrok</th>
                    <th>Pro koho volat</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $o) {
                $orderId    = (int) $o['order_id'];
                $pending    = (int) ($o['pending_count'] ?? 0);
                $tradeable  = (int) ($o['tradeable_count'] ?? 0);
                $nontrad    = (int) ($o['non_tradeable_count'] ?? 0);
                $reserved   = (int) $o['reserved_count'];
                $requested  = (int) $o['requested_count'];
                $done       = $tradeable + $nontrad;
                $price      = (float) $o['price_per_lead'];
                $regions    = (string) ($o['regions_json'] ?? '');
                $regionsArr = $regions !== '' ? (json_decode($regions, true) ?: []) : [];
            ?>
                <tr>
                    <td>
                        <strong><?= crm_h((string) $o['oz_name']) ?></strong>
                        <div class="pc-meta">
                            #<?= $orderId ?> ·
                            <?= crm_h($_czechMonth((int)$o['month'])) ?> <?= (int) $o['year'] ?> ·
                            <?= crm_h(date('j.n.Y', strtotime((string) $o['created_at']))) ?>
                        </div>
                        <?php if ($regionsArr !== []) { ?>
                            <div class="pc-meta">
                                Kraje: <strong><?= crm_h(implode(', ', array_map('crm_region_label', $regionsArr))) ?></strong>
                            </div>
                        <?php } ?>
                        <?php if (!empty($o['note'])) { ?>
                            <div class="pc-meta">📝 <?= crm_h((string) $o['note']) ?></div>
                        <?php } ?>
                    </td>
                    <td>
                        <span class="pc-badge-pending"><?= $pending ?></span>
                    </td>
                    <td>
                        <strong><?= number_format($price, 2, ',', ' ') ?> Kč</strong>
                        <div class="pc-meta">za vyčištěný lead</div>
                    </td>
                    <td>
                        <strong><?= $done ?>/<?= $requested ?></strong>
                        <div class="pc-meta">
                            <?php if ($tradeable > 0) { ?>✅ <?= $tradeable ?> &nbsp;<?php } ?>
                            <?php if ($nontrad > 0) { ?>❌ <?= $nontrad ?><?php } ?>
                        </div>
                        <?php if ($reserved < $requested) { ?>
                            <div class="pc-meta" style="color:#a06800;">
                                🔄 Doplňuje se <?= $reserved ?>/<?= $requested ?>
                            </div>
                        <?php } ?>
                    </td>
                    <td>
                        <?php if (!empty($o['preferred_caller_name'])) { ?>
                            <strong><?= crm_h((string) $o['preferred_caller_name']) ?></strong>
                        <?php } else { ?>
                            <em style="color:var(--color-text-muted);">rotace mezi všemi</em>
                        <?php } ?>
                    </td>
                    <td>
                        <?php
                        $acceptedBy = (int) ($o['accepted_by_cleaner_id'] ?? 0);
                        $myOwn      = $acceptedBy > 0 && $acceptedBy === (int) ($user['id'] ?? 0);
                        $isOpen     = $acceptedBy === 0;
                        $myUserId   = (int) ($user['id'] ?? 0);
                        $isCisticka = ((string) ($user['role'] ?? '')) === 'cisticka';
                        ?>
                        <?php if ($isOpen && $isCisticka) { ?>
                            <!-- Otevřená pro všechny + jsem čistička — POST tlačítko Přijmu -->
                            <form method="post" action="<?= crm_h(crm_url('/cisticka/premium/accept')) ?>" style="margin:0;">
                                <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                                <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                <button type="submit" class="pc-cta-accept">
                                    ✓ Přijmu objednávku
                                </button>
                            </form>
                        <?php } else if ($isOpen && !$isCisticka) { ?>
                            <!-- Otevřená objednávka ale já jsem admin/majitel — jen náhled, žádný claim -->
                            <a href="<?= crm_h(crm_url('/cisticka/premium/order?id=' . $orderId)) ?>"
                               style="background:#fff; border:1px solid var(--color-border-strong); color:var(--color-text); padding:0.4rem 0.85rem; border-radius:5px; text-decoration:none; font-size:0.78rem; font-weight:600;">
                                👁 Náhled
                            </a>
                            <div style="font-size:0.7rem; color:var(--color-text-muted); margin-top:3px;">
                                jen prohlížíš (admin)
                            </div>
                        <?php } else if ($myOwn) { ?>
                            <!-- Moje přijatá — pokračuj v práci -->
                            <a href="<?= crm_h(crm_url('/cisticka/premium/order?id=' . $orderId)) ?>"
                               class="pc-cta-accept">
                                📂 Pokračovat
                            </a>
                            <div style="font-size:0.7rem; color:var(--color-text-muted); margin-top:3px;">
                                přijato <?= !empty($o['accepted_at']) ? crm_h(date('j.n. H:i', strtotime((string) $o['accepted_at']))) : '' ?>
                            </div>
                        <?php } else { ?>
                            <!-- Přijatá kým jiným (jen majitel/superadmin to vidí, normální čistička ji v listu nemá) -->
                            <a href="<?= crm_h(crm_url('/cisticka/premium/order?id=' . $orderId)) ?>"
                               style="background:#fff; border:1px solid var(--color-border); color:var(--color-text-muted); padding:0.4rem 0.8rem; border-radius:4px; text-decoration:none; font-size:0.78rem;">
                                👁 Detail
                            </a>
                            <div style="font-size:0.7rem; color:var(--color-text-muted); margin-top:3px;">
                                pracuje: <strong><?= crm_h((string) ($o['accepted_by_name'] ?? '')) ?></strong>
                            </div>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</section>

<!-- Hotové objednávky (closed/cancelled) — pohled "Co jsem dotahla, kdo už zaplatil" -->
<?php if (!empty($closedOrders)) { ?>
<section class="card" style="margin-top: 1.5rem;">
    <h2 style="margin: 0 0 0.6rem; font-size: 1.15rem;">🏁 Hotové objednávky</h2>
    <p class="lead" style="margin-bottom: 1rem;">
        Objednávky, které OZ uzavřel nebo zrušil. Tady vidíš <strong>kolik dostaneš</strong>
        a jestli ti už OZ <strong>označil platbu jako odeslanou</strong>.
    </p>

    <table class="pc-table">
        <thead>
            <tr>
                <th>Objednávka / OZ</th>
                <th>Vyčištěno</th>
                <th>Cena za lead</th>
                <th>Tvá výplata</th>
                <th>Stav platby</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($closedOrders as $co) {
            $oid       = (int) $co['order_id'];
            $myDone    = (int) ($co['my_done'] ?? 0);
            $myPayable = (int) ($co['my_payable'] ?? 0);
            $myRefund  = (int) ($co['my_refund'] ?? 0);
            $price     = (float) $co['price_per_lead'];
            $myEarn    = $myPayable * $price;
            $status    = (string) $co['order_status'];
            $paidAt    = (string) ($co['paid_to_cleaner_at'] ?? '');
            $isPaid    = $paidAt !== '';
            $statusLabel = $status === 'closed' ? '🏁 Uzavřená' : '✖ Zrušená';
        ?>
            <tr>
                <td>
                    <strong>#<?= $oid ?></strong>
                    <span style="font-size:0.7rem; color:var(--color-text-muted); margin-left:4px;">
                        <?= crm_h($statusLabel) ?>
                    </span>
                    <div class="pc-meta">
                        OZ: <strong><?= crm_h((string) $co['oz_name']) ?></strong>
                    </div>
                    <div class="pc-meta">
                        Období: <?= crm_h(['','Leden','Únor','Březen','Duben','Květen','Červen','Červenec','Srpen','Září','Říjen','Listopad','Prosinec'][(int)$co['month']] ?? '') ?>
                        <?= (int) $co['year'] ?>
                    </div>
                </td>
                <td>
                    <strong><?= $myDone ?></strong> ks
                    <?php if ($myRefund > 0) { ?>
                        <div class="pc-meta" style="color:#dc2626;">⚠ Reklamace: <?= $myRefund ?></div>
                    <?php } ?>
                </td>
                <td>
                    <?= number_format($price, 2, ',', ' ') ?> Kč
                </td>
                <td>
                    <strong style="color:#7e3ff2;">
                        <?= number_format($myEarn, 2, ',', ' ') ?> Kč
                    </strong>
                    <div class="pc-meta">za <?= $myPayable ?> placených</div>
                </td>
                <td>
                    <?php if ($isPaid) { ?>
                        <span style="background:#d1fae5; color:#065f46; padding:3px 9px; border-radius:10px; font-weight:700; font-size:0.78rem;">
                            ✅ Zaplaceno <?= crm_h(date('j.n.Y', strtotime($paidAt))) ?>
                        </span>
                    <?php } else { ?>
                        <span style="background:#fef3c7; color:#92400e; padding:3px 9px; border-radius:10px; font-weight:700; font-size:0.78rem;">
                            ○ Čeká na platbu
                        </span>
                    <?php } ?>
                </td>
                <td>
                    <a href="<?= crm_h(crm_url('/cisticka/premium/payout/print?order_id=' . $oid)) ?>"
                       target="_blank"
                       style="background:#fff; border:1px solid var(--color-border-strong); color:var(--color-text); padding:0.35rem 0.75rem; border-radius:5px; text-decoration:none; font-size:0.78rem; font-weight:600;"
                       title="Faktura jen za tuto objednávku">
                        🖨 Faktura
                    </a>
                </td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
</section>
<?php } ?>
