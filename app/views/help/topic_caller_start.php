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
.hp-good { background:#d1fae5; border:1px solid #6ee7b7; padding:.8rem 1rem; border-radius:8px; color:#065f46; font-size:.9rem; margin: .8rem 0; }
</style>

<div class="hp-wrap">
    <a href="/help" class="back">← Zpět na rozcestník</a>
    <h1>📞 Vítej, navolávačko!</h1>
    <p class="lead">
        Tvůj job: <strong>volat ověřené kontakty</strong>, zjistit jestli mají zájem o nabídku,
        a úspěšné předat obchodákovi (OZ) na schůzku. Za každou úspěšnou výhru máš odměnu.
    </p>

    <div class="hp-section">
        <h2>📋 Tvoje pracovní plocha</h2>
        <p>V sidebaru jsou pod <strong>Hovory</strong> tři typy:</p>
        <ul>
            <li><strong>Standardní pool</strong> — běžné kontakty z fronty (čistička je předem ověřila jako TM/O2).</li>
            <li><strong>Premium navolávky</strong> — speciální objednávky od OZ <em>s bonusem</em>. Pracují se zvlášť.</li>
            <li><strong>Sázky (kampaně)</strong> — cílené kampaně (např. „100 kontaktů z Prahy"). Vidíš jen kampaně, do kterých tě admin zařadil.</li>
        </ul>
    </div>

    <div class="hp-section">
        <h2>🔒 Jak funguje zámek (lock)</h2>
        <p>
            Když si vezmeš kontakt do práce, systém ho <strong>zamkne pro tebe na 10 minut</strong>.
            Jiná navolávačka ho v té chvíli nevidí. To brání situaci, kdy by dva lidé volali stejnému zákazníkovi.
        </p>
        <p>Máš najednou max 10 kontaktů v zámku (10×10 minut = až 100 minut práce v queue).</p>
        <div class="hp-tip">
            💡 Když 10 minut zámek vyprší a ty jsi se neozvala, kontakt se uvolní jinému callerovi. Není to tragédie, ale snaž se hovor stíhat.
        </div>
    </div>

    <div class="hp-section">
        <h2>🎯 Co dělá které tlačítko (po hovoru)</h2>

        <div class="hp-btn-row">
            <span class="label">✅ Výhra (CALLED_OK)</span> — zákazník má zájem.
            Vybereš OZ ze seznamu, napíšeš poznámku, klikneš → kontakt jde k OZ na schůzku.
            <strong>Toto je tvoje hlavní odměna.</strong>
        </div>

        <div class="hp-btn-row">
            <span class="label">❌ Nezájem (NEZAJEM)</span> — zvedl, ale nemá zájem.
            Napiš poznámku (povinná), klik. Lead je uzavřen.
        </div>

        <div class="hp-btn-row">
            <span class="label">📞 Nedovoláno (NEDOVOLANO)</span> — nezvedl. Můžeš zkusit znovu později (3× max).
            Po 3× se kontakt automaticky uzavře.
        </div>

        <div class="hp-btn-row">
            <span class="label">📅 Callback (CALLBACK)</span> — domluvíš se zákazníkem,
            kdy mu zavoláš zpět. Zadáš datum + čas → kontakt se ti v daný den vrátí do fronty.
        </div>

        <div class="hp-btn-row">
            <span class="label">⚠ Chybný kontakt (CALLED_BAD)</span> — telefon je špatně,
            někdo jiný, nedostupný. Napiš poznámku, klik.
        </div>

        <div class="hp-btn-row">
            <span class="label">🔇 Izolace (IZOLACE)</span> — zákazník nechce být kontaktován vůbec
            (DNC — Do Not Call). <strong>Důležité!</strong> Klikneš → kontakt jde na blacklist napořád.
        </div>

        <div class="hp-warn">
            ⚠ <strong>U Izolace si dej pozor.</strong> Pokud zákazník výslovně řekne „už mi nevolejte",
            klikni Izolaci. Když ne, použij jen Nezájem. Izolace = napořád, nelze odvolat běžnou cestou.
        </div>
    </div>

    <div class="hp-section">
        <h2>🎯 Sázky / kampaně (zvláštnost)</h2>
        <p>
            Když máš v sidebaru <strong>Hovory → Sázky</strong>, znamená to, že admin vytvořil
            cílenou kampaň (např. „50 kontaktů z Prahy") a zařadil tě do ní. Volání funguje stejně jako standardní,
            ale máš <strong>vyšší bonus za výhru</strong> (kampaň má speciální sazbu).
        </p>
        <p>
            V kampani je <strong>OZ předem určen</strong> — nemusíš vybírat ze seznamu, systém ho zafixuje za tebe.
        </p>
    </div>

    <div class="hp-section">
        <h2>💎 Premium navolávky</h2>
        <p>
            OZ si občas objedná <strong>premium pool</strong> — speciální skupinu kontaktů
            s vyšší pravděpodobností úspěchu (a vyšším bonusem). V sidebaru je <strong>Hovory → Premium navolávky</strong>.
        </p>
        <p>
            Pracuje se s nimi stejně jako se standardním poolem, jen výsledky (success / failed)
            mají vyšší výplatu. Sazbu nastavuje OZ + majitel.
        </p>
    </div>

    <div class="hp-section">
        <h2>🆘 Záchrana leadu (rescue)</h2>
        <p>
            Pokud ti OZ pošle <strong>lead na záchranu</strong>, znamená to, že s ním měl problém
            (zákazník přestal reagovat). Ty máš <strong>14 dní</strong> ho znovu kontaktovat.
            Najdeš ho ve své frontě, označený jako 🆘.
        </p>
        <p>
            <strong>Bonus:</strong> pokud zákazníka zachráníš (CALLED_OK), dostaneš
            mimořádný bonus = výše smlouvy (BMSL). Hodně se vyplatí pokoušet se i o těžké leady.
        </p>
    </div>

    <div class="hp-section">
        <h2>📅 Kalendář callbacků</h2>
        <p>
            V sidebaru klikni na <strong>Kalendář</strong> — uvidíš všechny callbacky,
            které máš na konkrétní den + čas. Vrátí se ti do fronty automaticky.
        </p>
        <div class="hp-good">
            ✅ <strong>Pravidlo č. 1 pro callbacky:</strong> Vždy volej v dohodnutý čas, nebo 5 minut po. Zákazník se pak víc otevírá.
        </div>
    </div>

    <div class="hp-section">
        <h2>💰 Můj výdělek</h2>
        <p>
            V sidebaru najdeš <strong>Můj výdělek</strong> — uvidíš svoje statistiky:
            kolik výher za měsíc, kolik bonusů z premium, callbacků, sázek.
        </p>
        <p>
            Sazby (base reward + bonusy) jsou v rukou majitele. Když máš pocit, že něco nesedí, zeptej se ho.
        </p>
    </div>

    <div class="hp-tip">
        💡 <strong>Tip od zkušených navolávaček:</strong>
        <ul>
            <li>První 5 sekund hovoru rozhoduje. Začni krátce a zřetelně.</li>
            <li>Ne je jen krok blíž k Ano — zaznamenej, jdi dál.</li>
            <li>Callbacky striktně dodržuj. Zákazník to ocení a víc otevře.</li>
            <li>Nepřehánej s nátlakem na Izolaci — když si nejsi jistá, použij Nezájem.</li>
        </ul>
    </div>
</div>
