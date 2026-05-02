/* ══════════════════════════════════════════════════════════════════
 * OZ Pracovní plocha — JS logika
 *
 * Dependencies (inline <script> tagy MUSÍ být před tímto souborem):
 *   - window.OZ_CONFIG  ... { userId, csrf, csrfField, urls: {...} }
 *   - window._ozRenewals, window._ozPending  ... data od PHP (renewals, pending leads)
 *   - window._ozBoData, window._ozBoUrlBase  ... data od PHP (BO vrácené karty)
 *
 * Vše původně inline v app/views/oz/leads.php (bývalo přes <?= ... ?>),
 * teď načítáno z window.OZ_CONFIG (PHP-bridge) — viz oz/leads.php.
 *
 * POZN.: Kód NENÍ zabalen v IIFE — záměrně, aby všechny funkce zůstaly
 * globální (tak jak byly v původním inline <script>) a fungovaly z
 * onclick="..." atributů v HTML.
 * ══════════════════════════════════════════════════════════════════ */

var OZC = window.OZ_CONFIG || { userId: 0, csrf: '', csrfField: 'csrf_token', urls: {} };
if (!OZC.urls) OZC.urls = {};

// ══════════════════════════════════════════════════════════════════
// Scroll preservation při změně stavu karty (varianta C — hybrid)
// A) Cílová karta v aktuálním tabu existuje → nativní anchor scroll
//    (controller už redirectuje na ?tab=...#c-{id}) + doladění na střed
// B) Karta zmizela (přesun do jiného tabu) → obnov uloženou Y pozici
// ══════════════════════════════════════════════════════════════════
(function ozScrollPreservation() {
    var SK = 'oz_scroll_state';
    var STALE_MS = 30000;

    function getCurrentTab() {
        var m = window.location.search.match(/[?&]tab=([^&#]+)/);
        return m ? decodeURIComponent(m[1]) : '';
    }

    // Uložit Y + aktuální tab před každým submitem formuláře na této stránce.
    // Klíčové: bublání + capture, ať pokryjeme i formuláře co volají preventDefault.
    // Celé tělo zabaleno v try-catch — error v tomhle handleru NESMÍ zablokovat submit.
    document.addEventListener('submit', function(e) {
        try {
            var form = e.target;
            if (!form || form.nodeName !== 'FORM') return;
            sessionStorage.setItem(SK, JSON.stringify({
                y:   window.scrollY || window.pageYOffset || 0,
                tab: getCurrentTab(),
                ts:  Date.now()
            }));
        } catch (_) { /* private mode / disabled storage / cokoliv jiného — ignoruj */ }
    }, true);

    // Obnova po načtení.
    function restore() {
        var raw;
        try { raw = sessionStorage.getItem(SK); } catch (_) { return; }
        if (!raw) return;
        // Smaž hned, aby další navigace (např. klik na link) nedědila stale stav.
        try { sessionStorage.removeItem(SK); } catch (_) {}

        var st;
        try { st = JSON.parse(raw); } catch (_) { return; }
        if (!st || typeof st.y !== 'number') return;
        // Stale (uživatel třeba odešel a vrátil se za hodinu) → ignoruj
        if (!st.ts || Date.now() - st.ts > STALE_MS) return;

        var hash = window.location.hash; // např. "#c-1234"
        if (hash && hash.indexOf('#c-') === 0) {
            var target = document.getElementById(hash.slice(1));
            if (target) {
                // Karta existuje → browser už k ní nativně skroloval, ale layout se
                // může ještě změnit (snail race AJAX) → doladíme po dalším frame
                requestAnimationFrame(function() {
                    target.scrollIntoView({ block: 'center', behavior: 'instant' });
                });
                return;
            }
            // Karta v aktuálním tabu neexistuje → fallback na uloženou Y
        }
        // Restore Y — jen když jsme ve stejném tabu (jinak by Y nedávalo smysl)
        if (st.tab === getCurrentTab()) {
            window.scrollTo(0, st.y);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', restore);
    } else {
        restore();
    }
})();

// ── Custom confirm modál ─────────────────────────────────────────
(function() {
    let _cb = null;
    const modal   = document.getElementById('oz-confirm-modal');
    const overlay = document.getElementById('oz-modal-overlay');
    const btnOk   = document.getElementById('oz-modal-ok');
    const btnCx   = document.getElementById('oz-modal-cancel');

    window.ozShowConfirm = function(title, bodyHtml, onOk, icon) {
        document.getElementById('oz-modal-title').textContent = title;
        document.getElementById('oz-modal-body').innerHTML    = bodyHtml;
        document.getElementById('oz-modal-icon').textContent  = icon || '🏆';
        _cb = onOk;
        modal.style.display = 'flex';
    };

    btnOk.addEventListener('click', () => {
        modal.style.display = 'none';
        if (_cb) { _cb(); _cb = null; }
    });
    btnCx.addEventListener('click', () => {
        modal.style.display = 'none';
        _cb = null;
    });
    // Klik na overlay = zrušit
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) { modal.style.display = 'none'; _cb = null; }
    });
    // ESC = zrušit
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.style.display !== 'none') {
            modal.style.display = 'none'; _cb = null;
        }
    });
})();

// ── Poznámka — rozbalit / sbalit (BO_VRACENO) ───────────────────────
function ozNoteExpand(cId) {
    const stub = document.getElementById('note-stub-' + cId);
    const wrap = document.getElementById('note-wrap-' + cId);
    const note = document.getElementById('note-' + cId);
    if (!note) return;
    if (stub) stub.style.display = 'none';
    if (wrap) wrap.style.display = '';
    setTimeout(() => note.focus(), 30);
}
function ozNoteCollapse(cId) {
    const stub = document.getElementById('note-stub-' + cId);
    const wrap = document.getElementById('note-wrap-' + cId);
    const note = document.getElementById('note-' + cId);
    if (!stub || !wrap) return;
    if (note) note.value = '';
    wrap.style.display = 'none';
    stub.style.display = 'block';
}

// ── Validace: poznámka povinná (kromě stavů s data-optional="1") ────
function ozRequireNote(cId) {
    const note = document.getElementById('note-' + cId);
    if (!note) return true;
    // Ve stavu BO_VRACENO je poznámka volitelná (data-optional="1")
    if (note.dataset.optional === '1') return true;
    if (note.value.trim() !== '') {
        note.classList.remove('oz-note-input--required');
        return true;
    }
    note.classList.add('oz-note-shake');
    note.classList.add('oz-note-input--required');
    note.focus();
    note.placeholder = '⚠ Nejdříve napište poznámku!';
    setTimeout(() => note.classList.remove('oz-note-shake'), 550);
    return false;
}

function ozSubmit(cId, stav) {
    if (!ozRequireNote(cId)) return;
    document.getElementById('stav-' + cId).value = stav;
    document.getElementById('ozf-' + cId).submit();
}

function ozConfirm(cId, stav, msg, icon) {
    if (!ozRequireNote(cId)) return;
    ozShowConfirm(msg, '', () => ozSubmit(cId, stav), icon || '❓');
}

// "Znovu předat BO" z UZAVRENO bannerze (mimo hlavní lead-status form)
function ozReopenFromUzavreno(cId) {
    ozShowConfirm(
        'Předat zpět do Back-office?',
        '',
        () => {
            const form = document.getElementById('oz-reopen-bo-form-' + cId);
            if (form) form.submit();
        },
        '📤'
    );
}

function ozTogglePanel(panelId, cId) {
    // Zavřít ostatní panely stejného kontaktu
    const form = document.getElementById('ozf-' + cId);
    if (form) {
        form.querySelectorAll('.oz-datetime-panel, .oz-smlouva-panel').forEach(p => {
            if (p.id !== panelId) p.classList.remove('visible');
        });
    }
    const panel = document.getElementById(panelId);
    if (panel) panel.classList.toggle('visible');
}

// JS bloky pro dynamické instalační adresy odebrány — funkčnost přesunuta
// do panelu "Nabídnuté služby" (typ Internet, identifier = adresa).

function ozToggleSmlouvaPanel(cId) {
    if (!ozRequireNote(cId)) return;
    const panelId = 'smlouva-panel-' + cId;
    const form = document.getElementById('ozf-' + cId);
    if (form) {
        form.querySelectorAll('.oz-datetime-panel, .oz-smlouva-panel').forEach(p => {
            if (p.id !== panelId) p.classList.remove('visible');
        });
    }
    const panel = document.getElementById(panelId);
    if (panel) panel.classList.toggle('visible');
    // Focus na BMSL pole
    if (panel && panel.classList.contains('visible')) {
        setTimeout(() => {
            const bmslInput = document.getElementById('bmsl-' + cId);
            if (bmslInput) bmslInput.focus();
        }, 50);
    }
}

function ozToggleReklamacePanel(cId) {
    const panel = document.getElementById('rekl-panel-' + cId);
    if (!panel) return;
    panel.classList.toggle('visible');
    if (panel.classList.contains('visible')) {
        setTimeout(() => {
            const ta = document.getElementById('rekl-note-' + cId);
            if (ta) ta.focus();
        }, 50);
    }
}

/**
 * 2-step inline potvrzení pro tlačítko "Odeslat navolávačce" (chybný lead).
 *
 * UX: stejný pattern jako na call screenu (/oz/work).
 * - První klik → validuje textarea reason, button se změní na warning amber
 *   "⚠ Klikni znovu pro potvrzení". Reset timer 5s.
 * - Druhý klik do 5s → submit formuláře.
 * - Po 5s nečinnosti → reset zpět.
 *
 * Per-card state v WeakMap (každá karta má svůj timer + flag).
 */
const _ozReklamaceState = new Map(); // cId → { pending: bool, timeout: id }

function ozLeadsConfirmReklamace(btn, cId) {
    const ta   = document.getElementById('rekl-note-' + cId);
    const form = document.getElementById('rekl-form-' + cId);
    if (!ta || !form) return;

    const reason = (ta.value || '').trim();
    if (reason === '') {
        alert('⚠ Vyplňte důvod chybného leadu.');
        ta.focus();
        return;
    }

    let state = _ozReklamaceState.get(cId);
    if (state && state.pending) {
        // Druhý klik do 5s = potvrzeno → submit
        clearTimeout(state.timeout);
        _ozReklamaceState.delete(cId);
        btn.disabled = true;
        form.submit();
        return;
    }

    // První klik — switch button to warning state
    const origText = btn.dataset.origText || btn.textContent;
    btn.textContent = '⚠ Klikni znovu pro potvrzení';
    btn.classList.add('oz-btn--confirm-pending');

    state = {
        pending: true,
        timeout: setTimeout(() => {
            btn.textContent = origText;
            btn.classList.remove('oz-btn--confirm-pending');
            _ozReklamaceState.delete(cId);
        }, 5000),
    };
    _ozReklamaceState.set(cId, state);
}

function ozBmslPreview(cId, val) {
    const preview = document.getElementById('bmsl-preview-' + cId);
    if (!preview) return;
    const n = parseFloat(val);
    if (isNaN(n) || n < 100) { preview.textContent = ''; return; }
    const rounded = Math.floor(n / 100) * 100;
    preview.textContent = '= ' + new Intl.NumberFormat('cs-CZ').format(rounded) + ' Kč';
}

function ozSubmitSmlouva(cId) {
    const bmslInput   = document.getElementById('bmsl-' + cId);
    const dateInput   = document.getElementById('smlouvadate-' + cId);
    const nabidkaInput = document.getElementById('nabidkaid-' + cId);
    const bmslVal     = bmslInput ? parseFloat(bmslInput.value) : 0;

    if (!bmslInput || isNaN(bmslVal) || bmslVal < 100) {
        if (bmslInput) {
            bmslInput.style.borderColor = 'var(--oz-nezajem)';
            bmslInput.focus();
        }
        return;
    }
    if (!dateInput || !dateInput.value) {
        if (dateInput) dateInput.focus();
        return;
    }
    if (!nabidkaInput || !nabidkaInput.value.trim()) {
        if (nabidkaInput) {
            nabidkaInput.style.borderColor = 'var(--oz-nezajem)';
            nabidkaInput.focus();
        }
        return;
    }

    // Validace + serializace instalačních adres odebrána —
    // adresy se nyní spravují v panelu "Nabídnuté služby".

    const rounded = Math.floor(bmslVal / 100) * 100;
    const fmt  = new Intl.NumberFormat('cs-CZ').format(rounded);

    const body = `<strong>${fmt} Kč</strong> BMSL<br>
                  <span style="color:var(--muted);font-size:0.8rem;">Datum podpisu: ${dateInput.value}</span><br>
                  <span style="color:var(--muted);font-size:0.8rem;">ID nabídky: <strong style="color:var(--text);font-family:monospace;">${nabidkaInput.value.trim()}</strong></span>`;
    ozShowConfirm('🏆 Potvrdit smlouvu?', body, () => ozSubmit(cId, 'SMLOUVA'));
}

// Reset border-color při editaci (BMSL, nabídka)
document.addEventListener('input', function(e) {
    if (!e.target) return;
    const id  = e.target.id || '';
    if (id.startsWith('bmsl-') || id.startsWith('nabidkaid-')) {
        e.target.style.borderColor = '';
    }
});

// ── Pending popover (globální, mimo stacking context) ───────────
function ozCloseAllPops() {
    const pop = document.getElementById('oz-pending-pop-global');
    if (pop) pop.style.display = 'none';
    const bd = document.getElementById('oz-pending-backdrop');
    if (bd) bd.classList.remove('visible');
    window._ozOpenCallerId = null;
    // Zavřít i BO popover (sdílí backdrop)
    const boPop = document.getElementById('oz-bo-pop');
    if (boPop) boPop.classList.remove('visible');
    window._ozBoPopOpen = false;
    // Zavřít i renewal popover (sdílí backdrop)
    const rnPop = document.getElementById('oz-renewal-pop');
    if (rnPop) rnPop.classList.remove('visible');
    window._ozRenewalPopOpen = false;
}

// ── Renewal popover — ODSTRANĚNO (Krok 5F refactor)
//   Sidebar s renewal stackem byl odstraněn v Kroku 5a (duplikoval /oz/queue,
//   kde mají renewal alerty vlastní sekci). Funkce ozRenewalTogglePop
//   nemá v HTML žádný caller — bezpečně odstraněna.

// ── BO popover — výpis vrácených karet ─────────────────────────────
// Volá se z onclick="ozBoTogglePop(event)" na .oz-bo-stack (jen při 2+ kartičkách).
// Pro 1 kartičku je <a href> přímý anchor — popover se vůbec nepoužije.
function ozBoTogglePop(event) {
    if (event) event.stopPropagation();
    const pop = document.getElementById('oz-bo-pop');
    const bd  = document.getElementById('oz-pending-backdrop');
    if (!pop) return;

    // Toggle: pokud už je otevřený → zavři
    if (window._ozBoPopOpen) { ozCloseAllPops(); return; }
    // Zavřít případný pending popover
    ozCloseAllPops();

    const data = window._ozBoData || [];
    const base = window._ozBoUrlBase || '';
    if (data.length === 0) return;

    // Sestavit seznam firem jako odkazy na konkrétní karty
    let listHtml = '';
    for (const b of data) {
        const href = base + b.id;
        listHtml += `<a href="${_ozEsc(href)}" class="oz-bo-pop__item" title="${_ozEsc(b.firma)}">
            <span class="oz-bo-pop__firma">${_ozEsc(b.firma)}</span>
            <span class="oz-bo-pop__arrow">→</span>
        </a>`;
    }
    pop.innerHTML = `
        <div class="oz-bo-pop__header">↩️ Vráceno z BO (${data.length})</div>
        <div class="oz-bo-pop__list">${listHtml}</div>`;

    // Pozice: u věže BO sidebaru, vlevo od ní
    const stack = document.querySelector('.oz-bo-sidebar .oz-bo-stack');
    if (stack) {
        const rect = stack.getBoundingClientRect();
        pop.style.top   = Math.max(8, rect.top) + 'px';
        pop.style.right = (window.innerWidth - rect.left + 10) + 'px';
        pop.classList.add('visible');
        // Pokud popover přesahuje pod spodní okraj, posuň nahoru
        requestAnimationFrame(() => {
            const popH = pop.offsetHeight;
            if (rect.top + popH > window.innerHeight - 16) {
                pop.style.top = Math.max(8, window.innerHeight - popH - 16) + 'px';
            }
        });
    }

    if (bd) bd.classList.add('visible');
    window._ozBoPopOpen = true;
}

// ── Pending caller popover — ODSTRANĚNO (Krok 5F refactor)
//   Pending sidebar v leads.php byl nahrazen /oz/queue (kde má per-caller
//   sgrupování zabudované). Funkce ozTogglePop nemá v HTML žádný caller.

function _ozEsc(str) {
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Performance widget — ODSTRANĚNO (Krok 4 refactor)
//   Performance widget (osobní milníky + týmové stages) byl přesunut
//   na /oz dashboard jako sbalitelná sekce. Funkce ozTogglePerfWidget
//   nemá v HTML žádný caller.

// Změna textarey odstraní warning styl
document.querySelectorAll('.oz-note-input').forEach(ta => {
    ta.addEventListener('input', function() {
        if (this.value.trim()) {
            this.classList.remove('oz-note-input--required');
            if (this.placeholder.startsWith('⚠')) {
                this.placeholder = 'Napište poznámku (povinné před jakoukoliv akcí)…';
            }
        }
    });
});

// ── Šněčí závody OZ ──────────────────────────────────────────────
(function() {
    const inner = document.getElementById('oz-race-inner');
    if (!inner) return;

    function render(data) {
        if (!data.ok || !data.oz || data.oz.length === 0) {
            inner.innerHTML = '<div style="font-size:0.75rem;color:var(--muted);padding:0.3rem 0;">Žádná data.</div>';
            return;
        }
        const myId = OZC.userId;
        let html = '<div class="race-tracks">'
                 + '<div class="race-side">Start</div>'
                 + '<div class="race-lanes">';
        for (const oz of data.oz) {
            const pct = Math.max(1, Math.min(98, oz.pct || 0));
            const me  = oz.me || oz.id === myId;
            html += `<div class="race-lane">
                <div class="race-snail ${me ? 'race-snail--me' : ''}" style="left:${pct}%">
                    <span class="race-snail__emoji">🐌</span>
                    <span class="race-snail__name">${oz.name} (${oz.wins})</span>
                </div>
            </div>`;
        }
        html += '</div><div class="race-side race-side--end">Cíl</div></div>';
        inner.innerHTML = html;
    }

    function load() {
        fetch(OZC.urls.ozRaceJson)
            .then(r => r.json())
            .then(render)
            .catch(() => {
                inner.innerHTML = '<span style="color:#e74c3c;font-size:0.75rem;">Chyba načítání.</span>';
            });
    }

    load();
    setInterval(load, 30000);
})();

// ═══ ARES lookup — načte název firmy a adresu podle IČO ═════════════
function ozAresLookup(cId) {
    const icoInput  = document.getElementById('oz-edit-ico-' + cId);
    const firmaInput = document.getElementById('oz-edit-firma-' + cId);
    const adresaInput = document.getElementById('oz-edit-adresa-' + cId);
    const status   = document.getElementById('oz-ares-status-' + cId);
    if (!icoInput || !status) return;

    const ico = (icoInput.value || '').replace(/\D+/g, '');
    if (ico.length !== 8) {
        status.textContent = '⚠ IČO musí mít 8 číslic.';
        status.style.color = '#e74c3c';
        return;
    }
    status.textContent = '⏳ Načítám z ARES…';
    status.style.color = 'var(--muted)';

    fetch(OZC.urls.ozAresLookup + '?ico=' + encodeURIComponent(ico), {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
    })
    .then(r => r.json().then(j => ({ ok: r.ok, data: j })))
    .then(({ ok, data }) => {
        if (!ok || !data || !data.ok) {
            status.textContent = '⚠ ' + ((data && data.error) ? data.error : 'Chyba načtení.');
            status.style.color = '#e74c3c';
            return;
        }
        let filled = [];
        if (firmaInput && data.firma) {
            firmaInput.value = data.firma;
            filled.push('firma');
        }
        if (adresaInput && data.adresa) {
            adresaInput.value = data.adresa;
            filled.push('adresa');
        }
        status.textContent = filled.length
            ? '✓ Načteno z ARES (' + filled.join(' + ') + '). Zkontrolujte a uložte.'
            : '⚠ ARES nevrátil firmu ani adresu.';
        status.style.color = filled.length ? '#2ecc71' : '#f39c12';
    })
    .catch(() => {
        status.textContent = '⚠ Síťová chyba — zkuste to znovu.';
        status.style.color = '#e74c3c';
    });
}

// ═══ Editace údajů kontaktu — toggle view/edit ════════════════════════
function ozContactEditToggle(cId) {
    const view = document.getElementById('oz-info-view-' + cId);
    const edit = document.getElementById('oz-info-edit-' + cId);
    if (!view || !edit) return;
    const isEditOpen = edit.style.display !== 'none';
    if (isEditOpen) {
        edit.style.display = 'none';
        view.style.display = '';
    } else {
        view.style.display = 'none';
        edit.style.display = 'block';
        setTimeout(() => {
            const firstInput = edit.querySelector('input[name="firma"]');
            if (firstInput) firstInput.focus();
        }, 30);
    }
}

// ═══ Předat BO dialog (zobrazí se, když chybí ID nabídky) ════════════
function ozPredatBoDialogToggle(cId) {
    const dlg = document.getElementById('oz-predat-bo-dialog-' + cId);
    if (!dlg) return;
    const isOpen = dlg.style.display === 'block';
    if (isOpen) {
        dlg.style.display = 'none';
    } else {
        dlg.style.display = 'block';
        setTimeout(() => {
            const input = dlg.querySelector('input[name="offer_id"]');
            if (input) { input.focus(); input.select(); }
        }, 30);
    }
}

// ═══ ID nabídky z OT — toggle inline editoru ═════════════════════════
function ozOfferIdToggle(cId) {
    const view = document.getElementById('oz-offer-view-' + cId);
    const form = document.getElementById('oz-offer-form-' + cId);
    if (!view || !form) return;
    const isFormOpen = form.style.display === 'flex';
    if (isFormOpen) {
        form.style.display = 'none';
        view.style.display = 'flex';
    } else {
        view.style.display = 'none';
        form.style.display = 'flex';
        setTimeout(() => {
            const input = form.querySelector('input[name="offer_id"]');
            if (input) { input.focus(); input.select(); }
        }, 30);
    }
}

// ═══ Drag & drop pořadí tabů (top-level + sub-taby uvnitř super-tabu) ═
(function () {
    const container = document.getElementById('oz-tabs-container');
    if (!container) return;

    let dragSrc = null;
    let dragScope = null; // 'top' nebo super-tab key (pro sub-tab drag)

    function attachHandlers() {
        // Top-level wrappers — přímí potomci kontejneru
        container.querySelectorAll(':scope > .oz-tab-wrap[draggable="true"]').forEach(el => {
            bindDrag(el, 'top');
        });
        // Sub-tab wrappers uvnitř super-tab dropdownů
        container.querySelectorAll('.oz-supertab-dropdown > .oz-tab-wrap--child[draggable="true"]').forEach(el => {
            const parent = el.dataset.parent || '';
            bindDrag(el, parent);
        });
    }

    function bindDrag(el, scope) {
        el.addEventListener('dragstart', (e) => {
            // Drag začíná jen na samotném wrapperu, ne na vnořeném inputu/buttonu
            dragSrc = el;
            dragScope = scope;
            el.style.opacity = '0.4';
            try {
                e.dataTransfer.setData('text/plain', el.dataset.tabKey || '');
                e.dataTransfer.effectAllowed = 'move';
            } catch (err) {}
            // Sub-tab? Udrž parent super-tab otevřený během dragu (jinak se zavře hover-em)
            if (scope !== 'top') {
                const parentWrap = el.closest('.oz-tab-wrap--super');
                if (parentWrap) parentWrap.classList.add('oz-tab-wrap--super-open');
            }
            e.stopPropagation();
        });
        el.addEventListener('dragend', () => {
            el.style.opacity = '';
            container.querySelectorAll('.oz-tab-wrap').forEach(t => {
                t.style.outline = '';
                t.classList.remove('oz-tab-drop-target');
            });
            // Zruš force-open na všech super-tabech (vrátí se k hover-only chování)
            document.querySelectorAll('.oz-tab-wrap--super-open').forEach(w => {
                w.classList.remove('oz-tab-wrap--super-open');
            });
            dragSrc = null;
            dragScope = null;
        });
        el.addEventListener('dragover', (e) => {
            if (!dragSrc || dragSrc === el) return;
            // Drop jen v rámci stejné úrovně (top↔top nebo stejná super-skupina)
            const myScope = (el.dataset.parent || (el.dataset.super === '1' ? 'top' : 'top'));
            const myActualScope = el.classList.contains('oz-tab-wrap--child') ? (el.dataset.parent || '') : 'top';
            if (myActualScope !== dragScope) return;
            e.preventDefault();
            e.stopPropagation();
            el.style.outline = '2px dashed rgba(52,152,219,0.6)';
        });
        el.addEventListener('dragleave', () => {
            el.style.outline = '';
        });
        el.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            el.style.outline = '';
            if (!dragSrc || dragSrc === el) return;
            const myActualScope = el.classList.contains('oz-tab-wrap--child') ? (el.dataset.parent || '') : 'top';
            if (myActualScope !== dragScope) return;

            // Insert before/after podle pozice myši (vertikální v dropdownu, horizontální top-level)
            const rect = el.getBoundingClientRect();
            const isVertical = el.classList.contains('oz-tab-wrap--child');
            const before = isVertical
                ? (e.clientY - rect.top) < (rect.height / 2)
                : (e.clientX - rect.left) < (rect.width / 2);
            el.parentNode.insertBefore(dragSrc, before ? el : el.nextSibling);
            saveTabOrder();
        });
    }

    function saveTabOrder() {
        // Top-level pořadí — přímí potomci kontejneru
        const top = Array.from(container.querySelectorAll(':scope > .oz-tab-wrap'))
            .map(w => w.dataset.tabKey)
            .filter(k => !!k);

        // Sub-tab pořadí pro každou skupinu (Plán, BO)
        const subPlanEl = container.querySelector('.oz-supertab-dropdown[data-super-dropdown="plan"]');
        const subBoEl   = container.querySelector('.oz-supertab-dropdown[data-super-dropdown="bo"]');
        const subPlan = subPlanEl
            ? Array.from(subPlanEl.querySelectorAll(':scope > .oz-tab-wrap--child'))
                  .map(w => w.dataset.tabKey).filter(k => !!k)
            : [];
        const subBo = subBoEl
            ? Array.from(subBoEl.querySelectorAll(':scope > .oz-tab-wrap--child'))
                  .map(w => w.dataset.tabKey).filter(k => !!k)
            : [];

        const fd = new FormData();
        fd.append(OZC.csrfField, OZC.csrf);
        top.forEach(k     => fd.append('order[]', k));
        subPlan.forEach(k => fd.append('sub_order_plan[]', k));
        subBo.forEach(k   => fd.append('sub_order_bo[]', k));

        fetch(OZC.urls.ozTabReorder, {
            method: 'POST', body: fd, credentials: 'same-origin'
        }).catch(() => { /* tichá chyba */ });
    }

    attachHandlers();
})();

// ═══ Super-tab toggle (pro touch zařízení — hover nefunguje) ═════════
function ozSuperTabToggle(event, key) {
    // Na desktopu hover otevírá dropdown sám; klik tedy primárně pro touch.
    // Detekce touch: použijeme matchMedia (hover: none) jako proxy.
    const isTouch = window.matchMedia && window.matchMedia('(hover: none)').matches;
    if (!isTouch) return; // Na desktopu klik na parent nedělá nic — naviguje sub-tab.

    event.preventDefault();
    event.stopPropagation();
    const wrap = event.currentTarget.closest('.oz-tab-wrap--super');
    if (!wrap) return;
    // Zavři ostatní open
    document.querySelectorAll('.oz-tab-wrap--super-open').forEach(w => {
        if (w !== wrap) w.classList.remove('oz-tab-wrap--super-open');
    });
    wrap.classList.toggle('oz-tab-wrap--super-open');
}
// Klik mimo super-tab → zavřít všechny otevřené (touch)
document.addEventListener('click', (e) => {
    if (!e.target.closest('.oz-tab-wrap--super')) {
        document.querySelectorAll('.oz-tab-wrap--super-open').forEach(w => {
            w.classList.remove('oz-tab-wrap--super-open');
        });
    }
});

// ═══ Předat BO confirm — zobrazí ID nabídky + BMSL k potvrzení ═══════
function ozPredatBoConfirm(cId, title, bodyHtml) {
    // Stejně jako ozConfirm: poznámku zkontroluj PŘED zobrazením dialogu
    if (!ozRequireNote(cId)) return;
    if (typeof ozShowConfirm !== 'function') {
        // Fallback — pokud z nějakého důvodu chybí (neměl by)
        if (confirm(title.replace(/<[^>]+>/g, ''))) ozSubmit(cId, 'BO_PREDANO');
        return;
    }
    ozShowConfirm(title, bodyHtml || '', () => ozSubmit(cId, 'BO_PREDANO'), '📤');
}

// ═══ BMSL: zaokrouhlení dolů na celé stokoruny při blur ══════════════
function ozBmslRoundDown(input, cId) {
    if (!input) return;
    const raw = String(input.value || '').replace(/[\s,]/g, '').replace(',', '.');
    const num = parseFloat(raw);
    if (!isFinite(num) || num < 100) {
        // Příliš málo nebo nevalidní — necháme jak je, server odmítne
        return;
    }
    const rounded = Math.floor(num / 100) * 100;
    input.value = rounded;
    ozBmslPreviewPredat(cId, rounded);
}
function ozBmslPreviewPredat(cId, value) {
    const preview = document.getElementById('bmsl-predat-preview-' + cId);
    if (!preview) return;
    const num = parseFloat(String(value).replace(/[\s,]/g, '').replace(',', '.'));
    if (!isFinite(num) || num < 100) {
        preview.textContent = '';
        return;
    }
    const rounded = Math.floor(num / 100) * 100;
    preview.textContent = '= ' + rounded.toLocaleString('cs-CZ') + ' Kč';
}

// ═══ BO progress checkboxy — AJAX toggle (OZ + BO sdílí stejné DOM API) ═
function ozCheckboxToggle(input) {
    if (!input) return;
    const cId     = input.dataset.cbCid || '';
    const field   = input.dataset.cbField || '';
    const tab     = input.dataset.cbTab || '';
    const role    = input.dataset.cbRole || 'oz'; // 'oz' nebo 'bo'
    const checked = input.checked ? 1 : 0;

    // Optimistický UI feedback — disable input dokud se nevrátí response
    input.disabled = true;

    const fd = new FormData();
    fd.append(OZC.csrfField, OZC.csrf);
    fd.append('contact_id', cId);
    fd.append('field', field);
    fd.append('tab', tab);
    if (checked) fd.append('checked', '1');

    const url = role === 'bo' ? OZC.urls.boCheckboxToggle
                              : OZC.urls.ozCheckboxToggle;

    fetch(url, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        input.disabled = false;
        if (!data || !data.ok) {
            // Vrátit checkbox do původního stavu
            input.checked = !checked;
            if (data && data.error) alert(data.error);
            return;
        }
        // Pokud je to "podpis_potvrzen", BMSL bar potřebuje přepočet — reload (zachová hash anchor).
        if (field === 'podpis_potvrzen') {
            // Drobný delay, ať uživatel uvidí změnu checkboxu před reloadem
            setTimeout(() => { window.location.reload(); }, 250);
            return;
        }
        // Ostatní checkboxy: jen visualní aktualizace
        const label = input.closest('label');
        if (label) {
            const span = label.querySelector('span');
            if (span) {
                if (checked) {
                    span.style.color = 'var(--oz-bo)';
                    span.style.fontWeight = '600';
                } else {
                    span.style.color = 'var(--muted)';
                    span.style.fontWeight = '';
                }
            }
        }
    })
    .catch(() => {
        input.disabled = false;
        input.checked = !checked;
        alert('Chyba sítě — zkuste to znovu.');
    });
}

// ═══ Pracovní deník — sbalit / rozbalit ══════════════════════════════
function ozActionsToggle(cId) {
    const body = document.getElementById('oz-actions-body-' + cId);
    const btn  = document.getElementById('oz-actions-toggle-' + cId);
    if (!body || !btn) return;
    const isOpen = body.style.display !== 'none';
    if (isOpen) {
        body.style.display = 'none';
        btn.textContent = '▼ rozbalit';
    } else {
        body.style.display = 'flex';
        btn.textContent = '▲ sbalit';
    }
}

// ═══ Pracovní deník — AJAX submit (bez reloadu, OZ zůstane na místě) ═
document.addEventListener('submit', function (e) {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.action && form.action.indexOf('/oz/action/add') !== -1) {
        e.preventDefault();
        const fd = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (!data || !data.ok) {
                alert((data && data.error) ? data.error : 'Chyba při ukládání.');
                return;
            }
            // Najít list záznamů uvnitř těla deníku stejné karty
            const body = form.closest('[id^="oz-actions-body-"]');
            if (!body) return;
            // Vložit nový řádek na začátek seznamu (DESC pořadí)
            const list = body.querySelector('div[style*="flex-direction:column;gap:0.25rem"]')
                       || body.querySelector('ul')  // fallback
                       || (() => { // pokud žádný list ještě není (prázdný stav), vytvořit
                            // odebrat "Zatím žádný úkon" placeholder
                            const empty = body.querySelector('div[style*="font-style:italic"]');
                            if (empty) empty.remove();
                            const wrap = document.createElement('div');
                            wrap.style.cssText = 'display:flex;flex-direction:column;gap:0.25rem;';
                            body.appendChild(wrap);
                            return wrap;
                          })();
            const row = document.createElement('div');
            row.style.cssText = 'display:flex;align-items:flex-start;gap:0.5rem;font-size:0.78rem;'
                              + 'padding:0.3rem 0.55rem;border-radius:4px;'
                              + 'background:rgba(52,152,219,0.07);border:1px solid rgba(52,152,219,0.25);';
            const escapeHtml = (s) => String(s).replace(/[&<>"']/g, c =>
                ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
            row.innerHTML =
                '<span style="flex:0 0 90px;color:#9b59b6;font-weight:700;font-family:monospace;font-size:0.74rem;">'
                  + escapeHtml(data.date_fmt) + '</span>'
                + '<span style="flex:1 1 auto;color:var(--text);white-space:pre-wrap;">'
                  + escapeHtml(data.action_text) + '</span>'
                + '<span style="flex:0 0 auto;font-size:0.66rem;color:var(--muted);'
                  + 'padding:0.05rem 0.4rem;border-radius:3px;background:rgba(255,255,255,0.04);">'
                  + '🛒 ' + escapeHtml(data.author_name) + '</span>'
                + '<span style="width:22px;height:22px;flex:0 0 auto;" aria-hidden="true"></span>';
            list.insertBefore(row, list.firstChild);

            // Aktualizovat počítadlo (X) v hlavičce deníku
            const header = body.previousElementSibling;
            if (header) {
                const countSpan = header.querySelector('span[style*="font-size:0.65rem"]');
                if (countSpan) {
                    const m = countSpan.textContent.match(/\((\d+)\)/);
                    const n = m ? parseInt(m[1], 10) + 1 : 1;
                    countSpan.textContent = '(' + n + ')';
                }
            }

            // Reset textových polí (datum nechat na dnešní)
            const textInput = form.querySelector('input[name="action_text"]');
            if (textInput) { textInput.value = ''; textInput.focus(); }
        })
        .catch(() => alert('Chyba při ukládání úkonu.'));
    }
});

// ═══ Nabídnuté služby (Fáze 2 CRUD) — DEAKTIVOVÁNO ═══════════════════
// UI panel je vypnutý v body view (vyhledat "DEAKTIVOVÁNO" výše).
// Pro reaktivaci změňte níže "if (false)" na "if (true)" + stejně tak ve view.
// (Vlastní deaktivovaný kód žije v inline <script>-u v oz/leads.php uvnitř
//  PHP if(false){...}, takže když se "if(false)" změní, JS se znovu aktivuje.)
// ═══ /Nabídnuté služby — DEAKTIVOVÁNO ═════════════════════════════════
