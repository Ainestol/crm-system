<?php
// e:\Snecinatripu\app\views\login\forgot.php
declare(strict_types=1);
/** @var string|null $flash */
/** @var string $csrf */
?>
<section class="card card--login">
    <h1>🔐 Zapomenuté heslo</h1>
    <?php if (!empty($flash)) { ?>
        <p class="alert"><?= crm_h($flash) ?></p>
    <?php } ?>
    <p style="font-size:0.88rem;color:var(--color-text-muted, #6b7280);margin-bottom:1rem;">
        Zadejte email, kterým se přihlašujete. Pokud účet existuje, pošleme na něj
        odkaz pro reset hesla. Odkaz funguje 1 hodinu.
    </p>
    <form method="post" action="<?= crm_h(crm_url('/password/forgot')) ?>" class="form">
        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" autocomplete="username" required maxlength="255"
               placeholder="vase.email@firma.cz">

        <button type="submit" class="btn">Poslat odkaz pro reset</button>
    </form>
    <p style="margin-top:1rem;text-align:center;font-size:0.85rem;">
        <a href="<?= crm_h(crm_url('/login')) ?>"
           style="color:var(--color-text-muted, #6b7280);text-decoration:none;">
            ← Zpět na přihlášení
        </a>
    </p>
</section>
