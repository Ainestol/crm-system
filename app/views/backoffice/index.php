<?php
// e:\Snecinatripu\app\views\backoffice\index.php
declare(strict_types=1);

/** @var array<string, mixed>                          $user */
/** @var list<array<string, mixed>>                    $contacts */
/** @var array<int, list<array<string, mixed>>>        $notesByContact */
/** @var array<int, list<array<string, mixed>>>        $actionsByContact */
/** @var array<string, int>                            $tabCounts */
/** @var string                                        $tab */
/** @var string                                        $sort      'oldest' | 'newest' */
/** @var string|null                                   $flash */
/** @var string                                        $csrf */
/** @var list<array{oz_name:string,oz_id:int,contacts:list<array<string,mixed>>}> $contactsByOz */

$contactsByOz = $contactsByOz ?? [];
$sort         = $sort ?? 'oldest';

$tabLabels = [
    'k_priprave'  => '📥 K přípravě',
    'v_praci'     => '🔧 V práci',
    'vraceno_oz'  => '↩ Vráceno OZ',
    'uzavreno'    => '✅ Uzavřeno',
    'nezajem_vse' => '✗ Nezájem (vše OZ)',
];

// Pomocná funkce pro relativní časové popisky
if (!function_exists('boElapsed')) {
    function boElapsed(?string $dt): string {
        if ($dt === null || $dt === '') return '';
        $diff = time() - strtotime($dt);
        if ($diff < 60)    return 'právě teď';
        if ($diff < 3600)  return 'před ' . (int)($diff/60) . ' min';
        if ($diff < 86400) return 'před ' . (int)($diff/3600) . ' h';
        return 'před ' . (int)($diff/86400) . ' d';
    }
}
?>
<link rel="stylesheet" href="<?= crm_h(crm_url('/assets/css/bo_kit.css')) ?>">

<section class="bo-page">

    <?php if (!empty($flash)) { ?>
        <div class="alert alert-info" style="margin-bottom:0.8rem;"><?= crm_h($flash) ?></div>
    <?php } ?>

    <div class="bo-page__header">
        <h1 class="bo-page__title">
            🏢 Back-office
            <span class="bo-page__subtitle">— pracovní plocha</span>
        </h1>
    </div>

    <!-- ── STAT OVERVIEW (sbalitelné — sumář všech tabů) ───────────── -->
    <?php
    $cKp  = (int)($tabCounts['k_priprave']  ?? 0);
    $cVp  = (int)($tabCounts['v_praci']     ?? 0);
    $cVo  = (int)($tabCounts['vraceno_oz']  ?? 0);
    $cUz  = (int)($tabCounts['uzavreno']    ?? 0);
    $cNz  = (int)($tabCounts['nezajem_vse'] ?? 0);
    // Inline summary: ukáže ty, které mají hodnotu > 0
    $inlineParts = [];
    if ($cKp > 0) $inlineParts[] = "$cKp k přípravě";
    if ($cVp > 0) $inlineParts[] = "$cVp v práci";
    if ($cVo > 0) $inlineParts[] = "<strong style=\"color:#f39c12;\">$cVo vráceno OZ</strong>";
    if ($cUz > 0) $inlineParts[] = "$cUz uzavřeno";
    $inlineSummary = $inlineParts === [] ? 'žádná pracovní zátěž' : implode(' · ', $inlineParts);
    ?>
    <details class="bo-stats-collapse">
        <summary>
            <span>📊 Souhrn pracovní zátěže</span>
            <span class="bo-stats-collapse__inline"><?= $inlineSummary ?></span>
        </summary>
        <div class="bo-stats-collapse__inner">
            <div class="bo-stat-card bo-stat-card--priprava">
                <div class="bo-stat-card__val"><?= $cKp ?></div>
                <div class="bo-stat-card__lbl">K přípravě</div>
            </div>
            <div class="bo-stat-card bo-stat-card--vpraci">
                <div class="bo-stat-card__val"><?= $cVp ?></div>
                <div class="bo-stat-card__lbl">V práci</div>
            </div>
            <div class="bo-stat-card bo-stat-card--vraceno">
                <div class="bo-stat-card__val"><?= $cVo ?></div>
                <div class="bo-stat-card__lbl">Vráceno OZ</div>
            </div>
            <div class="bo-stat-card bo-stat-card--uzavreno">
                <div class="bo-stat-card__val"><?= $cUz ?></div>
                <div class="bo-stat-card__lbl">Uzavřeno</div>
            </div>
            <div class="bo-stat-card">
                <div class="bo-stat-card__val"><?= $cNz ?></div>
                <div class="bo-stat-card__lbl">Nezájem (vše)</div>
            </div>
        </div>
    </details>

    <!-- ── ZÁLOŽKY ─────────────────────────────────────────────────── -->
    <?php
    // Helper pro tab URL — preserve sort param (jen pokud non-default)
    $tabUrl = function (string $tabKey) use ($sort): string {
        $defaultSort = in_array($tabKey, ['uzavreno', 'nezajem_vse'], true) ? 'newest' : 'oldest';
        $params = ['tab' => $tabKey];
        if ($sort !== $defaultSort) {
            $params['sort'] = $sort;
        }
        return crm_url('/bo?' . http_build_query($params));
    };
    ?>
    <div class="bo-tabs">
        <?php foreach ($tabLabels as $tabKey => $label) {
            $isActive = $tab === $tabKey;
            $count    = (int) ($tabCounts[$tabKey] ?? 0);
            // Variant třída pro aktivní stav (variant color)
            $variant  = match ($tabKey) {
                'k_priprave'  => 'bo-tab--priprava',
                'v_praci'     => 'bo-tab--vpraci',
                'vraceno_oz'  => 'bo-tab--vraceno',
                'uzavreno'    => 'bo-tab--uzavreno',
                'nezajem_vse' => 'bo-tab--nezajem',
                default       => '',
            };
            $cls = 'bo-tab ' . $variant . ($isActive ? ' bo-tab--active' : '');
        ?>
            <a href="<?= crm_h($tabUrl($tabKey)) ?>"
               class="<?= crm_h($cls) ?>">
                <?= crm_h($label) ?>
                <span class="bo-tab__count"><?= $count ?></span>
            </a>
        <?php } ?>
    </div>

    <!-- ── SORT TOGGLE — směr třídění karet ──────────────────────────
         FIFO (Starší nahoru) je default pro pracovní taby → BO má
         stabilní pořadí, rozpracovaná karta neskáče nahoru.
         Pro Uzavřeno + Nezájem je default Novější nahoru (review). -->
    <?php
    $sortUrl = function (string $sortKey) use ($tab): string {
        return crm_url('/bo?' . http_build_query(['tab' => $tab, 'sort' => $sortKey]));
    };
    ?>
    <div class="bo-sort-toggle">
        <span class="bo-sort-toggle__label">🔃 Třídit:</span>
        <a href="<?= crm_h($sortUrl('oldest')) ?>"
           class="bo-sort-btn <?= $sort === 'oldest' ? 'bo-sort-btn--active' : '' ?>"
           title="Karty čekající nejdéle nahoru (FIFO — first in, first out)">
            ⏬ Starší nahoru
        </a>
        <a href="<?= crm_h($sortUrl('newest')) ?>"
           class="bo-sort-btn <?= $sort === 'newest' ? 'bo-sort-btn--active' : '' ?>"
           title="Nejčerstvější karty nahoru (LIFO)">
            ⏫ Novější nahoru
        </a>
    </div>

    <!-- ── SEZNAM KARET ─────────────────────────────────────────────── -->
    <?php if ($contacts === []) { ?>
        <div class="bo-empty">
            <?php
            echo match ($tab) {
                'k_priprave'  => '📥 Žádné nové kontakty k přípravě.',
                'v_praci'     => '🔧 Žádné kontakty rozpracované.',
                'vraceno_oz'  => '↩ Žádné kontakty vrácené OZ.',
                'uzavreno'    => '✅ Žádné uzavřené kontakty.',
                'nezajem_vse' => '✅ Žádný „nezájem" — všichni OZ mají čisté koše.',
                default       => 'Žádné kontakty.',
            };
            ?>
        </div>
    <?php } elseif ($tab === 'nezajem_vse') { ?>
        <!-- ── Nezájem (vše OZ) — sbalitelné sekce per OZ ── -->
        <div class="bo-card-list">
            <?php foreach ($contactsByOz as $group) {
                $ozName = (string) ($group['oz_name'] ?? '—');
                $items  = (array) ($group['contacts'] ?? []);
                $cnt    = count($items);
            ?>
            <details open class="bo-nezajem-group">
                <summary>
                    <span class="bo-nezajem-group__icon">🛒</span>
                    <span><?= crm_h($ozName) ?></span>
                    <span class="bo-nezajem-group__count">
                        — <?= $cnt ?> kontakt<?= $cnt === 1 ? '' : ($cnt < 5 ? 'y' : 'ů') ?> v koši Nezájem
                    </span>
                </summary>
                <div class="bo-nezajem-group__list">
                    <?php foreach ($items as $c) {
                        $cId         = (int) ($c['id'] ?? 0);
                        $firma       = (string) ($c['firma'] ?? '—');
                        $tel         = (string) ($c['telefon'] ?? '');
                        $email       = (string) ($c['email'] ?? '');
                        $ico         = (string) ($c['ico'] ?? '');
                        $adresa      = (string) ($c['adresa'] ?? '');
                        $callerName  = (string) ($c['caller_name'] ?? '—');
                        $callerNote  = (string) ($c['caller_poznamka'] ?? '');
                        $stavCh      = (string) ($c['oz_stav_changed_at'] ?? '');
                        $cActions    = $actionsByContact[$cId] ?? [];
                        // Najít poslední záznam typu "Nezájem" — důvod z deníku
                        $nezajemReason = '';
                        foreach ($cActions as $a) {
                            $txt = (string) ($a['action_text'] ?? '');
                            if (mb_stripos($txt, 'Nezájem') !== false) {
                                $nezajemReason = $txt;
                                break; // první (nejnovější) match
                            }
                        }
                    ?>
                    <details id="c-<?= $cId ?>" class="bo-card bo-card--nezajem"
                             style="background:rgba(255,255,255,0.02);">
                        <summary style="cursor:pointer;padding:0.5rem 0.75rem;
                                        display:flex;align-items:center;gap:0.7rem;flex-wrap:wrap;list-style:none;">
                            <span style="font-size:0.65rem;color:var(--bo-text-3);">▶</span>
                            <strong style="font-size:0.88rem;"><?= crm_h($firma) ?></strong>
                            <?php if ($tel !== '') { ?>
                                <span style="font-size:0.78rem;color:var(--bo-text-3);">📞 <?= crm_h($tel) ?></span>
                            <?php } ?>
                            <?php if ($stavCh !== '') { ?>
                                <span style="font-size:0.66rem;color:var(--bo-text-3);font-style:italic;margin-left:auto;">
                                    🕒 v Nezájmu <?= crm_h(boElapsed($stavCh)) ?>
                                </span>
                            <?php } ?>
                        </summary>
                        <div style="padding:0.7rem 0.95rem;border-top:1px solid var(--bo-border-soft);
                                    background:rgba(0,0,0,0.15);display:flex;flex-direction:column;gap:0.55rem;
                                    font-size:0.8rem;">

                            <!-- Důvod nezájmu z deníku -->
                            <?php if ($nezajemReason !== '') { ?>
                            <div class="bo-note-block" style="border-left-color:var(--bo-error);
                                                              background:rgba(231,76,60,0.08);">
                                <div class="bo-note-block__label" style="color:var(--bo-error);">✗ Důvod nezájmu</div>
                                <div class="bo-note-block__text" style="font-style:normal;"><?= crm_h($nezajemReason) ?></div>
                            </div>
                            <?php } ?>

                            <!-- Kontaktní info -->
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:0.35rem 1rem;">
                                <?php if ($email !== '') { ?>
                                    <div><span style="color:var(--bo-text-3);">E-mail:</span> <?= crm_h($email) ?></div>
                                <?php } ?>
                                <?php if ($ico !== '') { ?>
                                    <div>
                                        <span style="color:var(--bo-text-3);">IČO:</span>
                                        <?= crm_h($ico) ?>
                                        <a href="<?= crm_h('https://ares.gov.cz/ekonomicke-subjekty?ico=' . urlencode($ico)) ?>"
                                           target="_blank" rel="noopener noreferrer"
                                           style="color:#3498db;text-decoration:none;font-size:0.7rem;margin-left:0.3rem;">🔗 ARES</a>
                                    </div>
                                <?php } ?>
                                <?php if ($adresa !== '') { ?>
                                    <div><span style="color:var(--bo-text-3);">Adresa:</span> <?= crm_h($adresa) ?></div>
                                <?php } ?>
                                <div><span style="color:var(--bo-text-3);">Navolal/a:</span> <?= crm_h($callerName) ?></div>
                            </div>

                            <!-- Poznámka navolávačky -->
                            <?php if ($callerNote !== '') { ?>
                            <div class="bo-note-block bo-note-block--oz">
                                <div class="bo-note-block__label">📞 Navolávačka</div>
                                <div class="bo-note-block__text"><?= crm_h($callerNote) ?></div>
                            </div>
                            <?php } ?>

                            <!-- Pracovní deník (časová osa) -->
                            <?php if ($cActions !== []) { ?>
                            <div>
                                <div style="font-size:0.7rem;font-weight:700;color:var(--bo-text-3);
                                            text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.35rem;">
                                    📋 Pracovní deník (<?= count($cActions) ?>)
                                </div>
                                <div style="display:flex;flex-direction:column;gap:0.25rem;">
                                    <?php foreach ($cActions as $a) {
                                        $aDate    = (string) ($a['action_date'] ?? '');
                                        $aText    = (string) ($a['action_text'] ?? '');
                                        $aAuthor  = (string) ($a['author_name'] ?? '—');
                                        $aRole    = (string) ($a['author_role'] ?? '');
                                        $roleIcon = match ($aRole) {
                                            'backoffice' => '🏢',
                                            'obchodak'   => '🛒',
                                            'majitel', 'superadmin' => '👑',
                                            default      => '👤',
                                        };
                                    ?>
                                    <div class="bo-action-entry">
                                        <span class="bo-action-entry__date">
                                            <?= $aDate !== '' ? crm_h(date('d.m.Y', strtotime($aDate))) : '' ?>
                                        </span>
                                        <span class="bo-action-entry__text"><?= crm_h($aText) ?></span>
                                        <span class="bo-action-entry__author">
                                            <?= $roleIcon ?> <?= crm_h($aAuthor) ?>
                                        </span>
                                    </div>
                                    <?php } ?>
                                </div>
                            </div>
                            <?php } ?>
                        </div>
                    </details>
                    <?php } ?>
                </div>
            </details>
            <?php } ?>
        </div>
    <?php } else { ?>

        <!-- ── HLAVNÍ SEZNAM (k_priprave / v_praci / vraceno_oz / uzavreno) ── -->
        <div class="bo-card-list">
            <?php foreach ($contacts as $c) {
                $cId        = (int) ($c['id'] ?? 0);
                $firma      = (string) ($c['firma'] ?? '—');
                $tel        = (string) ($c['telefon'] ?? '');
                $email      = (string) ($c['email'] ?? '');
                $ico        = crm_normalize_ico((string) ($c['ico'] ?? ''));
                $adresa     = (string) ($c['adresa'] ?? '');
                $region     = (string) ($c['region'] ?? '');
                $operator   = (string) ($c['operator'] ?? '');
                $callerNote = (string) ($c['caller_poznamka'] ?? '');
                $callerName = (string) ($c['caller_name'] ?? '—');
                $ozName     = (string) ($c['oz_name'] ?? '—');
                $ozStav     = (string) ($c['oz_stav'] ?? '');
                $bmsl       = $c['oz_bmsl'] !== null ? (int) $c['oz_bmsl'] : null;
                $smlouvaDt  = (string) ($c['oz_smlouva_date'] ?? '');
                $nabidkaId  = (string) ($c['oz_nabidka_id'] ?? '');
                $contactActions = $actionsByContact[$cId] ?? [];
                $contactNotes   = $notesByContact[$cId] ?? [];
                $stavCh         = (string) ($c['oz_stav_changed_at'] ?? '');

                // Stav badge text + variant třída
                $stavText  = match ($ozStav) {
                    'BO_PREDANO' => '📤 Předáno BO',
                    'BO_VPRACI'  => '🔧 V práci',
                    'BO_VRACENO' => '↩ Vráceno OZ',
                    'UZAVRENO'   => '✅ Uzavřeno',
                    'SMLOUVA'    => '📤 Předáno BO',
                    default      => $ozStav,
                };
                $stavVariant = match ($ozStav) {
                    'BO_PREDANO', 'SMLOUVA' => 'bo-card__stav-badge--priprava',
                    'BO_VRACENO'            => 'bo-card__stav-badge--vraceno',
                    'UZAVRENO'              => 'bo-card__stav-badge--uzavreno',
                    default                 => '',
                };

                // Card variant (levý border)
                $cardVariant = match ($ozStav) {
                    'BO_PREDANO', 'SMLOUVA' => 'bo-card--priprava',
                    'BO_VPRACI'             => 'bo-card--vpraci',
                    'BO_VRACENO'            => 'bo-card--vraceno',
                    'UZAVRENO'              => 'bo-card--uzavreno',
                    default                 => '',
                };
            ?>
            <div id="c-<?= $cId ?>" class="bo-card <?= $cardVariant ?>">

                <!-- ── HLAVIČKA ── -->
                <div class="bo-card__head">
                    <strong class="bo-card__firm"><?= crm_h($firma) ?></strong>
                    <span class="bo-card__stav-badge <?= $stavVariant ?>"><?= crm_h($stavText) ?></span>
                    <?php if ($stavCh !== '') { ?>
                        <span class="bo-card__time-pill"
                              title="V této záložce od: <?= crm_h(date('d.m.Y H:i', strtotime($stavCh))) ?>">
                            🕒 <?= crm_h(boElapsed($stavCh)) ?>
                        </span>
                    <?php } ?>
                    <span class="bo-card__meta">
                        <?php if ($region !== '') { ?>
                            <span><?= crm_h(crm_region_label($region)) ?></span>
                        <?php } ?>
                        <span>OZ: <strong><?= crm_h($ozName) ?></strong></span>
                        <span>· navolal/a: <?= crm_h($callerName) ?></span>
                    </span>
                </div>

                <!-- ── TĚLO ── -->
                <div class="bo-card__body">
                    <!-- Levý sloupec: kontaktní info -->
                    <div class="bo-card__col">
                        <?php if ($tel !== '') { ?>
                            <div class="bo-card__field">
                                <span class="bo-card__field-label">Tel.</span>
                                <span class="bo-card__field-value" style="font-family:monospace;"><?= crm_h($tel) ?></span>
                            </div>
                        <?php } ?>
                        <?php if ($email !== '') { ?>
                            <div class="bo-card__field">
                                <span class="bo-card__field-label">E-mail</span>
                                <span class="bo-card__field-value"><?= crm_h($email) ?></span>
                            </div>
                        <?php } ?>
                        <?php if ($ico !== '') { ?>
                            <div class="bo-card__field">
                                <span class="bo-card__field-label">IČO</span>
                                <span class="bo-card__field-value"><?= crm_h($ico) ?></span>
                                <a href="<?= crm_h('https://ares.gov.cz/ekonomicke-subjekty?ico=' . urlencode($ico)) ?>"
                                   target="_blank" rel="noopener noreferrer"
                                   title="Ověřit firmu v ARES (otevře se v novém okně)"
                                   style="margin-left:0.4rem;color:#3498db;text-decoration:none;
                                          font-size:0.7rem;padding:0.05rem 0.35rem;border-radius:3px;
                                          background:rgba(52,152,219,0.1);
                                          border:1px solid rgba(52,152,219,0.25);">
                                    🔗 ARES
                                </a>
                            </div>
                        <?php } ?>
                        <?php if ($adresa !== '') { ?>
                            <div class="bo-card__field">
                                <span class="bo-card__field-label">Adresa</span>
                                <span class="bo-card__field-value"><?= crm_h($adresa) ?></span>
                            </div>
                        <?php } ?>
                        <?php if ($operator !== '') { ?>
                            <div class="bo-card__field">
                                <span class="bo-card__field-label">Operátor</span>
                                <strong class="bo-card__field-value"><?= crm_h(strtoupper($operator)) ?></strong>
                            </div>
                        <?php } ?>
                    </div>

                    <!-- Pravý sloupec: poznámky + BMSL -->
                    <div class="bo-card__col bo-card__col--right">
                        <?php if ($callerNote !== '') { ?>
                            <div class="bo-note-block">
                                <div class="bo-note-block__label">📞 Poznámka navolávačky</div>
                                <div class="bo-note-block__text"><?= crm_h($callerNote) ?></div>
                            </div>
                        <?php } ?>

                        <?php if ($contactNotes !== []) { ?>
                            <div class="bo-note-block bo-note-block--oz">
                                <div class="bo-note-block__label">
                                    📝 Poznámky OZ <span style="opacity:0.7;font-weight:400;">(<?= count($contactNotes) ?>)</span>
                                </div>
                                <?php foreach ($contactNotes as $note) {
                                    $noteAuthor = (string) ($note['author_name'] ?? '—');
                                    $noteText   = (string) ($note['note'] ?? '');
                                    $noteAt     = (string) ($note['created_at'] ?? '');
                                    $noteAtFmt  = $noteAt !== '' ? date('d.m.Y H:i', strtotime($noteAt)) : '';
                                ?>
                                <div class="bo-note-block__entry">
                                    <div class="bo-note-block__entry-meta">
                                        <span><?= crm_h($noteAtFmt) ?></span>
                                        <span style="margin-left:auto;">🛒 <?= crm_h($noteAuthor) ?></span>
                                    </div>
                                    <span style="white-space:pre-wrap;color:var(--bo-text);"><?= crm_h($noteText) ?></span>
                                </div>
                                <?php } ?>
                            </div>
                        <?php } ?>

                        <?php if ($bmsl !== null) { ?>
                            <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                                <span class="bo-bmsl-pill">
                                    💰 BMSL: <?= number_format($bmsl, 0, ',', ' ') ?> Kč
                                </span>
                                <?php if ($smlouvaDt !== '') { ?>
                                    <span style="color:var(--bo-text-3);font-size:0.72rem;">
                                        📄 Podpis: <?= crm_h(date('d.m.Y', strtotime($smlouvaDt))) ?>
                                    </span>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>

                <!-- ── ID NABÍDKY ── -->
                <?php if ($nabidkaId !== '') { ?>
                <div class="bo-nabidka-pill">
                    <span style="font-size:0.7rem;font-weight:700;color:#1abc9c;
                                 text-transform:uppercase;letter-spacing:0.04em;">
                        🔖 ID nabídky (OT)
                    </span>
                    <span class="bo-nabidka-pill__id"><?= crm_h($nabidkaId) ?></span>
                </div>
                <?php } else { ?>
                <div class="bo-nabidka-missing">⚠ ID nabídky ještě OZ neuvedl</div>
                <?php } ?>

                <!-- ── PRACOVNÍ DENÍK (sbalitelné default — vidíš poslední záznam) ── -->
                <?php
                $latest = $contactActions[0] ?? null;
                $latestPreview = '';
                if ($latest !== null) {
                    $latestDate    = (string) ($latest['action_date'] ?? '');
                    $latestText    = (string) ($latest['action_text'] ?? '');
                    $latestDateFmt = $latestDate !== '' ? date('d.m.', strtotime($latestDate)) : '';
                    $textShort     = mb_strlen($latestText) > 70 ? mb_substr($latestText, 0, 67) . '…' : $latestText;
                    $latestPreview = trim($latestDateFmt . ' ' . $textShort);
                }
                // Default: rozbalený pokud je v "v_praci" (aktivní práce), jinak sbalený
                $denikOpenDefault = ($tab === 'v_praci' || $tab === 'vraceno_oz');
                ?>
                <details class="bo-section bo-section--denik" <?= $denikOpenDefault ? 'open' : '' ?>>
                    <summary>
                        <span class="bo-section__title">📋 Pracovní deník</span>
                        <span class="bo-section__count">(<?= count($contactActions) ?>)</span>
                        <?php if ($latestPreview !== '') { ?>
                            <span class="bo-section__preview">· <?= crm_h($latestPreview) ?></span>
                        <?php } ?>
                    </summary>
                    <div class="bo-section__body">

                        <!-- Form pro přidání nového záznamu (jen pro aktivní BO_PREDANO/BO_VRACENO) -->
                        <?php if (in_array($ozStav, ['BO_PREDANO', 'BO_VRACENO'], true)) { ?>
                        <form method="post" action="<?= crm_h(crm_url('/bo/action/add')) ?>"
                              class="bo-action-add-form">
                            <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                            <input type="hidden" name="contact_id" value="<?= $cId ?>">
                            <input type="hidden" name="tab" value="<?= crm_h($tab) ?>">
                            <input type="date" name="action_date"
                                   value="<?= crm_h(date('Y-m-d')) ?>" required>
                            <input type="text" name="action_text"
                                   required maxlength="1000"
                                   placeholder="Popis úkonu (např. „Připraveno v Siebelu", „Datovka odeslána"…)">
                            <button type="submit" class="bo-btn-add">+ Přidat</button>
                        </form>
                        <?php } ?>

                        <!-- Seznam záznamů -->
                        <?php if ($contactActions === []) { ?>
                            <div style="font-size:0.74rem;color:var(--bo-text-3);font-style:italic;">
                                Zatím žádný úkon. První záznam zapíšete přidáním data + popisu výše.
                            </div>
                        <?php } else { ?>
                            <div style="display:flex;flex-direction:column;gap:0.25rem;">
                            <?php foreach ($contactActions as $action) {
                                $actId       = (int) ($action['id'] ?? 0);
                                $actDate     = (string) ($action['action_date'] ?? '');
                                $actText     = (string) ($action['action_text'] ?? '');
                                $actAuthor   = (string) ($action['author_name'] ?? '—');
                                $actRole     = (string) ($action['author_role'] ?? '');
                                $actAuthorId = (int) ($action['author_id'] ?? 0);
                                $actDateFmt  = $actDate !== '' ? date('d.m.Y', strtotime($actDate)) : '—';
                                $isMine      = $actAuthorId === (int) ($user['id'] ?? 0);
                                $roleIcon    = match ($actRole) {
                                    'backoffice'              => '🏢',
                                    'obchodak'                => '🛒',
                                    'navolavacka'             => '📞',
                                    'majitel', 'superadmin'   => '👑',
                                    default                   => '👤',
                                };
                                $roleLabel   = match ($actRole) {
                                    'backoffice'              => 'BO',
                                    'obchodak'                => 'OZ',
                                    'navolavacka'             => 'Caller',
                                    'majitel'                 => 'Majitel',
                                    'superadmin'              => 'Admin',
                                    default                   => '',
                                };
                            ?>
                            <div class="bo-action-entry">
                                <span class="bo-action-entry__date"><?= crm_h($actDateFmt) ?></span>
                                <span class="bo-action-entry__text"><?= crm_h($actText) ?></span>
                                <span class="bo-action-entry__author"
                                      title="Autor: <?= crm_h($actAuthor) ?><?= $roleLabel !== '' ? ' (' . crm_h($roleLabel) . ')' : '' ?>">
                                    <?= $roleIcon ?> <?= crm_h($actAuthor) ?>
                                </span>
                                <?php if ($isMine) { ?>
                                <form method="post" action="<?= crm_h(crm_url('/bo/action/delete')) ?>"
                                      style="display:inline;"
                                      onsubmit="return confirm('Smazat tento záznam?');">
                                    <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                                    <input type="hidden" name="action_id" value="<?= $actId ?>">
                                    <input type="hidden" name="tab" value="<?= crm_h($tab) ?>">
                                    <button type="submit" class="bo-action-entry__delete" title="Smazat svůj záznam">×</button>
                                </form>
                                <?php } ?>
                            </div>
                            <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                </details>

                <!-- ── BO POSTUP (sbalitelné default — summary ukazuje X/4) ── -->
                <?php if (in_array($ozStav, ['BO_PREDANO','BO_VPRACI','BO_VRACENO','UZAVRENO','SMLOUVA'], true)) {
                    $cbPriprava = (int) ($c['cb_priprava'] ?? 0);
                    $cbDatovka  = (int) ($c['cb_datovka']  ?? 0);
                    $cbPodpis   = (int) ($c['cb_podpis']   ?? 0);
                    $cbUbotem   = (int) ($c['cb_ubotem']   ?? 0);
                    $cbPodpisAt = (string) ($c['cb_podpis_at'] ?? '');
                    $isUzavrenoBo = ($ozStav === 'UZAVRENO');
                    $boDoneCount  = $cbPriprava + $cbDatovka + $cbPodpis + $cbUbotem;
                    $postupOpen   = ($tab === 'v_praci'); // jen v active tab open default
                ?>
                <details class="bo-section bo-section--postup" <?= $postupOpen ? 'open' : '' ?>>
                    <summary>
                        <span class="bo-section__title">✅ Postup zakázky</span>
                        <span class="bo-section__count">(<?= $boDoneCount ?>/4 hotovo)</span>
                        <?php if ($isUzavrenoBo) { ?>
                            <span class="bo-readonly-badge" style="margin-left:auto;">· uzavřeno (read-only)</span>
                        <?php } ?>
                    </summary>
                    <div class="bo-section__body">
                        <?php
                        // Pořadí kroků BO postupu (synchronizované s oz/leads.php)
                        $boRows = [
                            ['priprava_smlouvy', $cbPriprava, '📝 Příprava smlouvy', 'OT a Siebel'],
                            ['ubotem_zpracovano',$cbUbotem,   '🤖 Zpracování UBotem',  'BO zpracoval přes UBota'],
                            ['datovka_odeslana', $cbDatovka,  '📨 Odesláno do datovky', 'Datová schránka'],
                            ['podpis_potvrzen',  $cbPodpis,   '✍ Podpis potvrzen',     'Podpis byl potvrzen — započítá se do BMSL baru OZ'],
                        ];
                        foreach ($boRows as [$field, $val, $label, $hint]) {
                            $isChecked = $val === 1;
                            $disabled  = $isUzavrenoBo;
                            $rowCls = 'bo-cb-row';
                            if ($isChecked) $rowCls .= ' bo-cb-row--checked';
                            if ($disabled)  $rowCls .= ' bo-cb-row--disabled';
                        ?>
                        <label class="<?= crm_h($rowCls) ?>" title="<?= crm_h($hint) ?>">
                            <input type="checkbox"
                                   data-cb-field="<?= crm_h($field) ?>"
                                   data-cb-cid="<?= $cId ?>"
                                   data-cb-tab="<?= crm_h($tab) ?>"
                                   data-cb-role="bo"
                                   <?= $isChecked ? 'checked' : '' ?>
                                   <?= $disabled ? 'disabled' : '' ?>
                                   onchange="boCheckboxToggle(this)">
                            <span class="bo-cb-label"><?= crm_h($label) ?></span>
                            <?php if ($isChecked) { ?>
                                <span class="bo-cb-done-tag">✓ HOTOVO</span>
                            <?php } ?>
                        </label>
                        <?php } ?>
                    </div>
                    <?php if ($cbPodpis === 1 && $cbPodpisAt !== '') { ?>
                        <div style="font-size:0.66rem;color:var(--bo-text-3);font-style:italic;
                                    border-top:1px dashed var(--bo-border);padding:0.3rem 0.85rem;">
                            💰 Podpis potvrzen <?= crm_h(boElapsed($cbPodpisAt)) ?> · BMSL se počítá v baru OZ od této chvíle.
                        </div>
                    <?php } ?>
                </details>
                <?php } ?>

                <!-- ── AKČNÍ TLAČÍTKA ── -->
                <?php
                $canClose = ((int) ($c['cb_podpis'] ?? 0)) === 1;
                ?>
                <?php if (in_array($ozStav, ['BO_PREDANO', 'SMLOUVA'], true)) { ?>
                <!-- K přípravě: Začít zpracovávat (PRIMARY) + Nezájem (NEGATIVE) -->
                <div class="bo-card__actions">
                    <form method="post" action="<?= crm_h(crm_url('/bo/start-work')) ?>" style="display:inline;">
                        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                        <input type="hidden" name="contact_id" value="<?= $cId ?>">
                        <input type="hidden" name="tab" value="<?= crm_h($tab) ?>">
                        <button type="submit" class="bo-btn-secondary">
                            🔧 Začít zpracovávat
                        </button>
                    </form>
                    <button type="button"
                            class="bo-btn-negative"
                            onclick="boNezajemToggle(<?= $cId ?>)"
                            title="Označit jako nezájem (zákazník odmítl) — kontakt skočí do Nezájem koše OZ">
                        ✗ Nezájem
                    </button>
                </div>

                <?php } elseif ($ozStav === 'BO_VPRACI') { ?>
                <!-- V práci: Vrátit OZ (WARNING) + Uzavřít smlouvu (PRIMARY) + Nezájem (NEGATIVE) -->
                <div class="bo-card__actions">
                    <button type="button"
                            class="bo-btn-warning"
                            onclick="boReturnOzToggle(<?= $cId ?>)">
                        ↩ Vrátit OZ
                    </button>

                    <button type="button"
                            class="bo-btn-primary"
                            <?= $canClose ? '' : 'disabled' ?>
                            onclick="boCloseToggle(<?= $cId ?>)"
                            title="<?= $canClose ? 'Otevřít formulář pro uzavření smlouvy' : 'Nejprve zaškrtněte „Podpis potvrzen" v progress checkboxech' ?>">
                        <?= $canClose ? '✅ Uzavřít smlouvu' : '🔒 Uzavřít · čeká podpis' ?>
                    </button>

                    <button type="button"
                            class="bo-btn-negative"
                            onclick="boNezajemToggle(<?= $cId ?>)"
                            title="Označit jako nezájem (zákazník odmítl)">
                        ✗ Nezájem
                    </button>
                </div>

                <?php } elseif ($ozStav === 'BO_VRACENO') { ?>
                <!-- Vráceno OZ: jen Nezájem -->
                <div class="bo-card__actions">
                    <span class="bo-card__actions-info">
                        ↩ Čeká, až OZ doplní informace a kontakt vrátí.
                    </span>
                    <button type="button"
                            class="bo-btn-negative"
                            onclick="boNezajemToggle(<?= $cId ?>)"
                            title="Označit jako nezájem (zákazník odmítl)">
                        ✗ Nezájem
                    </button>
                </div>

                <?php } elseif ($ozStav === 'UZAVRENO') { ?>
                <!-- Uzavřeno: Otevřít znovu -->
                <div class="bo-card__actions">
                    <form method="post" action="<?= crm_h(crm_url('/bo/reopen')) ?>"
                          style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;flex:1 1 auto;"
                          onsubmit="return confirm('Otevřít kontakt znovu? Vrátí se do V práci.');">
                        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                        <input type="hidden" name="contact_id" value="<?= $cId ?>">
                        <input type="hidden" name="tab" value="<?= crm_h($tab) ?>">
                        <input type="text" name="reason" maxlength="500"
                               placeholder="Důvod znovuotevření (volitelné)"
                               style="flex:1 1 220px;background:var(--bo-bg);color:var(--bo-text);
                                      border:1px solid var(--bo-border);border-radius:5px;
                                      padding:0.35rem 0.5rem;font-size:0.78rem;font-family:inherit;">
                        <button type="submit" class="bo-btn-secondary">
                            🔄 Otevřít znovu
                        </button>
                    </form>
                </div>
                <?php } ?>

                <!-- ── SKRYTÝ FORM: UZAVŘÍT SMLOUVU (BO_VPRACI) ── -->
                <?php if ($ozStav === 'BO_VPRACI') {
                    $cisloSmlExisting   = (string) ($c['cislo_smlouvy']        ?? '');
                    $datumUzavExisting  = (string) ($c['datum_uzavreni']       ?? '');
                    $trvaniExisting     = (int)    ($c['smlouva_trvani_roky']  ?? 3);
                ?>
                <div id="bo-close-form-<?= $cId ?>" class="bo-inline-form bo-inline-form--close">
                    <form method="post" action="<?= crm_h(crm_url('/bo/close')) ?>"
                          style="display:flex;flex-direction:column;gap:0.55rem;">
                        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                        <input type="hidden" name="contact_id" value="<?= $cId ?>">
                        <input type="hidden" name="tab" value="<?= crm_h($tab) ?>">
                        <label class="bo-inline-form__label">✅ Uzavření smlouvy — vyplňte detaily:</label>

                        <div class="bo-close-form__grid">
                            <div>
                                <label for="cislo-sml-<?= $cId ?>">Číslo smlouvy *</label>
                                <input id="cislo-sml-<?= $cId ?>" type="text" name="cislo_smlouvy"
                                       required maxlength="50"
                                       value="<?= crm_h($cisloSmlExisting) ?>"
                                       placeholder="např. SML-2026-0123">
                            </div>
                            <div>
                                <label for="datum-uz-<?= $cId ?>">Datum uzavření *</label>
                                <input id="datum-uz-<?= $cId ?>" type="date" name="datum_uzavreni" required
                                       value="<?= crm_h($datumUzavExisting !== '' ? $datumUzavExisting : date('Y-m-d')) ?>"
                                       max="<?= date('Y-m-d') ?>">
                            </div>
                            <div>
                                <label for="trvani-<?= $cId ?>">Trvání</label>
                                <select id="trvani-<?= $cId ?>" name="smlouva_trvani_roky">
                                    <?php foreach ([1,2,3,5,10] as $opt) { ?>
                                        <option value="<?= $opt ?>" <?= $opt === ($trvaniExisting ?: 3) ? 'selected' : '' ?>>
                                            <?= $opt ?> <?= $opt === 1 ? 'rok' : ($opt < 5 ? 'roky' : 'let') ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>

                        <textarea name="note" maxlength="1000" rows="2"
                                  placeholder="Poznámka (volitelná) — např. detail balíčku, upsell, atd."></textarea>

                        <div class="bo-inline-form__actions">
                            <button type="button" class="bo-btn-ghost"
                                    onclick="boCloseToggle(<?= $cId ?>)">Zrušit</button>
                            <button type="submit" class="bo-inline-form__submit">
                                ✅ Potvrdit uzavření
                            </button>
                        </div>
                        <small class="bo-inline-form__hint">
                            ℹ Po uzavření se automaticky nastaví výročí na
                            <strong id="vyroci-preview-<?= $cId ?>">datum + 3 roky</strong>.
                            Číslo smlouvy musí být unikátní v celém systému.
                        </small>
                    </form>
                </div>
                <?php } ?>

                <!-- ── SKRYTÝ FORM: NEZÁJEM (BO_PREDANO / BO_VPRACI / BO_VRACENO / SMLOUVA) ── -->
                <?php if (in_array($ozStav, ['BO_PREDANO','BO_VPRACI','BO_VRACENO','SMLOUVA'], true)) { ?>
                <div id="bo-nezajem-form-<?= $cId ?>" class="bo-inline-form bo-inline-form--nezajem">
                    <form method="post" action="<?= crm_h(crm_url('/bo/nezajem')) ?>"
                          style="display:flex;flex-direction:column;gap:0.5rem;">
                        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                        <input type="hidden" name="contact_id" value="<?= $cId ?>">
                        <input type="hidden" name="tab" value="<?= crm_h($tab) ?>">
                        <label class="bo-inline-form__label">✗ Důvod nezájmu (povinné):</label>
                        <textarea name="reason" required maxlength="1000" rows="2"
                                  placeholder="např. „Zákazník odmítl podepsat", „Špatné kontaktní údaje", „Nemá zájem o žádnou službu"…"></textarea>
                        <div class="bo-inline-form__actions">
                            <button type="button" class="bo-btn-ghost"
                                    onclick="boNezajemToggle(<?= $cId ?>)">Zrušit</button>
                            <button type="submit" class="bo-inline-form__submit">
                                ✗ Označit jako nezájem
                            </button>
                        </div>
                    </form>
                </div>
                <?php } ?>

                <!-- ── SKRYTÝ FORM: VRÁTIT OZ (BO_VPRACI) ── -->
                <?php if ($ozStav === 'BO_VPRACI') { ?>
                <div id="bo-return-form-<?= $cId ?>" class="bo-inline-form bo-inline-form--vraceno">
                    <form method="post" action="<?= crm_h(crm_url('/bo/return-oz')) ?>"
                          style="display:flex;flex-direction:column;gap:0.5rem;">
                        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                        <input type="hidden" name="contact_id" value="<?= $cId ?>">
                        <input type="hidden" name="tab" value="<?= crm_h($tab) ?>">
                        <label class="bo-inline-form__label">↩ Důvod vrácení OZ (povinné):</label>
                        <textarea name="reason" required maxlength="1000" rows="2"
                                  placeholder="např. „Chybí OKU pro 731170559", „Špatné číslo nabídky", „Nesedí adresa instalace"…"></textarea>
                        <div class="bo-inline-form__actions">
                            <button type="button" class="bo-btn-ghost"
                                    onclick="boReturnOzToggle(<?= $cId ?>)">Zrušit</button>
                            <button type="submit" class="bo-inline-form__submit">
                                ↩ Odeslat zpět OZ
                            </button>
                        </div>
                    </form>
                </div>
                <?php } ?>
            </div>
            <?php } ?>
        </div>
    <?php } ?>

</section>

<script>
// Toggle "Uzavřít smlouvu" formulář (číslo + datum + trvání)
function boCloseToggle(cId) {
    const wrap = document.getElementById('bo-close-form-' + cId);
    if (!wrap) return;
    const isOpen = wrap.classList.contains('visible');
    wrap.classList.toggle('visible');
    if (!isOpen) {
        setTimeout(() => {
            const inp = wrap.querySelector('input[name="cislo_smlouvy"]');
            if (inp) inp.focus();
        }, 30);
    }
    // Live preview výročí (datum_uzavreni + trvani let)
    const dateInput   = wrap.querySelector('input[name="datum_uzavreni"]');
    const trvaniInput = wrap.querySelector('select[name="smlouva_trvani_roky"]');
    const preview     = wrap.querySelector('#vyroci-preview-' + cId);
    function updatePreview() {
        if (!dateInput || !trvaniInput || !preview) return;
        const d = new Date(dateInput.value);
        const t = parseInt(trvaniInput.value, 10) || 3;
        if (!isNaN(d.getTime())) {
            d.setFullYear(d.getFullYear() + t);
            preview.textContent = d.toLocaleDateString('cs-CZ');
        } else {
            preview.textContent = 'datum + ' + t + ' let';
        }
    }
    if (dateInput && !dateInput._previewBound) {
        dateInput.addEventListener('input', updatePreview);
        dateInput._previewBound = true;
    }
    if (trvaniInput && !trvaniInput._previewBound) {
        trvaniInput.addEventListener('change', updatePreview);
        trvaniInput._previewBound = true;
    }
    updatePreview();
}

// Toggle "Vrátit OZ" formulář (důvod vrácení)
function boReturnOzToggle(cId) {
    const wrap = document.getElementById('bo-return-form-' + cId);
    if (!wrap) return;
    const isOpen = wrap.classList.contains('visible');
    wrap.classList.toggle('visible');
    if (!isOpen) {
        setTimeout(() => {
            const ta = wrap.querySelector('textarea[name="reason"]');
            if (ta) ta.focus();
        }, 30);
    }
}

// Toggle "Nezájem" formulář (důvod)
function boNezajemToggle(cId) {
    const wrap = document.getElementById('bo-nezajem-form-' + cId);
    if (!wrap) return;
    const isOpen = wrap.classList.contains('visible');
    wrap.classList.toggle('visible');
    if (!isOpen) {
        setTimeout(() => {
            const ta = wrap.querySelector('textarea[name="reason"]');
            if (ta) ta.focus();
        }, 30);
    }
}

// BO progress checkbox toggle (AJAX) — nezměněno, jen kosmetický update labelu
function boCheckboxToggle(input) {
    if (!input) return;
    const cId     = input.dataset.cbCid || '';
    const field   = input.dataset.cbField || '';
    const tab     = input.dataset.cbTab || '';
    const checked = input.checked ? 1 : 0;

    input.disabled = true;

    const fd = new FormData();
    fd.append('<?= crm_h(crm_csrf_field_name()) ?>', '<?= crm_h($csrf) ?>');
    fd.append('contact_id', cId);
    fd.append('field', field);
    fd.append('tab', tab);
    if (checked) fd.append('checked', '1');

    fetch('<?= crm_h(crm_url('/bo/checkbox-toggle')) ?>', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        input.disabled = false;
        if (!data || !data.ok) {
            input.checked = !checked;
            if (data && data.error) alert(data.error);
            return;
        }
        // Při zaškrtnutí "Podpis potvrzen" potřebujeme reload —
        // spustí přepočet BMSL u OZ + odemkne tlačítko "Uzavřít smlouvu".
        if (field === 'podpis_potvrzen') {
            setTimeout(() => { window.location.reload(); }, 250);
            return;
        }
        // Ostatní checkboxy: jen visualní aktualizace (toggle 'bo-cb-row--checked')
        const label = input.closest('label');
        if (label) {
            if (checked) {
                label.classList.add('bo-cb-row--checked');
            } else {
                label.classList.remove('bo-cb-row--checked');
            }
        }
    })
    .catch(() => {
        input.disabled = false;
        input.checked = !checked;
        alert('Chyba sítě — zkuste to znovu.');
    });
}
</script>
