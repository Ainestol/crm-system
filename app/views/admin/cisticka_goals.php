<?php
// e:\Snecinatripu\app\views\admin\cisticka_goals.php
declare(strict_types=1);
/** @var array<string,mixed>      $user */
/** @var string|null              $flash */
/** @var string                   $csrf */
/** @var array<string,int>        $existing          region → monthly_target (pro vybraný měsíc) */
/** @var array<string,int>        $existingPriority  region → priority 1-10 (pro vybraný měsíc) */
/** @var array<string,int>        $progress          region → done count ve vybraném měsíci */
/** @var list<string>             $allRegions        všechny dostupné kraje */
/** @var string                   $monthLabel        např. "květen 2026" (vybraný měsíc) */
/** @var list<array{key:string,period:int,label:string}> $monthOptions  možnosti pro <select> */
/** @var string                   $selectedMonthKey   "YYYY-MM" — pro <select>/hidden */
/** @var bool                     $isCurrentMonth */
/** @var bool                     $isFutureMonth */
/** @var bool                     $isPastMonth */
?>
<style>
.goals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 0.7rem;
    margin: 0.6rem 0 1rem;
}
.goal-row {
    background: rgba(0,0,0,0.03);
    border: 1px solid rgba(0,0,0,0.08);
    border-left: 3px solid rgba(46,204,113,0.5);
    border-radius: 8px;
    padding: 0.6rem 0.85rem;
    display: flex; flex-direction: column; gap: 0.4rem;
}
.goal-row--completed { border-left-color: #2ecc71; background: rgba(46,204,113,0.06); }
.goal-row--off       { border-left-color: rgba(0,0,0,0.12); opacity: 0.7; }

.goal-row__head {
    display: flex; align-items: center; gap: 0.55rem;
}
.goal-row__label { flex: 1; font-size: 0.92rem; font-weight: 600; }
.goal-row__input {
    width: 90px;
    padding: 0.35rem 0.55rem;
    background: var(--bg); color: var(--text);
    border: 1px solid rgba(0,0,0,0.18);
    border-radius: 5px;
    font-size: 0.95rem; font-weight: 700;
    text-align: right;
    font-family: monospace;
}
.goal-row__input:focus { outline: none; border-color: rgba(46,204,113,0.6); }
.goal-row__input--has-value { border-color: rgba(46,204,113,0.5); }
.goal-row__hint  { font-size: 0.7rem; color: var(--muted); }

/* ── Priorita input (vedle targetu) ─────────────────────────────── */
.goal-row__prio {
    display: flex; align-items: center; gap: 0.4rem;
    margin-top: 0.15rem;
    font-size: 0.72rem; color: var(--muted);
}
.goal-row__prio-label { font-weight: 600; letter-spacing: 0.3px; text-transform: uppercase; font-size: 0.66rem; }
.goal-row__prio-input {
    width: 56px;
    padding: 0.22rem 0.4rem;
    background: var(--bg); color: var(--text);
    border: 1px solid rgba(0,0,0,0.18);
    border-radius: 4px;
    font-size: 0.82rem; font-weight: 700;
    text-align: center;
    font-family: monospace;
}
.goal-row__prio-input:focus { outline: none; border-color: rgba(241,196,15,0.7); }
.goal-row__prio-input.is-top { border-color: rgba(231,76,60,0.6); color: #e74c3c; }
.goal-row__prio-hint { font-size: 0.68rem; color: var(--muted); font-style: italic; }

.goal-row__bar {
    height: 8px; border-radius: 4px;
    background: rgba(0,0,0,0.07);
    overflow: hidden;
    position: relative;
}
.goal-row__bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #27ae60 0%, #2ecc71 100%);
    border-radius: 4px;
    transition: width 0.6s cubic-bezier(.4,0,.2,1);
}
.goal-row--completed .goal-row__bar-fill {
    background: linear-gradient(90deg, #2ecc71 0%, #27d178 100%);
}
.goal-row__progress {
    display: flex; justify-content: space-between; align-items: baseline;
    font-size: 0.78rem;
    font-family: monospace;
}
.goal-row__progress strong { color: var(--text); font-weight: 700; }
.goal-row__progress .pct   { color: #2ecc71; font-weight: 700; }
.goal-row--off .goal-row__progress .pct { color: var(--muted); }
.goal-row__progress .check { color: #2ecc71; font-size: 0.95rem; }

/* ── Měsíční přepínač + bannery ─────────────────────────────────── */
.month-switch {
    display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;
    background: rgba(0,0,0,0.03);
    border: 1px solid rgba(0,0,0,0.08);
    border-radius: 8px;
    padding: 0.55rem 0.75rem;
    margin-bottom: 0.8rem;
}
.month-switch label {
    font-size: 0.82rem; color: var(--muted); font-weight: 600;
}
.month-switch select {
    padding: 0.35rem 0.55rem;
    background: var(--bg); color: var(--text);
    border: 1px solid rgba(0,0,0,0.18);
    border-radius: 5px;
    font-size: 0.9rem; font-weight: 600;
    min-width: 180px;
}
.month-switch select:focus { outline: none; border-color: rgba(46,204,113,0.6); }
.month-switch .badge-current,
.month-switch .badge-past,
.month-switch .badge-future {
    font-size: 0.72rem; font-weight: 700;
    padding: 0.18rem 0.55rem; border-radius: 4px;
    text-transform: uppercase; letter-spacing: 0.4px;
}
.month-switch .badge-current { background: rgba(46,204,113,0.18); color: #2ecc71; }
.month-switch .badge-past    { background: rgba(241,196,15,0.18); color: #f1c40f; }
.month-switch .badge-future  { background: rgba(52,152,219,0.18); color: #3498db; }

.period-banner {
    border-radius: 8px;
    padding: 0.55rem 0.85rem;
    margin: 0 0 0.8rem;
    font-size: 0.83rem; line-height: 1.55;
}
.period-banner--past   { background: rgba(241,196,15,0.07); border: 1px solid rgba(241,196,15,0.25); color: #f1c40f; }
.period-banner--future { background: rgba(52,152,219,0.07); border: 1px solid rgba(52,152,219,0.25); color: #3498db; }
</style>

<section class="card" style="max-width:1100px;">
    <h1 style="margin-top:0;">🎯 Cíle čističky podle krajů</h1>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info"><?= crm_h($flash) ?></p>
    <?php } ?>

    <!--
        Měsíční přepínač — GET form, mění ?month_key=YYYY-MM. JS níže auto-submituje
        při změně selectu, takže není nutné samostatné tlačítko.
    -->
    <form method="get" action="<?= crm_h(crm_url('/admin/cisticka-goals')) ?>" class="month-switch" id="monthSwitchForm">
        <label for="month_key">📅 Měsíc:</label>
        <select id="month_key" name="month_key" onchange="document.getElementById('monthSwitchForm').submit();">
            <?php foreach ($monthOptions as $opt) { ?>
                <option value="<?= crm_h($opt['key']) ?>"<?= ($opt['key'] === $selectedMonthKey) ? ' selected' : '' ?>>
                    <?= crm_h($opt['label']) ?>
                </option>
            <?php } ?>
        </select>
        <?php if ($isCurrentMonth) { ?>
            <span class="badge-current">Aktuální</span>
        <?php } elseif ($isPastMonth) { ?>
            <span class="badge-past">Historie</span>
        <?php } else { ?>
            <span class="badge-future">Plán</span>
        <?php } ?>
        <noscript>
            <button type="submit" class="btn btn-secondary" style="padding:0.3rem 0.7rem;font-size:0.85rem;">Načíst</button>
        </noscript>
    </form>

    <p style="font-size:0.88rem;color:var(--text);margin-bottom:0.4rem;">
        📅 Vybrané období: <strong><?= crm_h($monthLabel) ?></strong>
        <span style="color:var(--muted);font-weight:normal;"> · reset 1. dne každého měsíce v 00:00</span>
    </p>

    <?php if ($isPastMonth) { ?>
        <div class="period-banner period-banner--past">
            ⚠ <strong>Historický záznam.</strong> Prohlížíš si cíle a progress za uplynulý měsíc.
            Změny v tomto formuláři přepíšou historický cíl pro daný měsíc — counter (progress) se nemění,
            ten je odvozen z workflow_log podle data ověření.
        </div>
    <?php } elseif ($isFutureMonth) { ?>
        <div class="period-banner period-banner--future">
            🔮 <strong>Plánovaný měsíc.</strong> Můžeš si dopředu nastavit cíle pro tento měsíc.
            Counter (progress) bude 0 — naplní se průběžně v daném měsíci.
        </div>
    <?php } ?>

    <p style="font-size:0.78rem;color:var(--muted);margin-bottom:0.4rem;line-height:1.55;">
        Nastavte <strong>měsíční cíl</strong> pro každý kraj. Progress bar ukazuje, kolik se z cíle už splnilo
        ve vybraném měsíci (DISTINCT kontakty, všichni operátoři dohromady).<br>
        Po dosažení 100 % zelený checkmark ✓ — admin pak může cíl zvednout.<br>
        🚫 Hodnota <code>0</code> = "bez cíle pro tento kraj", v progress panelu se nezobrazí.<br>
        ⭐ <strong>Priorita 1–10</strong> (1 = nejvyšší): u čističky se kraje řadí ASC podle priority,
        nejvyšší se zvýrazní jako "začni zde". Při shodě priority abecedně.
    </p>

    <form method="post" action="<?= crm_h(crm_url('/admin/cisticka-goals')) ?>">
        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
        <!-- Hidden period — UPSERT půjde přesně pro vybraný měsíc, ne pro současný. -->
        <input type="hidden" name="period" value="<?= crm_h($selectedMonthKey) ?>">

        <div class="goals-grid">
            <?php foreach ($allRegions as $region) {
                $target  = (int) ($existing[$region] ?? 0);
                $prio    = (int) ($existingPriority[$region] ?? 5);
                $done    = (int) ($progress[$region] ?? 0);
                $pct     = $target > 0 ? min(100, (int) round($done / $target * 100)) : 0;
                $done    = $target > 0 ? $done : 0;
                $isOff   = $target === 0;
                $isDone  = !$isOff && $done >= $target;
                $label   = function_exists('crm_region_label') ? crm_region_label($region) : $region;
                $rowCls  = 'goal-row';
                if ($isDone) $rowCls .= ' goal-row--completed';
                if ($isOff)  $rowCls .= ' goal-row--off';
                $prioInputCls = 'goal-row__prio-input';
                if ($prio === 1) $prioInputCls .= ' is-top';
            ?>
            <div class="<?= crm_h($rowCls) ?>">
                <div class="goal-row__head">
                    <span class="goal-row__label"><?= crm_h($label) ?></span>
                    <input type="number"
                           name="goal[<?= crm_h($region) ?>]"
                           value="<?= $target ?>"
                           min="0" max="100000" step="10"
                           class="goal-row__input <?= $target > 0 ? 'goal-row__input--has-value' : '' ?>"
                           placeholder="0">
                    <span class="goal-row__hint">/ měsíc</span>
                </div>

                <!-- Priorita: 1-10, 1 = nejvyšší. Vždy editovatelné, i když je target 0. -->
                <div class="goal-row__prio">
                    <span class="goal-row__prio-label">⭐ Priorita</span>
                    <input type="number"
                           name="priority[<?= crm_h($region) ?>]"
                           value="<?= $prio ?>"
                           min="1" max="10" step="1"
                           class="<?= crm_h($prioInputCls) ?>"
                           title="1 = nejvyšší priorita, 10 = nejnižší. Default 5.">
                    <span class="goal-row__prio-hint">1 = první v pořadí · 10 = poslední</span>
                </div>

                <?php if (!$isOff) { ?>
                <div class="goal-row__bar">
                    <div class="goal-row__bar-fill" style="width:<?= $pct ?>%;"></div>
                </div>
                <div class="goal-row__progress">
                    <span>
                        <strong><?= $done ?></strong>
                        <span style="color:var(--muted);">/ <?= $target ?></span>
                    </span>
                    <span class="pct">
                        <?= $pct ?>%
                        <?php if ($isDone) { ?><span class="check">✓</span><?php } ?>
                    </span>
                </div>
                <?php } else { ?>
                <div class="goal-row__progress" style="font-size:0.72rem;">
                    <span style="color:var(--muted);font-style:italic;">Cíl není nastaven (zadej hodnotu výše)</span>
                </div>
                <?php } ?>
            </div>
            <?php } ?>
        </div>

        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:1rem;">
            <button type="submit" class="btn">💾 Uložit cíle</button>
            <a href="<?= crm_h(crm_url('/dashboard')) ?>" class="btn btn-secondary">← Dashboard</a>
            <a href="<?= crm_h(crm_url('/cisticka')) ?>" class="btn btn-secondary">Náhled — Čistička</a>
        </div>
    </form>
</section>

<script>
// Vizuální feedback — když user napíše hodnotu > 0, input zezelená
document.querySelectorAll('.goal-row__input').forEach(function(inp) {
    inp.addEventListener('input', function() {
        if (parseInt(inp.value || '0', 10) > 0) {
            inp.classList.add('goal-row__input--has-value');
        } else {
            inp.classList.remove('goal-row__input--has-value');
        }
    });
});

// Priority input: visuální feedback pro priorita = 1 (nejvyšší).
// Klamp do 1-10 přímo v UI (browser HTML max/min už řeší, ale pro jistotu).
document.querySelectorAll('.goal-row__prio-input').forEach(function(inp) {
    inp.addEventListener('input', function() {
        var v = parseInt(inp.value || '5', 10);
        if (isNaN(v)) v = 5;
        if (v < 1)  v = 1;
        if (v > 10) v = 10;
        if (String(v) !== inp.value && inp.value !== '') {
            // jen když je hodnota out-of-range (ne při průběžném psaní prázdného)
        }
        if (v === 1) { inp.classList.add('is-top'); }
        else         { inp.classList.remove('is-top'); }
    });
    inp.addEventListener('blur', function() {
        var v = parseInt(inp.value || '5', 10);
        if (isNaN(v) || v < 1)  v = 1;
        if (v > 10)              v = 10;
        inp.value = v;
    });
});
</script>
