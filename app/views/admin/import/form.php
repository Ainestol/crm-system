<?php
// e:\Snecinatripu\app\views\admin\import\form.php
declare(strict_types=1);
/** @var string|null $flash */
/** @var string $csrf */
?>
<style>
/* ── Import — formulář & progress bar ── */
.import-card { max-width: 760px; }
.import-row { display: flex; flex-direction: column; gap: 0.4rem; margin-bottom: 0.85rem; }
.import-row label { font-size: 0.85rem; color: var(--text); font-weight: 600; }

.import-drop {
    border: 2px dashed rgba(0,0,0,0.18);
    border-radius: 10px;
    padding: 1.4rem 1rem;
    text-align: center;
    background: rgba(0,0,0,0.02);
    cursor: pointer;
    transition: border-color 0.18s, background 0.18s;
}
.import-drop:hover, .import-drop.is-dragover {
    border-color: rgba(61,139,253,0.6);
    background: rgba(61,139,253,0.08);
}
.import-drop__icon { font-size: 2.4rem; margin-bottom: 0.4rem; opacity: 0.85; }
.import-drop__hint { font-size: 0.78rem; color: var(--muted); margin-top: 0.4rem; }
.import-drop input[type=file] { display: none; }
.import-drop__filename {
    margin-top: 0.5rem; font-size: 0.85rem; font-weight: 600;
    color: var(--accent); word-break: break-all;
}
.import-drop__filesize {
    font-size: 0.72rem; color: var(--muted); margin-top: 0.15rem;
}

.import-progress { display: none; margin-top: 1rem; }
.import-progress.is-active { display: block; }
.import-progress__label {
    display: flex; justify-content: space-between; align-items: baseline;
    font-size: 0.8rem; margin-bottom: 0.35rem;
}
.import-progress__pct { font-weight: 700; color: var(--accent); }
.import-progress__bar {
    height: 16px; background: rgba(0,0,0,0.08);
    border-radius: 8px; overflow: hidden;
    position: relative;
}
.import-progress__fill {
    height: 100%; background: linear-gradient(90deg, #3d8bfd, #6ab2ff);
    border-radius: 8px; width: 0%;
    transition: width 0.2s ease;
}
.import-progress__fill--processing {
    background: linear-gradient(90deg, #9b59b6, #b574c6);
    animation: import-pulse 1.4s ease-in-out infinite;
}
@keyframes import-pulse {
    0%,100% { opacity: 0.85; }
    50%      { opacity: 1; }
}
.import-progress__hint {
    font-size: 0.72rem; color: var(--muted); margin-top: 0.35rem; font-style: italic;
}
</style>

<section class="card import-card">
    <div style="margin-bottom:0.8rem;font-size:0.78rem;display:flex;gap:0.4rem;flex-wrap:wrap;">
        <a href="<?= crm_h(crm_url('/dashboard')) ?>" style="color:var(--brand-primary,#5a6cff);text-decoration:none;padding:0.25rem 0.55rem;border-radius:6px;background:rgba(90,108,255,0.1);border:1px solid rgba(90,108,255,0.25);">← Dashboard</a>
        <a href="<?= crm_h(crm_url('/admin/datagrid')) ?>" style="color:var(--brand-primary,#5a6cff);text-decoration:none;padding:0.25rem 0.55rem;border-radius:6px;background:rgba(90,108,255,0.1);border:1px solid rgba(90,108,255,0.25);">📊 Live datagrid</a>
        <a href="<?= crm_h(crm_url('/admin/duplicates')) ?>" style="color:var(--brand-primary,#5a6cff);text-decoration:none;padding:0.25rem 0.55rem;border-radius:6px;background:rgba(90,108,255,0.1);border:1px solid rgba(90,108,255,0.25);">🕵 Audit duplicit</a>
    </div>
    <h1>📥 Import kontaktů</h1>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info"><?= crm_h($flash) ?></p>
    <?php } ?>

    <p class="muted" style="margin-bottom:0.6rem;">
        Podporované formáty: <strong>CSV</strong> (UTF-8, oddělovač <code>;</code> nebo <code>,</code>) a <strong>XLSX / XLS</strong>.
        Maximum 200 MB / 300 000 datových řádků.
    </p>

    <details style="margin-bottom:1rem;">
        <summary style="cursor:pointer;font-size:0.85rem;color:var(--muted);">
            ▸ Co soubor musí obsahovat (kliknutím zobrazit)
        </summary>
        <div style="font-size:0.8rem;color:var(--muted);padding:0.5rem 0 0;line-height:1.5;">
            <strong>Povinné sloupce:</strong> <code>firma</code> (nebo <code>nazev_firmy</code>) + zdroj kraje:
            <code>kraj</code>, <code>region</code>, nebo <code>město</code> (kraj se odvodí automaticky podle města).<br>
            <strong>Volitelné:</strong> <code>ico</code> (i <code>ičo</code>), <code>adresa</code>, <code>telefon</code> / <code>mobil</code>,
            <code>email</code>, <code>poznamka</code>, <code>operator</code>, <code>narozeniny_majitele</code>, <code>vyrocni_smlouvy</code>,
            <code>datum_uzavreni</code>.<br>
            <strong>Kraj přijímá:</strong> kód (<code>jihomoravsky</code>), český název (<code>Jihomoravský kraj</code>),
            nebo město (<code>Brno</code> → Jihomoravský).<br>
            <strong>Datum:</strong> <code>25.04.2026</code>, <code>2026-04-25</code>, nebo Excel serial number.<br>
            <strong>💡 Speciálka pro staré uzavřené smlouvy:</strong> pokud vyplníš sloupec <code>datum_uzavreni</code>,
            kontakt se rovnou založí jako <strong>UZAVRENO</strong>, výročí se dopočítá automaticky
            (<code>datum_uzavreni + 3 roky</code>) a objeví se v BO/Uzavřeno tabu.
        </div>
    </details>

    <form id="import-form" method="post" action="<?= crm_h(crm_url('/admin/import')) ?>"
          enctype="multipart/form-data">
        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">

        <!-- Drop zone / file picker -->
        <div class="import-row">
            <label for="csv">Soubor</label>
            <label class="import-drop" id="import-drop" for="csv">
                <div class="import-drop__icon">📁</div>
                <div><strong id="import-drop-text">Klikněte nebo přetáhněte soubor</strong></div>
                <div class="import-drop__hint">CSV, XLSX, XLS · max 200 MB</div>
                <div class="import-drop__filename" id="import-drop-filename"></div>
                <div class="import-drop__filesize" id="import-drop-filesize"></div>
                <input id="csv" name="csv" type="file"
                       accept=".csv,.xlsx,.xls,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                       required>
            </label>
        </div>

        <!-- Default region -->
        <div class="import-row">
            <label for="default_region">Výchozí kraj <span style="font-weight:400;color:var(--muted);font-size:0.78rem;">(použije se, pokud řádek nemá vlastní)</span></label>
            <select id="default_region" name="default_region">
                <option value="">— žádný (jen sloupec ze souboru) —</option>
                <?php foreach (crm_region_choices() as $rc) { ?>
                    <option value="<?= crm_h($rc) ?>"><?= crm_h(crm_region_label($rc)) ?></option>
                <?php } ?>
            </select>
        </div>

        <!-- Progress bar (skrytý do submitu) -->
        <div class="import-progress" id="import-progress">
            <div class="import-progress__label">
                <span id="import-progress-label">Nahrávám soubor…</span>
                <span class="import-progress__pct" id="import-progress-pct">0 %</span>
            </div>
            <div class="import-progress__bar">
                <div class="import-progress__fill" id="import-progress-fill"></div>
            </div>
            <div class="import-progress__hint" id="import-progress-hint">
                Čekejte prosím. Velké soubory (300k+ řádků) mohou trvat několik desítek sekund.
            </div>
        </div>

        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:1rem;">
            <button type="submit" class="btn" id="import-submit">
                Nahrát a analyzovat
            </button>
            <a class="btn btn-secondary" href="<?= crm_h(crm_url('/dashboard')) ?>">← Dashboard</a>
        </div>
    </form>

    <!-- ── Pokročilé akce: RESET databáze (skryté pod details) ───────── -->
    <details style="margin-top:2rem;border-top:1px solid rgba(0,0,0,0.08);padding-top:1rem;">
        <summary style="cursor:pointer;font-size:0.82rem;color:#e74c3c;font-weight:600;user-select:none;">
            ⚠ Pokročilé akce — Reset všech kontaktů
        </summary>

        <div style="margin-top:0.85rem;padding:0.85rem 1rem;
                    background:rgba(231,76,60,0.05);
                    border:1px solid rgba(231,76,60,0.3);
                    border-left:4px solid #e74c3c;
                    border-radius:0 8px 8px 0;">

            <p style="font-size:0.85rem;margin:0 0 0.5rem;color:var(--text);">
                <strong>🗑 Smazat všechny kontakty a závislá data</strong>
            </p>
            <p style="font-size:0.78rem;color:var(--muted);margin:0 0 0.7rem;line-height:1.5;">
                Smaže <strong>všechny záznamy</strong> v těchto tabulkách:
                <code>contacts</code>, <code>workflow_log</code>, <code>contact_notes</code>,
                <code>commissions</code>, <code>assignment_log</code>, <code>sms_log</code>,
                <code>contact_quality_ratings</code>, <code>oz_contact_workflow</code>,
                <code>oz_contact_notes</code>, <code>oz_contact_actions</code>,
                <code>contact_oz_flags</code> a vyprázdní <code>import_log</code>.<br><br>
                <strong>Zachová se:</strong> uživatelé, role, kvóty OZ, stage cíle, milníky, denní cíle, audit_log.<br>
                <strong>Operace je NEVRATNÁ.</strong> Akce se zaznamená do audit_logu (kdo + kdy + kolik řádků).
            </p>

            <form method="post" action="<?= crm_h(crm_url('/admin/import/reset')) ?>"
                  onsubmit="return confirmResetSubmit(this);"
                  style="display:flex;flex-direction:column;gap:0.5rem;max-width:380px;">
                <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">

                <label style="font-size:0.78rem;color:var(--text);">
                    Pro povolení tlačítka napište do pole přesně <code>RESET</code>:
                </label>
                <input type="text" name="confirm_text" id="reset-confirm-text"
                       autocomplete="off" spellcheck="false"
                       placeholder="napište RESET"
                       style="padding:0.45rem 0.6rem;font-size:0.85rem;font-family:monospace;
                              background:var(--bg);color:var(--text);
                              border:1px solid rgba(231,76,60,0.4);border-radius:5px;">

                <button type="submit" id="reset-submit" disabled
                        style="padding:0.55rem 1rem;font-size:0.85rem;font-weight:700;
                               background:#e74c3c;color:#fff;border:0;border-radius:6px;
                               cursor:not-allowed;opacity:0.5;
                               transition:opacity 0.15s, cursor 0.15s;">
                    🗑 Smazat všechny kontakty
                </button>
            </form>
        </div>
    </details>

</section>

<script>
// ── Reset: odemkni tlačítko jen když je v inputu přesně "RESET" ──
(function(){
    const inp = document.getElementById('reset-confirm-text');
    const btn = document.getElementById('reset-submit');
    if (!inp || !btn) return;

    inp.addEventListener('input', () => {
        const ok = inp.value.trim() === 'RESET';
        btn.disabled       = !ok;
        btn.style.cursor   = ok ? 'pointer' : 'not-allowed';
        btn.style.opacity  = ok ? '1' : '0.5';
    });
})();

// ── Druhé potvrzení (browser confirm) tesně před submitem ──
function confirmResetSubmit(form) {
    const inp = form.querySelector('input[name="confirm_text"]');
    if (!inp || inp.value.trim() !== 'RESET') {
        alert('Pro reset musíte napsat přesně "RESET" (velkými písmeny).');
        return false;
    }
    return confirm(
        '⚠ POSLEDNÍ VAROVÁNÍ\n\n' +
        'Skutečně smazat VŠECHNY kontakty a všechny závislé záznamy?\n' +
        'Tato akce je NEVRATNÁ a nelze ji vrátit.\n\n' +
        'Klikněte OK pro pokračování, Cancel pro zrušení.'
    );
}
</script>

<script>
(function(){
    const form     = document.getElementById('import-form');
    const fileIn   = document.getElementById('csv');
    const dropZ    = document.getElementById('import-drop');
    const fnEl     = document.getElementById('import-drop-filename');
    const fsEl     = document.getElementById('import-drop-filesize');
    const dropTxt  = document.getElementById('import-drop-text');
    const progress = document.getElementById('import-progress');
    const progLbl  = document.getElementById('import-progress-label');
    const progPct  = document.getElementById('import-progress-pct');
    const progFill = document.getElementById('import-progress-fill');
    const progHint = document.getElementById('import-progress-hint');
    const btnSub   = document.getElementById('import-submit');

    // ── Filename + size display ──
    function fmtBytes(n){
        if (n < 1024) return n + ' B';
        if (n < 1024*1024) return (n/1024).toFixed(1) + ' KB';
        return (n/1024/1024).toFixed(2) + ' MB';
    }
    fileIn.addEventListener('change', () => {
        const f = fileIn.files && fileIn.files[0];
        if (!f) { fnEl.textContent = ''; fsEl.textContent = ''; return; }
        fnEl.textContent = f.name;
        fsEl.textContent = fmtBytes(f.size);
        dropTxt.textContent = '✓ Soubor vybrán';
    });

    // ── Drag & drop ──
    ['dragenter','dragover'].forEach(ev =>
        dropZ.addEventListener(ev, e => { e.preventDefault(); dropZ.classList.add('is-dragover'); }));
    ['dragleave','drop'].forEach(ev =>
        dropZ.addEventListener(ev, e => { e.preventDefault(); dropZ.classList.remove('is-dragover'); }));
    dropZ.addEventListener('drop', e => {
        if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
            fileIn.files = e.dataTransfer.files;
            fileIn.dispatchEvent(new Event('change'));
        }
    });

    // ── Inline error banner (zobrazí konkrétní chybu, kterou poslal server) ──
    function showInlineError(message) {
        // Reset progress UI
        progress.classList.remove('is-active');
        progFill.classList.remove('import-progress__fill--processing');
        progFill.style.width = '0%';
        progPct.textContent  = '0 %';
        btnSub.disabled = false;
        btnSub.textContent = 'Nahrát a analyzovat';

        // Insert / update error banner před formulářem
        let banner = document.getElementById('import-error-banner');
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'import-error-banner';
            banner.style.cssText =
                'background:rgba(231,76,60,0.10);border:1px solid rgba(231,76,60,0.45);'
                + 'border-left:4px solid #e74c3c;border-radius:0 8px 8px 0;'
                + 'padding:0.75rem 1rem;margin-bottom:1rem;color:var(--text);'
                + 'font-size:0.88rem;line-height:1.5;display:flex;align-items:flex-start;gap:0.6rem;';
            form.parentNode.insertBefore(banner, form);
        }
        banner.innerHTML =
            '<span style="font-size:1.2rem;">❌</span>'
            + '<div><strong>Import selhal:</strong><br>'
            + '<span style="color:var(--muted);">' + escapeHtml(String(message)) + '</span></div>';
        banner.scrollIntoView({behavior: 'smooth', block: 'center'});
    }
    function escapeHtml(s) {
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }

    // ── Submit přes XHR pro progress bar ──
    form.addEventListener('submit', e => {
        const f = fileIn.files && fileIn.files[0];
        if (!f) return;

        e.preventDefault();

        // Skryj případný předchozí error banner
        const oldBanner = document.getElementById('import-error-banner');
        if (oldBanner) oldBanner.remove();

        progress.classList.add('is-active');
        btnSub.disabled = true;
        btnSub.textContent = 'Probíhá…';

        const xhr = new XMLHttpRequest();
        xhr.open('POST', form.action, true);
        // Klíčové: header říká serveru "tohle je XHR" → server vrátí JSON místo redirect
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Accept', 'application/json');

        // Upload progress (0-100 %, fáze "nahrávání")
        xhr.upload.addEventListener('progress', ev => {
            if (!ev.lengthComputable) return;
            const pct = Math.round((ev.loaded / ev.total) * 100);
            progFill.style.width = pct + '%';
            progPct.textContent  = pct + ' %';
            progLbl.textContent  = 'Nahrávám soubor (' + fmtBytes(ev.loaded) + ' / ' + fmtBytes(ev.total) + ')';
        });

        // Po skončení uploadu — server začne analyzovat
        xhr.upload.addEventListener('load', () => {
            progFill.style.width = '100%';
            progPct.textContent  = '';
            progLbl.textContent  = '⏳ Analyzuji soubor… (může trvat až ~30 s pro 300k řádků)';
            progFill.classList.add('import-progress__fill--processing');
            progHint.textContent = 'Server kontroluje řádky, hledá duplicity, ověřuje kraje.';
        });

        // Po response — server vrací JSON (díky X-Requested-With)
        xhr.onreadystatechange = () => {
            if (xhr.readyState !== 4) return;

            let data = null;
            try { data = JSON.parse(xhr.responseText); } catch (_) {}

            if (xhr.status >= 200 && xhr.status < 300 && data && data.ok && data.redirect) {
                // Úspěch — naviguj na preview
                window.location.href = data.redirect;
                return;
            }
            // Chyba — zobraz konkrétní message
            const msg = (data && data.error)
                ? data.error
                : (xhr.status === 0
                    ? 'Žádná odpověď ze serveru. Možná je soubor moc velký, nebo PHP timeout.'
                    : 'HTTP ' + xhr.status + ' — neočekávaná odpověď serveru.');
            showInlineError(msg);
        };
        xhr.onerror = () => showInlineError('Síťová chyba při uploadu.');
        xhr.ontimeout = () => showInlineError('Vypršel časový limit uploadu.');

        const fd = new FormData(form);
        xhr.send(fd);
    });
})();
</script>
