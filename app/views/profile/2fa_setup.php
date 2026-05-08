<?php
// e:\Snecinatripu\app\views\profile\2fa_setup.php
declare(strict_types=1);
/** @var array<string,mixed> $actor */
/** @var string $secret  Base32 secret (32 znaků) */
/** @var string $otpUri  otpauth://totp/... */
/** @var string|null $flash */
/** @var string $csrf */
?>
<style>
.tfa-card { max-width: 720px; }
.tfa-step {
    display: flex; align-items: flex-start; gap: 0.8rem;
    margin-bottom: 1.4rem; padding: 0.9rem 1.1rem;
    background: rgba(0,0,0,0.02);
    border-left: 4px solid var(--accent, #3d8bfd);
    border-radius: 0 8px 8px 0;
}
.tfa-step__num {
    flex: 0 0 auto;
    width: 36px; height: 36px;
    background: var(--accent, #3d8bfd); color: #fff;
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 1rem;
}
.tfa-step__body { flex: 1; line-height: 1.55; }
.tfa-step__body h3 { margin: 0 0 0.4rem; font-size: 1rem; }
.tfa-step__body p { margin: 0 0 0.5rem; color: var(--muted); font-size: 0.86rem; }
.tfa-qr-wrap {
    display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;
    background: #fff; padding: 1rem; border-radius: 8px;
    border: 1px solid rgba(0,0,0,0.08);
}
#tfa-qr canvas { display: block; }
.tfa-secret-box {
    font-family: monospace; font-size: 1.05rem; letter-spacing: 0.08em;
    background: rgba(61,139,253,0.07);
    border: 1px solid rgba(61,139,253,0.25);
    padding: 0.55rem 0.8rem; border-radius: 6px;
    word-break: break-all; color: var(--text);
    user-select: all;
}
.tfa-code-input {
    font-family: monospace; font-size: 1.6rem;
    letter-spacing: 0.5rem; text-align: center;
    padding: 0.6rem; width: 220px;
    border: 2px solid rgba(0,0,0,0.18); border-radius: 8px;
}
.tfa-code-input:focus { border-color: var(--accent, #3d8bfd); outline: none; }
</style>

<section class="card tfa-card">
    <h1 style="margin-bottom:0.4rem;">🔐 Aktivace dvoufaktorového ověření</h1>
    <p style="color:var(--muted);font-size:0.86rem;margin:0 0 1.2rem;line-height:1.55;">
        Po aktivaci budeš při přihlášení potřebovat <strong>6-místný kód</strong> z aplikace na mobilu.
        Trusted device cookie tě bude pamatovat <strong>30 dní</strong>, takže to nebudeš muset zadávat často.
    </p>

    <?php if (!empty($flash)) { ?>
        <p class="alert"><?= crm_h($flash) ?></p>
    <?php } ?>

    <!-- KROK 1: Naskenuj QR -->
    <div class="tfa-step">
        <div class="tfa-step__num">1</div>
        <div class="tfa-step__body">
            <h3>Naskenuj QR kód v autentikační aplikaci</h3>
            <p>
                Doporučené aplikace (všechny zdarma): <strong>Google Authenticator</strong>,
                <strong>Authy</strong>, <strong>Microsoft Authenticator</strong>.
                Otevři aplikaci → „Přidat účet" → „Naskenovat QR".
            </p>
            <div class="tfa-qr-wrap">
                <div id="tfa-qr"></div>
                <div style="flex: 1; min-width: 240px;">
                    <p style="margin:0 0 0.4rem; font-size:0.78rem; color:var(--muted);">
                        Pokud nelze naskenovat, zadej tento secret ručně:
                    </p>
                    <div class="tfa-secret-box"><?= crm_h($secret) ?></div>
                    <p style="margin:0.4rem 0 0; font-size:0.72rem; color:var(--muted);">
                        Issuer: <code>Šneci na tripu CRM</code> &middot;
                        Účet: <code><?= crm_h((string) ($actor['email'] ?? '')) ?></code>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- KROK 2: Zadej ověřovací kód -->
    <div class="tfa-step">
        <div class="tfa-step__num">2</div>
        <div class="tfa-step__body">
            <h3>Zadej 6-místný kód, který vidíš v aplikaci</h3>
            <p>
                Aplikace zobrazí kód, který se každých 30 sekund mění.
                Zadáním ověříme, že je vše správně nastavené.
            </p>
            <form method="post" action="<?= crm_h(crm_url('/profile/2fa/setup')) ?>" autocomplete="off"
                  style="display:flex; gap:0.6rem; align-items:center; flex-wrap:wrap; margin-top:0.4rem;">
                <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                <input type="text" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6"
                       required autofocus
                       class="tfa-code-input" placeholder="000000">
                <button type="submit" class="btn">✓ Ověřit a aktivovat</button>
                <a class="btn btn-secondary" href="<?= crm_h(crm_url('/dashboard')) ?>">Zrušit</a>
            </form>
        </div>
    </div>

    <p style="font-size:0.78rem; color:var(--muted); margin-top:1rem; line-height:1.5;">
        💡 <strong>Co se stane po aktivaci:</strong> Dostaneš 8 jednorázových backup kódů (pro případ ztráty mobilu).
        Při dalším loginu zadáš heslo + 6-místný kód a můžeš zaškrtnout
        „Důvěřovat tomuto zařízení 30 dní" → další přihlášení bude bez 2FA (občas si to vyžádá pro jistotu).
    </p>
</section>

<!-- QR kód generování přes qrcode.js z CDN (žádný PHP package potřeba) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function () {
    var uri = <?= json_encode($otpUri, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var box = document.getElementById('tfa-qr');
    if (!box || typeof QRCode === 'undefined') return;
    new QRCode(box, {
        text: uri,
        width: 200,
        height: 200,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
    });
})();
</script>
