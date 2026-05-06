<?php
// e:\Snecinatripu\app\views\admin\daily_goals.php
declare(strict_types=1);
/** @var array<string,mixed>      $user */
/** @var array<string,mixed>      $reward       základní odměna */
/** @var array<string,mixed>      $monthlyGoal  měsíční cíl + bonusy */
/** @var int                      $workDays     pracovní dny v měsíci */
/** @var int                      $derivedDailyWin odvozený denní cíl */
/** @var string                   $csrf */
/** @var string|null              $flash */

$mg      = $monthlyGoal;
$enabled = (bool) ($mg['motiv_enabled'] ?? true);
$t1      = (int) round((int)$mg['target_wins'] * (int)$mg['bonus1_at_pct'] / 100);
$t2      = (int) round((int)$mg['target_wins'] * (int)$mg['bonus2_at_pct'] / 100);
$base    = (float) ($reward['amount_czk'] ?? 0);
$rate1   = $base * (1 + (float)$mg['bonus1_pct'] / 100);
$rate2   = $base * (1 + (float)$mg['bonus1_pct'] / 100 + (float)$mg['bonus2_pct'] / 100);

$inputStyle = 'width:100%;padding:0.5rem 0.7rem;border-radius:7px;border:1px solid rgba(0,0,0,0.15);background:#ffffff;color:var(--text);box-sizing:border-box;';
$fsStyle    = 'border:1px solid rgba(0,0,0,0.1);border-radius:8px;padding:1rem 1.2rem;margin-bottom:1.2rem;';
$legStyle   = 'font-size:0.85rem;color:var(--muted);padding:0 0.4rem;';
?>
<section class="card">
    <h1>📆 Denní cíle a odměny navolávaček</h1>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info"><?= crm_h($flash) ?></p>
    <?php } ?>

    <!-- Cross-reference: kde se nastavuje sazba čističky -->
    <p style="font-size:0.8rem;color:var(--muted);margin:0 0 1rem;
              background:rgba(0,0,0,0.03);padding:0.45rem 0.7rem;border-radius:5px;
              border-left:3px solid rgba(0,0,0,0.2);max-width:460px;">
        💡 Hledáš <strong>sazbu pro čističku</strong> (cca 0,70 Kč za ověření)? Je v
        <a href="<?= crm_h(crm_url('/admin/cisticka-goals')) ?>"
           style="color:#185fa5;font-weight:600;text-decoration:none;">🧹 Cíle a sazba čističky</a>.
    </p>

    <form method="post" action="<?= crm_h(crm_url('/admin/daily-goals/save')) ?>" class="form" style="max-width:460px;">
        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">

        <!-- ── Enable / Disable přepínač ────────────────────────────────────── -->
        <fieldset style="<?= $fsStyle ?> border-color:<?= $enabled ? 'rgba(46,204,113,0.3)' : 'rgba(231,76,60,0.3)' ?>;">
            <legend style="<?= $legStyle ?>">🔘 Motivační systém</legend>
            <label style="display:flex;align-items:center;gap:0.7rem;cursor:pointer;font-size:0.95rem;">
                <input type="checkbox" name="motiv_enabled" value="1"
                       <?= $enabled ? 'checked' : '' ?>
                       style="width:18px;height:18px;cursor:pointer;"
                       id="motiv-toggle">
                <span id="motiv-label"><?= $enabled ? '✅ Motivační systém je <strong>zapnutý</strong> — navolávačky vidí měsíční progress a bonusy.' : '⛔ Motivační systém je <strong>vypnutý</strong> — navolávačky nevidí nic.' ?></span>
            </label>
        </fieldset>

        <!-- ── Měsíční cíl výher ──────────────────────────────────────────── -->
        <fieldset style="<?= $fsStyle ?>">
            <legend style="<?= $legStyle ?>">📅 Měsíční cíl výher</legend>

            <label>Cíl výher za měsíc</label>
            <input type="number" name="target_wins_month" min="1" max="9999"
                   value="<?= (int) $mg['target_wins'] ?>"
                   style="<?= $inputStyle ?>">

            <p class="muted" style="font-size:0.82rem;margin:0.5rem 0 0;">
                Aktuální měsíc má <strong><?= $workDays ?> pracovních dní</strong>
                (Po–Pá) &rarr; odvozený denní cíl: <strong><?= $derivedDailyWin ?> výher/den</strong>.
            </p>

            <input type="hidden" name="target_wins" value="<?= $derivedDailyWin ?>">
        </fieldset>

        <!-- ── Základní odměna za výhru ──────────────────────────────────── -->
        <fieldset style="<?= $fsStyle ?>">
            <legend style="<?= $legStyle ?>">💰 Základní odměna za výhru</legend>

            <p class="muted" style="font-size:0.82rem;margin:0 0 0.7rem;">
                Aktuálně: <strong><?= number_format($base, 0, ',', ' ') ?> Kč</strong>
                od <?= crm_h((string) ($reward['valid_from'] ?? '—')) ?>
            </p>

            <label>Nová základní odměna (Kč / výhra)</label>
            <input type="number" name="amount_czk" min="0" max="99999" step="10"
                   value="<?= (int) $base ?>"
                   style="<?= $inputStyle ?>">
            <p class="muted" style="font-size:0.78rem;margin:0.3rem 0 0;">
                Uložením se nastaví nová odměna platná od dnes. 0 = žádná změna.
            </p>
        </fieldset>

        <!-- ── Bonusové pásy ──────────────────────────────────────────────── -->
        <fieldset style="<?= $fsStyle ?>">
            <legend style="<?= $legStyle ?>">🏆 Bonusové pásy (za překročení cíle)</legend>

            <p class="muted" style="font-size:0.82rem;margin:0 0 1rem;">
                Bonusy jsou <strong>marginální</strong> — platí jen na výhry NAD daným prahem,
                nikoliv retroaktivně na celou mzdu.
            </p>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;margin-bottom:0.8rem;">
                <div>
                    <label>1. bonus: při % plnění cíle</label>
                    <div style="display:flex;align-items:center;gap:0.4rem;">
                        <input type="number" name="bonus1_at_pct" min="100" max="999"
                               value="<?= (int) $mg['bonus1_at_pct'] ?>"
                               style="<?= $inputStyle ?> width:80px;">
                        <span class="muted">%</span>
                        <span class="muted" style="font-size:0.8rem;">(= <?= $t1 ?> výher)</span>
                    </div>
                </div>
                <div>
                    <label>Bonus %</label>
                    <div style="display:flex;align-items:center;gap:0.4rem;">
                        <input type="number" name="bonus1_pct" min="0" max="50" step="0.5"
                               value="<?= number_format((float)$mg['bonus1_pct'], 1, '.', '') ?>"
                               style="<?= $inputStyle ?> width:80px;">
                        <span class="muted">% navíc</span>
                    </div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;margin-bottom:1rem;">
                <div>
                    <label>2. bonus: při % plnění cíle</label>
                    <div style="display:flex;align-items:center;gap:0.4rem;">
                        <input type="number" name="bonus2_at_pct" min="101" max="999"
                               value="<?= (int) $mg['bonus2_at_pct'] ?>"
                               style="<?= $inputStyle ?> width:80px;">
                        <span class="muted">%</span>
                        <span class="muted" style="font-size:0.8rem;">(= <?= $t2 ?> výher)</span>
                    </div>
                </div>
                <div>
                    <label>Bonus %</label>
                    <div style="display:flex;align-items:center;gap:0.4rem;">
                        <input type="number" name="bonus2_pct" min="0" max="50" step="0.5"
                               value="<?= number_format((float)$mg['bonus2_pct'], 1, '.', '') ?>"
                               style="<?= $inputStyle ?> width:80px;">
                        <span class="muted">% navíc</span>
                    </div>
                </div>
            </div>

            <!-- Náhled sazeb -->
            <?php if ($base > 0) { ?>
            <div style="background:#0e1724;border-radius:7px;padding:0.7rem 0.9rem;font-size:0.83rem;">
                <div style="color:var(--muted);margin-bottom:0.3rem;">Náhled sazeb při aktuální odměně <?= number_format($base, 0, ',', ' ') ?> Kč:</div>
                <div style="display:flex;gap:1.5rem;flex-wrap:wrap;">
                    <span>Výhry 1–<?= $t1 ?>: <strong><?= number_format($base, 0, ',', ' ') ?> Kč</strong></span>
                    <span style="color:#f0a030;">Výhry <?= ($t1+1) ?>–<?= $t2 ?>: <strong><?= number_format($rate1, 0, ',', ' ') ?> Kč</strong></span>
                    <span style="color:#2ecc71;">Výhry <?= ($t2+1) ?>+: <strong><?= number_format($rate2, 0, ',', ' ') ?> Kč</strong></span>
                </div>
            </div>
            <?php } ?>
        </fieldset>

        <button type="submit" class="btn btn-primary">💾 Uložit nastavení</button>
    </form>

    <!-- Dashboard tlačítko odstraněno — sidebar je jediný zdroj navigace. -->
</section>

<script>
// Toggle label motivačního systému
document.getElementById('motiv-toggle').addEventListener('change', function() {
    var lbl = document.getElementById('motiv-label');
    if (this.checked) {
        lbl.innerHTML = '✅ Motivační systém je <strong>zapnutý</strong> — navolávačky vidí měsíční progress a bonusy.';
    } else {
        lbl.innerHTML = '⛔ Motivační systém je <strong>vypnutý</strong> — navolávačky nevidí nic.';
    }
});

// Live preview sazeb při úpravě formuláře
(function () {
    var fields = ['target_wins_month','amount_czk','bonus1_at_pct','bonus1_pct','bonus2_at_pct','bonus2_pct'];
    fields.forEach(function(n) {
        var el = document.querySelector('[name="'+n+'"]');
        if (el) el.addEventListener('input', updatePreview);
    });

    function updatePreview() {
        var tw    = parseInt(document.querySelector('[name=target_wins_month]').value) || 150;
        var base  = parseFloat(document.querySelector('[name=amount_czk]').value) || 0;
        var b1at  = parseInt(document.querySelector('[name=bonus1_at_pct]').value) || 100;
        var b1pct = parseFloat(document.querySelector('[name=bonus1_pct]').value) || 0;
        var b2at  = parseInt(document.querySelector('[name=bonus2_at_pct]').value) || 120;
        var b2pct = parseFloat(document.querySelector('[name=bonus2_pct]').value) || 0;

        var t1 = Math.round(tw * b1at / 100);
        var t2 = Math.round(tw * b2at / 100);
        var rate1 = base * (1 + b1pct / 100);
        var rate2 = base * (1 + b1pct / 100 + b2pct / 100);

        // update (X výher) labels
        document.querySelectorAll('[name=bonus1_at_pct]').forEach(function(el) {
            var hint = el.parentNode.querySelector('.muted:last-child');
            if (hint) hint.textContent = '(= ' + t1 + ' výher)';
        });
        document.querySelectorAll('[name=bonus2_at_pct]').forEach(function(el) {
            var hint = el.parentNode.querySelector('.muted:last-child');
            if (hint) hint.textContent = '(= ' + t2 + ' výher)';
        });

        // update derived daily goal
        var workDays = <?= (int) $workDays ?>;
        var derived  = workDays > 0 ? Math.ceil(tw / workDays) : 0;
        var hiddenDW = document.querySelector('[name=target_wins]');
        if (hiddenDW) hiddenDW.value = derived;
        var muted = document.querySelector('[name=target_wins_month]').parentNode.querySelector('.muted');
        if (muted) {
            muted.innerHTML = 'Aktuální měsíc má <strong><?= (int) $workDays ?> pracovních dní</strong> (Po–Pá) &rarr; odvozený denní cíl: <strong>' + derived + ' výher/den</strong>.';
        }
    }
})();
</script>
