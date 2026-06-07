<?php
// e:\Snecinatripu\app\views\admin\contacts-added\index.php
declare(strict_types=1);
/** @var array<string,mixed>       $user */
/** @var string                    $csrf */
/** @var ?string                   $flash */
/** @var list<array<string,mixed>> $rows         max 500 nedávno přidaných */
/** @var list<array<string,mixed>> $topAdders    top přidávači (pro filtr) */
/** @var array<string,int>         $totals       total/today/last7 */
/** @var int                       $byUser       aktivní filtr uživatele (0 = vše) */
/** @var string                    $period       aktivní filtr období (today|7d|30d|90d|all) */

$totals = $totals ?? ['total' => 0, 'today' => 0, 'last7' => 0];

// Helper: barva věku (jak dlouho je v DB)
function ca_ageColor(string $created): string {
    if ($created === '') return 'var(--color-text-muted)';
    $ts = strtotime($created);
    if ($ts === false) return 'var(--color-text-muted)';
    $hours = (time() - $ts) / 3600;
    if ($hours <= 24)  return '#16a34a';
    if ($hours <= 168) return '#374151';
    return 'var(--color-text-muted)';
}
function ca_elapsed(string $created): string {
    if ($created === '') return '—';
    $ts = strtotime($created);
    if ($ts === false) return $created;
    $d = time() - $ts;
    if ($d < 60)    return 'právě teď';
    if ($d < 3600)  return 'před ' . (int)($d / 60) . ' min';
    if ($d < 86400) return 'před ' . (int)($d / 3600) . ' h';
    return 'před ' . (int)($d / 86400) . ' d';
}
?>

<style>
.ca-wrap { max-width: 1200px; margin: 0 auto; }
.ca-stats {
    display: flex; gap: 0.8rem; flex-wrap: wrap; margin-bottom: 1rem;
}
.ca-stat {
    flex: 1 1 160px;
    background: #fff; border: 1px solid var(--color-border);
    border-radius: 8px; padding: 0.75rem 1rem;
}
.ca-stat__val { font-size: 1.5rem; font-weight: 700; color: var(--color-text); }
.ca-stat__lbl { font-size: 0.75rem; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.04em; }

.ca-filters {
    display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: end;
    background: rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.06);
    border-radius: 8px; padding: 0.7rem 0.9rem; margin-bottom: 1rem;
}
.ca-filters label { display: flex; flex-direction: column; gap: 0.2rem; font-size: 0.74rem; color: var(--color-text-muted); }
.ca-filters select { padding: 0.35rem 0.55rem; font-size: 0.85rem; border: 1px solid var(--color-border-strong); border-radius: 5px; }
.ca-filters .ca-period {
    display: flex; gap: 0.3rem;
}
.ca-filters .ca-period a {
    padding: 0.25rem 0.65rem; font-size: 0.77rem;
    text-decoration: none; border-radius: 12px;
    background: #fff; color: #374151;
    border: 1px solid rgba(0,0,0,0.12);
}
.ca-filters .ca-period a.active {
    background: #0e7490; color: #fff; border-color: #0e7490; font-weight: 700;
}

.ca-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 6px; overflow: hidden; }
.ca-table th, .ca-table td {
    padding: 0.5rem 0.7rem; text-align: left;
    border-bottom: 1px solid rgba(0,0,0,0.05); font-size: 0.85rem; vertical-align: top;
}
.ca-table th {
    background: rgba(0,0,0,0.03); font-size: 0.7rem; text-transform: uppercase;
    color: var(--color-text-muted); font-weight: 600; letter-spacing: 0.03em;
}
.ca-table tr:hover td { background: rgba(0,0,0,0.02); }
.ca-table code { font-family: monospace; font-size: 0.78rem; color: #6b7280; }
.ca-badge {
    display: inline-block; padding: 0.1rem 0.5rem; border-radius: 10px;
    font-size: 0.7rem; font-weight: 600; white-space: nowrap;
}
.ca-empty { text-align: center; padding: 3rem 1rem; color: var(--color-text-muted); }
</style>

<section class="ca-wrap">
    <h1 style="margin:0 0 0.25rem;">📋 Přidané kontakty <span style="color:var(--color-text-muted);font-weight:500;font-size:1.1rem;">(doporučenky)</span></h1>
    <p style="color:var(--color-text-muted);font-size:0.85rem;margin-bottom:1rem;">
        Všechny kontakty, které zaměstnanci přidali přímo přes „<strong>➕ Nový kontakt</strong>"
        (mimo navolávačku). Vidíš <strong>kdo</strong> kontakt přidal,
        <strong>komu</strong> ho přiřadil a v jakém je <strong>aktuálním stavu</strong>.
    </p>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info" style="margin-bottom:1rem;"><?= crm_h($flash) ?></p>
    <?php } ?>

    <!-- Stats -->
    <div class="ca-stats">
        <div class="ca-stat">
            <div class="ca-stat__val"><?= (int) $totals['total'] ?></div>
            <div class="ca-stat__lbl">Celkem přidaných</div>
        </div>
        <div class="ca-stat">
            <div class="ca-stat__val"><?= (int) $totals['today'] ?></div>
            <div class="ca-stat__lbl">Dnes</div>
        </div>
        <div class="ca-stat">
            <div class="ca-stat__val"><?= (int) $totals['last7'] ?></div>
            <div class="ca-stat__lbl">Posledních 7 dní</div>
        </div>
    </div>

    <!-- Filtry -->
    <form method="get" action="<?= crm_h(crm_url('/admin/contacts/added')) ?>" class="ca-filters">
        <label>
            Kdo přidal:
            <select name="by" onchange="this.form.submit()">
                <option value="0">— všichni —</option>
                <?php foreach ($topAdders as $a) {
                    $uid  = (int) $a['uid'];
                    $sel  = $uid === $byUser ? 'selected' : '';
                ?>
                    <option value="<?= $uid ?>" <?= $sel ?>>
                        <?= crm_h((string) $a['jmeno']) ?>
                        (<?= crm_h((string) $a['role']) ?>)
                        · <?= (int) $a['cnt'] ?>
                    </option>
                <?php } ?>
            </select>
        </label>
        <div class="ca-period">
            <?php
            $periodMap = [
                'today' => 'Dnes',
                '7d'    => 'Poslední týden',
                '30d'   => 'Posledních 30 dní',
                '90d'   => 'Posledních 90 dní',
                'all'   => 'Vše',
            ];
            foreach ($periodMap as $key => $lbl) {
                $url = '/admin/contacts/added?period=' . $key
                    . ($byUser > 0 ? '&by=' . $byUser : '');
                $cls = $key === $period ? 'active' : '';
                echo '<a href="' . crm_h(crm_url($url)) . '" class="' . $cls . '">' . crm_h($lbl) . '</a>';
            }
            ?>
        </div>
        <noscript>
            <button type="submit" style="padding:0.35rem 0.7rem;font-size:0.8rem;">Filtrovat</button>
        </noscript>
    </form>

    <!-- Tabulka -->
    <?php if ($rows === []) { ?>
        <div class="ca-empty">
            <div style="font-size:2rem;margin-bottom:0.5rem;">📭</div>
            <div>V tomto období nikdo nepřidal žádnou doporučenku.</div>
            <div style="font-size:0.78rem;margin-top:0.4rem;">
                Zkus jiné období (<a href="<?= crm_h(crm_url('/admin/contacts/added?period=all')) ?>">Vše</a>)
                nebo jiného uživatele.
            </div>
        </div>
    <?php } else { ?>
        <table class="ca-table">
            <thead>
                <tr>
                    <th>Kdy</th>
                    <th>Kdo přidal</th>
                    <th>Firma</th>
                    <th>IČO</th>
                    <th>Kraj</th>
                    <th>Přiřazený OZ</th>
                    <th>Stav</th>
                    <th>Akce</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r) {
                    $cid     = (int) $r['id'];
                    $created = (string) ($r['created_at'] ?? '');
                    $ageCol  = ca_ageColor($created);
                ?>
                    <tr>
                        <td>
                            <div style="color:<?= $ageCol ?>;font-weight:500;">
                                <?= crm_h(ca_elapsed($created)) ?>
                            </div>
                            <div style="font-size:0.7rem;color:var(--color-text-muted);">
                                <?= crm_h(date('d.m.Y H:i', strtotime($created) ?: 0)) ?>
                            </div>
                        </td>
                        <td>
                            <strong><?= crm_h((string)($r['adder_name'] ?? '—')) ?></strong>
                            <div style="font-size:0.7rem;color:var(--color-text-muted);">
                                <?= crm_h((string)($r['adder_role'] ?? '')) ?>
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
                        <td><code style="font-size:0.74rem;"><?= crm_h((string)($r['effective_stav'] ?? '—')) ?></code></td>
                        <td>
                            <a href="<?= crm_h(crm_url('/oz/search/card?id=' . $cid)) ?>"
                               target="_blank"
                               style="font-size:0.78rem;color:#0e7490;text-decoration:none;">
                                → Karta
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
</section>
