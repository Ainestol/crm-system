<?php
// e:\Snecinatripu\app\views\oz\work.php
//
// /oz/work?id=X — call screen pro 1 lead.
// Refactor view (Krok 2) — fokus mode, žádné stats / závod / taby.
//
// Filozofie: jeden lead na obrazovce, NIC víc. Pro komplexní stavy
// (BO_PREDANO, SMLOUVA s instalačními adresami) je link "Plná pracovní
// plocha" → /oz/leads (existující UI).
declare(strict_types=1);

/** @var array<string,mixed>           $user */
/** @var array<string,mixed>           $contact         lead data + workflow stav */
/** @var list<array<string,mixed>>     $recentNotes     posledních 5 poznámek */
/** @var int                           $remainingPending počet čekajících leadů */
/** @var string|null                   $flash */
/** @var string                        $csrf */

$cId      = (int) ($contact['id'] ?? 0);
$firma    = (string) ($contact['firma'] ?? '—');
$tel      = (string) ($contact['telefon'] ?? '');
$email    = (string) ($contact['email'] ?? '');
$ico      = (string) ($contact['ico'] ?? '');
$adr      = (string) ($contact['adresa'] ?? '');
$reg      = (string) ($contact['region'] ?? '');
$ozStav   = (string) ($contact['oz_stav'] ?? 'NOVE');
$cbAt     = (string) ($contact['callback_at'] ?? '');
$saAt     = (string) ($contact['schuzka_at'] ?? '');
$lastPoz  = (string) ($contact['last_poznamka'] ?? '');
$origPoz  = (string) ($contact['caller_poznamka'] ?? '');
$regLabel = function_exists('crm_region_label') ? crm_region_label($reg) : $reg;

// Mapa stavů na uživatelsky čitelné labely
$stavLabels = [
    'NOVE'       => '🆕 Nový',
    'OBVOLANO'   => '📞 Obvoláno',
    'ZPRACOVAVA' => '▶ Zpracovává',
    'NABIDKA'    => '📨 Nabídka',
    'SCHUZKA'    => '📅 Schůzka',
    'CALLBACK'   => '↻ Callback',
    'SANCE'      => '⭐ Šance',
    'SMLOUVA'    => '🏆 Smlouva',
    'BO_PREDANO' => '📤 BO',
    'BO_VRACENO' => '↩ BO vráceno',
    'NEZAJEM'    => '❌ Nezájem',
    'NERELEVANTNI' => '— Nerelevantní',
];
$stavLabel = $stavLabels[$ozStav] ?? $ozStav;
?>
<link rel="stylesheet" href="<?= crm_h(crm_url('/assets/css/oz_kit.css')) ?>">

<div class="oz-page oz-page--call">

    <!-- ── TOP BAR — minimální, jen navigace ──────────────────────── -->
    <div class="oz-page__header">
        <div>
            <a href="<?= crm_h(crm_url('/oz/queue')) ?>" class="oz-btn-ghost oz-btn-sm">
                ← Queue<?php if ($remainingPending > 0) { ?> <span style="opacity:0.6;">(zbývá <?= $remainingPending ?>)</span><?php } ?>
            </a>
        </div>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <a href="<?= crm_h(crm_url('/oz/leads?tab=nove#c-' . $cId)) ?>"
               class="oz-btn-ghost oz-btn-sm"
               title="Komplexní akce (Smlouva, BO předání, Šance, ID nabídky atd.)">
                🔧 Plná pracovní plocha
            </a>
        </div>
    </div>

    <?php if (!empty($flash)) { ?>
        <div class="alert alert-info" style="margin-bottom:1rem;"><?= crm_h($flash) ?></div>
    <?php } ?>

    <!-- ══════════════════════════════════════════════════════════════
         CALL SCREEN CARD — jediný lead, fokus mode
    ══════════════════════════════════════════════════════════════ -->
    <article class="oz-card oz-card--active" style="margin-bottom:1rem;">

        <!-- HEADER — dominantní jméno + tel + region -->
        <div class="oz-card__header">
            <div>
                <h1 class="oz-card__title"><?= crm_h($firma) ?></h1>
                <?php if ($tel !== '') { ?>
                    <a href="tel:<?= crm_h($tel) ?>" class="oz-card__phone"
                       title="Klikni pro volání">📞 <?= crm_h($tel) ?></a>
                <?php } else { ?>
                    <span style="font-size:var(--oz-text-lg);color:var(--oz-text-3);">📞 (telefon nezadán)</span>
                <?php } ?>
            </div>
            <div class="oz-card__header-meta">
                <?php if ($reg !== '') { ?>
                    <span class="oz-badge"><?= crm_h($regLabel) ?></span>
                <?php } ?>
                <span class="oz-badge oz-badge--primary"><?= crm_h($stavLabel) ?></span>
            </div>
        </div>

        <!-- DETAIL — sbalený default -->
        <details class="oz-card__detail">
            <summary>Detail kontaktu</summary>
            <dl class="oz-card__detail-grid">
                <?php if ($email !== '') { ?>
                    <div><dt>E-mail</dt><dd><?= crm_h($email) ?></dd></div>
                <?php } ?>
                <?php if ($ico !== '') { ?>
                    <div><dt>IČO</dt><dd><?= crm_h($ico) ?></dd></div>
                <?php } ?>
                <?php if ($adr !== '') { ?>
                    <div><dt>Adresa</dt><dd><?= crm_h($adr) ?></dd></div>
                <?php } ?>
                <?php if ($cbAt !== '') { ?>
                    <div><dt>Callback naplánován</dt>
                         <dd style="color:var(--oz-warning);"><?= crm_h(date('j.n.Y H:i', strtotime($cbAt))) ?></dd></div>
                <?php } ?>
                <?php if ($saAt !== '') { ?>
                    <div><dt>Schůzka naplánována</dt>
                         <dd style="color:var(--oz-primary);"><?= crm_h(date('j.n.Y H:i', strtotime($saAt))) ?></dd></div>
                <?php } ?>
            </dl>
        </details>

        <!-- ORIGINÁLNÍ POZNÁMKA OD NAVOLÁVAČKY (pokud existuje) -->
        <?php if ($origPoz !== '') { ?>
        <div style="font-size:var(--oz-text-sm);color:var(--oz-text-2);
                    border-left: 3px solid var(--oz-border);
                    padding: 0.5rem 0.85rem;
                    background: var(--oz-border-soft);
                    border-radius: 0 var(--oz-radius-md) var(--oz-radius-md) 0;
                    font-style: italic;">
            <span style="font-size:var(--oz-text-xs);text-transform:uppercase;color:var(--oz-text-3);
                         letter-spacing:0.05em;display:block;margin-bottom:0.2rem;font-style:normal;">
                Poznámka od navolávačky
            </span>
            „<?= crm_h($origPoz) ?>"
        </div>
        <?php } ?>

        <!-- POZNÁMKY OD OZ — historie (sbalené default, otevřené pokud existují) -->
        <?php if ($recentNotes !== []) { ?>
        <details<?= count($recentNotes) <= 2 ? ' open' : '' ?>>
            <summary style="font-size:var(--oz-text-sm);color:var(--oz-text-3);cursor:pointer;
                            padding:0.3rem 0;list-style:none;user-select:none;">
                ▾ Předchozí poznámky (<?= count($recentNotes) ?>)
            </summary>
            <div style="margin-top:0.5rem;">
                <?php foreach ($recentNotes as $n) {
                    $when = (string) ($n['created_at'] ?? '');
                    $note = (string) ($n['note'] ?? '');
                ?>
                <div style="padding:0.4rem 0.75rem;margin-bottom:0.3rem;
                            background:var(--oz-bg);border-radius:var(--oz-radius-md);
                            font-size:var(--oz-text-sm);">
                    <div style="font-size:var(--oz-text-xs);color:var(--oz-text-3);margin-bottom:0.2rem;">
                        <?= $when !== '' ? crm_h(date('j.n.Y H:i', strtotime($when))) : '' ?>
                    </div>
                    <div style="color:var(--oz-text-2);"><?= nl2br(crm_h($note)) ?></div>
                </div>
                <?php } ?>
            </div>
        </details>
        <?php } ?>

    </article>

    <!-- ══════════════════════════════════════════════════════════════
         AKCE — 5 hlavních akcí, jednotný styl
    ══════════════════════════════════════════════════════════════ -->
    <div class="oz-card" style="background:var(--oz-bg);">

        <!-- POZNÁMKA (povinná pro většinu akcí) -->
        <div>
            <label for="oz-note-input" style="display:block;font-size:var(--oz-text-sm);
                       color:var(--oz-text-3);margin-bottom:0.4rem;font-weight:600;">
                💬 Poznámka <span style="color:var(--oz-error);">*</span>
            </label>
            <textarea id="oz-note-input" class="oz-note"
                      placeholder="Zapiš co se s leadem děje, výsledek hovoru, dohoda…"
                      maxlength="2000"></textarea>
        </div>

        <!-- AKCE — 6 hlavních akcí (jako stará UI), sjednocený styl -->
        <div class="oz-card__actions" style="margin-top:0.5rem;">

            <!-- 1) Nabídka odeslána (NABIDKA) -->
            <button type="button" class="oz-btn-secondary"
                    onclick="ozWorkSubmit('NABIDKA', this)">
                ✉ Nabídka odeslána
            </button>

            <!-- 2) Schůzka (SCHUZKA — vyžaduje datum) -->
            <button type="button" class="oz-btn-secondary"
                    onclick="ozWorkExpand('schuzka')">
                📅 Schůzka
            </button>

            <!-- 3) Callback (CALLBACK — vyžaduje datum) -->
            <button type="button" class="oz-btn-secondary"
                    onclick="ozWorkExpand('callback')">
                ↻ Callback
            </button>

            <!-- 4) Šance (SANCE — vyžaduje BMSL) -->
            <button type="button" class="oz-btn-secondary"
                    onclick="ozWorkExpand('sance')">
                ⭐ Šance
            </button>

            <!-- 5) Předat BO (BO_PREDANO — vyžaduje nabidka_id + BMSL) -->
            <button type="button" class="oz-btn-secondary"
                    onclick="ozWorkExpand('bopredano')">
                📤 Předat BO
            </button>

            <!-- 6) Save note only (NOTE_ONLY — jen poznámka, stav nemění) -->
            <button type="button" class="oz-btn-secondary"
                    onclick="ozWorkSubmit('NOTE_ONLY', this)">
                💾 Uložit poznámku
            </button>

            <span class="oz-card__actions-spacer"></span>

            <!-- 7) Chybný lead (REKLAMACE — warning, vpravo před Nezájem) -->
            <button type="button" class="oz-btn-secondary"
                    onclick="ozWorkExpand('reklamace')"
                    style="color:var(--oz-warning);border-color:rgba(240,160,48,0.3);"
                    title="Vrátit lead navolávačce — kvalita kontaktu je špatná">
                ⚠ Chybný lead
            </button>

            <!-- 8) Nezájem (NEZAJEM) — vpravo, separated -->
            <button type="button" class="oz-btn-negative"
                    onclick="ozWorkConfirmNezajem(this)">
                ❌ Nezájem
            </button>
        </div>

        <!-- Inline panel: Schůzka -->
        <div id="oz-panel-schuzka" class="oz-inline-panel" hidden>
            <label style="display:block;font-size:var(--oz-text-sm);color:var(--oz-text-3);
                          margin-bottom:0.3rem;">📅 Datum a čas schůzky</label>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
                <input type="datetime-local" id="oz-schuzka-at"
                       style="padding:0.5rem 0.7rem;background:var(--oz-bg);
                              color:var(--oz-text);border:1px solid var(--oz-border);
                              border-radius:var(--oz-radius-md);font-family:inherit;
                              font-size:var(--oz-text-base);">
                <button type="button" class="oz-btn-primary"
                        onclick="ozWorkSubmit('SCHUZKA', this)">
                    Naplánovat schůzku
                </button>
                <button type="button" class="oz-btn-ghost oz-btn-sm"
                        onclick="ozWorkCollapse('schuzka')">Zrušit</button>
            </div>
        </div>

        <!-- Inline panel: Callback -->
        <div id="oz-panel-callback" class="oz-inline-panel" hidden>
            <label style="display:block;font-size:var(--oz-text-sm);color:var(--oz-text-3);
                          margin-bottom:0.3rem;">↻ Datum a čas callbacku</label>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
                <input type="datetime-local" id="oz-callback-at"
                       style="padding:0.5rem 0.7rem;background:var(--oz-bg);
                              color:var(--oz-text);border:1px solid var(--oz-border);
                              border-radius:var(--oz-radius-md);font-family:inherit;
                              font-size:var(--oz-text-base);">
                <button type="button" class="oz-btn-primary"
                        onclick="ozWorkSubmit('CALLBACK', this)">
                    Nastavit callback
                </button>
                <button type="button" class="oz-btn-ghost oz-btn-sm"
                        onclick="ozWorkCollapse('callback')">Zrušit</button>
            </div>
        </div>

        <!-- Inline panel: Šance — vyžaduje BMSL -->
        <div id="oz-panel-sance" class="oz-inline-panel" hidden>
            <label style="display:block;font-size:var(--oz-text-sm);color:var(--oz-text-3);
                          margin-bottom:0.3rem;">⭐ BMSL (Kč bez DPH, zaokrouhlení dolů na stokoruny)</label>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
                <input type="number" id="oz-sance-bmsl" min="100" step="100"
                       placeholder="např. 2500"
                       style="padding:0.5rem 0.7rem;background:var(--oz-bg);
                              color:var(--oz-text);border:1px solid var(--oz-border);
                              border-radius:var(--oz-radius-md);font-family:inherit;
                              font-size:var(--oz-text-base);width:140px;">
                <span style="color:var(--oz-text-3);font-size:var(--oz-text-sm);">Kč</span>
                <button type="button" class="oz-btn-primary"
                        onclick="ozWorkSubmit('SANCE', this)">
                    Označit jako Šance
                </button>
                <button type="button" class="oz-btn-ghost oz-btn-sm"
                        onclick="ozWorkCollapse('sance')">Zrušit</button>
            </div>
        </div>

        <!-- Inline panel: Chybný lead (reklamace) — vyžaduje důvod -->
        <div id="oz-panel-reklamace" class="oz-inline-panel" hidden
             style="border-color:rgba(240,160,48,0.4);background:rgba(240,160,48,0.06);">
            <label style="display:block;font-size:var(--oz-text-sm);color:var(--oz-warning);
                          margin-bottom:0.3rem;font-weight:600;">
                ⚠ Důvod chybného leadu (kvalita, špatný kontakt, …)
            </label>
            <div style="font-size:var(--oz-text-xs);color:var(--oz-text-3);margin-bottom:0.4rem;">
                Lead bude vrácen navolávačce s tímto popisem. Důvod je povinný.
            </div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
                <input type="text" id="oz-reklamace-reason" maxlength="500"
                       placeholder="např. Číslo neplatné · Klient netuší o ničem"
                       style="flex:1;min-width:240px;padding:0.5rem 0.7rem;background:var(--oz-bg);
                              color:var(--oz-text);border:1px solid var(--oz-border);
                              border-radius:var(--oz-radius-md);font-family:inherit;
                              font-size:var(--oz-text-base);">
                <button type="button" class="oz-btn-primary"
                        style="background:var(--oz-warning);border-color:var(--oz-warning);"
                        onclick="ozWorkSubmitReklamace(this)">
                    Vrátit navolávačce
                </button>
                <button type="button" class="oz-btn-ghost oz-btn-sm"
                        onclick="ozWorkCollapse('reklamace')">Zrušit</button>
            </div>
        </div>

        <!-- Inline panel: Předat BO — vyžaduje ID nabídky + BMSL -->
        <div id="oz-panel-bopredano" class="oz-inline-panel" hidden>
            <label style="display:block;font-size:var(--oz-text-sm);color:var(--oz-text-3);
                          margin-bottom:0.3rem;">📤 Předat BO — vyžaduje ID nabídky + BMSL</label>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
                <input type="text" id="oz-bo-nabidka-id" maxlength="80"
                       placeholder="ID nabídky (z OT)"
                       style="padding:0.5rem 0.7rem;background:var(--oz-bg);
                              color:var(--oz-text);border:1px solid var(--oz-border);
                              border-radius:var(--oz-radius-md);font-family:inherit;
                              font-size:var(--oz-text-base);width:160px;">
                <input type="number" id="oz-bo-bmsl" min="100" step="100"
                       placeholder="BMSL"
                       style="padding:0.5rem 0.7rem;background:var(--oz-bg);
                              color:var(--oz-text);border:1px solid var(--oz-border);
                              border-radius:var(--oz-radius-md);font-family:inherit;
                              font-size:var(--oz-text-base);width:120px;">
                <span style="color:var(--oz-text-3);font-size:var(--oz-text-sm);">Kč</span>
                <button type="button" class="oz-btn-primary"
                        onclick="ozWorkSubmit('BO_PREDANO', this)">
                    Předat BO
                </button>
                <button type="button" class="oz-btn-ghost oz-btn-sm"
                        onclick="ozWorkCollapse('bopredano')">Zrušit</button>
            </div>
        </div>

    </div>

    <!-- Hidden form pro POST (vyplníme z JS) -->
    <form id="oz-work-form" method="post"
          action="<?= crm_h(crm_url('/oz/work/quick-status')) ?>"
          style="display:none;">
        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
        <input type="hidden" name="contact_id" value="<?= $cId ?>">
        <input type="hidden" name="oz_stav" value="">
        <input type="hidden" name="oz_poznamka" value="">
        <input type="hidden" name="callback_at" value="">
        <input type="hidden" name="schuzka_at" value="">
        <input type="hidden" name="bmsl" value="">
        <input type="hidden" name="nabidka_id" value="">
        <input type="hidden" name="return_to" value="queue">
    </form>

    <!-- Volba: po akci → další lead nebo zůstat -->
    <div style="display:flex;align-items:center;gap:0.5rem;margin-top:0.6rem;
                font-size:var(--oz-text-sm);color:var(--oz-text-3);">
        <input type="checkbox" id="oz-stay-after" style="cursor:pointer;">
        <label for="oz-stay-after" style="cursor:pointer;user-select:none;">
            Po akci zůstat na tomto leadu (jinak → další z queue)
        </label>
    </div>

</div>

<style>
.oz-inline-panel {
    margin-top: var(--oz-space-3);
    padding: var(--oz-space-3) var(--oz-space-4);
    background: var(--oz-card);
    border: 1px solid var(--oz-border);
    border-radius: var(--oz-radius-md);
}
.oz-inline-panel[hidden] { display: none !important; }
</style>

<script>
/* ─────────────────────────────────────────────────────────────────────
   /oz/work — interaktivita
   - 4 secondary akce + 1 primary save + 1 negative nezájem
   - Inline panely pro Schůzka / Callback (vyžadují datum)
   - Klávesy: 1=Nabídka, 2=Schůzka, 3=Callback, 4=Save note, ESC=collapse panely
   - Po akci redirect: queue (default) nebo stay (pokud zaškrtnuto)
   ───────────────────────────────────────────────────────────────────── */
(function () {
    var form         = document.getElementById('oz-work-form');
    var noteEl       = document.getElementById('oz-note-input');
    var stayEl       = document.getElementById('oz-stay-after');
    var schuzkaPanel = document.getElementById('oz-panel-schuzka');
    var callbackPanel= document.getElementById('oz-panel-callback');
    var sancePanel   = document.getElementById('oz-panel-sance');
    var bopredanoPanel = document.getElementById('oz-panel-bopredano');
    var reklamacePanel = document.getElementById('oz-panel-reklamace');

    /** Skryje všechny inline panely. */
    function collapseAll() {
        if (schuzkaPanel)   schuzkaPanel.hidden   = true;
        if (callbackPanel)  callbackPanel.hidden  = true;
        if (sancePanel)     sancePanel.hidden     = true;
        if (bopredanoPanel) bopredanoPanel.hidden = true;
        if (reklamacePanel) reklamacePanel.hidden = true;
    }

    function setReturnTo() {
        form.elements['return_to'].value = stayEl.checked ? 'stay' : 'queue';
    }

    /** Submit s daným stavem. Validuje poznámku + sestaví formulář. */
    window.ozWorkSubmit = function (stav, btn) {
        var note = (noteEl.value || '').trim();
        // Validace pro NOTE_ONLY (= jen uložit poznámku)
        if (stav === 'NOTE_ONLY' && note === '') {
            alert('Poznámka nesmí být prázdná.');
            noteEl.focus();
            return;
        }
        // Pro ostatní stavy je poznámka povinná
        if (stav !== 'NOTE_ONLY' && note === '') {
            alert('⚠ Nejdříve vyplň poznámku.');
            noteEl.focus();
            return;
        }
        // Pro SCHUZKA / CALLBACK je nutné datum
        if (stav === 'SCHUZKA') {
            var saAt = document.getElementById('oz-schuzka-at').value;
            if (!saAt) {
                alert('⚠ Vyplň datum a čas schůzky.');
                return;
            }
            form.elements['schuzka_at'].value = saAt;
        }
        if (stav === 'CALLBACK') {
            var cbAt = document.getElementById('oz-callback-at').value;
            if (!cbAt) {
                alert('⚠ Vyplň datum a čas callbacku.');
                return;
            }
            form.elements['callback_at'].value = cbAt;
        }
        // Pro SANCE je nutný BMSL
        if (stav === 'SANCE') {
            var sanceBmsl = (document.getElementById('oz-sance-bmsl').value || '').trim();
            if (!sanceBmsl || parseInt(sanceBmsl, 10) < 100) {
                alert('⚠ Vyplň BMSL (Kč bez DPH, alespoň 100).');
                return;
            }
            form.elements['bmsl'].value = sanceBmsl;
        }
        // Pro BO_PREDANO je nutné ID nabídky + BMSL
        if (stav === 'BO_PREDANO') {
            var nid = (document.getElementById('oz-bo-nabidka-id').value || '').trim();
            var boBmsl = (document.getElementById('oz-bo-bmsl').value || '').trim();
            if (!nid) {
                alert('⚠ Vyplň ID nabídky.');
                return;
            }
            if (!boBmsl || parseInt(boBmsl, 10) < 100) {
                alert('⚠ Vyplň BMSL (Kč bez DPH, alespoň 100).');
                return;
            }
            form.elements['nabidka_id'].value = nid;
            form.elements['bmsl'].value       = boBmsl;
        }
        // Sestavit a odeslat
        form.elements['oz_stav'].value     = stav;
        form.elements['oz_poznamka'].value = note;
        setReturnTo();
        if (btn) btn.disabled = true;
        form.submit();
    };

    /** Nezájem — inline 2-step potvrzení (žádný native confirm).
        První klik: tlačítko se změní na "Klikni znovu pro potvrzení".
        Druhý klik (do 5s): submit. Po 5s se reset. */
    var _nezajemPending = false;
    var _nezajemTimeout = null;
    window.ozWorkConfirmNezajem = function (btn) {
        var note = (noteEl.value || '').trim();
        if (note === '') {
            alert('⚠ Nejdříve vyplň poznámku — důvod nezájmu.');
            noteEl.focus();
            return;
        }
        if (_nezajemPending) {
            // Druhý klik do 5s = potvrzeno → submit
            _nezajemPending = false;
            if (_nezajemTimeout) clearTimeout(_nezajemTimeout);
            ozWorkSubmit('NEZAJEM', btn);
            return;
        }
        // První klik → změnit text + nastavit reset timer
        _nezajemPending = true;
        var origText = btn.textContent;
        btn.textContent = '⚠ Klikni znovu pro potvrzení';
        btn.style.background = 'rgba(231,76,60,0.18)';
        btn.style.color = '#e74c3c';
        btn.style.borderColor = '#e74c3c';
        _nezajemTimeout = setTimeout(function () {
            _nezajemPending = false;
            btn.textContent = origText;
            btn.style.background = '';
            btn.style.color = '';
            btn.style.borderColor = '';
        }, 5000);
    };

    /** Rozbalit inline panel — vždy zavře ostatní panely. */
    window.ozWorkExpand = function (which) {
        collapseAll();
        if (which === 'schuzka') {
            schuzkaPanel.hidden = false;
            document.getElementById('oz-schuzka-at').focus();
        } else if (which === 'callback') {
            callbackPanel.hidden = false;
            document.getElementById('oz-callback-at').focus();
        } else if (which === 'sance') {
            sancePanel.hidden = false;
            document.getElementById('oz-sance-bmsl').focus();
        } else if (which === 'bopredano') {
            bopredanoPanel.hidden = false;
            document.getElementById('oz-bo-nabidka-id').focus();
        } else if (which === 'reklamace') {
            reklamacePanel.hidden = false;
            document.getElementById('oz-reklamace-reason').focus();
        }
    };
    window.ozWorkCollapse = function (which) {
        if (which === 'schuzka')   schuzkaPanel.hidden   = true;
        if (which === 'callback')  callbackPanel.hidden  = true;
        if (which === 'sance')     sancePanel.hidden     = true;
        if (which === 'bopredano') bopredanoPanel.hidden = true;
        if (which === 'reklamace') reklamacePanel.hidden = true;
    };

    /** Submit reklamace — důvod z vlastního pole (NE z hlavní textarey).
        UX: inline 2-step potvrzení (žádný native confirm).
        První klik na "Vrátit navolávačce" = button změní na warning
        "⚠ Klikni znovu pro potvrzení". Druhý klik do 5s = submit.
        Po 5s reset zpět. */
    var _reklamacePending = false;
    var _reklamaceTimeout = null;
    window.ozWorkSubmitReklamace = function (btn) {
        var reason = (document.getElementById('oz-reklamace-reason').value || '').trim();
        if (reason === '') {
            alert('⚠ Vyplň důvod chybného leadu.');
            document.getElementById('oz-reklamace-reason').focus();
            return;
        }
        if (_reklamacePending) {
            // Druhý klik do 5s = potvrzeno → submit
            _reklamacePending = false;
            if (_reklamaceTimeout) clearTimeout(_reklamaceTimeout);
            // Reklamace POUŽÍVÁ vlastní reason jako poznámku (přepíše textarea).
            form.elements['oz_stav'].value     = 'REKLAMACE';
            form.elements['oz_poznamka'].value = reason;
            setReturnTo();
            if (btn) btn.disabled = true;
            form.submit();
            return;
        }
        // První klik → změnit text + nastavit reset timer
        _reklamacePending = true;
        var origText = btn.textContent;
        var origBg   = btn.style.background;
        var origBor  = btn.style.borderColor;
        btn.textContent = '⚠ Klikni znovu pro potvrzení';
        btn.style.background   = 'rgba(243,156,18,0.25)';
        btn.style.borderColor  = '#f39c12';
        btn.style.color        = '#fff';
        _reklamaceTimeout = setTimeout(function () {
            _reklamacePending = false;
            btn.textContent       = origText;
            btn.style.background  = origBg;
            btn.style.borderColor = origBor;
            btn.style.color       = '';
        }, 5000);
    };

    // Klávesy: 1=Nabídka, 2=Schůzka, 3=Callback, 4=Šance, 5=Předat BO, 6=Save note, ESC=collapse
    document.addEventListener('keydown', function (e) {
        var t = e.target;
        if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable)) {
            if (e.key === 'Escape') collapseAll();
            return; // neaktivuj číselné šipky když píše do inputu
        }
        if (e.ctrlKey || e.metaKey || e.altKey) return;

        switch (e.key) {
            case '1': e.preventDefault(); ozWorkSubmit('NABIDKA');     break;
            case '2': e.preventDefault(); ozWorkExpand('schuzka');     break;
            case '3': e.preventDefault(); ozWorkExpand('callback');    break;
            case '4': e.preventDefault(); ozWorkExpand('sance');       break;
            case '5': e.preventDefault(); ozWorkExpand('bopredano');   break;
            case '6': e.preventDefault(); ozWorkSubmit('NOTE_ONLY');   break;
            case '7': e.preventDefault(); ozWorkExpand('reklamace');   break;
            case 'Escape': collapseAll(); break;
        }
    });

    // Auto-focus na poznámku při načtení (UX: OZ rovnou píše)
    document.addEventListener('DOMContentLoaded', function () {
        if (noteEl) noteEl.focus();
    });
})();
</script>
