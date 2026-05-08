<?php
// e:\Snecinatripu\app\views\login\two_factor.php
declare(strict_types=1);
/** @var string|null $flash */
/** @var string $csrf */

// Pokud je tu trusted cookie, je to reverify scenario (= žádné nové cookie nutné)
$_isReverify = function_exists('crm_trusted_device_get_cookie_token')
    && crm_trusted_device_get_cookie_token() !== null;
?>
<section class="card" style="max-width:460px;">
    <h1>🔐 Dvoufaktorové ověření</h1>
    <?php if ($_isReverify) { ?>
        <p class="muted" style="font-size:0.86rem;line-height:1.5;">
            Pravidelná kontrola — zadej aktuální 6-místný kód z aplikace autentizátoru.
            Po ověření tě budeme zase pamatovat 7 dní.
        </p>
    <?php } else { ?>
        <p class="muted" style="font-size:0.86rem;line-height:1.5;">
            Zadej 6-místný kód z aplikace autentizátoru (Google Authenticator / Authy / atd.)
            nebo jeden z 8 záložních kódů.
        </p>
    <?php } ?>
    <?php if (!empty($flash)) { ?>
        <p class="alert"><?= crm_h($flash) ?></p>
    <?php } ?>
    <form method="post" action="<?= crm_h(crm_url('/login/two-factor')) ?>" class="form" autocomplete="off">
        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
        <label for="code">Kód</label>
        <input id="code" name="code" type="text" inputmode="numeric"
               pattern="[0-9A-Za-z\-]*" autocomplete="one-time-code"
               required maxlength="32" autofocus
               style="font-family:monospace; font-size:1.4rem; letter-spacing:0.4rem; text-align:center;">

        <?php if (!$_isReverify) { ?>
            <!-- Remember device — jen u nového loginu (ne při reverify) -->
            <label class="check" style="display:flex; align-items:center; gap:0.5rem; margin-top:0.6rem; font-size:0.84rem;">
                <input type="checkbox" name="remember_device" value="1" checked>
                <span>Důvěřovat tomuto zařízení 30 dní (auto-login bez hesla, 2FA jen občas)</span>
            </label>
        <?php } ?>

        <button type="submit" class="btn" style="margin-top:0.6rem; width:100%;">
            ✓ Ověřit
        </button>
    </form>
    <p class="muted small" style="margin-top:0.7rem;">
        <a href="<?= crm_h(crm_url('/login')) ?>">← Zpět na přihlášení</a> (zruší rozpracované 2FA)
    </p>
</section>
