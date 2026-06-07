<?php
// e:\Snecinatripu\app\views\contact-proposals\new.php
declare(strict_types=1);
/** @var array<string,mixed>             $user */
/** @var string                          $csrf */
/** @var ?string                         $flash */
/** @var array<string,string>            $regions      kód → label */
/** @var list<array<string,mixed>>       $salesUsers   aktivní OZ pro dropdown */
/** @var list<string>                    $operators    whitelist operátorů */
/** @var array<string,mixed>             $form         re-fill data po validační chybě */

// Detekce: má user roli OZ (primární nebo extra)?
$_userIsOz = false;
if ((string) ($user['role'] ?? '') === 'obchodak') { $_userIsOz = true; }
else {
    $_extra = $user['roles_extra'] ?? null;
    if (is_string($_extra)) { $_extra = json_decode($_extra, true) ?: []; }
    if (is_array($_extra) && in_array('obchodak', $_extra, true)) { $_userIsOz = true; }
}
$_userId = (int) ($user['id'] ?? 0);

// Default zvolený OZ: pokud OZ → sebe; jinak re-fill z form data
$_defaultOzId = (int) ($form['suggested_oz_id'] ?? 0);
if ($_defaultOzId <= 0 && $_userIsOz) { $_defaultOzId = $_userId; }
?>

<style>
.cp-card { max-width: 720px; margin: 0 auto; }
.cp-card h1 { margin: 0 0 0.4rem; font-size: 1.4rem; }
.cp-card .lead {
    color: var(--color-text-muted);
    font-size: 0.85rem;
    margin-bottom: 1.2rem;
    line-height: 1.5;
}
.cp-card .lead strong { color: var(--color-text); }

.cp-form { display: grid; gap: 0.85rem; }
.cp-form__row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.85rem;
}
@media (max-width: 600px) {
    .cp-form__row { grid-template-columns: 1fr; }
}
.cp-form label {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--color-text);
}
.cp-form label .req { color: #e74c3c; margin-left: 2px; }
.cp-form label .hint {
    font-size: 0.7rem;
    font-weight: 400;
    color: var(--color-text-muted);
    margin-top: 0.15rem;
}
.cp-form input[type="text"],
.cp-form input[type="email"],
.cp-form input[type="tel"],
.cp-form select,
.cp-form textarea {
    background: #ffffff;
    color: var(--color-text);
    border: 1px solid var(--color-border-strong);
    border-radius: 5px;
    padding: 0.45rem 0.6rem;
    font-size: 0.9rem;
    font-family: var(--font-main);
}
.cp-form textarea { min-height: 90px; resize: vertical; }

.cp-info-box {
    background: var(--color-badge-nove-bg);
    border: 1px solid #b5d4f4;
    border-left: 4px solid var(--color-badge-nove);
    border-radius: 0 6px 6px 0;
    padding: 0.65rem 0.85rem;
    font-size: 0.78rem;
    color: var(--color-badge-nove-text);
    margin-bottom: 1rem;
}

.cp-dup-warn {
    display: none;
    background: #fef3c7;
    border: 1px solid #fcd34d;
    border-left: 4px solid #f59e0b;
    border-radius: 0 6px 6px 0;
    padding: 0.7rem 0.9rem;
    font-size: 0.83rem;
    color: #78350f;
    margin: 0.4rem 0 0;
    line-height: 1.5;
}
.cp-dup-warn strong { color: #92400e; }
.cp-dup-warn a { color: #b45309; text-decoration: underline; }
.cp-dup-warn .cp-dup-checkbox-row {
    margin-top: 0.55rem;
    padding-top: 0.45rem;
    border-top: 1px dashed rgba(120, 53, 15, 0.3);
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.cp-ico-status {
    font-size: 0.7rem;
    font-weight: 400;
    color: var(--color-text-muted);
    margin-top: 0.15rem;
    min-height: 0.9rem;
}

.cp-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.5rem;
    flex-wrap: wrap;
}
.cp-btn-primary {
    background: var(--color-badge-nove);
    color: #fff;
    border: 1px solid var(--color-badge-nove);
    border-radius: 5px;
    padding: 0.5rem 1.1rem;
    font-size: 0.88rem;
    font-weight: 600;
    cursor: pointer;
    font-family: var(--font-main);
}
.cp-btn-primary:hover { filter: brightness(0.95); }
</style>

<section class="card cp-card">
    <h1>➕ Nový kontakt</h1>
    <p class="lead">
        Kontakt se uloží <strong>okamžitě</strong> a přiřadí vybranému OZ —
        objeví se mu v <strong>Příchozí leady</strong> (sekce „📥 Příchozí leady"),
        odkud ho jedním klikem na <strong>„Přijmout"</strong> vezme do své pracovní plochy.
        Navolávačka se tím přeskakuje.
        <?php if ($_userIsOz) { ?>
            Jako OZ máš sebe přednastavené, ale můžeš přiřadit kolegovi.
        <?php } else { ?>
            Vyber, komu má kontakt patřit.
        <?php } ?>
    </p>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info" style="margin-bottom:1rem;"><?= crm_h($flash) ?></p>
    <?php } ?>

    <div class="cp-info-box">
        🚀 Pole označená <span style="color:#e74c3c;">*</span> jsou povinná.
        Po vyplnění IČO ověříme, jestli už kontakt nemáme v databázi.
    </div>

    <form method="post" action="<?= crm_h(crm_url('/contacts/new')) ?>" class="cp-form" autocomplete="off" id="cp-form">
        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">

        <!-- Firma + IČO -->
        <div class="cp-form__row">
            <label>
                Firma <span class="req">*</span>
                <input type="text" name="firma" required maxlength="500"
                       value="<?= crm_h((string)($form['firma'] ?? '')) ?>"
                       placeholder="Název s.r.o. / Jan Novák (živnostník)">
                <span class="hint">Pro živnostníka uveďte jméno a příjmení.</span>
            </label>
            <label>
                IČO <span class="req">*</span>
                <input type="text" name="ico" required maxlength="20"
                       id="cp-ico"
                       value="<?= crm_h((string)($form['ico'] ?? '')) ?>"
                       placeholder="8 číslic">
                <span class="cp-ico-status" id="cp-ico-status"></span>
            </label>
        </div>

        <!-- Duplicita warning (skryté, JS odhalí když najde) -->
        <div class="cp-dup-warn" id="cp-dup-warn">
            <strong>⚠ Tento IČO už v databázi máme.</strong><br>
            <span id="cp-dup-info">…</span>
            <div class="cp-dup-checkbox-row">
                <input type="checkbox" name="allow_duplicate" value="1" id="cp-allow-dup">
                <label for="cp-allow-dup" style="font-weight:500;cursor:pointer;font-size:0.78rem;">
                    Přidat přesto i jako duplicitu (pokud opravdu chceš)
                </label>
            </div>
        </div>

        <!-- Telefon + Email -->
        <div class="cp-form__row">
            <label>
                Telefon <span class="req">*</span>
                <input type="tel" name="telefon" required maxlength="50"
                       value="<?= crm_h((string)($form['telefon'] ?? '')) ?>"
                       placeholder="+420 …">
            </label>
            <label>
                E-mail <span class="req">*</span>
                <input type="email" name="email" required maxlength="255"
                       value="<?= crm_h((string)($form['email'] ?? '')) ?>"
                       placeholder="kontakt@firma.cz">
            </label>
        </div>

        <!-- Adresa -->
        <label>
            Adresa <span class="req">*</span>
            <input type="text" name="adresa" required maxlength="500"
                   value="<?= crm_h((string)($form['adresa'] ?? '')) ?>"
                   placeholder="Ulice 123, Město, PSČ">
        </label>

        <!-- Kraj + Operátor -->
        <div class="cp-form__row">
            <label>
                Kraj <span class="req">*</span>
                <select name="region" required>
                    <option value="">— vyberte kraj —</option>
                    <?php
                    $selRegion = (string)($form['region'] ?? '');
                    foreach ($regions as $rc) {
                        $sel = $rc === $selRegion ? 'selected' : '';
                        echo '<option value="' . crm_h($rc) . '" ' . $sel . '>'
                           . crm_h(crm_region_label($rc)) . '</option>';
                    }
                    ?>
                </select>
            </label>
            <label>
                Operátor <span class="req">*</span>
                <select name="operator" required>
                    <option value="">— vyberte operátora —</option>
                    <?php
                    $selOp = (string)($form['operator'] ?? '');
                    foreach ($operators as $op) {
                        $sel = $op === $selOp ? 'selected' : '';
                        echo '<option value="' . crm_h($op) . '" ' . $sel . '>' . crm_h($op) . '</option>';
                    }
                    ?>
                </select>
                <span class="hint">Aktuální poskytovatel zákazníka.</span>
            </label>
        </div>

        <!-- Přiřadit OZ — povinný pro všechny -->
        <label>
            Přiřadit OZ <span class="req">*</span>
            <select name="suggested_oz_id" required>
                <option value="0">— vyberte OZ —</option>
                <?php foreach ($salesUsers as $oz) {
                    $oid = (int)($oz['id'] ?? 0);
                    $sel = $oid === $_defaultOzId ? 'selected' : '';
                    $isSelf = $_userIsOz && $oid === $_userId;
                    $label = (string)($oz['jmeno'] ?? '') . ($isSelf ? ' (já)' : '');
                ?>
                    <option value="<?= $oid ?>" <?= $sel ?>><?= crm_h($label) ?></option>
                <?php } ?>
            </select>
            <span class="hint">
                <?php if ($_userIsOz) { ?>
                    Kontakt se přiřadí tomuto OZ — uvidí ho v Příchozí leady.
                    Sebe můžeš změnit na kolegu.
                <?php } else { ?>
                    Komu se kontakt přiřadí. Objeví se mu okamžitě v Příchozí leady.
                <?php } ?>
            </span>
        </label>

        <!-- Poznámka -->
        <label>
            Poznámka <span class="req">*</span>
            <textarea name="poznamka" required maxlength="1000"
                      placeholder="Proč je to hot lead? Doporučení od kterého klienta? Jaký je kontext?"><?= crm_h((string)($form['poznamka'] ?? '')) ?></textarea>
            <span class="hint">OZ-ovi pomůže při prvním navázání kontaktu.</span>
        </label>

        <div class="cp-actions">
            <button type="submit" class="cp-btn-primary">🚀 Přidat kontakt</button>
        </div>
    </form>
</section>

<script>
(function () {
    'use strict';
    // ── Live IČO duplicita check ─────────────────────────────────────
    // Po 600ms ticha (debounce) zavolá /contacts/check-ico?ico=...
    // Pokud najde existující kontakt → zobrazí warning + checkbox „Přesto přidat".
    var icoInput    = document.getElementById('cp-ico');
    var statusEl    = document.getElementById('cp-ico-status');
    var warnBox     = document.getElementById('cp-dup-warn');
    var infoEl      = document.getElementById('cp-dup-info');
    var allowDupCb  = document.getElementById('cp-allow-dup');
    if (!icoInput) return;

    var debounceTimer = null;
    var lastChecked   = '';

    function digitsOnly(s) { return (s || '').replace(/\D+/g, ''); }
    function hideWarn() {
        warnBox.style.display = 'none';
        allowDupCb.checked = false;
    }

    function performCheck() {
        var ico = digitsOnly(icoInput.value);
        if (ico.length !== 8) {
            statusEl.textContent = '';
            hideWarn();
            return;
        }
        if (ico === lastChecked) return;
        lastChecked = ico;
        statusEl.textContent = '⏳ Ověřuji v databázi…';

        fetch('<?= crm_h(crm_url('/contacts/check-ico')) ?>?ico=' + encodeURIComponent(ico), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.found) {
                    statusEl.innerHTML = '<span style="color:#16a34a;">✓ V databázi nemáme — můžeš pokračovat.</span>';
                    hideWarn();
                    return;
                }
                var c = data.contact || {};
                statusEl.innerHTML = '<span style="color:#dc2626;font-weight:600;">⚠ Tento IČO už máme!</span>';
                var html =
                    'Firma: <strong>' + escapeHtml(c.firma || '—') + '</strong> '
                    + '(#' + (c.id || '?') + ')<br>'
                    + 'OZ: <strong>' + escapeHtml(c.oz_name || '—') + '</strong> · '
                    + 'Kraj: ' + escapeHtml(c.region || '—') + ' · '
                    + 'Stav: <code>' + escapeHtml(c.stav || '—') + '</code><br>'
                    + '<a href="<?= crm_h(crm_url('/oz/search/card?id=')) ?>' + (c.id || 0) + '" target="_blank">→ Otevřít existující kartu</a>';
                infoEl.innerHTML = html;
                warnBox.style.display = 'block';
            })
            .catch(function () {
                statusEl.innerHTML = '<span style="color:#9ca3af;">⚠ Nepodařilo se ověřit — zkus znovu.</span>';
                hideWarn();
            });
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (ch) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[ch];
        });
    }

    icoInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(performCheck, 600);
    });
    icoInput.addEventListener('blur', function () {
        clearTimeout(debounceTimer);
        performCheck();
    });

    // Pokud server vrátil bounce s "už existuje" hláškou a form je re-fillnutý,
    // udělej check rovnou hned, aby user viděl warning.
    if (icoInput.value && digitsOnly(icoInput.value).length === 8) {
        performCheck();
    }
})();
</script>
