<?php
/** @var array<int,array<string,mixed>> $tenants */
/** @var array<int,array<string,mixed>> $plans */
/** @var ?string $flash */
/** @var string $csrf */
/** @var array $user */
?>
<style>
.tenants-wrap { padding: 1rem 1.25rem; max-width: 1400px; }
.tenants-wrap h1 { margin: 0 0 1rem; font-size: 1.5rem; }
.tenants-grid {
    display: grid; gap: 12px; grid-template-columns: 1fr;
}
@media (min-width: 900px) { .tenants-grid { grid-template-columns: repeat(2, 1fr); } }
@media (min-width: 1300px){ .tenants-grid { grid-template-columns: repeat(3, 1fr); } }
.tenant-card {
    background:#fff; border:1px solid #e5e7eb; border-radius:10px;
    padding:1rem; display:flex; flex-direction:column; gap:.55rem;
    box-shadow:0 1px 2px rgba(0,0,0,.04);
}
.tenant-card.suspended { background:#fef2f2; border-color:#fca5a5; }
.tenant-card h3 { margin:0; font-size:1.05rem; display:flex; align-items:center; gap:.5rem;}
.tenant-card .sub { color:#6b7280; font-size:.85rem; }
.tenant-card .row { display:flex; justify-content:space-between; align-items:center; gap:.5rem; font-size:.88rem; }
.badge {
    display:inline-block; padding:.1rem .5rem; border-radius:999px;
    font-size:.72rem; font-weight:600;
}
.badge.plan-free       { background:#f3f4f6; color:#374151; }
.badge.plan-starter    { background:#dbeafe; color:#1e40af; }
.badge.plan-business   { background:#fef3c7; color:#92400e; }
.badge.plan-enterprise { background:#ede9fe; color:#5b21b6; }
.badge.status-active   { background:#d1fae5; color:#065f46; }
.badge.status-expiring { background:#fef3c7; color:#92400e; }
.badge.status-expired  { background:#fee2e2; color:#991b1b; }
.badge.status-unknown  { background:#f3f4f6; color:#6b7280; }
.badge.lc-unlimited    { background:#ede9fe; color:#5b21b6; }
.badge.lc-trial        { background:#dbeafe; color:#1e40af; }
.badge.lc-active       { background:#d1fae5; color:#065f46; }
.badge.lc-grace        { background:#fef3c7; color:#92400e; border:1px dashed #d97706; }
.badge.lc-expired_paid,
.badge.lc-expired_trial { background:#fecaca; color:#991b1b; }
.badge.lc-suspended    { background:#fee2e2; color:#991b1b; }
.bar { background:#e5e7eb; border-radius:6px; height:6px; overflow:hidden; }
.bar > div { height:100%; background:#60a5fa; transition:width .2s; }
.bar.warn > div { background:#f59e0b; }
.bar.over > div { background:#ef4444; }
.tenant-card a.edit { color:#2563eb; font-weight:600; text-decoration:none; font-size:.85rem; }
.tenant-card a.edit:hover { text-decoration:underline; }
.flash-box {
    background:#d1fae5; color:#065f46; padding:.7rem 1rem; border-radius:6px;
    margin-bottom:1rem; border:1px solid #6ee7b7;
}
</style>
<div class="tenants-wrap">
    <h1>🏢 Správa firem (tenants)</h1>

    <?php if ($flash): ?>
        <div class="flash-box"><?= htmlspecialchars((string) $flash, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <p style="color:#6b7280; margin:0 0 1rem;">
        Celkem firem: <strong><?= count($tenants) ?></strong> &middot;
        Aktivní plány: <strong><?= count($plans) ?></strong>
    </p>

    <div class="tenants-grid">
        <?php foreach ($tenants as $t): ?>
            <?php
                $plan = (string) ($t['plan_code'] ?? 'free');
                $status = (string) ($t['paid_status'] ?? 'unknown');
                $cls = (int) ($t['active'] ?? 1) === 0 ? 'suspended' : '';
            ?>
            <div class="tenant-card <?= $cls ?>">
                <h3>
                    <?= htmlspecialchars((string) $t['name'], ENT_QUOTES, 'UTF-8') ?>
                    <?php
                        $_lc = (string) ($t['lifecycle']['state'] ?? 'unknown');
                        $_lcLabel = [
                            'unlimited'     => '∞ Unlimited',
                            'trial'         => '🧪 Trial',
                            'active'        => '✓ Active',
                            'grace'         => '⚠ Grace',
                            'expired_paid'  => '⏰ Expired',
                            'expired_trial' => '⏰ Trial skončil',
                            'suspended'     => '🚫 Suspended',
                        ][$_lc] ?? $_lc;
                    ?>
                    <span class="badge lc-<?= htmlspecialchars($_lc, ENT_QUOTES, 'UTF-8') ?>" title="Lifecycle stav">
                        <?= htmlspecialchars($_lcLabel, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </h3>
                <div class="sub">
                    <strong>ID #<?= (int) $t['id'] ?></strong>
                    &middot; subdomain: <code><?= htmlspecialchars((string) $t['subdomain'], ENT_QUOTES, 'UTF-8') ?></code>
                </div>

                <div class="row">
                    <span>Plán:</span>
                    <span>
                        <span class="badge plan-<?= htmlspecialchars($plan, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars((string) ($t['plan_name'] ?? $plan), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </span>
                </div>

                <?php if (!empty($t['paid_until'])): ?>
                    <div class="row">
                        <span>Zaplaceno do:</span>
                        <span>
                            <?= htmlspecialchars(date('d.m.Y', strtotime((string) $t['paid_until'])), ENT_QUOTES, 'UTF-8') ?>
                            <span class="badge status-<?= $status ?>">
                                <?php if ($status === 'expired'): ?>
                                    PROŠLÉ
                                <?php elseif ($status === 'expiring'): ?>
                                    blíží se konec (<?= (int) $t['paid_days_left'] ?> dní)
                                <?php else: ?>
                                    OK (<?= (int) $t['paid_days_left'] ?> dní)
                                <?php endif; ?>
                            </span>
                        </span>
                    </div>
                <?php endif; ?>

                <!-- Users limit -->
                <div>
                    <div class="row" style="margin-bottom:.2rem;">
                        <span>Uživatelé:</span>
                        <strong><?= (int) $t['users_count'] ?> / <?= $t['usage']['users_max'] !== null ? (int) $t['usage']['users_max'] : '∞' ?></strong>
                    </div>
                    <?php if ($t['pct_users'] !== null): ?>
                        <?php $cls = $t['pct_users'] >= 100 ? 'over' : ($t['pct_users'] >= 80 ? 'warn' : ''); ?>
                        <div class="bar <?= $cls ?>"><div style="width:<?= (int) $t['pct_users'] ?>%"></div></div>
                    <?php endif; ?>
                </div>

                <!-- Contacts limit -->
                <div>
                    <div class="row" style="margin-bottom:.2rem;">
                        <span>Kontakty:</span>
                        <strong><?= number_format((int) $t['contacts_count'], 0, ',', ' ') ?> / <?= $t['usage']['contacts_max'] !== null ? number_format((int) $t['usage']['contacts_max'], 0, ',', ' ') : '∞' ?></strong>
                    </div>
                    <?php if ($t['pct_contacts'] !== null): ?>
                        <?php $cls = $t['pct_contacts'] >= 100 ? 'over' : ($t['pct_contacts'] >= 80 ? 'warn' : ''); ?>
                        <div class="bar <?= $cls ?>"><div style="width:<?= (int) $t['pct_contacts'] ?>%"></div></div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($t['email_owner'])): ?>
                    <div class="sub">📧 <?= htmlspecialchars((string) $t['email_owner'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <div class="row" style="margin-top:.4rem; padding-top:.4rem; border-top:1px solid #e5e7eb;">
                    <span class="sub">
                        Lifetime: <?= number_format((float) $t['lifetime_paid'], 0, ',', ' ') ?> Kč
                    </span>
                    <a class="edit" href="/admin/tenants/edit?id=<?= (int) $t['id'] ?>">✏️ Detail / edit</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
