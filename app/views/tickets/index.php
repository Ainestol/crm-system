<?php
// e:\Snecinatripu\app\views\tickets\index.php
declare(strict_types=1);
/**
 * Seznam ticketů + formulář na nový (s vkládáním obrázků přes Ctrl+V).
 * Proměnné: $tickets, $counts, $tenantsList, $roleOptions, $filters,
 *           $attByTicket, $isAdmin, $isSuper, $user, $flash
 */
/** @var list<array<string,mixed>> $tickets */
$tickets     = $tickets ?? [];
$counts      = $counts ?? ['open' => 0, 'in_progress' => 0, 'resolved' => 0];
$tenantsList = $tenantsList ?? [];
$roleOptions = $roleOptions ?? [];
$filters     = $filters ?? [];
$attByTicket = $attByTicket ?? [];
$isAdmin     = !empty($isAdmin);
$isSuper     = !empty($isSuper);

$prioLabels = ['low' => 'Nízká', 'medium' => 'Střední', 'high' => 'Vysoká'];
$statLabels = ['open' => 'Otevřený', 'in_progress' => 'V řešení', 'resolved' => 'Vyřešený'];

$fmt = static function (?string $dt): string {
    if (!$dt) { return '—'; }
    $ts = strtotime($dt);
    return $ts ? date('d.m.Y H:i', $ts) : '—';
};
?>
<div class="tickets-wrap">

    <?php if (!empty($flash)) { ?>
        <div class="tk-flash"><?= crm_h((string) $flash) ?></div>
    <?php } ?>

    <div class="tk-head">
        <div>
            <h1 class="tk-title">🎫 Tickety</h1>
            <p class="tk-hint">
                <?= $isAdmin
                    ? 'Tickety k řešení. Posuň je do „v řešení" a po vyřízení označ jako vyřešené.'
                    : 'Tvoje tickety. Něco nefunguje? Založ nový — můžeš vložit i screenshot (Ctrl+V).' ?>
            </p>
        </div>
        <div class="tk-counts">
            <span class="tk-pill tk-st-open"><span class="tk-dot"></span><?= (int) $counts['open'] ?> otevřených</span>
            <span class="tk-pill tk-st-in_progress"><span class="tk-dot"></span><?= (int) $counts['in_progress'] ?> v řešení</span>
            <span class="tk-pill tk-st-resolved"><span class="tk-dot"></span><?= (int) $counts['resolved'] ?> vyřešených</span>
        </div>
    </div>

    <!-- ── Nový ticket ── -->
    <details class="tk-new" <?= $tickets === [] ? 'open' : '' ?>>
        <summary><span class="tk-new-plus">＋</span> Nový ticket</summary>
        <form method="POST" action="<?= crm_h(crm_url('/tickets/create')) ?>" class="tk-form" id="tk-form">
            <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h(crm_csrf_token()) ?>">
            <input type="hidden" name="upload_token" id="tk-upload-token" value="">

            <div class="tk-form-row">
                <label class="tk-lbl">
                    <span class="tk-lbl-txt">Předmět *</span>
                    <input type="text" name="subject" maxlength="200" required
                           placeholder="Krátce co potřebuješ…" class="tk-input">
                </label>
                <label class="tk-lbl tk-lbl--prio">
                    <span class="tk-lbl-txt">Priorita</span>
                    <select name="priority" class="tk-input">
                        <option value="low">🟦 Nízká</option>
                        <option value="medium" selected>🟨 Střední</option>
                        <option value="high">🟥 Vysoká</option>
                    </select>
                </label>
            </div>

            <label class="tk-lbl">
                <span class="tk-lbl-txt">Popis</span>
                <textarea name="body" id="tk-body" rows="3" class="tk-input"
                          placeholder="Detaily problému… Tip: klikni sem a stiskni Ctrl+V pro vložení screenshotu."></textarea>
            </label>

            <!-- Dropzone / paste zone -->
            <div class="tk-drop" id="tk-drop" tabindex="0">
                <div class="tk-drop-inner">
                    <span class="tk-drop-icon">🖼️</span>
                    <span class="tk-drop-txt">
                        Vlož screenshot přes <kbd>Ctrl</kbd>+<kbd>V</kbd>,
                        přetáhni sem obrázek, nebo
                        <button type="button" class="tk-link-btn" id="tk-pick">vyber soubor</button>.
                    </span>
                </div>
                <input type="file" id="tk-file" accept="image/*" multiple hidden>
            </div>

            <div class="tk-thumbs" id="tk-thumbs"></div>
            <div class="tk-up-status" id="tk-up-status"></div>

            <div>
                <button type="submit" class="tk-btn tk-btn--primary">Založit ticket</button>
            </div>
        </form>
    </details>

    <!-- ── Filtry (admin) — jen prohledávání seznamu, NE zakládání ── -->
    <?php if ($isAdmin) { ?>
        <div class="tk-filter-label">🔎 Filtr ticketů (jen pro hledání v seznamu)</div>
        <form method="GET" action="<?= crm_h(crm_url('/tickets')) ?>" class="tk-filters">
            <select name="status" class="tk-input tk-input--sm">
                <option value="">— stav —</option>
                <?php foreach ($statLabels as $k => $v) { ?>
                    <option value="<?= crm_h($k) ?>" <?= ($filters['status'] ?? '') === $k ? 'selected' : '' ?>><?= crm_h($v) ?></option>
                <?php } ?>
            </select>
            <select name="priority" class="tk-input tk-input--sm">
                <option value="">— priorita —</option>
                <?php foreach ($prioLabels as $k => $v) { ?>
                    <option value="<?= crm_h($k) ?>" <?= ($filters['priority'] ?? '') === $k ? 'selected' : '' ?>><?= crm_h($v) ?></option>
                <?php } ?>
            </select>
            <select name="role" class="tk-input tk-input--sm">
                <option value="">— role —</option>
                <?php foreach ($roleOptions as $k => $v) { ?>
                    <option value="<?= crm_h($k) ?>" <?= ($filters['role'] ?? '') === $k ? 'selected' : '' ?>><?= crm_h($v) ?></option>
                <?php } ?>
            </select>
            <?php if ($isSuper && $tenantsList !== []) { ?>
                <select name="tenant" class="tk-input tk-input--sm">
                    <option value="0">— firma —</option>
                    <?php foreach ($tenantsList as $tn) { ?>
                        <option value="<?= (int) $tn['id'] ?>" <?= (int) ($filters['tenant'] ?? 0) === (int) $tn['id'] ? 'selected' : '' ?>><?= crm_h((string) $tn['name']) ?></option>
                    <?php } ?>
                </select>
            <?php } ?>
            <input type="date" name="from" value="<?= crm_h((string) ($filters['from'] ?? '')) ?>" class="tk-input tk-input--sm" title="Od data">
            <input type="date" name="to" value="<?= crm_h((string) ($filters['to'] ?? '')) ?>" class="tk-input tk-input--sm" title="Do data">
            <input type="text" name="q" value="<?= crm_h((string) ($filters['q'] ?? '')) ?>" placeholder="🔍 jméno / předmět" class="tk-input tk-input--sm">
            <button type="submit" class="tk-btn">Filtrovat</button>
            <a href="<?= crm_h(crm_url('/tickets')) ?>" class="tk-btn tk-btn--ghost">Zrušit</a>
        </form>
    <?php } ?>

    <!-- ── Seznam ── -->
    <?php if ($tickets === []) { ?>
        <div class="tk-empty">📭 Žádné tickety.</div>
    <?php } else {
        foreach ($tickets as $t) {
            $st   = (string) ($t['status'] ?? 'open');
            $prio = (string) ($t['priority'] ?? 'medium');
            $tid  = (int) ($t['id'] ?? 0);
            $atts = $attByTicket[$tid] ?? [];
    ?>
        <div class="tk-card tk-prio-<?= crm_h($prio) ?>">
            <div class="tk-card-top">
                <span class="tk-pill tk-st-<?= crm_h($st) ?>"><span class="tk-dot"></span><?= crm_h($statLabels[$st] ?? $st) ?></span>
                <span class="tk-pill tk-pr-<?= crm_h($prio) ?>"><?= crm_h($prioLabels[$prio] ?? $prio) ?></span>
                <span class="tk-subject"><?= crm_h((string) ($t['subject'] ?? '')) ?></span>
                <span class="tk-id">#<?= $tid ?></span>
            </div>

            <?php if (trim((string) ($t['body'] ?? '')) !== '') { ?>
                <div class="tk-body-txt"><?= nl2br(crm_h((string) $t['body'])) ?></div>
            <?php } ?>

            <?php if ($atts !== []) { ?>
                <div class="tk-att">
                    <?php foreach ($atts as $a) {
                        $aurl = crm_url('/tickets/attachment?id=' . (int) $a['id']); ?>
                        <a href="<?= crm_h($aurl) ?>" target="_blank" rel="noopener" class="tk-att-thumb"
                           title="<?= crm_h((string) ($a['orig_name'] ?? 'obrázek')) ?>">
                            <img src="<?= crm_h($aurl) ?>" alt="příloha" loading="lazy">
                        </a>
                    <?php } ?>
                </div>
            <?php } ?>

            <div class="tk-meta">
                <span>👤 <strong><?= crm_h((string) ($t['creator_name'] ?? '—')) ?></strong>
                    <?php if (!empty($t['creator_role'])) { ?>
                        · <?= crm_h($roleOptions[(string) $t['creator_role']] ?? (string) $t['creator_role']) ?>
                    <?php } ?>
                </span>
                <span>· <?= crm_h($fmt((string) ($t['created_at'] ?? ''))) ?></span>
                <?php if ($isSuper && !empty($t['tenant_name'])) { ?>
                    <span>· 🏢 <?= crm_h((string) $t['tenant_name']) ?></span>
                <?php } ?>
                <?php if (!empty($t['in_progress_at'])) { ?>
                    <span>· ▶ <?= crm_h($fmt((string) $t['in_progress_at'])) ?></span>
                <?php } ?>
                <?php if (!empty($t['resolved_at'])) { ?>
                    <span>· ✓ <?= crm_h($fmt((string) $t['resolved_at'])) ?>
                        <?php if (!empty($t['assignee_name'])) { ?>(<?= crm_h((string) $t['assignee_name']) ?>)<?php } ?>
                    </span>
                <?php } ?>
            </div>

            <?php if (trim((string) ($t['resolution'] ?? '')) !== '') { ?>
                <div class="tk-resolution"><strong>✓ Řešení:</strong> <?= nl2br(crm_h((string) $t['resolution'])) ?></div>
            <?php } ?>

            <?php if ($isAdmin) { ?>
                <div class="tk-actions">
                    <?php if ($st !== 'in_progress') { ?>
                        <form method="POST" action="<?= crm_h(crm_url('/tickets/status')) ?>" class="tk-act-form">
                            <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h(crm_csrf_token()) ?>">
                            <input type="hidden" name="ticket_id" value="<?= $tid ?>">
                            <input type="hidden" name="status" value="in_progress">
                            <button type="submit" class="tk-btn tk-btn--sm">▶ Začít řešit</button>
                        </form>
                    <?php } ?>

                    <?php if ($st !== 'resolved') { ?>
                        <details class="tk-resolve">
                            <summary class="tk-btn tk-btn--sm tk-btn--ok">✓ Vyřešit</summary>
                            <form method="POST" action="<?= crm_h(crm_url('/tickets/status')) ?>" class="tk-act-form tk-act-form--col">
                                <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h(crm_csrf_token()) ?>">
                                <input type="hidden" name="ticket_id" value="<?= $tid ?>">
                                <input type="hidden" name="status" value="resolved">
                                <textarea name="resolution" rows="2" class="tk-input"
                                          placeholder="Jak bylo vyřešeno (volitelné)…"></textarea>
                                <button type="submit" class="tk-btn tk-btn--sm tk-btn--ok">Označit jako vyřešené</button>
                            </form>
                        </details>
                    <?php } else { ?>
                        <form method="POST" action="<?= crm_h(crm_url('/tickets/status')) ?>" class="tk-act-form">
                            <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h(crm_csrf_token()) ?>">
                            <input type="hidden" name="ticket_id" value="<?= $tid ?>">
                            <input type="hidden" name="status" value="open">
                            <button type="submit" class="tk-btn tk-btn--sm tk-btn--ghost">↩ Znovu otevřít</button>
                        </form>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    <?php }
    } ?>

</div>

<style>
.tickets-wrap { max-width: 940px; }
.tk-flash { background:#dcfce7; border:1px solid #86efac; color:#166534;
            padding:0.7rem 1rem; border-radius:10px; margin-bottom:1.1rem; font-weight:600; }

.tk-head { display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:0.8rem; margin-bottom:1.1rem; }
.tk-title { font-size:1.5rem; margin:0 0 0.2rem; letter-spacing:-0.01em; }
.tk-hint { color:var(--muted,#6b7280); font-size:0.88rem; margin:0; max-width:560px; }
.tk-counts { display:flex; gap:0.4rem; flex-wrap:wrap; }

/* Pills */
.tk-pill { display:inline-flex; align-items:center; gap:0.4rem; padding:0.22rem 0.7rem;
           border-radius:999px; font-size:0.76rem; font-weight:700; white-space:nowrap; }
.tk-dot { width:7px; height:7px; border-radius:50%; background:currentColor; opacity:0.85; }
.tk-st-open        { background:#fef3c7; color:#92400e; }
.tk-st-in_progress { background:#dbeafe; color:#1d4ed8; }
.tk-st-resolved    { background:#dcfce7; color:#15803d; }
.tk-pr-low    { background:#f1f5f9; color:#475569; }
.tk-pr-medium { background:#fef9c3; color:#854d0e; }
.tk-pr-high   { background:#fee2e2; color:#b91c1c; }

/* New ticket */
.tk-new { margin-bottom:1.2rem; border:1px solid var(--sb-border,#e5e7eb); border-radius:14px;
          background:var(--card-bg,#fff); box-shadow:0 1px 3px rgba(0,0,0,0.04); overflow:hidden; }
.tk-new > summary { cursor:pointer; font-weight:700; padding:0.9rem 1.1rem; list-style:none;
                    display:flex; align-items:center; gap:0.5rem; font-size:0.98rem; }
.tk-new > summary::-webkit-details-marker { display:none; }
.tk-new-plus { display:inline-flex; align-items:center; justify-content:center; width:24px; height:24px;
               border-radius:50%; background:#2563eb; color:#fff; font-weight:700; font-size:1rem; }
.tk-form { display:flex; flex-direction:column; gap:0.8rem; padding:0 1.1rem 1.1rem; }
.tk-form-row { display:flex; gap:0.8rem; flex-wrap:wrap; }
.tk-lbl { display:flex; flex-direction:column; gap:0.3rem; font-size:0.82rem; flex:1; min-width:220px; }
.tk-lbl--prio { flex:0 0 170px; min-width:150px; }
.tk-lbl-txt { font-weight:600; color:var(--text,#374151); }
.tk-input { padding:0.55rem 0.7rem; border:1px solid var(--sb-border,#d1d5db); border-radius:9px;
            font-size:0.92rem; font-family:inherit; width:100%; box-sizing:border-box;
            background:var(--card-bg,#fff); color:var(--text,#111); transition:border-color .15s, box-shadow .15s; }
.tk-input:focus { outline:none; border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,0.12); }
.tk-input--sm { width:auto; font-size:0.82rem; padding:0.4rem 0.55rem; border-radius:7px; }

/* Dropzone */
.tk-drop { border:2px dashed var(--sb-border,#d1d5db); border-radius:11px; padding:0.9rem 1rem;
           background:var(--sb-hover,#f9fafb); transition:border-color .15s, background .15s; cursor:pointer; }
.tk-drop:hover, .tk-drop:focus, .tk-drop.tk-drag { border-color:#2563eb; background:rgba(37,99,235,0.06); outline:none; }
.tk-drop-inner { display:flex; align-items:center; gap:0.6rem; font-size:0.85rem; color:var(--muted,#6b7280); }
.tk-drop-icon { font-size:1.3rem; }
.tk-drop kbd { background:var(--card-bg,#fff); border:1px solid var(--sb-border,#d1d5db); border-bottom-width:2px;
               border-radius:5px; padding:0.05rem 0.35rem; font-size:0.75rem; font-family:monospace; }
.tk-link-btn { background:none; border:none; color:#2563eb; font-weight:600; cursor:pointer; padding:0; font-size:inherit; text-decoration:underline; }

/* Thumbnails */
.tk-thumbs { display:flex; gap:0.5rem; flex-wrap:wrap; }
.tk-thumb { position:relative; width:84px; height:84px; border-radius:9px; overflow:hidden;
            border:1px solid var(--sb-border,#e5e7eb); background:#000; }
.tk-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
.tk-thumb-x { position:absolute; top:2px; right:2px; width:20px; height:20px; border-radius:50%;
              background:rgba(0,0,0,0.65); color:#fff; border:none; cursor:pointer; font-size:0.8rem;
              line-height:1; display:flex; align-items:center; justify-content:center; }
.tk-thumb-x:hover { background:#dc2626; }
.tk-thumb.tk-thumb--loading { display:flex; align-items:center; justify-content:center; color:#fff; font-size:0.7rem; }
.tk-up-status { font-size:0.78rem; color:var(--muted,#6b7280); min-height:1em; }
.tk-up-status.tk-err { color:#dc2626; }

/* Buttons */
.tk-btn { background:var(--card-bg,#fff); border:1px solid var(--sb-border,#d1d5db); color:var(--text,#111);
          border-radius:9px; padding:0.5rem 0.95rem; cursor:pointer; font-weight:600; font-size:0.86rem;
          text-decoration:none; display:inline-block; transition:filter .12s, transform .04s; }
.tk-btn:hover { filter:brightness(0.97); }
.tk-btn:active { transform:translateY(1px); }
.tk-btn--primary { background:#2563eb; color:#fff; border-color:#2563eb; }
.tk-btn--ok { background:#16a34a; color:#fff; border-color:#16a34a; }
.tk-btn--ghost { background:transparent; }
.tk-btn--sm { padding:0.35rem 0.7rem; font-size:0.8rem; border-radius:7px; }

.tk-filter-label { font-size:0.78rem; font-weight:700; color:var(--muted,#6b7280);
                   text-transform:uppercase; letter-spacing:0.04em; margin:0.4rem 0 0.4rem 0.2rem; }
.tk-filters { display:flex; gap:0.45rem; flex-wrap:wrap; align-items:center; margin-bottom:1.1rem;
              padding:0.7rem; background:var(--sb-hover,#f9fafb); border:1px solid var(--sb-border,#e5e7eb);
              border-radius:11px; }

.tk-empty { color:var(--muted,#9ca3af); font-style:italic; padding:2rem 0; text-align:center; font-size:0.95rem; }

/* Cards */
.tk-card { border:1px solid var(--sb-border,#e5e7eb); border-left-width:4px; border-radius:12px;
           padding:0.9rem 1.1rem; margin-bottom:0.8rem; background:var(--card-bg,#fff);
           box-shadow:0 1px 3px rgba(0,0,0,0.04); transition:box-shadow .15s, transform .08s; }
.tk-card:hover { box-shadow:0 4px 14px rgba(0,0,0,0.08); }
.tk-card.tk-prio-high   { border-left-color:#ef4444; }
.tk-card.tk-prio-medium { border-left-color:#eab308; }
.tk-card.tk-prio-low    { border-left-color:#cbd5e1; }
.tk-card-top { display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap; }
.tk-subject { font-weight:700; font-size:1rem; flex:1; min-width:160px; }
.tk-id { color:var(--muted,#9ca3af); font-family:monospace; font-size:0.8rem; }
.tk-body-txt { margin:0.6rem 0; font-size:0.92rem; line-height:1.55; color:var(--text,#111); white-space:pre-wrap; }

.tk-att { display:flex; gap:0.5rem; flex-wrap:wrap; margin:0.6rem 0; }
.tk-att-thumb { width:96px; height:96px; border-radius:9px; overflow:hidden; display:block;
                border:1px solid var(--sb-border,#e5e7eb); background:#000; transition:transform .1s; }
.tk-att-thumb:hover { transform:scale(1.03); }
.tk-att-thumb img { width:100%; height:100%; object-fit:cover; display:block; }

.tk-meta { display:flex; gap:0.4rem; flex-wrap:wrap; font-size:0.78rem; color:var(--muted,#6b7280); margin-top:0.5rem; }
.tk-resolution { margin-top:0.6rem; padding:0.6rem 0.8rem; background:#f0fdf4; border:1px solid #bbf7d0;
                 border-radius:9px; font-size:0.86rem; color:#166534; }
.tk-actions { display:flex; gap:0.5rem; flex-wrap:wrap; align-items:flex-start; margin-top:0.8rem;
              padding-top:0.7rem; border-top:1px dashed var(--sb-border,#e5e7eb); }
.tk-act-form { margin:0; }
.tk-act-form--col { display:flex; flex-direction:column; gap:0.45rem; margin-top:0.45rem; min-width:300px; }
.tk-resolve > summary { list-style:none; display:inline-block; }
.tk-resolve > summary::-webkit-details-marker { display:none; }
</style>

<script>
(function () {
    var form    = document.getElementById('tk-form');
    if (!form) return;
    var drop    = document.getElementById('tk-drop');
    var body    = document.getElementById('tk-body');
    var fileIn  = document.getElementById('tk-file');
    var pickBtn = document.getElementById('tk-pick');
    var thumbs  = document.getElementById('tk-thumbs');
    var statusEl= document.getElementById('tk-up-status');
    var tokenEl = document.getElementById('tk-upload-token');

    var UPLOAD_URL = <?= json_encode(crm_url('/tickets/upload'), JSON_UNESCAPED_SLASHES) ?>;
    var CSRF_FIELD = <?= json_encode(crm_csrf_field_name(), JSON_UNESCAPED_SLASHES) ?>;
    var CSRF_TOKEN = <?= json_encode(crm_csrf_token(), JSON_UNESCAPED_SLASHES) ?>;

    // upload token (páruje nahrané obrázky k zakládanému ticketu)
    function uuid() {
        try { if (crypto && crypto.randomUUID) return crypto.randomUUID(); } catch (e) {}
        return 'tok-' + Date.now() + '-' + Math.random().toString(16).slice(2);
    }
    tokenEl.value = uuid();

    function setStatus(msg, isErr) {
        statusEl.textContent = msg || '';
        statusEl.className = 'tk-up-status' + (isErr ? ' tk-err' : '');
    }

    function addHiddenId(id) {
        var h = document.createElement('input');
        h.type = 'hidden'; h.name = 'attachment_ids[]'; h.value = String(id);
        h.dataset.attId = String(id);
        form.appendChild(h);
    }
    function removeHiddenId(id) {
        var h = form.querySelector('input[data-att-id="' + id + '"]');
        if (h) h.remove();
    }

    function uploadBlob(blob, name) {
        // placeholder thumbnail
        var ph = document.createElement('div');
        ph.className = 'tk-thumb tk-thumb--loading';
        ph.textContent = '⏳';
        thumbs.appendChild(ph);
        setStatus('Nahrávám obrázek…', false);

        var fd = new FormData();
        fd.append(CSRF_FIELD, CSRF_TOKEN);
        fd.append('upload_token', tokenEl.value);
        fd.append('image', blob, name || 'screenshot.png');

        fetch(UPLOAD_URL, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.ok) {
                    ph.remove();
                    setStatus((d && d.error) ? d.error : 'Nahrání selhalo.', true);
                    return;
                }
                ph.className = 'tk-thumb';
                ph.textContent = '';
                var img = document.createElement('img');
                img.src = d.url; img.alt = d.name || 'příloha';
                ph.appendChild(img);
                var x = document.createElement('button');
                x.type = 'button'; x.className = 'tk-thumb-x'; x.textContent = '×';
                x.title = 'Odebrat';
                x.addEventListener('click', function () { ph.remove(); removeHiddenId(d.id); });
                ph.appendChild(x);
                addHiddenId(d.id);
                setStatus('', false);
            })
            .catch(function () { ph.remove(); setStatus('Síťová chyba při nahrávání.', true); });
    }

    function handleFiles(files) {
        if (!files) return;
        for (var i = 0; i < files.length; i++) {
            var f = files[i];
            if (f && f.type && f.type.indexOf('image/') === 0) {
                uploadBlob(f, f.name);
            }
        }
    }

    // Paste (Ctrl+V) — na textarea i dropzone
    function onPaste(e) {
        var items = (e.clipboardData || window.clipboardData) ? (e.clipboardData || window.clipboardData).items : null;
        if (!items) return;
        var found = false;
        for (var i = 0; i < items.length; i++) {
            if (items[i].type && items[i].type.indexOf('image/') === 0) {
                var blob = items[i].getAsFile();
                if (blob) { uploadBlob(blob, 'screenshot.png'); found = true; }
            }
        }
        if (found) e.preventDefault();
    }
    if (body) body.addEventListener('paste', onPaste);
    drop.addEventListener('paste', onPaste);
    // umožni paste i když je fokus na dropzone
    drop.addEventListener('click', function () { fileIn.click(); });
    if (pickBtn) pickBtn.addEventListener('click', function (e) { e.stopPropagation(); fileIn.click(); });
    fileIn.addEventListener('change', function () { handleFiles(fileIn.files); fileIn.value = ''; });

    // Drag & drop
    ['dragenter', 'dragover'].forEach(function (ev) {
        drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('tk-drag'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('tk-drag'); });
    });
    drop.addEventListener('drop', function (e) {
        if (e.dataTransfer && e.dataTransfer.files) handleFiles(e.dataTransfer.files);
    });
})();
</script>
