<?php
// e:\Snecinatripu\app\views\admin\contacts\delete.php
declare(strict_types=1);
/** @var array<string, mixed> $user */
/** @var string|null $flash */
/** @var string $csrf */
/** @var list<string> $contactStavs */
/** @var array $regionChoices */
?>
<section class="card">
    <h1>🗑 Mazání kontaktů</h1>
    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info"><?= crm_h($flash) ?></p>
    <?php } ?>

    <p style="font-size:0.9rem;color:var(--bo-text-3, #6b7280);margin-bottom:1.2rem;">
        Postav filtr → klikni „Náhled" → uvidíš kolik kontaktů odpovídá → stáhni CSV backup → napiš
        „SMAZAT" → klikni mazací tlačítko. <strong>Default chrání</strong> rozjednané kontakty
        a smlouvy; checkboxem můžeš ochranu vypnout.
    </p>

    <form id="del-form" method="post">
        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">

        <style>
            .chk-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 0.3rem;
                max-height: 220px;
                overflow-y: auto;
                background: #fff;
                border: 1px solid rgba(0,0,0,0.15);
                border-radius: 6px;
                padding: 0.5rem;
            }
            .chk-grid label {
                display: flex;
                align-items: center;
                gap: 0.3rem;
                font-size: 0.82rem;
                padding: 0.2rem 0.35rem;
                border-radius: 4px;
                cursor: pointer;
            }
            .chk-grid label:hover {
                background: rgba(99,102,241,0.08);
            }
            .chk-grid input[type=checkbox]:checked + span {
                font-weight: 700;
                color: #2563eb;
            }
            .danger-toggle {
                display: flex; align-items: center; gap: 0.5rem;
                padding: 0.5rem 0.7rem; border-radius: 6px;
                background: #f0fdf4; border: 1px solid #86efac;
                font-size: 0.86rem; cursor: pointer;
                transition: all 0.15s;
            }
            .danger-toggle:has(input:checked) {
                background: #fef2f2; border-color: #ef4444;
                color: #991b1b; font-weight: 600;
            }
            .danger-toggle:hover { transform: translateY(-1px); }
            .toggle-shield { font-size: 1.1rem; }
            .danger-toggle:has(input:checked) .toggle-shield::before { content: '⚠'; }
            .danger-toggle:not(:has(input:checked)) .toggle-shield::before { content: '🛡'; }
        </style>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem;
                    background:rgba(0,0,0,0.02);border:1px solid rgba(0,0,0,0.08);
                    border-radius:8px;padding:1rem;margin-bottom:1rem;">

            <!-- Stav kontaktu -->
            <div>
                <label style="font-weight:600;display:block;margin-bottom:0.4rem;">Stav kontaktu</label>
                <div class="chk-grid">
                    <?php foreach ($contactStavs as $s) { ?>
                        <label>
                            <input type="checkbox" name="stav[]" value="<?= crm_h($s) ?>">
                            <span><?= crm_h($s) ?></span>
                        </label>
                    <?php } ?>
                </div>
                <small style="color:#6b7280;">Zaškrtni jeden nebo víc stavů (nic = všechny stavy).</small>
            </div>

            <!-- Kraj -->
            <div>
                <label style="font-weight:600;display:block;margin-bottom:0.4rem;">Kraj</label>
                <div class="chk-grid">
                    <?php foreach ($regionChoices as $code) {
                        // crm_region_choices() vrací list of strings (kódy krajů),
                        // ne asociativní pole. Hodnota checkboxu = kód kraje.
                        $label = function_exists('crm_region_label')
                            ? crm_region_label((string) $code)
                            : (string) $code;
                    ?>
                        <label>
                            <input type="checkbox" name="region[]" value="<?= crm_h((string) $code) ?>">
                            <span><?= crm_h($label) ?></span>
                        </label>
                    <?php } ?>
                </div>
                <small style="color:#6b7280;">Zaškrtni jeden nebo víc krajů (nic = všechny).</small>
            </div>

            <!-- Typ + datum -->
            <div>
                <label style="font-weight:600;display:block;margin-bottom:0.4rem;">Typ subjektu</label>
                <div class="chk-grid" style="max-height:none;">
                    <label>
                        <input type="checkbox" name="subject_type[]" value="firma">
                        <span>Firma</span>
                    </label>
                    <label>
                        <input type="checkbox" name="subject_type[]" value="osvc">
                        <span>OSVČ</span>
                    </label>
                    <label>
                        <input type="checkbox" name="subject_type[]" value="unknown">
                        <span>Neznámý</span>
                    </label>
                </div>

                <label style="font-weight:600;display:block;margin-top:0.8rem;margin-bottom:0.4rem;">Vytvořen od</label>
                <input type="date" name="date_from" style="width:100%;font-size:0.85rem;">

                <label style="font-weight:600;display:block;margin-top:0.4rem;margin-bottom:0.4rem;">Vytvořen do</label>
                <input type="date" name="date_to" style="width:100%;font-size:0.85rem;">
            </div>
        </div>

        <!-- Pojistky — clear UX -->
        <div style="background:rgba(34,197,94,0.05);border:1px solid rgba(34,197,94,0.3);
                    border-radius:8px;padding:0.9rem 1rem;margin-bottom:1rem;">
            <p style="margin:0 0 0.7rem 0;font-weight:700;font-size:0.92rem;">
                🛡 Pojistky — co je default <strong>CHRÁNĚNO</strong>
            </p>
            <p style="margin:0 0 0.6rem 0;font-size:0.82rem;color:#6b7280;">
                Defaultně se <strong>NEMAŽOU</strong> kontakty, kde někdo aktivně pracuje.
                Pokud chceš ochranu vypnout (= zahrnout je do mazání), zaškrtni příslušný checkbox.
                Po zaškrtnutí pole zčervená — víš, že ochrana je <strong>pryč</strong>.
            </p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:0.5rem;">
                <label class="danger-toggle">
                    <span class="toggle-shield"></span>
                    <input type="checkbox" name="include_oz" value="1" style="margin:0;">
                    <span style="flex:1;">
                        <strong>Smazat i s přiřazeným OZ</strong><br>
                        <small style="opacity:0.7;">Kontakty, které má Ester / Honza atd.</small>
                    </span>
                </label>
                <label class="danger-toggle">
                    <span class="toggle-shield"></span>
                    <input type="checkbox" name="include_contract" value="1" style="margin:0;">
                    <span style="flex:1;">
                        <strong>Smazat i uzavřené smlouvy</strong><br>
                        <small style="opacity:0.7;">Workflow stav = UZAVRENO.</small>
                    </span>
                </label>
                <label class="danger-toggle">
                    <span class="toggle-shield"></span>
                    <input type="checkbox" name="include_active" value="1" style="margin:0;">
                    <span style="flex:1;">
                        <strong>Smazat i rozjednané</strong><br>
                        <small style="opacity:0.7;">NOVE / ZPRACOVAVA / SCHUZKA / NABIDKA / SANCE / CALLBACK / BO_* / SMLOUVA</small>
                    </span>
                </label>
                <label class="danger-toggle">
                    <span class="toggle-shield"></span>
                    <input type="checkbox" name="include_recycled" value="1" style="margin:0;">
                    <span style="flex:1;">
                        <strong>Smazat i už recyklované</strong><br>
                        <small style="opacity:0.7;">Kontakty co už byly vráceny do oběhu.</small>
                    </span>
                </label>
            </div>
        </div>

        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem;">
            <button type="button" id="btn-preview" class="btn">🔍 Náhled</button>
            <button type="button" id="btn-csv" class="btn"
                    style="background:#3498db;color:#fff;">📥 Stáhnout CSV backup</button>
        </div>

        <!-- Náhled -->
        <div id="preview-box" style="display:none;background:rgba(0,0,0,0.03);
             border:1px solid rgba(0,0,0,0.1);border-radius:6px;padding:1rem;margin-bottom:1rem;">
            <div id="preview-count" style="font-size:1.2rem;font-weight:700;margin-bottom:0.5rem;"></div>
            <div id="preview-by-stav" style="font-size:0.85rem;color:#6b7280;margin-bottom:0.7rem;"></div>
            <div id="preview-sample"></div>
        </div>

        <!-- Confirmation -->
        <div style="background:rgba(220,38,38,0.08);border:2px solid #dc2626;
                    border-radius:6px;padding:1rem;">
            <p style="margin:0 0 0.6rem 0;font-weight:700;color:#991b1b;">
                🗑 Pro smazání napiš přesně „SMAZAT" a klikni na tlačítko
            </p>
            <div style="display:flex;gap:0.5rem;align-items:center;">
                <input type="text" name="confirm_text" placeholder="SMAZAT"
                       style="flex:1;max-width:200px;font-family:monospace;font-weight:700;
                              padding:0.5rem;border:1px solid #dc2626;border-radius:4px;">
                <button type="submit" formaction="<?= crm_h(crm_url('/admin/contacts/delete/execute')) ?>"
                        style="background:#dc2626;color:#fff;font-weight:700;
                               padding:0.55rem 1.2rem;border:0;border-radius:4px;cursor:pointer;">
                    🗑 Smazat vybrané kontakty
                </button>
            </div>
            <p style="margin:0.6rem 0 0 0;font-size:0.8rem;color:#6b7280;">
                Smazání je nevratné. Před tímto krokem doporučujeme stáhnout CSV backup.
            </p>
        </div>
    </form>
</section>

<script>
(function() {
    const form     = document.getElementById('del-form');
    const btnPrev  = document.getElementById('btn-preview');
    const btnCsv   = document.getElementById('btn-csv');
    const previewBox  = document.getElementById('preview-box');
    const previewCount = document.getElementById('preview-count');
    const previewByStav = document.getElementById('preview-by-stav');
    const previewSample = document.getElementById('preview-sample');

    btnPrev.addEventListener('click', function() {
        const fd = new FormData(form);
        fd.delete('confirm_text');
        btnPrev.disabled = true;
        btnPrev.textContent = '⏳ Načítám…';
        fetch('<?= crm_h(crm_url('/admin/contacts/delete/preview')) ?>', {
            method: 'POST', body: fd
        })
        .then(r => r.json())
        .then(d => {
            btnPrev.disabled = false;
            btnPrev.textContent = '🔍 Náhled';
            if (!d.ok) {
                alert('⚠ ' + (d.error || 'Neznámá chyba'));
                return;
            }
            if (!d.has_user_filter) {
                previewBox.style.display = 'block';
                previewCount.innerHTML = '⚠ <span style="color:#dc2626;">Nastav alespoň 1 filtr</span> (stav, kraj, typ nebo datum). Bez filtru se nemaže nic.';
                previewByStav.innerHTML = '';
                previewSample.innerHTML = '';
                return;
            }
            previewBox.style.display = 'block';
            const cnt = d.total;
            previewCount.innerHTML = '🎯 Odpovídá <span style="color:#dc2626;">' + cnt + '</span> kontaktů';
            const byStavTxt = Object.entries(d.by_stav || {})
                .map(([k, v]) => '<strong>' + k + '</strong>: ' + v)
                .join(' · ');
            previewByStav.innerHTML = byStavTxt || '(žádný rozpad)';
            if ((d.sample || []).length === 0) {
                previewSample.innerHTML = '<p style="color:#6b7280;font-style:italic;">Žádné řádky neodpovídají.</p>';
                return;
            }
            let html = '<p style="font-size:0.82rem;color:#6b7280;margin:0.3rem 0;">Ukázka prvních ' + d.sample.length + ' řádků:</p>';
            html += '<table style="width:100%;font-size:0.78rem;border-collapse:collapse;">';
            html += '<tr style="background:rgba(0,0,0,0.05);">'
                  + '<th style="text-align:left;padding:0.3rem;">ID</th>'
                  + '<th style="text-align:left;padding:0.3rem;">Firma</th>'
                  + '<th style="text-align:left;padding:0.3rem;">Tel</th>'
                  + '<th style="text-align:left;padding:0.3rem;">Stav</th>'
                  + '<th style="text-align:left;padding:0.3rem;">WF</th>'
                  + '<th style="text-align:left;padding:0.3rem;">OZ</th>'
                  + '<th style="text-align:left;padding:0.3rem;">Kraj</th>'
                  + '<th style="text-align:left;padding:0.3rem;">Vytvořen</th>'
                  + '</tr>';
            d.sample.forEach(r => {
                html += '<tr style="border-bottom:1px solid rgba(0,0,0,0.05);">'
                      + '<td style="padding:0.3rem;">' + r.id + '</td>'
                      + '<td style="padding:0.3rem;">' + escapeHtml(r.firma || '') + '</td>'
                      + '<td style="padding:0.3rem;">' + escapeHtml(r.telefon || '') + '</td>'
                      + '<td style="padding:0.3rem;">' + escapeHtml(r.stav || '') + '</td>'
                      + '<td style="padding:0.3rem;">' + escapeHtml(r.wf_stav || '—') + '</td>'
                      + '<td style="padding:0.3rem;">' + escapeHtml(r.oz_name || '—') + '</td>'
                      + '<td style="padding:0.3rem;">' + escapeHtml(r.region || '') + '</td>'
                      + '<td style="padding:0.3rem;font-family:monospace;font-size:0.72rem;">'
                          + escapeHtml((r.created_at || '').substring(0, 10)) + '</td>'
                      + '</tr>';
            });
            html += '</table>';
            previewSample.innerHTML = html;
        })
        .catch(e => {
            btnPrev.disabled = false;
            btnPrev.textContent = '🔍 Náhled';
            alert('⚠ Síťová chyba: ' + e);
        });
    });

    btnCsv.addEventListener('click', function() {
        form.action = '<?= crm_h(crm_url('/admin/contacts/delete/csv')) ?>';
        form.submit();
        // Reset action zpět (kdyby admin pokračoval k delete)
        setTimeout(() => { form.action = ''; }, 100);
    });

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, ch =>
            ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[ch]);
    }
})();
</script>
