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
.dup-wrap { padding: 1.5rem 1rem; max-width: 1200px; margin: 0 auto; }
.dup-wrap h1 { margin: 0 0 0.5rem; font-size: 1.4rem; }
.dup-wrap > .lead { color: var(--bo-text-3, #888); font-size: 0.9rem; margin-bottom: 1.5rem; }
.dup-wrap .breadcrumb { margin-bottom: 0.8rem; font-size: 0.78rem; }
.dup-wrap .breadcrumb a { color: var(--brand-primary, #5a6cff); text-decoration: none; }

.dup-summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.8rem;
    margin-bottom: 1.5rem;
}
.dup-tile {
    display: block;
    padding: 1rem 1.2rem;
    border: 1px solid var(--bo-border, rgba(255,255,255,0.08));
    border-radius: 10px;
    background: var(--bo-surface, rgba(255,255,255,0.02));
    color: inherit;
    text-decoration: none;
    transition: all 0.15s;
}
.dup-tile:hover { border-color: var(--brand-primary, #5a6cff); transform: translateY(-1px); }
.dup-tile--active { border-color: var(--brand-primary, #5a6cff); background: rgba(90,108,255,0.08); }
.dup-tile__title { font-size: 0.78rem; font-weight: 700; color: var(--bo-text-2, #aaa); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; }
.dup-tile__count {
    font-size: 1.6rem; font-weight: 800; color: var(--bo-text, #fff); line-height: 1;
}
.dup-tile__count--zero { color: var(--bo-success, #66bb6a); }
.dup-tile__count--warn { color: var(--bo-warning, #f39c12); }
.dup-tile__sub { font-size: 0.75rem; color: var(--bo-text-3, #888); margin-top: 0.4rem; }

.dup-empty {
    padding: 3rem 1rem;
    text-align: center;
    color: var(--bo-text-3);
    background: rgba(102,187,106,0.04);
    border: 1px dashed rgba(102,187,106,0.3);
    border-radius: 10px;
}
.dup-empty__icon { font-size: 2.5rem; margin-bottom: 0.5rem; }

.dup-groups { display: flex; flex-direction: column; gap: 1rem; }
.dup-group {
    border: 1px solid var(--bo-border, rgba(255,255,255,0.08));
    border-left: 4px solid var(--bo-warning, #f39c12);
    border-radius: 8px;
    background: var(--bo-surface, rgba(255,255,255,0.02));
    overflow: hidden;
}
.dup-group__header {
    padding: 0.6rem 0.9rem;
    background: rgba(243,156,18,0.06);
    font-size: 0.85rem;
    border-bottom: 1px solid var(--bo-border, rgba(255,255,255,0.06));
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
}
.dup-group__key { font-weight: 800; font-family: 'Consolas', 'Monaco', monospace; }
.dup-group__count { font-size: 0.72rem; color: var(--bo-warning, #f39c12); padding: 0.15rem 0.6rem; background: rgba(243,156,18,0.15); border-radius: 999px; font-weight: 700; }

.dup-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
.dup-table th, .dup-table td { padding: 0.4rem 0.6rem; text-align: left; border-bottom: 1px solid var(--bo-border, rgba(255,255,255,0.05)); vertical-align: top; }
.dup-table th { font-weight: 700; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--bo-text-2, #aaa); background: rgba(255,255,255,0.02); }
.dup-table tr:last-child td { border-bottom: 0; }
.dup-table .id-col { font-family: 'Consolas', 'Monaco', monospace; font-weight: 700; }
.dup-table a { color: var(--brand-primary, #5a6cff); text-decoration: none; }
.dup-table a:hover { text-decoration: underline; }
.dup-table .stav-pill {
    display: inline-block;
    padding: 0.1rem 0.5rem;
    font-size: 0.68rem;
    font-weight: 700;
    border-radius: 999px;
    background: rgba(255,255,255,0.06);
    color: var(--bo-text-2, #aaa);
}
.dup-table .stav-pill--ok { background: rgba(102,187,106,0.15); color: #66bb6a; }
.dup-table .stav-pill--bad { background: rgba(231,76,60,0.15); color: #e74c3c; }

.dup-flash {
    padding: 0.6rem 1rem; border-radius: 6px;
    background: rgba(90,108,255,0.1); border: 1px solid rgba(90,108,255,0.25);
    margin-bottom: 1rem; font-size: 0.85rem;
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
        <a href="<?= crm_h(crm_url('/dashboard')) ?>" style="padding:0.25rem 0.55rem;border-radius:6px;background:rgba(90,108,255,0.1);border:1px solid rgba(90,108,255,0.25);">← Dashboard</a>
        &nbsp;
        <a href="<?= crm_h(crm_url('/admin/datagrid')) ?>" style="padding:0.25rem 0.55rem;border-radius:6px;background:rgba(90,108,255,0.1);border:1px solid rgba(90,108,255,0.25);">📊 Live datagrid</a>
        &nbsp;
        <a href="<?= crm_h(crm_url('/admin/import')) ?>" style="padding:0.25rem 0.55rem;border-radius:6px;background:rgba(90,108,255,0.1);border:1px solid rgba(90,108,255,0.25);">📥 Import</a>
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
