<?php
/** @var array<string,mixed> $payload */
$session = $payload['session'];
$request = $payload['request'];
$data    = $payload['data'];
$note    = (string) $payload['note'];
$isOk    = str_starts_with($note, 'OK');
?>
<style>
.dbg-wrap { padding: 1rem 1.4rem; max-width: 1100px; }
.dbg-wrap h1 { margin: 0 0 .35rem; font-size: 1.4rem; }
.dbg-wrap .sub { color:#6b7280; font-size:.85rem; margin-bottom:1rem; }
.dbg-banner {
    padding:.75rem 1rem; border-radius:8px; margin-bottom:1rem;
    font-size:.9rem;
}
.dbg-banner.ok   { background:#d1fae5; border:1px solid #6ee7b7; color:#065f46; }
.dbg-banner.warn { background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; }

.dbg-grid { display:grid; gap:14px; grid-template-columns: 1fr; margin-bottom:1rem; }
@media (min-width: 900px) { .dbg-grid { grid-template-columns: repeat(2, 1fr); } }

.dbg-card {
    background:#fff; border:1px solid #e5e7eb; border-radius:10px;
    padding:1rem 1.25rem; box-shadow:0 1px 2px rgba(0,0,0,.04);
}
.dbg-card h2 {
    margin:0 0 .7rem; font-size:.9rem; text-transform:uppercase; letter-spacing:.06em;
    color:#6b7280; font-weight:700;
}
.dbg-card h2 .ico { font-size:1.1rem; margin-right:.4rem; }

.kv { display:flex; justify-content:space-between; gap:1rem;
      padding:.45rem 0; border-bottom:1px dashed #f0f0f0; font-size:.9rem; }
.kv:last-child { border-bottom:0; }
.kv .k { color:#6b7280; }
.kv .v { color:#111827; font-weight:600; font-variant-numeric: tabular-nums; word-break:break-all; }
.kv .v code { background:#f3f4f6; padding:.1rem .4rem; border-radius:4px; font-size:.82rem; }
.badge {
    display:inline-block; padding:.12rem .55rem; border-radius:999px;
    font-size:.72rem; font-weight:600;
}
.b-true   { background:#d1fae5; color:#065f46; }
.b-false  { background:#fee2e2; color:#991b1b; }
.b-active { background:#d1fae5; color:#065f46; }
.b-inactive { background:#fee2e2; color:#991b1b; }
.b-current { background:#dbeafe; color:#1e40af; }

table.dbg-table { width:100%; border-collapse:collapse; font-size:.85rem; }
.dbg-table th, .dbg-table td { padding:.45rem .55rem; border-bottom:1px solid #f0f0f0; text-align:left; }
.dbg-table th { background:#f9fafb; font-weight:600; color:#374151; font-size:.72rem; text-transform:uppercase; letter-spacing:.05em; }
.dbg-table tr.current { background:#eff6ff; }

.dbg-actions {
    margin-top:1rem; display:flex; gap:.5rem; flex-wrap:wrap;
}
.dbg-btn {
    display:inline-flex; align-items:center; gap:.4rem;
    padding:.5rem .85rem; border-radius:6px;
    background:#f3f4f6; color:#374151; text-decoration:none;
    font-size:.85rem; font-weight:500;
}
.dbg-btn:hover { background:#e5e7eb; color:#111827; }
.dbg-btn.primary { background:#2563eb; color:#fff; }
.dbg-btn.primary:hover { background:#1d4ed8; color:#fff; }
</style>

<div class="dbg-wrap">
    <h1>🔍 Tenant diagnostika</h1>
    <div class="sub">Pohled super-admina na vše, co řídí multi-tenant routing.</div>

    <div class="dbg-banner <?= $isOk ? 'ok' : 'warn' ?>">
        <?= $isOk ? '✓' : '⚠' ?> <?= htmlspecialchars($note, ENT_QUOTES, 'UTF-8') ?>
    </div>

    <div class="dbg-grid">
        <!-- Session info -->
        <div class="dbg-card">
            <h2><span class="ico">👤</span> Session</h2>
            <div class="kv">
                <span class="k">User ID</span>
                <span class="v">#<?= (int) $session['user_id'] ?></span>
            </div>
            <div class="kv">
                <span class="k">Email</span>
                <span class="v"><code><?= htmlspecialchars((string) $session['user_email'], ENT_QUOTES, 'UTF-8') ?></code></span>
            </div>
            <div class="kv">
                <span class="k">Aktivní tenant</span>
                <span class="v">#<?= (int) $session['tenant_id'] ?></span>
            </div>
            <div class="kv">
                <span class="k">Super-admin</span>
                <span class="v">
                    <span class="badge <?= $session['is_super_admin'] ? 'b-true' : 'b-false' ?>">
                        <?= $session['is_super_admin'] ? 'ANO' : 'NE' ?>
                    </span>
                </span>
            </div>
        </div>

        <!-- Request info -->
        <div class="dbg-card">
            <h2><span class="ico">🌐</span> Request</h2>
            <div class="kv">
                <span class="k">Host</span>
                <span class="v"><code><?= htmlspecialchars((string) $request['host'], ENT_QUOTES, 'UTF-8') ?></code></span>
            </div>
            <div class="kv">
                <span class="k">Subdoména</span>
                <span class="v"><code><?= $request['subdomain'] !== null ? htmlspecialchars((string) $request['subdomain'], ENT_QUOTES, 'UTF-8') : '—' ?></code></span>
            </div>
            <div class="kv">
                <span class="k">App ENV</span>
                <span class="v"><code><?= htmlspecialchars((string) $request['app_env'], ENT_QUOTES, 'UTF-8') ?></code></span>
            </div>
            <div class="kv">
                <span class="k">Dev mode</span>
                <span class="v">
                    <span class="badge <?= $request['is_dev_mode'] ? 'b-true' : 'b-false' ?>">
                        <?= $request['is_dev_mode'] ? 'ANO' : 'NE' ?>
                    </span>
                </span>
            </div>
            <div class="kv">
                <span class="k">Resolved tenant_id</span>
                <span class="v">#<?= (int) $request['resolved_tid'] ?></span>
            </div>
        </div>
    </div>

    <!-- Data -->
    <div class="dbg-card" style="margin-bottom:1rem;">
        <h2><span class="ico">📊</span> Data v DB</h2>
        <div class="kv">
            <span class="k">NEW kontaktů v session tenantu</span>
            <span class="v"><?= number_format((int) $data['new_count_for_session'], 0, ',', ' ') ?></span>
        </div>
        <?php if (!empty($data['contacts_per_tenant'])): ?>
            <h2 style="margin-top:1rem;"><span class="ico">📋</span> Kontakty per tenant</h2>
            <table class="dbg-table">
                <thead>
                    <tr><th>Tenant ID</th><th style="text-align:right;">Kontaktů</th></tr>
                </thead>
                <tbody>
                <?php foreach ($data['contacts_per_tenant'] as $tid => $cnt): ?>
                    <tr class="<?= (int) $tid === (int) $session['tenant_id'] ? 'current' : '' ?>">
                        <td>#<?= (int) $tid ?>
                            <?php if ((int) $tid === (int) $session['tenant_id']): ?>
                                <span class="badge b-current">aktivní</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right; font-variant-numeric:tabular-nums;"><?= number_format((int) $cnt, 0, ',', ' ') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Tenants list -->
    <div class="dbg-card" style="margin-bottom:1rem;">
        <h2><span class="ico">🏢</span> Všechny firmy v systému</h2>
        <table class="dbg-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Jméno</th>
                    <th>Subdoména</th>
                    <th>Plán</th>
                    <th>Status</th>
                    <th>Zaplaceno do</th>
                    <th>Trial do</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($data['tenants'] as $t): ?>
                <tr class="<?= (int) $t['id'] === (int) $session['tenant_id'] ? 'current' : '' ?>">
                    <td>#<?= (int) $t['id'] ?></td>
                    <td>
                        <strong><?= htmlspecialchars((string) $t['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <?php if ((int) $t['id'] === (int) $session['tenant_id']): ?>
                            <span class="badge b-current">aktivní</span>
                        <?php endif; ?>
                    </td>
                    <td><code><?= htmlspecialchars((string) $t['subdomain'], ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><?= htmlspecialchars((string) ($t['plan_code'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <span class="badge <?= (int) $t['active'] === 1 ? 'b-active' : 'b-inactive' ?>">
                            <?= (int) $t['active'] === 1 ? 'aktivní' : 'suspendovaný' ?>
                        </span>
                    </td>
                    <td><?= !empty($t['paid_until']) ? htmlspecialchars(date('d.m.Y', strtotime((string) $t['paid_until'])), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                    <td><?= !empty($t['trial_ends_at']) ? htmlspecialchars(date('d.m.Y', strtotime((string) $t['trial_ends_at'])), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- User access -->
    <div class="dbg-card">
        <h2><span class="ico">🔐</span> Tvůj přístup do firem</h2>
        <table class="dbg-table">
            <thead>
                <tr><th>Tenant</th><th>Role</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php if ($data['user_tenants'] === []): ?>
                <tr><td colspan="3" style="color:#9ca3af; text-align:center; padding:1rem;">Žádný záznam v user_tenants.</td></tr>
            <?php endif; ?>
            <?php foreach ($data['user_tenants'] as $ut): ?>
                <tr class="<?= (int) $ut['tenant_id'] === (int) $session['tenant_id'] ? 'current' : '' ?>">
                    <td>
                        #<?= (int) $ut['tenant_id'] ?>
                        <?php if (!empty($ut['tenant_name'])): ?>
                            · <?= htmlspecialchars((string) $ut['tenant_name'], ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </td>
                    <td><code><?= htmlspecialchars((string) ($ut['role'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td>
                        <span class="badge <?= (int) $ut['active'] === 1 ? 'b-active' : 'b-inactive' ?>">
                            <?= (int) $ut['active'] === 1 ? 'aktivní' : 'neaktivní' ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="dbg-actions">
        <a href="/admin/tenants" class="dbg-btn primary">🏢 Správa firem</a>
        <a href="/debug/tenant?format=json" class="dbg-btn" target="_blank">📦 Raw JSON</a>
        <a href="/owner-dashboard" class="dbg-btn">📊 Owner dashboard</a>
    </div>
</div>
