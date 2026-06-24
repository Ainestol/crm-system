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
/* Email — single-line s ellipsis (kdyby byl výjimečně přes 230px),
   plný text vidíš v tooltipu při najetí myší. */
.dg-email {
    display: inline-block;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
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

        <select id="dg-filter-stav" title="Filtr: Workflow stav (Stav OZ)">
            <option value="">Všechny stavy</option>
            <option value="__empty__">— prázdné (bez Stavu OZ) —</option>
            <option>NOVE</option>
            <option>ZPRACOVAVA</option>
            <option>NABIDKA</option>
            <option>SCHUZKA</option>
            <option>SANCE</option>
            <option>CALLBACK</option>
            <option>BO_PREDANO</option>
            <option>BO_VPRACI</option>
            <option>BO_VRACENO</option>
            <option>SMLOUVA</option>
            <option>UZAVRENO</option>
            <option>NEZAJEM</option>
            <option>REKLAMACE</option>
            <option>FOR_SALES</option>
        </select>

        <select id="dg-filter-contact-stav" title="Filtr: Stav kontaktu">
            <option value="">Všechny stavy kontaktu</option>
            <option value="__empty__">— prázdné —</option>
            <option>NEW</option>
            <option>READY</option>
            <option>ASSIGNED</option>
            <option>VF_SKIP</option>
            <option>NEDOVOLANO</option>
            <option>CALLED_OK</option>
            <option>CALLED_BAD</option>
            <option>CHYBNY_KONTAKT</option>
            <option>NEZAJEM</option>
            <option>FOR_SALES</option>
            <option>IZOLACE</option>
            <option>DONE</option>
        </select>

        <select id="dg-filter-region" title="Filtr: Kraj">
            <option value="">Všechny kraje</option>
        </select>

        <label title="Ukázat jen kontakty s výročím smlouvy v příštích 6 měsících">
            <input type="checkbox" id="dg-filter-vyroci"> 🎂 Jen blížící se výročí (≤ 180 dní)
        </label>

        <button type="button" id="dg-reload">🔄 Reload teď</button>
        <button type="button" id="dg-export-csv" class="primary">⬇ Export CSV</button>

        <!-- Sloupce: per-user show/hide (localStorage) -->
        <div id="dg-cols-wrap" style="position:relative;display:inline-block;">
            <button type="button" id="dg-cols-toggle" title="Zobrazit/skrýt sloupce">⚙ Sloupce</button>
            <div id="dg-cols-panel" style="display:none;position:absolute;top:100%;right:0;margin-top:4px;
                                            background:var(--bo-bg-2, #fff);border:1px solid var(--bo-border, #d1d5db);
                                            border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,0.15);
                                            padding:0.7rem 0.9rem;min-width:260px;max-height:420px;overflow-y:auto;
                                            z-index:9999;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;
                            padding-bottom:0.4rem;border-bottom:1px solid var(--bo-border, #e5e7eb);">
                    <strong style="font-size:0.8rem;">Zobrazené sloupce</strong>
                    <div style="display:flex;gap:0.3rem;">
                        <button type="button" id="dg-cols-all"  style="font-size:0.7rem;padding:0.2rem 0.5rem;"
                                title="Zobrazit všechny">✓ Vše</button>
                        <button type="button" id="dg-cols-none" style="font-size:0.7rem;padding:0.2rem 0.5rem;"
                                title="Skrýt vše kromě ID + Firma">∅ Min</button>
                    </div>
                </div>
                <div id="dg-cols-list" style="display:flex;flex-direction:column;gap:0.3rem;font-size:0.82rem;">
                    <!-- Naplní se z JS -->
                </div>
                <div style="margin-top:0.5rem;padding-top:0.4rem;border-top:1px solid var(--bo-border, #e5e7eb);
                            font-size:0.7rem;color:var(--bo-text-3, #888);">
                    💡 Nastavení se ukládá do tvého prohlížeče.
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk akce toolbar — zobrazí se až když je něco vybrané -->
    <div id="dg-bulk-bar" style="display:none;position:sticky;top:0;z-index:50;
                                  background:linear-gradient(180deg,#fef3c7,#fde68a);
                                  border:1px solid #f59e0b;border-radius:8px;
                                  padding:0.6rem 0.9rem;margin-top:0.6rem;
                                  display:flex;align-items:center;gap:0.7rem;flex-wrap:wrap;
                                  box-shadow:0 2px 8px rgba(245,158,11,0.2);">
        <strong style="color:#78350f;">
            Vybráno: <span id="dg-bulk-count">0</span> řádků
        </strong>
        <select id="dg-bulk-action" style="font-size:0.85rem;padding:0.3rem 0.5rem;">
            <option value="">— Vyber akci —</option>
            <option value="assign_caller">🎯 Přiřadit navolávačku</option>
            <option value="assign_oz">🎯 Přiřadit OZ</option>
            <option value="reset_to_pool">🔄 Vrátit do poolu</option>
        </select>
        <select id="dg-bulk-user" style="font-size:0.85rem;padding:0.3rem 0.5rem;display:none;min-width:180px;">
            <option value="">— Vyber uživatele —</option>
        </select>
        <button type="button" id="dg-bulk-execute"
                style="background:#dc2626;color:#fff;border:0;border-radius:4px;
                       padding:0.4rem 0.9rem;cursor:pointer;font-weight:700;">
            ✓ Provést
        </button>
        <button type="button" id="dg-bulk-clear"
                style="background:transparent;border:1px solid #92400e;color:#92400e;
                       border-radius:4px;padding:0.4rem 0.7rem;cursor:pointer;">
            ✗ Zrušit výběr
        </button>
        <small id="dg-bulk-hint" style="color:#78350f;flex:1;text-align:right;">
            Max 500 řádků / akce.
        </small>
    </div>

    <!-- Tlačítko VŽDY VIDITELNÉ pro vybrání všech viditelných řádků -->
    <div id="dg-select-helpers" style="margin-top:0.5rem;display:flex;gap:0.5rem;flex-wrap:wrap;">
        <button type="button" id="dg-select-all-visible"
                style="background:#3498db;color:#fff;border:0;border-radius:4px;
                       padding:0.4rem 0.9rem;cursor:pointer;font-size:0.85rem;">
            ☑ Vybrat všechny viditelné (na stránce)
        </button>
        <button type="button" id="dg-select-none"
                style="background:#6b7280;color:#fff;border:0;border-radius:4px;
                       padding:0.4rem 0.9rem;cursor:pointer;font-size:0.85rem;">
            ☐ Zrušit celý výběr
        </button>
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

    <!-- Modal: inline edit buňky -->
    <div id="dg-edit-overlay"
         style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;
                align-items:center;justify-content:center;padding:1rem;">
        <div style="background:#fff;border-radius:10px;max-width:480px;width:100%;padding:1.5rem;
                    box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <h3 id="dg-edit-title" style="margin:0 0 0.4rem;font-size:1.1rem;color:#1f2937;">Upravit buňku</h3>
            <p style="margin:0 0 1rem;color:#6b7280;font-size:0.85rem;">
                Kontakt #<span id="dg-edit-cid"></span> · sloupec <strong id="dg-edit-field-label"></strong>
            </p>
            <div id="dg-edit-current" style="background:#f9fafb;padding:0.6rem 0.8rem;border-radius:5px;font-size:0.88rem;margin-bottom:0.8rem;">
                <strong>Aktuální:</strong> <span id="dg-edit-current-val" style="color:#7e22ce;font-family:monospace;">—</span>
            </div>
            <div id="dg-edit-input-wrap" style="margin-bottom:1rem;">
                <!-- input nebo select se sem inject -->
            </div>
            <div style="background:#fef3c7;border-left:3px solid #f59e0b;padding:0.5rem 0.7rem;border-radius:4px;
                        font-size:0.78rem;color:#92400e;margin-bottom:1rem;">
                ⚠️ Tato akce se uloží do workflow_log + audit_log. Buď opatrný — manuální změny obcházejí standardní workflow.
            </div>
            <div style="display:flex;gap:0.5rem;justify-content:flex-end;">
                <button type="button" id="dg-edit-cancel"
                        style="background:#f3f4f6;color:#374151;border:none;padding:0.5rem 1rem;
                               border-radius:5px;font-weight:600;cursor:pointer;">Zrušit</button>
                <button type="button" id="dg-edit-save"
                        style="background:#7e22ce;color:#fff;border:none;padding:0.5rem 1.2rem;
                               border-radius:5px;font-weight:700;cursor:pointer;">💾 Uložit</button>
            </div>
        </div>
    </div>
</section>

<style>
    .dg-edit-cell {
        cursor: pointer;
        padding: 0.1rem 0.3rem;
        border-radius: 3px;
        transition: background 0.15s;
        display: inline-block;
    }
    .dg-edit-cell:hover {
        background: rgba(126,34,206,0.1);
        outline: 1px dashed rgba(126,34,206,0.4);
    }
    .dg-edit-cell:hover::after {
        content: " ✏";
        font-size: 0.7em;
        opacity: 0.6;
    }
    .dg-edit-btn {
        background: rgba(126,34,206,0.08);
        border: 1px solid rgba(126,34,206,0.25);
        color: #7e22ce;
        padding: 0 0.3rem;
        border-radius: 3px;
        cursor: pointer;
        font-size: 0.7rem;
        margin-left: 0.2rem;
        opacity: 0.4;
        transition: opacity 0.15s;
    }
    .dg-firma-link:hover + .dg-edit-btn,
    .dg-edit-btn:hover {
        opacity: 1;
    }
    .dg-add-note-btn {
        background: rgba(22,163,74,0.1);
        border: 1px solid rgba(22,163,74,0.3);
        color: #16a34a;
        padding: 0.15rem 0.45rem;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.72rem;
        font-weight: 600;
        margin-left: 0.3rem;
        transition: background 0.15s;
    }
    .dg-add-note-btn:hover {
        background: rgba(22,163,74,0.2);
    }
</style>

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

    // Mapa kód kraje → lidské jméno (synced s helpers/users_admin.php crm_region_label)
    const REGION_LABELS = {
        'praha': 'Hlavní město Praha',
        'stredocesky': 'Středočeský kraj',
        'jihocesky': 'Jihočeský kraj',
        'plzensky': 'Plzeňský kraj',
        'karlovarsky': 'Karlovarský kraj',
        'ustecky': 'Ústecký kraj',
        'liberecky': 'Liberecký kraj',
        'kralovehradecky': 'Královéhradecký kraj',
        'pardubicky': 'Pardubický kraj',
        'vysocina': 'Kraj Vysočina',
        'jihomoravsky': 'Jihomoravský kraj',
        'olomoucky': 'Olomoucký kraj',
        'zlinsky': 'Zlínský kraj',
        'moravskoslezsky': 'Moravskoslezský kraj',
    };
    function regionLabel(code) {
        if (!code) return '';
        return REGION_LABELS[code] || (code.charAt(0).toUpperCase() + code.slice(1));
    }

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
        const cStav    = document.getElementById('dg-filter-contact-stav')
                            ? document.getElementById('dg-filter-contact-stav').value : '';
        const region   = document.getElementById('dg-filter-region').value;
        const onlyVyr  = document.getElementById('dg-filter-vyroci').checked;
        const search   = (document.getElementById('dg-search').value || '').toLowerCase().trim();
        const sDigits  = search.replace(/\D/g, ''); // jen číslice — pro tel
        const isDigit  = sDigits !== '' && /^\d+$/.test(search.replace(/\s|\+/g, ''));

        // Helper: prázdné = '' / '—' / null
        const isEmpty = (v) => !v || v === '—' || v === '';

        return rows.filter(r => {
            if (stav) {
                if (stav === '__empty__') {
                    if (!isEmpty(r.workflow_stav)) return false;
                } else if (r.workflow_stav !== stav) {
                    return false;
                }
            }
            if (cStav) {
                if (cStav === '__empty__') {
                    if (!isEmpty(r.contact_stav)) return false;
                } else if (r.contact_stav !== cStav) {
                    return false;
                }
            }
            if (region && r.region !== region) return false;
            if (onlyVyr) {
                // Výročí v rozmezí 0–180 dní (ani v minulosti, ani daleko)
                if (r.vyroci_in_days == null) return false;
                if (r.vyroci_in_days < 0 || r.vyroci_in_days > 180) return false;
            }
            if (!search) return true;

            // Textová shoda v plain polích (vč. IČO jako text — kdyby měl mezery / vodicí nuly)
            const haystack = (
                (r.firma || '') + ' ' +
                (r.email || '') + ' ' +
                (r.ico || '') + ' ' +
                (r.region || '') + ' ' +
                regionLabel(r.region) + ' ' +  // i v lidsky čitelném názvu kraje
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

            // IČO — taky jen digits, aby fungovalo i s vodicími nulami nebo mezerami
            if (sDigits.length >= 3) {
                const icoDigits = (r.ico || '').toString().replace(/\D/g, '');
                if (icoDigits && icoDigits.includes(sDigits)) return true;
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

    // Helper: vytvoří editovatelný span (kliknutelný, otevře modal). Vrací gridjs.html.
    function editSpan(cid, field, value, label) {
        const safeVal = escapeHtml(String(value ?? ''));
        const safeLbl = escapeHtml(String(label ?? value ?? '') || '— prázdné —');
        return `<span class="dg-edit-cell" data-cid="${cid}" data-field="${field}" data-value="${safeVal}" title="Klikni pro editaci">${safeLbl}</span>`;
    }

    function renderGrid(filtered) {
        // Raw data pole — formatters dole budou stavět HTML s edit-span
        const data = filtered.map(r => [
            r.id,
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
                    { name: 'ID', width: '90px',
                      formatter: id => gridjs.html(
                          `<input type="checkbox" class="dg-row-check" data-cid="${id}" title="Vybrat" style="margin-right:0.3rem;vertical-align:middle;" onclick="event.stopPropagation()">` +
                          `<a href="javascript:void(0)" onclick="dgOpenHistory(${id})" class="dg-id-link" title="Zobrazit historii">#${id}</a>`
                      ) },
                    { name: 'Firma', width: '170px',
                      formatter: (firma, row) => {
                          const id = row.cells[0].data;
                          const v = String(firma || '');
                          return gridjs.html(
                              `<a href="javascript:void(0)" onclick="dgOpenHistory(${id})" class="dg-firma-link" title="Zobrazit historii">${escapeHtml(v)}</a>` +
                              ` <button type="button" class="dg-edit-btn" data-cid="${id}" data-field="firma" data-value="${escapeHtml(v)}" title="Upravit firma">✏</button>`
                          );
                      } },
                    { name: 'IČO',         width: '110px',
                      formatter: (v, row) => gridjs.html(editSpan(row.cells[0].data, 'ico', v, v)) },
                    { name: 'Telefon',     width: '130px',
                      formatter: (v, row) => gridjs.html(editSpan(row.cells[0].data, 'telefon', v, v)) },
                    { name: 'Email',       width: '230px',
                      formatter: (e, row) => {
                          const id = row.cells[0].data;
                          const v = String(e || '');
                          return gridjs.html(editSpan(id, 'email', v, v));
                      } },
                    { name: 'Adresa',      width: '200px',
                      formatter: (v, row) => gridjs.html(editSpan(row.cells[0].data, 'adresa', v, v)) },
                    { name: 'Kraj',        width: '160px',
                      formatter: (c, row) => gridjs.html(editSpan(row.cells[0].data, 'region', String(c || ''), regionLabel(String(c || '')))) },
                    { name: 'Operátor',    width: '105px',
                      formatter: (v, row) => gridjs.html(editSpan(row.cells[0].data, 'operator', v, v)) },
                    { name: 'Příležitost', width: '180px',
                      formatter: (v, row) => gridjs.html(editSpan(row.cells[0].data, 'prilez', v, v)) },
                    { name: 'Navolávačka', width: '140px',
                      formatter: (v, row) => gridjs.html(editSpan(row.cells[0].data, 'assigned_caller_id', v || '', v || '—')) },
                    { name: 'OZ',          width: '140px',
                      formatter: (v, row) => gridjs.html(editSpan(row.cells[0].data, 'assigned_sales_id', v || '', v || '—')) },
                    { name: 'Stav OZ',     width: '160px',
                      formatter: (c, row) => {
                          const id = row.cells[0].data;
                          const stavStr = String(c).replace(/<[^>]+>/g, '').trim(); // extrahuj plain stav z pillStav HTML
                          // Zachováme barevný pill, ale přidáme edit tlačítko ✏
                          return gridjs.html(
                              String(c) +
                              ` <button type="button" class="dg-edit-btn" data-cid="${id}" data-field="workflow_stav" data-value="${escapeHtml(stavStr === '—' ? '' : stavStr)}" title="Upravit Stav OZ">✏</button>`
                          );
                      } },
                    { name: 'Stav kontaktu', width: '160px',
                      formatter: (v, row) => gridjs.html(editSpan(row.cells[0].data, 'stav', v, v)) },
                    { name: 'Důvod zamítnutí', width: '160px',
                      formatter: (v, row) => gridjs.html(editSpan(row.cells[0].data, 'rejection_reason', v, v)) },
                    { name: 'Nedovoláno ×', width: '120px',
                      formatter: (v, row) => gridjs.html(editSpan(row.cells[0].data, 'nedovolano_count', String(v ?? 0), String(v ?? 0))) },
                    { name: 'Premium',     width: '190px', formatter: c => gridjs.html(String(c || '')) },
                    { name: 'Smlouva',     width: '230px',
                      formatter: (c, row) => {
                          const id = row.cells[0].data;
                          // c je smlouvaCell HTML; přidáme edit ✏ pro číslo smlouvy + datum uzavření + trvání let
                          return gridjs.html(
                              String(c) +
                              ` <button type="button" class="dg-edit-btn" data-cid="${id}" data-field="workflow_cislo_smlouvy" data-value="" title="Upravit číslo smlouvy">📄</button>` +
                              ` <button type="button" class="dg-edit-btn" data-cid="${id}" data-field="workflow_datum_uzavreni" data-value="" title="Upravit datum uzavření">📅</button>` +
                              ` <button type="button" class="dg-edit-btn" data-cid="${id}" data-field="workflow_smlouva_trvani_roky" data-value="" title="Upravit trvání smlouvy (let)">⏱</button>`
                          );
                      } },
                    { name: 'Výročí',      width: '220px',
                      formatter: (c, row) => {
                          const id = row.cells[0].data;
                          return gridjs.html(
                              String(c) +
                              ` <button type="button" class="dg-edit-btn" data-cid="${id}" data-field="vyrocni_smlouvy" data-value="" title="Upravit výročí">✏</button>`
                          );
                      } },
                    { name: 'Callback',    width: '140px',
                      formatter: (v, row) => gridjs.html(editSpan(row.cells[0].data, 'callback_at', v, v)) },
                    { name: 'Datum volání', width: '140px',
                      formatter: (v, row) => gridjs.html(editSpan(row.cells[0].data, 'datum_volani', v, v)) },
                    { name: 'Předáno OZ',  width: '140px',
                      formatter: (v, row) => gridjs.html(editSpan(row.cells[0].data, 'datum_predani', v, v)) },
                    { name: 'Aktivace',    width: '130px',
                      formatter: (v, row) => gridjs.html(editSpan(row.cells[0].data, 'activation_date', v, v)) },
                    { name: 'Storno',      width: '130px',
                      formatter: (v, row) => gridjs.html(editSpan(row.cells[0].data, 'cancellation_date', v, v)) },
                    { name: 'DNC',         width: '90px',
                      formatter: (v, row) => gridjs.html(editSpan(row.cells[0].data, 'dnc_flag', v ? '1' : '0', v ? '🚫 ANO' : 'ne')) },
                    { name: 'Narozeniny', width: '140px',
                      formatter: (v, row) => gridjs.html(editSpan(row.cells[0].data, 'narozeniny_majitele', v, v)) },
                    { name: 'Cena (Kč)',   width: '120px',
                      formatter: (v, row) => gridjs.html(editSpan(row.cells[0].data, 'sale_price', v, v)) },
                    { name: 'Poznámka', width: '280px',
                      formatter: (v, row) => {
                          const id = row.cells[0].data;
                          // Najdi raw row data podle id
                          const rRaw = allRows.find(r => r.id === id);
                          // Priority: latest_note (z contact_notes) > legacy poznamka (z contacts)
                          const latest = rRaw && rRaw.latest_note ? String(rRaw.latest_note) : '';
                          const legacy = String(v || '');
                          const text = latest || legacy;
                          const count = rRaw ? (rRaw.notes_count || 0) : 0;
                          const trimmed = text.length > 60 ? text.substring(0, 60) + '…' : text;
                          const isAdmin = latest && latest.startsWith('[ADMIN');
                          const styleClass = isAdmin ? 'color:#7e22ce;font-weight:600;' : 'color:#6b7280;';
                          return gridjs.html(
                              `<span style="font-size:0.78rem;${styleClass}" title="${escapeHtml(text)}">${escapeHtml(trimmed)}</span> ` +
                              (count > 1 ? `<small style="color:#9ca3af;">(${count}×)</small> ` : '') +
                              `<button type="button" class="dg-add-note-btn" data-cid="${id}" title="Přidat novou poznámku">📝 Přidat</button>`
                          );
                      } },
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
            });
            // Aplikuj hidden state PŘED prvním renderem (jen nastavit flagy, žádný forceRender)
            applyHiddenToGrid(true);
            grid.render(document.getElementById('dg-grid'));
            // Naplň panel sloupců po prvním renderu
            renderColsPanel();
        }
    }

    // ════════════════════════════════════════════════════════════════
    // SHOW/HIDE SLOUPCŮ — per-user (localStorage)
    // ════════════════════════════════════════════════════════════════
    const COLS_STORAGE_KEY = 'dg_hidden_cols_v1';
    const ALWAYS_VISIBLE = new Set(['ID', 'Firma']); // klíčové sloupce nelze skrýt

    /** Vrátí Set jmen aktuálně skrytých sloupců (z localStorage). */
    function getHiddenCols() {
        try {
            const raw = localStorage.getItem(COLS_STORAGE_KEY);
            if (!raw) return new Set();
            const arr = JSON.parse(raw);
            return new Set(Array.isArray(arr) ? arr : []);
        } catch (_) { return new Set(); }
    }
    function setHiddenCols(set) {
        try {
            localStorage.setItem(COLS_STORAGE_KEY, JSON.stringify([...set]));
        } catch (_) {}
    }

    /** Aplikuje hidden flag na grid.config.columns (in-place) a (volitelně) forceRender. */
    function applyHiddenToGrid(skipRender) {
        if (!grid) return;
        const hidden = getHiddenCols();
        // grid.config.columns je gridjs ProcessedColumn, ale má .name
        const cols = grid.config.columns;
        cols.forEach(c => {
            const nm = (typeof c.name === 'string') ? c.name : '';
            if (ALWAYS_VISIBLE.has(nm)) { c.hidden = false; return; }
            c.hidden = hidden.has(nm);
        });
        // Při PRVNÍM volání (před .render()) přeskočit forceRender — ten by selhal.
        if (!skipRender && typeof grid.forceRender === 'function') {
            try { grid.forceRender(); } catch (_) {}
        }
    }

    /** Naplní dropdown panel checkboxy. */
    function renderColsPanel() {
        const list = document.getElementById('dg-cols-list');
        if (!list || !grid) return;
        const hidden = getHiddenCols();
        list.innerHTML = '';
        grid.config.columns.forEach(c => {
            const nm = (typeof c.name === 'string') ? c.name : '';
            if (!nm) return;
            const isFixed = ALWAYS_VISIBLE.has(nm);
            const row = document.createElement('label');
            row.style.cssText = 'display:flex;align-items:center;gap:0.5rem;cursor:' + (isFixed ? 'not-allowed' : 'pointer') +
                                ';padding:0.18rem 0.3rem;border-radius:4px;' + (isFixed ? 'opacity:0.55;' : '');
            row.onmouseover = () => { if (!isFixed) row.style.background = 'rgba(99,102,241,0.08)'; };
            row.onmouseout  = () => { row.style.background = ''; };
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.checked = !hidden.has(nm);
            cb.disabled = isFixed;
            cb.dataset.col = nm;
            cb.addEventListener('change', () => {
                const h = getHiddenCols();
                if (cb.checked) h.delete(nm); else h.add(nm);
                setHiddenCols(h);
                applyHiddenToGrid();
            });
            const txt = document.createElement('span');
            txt.textContent = nm + (isFixed ? '  🔒' : '');
            row.appendChild(cb);
            row.appendChild(txt);
            list.appendChild(row);
        });
    }

    // Toggle panelu + outside-click close
    document.addEventListener('click', function(e) {
        const wrap = document.getElementById('dg-cols-wrap');
        const panel = document.getElementById('dg-cols-panel');
        if (!wrap || !panel) return;
        if (e.target && e.target.id === 'dg-cols-toggle') {
            panel.style.display = (panel.style.display === 'none' || !panel.style.display) ? 'block' : 'none';
            if (panel.style.display === 'block') renderColsPanel();
            return;
        }
        if (!wrap.contains(e.target)) {
            panel.style.display = 'none';
        }
    });
    // Tlačítko "Vše" — zobrazit všechny
    document.addEventListener('click', function(e) {
        if (e.target && e.target.id === 'dg-cols-all') {
            setHiddenCols(new Set());
            applyHiddenToGrid();
            renderColsPanel();
        }
        if (e.target && e.target.id === 'dg-cols-none') {
            // Skrýt vše kromě ALWAYS_VISIBLE
            const h = new Set();
            grid.config.columns.forEach(c => {
                const nm = (typeof c.name === 'string') ? c.name : '';
                if (nm && !ALWAYS_VISIBLE.has(nm)) h.add(nm);
            });
            setHiddenCols(h);
            applyHiddenToGrid();
            renderColsPanel();
        }
    });

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
    const dgFilterCS = document.getElementById('dg-filter-contact-stav');
    if (dgFilterCS) dgFilterCS.addEventListener('change', () => renderGrid(applyFilters(allRows)));
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
                const roleClass = ev.user_role ? 'dg-history-event__role--' + ev.user_role : '';
                const head = `
                    <div class="dg-history-event__head">
                        <span class="dg-history-event__user">${escapeHtml(ev.user_name)}</span>
                        ${ev.user_role ? `<span class="dg-history-event__role ${roleClass}">${escapeHtml(ev.user_role)}</span>` : ''}
                        <span class="dg-history-event__time">${escapeHtml(dtFmt)}</span>
                    </div>`;

                // Akce z deníku / poznámky — bez přechodu stavu, jen štítek + text
                if (ev.kind === 'action' || ev.kind === 'note') {
                    const label = ev.kind === 'note' ? '📝 Poznámka' : '✎ Pracovní úkon';
                    return `
                    <div class="dg-history-event">
                        ${head}
                        <div class="dg-history-event__transition"><span class="dg-stav-pill dg-stav-pill--brand">${label}</span></div>
                        ${ev.note ? `<div class="dg-history-event__note">${escapeHtml(ev.note)}</div>` : ''}
                    </div>`;
                }

                // Změna stavu (workflow_log)
                const oldStavPill = ev.old_status
                    ? `<span class="dg-stav-pill ${stavCls(ev.old_status)}">${escapeHtml(ev.old_status)}</span>`
                    : '<span class="dg-stav-pill">—</span>';
                const newStavPill = `<span class="dg-stav-pill ${stavCls(ev.new_status)}">${escapeHtml(ev.new_status)}</span>`;
                return `
                <div class="dg-history-event">
                    ${head}
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

    // ── Inline edit funkcionalita ────────────────────────────────────
    const EDIT_OPTIONS_ENDPOINT = '<?= crm_h(crm_url('/admin/datagrid/edit-options')) ?>';
    const EDIT_UPDATE_ENDPOINT  = '<?= crm_h(crm_url('/admin/datagrid/update')) ?>';
    const BULK_ENDPOINT         = '<?= crm_h(crm_url('/admin/datagrid/bulk')) ?>';
    const EDIT_CSRF             = '<?= crm_h($csrf) ?>';
    const EDIT_CSRF_KEY         = '<?= crm_h(crm_csrf_field_name()) ?>';

    let editOptions = null; // cache pro options (stav/oz/caller/region/operator)
    let editContext = null; // { cid, field, currentValue }

    // Pre-load options jednou na startu (cache)
    function loadEditOptions() {
        return fetch(EDIT_OPTIONS_ENDPOINT)
            .then(r => r.json())
            .then(d => { if (d.ok) editOptions = d; return d; })
            .catch(() => null);
    }

    // Field → label mapping
    const FIELD_LABELS = {
        firma: 'Firma',
        ico: 'IČO',
        telefon: 'Telefon',
        email: 'Email',
        adresa: 'Adresa',
        region: 'Kraj',
        operator: 'Operátor',
        prilez: 'Příležitost',
        assigned_caller_id: 'Navolávačka',
        assigned_sales_id: 'OZ (obchodák)',
        stav: 'Stav kontaktu',
        workflow_stav: 'Stav OZ (workflow)',
        poznamka: 'Poznámka',
        add_note: '📝 Přidat poznámku',
        // Datumy / čísla
        callback_at: 'Callback (datum/čas)',
        datum_volani: 'Datum volání',
        datum_predani: 'Předáno OZ (datum)',
        activation_date: 'Aktivace služby',
        cancellation_date: 'Storno smlouvy',
        narozeniny_majitele: 'Narozeniny majitele',
        vyrocni_smlouvy: 'Výročí smlouvy',
        sale_price: 'Cena smlouvy (Kč)',
        dnc_flag: 'DNC flag (1=zákaz volat)',
        nedovolano_count: 'Počet nedovolání',
        rejection_reason: 'Důvod zamítnutí',
        // Workflow fieldy (oz_contact_workflow)
        workflow_cislo_smlouvy: 'Číslo smlouvy',
        workflow_datum_uzavreni: 'Datum uzavření smlouvy',
        workflow_smlouva_trvani_roky: 'Trvání smlouvy (let)',
        workflow_schuzka_at: 'Schůzka (datum/čas)',
        workflow_bmsl: 'BMSL (měsíční hodnota)',
    };

    function openEditModal(cid, field, currentValue) {
        editContext = { cid, field, currentValue };
        document.getElementById('dg-edit-cid').textContent = cid;
        document.getElementById('dg-edit-field-label').textContent = FIELD_LABELS[field] || field;
        document.getElementById('dg-edit-current-val').textContent = currentValue || '— prázdné —';

        const wrap = document.getElementById('dg-edit-input-wrap');
        wrap.innerHTML = '';

        // Speciální handling: add_note — empty textarea, neukazuj "aktuální"
        if (field === 'add_note') {
            document.getElementById('dg-edit-current').style.display = 'none';
            const ta = document.createElement('textarea');
            ta.id = 'dg-edit-input';
            ta.placeholder = 'Napiš poznámku (uvidí ji navolávačka, OZ i BO v timeline kontaktu)…';
            ta.style.cssText = 'width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:5px;font-size:0.95rem;min-height:120px;font-family:inherit;';
            wrap.appendChild(ta);
        }
        // Workflow stav — speciální dropdown s workflow stavy
        else if (field === 'workflow_stav') {
            document.getElementById('dg-edit-current').style.display = '';
            const sel = document.createElement('select');
            sel.id = 'dg-edit-input';
            sel.style.cssText = 'width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:5px;font-size:0.95rem;';
            const wfStavs = {
                '': '— prázdný (žádný workflow řádek) —',
                'NOVE': 'NOVE (právě přijaté OZ)',
                'ZPRACOVAVA': 'ZPRACOVAVA (OZ aktivně pracuje)',
                'CALLBACK': 'CALLBACK (domluvený zpětný hovor)',
                'SCHUZKA': 'SCHUZKA (naplánovaná schůzka)',
                'NABIDKA': 'NABIDKA (odeslaná nabídka)',
                'SANCE': 'SANCE (aktivní obchod, BMSL známé)',
                'BO_PREDANO': 'BO_PREDANO (předáno backoffice)',
                'BO_VPRACI': 'BO_VPRACI (BO řeší smlouvu)',
                'BO_VRACENO': 'BO_VRACENO (BO vrátil OZ)',
                'SMLOUVA': 'SMLOUVA (smlouva vystavena)',
                'UZAVRENO': 'UZAVRENO (smlouva podepsána)',
                'REKLAMACE': 'REKLAMACE (chybný lead, vráceno)',
                'FOR_SALES': 'FOR_SALES (legacy CHCE)',
            };
            Object.entries(wfStavs).forEach(([key, lbl]) => {
                if (key === '') return; // empty option je v defaultu
                const opt = document.createElement('option');
                opt.value = key;
                opt.textContent = lbl;
                if (key === currentValue) opt.selected = true;
                sel.appendChild(opt);
            });
            wrap.appendChild(sel);

            // Hint: pokud kontakt nemá assigned_sales_id, edit selže
            const hint = document.createElement('div');
            hint.style.cssText = 'font-size:0.78rem;color:#92400e;margin-top:0.5rem;';
            hint.innerHTML = '⚠ Pozor: kontakt musí mít přiřazeného OZ (sloupec „OZ"). Bez OZ workflow stav neuložíš.';
            wrap.appendChild(hint);
        }
        // Vyber input typ podle field
        else if (field === 'stav' && editOptions && editOptions.stav) {
            const sel = document.createElement('select');
            sel.id = 'dg-edit-input';
            sel.style.cssText = 'width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:5px;font-size:0.95rem;';
            Object.entries(editOptions.stav).forEach(([key, lbl]) => {
                const opt = document.createElement('option');
                opt.value = key;
                opt.textContent = lbl;
                if (key === currentValue) opt.selected = true;
                sel.appendChild(opt);
            });
            wrap.appendChild(sel);
        }
        else if (field === 'assigned_sales_id' && editOptions && editOptions.oz) {
            const sel = document.createElement('select');
            sel.id = 'dg-edit-input';
            sel.style.cssText = 'width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:5px;font-size:0.95rem;';
            sel.innerHTML = '<option value="">— bez OZ —</option>';
            editOptions.oz.forEach(oz => {
                const opt = document.createElement('option');
                opt.value = oz.id;
                opt.textContent = oz.jmeno + ' (' + oz.email + ')';
                sel.appendChild(opt);
            });
            wrap.appendChild(sel);
        }
        else if (field === 'assigned_caller_id' && editOptions && editOptions.caller) {
            const sel = document.createElement('select');
            sel.id = 'dg-edit-input';
            sel.style.cssText = 'width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:5px;font-size:0.95rem;';
            sel.innerHTML = '<option value="">— bez navolávačky —</option>';
            editOptions.caller.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.jmeno + ' (' + c.email + ')';
                sel.appendChild(opt);
            });
            wrap.appendChild(sel);
        }
        else if (field === 'region' && editOptions && editOptions.region) {
            const sel = document.createElement('select');
            sel.id = 'dg-edit-input';
            sel.style.cssText = 'width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:5px;font-size:0.95rem;';
            sel.innerHTML = '<option value="">— prázdný —</option>';
            Object.entries(editOptions.region).forEach(([code, lbl]) => {
                const opt = document.createElement('option');
                opt.value = code;
                opt.textContent = lbl;
                if (code === currentValue) opt.selected = true;
                sel.appendChild(opt);
            });
            wrap.appendChild(sel);
        }
        else if (field === 'operator' && editOptions && editOptions.operator) {
            const sel = document.createElement('select');
            sel.id = 'dg-edit-input';
            sel.style.cssText = 'width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:5px;font-size:0.95rem;';
            Object.entries(editOptions.operator).forEach(([key, lbl]) => {
                const opt = document.createElement('option');
                opt.value = key;
                opt.textContent = lbl;
                if (key === currentValue) opt.selected = true;
                sel.appendChild(opt);
            });
            wrap.appendChild(sel);
        }
        // ── Datum / datum-čas fieldy ──
        else if (['callback_at', 'datum_volani', 'datum_predani',
                  'workflow_schuzka_at'].includes(field)) {
            const el = document.createElement('input');
            el.id = 'dg-edit-input';
            el.type = 'datetime-local';
            // Konvertovat „YYYY-MM-DD HH:MM:SS" na „YYYY-MM-DDTHH:MM" pro HTML input
            if (currentValue && currentValue !== '0000-00-00 00:00:00') {
                const m = currentValue.match(/^(\d{4}-\d{2}-\d{2})[\sT](\d{2}:\d{2})/);
                el.value = m ? `${m[1]}T${m[2]}` : '';
            }
            el.style.cssText = 'width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:5px;font-size:0.95rem;';
            wrap.appendChild(el);
        }
        else if (['activation_date', 'cancellation_date', 'narozeniny_majitele',
                  'vyrocni_smlouvy', 'workflow_datum_uzavreni'].includes(field)) {
            const el = document.createElement('input');
            el.id = 'dg-edit-input';
            el.type = 'date';
            if (currentValue && currentValue !== '0000-00-00') {
                const m = currentValue.match(/^(\d{4}-\d{2}-\d{2})/);
                el.value = m ? m[1] : '';
            }
            el.style.cssText = 'width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:5px;font-size:0.95rem;';
            wrap.appendChild(el);
        }
        // ── Číselné fieldy ──
        else if (['sale_price', 'nedovolano_count', 'workflow_bmsl'].includes(field)) {
            const el = document.createElement('input');
            el.id = 'dg-edit-input';
            el.type = 'number';
            el.step = field === 'sale_price' || field === 'workflow_bmsl' ? '0.01' : '1';
            el.min = '0';
            el.value = currentValue || '';
            el.placeholder = field === 'sale_price' ? 'např. 2500.00' : '0';
            el.style.cssText = 'width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:5px;font-size:0.95rem;';
            wrap.appendChild(el);
        }
        // ── Trvání smlouvy (let) — speciální dropdown 1-10 ──
        else if (field === 'workflow_smlouva_trvani_roky') {
            const sel = document.createElement('select');
            sel.id = 'dg-edit-input';
            sel.style.cssText = 'width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:5px;font-size:0.95rem;';
            // Najít aktuální hodnotu z grid dat
            const rRaw = allRows.find(r => r.id === editContext.cid);
            const curTrvani = rRaw ? (rRaw.smlouva_trvani_roky || 3) : 3;
            for (let y = 1; y <= 10; y++) {
                const opt = document.createElement('option');
                opt.value = String(y);
                opt.textContent = y === 1 ? '1 rok' : (y < 5 ? `${y} roky` : `${y} let`);
                if (y === curTrvani) opt.selected = true;
                sel.appendChild(opt);
            }
            wrap.appendChild(sel);

            const hint = document.createElement('div');
            hint.style.cssText = 'font-size:0.78rem;color:#0369a1;margin-top:0.5rem;';
            hint.innerHTML = '💡 Po uložení se automaticky přepočítá <strong>Výročí smlouvy</strong> (= datum uzavření + trvání).';
            wrap.appendChild(hint);
        }
        // ── DNC flag toggle ──
        else if (field === 'dnc_flag') {
            const sel = document.createElement('select');
            sel.id = 'dg-edit-input';
            sel.style.cssText = 'width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:5px;font-size:0.95rem;';
            sel.innerHTML = '<option value="0">0 — povoleno volat</option><option value="1">1 — 🚫 DNC (zákaz volat, GDPR)</option>';
            sel.value = currentValue === '1' ? '1' : '0';
            wrap.appendChild(sel);
        }
        else {
            // Volný text input pro firma/telefon/email/ico/adresa/poznamka/prilez/rejection_reason/workflow_cislo_smlouvy
            const isLong = ['poznamka', 'adresa'].includes(field);
            const el = document.createElement(isLong ? 'textarea' : 'input');
            el.id = 'dg-edit-input';
            if (!isLong) el.type = 'text';
            el.value = currentValue || '';
            el.style.cssText = 'width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:5px;font-size:0.95rem;font-family:inherit;'
                + (isLong ? 'min-height:80px;' : '');
            wrap.appendChild(el);
        }

        document.getElementById('dg-edit-overlay').style.display = 'flex';
        setTimeout(() => document.getElementById('dg-edit-input')?.focus(), 50);
    }

    function closeEditModal() {
        document.getElementById('dg-edit-overlay').style.display = 'none';
        editContext = null;
        // KRITICKÉ: reset save button — jinak při příštím otevření zůstane disabled+"Ukládám..."
        const btnSave = document.getElementById('dg-edit-save');
        if (btnSave) {
            btnSave.disabled = false;
            btnSave.textContent = '💾 Uložit';
        }
        // Reset zobrazení "current value" sekce (add_note ji schovává)
        const curBox = document.getElementById('dg-edit-current');
        if (curBox) curBox.style.display = '';
    }

    function saveEdit() {
        if (!editContext) return;
        const input = document.getElementById('dg-edit-input');
        if (!input) return;
        const newValue = input.value;
        const btnSave = document.getElementById('dg-edit-save');
        btnSave.disabled = true;
        btnSave.textContent = '⏳ Ukládám…';

        const fd = new FormData();
        fd.append('contact_id', editContext.cid);
        fd.append('field', editContext.field);
        fd.append('value', newValue);
        fd.append(EDIT_CSRF_KEY, EDIT_CSRF);

        fetch(EDIT_UPDATE_ENDPOINT, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.ok) {
                    closeEditModal();
                    // Refresh dat z DB — nejjednodušší
                    fetchData();
                } else {
                    alert('⚠ Chyba: ' + (d.error || 'Neznámá'));
                    btnSave.disabled = false;
                    btnSave.textContent = '💾 Uložit';
                }
            })
            .catch(e => {
                alert('⚠ Síťová chyba: ' + e);
                btnSave.disabled = false;
                btnSave.textContent = '💾 Uložit';
            });
    }

    // Event delegation pro click na .dg-edit-cell, .dg-edit-btn, .dg-add-note-btn
    document.addEventListener('click', function(e) {
        // Speciální: přidat poznámku (nemá data-field)
        const addNoteBtn = e.target.closest('.dg-add-note-btn');
        if (addNoteBtn) {
            e.preventDefault();
            e.stopPropagation();
            const cid = parseInt(addNoteBtn.dataset.cid, 10);
            if (!editOptions) {
                loadEditOptions().then(() => openEditModal(cid, 'add_note', ''));
            } else {
                openEditModal(cid, 'add_note', '');
            }
            return;
        }

        const cell = e.target.closest('.dg-edit-cell, .dg-edit-btn');
        if (!cell) return;
        e.preventDefault();
        e.stopPropagation();
        const cid = parseInt(cell.dataset.cid, 10);
        const field = cell.dataset.field;
        const currentValue = cell.dataset.value || '';
        if (!editOptions) {
            loadEditOptions().then(() => openEditModal(cid, field, currentValue));
        } else {
            openEditModal(cid, field, currentValue);
        }
    });

    document.getElementById('dg-edit-cancel').addEventListener('click', closeEditModal);
    document.getElementById('dg-edit-save').addEventListener('click', saveEdit);
    document.getElementById('dg-edit-overlay').addEventListener('click', function(e) {
        if (e.target.id === 'dg-edit-overlay') closeEditModal();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && editContext) closeEditModal();
    });

    // ── Boot ────────────────────────────────────────────────────────
    fetchData().then(() => {
        setupAutoRefresh();
        setupHorizontalScroll();
        loadEditOptions(); // pre-cache options
        setupBulkActions(); // bulk akce (checkboxy + toolbar)
    });
    // Activity feed je nyní samostatná stránka /admin/feed

    // ════════════════════════════════════════════════════════════════
    // BULK AKCE
    // ════════════════════════════════════════════════════════════════
    const selectedIds = new Set();

    function setupBulkActions() {
        const bar       = document.getElementById('dg-bulk-bar');
        const countEl   = document.getElementById('dg-bulk-count');
        const actionSel = document.getElementById('dg-bulk-action');
        const userSel   = document.getElementById('dg-bulk-user');
        const btnRun    = document.getElementById('dg-bulk-execute');
        const btnClear  = document.getElementById('dg-bulk-clear');

        // Event delegation pro checkboxy (Grid.js re-renders měnily DOM)
        document.addEventListener('change', function(e) {
            if (e.target.classList && e.target.classList.contains('dg-row-check')) {
                const cid = parseInt(e.target.dataset.cid, 10);
                if (!cid) return;
                if (e.target.checked) selectedIds.add(cid);
                else                  selectedIds.delete(cid);
                updateBulkBar();
            }
        });

        // Tlačítka "Vybrat všechny viditelné na stránce" / "Zrušit"
        // POZOR: Grid.js drží všechny řádky v DOM (i z jiných stránek),
        // jen je skrývá CSS. Proto bereme JEN ty s offsetParent != null
        // (= reálně viditelné v aktuální stránce).
        const btnSelAll  = document.getElementById('dg-select-all-visible');
        const btnSelNone = document.getElementById('dg-select-none');
        if (btnSelAll) btnSelAll.addEventListener('click', function() {
            let cnt = 0;
            document.querySelectorAll('#dg-grid .dg-row-check').forEach(cb => {
                // offsetParent === null znamená, že element je skrytý
                // (display:none v rodičovi nebo přímo). Skip ty.
                if (cb.offsetParent === null) return;
                cb.checked = true;
                const cid = parseInt(cb.dataset.cid, 10);
                if (cid) { selectedIds.add(cid); cnt++; }
            });
            updateBulkBar();
        });
        if (btnSelNone) btnSelNone.addEventListener('click', clearSelection);

        // Action select — show/hide user picker podle akce
        actionSel.addEventListener('change', function() {
            const a = actionSel.value;
            if (a === 'assign_caller' || a === 'assign_oz') {
                userSel.style.display = '';
                // Naplň options podle role.
                // editOptions.caller/oz jsou POLE OBJEKTŮ [{id, jmeno, email}, ...]
                userSel.innerHTML = '<option value="">— Vyber uživatele —</option>';
                if (editOptions) {
                    const opts = a === 'assign_caller' ? editOptions.caller : editOptions.oz;
                    (Array.isArray(opts) ? opts : []).forEach(u => {
                        if (!u || !u.id) return;
                        const o = document.createElement('option');
                        o.value = String(u.id);
                        o.textContent = u.jmeno + (u.email ? ' (' + u.email + ')' : '');
                        userSel.appendChild(o);
                    });
                }
            } else {
                userSel.style.display = 'none';
                userSel.value = '';
            }
        });

        // Execute (async pro await crmConfirm)
        btnRun.addEventListener('click', async function() {
            const action = actionSel.value;
            if (!action) { crmAlert('Nejprve vyber akci.', { type: 'warn' }); return; }
            if (selectedIds.size === 0) { crmAlert('Žádné řádky nejsou vybrané.', { type: 'warn' }); return; }
            if (selectedIds.size > 500) {
                crmAlert('Max 500 řádků naráz (vybráno ' + selectedIds.size + '). Zúž výběr.', { type: 'warn' });
                return;
            }
            let userId = 0;
            if (action === 'assign_caller' || action === 'assign_oz') {
                userId = parseInt(userSel.value, 10);
                if (!userId) { crmAlert('Vyber uživatele z dropdownu.', { type: 'warn' }); return; }
            }

            const labels = {
                'assign_caller': '🎯 přiřadit navolávačku',
                'assign_oz':     '🎯 přiřadit OZ',
                'reset_to_pool': '🔄 vrátit do poolu',
            };
            const big = selectedIds.size > 50;
            const confirmMsg = `Opravdu chceš ${labels[action]} pro ${selectedIds.size} kontaktů?` +
                               (big ? '\n\n⚠ Větší dávka — operace je nevratná.' : '');
            const ok = await crmConfirm(confirmMsg, {
                type: big ? 'danger' : 'confirm',
                title: big ? 'Velká dávka — pozor' : 'Potvrzení akce',
                okText: '✓ Provést',
                cancelText: 'Zrušit',
            });
            if (!ok) return;

            btnRun.disabled = true;
            btnRun.textContent = '⏳ Provádím…';

            const fd = new FormData();
            fd.append(EDIT_CSRF_KEY, EDIT_CSRF);
            fd.append('action', action);
            fd.append('value', String(userId));
            selectedIds.forEach(id => fd.append('ids[]', String(id)));

            fetch(BULK_ENDPOINT, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    btnRun.disabled = false;
                    btnRun.textContent = '✓ Provést';
                    if (d.ok) {
                        crmToast(d.message, 'success');
                        clearSelection();
                        fetchData();
                    } else {
                        crmAlert(d.error || 'Neznámá chyba.', { type: 'danger', title: 'Chyba akce' });
                    }
                })
                .catch(e => {
                    btnRun.disabled = false;
                    btnRun.textContent = '✓ Provést';
                    crmAlert('Síťová chyba: ' + e, { type: 'danger', title: 'Spojení selhalo' });
                });
        });

        btnClear.addEventListener('click', clearSelection);
    }

    function updateBulkBar() {
        const bar = document.getElementById('dg-bulk-bar');
        const cnt = document.getElementById('dg-bulk-count');
        if (selectedIds.size === 0) {
            bar.style.display = 'none';
        } else {
            bar.style.display = 'flex';
            cnt.textContent = selectedIds.size;
        }
    }

    function clearSelection() {
        selectedIds.clear();
        document.querySelectorAll('.dg-row-check').forEach(cb => cb.checked = false);
        const actionSel = document.getElementById('dg-bulk-action');
        const userSel   = document.getElementById('dg-bulk-user');
        if (actionSel) actionSel.value = '';
        if (userSel) { userSel.style.display = 'none'; userSel.value = ''; }
        updateBulkBar();
    }
})();
</script>
