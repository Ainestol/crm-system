<?php
// e:\Snecinatripu\app\views\cisticka\index.php
declare(strict_types=1);
/** @var array<string,mixed>       $user */
/** @var list<array<string,mixed>> $contacts */
/** @var string                    $tab */
/** @var int                       $page */
/** @var int                       $totalPages */
/** @var int                       $totalCount */
/** @var int                       $newCount */
/** @var array<string,mixed>       $todayStats */
/** @var list<string>              $availableRegions */
/** @var string                    $selectedRegion */
/** @var array<string,int>         $regionCounts */
/** @var string|null               $flash */
/** @var string                    $csrf */

$readyToday = (int) ($todayStats['ready_count'] ?? 0);
$vfToday    = (int) ($todayStats['vf_count']    ?? 0);
$totalToday = (int) ($todayStats['total_today'] ?? 0);
/** @var list<array<string,mixed>> $regionGoals — kraje s cílem + progress pro VYBRANÝ měsíc */
/** @var string $monthLabel                     — např. "květen 2026" (vybraný měsíc) */
/** @var bool   $hasGoals                       — jsou nějaké aktivní goals pro AKTUÁLNÍ měsíc? */
/** @var bool   $strictEmpty                    — K-ověření tab + žádné current-month goals */
/** @var list<array{key:string,period:int,label:string}> $monthOptions  možnosti pro <select> */
/** @var string $selectedMonthKey  — "YYYY-MM" pro <select>/URL */
/** @var bool   $isCurrentPeriod */
/** @var bool   $isPastPeriod */
/** @var bool   $isFuturePeriod */
/** @var int    $zkontrolovaneTotal — počet vsech kontaktů které čistička kdy verifikovala */
$regionGoals       = $regionGoals       ?? [];
$monthLabel        = $monthLabel        ?? '';
$hasGoals          = $hasGoals          ?? ($regionGoals !== []);
$strictEmpty       = $strictEmpty       ?? false;
$monthOptions      = $monthOptions      ?? [];
$selectedMonthKey  = $selectedMonthKey  ?? '';
$isCurrentPeriod   = $isCurrentPeriod   ?? true;
$isPastPeriod      = $isPastPeriod      ?? false;
$isFuturePeriod    = $isFuturePeriod    ?? false;
$zkontrolovaneTotal = $zkontrolovaneTotal ?? 0;

// Identifikuj index první nesplněné tile (podle pořadí v $regionGoals,
// které je už seřazené ASC priority, ASC region) — dostane focus class.
$focusIndex = -1;
foreach ($regionGoals as $idx => $g) {
    if (empty($g['completed'])) {
        $focusIndex = $idx;
        break;
    }
}

// Globální URL helper — propaguje month_key, aby přepínač
// neztrácel context při klikání na tiles, paginaci, taby.
// (Definovaný jako closure aby měl přístup k $selectedMonthKey;
//  použitý všude místo cistPageUrl.)
$cistUrl = function (array $params) use ($selectedMonthKey, $isCurrentPeriod): string {
    if (!$isCurrentPeriod && $selectedMonthKey !== '') {
        $params['month_key'] = $selectedMonthKey;
    }
    return crm_url('/cisticka?' . http_build_query($params));
};

function cistPagination(int $page, int $totalPages, string $tab, string $selectedRegion, callable $urlFn): void {
    if ($totalPages <= 1) return;
    echo '<div class="cist-pagination">';

    $mkParams = static function (int $p) use ($tab, $selectedRegion): array {
        $q = ['tab' => $tab, 'page' => $p];
        if ($selectedRegion !== '') $q['region'] = $selectedRegion;
        return $q;
    };

    if ($page > 1) {
        echo '<a href="' . crm_h($urlFn($mkParams($page - 1))) . '" class="btn btn-secondary btn-sm">← Předchozí</a>';
    } else {
        echo '<span class="cist-page-btn cist-page-disabled">←</span>';
    }

    $from = max(1, $page - 3);
    $to   = min($totalPages, $page + 3);

    if ($from > 1) {
        echo '<a href="' . crm_h($urlFn($mkParams(1))) . '" class="cist-page-btn">1</a>';
        if ($from > 2) echo '<span class="cist-page-dots">…</span>';
    }
    for ($i = $from; $i <= $to; $i++) {
        if ($i === $page) {
            echo '<span class="cist-page-btn cist-page-current">' . $i . '</span>';
        } else {
            echo '<a href="' . crm_h($urlFn($mkParams($i))) . '" class="cist-page-btn">' . $i . '</a>';
        }
    }
    if ($to < $totalPages) {
        if ($to < $totalPages - 1) echo '<span class="cist-page-dots">…</span>';
        echo '<a href="' . crm_h($urlFn($mkParams($totalPages))) . '" class="cist-page-btn">' . $totalPages . '</a>';
    }

    if ($page < $totalPages) {
        echo '<a href="' . crm_h($urlFn($mkParams($page + 1))) . '" class="btn btn-secondary btn-sm">Další →</a>';
    } else {
        echo '<span class="cist-page-btn cist-page-disabled">→</span>';
    }

    echo '</div>';
}
?>
<section class="card">
    <h1>Ověřování kontaktů</h1>
    <p class="muted">Přihlášena: <strong><?= crm_h((string) ($user['jmeno'] ?? '')) ?></strong></p>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info"><?= crm_h($flash) ?></p>
    <?php } ?>

    <!-- Denní statistika -->
    <div class="cist-stats">
        <div class="cist-stat-box cist-stat-total">
            <span class="cist-stat-num" id="stat-total"><?= $totalToday ?></span>
            <span class="cist-stat-label">dnes celkem</span>
        </div>
        <div class="cist-stat-box cist-stat-tm">
            <span class="cist-stat-num" id="stat-ready"><?= $readyToday ?></span>
            <span class="cist-stat-label">TM + O2</span>
        </div>
        <div class="cist-stat-box cist-stat-vf">
            <span class="cist-stat-num" id="stat-vf"><?= $vfToday ?></span>
            <span class="cist-stat-label">VF přeskočeno</span>
        </div>
        <div class="cist-stat-box cist-stat-queue">
            <span class="cist-stat-num" id="stat-queue"><?= $newCount ?></span>
            <span class="cist-stat-label">zbývá k ověření</span>
        </div>
    </div>

    <!-- ── Měsíční přepínač (read-only přehled cílů + progress) ─────────────
         Přepnutí na minulý / budoucí měsíc OVLIVNÍ POUZE TILES.
         K-ověření / Zkontrolováno seznamy zůstávají v aktuálním stavu —
         čistička nemůže pracovat zpětně, a Zkontrolováno je all-time history.
         Hidden form approach: změna selectu auto-submituje GET. -->
    <?php if (count($monthOptions) > 0) { ?>
    <form method="get" action="<?= crm_h(crm_url('/cisticka')) ?>" class="cist-month-switch" id="cistMonthSwitch">
        <!-- Zachovat aktuální tab + region pri přepínání měsíce -->
        <?php if ($tab !== '')             { ?><input type="hidden" name="tab"    value="<?= crm_h($tab) ?>"><?php } ?>
        <?php if ($selectedRegion !== '')  { ?><input type="hidden" name="region" value="<?= crm_h($selectedRegion) ?>"><?php } ?>
        <label for="cist-month-key">📅 Měsíc:</label>
        <select id="cist-month-key" name="month_key" onchange="document.getElementById('cistMonthSwitch').submit();">
            <?php foreach ($monthOptions as $opt) { ?>
                <option value="<?= crm_h($opt['key']) ?>"<?= ($opt['key'] === $selectedMonthKey) ? ' selected' : '' ?>>
                    <?= crm_h($opt['label']) ?>
                </option>
            <?php } ?>
        </select>
        <?php if ($isCurrentPeriod) { ?>
            <span class="cist-month-badge cist-month-badge--current">Aktuální</span>
        <?php } elseif ($isPastPeriod) { ?>
            <span class="cist-month-badge cist-month-badge--past">Historie</span>
        <?php } else { ?>
            <span class="cist-month-badge cist-month-badge--future">Plán</span>
        <?php } ?>
        <noscript><button type="submit" class="btn btn-secondary btn-sm">Načíst</button></noscript>
    </form>

    <?php if ($isPastPeriod) { ?>
        <div class="cist-period-notice cist-period-notice--past">
            ⚠ <strong>Historický přehled —</strong> prohlížíš si cíle a progress za <?= crm_h($monthLabel) ?>.
            Seznamy <em>K&nbsp;ověření</em> a <em>Zkontrolováno</em> zůstávají v aktuálním stavu (nelze čistit zpětně).
        </div>
    <?php } elseif ($isFuturePeriod) { ?>
        <div class="cist-period-notice cist-period-notice--future">
            🔮 <strong>Plánovaný měsíc —</strong> cíle pro <?= crm_h($monthLabel) ?>.
            Progress bude růst, jakmile bude tento měsíc aktuální.
        </div>
    <?php } ?>
    <?php } ?>

    <!-- ── Progress panel + region filtr (klikatelné tiles) ──────────────────
         Tiles fungují zároveň jako:
           1) progress bar (kolik z cíle už hotovo)
           2) FILTR kontaktů — klik na tile filtruje seznam na daný kraj
         "Vše" tile zruší filtr (zobrazí kontakty všech goal-krajů dohromady).
         Splněné tiles jsou stále klikatelné (i když mají 100 %).
         První nesplněná tile (podle priority) dostává focus class — vizuální
         "začni zde" indikátor. -->
    <?php if ($regionGoals !== []) { ?>
    <div class="cist-goals" id="cist-goals">
        <div class="cist-goals__title">
            🎯 Cíle podle krajů
            <?php if ($monthLabel !== '') { ?>
                <span style="font-size:0.78rem;color:var(--muted);font-weight:normal;margin-left:0.4rem;">
                    · <?= crm_h($monthLabel) ?>
                </span>
            <?php } ?>
            <?php if ($isCurrentPeriod) { ?>
            <span style="font-size:0.72rem;color:var(--muted);font-weight:normal;margin-left:0.6rem;">
                · klikni pro filtr
            </span>
            <?php } ?>
        </div>
        <div class="cist-goals__grid">
            <?php
            // Tiles jsou klikatelné JEN v aktuálním měsíci — past/future view
            // je read-only přehled (filter logika běží přes current goal-regions
            // a kliknutí na past tile by se neaplikovalo nebo bylo matoucí).
            $tilesClickable = $isCurrentPeriod;
            $allActive = ($selectedRegion === '');
            $allHref   = $tilesClickable ? $cistUrl(['tab' => $tab, 'page' => 1]) : '';
            ?>
            <!-- "Vše" tile -->
            <?php if ($tilesClickable) { ?>
            <a href="<?= crm_h($allHref) ?>"
               class="cist-goal cist-goal--all cist-goal--clickable <?= $allActive ? 'cist-goal--active' : '' ?>"
               title="Zobrazit kontakty ze všech krajů s cílem">
                <div class="cist-goal__head">
                    <span class="cist-goal__label">🗺️ Vše</span>
                </div>
                <div class="cist-goal__status">
                    <span style="color:var(--muted);">Všechny goal kraje</span>
                </div>
            </a>
            <?php } ?>

            <?php foreach ($regionGoals as $idx => $g) {
                $reg     = (string) $g['region'];
                $label   = (string) $g['label'];
                $target  = (int) $g['target'];
                $done    = (int) $g['done'];
                $pct     = (int) $g['percent'];
                $done100 = (bool) $g['completed'];
                $prio    = (int) ($g['priority'] ?? 5);
                $isFocus = ($tilesClickable && $idx === $focusIndex); // jen v current period
                $isActive = ($selectedRegion === $reg);
                $tileHref = $cistUrl([
                    'tab' => $tab, 'page' => 1, 'region' => $reg,
                ]);
                $newCnt = (int) ($regionCounts[$reg] ?? 0);

                $cls = 'cist-goal';
                if ($tilesClickable) $cls .= ' cist-goal--clickable';
                else                 $cls .= ' cist-goal--readonly';
                if ($done100)        $cls .= ' cist-goal--done';
                if ($isFocus)        $cls .= ' cist-goal--focus';
                if ($isActive && $tilesClickable) $cls .= ' cist-goal--active';

                $tileTag    = $tilesClickable ? 'a' : 'div';
                $tileTitle  = $done100
                    ? 'Hotovo'
                    : ($tilesClickable ? 'Klikni pro filtr na tento kraj' : 'Historický náhled — read only');
            ?>
            <<?= $tileTag ?><?php if ($tilesClickable) { ?> href="<?= crm_h($tileHref) ?>"<?php } ?>
               class="<?= crm_h($cls) ?>"
               id="goal-<?= crm_h($reg) ?>"
               data-region="<?= crm_h($reg) ?>"
               data-target="<?= $target ?>"
               data-done="<?= $done ?>"
               title="<?= crm_h($tileTitle) ?>">
                <div class="cist-goal__head">
                    <span class="cist-goal__priority cist-goal__priority--p<?= $prio ?>"
                          title="Priorita <?= $prio ?> z 10">
                        ⭐<?= $prio ?>
                    </span>
                    <span class="cist-goal__label"><?= crm_h($label) ?></span>
                    <span class="cist-goal__count">
                        <strong class="cist-goal__done"><?= $done ?></strong>
                        / <?= $target ?>
                    </span>
                </div>
                <div class="cist-goal__bar">
                    <div class="cist-goal__fill" style="width:<?= $pct ?>%"></div>
                </div>
                <div class="cist-goal__status">
                    <?php if ($isFocus) { ?>
                        <span class="cist-goal__focus-hint">👈 Začni zde · priorita <?= $prio ?></span>
                    <?php } elseif ($done100) { ?>
                        <span style="color:var(--muted);">✓ Hotovo · čeká na nový cíl</span>
                    <?php } else { ?>
                        <span style="color:var(--muted);">
                            Zbývá: <?= max(0, $target - $done) ?> · <?= $pct ?> %
                        </span>
                    <?php } ?>
                    <?php /* Badge "X NEW" pouze na záložce K-ověření — jinak by
                              ukazovala historický count, což je zavádějící. */ ?>
                    <?php if ($tab === 'overit' && $newCnt > 0 && !$done100) { ?>
                        <span class="cist-goal__newcnt" id="goal-newcnt-<?= crm_h($reg) ?>">
                            <?= $newCnt ?> NEW
                        </span>
                    <?php } ?>
                </div>
            </<?= $tileTag ?>>
            <?php } ?>
        </div>
    </div>
    <?php } ?>

    <!-- Spodní region filtr — VIDITELNÝ POUZE když:
           - nejsou goals (legacy fallback)
           - A NENÍ to strict-empty stav v K-ověření (tam by jen mátl).
         Když má čistička goals, filtr je sloučený do tiles výše (klikatelné). -->
    <?php if (!$hasGoals && !$strictEmpty && $availableRegions !== []) { ?>
        <?php
        $activeRegionLabel = $selectedRegion === ''
            ? '🗺️ Všechny kraje'
            : crm_region_label($selectedRegion);
        ?>
        <details class="cist-region-collapse" <?= $selectedRegion === '' ? '' : 'open' ?>>
            <summary class="cist-region-summary">
                <span class="cist-region-label">Kraj:</span>
                <strong><?= crm_h($activeRegionLabel) ?></strong>
                <span class="cist-region-toggle">▾ rozbalit</span>
            </summary>
            <div class="cist-region-filter">
                <a href="<?= crm_h($cistUrl(['tab' => $tab, 'page' => 1])) ?>"
                   class="cist-region-btn <?= $selectedRegion === '' ? 'cist-region-btn--active' : '' ?>">
                    🗺️ Vše
                </a>
                <?php foreach ($availableRegions as $reg) {
                    $cnt = $regionCounts[$reg] ?? 0;
                    $cntClass = $tab === 'overit' ? 'cist-region-cnt cist-region-cnt--new' : 'cist-region-cnt';
                ?>
                    <a href="<?= crm_h($cistUrl(['tab' => $tab, 'page' => 1, 'region' => $reg])) ?>"
                       class="cist-region-btn <?= $selectedRegion === $reg ? 'cist-region-btn--active' : '' ?>">
                        <?= crm_h(crm_region_label($reg)) ?>
                        <span class="<?= $cntClass ?>" id="region-cnt-<?= crm_h($reg) ?>"><?= $cnt ?></span>
                    </a>
                <?php } ?>
            </div>
        </details>
    <?php } ?>

    <!-- Taby -->
    <div class="tabs" style="margin-top:1rem;">
        <?php
        $overitQ = array_filter(['tab' => 'overit', 'region' => $selectedRegion]);
        $zkontQ  = array_filter(['tab' => 'zkontrolovano', 'region' => $selectedRegion]);
        ?>
        <a href="<?= crm_h($cistUrl($overitQ)) ?>"
           class="tab <?= $tab === 'overit' ? 'tab--active' : '' ?>">
            K ověření
            <?php if ($newCount > 0) { ?>
                <span class="badge" id="tab-badge-new"><?= $newCount ?></span>
            <?php } ?>
        </a>
        <a href="<?= crm_h($cistUrl($zkontQ)) ?>"
           class="tab <?= $tab === 'zkontrolovano' ? 'tab--active' : '' ?>"
           title="Všeho času zkontrolovaných kontaktů">
            Zkontrolováno
            <span class="badge" id="tab-badge-done"
                  style="background:rgba(46,204,113,0.2);color:#2ecc71;"
                  data-today="<?= $totalToday ?>"
                  title="Celkem <?= $zkontrolovaneTotal ?> kontaktů (z toho <?= $totalToday ?> dnes)"><?= $zkontrolovaneTotal ?></span>
        </a>
        <a href="<?= crm_h(crm_url('/cisticka/stats')) ?>" class="tab tab--stats">📊 Výkon</a>
    </div>

    <?php if ($tab === 'overit') { ?>
        <!-- ── K OVĚŘENÍ ── -->
        <?php if ($strictEmpty) { ?>
            <!-- Strict empty state: čistička nemá pro aktuální měsíc nastavené žádné cíle -->
            <div class="cist-empty-strict" style="margin-top:1.5rem;padding:1.4rem 1.6rem;border-radius:10px;background:rgba(241,196,15,0.07);border:1px solid rgba(241,196,15,0.25);color:var(--text);">
                <div style="font-size:1.1rem;font-weight:600;margin-bottom:0.4rem;">⏳ Počkej, až admin nastaví cíle</div>
                <p style="font-size:0.88rem;color:var(--muted);line-height:1.55;margin:0;">
                    Pro <strong><?= crm_h($monthLabel) ?></strong> zatím nejsou nastavené žádné cíle podle krajů.<br>
                    Jakmile majitel/admin nastaví cíle (Cíle čističky podle krajů), zobrazí se ti tady kontakty k ověření.
                </p>
            </div>
        <?php } elseif ($contacts === []) { ?>
            <p class="muted" style="margin-top:1.5rem;">
                ✅ Vše zkontrolováno<?php if ($selectedRegion !== '') { ?> v kraji <strong><?= crm_h(crm_region_label($selectedRegion)) ?></strong><?php } ?>.
            </p>
        <?php } else { ?>

            <!-- Topbar: info + paginace nahoře -->
            <div class="cist-topbar">
                <span class="muted">
                    Strana <?= $page ?> / <?= $totalPages ?>
                    &nbsp;·&nbsp; <?= $totalCount ?> kontaktů
                    <?php if ($selectedRegion !== '') { ?>
                        &nbsp;·&nbsp; <strong><?= crm_h(crm_region_label($selectedRegion)) ?></strong>
                    <?php } ?>
                </span>
                <?php cistPagination($page, $totalPages, $tab, $selectedRegion, $cistUrl); ?>
            </div>

            <div class="cist-list" id="cist-list">
                <?php foreach ($contacts as $c) {
                    $cId       = (int) $c['id'];
                    $currentOp = strtoupper(trim((string) ($c['operator'] ?? '')));
                    $opClass   = match ($currentOp) {
                        'VF' => 'op-vf', 'TM' => 'op-tm', 'O2' => 'op-o2', default => 'op-unknown',
                    };
                ?>
                    <div class="cist-row" id="cist-row-<?= $cId ?>" data-region="<?= crm_h((string) ($c['region'] ?? '')) ?>" data-cid="<?= $cId ?>">
                        <div class="cist-info">
                            <span class="cist-firma"><?= crm_h((string) ($c['firma'] ?? '—')) ?></span>
                            <span class="cist-phone"><?= crm_h((string) ($c['telefon'] ?? '—')) ?></span>
                            <span class="cist-op-badge <?= $opClass ?>"><?= $currentOp !== '' ? crm_h($currentOp) : '?' ?></span>
                            <span class="cist-region muted"><?= crm_h((string) ($c['region'] ?? '')) ?></span>
                        </div>
                        <div class="cist-actions">
                            <button type="button" class="btn-cist-vf" onclick="cistVerify(<?= $cId ?>, 'vf_skip', this)" title="Klávesa: 1">
                                🔴 VF<span class="cist-kbd-hint">1</span>
                            </button>
                            <button type="button" class="btn-cist-tm" onclick="cistVerify(<?= $cId ?>, 'tm', this)" title="Klávesa: 2">
                                🌸 TM<span class="cist-kbd-hint">2</span>
                            </button>
                            <button type="button" class="btn-cist-o2" onclick="cistVerify(<?= $cId ?>, 'o2', this)" title="Klávesa: 3">
                                🔵 O2<span class="cist-kbd-hint">3</span>
                            </button>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <!-- Paginace dole -->
            <div class="cist-footer">
                <?php cistPagination($page, $totalPages, $tab, $selectedRegion, $cistUrl); ?>
            </div>
        <?php } ?>

    <?php } else { ?>
        <!-- ── ZKONTROLOVÁNO ── -->
        <?php if ($contacts === []) { ?>
            <p class="muted" style="margin-top:1.5rem;">Zatím nic zkontrolováno.</p>
        <?php } else { ?>

            <!-- Topbar -->
            <div class="cist-topbar">
                <span class="muted">Strana <?= $page ?> / <?= $totalPages ?> · <?= $totalCount ?> celkem</span>
                <?php cistPagination($page, $totalPages, $tab, $selectedRegion, $cistUrl); ?>
            </div>

            <div class="cist-list">
                <?php foreach ($contacts as $c) {
                    $cId        = (int) $c['id'];
                    $op         = strtoupper(trim((string) ($c['operator'] ?? '')));
                    $stav       = (string) ($c['stav'] ?? '');
                    $rowClass   = match ($op) {
                        'VF' => 'cist-row--vf', 'TM' => 'cist-row--tm', 'O2' => 'cist-row--o2', default => '',
                    };
                    $verifiedAt = (string) ($c['verified_at'] ?? '');
                ?>
                    <div class="cist-row <?= $rowClass ?>" id="zkont-row-<?= $cId ?>">
                        <div class="cist-info">
                            <span class="cist-firma"><?= crm_h((string) ($c['firma'] ?? '—')) ?></span>
                            <span class="cist-phone"><?= crm_h((string) ($c['telefon'] ?? '—')) ?></span>
                            <span class="cist-op-badge <?= 'op-' . strtolower($op) ?>" id="zkont-badge-<?= $cId ?>"><?= crm_h($op !== '' ? $op : '?') ?></span>
                            <span class="cist-region muted"><?= crm_h((string) ($c['region'] ?? '')) ?></span>
                        </div>
                        <div class="cist-verified-info">
                            <?php if ($verifiedAt !== '') {
                                // Datum + čas — pokud je z dneška, jen čas; pokud je
                                // ze stejného roku, "D.M. HH:MM"; jinak "D.M.YYYY HH:MM".
                                // Důvod: čistička často kouká na Zkontrolováno a chce
                                // hned vidět, kdy přesně to udělala (pomáhá poznat,
                                // zda se to počítá do aktuálního měsíčního cíle nebo ne).
                                $verTs   = strtotime((string) $verifiedAt);
                                $isToday = ($verTs !== false && date('Y-m-d', $verTs) === date('Y-m-d'));
                                $isYear  = ($verTs !== false && date('Y', $verTs) === date('Y'));
                                if ($isToday) {
                                    $verLabel = date('H:i', $verTs);
                                } elseif ($isYear) {
                                    $verLabel = date('j.n.', $verTs) . ' ' . date('H:i', $verTs);
                                } else {
                                    $verLabel = date('j.n.Y', $verTs) . ' ' . date('H:i', $verTs);
                                }
                            ?>
                                <span class="muted" style="font-size:0.75rem;"
                                      title="<?= crm_h((string) $verifiedAt) ?>"><?= crm_h($verLabel) ?></span>
                            <?php } ?>
                            <!-- Překlasifikační tlačítka -->
                            <div class="cist-reclassify" id="zkont-actions-<?= $cId ?>">
                                <?php if ($op !== 'VF') { ?>
                                    <button type="button" class="btn-cist-vf btn-cist-sm"
                                            onclick="cistReclassify(<?= $cId ?>, 'vf_skip', this)">🔴 VF</button>
                                <?php } ?>
                                <?php if ($op !== 'TM') { ?>
                                    <button type="button" class="btn-cist-tm btn-cist-sm"
                                            onclick="cistReclassify(<?= $cId ?>, 'tm', this)">🌸 TM</button>
                                <?php } ?>
                                <?php if ($op !== 'O2') { ?>
                                    <button type="button" class="btn-cist-o2 btn-cist-sm"
                                            onclick="cistReclassify(<?= $cId ?>, 'o2', this)">🔵 O2</button>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <!-- Paginace dole -->
            <div class="cist-footer">
                <?php cistPagination($page, $totalPages, $tab, $selectedRegion, $cistUrl); ?>
            </div>
        <?php } ?>
    <?php } ?>

    <div style="margin-top:1.5rem;">
        <a href="<?= crm_h(crm_url('/dashboard')) ?>" class="btn btn-secondary">Dashboard</a>
    </div>
</section>

<script>
var CIST_CSRF_FIELD     = <?= json_encode(crm_csrf_field_name()) ?>;
var CIST_CSRF_TOKEN     = <?= json_encode($csrf) ?>;
var CIST_VERIFY_URL     = <?= json_encode(crm_url('/cisticka/verify')) ?>;
var CIST_UNDO_URL       = <?= json_encode(crm_url('/cisticka/undo')) ?>;
var CIST_RECLASSIFY_URL = <?= json_encode(crm_url('/cisticka/reclassify')) ?>;

// Když je čistička v past/future view, tile data-target/data-done zobrazují
// historický progress, ne aktuální. JS by neměl tile inkrementovat při
// verifikaci (odpojí se od reality). Po reload se zobrazí správné historické
// hodnoty. Aktuální (current period) view JS update funguje normálně.
var CIST_IS_CURRENT_PERIOD = <?= json_encode((bool) $isCurrentPeriod) ?>;

var UNDO_SECONDS = 5;

/* ── Ověření jednoho kontaktu ─────────────────────────────────────── */
function cistVerify(contactId, action, btn) {
    var row = document.getElementById('cist-row-' + contactId);
    if (!row || row.dataset.done === '1') return;
    row.dataset.done = '1';

    var buttons = row.querySelectorAll('button');
    buttons.forEach(function(b) { b.disabled = true; });

    cistPost(CIST_VERIFY_URL, { contact_id: contactId, action: action })
        .then(function(data) {
            if (data.ok) {
                var op = data.operator;
                row.classList.remove('cist-row--vf', 'cist-row--tm', 'cist-row--o2');
                if (op === 'VF') row.classList.add('cist-row--vf');
                if (op === 'TM') row.classList.add('cist-row--tm');
                if (op === 'O2') row.classList.add('cist-row--o2');

                var icon = op === 'VF' ? '🔴 VF' : (op === 'TM' ? '🌸 TM' : '🔵 O2');
                var actDiv = row.querySelector('.cist-actions');
                if (actDiv) {
                    actDiv.innerHTML =
                        '<span class="cist-done-badge">✓ ' + icon + '</span>' +
                        '<button type="button" class="btn-cist-undo" id="undo-btn-' + contactId + '"' +
                        ' onclick="cistUndo(' + contactId + ', \'' + action + '\')">↩ Zpět (<span id="undo-cnt-' + contactId + '">' + UNDO_SECONDS + '</span>s)</button>';

                    cistStartUndoCountdown(contactId);
                }

                cistUpdateStats(op, +1);
            } else {
                row.dataset.done = '0';
                buttons.forEach(function(b) { b.disabled = false; });
                alert(data.error || 'Chyba při ukládání.');
            }
        })
        .catch(function() {
            row.dataset.done = '0';
            buttons.forEach(function(b) { b.disabled = false; });
            alert('Síťová chyba, zkuste znovu.');
        });
}

/* ── Undo (vrácení zpět do NEW) ───────────────────────────────────── */
function cistUndo(contactId, originalAction) {
    var btn = document.getElementById('undo-btn-' + contactId);
    if (btn) btn.disabled = true;

    cistPost(CIST_UNDO_URL, { contact_id: contactId })
        .then(function(data) {
            if (data.ok) {
                var row = document.getElementById('cist-row-' + contactId);
                if (row) {
                    row.dataset.done = '0';
                    row.classList.remove('cist-row--vf', 'cist-row--tm', 'cist-row--o2');

                    // Obnovit původní tlačítka (s klávesovými hinty)
                    var actDiv = row.querySelector('.cist-actions');
                    if (actDiv) {
                        actDiv.innerHTML =
                            '<button type="button" class="btn-cist-vf" onclick="cistVerify(' + contactId + ', \'vf_skip\', this)" title="Klávesa: 1">🔴 VF<span class="cist-kbd-hint">1</span></button>' +
                            '<button type="button" class="btn-cist-tm" onclick="cistVerify(' + contactId + ', \'tm\', this)" title="Klávesa: 2">🌸 TM<span class="cist-kbd-hint">2</span></button>' +
                            '<button type="button" class="btn-cist-o2" onclick="cistVerify(' + contactId + ', \'o2\', this)" title="Klávesa: 3">🔵 O2<span class="cist-kbd-hint">3</span></button>';
                    }
                }

                // Určit jaký operator byl (abychom odečetli ze stats)
                var wasOp = originalAction === 'vf_skip' ? 'VF' : (originalAction === 'tm' ? 'TM' : 'O2');
                cistUpdateStats(wasOp, -1);
            } else {
                if (btn) btn.disabled = false;
                alert(data.error || 'Undo se nezdařilo.');
            }
        })
        .catch(function() {
            if (btn) btn.disabled = false;
            alert('Síťová chyba.');
        });
}

/* ── Odpočítávání u undo tlačítka ─────────────────────────────────── */
function cistStartUndoCountdown(contactId) {
    var remaining = UNDO_SECONDS;
    var interval = setInterval(function() {
        remaining--;
        var cntEl = document.getElementById('undo-cnt-' + contactId);
        var btnEl = document.getElementById('undo-btn-' + contactId);
        if (!cntEl || !btnEl) { clearInterval(interval); return; }
        if (remaining <= 0) {
            clearInterval(interval);
            btnEl.style.display = 'none';
            // ── Po expiraci: odeber řádek z DOM + sniž počet kraje ──
            cistRemoveRow(contactId);
        } else {
            cntEl.textContent = remaining;
        }
    }, 1000);
}

/* ── Odebrání řádku z DOM po schválení (fade-out) ─────────────────── */
function cistRemoveRow(contactId) {
    var row = document.getElementById('cist-row-' + contactId);
    if (!row) return;

    // Dekrementuj počet kraje v region filtru (legacy spodní filtr).
    var region = row.dataset.region || '';
    if (region) {
        var badge = document.getElementById('region-cnt-' + region);
        if (badge) {
            var cur = parseInt(badge.textContent, 10) || 0;
            badge.textContent = Math.max(0, cur - 1);
        }
        // Také dekrementuj NEW counter na tile (nový region filter v goals).
        var goalNewBadge = document.getElementById('goal-newcnt-' + region);
        if (goalNewBadge) {
            var match = goalNewBadge.textContent.match(/^\s*(\d+)/);
            var n = match ? parseInt(match[1], 10) : 0;
            if (n > 0) {
                goalNewBadge.textContent = Math.max(0, n - 1) + ' NEW';
            }
        }
    }

    // Fade-out animace → pak remove
    row.style.transition = 'opacity 0.4s ease, max-height 0.4s ease, margin 0.4s ease, padding 0.4s ease';
    row.style.overflow = 'hidden';
    row.style.opacity = '0';
    row.style.maxHeight = row.offsetHeight + 'px';
    // Spustit v dalším frame aby transition proběhla
    requestAnimationFrame(function() {
        requestAnimationFrame(function() {
            row.style.maxHeight = '0';
            row.style.marginBottom = '0';
            row.style.paddingTop = '0';
            row.style.paddingBottom = '0';
        });
    });
    setTimeout(function() {
        if (row.parentNode) row.parentNode.removeChild(row);
        // Aktualizuj celkový počet stránky (text "X kontaktů")
        var totalEl = document.querySelector('.cist-topbar .muted');
        // (informativní – page count se sníží při refreshi stránky)
    }, 500);
}

/* ── Překlasifikace v záložce Zkontrolováno ──────────────────────── */
function cistReclassify(contactId, action, btn) {
    btn.disabled = true;

    cistPost(CIST_RECLASSIFY_URL, { contact_id: contactId, action: action })
        .then(function(data) {
            if (data.ok) {
                var op  = data.operator;
                var row = document.getElementById('zkont-row-' + contactId);
                if (row) {
                    row.classList.remove('cist-row--vf', 'cist-row--tm', 'cist-row--o2');
                    if (op === 'VF') row.classList.add('cist-row--vf');
                    if (op === 'TM') row.classList.add('cist-row--tm');
                    if (op === 'O2') row.classList.add('cist-row--o2');
                }

                // Aktualizuj badge operátora
                var badge = document.getElementById('zkont-badge-' + contactId);
                if (badge) {
                    badge.className = 'cist-op-badge op-' + op.toLowerCase();
                    badge.textContent = op;
                }

                // Přegeneruj tlačítka (skryj aktuální operator)
                var actDiv = document.getElementById('zkont-actions-' + contactId);
                if (actDiv) {
                    actDiv.innerHTML =
                        (op !== 'VF' ? '<button type="button" class="btn-cist-vf btn-cist-sm" onclick="cistReclassify(' + contactId + ', \'vf_skip\', this)">🔴 VF</button>' : '') +
                        (op !== 'TM' ? '<button type="button" class="btn-cist-tm btn-cist-sm" onclick="cistReclassify(' + contactId + ', \'tm\', this)">🌸 TM</button>' : '') +
                        (op !== 'O2' ? '<button type="button" class="btn-cist-o2 btn-cist-sm" onclick="cistReclassify(' + contactId + ', \'o2\', this)">🔵 O2</button>' : '');
                }
            } else {
                btn.disabled = false;
                alert(data.error || 'Chyba při překlasifikaci.');
            }
        })
        .catch(function() {
            btn.disabled = false;
            alert('Síťová chyba.');
        });
}

/* ── Aktualizace statistik ────────────────────────────────────────── */
function cistUpdateStats(op, delta) {
    var elTotal = document.getElementById('stat-total');
    var elReady = document.getElementById('stat-ready');
    var elVf    = document.getElementById('stat-vf');
    var elQueue = document.getElementById('stat-queue');
    var elNew   = document.getElementById('tab-badge-new');
    var elDone  = document.getElementById('tab-badge-done');

    if (delta > 0) {
        // Přidání (verify)
        if (elTotal) elTotal.textContent = (parseInt(elTotal.textContent, 10) || 0) + 1;
        if (op === 'VF') {
            if (elVf) elVf.textContent = (parseInt(elVf.textContent, 10) || 0) + 1;
        } else {
            if (elReady) elReady.textContent = (parseInt(elReady.textContent, 10) || 0) + 1;
        }
        if (elQueue) elQueue.textContent = Math.max(0, (parseInt(elQueue.textContent, 10) || 1) - 1);
        if (elNew)   elNew.textContent   = Math.max(0, (parseInt(elNew.textContent, 10)   || 1) - 1);
        // Zkontrolováno badge = all-time DISTINCT contact_id; verify nově ověřil,
        // takže počet roste o 1.
        if (elDone)  elDone.textContent  = (parseInt(elDone.textContent, 10) || 0) + 1;
    } else {
        // Odebrání (undo) — vrací kontakt zpět do NEW.
        // POZN: stat-total / stat-ready / stat-vf jsou DNEŠNÍ stats (DATE = today).
        // Pro DNEŠNÍ stats po undo se počet nezmění (subquery filtruje status IN
        // ('READY','VF_SKIP'), takže původní READY entry v dnesní stats stále je),
        // ale historicky JS tyto čítače dekrementoval. Necháváme tak (consistency).
        if (elTotal) elTotal.textContent = Math.max(0, (parseInt(elTotal.textContent, 10) || 1) - 1);
        if (op === 'VF') {
            if (elVf) elVf.textContent = Math.max(0, (parseInt(elVf.textContent, 10) || 1) - 1);
        } else {
            if (elReady) elReady.textContent = Math.max(0, (parseInt(elReady.textContent, 10) || 1) - 1);
        }
        if (elQueue) elQueue.textContent = (parseInt(elQueue.textContent, 10) || 0) + 1;
        if (elNew)   elNew.textContent   = (parseInt(elNew.textContent, 10)   || 0) + 1;
        // Zkontrolováno badge: NEDEKREMENTOVAT po undo. Badge ukazuje all-time
        // DISTINCT contact_id WHERE status IN ('READY','VF_SKIP'). Po undo se
        // přidá NEW záznam, ale původní READY záznam v historii zůstává →
        // contact je STÁLE v COUNT(DISTINCT). Server-side count se nemění,
        // takže ani JS nesmí dekrementovat (jinak by badge desynced).
    }
}

/* ── Pomocná fetch funkce ─────────────────────────────────────────── */
function cistPost(url, extraData) {
    var body = new URLSearchParams();
    body.append(CIST_CSRF_FIELD, CIST_CSRF_TOKEN);
    for (var key in extraData) {
        body.append(key, extraData[key]);
    }
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: body.toString()
    }).then(function(res) { return res.json(); });
}

/* ─────────────────────────────────────────────────────────────────────
   Aktivní řádek + auto-advance + klávesové zkratky
   - První řádek s nedokončeným kontaktem je AKTIVNÍ (zvýrazněn zeleně)
   - Po kliknutí TM/O2/VF řádek krátce blikne, pak se aktivní stane DALŠÍ
   - Klávesy 1=VF, 2=TM, 3=O2 → aktivuje akci aktivního řádku (jako klik)
   - Šipky ↑/↓ ručně přepínají aktivní řádek
   ───────────────────────────────────────────────────────────────────── */

function cistGetActiveRow() {
    return document.querySelector('.cist-row.cist-row--active');
}

function cistSetActive(row) {
    document.querySelectorAll('.cist-row--active').forEach(function(r) {
        r.classList.remove('cist-row--active');
    });
    if (row) {
        row.classList.add('cist-row--active');
        // Plynulé scroll do středu, jen pokud řádek není ve viewportu
        var rect = row.getBoundingClientRect();
        var inView = rect.top >= 0 && rect.bottom <= window.innerHeight;
        if (!inView) {
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
}

/** Najdi DALŠÍ řádek po aktuálním aktivním, který ještě není zpracovaný. */
function cistFindNextRow(currentRow) {
    if (!currentRow) {
        // Žádný aktivní — vezmi první nezpracovaný
        return document.querySelector('.cist-row[data-cid]:not([data-done="1"])');
    }
    var sib = currentRow.nextElementSibling;
    while (sib) {
        if (sib.classList.contains('cist-row') && sib.dataset.done !== '1') return sib;
        sib = sib.nextElementSibling;
    }
    // Nic — projdi seznam od začátku (mohly se uvolnit přes Undo)
    return document.querySelector('.cist-row[data-cid]:not([data-done="1"])');
}

function cistFindPrevRow(currentRow) {
    if (!currentRow) return null;
    var sib = currentRow.previousElementSibling;
    while (sib) {
        if (sib.classList.contains('cist-row')) return sib;
        sib = sib.previousElementSibling;
    }
    return null;
}

/** Po kliknutí: krátký flash + posun na další. */
function cistFlashAndAdvance(row, action) {
    if (!row) return;
    var flashClass = action === 'vf_skip' ? 'cist-row--flash-vf' : 'cist-row--flash-ok';
    row.classList.add(flashClass);
    setTimeout(function() { row.classList.remove(flashClass); }, 460);

    // Drobný delay (300 ms), aby uživatel viděl výsledek, pak advance
    setTimeout(function() {
        var next = cistFindNextRow(row);
        cistSetActive(next);
    }, 320);
}

// Hook do existujícího cistVerify — po úspěchu zavolat advance + update goal progress.
// Děláme to monkey-patch způsobem, ať nemusíme měnit původní funkci.
var _cistVerifyOriginal = cistVerify;
cistVerify = function(contactId, action, btn) {
    var row = document.getElementById('cist-row-' + contactId);
    var region = row ? (row.dataset.region || '') : '';
    var result = _cistVerifyOriginal(contactId, action, btn);
    // Flash + advance + update region goal (každý zdařený klik = +1 v daném kraji)
    cistFlashAndAdvance(row, action);
    if (region !== '') {
        // Drobné zpoždění, aby update proběhl po DB potvrzení (animace flash)
        setTimeout(function() { cistGoalIncrement(region); }, 200);
    }
    return result;
};

/** Inkrementuje progress bar pro daný kraj (real-time bez reloadu). */
function cistGoalIncrement(region) {
    // V past/future view tile zobrazují historický progress —
    // JS by neměl inkrementovat (data-done je z minulého měsíce, ne z teď).
    if (!CIST_IS_CURRENT_PERIOD) return;

    var goal = document.getElementById('goal-' + region);
    if (!goal) return; // pro tento kraj není nastavený cíl
    var done   = parseInt(goal.dataset.done || '0', 10) + 1;
    var target = parseInt(goal.dataset.target || '0', 10);
    if (target <= 0) return;

    goal.dataset.done = String(done);
    var pct = Math.min(100, Math.round(done / target * 100));

    var doneEl = goal.querySelector('.cist-goal__done');
    var fillEl = goal.querySelector('.cist-goal__fill');
    var statusEl = goal.querySelector('.cist-goal__status');

    if (doneEl) doneEl.textContent = String(done);
    if (fillEl) fillEl.style.width = pct + '%';
    if (statusEl) {
        if (done >= target) {
            statusEl.innerHTML = '<span style="color:var(--muted);">✓ Hotovo · čeká na nový cíl</span>';
            goal.classList.add('cist-goal--done');
            // Splněný kraj už není "začni zde" — sundat focus pulse.
            goal.classList.remove('cist-goal--focus');
            // Posun focusu na další nesplněnou tile (po prioritním pořadí).
            var allTiles = document.querySelectorAll('.cist-goal--clickable[data-region]');
            for (var i = 0; i < allTiles.length; i++) {
                var t = allTiles[i];
                if (t.classList.contains('cist-goal--done')) continue;
                if (t.classList.contains('cist-goal--focus')) break; // už nějaký focus mají
                t.classList.add('cist-goal--focus');
                // Nahraď text statusu hint o focusu (jen pro vizuál — refresh stránky
                // později vrátí kompletní stav).
                var ts = t.querySelector('.cist-goal__status');
                if (ts && !ts.querySelector('.cist-goal__focus-hint')) {
                    var prio = (t.querySelector('.cist-goal__priority') || {}).textContent || '';
                    var hint = document.createElement('span');
                    hint.className = 'cist-goal__focus-hint';
                    hint.textContent = '👈 Začni zde · priorita ' + prio.replace(/\D/g, '');
                    ts.insertBefore(hint, ts.firstChild);
                }
                break;
            }
        } else {
            statusEl.innerHTML = '<span style="color:var(--muted);">Zbývá: '
                + (target - done) + ' · ' + pct + ' %</span>';
        }
    }

    // Krátký flash pro vizuální feedback
    goal.classList.add('cist-goal--flash');
    setTimeout(function() { goal.classList.remove('cist-goal--flash'); }, 520);
}

// Klávesové zkratky: 1 = VF, 2 = TM, 3 = O2, ↑ = předchozí, ↓ = další
document.addEventListener('keydown', function(e) {
    // Ignoruj pokud uživatel píše do inputu/textarea
    var t = e.target;
    if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable)) return;
    // Ignoruj pokud má modifier (cmd/ctrl/alt)
    if (e.ctrlKey || e.metaKey || e.altKey) return;

    var active = cistGetActiveRow();
    if (!active) return;
    var cid = parseInt(active.dataset.cid || '0', 10);
    if (!cid) return;

    var key = e.key;
    if (key === '1') {
        e.preventDefault();
        var btn = active.querySelector('.btn-cist-vf');
        if (btn && !btn.disabled) btn.click();
    } else if (key === '2') {
        e.preventDefault();
        var btn = active.querySelector('.btn-cist-tm');
        if (btn && !btn.disabled) btn.click();
    } else if (key === '3') {
        e.preventDefault();
        var btn = active.querySelector('.btn-cist-o2');
        if (btn && !btn.disabled) btn.click();
    } else if (key === 'ArrowDown' || key === 'j') {
        e.preventDefault();
        var next = cistFindNextRow(active);
        if (next) cistSetActive(next);
    } else if (key === 'ArrowUp' || key === 'k') {
        e.preventDefault();
        var prev = cistFindPrevRow(active);
        if (prev) cistSetActive(prev);
    }
});

// Klik na řádek (mimo tlačítka) → aktivovat ho
document.addEventListener('click', function(e) {
    var row = e.target.closest('.cist-row');
    if (!row || !row.dataset.cid) return;
    if (e.target.tagName === 'BUTTON') return; // klik na tlačítko nemění aktivní
    cistSetActive(row);
});

// Init: po načtení stránky aktivuj první nezpracovaný řádek
document.addEventListener('DOMContentLoaded', function() {
    var first = document.querySelector('.cist-row[data-cid]:not([data-done="1"])');
    if (first) cistSetActive(first);

    // Hint o klávesových zkratkách (vypíše se 1× per session)
    if (!sessionStorage.getItem('cist_kbd_hint_seen')) {
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
            + '<code style="background:rgba(0,0,0,0.2);padding:1px 5px;border-radius:3px;">1</code> = VF · '
            + '<code style="background:rgba(0,0,0,0.2);padding:1px 5px;border-radius:3px;">2</code> = TM · '
            + '<code style="background:rgba(0,0,0,0.2);padding:1px 5px;border-radius:3px;">3</code> = O2<br>'
            + '<code style="background:rgba(0,0,0,0.2);padding:1px 5px;border-radius:3px;">↑↓</code> přepnout řádek';
        document.body.appendChild(hint);
        setTimeout(function() {
            hint.style.transition = 'opacity 0.4s';
            hint.style.opacity = '0';
            setTimeout(function() { hint.remove(); }, 400);
        }, 6000);
        sessionStorage.setItem('cist_kbd_hint_seen', '1');
    }
});
</script>
