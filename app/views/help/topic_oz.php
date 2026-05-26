<?php
declare(strict_types=1);
/** @var array<string,mixed> $topic */
?>

<div style="max-width:1100px;margin:0 auto;padding:1rem;">
    <div style="margin-bottom:1rem;">
        <a href="<?= crm_url('/help') ?>" style="color:#6b7280;text-decoration:none;font-size:0.9rem;">← Zpět na rozcestník</a>
    </div>

    <h1><?= $topic['icon'] ?> <?= crm_h($topic['label']) ?></h1>
    <p style="color:#6b7280;"><?= crm_h($topic['short']) ?></p>

    <div style="background:#f3e8ff;border-left:4px solid #7e22ce;padding:0.9rem 1.1rem;border-radius:0 6px 6px 0;margin:1rem 0;">
        <strong>📌 Kdo jsem:</strong> Obchodní zástupce (OZ) je třetí v pipeline. Dostane <strong>navolané leady</strong>, schází se s nimi, posílá nabídky a uzavírá smlouvy. Z každé uzavřené smlouvy má provizi.
    </div>

    <h2>🎯 Cíle OZ</h2>
    <ul style="line-height:1.7;">
        <li><strong>Vyřešit leady</strong> z queue (CALLED_OK od navolávaček)</li>
        <li><strong>Domluvit schůzku</strong> nebo poslat nabídku</li>
        <li><strong>Předat BO</strong> kontakty k uzavření smlouvy</li>
        <li><strong>Potvrdit podpis</strong> až BO smlouvu dotáhne</li>
        <li><strong>Splnit kvótu</strong> (počet smluv za měsíc dle kraje)</li>
    </ul>

    <h2>🖥️ Hlavní obrazovky OZ</h2>
    <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:8px;font-size:0.9rem;">
        <thead style="background:#f3f4f6;">
            <tr><th style="text-align:left;padding:0.5rem 0.8rem;">URL</th><th style="text-align:left;padding:0.5rem 0.8rem;">Co to je</th></tr>
        </thead>
        <tbody>
            <tr><td style="padding:0.4rem 0.8rem;"><code>/oz/queue</code></td><td style="padding:0.4rem 0.8rem;">📬 Příchozí leady — čerstvé předané od navolávaček</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;"><code>/oz/leads</code></td><td style="padding:0.4rem 0.8rem;">💼 Pracovní plocha — rozpracované kontakty (přijaté z queue)</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;"><code>/oz</code></td><td style="padding:0.4rem 0.8rem;">📅 Můj měsíc — kvóty, milníky, dlužné navolávačkám za záchrany</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;"><code>/oz/campaigns</code></td><td style="padding:0.4rem 0.8rem;">🎯 Moje kampaně — sázky kde jsem příjemce</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;"><code>/oz/email-leads</code></td><td style="padding:0.4rem 0.8rem;">📧 Email leady — ze sázek delivery_type=email (export do XLSX)</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;"><code>/oz/performance</code></td><td style="padding:0.4rem 0.8rem;">🏆 Výkon týmu — srovnání s ostatními OZ</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;"><code>/oz/premium</code></td><td style="padding:0.4rem 0.8rem;">💎 Premium objednávky — vlastní objednávky druhého čištění</td></tr>
        </tbody>
    </table>

    <h2>🔄 Workflow stavy v pracovní ploše</h2>
    <p>Kontakt po přijetí z queue prochází těmito stavy v <code>/oz/leads</code>:</p>
    <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:8px;font-size:0.9rem;">
        <thead style="background:#f3f4f6;">
            <tr><th style="text-align:left;padding:0.5rem 0.8rem;">Stav</th><th style="text-align:left;padding:0.5rem 0.8rem;">Co znamená</th><th style="text-align:left;padding:0.5rem 0.8rem;">Co OZ dělá</th></tr>
        </thead>
        <tbody>
            <tr><td style="padding:0.4rem 0.8rem;font-weight:600;">NOVE</td><td style="padding:0.4rem 0.8rem;">Čerstvě přijatý z queue</td><td style="padding:0.4rem 0.8rem;">Začít zpracovávat</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;font-weight:600;">ZPRACOVAVA</td><td style="padding:0.4rem 0.8rem;">OZ s ním aktivně pracuje</td><td style="padding:0.4rem 0.8rem;">Volat, schůzka, nabídka</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;font-weight:600;">CALLBACK</td><td style="padding:0.4rem 0.8rem;">Naplánovaný callback</td><td style="padding:0.4rem 0.8rem;">Zavolat v daný čas</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;font-weight:600;">SCHUZKA</td><td style="padding:0.4rem 0.8rem;">Naplánovaná schůzka</td><td style="padding:0.4rem 0.8rem;">Sejít se, prezentace</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;font-weight:600;">NABIDKA</td><td style="padding:0.4rem 0.8rem;">Odeslal cenovou nabídku</td><td style="padding:0.4rem 0.8rem;">Čekat na odpověď</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;font-weight:600;">SANCE</td><td style="padding:0.4rem 0.8rem;">Aktivní obchod, BMSL známé</td><td style="padding:0.4rem 0.8rem;">Dotáhnout, předat BO</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;font-weight:600;">BO_PREDANO</td><td style="padding:0.4rem 0.8rem;">Předáno backoffice</td><td style="padding:0.4rem 0.8rem;">Čekat — BO řeší smlouvu</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;font-weight:600;">SMLOUVA</td><td style="padding:0.4rem 0.8rem;">Smlouva uzavřena</td><td style="padding:0.4rem 0.8rem;">Potvrdit podpis ✓</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;font-weight:600;">UZAVRENO</td><td style="padding:0.4rem 0.8rem;">Smlouva podepsána, aktivována</td><td style="padding:0.4rem 0.8rem;">Hotovo — provize dorazí</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;font-weight:600;color:#dc2626;">REKLAMACE</td><td style="padding:0.4rem 0.8rem;">OZ označil jako chybný lead</td><td style="padding:0.4rem 0.8rem;">Vrátit navolávačce, neplatit</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;font-weight:600;color:#dc2626;">NEZAJEM</td><td style="padding:0.4rem 0.8rem;">Klient odmítl</td><td style="padding:0.4rem 0.8rem;">Uzavřít prohru</td></tr>
        </tbody>
    </table>

    <h2>📋 Postup zpracování leadu (krok za krokem)</h2>
    <ol style="line-height:1.7;">
        <li>OZ se podívá do <code>/oz/queue</code> a uvidí kartičky čerstvých leadů od navolávaček</li>
        <li>Klikne <strong>✓ PŘIJMOUT</strong> u prvního leadu</li>
        <li>Otevře se pracovní plocha (<code>/oz/work?id=N</code>)</li>
        <li>OZ napíše <strong>poznámku</strong> (povinné před každou akcí) a klikne tlačítko podle situace:
            <ul>
                <li><strong>📧 Nabídka odeslána</strong> → stav NABIDKA</li>
                <li><strong>📅 Schůzka</strong> → vyplní datum/čas → stav SCHUZKA</li>
                <li><strong>↻ Callback</strong> → datum/čas → stav CALLBACK</li>
                <li><strong>⭐ Šance</strong> → vyplní BMSL → stav SANCE</li>
                <li><strong>📤 Předat BO</strong> → ID nabídky + BMSL → stav BO_PREDANO</li>
                <li><strong>💾 Uložit poznámku</strong> → bez změny stavu</li>
                <li><strong>⚠ Chybný lead</strong> → REKLAMACE (vrátí navolávačce)</li>
                <li><strong>🆘 Záchrana</strong> → vrátí navolávačce na 2. šanci</li>
                <li><strong>❌ Nezájem</strong> → NEZAJEM</li>
            </ul>
        </li>
        <li>OZ se vrátí do queue a vezme další lead</li>
        <li>Po BO_PREDANO se BO postará o smlouvu, OZ jen čeká</li>
        <li>Až BO dokončí, OZ v <code>/oz/leads</code> zaškrtne <strong>✓ Podpis potvrzen</strong> → UZAVRENO</li>
    </ol>

    <h2>📅 Můj měsíc (`/oz`)</h2>
    <p>Dashboardová stránka s přehledem:</p>
    <ul style="line-height:1.7;">
        <li><strong>Souhrn měsíce</strong> — kolik leadů přišlo, kolik uzavřel</li>
        <li><strong>Výkon &amp; milníky</strong> — počet smluv vs cíl, kraje</li>
        <li><strong>Per kraj</strong> — kvóty a pokrok</li>
        <li><strong>🆘 Záchrany — dlužné navolávačkám</strong> — pokud OZ poslal něco na záchranu a navolávačka uspěla</li>
        <li><strong>📄 Výplata navolávaček (PDF)</strong> — kolik OZ dluží navolávačkám za leady</li>
    </ul>

    <h2>🎯 Sázky a kampaně</h2>
    <p>Pokud je OZ příjemce sázky (vybral ho admin), uvidí v <code>/oz/campaigns</code> přehled kampaní s pokrokem. Detaily v <a href="<?= crm_url('/help/topic?id=bet') ?>">🎯 Sázky / kampaně</a>.</p>

    <h2>💡 Časté otázky</h2>
    <details style="margin-bottom:0.5rem;background:#f9fafb;padding:0.6rem 0.9rem;border-radius:5px;cursor:pointer;">
        <summary style="font-weight:600;">Co dělat když zákazník nereaguje 4× — Chybný lead nebo Záchrana?</summary>
        <p style="margin:0.4rem 0 0;font-size:0.88rem;color:#6b7280;">
            <strong>Chybný lead</strong> = lead byl od navolávačky špatně navolán (= zákazník se nestaví o ničem nebo telefon neplatný). Reklamace, neplatíš za něj.
            <br><br>
            <strong>Záchrana</strong> = lead byl správný (zákazník mluvil s navolávačkou, projevil zájem), ale teď nereaguje. Posíláš zpět navolávačce, ať to zkusí znovu. Pokud uspěje, dáš jí bonus = 1× hodnota smlouvy.
        </p>
    </details>
    <details style="margin-bottom:0.5rem;background:#f9fafb;padding:0.6rem 0.9rem;border-radius:5px;cursor:pointer;">
        <summary style="font-weight:600;">Co je BMSL a kde to zadat?</summary>
        <p style="margin:0.4rem 0 0;font-size:0.88rem;color:#6b7280;">
            BMSL = <strong>měsíční hodnota smlouvy</strong> bez DPH, zaokrouhleno na stokoruny dolů. Zadáváš v inline panelu při akci „⭐ Šance" nebo „📤 Předat BO". BMSL je klíčové pro provizní výpočty a bonus při záchraně.
        </p>
    </details>
</div>
