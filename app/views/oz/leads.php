<?php
// e:\Snecinatripu\app\views\oz\leads.php
declare(strict_types=1);
/** @var array<string, mixed>                          $user */
/** @var list<array<string, mixed>>                    $contacts */
/** @var array<int, list<array<string, mixed>>>        $notesByContact */
/** @var array<int, list<array{id:int,action_date:string,action_text:string,created_at:string}>> $actionsByContact */
/** @var list<string>                                  $hiddenTabs */
/** @var list<string>                                  $tabOrder */
/** @var array<string, list<string>>                   $subTabOrder */
/** @var array<int, list<array{service: array<string,mixed>, items: list<array<string,mixed>>}>> $offeredServicesByContact */
/** @var list<array<string, mixed>>                    $meetingNotifications */
/** @var array<string, int|string>                     $tabCounts */
/** @var string                                        $tab */
/** @var int                                           $monthWins */
/** @var int                                           $curYear */
/** @var int                                           $curMonth */
/** @var string|null                                   $flash */
/** @var int                                           $monthBmsl */
/** @var array<string, mixed>                          $teamStats          – contracts, bmsl */
/** @var list<array<string, mixed>>                    $teamStages */
/** @var list<array<string, mixed>>                    $personalMilestones */
/** @var string                                        $csrf */
/** @var list<array{caller_id:int,caller_name:string,contacts:list<array<string,mixed>>}> $pendingByCaller */
/** @var list<array<string, mixed>>                    $boReturned */
/** @var list<array{id:int,firma:string,vyrocni_smlouvy:string,days_until:int}> $renewalsForOz */

$czechMonths = ['','Leden','Únor','Březen','Duben','Květen','Červen',
                'Červenec','Srpen','Září','Říjen','Listopad','Prosinec'];

function ozElapsed(?string $dt): string {
    if ($dt === null || $dt === '') return '';
    $diff = time() - strtotime($dt);
    if ($diff < 60)    return 'právě teď';
    if ($diff < 3600)  return 'před ' . (int)($diff/60) . ' min';
    if ($diff < 86400) return 'před ' . (int)($diff/3600) . ' h';
    return 'před ' . (int)($diff/86400) . ' d';
}

function ozMeetingLabel(string $dt): string {
    $ts   = strtotime($dt);
    $diff = $ts - time();
    $date = date('d.m.Y H:i', $ts);
    if ($diff < 0)      return $date . ' (probíhá!)';
    if ($diff < 3600)   return $date . ' (za ' . (int)($diff/60) . ' min)';
    if ($diff < 86400)  return $date . ' (za ' . (int)($diff/3600) . ' h)';
    if ($diff < 172800) return $date . ' (zítra)';
    return $date;
}

// Set skrytých tabů — kontrola viditelnosti akčních tlačítek napříč kartami
$hiddenTabsSet = array_flip($hiddenTabs ?? []);
?>

<link rel="stylesheet" href="<?= crm_h(crm_url('/assets/css/oz_leads.css')) ?>">

<?php
// Spočítej čekající leady (pending — před přijmutím v /oz/queue) pro banner.
$pendingTotal = 0;
foreach (($pendingByCaller ?? []) as $pg) {
    $pendingTotal += count((array) ($pg['contacts'] ?? []));
}
?>
<!-- ══════════════════════════════════════════════════════════════════
     MIGRATION BANNER — subtle inline tip na novou pracovní plochu.
     Pokud existují pending leady, zobrazí jejich počet jako CTA.
     Schovatelný na 7 dní (LocalStorage).
══════════════════════════════════════════════════════════════════ -->
<div id="oz-newui-banner" style="display:none;
        background: <?= $pendingTotal > 0 ? 'rgba(46,204,113,0.10)' : 'rgba(46,204,113,0.06)' ?>;
        border: 1px solid rgba(46,204,113,0.25);
        border-left: 3px solid #2ecc71;
        border-radius: 6px;
        padding: 0.5rem 0.85rem;
        margin-bottom: 0.8rem;
        display: flex;
        align-items: center;
        gap: 0.6rem;
        flex-wrap: wrap;
        font-size: 0.82rem;">
    <span style="font-size:1.05rem;"><?= $pendingTotal > 0 ? '🔔' : '✨' ?></span>
    <span style="color:rgba(255,255,255,0.85);flex:1;min-width:160px;">
        <?php if ($pendingTotal > 0) { ?>
            <strong style="color:#2ecc71;">Máš <?= $pendingTotal ?> čekající<?= $pendingTotal === 1 ? ' lead' : ($pendingTotal < 5 ? ' leady' : 'ch leadů') ?></strong>
            v nové pracovní ploše — přijmi je a začni řešit.
        <?php } else { ?>
            <strong style="color:#2ecc71;">Nová pracovní plocha</strong> — zaměřené řešení leadů (žádné čekající).
        <?php } ?>
    </span>
    <a href="<?= crm_h(crm_url('/oz/queue')) ?>"
       style="color:#fff;font-weight:600;text-decoration:none;font-size:0.85rem;
              padding:0.3rem 0.85rem;background:#2ecc71;
              border-radius:5px;white-space:nowrap;">
        <?= $pendingTotal > 0 ? 'Otevřít queue (' . $pendingTotal . ')' : 'Otevřít →' ?>
    </a>
    <button type="button" onclick="ozHideNewUiBanner()"
            style="background:transparent;border:none;
                   color:rgba(255,255,255,0.4);padding:0.1rem 0.35rem;
                   cursor:pointer;font-size:1rem;line-height:1;"
            title="Skrýt na 7 dní">
        ×
    </button>
</div>
<script>
(function () {
    var KEY = 'oz_newui_banner_hidden_until';
    var until = parseInt(localStorage.getItem(KEY) || '0', 10);
    if (Date.now() > until) {
        document.getElementById('oz-newui-banner').style.display = 'flex';
    }
    window.ozHideNewUiBanner = function () {
        // skrýt na 7 dní
        localStorage.setItem(KEY, String(Date.now() + 7 * 24 * 60 * 60 * 1000));
        document.getElementById('oz-newui-banner').style.display = 'none';
    };
})();
</script>

<section class="card">
<div class="oz-layout">

<?php
// Levý sidebar je viditelný pokud má nějaký obsah (pending NEBO renewal).
$leftSidebarEmpty = $pendingByCaller === [] && ($renewalsForOz ?? []) === [];
?>
<!-- ══════════════════════════════════════════════════════
     LEVÝ SIDEBAR — ODSTRANĚN (Krok 5a refactor)
     Pending leady & renewals nyní žijí na /oz/queue (čistší UX).
     JS funkce ozTogglePop/ozRenewalTogglePop v oz_leads.js zůstávají
     jako dead code — odstraní se v Kroku 5f (cleanup).
══════════════════════════════════════════════════════ -->
<?php
// Zachováváme proměnné pro zpětnou kompatibilitu (pokud by je něco jinde četlo).
$renewalsForOz = $renewalsForOz ?? [];
?>

<?php /* Data pro JS popovery — ODSTRANĚNO (Krok 5F refactor)
   _ozRenewals a _ozPending byly pouze pro pending sidebar popovery,
   které byly odstraněny v Kroku 5a. Pending leads + renewal alerts
   nyní žijí na /oz/queue (čistší UX, vlastní views). */ ?>

<!-- ══════════════════════════════════════════════════════
     HLAVNÍ OBSAH
══════════════════════════════════════════════════════ -->
<div class="oz-main-content">

    <!-- ── Notifikace schůzek (neodkliknuté) ── -->
    <?php if ($meetingNotifications !== []) { ?>
    <div class="oz-meeting-alerts">
        <?php foreach ($meetingNotifications as $m) {
            $ts    = strtotime((string)$m['schuzka_at']);
            $diff  = $ts - time();
            $urgent = $diff < 3600;
        ?>
        <div class="oz-meeting-alert <?= $urgent ? 'oz-meeting-alert--urgent' : '' ?>">
            <span class="oz-meeting-alert__icon"><?= $urgent ? '🚨' : '🗓️' ?></span>
            <div class="oz-meeting-alert__text">
                <?= $urgent ? '<strong>SCHŮZKA PRÁVĚ TEĎ:</strong>' : '<strong>Schůzka:</strong>' ?>
                <?= crm_h((string)($m['firma'] ?? '—')) ?>
                —
                <?php
                $label = ozMeetingLabel((string)$m['schuzka_at']);
                echo crm_h($label);
                ?>
            </div>
            <form method="post" action="<?= crm_h(crm_url('/oz/acknowledge-meeting')) ?>">
                <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                <input type="hidden" name="contact_id" value="<?= (int)$m['id'] ?>">
                <button type="submit" class="oz-ack-btn <?= $urgent ? 'oz-ack-btn--urgent' : '' ?>">
                    ✓ Beru na vědomí
                </button>
            </form>
        </div>
        <?php } ?>
    </div>
    <?php } ?>

    <!-- ── Flash zpráva ── -->
    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info"><?= crm_h($flash) ?></p>
    <?php } ?>

    <!-- ══ HEADER BLOK (závody + topbar + výkon) — vizuálně oddělený od pracovního prostoru ══ -->
    <div class="oz-header-block">

    <!-- ── Šněčí závody OZ — sbalené default (Krok 5a refactor)
         OZ to potřebuje vidět 1× za den, ne pořád. Když rozbalí, JS
         (oz_race.js / fetch /oz/race.json) pokračuje v běhu.
    ── -->
    <details class="oz-race-collapse" style="margin-bottom:0.85rem;">
        <summary style="list-style:none;cursor:pointer;user-select:none;
                        padding:0.5rem 0.85rem;
                        background:rgba(255,255,255,0.03);
                        border:1px solid rgba(255,255,255,0.08);
                        border-radius:8px;
                        font-size:0.85rem;color:var(--muted);
                        display:flex;justify-content:space-between;align-items:center;
                        transition:background 0.15s;">
            <span>🐌 Šněčí závody OZ — <?= crm_h($czechMonths[$curMonth] . ' ' . $curYear) ?></span>
            <span style="font-size:0.72rem;color:var(--muted);">▾ rozbalit</span>
        </summary>
        <div class="oz-race-wrap" style="margin-top:0.5rem;">
            <div class="snail-race" id="oz-race-box">
                <div id="oz-race-inner"><div class="snail-loading">Načítám závod…</div></div>
            </div>
        </div>
    </details>
    <style>
        .oz-race-collapse > summary::-webkit-details-marker { display:none; }
        .oz-race-collapse > summary:hover { background:rgba(255,255,255,0.06) !important; color:var(--text); }
        .oz-race-collapse[open] > summary span:last-child { display:none; }
    </style>

    <!-- ── Topbar — JEN tab counts (Krok 5b refactor)
         Měsíční smluv/BMSL byly přesunuty na /oz dashboard (Výkon & milníky).
         Tady je smysluplné jen "kolik mám v jakém tabu" — kontextová info
         pro pracovní plochu. Sjednocený neutrální styling, žádný rainbow.
    ── -->
    <?php
    $boActiveCount = (int)($tabCounts['bo_predano'] ?? 0) + (int)($tabCounts['bo_vraceno'] ?? 0);
    $tabStats = [
        ['lbl' => 'Nové',      'val' => (int)($tabCounts['nove']     ?? 0)],
        ['lbl' => 'Nabídky',   'val' => (int)($tabCounts['nabidka']  ?? 0)],
        ['lbl' => 'Schůzky',   'val' => (int)($tabCounts['schuzka']  ?? 0)],
        ['lbl' => 'Callbacky', 'val' => (int)($tabCounts['callback'] ?? 0)],
        ['lbl' => 'Šance',     'val' => (int)($tabCounts['sance']    ?? 0)],
        ['lbl' => 'U BO',      'val' => $boActiveCount],
    ];
    ?>
    <div class="oz-topbar oz-topbar--clean">
        <?php foreach ($tabStats as $st) {
            $isZero = $st['val'] === 0;
        ?>
        <div class="oz-stat <?= $isZero ? 'oz-stat--zero' : '' ?>">
            <span class="oz-stat__val"><?= (int) $st['val'] ?></span>
            <span class="oz-stat__lbl"><?= crm_h($st['lbl']) ?></span>
        </div>
        <?php } ?>
        <div class="oz-topbar-actions">
            <a href="<?= crm_h(crm_url('/oz')) ?>"             class="btn btn-secondary btn-sm">📊 Moje kvóty</a>
            <a href="<?= crm_h(crm_url('/oz/performance')) ?>" class="btn btn-secondary btn-sm">🏅 Výkon týmu</a>
        </div>
    </div>

    <!-- ── Info hint — performance widget byl přesunut na /oz dashboard.
         Link Moje kvóty je v topbaru nahoře (vedle Výkon týmu).
         Tento hint se schová na 14 dní po prvním zavření (LocalStorage). ── -->
    <div id="oz-perf-moved-hint" style="display:none;margin-bottom:0.85rem;
                font-size:0.75rem;color:var(--muted);
                padding:0.35rem 0.7rem;background:rgba(255,255,255,0.02);
                border-radius:6px;border:1px dashed rgba(255,255,255,0.08);
                display:flex;align-items:center;gap:0.5rem;">
        <span>💡 Osobní milníky + týmové stage cíle se přesunuly do <strong>Moje kvóty</strong> (tlačítko nahoře vpravo).</span>
        <button type="button" onclick="ozHidePerfMovedHint()"
                style="margin-left:auto;background:transparent;border:1px solid rgba(255,255,255,0.12);
                       color:var(--muted);padding:0.15rem 0.45rem;border-radius:4px;
                       cursor:pointer;font-size:0.7rem;">×</button>
    </div>
    <script>
    (function () {
        var KEY   = 'oz_perf_moved_hint_hidden_until';
        var hint  = document.getElementById('oz-perf-moved-hint');
        var until = parseInt(localStorage.getItem(KEY) || '0', 10);
        if (!hint) return;
        if (Date.now() > until) hint.style.display = 'flex';
        window.ozHidePerfMovedHint = function () {
            localStorage.setItem(KEY, String(Date.now() + 14 * 24 * 60 * 60 * 1000));
            hint.style.display = 'none';
        };
    })();
    </script>

    </div><!-- /.oz-header-block ══════════════════════════════════════ -->

    <!-- Vizuální oddělovač mezi headerem a pracovní plochou -->
    <div class="oz-section-divider">
        <span>📋 Moje pracovní plocha</span>
    </div>

    <!-- ── Taby (s hierarchií super-tabů: 📅 V plánu, 🏢 Back-office) ── -->
    <?php
    // Atomické taby (každý má vlastní URL ?tab=…). Sub-taby super-tabu mají 'parent'.
    /** @var array<string,array{key:string,label:string,cls:string,title?:string,parent?:string}> $atomicTabsRaw */
    $atomicTabsRaw = [
        'nove'       => ['key' => 'nove',       'label' => '📋 Rozpracované',         'cls' => 'oz-tab--nove', 'title' => 'Přijaté leady, kterým ještě nepadlo rozhodnutí (po akci se přesunou do svého tabu)'],
        'nabidka'    => ['key' => 'nabidka',    'label' => '📨 Odeslané nabídky',     'cls' => 'oz-tab--nabidka'],
        'callback'   => ['key' => 'callback',   'label' => '📞 Callbacky',            'cls' => 'oz-tab--callback', 'parent' => 'plan'],
        'schuzka'    => ['key' => 'schuzka',    'label' => '📅 Schůzky',              'cls' => 'oz-tab--schuzka',  'parent' => 'plan'],
        'sance'      => ['key' => 'sance',      'label' => '💡 Šance',                'cls' => 'oz-tab--sance',    'title' => 'Zákazník chce, ale chybí mu administrativní doklady'],
        'bo_predano' => ['key' => 'bo_predano', 'label' => '📤 Předáno BO',           'cls' => 'oz-tab--bo',       'parent' => 'bo', 'title' => 'U Back-office ke zpracování'],
        'bo_vraceno' => ['key' => 'bo_vraceno', 'label' => '↩ Vráceno z BO',          'cls' => 'oz-tab--bo',       'parent' => 'bo', 'title' => 'BO vrátil — OZ má doplnit'],
        'dokonceno'  => ['key' => 'dokonceno',  'label' => '✅ Dokončeno',            'cls' => 'oz-tab--dokonceno','parent' => 'bo', 'title' => 'Uzavřené smlouvy — filtr podle měsíce'],
        'reklamace'  => ['key' => 'reklamace',  'label' => '⚠ Chybné leady',          'cls' => 'oz-tab--reklamace'],
        'nezajem'    => ['key' => 'nezajem',    'label' => '✗ Nezájem',               'cls' => 'oz-tab--nezajem'],
    ];

    // Super-taby — hover dropdown se sub-taby
    /** @var array<string,array{key:string,label:string,cls:string,children:list<string>}> $superTabsDef */
    $superTabsDef = [
        'plan' => [
            'key'      => 'plan',
            'label'    => '📅 V plánu',
            'cls'      => 'oz-tab--super-plan',
            'children' => ['callback', 'schuzka'],
        ],
        'bo'   => [
            'key'      => 'bo',
            'label'    => '🏢 Back-office',
            'cls'      => 'oz-tab--super-bo',
            'children' => ['bo_predano', 'bo_vraceno', 'dokonceno'],
        ],
    ];

    // Defaultní top-level pořadí (atomické top-level taby + super-taby)
    $defaultTopOrder = ['nove', 'nabidka', 'plan', 'sance', 'bo', 'reklamace', 'nezajem'];

    // Aplikuj per-user pořadí (filtrované — ignoruj sub-tab klíče i neznámé)
    $allowedTop = $defaultTopOrder;
    $topOrder = [];
    if (!empty($tabOrder)) {
        foreach ($tabOrder as $tk) {
            if (in_array($tk, $allowedTop, true) && !in_array($tk, $topOrder, true)) {
                $topOrder[] = $tk;
            }
        }
    }
    foreach ($defaultTopOrder as $tk) {
        if (!in_array($tk, $topOrder, true)) { $topOrder[] = $tk; }
    }

    // Pořadí dětí v super-tabu (per-user, default je v $superTabsDef)
    $subOrders = [];
    foreach ($superTabsDef as $sk => $sg) {
        $userOrder = $subTabOrder[$sk] ?? [];
        $resolved  = [];
        foreach ($userOrder as $sub) {
            if (in_array($sub, $sg['children'], true) && !in_array($sub, $resolved, true)) {
                $resolved[] = $sub;
            }
        }
        // Doplň chybějící (např. když přibyl nový sub-tab)
        foreach ($sg['children'] as $sub) {
            if (!in_array($sub, $resolved, true)) { $resolved[] = $sub; }
        }
        $subOrders[$sk] = $resolved;
    }

    $hiddenSet = array_flip($hiddenTabs ?? []);
    ?>
    <div class="oz-tabs" id="oz-tabs-container">
        <?php foreach ($topOrder as $topKey) {
            // ── Super-tab (Plán, BO) ──────────────────────────────────
            if (isset($superTabsDef[$topKey])) {
                $sg          = $superTabsDef[$topKey];
                $children    = $subOrders[$topKey] ?? $sg['children'];
                $visibleKids = array_values(array_filter($children, static fn($c) => !isset($hiddenSet[$c])));
                if (empty($visibleKids)) { continue; /* celý super-tab schovaný */ }

                $anyActive  = in_array($tab, $visibleKids, true);
                $superCount = (int) ($tabCounts[$topKey] ?? 0);
            ?>
            <span class="oz-tab-wrap oz-tab-wrap--super"
                  data-tab-key="<?= crm_h($topKey) ?>"
                  data-super="1"
                  draggable="true"
                  style="display:inline-flex;align-items:center;position:relative;cursor:grab;">
                <span class="oz-tab oz-tab--super <?= crm_h($sg['cls']) ?> <?= $anyActive ? 'oz-tab--active' : '' ?>"
                      tabindex="0"
                      onclick="ozSuperTabToggle(event, '<?= crm_h($topKey) ?>')"
                      title="Najetím myší (nebo klikem) zobrazí sub-záložky">
                    <?= crm_h($sg['label']) ?>
                    <span class="oz-badge"><?= $superCount ?></span>
                    <span class="oz-supertab-arrow">▾</span>
                </span>
                <div class="oz-supertab-dropdown" data-super-dropdown="<?= crm_h($topKey) ?>">
                    <?php foreach ($visibleKids as $childKey) {
                        if (!isset($atomicTabsRaw[$childKey])) { continue; }
                        $childDef    = $atomicTabsRaw[$childKey];
                        $isChildAct  = $tab === $childKey;
                        $childCount  = (int) ($tabCounts[$childKey] ?? 0);
                    ?>
                    <span class="oz-tab-wrap oz-tab-wrap--child"
                          data-tab-key="<?= crm_h($childKey) ?>"
                          data-parent="<?= crm_h($topKey) ?>"
                          draggable="true"
                          style="display:flex;align-items:center;position:relative;cursor:grab;">
                        <a href="<?= crm_h(crm_url('/oz/leads?tab=' . $childKey)) ?>"
                           class="oz-tab oz-tab--child <?= crm_h($childDef['cls']) ?> <?= $isChildAct ? 'oz-tab--active' : '' ?>"
                           <?php if (!empty($childDef['title'])) { ?>title="<?= crm_h($childDef['title']) ?>"<?php } ?>>
                            <?= crm_h($childDef['label']) ?>
                            <span class="oz-badge"><?= $childCount ?></span>
                        </a>
                        <form method="post" action="<?= crm_h(crm_url('/oz/tab/hide')) ?>"
                              style="display:inline;margin-left:-0.2rem;"
                              title="Skrýt tuto sub-záložku">
                            <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                            <input type="hidden" name="tab_key" value="<?= crm_h($childKey) ?>">
                            <input type="hidden" name="current_tab" value="<?= crm_h($tab) ?>">
                            <button type="submit"
                                    title="Skrýt tuto sub-záložku"
                                    style="background:transparent;color:var(--muted);
                                           border:0;cursor:pointer;font-size:0.65rem;padding:0.1rem 0.3rem;
                                           opacity:0.5;transition:opacity 0.15s;"
                                    onmouseover="this.style.opacity='1';this.style.color='#e74c3c';"
                                    onmouseout="this.style.opacity='0.5';this.style.color='';">×</button>
                        </form>
                    </span>
                    <?php } ?>
                </div>
                <form method="post" action="<?= crm_h(crm_url('/oz/tab/hide')) ?>"
                      style="display:inline;margin-left:-0.4rem;"
                      title="Skrýt celý super-tab (všechny sub-záložky)">
                    <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                    <input type="hidden" name="tab_key" value="<?= crm_h($topKey) ?>">
                    <input type="hidden" name="current_tab" value="<?= crm_h($tab) ?>">
                    <button type="submit"
                            title="Skrýt celý super-tab — schová všechny sub-záložky najednou"
                            style="background:transparent;color:var(--muted);
                                   border:0;cursor:pointer;font-size:0.7rem;padding:0.1rem 0.35rem;
                                   opacity:0.5;transition:opacity 0.15s;"
                            onmouseover="this.style.opacity='1';this.style.color='#e74c3c';"
                            onmouseout="this.style.opacity='0.5';this.style.color='';">×</button>
                </form>
            </span>
            <?php
                continue;
            }

            // ── Atomický top-level tab ──────────────────────────────
            if (!isset($atomicTabsRaw[$topKey])) { continue; }
            $tabDef = $atomicTabsRaw[$topKey];
            // Sub-tab nesmí být na top-level (defenzivní pojistka)
            if (isset($tabDef['parent'])) { continue; }
            if (isset($hiddenSet[$topKey])) { continue; }
            $isActive = $tab === $topKey;
            $count    = (int) ($tabCounts[$topKey] ?? 0);
            $canHide  = $topKey !== 'nove';
        ?>
        <span class="oz-tab-wrap"
              data-tab-key="<?= crm_h($topKey) ?>"
              draggable="true"
              style="display:inline-flex;align-items:center;position:relative;cursor:grab;">
            <a href="<?= crm_h(crm_url('/oz/leads?tab=' . $topKey)) ?>"
               class="oz-tab <?= crm_h($tabDef['cls']) ?> <?= $isActive ? 'oz-tab--active' : '' ?>"
               <?php if (!empty($tabDef['title'])) { ?>title="<?= crm_h($tabDef['title']) ?>"<?php } ?>>
                <?= crm_h($tabDef['label']) ?>
                <span class="oz-badge"><?= $count ?></span>
            </a>
            <?php if ($canHide) { ?>
            <form method="post" action="<?= crm_h(crm_url('/oz/tab/hide')) ?>"
                  style="display:inline;margin-left:-0.4rem;"
                  title="Skrýt záložku">
                <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                <input type="hidden" name="tab_key" value="<?= crm_h($topKey) ?>">
                <input type="hidden" name="current_tab" value="<?= crm_h($tab) ?>">
                <button type="submit"
                        title="Skrýt tuto záložku z menu (později ji můžete připnout zpět)"
                        style="background:transparent;color:var(--muted);
                               border:0;cursor:pointer;font-size:0.7rem;padding:0.1rem 0.35rem;
                               opacity:0.5;transition:opacity 0.15s;"
                        onmouseover="this.style.opacity='1';this.style.color='#e74c3c';"
                        onmouseout="this.style.opacity='0.5';this.style.color='';">
                    ×
                </button>
            </form>
            <?php } ?>
        </span>
        <?php } ?>
    </div>

    <!-- ── Filtr měsíců — jen pro tab Dokončené ── -->
    <?php if ($tab === 'dokonceno') {
        $czechMonthsLong = ['','Leden','Únor','Březen','Duben','Květen','Červen',
                            'Červenec','Srpen','Září','Říjen','Listopad','Prosinec'];
        $curY = (int) ($doneYear  ?? date('Y'));
        $curM = (int) ($doneMonth ?? date('n'));
    ?>
    <div style="margin:0 0 1rem;padding:0.55rem 0.85rem;
                background:rgba(46,204,113,0.06);border:1px solid rgba(46,204,113,0.25);
                border-left:4px solid #2ecc71;border-radius:8px;
                display:flex;align-items:center;gap:0.7rem;flex-wrap:wrap;">
        <span style="color:#2ecc71;font-size:0.78rem;font-weight:700;">
            ✅ Dokončené smlouvy:
        </span>
        <form method="get" action="<?= crm_h(crm_url('/oz/leads')) ?>"
              style="display:flex;gap:0.4rem;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="tab" value="dokonceno">
            <select name="m"
                    style="background:var(--bg);color:var(--text);
                           border:1px solid rgba(255,255,255,0.15);border-radius:5px;
                           padding:0.25rem 0.5rem;font-size:0.78rem;font-family:inherit;cursor:pointer;">
                <?php for ($mi = 1; $mi <= 12; $mi++) { ?>
                    <option value="<?= $mi ?>" <?= $mi === $curM ? 'selected' : '' ?>>
                        <?= crm_h($czechMonthsLong[$mi]) ?>
                    </option>
                <?php } ?>
            </select>
            <select name="y"
                    style="background:var(--bg);color:var(--text);
                           border:1px solid rgba(255,255,255,0.15);border-radius:5px;
                           padding:0.25rem 0.5rem;font-size:0.78rem;font-family:inherit;cursor:pointer;">
                <?php
                $thisYear = (int) date('Y');
                for ($yi = $thisYear - 2; $yi <= $thisYear + 1; $yi++) { ?>
                    <option value="<?= $yi ?>" <?= $yi === $curY ? 'selected' : '' ?>><?= $yi ?></option>
                <?php } ?>
            </select>
            <button type="submit"
                    style="padding:0.25rem 0.7rem;font-size:0.75rem;font-weight:700;
                           background:#2ecc71;color:#fff;border:0;border-radius:5px;cursor:pointer;">
                Zobrazit
            </button>
        </form>
        <span style="margin-left:auto;color:var(--muted);font-size:0.72rem;font-style:italic;">
            <?= (int) ($tabCounts['dokonceno'] ?? 0) ?> smluv v <?= crm_h($czechMonthsLong[$curM] . ' ' . $curY) ?>
        </span>
    </div>
    <?php } ?>

    <!-- ── Mini panel skrytých záložek (jen když má OZ nějakou skrytou) ── -->
    <?php
    // Spočítej které super-taby jsou KOMPLETNĚ schované (všechny děti v hidden)
    // — ty zobrazíme jako jeden "+ Super-tab" tlačítko (jeden klik vrátí všechny děti).
    $fullyHiddenSupers = [];
    $childrenOfFullySuper = [];
    foreach ($superTabsDef as $sk => $sg) {
        $allHidden = true;
        foreach ($sg['children'] as $sub) {
            if (!isset($hiddenSet[$sub])) { $allHidden = false; break; }
        }
        if ($allHidden && !empty($sg['children'])) {
            $fullyHiddenSupers[] = $sk;
            foreach ($sg['children'] as $sub) {
                $childrenOfFullySuper[$sub] = true;
            }
        }
    }
    // Zobrazení jen pokud je něco skryté
    if (!empty($hiddenTabs) || !empty($fullyHiddenSupers)) { ?>
    <div style="margin:-0.4rem 0 0.8rem;padding:0;
                display:flex;align-items:center;gap:0.4rem;flex-wrap:wrap;font-size:0.7rem;">
        <span style="color:var(--muted);font-size:0.65rem;">📂 Skryté:</span>

        <?php // Nejprve zobraz fully-hidden super-taby (1 tlačítko = vrátí celý super-tab)
        foreach ($fullyHiddenSupers as $sk) {
            $sg     = $superTabsDef[$sk];
            $count  = (int) ($tabCounts[$sk] ?? 0);
        ?>
        <form method="post" action="<?= crm_h(crm_url('/oz/tab/show')) ?>" style="display:inline;">
            <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
            <input type="hidden" name="tab_key" value="<?= crm_h($sk) ?>">
            <input type="hidden" name="current_tab" value="<?= crm_h($tab) ?>">
            <button type="submit"
                    title="Připnout celý super-tab zpět (vrátí všechny sub-záložky)"
                    style="background:transparent;color:var(--muted);
                           border:1px solid rgba(155,89,182,0.35);border-radius:4px;
                           padding:0.1rem 0.4rem;font-size:0.68rem;cursor:pointer;
                           font-family:inherit;
                           transition:color 0.15s, border-color 0.15s;"
                    onmouseover="this.style.color='var(--text)';this.style.borderColor='rgba(155,89,182,0.7)';"
                    onmouseout="this.style.color='var(--muted)';this.style.borderColor='rgba(155,89,182,0.35)';">
                + <?= crm_h($sg['label']) ?> <span style="opacity:0.7;">(super)</span> (<?= $count ?>)
            </button>
        </form>
        <?php } ?>

        <?php // Pak zobraz jednotlivé skryté atomické taby (mimo ty z fully-hidden super-tabů)
        foreach ($hiddenTabs as $hKey) {
            if (isset($childrenOfFullySuper[$hKey])) { continue; /* už zobrazeno jako super-tab */ }
            if (!isset($atomicTabsRaw[$hKey])) { continue; }
            $tabDef = $atomicTabsRaw[$hKey];
            $count  = (int) ($tabCounts[$hKey] ?? 0);
            $isChild = isset($tabDef['parent']);
        ?>
        <form method="post" action="<?= crm_h(crm_url('/oz/tab/show')) ?>" style="display:inline;">
            <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
            <input type="hidden" name="tab_key" value="<?= crm_h($hKey) ?>">
            <input type="hidden" name="current_tab" value="<?= crm_h($tab) ?>">
            <button type="submit"
                    title="<?= $isChild ? 'Připnout sub-záložku zpět do super-tabu' : 'Připnout záložku zpět do menu' ?>"
                    style="background:transparent;color:var(--muted);
                           border:1px solid rgba(255,255,255,0.1);border-radius:4px;
                           padding:0.1rem 0.4rem;font-size:0.68rem;cursor:pointer;
                           font-family:inherit;
                           transition:color 0.15s, border-color 0.15s;"
                    onmouseover="this.style.color='var(--text)';this.style.borderColor='rgba(255,255,255,0.3)';"
                    onmouseout="this.style.color='var(--muted)';this.style.borderColor='rgba(255,255,255,0.1)';">
                + <?= crm_h($tabDef['label']) ?><?php if ($isChild) { ?><span style="opacity:0.6;font-size:0.6rem;"> ↳ <?= crm_h($superTabsDef[$tabDef['parent']]['label']) ?></span><?php } ?> (<?= $count ?>)
            </button>
        </form>
        <?php } ?>
    </div>
    <?php } ?>

    <!-- ── Vždy viditelný hint pro Rozpracované tab — vysvětlivka co tady patří ── -->
    <?php if ($tab === 'nove') { ?>
    <div style="margin-bottom:0.7rem;font-size:0.74rem;color:var(--muted);
                padding:0.4rem 0.75rem;background:rgba(255,255,255,0.02);
                border-radius:6px;border-left:3px solid rgba(230,176,32,0.4);">
        💡 <strong style="color:rgba(230,176,32,0.9);">Rozpracované</strong> = leady přijaté z queue, kterým ještě
        nepadlo rozhodnutí. Po kliknutí na akci (Nabídka, Schůzka, Callback, Šance, …) se přesunou do svého tabu.
    </div>
    <?php } ?>

    <!-- ── Kontakty ── -->
    <?php if ($contacts === []) { ?>
        <p class="oz-empty">
            <?= match($tab) {
                'nove'       => '✅ Žádné rozpracované leady — vše má jasné rozhodnutí.',
                'obvolano'   => 'Žádné kontakty ve stavu Obvoláno.',
                'nabidka'    => 'Žádné odeslané nabídky.',
                'schuzka'    => 'Žádné naplánované schůzky.',
                'callback'   => 'Žádné callbacky.',
                'sance'      => 'Žádné šance — zákazníci, kterým chybí jen administrativa.',
                'smlouva'    => 'Žádné podepsané smlouvy — zatím! 💪',
                'bo_predano' => 'Žádné kontakty předané do BO.',
                'bo_vraceno' => '✅ Žádné kontakty vrácené od BO — všechno v pořádku.',
                'dokonceno'  => 'Žádné dokončené smlouvy v tomto měsíci.',
                'nezajem'    => 'Žádné kontakty označené jako nezájem.',
                'reklamace'  => '✅ Žádné chybné leady — skvělá práce! 👍',
                default      => 'Žádné kontakty.',
            } ?>
        </p>
    <?php } ?>

    <?php $cardIndex = 0; $totalCards = count($contacts); foreach ($contacts as $c) {
        $cardIndex++;
        $ozStav    = (string) ($c['oz_stav'] ?? 'NOVE');
        $cId       = (int) $c['id'];
        $panelCbId = 'cb-panel-' . $cId;
        $panelSaId = 'sa-panel-' . $cId;
        $formId    = 'ozf-' . $cId;
        $startedAt = (string) ($c['started_at'] ?? '');
        $schuzkaAt = (string) ($c['oz_schuzka_at'] ?? '');
        $contactNotes = $notesByContact[$cId] ?? [];
        $contactActions = $actionsByContact[$cId] ?? [];
        $contactOfferedServices = $offeredServicesByContact[$cId] ?? [];

        $ozBmsl        = isset($c['oz_bmsl']) && $c['oz_bmsl'] !== null ? (int) $c['oz_bmsl'] : null;
        $ozSmlouvaDate = (string) ($c['oz_smlouva_date'] ?? '');
        $ozNabidkaId      = (string) ($c['oz_nabidka_id'] ?? '');
        $ozInstInternet   = (int) ($c['oz_install_internet'] ?? 0);
        $ozInstAdresyRaw  = (string) ($c['oz_install_adresy'] ?? '');
        /** @var list<array{ulice:string,mesto:string,psc:string,byt:string}> $ozInstAdresy */
        $ozInstAdresy     = ($ozInstAdresyRaw !== '' && $ozInstAdresyRaw !== 'null')
            ? (array) json_decode($ozInstAdresyRaw, true)
            : [];

        $stavClass = match($ozStav) {
            'NOVE'                   => 'oz-contact--nove',
            'ZPRACOVAVA', 'OBVOLANO' => 'oz-contact--obvolano',
            'NABIDKA'                => 'oz-contact--nabidka',
            'SCHUZKA'                => 'oz-contact--schuzka',
            'CALLBACK'               => 'oz-contact--callback',
            'SANCE'                  => 'oz-contact--sance',
            'SMLOUVA'                => 'oz-contact--smlouva',
            'BO_PREDANO'             => 'oz-contact--bo-predano',
            'BO_VRACENO'             => 'oz-contact--bo-vraceno',
            'UZAVRENO'               => 'oz-contact--uzavreno',
            'REKLAMACE'              => 'oz-contact--nekvalitni',
            'NEZAJEM', 'NERELEVANTNI'=> 'oz-contact--nezajem',
            default                  => '',
        };
        $stavLabel = match($ozStav) {
            'NOVE'         => '● Nový',
            'ZPRACOVAVA'   => '📞 Zpracovávám',
            'OBVOLANO'     => '📞 Obvoláno',
            'NABIDKA'      => '📨 Nabídka odeslána',
            'SCHUZKA'      => '📅 Schůzka',
            'CALLBACK'     => '📞 Callback',
            'SANCE'        => '💡 Šance',
            'SMLOUVA'      => '🏆 Smlouva',
            'BO_PREDANO'   => '📤 Předáno BO',
            'BO_VRACENO'   => '↩️ Vráceno od BO',
            'UZAVRENO'     => '✅ Dokončeno',
            'REKLAMACE'    => '⚠ Chybný lead',
            'NEZAJEM'      => '✗ Nezájem',
            'NERELEVANTNI' => '✗ Nerelevantní',
            default        => $ozStav,
        };
        $badgeClass = match($ozStav) {
            'BO_PREDANO'   => 'badge--bo_predano',
            'BO_VRACENO'   => 'badge--bo_vraceno',
            'REKLAMACE'    => 'badge--reklamace',
            'NERELEVANTNI' => 'badge--nezajem',
            default        => 'badge--' . strtolower($ozStav),
        };
    ?>
    <div class="oz-contact <?= $stavClass ?>" id="c-<?= $cId ?>">

        <!-- Pořadové číslo karty (orientace v dlouhém seznamu) -->
        <span class="oz-card-index" title="Pořadí v této záložce">
            #<?= $cardIndex ?><span style="opacity:0.5;font-weight:400;">/<?= $totalCards ?></span>
        </span>

        <!-- Horní lišta -->
        <div class="oz-contact__head">
            <span class="oz-contact__firm"><?= crm_h((string)($c['firma'] ?? '—')) ?></span>
            <?php if (!in_array($ozStav, ['REKLAMACE', 'BO_PREDANO', 'UZAVRENO', 'NEZAJEM', 'NERELEVANTNI'], true)) { ?>
            <button type="button" class="oz-btn oz-btn--reklamace oz-btn--chybny-lead"
                    onclick="ozToggleReklamacePanel(<?= $cId ?>)"
                    title="Lead byl špatně navolán — navolávačka dostane upozornění, lead se nepočítá jako placený">
                ⚠ Chybný lead
            </button>
            <?php } ?>
            <div class="oz-contact__meta">
                <?php if (!empty($c['region'])) { ?>
                    <span class="oz-contact__region"><?= crm_h(crm_region_label((string)$c['region'])) ?></span>
                <?php } ?>
                <?php if (!empty($c['datum_volani'])) { ?>
                    <span class="oz-contact__when">
                        navolán <?= crm_h(ozElapsed((string)$c['datum_volani'])) ?>
                        · <?= crm_h(date('d.m. H:i', strtotime((string)$c['datum_volani']))) ?>
                    </span>
                <?php } ?>
                <?php if ($startedAt !== '') { ?>
                    <span style="font-size:0.67rem;color:var(--muted);"
                          title="Začal zpracovávat: <?= crm_h(date('d.m. H:i', strtotime($startedAt))) ?>">
                        ▶ <?= crm_h(ozElapsed($startedAt)) ?>
                    </span>
                <?php } ?>
                <span class="oz-contact__stav-badge <?= $badgeClass ?>"><?= $stavLabel ?></span>
                <?php
                // Časová plaketka: "v této záložce: před X" (jak dlouho je karta v aktuálním stavu)
                $stavChangedAt = (string) ($c['oz_stav_changed_at'] ?? '');
                if ($stavChangedAt !== '') { ?>
                    <span style="font-size:0.66rem;color:var(--muted);
                                 background:rgba(255,255,255,0.04);
                                 border:1px solid rgba(255,255,255,0.08);border-radius:10px;
                                 padding:0.08rem 0.45rem;white-space:nowrap;"
                          title="V této záložce od: <?= crm_h(date('d.m.Y H:i', strtotime($stavChangedAt))) ?>">
                        🕒 v této záložce: <?= crm_h(ozElapsed($stavChangedAt)) ?>
                    </span>
                <?php } ?>
                <?php if ((int)($c['flagged'] ?? 0)) { ?>
                    <span class="oz-contact__stav-badge badge--nekvalitni"
                          title="<?= crm_h((string)$c['flag_reason']) ?>">⚠ Chybný lead</span>
                <?php } ?>
                <?php // Mini badge ID nabídky — jen pro BO stavy (full panel je skrytý)
                      if ($ozNabidkaId !== '' && in_array($ozStav, ['BO_PREDANO','BO_VPRACI','BO_VRACENO','UZAVRENO','SMLOUVA'], true)) { ?>
                    <span style="font-size:0.66rem;color:var(--oz-callback);
                                 background:rgba(26,188,156,0.12);
                                 border:1px solid rgba(26,188,156,0.3);border-radius:4px;
                                 padding:0.1rem 0.45rem;font-family:monospace;font-weight:700;"
                          title="ID nabídky z OT">
                        🔖 <?= crm_h($ozNabidkaId) ?>
                    </span>
                <?php } ?>
            </div>
        </div>
        <!-- Panel chybný lead (hned pod hlavičkou) -->
        <?php if (!in_array($ozStav, ['REKLAMACE', 'BO_PREDANO', 'UZAVRENO', 'NEZAJEM', 'NERELEVANTNI'], true)) { ?>
        <div class="oz-reklamace-panel" id="rekl-panel-<?= $cId ?>">
            <div class="oz-reklamace-panel__title">⚠ Nahlásit chybný lead</div>
            <div class="oz-reklamace-panel__hint">
                Zákazník říká, že o kontaktování nežádal nebo se ho to netýká.
                Navolávačka dostane upozornění, lead se nepočítá jako placený.
            </div>
            <form method="post" action="<?= crm_h(crm_url('/oz/reklamace')) ?>"
                  id="rekl-form-<?= $cId ?>"
                  style="display:flex;flex-direction:column;gap:0.35rem;">
                <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                <input type="hidden" name="contact_id" value="<?= $cId ?>">
                <input type="hidden" name="tab" value="<?= crm_h($tab) ?>">
                <textarea name="reklamace_reason"
                          id="rekl-note-<?= $cId ?>"
                          class="oz-note-input"
                          style="border-color:rgba(243,156,18,0.4);"
                          placeholder="Důvod — co zákazník říkal…"
                          required></textarea>
                <div style="display:flex;gap:0.5rem;align-items:center;">
                    <!-- 2-step inline potvrzení (jako u call screenu).
                         První klik = button změní text na warning + 5s reset timer.
                         Druhý klik do 5s = submit formuláře. -->
                    <button type="button" class="oz-btn oz-btn--reklamace"
                            id="rekl-submit-<?= $cId ?>"
                            data-orig-text="⚠ Odeslat navolávačce"
                            style="font-size:0.78rem;padding:0.35rem 0.9rem;"
                            onclick="ozLeadsConfirmReklamace(this, <?= $cId ?>)">
                        ⚠ Odeslat navolávačce
                    </button>
                    <button type="button" class="oz-btn oz-btn--save"
                            onclick="ozToggleReklamacePanel(<?= $cId ?>)">
                        Zrušit
                    </button>
                </div>
            </form>
        </div>
        <?php } ?>

        <!-- Schůzka čas (pokud nastavena) -->
        <?php if ($schuzkaAt !== '') { ?>
        <div style="padding: 0 0.9rem 0.3rem;">
            <span class="oz-schuzka-info">
                📅 Schůzka: <strong><?= crm_h(ozMeetingLabel($schuzkaAt)) ?></strong>
            </span>
        </div>
        <?php } ?>

        <?php // Callback badge — zobrazit JEN ve stavu CALLBACK, plus rozlišit budoucí/dnes/prošlý
              if ($ozStav === 'CALLBACK') {
                  $cbAt = (string) ($c['oz_callback_at'] ?? '');
                  if ($cbAt !== '') {
                      $cbTs    = strtotime($cbAt);
                      $cbToday = $cbTs !== false && date('Y-m-d', $cbTs) === date('Y-m-d');
                      $cbPast  = $cbTs !== false && $cbTs < time() && !$cbToday;
                      if ($cbPast) {
                          $cbColor = '#e67e22'; $cbBg = 'rgba(230,126,34,0.10)'; $cbBorder = 'rgba(230,126,34,0.35)';
                          $cbLabel = '⚠ Callback prošlý:';
                      } elseif ($cbToday) {
                          $cbColor = '#f1c40f'; $cbBg = 'rgba(241,196,15,0.10)'; $cbBorder = 'rgba(241,196,15,0.4)';
                          $cbLabel = '📞 Callback DNES:';
                      } else {
                          $cbColor = 'var(--oz-callback)'; $cbBg = 'rgba(26,188,156,0.09)'; $cbBorder = 'rgba(26,188,156,0.25)';
                          $cbLabel = '📞 Naplánovaný callback:';
                      }
        ?>
        <div style="padding: 0 0.9rem 0.3rem;">
            <span style="display:inline-flex;align-items:center;gap:0.3rem;font-size:0.72rem;
                         color:<?= $cbColor ?>;background:<?= $cbBg ?>;
                         border:1px solid <?= $cbBorder ?>;
                         border-radius:5px;padding:0.15rem 0.55rem;">
                <?= crm_h($cbLabel) ?>
                <strong><?= crm_h(date('d.m.Y H:i', $cbTs)) ?></strong>
            </span>
        </div>
        <?php } else { ?>
        <div style="padding: 0 0.9rem 0.3rem;">
            <span style="display:inline-flex;align-items:center;gap:0.3rem;font-size:0.72rem;color:var(--oz-callback);background:rgba(26,188,156,0.09);border-radius:5px;padding:0.15rem 0.5rem;font-style:italic;">
                🕓 Callback bez konkrétního termínu
            </span>
        </div>
        <?php }
              }
        ?>
        <?php // BMSL badge zobrazujeme ve všech stavech, kde už BMSL existuje v DB:
              //   SMLOUVA, BO_PREDANO, BO_VPRACI (BO škrtá checkboxy), BO_VRACENO, UZAVRENO. ?>
        <?php if (in_array($ozStav, ['SMLOUVA', 'BO_PREDANO', 'BO_VPRACI', 'BO_VRACENO', 'UZAVRENO'], true) && $ozBmsl !== null) { ?>
        <div style="padding: 0 0.9rem 0.35rem; display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
            <span class="oz-bmsl-badge">
                💰 BMSL: <?= number_format($ozBmsl, 0, ',', ' ') ?> Kč
            </span>
            <?php if ($ozSmlouvaDate !== '') { ?>
            <span style="display:inline-flex;align-items:center;gap:0.3rem;font-size:0.72rem;color:var(--muted);">
                📄 Podpis: <?= crm_h(date('d.m.Y', strtotime($ozSmlouvaDate))) ?>
            </span>
            <?php } ?>
            <?php /* ID nabídky je teď v samostatném pásku pod hlavičkou (vždy viditelný). */ ?>
        </div>
        <?php } ?>
        <?php /* Panel "Instalace internetu" odebrán — informace nyní spravuje
                 univerzální blok "Nabídnuté služby" (typ Internet, identifier = adresa). */ ?>

        <!-- Tělo: info + poznámky -->
        <div class="oz-contact__body">

            <!-- Kontaktní data -->
            <div class="oz-contact__col">

                <!-- View režim — read-only s tlačítkem na edit (nahoře vlevo) -->
                <div id="oz-info-view-<?= $cId ?>">
                    <div style="display:flex;align-items:center;justify-content:flex-start;margin-bottom:0.4rem;">
                        <button type="button"
                                onclick="ozContactEditToggle(<?= $cId ?>)"
                                title="Upravit údaje kontaktu (firma, tel, email, IČO, adresa)"
                                style="background:rgba(52,152,219,0.1);color:#3498db;
                                       border:1px solid rgba(52,152,219,0.35);border-radius:4px;
                                       padding:0.2rem 0.55rem;font-size:0.72rem;cursor:pointer;
                                       font-family:inherit;font-weight:600;">
                            ✏️ Upravit kontakt
                        </button>
                    </div>
                    <div class="oz-info-row">
                        <span class="oz-info-label">Tel.</span>
                        <span class="oz-info-val" style="font-family:monospace;">
                            <?= !empty($c['telefon']) ? crm_h((string)$c['telefon']) : '<span style="color:var(--muted);">—</span>' ?>
                        </span>
                    </div>
                    <div class="oz-info-row">
                        <span class="oz-info-label">E-mail</span>
                        <span class="oz-info-val">
                            <?= !empty($c['email']) ? crm_h((string)$c['email']) : '<span style="color:var(--muted);">—</span>' ?>
                        </span>
                    </div>
                    <div class="oz-info-row">
                        <span class="oz-info-label">IČO</span>
                        <span class="oz-info-val">
                            <?php if (!empty($c['ico'])) {
                                $icoNorm = crm_normalize_ico((string)$c['ico']);
                            ?>
                                <span style="font-family:monospace;"><?= crm_h($icoNorm) ?></span>
                                <a href="<?= crm_h('https://ares.gov.cz/ekonomicke-subjekty?ico=' . urlencode($icoNorm)) ?>"
                                   target="_blank" rel="noopener noreferrer"
                                   title="Ověřit firmu v ARES"
                                   style="margin-left:0.4rem;color:#3498db;text-decoration:none;
                                          font-size:0.7rem;padding:0.05rem 0.3rem;border-radius:3px;
                                          background:rgba(52,152,219,0.1);
                                          border:1px solid rgba(52,152,219,0.25);">
                                    🔗 ARES
                                </a>
                            <?php } else { ?>
                                <span style="color:var(--muted);">—</span>
                            <?php } ?>
                        </span>
                    </div>
                    <div class="oz-info-row">
                        <span class="oz-info-label">Adresa</span>
                        <span class="oz-info-val">
                            <?= !empty($c['adresa']) ? crm_h((string)$c['adresa']) : '<span style="color:var(--muted);">—</span>' ?>
                        </span>
                    </div>
                    <?php if (!empty($c['operator'])) {
                        $opRaw = strtoupper(trim((string)$c['operator']));
                    ?>
                    <div class="oz-info-row">
                        <span class="oz-info-label">Operátor</span>
                        <span class="oz-info-val" style="color:var(--muted);font-size:0.78rem;letter-spacing:0.04em;">
                            <?= crm_h($opRaw) ?>
                        </span>
                    </div>
                    <?php } ?>
                </div>

                <!-- Edit režim — formulář (skrytý do kliknutí na ✏️) -->
                <div id="oz-info-edit-<?= $cId ?>" style="display:none;">
                    <form method="post" action="<?= crm_h(crm_url('/oz/contact/edit')) ?>"
                          style="display:flex;flex-direction:column;gap:0.35rem;">
                        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                        <input type="hidden" name="contact_id" value="<?= $cId ?>">
                        <input type="hidden" name="tab" value="<?= crm_h($tab) ?>">

                        <div style="font-size:0.65rem;color:#3498db;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;">
                            ✏️ Úprava údajů kontaktu
                        </div>

                        <label style="display:flex;flex-direction:column;gap:0.15rem;font-size:0.7rem;color:var(--muted);">
                            Firma <span style="color:#e74c3c;">*</span>
                            <input type="text" name="firma" required maxlength="200"
                                   id="oz-edit-firma-<?= $cId ?>"
                                   value="<?= crm_h((string)($c['firma'] ?? '')) ?>"
                                   style="background:var(--bg);color:var(--text);
                                          border:1px solid rgba(255,255,255,0.15);border-radius:4px;
                                          padding:0.25rem 0.45rem;font-size:0.82rem;font-family:inherit;">
                        </label>
                        <label style="display:flex;flex-direction:column;gap:0.15rem;font-size:0.7rem;color:var(--muted);">
                            Telefon
                            <input type="text" name="telefon" maxlength="50"
                                   value="<?= crm_h((string)($c['telefon'] ?? '')) ?>"
                                   style="background:var(--bg);color:var(--text);
                                          border:1px solid rgba(255,255,255,0.15);border-radius:4px;
                                          padding:0.25rem 0.45rem;font-size:0.82rem;font-family:monospace;">
                        </label>
                        <label style="display:flex;flex-direction:column;gap:0.15rem;font-size:0.7rem;color:var(--muted);">
                            E-mail
                            <input type="email" name="email" maxlength="200"
                                   value="<?= crm_h((string)($c['email'] ?? '')) ?>"
                                   style="background:var(--bg);color:var(--text);
                                          border:1px solid rgba(255,255,255,0.15);border-radius:4px;
                                          padding:0.25rem 0.45rem;font-size:0.82rem;font-family:inherit;">
                        </label>
                        <label style="display:flex;flex-direction:column;gap:0.15rem;font-size:0.7rem;color:var(--muted);">
                            IČO
                            <div style="display:flex;gap:0.35rem;align-items:center;">
                                <input type="text" name="ico" maxlength="20"
                                       id="oz-edit-ico-<?= $cId ?>"
                                       value="<?= crm_h(crm_normalize_ico((string)($c['ico'] ?? ''))) ?>"
                                       style="flex:1 1 auto;background:var(--bg);color:var(--text);
                                              border:1px solid rgba(255,255,255,0.15);border-radius:4px;
                                              padding:0.25rem 0.45rem;font-size:0.82rem;font-family:monospace;">
                                <button type="button"
                                        onclick="ozAresLookup(<?= $cId ?>)"
                                        title="Načíst název firmy a adresu z ARES podle IČO"
                                        style="flex:0 0 auto;padding:0.25rem 0.6rem;font-size:0.72rem;
                                               background:rgba(52,152,219,0.15);color:#3498db;
                                               border:1px solid rgba(52,152,219,0.4);border-radius:4px;
                                               cursor:pointer;font-family:inherit;font-weight:700;">
                                    📋 z ARES
                                </button>
                            </div>
                            <span id="oz-ares-status-<?= $cId ?>" style="font-size:0.65rem;color:var(--muted);min-height:0.9rem;"></span>
                        </label>
                        <label style="display:flex;flex-direction:column;gap:0.15rem;font-size:0.7rem;color:var(--muted);">
                            Adresa (sídlo firmy)
                            <input type="text" name="adresa" maxlength="300"
                                   id="oz-edit-adresa-<?= $cId ?>"
                                   value="<?= crm_h((string)($c['adresa'] ?? '')) ?>"
                                   placeholder="Ulice č.p., město, PSČ"
                                   style="background:var(--bg);color:var(--text);
                                          border:1px solid rgba(255,255,255,0.15);border-radius:4px;
                                          padding:0.25rem 0.45rem;font-size:0.82rem;font-family:inherit;">
                        </label>

                        <div style="font-size:0.65rem;color:var(--muted);font-style:italic;margin-top:0.15rem;">
                            ℹ Operátor a kraj řídí admin.<br>
                            Instalační adresa internetu se píše do Pracovního deníku.
                        </div>

                        <div style="display:flex;gap:0.35rem;justify-content:flex-end;margin-top:0.2rem;">
                            <button type="button"
                                    onclick="ozContactEditToggle(<?= $cId ?>)"
                                    style="padding:0.3rem 0.7rem;font-size:0.75rem;
                                           background:transparent;color:var(--muted);
                                           border:1px solid rgba(255,255,255,0.15);border-radius:4px;cursor:pointer;">
                                Zrušit
                            </button>
                            <button type="submit"
                                    style="padding:0.3rem 0.85rem;font-size:0.75rem;font-weight:700;
                                           background:#3498db;color:#fff;
                                           border:0;border-radius:4px;cursor:pointer;">
                                💾 Uložit
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Poznámky: navolávačka + historie OZ -->
            <div class="oz-contact__col">

                <!-- Poznámka navolávačky -->
                <div class="oz-caller-block">
                    <div class="oz-caller-block__who">
                        📞 Navolal/a: <?= crm_h((string)($c['caller_name'] ?? '—')) ?>
                    </div>
                    <?php if (!empty($c['caller_poznamka'])) { ?>
                        <div class="oz-caller-block__note"><?= crm_h((string)$c['caller_poznamka']) ?></div>
                    <?php } else { ?>
                        <div class="oz-caller-block__empty">Bez poznámky navolávačky.</div>
                    <?php } ?>
                </div>


                <!-- Historie poznámek OZ -->
                <?php if ($contactNotes !== []) { ?>
                <div class="oz-notes-history">
                    <div class="oz-notes-history__label">Moje poznámky</div>
                    <?php foreach ($contactNotes as $note) { ?>
                    <div class="oz-note-item">
                        <div class="oz-note-item__time"><?= crm_h(date('d.m.Y H:i', strtotime((string)$note['created_at']))) ?></div>
                        <div class="oz-note-item__text"><?= crm_h((string)$note['note']) ?></div>
                    </div>
                    <?php } ?>
                </div>
                <?php } ?>


                <?php
                /* ══ Nabídnuté služby — DEAKTIVOVÁNO ════════════════════════════
                   Nahrazeno: pole "ID nabídky z OT" + "Pracovní deník".
                   Když bude potřeba vrátit, změňte níže "if (false)" na "if (true)"
                   a stejně tak v JS bloku (vyhledat "DEAKTIVOVÁNO" níže).
                   Backend, routy a DB tabulky zůstávají funkční. */
                if (false) { ?>
                <!-- ══ Nabídnuté služby (Fáze 2 — CRUD) ══ -->
                <div style="margin-top:0.5rem;padding:0.55rem 0.7rem;
                            background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);
                            border-left:3px solid #3498db;border-radius:0 6px 6px 0;
                            display:flex;flex-direction:column;gap:0.4rem;">

                    <!-- Hlavička panelu -->
                    <div style="display:flex;align-items:center;gap:0.5rem;font-size:0.8rem;font-weight:700;">
                        <span>📦 Nabídnuté služby</span>
                        <span style="font-size:0.65rem;color:var(--muted);font-weight:400;">
                            (<?= count($contactOfferedServices) ?>)
                        </span>
                        <button type="button"
                                onclick="ozOfferedToggleForm(<?= $cId ?>)"
                                style="margin-left:auto;font-size:0.7rem;padding:0.2rem 0.6rem;
                                       background:rgba(52,152,219,0.15);color:#3498db;
                                       border:1px solid rgba(52,152,219,0.4);border-radius:5px;
                                       cursor:pointer;font-weight:700;">
                            + Přidat službu
                        </button>
                    </div>

                    <!-- Seznam stávajících služeb -->
                    <?php if ($contactOfferedServices === []) { ?>
                        <div style="font-size:0.75rem;color:var(--muted);font-style:italic;">
                            Zatím žádné nabídnuté služby.
                        </div>
                    <?php } else { ?>
                        <?php foreach ($contactOfferedServices as $entry) {
                            $svc          = $entry['service'];
                            $svcItems     = $entry['items'];
                            $svcId        = (int) $svc['id'];
                            $svcType      = (string) $svc['service_type'];
                            $svcLabel     = (string) $svc['service_label'];
                            $modemLabel   = (string) ($svc['modem_label'] ?? '');
                            $svcPrice     = $svc['price_monthly'] !== null ? (float) $svc['price_monthly'] : null;
                            $svcNote      = (string) ($svc['note'] ?? '');
                            $typeIcon     = match ($svcType) {
                                'mobil'    => '📱',
                                'internet' => '🌐',
                                'tv'       => '📺',
                                'data'     => '📡',
                                default    => '•',
                            };
                        ?>
                        <div id="oz-svc-view-<?= $svcId ?>"
                             style="background:rgba(0,0,0,0.18);border-radius:5px;padding:0.4rem 0.6rem;
                                    display:flex;flex-direction:column;gap:0.3rem;">
                            <!-- Hlavička služby -->
                            <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;font-size:0.78rem;">
                                <span style="font-size:1rem;line-height:1;"><?= $typeIcon ?></span>
                                <strong style="color:var(--text);"><?= crm_h($svcLabel) ?></strong>
                                <?php if ($svcItems !== []) { ?>
                                    <span style="color:var(--muted);font-size:0.7rem;">×<?= count($svcItems) ?></span>
                                <?php } ?>
                                <?php if ($svcPrice !== null) { ?>
                                    <span style="margin-left:auto;color:#2ecc71;font-weight:600;">
                                        <?= number_format($svcPrice, 0, ',', ' ') ?> Kč
                                    </span>
                                <?php } ?>
                                <!-- Akční tlačítka: Upravit + Smazat -->
                                <div style="display:flex;gap:0.25rem;<?= $svcPrice === null ? 'margin-left:auto;' : '' ?>">
                                    <button type="button"
                                            onclick="ozOfferedEditToggle(<?= $svcId ?>)"
                                            title="Upravit službu"
                                            style="background:transparent;color:#3498db;border:1px solid rgba(52,152,219,0.3);
                                                   border-radius:4px;width:22px;height:22px;line-height:1;
                                                   cursor:pointer;font-size:0.75rem;padding:0;">✏️</button>
                                    <form method="post" action="<?= crm_h(crm_url('/oz/offered-service/delete')) ?>"
                                          style="display:inline;"
                                          onsubmit="return confirm('Smazat tuto službu? Tato akce je nevratná.');">
                                        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                                        <input type="hidden" name="service_id" value="<?= $svcId ?>">
                                        <input type="hidden" name="tab" value="<?= crm_h($tab) ?>">
                                        <button type="submit"
                                                title="Smazat službu"
                                                style="background:transparent;color:#e74c3c;border:1px solid rgba(231,76,60,0.3);
                                                       border-radius:4px;width:22px;height:22px;line-height:1;
                                                       cursor:pointer;font-size:0.85rem;padding:0;">×</button>
                                    </form>
                                </div>
                            </div>

                            <?php if ($modemLabel !== '') { ?>
                            <div style="font-size:0.7rem;color:var(--muted);">
                                Modem: <span style="color:var(--text);"><?= crm_h($modemLabel) ?></span>
                            </div>
                            <?php } ?>

                            <?php if ($svcNote !== '') { ?>
                            <div style="font-size:0.72rem;color:var(--text);font-style:italic;
                                        background:rgba(255,255,255,0.04);padding:0.2rem 0.4rem;border-radius:4px;">
                                <?= crm_h($svcNote) ?>
                            </div>
                            <?php } ?>

                            <?php if ($svcItems !== []) { ?>
                            <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:0.2rem;">
                                <?php
                                /** @var int $itemId */
                                foreach ($svcItems as $item) {
                                    $itemId     = (int) ($item['id'] ?? 0);
                                    $hasOku     = !empty($item['oku_code']);
                                    $okuCode    = (string) ($item['oku_code'] ?? '');
                                    $identifier = (string) ($item['identifier'] ?? '');
                                ?>
                                <li style="display:flex;flex-direction:column;gap:0.2rem;font-size:0.74rem;
                                           padding:0.15rem 0.3rem;border-radius:3px;
                                           background:rgba(255,255,255,0.02);">
                                    <div style="display:flex;align-items:center;gap:0.4rem;">
                                        <span style="color:var(--accent);font-family:monospace;"><?= crm_h($identifier) ?></span>
                                        <?php if ($hasOku) { ?>
                                            <button type="button"
                                                    onclick="ozOkuToggle(<?= $itemId ?>)"
                                                    title="Klikněte pro úpravu OKU"
                                                    style="margin-left:auto;font-size:0.66rem;
                                                           background:rgba(241,196,15,0.15);color:#f1c40f;
                                                           padding:0.1rem 0.4rem;border-radius:3px;font-weight:700;
                                                           border:1px solid rgba(241,196,15,0.3);
                                                           cursor:pointer;font-family:inherit;">
                                                ✓ OKU: <?= crm_h($okuCode) ?>
                                            </button>
                                        <?php } else { ?>
                                            <button type="button"
                                                    onclick="ozOkuToggle(<?= $itemId ?>)"
                                                    title="Klikněte pro doplnění OKU kódu"
                                                    style="margin-left:auto;font-size:0.66rem;color:var(--muted);
                                                           background:transparent;border:1px dashed rgba(255,255,255,0.15);
                                                           border-radius:3px;padding:0.1rem 0.4rem;
                                                           cursor:pointer;font-family:inherit;">
                                            + doplnit OKU
                                            </button>
                                        <?php } ?>
                                    </div>
                                    <!-- Inline OKU form (skrytý) -->
                                    <div id="oz-oku-form-<?= $itemId ?>" style="display:none;
                                         padding:0.3rem 0.45rem;background:rgba(241,196,15,0.06);
                                         border:1px solid rgba(241,196,15,0.25);border-radius:4px;">
                                        <form method="post" action="<?= crm_h(crm_url('/oz/offered-service-item/oku')) ?>"
                                              style="display:flex;gap:0.3rem;align-items:center;flex-wrap:wrap;">
                                            <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                                            <input type="hidden" name="item_id" value="<?= $itemId ?>">
                                            <input type="hidden" name="tab" value="<?= crm_h($tab) ?>">
                                            <input type="text" name="oku_code"
                                                   value="<?= crm_h($okuCode) ?>"
                                                   maxlength="64"
                                                   placeholder="OKU kód (přenosový kód)"
                                                   style="flex:1 1 160px;background:var(--bg);color:var(--text);
                                                          border:1px solid rgba(241,196,15,0.4);border-radius:4px;
                                                          padding:0.2rem 0.4rem;font-size:0.72rem;
                                                          font-family:monospace;">
                                            <button type="submit"
                                                    style="font-size:0.7rem;padding:0.2rem 0.55rem;
                                                           background:#f1c40f;color:#000;
                                                           border:0;border-radius:4px;cursor:pointer;font-weight:700;">
                                                Uložit
                                            </button>
                                            <button type="button"
                                                    onclick="ozOkuToggle(<?= $itemId ?>)"
                                                    style="font-size:0.7rem;padding:0.2rem 0.55rem;
                                                           background:transparent;color:var(--muted);
                                                           border:1px solid rgba(255,255,255,0.15);border-radius:4px;cursor:pointer;">
                                                Zrušit
                                            </button>
                                        </form>
                                    </div>
                                </li>
                                <?php } ?>
                            </ul>
                            <?php } ?>
                        </div>

                        <!-- Inline EDIT form pro tuto službu (skrytý do kliknutí na ✏️) -->
                        <div id="oz-svc-edit-<?= $svcId ?>" style="display:none;
                             background:rgba(52,152,219,0.06);border:1px solid rgba(52,152,219,0.3);
                             border-radius:5px;padding:0.55rem 0.7rem;flex-direction:column;gap:0.4rem;">
                            <form method="post" action="<?= crm_h(crm_url('/oz/offered-service/edit')) ?>"
                                  style="display:flex;flex-direction:column;gap:0.4rem;">
                                <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                                <input type="hidden" name="service_id" value="<?= $svcId ?>">
                                <input type="hidden" name="tab" value="<?= crm_h($tab) ?>">

                                <div style="font-size:0.68rem;color:var(--muted);font-style:italic;">
                                    ✏️ Úprava služby — telefonní čísla / adresy upravíte smazáním a založením nové služby (zachová OKU kódy u zbývajících).
                                </div>

                                <!-- Řádek 1: Typ + Tarif -->
                                <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
                                    <select name="service_type" required
                                            id="oz-offered-edit-type-<?= $svcId ?>"
                                            onchange="ozOfferedEditTypeChanged(<?= $svcId ?>)"
                                            style="flex:0 0 140px;background:var(--bg);color:var(--text);
                                                   border:1px solid rgba(255,255,255,0.15);border-radius:5px;
                                                   padding:0.3rem 0.5rem;font-size:0.78rem;font-family:inherit;">
                                        <?php foreach (crm_offered_services_catalog() as $tKey => $tDef) { ?>
                                            <option value="<?= crm_h($tKey) ?>" <?= $tKey === $svcType ? 'selected' : '' ?>>
                                                <?= crm_h($tDef['label']) ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                    <select name="service_label" required
                                            id="oz-offered-edit-label-<?= $svcId ?>"
                                            data-current-label="<?= crm_h($svcLabel) ?>"
                                            style="flex:1 1 220px;min-width:200px;background:var(--bg);color:var(--text);
                                                   border:1px solid rgba(255,255,255,0.15);border-radius:5px;
                                                   padding:0.3rem 0.5rem;font-size:0.78rem;font-family:inherit;">
                                        <option value="<?= crm_h($svcLabel) ?>" selected><?= crm_h($svcLabel) ?></option>
                                    </select>
                                </div>

                                <!-- Řádek 2: Modem (jen pro internet) + Cena -->
                                <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
                                    <div id="oz-offered-edit-modem-wrap-<?= $svcId ?>"
                                         style="display:<?= $svcType === 'internet' ? 'block' : 'none' ?>;flex:1 1 220px;min-width:200px;">
                                        <select name="modem_label"
                                                id="oz-offered-edit-modem-<?= $svcId ?>"
                                                style="width:100%;background:var(--bg);color:var(--text);
                                                       border:1px solid rgba(255,255,255,0.15);border-radius:5px;
                                                       padding:0.3rem 0.5rem;font-size:0.78rem;font-family:inherit;">
                                            <option value="" <?= $modemLabel === '' ? 'selected' : '' ?>>— Bez modemu —</option>
                                            <?php foreach (crm_offered_services_modems() as $modemOpt) { ?>
                                                <option value="<?= crm_h($modemOpt) ?>" <?= $modemOpt === $modemLabel ? 'selected' : '' ?>>
                                                    <?= crm_h($modemOpt) ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <input type="number" name="price_monthly"
                                           value="<?= $svcPrice !== null ? (int) $svcPrice : '' ?>"
                                           min="0" max="999999" step="1"
                                           placeholder="Cena (Kč)"
                                           style="flex:0 0 110px;background:var(--bg);color:var(--text);
                                                  border:1px solid rgba(255,255,255,0.15);border-radius:5px;
                                                  padding:0.3rem 0.5rem;font-size:0.78rem;font-family:inherit;">
                                </div>

                                <!-- Poznámka -->
                                <textarea name="note"
                                          placeholder="Poznámka (volitelné)"
                                          rows="1"
                                          style="background:var(--bg);color:var(--text);
                                                 border:1px solid rgba(255,255,255,0.15);border-radius:5px;
                                                 padding:0.3rem 0.5rem;font-size:0.78rem;font-family:inherit;
                                                 resize:vertical;"><?= crm_h($svcNote) ?></textarea>

                                <!-- Tlačítka -->
                                <div style="display:flex;gap:0.4rem;justify-content:flex-end;flex-wrap:wrap;">
                                    <button type="button"
                                            onclick="ozOfferedEditToggle(<?= $svcId ?>)"
                                            style="padding:0.3rem 0.7rem;font-size:0.75rem;
                                                   background:transparent;color:var(--muted);
                                                   border:1px solid rgba(255,255,255,0.15);border-radius:5px;cursor:pointer;">
                                        Zrušit
                                    </button>
                                    <button type="submit"
                                            style="padding:0.3rem 0.9rem;font-size:0.75rem;font-weight:700;
                                                   background:#3498db;color:#fff;
                                                   border:0;border-radius:5px;cursor:pointer;">
                                        💾 Uložit změny
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php } ?>
                    <?php } ?>

                    <!-- Inline formulář pro přidání nové služby (skrytý do kliknutí na "+ Přidat službu") -->
                    <div id="oz-offered-form-<?= $cId ?>" style="display:none;
                         background:rgba(52,152,219,0.06);border:1px solid rgba(52,152,219,0.3);
                         border-radius:5px;padding:0.55rem 0.7rem;margin-top:0.2rem;
                         flex-direction:column;gap:0.4rem;">
                        <form method="post" action="<?= crm_h(crm_url('/oz/offered-service/add')) ?>"
                              style="display:flex;flex-direction:column;gap:0.4rem;">
                            <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                            <input type="hidden" name="contact_id" value="<?= $cId ?>">
                            <input type="hidden" name="tab" value="<?= crm_h($tab) ?>">

                            <!-- Řádek 1: Typ + Tarif -->
                            <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
                                <select name="service_type" required
                                        id="oz-offered-type-<?= $cId ?>"
                                        onchange="ozOfferedTypeChanged(<?= $cId ?>)"
                                        style="flex:0 0 140px;background:var(--bg);color:var(--text);
                                               border:1px solid rgba(255,255,255,0.15);border-radius:5px;
                                               padding:0.3rem 0.5rem;font-size:0.78rem;font-family:inherit;">
                                    <option value="">— Typ —</option>
                                    <?php foreach (crm_offered_services_catalog() as $tKey => $tDef) { ?>
                                        <option value="<?= crm_h($tKey) ?>"><?= crm_h($tDef['label']) ?></option>
                                    <?php } ?>
                                </select>
                                <select name="service_label" required disabled
                                        id="oz-offered-label-<?= $cId ?>"
                                        style="flex:1 1 220px;min-width:200px;background:var(--bg);color:var(--text);
                                               border:1px solid rgba(255,255,255,0.15);border-radius:5px;
                                               padding:0.3rem 0.5rem;font-size:0.78rem;font-family:inherit;">
                                    <option value="">— Nejprve vyberte typ —</option>
                                </select>
                            </div>

                            <!-- Řádek 2: Modem (jen pro internet) + Cena -->
                            <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
                                <div id="oz-offered-modem-wrap-<?= $cId ?>"
                                     style="display:none;flex:1 1 220px;min-width:200px;">
                                    <select name="modem_label"
                                            id="oz-offered-modem-<?= $cId ?>"
                                            style="width:100%;background:var(--bg);color:var(--text);
                                                   border:1px solid rgba(255,255,255,0.15);border-radius:5px;
                                                   padding:0.3rem 0.5rem;font-size:0.78rem;font-family:inherit;">
                                        <option value="">— Bez modemu —</option>
                                        <?php foreach (crm_offered_services_modems() as $modemOpt) { ?>
                                            <option value="<?= crm_h($modemOpt) ?>"><?= crm_h($modemOpt) ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <input type="number" name="price_monthly"
                                       min="0" max="999999" step="1"
                                       placeholder="Cena (Kč)"
                                       style="flex:0 0 110px;background:var(--bg);color:var(--text);
                                              border:1px solid rgba(255,255,255,0.15);border-radius:5px;
                                              padding:0.3rem 0.5rem;font-size:0.78rem;font-family:inherit;">
                            </div>

                            <!-- Identifikátory (jeden na řádek = jedna položka) -->
                            <textarea name="identifiers"
                                      placeholder="Tel. číslo / adresa — jedno na řádek (např. 731170559)"
                                      rows="2"
                                      style="background:var(--bg);color:var(--text);
                                             border:1px solid rgba(255,255,255,0.15);border-radius:5px;
                                             padding:0.3rem 0.5rem;font-size:0.78rem;font-family:inherit;
                                             resize:vertical;"></textarea>

                            <!-- Volitelná poznámka -->
                            <textarea name="note"
                                      placeholder="Poznámka (volitelné)"
                                      rows="1"
                                      style="background:var(--bg);color:var(--text);
                                             border:1px solid rgba(255,255,255,0.15);border-radius:5px;
                                             padding:0.3rem 0.5rem;font-size:0.78rem;font-family:inherit;
                                             resize:vertical;"></textarea>

                            <!-- Tlačítka -->
                            <div style="display:flex;gap:0.4rem;justify-content:flex-end;flex-wrap:wrap;">
                                <button type="button"
                                        onclick="ozOfferedToggleForm(<?= $cId ?>)"
                                        style="padding:0.3rem 0.7rem;font-size:0.75rem;
                                               background:transparent;color:var(--muted);
                                               border:1px solid rgba(255,255,255,0.15);border-radius:5px;cursor:pointer;">
                                    Zrušit
                                </button>
                                <button type="submit"
                                        style="padding:0.3rem 0.9rem;font-size:0.75rem;font-weight:700;
                                               background:#3498db;color:#fff;
                                               border:0;border-radius:5px;cursor:pointer;">
                                    💾 Uložit službu
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- ══ /Nabídnuté služby ══ -->
                <?php } /* end if(false) — Nabídnuté služby DEAKTIVOVÁNO */ ?>

            </div>
        </div>

        <!-- ══ ID nabídky z OT — velký panel jen v aktivních stavech (NOVE/NABIDKA/SCHUZKA/CALLBACK/SANCE).
              V BO stavech (předáno BO/v práci/vráceno/uzavřeno/smlouva) ho ukáže pouze malý badge v hlavičce. ══ -->
        <?php
        $ozBoStavy = ['BO_PREDANO', 'BO_VPRACI', 'BO_VRACENO', 'UZAVRENO', 'SMLOUVA'];
        $showOfferIdPanel = $ozNabidkaId !== ''
                          && !in_array($ozStav, ['NEZAJEM', 'NERELEVANTNI'], true)
                          && !in_array($ozStav, $ozBoStavy, true);
        if ($showOfferIdPanel) { ?>
        <div style="margin:0 0.9rem 0.35rem;padding:0.4rem 0.7rem;
                    background:rgba(26,188,156,0.07);border:1px solid rgba(26,188,156,0.25);
                    border-left:3px solid var(--oz-callback);border-radius:0 6px 6px 0;
                    display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap;">
            <span style="font-size:0.7rem;font-weight:700;color:var(--oz-callback);
                         text-transform:uppercase;letter-spacing:0.04em;">
                🔖 ID nabídky (OT)
            </span>

            <!-- View režim -->
            <div id="oz-offer-view-<?= $cId ?>" style="display:flex;align-items:center;gap:0.4rem;flex-wrap:wrap;">
                <?php if ($ozNabidkaId !== '') { ?>
                    <span style="background:rgba(26,188,156,0.18);color:var(--oz-callback);
                                 padding:0.15rem 0.6rem;border-radius:4px;
                                 font-family:monospace;font-weight:700;font-size:0.85rem;">
                        <?= crm_h($ozNabidkaId) ?>
                    </span>
                    <button type="button"
                            onclick="ozOfferIdToggle(<?= $cId ?>)"
                            title="Upravit ID nabídky"
                            style="background:transparent;color:var(--oz-callback);
                                   border:1px solid rgba(26,188,156,0.3);border-radius:4px;
                                   width:22px;height:22px;line-height:1;cursor:pointer;
                                   font-size:0.75rem;padding:0;">✏️</button>
                <?php } else { ?>
                    <button type="button"
                            onclick="ozOfferIdToggle(<?= $cId ?>)"
                            title="Přidat ID nabídky"
                            style="background:transparent;color:var(--muted);
                                   border:1px dashed rgba(255,255,255,0.2);border-radius:4px;
                                   padding:0.15rem 0.55rem;font-size:0.72rem;cursor:pointer;
                                   font-family:inherit;">
                        + zadat ID nabídky
                    </button>
                <?php } ?>
            </div>

            <!-- Edit režim -->
            <form id="oz-offer-form-<?= $cId ?>"
                  method="post" action="<?= crm_h(crm_url('/oz/set-offer-id')) ?>"
                  style="display:none;align-items:center;gap:0.35rem;flex-wrap:wrap;">
                <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                <input type="hidden" name="contact_id" value="<?= $cId ?>">
                <input type="hidden" name="tab" value="<?= crm_h($tab) ?>">
                <input type="text" name="offer_id"
                       value="<?= crm_h($ozNabidkaId) ?>"
                       maxlength="80"
                       placeholder="např. 020098"
                       style="background:var(--bg);color:var(--text);
                              border:1px solid rgba(26,188,156,0.4);border-radius:4px;
                              padding:0.2rem 0.45rem;font-size:0.78rem;font-family:monospace;
                              width:160px;">
                <button type="submit"
                        style="font-size:0.72rem;padding:0.2rem 0.6rem;
                               background:var(--oz-callback);color:#000;font-weight:700;
                               border:0;border-radius:4px;cursor:pointer;">
                    Uložit
                </button>
                <button type="button"
                        onclick="ozOfferIdToggle(<?= $cId ?>)"
                        style="font-size:0.72rem;padding:0.2rem 0.6rem;
                               background:transparent;color:var(--muted);
                               border:1px solid rgba(255,255,255,0.15);border-radius:4px;cursor:pointer;">
                    Zrušit
                </button>
            </form>
        </div>
        <?php } ?>
        <!-- ══ /ID nabídky ══ -->

        <!-- ══ BO Progress checkboxy — viditelné když OZ kartu předal BO ══ -->
        <?php
        // OZ vidí progress checklist ve stavech BO_PREDANO, BO_VPRACI, BO_VRACENO, UZAVRENO.
        // Může sám zaškrtnout JEN "Podpis potvrzen" (čeká, až mu zákazník potvrdí).
        if (in_array($ozStav, ['BO_PREDANO', 'BO_VPRACI', 'BO_VRACENO', 'UZAVRENO', 'SMLOUVA'], true)) {
            $cbPriprava = (int) ($c['cb_priprava'] ?? 0);
            $cbDatovka  = (int) ($c['cb_datovka']  ?? 0);
            $cbPodpis   = (int) ($c['cb_podpis']   ?? 0);
            $cbUbotem   = (int) ($c['cb_ubotem']   ?? 0);
            $cbPodpisAt = (string) ($c['cb_podpis_at'] ?? '');
            $isUzavreno = ($ozStav === 'UZAVRENO');
        ?>
        <?php /* Bez hlavičky a bez collapsu — OZ vidí 4 zatrhávačky přímo v jednom řádku.
                 Levý růžový pásek + jemné pozadí drží vizuální identitu BO postupu.
                 Outer wrapper drží rám, vnitřní flex řádek nese checkboxy.            */ ?>
        <div style="margin:0 0.9rem 0.5rem;padding:0.4rem 0.6rem;
                    background:rgba(233,30,140,0.05);border:1px solid rgba(233,30,140,0.22);
                    border-left:3px solid var(--oz-bo);border-radius:0 6px 6px 0;">
            <div class="oz-bo-checks"
                 style="display:flex;flex-wrap:wrap;gap:0.35rem;align-items:stretch;">
                <?php
                // Pořadí kroků BO postupu (synchronizované s backoffice/index.php):
                //   1) Příprava smlouvy   2) Zpracování UBotem
                //   3) Odesláno do datovky 4) Podpis potvrzen
                $rows = [
                    ['priprava_smlouvy', $cbPriprava, '📝 Příprava smlouvy', 'OT a Siebel — BO připravuje',     false],
                    ['ubotem_zpracovano',$cbUbotem,   '🤖 Zpracování UBotem',  'BO zpracoval přes UBota',       false],
                    ['datovka_odeslana', $cbDatovka,  '📨 Odesláno do datovky', 'BO odeslal do datové schránky', false],
                    ['podpis_potvrzen',  $cbPodpis,   '✍ Podpis potvrzen',     'Podpis byl potvrzen — započítá se do BMSL baru', true],
                ];
                foreach ($rows as [$field, $val, $label, $hint, $ozCanCheck]) {
                    $disabled = !$ozCanCheck || $isUzavreno;
                    $isChecked = $val === 1;
                    $rowBg     = $isChecked ? 'rgba(233,30,140,0.14)' : 'rgba(255,255,255,0.03)';
                    $rowBorder = $isChecked ? 'rgba(233,30,140,0.45)' : 'rgba(255,255,255,0.10)';
                    $titleSuffix = ($disabled && !$isUzavreno) ? ' · jen BO může měnit' : '';
                ?>
                <label style="flex:1 1 130px;min-width:130px;display:flex;align-items:center;gap:0.4rem;
                              font-size:0.72rem;line-height:1.2;
                              <?= $disabled ? 'cursor:not-allowed;opacity:0.7;' : 'cursor:pointer;' ?>
                              padding:0.32rem 0.5rem;
                              background:<?= $rowBg ?>;
                              border:1px solid <?= $rowBorder ?>;
                              border-radius:5px;
                              transition:background 0.15s, border-color 0.15s;"
                       title="<?= crm_h($hint . $titleSuffix) ?>"
                       <?= $disabled ? '' : 'onmouseover="this.style.background=\'rgba(233,30,140,0.18)\';this.style.borderColor=\'rgba(233,30,140,0.5)\';" onmouseout="this.style.background=\''.$rowBg.'\';this.style.borderColor=\''.$rowBorder.'\';"' ?>>
                    <input type="checkbox"
                           data-cb-field="<?= crm_h($field) ?>"
                           data-cb-cid="<?= $cId ?>"
                           data-cb-tab="<?= crm_h($tab) ?>"
                           data-cb-role="oz"
                           <?= $isChecked ? 'checked' : '' ?>
                           <?= $disabled ? 'disabled' : '' ?>
                           onchange="ozCheckboxToggle(this)"
                           style="cursor:inherit;width:14px;height:14px;flex-shrink:0;margin:0;
                                  accent-color:#e91e8c;">
                    <span style="<?= $isChecked ? 'color:#fff;font-weight:600;' : 'color:var(--text);' ?>;
                                 white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0;flex:1;"><?= crm_h($label) ?></span>
                </label>
                <?php } ?>
            </div><!-- /.oz-bo-checks -->
            <?php if ($cbPodpis === 1 && $cbPodpisAt !== '') { ?>
                <div style="font-size:0.66rem;color:var(--muted);font-style:italic;
                            border-top:1px dashed rgba(255,255,255,0.07);
                            margin-top:0.35rem;padding-top:0.3rem;">
                    💰 Podpis potvrzen <?= crm_h(ozElapsed($cbPodpisAt)) ?> · BMSL se počítá od této chvíle.
                </div>
            <?php } ?>
        </div><!-- /BO progress wrapper -->
        <?php } ?>
        <!-- ══ /BO Progress checkboxy ══ -->

        <!-- ══ Pracovní deník akcí — pro OZ viditelný v BO_VRACENO a UZAVRENO (historie + případná diskuse) ══ -->
        <?php if (in_array($ozStav, ['BO_VRACENO', 'UZAVRENO'], true)) { ?>
        <div style="margin:0 0.9rem 0.5rem;padding:0.55rem 0.75rem;
                    background:rgba(155,89,182,0.05);border:1px solid rgba(155,89,182,0.2);
                    border-left:3px solid #9b59b6;border-radius:0 6px 6px 0;
                    display:flex;flex-direction:column;gap:0.4rem;">

            <?php
            // Default: prázdný deník otevřený, neprázdný sbalený s náhledem
            $deníkOpen = ($contactActions === []);
            // Náhled nejnovějšího záznamu (pokud existuje)
            $latest        = $contactActions[0] ?? null;
            $latestPreview = '';
            if ($latest !== null) {
                $latestDate    = (string) ($latest['action_date'] ?? '');
                $latestText    = (string) ($latest['action_text'] ?? '');
                $latestDateFmt = $latestDate !== '' ? date('d.m.', strtotime($latestDate)) : '';
                $textShort     = mb_strlen($latestText) > 60 ? mb_substr($latestText, 0, 57) . '…' : $latestText;
                $latestPreview = trim($latestDateFmt . ' ' . $textShort);
            }
            ?>
            <div style="display:flex;align-items:center;gap:0.5rem;font-size:0.8rem;font-weight:700;cursor:pointer;"
                 onclick="ozActionsToggle(<?= $cId ?>)"
                 title="<?= $deníkOpen ? 'Sbalit pracovní deník' : 'Rozbalit pracovní deník' ?>">
                <span>📋 Pracovní deník</span>
                <span style="font-size:0.65rem;color:var(--muted);font-weight:400;">
                    (<?= count($contactActions) ?>)
                </span>
                <?php if ($latestPreview !== '' && !$deníkOpen) { ?>
                    <span style="font-size:0.72rem;font-weight:400;font-style:italic;color:var(--muted);
                                 white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1 1 auto;min-width:0;">
                        · <?= crm_h($latestPreview) ?>
                    </span>
                <?php } else { ?>
                    <span style="font-size:0.65rem;color:var(--muted);font-weight:400;flex:1 1 auto;
                                 text-align:right;<?= $deníkOpen ? '' : 'display:none;' ?>">
                        pro back-office &middot; doplňování postupu zakázky
                    </span>
                <?php } ?>
                <span id="oz-actions-toggle-<?= $cId ?>"
                      style="font-size:0.7rem;color:var(--muted);font-weight:400;
                             background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);
                             border-radius:4px;padding:0.1rem 0.45rem;flex:0 0 auto;">
                    <?= $deníkOpen ? '▲ sbalit' : '▼ rozbalit' ?>
                </span>
            </div>

            <!-- Tělo deníku (form + seznam) — toggle přes hlavičku -->
            <div id="oz-actions-body-<?= $cId ?>"
                 style="display:<?= $deníkOpen ? 'flex' : 'none' ?>;flex-direction:column;gap:0.4rem;">

            <!-- Form pro přidání nového záznamu -->
            <form method="post" action="<?= crm_h(crm_url('/oz/action/add')) ?>"
                  style="display:flex;gap:0.4rem;align-items:flex-start;flex-wrap:wrap;">
                <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                <input type="hidden" name="contact_id" value="<?= $cId ?>">
                <input type="hidden" name="tab" value="<?= crm_h($tab) ?>">
                <input type="date" name="action_date"
                       value="<?= crm_h(date('Y-m-d')) ?>"
                       required
                       style="flex:0 0 140px;background:var(--bg);color:var(--text);
                              border:1px solid rgba(255,255,255,0.15);border-radius:5px;
                              padding:0.3rem 0.5rem;font-size:0.78rem;font-family:inherit;">
                <input type="text" name="action_text"
                       required maxlength="1000"
                       placeholder="Popis úkonu (např. telefonát, schůzka, doplnění OKU…)"
                       style="flex:1 1 320px;min-width:220px;background:var(--bg);color:var(--text);
                              border:1px solid rgba(255,255,255,0.15);border-radius:5px;
                              padding:0.3rem 0.55rem;font-size:0.78rem;font-family:inherit;">
                <button type="submit"
                        style="padding:0.3rem 0.85rem;font-size:0.75rem;font-weight:700;
                               background:#9b59b6;color:#fff;
                               border:0;border-radius:5px;cursor:pointer;">
                    + Přidat
                </button>
            </form>

            <!-- Seznam úkonů (DESC: nejnovější nahoře) -->
            <?php if ($contactActions === []) { ?>
                <div style="font-size:0.74rem;color:var(--muted);font-style:italic;">
                    Zatím žádný úkon. První záznam zapíšete přidáním data + popisu výše.
                </div>
            <?php } else { ?>
                <div style="display:flex;flex-direction:column;gap:0.25rem;">
                <?php foreach ($contactActions as $action) {
                    $actId      = (int) ($action['id'] ?? 0);
                    $actDate    = (string) ($action['action_date'] ?? '');
                    $actText    = (string) ($action['action_text'] ?? '');
                    $actAuthor  = (string) ($action['author_name'] ?? '—');
                    $actRole    = (string) ($action['author_role'] ?? '');
                    $actAuthorId = (int) ($action['author_id'] ?? 0);
                    $actDateFmt = $actDate !== '' ? date('d.m.Y', strtotime($actDate)) : '—';
                    // Vlastní záznamy lze smazat; cizí jen číst (ze sdíleného deníku)
                    $isMine     = $actAuthorId === (int) ($user['id'] ?? 0);
                    // Vizuální odlišení autora podle role
                    $roleIcon   = match ($actRole) {
                        'backoffice'              => '🏢',
                        'obchodak'                => '🛒',
                        'navolavacka'             => '📞',
                        'majitel', 'superadmin'   => '👑',
                        default                   => '👤',
                    };
                    $roleLabel  = match ($actRole) {
                        'backoffice'              => 'BO',
                        'obchodak'                => 'OZ',
                        'navolavacka'             => 'Caller',
                        'majitel'                 => 'Majitel',
                        'superadmin'              => 'Admin',
                        default                   => '',
                    };
                    // Barva záznamu — auto-zápis "Vráceno OZ" zlatý, jinak podle role
                    $isReturned = str_starts_with($actText, '↩ Vráceno OZ');
                    if ($isReturned) {
                        $actBg = 'rgba(241,196,15,0.10)';
                        $actBd = 'rgba(241,196,15,0.40)';
                    } elseif ($actRole === 'backoffice') {
                        $actBg = 'rgba(46,204,113,0.07)';   // zelená
                        $actBd = 'rgba(46,204,113,0.25)';
                    } elseif ($actRole === 'obchodak') {
                        $actBg = 'rgba(52,152,219,0.07)';   // modrá
                        $actBd = 'rgba(52,152,219,0.25)';
                    } else {
                        $actBg = 'rgba(155,89,182,0.07)';   // fialová default
                        $actBd = 'rgba(155,89,182,0.18)';
                    }
                ?>
                <div style="display:flex;align-items:flex-start;gap:0.5rem;font-size:0.78rem;
                            padding:0.3rem 0.55rem;border-radius:4px;
                            background:<?= $actBg ?>;border:1px solid <?= $actBd ?>;">
                    <span style="flex:0 0 90px;color:#9b59b6;font-weight:700;font-family:monospace;font-size:0.74rem;"><?= crm_h($actDateFmt) ?></span>
                    <span style="flex:1 1 auto;color:var(--text);white-space:pre-wrap;"><?= crm_h($actText) ?></span>
                    <span style="flex:0 0 auto;font-size:0.66rem;color:var(--muted);
                                 padding:0.05rem 0.4rem;border-radius:3px;
                                 background:rgba(255,255,255,0.04);"
                          title="Autor: <?= crm_h($actAuthor) ?><?= $roleLabel !== '' ? ' (' . crm_h($roleLabel) . ')' : '' ?>">
                        <?= $roleIcon ?> <?= crm_h($actAuthor) ?>
                    </span>
                    <?php if ($isMine) { ?>
                    <form method="post" action="<?= crm_h(crm_url('/oz/action/delete')) ?>"
                          style="display:inline;"
                          onsubmit="return confirm('Smazat tento záznam?');">
                        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                        <input type="hidden" name="action_id" value="<?= $actId ?>">
                        <input type="hidden" name="tab" value="<?= crm_h($tab) ?>">
                        <button type="submit"
                                title="Smazat svůj záznam"
                                style="background:transparent;color:#e74c3c;
                                       border:1px solid rgba(231,76,60,0.25);border-radius:3px;
                                       width:22px;height:22px;line-height:1;cursor:pointer;
                                       font-size:0.85rem;padding:0;">×</button>
                    </form>
                    <?php } else { ?>
                    <span style="width:22px;height:22px;flex:0 0 auto;" aria-hidden="true"></span>
                    <?php } ?>
                </div>
                <?php } ?>
                </div>
            <?php } ?>
            </div>
            <!-- /tělo deníku -->
        </div>
        <?php } ?>
        <!-- ══ /Pracovní deník ══ -->

        <!-- Akční formulář -->
        <?php
        $showForm    = !in_array($ozStav, ['BO_PREDANO', 'UZAVRENO', 'REKLAMACE', 'NEZAJEM', 'NERELEVANTNI'], true);
        $smlouvaOnly = ($ozStav === 'SMLOUVA');
        ?>
        <?php if ($showForm) { ?>
        <div class="oz-contact__actions">
            <form method="post" action="<?= crm_h(crm_url('/oz/lead-status')) ?>"
                  id="<?= $formId ?>" style="width:100%;">
                <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                <input type="hidden" name="contact_id" value="<?= $cId ?>">
                <input type="hidden" name="tab"        value="<?= crm_h($tab) ?>">
                <input type="hidden" name="oz_stav"    id="stav-<?= $cId ?>" value="">

                <div class="oz-note-label">
                    Poznámka <span>*povinná</span>
                </div>
                <?php
                // Ve stavu BO_VRACENO není poznámka povinná — OZ odpovídá BO přes Pracovní deník.
                // Ve stavu BO_VRACENO je textarea collapsed (stačí klik na "+ Přidat poznámku").
                $noteOptional  = ($ozStav === 'BO_VRACENO');
                $noteCollapsed = ($ozStav === 'BO_VRACENO');
                ?>
                <?php if ($noteCollapsed) { ?>
                <button type="button" id="note-stub-<?= $cId ?>"
                        onclick="ozNoteExpand(<?= $cId ?>)"
                        style="display:block;width:100%;text-align:left;
                               background:transparent;color:var(--muted);
                               border:1px dashed rgba(255,255,255,0.15);border-radius:5px;
                               padding:0.4rem 0.65rem;font-size:0.78rem;cursor:pointer;
                               font-family:inherit;font-style:italic;
                               transition:border-color 0.15s, color 0.15s;"
                        onmouseover="this.style.borderColor='rgba(255,255,255,0.3)';this.style.color='var(--text)';"
                        onmouseout="this.style.borderColor='rgba(255,255,255,0.15)';this.style.color='var(--muted)';">
                    📝 + Přidat krátkou poznámku k akci
                    <span style="opacity:0.6;font-size:0.7rem;">(volitelné — hlavní komunikace v Pracovním deníku výše)</span>
                </button>
                <?php } ?>
                <div id="note-wrap-<?= $cId ?>" style="<?= $noteCollapsed ? 'display:none;' : '' ?>position:relative;">
                    <?php if ($noteCollapsed) { ?>
                    <button type="button"
                            onclick="ozNoteCollapse(<?= $cId ?>)"
                            title="Sbalit poznámku"
                            style="position:absolute;top:0.25rem;right:0.4rem;z-index:2;
                                   background:rgba(0,0,0,0.4);color:var(--muted);
                                   border:1px solid rgba(255,255,255,0.15);border-radius:4px;
                                   padding:0.05rem 0.4rem;font-size:0.7rem;cursor:pointer;
                                   font-family:inherit;line-height:1;">
                        × sbalit
                    </button>
                    <?php } ?>
                    <textarea name="oz_poznamka"
                              id="note-<?= $cId ?>"
                              class="oz-note-input <?= $noteOptional ? '' : 'oz-note-input--required' ?>"
                              <?= $noteOptional ? 'data-optional="1"' : '' ?>
                              placeholder="<?= $noteOptional
                                  ? 'Krátká poznámka k akci (volitelné)'
                                  : 'Napište poznámku (povinné před jakoukoliv akcí)…' ?>"></textarea>
                </div>

                <div class="oz-action-row">
                    <?php if (!$smlouvaOnly) { ?>

                    <?php /* Tlačítko "📞 Obvoláno" odebráno — tab Obvoláno už neexistuje,
                             stavy OBVOLANO/ZPRACOVAVA spadají do Nové. */ ?>

                    <?php if (in_array($ozStav, ['NOVE', 'OBVOLANO', 'ZPRACOVAVA', 'NABIDKA', 'BO_VRACENO'], true) && !isset($hiddenTabsSet['nabidka'])) { ?>
                        <button type="button" class="oz-btn oz-btn--nabidka"
                                onclick="ozSubmit(<?= $cId ?>, 'NABIDKA')"
                                title="Nabídka byla odeslána zákazníkovi">
                            📨 Nabídka odeslána
                        </button>
                    <?php } ?>

                    <?php if ($ozStav !== 'SCHUZKA' && !isset($hiddenTabsSet['schuzka'])) { ?>
                        <button type="button" class="oz-btn oz-btn--schuzka"
                                onclick="ozTogglePanel('<?= $panelSaId ?>', <?= $cId ?>)">
                            📅 Schůzka
                        </button>
                    <?php } ?>

                    <?php if ($ozStav !== 'CALLBACK' && !isset($hiddenTabsSet['callback'])) { ?>
                        <button type="button" class="oz-btn oz-btn--callback"
                                onclick="ozTogglePanel('<?= $panelCbId ?>', <?= $cId ?>)">
                            📞 Callback
                        </button>
                    <?php } ?>

                    <?php if ($ozStav !== 'SANCE' && !isset($hiddenTabsSet['sance'])) { ?>
                        <button type="button" class="oz-btn oz-btn--sance"
                                onclick="ozConfirm(<?= $cId ?>, 'SANCE', 'Přesunout do Šance? (zákazník chce, ale chybí mu administrativa)', '💡')"
                                title="Zákazník chce, ale ještě nemá administrativní doklady">
                            💡 Šance
                        </button>
                    <?php } ?>

                    <?php if (!isset($hiddenTabsSet['bo_predano'])) { ?>
                    <?php /* Tlačítko "🏆 Smlouva" odebráno —
                             OZ nezavírá smlouvu sám, předává BO přes "Předat BO".
                             BMSL/podpis si BO uloží podle nabídky z OT. */ ?>

                    <?php // "Předat BO" — pokud je ID nabídky i BMSL vyplněné, jen confirm + submit.
                          //                 pokud něco chybí, klik otevře inline dialog s poli pro ID + BMSL.
                          $predatBoReady = ($ozNabidkaId !== '' && $ozBmsl !== null && (int)$ozBmsl > 0);
                          if ($predatBoReady) {
                              $confirmTitle = ($ozStav === 'BO_VRACENO')
                                  ? 'Předat zpět do Back-office?'
                                  : 'Předat kontakt do Back-office?';
                              $bmslFmt = number_format((int)$ozBmsl, 0, ',', ' ');
                              $confirmBody = '<div style="display:flex;flex-direction:column;gap:0.35rem;font-size:0.85rem;line-height:1.5;">'
                                  . '<div>🔖 ID nabídky: <strong style="color:var(--oz-bo);">' . crm_h((string)$ozNabidkaId) . '</strong></div>'
                                  . '<div>💰 BMSL: <strong style="color:var(--oz-smlouva);">' . crm_h($bmslFmt) . ' Kč</strong> <span style="color:var(--muted);font-size:0.78rem;">bez DPH</span></div>'
                                  . '<div style="margin-top:0.3rem;font-size:0.78rem;color:var(--muted);">BO převezme zpracování (datovka, podpis, OKU…).</div>'
                                  . '</div>';
                          ?>
                        <button type="button" class="oz-btn oz-btn--bo"
                                onclick='ozPredatBoConfirm(<?= $cId ?>, <?= json_encode($confirmTitle, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, <?= json_encode($confirmBody, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                title="Předá kontakt do Back-office">
                            📤 Předat BO
                        </button>
                    <?php } else { ?>
                        <button type="button" class="oz-btn oz-btn--bo"
                                onclick="ozPredatBoDialogToggle(<?= $cId ?>)"
                                title="Otevře dialog pro vyplnění ID nabídky a BMSL">
                            📤 Předat BO
                        </button>
                    <?php } ?>
                    <?php } ?>

                    <?php if ($ozStav !== 'NOVE') { ?>
                        <button type="button" class="oz-btn oz-btn--save"
                                onclick="ozSubmit(<?= $cId ?>, 'NOTE_ONLY')"
                                title="Uloží poznámku bez změny stavu">
                            💾 Uložit poznámku
                        </button>
                    <?php } ?>

                    <?php if (!isset($hiddenTabsSet['nezajem'])) { ?>
                    <button type="button" class="oz-btn oz-btn--nezajem"
                            onclick="ozConfirm(<?= $cId ?>, 'NEZAJEM', 'Označit jako nezájem?')">
                        ✗ Nezájem
                    </button>
                    <?php } ?>

                    <?php } else { ?>
                    <!-- SMLOUVA: Předat BO + Uložit poznámku -->
                    <?php
                    $smlouvaReady = ($ozNabidkaId !== '' && $ozBmsl !== null && (int)$ozBmsl > 0);
                    if ($smlouvaReady) {
                        $bmslFmtS = number_format((int)$ozBmsl, 0, ',', ' ');
                        $smlBody = '<div style="display:flex;flex-direction:column;gap:0.35rem;font-size:0.85rem;line-height:1.5;">'
                            . '<div>🔖 ID nabídky: <strong style="color:var(--oz-bo);">' . crm_h((string)$ozNabidkaId) . '</strong></div>'
                            . '<div>💰 BMSL: <strong style="color:var(--oz-smlouva);">' . crm_h($bmslFmtS) . ' Kč</strong> <span style="color:var(--muted);font-size:0.78rem;">bez DPH</span></div>'
                            . '</div>';
                    ?>
                        <button type="button" class="oz-btn oz-btn--bo"
                                onclick='ozPredatBoConfirm(<?= $cId ?>, "Předat smlouvu do Back-office?", <?= json_encode($smlBody, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                title="Předá kontrakt do Back-office ke zpracování">
                            📤 Předat BO
                        </button>
                    <?php } else { ?>
                        <button type="button" class="oz-btn oz-btn--bo"
                                onclick="ozPredatBoDialogToggle(<?= $cId ?>)"
                                title="Doplňte ID nabídky a BMSL pro předání BO"
                                style="background:rgba(233,30,140,0.6);">
                            📤 Předat BO <span style="font-size:0.65rem;font-weight:400;">· doplnit údaje</span>
                        </button>
                    <?php } ?>
                    <button type="button" class="oz-btn oz-btn--save"
                            onclick="ozSubmit(<?= $cId ?>, 'NOTE_ONLY')"
                            title="Uloží poznámku bez změny stavu">
                        💾 Uložit poznámku
                    </button>
                    <?php } ?>
                </div>

                <!-- Panel: Schůzka (uvnitř formuláře — vstupy se submitují správně) -->
                <div class="oz-datetime-panel" id="<?= $panelSaId ?>">
                    <span class="oz-datetime-panel__label">📅 Schůzka:</span>
                    <input type="datetime-local" name="schuzka_at" class="oz-dt-input"
                           min="<?= date('Y-m-d\TH:i') ?>"
                           value="<?= $schuzkaAt !== '' ? crm_h(date('Y-m-d\TH:i', strtotime($schuzkaAt))) : '' ?>">
                    <button type="button" class="oz-dt-confirm oz-dt-confirm--schuzka"
                            onclick="ozSubmit(<?= $cId ?>, 'SCHUZKA')">
                        Uložit schůzku
                    </button>
                </div>

                <!-- Panel: Callback (datum volitelné) -->
                <div class="oz-datetime-panel" id="<?= $panelCbId ?>">
                    <span class="oz-datetime-panel__label">📞 Callback:</span>
                    <input type="datetime-local" name="callback_at" id="cb-input-<?= $cId ?>"
                           class="oz-dt-input"
                           min="<?= date('Y-m-d\TH:i') ?>"
                           value="<?= !empty($c['oz_callback_at']) ? crm_h(date('Y-m-d\TH:i', strtotime((string)$c['oz_callback_at']))) : '' ?>">
                    <button type="button" class="oz-dt-confirm oz-dt-confirm--callback"
                            onclick="ozSubmit(<?= $cId ?>, 'CALLBACK')">
                        Uložit callback
                    </button>
                    <button type="button"
                            onclick="document.getElementById('cb-input-<?= $cId ?>').value = ''; ozSubmit(<?= $cId ?>, 'CALLBACK');"
                            title="Uloží do Callbacků bez konkrétního termínu — kontakt zůstane na konci seznamu"
                            style="font-size:0.78rem;padding:0.35rem 0.85rem;
                                   background:transparent;color:var(--muted);
                                   border:1px dashed rgba(255,255,255,0.2);border-radius:5px;cursor:pointer;
                                   font-family:inherit;">
                        🕓 Bez data
                    </button>
                </div>

                <!-- Panel: Smlouva (BMSL + datum podpisu) -->
                <div class="oz-smlouva-panel" id="smlouva-panel-<?= $cId ?>">
                    <div class="oz-smlouva-panel__row">
                        <span class="oz-datetime-panel__label">💰 BMSL (Kč bez DPH):</span>
                        <input type="number" name="bmsl" id="bmsl-<?= $cId ?>"
                               class="oz-dt-input" min="100" step="100"
                               placeholder="např. 25000"
                               oninput="ozBmslPreview(<?= $cId ?>, this.value)"
                               style="max-width:155px;">
                        <span id="bmsl-preview-<?= $cId ?>"
                              style="font-size:0.8rem;font-weight:700;color:var(--oz-smlouva);font-family:monospace;min-width:80px;"></span>
                        <span style="font-size:0.68rem;color:var(--muted);">↓ stovky, bez DPH</span>
                    </div>
                    <div class="oz-smlouva-panel__row">
                        <span class="oz-datetime-panel__label">📄 Datum podpisu:</span>
                        <input type="date" name="smlouva_date" id="smlouvadate-<?= $cId ?>"
                               class="oz-dt-input"
                               style="max-width:160px;"
                               value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="oz-smlouva-panel__row">
                        <span class="oz-datetime-panel__label">🔖 ID nabídky:</span>
                        <input type="text" name="nabidka_id" id="nabidkaid-<?= $cId ?>"
                               class="oz-dt-input"
                               inputmode="numeric" pattern="\d+"
                               style="max-width:160px;font-family:monospace;letter-spacing:0.05em;"
                               placeholder="např. 020098"
                               maxlength="50"
                               autocomplete="off">
                        <span style="font-size:0.68rem;color:var(--muted);">pouze číslice</span>
                    </div>

                    <?php /* Checkbox "Instalace internetu" + dynamické adresy odebrány —
                             instalační adresa se nyní zadává v panelu "Nabídnuté služby"
                             (typ Internet, identifier = adresa). */ ?>

                    <!-- Potvrzovací tlačítko -->
                    <div class="oz-smlouva-panel__row" style="margin-top:0.5rem;">
                        <button type="button"
                                class="oz-dt-confirm"
                                style="background:var(--oz-smlouva);padding:0.45rem 1.2rem;font-size:0.8rem;"
                                onclick="ozSubmitSmlouva(<?= $cId ?>)">
                            🏆 Potvrdit smlouvu
                        </button>
                    </div>
                </div>

            </form>

            <!-- ══ Inline dialog: vyplnit ID nabídky + BMSL a předat BO ══ -->
            <?php
            // Dialog je k dispozici, kdykoli je BO_PREDANO sub-tab dostupný (není skrytý).
            // Tlačítko Předat BO ho otevře, pokud něco chybí; pole se předvyplní existujícími hodnotami.
            $boDialogAvailable = !isset($hiddenTabsSet['bo_predano']);
            $bmslExisting = ($ozBmsl !== null && (int)$ozBmsl > 0) ? (int)$ozBmsl : null;
            if ($boDialogAvailable && !in_array($ozStav, ['BO_PREDANO','UZAVRENO','REKLAMACE','NEZAJEM','NERELEVANTNI'], true)) { ?>
            <div id="oz-predat-bo-dialog-<?= $cId ?>" style="display:none;
                 margin-top:0.6rem;padding:0.7rem 0.85rem;
                 background:rgba(233,30,140,0.06);
                 border:1px solid rgba(233,30,140,0.35);
                 border-left:4px solid var(--oz-bo);
                 border-radius:0 8px 8px 0;">
                <form method="post" action="<?= crm_h(crm_url('/oz/set-offer-id')) ?>"
                      style="display:flex;flex-direction:column;gap:0.55rem;">
                    <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                    <input type="hidden" name="contact_id" value="<?= $cId ?>">
                    <input type="hidden" name="tab" value="<?= crm_h($tab) ?>">
                    <input type="hidden" name="then_predat" value="1">

                    <div style="font-size:0.78rem;font-weight:700;color:var(--oz-bo);
                                display:flex;align-items:center;gap:0.4rem;">
                        📤 Předat do Back-office
                    </div>
                    <div style="font-size:0.72rem;color:var(--muted);">
                        Pro předání BO je nutné vyplnit ID nabídky z OT a BMSL částku smlouvy. BMSL se zaokrouhlí dolů na celé stokoruny (1 199 → 1 100).
                    </div>

                    <!-- ID nabídky -->
                    <label style="display:flex;flex-direction:column;gap:0.2rem;">
                        <span style="font-size:0.7rem;color:var(--muted);font-weight:600;">🔖 ID nabídky (OT)</span>
                        <input type="text" name="offer_id" required maxlength="80"
                               value="<?= crm_h((string)$ozNabidkaId) ?>"
                               placeholder="např. 020098"
                               inputmode="numeric"
                               style="background:var(--bg);color:var(--text);
                                      border:1px solid rgba(233,30,140,0.45);border-radius:5px;
                                      padding:0.4rem 0.55rem;font-size:0.85rem;font-family:monospace;
                                      font-weight:600;letter-spacing:0.05em;">
                    </label>

                    <!-- BMSL částka -->
                    <label style="display:flex;flex-direction:column;gap:0.2rem;">
                        <span style="font-size:0.7rem;color:var(--muted);font-weight:600;">💰 BMSL (Kč bez DPH) — zaokrouhlí se dolů na stokoruny</span>
                        <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                            <input type="number" name="bmsl" required min="100" step="100"
                                   id="bmsl-predat-<?= $cId ?>"
                                   value="<?= $bmslExisting !== null ? $bmslExisting : '' ?>"
                                   placeholder="např. 1199 → uloží se 1100"
                                   onblur="ozBmslRoundDown(this, <?= $cId ?>)"
                                   oninput="ozBmslPreviewPredat(<?= $cId ?>, this.value)"
                                   style="flex:1;min-width:140px;background:var(--bg);color:var(--text);
                                          border:1px solid rgba(155,89,182,0.45);border-radius:5px;
                                          padding:0.4rem 0.55rem;font-size:0.85rem;font-family:monospace;
                                          font-weight:600;">
                            <span id="bmsl-predat-preview-<?= $cId ?>"
                                  style="font-size:0.78rem;color:var(--oz-smlouva);font-family:monospace;
                                         font-weight:700;min-width:90px;">
                                <?= $bmslExisting !== null ? '= ' . number_format($bmslExisting, 0, ',', ' ') . ' Kč' : '' ?>
                            </span>
                        </div>
                    </label>

                    <div style="display:flex;gap:0.45rem;justify-content:flex-end;flex-wrap:wrap;">
                        <button type="button"
                                onclick="ozPredatBoDialogToggle(<?= $cId ?>)"
                                style="padding:0.35rem 0.85rem;font-size:0.78rem;
                                       background:transparent;color:var(--muted);
                                       border:1px solid rgba(255,255,255,0.15);border-radius:5px;cursor:pointer;">
                            Zrušit
                        </button>
                        <button type="submit"
                                style="padding:0.35rem 1rem;font-size:0.78rem;font-weight:700;
                                       background:var(--oz-bo);color:#fff;
                                       border:0;border-radius:5px;cursor:pointer;">
                            📤 Uložit a předat BO
                        </button>
                    </div>
                </form>
            </div>
            <?php } ?>
        </div>

        <?php } else { ?>
        <!-- BO_PREDANO / UZAVRENO / REKLAMACE / NEZÁJEM — stavové bannery -->
        <?php if ($ozStav === 'BO_PREDANO') { ?>
        <div style="padding:0.35rem 0.9rem 0.65rem;">
            <span style="font-size:0.75rem;color:var(--oz-bo);background:rgba(233,30,140,0.08);
                         border:1px solid rgba(233,30,140,0.25);border-radius:6px;
                         padding:0.25rem 0.7rem;display:inline-flex;align-items:center;gap:0.4rem;">
                📤 Předáno do Back-office — čeká se na zpracování
            </span>
        </div>
        <?php } elseif ($ozStav === 'UZAVRENO') { ?>
        <div style="padding:0.35rem 0.9rem 0.65rem;display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
            <span style="font-size:0.75rem;color:#2ecc71;background:rgba(46,204,113,0.08);
                         border:1px solid rgba(46,204,113,0.25);border-radius:6px;
                         padding:0.25rem 0.7rem;display:inline-flex;align-items:center;gap:0.4rem;">
                ✅ Dokončeno — smlouva aktivní
            </span>
            <!-- OZ může vrátit kontakt zpět do BO procesu (např. zákazník chce dokoupit službu) -->
            <form id="oz-reopen-bo-form-<?= $cId ?>"
                  method="post" action="<?= crm_h(crm_url('/oz/lead-status')) ?>"
                  style="display:inline;">
                <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                <input type="hidden" name="contact_id" value="<?= $cId ?>">
                <input type="hidden" name="tab"        value="<?= crm_h($tab) ?>">
                <input type="hidden" name="oz_stav"    value="BO_PREDANO">
                <input type="hidden" name="oz_poznamka" value="🔄 Znovu otevřeno OZ — další služba / úprava">
                <button type="button"
                        onclick="ozReopenFromUzavreno(<?= $cId ?>)"
                        style="font-size:0.78rem;padding:0.3rem 0.85rem;
                               background:rgba(233,30,140,0.12);color:var(--oz-bo);
                               border:1px solid rgba(233,30,140,0.4);border-radius:6px;
                               cursor:pointer;font-weight:700;font-family:inherit;">
                    📤 Znovu předat BO
                </button>
            </form>
        </div>
        <?php } elseif ($ozStav === 'REKLAMACE') {
            $callerComment   = (string)($c['flag_caller_comment']   ?? '');
            $callerConfirmed = (int)($c['flag_caller_confirmed']     ?? 0);
            $ozComment       = (string)($c['flag_oz_comment']        ?? '');
            $ozConfirmed     = (int)($c['flag_oz_confirmed']         ?? 0);
            $bothClosed      = $callerConfirmed === 1 && $ozConfirmed === 1;
        ?>
        <div style="margin:0 0.9rem 0.65rem;border:1px solid rgba(243,156,18,0.3);
                    border-left:3px solid var(--oz-reklamace);border-radius:0 8px 8px 0;
                    background:rgba(243,156,18,0.05);padding:0.55rem 0.75rem;
                    display:flex;flex-direction:column;gap:0.35rem;">

            <!-- Status řádek -->
            <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                <?php if ($bothClosed) { ?>
                    <span style="font-size:0.75rem;font-weight:700;color:#2ecc71;">✅ Případ uzavřen oběma stranami</span>
                    <span style="font-size:0.68rem;color:var(--muted);">Lead evidován jako chybný — nepočítá se do výplaty</span>
                <?php } elseif ($ozConfirmed) { ?>
                    <span style="font-size:0.75rem;font-weight:700;color:var(--oz-reklamace);">⏳ Čeká se na potvrzení navolávačky</span>
                <?php } elseif ($callerConfirmed) { ?>
                    <span style="font-size:0.75rem;font-weight:700;color:var(--oz-reklamace);">⏳ Navolávačka přijala — čeká se na vaše uzavření</span>
                <?php } else { ?>
                    <span style="font-size:0.75rem;font-weight:700;color:var(--oz-reklamace);">⚠ Otevřeno</span>
                <?php } ?>
            </div>

            <!-- Komentář navolávačky -->
            <?php if ($callerComment !== '') { ?>
            <div style="background:rgba(255,255,255,0.04);border-radius:5px;
                        padding:0.35rem 0.6rem;font-size:0.78rem;
                        border-left:2px solid rgba(52,152,219,0.5);">
                <span style="font-size:0.65rem;text-transform:uppercase;color:#3498db;
                             letter-spacing:0.05em;font-weight:700;">Navolávačka:</span><br>
                <em style="color:var(--text);"><?= crm_h($callerComment) ?></em>
            </div>
            <?php } ?>

            <!-- Vaše předchozí odpověď (pokud existuje) -->
            <?php if ($ozComment !== '') { ?>
            <div style="background:rgba(255,255,255,0.04);border-radius:5px;
                        padding:0.35rem 0.6rem;font-size:0.78rem;
                        border-left:2px solid rgba(243,156,18,0.5);">
                <span style="font-size:0.65rem;text-transform:uppercase;color:var(--oz-reklamace);
                             letter-spacing:0.05em;font-weight:700;">Vaše odpověď:</span><br>
                <em style="color:var(--text);"><?= crm_h($ozComment) ?></em>
            </div>
            <?php } ?>

            <!-- Formulář: odpověď + uzavření (jen pokud není uzavřeno) -->
            <?php if (!$bothClosed) { ?>
            <div style="display:flex;flex-direction:column;gap:0.4rem;margin-top:0.15rem;">
                <!-- Odpověď navolávačce -->
                <?php if (!$ozConfirmed) { ?>
                <form method="post" action="<?= crm_h(crm_url('/oz/chybny-comment')) ?>">
                    <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                    <input type="hidden" name="contact_id" value="<?= $cId ?>">
                    <textarea name="oz_comment"
                              class="oz-note-input"
                              style="border-color:rgba(243,156,18,0.4);font-size:0.78rem;"
                              placeholder="Napište odpověď navolávačce…"
                              rows="2"></textarea>
                    <div style="display:flex;gap:0.5rem;margin-top:0.3rem;flex-wrap:wrap;">
                        <button type="submit" class="oz-btn oz-btn--obvolano"
                                style="font-size:0.75rem;padding:0.3rem 0.8rem;">
                            💬 Odeslat odpověď
                        </button>
                    </div>
                </form>
                <!-- Uzavřít případ -->
                <form method="post" action="<?= crm_h(crm_url('/oz/chybny-close')) ?>">
                    <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                    <input type="hidden" name="contact_id" value="<?= $cId ?>">
                    <button type="submit" class="oz-btn oz-btn--reklamace"
                            style="font-size:0.75rem;padding:0.3rem 0.8rem;"
                            onclick="return confirm('Uzavřít případ? Lead zůstane evidován jako chybný a nepočítá se do výplaty navolávačky.')">
                        ✅ Uzavřít případ z mé strany
                    </button>
                </form>
                <?php } ?>
            </div>
            <?php } ?>
        </div>
        <?php } elseif ($contactNotes === [] && empty($c['caller_poznamka'])) { ?>
        <div style="padding:0.4rem 0.9rem 0.65rem;"></div>
        <?php } ?>
        <?php } ?>


    </div>
    <?php } ?>

</div><!-- /.oz-main-content -->

<!-- ══════════════════════════════════════════════════════
     PRAVÝ SIDEBAR — kontakty vrácené od BO (věž s přesahem)
     1 BO  → přímý <a> redirect na ?tab=bo_vraceno#c-{id}
     2+ BO → klik otevře popover se seznamem firem; klik na firmu → konkrétní karta
══════════════════════════════════════════════════════ -->
<div class="oz-bo-sidebar <?= $boReturned === [] ? 'oz-bo-sidebar--empty' : '' ?>">
<?php
$boCount = count($boReturned);
if ($boCount > 0) {
    $boTop      = $boReturned[0];
    $boTopId    = (int) ($boTop['id'] ?? 0);
    $boTopFirma = (string) ($boTop['firma'] ?? '—');

    if ($boCount === 1) {
        // Jen jedna vrácená karta — přímý anchor (žádný popover)
        $boHref  = crm_url('/oz/leads?tab=bo_vraceno#c-' . $boTopId);
        $boTitle = 'BO vráceno: ' . $boTopFirma;
?>
<a href="<?= crm_h($boHref) ?>" class="oz-bo-stack" title="<?= crm_h($boTitle) ?>">
    <div class="oz-bo-card">
        <span class="oz-bo-card__emoji">↩️</span>
        <span class="oz-bo-label">BO</span>
        <span class="oz-bo-card__firma"><?= crm_h($boTopFirma) ?></span>
    </div>
</a>
<?php
    } else {
        // 2+ vrácené karty — věž otevírá popover
        $boTitle = 'BO vráceno: ' . $boCount . ' kontaktů — klikni pro výpis';
?>
<div class="oz-bo-stack" onclick="ozBoTogglePop(event)" title="<?= crm_h($boTitle) ?>">
    <?php // Vykresli max 3 vrstvy (vrchní + 2 pozadí) ?>
    <?php for ($bi = 0; $bi < min($boCount, 3); $bi++) { ?>
    <div class="oz-bo-card">
        <?php if ($bi === 0) { ?>
            <span class="oz-bo-card__emoji">↩️</span>
            <span class="oz-bo-card__count"><?= $boCount ?></span>
            <span class="oz-bo-label">BO</span>
        <?php } ?>
    </div>
    <?php } ?>
</div>
<?php
    }
}
?>
</div><!-- /.oz-bo-sidebar -->

<?php // Data pro BO popover — pole {id, firma} pro JS ?>
<script>
window._ozBoData = <?= json_encode(array_map(fn($b) => [
    'id'    => (int) ($b['id'] ?? 0),
    'firma' => (string) ($b['firma'] ?? '—'),
], $boReturned), JSON_UNESCAPED_UNICODE) ?>;
window._ozBoUrlBase = <?= json_encode(crm_url('/oz/leads?tab=bo_vraceno#c-')) ?>;
</script>

<!-- BO popover — globální (mimo sidebar, ať není ořezán překryvem) -->
<div class="oz-bo-pop" id="oz-bo-pop"></div>

</div><!-- /.oz-layout -->
</section>

<!-- Backdrop + globální popup — mimo section, na úrovni body -->
<div class="oz-pending-backdrop" id="oz-pending-backdrop" onclick="ozCloseAllPops()"></div>
<div class="oz-pending-pop" id="oz-pending-pop-global" style="display:none;"></div>
<div class="oz-renewal-pop" id="oz-renewal-pop"></div>

<!-- ── Custom confirm modál ───────────────────────────────────────── -->
<div id="oz-confirm-modal" style="display:none;" aria-modal="true" role="dialog">
    <div class="oz-modal-overlay" id="oz-modal-overlay">
        <div class="oz-modal-box">
            <div class="oz-modal-icon" id="oz-modal-icon">🏆</div>
            <div class="oz-modal-title" id="oz-modal-title"></div>
            <div class="oz-modal-body"  id="oz-modal-body"></div>
            <div class="oz-modal-actions">
                <button id="oz-modal-ok"     class="oz-modal-btn oz-modal-btn--ok">✓ Potvrdit</button>
                <button id="oz-modal-cancel" class="oz-modal-btn oz-modal-btn--cancel">Zrušit</button>
            </div>
        </div>
    </div>
</div>

<!-- ── PHP-bridge: dynamické hodnoty pro oz_leads.js ─────────────────── -->
<script>
window.OZ_CONFIG = {
    userId:    <?= (int)$user['id'] ?>,
    csrf:      <?= json_encode($csrf) ?>,
    csrfField: <?= json_encode(crm_csrf_field_name()) ?>,
    urls: {
        callerSearch:     <?= json_encode(crm_url('/caller/search')) ?>,
        ozRaceJson:       <?= json_encode(crm_url('/oz/race.json')) ?>,
        ozAresLookup:     <?= json_encode(crm_url('/oz/ares-lookup')) ?>,
        ozTabReorder:     <?= json_encode(crm_url('/oz/tab/reorder')) ?>,
        boCheckboxToggle: <?= json_encode(crm_url('/bo/checkbox-toggle')) ?>,
        ozCheckboxToggle: <?= json_encode(crm_url('/oz/checkbox-toggle')) ?>
    }
};
</script>

<!-- ── Hlavní logika OZ — externí soubor (Phase 2 refactor) ──────────── -->
<script src="<?= crm_h(crm_url('/assets/js/oz_leads.js')) ?>"></script>

<!-- ══ Nabídnuté služby (Fáze 2 CRUD) — DEAKTIVOVÁNO ═══════════════════
     UI panel je vypnutý v body view (vyhledat "DEAKTIVOVÁNO" výše).
     Pro reaktivaci změňte if(false) na if(true) níže I v body view.    -->
<?php if (false) { ?>
<script>
// Katalog tarifů z PHP (typ → skupina → tarify)
window.OZ_OFFERED_CATALOG = <?= json_encode(
    crm_offered_services_catalog(),
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;

/** Toggle viditelnost inline formuláře pro přidání služby. */
function ozOfferedToggleForm(cId) {
    const wrap = document.getElementById('oz-offered-form-' + cId);
    if (!wrap) return;
    const isOpen = wrap.style.display === 'flex';
    if (isOpen) {
        wrap.style.display = 'none';
    } else {
        wrap.style.display = 'flex';
        // Reset formuláře při otevření
        const form = wrap.querySelector('form');
        if (form) form.reset();
        const labelSelect = document.getElementById('oz-offered-label-' + cId);
        if (labelSelect) {
            labelSelect.disabled = true;
            labelSelect.innerHTML = '<option value="">— Nejprve vyberte typ —</option>';
        }
        // Focus na typ
        setTimeout(() => {
            const typeSel = document.getElementById('oz-offered-type-' + cId);
            if (typeSel) typeSel.focus();
        }, 50);
    }
}

/** Generický renderer dropdownu tarifů + skrývání modemu. */
function _ozOfferedRenderTariffs(typeSel, labelSel, modemWrap, modemSel, currentLabel) {
    if (!typeSel || !labelSel) return;
    const type = typeSel.value;
    labelSel.innerHTML = '';

    // Modem je relevantní jen pro Pevný internet
    if (modemWrap) {
        if (type === 'internet') {
            modemWrap.style.display = '';
        } else {
            modemWrap.style.display = 'none';
            if (modemSel) modemSel.value = '';
        }
    }

    if (!type || !window.OZ_OFFERED_CATALOG[type]) {
        labelSel.disabled = true;
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = '— Nejprve vyberte typ —';
        labelSel.appendChild(opt);
        return;
    }

    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = currentLabel ? '— Změnit tarif —' : '— Vyberte tarif —';
    labelSel.appendChild(placeholder);

    let matched = false;
    const groups = window.OZ_OFFERED_CATALOG[type].groups || {};
    Object.keys(groups).forEach(groupName => {
        const og = document.createElement('optgroup');
        og.label = groupName;
        (groups[groupName] || []).forEach(tariff => {
            const o = document.createElement('option');
            o.value = tariff;
            o.textContent = tariff;
            if (tariff === currentLabel) {
                o.selected = true;
                matched = true;
            }
            og.appendChild(o);
        });
        labelSel.appendChild(og);
    });

    // Pokud current label nepatří k tomuto typu (user změnil typ), placeholder zůstává prázdný
    if (!matched) placeholder.selected = true;

    labelSel.disabled = false;
}

/** Při změně typu v ADD formuláři. */
function ozOfferedTypeChanged(cId) {
    _ozOfferedRenderTariffs(
        document.getElementById('oz-offered-type-' + cId),
        document.getElementById('oz-offered-label-' + cId),
        document.getElementById('oz-offered-modem-wrap-' + cId),
        document.getElementById('oz-offered-modem-' + cId),
        ''
    );
}

/** Při změně typu v EDIT formuláři — zachovává current label, pokud je v novém typu. */
function ozOfferedEditTypeChanged(svcId) {
    const labelSel = document.getElementById('oz-offered-edit-label-' + svcId);
    const currentLabel = labelSel ? (labelSel.dataset.currentLabel || '') : '';
    _ozOfferedRenderTariffs(
        document.getElementById('oz-offered-edit-type-' + svcId),
        labelSel,
        document.getElementById('oz-offered-edit-modem-wrap-' + svcId),
        document.getElementById('oz-offered-edit-modem-' + svcId),
        currentLabel
    );
}

/** Toggle mezi view a edit režimem konkrétní služby. */
function ozOfferedEditToggle(svcId) {
    const view = document.getElementById('oz-svc-view-' + svcId);
    const edit = document.getElementById('oz-svc-edit-' + svcId);
    if (!view || !edit) return;
    const isEditOpen = edit.style.display === 'flex';
    if (isEditOpen) {
        edit.style.display = 'none';
        view.style.display = '';
    } else {
        view.style.display = 'none';
        edit.style.display = 'flex';
        // Načíst tarif dropdown podle aktuálního typu
        ozOfferedEditTypeChanged(svcId);
        setTimeout(() => {
            const typeSel = document.getElementById('oz-offered-edit-type-' + svcId);
            if (typeSel) typeSel.focus();
        }, 50);
    }
}

/** Toggle viditelnost inline OKU formuláře pro konkrétní položku. */
function ozOkuToggle(itemId) {
    const wrap = document.getElementById('oz-oku-form-' + itemId);
    if (!wrap) return;
    const isOpen = wrap.style.display === 'block';
    if (isOpen) {
        wrap.style.display = 'none';
    } else {
        wrap.style.display = 'block';
        setTimeout(() => {
            const input = wrap.querySelector('input[name="oku_code"]');
            if (input) { input.focus(); input.select(); }
        }, 30);
    }
}
</script>
<?php } /* end if(false) — Nabídnuté služby JS DEAKTIVOVÁNO */ ?>
<!-- ══ /Nabídnuté služby — DEAKTIVOVÁNO ════════════════════════════════ -->
