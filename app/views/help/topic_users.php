<?php
declare(strict_types=1);
/** @var array<string,mixed> $topic */
?>

<div style="max-width:1000px;margin:0 auto;padding:1rem;">
    <div style="margin-bottom:1rem;">
        <a href="<?= crm_url('/help') ?>" style="color:#6b7280;text-decoration:none;font-size:0.9rem;">← Zpět na rozcestník</a>
    </div>

    <h1><?= $topic['icon'] ?> <?= crm_h($topic['label']) ?></h1>
    <p style="color:#6b7280;"><?= crm_h($topic['short']) ?></p>

    <h2>👥 Role v systému</h2>
    <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:8px;font-size:0.9rem;">
        <thead style="background:#f3f4f6;">
            <tr><th style="text-align:left;padding:0.5rem 0.8rem;">Role</th><th style="text-align:left;padding:0.5rem 0.8rem;">Co dělá</th></tr>
        </thead>
        <tbody>
            <tr><td style="padding:0.4rem 0.8rem;"><code>superadmin</code></td><td style="padding:0.4rem 0.8rem;">Plný přístup ke všemu. Může mazat data, impersonovat, měnit hesla. Jen 1 osoba.</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;"><code>majitel</code></td><td style="padding:0.4rem 0.8rem;">Přístup ke všem admin funkcím včetně cílů, kvót, sázek, fakturace. Nemůže smazat superadmina.</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;"><code>cisticka</code></td><td style="padding:0.4rem 0.8rem;">Pracuje v <code>/cisticka</code> — verify operátora (TM/O2/VF), označení chybných kontaktů.</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;"><code>navolavacka</code></td><td style="padding:0.4rem 0.8rem;">Pracuje v <code>/caller</code> — volá leady, výhra/prohra, callbacky, premium navolávky.</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;"><code>obchodak</code></td><td style="padding:0.4rem 0.8rem;">Pracuje v <code>/oz</code> — přijímá leady, schůzky, nabídky, předání BO, podpisy.</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;"><code>backoffice</code></td><td style="padding:0.4rem 0.8rem;">Pracuje v <code>/backoffice</code> — finální zpracování smluv, podpisy.</td></tr>
        </tbody>
    </table>

    <h2>🔄 Multi-role uživatelé</h2>
    <p>Jeden uživatel může mít víc rolí — sloupec <code>roles_extra</code> (JSON pole). Příklad: Petra má primárně <strong>navolavacka</strong> + extra <strong>obchodak</strong>. V topbar má tlačítko <strong>🔄 Přepnout roli</strong>.</p>

    <h2>🔑 Reset hesla a custom heslo</h2>
    <p>V <code>/admin/users</code> u každého aktivního uživatele:</p>
    <ul style="line-height:1.7;">
        <li><strong>🔄 Reset hesla</strong> — vygeneruje náhodné dočasné heslo. Uživatel se s ním přihlásí a hned musí změnit.</li>
        <li><strong>🔑 Vlastní heslo</strong> — admin nastaví konkrétní heslo (např. „debug123"). Uživatel se přihlásí bez nutnosti změnit. <strong>Použij pro vlastní debug nebo když musíš rychle nasimulovat přístup.</strong></li>
    </ul>

    <h2>🎭 Impersonate (přepnout se do účtu)</h2>
    <p>Admin/majitel může přes <strong>🎭 Přepnout se</strong> dočasně „vstoupit" do účtu jiného uživatele a vidět co on. Bez nutnosti znát jeho heslo.</p>
    <ul style="line-height:1.7;">
        <li><strong>Vrátit se zpět</strong>: vpravo nahoře oranžový blikající widget <strong>🎭 ← Zpět do admin</strong></li>
        <li><strong>Audit log</strong>: každé spuštění / ukončení impersonate je zaznamenáno (kdo a kdy)</li>
        <li><strong>Bezpečnost</strong>: superadmina nelze impersonovat (i jiný superadmin ne). Sám sebe nelze.</li>
    </ul>

    <div style="background:#fef3c7;border:1px solid #fbbf24;border-radius:8px;padding:0.9rem 1.1rem;font-size:0.88rem;color:#92400e;margin-top:1rem;">
        ⚠️ <strong>Pozor:</strong> Impersonate používej jen pro debug. Pokud uděláš akci v cizím účtu (např. potvrdíš podpis), zaznamená se to jako akce toho uživatele. Pro audit je to viditelné, ale pro běžnou práci je to matoucí.
    </div>
</div>
