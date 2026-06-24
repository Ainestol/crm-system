<?php
/** @var list<array<string,mixed>> $rules */
/** @var string $role */
/** @var ?string $flash */
/** @var string $csrf */

$roleLabels = [
    'cisticka'    => '🧹 Čistička',
    'navolavacka' => '📞 Navolávačka',
    'obchodak'    => '💼 Obchodák',
    'backoffice'  => '📋 Backoffice',
    'majitel'     => '👑 Majitel',
];
?>
<style>
.as-wrap { padding: 1rem 1.25rem; max-width: 1000px; }
.as-wrap h1 { margin: 0 0 .35rem; font-size: 1.4rem; }
.as-wrap .sub { color:#6b7280; font-size:.85rem; margin-bottom:1rem; }
.as-tabs { display:flex; flex-wrap:wrap; gap:.4rem; margin-bottom:1rem; }
.as-tab {
    padding:.5rem .85rem; border-radius:6px; background:#f3f4f6; color:#374151;
    text-decoration:none; font-size:.88rem; font-weight:600;
}
.as-tab.active { background:#2563eb; color:#fff; }
.as-card {
    background:#fff; border:1px solid #e5e7eb; border-radius:10px;
    padding:1rem 1.25rem; box-shadow:0 1px 2px rgba(0,0,0,.04);
}
.as-table { width:100%; border-collapse:collapse; font-size:.92rem; }
.as-table th, .as-table td { padding:.55rem .65rem; text-align:left; border-bottom:1px solid #f0f0f0; }
.as-table th { background:#f9fafb; font-weight:600; color:#374151; font-size:.78rem; text-transform:uppercase; letter-spacing:.05em; }
.as-table input[type=number] {
    width:80px; padding:.4rem .5rem; border:1px solid #d1d5db; border-radius:6px; font-size:.92rem;
    text-align:right; font-variant-numeric: tabular-nums;
}
.as-table .label-col { min-width:280px; }
.as-table .type-col code { background:#f3f4f6; padding:.1rem .35rem; border-radius:4px; font-size:.78rem; color:#374151; }
.btn-primary { background:#2563eb; color:#fff; padding:.55rem 1.2rem; border-radius:6px; border:0; font-weight:600; cursor:pointer; font-size:.95rem; }
.btn-primary:hover { background:#1d4ed8; }
.as-flash { background:#d1fae5; color:#065f46; padding:.7rem 1rem; border-radius:6px; margin-bottom:1rem; border:1px solid #6ee7b7; }
.as-help {
    background:#eff6ff; border:1px solid #bfdbfe; color:#1e3a8a;
    padding:.7rem 1rem; border-radius:6px; margin-bottom:1rem; font-size:.85rem;
}
</style>

<div class="as-wrap">
    <h1>⚙️ Bodování aktivit</h1>
    <div class="sub">
        Per-tenant katalog akcí a bodů. Změny se aplikují <strong>jen na nové akce</strong> (historický
        activity_log si pamatuje body, které platily v okamžiku zápisu).
        &middot; <a href="/owner-dashboard" style="color:#2563eb;">← zpět na Dashboard</a>
    </div>

    <?php if ($flash): ?>
        <div class="as-flash"><?= htmlspecialchars((string) $flash, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="as-help">
        💡 <strong>Tip:</strong> Body 0 = akce se eviduje, ale nezapočítá se do skóre.
        Vypnutá akce (Active = ❌) = akce se vůbec nezapíše do activity_log.
    </div>

    <!-- Tabs s rolemi -->
    <div class="as-tabs">
        <?php foreach ($roleLabels as $slug => $label): ?>
            <a href="?role=<?= urlencode($slug) ?>" class="as-tab <?= $slug === $role ? 'active' : '' ?>">
                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="as-card">
        <form method="post" action="/admin/activity-scoring/save">
            <input type="hidden" name="<?= crm_csrf_field_name() ?>" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="role" value="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>">

            <?php if ($rules === []): ?>
                <p style="color:#9ca3af; text-align:center; padding:2rem;">
                    Pro roli <strong><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?></strong>
                    nejsou žádná pravidla. Spusť migraci 035 a refresh stránku.
                </p>
            <?php else: ?>
                <table class="as-table">
                    <thead>
                        <tr>
                            <th class="label-col">Akce</th>
                            <th class="type-col">Slug (technický)</th>
                            <th style="text-align:right;">Body</th>
                            <th style="text-align:center;">Aktivní</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rules as $r): ?>
                            <tr>
                                <td class="label-col">
                                    <strong><?= htmlspecialchars((string) $r['action_label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                </td>
                                <td class="type-col">
                                    <code><?= htmlspecialchars((string) $r['action_type'], ENT_QUOTES, 'UTF-8') ?></code>
                                </td>
                                <td style="text-align:right;">
                                    <input type="number" min="0" max="1000" step="1"
                                           name="points_<?= (int) $r['id'] ?>"
                                           value="<?= (int) $r['points'] ?>">
                                </td>
                                <td style="text-align:center;">
                                    <input type="checkbox" name="active_<?= (int) $r['id'] ?>" value="1"
                                           <?= (int) $r['active'] === 1 ? 'checked' : '' ?>>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="margin-top:1rem; text-align:right;">
                    <button class="btn-primary" type="submit">💾 Uložit změny</button>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>
