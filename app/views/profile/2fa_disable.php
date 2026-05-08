<?php
// e:\Snecinatripu\app\views\profile\2fa_disable.php
declare(strict_types=1);
/** @var array<string,mixed> $actor */
/** @var list<array<string,mixed>> $devices */
/** @var int $unusedBackupCount */
/** @var string|null $flash */
/** @var string $csrf */
?>
<style>
.tfa-disable-card { max-width: 720px; }
.tfa-status-on {
    display: inline-block; padding: 0.25rem 0.7rem;
    background: rgba(46,204,113,0.12); color: #2ecc71;
    border: 1px solid rgba(46,204,113,0.4);
    border-radius: 999px; font-weight: 700; font-size: 0.82rem;
}
.tfa-device-list { margin-top: 0.6rem; }
.tfa-device-row {
    display: flex; gap: 0.8rem; align-items: center;
    padding: 0.55rem 0.8rem;
    background: rgba(0,0,0,0.02);
    border: 1px solid rgba(0,0,0,0.06);
    border-radius: 6px;
    font-size: 0.78rem;
    margin-bottom: 0.35rem;
}
.tfa-device-row__ua { flex: 1; word-break: break-all; }
.tfa-device-row__meta { color: var(--muted); font-size: 0.72rem; }
</style>

<section class="card tfa-disable-card">
    <h1>🔐 Stav dvoufaktorového ověření</h1>

    <div style="margin: 0.6rem 0 1.2rem;">
        <span class="tfa-status-on">✓ 2FA je aktivní</span>
        <span style="margin-left: 0.6rem; color: var(--muted); font-size: 0.85rem;">
            Backup kódů zbývá: <strong><?= (int) $unusedBackupCount ?></strong> z 8
        </span>
    </div>

    <?php if (!empty($flash)) { ?>
        <p class="alert"><?= crm_h($flash) ?></p>
    <?php } ?>

    <!-- ── Trusted devices ── -->
    <h2 style="font-size: 1rem; margin-bottom: 0.4rem;">Důvěryhodná zařízení</h2>
    <p style="color: var(--muted); font-size: 0.82rem; margin: 0 0 0.5rem;">
        Zařízení, kterým byl vystaven 30-denní auto-login token. Můžeš je odhlásit jednotlivě
        (TODO — zatím jen "Odhlásit ze všech").
    </p>

    <?php if ($devices === []) { ?>
        <p style="color: var(--muted); font-style: italic; font-size: 0.82rem;">
            Žádná aktivní důvěryhodná zařízení.
        </p>
    <?php } else { ?>
        <div class="tfa-device-list">
            <?php foreach ($devices as $d) { ?>
                <div class="tfa-device-row">
                    <div class="tfa-device-row__ua">
                        <?php
                        $ua = (string) ($d['user_agent'] ?? '');
                        $shortUa = $ua;
                        // Zkrať UA na čitelnější formu
                        if (preg_match('/(Chrome|Firefox|Safari|Edge|Opera)\/[\d.]+/', $ua, $m)) {
                            $shortUa = $m[0];
                        }
                        if (preg_match('/(Windows|Mac|Linux|Android|iOS|iPhone|iPad)/', $ua, $m2)) {
                            $shortUa = ($m2[0] ?? '') . ' · ' . $shortUa;
                        }
                        ?>
                        <strong><?= crm_h($shortUa !== '' ? $shortUa : 'Neznámé zařízení') ?></strong>
                        <span class="tfa-device-row__meta">
                            · IP: <?= crm_h((string) ($d['ip_address'] ?? '?')) ?>
                            · Použito: <?= crm_h(date('j.n. H:i', strtotime((string) ($d['last_used_at'] ?? '')))) ?>
                            · Vyprší: <?= crm_h(date('j.n.Y', strtotime((string) ($d['expires_at'] ?? '')))) ?>
                        </span>
                    </div>
                </div>
            <?php } ?>
        </div>

        <form method="post" action="<?= crm_h(crm_url('/profile/2fa/revoke-all')) ?>"
              onsubmit="return confirm('Odhlásit ze všech zařízení? Při dalším návratu budeš muset zadat heslo + 2FA znovu.');"
              style="margin-top: 0.6rem;">
            <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
            <button type="submit" class="btn btn-secondary">🚪 Odhlásit ze všech zařízení</button>
        </form>
    <?php } ?>

    <hr style="margin: 1.5rem 0; border: 0; border-top: 1px solid rgba(0,0,0,0.08);">

    <!-- ── Vypnout 2FA ── -->
    <h2 style="font-size: 1rem; color: #e74c3c; margin-bottom: 0.4rem;">Vypnout dvoufaktorové ověření</h2>
    <p style="font-size: 0.82rem; color: var(--muted); margin: 0 0 0.7rem; line-height: 1.5;">
        ⚠ Po vypnutí bude účet chráněn jen heslem. <strong>Všechna důvěryhodná zařízení se odhlásí</strong>
        a backup kódy se znehodnotí. Pro vypnutí zadej <strong>heslo</strong> a <strong>aktuální 2FA kód</strong>
        (nebo backup kód).
    </p>

    <form method="post" action="<?= crm_h(crm_url('/profile/2fa/disable')) ?>" autocomplete="off"
          onsubmit="return confirm('Opravdu vypnout 2FA? Tvoje zařízení se odhlásí.');"
          style="display: flex; flex-direction: column; gap: 0.6rem; max-width: 380px;">
        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">

        <label for="pw" style="font-size: 0.82rem; font-weight: 600;">Tvoje heslo</label>
        <input id="pw" type="password" name="password" required autocomplete="current-password"
               style="padding:0.5rem 0.7rem; border:1px solid rgba(0,0,0,0.18); border-radius:6px;">

        <label for="code" style="font-size: 0.82rem; font-weight: 600;">2FA kód (6 čísel) nebo backup kód</label>
        <input id="code" type="text" name="code" inputmode="numeric" required maxlength="32"
               style="padding:0.5rem 0.7rem; font-family: monospace; letter-spacing: 0.1em;
                      border:1px solid rgba(0,0,0,0.18); border-radius:6px;">

        <div style="display: flex; gap: 0.6rem; margin-top: 0.4rem;">
            <button type="submit" class="btn"
                    style="background:#e74c3c; border-color:#e74c3c; color:#fff;">
                🔓 Vypnout 2FA
            </button>
            <a class="btn btn-secondary" href="<?= crm_h(crm_url('/dashboard')) ?>">Zrušit</a>
        </div>
    </form>
</section>
