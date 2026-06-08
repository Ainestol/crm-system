<?php
// e:\Snecinatripu\app\views\oz\search_card.php
declare(strict_types=1);
/** @var array<string,mixed> $contact */
/** @var array<int,array<string,mixed>> $timeline */
/** @var array{label:string,color:string} $statusLabel */
/** @var bool $canTakeover */
/** @var string|null $flash */
/** @var string $csrf */
/** @var int $ozId */
?>
<section class="card">
    <div style="display:flex;align-items:center;gap:0.7rem;margin-bottom:1rem;flex-wrap:wrap;">
        <a href="<?= crm_h(crm_url('/oz/search')) ?>"
           style="color:#0e7490;text-decoration:none;font-size:0.85rem;">← Zpět na hledání</a>
        <span style="color:#d1d5db;">|</span>
        <a href="<?= crm_h(crm_url('/oz/leads')) ?>"
           style="color:#0e7490;text-decoration:none;font-size:0.85rem;">Moje pracovní plocha</a>
    </div>

    <h1 style="margin-bottom:0.3rem;"><?= crm_h((string)($contact['firma'] ?? 'Kontakt')) ?></h1>
    <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:1.2rem;flex-wrap:wrap;">
        <span style="background:<?= crm_h($statusLabel['color']) ?>;color:#fff;
                     padding:0.3rem 0.8rem;border-radius:12px;font-size:0.8rem;font-weight:600;">
            <?= crm_h($statusLabel['label']) ?>
        </span>
        <span style="color:#6b7280;font-size:0.82rem;">
            ID #<?= (int)($contact['id'] ?? 0) ?>
        </span>
        <?php if (!empty($contact['subject_type'])) { ?>
            <span style="background:#f3f4f6;color:#374151;padding:0.15rem 0.5rem;border-radius:8px;font-size:0.72rem;">
                <?= $contact['subject_type'] === 'firma' ? '🏢 Firma' : '👤 OSVČ' ?>
            </span>
        <?php } ?>
    </div>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info"><?= crm_h($flash) ?></p>
    <?php } ?>

    <!-- Toggle edit mode -->
    <div style="margin-bottom:1rem;text-align:right;">
        <button type="button" id="oz-card-edit-toggle"
                onclick="ozCardToggleEdit(true)"
                style="background:#fff;border:1px solid #0e7490;color:#0e7490;
                       border-radius:6px;padding:0.4rem 0.9rem;cursor:pointer;
                       font-weight:600;font-size:0.85rem;">
            ✏ Upravit
        </button>
    </div>

    <form method="post" action="<?= crm_h(crm_url('/oz/search/edit')) ?>" id="oz-card-edit-form">
        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
        <input type="hidden" name="contact_id" value="<?= (int)($contact['id'] ?? 0) ?>">

    <!-- Základní info v 2 sloupcích -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <div style="background:#f9fafb;border-radius:8px;padding:1rem 1.2rem;">
            <h3 style="margin:0 0 0.7rem;font-size:0.92rem;color:#374151;">📋 Kontaktní údaje</h3>
            <?php
            $tel = (string)($contact['telefon'] ?? '');
            $td  = preg_replace('/\D+/', '', $tel);
            $em  = (string)($contact['email'] ?? '');
            $pr  = (string)($contact['prilez'] ?? '');
            $op  = (string)($contact['operator'] ?? '');
            $reg = (string)($contact['region'] ?? '');
            $allRegions = function_exists('crm_region_choices') ? crm_region_choices() : [];
            $inputCss = 'width:100%;padding:0.32rem 0.5rem;border:1px solid #93c5fd;border-radius:4px;font-size:0.85rem;font-family:inherit;';
            ?>
            <table style="width:100%;font-size:0.85rem;">
                <tr><td style="color:#6b7280;padding:0.18rem 0;">Firma:</td>
                    <td>
                        <span class="oz-card-view"><?= crm_h((string)($contact['firma'] ?? '')) ?: '—' ?></span>
                        <input class="oz-card-edit" type="text" name="firma" value="<?= crm_h((string)($contact['firma'] ?? '')) ?>" style="<?= $inputCss ?>display:none;" maxlength="255">
                    </td></tr>
                <tr><td style="color:#6b7280;padding:0.18rem 0;">IČO:</td>
                    <td>
                        <span class="oz-card-view"><?= crm_h((string)($contact['ico'] ?? '')) ?: '—' ?></span>
                        <span class="oz-card-edit oz-card-edit-flex" style="display:none;gap:0.3rem;align-items:center;">
                            <input type="text" name="ico" id="oz-card-ico-input" value="<?= crm_h((string)($contact['ico'] ?? '')) ?>" style="<?= $inputCss ?>flex:1;" maxlength="20">
                            <button type="button" onclick="ozCardAresLookup()"
                                    style="background:#0e7490;color:#fff;border:0;border-radius:4px;
                                           padding:0.32rem 0.7rem;cursor:pointer;font-size:0.8rem;white-space:nowrap;">
                                🔄 ARES
                            </button>
                        </span>
                    </td></tr>
                <tr><td style="color:#6b7280;padding:0.18rem 0;">Telefon:</td>
                    <td>
                        <span class="oz-card-view">
                            <?php if ($tel) { ?>
                                <a href="tel:<?= crm_h($td) ?>" style="color:#0e7490;text-decoration:none;font-family:monospace;"><?= crm_h($tel) ?></a>
                            <?php } else { echo '—'; } ?>
                        </span>
                        <input class="oz-card-edit" type="text" name="telefon" value="<?= crm_h($tel) ?>" style="<?= $inputCss ?>display:none;" maxlength="40">
                    </td></tr>
                <tr><td style="color:#6b7280;padding:0.18rem 0;">E-mail:</td>
                    <td>
                        <span class="oz-card-view">
                            <?php if ($em) { ?>
                                <a href="mailto:<?= crm_h($em) ?>" style="color:#0e7490;text-decoration:none;"><?= crm_h($em) ?></a>
                            <?php } else { echo '—'; } ?>
                        </span>
                        <input class="oz-card-edit" type="email" name="email" value="<?= crm_h($em) ?>" style="<?= $inputCss ?>display:none;" maxlength="255">
                    </td></tr>
                <tr><td style="color:#6b7280;padding:0.18rem 0;">Adresa:</td>
                    <td>
                        <span class="oz-card-view"><?= crm_h((string)($contact['adresa'] ?? '')) ?: '—' ?></span>
                        <input class="oz-card-edit" type="text" name="adresa" value="<?= crm_h((string)($contact['adresa'] ?? '')) ?>" style="<?= $inputCss ?>display:none;" maxlength="255">
                    </td></tr>
                <tr><td style="color:#6b7280;padding:0.18rem 0;">Kraj:</td>
                    <td>
                        <span class="oz-card-view"><?= crm_h($reg) ?: '—' ?></span>
                        <select class="oz-card-edit" name="region" style="<?= $inputCss ?>display:none;">
                            <option value="">— vyber kraj —</option>
                            <?php foreach ($allRegions as $rc) { ?>
                                <option value="<?= crm_h((string)$rc) ?>"<?= $reg === $rc ? ' selected' : '' ?>><?= crm_h(crm_region_label((string)$rc)) ?></option>
                            <?php } ?>
                        </select>
                    </td></tr>
                <?php
                $prDo  = (string)($contact['prilez_do'] ?? '');
                $hasPr = $pr !== '';
                $prDoFmt = ($prDo !== '' && $prDo !== '0000-00-00') ? date('j. n. Y', strtotime($prDo)) : '';
                ?>
                <tr><td style="color:#6b7280;padding:0.18rem 0;vertical-align:top;">Příležitost:</td>
                    <td>
                        <!-- READ mode -->
                        <span class="oz-card-view">
                            <?php if ($hasPr) { ?>
                                <span style="background:#ddd6fe;color:#5b21b6;padding:0.12rem 0.5rem;
                                             border-radius:6px;font-size:0.78rem;font-weight:600;">
                                    💡 Má příležitost<?= $prDoFmt !== '' ? ' · do ' . crm_h($prDoFmt) : '' ?>
                                </span>
                                <?php if ($pr !== '' && $pr !== 'ano') { ?>
                                    <small style="color:#6b7280;display:block;margin-top:0.2rem;">📝 <?= crm_h($pr) ?></small>
                                <?php } ?>
                            <?php } else { ?>
                                <span style="background:#f3f4f6;color:#9ca3af;padding:0.12rem 0.5rem;
                                             border-radius:6px;font-size:0.78rem;font-style:italic;">
                                    ❌ Nemá příležitost
                                </span>
                            <?php } ?>
                        </span>
                        <!-- EDIT mode: checkbox + nested fields -->
                        <span class="oz-card-edit" style="display:none;">
                            <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.85rem;cursor:pointer;">
                                <input type="checkbox" name="has_prilez" id="oz-card-has-prilez" value="1" <?= $hasPr ? 'checked' : '' ?>
                                       onchange="document.getElementById('oz-card-prilez-details').style.display = this.checked ? '' : 'none';">
                                <strong>Má příležitost</strong>
                            </label>
                            <div id="oz-card-prilez-details" style="<?= $hasPr ? '' : 'display:none;' ?>margin-top:0.4rem;display:flex;gap:0.4rem;flex-wrap:wrap;">
                                <input type="date" name="prilez_do" value="<?= crm_h($prDo) ?>"
                                       style="<?= $inputCss ?>flex:0 0 170px;" title="Do kdy je příležitost platná (volitelné)">
                                <input type="text" name="prilez" value="<?= crm_h($pr === 'ano' ? '' : $pr) ?>" maxlength="200"
                                       placeholder="Volitelný popis (Internet, TV, …)" style="<?= $inputCss ?>flex:1;min-width:160px;">
                            </div>
                        </span>
                    </td></tr>
                <tr><td style="color:#6b7280;padding:0.18rem 0;">Operátor:</td>
                    <td>
                        <span class="oz-card-view">
                            <?php if ($op !== '') { ?>
                                <span style="background:#fef3c7;color:#92400e;padding:0.12rem 0.5rem;
                                             border-radius:6px;font-size:0.78rem;font-weight:600;">
                                    📡 <?= crm_h($op) ?>
                                </span>
                            <?php } else { echo '—'; } ?>
                        </span>
                        <select class="oz-card-edit" name="operator" style="<?= $inputCss ?>display:none;">
                            <option value=""<?= $op === '' ? ' selected' : '' ?>>— bez operátora —</option>
                            <option value="TM"<?= $op === 'TM' ? ' selected' : '' ?>>TM</option>
                            <option value="O2"<?= $op === 'O2' ? ' selected' : '' ?>>O2</option>
                            <option value="VF"<?= $op === 'VF' ? ' selected' : '' ?>>VF</option>
                        </select>
                    </td></tr>
                <?php if (!empty($contact['ico'])) { ?>
                <tr><td style="color:#6b7280;padding:0.18rem 0;">ARES:</td>
                    <td>
                        <a href="https://ares.gov.cz/ekonomicke-subjekty?ico=<?= crm_h((string)$contact['ico']) ?>"
                           target="_blank" rel="noopener" style="color:#0e7490;font-size:0.78rem;">🔗 Ověřit v ARES →</a>
                    </td></tr>
                <?php } ?>
            </table>
        </div>

        <div style="background:#f9fafb;border-radius:8px;padding:1rem 1.2rem;">
            <h3 style="margin:0 0 0.7rem;font-size:0.92rem;color:#374151;">🎯 Workflow</h3>
            <table style="width:100%;font-size:0.85rem;">
                <tr><td style="color:#6b7280;padding:0.18rem 0;">Stav kontaktu:</td>
                    <td><strong><?= crm_h((string)($contact['stav'] ?? '')) ?: '—' ?></strong></td></tr>
                <tr><td style="color:#6b7280;padding:0.18rem 0;">Workflow stav OZ:</td>
                    <td><strong><?= crm_h((string)($contact['wf_stav'] ?? '')) ?: '—' ?></strong></td></tr>
                <tr><td style="color:#6b7280;padding:0.18rem 0;">Navolávačka:</td>
                    <td><?= crm_h((string)($contact['caller_name'] ?? '')) ?: '— (volný)' ?></td></tr>
                <tr><td style="color:#6b7280;padding:0.18rem 0;">OZ:</td>
                    <td>
                        <?php $sn = (string)($contact['sales_name'] ?? ''); $sid = (int)($contact['assigned_sales_id'] ?? 0); ?>
                        <?php if ($sn) { ?>
                            <strong style="color:<?= $sid === $ozId ? '#16a34a' : '#1f2937' ?>;">
                                <?= crm_h($sn) ?><?= $sid === $ozId ? ' (já)' : '' ?>
                            </strong>
                        <?php } else { echo '<span style="color:#9ca3af;">— (volný)</span>'; } ?>
                    </td></tr>
                <?php if (!empty($contact['wf_cislo'])) { ?>
                <tr><td style="color:#6b7280;padding:0.18rem 0;">Číslo smlouvy:</td>
                    <td><strong><?= crm_h((string)$contact['wf_cislo']) ?></strong></td></tr>
                <?php } ?>
                <?php if (!empty($contact['wf_datum'])) { ?>
                <tr><td style="color:#6b7280;padding:0.18rem 0;">Datum uzavření:</td>
                    <td><?= crm_h(date('j. n. Y', strtotime((string)$contact['wf_datum']))) ?></td></tr>
                <?php } ?>
                <?php if (!empty($contact['datum_volani'])) { ?>
                <tr><td style="color:#6b7280;padding:0.18rem 0;">Naposled voláno:</td>
                    <td><?= crm_h(date('j. n. Y', strtotime((string)$contact['datum_volani']))) ?></td></tr>
                <?php } ?>
            </table>
        </div>
    </div>

    <?php
    // ── Poznámka od navolávačky — kritický kontext pro OZ ──
    // Když si OZ chce převzít kontakt, musí vědět, co s ním navolávačka řešila
    // (zájem, kterou službu chce, co odmítl). Zobrazujeme c.poznamka (= caller_poznamka v leads.php).
    $callerPoznamka = trim((string) ($contact['poznamka'] ?? ''));
    $callerJmeno    = trim((string) ($contact['caller_name'] ?? ''));
    ?>
    <?php if ($callerPoznamka !== '' || $callerJmeno !== '') { ?>
    <div style="background:#fef9c3;border:1px solid #fde047;border-left:5px solid #ca8a04;
                border-radius:0 8px 8px 0;padding:0.95rem 1.2rem;margin-bottom:1.5rem;">
        <div style="font-size:0.75rem;color:#854d0e;text-transform:uppercase;
                    letter-spacing:0.04em;font-weight:700;margin-bottom:0.35rem;">
            📞 Poznámka od navolávačky
            <?php if ($callerJmeno !== '') { ?>
                <span style="font-weight:500;text-transform:none;letter-spacing:0;margin-left:0.3rem;">
                    — <?= crm_h($callerJmeno) ?>
                </span>
            <?php } ?>
        </div>
        <?php if ($callerPoznamka !== '') { ?>
            <div style="font-size:0.92rem;color:#422006;line-height:1.5;white-space:pre-wrap;">
                <?= crm_h($callerPoznamka) ?>
            </div>
        <?php } else { ?>
            <div style="font-size:0.85rem;color:#854d0e;font-style:italic;">
                Navolávačka neuložila žádnou poznámku.
            </div>
        <?php } ?>
    </div>
    <?php } ?>

        <!-- Edit mode: tlačítka Uložit / Zrušit (skryté v read mode) -->
        <div id="oz-card-edit-actions" style="display:none;margin-bottom:1.5rem;
             padding:0.8rem 1rem;background:#eff6ff;border:1px solid #93c5fd;border-radius:8px;
             display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
            <button type="submit"
                    style="background:#0e7490;color:#fff;border:0;border-radius:5px;
                           padding:0.5rem 1.2rem;cursor:pointer;font-weight:600;font-size:0.88rem;">
                💾 Uložit změny
            </button>
            <button type="button" onclick="ozCardToggleEdit(false); return false;"
                    style="background:#fff;border:1px solid #d1d5db;color:#374151;
                           border-radius:5px;padding:0.5rem 1rem;cursor:pointer;font-size:0.88rem;">
                ✗ Zrušit
            </button>
            <small style="color:#1e40af;flex:1;text-align:right;font-size:0.78rem;">
                Změny se zapíší přes audit log. Stav a přiřazení nelze měnit z této karty.
            </small>
        </div>
    </form>

    <script>
    function ozCardToggleEdit(edit) {
        const views = document.querySelectorAll('.oz-card-view');
        const edits = document.querySelectorAll('.oz-card-edit');
        const toggleBtn = document.getElementById('oz-card-edit-toggle');
        const actions = document.getElementById('oz-card-edit-actions');
        views.forEach(v => v.style.display = edit ? 'none' : '');
        edits.forEach(e => {
            if (e.classList.contains('oz-card-edit-flex')) {
                e.style.display = edit ? 'flex' : 'none';
            } else {
                e.style.display = edit ? '' : 'none';
            }
        });
        if (toggleBtn) toggleBtn.style.display = edit ? 'none' : '';
        if (actions)   actions.style.display   = edit ? 'flex' : 'none';
    }

    // ARES lookup — stáhne firma + adresa podle IČO
    async function ozCardAresLookup() {
        const icoIn = document.getElementById('oz-card-ico-input');
        if (!icoIn) return;
        const ico = (icoIn.value || '').replace(/\D+/g, '');
        if (ico.length < 1 || ico.length > 8) {
            if (window.crmAlert) {
                crmAlert('IČO musí mít 1–8 číslic. Doplním zleva nulami.', { type: 'warn' });
            } else {
                alert('IČO musí mít 1–8 číslic.');
            }
            return;
        }

        // Najdi inputs pro firma a adresa
        const firmaIn  = document.querySelector('input[name="firma"]');
        const adresaIn = document.querySelector('input[name="adresa"]');

        try {
            const resp = await fetch('<?= crm_h(crm_url('/oz/ares-lookup')) ?>?ico=' + encodeURIComponent(ico));
            const d = await resp.json();
            if (!d.ok) {
                if (window.crmAlert) {
                    crmAlert(d.error || 'ARES selhal.', { type: 'danger', title: 'ARES' });
                } else {
                    alert('⚠ ' + (d.error || 'ARES selhal.'));
                }
                return;
            }
            if (d.firma  && firmaIn)  firmaIn.value  = d.firma;
            if (d.adresa && adresaIn) adresaIn.value = d.adresa;
            if (d.ico    && icoIn)    icoIn.value    = d.ico;
            if (window.crmToast) {
                crmToast('✓ Načteno z ARES: ' + (d.firma || ''), 'success');
            }
        } catch (e) {
            if (window.crmAlert) {
                crmAlert('Síťová chyba: ' + e, { type: 'danger', title: 'ARES' });
            } else {
                alert('⚠ Síťová chyba: ' + e);
            }
        }
    }
    </script>

    <!-- Akce — Přidat poznámku + Převzít -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <!-- Poznámka -->
        <form method="post" action="<?= crm_h(crm_url('/oz/search/note')) ?>"
              style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:1rem 1.2rem;">
            <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
            <input type="hidden" name="contact_id" value="<?= (int)($contact['id'] ?? 0) ?>">
            <h3 style="margin:0 0 0.6rem;font-size:0.92rem;color:#166534;">📝 Přidat poznámku</h3>
            <textarea name="note" required maxlength="2000" rows="3"
                      placeholder="Co se má vědět o tomto kontaktu… (max 2000 znaků)"
                      style="width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:4px;
                             font-family:inherit;font-size:0.85rem;resize:vertical;"></textarea>
            <button type="submit"
                    style="margin-top:0.5rem;background:#16a34a;color:#fff;border:0;border-radius:5px;
                           padding:0.45rem 1rem;cursor:pointer;font-weight:600;font-size:0.85rem;">
                💾 Uložit poznámku
            </button>
            <small style="display:block;margin-top:0.4rem;color:#166534;font-size:0.72rem;">
                Poznámka se uloží do timeline (vidí všichni — navolávačka, OZ, BO, admin).
            </small>
        </form>

        <!-- Převzít -->
        <?php if ($canTakeover) { ?>
        <form method="post" action="<?= crm_h(crm_url('/oz/search/takeover')) ?>"
              onsubmit="return confirm('Opravdu převzít tento kontakt? Objeví se ve tvojí pracovní ploše v záložce Rozpracované.');"
              style="background:#eff6ff;border:1px solid #93c5fd;border-radius:8px;padding:1rem 1.2rem;
                     display:flex;flex-direction:column;justify-content:space-between;">
            <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
            <input type="hidden" name="contact_id" value="<?= (int)($contact['id'] ?? 0) ?>">
            <div>
                <h3 style="margin:0 0 0.5rem;font-size:0.92rem;color:#1e40af;">🎯 Převzít na sebe</h3>
                <p style="margin:0;font-size:0.82rem;color:#1e40af;">
                    Kontakt nemá přiřazeného OZ. Klikni a převezmeš ho — objeví se ve tvojí
                    pracovní ploše v záložce <strong>Rozpracované</strong>.
                </p>
            </div>
            <button type="submit"
                    style="margin-top:0.7rem;background:#1e40af;color:#fff;border:0;border-radius:5px;
                           padding:0.5rem 1rem;cursor:pointer;font-weight:600;font-size:0.88rem;">
                🎯 Převzít na sebe
            </button>
        </form>
        <?php } else { ?>
        <div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;padding:1rem 1.2rem;
                    color:#991b1b;font-size:0.85rem;">
            <h3 style="margin:0 0 0.4rem;font-size:0.92rem;color:#991b1b;">🔒 Kontakt má jiný OZ</h3>
            <p style="margin:0;line-height:1.5;">
                Tento kontakt má přiřazeného <strong><?= crm_h((string)($contact['sales_name'] ?? '?')) ?></strong>.
                Pokud bys ho chtěl převzít, požádej administrátora — přes datagrid může OZ změnit.
            </p>
        </div>
        <?php } ?>
    </div>

    <!-- Timeline -->
    <div style="background:#f9fafb;border-radius:8px;padding:1rem 1.2rem;">
        <h3 style="margin:0 0 0.8rem;font-size:0.92rem;color:#374151;">⏰ Historie kontaktu</h3>
        <?php if ($timeline === []) { ?>
            <p style="color:#9ca3af;font-style:italic;margin:0;font-size:0.85rem;">
                Žádné události — kontakt je čerstvý nebo ještě nikdo s ním nepracoval.
            </p>
        <?php } else { ?>
            <div style="display:flex;flex-direction:column;gap:0.5rem;">
                <?php
                // Mapování role na lidský label + barvu badge (rychlý vizuální klíč:
                // hned vidíš "tahle poznámka je od navolávačky / OZ / BO / admina")
                $roleLabels = [
                    'navolavacka' => ['Navolávačka', '#fef3c7', '#92400e'],
                    'cisticka'    => ['Čistička',    '#e0e7ff', '#3730a3'],
                    'obchodak'    => ['OZ',          '#cffafe', '#155e75'],
                    'backoffice'  => ['BO',          '#ede9fe', '#5b21b6'],
                    'majitel'     => ['Majitel',     '#fce7f3', '#9f1239'],
                    'superadmin'  => ['Admin',       '#fce7f3', '#9f1239'],
                ];
                foreach ($timeline as $ev) {
                    $icon = match ($ev['type']) {
                        'note'     => '📝',
                        'oz_note'  => '💼',  // poznámka OZ z jeho pracovní plochy
                        'workflow' => '🔄',
                        'action'   => '📊',
                        default    => '•',
                    };
                    $color = match ($ev['type']) {
                        'note'     => '#16a34a',
                        'oz_note'  => '#0e7490',
                        'workflow' => '#7c3aed',
                        'action'   => '#ea580c',
                        default    => '#6b7280',
                    };
                    $when = (string)($ev['when'] ?? '');
                    $whenFmt = $when !== '' ? date('j. n. Y H:i', strtotime($when)) : '—';
                    $role = (string) ($ev['role'] ?? '');
                    [$roleLbl, $roleBg, $roleFg] = $roleLabels[$role] ?? ['', '#f3f4f6', '#6b7280'];
                ?>
                    <div style="display:flex;gap:0.7rem;padding:0.5rem 0.7rem;background:#fff;
                                border-left:3px solid <?= crm_h($color) ?>;border-radius:0 5px 5px 0;font-size:0.82rem;">
                        <span style="font-size:1.1rem;line-height:1;"><?= $icon ?></span>
                        <div style="flex:1;">
                            <div style="color:#1f2937;line-height:1.4;"><?= crm_h((string)$ev['msg']) ?></div>
                            <small style="color:#9ca3af;display:flex;gap:0.4rem;align-items:center;margin-top:0.15rem;">
                                <?php if ($roleLbl !== '') { ?>
                                    <span style="background:<?= $roleBg ?>;color:<?= $roleFg ?>;
                                                 padding:0.05rem 0.4rem;border-radius:8px;
                                                 font-weight:700;font-size:0.7rem;">
                                        <?= crm_h($roleLbl) ?>
                                    </span>
                                <?php } ?>
                                <span><?= crm_h((string)$ev['who']) ?> · <?= crm_h($whenFmt) ?></span>
                            </small>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</section>
