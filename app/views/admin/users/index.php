<?php
// e:\Snecinatripu\app\views\admin\users\index.php
declare(strict_types=1);
/** @var array<string, mixed> $actor */
/** @var list<array<string, mixed>> $users */
/** @var list<array<string, mixed>> $callers */
/** @var list<array<string, mixed>> $salesmen */
/** @var string|null $flash */
/** @var string $csrf */

// Seřadit uživatele do skupin
$groups = [
    'majitel'    => [],
    'superadmin' => [],
    'backoffice' => [],
    'cisticka'   => [],
    'navolavacka'=> [],
    'obchodak'   => [],
];
foreach ($users as $u) {
    $r = (string) ($u['role'] ?? '');
    if (isset($groups[$r])) {
        $groups[$r][] = $u;
    }
}

$roleMeta = [
    'majitel'     => ['label' => 'Majitel',     'color' => '#f0a030', 'icon' => '👑'],
    'superadmin'  => ['label' => 'Super Admin',  'color' => '#9b59b6', 'icon' => '🛡️'],
    'backoffice'  => ['label' => 'Back Office',  'color' => '#17a2b8', 'icon' => '🗂️'],
    'cisticka'    => ['label' => 'Čistička',     'color' => '#1abc9c', 'icon' => '🔍'],
    'navolavacka' => ['label' => 'Navolávačka',  'color' => '#2ecc71', 'icon' => '📞'],
    'obchodak'    => ['label' => 'Obchodák',     'color' => '#3498db', 'icon' => '💼'],
];

/**
 * Vykreslí kartu jednoho uživatele.
 * @param array<string, mixed> $u
 * @param list<array<string, mixed>> $callers
 * @param list<array<string, mixed>> $salesmen
 */
function renderUserCard(array $u, array $callers, array $salesmen, array $actor, string $csrf): void
{
    $uid    = (int) ($u['id'] ?? 0);
    $aktivni = (int) ($u['aktivni'] ?? 0) === 1;
    $role   = (string) ($u['role'] ?? '');
    $canManage = crm_users_actor_can_manage_target((string) $actor['role'], $u);
    $isSelf = $uid === (int) ($actor['id'] ?? 0);
    $isSuperadmin = (string) ($actor['role'] ?? '') === 'superadmin';
    ?>
    <div class="ucard <?= $aktivni ? '' : 'ucard--inactive' ?>">
        <div class="ucard__info">
            <div class="ucard__name"><?= crm_h((string) ($u['jmeno'] ?? '')) ?></div>
            <div class="ucard__email"><?= crm_h((string) ($u['email'] ?? '')) ?></div>
            <?php if (!$aktivni) { ?>
                <span class="ucard__badge ucard__badge--inactive">Deaktivovaný</span>
            <?php } ?>
        </div>
        <div class="ucard__actions">
            <?php if ($canManage) { ?>
                <a href="<?= crm_h(crm_url('/admin/users/edit?id=' . $uid)) ?>" class="btn btn-secondary btn-sm">Upravit</a>
            <?php } ?>
            <?php if ($aktivni && $canManage && !$isSelf) { ?>
                <form method="post" action="<?= crm_h(crm_url('/admin/users/reset-password')) ?>"
                      class="inline-form"
                      onsubmit="return confirm('Odeslat nové heslo?');">
                    <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                    <input type="hidden" name="id" value="<?= $uid ?>">
                    <button type="submit" class="btn btn-secondary btn-sm">Reset hesla</button>
                </form>
            <?php } ?>
            <?php if ($isSuperadmin && !$isSelf) { ?>
                <form method="post" action="<?= crm_h(crm_url('/admin/users/delete')) ?>"
                      class="inline-form ucard-delete-form" data-uid="<?= $uid ?>">
                    <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                    <input type="hidden" name="id" value="<?= $uid ?>">
                    <button type="button" class="btn btn-danger-ghost btn-sm ucard-delete-btn"
                            data-uid="<?= $uid ?>"
                            data-name="<?= crm_h((string)($u['jmeno'] ?? '')) ?>"
                            title="Trvale smazat uživatele"
                            onclick="userDeleteToggle(this)">🗑</button>
                </form>
            <?php } ?>
        </div>
        <?php if ($aktivni && $canManage && !$isSelf) { ?>
        <div class="ucard__deactivate">
            <form method="post" action="<?= crm_h(crm_url('/admin/users/deactivate')) ?>"
                  class="deactivate-form"
                  onsubmit="return confirm('Opravdu deaktivovat tohoto uživatele?');">
                <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                <input type="hidden" name="id" value="<?= $uid ?>">
                <?php if ($role === 'navolavacka') { ?>
                    <label class="ucard__reassign-label">Přeřadit kontakty na:
                        <select name="reassign_caller_to" class="ucard__reassign-select">
                            <option value="0">— ponechat —</option>
                            <?php foreach ($callers as $c) {
                                $cid = (int) ($c['id'] ?? 0);
                                if ($cid === $uid) continue; ?>
                                <option value="<?= $cid ?>"><?= crm_h((string) ($c['jmeno'] ?? '')) ?></option>
                            <?php } ?>
                        </select>
                    </label>
                <?php } elseif ($role === 'obchodak') { ?>
                    <label class="ucard__reassign-label">Přeřadit výhry na:
                        <select name="reassign_sales_to" class="ucard__reassign-select">
                            <option value="0">— ponechat —</option>
                            <?php foreach ($salesmen as $s) {
                                $sid = (int) ($s['id'] ?? 0);
                                if ($sid === $uid) continue; ?>
                                <option value="<?= $sid ?>"><?= crm_h((string) ($s['jmeno'] ?? '')) ?></option>
                            <?php } ?>
                        </select>
                    </label>
                <?php } ?>
                <button type="submit" class="btn btn-danger btn-sm">Deaktivovat</button>
            </form>
        </div>
        <?php } ?>
    </div>
    <?php
}
?>

<style>
/* ── Správa uživatelů ── */
.users-toolbar {
    display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1.5rem;
}

/* Sekce role (management) */
.role-section {
    margin-bottom: 1.2rem;
}
.role-section__header {
    display: flex; align-items: center; gap: 0.5rem;
    font-size: 0.75rem; font-weight: 700; letter-spacing: 0.06em;
    text-transform: uppercase; color: var(--muted);
    margin-bottom: 0.5rem; padding-bottom: 0.35rem;
    border-bottom: 2px solid;
}
.role-section--empty { display: none; }

/* Dvousloupcový layout */
.users-two-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.2rem;
    margin-bottom: 1.2rem;
}
@media (max-width: 700px) {
    .users-two-col { grid-template-columns: 1fr; }
}
.col-panel {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 10px;
    padding: 0.9rem 1rem 0.6rem;
}
.col-panel__header {
    display: flex; align-items: center; gap: 0.5rem;
    font-size: 0.75rem; font-weight: 700; letter-spacing: 0.06em;
    text-transform: uppercase; margin-bottom: 0.75rem;
    padding-bottom: 0.4rem; border-bottom: 2px solid;
}

/* Karta uživatele */
.ucard {
    background: var(--card);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 8px;
    padding: 0.65rem 0.85rem;
    margin-bottom: 0.5rem;
    transition: border-color 0.15s;
}
.ucard:hover { border-color: rgba(255,255,255,0.15); }
.ucard--inactive { opacity: 0.45; }
.ucard__info { margin-bottom: 0.4rem; }
.ucard__name {
    font-weight: 600; font-size: 0.9rem; color: var(--text);
}
.ucard__email {
    font-size: 0.75rem; color: var(--muted); margin-top: 0.1rem;
}
.ucard__badge--inactive {
    display: inline-block; font-size: 0.65rem; background: rgba(231,76,60,0.2);
    color: #e74c3c; border-radius: 4px; padding: 0.1rem 0.4rem; margin-top: 0.2rem;
}
.ucard__actions {
    display: flex; flex-wrap: wrap; gap: 0.35rem; margin-bottom: 0.3rem;
}
.ucard__deactivate {
    border-top: 1px solid rgba(255,255,255,0.06);
    padding-top: 0.4rem; margin-top: 0.3rem;
}
/* Smazat trvale — malé ikonkové tlačítko (2-step inline confirm) */
.btn-danger-ghost {
    background: transparent;
    color: #c5535b;
    border: 1px solid rgba(231,76,60,0.4);
    padding: 0.3rem 0.55rem;
    font-size: 0.85rem;
    line-height: 1;
}
.btn-danger-ghost:hover {
    background: rgba(231,76,60,0.12);
    color: #e74c3c;
    border-color: rgba(231,76,60,0.7);
}
.btn-danger-ghost.confirm-armed {
    background: #e74c3c;
    color: #fff;
    border-color: #e74c3c;
    padding: 0.3rem 0.7rem;
    font-size: 0.78rem;
    font-weight: 700;
    animation: ucard-armed-pulse 0.6s ease-in-out infinite alternate;
}
@keyframes ucard-armed-pulse {
    from { box-shadow: 0 0 0 0 rgba(231,76,60,0.5); }
    to   { box-shadow: 0 0 0 4px rgba(231,76,60,0.15); }
}
.ucard__deactivate .deactivate-form {
    display: flex; flex-wrap: wrap; align-items: center; gap: 0.4rem;
}
.ucard__reassign-label {
    font-size: 0.72rem; color: var(--muted);
    display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap;
}
.ucard__reassign-select {
    font-size: 0.72rem; padding: 0.2rem 0.4rem;
    background: var(--bg); color: var(--text);
    border: 1px solid rgba(255,255,255,0.15); border-radius: 4px;
}
</style>

<section class="card">
    <h1>Správa uživatelů</h1>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info"><?= crm_h($flash) ?></p>
    <?php } ?>

    <div class="users-toolbar">
        <a class="btn" href="<?= crm_h(crm_url('/admin/users/new')) ?>">+ Nový uživatel</a>
        <a class="btn btn-secondary" href="<?= crm_h(crm_url('/admin/import')) ?>">CSV import kontaktů</a>
        <a class="btn btn-secondary" href="<?= crm_h(crm_url('/admin/oz-targets')) ?>">🎯 Kvóty OZ</a>
        <a class="btn btn-secondary" href="<?= crm_h(crm_url('/dashboard')) ?>">Zpět na dashboard</a>
    </div>

    <!-- ── Management: Majitel → Superadmin → Backoffice → Čistička ── -->
    <?php foreach (['majitel', 'superadmin', 'backoffice', 'cisticka'] as $role) {
        $meta  = $roleMeta[$role];
        $usersInGroup = $groups[$role];
        $hasUsers = $usersInGroup !== [];
    ?>
    <div class="role-section <?= $hasUsers ? '' : 'role-section--empty' ?>">
        <div class="role-section__header" style="border-color:<?= $meta['color'] ?>;color:<?= $meta['color'] ?>;">
            <span><?= $meta['icon'] ?></span>
            <span><?= $meta['label'] ?></span>
            <span style="opacity:.5;font-weight:400;">(<?= count($usersInGroup) ?>)</span>
        </div>
        <?php foreach ($usersInGroup as $u) {
            renderUserCard($u, $callers, $salesmen, $actor, $csrf);
        } ?>
        <?php if (!$hasUsers) { ?>
            <p class="muted" style="font-size:0.8rem;margin:0;">Žádní uživatelé.</p>
        <?php } ?>
    </div>
    <?php } ?>

    <!-- ── Dvousloupcový layout: Navolávačky | Obchodáci ── -->
    <div class="users-two-col">

        <!-- Navolávačky -->
        <div class="col-panel">
            <div class="col-panel__header" style="border-color:#2ecc71;color:#2ecc71;">
                <span>📞</span>
                <span>Navolávačky</span>
                <span style="opacity:.5;font-weight:400;">(<?= count($groups['navolavacka']) ?>)</span>
            </div>
            <?php if ($groups['navolavacka'] === []) { ?>
                <p class="muted" style="font-size:0.8rem;">Žádné navolávačky.</p>
            <?php } ?>
            <?php foreach ($groups['navolavacka'] as $u) {
                renderUserCard($u, $callers, $salesmen, $actor, $csrf);
            } ?>
        </div>

        <!-- Obchodáci -->
        <div class="col-panel">
            <div class="col-panel__header" style="border-color:#3498db;color:#3498db;">
                <span>💼</span>
                <span>Obchodáci</span>
                <span style="opacity:.5;font-weight:400;">(<?= count($groups['obchodak']) ?>)</span>
            </div>
            <?php if ($groups['obchodak'] === []) { ?>
                <p class="muted" style="font-size:0.8rem;">Žádní obchodáci.</p>
            <?php } ?>
            <?php foreach ($groups['obchodak'] as $u) {
                renderUserCard($u, $callers, $salesmen, $actor, $csrf);
            } ?>
        </div>

    </div>

</section>

<script>
// 2-step inline confirm pro Smazat trvale (žádný native confirm dialog)
(function () {
    const ARM_TIMEOUT_MS = 5000;
    const armed = new Map(); // uid → timeoutId

    window.userDeleteToggle = function (btn) {
        const uid  = btn.dataset.uid;
        const name = btn.dataset.name || 'uživatele';

        if (armed.has(uid)) {
            // Druhý klik → submit form
            clearTimeout(armed.get(uid));
            armed.delete(uid);
            const form = btn.closest('form.ucard-delete-form');
            if (form) form.submit();
            return;
        }

        // První klik → "armed" stav s odpočtem
        btn.classList.add('confirm-armed');
        btn.innerHTML = '⚠ Klikni znovu — smazat „' + name + '"';
        const tid = setTimeout(() => {
            btn.classList.remove('confirm-armed');
            btn.innerHTML = '🗑';
            armed.delete(uid);
        }, ARM_TIMEOUT_MS);
        armed.set(uid, tid);
    };
})();
</script>
