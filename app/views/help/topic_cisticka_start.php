<?php
/** @var array<string,mixed> $topic */
?>
<style>
.hp-wrap { max-width: 920px; margin: 0 auto; padding: 1rem 1.4rem; }
.hp-wrap a.back { color: #2563eb; text-decoration: none; font-size: .9rem; }
.hp-wrap h1 { margin: .5rem 0 .3rem; font-size: 1.6rem; }
.hp-wrap .lead { color: #6b7280; font-size: 1rem; margin-bottom: 1.5rem; line-height: 1.6; }
.hp-section { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:1.2rem 1.4rem; margin-bottom:1rem; }
.hp-section h2 { margin: 0 0 .7rem; font-size: 1.1rem; color:#111827; }
.hp-section h3 { margin: 1rem 0 .4rem; font-size: .98rem; color:#374151; }
.hp-section p { margin: .4rem 0; line-height: 1.6; color:#374151; }
.hp-section ul { margin: .4rem 0; padding-left: 1.4rem; line-height: 1.7; color:#374151; }
.hp-section ul li { margin-bottom: .3rem; }
.hp-section strong { color: #111827; }
.hp-btn-row { background: linear-gradient(135deg, #eff6ff, #f3e8ff); border:1px solid #bfdbfe; padding:1rem 1.2rem; border-radius:10px; margin: .5rem 0 1rem; }
.hp-btn-row .label { font-weight:700; color:#1e3a8a; }
.hp-tip { background:#fef3c7; border:1px solid #fcd34d; padding:.8rem 1rem; border-radius:8px; color:#92400e; font-size:.9rem; margin: .8rem 0; }
.hp-warn { background:#fee2e2; border:1px solid #fca5a5; padding:.8rem 1rem; border-radius:8px; color:#991b1b; font-size:.9rem; margin: .8rem 0; }
.kbd { background:#f3f4f6; border:1px solid #d1d5db; border-radius:4px; padding:.1rem .4rem; font-family: monospace; font-size:.85rem; }
</style>

<div class="hp-wrap">
    <a href="/help" class="back">← Zpět na rozcestník</a>
    <h1>🧹 Vítej, čističko!</h1>
    <p class="lead">
        Tvůj úkol je ověřit, jestli kontakty (telefony) patří k operátorům
        <strong>T-Mobile (TM)</strong> nebo <strong>O2</strong> — ty jdou dál do navolávačky.
        Vodafone (VF) a chybné kontakty se sem nevolají, ale i ty musíš správně označit.
    </p>

    <div class="hp-section">
        <h2>📋 Tvoje pracovní plocha</h2>
        <p>V sidebaru klikni na <strong>Pracovní plochy → Standardní čištění</strong>. Uvidíš:</p>
        <ul>
            <li><strong>Tile s cíli</strong> nahoře — kolik kontaktů máš dnes vyčistit per kraj (např. „Praha 12/15“). Postup je vidět live.</li>
            <li><strong>Filtr krajů</strong> — pokud máš víc krajů, klikni na konkrétní a uvidíš jen jeho kontakty.</li>
            <li><strong>Záložka „K ověření"</strong> — kontakty čekající na zpracování.</li>
            <li><strong>Záložka „Zkontrolováno"</strong> — co už jsi ověřila (historie tvojí práce).</li>
        </ul>
    </div>

    <div class="hp-section">
        <h2>🎯 Co dělá které tlačítko</h2>

        <h3>U každého kontaktu</h3>

        <div class="hp-btn-row">
            <span class="label">📞 TM</span> — kontakt patří T-Mobilu. Klikni → kontakt jde do fronty navolávačky.
        </div>

        <div class="hp-btn-row">
            <span class="label">📞 O2</span> — kontakt patří O2. Stejné jako TM, jde do fronty.
        </div>

        <div class="hp-btn-row">
            <span class="label">⏭ VF (Vodafone)</span> — telefon patří VF. <strong>Nevoláme jim.</strong> Klik označí kontakt jako VF_SKIP — vypadne z poolu.
        </div>

        <div class="hp-btn-row">
            <span class="label">❌ Chybný kontakt</span> — telefon neexistuje, špatný formát, nedosažitelný operátor. Klikneš a zapíšeš krátkou poznámku.
        </div>

        <div class="hp-warn">
            ⚠ <strong>Nejistá?</strong> Použij operátor lookup nahoře (zadáš číslo → systém ti řekne TM/O2/VF).
            Nikdy nehádej. Špatně označený VF nás přijde na pokutu.
        </div>
    </div>

    <div class="hp-section">
        <h2>↩ Udělala jsi chybu? Žádný stres</h2>
        <p>
            Když klikneš omylem na špatné tlačítko, máš <strong>5 sekund undo</strong> okno.
            Stačí kliknout na <strong>Zpět</strong> v notifikaci nahoře.
        </p>
        <p>
            Pokud uplynulo víc než 5 sekund, kontakt se přesune do záložky <strong>Zkontrolováno</strong>.
            Tam ho najdeš a klikneš <strong>Znovu otevřít</strong> — vrátí se ti k ověření.
        </p>
    </div>

    <div class="hp-section">
        <h2>💎 Premium čištění (druhá pracovní plocha)</h2>
        <p>
            Občas přijde od OZ <strong>premium objednávka</strong> — speciální požadavek na druhé čištění
            kontaktů, které už prošly. Najdeš ji v sidebaru pod <strong>Pracovní plochy → Premium čištění</strong>.
            Tam jsou jiná tlačítka (Tradeable / Non-tradeable) a vyšší sazba.
        </p>
        <div class="hp-tip">
            💡 Premium se počítá zvlášť. Sazbu a pravidla ti řekne majitel firmy.
        </div>
    </div>

    <div class="hp-section">
        <h2>📊 Cíle a sazba</h2>
        <p>
            Tvoje denní cíle (kolik kontaktů ověřit) nastavuje <strong>majitel firmy</strong> v Cílech čističky.
            Když splníš plán, tile zezelená a uvidíš ✓.
        </p>
        <p>
            Sazba (kolik dostaneš za jeden ověřený kontakt) je také v rukou majitele — najdeš ji v <strong>Můj výdělek</strong> (pokud máš), nebo se zeptej.
        </p>
    </div>

    <div class="hp-tip">
        💡 <strong>Nejčastější mýtus:</strong> &nbsp; "Když omylem označím TM místo O2, je to průšvih."
        <br>Ne, není. Navolávačka to volá stejně. Důležité je nepřehlédnout <strong>Vodafone</strong> —
        toho se nesmí dotknout (zákazník nás může nahlásit).
    </div>
</div>
