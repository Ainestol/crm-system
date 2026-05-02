<?php
// e:\Snecinatripu\app\views\login\two_factor.php
declare(strict_types=1);
/** @var string|null $flash */
/** @var string $csrf */
?>
<section class="card">
    <h1>Dvoufaktorové ověření</h1>
    <p class="muted">Zadejte 6místný kód z aplikace autentizátoru nebo záložní kód.</p>
    <?php if (!empty($flash)) { ?>
        <p class="alert"><?= crm_h($flash) ?></p>
    <?php } ?>
    <form method="post" action="<?= crm_h(crm_url('/login/two-factor')) ?>" class="form">
        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
        <label for="code">Kód</label>
        <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9A-Za-z\-]*" autocomplete="one-time-code" required maxlength="32">

        <button type="submit" class="btn">Ověřit</button>
    </form>
    <p class="muted small"><a href="<?= crm_h(crm_url('/login')) ?>">Zpět na přihlášení</a> (zruší rozpracované 2FA)</p>
</section>
