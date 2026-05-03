<?php
// e:\Snecinatripu\app\views\admin\oz_milestones.php
declare(strict_types=1);
/** @var array<string, mixed>       $user */
/** @var list<array<string, mixed>> $ozUsers        – id, jmeno */
/** @var int                        $selectedOzId */
/** @var list<array<string, mixed>> $milestones     – id, label, target_bmsl, reward_note */
/** @var int                        $year */
/** @var int                        $month */
/** @var string|null                $flash */
/** @var string                     $csrf */

$czechMonths = ['','Leden','Únor','Březen','Duben','Květen','Červen',
                'Červenec','Srpen','Září','Říjen','Listopad','Prosinec'];

$selectedOzName = '';
foreach ($ozUsers as $u) {
    if ((int) $u['id'] === $selectedOzId) { $selectedOzName = (string) $u['jmeno']; break; }
}
?>

<style>
.ms-header { display:flex; align-items:center; flex-wrap:wrap; gap:.6rem; margin-bottom:1.1rem; }
.ms-header__title { font-size:1.05rem; font-weight:700; flex:1; }
.ms-selectors { display:flex; gap:.35rem; align-items:center; flex-wrap:wrap; }
.ms-sel {
    font-size:.8rem; padding:.25rem .45rem;
    background:var(--bg); color:var(--text);
    border:1px solid rgba(0,0,0,.15); border-radius:5px;
}

.ms-add-form {
    display:flex; gap:.5rem; flex-wrap:wrap; align-items:flex-end;
    background:rgba(155,89,182,.06); border:1px solid rgba(155,89,182,.18);
    border-radius:8px; padding:.75rem 1rem; margin-bottom:1.2rem;
}
.ms-add-form__group { display:flex; flex-direction:column; gap:.2rem; flex:1; min-width:150px; }
.ms-add-form__label { font-size:.68rem; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; }
.ms-add-form__input {
    font-size:.82rem; padding:.3rem .55rem;
    background:var(--bg); color:var(--text);
    border:1px solid rgba(0,0,0,.15); border-radius:6px;
}
.ms-add-form__input:focus { outline:none; border-color:rgba(155,89,182,.5); }

.ms-list { display:flex; flex-direction:column; gap:.5rem; }
.ms-item {
    display:flex; align-items:center; gap:.75rem; flex-wrap:wrap;
    background:var(--card); border:1px solid rgba(0,0,0,.07);
    border-radius:8px; padding:.6rem .9rem;
}
.ms-item__label  { font-size:.85rem; font-weight:600; flex:1; }
.ms-item__bmsl   { font-family:monospace; font-size:.85rem; color:#9b59b6; white-space:nowrap; }
.ms-item__reward {
    font-size:.72rem; color:var(--muted); font-style:italic;
    max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
}
.ms-item__del { margin-left:auto; }
.ms-empty { color:var(--muted); font-size:.83rem; font-style:italic; text-align:center; padding:1rem 0; }
.ms-hint  { font-size:.75rem; color:var(--muted); margin-top:1rem; line-height:1.6; }
.ms-no-oz { color:var(--muted); text-align:center; padding:1.5rem 0; }
</style>

<section class="card">

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info"><?= crm_h($flash) ?></p>
    <?php } ?>

    <div class="ms-header">
        <span class="ms-header__title">🎯 Osobní milníky OZ — <?= crm_h($czechMonths[$month] . ' ' . $year) ?></span>

        <form method="get" action="<?= crm_h(crm_url('/admin/oz-milestones')) ?>" class="ms-selectors">
            <?php if ($selectedOzId) { ?>
                <input type="hidden" name="oz_id" value="<?= $selectedOzId ?>">
            <?php } ?>
            <select name="month" class="ms-sel" onchange="this.form.submit()">
                <?php for ($m = 1; $m <= 12; $m++) { ?>
                    <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= crm_h($czechMonths[$m]) ?></option>
                <?php } ?>
            </select>
            <select name="year" class="ms-sel" onchange="this.form.submit()">
                <?php for ($y = 2024; $y <= (int) date('Y') + 1; $y++) { ?>
                    <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
                <?php } ?>
            </select>
        </form>

        <a href="<?= crm_h(crm_url('/admin/oz-stages')) ?>" class="btn btn-secondary btn-sm">⚙ Stage cíle</a>
        <a href="<?= crm_h(crm_url('/oz/performance')) ?>" class="btn btn-secondary btn-sm">📊 Výkon týmu</a>
        <a href="<?= crm_h(crm_url('/admin/oz-targets')) ?>" class="btn btn-secondary btn-sm">← Kvóty OZ</a>
    </div>

    <?php if ($ozUsers === []) { ?>
        <p class="ms-no-oz">Žádní aktivní obchodní zástupci v systému.</p>
    <?php } else { ?>

    <!-- Výběr OZ -->
    <div style="margin-bottom:1rem;">
        <form method="get" action="<?= crm_h(crm_url('/admin/oz-milestones')) ?>" style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="year"  value="<?= $year ?>">
            <input type="hidden" name="month" value="<?= $month ?>">
            <label style="font-size:.78rem;color:var(--muted);">Obchodní zástupce:</label>
            <select name="oz_id" class="ms-sel" onchange="this.form.submit()" style="min-width:180px;">
                <?php foreach ($ozUsers as $u) { ?>
                    <option value="<?= (int) $u['id'] ?>" <?= (int) $u['id'] === $selectedOzId ? 'selected' : '' ?>>
                        <?= crm_h((string) $u['jmeno']) ?>
                    </option>
                <?php } ?>
            </select>
        </form>
    </div>

    <?php if ($selectedOzId > 0) { ?>

    <!-- Formulář: přidat milník -->
    <form method="post" action="<?= crm_h(crm_url('/admin/oz-milestones/save')) ?>">
        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
        <input type="hidden" name="year"  value="<?= $year ?>">
        <input type="hidden" name="month" value="<?= $month ?>">
        <input type="hidden" name="oz_id" value="<?= $selectedOzId ?>">

        <div class="ms-add-form">
            <div class="ms-add-form__group">
                <label class="ms-add-form__label">Popisek milníku</label>
                <input type="text" name="label" class="ms-add-form__input"
                       placeholder="např. Hvězda měsíce, Top performer…"
                       maxlength="100" required>
            </div>
            <div class="ms-add-form__group" style="max-width:180px;">
                <label class="ms-add-form__label">BMSL cíl (Kč, bez DPH)</label>
                <input type="number" name="target_bmsl" class="ms-add-form__input"
                       placeholder="např. 50000" min="100" step="100" required>
            </div>
            <div class="ms-add-form__group" style="min-width:200px;">
                <label class="ms-add-form__label">Odměna / poznámka</label>
                <input type="text" name="reward_note" class="ms-add-form__input"
                       placeholder="např. Bonus 2 000 Kč, extra volno…"
                       maxlength="200">
            </div>
            <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end;">
                + Přidat milník pro <?= crm_h($selectedOzName) ?>
            </button>
        </div>
    </form>

    <!-- Seznam milníků -->
    <?php if ($milestones === []) { ?>
        <p class="ms-empty">Žádné osobní milníky pro <?= crm_h($selectedOzName) ?> v <?= crm_h($czechMonths[$month] . ' ' . $year) ?>.</p>
    <?php } else { ?>
    <div class="ms-list">
        <?php foreach ($milestones as $ms) { ?>
        <div class="ms-item">
            <span class="ms-item__label">🎯 <?= crm_h((string) $ms['label']) ?></span>
            <span class="ms-item__bmsl"><?= number_format((int) $ms['target_bmsl'], 0, ',', ' ') ?> Kč</span>
            <?php if (!empty($ms['reward_note'])) { ?>
            <span class="ms-item__reward" title="<?= crm_h((string) $ms['reward_note']) ?>">
                🎁 <?= crm_h((string) $ms['reward_note']) ?>
            </span>
            <?php } ?>
            <form method="post" action="<?= crm_h(crm_url('/admin/oz-milestones/delete')) ?>"
                  class="ms-item__del"
                  onsubmit="return confirm('Smazat milník: <?= crm_h(addslashes((string)$ms['label'])) ?>?')">
                <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                <input type="hidden" name="year"         value="<?= $year ?>">
                <input type="hidden" name="month"        value="<?= $month ?>">
                <input type="hidden" name="oz_id"        value="<?= $selectedOzId ?>">
                <input type="hidden" name="milestone_id" value="<?= (int) $ms['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">✕</button>
            </form>
        </div>
        <?php } ?>
    </div>
    <?php } ?>

    <?php } /* selectedOzId > 0 */ ?>
    <?php } /* ozUsers !== [] */ ?>

    <p class="ms-hint">
        💡 Osobní milníky jsou viditelné přímo na pracovní ploše OZ — zobrazí se jako progress bar.<br>
        Každý OZ vidí jen své vlastní milníky. Odměna je orientační popis pro majitele,
        CRM ji nevyplácí automaticky.<br>
        Hodnoty BMSL jsou zaokrouhleny dolů na celé stovky.
    </p>

</section>
