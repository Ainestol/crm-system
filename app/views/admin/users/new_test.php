<?php
// e:\Snecinatripu\app\views\admin\users\new_test.php
declare(strict_types=1);
/** @var array<string, mixed> $actor */
/** @var list<string> $roleOptions */
/** @var string $testDomain */
/** @var string|null $flash */
/** @var string $csrf */
?>
<section class="card">
    <h1>🧪 Nový testovací účet</h1>

    <p style="font-size:0.85rem;color:var(--muted);margin:0 0 1rem;line-height:1.55;">
        Účet pro testera bez emailu. Heslo si zadáš sám a předáš testerovi.
        Login bude ve tvaru <code><?= crm_h('username@' . $testDomain) ?></code> — tester ho použije
        v přihlašovacím formuláři jako e-mail. <strong>Nevynucuje se změna hesla</strong> ani 2FA.
    </p>

    <?php if (!empty($flash)) { ?>
        <p class="alert"><?= crm_h($flash) ?></p>
    <?php } ?>

    <form method="post" action="<?= crm_h(crm_url('/admin/users/new-test')) ?>" class="form" autocomplete="off">
        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">

        <label for="username">Přihlašovací jméno</label>
        <div style="display:flex;align-items:center;gap:0.4rem;">
            <input id="username" name="username" required maxlength="32" pattern="[A-Za-z0-9][A-Za-z0-9._\-]{1,31}"
                   placeholder="napr. tester1" style="flex:1;" autocapitalize="off" autocorrect="off" spellcheck="false"
                   value="<?= crm_h((string) ($_POST['username'] ?? '')) ?>">
            <span style="color:var(--muted);font-size:0.85rem;">@<?= crm_h($testDomain) ?></span>
        </div>
        <small style="display:block;color:var(--muted);font-size:0.72rem;margin-top:-0.4rem;">
            Povoleno: a–z, 0–9, tečka, podtržítko, pomlčka. 2–32 znaků. Velká písmena se převedou na malá.
        </small>

        <label for="jmeno">Zobrazované jméno (volitelné)</label>
        <input id="jmeno" name="jmeno" maxlength="255"
               placeholder="Pokud necháš prázdné, použije se přihlašovací jméno"
               value="<?= crm_h((string) ($_POST['jmeno'] ?? '')) ?>">

        <label for="role">Role</label>
        <select id="role" name="role" required>
            <option value="">— vyber roli —</option>
            <?php foreach ($roleOptions as $r) { ?>
                <option value="<?= crm_h($r) ?>"<?= (($_POST['role'] ?? '') === $r) ? ' selected' : '' ?>><?= crm_h($r) ?></option>
            <?php } ?>
        </select>

        <label for="password">Heslo (min. 6 znaků)</label>
        <input id="password" name="password" type="text" required minlength="6" maxlength="128" autocomplete="new-password">

        <label for="password_confirm">Heslo znovu</label>
        <input id="password_confirm" name="password_confirm" type="text" required minlength="6" maxlength="128" autocomplete="new-password">

        <button type="submit" class="btn">Vytvořit testovací účet</button>
        <a class="btn btn-secondary" href="<?= crm_h(crm_url('/admin/users')) ?>">Zpět</a>
    </form>
</section>
