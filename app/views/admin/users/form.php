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
// Roles_extra (multi-role) — pokud má user obchodaka jako přídruženou roli,
// region sekce se musí taky zobrazit (jinak admin nemůže nastavit region).
$rolesExtraRaw = $isEdit ? (string) ($editUser['roles_extra'] ?? '[]') : '[]';
$rolesExtraArr = [];
if ($rolesExtraRaw !== '' && $rolesExtraRaw !== '[]') {
    $decoded = json_decode($rolesExtraRaw, true);
    if (is_array($decoded)) {
        $rolesExtraArr = array_map('strval', $decoded);
    }
}
// Region pole se ukáže pro:
//   - OZ (obchodák): primární region + povolené (auto-rotation FOR_SALES)
//   - Navolávačka: povolené regiony omezují, ve kterých krajích vidí kontakty v queue
//   - Multi-role kombinace: stejně pro obě
$showRegions  = in_array($currentRole, ['obchodak', 'navolavacka'], true)
             || in_array('obchodak', $rolesExtraArr, true)
             || in_array('navolavacka', $rolesExtraArr, true);
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
                💡 <strong>Regionální omezení:</strong>
                <br>• <strong>OZ (obchodák):</strong> primární region určuje auto-rotation leadů (FOR_SALES).
                Povolené regiony omezují, které kraje OZ vidí v UI.
                <br>• <strong>Navolávačka:</strong> povolené regiony určují, ve kterých krajích vidí kontakty ve své frontě.
                Pokud nezaškrtneš nic, vidí všechny kraje, kde má kontakty.
                <br>• <strong>Čistička:</strong> region picker v jejím panelu — tady se nenastavuje.
            </p>

            <label for="primary_region">Primární region (jen pro OZ — auto-rotation FOR_SALES leadů)</label>
            <select id="primary_region" name="primary_region">
                <option value="">—</option>
                <?php foreach ($choices as $c) { ?>
                    <option value="<?= crm_h($c) ?>"<?= ($isEdit && (($editUser['primary_region'] ?? '') === $c)) ? ' selected' : '' ?>><?= crm_h(crm_region_label($c)) ?></option>
                <?php } ?>
            </select>

            <fieldset class="fieldset">
                <legend>Povolené regiony (které kraje uvidí v UI — pro OZ i navolávačku)</legend>
                <?php foreach ($choices as $c) { ?>
                    <label class="check"><input type="checkbox" name="regions[]" value="<?= crm_h($c) ?>"<?= in_array($c, $userRegions, true) ? ' checked' : '' ?>> <?= crm_h(crm_region_label($c)) ?></label>
                <?php } ?>
            </fieldset>
        </div>

        <!--
            Subject type preference — VIDITELNÉ POUZE PRO ROLE = navolavacka.
            Některé navolávačky chtějí volat jen firmy, jiné jen OSVČ.
            Default 'any' = navolávačka dostane oboje (= chování jako dosud).
        -->
        <?php
        $subjectPref = $isEdit ? (string) ($editUser['subject_type_pref'] ?? 'any') : 'any';
        $showSubjectPref = ($currentRole === 'navolavacka') || in_array('navolavacka', $rolesExtraArr, true);
        ?>
        <div id="caller-subject-group" data-show-for="navolavacka" style="<?= $showSubjectPref ? '' : 'display:none;' ?>">
            <p style="font-size:0.78rem;color:var(--muted);margin:0.8rem 0 0.4rem;line-height:1.5;">
                🎯 <strong>Pouze pro navolávačku:</strong> filtr typu subjektu v frontě k provolání.
                Některé navolávačky preferují jen firmy (s.r.o., a.s.), jiné jen OSVČ.
                Mix poměr (globální) zůstává — tohle je jen filter, který kontakty navolávačka uvidí.
            </p>
            <label for="subject_type_pref">Co chce volat</label>
            <select id="subject_type_pref" name="subject_type_pref">
                <option value="any"   <?= $subjectPref === 'any'   ? 'selected' : '' ?>>🌐 Vše (default)</option>
                <option value="firma" <?= $subjectPref === 'firma' ? 'selected' : '' ?>>🏢 Jen firmy (s.r.o., a.s., …)</option>
                <option value="osvc"  <?= $subjectPref === 'osvc'  ? 'selected' : '' ?>>👤 Jen OSVČ</option>
            </select>
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
// Region pole se zobrazí, pokud má user obchodaka buď JAKO PRIMÁRNÍ roli,
// NEBO jako přídruženou v roles_extra (multi-role). Hodnoty se v DB zachovají
// i při skrytí (odeslány vždy), takže přepnutí role na non-OZ a zpět na OZ
// neztratí původní nastavení.
(function () {
    var roleSel = document.getElementById('role');
    var group   = document.getElementById('oz-region-group');
    if (!roleSel || !group) return;

    // Region sekce platí pro obchodak NEBO navolavacka (primary i extra)
    var extraObchodakCb   = document.querySelector('input[type="checkbox"][name="roles_extra[]"][value="obchodak"]');
    var extraNavolavCb    = document.querySelector('input[type="checkbox"][name="roles_extra[]"][value="navolavacka"]');

    function shouldShow() {
        var r = roleSel.value;
        if (r === 'obchodak' || r === 'navolavacka') return true;
        if (extraObchodakCb && extraObchodakCb.checked) return true;
        if (extraNavolavCb  && extraNavolavCb.checked)  return true;
        return false;
    }
    function sync() {
        group.style.display = shouldShow() ? '' : 'none';
    }
    roleSel.addEventListener('change', sync);
    if (extraObchodakCb) extraObchodakCb.addEventListener('change', sync);
    if (extraNavolavCb)  extraNavolavCb.addEventListener('change', sync);
    sync();
})();

// Subject type pref — viditelné pro navolávačku (primary nebo extra)
(function () {
    var roleSel = document.getElementById('role');
    var group   = document.getElementById('caller-subject-group');
    if (!roleSel || !group) return;
    var showFor = group.dataset.showFor || 'navolavacka';
    var extraCb = document.querySelector('input[type="checkbox"][name="roles_extra[]"][value="navolavacka"]');

    function isActive() {
        if (roleSel.value === showFor) return true;
        if (extraCb && extraCb.checked)  return true;
        return false;
    }
    function sync() {
        group.style.display = isActive() ? '' : 'none';
    }
    roleSel.addEventListener('change', sync);
    if (extraCb) extraCb.addEventListener('change', sync);
    sync();
})();
</script>
