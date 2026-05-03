<?php
// e:\Snecinatripu\app\views\admin\import\preview.php
declare(strict_types=1);
/** @var array<string,mixed> $analysis  Načtený preview JSON */
/** @var string|null         $flash */
/** @var string              $csrf */

$importId   = (string) ($analysis['import_id'] ?? '');
$filename   = (string) ($analysis['filename']  ?? '');
$format     = strtoupper((string) ($analysis['format'] ?? ''));
$sheetName  = (string) ($analysis['sheet_name']  ?? '');
$sheetCount = (int) ($analysis['sheet_count'] ?? 0);
$sheetNames = (array) ($analysis['sheet_names'] ?? []);
$totalRows  = (int) ($analysis['total_rows'] ?? 0);
$okRows     = (int) ($analysis['ok_rows']    ?? 0);
$counts     = (array) ($analysis['counts']   ?? []);
$errCount        = (int) ($counts['errors']                   ?? 0);
$dupFile         = (int) ($counts['duplicates_in_file']       ?? 0);
$dupFileShown    = (int) ($counts['duplicates_in_file_shown'] ?? $dupFile);
$dupDb           = (int) ($counts['duplicates_in_db']         ?? 0);
$dupDbShown      = (int) ($counts['duplicates_in_db_shown']   ?? $dupDb);
$dncCount        = (int) ($counts['dnc']                      ?? 0);
$dncShown        = (int) ($counts['dnc_shown']                ?? $dncCount);
$dupsTruncated   = (bool) ($counts['dups_truncated']          ?? false);

$errors    = (array) ($analysis['errors']             ?? []);
$dupsFile  = (array) ($analysis['duplicates_in_file'] ?? []);
$dupsDb    = (array) ($analysis['duplicates_in_db']   ?? []);
$dncList   = (array) ($analysis['dnc']                ?? []);

$canImport = $totalRows > 0 && $okRows > 0;

/** Render snapshotu řádku jako kompaktní řádkový text */
function renderSnap(array $snap): string {
    $parts = [];
    foreach (['firma','ico','tel','email','kraj','adresa'] as $k) {
        $v = trim((string) ($snap[$k] ?? ''));
        if ($v === '') continue;
        $label = match ($k) {
            'firma'  => 'Firma',
            'ico'    => 'IČO',
            'tel'    => 'Tel',
            'email'  => 'Email',
            'kraj'   => 'Kraj',
            'adresa' => 'Adresa',
        };
        $parts[] = '<span style="color:var(--muted);font-size:0.66rem;">'
                 . crm_h($label) . ':</span> '
                 . '<span style="color:var(--text);">' . crm_h(mb_substr($v, 0, 60)) . '</span>';
    }
    return implode(' &middot; ', $parts);
}
?>
<style>
.preview-card { max-width: 1100px; }
.preview-meta {
    display: flex; gap: 0.5rem 1rem; flex-wrap: wrap;
    font-size: 0.82rem; color: var(--muted);
    border-bottom: 1px solid rgba(0,0,0,0.08);
    padding-bottom: 0.7rem; margin-bottom: 1rem;
}
.preview-meta strong { color: var(--text); }

.preview-stats {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 0.6rem; margin-bottom: 1.2rem;
}
.preview-stat {
    background: var(--card); border: 1px solid rgba(0,0,0,0.08);
    border-left: 4px solid rgba(0,0,0,0.18);
    border-radius: 8px; padding: 0.7rem 0.9rem;
}
.preview-stat__val { font-size: 1.5rem; font-weight: 700; line-height: 1; }
.preview-stat__lbl { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em;
                     color: var(--muted); margin-top: 0.3rem; }
.preview-stat--ok    { border-left-color: #2ecc71; }
.preview-stat--ok    .preview-stat__val { color: #2ecc71; }
.preview-stat--warn  { border-left-color: #f0a030; }
.preview-stat--warn  .preview-stat__val { color: #f0a030; }
.preview-stat--err   { border-left-color: #e74c3c; }
.preview-stat--err   .preview-stat__val { color: #e74c3c; }
.preview-stat--info  { border-left-color: #3d8bfd; }
.preview-stat--info  .preview-stat__val { color: #3d8bfd; }
.preview-stat--ban   { border-left-color: #9b59b6; }
.preview-stat--ban   .preview-stat__val { color: #9b59b6; }

.preview-section {
    background: var(--card); border: 1px solid rgba(0,0,0,0.08);
    border-radius: 8px; padding: 0.85rem 1rem; margin-bottom: 0.85rem;
}
.preview-section__title {
    font-size: 0.92rem; font-weight: 700; margin-bottom: 0.5rem;
    display: flex; align-items: center; gap: 0.5rem;
}
.preview-section__title small { font-weight: 400; color: var(--muted); font-size: 0.75rem; }

.preview-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
.preview-table th, .preview-table td {
    padding: 0.32rem 0.55rem; text-align: left;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    vertical-align: top;
}
.preview-table th {
    color: var(--muted); font-size: 0.66rem; text-transform: uppercase;
    letter-spacing: 0.04em; font-weight: 600;
    background: rgba(0,0,0,0.02);
}
.preview-table tbody tr:hover td { background: rgba(0,0,0,0.02); }
.preview-table .row-num {
    font-family: monospace; color: var(--accent); font-weight: 700; width: 4rem;
}
.preview-table .reason { color: #e74c3c; }
.preview-table .col    { color: #f0a030; font-family: monospace; font-size: 0.74rem; }
.preview-table .value  { font-style: italic; color: var(--muted); }

.preview-scroll {
    max-height: 280px; overflow-y: auto; border-radius: 6px;
    border: 1px solid rgba(0,0,0,0.06);
}

.preview-actions {
    background: rgba(46,204,113,0.05);
    border: 1px solid rgba(46,204,113,0.25);
    border-left: 4px solid #2ecc71;
    border-radius: 0 8px 8px 0;
    padding: 1rem 1.2rem; margin-top: 1.5rem;
}
.preview-actions h2 { margin-top: 0; font-size: 1rem; }
.preview-actions__choice { display: flex; flex-direction: column; gap: 0.35rem; margin: 0.6rem 0; }
.preview-actions__choice label {
    display: flex; align-items: flex-start; gap: 0.55rem; cursor: pointer;
    padding: 0.4rem 0.55rem; border-radius: 6px;
    border: 1px solid rgba(0,0,0,0.08);
    background: rgba(0,0,0,0.02);
    transition: background 0.15s, border-color 0.15s;
}
.preview-actions__choice label:hover {
    background: rgba(0,0,0,0.04); border-color: rgba(0,0,0,0.14);
}
.preview-actions__choice input[type=radio]:checked + .choice-content {
    color: var(--text);
}
.preview-actions__choice .choice-content { font-size: 0.84rem; line-height: 1.4; }
.preview-actions__choice .choice-content small { color: var(--muted); font-size: 0.72rem; }

/* ── Varianta A: dvě nezávislé sekce strategií ──────────────────── */
.dup-block {
    margin: 0.8rem 0;
    padding: 0.75rem 0.85rem;
    border-radius: 8px;
    background: rgba(0,0,0,0.025);
    border: 1px solid rgba(0,0,0,0.08);
}
.dup-block__title {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 0.5rem;
}
.dup-block + .dup-block { margin-top: 0.6rem; }
.dup-block .strat-choice { margin-top: 0; }

.preview-empty {
    color: var(--muted); font-style: italic; padding: 0.5rem 0; font-size: 0.82rem;
}
</style>

<section class="card preview-card">
    <div style="margin-bottom:0.8rem;font-size:0.78rem;display:flex;gap:0.4rem;flex-wrap:wrap;">
        <a href="<?= crm_h(crm_url('/dashboard')) ?>" style="color:var(--brand-primary,#5a6cff);text-decoration:none;padding:0.25rem 0.55rem;border-radius:6px;background:rgba(90,108,255,0.1);border:1px solid rgba(90,108,255,0.25);">← Dashboard</a>
        <a href="<?= crm_h(crm_url('/admin/import')) ?>" style="color:var(--brand-primary,#5a6cff);text-decoration:none;padding:0.25rem 0.55rem;border-radius:6px;background:rgba(90,108,255,0.1);border:1px solid rgba(90,108,255,0.25);">📥 Import (nahrát jiný soubor)</a>
        <a href="<?= crm_h(crm_url('/admin/datagrid')) ?>" style="color:var(--brand-primary,#5a6cff);text-decoration:none;padding:0.25rem 0.55rem;border-radius:6px;background:rgba(90,108,255,0.1);border:1px solid rgba(90,108,255,0.25);">📊 Live datagrid</a>
        <a href="<?= crm_h(crm_url('/admin/duplicates')) ?>" style="color:var(--brand-primary,#5a6cff);text-decoration:none;padding:0.25rem 0.55rem;border-radius:6px;background:rgba(90,108,255,0.1);border:1px solid rgba(90,108,255,0.25);">🕵 Audit duplicit</a>
    </div>
    <h1 style="margin-bottom:0.5rem;">📋 Náhled importu</h1>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info"><?= crm_h($flash) ?></p>
    <?php } ?>

    <div class="preview-meta">
        <span>📁 Soubor: <strong><?= crm_h($filename) ?></strong></span>
        <span>📦 Formát: <strong><?= crm_h($format) ?></strong></span>
        <span>📅 Nahráno: <strong><?= crm_h((string)($analysis['uploaded_at'] ?? '')) ?></strong></span>
        <?php if ($sheetName !== '') { ?>
            <span>📑 Zpracovaný list: <strong><?= crm_h($sheetName) ?></strong>
                <?php if ($sheetCount > 1) { ?>
                    <span style="color:#f0a030;font-weight:400;">
                        (z celkem <?= $sheetCount ?> listů — ostatní byly vynechány!)
                    </span>
                <?php } ?>
            </span>
        <?php } ?>
    </div>

    <?php if ($sheetCount > 1) { ?>
    <div style="background:rgba(240,160,48,0.08);border:1px solid rgba(240,160,48,0.4);
                border-left:4px solid #f0a030;border-radius:0 8px 8px 0;
                padding:0.7rem 1rem;margin-bottom:1rem;font-size:0.85rem;">
        ⚠ <strong>Varování:</strong> XLSX má <?= $sheetCount ?> listů.
        Zpracoval jsem <strong>jen první list</strong> — "<?= crm_h($sheetName) ?>".
        Ostatní listy (<?= crm_h(implode(', ', array_slice($sheetNames, 1))) ?>) jsem nečetl.
        <br>
        <span style="color:var(--muted);font-size:0.78rem;">
            Pokud chcete importovat i ostatní listy, musíte je uložit do samostatných XLSX souborů a nahrát postupně.
        </span>
    </div>
    <?php } ?>

    <?php if ($dupsTruncated) { ?>
    <div style="background:rgba(46,204,113,0.06);border:1px solid rgba(46,204,113,0.35);
                border-left:4px solid #2ecc71;border-radius:0 8px 8px 0;
                padding:0.7rem 1rem;margin-bottom:1rem;font-size:0.85rem;">
        ℹ <strong>Hodně duplicit?</strong> To je normální — počet je <strong>reálný a neomezený</strong>
        (vidíš celkové číslo nahoře v dlaždici). Detailně se vypíše prvních
        <strong>5 000 řádků</strong> kvůli velikosti stránky — víc by browser nestihl vykreslit.
        <br>
        <span style="color:var(--muted);font-size:0.78rem;">
            Pro hromadné rozhodnutí použij <strong>globální strategie</strong> dole („Sloučit / Přeskočit / Aktualizovat / Přidat") —
            ty se aplikují na <strong>všechny</strong> duplicity bez ohledu na cap.
            Per-řádek edit je jen pro výjimečné případy.
        </span>
    </div>
    <?php } ?>

    <!-- Stats grid -->
    <div class="preview-stats">
        <div class="preview-stat preview-stat--info">
            <div class="preview-stat__val"><?= number_format($totalRows, 0, ',', ' ') ?></div>
            <div class="preview-stat__lbl">Datových řádků celkem</div>
        </div>
        <div class="preview-stat preview-stat--ok">
            <div class="preview-stat__val"><?= number_format($okRows, 0, ',', ' ') ?></div>
            <div class="preview-stat__lbl">OK k importu</div>
        </div>
        <div class="preview-stat preview-stat--err">
            <div class="preview-stat__val"><?= number_format($errCount, 0, ',', ' ') ?></div>
            <div class="preview-stat__lbl">Chybné řádky</div>
        </div>
        <div class="preview-stat preview-stat--warn">
            <div class="preview-stat__val"><?= number_format($dupFile, 0, ',', ' ') ?></div>
            <div class="preview-stat__lbl">
                Duplicit v souboru
                <?php if ($dupFile > $dupFileShown) { ?>
                    <br><small style="color:var(--muted);font-style:italic;">(zobrazeno detailně <?= number_format($dupFileShown, 0, ',', ' ') ?>)</small>
                <?php } ?>
            </div>
        </div>
        <div class="preview-stat preview-stat--warn">
            <div class="preview-stat__val"><?= number_format($dupDb, 0, ',', ' ') ?></div>
            <div class="preview-stat__lbl">
                Duplicit v DB
                <?php if ($dupDb > $dupDbShown) { ?>
                    <br><small style="color:var(--muted);font-style:italic;">(zobrazeno detailně <?= number_format($dupDbShown, 0, ',', ' ') ?>)</small>
                <?php } ?>
            </div>
        </div>
        <div class="preview-stat preview-stat--ban">
            <div class="preview-stat__val"><?= number_format($dncCount, 0, ',', ' ') ?></div>
            <div class="preview-stat__lbl">DNC (zákaz volání)</div>
        </div>
    </div>

    <!-- ── Chyby ── -->
    <?php if ($errors !== []) { ?>
    <div class="preview-section">
        <div class="preview-section__title">
            ❌ Chyby ve <?= count($errors) ?> řádcích
            <small>· tyto řádky budou při importu vynechány — porovnejte "Co parser viděl" s tím co máte v Excelu</small>
        </div>
        <div class="preview-scroll" style="max-height:380px;">
            <table class="preview-table">
                <thead><tr>
                    <th>Řádek</th><th>Důvod</th><th>Co parser viděl v tom řádku</th>
                </tr></thead>
                <tbody>
                <?php foreach ($errors as $e) {
                    $snap = (array) ($e['snapshot'] ?? []);
                    $hasSnap = $snap !== [] && implode('', $snap) !== '';
                ?>
                    <tr>
                        <td class="row-num">#<?= (int)($e['row'] ?? 0) ?></td>
                        <td>
                            <span class="reason"><?= crm_h((string)($e['reason'] ?? '')) ?></span>
                            <?php if (!empty($e['col'])) { ?>
                                <br><span style="font-size:0.66rem;color:var(--muted);">
                                    sloupec: <code><?= crm_h((string) $e['col']) ?></code>
                                </span>
                            <?php } ?>
                        </td>
                        <td style="font-size:0.74rem;line-height:1.5;">
                            <?php if ($hasSnap) { ?>
                                <?= renderSnap($snap) ?>
                            <?php } else { ?>
                                <em style="color:var(--muted);">řádek je úplně prázdný</em>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php } ?>

    <?php // File duplicity rendering teď uvnitř commit formu — viz níže.
          // Per-row dropdowny posílají rozhodnutí spolu s POSTem na /commit. ?>

    <?php // DB duplicity rendering vykonáváme níž — uvnitř commit formuláře,
          // aby per-row dropdowny posílaly volby spolu s commit POST. ?>

    <!-- ── DNC ── -->
    <?php if ($dncList !== []) { ?>
    <div class="preview-section" style="border-left:4px solid #9b59b6;">
        <div class="preview-section__title">
            🚫 DNC (zákaz volání): <?= count($dncList) ?>
            <small>· tyto řádky se vždy přeskočí</small>
        </div>
        <div class="preview-scroll">
            <table class="preview-table">
                <thead><tr>
                    <th>Řádek</th><th>Shoda na</th><th>Hodnota</th><th>Firma</th>
                </tr></thead>
                <tbody>
                <?php foreach ($dncList as $d) { ?>
                    <tr>
                        <td class="row-num">#<?= (int)($d['row'] ?? 0) ?></td>
                        <td class="col"><?= crm_h((string)($d['match'] ?? '')) ?></td>
                        <td class="value"><?= crm_h((string)($d['value'] ?? '')) ?></td>
                        <td><?= crm_h((string)($d['firma'] ?? '')) ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php } ?>

    <!-- ── Akce: rozhodnutí + commit ── -->
    <?php if ($canImport) { ?>
    <?php
    // Lidsky čitelné labely pro typ shody — uživatel nemusí přemýšlet co je "ico"
    $matchLabels = [
        'ico'     => ['label' => '🏢 Pravděpodobně stejná firma',  'sub' => 'Shodné IČO — státem vydaný unikátní identifikátor'],
        'telefon' => ['label' => '📞 Sdílený telefon',              'sub' => 'Stejné telefonní číslo — často rodina nebo společný kontakt firmy'],
        'email'   => ['label' => '✉ Sdílený email',                 'sub' => 'Stejná e-mailová adresa — manželé / sdílený firemní mail'],
    ];
    ?>
    <div class="preview-actions">
        <h2 style="margin-top:0;">✅ Co dál s importem?</h2>

        <form method="post" action="<?= crm_h(crm_url('/admin/import/commit')) ?>" id="commit-form">
            <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
            <input type="hidden" name="import_id" value="<?= crm_h($importId) ?>">
            <!-- Globální dup_action pro DB duplicity (default skip — bezpečné). -->
            <input type="hidden" name="dup_action" id="hid-dup-action" value="skip">

            <!-- Strategie — dvě nezávislé volby -->
            <p style="font-size:0.88rem;margin-bottom:0.5rem;">
                <strong>Jak řešit duplicity?</strong>
                <span style="color:var(--muted);font-size:0.78rem;">Dvě nezávislé volby — pro shody v souboru a vůči existující DB.</span>
            </p>

            <!-- ── A) Duplicity v souboru ────────────────────────────────── -->
            <div class="dup-block">
                <div class="dup-block__title">📂 Duplicity v souboru (řádky uvnitř CSV/XLSX, které se opakují)</div>
                <div class="strat-choice">
                    <label class="strat-card strat-card--selected">
                        <input type="radio" name="strategy_file" value="merge_smart" checked
                               onchange="applyFileStrategy(this.value)">
                        <span class="strat-card__head">
                            🔀 <strong>Sloučit stejné firmy</strong>
                            <span class="strat-card__pill">doporučeno</span>
                        </span>
                        <span class="strat-card__desc">
                            Stejné IČO → 1 záznam (telefony spojené).<br>
                            Sdílený telefon / email = různí lidé → zachovat oba.
                        </span>
                    </label>
                    <label class="strat-card">
                        <input type="radio" name="strategy_file" value="keep_all"
                               onchange="applyFileStrategy(this.value)">
                        <span class="strat-card__head">
                            ➕ <strong>Zachovat vše</strong>
                        </span>
                        <span class="strat-card__desc">
                            Žádné slučování — každý řádek se naimportuje.
                        </span>
                    </label>
                    <label class="strat-card">
                        <input type="radio" name="strategy_file" value="skip_all"
                               onchange="applyFileStrategy(this.value)">
                        <span class="strat-card__head">
                            ⏭ <strong>Přeskočit duplicity</strong>
                        </span>
                        <span class="strat-card__desc">
                            Druhý výskyt v souboru se zahodí. Nejbezpečnější.
                        </span>
                    </label>
                </div>
            </div>

            <!-- ── B) Duplicity vs. existující databáze ──────────────────── -->
            <div class="dup-block">
                <div class="dup-block__title">🗄 Duplicity v databázi (kontakt už existuje v CRM)</div>
                <div class="strat-choice">
                    <label class="strat-card strat-card--selected">
                        <input type="radio" name="strategy_db" value="skip" checked
                               onchange="applyDbStrategy(this.value)">
                        <span class="strat-card__head">
                            ⏭ <strong>Přeskočit</strong>
                            <span class="strat-card__pill">doporučeno</span>
                        </span>
                        <span class="strat-card__desc">
                            Existující kontakt zůstane beze změny — řádek z importu se zahodí.
                        </span>
                    </label>
                    <label class="strat-card">
                        <input type="radio" name="strategy_db" value="update"
                               onchange="applyDbStrategy(this.value)">
                        <span class="strat-card__head">
                            🔄 <strong>Aktualizovat</strong>
                        </span>
                        <span class="strat-card__desc">
                            Doplň prázdná pole existujícího kontaktu novými daty (COALESCE).
                        </span>
                    </label>
                    <label class="strat-card">
                        <input type="radio" name="strategy_db" value="merge"
                               onchange="applyDbStrategy(this.value)">
                        <span class="strat-card__head">
                            🔀 <strong>Sloučit</strong>
                        </span>
                        <span class="strat-card__desc">
                            Slouč data do 1 kontaktu (telefony se spojí, ostatní pole COALESCE).
                        </span>
                    </label>
                    <label class="strat-card">
                        <input type="radio" name="strategy_db" value="add"
                               onchange="applyDbStrategy(this.value)">
                        <span class="strat-card__head">
                            ➕ <strong>Přidat jako nový</strong>
                        </span>
                        <span class="strat-card__desc">
                            Vznikne dvojitý záznam (riskantní — používej jen výjimečně).
                        </span>
                    </label>
                </div>
            </div>

            <!-- Pravidla — co se reálně stane -->
            <div id="strat-rules" class="strat-rules">
                <div class="strat-rules__title">Co se stane:</div>
                <ul id="strat-rules-list">
                    <li><span class="r-ok">📂</span> <strong>V souboru:</strong> Stejné IČO → sloučit; sdílený tel/email → ponechat oba</li>
                    <li><span class="r-ok">🗄</span> <strong>V DB:</strong> Jakákoliv duplicita → přeskočit (zachovat pouze první výskyt / existující v DB)</li>
                </ul>
            </div>

            <?php
            /**
             * Společný renderer řádku duplicity.
             *
             * @param array<string,string> $matchLabels
             */
            $renderDupRow = static function (string $matchType, int $rowNumNew, string $origLabel, array $origSnap,
                                              string $newLabel, array $newSnap, string $selectName,
                                              string $dupType, array $matchLabels): void {
                $info = $matchLabels[$matchType] ?? ['label' => $matchType, 'sub' => ''];
                ?>
                <tr class="dup-row" data-match="<?= crm_h($matchType) ?>" data-dup-type="<?= crm_h($dupType) ?>">
                    <td style="vertical-align:top;font-size:0.8rem;line-height:1.4;">
                        <strong style="color:var(--accent);"><?= crm_h($info['label']) ?></strong>
                        <br><span style="font-size:0.66rem;color:var(--muted);"><?= crm_h($info['sub']) ?></span>
                    </td>
                    <td style="font-size:0.74rem;line-height:1.5;">
                        <span class="row-num"><?= crm_h($origLabel) ?></span><br>
                        <?= $origSnap !== [] ? renderSnap($origSnap) : '<em>—</em>' ?>
                    </td>
                    <td style="font-size:0.74rem;line-height:1.5;background:rgba(46,204,113,0.04);">
                        <span class="row-num"><?= crm_h($newLabel) ?></span><br>
                        <?= $newSnap !== [] ? renderSnap($newSnap) : '<em>—</em>' ?>
                    </td>
                    <td style="vertical-align:top;">
                        <select name="<?= crm_h($selectName) ?>"
                                class="dup-select"
                                style="width:100%;font-size:0.74rem;padding:0.3rem 0.4rem;
                                       background:var(--bg);color:var(--text);
                                       border:1px solid rgba(0,0,0,0.15);border-radius:5px;">
                            <option value="merge">🔀 Sloučit kontakty</option>
                            <option value="add">➕ Ponechat odděleně</option>
                            <option value="skip">⏭ Přeskočit duplicitu</option>
                        </select>
                    </td>
                </tr>
                <?php
            };
            ?>

            <!-- Duplicity v souboru -->
            <?php if ($dupsFile !== []) { ?>
            <div class="dup-block dup-block--file">
                <div class="dup-block__title">
                    Duplicity v souboru: <?= count($dupsFile) ?>
                    <span class="dup-block__hint">každou můžeš ručně upravit níže</span>
                </div>
                <div class="preview-scroll" style="max-height:420px;background:transparent;border:0;">
                    <table class="preview-table">
                        <thead><tr>
                            <th style="width:24%;">Typ shody</th>
                            <th>Originál</th>
                            <th style="background:rgba(46,204,113,0.04);">Nový z importu</th>
                            <th style="width:180px;">Co s tím</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($dupsFile as $d) {
                            $rowNum    = (int) ($d['row'] ?? 0);
                            $origRow   = (int) ($d['first_seen_row'] ?? 0);
                            $matchType = (string) ($d['match'] ?? '');
                            $renderDupRow(
                                $matchType, $rowNum,
                                '#' . $origRow, (array)($d['snapshot_orig'] ?? []),
                                '#' . $rowNum,  (array)($d['snapshot_dup']  ?? []),
                                'file_dup_action[' . $rowNum . ']',
                                'file', $matchLabels
                            );
                        } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php } ?>

            <!-- Duplicity v DB -->
            <?php if ($dupsDb !== []) { ?>
            <div class="dup-block dup-block--db">
                <div class="dup-block__title">
                    Duplicity v databázi: <?= count($dupsDb) ?>
                    <span class="dup-block__hint">existující záznam vs. nový z importu</span>
                </div>
                <div class="preview-scroll" style="max-height:420px;background:transparent;border:0;">
                    <table class="preview-table">
                        <thead><tr>
                            <th style="width:24%;">Typ shody</th>
                            <th>Existující v DB</th>
                            <th style="background:rgba(46,204,113,0.04);">Nový z importu</th>
                            <th style="width:180px;">Co s tím</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($dupsDb as $d) {
                            $rowNum    = (int) ($d['row'] ?? 0);
                            $exId      = (int) ($d['existing_id'] ?? 0);
                            $matchType = (string) ($d['match'] ?? '');
                            $renderDupRow(
                                $matchType, $rowNum,
                                'DB id ' . $exId, (array)($d['snapshot_db']  ?? []),
                                '#' . $rowNum,    (array)($d['snapshot_new'] ?? []),
                                'row_action[' . $rowNum . ']',
                                'db', $matchLabels
                            );
                        } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php } ?>

            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:1rem;">
                <button type="submit" class="btn"
                        onclick="return confirm('Opravdu spustit import? Vloží se / aktualizuje se přibližně <?= $okRows ?> řádků.');">
                    ✓ Potvrdit a importovat (<?= $okRows ?> řádků)
                </button>
            </div>
        </form>

        <form method="post" action="<?= crm_h(crm_url('/admin/import/cancel')) ?>"
              style="display:inline-block;margin-top:0.6rem;">
            <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
            <input type="hidden" name="import_id" value="<?= crm_h($importId) ?>">
            <button type="submit" class="btn btn-secondary"
                    onclick="return confirm('Opravdu zrušit a smazat nahraný soubor?');">
                ✕ Zrušit a smazat soubor
            </button>
        </form>
    </div>
    <?php } else { ?>
    <div class="preview-actions" style="border-left-color:#e74c3c;background:rgba(231,76,60,0.05);border-color:rgba(231,76,60,0.25);">
        <h2 style="color:#e74c3c;">⚠ Není co importovat</h2>
        <p style="font-size:0.85rem;margin:0.3rem 0 0.6rem;">
            V souboru nezbyl žádný platný řádek. Opravte chyby v souboru a nahrajte znovu.
        </p>
        <form method="post" action="<?= crm_h(crm_url('/admin/import/cancel')) ?>">
            <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
            <input type="hidden" name="import_id" value="<?= crm_h($importId) ?>">
            <button type="submit" class="btn btn-secondary">← Zpět na nahrání</button>
        </form>
    </div>
    <?php } ?>
</section>

<style>
/* ── Strategy cards ────────────────────────────────────────────── */
.strat-choice {
    display: grid; gap: 0.55rem;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    margin-bottom: 0.85rem;
}
.strat-card {
    display: flex; flex-direction: column; gap: 0.3rem;
    padding: 0.7rem 0.85rem;
    background: rgba(0,0,0,0.02);
    border: 1.5px solid rgba(0,0,0,0.10);
    border-radius: 8px;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
    position: relative;
}
.strat-card:hover { border-color: rgba(0,0,0,0.20); background: rgba(0,0,0,0.04); }
.strat-card input[type=radio] {
    position: absolute; top: 0.7rem; right: 0.7rem;
    accent-color: #2ecc71;
}
.strat-card--selected,
.strat-card:has(input[type=radio]:checked) {
    border-color: rgba(46,204,113,0.55);
    background: rgba(46,204,113,0.05);
}
.strat-card__head {
    font-size: 0.92rem; display: flex; align-items: center; gap: 0.4rem;
}
.strat-card__pill {
    font-size: 0.6rem; padding: 0.05rem 0.35rem;
    background: rgba(46,204,113,0.18); color: #2ecc71;
    border-radius: 4px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.04em;
}
.strat-card__desc {
    font-size: 0.74rem; color: var(--muted); line-height: 1.4;
    padding-right: 1.4rem; /* prostor pro radio */
}

/* ── Pravidla ── */
.strat-rules {
    background: rgba(46,204,113,0.04);
    border: 1px solid rgba(46,204,113,0.2);
    border-left: 3px solid #2ecc71;
    border-radius: 0 6px 6px 0;
    padding: 0.6rem 0.85rem;
    margin-bottom: 1rem;
    font-size: 0.78rem;
}
.strat-rules__title {
    font-weight: 700; font-size: 0.78rem; margin-bottom: 0.3rem;
    color: #2ecc71;
}
.strat-rules ul { margin: 0; padding-left: 0.4rem; list-style: none; }
.strat-rules li { padding: 0.15rem 0; color: var(--text); }
.r-ok   { color: #2ecc71; font-weight: 700; margin-right: 0.3rem; }
.r-skip { color: var(--muted); font-weight: 700; margin-right: 0.3rem; }

/* ── Dup blocks ── */
.dup-block {
    margin-top: 1rem; padding: 0.7rem 0.9rem;
    background: rgba(0,0,0,0.02);
    border: 1px solid rgba(241,196,15,0.25);
    border-left: 4px solid #f1c40f;
    border-radius: 0 8px 8px 0;
}
.dup-block--db { border-color: rgba(240,160,48,0.3); border-left-color: #f0a030; }
.dup-block__title {
    font-size: 0.88rem; font-weight: 700; margin-bottom: 0.4rem;
    color: #f1c40f;
}
.dup-block--db .dup-block__title { color: #f0a030; }
.dup-block__hint {
    font-weight: 400; font-size: 0.72rem; color: var(--muted); margin-left: 0.3rem;
}
</style>

<script>
// ─────────────────────────────────────────────────────────────────
//  Strategie: nastaví všechny per-row dropdowny podle typu shody.
// ─────────────────────────────────────────────────────────────────
// ── Strategie pro IN-FILE duplicity (action per match-type) ───────
const FILE_STRATEGY_RULES = {
    merge_smart: {
        label: 'Stejné IČO → sloučit; sdílený tel/email → ponechat oba',
        actions: { ico: 'merge', telefon: 'add', email: 'add' },
    },
    keep_all: {
        label: 'Vše ponechat (vzniknou duplicity)',
        actions: { ico: 'add', telefon: 'add', email: 'add' },
    },
    skip_all: {
        label: 'Jakákoliv duplicita → přeskočit (jen první výskyt)',
        actions: { ico: 'skip', telefon: 'skip', email: 'skip' },
    },
};

// ── Strategie pro DB duplicity (jednotná akce pro všechny shody) ──
const DB_STRATEGY_RULES = {
    skip:   { label: 'Existující v DB nech beze změny — řádek z importu zahodit',           action: 'skip'   },
    update: { label: 'Doplň prázdná pole existujícího kontaktu novými daty (COALESCE)',     action: 'update' },
    merge:  { label: 'Slouč: telefony spojit přes „;" + ostatní pole COALESCE',             action: 'merge'  },
    add:    { label: 'Přidat jako nový kontakt (vznikne duplicita)',                         action: 'add'    },
};

let currentFileStrategy = 'merge_smart';
let currentDbStrategy   = 'skip';

function highlightSelectedCard(name, value) {
    document.querySelectorAll(`input[name="${name}"]`).forEach(r => {
        const card = r.closest('.strat-card');
        if (card) card.classList.toggle('strat-card--selected', r.value === value);
    });
}

function flashSelect(select) {
    select.style.transition = 'background 0.4s';
    select.style.background = 'rgba(46,204,113,0.18)';
    setTimeout(() => { select.style.background = 'var(--bg)'; }, 500);
}

function applyFileStrategy(mode) {
    const cfg = FILE_STRATEGY_RULES[mode];
    if (!cfg) return;
    currentFileStrategy = mode;

    // Per-row selecty pro file duplicity
    document.querySelectorAll('tr.dup-row[data-dup-type="file"]').forEach(row => {
        const match  = row.dataset.match || '';
        const select = row.querySelector('select.dup-select');
        if (!select) return;
        const action = cfg.actions[match];
        if (action) { select.value = action; flashSelect(select); }
    });

    highlightSelectedCard('strategy_file', mode);
    refreshRulesSummary();
}

function applyDbStrategy(mode) {
    const cfg = DB_STRATEGY_RULES[mode];
    if (!cfg) return;
    currentDbStrategy = mode;

    // Hidden field pro backend (backwards compat)
    const hidden = document.getElementById('hid-dup-action');
    if (hidden) hidden.value = cfg.action;

    // Per-row selecty pro DB duplicity
    document.querySelectorAll('tr.dup-row[data-dup-type="db"]').forEach(row => {
        const select = row.querySelector('select.dup-select');
        if (!select) return;
        select.value = cfg.action;
        flashSelect(select);
    });

    highlightSelectedCard('strategy_db', mode);
    refreshRulesSummary();
}

function refreshRulesSummary() {
    const list = document.getElementById('strat-rules-list');
    if (!list) return;
    list.innerHTML =
        `<li><span class="r-ok">📂</span> <strong>V souboru:</strong> ${FILE_STRATEGY_RULES[currentFileStrategy].label}</li>` +
        `<li><span class="r-ok">🗄</span> <strong>V DB:</strong> ${DB_STRATEGY_RULES[currentDbStrategy].label}</li>`;
}

// Init — defaulty
document.addEventListener('DOMContentLoaded', function() {
    applyFileStrategy('merge_smart');
    applyDbStrategy('skip');
});
</script>
