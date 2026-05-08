<?php
// e:\Snecinatripu\app\views\admin\premium\overview.php
declare(strict_types=1);
/** @var array<string,mixed>           $user */
/** @var string                        $csrf */
/** @var ?string                       $flash */
/** @var list<array<string,mixed>>     $orders */
/** @var array<string,mixed>           $stats */
/** @var array<string,mixed>           $poolStats */
/** @var array<string,mixed>           $money */
/** @var list<array<string,mixed>>     $ozList */
/** @var string                        $statusFilter */
/** @var int                           $ozFilter */

$_czechMonth = static fn(int $m): string => [
    1=>'Leden',2=>'Únor',3=>'Březen',4=>'Duben',5=>'Květen',6=>'Červen',
    7=>'Červenec',8=>'Srpen',9=>'Září',10=>'Říjen',11=>'Listopad',12=>'Prosinec'
][$m] ?? (string)$m;

$_statusLabels = [
    'open'      => '🟢 Otevřená',
    'cancelled' => '✖ Zrušená',
    'closed'    => '🏁 Uzavřená',
];
?>

<style>
.apo-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.6rem;
    margin-bottom: 1.2rem;
}
.apo-stat {
    background: #fff;
    border: 1px solid var(--color-border-strong);
    border-left: 4px solid #7e3ff2;
    border-radius: 6px;
    padding: 0.7rem 1rem;
}
.apo-stat .label {
    font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em;
    color: var(--color-text-muted);
}
.apo-stat .value {
    font-size: 1.4rem; font-weight: 700; color: var(--color-text);
    margin-top: 0.1rem;
}
.apo-stat .sub {
    font-size: 0.72rem; color: var(--color-text-muted); margin-top: 0.2rem;
    line-height: 1.3;
}
.apo-stat--money {
    border-left-color: #d97706;
    background: linear-gradient(135deg,#fffbeb 0%, #fff5e6 100%);
}
.apo-stat--money .value { color: #92400e; }

.apo-filters {
    display: flex; gap: 0.6rem; margin-bottom: 1rem; flex-wrap: wrap;
    align-items: center;
}
.apo-filters select {
    background: #fff;
    border: 1px solid var(--color-border-strong);
    border-radius: 5px;
    padding: 0.4rem 0.7rem;
    font-size: 0.85rem;
}

.apo-table {
    width: 100%; border-collapse: collapse;
    background: #fff; border: 1px solid var(--color-border-strong);
    border-radius: 6px; overflow: hidden;
    font-size: 0.83rem;
}
.apo-table th, .apo-table td {
    padding: 0.5rem 0.7rem; text-align: left;
    border-bottom: 1px solid var(--color-border);
    vertical-align: top;
}
.apo-table thead { background: #f5f0fc; }
.apo-table tbody tr:hover { background: #faf8fd; }

.apo-badge {
    display: inline-block; padding: 2px 8px; border-radius: 10px;
    font-size: 0.72rem; font-weight: 600;
}
.apo-b-open      { background: #dcf2dd; color: #1d6e2c; }
.apo-b-cancelled { background: #f4dada; color: #842424; }
.apo-b-closed    { background: #e0e7ff; color: #3730a3; }

.apo-pay-pill {
    display: inline-block; padding: 2px 7px; border-radius: 8px;
    font-size: 0.7rem; font-weight: 700;
}
.apo-pay-paid   { background: #d1fae5; color: #065f46; }
.apo-pay-unpaid { background: #fef3c7; color: #92400e; }
.apo-pay-na     { background: #f3f4f6; color: #6b7280; }

.apo-funnel {
    font-size: 0.72rem; color: var(--color-text-muted);
    margin-top: 0.2rem; line-height: 1.4;
}
.apo-funnel strong { color: var(--color-text); }
.apo-funnel .ok { color: #16a34a; }
.apo-funnel .called { color: #7e3ff2; }
</style>

<section class="card">
    <h1 style="margin: 0 0 0.4rem; font-size: 1.4rem;">💎 Premium pipeline — admin přehled</h1>
    <p style="color: var(--color-text-muted); font-size: 0.85rem; margin-bottom: 1rem; max-width: 720px;">
        Globální pohled na všechny premium objednávky napříč OZ. Vidíš počty, postup čištění a navolávání,
        kolik komu kdo dluží a co se zaplatilo. Pro detail klikni na konkrétní objednávku.
    </p>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info" style="margin-bottom:1rem;"><?= crm_h($flash) ?></p>
    <?php } ?>

    <!-- Globální statistiky -->
    <div class="apo-stats">
        <div class="apo-stat">
            <div class="label">Objednávek celkem</div>
            <div class="value"><?= (int) ($stats['orders_total'] ?? 0) ?></div>
            <div class="sub">
                🟢 <?= (int) ($stats['orders_open'] ?? 0) ?> otevř.
                · 🏁 <?= (int) ($stats['orders_closed'] ?? 0) ?> uzavř.
                · ✖ <?= (int) ($stats['orders_cancelled'] ?? 0) ?> zruš.
            </div>
        </div>
        <div class="apo-stat">
            <div class="label">Leadů v pipeline</div>
            <div class="value"><?= (int) ($poolStats['pool_total'] ?? 0) ?></div>
            <div class="sub">
                ⏳ <?= (int) ($poolStats['cleaning_pending'] ?? 0) ?> čeká
                · ✅ <?= (int) ($poolStats['cleaning_tradeable'] ?? 0) ?> obchod.
                · ❌ <?= (int) ($poolStats['cleaning_non_tradeable'] ?? 0) ?> neobch.
            </div>
        </div>
        <div class="apo-stat">
            <div class="label">Úspěšně navoláno</div>
            <div class="value" style="color:#7e3ff2;"><?= (int) ($poolStats['call_success'] ?? 0) ?></div>
            <div class="sub">
                ❌ <?= (int) ($poolStats['call_failed'] ?? 0) ?> neúspěch
                <?php if (!empty($poolStats['flagged_refund'])) { ?>
                    · ⚠ <?= (int) $poolStats['flagged_refund'] ?> reklamací
                <?php } ?>
            </div>
        </div>
        <div class="apo-stat apo-stat--money">
            <div class="label">💰 Dluhy čističce</div>
            <div class="value">
                <?= number_format((float) ($money['due_to_cleaner_total'] ?? 0), 2, ',', ' ') ?> Kč
            </div>
            <div class="sub">
                Zaplaceno: <strong><?= number_format((float) ($money['paid_to_cleaner_total'] ?? 0), 2, ',', ' ') ?> Kč</strong>
                · Nezaplaceno:
                <strong style="color:#dc2626;">
                    <?= number_format(
                        max(0.0, (float) ($money['due_to_cleaner_total'] ?? 0) - (float) ($money['paid_to_cleaner_total'] ?? 0)),
                        2, ',', ' '
                    ) ?> Kč
                </strong>
            </div>
        </div>
        <div class="apo-stat apo-stat--money">
            <div class="label">💰 Dluhy navolávačkám</div>
            <div class="value">
                <?= number_format((float) ($money['due_to_caller_total'] ?? 0), 2, ',', ' ') ?> Kč
            </div>
            <div class="sub">
                Zaplaceno: <strong><?= number_format((float) ($money['paid_to_caller_total'] ?? 0), 2, ',', ' ') ?> Kč</strong>
                · Nezaplaceno:
                <strong style="color:#dc2626;">
                    <?= number_format(
                        max(0.0, (float) ($money['due_to_caller_total'] ?? 0) - (float) ($money['paid_to_caller_total'] ?? 0)),
                        2, ',', ' '
                    ) ?> Kč
                </strong>
            </div>
        </div>
    </div>

    <!-- Filtry -->
    <form method="get" action="<?= crm_h(crm_url('/admin/premium-overview')) ?>" class="apo-filters">
        <label style="font-size:0.85rem;">Stav:
            <select name="status" onchange="this.form.submit()">
                <option value="all"      <?= $statusFilter === 'all' ? 'selected' : '' ?>>Vše</option>
                <option value="open"     <?= $statusFilter === 'open' ? 'selected' : '' ?>>🟢 Otevřené</option>
                <option value="closed"   <?= $statusFilter === 'closed' ? 'selected' : '' ?>>🏁 Uzavřené</option>
                <option value="cancelled"<?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>✖ Zrušené</option>
            </select>
        </label>
        <label style="font-size:0.85rem;">OZ:
            <select name="oz_id" onchange="this.form.submit()">
                <option value="0">Všichni OZ</option>
                <?php foreach ($ozList as $oz) { ?>
                    <option value="<?= (int) $oz['id'] ?>" <?= $ozFilter === (int) $oz['id'] ? 'selected' : '' ?>>
                        <?= crm_h((string) $oz['jmeno']) ?>
                    </option>
                <?php } ?>
            </select>
        </label>
        <span style="font-size: 0.78rem; color: var(--color-text-muted); margin-left: auto;">
            Zobrazeno: <?= count($orders) ?>
        </span>
    </form>

    <!-- Tabulka objednávek -->
    <?php if ($orders === []) { ?>
        <p style="text-align:center; padding: 2rem; color: var(--color-text-muted);">
            Žádné objednávky odpovídající filtru.
        </p>
    <?php } else { ?>
        <table class="apo-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>OZ / Období</th>
                    <th>Stav</th>
                    <th>Funnel</th>
                    <th>Cena × požad.</th>
                    <th>Navolávačka</th>
                    <th>💰 Čističce</th>
                    <th>💰 Navolávačce</th>
                    <th>Akce</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $o) {
                $oid       = (int) $o['id'];
                $status    = (string) $o['status'];
                $statusCls = 'apo-b-' . $status;

                $price       = (float) $o['price_per_lead'];
                $bonus       = (float) $o['caller_bonus_per_lead'];
                $payCleaner  = (int) ($o['payable_to_cleaner'] ?? 0);
                $payCaller   = (int) ($o['payable_to_caller']  ?? 0);
                $dueCleaner  = $payCleaner * $price;
                $dueCaller   = $payCaller  * $bonus;

                $paidCleanerAt = (string) ($o['paid_to_cleaner_at'] ?? '');
                $paidCallerAt  = (string) ($o['paid_to_caller_at']  ?? '');
                $isPaidCleaner = $paidCleanerAt !== '';
                $isPaidCaller  = $paidCallerAt !== '';

                $req       = (int) $o['requested_count'];
                $res       = (int) $o['reserved_count'];
                $pending   = (int) ($o['pool_pending'] ?? 0);
                $tradeable = (int) ($o['pool_tradeable'] ?? 0);
                $nontrad   = (int) ($o['pool_non_tradeable'] ?? 0);
                $called    = (int) ($o['pool_called_success'] ?? 0);
                $cleaned   = $tradeable + $nontrad;

                $regions   = (string) ($o['regions_json'] ?? '');
                $regsArr   = $regions !== '' ? (json_decode($regions, true) ?: []) : [];
            ?>
                <tr>
                    <td>
                        <strong>#<?= $oid ?></strong>
                        <div style="font-size:0.7rem; color:var(--color-text-muted);">
                            <?= crm_h(date('j.n.Y', strtotime((string) $o['created_at']))) ?>
                        </div>
                    </td>
                    <td>
                        <strong>👔 <?= crm_h((string) $o['oz_name']) ?></strong>
                        <div style="font-size:0.72rem; color:var(--color-text-muted);">
                            <?= crm_h($_czechMonth((int) $o['month'])) ?> <?= (int) $o['year'] ?>
                            <?php if ($regsArr !== []) { ?>
                                · <?= crm_h(implode(', ', array_map('crm_region_label', $regsArr))) ?>
                            <?php } ?>
                        </div>
                    </td>
                    <td>
                        <span class="apo-badge <?= crm_h($statusCls) ?>">
                            <?= crm_h($_statusLabels[$status] ?? $status) ?>
                        </span>
                    </td>
                    <td>
                        <div class="apo-funnel">
                            objednáno <strong><?= $req ?></strong>
                            → rezervace <strong><?= $res ?></strong><br>
                            → vyčištěno <strong><?= $cleaned ?></strong>
                            <?php if ($pending > 0) { ?>(⏳ <?= $pending ?>)<?php } ?><br>
                            → obch. <strong class="ok"><?= $tradeable ?></strong>,
                            neobch. <?= $nontrad ?><br>
                            → navoláno <strong class="called"><?= $called ?></strong>
                        </div>
                    </td>
                    <td>
                        <strong><?= number_format($price, 2, ',', ' ') ?> Kč</strong>/lead
                        <?php if ($bonus > 0) { ?>
                            <div style="font-size:0.72rem; color:#d97706; margin-top:2px;">
                                + <?= number_format($bonus, 2, ',', ' ') ?> Kč bonus/hovor
                            </div>
                        <?php } ?>
                    </td>
                    <td>
                        <?php if (!empty($o['preferred_caller_name'])) { ?>
                            <strong><?= crm_h((string) $o['preferred_caller_name']) ?></strong>
                        <?php } else { ?>
                            <em style="color:var(--color-text-muted); font-size:0.78rem;">rotace</em>
                        <?php } ?>
                    </td>
                    <td>
                        <strong><?= number_format($dueCleaner, 2, ',', ' ') ?> Kč</strong>
                        <div style="margin-top:3px;">
                            <?php if ($isPaidCleaner) { ?>
                                <span class="apo-pay-pill apo-pay-paid">
                                    ✅ <?= crm_h(date('j.n.Y', strtotime($paidCleanerAt))) ?>
                                </span>
                            <?php } else if ($payCleaner > 0) { ?>
                                <span class="apo-pay-pill apo-pay-unpaid">○ Nezaplaceno</span>
                            <?php } else { ?>
                                <span class="apo-pay-pill apo-pay-na">— nic</span>
                            <?php } ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($bonus <= 0) { ?>
                            <span class="apo-pay-pill apo-pay-na">bez bonusu</span>
                        <?php } else { ?>
                            <strong><?= number_format($dueCaller, 2, ',', ' ') ?> Kč</strong>
                            <div style="margin-top:3px;">
                                <?php if ($isPaidCaller) { ?>
                                    <span class="apo-pay-pill apo-pay-paid">
                                        ✅ <?= crm_h(date('j.n.Y', strtotime($paidCallerAt))) ?>
                                    </span>
                                <?php } else if ($payCaller > 0) { ?>
                                    <span class="apo-pay-pill apo-pay-unpaid">○ Nezaplaceno</span>
                                <?php } else { ?>
                                    <span class="apo-pay-pill apo-pay-na">— nic</span>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </td>
                    <td>
                        <a href="<?= crm_h(crm_url('/oz/premium/payout/print?order_id=' . $oid . '&oz_id=' . (int) $o['oz_id'])) ?>"
                           target="_blank"
                           style="background:#fff; border:1px solid var(--color-border-strong); padding:0.3rem 0.65rem; border-radius:4px; text-decoration:none; font-size:0.75rem; font-weight:600; color:var(--color-text);">
                            🖨 Faktura
                        </a>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</section>
