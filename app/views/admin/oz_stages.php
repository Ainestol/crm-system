<?php
// e:\Snecinatripu\app\views\admin\oz_stages.php
declare(strict_types=1);
/** @var array<string, mixed>       $user */
/** @var list<array<string, mixed>> $stages  – id, stage_number, label, target_bmsl */
/** @var int                        $year */
/** @var int                        $month */
/** @var string|null                $flash */
/** @var string                     $csrf */

$czechMonths = ['','Leden','Únor','Březen','Duben','Květen','Červen',
                'Červenec','Srpen','Září','Říjen','Listopad','Prosinec'];
?>

<style>
.stages-header {
    display: flex; align-items: center; flex-wrap: wrap;
    gap: 0.6rem; margin-bottom: 1.1rem;
}
.stages-header__title { font-size: 1.05rem; font-weight: 700; flex: 1; }
.stages-month-form { display: flex; gap: 0.3rem; align-items: center; }
.stages-month-sel {
    font-size: 0.8rem; padding: 0.25rem 0.45rem;
    background: var(--bg); color: var(--text);
    border: 1px solid rgba(255,255,255,0.15); border-radius: 5px;
}

/* Přidat form */
.stages-add-form {
    display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: flex-end;
    background: rgba(155,89,182,0.06); border: 1px solid rgba(155,89,182,0.18);
    border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1.2rem;
}
.stages-add-form__group { display: flex; flex-direction: column; gap: 0.2rem; flex: 1; min-width: 160px; }
.stages-add-form__label { font-size: 0.68rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; }
.stages-add-form__input {
    font-size: 0.82rem; padding: 0.3rem 0.55rem;
    background: var(--bg); color: var(--text);
    border: 1px solid rgba(255,255,255,0.15); border-radius: 6px;
}
.stages-add-form__input:focus { outline: none; border-color: rgba(155,89,182,0.5); }

/* Existující stages */
.stages-list { display: flex; flex-direction: column; gap: 0.5rem; }
.stages-item {
    display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;
    background: var(--card); border: 1px solid rgba(255,255,255,0.07);
    border-radius: 8px; padding: 0.6rem 0.9rem;
}
.stages-item__num {
    font-size: 0.7rem; padding: 0.15rem 0.5rem;
    background: rgba(155,89,182,0.15); color: #9b59b6;
    border-radius: 4px; font-weight: 700; white-space: nowrap;
}
.stages-item__label { font-size: 0.85rem; font-weight: 600; flex: 1; }
.stages-item__bmsl  { font-family: monospace; font-size: 0.85rem; color: #9b59b6; white-space: nowrap; }
.stages-item__del   { margin-left: auto; }

.stages-empty {
    color: var(--muted); font-size: 0.83rem; font-style: italic;
    text-align: center; padding: 1rem 0;
}
.stages-hint {
    font-size: 0.75rem; color: var(--muted); margin-top: 1rem; line-height: 1.5;
}
</style>

<section class="card">

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info"><?= crm_h($flash) ?></p>
    <?php } ?>

    <!-- Záhlaví -->
    <div class="stages-header">
        <span class="stages-header__title">
            ⚙ Stage cíle OZ — <?= crm_h($czechMonths[$month] . ' ' . $year) ?>
        </span>

        <form method="get" action="<?= crm_h(crm_url('/admin/oz-stages')) ?>" class="stages-month-form">
            <select name="month" class="stages-month-sel" onchange="this.form.submit()">
                <?php for ($m = 1; $m <= 12; $m++) { ?>
                    <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>>
                        <?= crm_h($czechMonths[$m]) ?>
                    </option>
                <?php } ?>
            </select>
            <select name="year" class="stages-month-sel" onchange="this.form.submit()">
                <?php for ($y = 2024; $y <= (int) date('Y') + 1; $y++) { ?>
                    <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
                <?php } ?>
            </select>
        </form>

        <a href="<?= crm_h(crm_url('/oz/performance?year=' . $year . '&month=' . $month)) ?>"
           class="btn btn-secondary btn-sm">📊 Zobrazit výkon týmu</a>
        <a href="<?= crm_h(crm_url('/admin/oz-targets')) ?>"
           class="btn btn-secondary btn-sm">← Kvóty OZ</a>
    </div>

    <!-- Formulář: přidat stage -->
    <form method="post" action="<?= crm_h(crm_url('/admin/oz-stages/save')) ?>">
        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
        <input type="hidden" name="year"  value="<?= $year ?>">
        <input type="hidden" name="month" value="<?= $month ?>">

        <div class="stages-add-form">
            <div class="stages-add-form__group">
                <label class="stages-add-form__label">Popisek stage</label>
                <input type="text" name="label" class="stages-add-form__input"
                       placeholder="např. Bronze, Standard, Gold…"
                       maxlength="100" required>
            </div>
            <div class="stages-add-form__group" style="max-width:200px;">
                <label class="stages-add-form__label">BMSL cíl (Kč, bez DPH)</label>
                <input type="number" name="target_bmsl" class="stages-add-form__input"
                       placeholder="např. 500000" min="1" step="1" required>
            </div>
            <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end;">
                + Přidat stage
            </button>
        </div>
    </form>

    <!-- Seznam existujících stages -->
    <?php if ($stages === []) { ?>
        <p class="stages-empty">Žádné stage cíle pro tento měsíc. Přidejte první výše.</p>
    <?php } else { ?>
    <div class="stages-list">
        <?php foreach ($stages as $s) { ?>
        <div class="stages-item">
            <span class="stages-item__num">Stage <?= (int) $s['stage_number'] ?></span>
            <span class="stages-item__label"><?= crm_h((string) $s['label']) ?></span>
            <span class="stages-item__bmsl">
                <?= number_format((int) $s['target_bmsl'], 0, ',', ' ') ?> Kč
            </span>
            <form method="post" action="<?= crm_h(crm_url('/admin/oz-stages/delete')) ?>"
                  class="stages-item__del"
                  onsubmit="return confirm('Smazat stage <?= (int) $s['stage_number'] ?>: <?= crm_h(addslashes((string)$s['label'])) ?>?')">
                <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                <input type="hidden" name="year"     value="<?= $year ?>">
                <input type="hidden" name="month"    value="<?= $month ?>">
                <input type="hidden" name="stage_id" value="<?= (int) $s['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">✕ Smazat</button>
            </form>
        </div>
        <?php } ?>
    </div>
    <?php } ?>

    <p class="stages-hint">
        💡 Stages jsou seřazeny automaticky dle výše BMSL cíle (nejnižší = Stage 1).
        Každý měsíc má vlastní sadu stage cílů — nastavte pro každý měsíc zvlášť.<br>
        Na stránce <strong>Výkon OZ týmu</strong> se zobrazí progress bar ukazující
        celkový BMSL týmu vůči nastaveným cílům.
    </p>

</section>
