<?php
// e:\Snecinatripu\app\views\account\password.php
declare(strict_types=1);
/** @var array<string, mixed> $user */
/** @var string|null           $flash */
/** @var string                $csrf */
/** @var bool                  $forced  Pravda = vynucená změna (must_change_password=1) */
?>
<section class="card" style="max-width:520px;margin:2rem auto;">
    <h1 style="margin-top:0;">🔒 Změna hesla</h1>

    <?php if ($forced) { ?>
        <div class="alert" style="background:rgba(243,156,18,0.12);border:1px solid rgba(243,156,18,0.4);
                                  border-left:4px solid #f39c12;border-radius:0 8px 8px 0;
                                  padding:0.75rem 1rem;margin-bottom:1rem;">
            <strong>⚠ Před pokračováním si nastavte vlastní heslo.</strong><br>
            <span style="font-size:0.85rem;color:var(--muted);">
                Administrátor vám resetoval heslo nebo používáte výchozí heslo z prvního přihlášení.
                Dokud si heslo nezměníte, ostatní stránky budou nedostupné.
            </span>
        </div>
    <?php } ?>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info"><?= crm_h($flash) ?></p>
    <?php } ?>

    <form method="post" action="<?= crm_h(crm_url('/account/password')) ?>"
          autocomplete="off"
          style="display:flex;flex-direction:column;gap:0.85rem;">
        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">

        <label style="display:flex;flex-direction:column;gap:0.3rem;font-size:0.85rem;">
            <span>Stávající heslo</span>
            <input type="password" name="current_password" required autocomplete="current-password"
                   style="padding:0.55rem 0.7rem;font-size:0.9rem;
                          background:var(--bg);color:var(--text);
                          border:1px solid rgba(0,0,0,0.18);border-radius:6px;">
        </label>

        <label style="display:flex;flex-direction:column;gap:0.3rem;font-size:0.85rem;">
            <span>Nové heslo</span>
            <input type="password" name="new_password" required minlength="10" autocomplete="new-password"
                   style="padding:0.55rem 0.7rem;font-size:0.9rem;
                          background:var(--bg);color:var(--text);
                          border:1px solid rgba(0,0,0,0.18);border-radius:6px;">
            <small style="font-size:0.72rem;color:var(--muted);">
                Min. 10 znaků, alespoň jedno písmeno a jedna číslice.
            </small>
        </label>

        <label style="display:flex;flex-direction:column;gap:0.3rem;font-size:0.85rem;">
            <span>Nové heslo (ověření)</span>
            <input type="password" name="new_password2" required minlength="10" autocomplete="new-password"
                   style="padding:0.55rem 0.7rem;font-size:0.9rem;
                          background:var(--bg);color:var(--text);
                          border:1px solid rgba(0,0,0,0.18);border-radius:6px;">
        </label>

        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.3rem;">
            <button type="submit" class="btn">Uložit nové heslo</button>
            <?php if (!$forced) { ?>
                <a href="<?= crm_h(crm_url('/dashboard')) ?>" class="btn btn-secondary">← Zpět</a>
            <?php } ?>
        </div>
    </form>

    <?php if ($forced) { ?>
        <p style="margin-top:1.2rem;font-size:0.78rem;color:var(--muted);">
            Pokud se chcete jen odhlásit, použijte tlačítko níže — k aplikaci se vrátíte po další úspěšné změně hesla.
        </p>
        <form method="post" action="<?= crm_h(crm_url('/logout')) ?>" style="margin-top:0.5rem;">
            <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
            <button type="submit" class="btn btn-secondary">Odhlásit se</button>
        </form>
    <?php } ?>
</section>
