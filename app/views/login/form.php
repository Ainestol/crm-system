<?php
// e:\Snecinatripu\app\views\login\form.php
declare(strict_types=1);
/** @var string|null $flash */
/** @var string $csrf */
?>
<section class="card card--login">
    <h1>Přihlášení</h1>
    <?php if (!empty($flash)) { ?>
        <p class="alert"><?= crm_h($flash) ?></p>
    <?php } ?>
    <form method="post" action="<?= crm_h(crm_url('/login')) ?>" class="form">
        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" autocomplete="username" required maxlength="255">

        <label for="password">Heslo</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>

        <button type="submit" class="btn">Přihlásit se</button>
    </form>
</section>
