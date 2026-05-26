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

    <div style="background:#dbeafe;border-left:4px solid #2563eb;padding:0.9rem 1.1rem;border-radius:0 6px 6px 0;margin:1rem 0;">
        <strong>📌 Kdo jsem:</strong> Čistička je první pracovnice v pipeline. Dostane <strong>NEW kontakt</strong> a její úkol je zjistit, jaký má operátor (TM/O2/VF) a jestli má vůbec smysl ho volat.
    </div>

    <h2>🎯 Cíl čističky</h2>
    <p>Z fronty NEW kontaktů co nejrychleji vytřídit:</p>
    <ul style="line-height:1.7;">
        <li><strong>🌸 TM</strong> (T-Mobile) → půjde dál navolávačce</li>
        <li><strong>🔵 O2</strong> → půjde dál navolávačce</li>
        <li><strong>🔴 VF</strong> (Vodafone) → přeskočí, my Vodafone klienty nevoláme</li>
        <li><strong>🚫 Chybný</strong> → neplatné / zahraniční / nesmyslné číslo</li>
    </ul>

    <h2>🖥️ Pracovní obrazovka — co kde najde</h2>

    <h3 style="margin-top:1rem;color:#7e22ce;">Top karta: 🐌 Šněčí závody</h3>
    <p>Měsíční progress čističky vůči ostatním. Vidí kolik už zpracovala vs. cíl. Animovaný šnečí závod motivuje.</p>

    <h3 style="margin-top:1rem;color:#7e22ce;">🎯 Cíle podle krajů</h3>
    <p>Kartičky pro každý kraj se kterým čistička může pracovat. Každá ukazuje:</p>
    <ul style="line-height:1.7;">
        <li><strong>X / Y</strong> — kolik už týmově vyčistila vs. cíl měsíce</li>
        <li><strong>Zbývá: Z · X %</strong> — zbývající kontakty + procento dokončení</li>
        <li><strong>(z toho ty: N)</strong> — kolik z toho udělala konkrétně tahle čistička</li>
        <li><strong>N v DB</strong> — kolik NEW kontaktů je v daném kraji aktuálně v DB (= dostupných k vyčištění)</li>
        <li><strong>⭐ priorita</strong> — od ⭐1 (nejvyšší) po ⭐10. Které kraje řešit nejdřív.</li>
    </ul>
    <p style="font-size:0.85rem;color:#6b7280;">Kliknutí na kraj filtruje seznam kontaktů níže jen na ten kraj.</p>

    <h3 style="margin-top:1rem;color:#7e22ce;">Záložky: K ověření / Zkontrolováno / Výkon</h3>
    <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:8px;font-size:0.9rem;">
        <thead style="background:#f3f4f6;">
            <tr><th style="text-align:left;padding:0.5rem 0.8rem;">Záložka</th><th style="text-align:left;padding:0.5rem 0.8rem;">Co obsahuje</th></tr>
        </thead>
        <tbody>
            <tr><td style="padding:0.4rem 0.8rem;font-weight:600;">K ověření</td><td style="padding:0.4rem 0.8rem;">NEW kontakty čekající na verify. Hlavní pracovní seznam.</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;font-weight:600;">Zkontrolováno</td><td style="padding:0.4rem 0.8rem;">Historie — všechny kontakty co kdy tahle čistička verifikovala (možnost překlasifikovat).</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;font-weight:600;">📊 Výkon</td><td style="padding:0.4rem 0.8rem;">Statistiky — graf dnes/měsíc, počty per den, projekce, výplata.</td></tr>
        </tbody>
    </table>

    <h2>⌨️ Postup verify (krok za krokem)</h2>
    <ol style="line-height:1.8;">
        <li>Čistička otevře <code>/cisticka</code></li>
        <li>Vybere kraj (klikne na tile) NEBO nechá „Vše"</li>
        <li>U každého kontaktu jsou 4 tlačítka: <strong>🔴 VF</strong> · <strong>🌸 TM</strong> · <strong>🔵 O2</strong> · <strong>🚫 Chybný</strong></li>
        <li>Klávesové zkratky: <kbd>1</kbd> = VF · <kbd>2</kbd> = TM · <kbd>3</kbd> = O2 · <kbd>4</kbd> = Chybný</li>
        <li>Po kliknutí: záznam zezelená, ukáže se odpočet <strong>5 sekund</strong> s tlačítkem <strong>↩ Zpět</strong></li>
        <li>Po 5 sekundách: záznam zmizí, počitátka se aktualizují (cíl kraje + Zkontrolováno badge)</li>
        <li>Pokud se čistička spletla → klikne <strong>↩ Zpět</strong> během 5 s → vrátí se</li>
    </ol>

    <h2>🚀 Hromadné zpracování (bulk verify)</h2>
    <p>Když má čistička jasno o celé stránce, místo klikání může:</p>
    <ol style="line-height:1.7;">
        <li>U každého kontaktu si <strong>označí 1 z 4 tlačítek</strong> (jakoby předbéhne)</li>
        <li>Klikne <strong>„Zpracovat vše"</strong> dole pod seznamem</li>
        <li>Všech 10/20 kontaktů se zpracuje najednou</li>
    </ol>

    <h2>💰 Výplata čističky</h2>
    <p>Čistička dostává <strong>X Kč za ověřený kontakt</strong> (sazbu nastavuje majitel v <code>/admin/cisticka-goals</code>). Plus může mít:</p>
    <ul style="line-height:1.7;">
        <li><strong>Pásmové bonusy</strong> — po splnění cíle dostane +5%, +10%</li>
        <li><strong>Premium navíc</strong> — když dělá premium objednávky pro OZ (viz Premium objednávky)</li>
    </ul>
    <p>PDF výplaty viděla na <code>/cisticka/payout/print</code>.</p>

    <h2>⚠️ Časté otázky / problémy</h2>
    <details style="margin-bottom:0.5rem;background:#f9fafb;padding:0.6rem 0.9rem;border-radius:5px;cursor:pointer;">
        <summary style="font-weight:600;">Klikla jsem omylem TM místo O2, co teď?</summary>
        <p style="margin:0.4rem 0 0;font-size:0.88rem;color:#6b7280;">
            Během 5 s klikni <strong>↩ Zpět</strong>. Nebo přejdi na záložku <strong>Zkontrolováno</strong> a tam klikni <strong>Reclassify</strong> — můžeš to opravit i později.
        </p>
    </details>
    <details style="margin-bottom:0.5rem;background:#f9fafb;padding:0.6rem 0.9rem;border-radius:5px;cursor:pointer;">
        <summary style="font-weight:600;">Proč některé kraje nevidím v cílech?</summary>
        <p style="margin:0.4rem 0 0;font-size:0.88rem;color:#6b7280;">
            Admin nastavil cíle jen pro některé kraje (kde je víc poptávky). Mimo cílové kraje nevidíš tile, ale můžeš si rozbalit „Filtr krajů" dole a vybrat libovolný kraj kde máš přístup.
        </p>
    </details>
    <details style="margin-bottom:0.5rem;background:#f9fafb;padding:0.6rem 0.9rem;border-radius:5px;cursor:pointer;">
        <summary style="font-weight:600;">Co je „strict mode" při aktivní sázce?</summary>
        <p style="margin:0.4rem 0 0;font-size:0.88rem;color:#6b7280;">
            Když admin spustí <strong>sázku</strong> v některém z tvých krajů, automaticky se ti zúží zobrazení JEN na ten kraj. Důvod: sázka má prioritu, soustředíš se na ni. Po uzavření sázky se filtr uvolní.
        </p>
    </details>
</div>
