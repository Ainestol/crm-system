<?php
// e:\Snecinatripu\app\views\caller\_notifications.php
// Sdílený partial: callback notifikace (browser + in-page banner)
// Include na konci caller/index.php i caller/calendar.php
declare(strict_types=1);
?>
<script>
(function () {
    'use strict';

    var POLL_MS      = 60000;          // poll každých 60 s
    var NOTIFY_BEFORE= 10 * 60 * 1000; // 10 minut v ms
    var ENDPOINT     = <?= json_encode(crm_url('/caller/callbacks.json')) ?>;

    // sessionStorage klíč: {contactId_callbackAt: true}
    var STORAGE_KEY  = 'crm_cb_notified';

    function getShown() {
        try { return JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '{}'); } catch(e) { return {}; }
    }
    function markShown(key) {
        var s = getShown(); s[key] = 1;
        try { sessionStorage.setItem(STORAGE_KEY, JSON.stringify(s)); } catch(e) {}
    }

    /* ── Vyžádání oprávnění pro browser notifikace ── */
    function requestPermission() {
        if (!('Notification' in window)) return;
        if (Notification.permission === 'default') {
            Notification.requestPermission();
        }
    }

    /* ── Browser notifikace (zůstane dokud uživatel neklikne) ── */
    function browserNotify(cb, mins) {
        if (!('Notification' in window) || Notification.permission !== 'granted') return;
        var body = (cb.telefon || '') + '\nZavolat za ' + mins + ' min';
        var n = new Notification('📞 Callback: ' + cb.firma, {
            body: body,
            requireInteraction: true,   // nezhasne sama
            tag: 'crm-cb-' + cb.id      // jedna notifikace per kontakt
        });
        n.onclick = function () { window.focus(); n.close(); };
    }

    /* ── In-page banner (musí se odkliknout) ── */
    function showBanner(cb, mins) {
        var container = document.getElementById('crm-cb-banners');
        if (!container) {
            container = document.createElement('div');
            container.id = 'crm-cb-banners';
            document.body.appendChild(container);
        }

        // Pokud banner pro tento kontakt už existuje, neduplicuj
        if (document.getElementById('crm-cb-' + cb.id)) return;

        var banner = document.createElement('div');
        banner.className = 'crm-cb-banner';
        banner.id = 'crm-cb-' + cb.id;
        banner.innerHTML =
            '<div class="crm-cb-banner__icon">📞</div>' +
            '<div class="crm-cb-banner__body">' +
                '<div class="crm-cb-banner__title">' + esc(cb.firma) + '</div>' +
                '<div class="crm-cb-banner__sub">Zavolat za <strong>' + mins + ' min</strong></div>' +
                (cb.telefon
                    ? '<a href="tel:' + esc(cb.telefon) + '" class="crm-cb-banner__phone">' + esc(cb.telefon) + '</a>'
                    : '') +
            '</div>' +
            '<button class="crm-cb-banner__ok" onclick="crmDismissBanner(\'' + cb.id + '\')">Potvrzuji, zavolám ✓</button>';

        container.appendChild(banner);

        // Zvuk (jednoduchý beep přes AudioContext pokud prohlížeč podporuje)
        try {
            var ctx = new (window.AudioContext || window.webkitAudioContext)();
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.connect(gain); gain.connect(ctx.destination);
            osc.frequency.value = 880;
            gain.gain.setValueAtTime(0.3, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
            osc.start(); osc.stop(ctx.currentTime + 0.4);
        } catch(e) {}
    }

    window.crmDismissBanner = function(id) {
        var el = document.getElementById('crm-cb-' + id);
        if (el) {
            el.classList.add('crm-cb-banner--dismissed');
            setTimeout(function() { el.remove(); }, 400);
        }
    };

    function esc(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    /* ── Polling ── */
    function poll() {
        fetch(ENDPOINT, { credentials: 'same-origin' })
            .then(function(r) { return r.ok ? r.json() : null; })
            .then(function(data) {
                if (!data || !Array.isArray(data.callbacks)) return;
                var now   = Date.now();
                var shown = getShown();

                data.callbacks.forEach(function(cb) {
                    var cbTs = new Date(cb.callback_at.replace(' ', 'T')).getTime();
                    var diff = cbTs - now;
                    var key  = 'cb_' + cb.id + '_' + cb.callback_at;

                    // Callback je do 10 minut v budoucnosti a ještě nebyl oznámen
                    if (diff >= -60000 && diff <= NOTIFY_BEFORE && !shown[key]) {
                        markShown(key);
                        var mins = diff > 0 ? Math.ceil(diff / 60000) : 0;
                        browserNotify(cb, mins);
                        showBanner(cb, mins);
                    }
                });
            })
            .catch(function() { /* tiché selhání */ });
    }

    // Spustit hned + pak každou minutu
    requestPermission();
    poll();
    setInterval(poll, POLL_MS);
})();
</script>
