<?php
// e:\Snecinatripu\app\views\admin\users\form.php
declare(strict_types=1);
/** @var array<string, mixed> $actor */
/** @var array<string, mixed>|null $editUser */
/** @var list<string> $userRegions */
/** @var list<string> $roleOptions */
/** @var string|null $flash */
/** @var string $csrf */
$isEdit       = is_array($editUser);
$action       = $isEdit ? crm_url('/admin/users/save') : crm_url('/admin/users/new');
$choices      = crm_region_choices();
$currentRole  = $isEdit ? (string) ($editUser['role'] ?? '') : '';
// Region pole má smysl jen pro OZ (obchodák) — má region-based rotation lead.
// Ostatní role (čistička, navolávačka, BO, admin) si region přepínají samy ve své view.
$showRegions  = ($currentRole === 'obchodak');
?>
<section class="card">
    <h1><?= $isEdit ? 'Upravit uživatele' : 'Nový uživatel' ?></h1>
    <?php if (!empty($flash)) { ?>
        <p class="alert"><?= crm_h($flash) ?></p>
    <?php } ?>
    <form method="post" action="<?= crm_h($action) ?>" class="form">
        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
        <?php if ($isEdit) { ?>
            <input type="hidden" name="id" value="<?= (int) ($editUser['id'] ?? 0) ?>">
        <?php } ?>

        <label for="jmeno">Jméno</label>
        <input id="jmeno" name="jmeno" required maxlength="255" value="<?= crm_h((string) ($editUser['jmeno'] ?? '')) ?>">

        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" required maxlength="255" value="<?= crm_h((string) ($editUser['email'] ?? '')) ?>">

        <label for="role">Primární role</label>
        <select id="role" name="role" required>
            <?php foreach ($roleOptions as $r) { ?>
                <option value="<?= crm_h($r) ?>"<?= ($isEdit && ($currentRole === $r)) ? ' selected' : '' ?>><?= crm_h($r) ?></option>
            <?php } ?>
        </select>
        <p style="font-size:0.78rem; color:var(--muted); margin:0.3rem 0 0.5rem; line-height:1.4;">
            💡 Primární role je výchozí — pro single-role uživatele jediná. Pokud uživatel zastává <strong>víc rolí</strong>
            (např. majitel + obchodák), zaškrtni další níže.
        </p>

        <fieldset class="fieldset" style="margin-top: 0.6rem;">
            <legend>🔄 Další role (multi-role) — uživatel si po loginu vybere kterou aktivně používá</legend>
            <?php
            // Aktuální extra role z DB (JSON array)
            $currentExtras = [];
            $rawExtras = $editUser['roles_extra'] ?? null;
            if (is_string($rawExtras) && $rawExtras !== '') {
                $decoded = json_decode($rawExtras, true);
                if (is_array($decoded)) $currentExtras = array_values(array_filter($decoded, 'is_string'));
            }
            foreach ($roleOptions as $r) {
                $checked = in_array($r, $currentExtras, true) ? 'checked' : '';
            ?>
                <label style="display:inline-flex; align-items:center; gap:0.4rem; margin-right:0.8rem; font-weight:400;">
                    <input type="checkbox" name="roles_extra[]" value="<?= crm_h($r) ?>" <?= $checked ?>>
                    <?= crm_h($r) ?>
                </label>
            <?php } ?>
            <p style="font-size:0.72rem; color:var(--muted); margin-top:0.4rem;">
                Tip: nezaškrtávej tu samou roli jakou má jako primární — ta je už přiřazená.
                Při loginu user uvidí výběr „Jako kým chceš pracovat?".
            </p>
        </fieldset>

        <!--
            Region pole (Primární + Povolené) — VIDITELNÉ POUZE PRO ROLE = obchodak.
            Ostatní role kontakty filtrují/přepínají samy ve své view (cisticka/caller),
            takže admin tady nic nenastavuje. Hodnoty zůstávají v DB i když je form
            skryje (přepínání role neztratí původní region config).
        -->
        <div id="oz-region-group" data-show-for="obchodak" style="<?= $showRegions ? '' : 'display:none;' ?>">
            <p style="font-size:0.78rem;color:var(--muted);margin:0.4rem 0 0.6rem;line-height:1.5;">
                💡 <strong>Pouze pro OZ (obchodák):</strong> nastavuje se zde, protože systém
                automaticky přiděluje leady (FOR_SALES) podle <em>primárního regionu</em>.
                Ostatní role (čistička, navolávačka) si region přepínají samy ve svém panelu.
            </p>

            <label for="primary_region">Primární region (auto-rotation leadů)</label>
            <select id="primary_region" name="primary_region">
                <option value="">—</option>
                <?php foreach ($choices as $c) { ?>
                    <option value="<?= crm_h($c) ?>"<?= ($isEdit && (($editUser['primary_region'] ?? '') === $c)) ? ' selected' : '' ?>><?= crm_h(crm_region_label($c)) ?></option>
                <?php } ?>
            </select>

            <fieldset class="fieldset">
                <legend>Povolené regiony (které kraje OZ vidí v UI)</legend>
                <?php foreach ($choices as $c) { ?>
                    <label class="check"><input type="checkbox" name="regions[]" value="<?= crm_h($c) ?>"<?= in_array($c, $userRegions, true) ? ' checked' : '' ?>> <?= crm_h(crm_region_label($c)) ?></label>
                <?php } ?>
            </fieldset>
        </div>

        <?php if ($isEdit && (int) ($editUser['totp_enabled'] ?? 0) === 1) { ?>
            <label class="check"><input type="checkbox" name="disable_2fa" value="1"> Vypnout 2FA (administrátorský zásah)</label>
        <?php } ?>

        <div style="display:flex; gap:0.6rem; justify-content:center; margin-top:0.6rem;">
            <button type="submit" class="btn" style="min-width:160px;"><?= $isEdit ? 'Uložit' : 'Vytvořit' ?></button>
            <a class="btn btn-secondary" style="min-width:120px; text-align:center;" href="<?= crm_h(crm_url('/admin/users')) ?>">Zpět</a>
        </div>
    </form>
</section>

<script>
// Region pole se zobrazí jen když je role = obchodak.
// Hodnoty se v DB zachovají i při skrytí (odeslány vždy), takže přepnutí role
// na non-OZ a zpět na OZ neztratí původní nastavení.
(function () {
    var roleSel = document.getElementById('role');
    var group   = document.getElementById('oz-region-group');
    if (!roleSel || !group) return;
    var showFor = group.dataset.showFor || 'obchodak';
    function sync() {
        group.style.display = (roleSel.value === showFor) ? '' : 'none';
    }
    roleSel.addEventListener('change', sync);
    sync();
})();
</script>
