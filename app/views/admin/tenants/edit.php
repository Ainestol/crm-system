<?php
/** @var array<string,mixed> $tenant */
/** @var array<string,mixed> $usage */
/** @var array<int,array<string,mixed>> $plans */
/** @var array<int,array<string,mixed>> $payments */
/** @var array<int,array<string,mixed>> $users */
/** @var ?string $flash */
/** @var string $csrf */
?>
<style>
.te-wrap { padding: 1rem 1.25rem; max-width: 1100px; }
.te-wrap h1 { margin: 0 0 .25rem; font-size: 1.4rem; }
.te-wrap .breadcrumb { color:#6b7280; font-size:.85rem; margin-bottom:1rem; }
.te-wrap .breadcrumb a { color:#2563eb; text-decoration:none; }
.te-grid { display:grid; gap:16px; grid-template-columns: 1fr; }
@media (min-width: 900px) { .te-grid { grid-template-columns: 2fr 1fr; } }
.card {
    background:#fff; border:1px solid #e5e7eb; border-radius:10px;
    padding:1rem 1.25rem; box-shadow:0 1px 2px rgba(0,0,0,.04);
}
.card h2 { margin:0 0 .75rem; font-size:1.05rem; }
.card label { display:block; font-size:.85rem; color:#374151; margin-bottom:.2rem; font-weight:600;}
.card input[type=text], .card input[type=number], .card input[type=email],
.card input[type=date], .card input[type=datetime-local], .card select, .card textarea {
    width:100%; padding:.45rem .6rem; border:1px solid #d1d5db; border-radius:6px; font-size:.92rem;
    margin-bottom:.65rem; background:#fff;
}
.card textarea { min-height:60px; resize:vertical; }
.card .row { display:flex; gap:.75rem; flex-wrap:wrap; }
.card .row > div { flex:1; min-width:140px; }
.btn { display:inline-block; padding:.5rem 1rem; border-radius:6px; border:0; font-weight:600; cursor:pointer; font-size:.9rem; }
.btn-primary { background:#2563eb; color:#fff; }
.btn-secondary { background:#e5e7eb; color:#111827; }
.btn-danger { background:#dc2626; color:#fff; }
.usage-mini { display:flex; gap:.7rem; flex-wrap:wrap; }
.usage-mini > div { flex:1; min-width:130px; padding:.55rem .7rem; background:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; }
.usage-mini strong { font-size:1.05rem; }
.usage-mini .bar { background:#e5e7eb; border-radius:6px; height:5px; margin-top:.3rem;}
.usage-mini .bar > div { height:100%; background:#60a5fa; border-radius:6px;}
.usage-mini .bar.warn > div { background:#f59e0b; }
.usage-mini .bar.over > div { background:#ef4444; }
table.payments { width:100%; border-collapse:collapse; font-size:.85rem; }
table.payments th, table.payments td { padding:.4rem .55rem; border-bottom:1px solid #f0f0f0; text-align:left; }
table.payments th { background:#f9fafb; font-weight:600; color:#374151; }
.users-list { font-size:.85rem; }
.users-list li { padding:.25rem 0; border-bottom:1px dashed #f0f0f0; list-style:none; }
.users-list li.inactive { color:#9ca3af; text-decoration:line-through; }
.flash-box { background:#d1fae5; color:#065f46; padding:.7rem 1rem; border-radius:6px; margin-bottom:1rem; border:1px solid #6ee7b7; }
.suspend-banner { background:#fee2e2; color:#991b1b; padding:.6rem 1rem; border-radius:6px; margin-bottom:1rem; }
</style>

<div class="te-wrap">
    <div class="breadcrumb">
        <a href="/admin/tenants">← Zpět na seznam firem</a>
    </div>
    <h1>🏢 <?= htmlspecialchars((string) $tenant['name'], ENT_QUOTES, 'UTF-8') ?></h1>
    <p style="color:#6b7280;margin:0 0 1rem;">
        ID #<?= (int) $tenant['id'] ?> &middot; subdomain <code><?= htmlspecialchars((string) $tenant['subdomain'], ENT_QUOTES, 'UTF-8') ?></code>
        &middot; vytvořeno <?= htmlspecialchars(date('d.m.Y', strtotime((string) $tenant['created_at'])), ENT_QUOTES, 'UTF-8') ?>
    </p>

    <?php if ($flash): ?>
        <div class="flash-box"><?= htmlspecialchars((string) $flash, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ((int) $tenant['active'] === 0): ?>
        <div class="suspend-banner">⚠ Firma je SUSPENDOVÁNA — uživatelé se nemohou přihlásit.</div>
    <?php endif; ?>

    <!-- Usage přehled -->
    <div class="card" style="margin-bottom:1rem;">
        <h2>📊 Aktuální využití</h2>
        <div class="usage-mini">
            <?php
            $items = [
                ['Uživatelé',    $usage['users_active'],              $usage['users_max']],
                ['Kontakty',     $usage['contacts_total'],            $usage['contacts_max']],
                ['Premium/měsíc',$usage['premium_orders_this_month'], $usage['premium_orders_max']],
            ];
            foreach ($items as [$label, $count, $max]):
                $pct = ($max && $max > 0) ? min(100, (int) round($count * 100 / $max)) : null;
                $cls = $pct !== null ? ($pct >= 100 ? 'over' : ($pct >= 80 ? 'warn' : '')) : '';
            ?>
                <div>
                    <div style="color:#6b7280;font-size:.78rem;"><?= $label ?></div>
                    <strong><?= number_format((int) $count, 0, ',', ' ') ?></strong>
                    <span style="color:#6b7280;font-size:.85rem;">/ <?= $max !== null ? number_format((int) $max, 0, ',', ' ') : '∞' ?></span>
                    <?php if ($pct !== null): ?>
                        <div class="bar <?= $cls ?>"><div style="width:<?= (int) $pct ?>%"></div></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="te-grid">
        <!-- LEVÝ SLOUPEC: edit form -->
        <div>
            <div class="card">
                <h2>✏️ Údaje firmy</h2>
                <form method="post" action="/admin/tenants/save">
                    <input type="hidden" name="<?= crm_csrf_field_name() ?>" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="tenant_id" value="<?= (int) $tenant['id'] ?>">

                    <label>Jméno firmy</label>
                    <input type="text" name="name" value="<?= htmlspecialchars((string) $tenant['name'], ENT_QUOTES, 'UTF-8') ?>" required>

                    <label>Kontakt na majitele (email)</label>
                    <input type="email" name="email_owner" value="<?= htmlspecialchars((string) ($tenant['email_owner'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                    <div class="row">
                        <div>
                            <label>Plán</label>
                            <select name="plan_code">
                                <?php foreach ($plans as $p): ?>
                                    <option value="<?= htmlspecialchars((string) $p['slug'], ENT_QUOTES, 'UTF-8') ?>"
                                        <?= (string) $tenant['plan_code'] === (string) $p['slug'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string) $p['name'], ENT_QUOTES, 'UTF-8') ?>
                                        (<?= (int) $p['monthly_price_czk'] ?> Kč/m)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Měsíční cena (Kč) — skutečná</label>
                            <input type="number" name="monthly_price_czk" step="0.01" min="0"
                                   value="<?= htmlspecialchars((string) ($tenant['monthly_price_czk'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="prázdné = dle plánu">
                        </div>
                    </div>

                    <div class="row">
                        <div>
                            <label>Max uživatelů (prázdné = ∞)</label>
                            <input type="number" name="max_users" min="0"
                                   value="<?= htmlspecialchars((string) ($tenant['max_users'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div>
                            <label>Max kontaktů (prázdné = ∞)</label>
                            <input type="number" name="max_contacts" min="0"
                                   value="<?= htmlspecialchars((string) ($tenant['max_contacts'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div>
                            <label>Max premium/měsíc (prázdné = ∞)</label>
                            <input type="number" name="max_premium_orders_per_month" min="0"
                                   value="<?= htmlspecialchars((string) ($tenant['max_premium_orders_per_month'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>

                    <label>Zaplaceno do</label>
                    <input type="date" name="paid_until"
                           value="<?= !empty($tenant['paid_until']) ? htmlspecialchars(date('Y-m-d', strtotime((string) $tenant['paid_until'])), ENT_QUOTES, 'UTF-8') : '' ?>">

                    <label>
                        <input type="checkbox" name="active" value="1" <?= (int) $tenant['active'] === 1 ? 'checked' : '' ?>>
                        Firma je aktivní (může se přihlašovat)
                    </label>

                    <label style="margin-top:.5rem;">Interní poznámka (jen super-admin vidí)</label>
                    <textarea name="admin_notes"><?= htmlspecialchars((string) ($tenant['admin_notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>

                    <button class="btn btn-primary" type="submit">💾 Uložit změny</button>
                </form>
            </div>

            <!-- Branding -->
            <div class="card" style="margin-top:1rem;">
                <h2>🎨 Vzhled (logo + barvy)</h2>
                <p style="color:#6b7280; font-size:.85rem; margin:.2rem 0 .7rem;">
                    Zobrazované jméno + logo v sidebaru. Barvy ovlivňují avatara firmy.
                </p>
                <form method="post" action="/admin/tenants/save-branding" enctype="multipart/form-data">
                    <input type="hidden" name="<?= crm_csrf_field_name() ?>" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="tenant_id" value="<?= (int) $tenant['id'] ?>">

                    <label>Zobrazované jméno (volitelné)</label>
                    <input type="text" name="display_name" value="<?= htmlspecialchars((string) ($branding['display_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="<?= htmlspecialchars((string) $tenant['name'], ENT_QUOTES, 'UTF-8') ?>">

                    <label>Logo — URL</label>
                    <input type="text" name="logo_url"
                           value="<?= htmlspecialchars((string) ($branding['logo_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="https://… (nebo nechte prázdné a nahrajte soubor níže)">

                    <?php if (!empty($branding['logo_url'])): ?>
                        <div style="margin:.5rem 0 .7rem; padding:.5rem; background:#f9fafb; border:1px solid #e5e7eb; border-radius:6px;">
                            <span style="color:#6b7280; font-size:.8rem;">Aktuální logo:</span><br>
                            <img src="<?= htmlspecialchars((string) $branding['logo_url'], ENT_QUOTES, 'UTF-8') ?>"
                                 alt="Logo" style="max-width:120px; max-height:48px; margin-top:.3rem;">
                        </div>
                    <?php endif; ?>

                    <label>Nebo nahrát soubor (JPG/PNG/SVG/WebP, max 500 KB)</label>
                    <input type="file" name="logo_file" accept="image/png,image/jpeg,image/svg+xml,image/webp,image/gif">

                    <div class="row" style="margin-top:.7rem;">
                        <div>
                            <label>Primární barva (avatar / accenty)</label>
                            <input type="color" name="primary_color" value="<?= htmlspecialchars((string) $branding['primary_color'], ENT_QUOTES, 'UTF-8') ?>" style="width:100%; height:38px; padding:.2rem;">
                        </div>
                        <div>
                            <label>Doplňková barva (gradient)</label>
                            <input type="color" name="accent_color" value="<?= htmlspecialchars((string) $branding['accent_color'], ENT_QUOTES, 'UTF-8') ?>" style="width:100%; height:38px; padding:.2rem;">
                        </div>
                    </div>

                    <button class="btn btn-primary" type="submit" style="margin-top:.7rem;">🎨 Uložit vzhled</button>
                </form>
            </div>

            <!-- Apply plan -->
            <div class="card" style="margin-top:1rem;">
                <h2>📋 Aplikovat plán (přepíše limity)</h2>
                <p style="color:#6b7280; font-size:.85rem; margin:.2rem 0 .7rem;">
                    Vybere plán a přepíše max_users / max_contacts / max_premium podle katalogu.
                    Užitečné když chceš rychle nastavit firmu na "Business" parametry.
                </p>
                <form method="post" action="/admin/tenants/apply-plan" style="display:flex;gap:.5rem; align-items:flex-end;">
                    <input type="hidden" name="<?= crm_csrf_field_name() ?>" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="tenant_id" value="<?= (int) $tenant['id'] ?>">
                    <div style="flex:1;">
                        <label style="font-size:.85rem;color:#374151;font-weight:600;">Plán</label>
                        <select name="plan_slug">
                            <?php foreach ($plans as $p): ?>
                                <option value="<?= htmlspecialchars((string) $p['slug'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars((string) $p['name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-secondary">Aplikovat</button>
                </form>
            </div>

            <!-- Log payment -->
            <div class="card" style="margin-top:1rem;">
                <h2>💰 Zaznamenat platbu</h2>
                <form method="post" action="/admin/tenants/log-payment">
                    <input type="hidden" name="<?= crm_csrf_field_name() ?>" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="tenant_id" value="<?= (int) $tenant['id'] ?>">
                    <div class="row">
                        <div>
                            <label>Částka (Kč)</label>
                            <input type="number" name="amount_czk" step="0.01" min="0" required>
                        </div>
                        <div>
                            <label>Zaplaceno dne</label>
                            <input type="date" name="paid_at" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div>
                            <label>Způsob</label>
                            <select name="payment_method">
                                <option value="bank_transfer">Bankovní převod</option>
                                <option value="card">Karta</option>
                                <option value="cash">Hotovost</option>
                                <option value="other">Jiné</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div>
                            <label>Předplatné platí OD</label>
                            <input type="date" name="period_from" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div>
                            <label>… DO (automaticky posune paid_until)</label>
                            <input type="date" name="period_until" value="<?= date('Y-m-d', strtotime('+1 month')) ?>">
                        </div>
                        <div>
                            <label>Č. faktury (volitelné)</label>
                            <input type="text" name="invoice_number">
                        </div>
                    </div>
                    <label>Poznámka</label>
                    <textarea name="notes" placeholder="např. 'roční předplatné se slevou'"></textarea>
                    <button class="btn btn-primary">💰 Uložit platbu</button>
                </form>
            </div>
        </div>

        <!-- PRAVÝ SLOUPEC: payment history + users -->
        <div>
            <div class="card">
                <h2>📜 Historie plateb</h2>
                <?php if ($payments === []): ?>
                    <p style="color:#9ca3af; font-size:.85rem;">Zatím žádné platby.</p>
                <?php else: ?>
                    <table class="payments">
                        <thead>
                            <tr>
                                <th>Datum</th>
                                <th>Částka</th>
                                <th>Za období</th>
                                <th>Kdo</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars(date('d.m.Y', strtotime((string) $p['paid_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><strong><?= number_format((float) $p['amount_czk'], 0, ',', ' ') ?> Kč</strong></td>
                                <td>
                                    <?= htmlspecialchars(date('d.m.', strtotime((string) $p['period_from'])), ENT_QUOTES, 'UTF-8') ?>
                                    &ndash;
                                    <?= htmlspecialchars(date('d.m.Y', strtotime((string) $p['period_until'])), ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td><?= htmlspecialchars((string) ($p['recorded_by_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                            <?php if (!empty($p['notes'])): ?>
                                <tr><td colspan="4" style="color:#6b7280;font-size:.8rem;padding-left:1rem;">↪ <?= htmlspecialchars((string) $p['notes'], ENT_QUOTES, 'UTF-8') ?></td></tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="card" style="margin-top:1rem;">
                <h2>👥 Uživatelé ve firmě (<?= count($users) ?>)</h2>
                <ul class="users-list" style="padding:0; margin:0;">
                    <?php foreach ($users as $u): ?>
                        <li class="<?= (int) $u['active'] === 0 ? 'inactive' : '' ?>">
                            <strong><?= htmlspecialchars((string) $u['jmeno'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <span style="color:#6b7280; font-size:.8rem;">
                                (<?= htmlspecialchars((string) $u['email'], ENT_QUOTES, 'UTF-8') ?>)
                                — <?= htmlspecialchars((string) $u['role'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
