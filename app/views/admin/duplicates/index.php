<?php
/**
 * @var string $title
 * @var ?string $flash
 * @var string $csrf
 * @var string $key
 * @var array<string,array{distinct_keys:int,total_extra_rows:int}> $summary
 * @var list<array{key_value:string,count:int,contacts:list<array<string,mixed>>}> $groups
 */
$keyLabels = [
    'telefon' => '📞 Telefon',
    'email'   => '📧 E-mail',
    'ico'     => '🏢 IČO',
];
$totalDuplicateRows = array_sum(array_column($summary, 'total_extra_rows'));
?>
<style>
.dup-wrap { padding: 0.8rem 1rem 1.5rem; max-width: 1200px; margin: 0 auto; }
.dup-wrap h1 { margin: 0 0 0.5rem; font-size: 1.4rem; color: var(--color-text); }
.dup-wrap > .lead { color: var(--color-text-muted); font-size: 0.9rem; margin-bottom: 1.5rem; }
.dup-wrap .breadcrumb {
    position: sticky;
    top: 0;
    z-index: 20;
    margin: -0.8rem -1rem 0.8rem;
    padding: 0.55rem 1rem;
    background: var(--color-card-bg);
    border-bottom: 1px solid var(--color-border);
    font-size: 0.78rem;
    display: flex; gap: 0.4rem; flex-wrap: wrap;
}
.dup-wrap .breadcrumb a {
    color: var(--color-badge-nove);
    text-decoration: none;
    padding: 0.25rem 0.6rem !important;
    border-radius: var(--radius-btn) !important;
    background: var(--color-badge-nove-bg) !important;
    border: 1px solid #b5d4f4 !important;
    font-weight: 600;
}
.dup-wrap .breadcrumb a:hover { background: #d4e5f7 !important; }
.dup-wrap .breadcrumb a.is-current { background: var(--color-badge-nove) !important; color: #fff; border-color: var(--color-badge-nove) !important; }

.dup-summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.8rem;
    margin-bottom: 1.5rem;
}
.dup-tile {
    display: block;
    padding: 1rem 1.2rem;
    border: 1px solid var(--color-border);
    border-radius: 10px;
    background: var(--color-card-bg);
    color: inherit;
    text-decoration: none;
    transition: all 0.15s;
    box-shadow: var(--shadow-card);
}
.dup-tile:hover { border-color: var(--color-badge-nove); transform: translateY(-1px); box-shadow: var(--shadow-card-hover); }
.dup-tile--active { border-color: var(--color-badge-nove); background: var(--color-badge-nove-bg); }
.dup-tile__title { font-size: 0.78rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; }
.dup-tile__count {
    font-size: 1.6rem; font-weight: 800; color: var(--color-text); line-height: 1;
}
.dup-tile__count--zero { color: var(--color-badge-uzavreno); }
.dup-tile__count--warn { color: var(--color-accent); }
.dup-tile__sub { font-size: 0.75rem; color: var(--color-text-light); margin-top: 0.4rem; }

.dup-empty {
    padding: 3rem 1rem;
    text-align: center;
    color: var(--color-text-muted);
    background: var(--color-badge-uzavreno-bg);
    border: 1px dashed #86efac;
    border-radius: 10px;
}
.dup-empty__icon { font-size: 2.5rem; margin-bottom: 0.5rem; }

.dup-groups { display: flex; flex-direction: column; gap: 1rem; }
.dup-group {
    border: 1px solid var(--color-border);
    border-left: 4px solid var(--color-accent);
    border-radius: 8px;
    background: var(--color-card-bg);
    overflow: hidden;
    box-shadow: var(--shadow-card);
}
.dup-group__header {
    padding: 0.6rem 0.9rem;
    background: var(--color-badge-callback-bg);
    font-size: 0.85rem;
    border-bottom: 1px solid var(--color-border);
    color: var(--color-badge-callback-text);
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
}
.dup-group__key { font-weight: 800; font-family: 'Consolas', 'Monaco', monospace; }
.dup-group__count { font-size: 0.72rem; color: var(--color-badge-callback-text); padding: 0.15rem 0.6rem; background: #ffffff; border-radius: 999px; font-weight: 700; border: 1px solid #fde68a; }

.dup-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
.dup-table th, .dup-table td { padding: 0.5rem 0.6rem; text-align: left; border-bottom: 1px solid var(--color-border); vertical-align: top; }
.dup-table th { font-weight: 700; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--color-text-muted); background: var(--color-surface); }
.dup-table tr:last-child td { border-bottom: 0; }
.dup-table tr:hover td { background: var(--color-surface); }
.dup-table .id-col { font-family: 'Consolas', 'Monaco', monospace; font-weight: 700; }
.dup-table a { color: var(--color-badge-nove); text-decoration: none; font-weight: 600; }
.dup-table a:hover { text-decoration: underline; }
.dup-table .stav-pill {
    display: inline-block;
    padding: 0.15rem 0.55rem;
    font-size: 0.68rem;
    font-weight: 700;
    border-radius: 999px;
    background: var(--color-surface);
    color: var(--color-text-muted);
    border: 1px solid var(--color-border);
}
.dup-table .stav-pill--ok { background: var(--color-badge-uzavreno-bg); color: var(--color-badge-uzavreno-text); border-color: #bbf7d0; }
.dup-table .stav-pill--bad { background: var(--color-badge-reklamace-bg); color: var(--color-badge-reklamace-text); border-color: #fca5a5; }

.dup-flash {
    padding: 0.7rem 1rem; border-radius: var(--radius-card);
    background: var(--color-badge-nove-bg);
    border: 1px solid #b5d4f4;
    border-left: 4px solid var(--color-badge-nove);
    color: var(--color-badge-nove-text);
    margin-bottom: 1rem; font-size: 0.85rem; font-weight: 500;
}
@media (max-width: 1000px) {
    .dup-summary { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 700px) {
    .dup-wrap { padding: 0.8rem 0.6rem; }
    .dup-wrap h1 { font-size: 1.15rem; }
    .dup-summary { grid-template-columns: 1fr; }
    /* Tabulky duplicit — horizontální scroll */
    .dup-group { overflow-x: auto; }
    .dup-table { font-size: 0.7rem; min-width: 700px; }
    .dup-table th, .dup-table td { padding: 0.3rem 0.4rem; }
}
</style>

<section class="dup-wrap">
    <div class="breadcrumb">
        <a href="<?= crm_h(crm_url('/dashboard')) ?>">← Dashboard</a>
        <a href="#" class="is-current">🕵 Audit duplicit</a>
        <a href="<?= crm_h(crm_url('/admin/feed')) ?>">📰 Activity feed</a>
        <a href="<?= crm_h(crm_url('/admin/datagrid')) ?>">📊 Live datagrid</a>
        <a href="<?= crm_h(crm_url('/admin/import')) ?>">📥 Import</a>
        <a href="<?= crm_h(crm_url('/admin/users')) ?>">👥 Uživatelé</a>
    </div>

    <h1>🕵 Audit duplicit v databázi</h1>
    <p class="lead">
        Před nahráním nové dávky kontaktů zkontroluj, jestli v DB nejsou staré duplicity. Read-only — žádné mazání ani slučování. Klikni na kontakt a vyřeš ho v BO/OZ workflow.
    </p>

    <?php if ($flash) { ?>
        <div class="dup-flash"><?= crm_h($flash) ?></div>
    <?php } ?>

    <!-- Souhrnné dlaždice -->
    <div class="dup-summary">
        <?php foreach (['telefon','email','ico'] as $col) {
            $s = $summary[$col];
            $isActive = $key === $col;
            $countClass = $s['distinct_keys'] === 0 ? 'dup-tile__count--zero' : 'dup-tile__count--warn';
        ?>
        <a href="<?= crm_h(crm_url('/admin/duplicates') . ($s['distinct_keys'] > 0 ? '?key=' . $col : '')) ?>"
           class="dup-tile <?= $isActive ? 'dup-tile--active' : '' ?>"
           <?= $s['distinct_keys'] === 0 ? 'aria-disabled="true"' : '' ?>>
            <div class="dup-tile__title"><?= $keyLabels[$col] ?></div>
            <div class="dup-tile__count <?= $countClass ?>"><?= $s['distinct_keys'] ?></div>
            <div class="dup-tile__sub">
                <?= $s['distinct_keys'] === 0
                    ? '✓ žádné duplicity'
                    : ($s['distinct_keys'] . ' opakujících se hodnot · ' . $s['total_extra_rows'] . ' přebývajících řádků') ?>
            </div>
        </a>
        <?php } ?>
    </div>

    <?php if ($key === '' && array_sum(array_column($summary, 'distinct_keys')) === 0) { ?>
        <div class="dup-empty">
            <div class="dup-empty__icon">🎉</div>
            <strong>Žádné duplicity v DB!</strong><br>
            <small>Můžeš v klidu importovat novou dávku.</small>
        </div>
    <?php } elseif ($key === '') { ?>
        <div class="dup-empty">
            <div class="dup-empty__icon">👆</div>
            <strong>Klikni na dlaždici výše</strong><br>
            <small>Vyber klíč (telefon / email / IČO), ať uvidíš detaily duplicit.</small>
        </div>
    <?php } else { ?>
        <h2 style="font-size: 1.05rem; margin: 0 0 0.8rem;">
            Detail: <?= $keyLabels[$key] ?>
            <small style="color: var(--bo-text-3); font-weight: normal;">
                (<?= count($groups) ?> skupin <?= count($groups) === 200 ? '· max 200 zobrazeno' : '' ?>)
            </small>
        </h2>

        <?php if (!$groups) { ?>
            <div class="dup-empty">
                <div class="dup-empty__icon">✓</div>
                <strong>Žádné duplicity podle <?= $keyLabels[$key] ?></strong>
            </div>
        <?php } else { ?>
            <div class="dup-groups">
                <?php foreach ($groups as $g) { ?>
                    <div class="dup-group">
                        <div class="dup-group__header">
                            <span class="dup-group__key"><?= crm_h($g['key_value']) ?></span>
                            <span class="dup-group__count"><?= $g['count'] ?>× v DB</span>
                        </div>
                        <table class="dup-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Firma</th>
                                    <th>IČO</th>
                                    <th>Telefon</th>
                                    <th>E-mail</th>
                                    <th>Region</th>
                                    <th>Stav</th>
                                    <th>Workflow</th>
                                    <th>OZ / Caller</th>
                                    <th>Vytvořeno</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($g['contacts'] as $c) {
                                    $cId = (int) $c['id'];
                                    $stavCls = match (true) {
                                        in_array($c['workflow_stav'], ['UZAVRENO'], true)         => 'stav-pill--ok',
                                        in_array($c['workflow_stav'], ['NEZAJEM','REKLAMACE'], true) => 'stav-pill--bad',
                                        default => '',
                                    };
                                ?>
                                    <tr>
                                        <td class="id-col"><?= $cId ?></td>
                                        <td><strong><?= crm_h((string)($c['firma'] ?? '')) ?></strong></td>
                                        <td><?= crm_h((string)($c['ico'] ?? '')) ?></td>
                                        <td><?= crm_h((string)($c['telefon'] ?? '')) ?></td>
                                        <td><?= crm_h((string)($c['email'] ?? '')) ?></td>
                                        <td><?= crm_h((string)($c['region'] ?? '')) ?></td>
                                        <td><span class="stav-pill"><?= crm_h((string)($c['contact_stav'] ?? '')) ?></span></td>
                                        <td><span class="stav-pill <?= $stavCls ?>"><?= crm_h((string)($c['workflow_stav'] ?? '—')) ?></span></td>
                                        <td>
                                            <?php if (!empty($c['oz_name'])) { ?>
                                                <small>OZ: <?= crm_h((string)$c['oz_name']) ?></small><br>
                                            <?php } ?>
                                            <?php if (!empty($c['caller_name'])) { ?>
                                                <small>📞 <?= crm_h((string)$c['caller_name']) ?></small>
                                            <?php } ?>
                                        </td>
                                        <td><small><?= crm_h(substr((string)($c['created_at'] ?? ''), 0, 10)) ?></small></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    <?php } ?>
</section>
