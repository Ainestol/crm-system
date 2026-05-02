<?php
/**
 * @var string $title
 * @var string $csrf
 * @var ?string $flash
 */
?>
<style>
.feed-wrap { padding: 1.2rem 1rem; max-width: 900px; margin: 0 auto; }
.feed-wrap h1 { margin: 0 0 0.4rem; font-size: 1.35rem; }
.feed-wrap .lead { color: var(--bo-text-3, #888); font-size: 0.85rem; margin-bottom: 1rem; }

.feed-breadcrumb { margin-bottom: 0.8rem; font-size: 0.78rem; display: flex; gap: 0.4rem; flex-wrap: wrap; }
.feed-breadcrumb a {
    color: var(--brand-primary, #5a6cff);
    text-decoration: none;
    padding: 0.25rem 0.55rem;
    border-radius: 6px;
    background: rgba(90,108,255,0.1);
    border: 1px solid rgba(90,108,255,0.25);
}
.feed-breadcrumb a:hover { background: rgba(90,108,255,0.18); }

.feed-toolbar {
    display: flex; gap: 0.6rem; flex-wrap: wrap; align-items: center;
    padding: 0.6rem 0.85rem;
    background: var(--bo-surface, rgba(255,255,255,0.02));
    border: 1px solid var(--bo-border, rgba(255,255,255,0.08));
    border-radius: 8px;
    margin-bottom: 0.8rem;
}
.feed-toolbar__info { flex: 1 1 auto; font-size: 0.85rem; color: var(--bo-text-2, #aaa); }
.feed-toolbar__info strong { color: var(--bo-text, #fff); }
.feed-toolbar select, .feed-toolbar input[type=text], .feed-toolbar button {
    padding: 0.35rem 0.6rem;
    background: var(--bo-bg);
    color: var(--bo-text);
    border: 1px solid var(--bo-border);
    border-radius: 6px;
    font-size: 0.82rem;
    cursor: pointer;
}
.feed-toolbar label {
    display: flex; gap: 0.3rem; align-items: center;
    font-size: 0.8rem; color: var(--bo-text-2, #aaa); cursor: pointer;
}
.feed-status {
    display: inline-block;
    margin-left: 0.4rem;
    padding: 0.1rem 0.5rem;
    font-size: 0.7rem; font-weight: 700;
    border-radius: 999px;
    background: rgba(102,187,106,0.15);
    color: var(--bo-success, #66bb6a);
}
.feed-status--paused { background: rgba(255,255,255,0.06); color: var(--bo-text-3, #888); }
.feed-status--loading { background: rgba(90,108,255,0.15); color: var(--brand-primary, #5a6cff); }

.feed-list {
    background: var(--bo-surface, rgba(255,255,255,0.02));
    border: 1px solid var(--bo-border, rgba(255,255,255,0.08));
    border-left: 4px solid var(--brand-primary, #5a6cff);
    border-radius: 8px;
    overflow: hidden;
}

.feed-loading, .feed-empty {
    padding: 3rem 1rem; text-align: center;
    color: var(--bo-text-3, #888); font-size: 0.95rem;
}
.feed-empty__icon { font-size: 2.5rem; margin-bottom: 0.5rem; }

.feed-item {
    padding: 0.85rem 1.1rem;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    transition: background 0.2s;
}
.feed-item:hover { background: rgba(255,255,255,0.03); }
.feed-item:last-child { border-bottom: 0; }
.feed-item--new { animation: feed-flash 2.8s ease-out; }
@keyframes feed-flash {
    0%   { background: rgba(102,187,106,0.22); }
    100% { background: transparent; }
}
.feed-item__head {
    display: flex; gap: 0.55rem; align-items: baseline; flex-wrap: wrap;
    margin-bottom: 0.2rem;
    font-size: 0.92rem;
}
.feed-item__icon { font-size: 1.15rem; }
.feed-item__actor { font-weight: 700; color: var(--bo-text, #fff); }
.feed-item__verb { color: var(--bo-text-2, #aaa); }
.feed-item__elapsed {
    margin-left: auto;
    font-size: 0.75rem; color: var(--bo-text-3, #888);
    white-space: nowrap;
}
.feed-item__payload { padding: 0.2rem 0; font-size: 0.85rem; }
.feed-item__sub {
    color: var(--bo-text-3, #888); font-size: 0.78rem; margin-top: 0.15rem;
}
.feed-item__sub a {
    color: var(--brand-primary, #5a6cff);
    text-decoration: none;
    font-weight: 600;
}
.feed-item__sub a:hover { text-decoration: underline; }

.feed-stav {
    display: inline-block;
    padding: 0.1rem 0.55rem;
    font-size: 0.7rem; font-weight: 700;
    border-radius: 999px;
    background: rgba(255,255,255,0.07);
    color: var(--bo-text-2, #aaa);
}
.feed-stav--ok    { background: rgba(102,187,106,0.18); color: #66bb6a; }
.feed-stav--bad   { background: rgba(231,76,60,0.18);   color: #e74c3c; }
.feed-stav--warn  { background: rgba(243,156,18,0.18);  color: #f39c12; }
.feed-stav--brand { background: rgba(90,108,255,0.18);  color: #5a6cff; }

/* ── RESPONSIVE ───────────────────────────────────── */
@media (max-width: 600px) {
    .feed-wrap { padding: 0.8rem 0.6rem; }
    .feed-wrap h1 { font-size: 1.15rem; }
    .feed-toolbar { padding: 0.5rem 0.6rem; gap: 0.4rem; }
    .feed-item { padding: 0.65rem 0.8rem; }
    .feed-item__elapsed { margin-left: 0; flex: 1 0 100%; order: 99; }
}
</style>

<section class="feed-wrap">
    <div class="feed-breadcrumb">
        <a href="<?= crm_h(crm_url('/dashboard')) ?>">← Dashboard</a>
        <a href="<?= crm_h(crm_url('/admin/datagrid')) ?>">📊 Live datagrid</a>
        <a href="<?= crm_h(crm_url('/admin/duplicates')) ?>">🕵 Audit duplicit</a>
        <a href="<?= crm_h(crm_url('/admin/import')) ?>">📥 Import</a>
    </div>

    <h1>📰 Activity feed</h1>
    <p class="lead">
        Co se právě děje v CRM napříč všemi rolemi. Změny stavu kontaktů, záznamy v pracovním deníku, uzavření smluv. Auto-refresh každých 12 sekund.
    </p>

    <div class="feed-toolbar">
        <div class="feed-toolbar__info">
            <strong id="feed-info-count">…</strong> událostí
            <span id="feed-info-status" class="feed-status">⏱ čeká</span>
            <span id="feed-info-fetched" style="color:var(--bo-text-3);font-size:0.72rem;margin-left:0.4rem;"></span>
        </div>

        <label>
            <input type="checkbox" id="feed-autorefresh" checked> Auto-refresh 12 s
        </label>

        <select id="feed-filter-kind" title="Filtr typu události">
            <option value="">Všechny události</option>
            <option value="stav_change">🔁 Jen změny stavů</option>
            <option value="action">📝 Jen záznamy v deníku</option>
        </select>

        <button type="button" id="feed-reload">🔄 Reload teď</button>
    </div>

    <div id="feed-list" class="feed-list">
        <div class="feed-loading">Načítání aktivit…</div>
    </div>
</section>

<script>
(function () {
    const ENDPOINT = '<?= crm_h(crm_url('/admin/datagrid/feed')) ?>';
    const REFRESH_MS = 12_000;

    let allEvents = [];
    let lastSeenIds = new Set();
    let lastFeedTime = 0;
    let autoRefreshTimer = null;
    let userIsTyping = false;

    function fmtElapsed(sec) {
        if (sec == null) return '';
        if (sec < 60) return 'právě teď';
        if (sec < 3600) return 'před ' + Math.floor(sec / 60) + ' min';
        if (sec < 86400) return 'před ' + Math.floor(sec / 3600) + ' h';
        return 'před ' + Math.floor(sec / 86400) + ' d';
    }
    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[ch]);
    }
    function stavClass(stav) {
        return ({
            UZAVRENO: 'feed-stav--ok',
            NEZAJEM: 'feed-stav--bad', REKLAMACE: 'feed-stav--bad',
            BO_VPRACI: 'feed-stav--warn', BO_PREDANO: 'feed-stav--warn', BO_VRACENO: 'feed-stav--warn',
            NABIDKA: 'feed-stav--brand', SCHUZKA: 'feed-stav--brand', SANCE: 'feed-stav--brand',
            CALLBACK: 'feed-stav--brand',
        })[stav] || '';
    }

    function renderItem(ev, isNew) {
        const elapsed = fmtElapsed(ev.elapsed_sec);
        const actor   = escapeHtml(ev.actor_name);
        const firma   = escapeHtml(ev.firma);
        const region  = escapeHtml(ev.region);
        let icon, verb, payload;

        if (ev.kind === 'stav_change') {
            icon    = '🔁';
            verb    = 'změnil stav →';
            payload = `<span class="feed-stav ${stavClass(ev.payload)}">${escapeHtml(ev.payload || '—')}</span>`;
        } else {
            icon    = '📝';
            verb    = 'zapsal záznam:';
            payload = '"' + escapeHtml(ev.payload || '').slice(0, 200) + (ev.payload && ev.payload.length > 200 ? '…' : '') + '"';
        }

        return `
        <div class="feed-item ${isNew ? 'feed-item--new' : ''}" data-uid="${ev.kind}-${ev.contact_id}-${ev.event_unix}" data-kind="${ev.kind}">
            <div class="feed-item__head">
                <span class="feed-item__icon">${icon}</span>
                <span class="feed-item__actor">${actor}</span>
                <span class="feed-item__verb">${verb}</span>
                <span class="feed-item__elapsed">${elapsed}</span>
            </div>
            <div class="feed-item__payload">${payload}</div>
            <div class="feed-item__sub">→ <strong>${firma}</strong>${region ? ' · ' + region : ''} <small>(#${ev.contact_id})</small></div>
        </div>`;
    }

    function applyFilters(events) {
        const kind = document.getElementById('feed-filter-kind').value;
        return kind ? events.filter(e => e.kind === kind) : events;
    }

    function rerender() {
        const list = document.getElementById('feed-list');
        const filtered = applyFilters(allEvents);
        document.getElementById('feed-info-count').textContent = filtered.length.toLocaleString('cs-CZ');
        if (!filtered.length) {
            list.innerHTML = '<div class="feed-empty"><div class="feed-empty__icon">🌙</div><strong>Klid v paláci</strong><br><small>Žádné události odpovídající filtru.</small></div>';
            return;
        }
        list.innerHTML = filtered.map(ev => renderItem(ev, false)).join('');
    }

    async function fetchFeed(initial = false) {
        const status = document.getElementById('feed-info-status');
        status.className = 'feed-status feed-status--loading';
        status.textContent = '… načítá';
        try {
            const url = ENDPOINT + (lastFeedTime > 0 && !initial ? '?since=' + lastFeedTime : '');
            const res = await fetch(url, { credentials: 'same-origin' });
            const json = await res.json();
            if (!json.ok) throw new Error(json.error || 'feed error');

            const events = json.events || [];
            lastFeedTime = json.now_unix || lastFeedTime;
            document.getElementById('feed-info-fetched').textContent =
                'načteno ' + new Date(json.fetched_at).toLocaleTimeString('cs-CZ');

            if (initial) {
                allEvents = events;
                events.forEach(ev => lastSeenIds.add(ev.kind + '-' + ev.contact_id + '-' + ev.event_unix));
                rerender();
            } else {
                const newOnes = events.filter(ev => {
                    const uid = ev.kind + '-' + ev.contact_id + '-' + ev.event_unix;
                    if (lastSeenIds.has(uid)) return false;
                    lastSeenIds.add(uid);
                    return true;
                });
                if (newOnes.length) {
                    allEvents = newOnes.concat(allEvents).slice(0, 500);
                    // Inkrementální update — přidej nové na začátek s flash highlight
                    const list = document.getElementById('feed-list');
                    const filtered = applyFilters(newOnes);
                    if (filtered.length) {
                        const html = filtered.map(ev => renderItem(ev, true)).join('');
                        list.insertAdjacentHTML('afterbegin', html);
                    }
                    document.getElementById('feed-info-count').textContent =
                        applyFilters(allEvents).length.toLocaleString('cs-CZ');
                }
            }
            status.className = 'feed-status';
            status.textContent = '✓ aktuální';
        } catch (e) {
            status.className = 'feed-status feed-status--paused';
            status.textContent = '✗ chyba';
            console.error(e);
        }
    }

    function setupAutoRefresh() {
        const cb = document.getElementById('feed-autorefresh');
        function tick() { if (cb.checked && !userIsTyping) fetchFeed(false); }
        function reset() {
            if (autoRefreshTimer) clearInterval(autoRefreshTimer);
            if (cb.checked) {
                autoRefreshTimer = setInterval(tick, REFRESH_MS);
                document.getElementById('feed-info-status').textContent = '⏱ čeká';
            } else {
                document.getElementById('feed-info-status').className = 'feed-status feed-status--paused';
                document.getElementById('feed-info-status').textContent = '⏸ pozastaveno';
            }
            try { localStorage.setItem('feed_autorefresh', cb.checked ? '1' : '0'); } catch {}
        }
        cb.addEventListener('change', reset);
        try { if (localStorage.getItem('feed_autorefresh') === '0') cb.checked = false; } catch {}
        reset();
    }

    document.getElementById('feed-filter-kind').addEventListener('change', rerender);
    document.getElementById('feed-reload').addEventListener('click', () => fetchFeed(false));

    fetchFeed(true).then(setupAutoRefresh);
})();
</script>
