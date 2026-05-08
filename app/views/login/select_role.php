<?php
// e:\Snecinatripu\app\views\login\select_role.php
declare(strict_types=1);
/** @var array<string,mixed> $user */
/** @var string $csrf */
/** @var ?string $flash */
/** @var string $preferred  poslední vybraná / cookie role */

$_roleLabels = [
    'superadmin'  => ['👑', 'Super Admin'],
    'majitel'     => ['🔑', 'Majitel'],
    'obchodak'    => ['💼', 'Obchodák'],
    'navolavacka' => ['📞', 'Navolávačka'],
    'cisticka'    => ['🧹', 'Čistička'],
    'backoffice'  => ['🏢', 'Backoffice'],
];
$_roleDesc = [
    'superadmin'  => 'Plná správa systému + uživatelé',
    'majitel'     => 'Statistiky, cíle, přehled celé firmy',
    'obchodak'    => 'Obchodní zástupce — uzavírá smlouvy s klienty',
    'navolavacka' => 'Volá kontakty, domlouvá schůzky pro OZ',
    'cisticka'    => 'Ověřuje kontakty (operátor) — připraví pool',
    'backoffice'  => 'Zpracování smluv a aktivace u velkých firem',
];

$allRoles = (array) ($user['all_roles'] ?? []);
?>

<style>
.sr-page {
    max-width: 540px; margin: 0 auto; padding: 1.2rem;
}
.sr-page h1 { font-size: 1.5rem; margin: 0 0 0.4rem; color: #4a2480; }
.sr-page .lead { color: var(--color-text-muted); font-size: 0.9rem; margin-bottom: 1.2rem; }
.sr-roles { display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem; }
.sr-role {
    display: flex; align-items: center; gap: 0.8rem;
    padding: 0.85rem 1rem;
    border: 2px solid var(--color-border-strong);
    border-radius: 8px;
    background: #fff;
    cursor: pointer;
    transition: all 0.15s;
}
.sr-role:hover { border-color: #7e3ff2; background: #faf8fd; }
.sr-role input[type="radio"] {
    width: 20px; height: 20px; cursor: pointer; flex-shrink: 0;
    accent-color: #7e3ff2;
}
.sr-role .icon { font-size: 1.6rem; flex-shrink: 0; }
.sr-role .info { flex: 1; }
.sr-role .name { font-weight: 700; font-size: 1rem; color: var(--color-text); }
.sr-role .desc { font-size: 0.78rem; color: var(--color-text-muted); margin-top: 2px; }
.sr-role.is-checked {
    border-color: #7e3ff2;
    background: linear-gradient(135deg,#faf8fd 0%,#f5f0fc 100%);
    box-shadow: 0 2px 6px rgba(126,63,242,0.15);
}

.sr-remember {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.6rem 0.9rem; background: #f9fafb;
    border-radius: 6px; margin-bottom: 1rem;
    font-size: 0.85rem; cursor: pointer;
}
.sr-remember input { width: 18px; height: 18px; accent-color: #7e3ff2; }

.sr-submit {
    width: 100%;
    background: linear-gradient(135deg,#7e3ff2 0%,#a056ff 100%);
    color: #fff; border: none; border-radius: 6px;
    padding: 0.7rem 1.4rem;
    font-size: 1rem; font-weight: 700; cursor: pointer;
    box-shadow: 0 2px 8px rgba(126,63,242,0.3);
}
.sr-submit:hover { filter: brightness(1.05); }
</style>

<section class="card sr-page">
    <h1>👋 Ahoj, <?= crm_h((string) ($user['jmeno'] ?? '')) ?>!</h1>
    <p class="lead">
        Máš povoleno víc rolí. <strong>Vyber roli</strong> kterou chceš teď používat.
        Roli můžeš kdykoliv přepnout přes tlačítko nahoře v topbaru.
    </p>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info" style="margin-bottom:1rem;"><?= crm_h($flash) ?></p>
    <?php } ?>

    <form method="post" action="<?= crm_h(crm_url('/login/select-role')) ?>" autocomplete="off">
        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">

        <div class="sr-roles">
            <?php foreach ($allRoles as $role) {
                [$icon, $name] = $_roleLabels[$role] ?? ['•', ucfirst($role)];
                $desc = $_roleDesc[$role] ?? '';
                $isPreferred = $role === $preferred;
            ?>
                <label class="sr-role <?= $isPreferred ? 'is-checked' : '' ?>"
                       onclick="document.querySelectorAll('.sr-role').forEach(el => el.classList.remove('is-checked')); this.classList.add('is-checked');">
                    <input type="radio" name="role" value="<?= crm_h($role) ?>"
                           <?= $isPreferred ? 'checked' : '' ?> required>
                    <span class="icon"><?= $icon ?></span>
                    <div class="info">
                        <div class="name"><?= crm_h($name) ?></div>
                        <?php if ($desc !== '') { ?>
                            <div class="desc"><?= crm_h($desc) ?></div>
                        <?php } ?>
                    </div>
                </label>
            <?php } ?>
        </div>

        <label class="sr-remember">
            <input type="checkbox" name="remember" value="1" checked>
            <span>📌 <strong>Pamatovat tuto volbu</strong> — příště se přihlásím rovnou jako vybraná role</span>
        </label>

        <button type="submit" class="sr-submit">
            ✓ Pokračovat
        </button>
    </form>
</section>
