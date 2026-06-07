<?php
// e:\Snecinatripu\app\views\my-additions\index.php
declare(strict_types=1);
/** @var array<string,mixed>       $user */
/** @var string                    $csrf */
/** @var ?string                   $flash */
/** @var list<array<string,mixed>> $rows    moje doporučenky v daném období */
/** @var array<string,int>         $stats   total/today/last7/uzavreno/aktivni/nezajem/ceka */
/** @var string                    $period  today|7d|30d|90d|all */

$stats = array_merge(
    ['total' => 0, 'today' => 0, 'last7' => 0,
     'uzavreno' => 0, 'aktivni' => 0, 'nezajem' => 0, 'ceka' => 0],
    array_map('intval', $stats ?? [])
);

// Helper: barva věku
function ma_ageColor(string $created): string {
    if ($created === '') return 'var(--color-text-muted)';
    $ts = strtotime($created);
    if ($ts === false) return 'var(--color-text-muted)';
    $hours = (time() - $ts) / 3600;
    if ($hours <= 24)  return '#16a34a';
    if ($hours <= 168) return '#374151';
    return 'var(--color-text-muted)';
}
function ma_elapsed(string $created): string {
    if ($created === '') return '—';
    $ts = strtotime($created);
    if ($ts === false) return $created;
    $d = time() - $ts;
    if ($d < 60)    return 'právě teď';
    if ($d < 3600)  return 'před ' . (int)($d / 60) . ' min';
    if ($d < 86400) return 'před ' . (int)($d / 3600) . ' h';
    return 'před ' . (int)($d / 86400) . ' d';
}

// Barevný badge pro effective_stav
function ma_stavBadge(string $stav): array {
    // [text, bg, fg]
    return match (true) {
        $stav === 'UZAVRENO'                            => ['✓ Uzavřeno',   '#dcfce7', '#166534'],
        in_array($stav, ['SMLOUVA','BO_PREDANO','BO_VPRACI'], true)
                                                        => ['🏢 BO ' . $stav,'#ede9fe','#5b21b6'],
        $stav === 'BO_VRACENO'                          => ['↩ BO vrátil',  '#fef3c7','#92400e'],
        $stav === 'SCHUZKA'                             => ['📅 Schůzka',   '#cffafe','#155e75'],
        $stav === 'CALLBACK'                            => ['↻ Callback',   '#fed7aa','#9a3412'],
        $stav === 'SANCE'                               => ['💡 Šance',     '#fef9c3','#854d0e'],
        $stav === 'NABIDKA'                             => ['📨 Nabídka',   '#cffafe','#155e75'],
        in_array($stav, ['NOVE','OBVOLANO','ZPRACOVAVA'], true)
                                                        => ['📋 Rozprac.',  '#e0e7ff','#3730a3'],
        in_array($stav, ['NEZAJEM','NERELEVANTNI'], true)
                                                        => ['✗ Nezájem',    '#f3f4f6','#6b7280'],
        $stav === 'CALLED_OK'                           => ['🆕 Čeká OZ',   '#dbeafe','#1e40af'],
        default                                         => [$stav,           '#f3f4f6','#374151'],
    };
}
?>

<style>
.ma-wrap { max-width: 1200px; margin: 0 auto; }
.ma-stats {
    display: grid; gap: 0.6rem; margin-bottom: 1.2rem;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
}
.ma-stat {
    background: #fff; border: 1px solid var(--color-border);
    border-radius: 8px; padding: 0.7rem 0.85rem;
    border-left: 4px solid var(--ma-c, #9ca3af);
}
.ma-stat__val { font-size: 1.5rem; font-weight: 700; color: var(--color-text); line-height: 1.1; }
.ma-stat__lbl { font-size: 0.7rem; color: var(--color-text-muted); text-transform: uppercase;
                letter-spacing: 0.04em; margin-top: 0.2rem; }
.ma-stat--total   { --ma-c: #6b7280; }
.ma-stat--aktiv   { --ma-c: #0e7490; }
.ma-stat--win     { --ma-c: #16a34a; }
.ma-stat--ceka    { --ma-c: #2563eb; }
.ma-stat--loss    { --ma-c: #9ca3af; }

.ma-period {
    display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 1rem;
    background: rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.06);
    border-radius: 8px; padding: 0.55rem 0.75rem; align-items: center;
}
.ma-period span.label { font-size: 0.74rem; color: var(--color-text-muted); font-weight: 600; }
.ma-period a {
    padding: 0.2rem 0.65rem; font-size: 0.78rem;
    text-decoration: none; border-radius: 12px;
    background: #fff; color: #374151;
    border: 1px solid rgba(0,0,0,0.12);
}
.ma-period a.active { background: #0e7490; color: #fff; border-color: #0e7490; font-weight: 700; }

.ma-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 6px; overflow: hidden; }
.ma-table th, .ma-table td {
    padding: 0.5rem 0.7rem; text-align: left;
    border-bottom: 1px solid rgba(0,0,0,0.05); font-size: 0.85rem; vertical-align: top;
}
.ma-table th {
    background: rgba(0,0,0,0.03); font-size: 0.7rem; text-transform: uppercase;
    color: var(--color-text-muted); font-weight: 600; letter-spacing: 0.03em;
}
.ma-table tr:hover td { background: rgba(0,0,0,0.02); }
.ma-table code { font-family: monospace; font-size: 0.78rem; color: #6b7280; }
.ma-badge { display: inline-block; padding: 0.1rem 0.5rem; border-radius: 10px;
            font-size: 0.7rem; font-weight: 700; white-space: nowrap; }
.ma-empty { text-align: center; padding: 3rem 1rem; color: var(--color-text-muted); }
.ma-cta {
    background: rgba(14, 116, 144, 0.08); border: 1px solid rgba(14, 116, 144, 0.25);
    border-radius: 8px; padding: 0.9rem 1rem; margin-top: 1rem;
    font-size: 0.83rem; color: #155e75;
}
.ma-cta a { color: #0e7490; font-weight: 700; }
</style>

<section class="ma-wrap">
    <h1 style="margin:0 0 0.25rem;">📋 Moje doporučenky</h1>
    <p style="color:var(--color-text-muted);font-size:0.85rem;margin-bottom:1.2rem;">
        Všechny kontakty, které jsi přidal přes <strong>„➕ Nový kontakt"</strong>.
        Vidíš, kolik jich bylo a v jakém jsou stavu — jestli s nimi OZ pracuje, uzavřel
        smlouvu, nebo to zákazník odmítl.
    </p>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info" style="margin-bottom:1rem;"><?= crm_h($flash) ?></p>
    <?php } ?>

    <!-- Konverzní stats — celkem za celou dobu (ne podle filtru) -->
    <div class="ma-stats">
        <div class="ma-stat ma-stat--total">
            <div class="ma-stat__val"><?= $stats['total'] ?></div>
            <div class="ma-stat__lbl">Celkem přidaných</div>
        </div>
        <div class="ma-stat ma-stat--total">
            <div class="ma-stat__val"><?= $stats['last7'] ?></div>
            <div class="ma-stat__lbl">Posledních 7 dní</div>
        </div>
        <div class="ma-stat ma-stat--ceka">
            <div class="ma-stat__val"><?= $stats['ceka'] ?></div>
            <div class="ma-stat__lbl">Čeká na OZ</div>
        </div>
        <div class="ma-stat ma-stat--aktiv">
            <div class="ma-stat__val"><?= $stats['aktivni'] ?></div>
            <div class="ma-stat__lbl">V jednání</div>
        </div>
        <div class="ma-stat ma-stat--win">
            <div class="ma-stat__val"><?= $stats['uzavreno'] ?></div>
            <div class="ma-stat__lbl">Uzavřeno</div>
        </div>
        <div class="ma-stat ma-stat--loss">
            <div class="ma-stat__val"><?= $stats['nezajem'] ?></div>
            <div class="ma-stat__lbl">Nezájem</div>
        </div>
    </div>

    <!-- Filtr období -->
    <div class="ma-period">
        <span class="label">🗓 Období:</span>
        <?php
        $periodMap = [
            'today' => 'Dnes',
            '7d'    => 'Poslední týden',
            '30d'   => 'Posledních 30 dní',
            '90d'   => 'Posledních 90 dní',
            'all'   => 'Vše',
        ];
        foreach ($periodMap as $key => $lbl) {
            $url = '/me/added-contacts?period=' . $key;
            $cls = $key === $period ? 'active' : '';
            echo '<a href="' . crm_h(crm_url($url)) . '" class="' . $cls . '">' . crm_h($lbl) . '</a>';
        }
        ?>
    </div>

    <?php if ($rows === []) { ?>
        <div class="ma-empty">
            <div style="font-size:2rem;margin-bottom:0.5rem;">📭</div>
            <?php if ((int) $stats['total'] === 0) { ?>
                <div>Zatím jsi nepřidal žádnou doporučenku.</div>
                <div style="font-size:0.8rem;margin-top:0.4rem;">
                    Když narazíš na hot lead (doporučení od klienta, vlastní akvizice…),
                    klikni v sidebaru na <strong>„➕ Nový kontakt"</strong>.
                </div>
            <?php } else { ?>
                <div>V tomto období žádné doporučenky.</div>
                <div style="font-size:0.78rem;margin-top:0.4rem;">
                    Zkus jiné období
                    (<a href="<?= crm_h(crm_url('/me/added-contacts?period=all')) ?>">Vše</a>).
                </div>
            <?php } ?>
        </div>
    <?php } else { ?>
        <table class="ma-table">
            <thead>
                <tr>
                    <th>Kdy</th>
                    <th>Firma</th>
                    <th>IČO</th>
                    <th>Kraj</th>
                    <th>Přiřazený OZ</th>
                    <th>Stav</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r) {
                    $cid     = (int) $r['id'];
                    $created = (string) ($r['created_at'] ?? '');
                    $ageCol  = ma_ageColor($created);
                    $stav    = (string) ($r['effective_stav'] ?? '—');
                    [$bText, $bBg, $bFg] = ma_stavBadge($stav);
                ?>
                    <tr>
                        <td>
                            <div style="color:<?= $ageCol ?>;font-weight:500;">
                                <?= crm_h(ma_elapsed($created)) ?>
                            </div>
                            <div style="font-size:0.7rem;color:var(--color-text-muted);">
                                <?= crm_h(date('d.m.Y H:i', strtotime($created) ?: 0)) ?>
                            </div>
                        </td>
                        <td>
                            <strong><?= crm_h((string)($r['firma'] ?? '—')) ?></strong>
                            <?php if (!empty($r['telefon'])) { ?>
                                <div style="font-size:0.72rem;color:var(--color-text-muted);font-family:monospace;">
                                    <?= crm_h((string) $r['telefon']) ?>
                                </div>
                            <?php } ?>
                        </td>
                        <td><code><?= crm_h((string)($r['ico'] ?? '—')) ?></code></td>
                        <td style="font-size:0.78rem;">
                            <?= crm_h(function_exists('crm_region_label_short')
                                    ? crm_region_label_short((string) $r['region'])
                                    : (string) $r['region']) ?>
                        </td>
                        <td><?= crm_h((string)($r['oz_name'] ?? '—')) ?></td>
                        <td>
                            <span class="ma-badge"
                                  style="background:<?= $bBg ?>;color:<?= $bFg ?>;">
                                <?= crm_h($bText) ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?= crm_h(crm_url('/me/contact-detail?id=' . $cid)) ?>"
                               style="font-size:0.78rem;color:#0e7490;text-decoration:none;">
                                → Detail
                            </a>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        <p style="font-size:0.7rem;color:var(--color-text-muted);margin-top:0.6rem;text-align:right;">
            Zobrazeno <?= count($rows) ?> řádků (limit 500).
        </p>
    <?php } ?>

    <div class="ma-cta">
        💡 <strong>Tip:</strong> Když najdeš další hot lead, klikni v sidebaru
        <a href="<?= crm_h(crm_url('/contacts/new')) ?>">➕ Nový kontakt</a>.
        Vyplníš detaily, vybereš OZ a kontakt se mu rovnou objeví v Příchozí leady.
    </div>
</section>
