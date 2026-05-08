<?php
/**
 * @var string $title
 * @var string $csrf
 * @var ?string $flash
 */
?>
<!-- Grid.js (CDN) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/gridjs/dist/theme/mermaid.min.css">
<script src="https://cdn.jsdelivr.net/npm/gridjs/dist/gridjs.umd.js"></script>

<style>
.dg-wrap { padding: 0.8rem 1rem 1.2rem; max-width: 1400px; margin: 0 auto; }
.dg-wrap h1 { margin: 0 0 0.4rem; font-size: 1.35rem; color: var(--color-text); }
.dg-wrap .lead { color: var(--color-text-muted); font-size: 0.85rem; margin-bottom: 1rem; }
/* (dg-breadcrumb odstraněn — navigaci řeší sidebar) */

.dg-toolbar {
    display: flex; gap: 0.6rem; flex-wrap: wrap; align-items: center;
    padding: 0.7rem 0.95rem;
    background: var(--color-card-bg);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    margin-bottom: 0.8rem;
    box-shadow: var(--shadow-card);
}
.dg-toolbar__info {
    flex: 1 1 auto;
    font-size: 0.85rem;
    color: var(--color-text-muted);
}
.dg-toolbar__info strong { color: var(--color-text); }
.dg-toolbar__refresh-status {
    display: inline-block;
    margin-left: 0.4rem;
    padding: 0.15rem 0.55rem;
    font-size: 0.7rem;
    font-weight: 700;
    border-radius: 999px;
    background: var(--color-badge-uzavreno-bg);
    color: var(--color-badge-uzavreno);
    border: 1px solid #bbf7d0;
}
.dg-toolbar__refresh-status--paused {
    background: var(--color-surface);
    color: var(--color-text-muted);
    border-color: var(--color-border);
}
.dg-toolbar__refresh-status--loading {
    background: var(--color-badge-nove-bg);
    color: var(--color-badge-nove);
    border-color: #b5d4f4;
}
.dg-toolbar label {
    display: flex; gap: 0.35rem; align-items: center;
    font-size: 0.8rem; color: var(--color-text-muted); cursor: pointer;
    font-weight: 500;
}
.dg-toolbar select, .dg-toolbar input[type=text] {
    padding: 0.4rem 0.65rem;
    background: #ffffff;
    color: var(--color-text);
    border: 1px solid var(--color-border-strong);
    border-radius: var(--radius-btn);
    font-size: 0.82rem;
    font-family: var(--font-main);
}
.dg-toolbar button {
    padding: 0.4rem 0.8rem;
    border: 1px solid var(--color-border-strong);
    background: var(--color-btn-bg);
    color: var(--color-text);
    border-radius: var(--radius-btn);
    cursor: pointer;
    font-size: 0.82rem;
    font-weight: 500;
    font-family: var(--font-main);
}
.dg-toolbar button:hover { background: var(--color-border); }
.dg-toolbar button.primary {
    background: var(--color-badge-nove);
    color: #fff;
    border-color: var(--color-badge-nove);
}

/* ── Grid.js: Excel-like LIGHT theme uvnitř tmavé stránky ──
   Důvod: jak chce uživatel "kdyby otevřel Excel" — bílý list, černý text.
   Tabulka má vlastní rámeček, takže nepůsobí cize na tmavém pozadí. */
/* Vnější container — jen vizuální (rámeček, stín). Scroll patří wrapperu uvnitř. */
#dg-scroll-container {
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.15);
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.18);
    /* overflow: hidden zde nefunguje se sticky headerem — místo něj ošetříme corners přes border-radius */
    overflow-x: auto; /* horizontální scroll fallback (top scrollbar je primární) */
    overflow-y: visible; /* sticky header potřebuje vidět scroll svého rodiče */
}
.gridjs-wrapper {
    background: #ffffff;
    border: 0 !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    overflow-x: auto !important;     /* horizontální scrollbar */
    overflow-y: auto !important;     /* vlastní vertikální scroll — to je klíč pro sticky header */
    max-height: calc(100vh - 280px); /* tabulka má vlastní scrollovací výšku → sticky thead drží uvnitř */
}
.gridjs-table { min-width: 3400px; } /* všechny sloupce se vejdou — wrapper scrolluje horizontálně */
.gridjs-table { font-size: 0.82rem; color: #1a1a1a; background: #ffffff; }
.gridjs-th {
    background: #f3f4f6 !important;
    color: #4a5568 !important;
    font-weight: 700;
    font-size: 0.7rem !important;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 0.55rem 0.7rem !important;
    border-bottom: 2px solid #cbd5e0 !important;
    /* DŮLEŽITÉ — povolit zalamování dlouhých hlaviček (jinak Grid.js zkrátí "Důvod zamítnutí" → "DŮVOD ZA…") */
    white-space: normal !important;
    word-wrap: break-word;
    line-height: 1.25;
    vertical-align: middle;
    min-height: 2.4rem;
}
/* Sticky header — celý <thead> zůstává při scrollu nahoře.
   Sticky je vůči nejbližšímu scrollujícímu rodiči — zde to je .crm-content.
   POZN: Top je 46px (výška topbar) aby header neproplival pod page-header. */
.gridjs-table thead,
.gridjs-table .gridjs-head {
    position: sticky !important;
    top: 0 !important;
    z-index: 100;
    background: #f3f4f6 !important;
    box-shadow: 0 2px 6px rgba(0,0,0,0.12);
}
.gridjs-th-content {
    white-space: normal !important;
    text-overflow: clip !important;
    overflow: visible !important;
}
.gridjs-th-sort:hover { background: #e2e8f0 !important; cursor: pointer; }
.gridjs-tr { border-bottom: 1px solid #e2e8f0 !important; background: #ffffff; }
.gridjs-tr:hover { background: #f7fafc !important; }
.gridjs-tr:nth-child(even) { background: #fafbfc; }
.gridjs-tr:nth-child(even):hover { background: #f7fafc !important; }
.gridjs-td {
    padding: 0.45rem 0.7rem !important;
    color: #1a1a1a !important;
    border-bottom: 1px solid #e2e8f0 !important;
    vertical-align: middle;
}
.gridjs-search { background: #ffffff; padding: 0.5rem; border-bottom: 1px solid #e2e8f0; }
.gridjs-search-input {
    background: #f9fafb !important;
    color: #1a1a1a !important;
    border: 1px solid #cbd5e0 !important;
    padding: 0.4rem 0.7rem !important;
    border-radius: 6px;
    width: 100%;
    max-width: 380px;
}
.gridjs-pagination {
    background: #f9fafb !important;
    color: #4a5568 !important;
    border-top: 1px solid #e2e8f0 !important;
    padding: 0.5rem !important;
}
.gridjs-pagination .gridjs-summary { color: #4a5568 !important; }
.gridjs-pagination .gridjs-pages button {
    background: #ffffff !important;
    color: #1a1a1a !important;
    border: 1px solid #cbd5e0 !important;
}
.gridjs-pagination .gridjs-pages button:hover { background: #f3f4f6 !important; }
.gridjs-pagination .gridjs-pages button.gridjs-currentPage {
    background: var(--brand-primary, #5a6cff) !important;
    color: #fff !important;
    border-color: var(--brand-primary, #5a6cff) !important;
}

/* Highlight nově změněné řádky (data-changed=1) */
@keyframes dg-flash {
    0%, 100% { background: transparent; }
    30%      { background: rgba(243,156,18,0.35) !important; }
}
.gridjs-tr[data-changed="1"] { animation: dg-flash 2.4s ease-out; }

/* Stav pill colors — Excel-like (na bílém podkladu) */
.dg-pill {
    display: inline-block;
    padding: 0.1rem 0.55rem;
    font-size: 0.68rem;
    font-weight: 700;
    border-radius: 999px;
    background: #edf2f7;
    color: #4a5568;
}
.dg-pill--ok      { background: #d4f4dd; color: #1f7a3a; }
.dg-pill--bad     { background: #fde2e2; color: #b83030; }
.dg-pill--warn    { background: #fff0d3; color: #92580d; }
.dg-pill--brand   { background: #e0e4ff; color: #3f4ea8; }
.dg-vyroci-soon   { color: #d97706; font-weight: 700; }
.dg-vyroci-future { color: #6b7280; }
.dg-vyroci-past   { color: #b83030; }
.dg-elapsed       { font-size: 0.72rem; color: #6b7280; }

/* Empty state */
.dg-empty {
    padding: 3rem 1rem;
    text-align: center;
    color: var(--bo-text-3);
}
.dg-empty__icon { font-size: 2.5rem; margin-bottom: 0.5rem; }

/* ── Klikací ID + Firma → otevře historii ─────────────────────── */
.dg-id-link {
    color: var(--brand-primary, #5a6cff);
    text-decoration: none;
    font-weight: 700;
    font-family: 'Consolas', 'Monaco', monospace;
}
.dg-id-link:hover { text-decoration: underline; }
.dg-firma-link {
    color: #1a1a1a;
    text-decoration: none;
    font-weight: 600;
    border-bottom: 1px dashed transparent;
}
.dg-firma-link:hover {
    color: var(--brand-primary, #5a6cff);
    border-bottom-color: var(--brand-primary, #5a6cff);
}

/* ── Modal: historie kontaktu ───────────────────────────────────── */
.dg-history-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.55);
    z-index: 9999;
    display: flex;
    align-items: stretch;
    justify-content: flex-end;
    backdrop-filter: blur(2px);
    animation: dg-fade-in 0.18s ease-out;
}
@keyframes dg-fade-in { from { opacity: 0; } to { opacity: 1; } }
.dg-history-overlay[hidden] { display: none; }
.dg-history-panel {
    width: min(640px, 100vw);
    background: var(--bo-bg, #1a1d23);
    border-left: 1px solid var(--bo-border, rgba(0,0,0,0.1));
    display: flex; flex-direction: column;
    overflow: hidden;
    animation: dg-slide-in 0.22s ease-out;
}
@keyframes dg-slide-in { from { transform: translateX(40px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
.dg-history-panel__head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0.85rem 1rem;
    border-bottom: 1px solid var(--bo-border, rgba(0,0,0,0.08));
    background: var(--bo-surface, rgba(0,0,0,0.03));
}
.dg-history-panel__head h2 { margin: 0; font-size: 1.05rem; }
.dg-history-close {
    background: transparent; border: 0;
    color: var(--bo-text-2, #aaa);
    font-size: 1.4rem; cursor: pointer; padding: 0.2rem 0.5rem;
}
.dg-history-close:hover { color: #fff; }
.dg-history-panel__meta {
    padding: 0.7rem 1rem;
    border-bottom: 1px solid var(--bo-border, rgba(0,0,0,0.06));
    font-size: 0.82rem; color: var(--bo-text-2, #aaa);
    display: flex; gap: 0.8rem; flex-wrap: wrap;
}
.dg-history-panel__meta > div { display: flex; flex-direction: column; gap: 0.1rem; }
.dg-history-panel__meta strong { color: var(--bo-text, #fff); font-size: 0.9rem; }
.dg-history-panel__meta small { font-size: 0.7rem; color: var(--bo-text-3, #888); text-transform: uppercase; letter-spacing: 0.04em; }
.dg-history-panel__body {
    overflow-y: auto;
    flex: 1 1 auto;
    padding: 0.5rem 0;
}
.dg-history-loading,
.dg-history-empty {
    padding: 2.5rem 1rem; text-align: center;
    color: var(--bo-text-3, #888); font-size: 0.9rem;
}
.dg-history-event {
    padding: 0.7rem 1rem;
    border-bottom: 1px solid rgba(0,0,0,0.04);
    position: relative;
    padding-left: 2.4rem;
}
.dg-history-event:last-child { border-bottom: 0; }
.dg-history-event::before {
    content: '';
    position: absolute;
    left: 1.1rem; top: 1.2rem;
    width: 8px; height: 8px;
    border-radius: 50%;
    background: var(--brand-primary, #5a6cff);
    box-shadow: 0 0 0 3px rgba(90,108,255,0.18);
}
.dg-history-event::after {
    content: '';
    position: absolute;
    left: calc(1.1rem + 3px); top: calc(1.2rem + 9px);
    width: 2px; bottom: -1.7rem;
    background: rgba(90,108,255,0.18);
}
.dg-history-event:last-child::after { display: none; }
.dg-history-event__head {
    display: flex; gap: 0.5rem; align-items: baseline; flex-wrap: wrap;
    font-size: 0.85rem;
}
.dg-history-event__time { color: var(--bo-text-3, #888); font-size: 0.75rem; margin-left: auto; white-space: nowrap; }
.dg-history-event__transition {
    display: flex; gap: 0.35rem; align-items: center;
    margin: 0.3rem 0; font-size: 0.78rem;
}
.dg-history-event__note { font-size: 0.78rem; color: var(--bo-text-2, #aaa); margin-top: 0.2rem; line-height: 1.45; }
.dg-history-event__user { font-weight: 700; color: var(--bo-text, #fff); }
.dg-history-event__role {
    font-size: 0.62rem; padding: 0.1rem 0.5rem;
    background: rgba(0,0,0,0.06); border-radius: 999px;
    color: var(--bo-text-3, #aaa);
    text-transform: uppercase; letter-spacing: 0.05em;
    font-weight: 700;
}
/* Role-specific colors — at a glance kdo to byl */
.dg-history-event__role--cisticka     { background: rgba(240,160,48,0.18);  color: #f0a030; }
.dg-history-event__role--navolavacka  { background: rgba(46,204,113,0.18);  color: #2ecc71; }
.dg-history-event__role--obchodak     { background: rgba(155,89,182,0.18);  color: #c074d0; }
.dg-history-event__role--backoffice   { background: rgba(233,30,140,0.18);  color: #ff66b3; }
.dg-history-event__role--majitel      { background: rgba(61,139,253,0.18);  color: #5a9cff; }
.dg-history-event__role--superadmin   { background: rgba(231,76,60,0.18);   color: #e74c3c; }
.dg-stav-pill {
    display: inline-block;
    padding: 0.05rem 0.45rem;
    font-size: 0.66rem; font-weight: 700;
    border-radius: 999px; background: rgba(0,0,0,0.07);
    color: var(--bo-text-2, #aaa);
}
.dg-stav-pill--ok    { background: rgba(102,187,106,0.18); color: #66bb6a; }
.dg-stav-pill--bad   { background: rgba(231,76,60,0.18);   color: #e74c3c; }
.dg-stav-pill--warn  { background: rgba(243,156,18,0.18);  color: #f39c12; }
.dg-stav-pill--brand { background: rgba(90,108,255,0.18);  color: #5a6cff; }

@media (max-width: 600px) {
    .dg-history-panel { width: 100%; }
    .dg-history-event { padding-left: 2rem; }
}

/* ── Responsive ─────────────────────────────────────────────────── */
@media (max-width: 768px) {
    .dg-wrap { padding: 0.8rem 0.5rem; }
    .dg-wrap h1 { font-size: 1.15rem; }
    .dg-toolbar { padding: 0.5rem 0.6rem; gap: 0.4rem; }
    .dg-toolbar > * { font-size: 0.78rem; }
    /* Tabulka už má min-width: 1230px → na mobilu se scrolluje horizontálně */
}
@media (max-width: 480px) {
    .dg-toolbar select, .dg-toolbar button, .dg-toolbar input[type=text] {
        flex: 1 1 calc(50% - 0.3rem);
        min-width: 0;
    }
    .dg-toolbar__info { flex: 1 0 100%; }
}
</style>

<section class="dg-wrap">
    <h1>📊 Live datagrid</h1>
    <p class="lead">
        Excel-like přehled celé DB. Sortuj klikem na hlavičku, filtruj přes vyhledávací pole, auto-refresh každých 10 sekund. Změny od posledního pollu krátce zableskou žlutě.
    </p>

    <div class="dg-toolbar">
        <div class="dg-toolbar__info">
            <strong id="dg-info-rows">…</strong> řádků
            <span id="dg-info-total" style="color:var(--bo-text-3);"></span>
            <span id="dg-info-status" class="dg-toolbar__refresh-status">⏱ čeká</span>
            <span id="dg-info-fetched" style="color:var(--bo-text-3);font-size:0.72rem;margin-left:0.5rem;"></span>
        </div>

        <label>
            <input type="checkbox" id="dg-autorefresh" checked> Auto-refresh 10 s
        </label>

        <input type="text" id="dg-search"
               placeholder="🔍 Hledat: firma, telefon, e-mail, OZ, číslo smlouvy…"
               style="flex:1 1 280px;min-width:200px;padding:0.4rem 0.7rem;background:var(--bo-bg);color:var(--bo-text);border:1px solid var(--bo-border);border-radius:6px;font-size:0.85rem;">

        <select id="dg-filter-stav" title="Filtr: Workflow stav">
            <option value="">Všechny stavy</option>
            <option>NEW</option>
            <option>READY</option>
            <option>ASSIGNED</option>
            <option>CALLBACK</option>
            <option>NABIDKA</option>
            <option>SCHUZKA</option>
            <option>SANCE</option>
            <option>BO_PREDANO</option>
            <option>BO_VPRACI</option>
            <option>BO_VRACENO</option>
            <option>UZAVRENO</option>
            <option>NEZAJEM</option>
            <option>REKLAMACE</option>
        </select>

        <select id="dg-filter-region" title="Filtr: Kraj">
            <option value="">Všechny kraje</option>
        </select>

        <label title="Ukázat jen kontakty s výročím smlouvy v příštích 6 měsících">
            <input type="checkbox" id="dg-filter-vyroci"> 🎂 Jen blížící se výročí (≤ 180 dní)
        </label>

        <button type="button" id="dg-reload">🔄 Reload teď</button>
        <button type="button" id="dg-export-csv" class="primary">⬇ Export CSV</button>
    </div>

    <!-- Horní scrollbar — duplikuje native scroll pro snadnější ovládání u širokých tabulek -->
    <div id="dg-scroll-top" style="overflow-x: auto; height: 17px; margin-top: 0.5rem;
                                    border: 1px solid rgba(0,0,0,0.15); border-bottom: none;
                                    border-radius: 8px 8px 0 0; background: #f5f0fc;">
        <div id="dg-scroll-spacer" style="width: 3400px; height: 1px;"></div>
    </div>

    <!-- Tabulka v plné šířce — activity feed je teď samostatně na /admin/feed -->
    <div id="dg-scroll-container">
        <div id="dg-grid"></div>
    </div>
    <p style="margin: 0.4rem 0 0; font-size: 0.78rem; color: var(--color-text-muted); text-align: center;">
        💡 <strong>Tip:</strong> tabulkou můžeš posouvat: scrollbarem nahoře, scrollbarem dole, kolečkem myši se Shift,
        klávesami ← →, nebo drag-and-drop přímo v tabulce (klik mimo buňky a táhni).
    </p>

    <!-- Modal: historie kontaktu (otevírá se klikem na ID v tabulce) -->
    <div id="dg-history-overlay" class="dg-history-overlay" hidden>
        <div class="dg-history-panel">
            <div class="dg-history-panel__head">
                <h2 id="dg-history-title">Historie kontaktu</h2>
                <button type="button" class="dg-history-close" onclick="dgCloseHistory()" aria-label="Zavřít">✕</button>
            </div>
            <div id="dg-history-meta" class="dg-history-panel__meta"></div>
            <div id="dg-history-body" class="dg-history-panel__body">
                <div class="dg-history-loading">Načítání…</div>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    const ENDPOINT = '<?= crm_h(crm_url('/admin/datagrid/data')) ?>';
    const AUTO_REFRESH_MS = 10_000;

    let lastSnapshot = new Map();   // id → JSON string previous row (pro detekci změn)
    let autoRefreshTimer = null;
    let countdownTimer = null;       // pro odpočet sekund do dalšího pollu
    let countdownSec = 10;            // 10s default
    let allRows = [];
    let grid = null;
    let userIsTyping = false; // pauza auto-refresh když user píše do search
    let lastRenderedHash = ''; // hash dat při posledním renderu — když se nezmění, neforceRender

    // ── Helpers ─────────────────────────────────────────────────────
    function fmtCs(dateStr) {
        if (!dateStr || dateStr === '0000-00-00') return '';
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('cs-CZ');
    }
    function fmtElapsed(sec) {
        if (sec == null) return '';
        if (sec < 60) return sec + 's';
        if (sec < 3600) return Math.floor(sec / 60) + 'm';
        if (sec < 86400) return Math.floor(sec / 3600) + 'h';
        return Math.floor(sec / 86400) + 'd';
    }
    function pillStav(stav) {
        const cls = ({
            UZAVRENO: 'dg-pill--ok',
            NEZAJEM: 'dg-pill--bad', REKLAMACE: 'dg-pill--bad',
            BO_VPRACI: 'dg-pill--warn', BO_PREDANO: 'dg-pill--warn', BO_VRACENO: 'dg-pill--warn',
            NABIDKA: 'dg-pill--brand', SCHUZKA: 'dg-pill--brand', SANCE: 'dg-pill--brand',
        })[stav] || '';
        return `<span class="dg-pill ${cls}">${stav || '—'}</span>`;
    }
    function vyrociCell(r) {
        if (!r.vyrocni_smlouvy) return '<span class="dg-vyroci-future">—</span>';
        const d = r.vyroci_in_days;
        let cls = 'dg-vyroci-future';
        if (d != null) {
            if (d < 0)         cls = 'dg-vyroci-past';
            else if (d <= 180) cls = 'dg-vyroci-soon';
        }
        const dd = (d == null) ? '' : (d < 0 ? `(před ${-d} d)` : `(za ${d} d)`);
        return `<span class="${cls}">${fmtCs(r.vyrocni_smlouvy)} <small>${dd}</small></span>`;
    }
    function smlouvaCell(r) {
        if (!r.cislo_smlouvy) return '';
        return `<strong>${escapeHtml(r.cislo_smlouvy)}</strong><br><small>${fmtCs(r.datum_uzavreni)} · ${r.smlouva_trvani_roky}let</small>`;
    }
    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[ch]);
    }

    // ── Filtrování ──────────────────────────────────────────────────
    function applyFilters(rows) {
        const stav     = document.getElementById('dg-filter-stav').value;
        const region   = document.getElementById('dg-filter-region').value;
        const onlyVyr  = document.getElementById('dg-filter-vyroci').checked;
        const search   = (document.getElementById('dg-search').value || '').toLowerCase().trim();
        const sDigits  = search.replace(/\D/g, ''); // jen číslice — pro tel
        const isDigit  = sDigits !== '' && /^\d+$/.test(search.replace(/\s|\+/g, ''));

        return rows.filter(r => {
            if (stav && r.workflow_stav !== stav) return false;
            if (region && r.region !== region) return false;
            if (onlyVyr) {
                // Výročí v rozmezí 0–180 dní (ani v minulosti, ani daleko)
                if (r.vyroci_in_days == null) return false;
                if (r.vyroci_in_days < 0 || r.vyroci_in_days > 180) return false;
            }
            if (!search) return true;

            // Textová shoda v plain polích
            const haystack = (
                (r.firma || '') + ' ' +
                (r.email || '') + ' ' +
                (r.region || '') + ' ' +
                (r.oz_name || '') + ' ' +
                (r.caller_name || '') + ' ' +
                (r.cislo_smlouvy || '')
            ).toLowerCase();
            if (haystack.includes(search)) return true;

            // Telefon — porovnává jen číslice (tj. +420 / mezery / pomlčky neřeší)
            if (sDigits.length >= 3) {
                const phoneDigits = (r.telefon || '').replace(/\D/g, '');
                if (phoneDigits.includes(sDigits)) return true;
            }

            // ID match (při hledání čísla zkus i ID)
            if (isDigit && String(r.id) === search.trim()) return true;

            return false;
        });
    }

    // ── Naplnit selecty pro region (z dat) ──────────────────────────
    function rebuildRegionSelect(rows) {
        const sel = document.getElementById('dg-filter-region');
        const cur = sel.value;
        const regions = [...new Set(rows.map(r => r.region).filter(Boolean))].sort();
        sel.innerHTML = '<option value="">Všechny kraje</option>'
            + regions.map(r => `<option ${r === cur ? 'selected' : ''}>${escapeHtml(r)}</option>`).join('');
    }

    // ── Render Grid.js ──────────────────────────────────────────────
    function premiumPill(r) {
        if (!r.premium_clean) return '';
        const cleanColors = {
            'pending':       'background:#ede9fe;color:#5b21b6',
            'tradeable':     'background:#d1fae5;color:#065f46',
            'non_tradeable': 'background:#fef3c7;color:#92400e',
        };
        const callColors = {
            'success': 'background:#d1fae5;color:#065f46',
            'failed':  'background:#fee2e2;color:#991b1b',
            'pending': 'background:#e5e7eb;color:#374151',
        };
        let html = '<span class="dg-pill" style="' + (cleanColors[r.premium_clean] || '') + '">💎 ' + escapeHtml(r.premium_clean) + '</span>';
        if (r.premium_call) {
            html += ' <span class="dg-pill" style="' + (callColors[r.premium_call] || '') + ';font-size:0.65rem;">📞 ' + escapeHtml(r.premium_call) + '</span>';
        }
        if (r.premium_order) {
            html += ' <small style="color:#6b7280;">#' + r.premium_order + '</small>';
        }
        return html;
    }

    function renderGrid(filtered) {
        const data = filtered.map(r => [
            r.id, // jen číslo — formatter pro 'ID' sloupec udělá z toho odkaz
            r.firma,
            r.ico,
            r.telefon,
            r.email,
            r.adresa,
            r.region,
            r.operator,
            r.prilez,
            r.caller_name,
            r.oz_name,
            pillStav(r.workflow_stav),
            r.contact_stav,
            r.rejection_reason,
            r.nedovolano_count,
            premiumPill(r),
            smlouvaCell(r),
            vyrociCell(r),
            r.callback_at ? fmtCs(r.callback_at) : '',
            r.datum_volani ? fmtCs(r.datum_volani) : '',
            r.datum_predani ? fmtCs(r.datum_predani) : '',
            r.activation_date ? fmtCs(r.activation_date) : '',
            r.cancellation_date ? fmtCs(r.cancellation_date) : '',
            r.dnc_flag ? '🚫 DNC' : '',
            r.narozeniny_majitele ? fmtCs(r.narozeniny_majitele) : '',
            r.sale_price,
            r.poznamka || '',
            `<span class="dg-elapsed">${fmtElapsed(r.elapsed_sec)}</span>`,
            r.denik_count,
            r.created_at ? fmtCs(r.created_at) : '',
        ]);

        if (grid) {
            // 1) Skip-render optimization: pokud se data NEZMĚNILA → vůbec nepřerenderuj
            //    (typicky 90 % auto-refreshů, kdy v DB nikdo nic neudělal)
            const newHash = JSON.stringify(data.length) + ':' + (data.length > 0 ? JSON.stringify(data[0]) + JSON.stringify(data[data.length-1]) : '');
            const fullHash = data.length + '|' + data.map(r => r.join('|')).join('§').slice(0, 8000);
            if (fullHash === lastRenderedHash) {
                return; // identicky → nech tabulku být, žádný scroll reset
            }
            lastRenderedHash = fullHash;

            // 2) Save scroll z aktuálního .gridjs-wrapper (Grid.js ho na forceRender re-creates)
            const oldWrapper = document.querySelector('#dg-grid .gridjs-wrapper');
            const savedScrollLeft = oldWrapper ? oldWrapper.scrollLeft : 0;
            const savedScrollTop  = oldWrapper ? oldWrapper.scrollTop  : 0;
            const savedWindowY    = window.scrollY;

            grid.updateConfig({ data }).forceRender();

            // 3) Restore scroll: po re-renderu si znovu najdi NOVÝ wrapper a aplikuj scroll
            //    Voláme ve více okamžicích (RAF + 50ms + 200ms), Grid.js někdy renderuje async
            const restore = () => {
                const w = document.querySelector('#dg-grid .gridjs-wrapper');
                if (w) {
                    if (w.scrollLeft !== savedScrollLeft) w.scrollLeft = savedScrollLeft;
                    if (w.scrollTop  !== savedScrollTop)  w.scrollTop  = savedScrollTop;
                }
            };
            requestAnimationFrame(restore);
            setTimeout(restore, 50);
            setTimeout(restore, 200);
            if (Math.abs(window.scrollY - savedWindowY) > 5) {
                requestAnimationFrame(() => window.scrollTo({ top: savedWindowY, behavior: 'instant' }));
            }
        } else {
            grid = new gridjs.Grid({
                data,
                columns: [
                    { name: 'ID', width: '60px',
                      formatter: id => gridjs.html(`<a href="javascript:void(0)" onclick="dgOpenHistory(${id})" class="dg-id-link" title="Zobrazit historii">#${id}</a>`) },
                    { name: 'Firma', width: '170px',
                      formatter: (firma, row) => {
                          const id = row.cells[0].data;
                          return gridjs.html(`<a href="javascript:void(0)" onclick="dgOpenHistory(${id})" class="dg-firma-link" title="Zobrazit historii">${escapeHtml(String(firma || ''))}</a>`);
                      } },
                    { name: 'IČO',         width: '90px' },
                    { name: 'Telefon',     width: '115px' },
                    { name: 'Email',       width: '160px' },
                    { name: 'Adresa',      width: '180px' },
                    { name: 'Kraj',        width: '105px' },
                    { name: 'Operátor',    width: '85px' },
                    { name: 'Příležitost', width: '160px' },
                    { name: 'Navolávačka', width: '110px' },
                    { name: 'OZ',          width: '110px' },
                    { name: 'Stav OZ',     width: '110px', formatter: c => gridjs.html(String(c)) },
                    { name: 'Stav kontaktu', width: '110px' },
                    { name: 'Důvod zamítnutí', width: '130px' },
                    { name: 'Nedovoláno ×', width: '85px' },
                    { name: 'Premium',     width: '190px', formatter: c => gridjs.html(String(c || '')) },
                    { name: 'Smlouva',     width: '160px', formatter: c => gridjs.html(String(c)) },
                    { name: 'Výročí',      width: '180px', formatter: c => gridjs.html(String(c)) },
                    { name: 'Callback',    width: '100px' },
                    { name: 'Datum volání', width: '105px' },
                    { name: 'Předáno OZ',  width: '105px' },
                    { name: 'Aktivace',    width: '95px' },
                    { name: 'Storno',      width: '95px' },
                    { name: 'DNC',         width: '70px' },
                    { name: 'Narozeniny', width: '100px' },
                    { name: 'Cena (Kč)',   width: '90px' },
                    { name: 'Poznámka',    width: '180px' },
                    { name: 'Posl. změna', width: '90px',  formatter: c => gridjs.html(String(c)) },
                    { name: 'Deník (počet)', width: '85px' },
                    { name: 'Vytvořen',    width: '100px' },
                ],
                sort: true,
                search: false, // používáme vlastní search (řeší tel s +420 a obnovu po auto-refresh)
                pagination: { enabled: true, limit: 100 },
                fixedHeader: false, // sticky breadcrumb nahoře překrýval Grid.js sticky header → tabulková hlavička je teď normální (vždy vidět nad daty, sortování klikem stejně funguje)
                language: {
                    pagination: { previous: '‹', next: '›', showing: 'Zobr.', of: 'z', to: '–', results: () => 'řádků' },
                    noRecordsFound: 'Žádná data — zkontroluj filtr nebo hledání',
                    error: 'Chyba načtení',
                },
            }).render(document.getElementById('dg-grid'));
        }
    }

    // ── Detekce změněných řádků (highlight) ─────────────────────────
    function flashChangedRows(rows) {
        // Trochu hack: po renderu Grid.js přidáme data-changed=1 na <tr> odpovídající změněným ID
        const next = new Map();
        const changedIds = [];
        rows.forEach(r => {
            const key = JSON.stringify({ s: r.workflow_stav, t: r.stav_changed_at, dk: r.denik_count, sm: r.cislo_smlouvy });
            const prev = lastSnapshot.get(r.id);
            if (prev && prev !== key) changedIds.push(r.id);
            next.set(r.id, key);
        });
        lastSnapshot = next;

        if (!changedIds.length) return;
        // Najdi <tr>s a označ je
        setTimeout(() => {
            document.querySelectorAll('#dg-grid .gridjs-tr').forEach(tr => {
                const firstCell = tr.querySelector('td:first-child');
                if (!firstCell) return;
                const id = parseInt(firstCell.textContent.trim(), 10);
                if (changedIds.includes(id)) {
                    tr.setAttribute('data-changed', '0');
                    void tr.offsetWidth; // restart animation
                    tr.setAttribute('data-changed', '1');
                }
            });
        }, 50);
    }

    // ── Fetch data ──────────────────────────────────────────────────
    async function fetchData() {
        const status = document.getElementById('dg-info-status');
        status.className = 'dg-toolbar__refresh-status dg-toolbar__refresh-status--loading';
        status.textContent = '… načítá';
        try {
            const res = await fetch(ENDPOINT, { credentials: 'same-origin' });
            const json = await res.json();
            if (!json.ok) throw new Error(json.error || 'fetch error');
            allRows = json.rows;
            document.getElementById('dg-info-rows').textContent = json.returned.toLocaleString('cs-CZ');
            const infoTotal = document.getElementById('dg-info-total');
            if (json.truncated) {
                infoTotal.innerHTML = `<span style="color:#f39c12;font-weight:600;" title="V DB je víc kontaktů, než zvládne client-side rendering. Pro full search napiš nám.">⚠ z ${json.total_db.toLocaleString('cs-CZ')} v DB · zobrazeno prvních ${json.returned.toLocaleString('cs-CZ')}</span>`;
            } else {
                infoTotal.innerHTML = '<span style="color:#66bb6a;">✓ celá DB</span>';
            }
            document.getElementById('dg-info-fetched').textContent =
                'načteno ' + new Date(json.fetched_at).toLocaleTimeString('cs-CZ');
            rebuildRegionSelect(allRows);
            const filtered = applyFilters(allRows);
            renderGrid(filtered);
            flashChangedRows(filtered);
            status.className = 'dg-toolbar__refresh-status';
            status.textContent = '✓ aktuální';
            // Po úspěšném fetchi resetuj countdown (10 → 0)
            startCountdown();
        } catch (e) {
            status.className = 'dg-toolbar__refresh-status dg-toolbar__refresh-status--paused';
            status.textContent = '✗ chyba';
            console.error(e);
        }
    }

    function startCountdown() {
        if (countdownTimer) clearInterval(countdownTimer);
        countdownSec = 10;
        const status = document.getElementById('dg-info-status');
        countdownTimer = setInterval(() => {
            const cb = document.getElementById('dg-autorefresh');
            if (!cb.checked || userIsTyping) return; // pauznuto → žádný countdown
            countdownSec--;
            if (countdownSec > 0) {
                status.className = 'dg-toolbar__refresh-status';
                status.textContent = '↻ za ' + countdownSec + ' s';
            } else {
                countdownSec = 10; // reset, fetch_data se spustí přes autoRefreshTimer
            }
        }, 1000);
    }

    // ── Auto-refresh ────────────────────────────────────────────────
    function setupAutoRefresh() {
        const cb = document.getElementById('dg-autorefresh');
        function tick() {
            // Když user píše do search nebo má focus na filtru, neproveď refresh
            if (cb.checked && !userIsTyping) fetchData();
        }
        function reset() {
            if (autoRefreshTimer) clearInterval(autoRefreshTimer);
            if (countdownTimer)   clearInterval(countdownTimer);
            if (cb.checked) {
                autoRefreshTimer = setInterval(tick, AUTO_REFRESH_MS);
                startCountdown(); // zapnout odpočet sekund
            } else {
                document.getElementById('dg-info-status').className =
                    'dg-toolbar__refresh-status dg-toolbar__refresh-status--paused';
                document.getElementById('dg-info-status').textContent = '⏸ pozastaveno';
            }
            // Persistovat preferenci
            try { localStorage.setItem('dg_autorefresh', cb.checked ? '1' : '0'); } catch {}
        }
        cb.addEventListener('change', reset);
        // Načíst preferenci
        try {
            const stored = localStorage.getItem('dg_autorefresh');
            if (stored === '0') cb.checked = false;
        } catch {}
        reset();
    }

    // ── Filter listenery ────────────────────────────────────────────
    document.getElementById('dg-filter-stav').addEventListener('change', () => renderGrid(applyFilters(allRows)));
    document.getElementById('dg-filter-region').addEventListener('change', () => renderGrid(applyFilters(allRows)));
    document.getElementById('dg-filter-vyroci').addEventListener('change', () => renderGrid(applyFilters(allRows)));

    // Pre-set filter z URL (např. ?vyroci=1 pro deep-link z dashboard widgetu)
    if ((new URLSearchParams(window.location.search)).get('vyroci') === '1') {
        document.getElementById('dg-filter-vyroci').checked = true;
    }

    // Search input — debounced + nezasahuje do hodnoty při auto-refresh
    let searchDebounce = null;
    const searchInput = document.getElementById('dg-search');
    searchInput.addEventListener('input', () => {
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(() => renderGrid(applyFilters(allRows)), 200);
    });
    // Když user píše, pauzni auto-refresh aby neblokoval input
    searchInput.addEventListener('focus', () => { userIsTyping = true; });
    searchInput.addEventListener('blur',  () => { userIsTyping = false; });

    // ── Reload teď ──────────────────────────────────────────────────
    document.getElementById('dg-reload').addEventListener('click', fetchData);

    // ── Export CSV (aktuálně viditelné) — všechny sloupce ──────────────
    document.getElementById('dg-export-csv').addEventListener('click', () => {
        const filtered = applyFilters(allRows);
        const headers = [
            'ID', 'Firma', 'IČO', 'Telefon', 'Email', 'Adresa', 'Kraj', 'Operátor', 'Příležitost',
            'Navolávačka', 'OZ', 'Stav OZ', 'Stav kontaktu', 'Důvod zamítnutí', 'Nedovoláno ×',
            'Premium clean', 'Premium call', 'Premium objednávka',
            'Číslo smlouvy', 'Datum uzavření', 'Trvání smlouvy (roky)', 'Výročí',
            'Callback', 'Datum volání', 'Předáno OZ', 'Aktivace', 'Storno',
            'DNC', 'Narozeniny', 'Cena (Kč)', 'Poznámka',
            'Posl. změna stavu', 'Deník (počet záznamů)', 'Vytvořen', 'Aktualizován',
        ];
        const escape = v => '"' + String(v ?? '').replace(/"/g, '""') + '"';
        const rows = filtered.map(r => [
            r.id, r.firma, r.ico, r.telefon, r.email, r.adresa, r.region, r.operator, r.prilez,
            r.caller_name, r.oz_name, r.workflow_stav, r.contact_stav, r.rejection_reason, r.nedovolano_count,
            r.premium_clean, r.premium_call, r.premium_order,
            r.cislo_smlouvy, r.datum_uzavreni, r.smlouva_trvani_roky, r.vyrocni_smlouvy,
            r.callback_at, r.datum_volani, r.datum_predani, r.activation_date, r.cancellation_date,
            r.dnc_flag ? 'ANO' : '', r.narozeniny_majitele, r.sale_price, r.poznamka,
            r.stav_changed_at, r.denik_count, r.created_at, r.updated_at,
        ].map(escape).join(';'));
        const csv = '﻿' + headers.map(escape).join(';') + '\n' + rows.join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'datagrid_' + new Date().toISOString().slice(0, 16).replace(/[:T]/g, '-') + '.csv';
        a.click();
        URL.revokeObjectURL(a.href);
    });

    // ── Modal: historie kontaktu ────────────────────────────────────
    const HISTORY_ENDPOINT = '<?= crm_h(crm_url('/admin/datagrid/contact-history')) ?>';

    window.dgOpenHistory = async function (contactId) {
        const overlay = document.getElementById('dg-history-overlay');
        const title   = document.getElementById('dg-history-title');
        const meta    = document.getElementById('dg-history-meta');
        const body    = document.getElementById('dg-history-body');
        title.textContent = 'Historie kontaktu #' + contactId;
        meta.innerHTML = '';
        body.innerHTML = '<div class="dg-history-loading">Načítání…</div>';
        overlay.hidden = false;
        // Pauznout auto-refresh aby se data v tabulce nehýbaly
        userIsTyping = true;

        try {
            const res = await fetch(HISTORY_ENDPOINT + '?id=' + contactId, { credentials: 'same-origin' });
            const json = await res.json();
            if (!json.ok) throw new Error(json.error || 'fetch error');

            const h = json.header;
            title.textContent = (h.firma || 'Kontakt') + ' #' + h.id;
            meta.innerHTML = `
                <div><small>Telefon</small><strong>${escapeHtml(h.telefon || '—')}</strong></div>
                <div><small>Kraj</small><strong>${escapeHtml(h.region || '—')}</strong></div>
                <div><small>Aktuální stav</small><strong><span class="dg-stav-pill ${stavCls(h.current_stav)}">${escapeHtml(h.current_stav || '—')}</span></strong></div>
                ${h.cislo_smlouvy ? `<div><small>Č. smlouvy</small><strong>${escapeHtml(h.cislo_smlouvy)}</strong></div>` : ''}
                ${h.datum_uzavreni ? `<div><small>Datum uzavření</small><strong>${escapeHtml(h.datum_uzavreni)}</strong></div>` : ''}
            `;

            const events = json.history || [];
            if (!events.length) {
                body.innerHTML = '<div class="dg-history-empty">Žádné záznamy v historii. Při dalších změnách stavu se začnou zobrazovat.</div>';
                return;
            }
            body.innerHTML = events.map(ev => {
                const dt = new Date(ev.created_at);
                const dtFmt = isNaN(dt.getTime()) ? ev.created_at : dt.toLocaleString('cs-CZ');
                const oldStavPill = ev.old_status
                    ? `<span class="dg-stav-pill ${stavCls(ev.old_status)}">${escapeHtml(ev.old_status)}</span>`
                    : '<span class="dg-stav-pill">—</span>';
                const newStavPill = `<span class="dg-stav-pill ${stavCls(ev.new_status)}">${escapeHtml(ev.new_status)}</span>`;
                const roleClass = ev.user_role ? 'dg-history-event__role--' + ev.user_role : '';
                return `
                <div class="dg-history-event">
                    <div class="dg-history-event__head">
                        <span class="dg-history-event__user">${escapeHtml(ev.user_name)}</span>
                        ${ev.user_role ? `<span class="dg-history-event__role ${roleClass}">${escapeHtml(ev.user_role)}</span>` : ''}
                        <span class="dg-history-event__time">${escapeHtml(dtFmt)}</span>
                    </div>
                    <div class="dg-history-event__transition">${oldStavPill} <span style="color:var(--bo-text-3);">→</span> ${newStavPill}</div>
                    ${ev.note ? `<div class="dg-history-event__note">${escapeHtml(ev.note)}</div>` : ''}
                </div>`;
            }).join('');
        } catch (e) {
            body.innerHTML = '<div class="dg-history-empty">⚠ Chyba načtení historie. Zkuste znovu.</div>';
            console.error(e);
        }
    };

    window.dgCloseHistory = function () {
        document.getElementById('dg-history-overlay').hidden = true;
        userIsTyping = false; // re-enable auto-refresh
    };

    // ESC zavře modal
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && !document.getElementById('dg-history-overlay').hidden) {
            window.dgCloseHistory();
        }
    });
    // Klik na overlay (mimo panel) také zavře
    document.getElementById('dg-history-overlay').addEventListener('click', e => {
        if (e.target.id === 'dg-history-overlay') window.dgCloseHistory();
    });

    function stavCls(s) {
        return ({
            UZAVRENO: 'dg-stav-pill--ok',
            NEZAJEM: 'dg-stav-pill--bad', REKLAMACE: 'dg-stav-pill--bad',
            BO_VPRACI: 'dg-stav-pill--warn', BO_PREDANO: 'dg-stav-pill--warn', BO_VRACENO: 'dg-stav-pill--warn',
            NABIDKA: 'dg-stav-pill--brand', SCHUZKA: 'dg-stav-pill--brand', SANCE: 'dg-stav-pill--brand',
            CALLBACK: 'dg-stav-pill--brand',
        })[s] || '';
    }

    // ── Horizontal scroll helpers — top scrollbar sync + drag-to-scroll ─
    function setupHorizontalScroll() {
        const topBar = document.getElementById('dg-scroll-top');
        if (!topBar) return;

        // Najít aktuální .gridjs-wrapper (může se znovu vykreslit při forceRender)
        function getWrapper() { return document.querySelector('#dg-grid .gridjs-wrapper'); }

        // 1) Top scrollbar SYNC — když posuneš nahoře, posune se i wrapper a naopak
        let syncing = false;
        topBar.addEventListener('scroll', () => {
            if (syncing) return;
            const w = getWrapper();
            if (!w) return;
            syncing = true;
            w.scrollLeft = topBar.scrollLeft;
            requestAnimationFrame(() => syncing = false);
        });

        // Wrapper → top sync (s polling intervalem, protože Grid.js wrapper se vyrábí znovu)
        function bindWrapperSync() {
            const w = getWrapper();
            if (!w || w.dataset.scrollSyncBound === '1') return;
            w.dataset.scrollSyncBound = '1';
            w.addEventListener('scroll', () => {
                if (syncing) return;
                syncing = true;
                topBar.scrollLeft = w.scrollLeft;
                requestAnimationFrame(() => syncing = false);
            });
            // Sync šířky spaceru s wrappers content
            const table = w.querySelector('.gridjs-table');
            if (table) {
                const spacer = document.getElementById('dg-scroll-spacer');
                if (spacer) spacer.style.width = table.offsetWidth + 'px';
            }
        }
        // Periodicky kontroluj jestli je nový wrapper (po Grid.js forceRender)
        setInterval(bindWrapperSync, 500);

        // 2) Drag-to-scroll — myší posun s grab kurzorem.
        //    Aktivuje se jen na pozadí buňky (ne když klikneš na odkaz/tlačítko).
        let dragWrapper = null;
        let isDragging = false;
        let startX = 0, startScrollLeft = 0, dragDistance = 0;

        document.addEventListener('mousedown', (e) => {
            const w = getWrapper();
            if (!w || !w.contains(e.target)) return;
            // Nepouštět drag na klikatelné prvky
            if (e.target.closest('a, button, input, select, .gridjs-th-sort')) return;
            dragWrapper = w;
            isDragging = true;
            startX = e.pageX;
            startScrollLeft = w.scrollLeft;
            dragDistance = 0;
            w.style.cursor = 'grabbing';
            w.style.userSelect = 'none';
        });
        document.addEventListener('mousemove', (e) => {
            if (!isDragging || !dragWrapper) return;
            const dx = e.pageX - startX;
            dragDistance = Math.abs(dx);
            if (dragDistance > 3) {
                e.preventDefault();
                dragWrapper.scrollLeft = startScrollLeft - dx;
            }
        });
        document.addEventListener('mouseup', () => {
            if (dragWrapper) {
                dragWrapper.style.cursor = '';
                dragWrapper.style.userSelect = '';
            }
            isDragging = false;
            dragWrapper = null;
        });

        // 3) Shift+wheel = horizontal scroll (browsers obvykle umí, ale pro jistotu)
        document.addEventListener('wheel', (e) => {
            const w = getWrapper();
            if (!w || !w.contains(e.target)) return;
            if (e.shiftKey && Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
                e.preventDefault();
                w.scrollLeft += e.deltaY;
            }
        }, { passive: false });
    }

    // ── Boot ────────────────────────────────────────────────────────
    fetchData().then(() => {
        setupAutoRefresh();
        setupHorizontalScroll();
    });
    // Activity feed je nyní samostatná stránka /admin/feed
})();
</script>
