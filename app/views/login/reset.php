<?php
// e:\Snecinatripu\app\views\login\reset.php
declare(strict_types=1);
/** @var string|null $flash */
/** @var string $csrf */
/** @var bool $validToken */
/** @var string $plainToken */
?>
<section class="card card--login">
    <h1>🔐 Nové heslo</h1>
    <?php if (!empty($flash)) { ?>
        <p class="alert"><?= crm_h($flash) ?></p>
    <?php } ?>
    <?php if (!$validToken) { ?>
        <p class="alert alert-warning" style="background:#fef3c7;border:1px solid #fbbf24;
                                              padding:0.8rem;border-radius:6px;color:#92400e;">
            ⚠ <strong>Odkaz je neplatný, vypršel, nebo už byl použit.</strong><br>
            Vyžádejte si nový email s odkazem.
        </p>
        <p style="margin-top:1rem;text-align:center;">
            <a href="<?= crm_h(crm_url('/password/forgot')) ?>" class="btn">
                Vyžádat nový odkaz
            </a>
        </p>
    <?php } else { ?>
        <p style="font-size:0.88rem;color:var(--color-text-muted, #6b7280);margin-bottom:1rem;">
            Zadejte své nové heslo. Musí mít alespoň 8 znaků. Pokud máte zapnuté 2FA,
            budete při dalším přihlášení stále potřebovat ověřovací kód.
        </p>
        <form method="post" action="<?= crm_h(crm_url('/password/reset')) ?>" class="form">
            <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
            <input type="hidden" name="token" value="<?= crm_h($plainToken) ?>">

            <label for="password">Nové heslo</label>
            <input id="password" name="password" type="password" autocomplete="new-password"
                   required minlength="8" placeholder="alespoň 8 znaků">

            <label for="password_confirm">Nové heslo znovu</label>
            <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password"
                   required minlength="8" placeholder="ověření shody">

            <button type="submit" class="btn">Nastavit nové heslo</button>
        </form>
    <?php } ?>
    <p style="margin-top:1rem;text-align:center;font-size:0.85rem;">
        <a href="<?= crm_h(crm_url('/login')) ?>"
           style="color:var(--color-text-muted, #6b7280);text-decoration:none;">
            ← Zpět na přihlášení
        </a>
    </p>
</section>
