<?php
// e:\Snecinatripu\app\views\oz\queue.php
//
// /oz/queue — incoming pending leads + renewal alerts.
// Refactor view (Krok 1) — používá nový oz_kit.css design system.
// Stará /oz/leads zůstává paralelně funkční až do dokončení migrace.
declare(strict_types=1);

/** @var array<string,mixed>                $user */
/** @var list<array<string,mixed>>          $pendingLeads   čekají na přijetí (po regionu filteru) */
/** @var list<array{caller_id:int,caller_name:string,contacts:list<array<string,mixed>>,count:int}> $pendingByCaller  sgrupované per navolávačka */
/** @var array<string,int>                  $regionCounts   region → počet pending (PŘED filtrem) */
/** @var string                             $selectedRegion  aktivní region filter ('' = vše) */
/** @var list<array<string,mixed>>          $renewals       smlouvy končící do 30 dní */
/** @var int                                $inProgressCount  rozpracované leady (NOVE/ZPRACOVAVA/...) */
/** @var string|null                        $flash */
/** @var string                             $csrf */
$inProgressCount = $inProgressCount ?? 0;
$pendingByCaller = $pendingByCaller ?? [];
$regionCounts    = $regionCounts ?? [];
$selectedRegion  = $selectedRegion ?? '';
$totalPending    = count($pendingLeads);

// Helper pro relativní čas ("před 12 min")
function ozqElapsed(?string $dt): string {
    if ($dt === null || $dt === '') return '';
    $ts = strtotime($dt);
    if ($ts === false) return '';
    $diff = time() - $ts;
    if ($diff < 60)    return 'právě teď';
    if ($diff < 3600)  return 'před ' . (int)($diff/60) . ' min';
    if ($diff < 86400) return 'před ' . (int)($diff/3600) . ' h';
    return 'před ' . (int)($diff/86400) . ' d';
}
?>
<!-- Načti samostatný design system jen pro tuto obrazovku -->
<link rel="stylesheet" href="<?= crm_h(crm_url('/assets/css/oz_kit.css')) ?>">

<div class="oz-page">

    <!-- ── PAGE HEADER ───────────────────────────────────────────── -->
    <div class="oz-page__header">
        <div>
            <h1 class="oz-page__title">📋 Příchozí leady</h1>
            <p class="oz-page__subtitle">Obchodní zástupce: <strong><?= crm_h((string) ($user['jmeno'] ?? '')) ?></strong></p>
        </div>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <a href="<?= crm_h(crm_url('/oz')) ?>" class="oz-btn-secondary oz-btn-sm">📊 Dashboard</a>
            <a href="<?= crm_h(crm_url('/oz/leads')) ?>" class="oz-btn-ghost oz-btn-sm" title="Otevřít plnou pracovní plochu se všemi taby">💼 Pracovní plocha →</a>
        </div>
    </div>

    <?php if (!empty($flash)) { ?>
        <div class="alert alert-info" style="margin-bottom:1rem;"><?= crm_h($flash) ?></div>
    <?php } ?>

    <!-- ══════════════════════════════════════════════════════════════
         1) RENEWAL ALERTY (smlouvy končící do 30 dní)
         Jen pokud existují — nezobrazujeme prázdné sekce.
    ══════════════════════════════════════════════════════════════ -->
    <?php if ($renewals !== []) { ?>
    <section class="oz-section oz-section--warning">
        <h2 class="oz-section__title">
            ⚠ Vypršení smluv
            <span class="oz-section__count">(<?= count($renewals) ?>)</span>
        </h2>

        <?php foreach ($renewals as $r) {
            $days = (int) $r['days_until'];
            $label = $days === 0 ? 'dnes' : ($days === 1 ? 'zítra' : "za {$days} dní");
        ?>
        <div class="oz-card" style="margin-bottom:0.5rem;padding:0.85rem 1rem;">
            <div class="oz-card__header" style="align-items:center;">
                <div>
                    <div style="font-weight:700;font-size:var(--oz-text-lg);"><?= crm_h((string) $r['firma']) ?></div>
                    <div style="font-size:var(--oz-text-sm);color:var(--oz-text-3);margin-top:0.2rem;">
                        Smlouva končí <?= crm_h((string) $r['vyrocni_smlouvy']) ?>
                    </div>
                </div>
                <div class="oz-card__header-meta">
                    <span class="oz-badge oz-badge--warning">⚠ <?= crm_h($label) ?></span>
                    <a href="<?= crm_h(crm_url('/oz/leads')) ?>#c-<?= (int) $r['id'] ?>"
                       class="oz-btn-secondary oz-btn-sm">Otevřít</a>
                </div>
            </div>
        </div>
        <?php } ?>
    </section>
    <?php } ?>

    <!-- ══════════════════════════════════════════════════════════════
         2) PŘÍCHOZÍ LEADY (pending — čekají na Přijmout)
            Sgrupované podle navolávačky. Region je viditelný na každé kartě
            jako velký badge vpravo nahoře.
    ══════════════════════════════════════════════════════════════ -->
    <section class="oz-section">
        <h2 class="oz-section__title">
            📥 Příchozí leady
            <span class="oz-section__count">(<?= $totalPending ?>)</span>
        </h2>

        <?php if ($pendingLeads === []) { ?>
            <div class="oz-empty">
                <div class="oz-empty__icon">📪</div>
                <div>Žádné nové leady od navolávaček.</div>
                <div style="font-size:var(--oz-text-sm);margin-top:0.5rem;color:var(--oz-text-3);">
                    Jakmile navolávačka označí kontakt jako vhodný pro tebe, objeví se tady.
                </div>
                <?php if ($inProgressCount > 0) { ?>
                    <div style="margin-top:1.4rem;padding-top:1.2rem;border-top:1px dashed var(--oz-border);
                                font-size:var(--oz-text-base);color:var(--oz-text-2);">
                        💼 Mezitím máš <strong style="color:var(--oz-primary);"><?= $inProgressCount ?></strong>
                        rozpracovan<?= $inProgressCount === 1 ? 'ý lead' : ($inProgressCount < 5 ? 'é leady' : 'ých leadů') ?> v plné pracovní ploše.
                        <div style="margin-top:0.7rem;">
                            <a href="<?= crm_h(crm_url('/oz/leads')) ?>" class="oz-btn-secondary">
                                💼 Otevřít rozpracované
                            </a>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } else { ?>

            <!-- Region filter — klikatelný (Bonus refactor)
                 Při kliknutí ?region=praha → server filter, redirect zpět na queue.
                 'Vše' tile odstraní filter. -->
            <?php if (count($regionCounts) > 1) { ?>
            <div style="display:flex;gap:0.4rem;flex-wrap:wrap;margin-bottom:1rem;
                        padding:0.55rem 0.75rem;background:var(--oz-border-soft);
                        border-radius:var(--oz-radius-md);font-size:var(--oz-text-sm);
                        color:var(--oz-text-3);align-items:center;">
                <span style="font-weight:600;flex-shrink:0;">🗺️ Filtr kraj:</span>
                <a href="<?= crm_h(crm_url('/oz/queue')) ?>"
                   class="oz-badge<?= $selectedRegion === '' ? ' oz-badge--primary' : '' ?>"
                   style="text-decoration:none;cursor:pointer;<?= $selectedRegion === '' ? '' : 'opacity:0.7;' ?>">
                    Vše · <?= array_sum($regionCounts) ?>
                </a>
                <?php foreach ($regionCounts as $reg => $cnt) {
                    $regL = function_exists('crm_region_label') ? crm_region_label($reg) : $reg;
                    $isActive = ($selectedRegion === $reg);
                    $href = crm_url('/oz/queue?region=' . urlencode($reg));
                ?>
                    <a href="<?= crm_h($href) ?>"
                       class="oz-badge<?= $isActive ? ' oz-badge--primary' : '' ?>"
                       style="text-decoration:none;cursor:pointer;<?= $isActive ? '' : 'opacity:0.7;' ?>"
                       title="Filtrovat na <?= crm_h($regL) ?>">
                        <?= crm_h($regL) ?> · <?= $cnt ?>
                    </a>
                <?php } ?>
                <?php if ($selectedRegion !== '') { ?>
                    <span style="margin-left:auto;font-size:var(--oz-text-xs);color:var(--oz-text-3);">
                        Filtr aktivní · <?= $totalPending ?> z <?= array_sum($regionCounts) ?>
                    </span>
                <?php } ?>
            </div>
            <?php } ?>

            <?php
            // Tracker — globální idx pro „aktivní 1. karta"
            $globalIdx = 0;
            foreach ($pendingByCaller as $group) {
                $callerId   = (int) $group['caller_id'];
                $callerName = (string) $group['caller_name'];
                $count      = (int) $group['count'];
            ?>

            <!-- ── SEKCE per CALLER ── -->
            <div class="oz-caller-group" style="margin-bottom:1.5rem;">

                <div class="oz-caller-group__head"
                     style="display:flex;align-items:center;gap:0.7rem;flex-wrap:wrap;
                            padding:0.55rem 0.85rem;background:var(--oz-card);
                            border:1px solid var(--oz-border);border-left:3px solid var(--oz-primary-40);
                            border-radius:var(--oz-radius-md);margin-bottom:0.5rem;">
                    <div style="flex:1;min-width:180px;">
                        <div style="font-size:var(--oz-text-xs);color:var(--oz-text-3);
                                    text-transform:uppercase;letter-spacing:0.05em;font-weight:600;">
                            Od navolávačky
                        </div>
                        <div style="font-size:var(--oz-text-lg);font-weight:700;color:var(--oz-text);
                                    margin-top:0.1rem;">
                            <?= crm_h($callerName) ?>
                            <span style="font-size:var(--oz-text-sm);color:var(--oz-text-3);
                                         font-weight:500;margin-left:0.4rem;">
                                · <?= $count ?> lead<?= $count === 1 ? '' : ($count < 5 ? 'y' : 'ů') ?>
                            </span>
                        </div>
                    </div>
                    <?php if ($count >= 2) { ?>
                    <form method="post" action="<?= crm_h(crm_url('/oz/accept-all-leads')) ?>"
                          style="margin:0;"
                          onsubmit="return confirm('Přijmout všechny <?= $count ?> leady od <?= crm_h($callerName) ?>?');">
                        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                        <input type="hidden" name="caller_id" value="<?= $callerId ?>">
                        <button type="submit" class="oz-btn-secondary oz-btn-sm"
                                title="Přijmout všechny leady od této navolávačky">
                            ✓ Přijmout vše (<?= $count ?>)
                        </button>
                    </form>
                    <?php } ?>
                </div>

                <?php foreach ($group['contacts'] as $lead) {
                    $cId   = (int) $lead['id'];
                    $firma = (string) ($lead['firma'] ?? '—');
                    $tel   = (string) ($lead['telefon'] ?? '');
                    $email = (string) ($lead['email'] ?? '');
                    $ico   = (string) ($lead['ico'] ?? '');
                    $adr   = (string) ($lead['adresa'] ?? '');
                    $reg   = (string) ($lead['region'] ?? '');
                    $cNote = (string) ($lead['caller_note'] ?? '');
                    $when  = (string) ($lead['datum_volani'] ?? '');
                    $regLabel = function_exists('crm_region_label') ? crm_region_label($reg) : $reg;
                    $isFirst  = ($globalIdx === 0);
                    $globalIdx++;
                ?>
                <article class="oz-card oz-pending-card<?= $isFirst ? ' oz-card--active' : ' oz-card--muted' ?>"
                         id="lead-<?= $cId ?>"
                         data-cid="<?= $cId ?>"
                         tabindex="0">

                    <!-- HEADER: jméno + telefon + region (zvýrazněný) -->
                    <div class="oz-card__header">
                        <div>
                            <h3 class="oz-card__title"><?= crm_h($firma) ?></h3>
                            <?php if ($tel !== '') { ?>
                                <a href="tel:<?= crm_h($tel) ?>" class="oz-card__phone"><?= crm_h($tel) ?></a>
                            <?php } ?>
                            <?php if ($when !== '') { ?>
                                <div style="font-size:var(--oz-text-xs);color:var(--oz-text-3);margin-top:0.3rem;">
                                    📞 navoláno <?= crm_h(ozqElapsed($when)) ?>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="oz-card__header-meta">
                            <?php if ($reg !== '') { ?>
                                <!-- Region zvýrazněný = primární barva, větší než default badge -->
                                <span class="oz-badge oz-badge--primary"
                                      style="font-size:var(--oz-text-sm);padding:0.3rem 0.7rem;letter-spacing:0.03em;">
                                    🗺️ <?= crm_h($regLabel) ?>
                                </span>
                            <?php } ?>
                        </div>
                    </div>

                    <!-- DETAIL (sbalené default) -->
                    <details class="oz-card__detail">
                        <summary>Detail kontaktu</summary>
                        <dl class="oz-card__detail-grid">
                            <?php if ($reg !== '') { ?>
                                <div><dt>Kraj</dt><dd><?= crm_h($regLabel) ?></dd></div>
                            <?php } ?>
                            <?php if ($email !== '') { ?>
                                <div><dt>E-mail</dt><dd><?= crm_h($email) ?></dd></div>
                            <?php } ?>
                            <?php if ($ico !== '') { ?>
                                <div><dt>IČO</dt><dd><?= crm_h($ico) ?></dd></div>
                            <?php } ?>
                            <?php if ($adr !== '') { ?>
                                <div><dt>Adresa</dt><dd><?= crm_h($adr) ?></dd></div>
                            <?php } ?>
                        </dl>
                    </details>

                    <!-- POZNÁMKA OD NAVOLÁVAČKY -->
                    <?php if ($cNote !== '') { ?>
                    <div style="font-size:var(--oz-text-sm);color:var(--oz-text-2);
                                border-left: 3px solid var(--oz-border);
                                padding: 0.4rem 0.75rem;
                                background: var(--oz-border-soft);
                                border-radius: 0 var(--oz-radius-md) var(--oz-radius-md) 0;
                                font-style: italic;">
                        „<?= crm_h($cNote) ?>"
                    </div>
                    <?php } ?>

                    <!-- AKCE -->
                    <div class="oz-card__actions">
                        <form method="post" action="<?= crm_h(crm_url('/oz/queue/accept')) ?>"
                              style="margin:0;">
                            <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                            <input type="hidden" name="contact_id" value="<?= $cId ?>">
                            <button type="submit" class="oz-btn-primary oz-btn-lg"
                                    title="Přijmout — Enter">
                                ✓ PŘIJMOUT
                            </button>
                        </form>
                        <span style="font-size:var(--oz-text-xs);color:var(--oz-text-3);">
                            Po přijetí se otevře pracovní plocha.
                        </span>
                    </div>
                </article>
                <?php } /* / contacts in group */ ?>

            </div>
            <?php } /* / pendingByCaller */ ?>

        <?php } /* / pendingLeads !== [] */ ?>
    </section>

    <!-- ══════════════════════════════════════════════════════════════
         Footer — návrat na dashboard
    ══════════════════════════════════════════════════════════════ -->
    <div style="margin-top:2rem;padding-top:1rem;border-top:1px solid var(--oz-border);">
        <a href="<?= crm_h(crm_url('/dashboard')) ?>" class="oz-btn-ghost oz-btn-sm">← Zpět na dashboard</a>
    </div>

</div>

<script>
/* ─────────────────────────────────────────────────────────────────────
   /oz/queue — interaktivita
   - První karta v pending je defaultně AKTIVNÍ (zelený glow)
   - Klik kdekoli na kartu (mimo button/input/link) ji aktivuje
   - Klávesy ↑/↓ (nebo j/k) přepínají aktivní kartu
   - ENTER na aktivní kartě = Přijmout (submit accept formuláře)
   - Klávesy ignorovány pokud uživatel píše do inputu/textarea
   ───────────────────────────────────────────────────────────────────── */
(function () {
    function getCards() {
        return document.querySelectorAll('.oz-pending-card[data-cid]');
    }
    function getActive() {
        return document.querySelector('.oz-pending-card.oz-card--active');
    }
    function setActive(card) {
        if (!card) return;
        document.querySelectorAll('.oz-pending-card').forEach(function (c) {
            c.classList.remove('oz-card--active');
            c.classList.add('oz-card--muted');
        });
        card.classList.remove('oz-card--muted');
        card.classList.add('oz-card--active');
        // Plynulý scroll do středu pokud je mimo viewport
        var rect = card.getBoundingClientRect();
        var inView = rect.top >= 0 && rect.bottom <= window.innerHeight;
        if (!inView) {
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    // Klik na kartu (mimo UI prvky) → aktivovat
    document.addEventListener('click', function (e) {
        var t = e.target;
        if (t.closest('button, input, textarea, select, a')) return;
        if (t.closest('summary')) return; // <details> toggle
        var card = t.closest('.oz-pending-card[data-cid]');
        if (!card) return;
        setActive(card);
    });

    // Klávesy ↑/↓ + Enter
    document.addEventListener('keydown', function (e) {
        var t = e.target;
        if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable)) return;
        if (e.ctrlKey || e.metaKey || e.altKey) return;

        var key = e.key;
        var cards = Array.prototype.slice.call(getCards());
        if (cards.length === 0) return;
        var active = getActive();
        var idx = active ? cards.indexOf(active) : -1;

        if (key === 'ArrowDown' || key === 'j') {
            e.preventDefault();
            var next = (idx >= 0 && idx < cards.length - 1) ? cards[idx + 1] : cards[0];
            setActive(next);
        } else if (key === 'ArrowUp' || key === 'k') {
            e.preventDefault();
            var prev = (idx > 0) ? cards[idx - 1] : cards[cards.length - 1];
            setActive(prev);
        } else if (key === 'Enter') {
            // Enter na aktivní kartě = submit accept formuláře
            if (!active) return;
            var form = active.querySelector('form');
            if (form) {
                e.preventDefault();
                form.requestSubmit ? form.requestSubmit() : form.submit();
            }
        }
    });

    // Hint při prvním zobrazení (1× per session)
    document.addEventListener('DOMContentLoaded', function () {
        if (!sessionStorage.getItem('oz_queue_hint_seen')) {
            var rows = getCards();
            if (rows.length > 0) {
                var hint = document.createElement('div');
                hint.style.cssText =
                    'position:fixed;bottom:1rem;right:1rem;z-index:9999;'
                    + 'background:rgba(46,204,113,0.95);color:#fff;'
                    + 'padding:0.6rem 0.95rem;border-radius:8px;'
                    + 'font-size:0.8rem;line-height:1.5;'
                    + 'box-shadow:0 4px 14px rgba(0,0,0,0.4);'
                    + 'max-width:280px;';
                hint.innerHTML =
                    '⌨️ <strong>Klávesové zkratky</strong><br>'
                    + '<code style="background:rgba(0,0,0,0.2);padding:1px 5px;border-radius:3px;">↑↓</code> '
                    + 'přepnout · <code style="background:rgba(0,0,0,0.2);padding:1px 5px;border-radius:3px;">Enter</code> přijmout';
                document.body.appendChild(hint);
                setTimeout(function () {
                    hint.style.transition = 'opacity 0.4s';
                    hint.style.opacity = '0';
                    setTimeout(function () { hint.remove(); }, 400);
                }, 5000);
                sessionStorage.setItem('oz_queue_hint_seen', '1');
            }
        }
    });
})();
</script>
