<?php
// e:\Snecinatripu\app\views\contact-proposals\new.php
declare(strict_types=1);
/** @var array<string,mixed>             $user */
/** @var string                          $csrf */
/** @var ?string                         $flash */
/** @var array<string,string>            $regions      kód → label (z crm_region_choices) */
/** @var list<array<string,mixed>>       $salesUsers   aktivní OZ pro dropdown */
/** @var list<string>                    $operators    whitelist operátorů */
/** @var array<string,mixed>             $form         re-fill data po validační chybě */

// Majitel/superadmin: by-pass schvalování — kontakt jde rovnou do contacts.
// View se podle toho přepíná: copy, povinnost OZ, label submit tlačítka.
$_isOwnerOrAdmin = in_array((string) ($user['role'] ?? ''), ['majitel', 'superadmin'], true);
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
    <?php if ($_isOwnerOrAdmin) { ?>
        <h1>➕ Nový kontakt</h1>
        <p class="lead">
            Jako <strong>majitel/superadmin</strong> kontakt vytváříš <strong>přímo</strong>
            (bez schvalovacího procesu). Vyber konkrétního OZ — kontakt se mu rovnou
            objeví v <strong>Příchozí leady</strong> jako každý jiný.
        </p>
    <?php } else { ?>
        <h1>➕ Nový kontakt — návrh ke schválení</h1>
        <p class="lead">
            Tento formulář <strong>neukládá kontakt přímo do databáze</strong>. Návrh nejprve
            zkontroluje a schválí majitel, který také rozhodne, kterému OZ-ovi bude přiřazen.
            Po schválení se kontakt objeví OZ-ovi v <strong>Příchozí leady</strong>.
        </p>
    <?php } ?>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info" style="margin-bottom:1rem;"><?= crm_h($flash) ?></p>
    <?php } ?>

    <div class="cp-info-box">
        <?php if ($_isOwnerOrAdmin) { ?>
            🚀 Kontakt se uloží <strong>okamžitě</strong> a přiřadí vybranému OZ
            (stav <code>CALLED_OK</code>). Pole označená <span style="color:#e74c3c;">*</span> jsou povinná.
        <?php } else { ?>
            💡 Hot lead = kontakt s vysokou pravděpodobností uzavření smlouvy
            (doporučení od klienta, vlastní akvizice, návrh majitele).
            Pole označená <span style="color:#e74c3c;">*</span> jsou povinná.
        <?php } ?>
    </div>

    <form method="post" action="<?= crm_h(crm_url('/contacts/new')) ?>" class="cp-form" autocomplete="off">
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
                       value="<?= crm_h((string)($form['ico'] ?? '')) ?>"
                       placeholder="8 číslic">
                <span class="hint">Po uložení se ověří přes ARES.</span>
            </label>
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

        <!-- Adresa (full row) -->
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

        <!-- OZ — povinný pro majitele (přímý INSERT), volitelný pro ostatní (návrh) -->
        <label>
            <?php if ($_isOwnerOrAdmin) { ?>
                Přiřadit OZ <span class="req">*</span>
            <?php } else { ?>
                Doporučený OZ
            <?php } ?>
            <select name="suggested_oz_id" <?= $_isOwnerOrAdmin ? 'required' : '' ?>>
                <option value="0">
                    <?= $_isOwnerOrAdmin ? '— vyberte OZ —' : '— nechat na majiteli —' ?>
                </option>
                <?php
                $selOz = (int)($form['suggested_oz_id'] ?? 0);
                foreach ($salesUsers as $oz) {
                    $oid = (int)($oz['id'] ?? 0);
                    $sel = $oid === $selOz ? 'selected' : '';
                    echo '<option value="' . $oid . '" ' . $sel . '>'
                       . crm_h((string)($oz['jmeno'] ?? '')) . '</option>';
                }
                ?>
            </select>
            <span class="hint">
                <?php if ($_isOwnerOrAdmin) { ?>
                    Komu se kontakt přiřadí. Objeví se mu okamžitě v Příchozí leady.
                <?php } else { ?>
                    Volitelné — můžeš nechat na majiteli, nebo navrhnout vlastního OZ-a.
                <?php } ?>
            </span>
        </label>

        <!-- Poznámka -->
        <label>
            Poznámka <span class="req">*</span>
            <textarea name="poznamka" required maxlength="1000"
                      placeholder="Proč je to hot lead? Doporučení od kterého klienta? Jaký je kontext?"><?= crm_h((string)($form['poznamka'] ?? '')) ?></textarea>
            <span class="hint">Pomáhá majiteli rozhodnout o schválení a OZ-ovi navázat kontakt.</span>
        </label>

        <div class="cp-actions">
            <button type="submit" class="cp-btn-primary">
                <?= $_isOwnerOrAdmin ? '🚀 Vytvořit kontakt' : '📨 Odeslat ke schválení' ?>
            </button>
        </div>
    </form>
</section>
