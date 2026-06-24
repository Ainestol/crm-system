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
.stage-flow {
    display:flex; gap:.4rem; flex-wrap:wrap; padding:.6rem 0;
    align-items:center;
}
.stage-flow .stage {
    background:#e0e7ff; color:#3730a3; padding:.3rem .7rem; border-radius:20px;
    font-size:.82rem; font-weight:600;
}
.stage-flow .arrow { color:#9ca3af; font-size:.85rem; }
</style>

<div class="hp-wrap">
    <a href="/help" class="back">← Zpět na rozcestník</a>
    <h1>💼 Vítej, obchodáku!</h1>
    <p class="lead">
        Tvůj úkol: <strong>od navolávačky převzít lead</strong>, domluvit schůzku, předložit nabídku
        a dotáhnout to k podpisu smlouvy. Za každou uzavřenou smlouvu máš provizi.
    </p>

    <div class="hp-section">
        <h2>📋 Tvoje pracovní plocha</h2>
        <p>V sidebaru pod <strong>Leady</strong> máš tři pohledy:</p>
        <ul>
            <li><strong>Příchozí</strong> — nové leady od navolávačky. <strong>Tady začni.</strong> Klik na lead = převzít a otevřít kartu.</li>
            <li><strong>Pracovní plocha</strong> — všichni tvoji rozpracovaní klienti (schůzky, nabídky, čeká BO, …).</li>
            <li><strong>Email leady</strong> — leady, které ti přišly přes web/email formulář.</li>
            <li><strong>Moje kampaně</strong> — speciální leady ze sázek (cílených kampaní).</li>
        </ul>
    </div>

    <div class="hp-section">
        <h2>🚀 Pipeline — co znamenají jednotlivé fáze (stages)</h2>
        <div class="stage-flow">
            <span class="stage">NOVÉ</span>
            <span class="arrow">→</span>
            <span class="stage">ZPRACOVÁVÁ</span>
            <span class="arrow">→</span>
            <span class="stage">SCHŮZKA</span>
            <span class="arrow">→</span>
            <span class="stage">NABÍDKA</span>
            <span class="arrow">→</span>
            <span class="stage">ŠANCE</span>
            <span class="arrow">→</span>
            <span class="stage">SMLOUVA</span>
            <span class="arrow">→</span>
            <span class="stage">PODEPSÁNO ✓</span>
        </div>
        <ul>
            <li><strong>NOVÉ</strong> — Lead od navolávačky, čeká na tvoji akci.</li>
            <li><strong>ZPRACOVÁVÁ</strong> — Začal jsi s ním pracovat, ale ještě nedomluvená schůzka.</li>
            <li><strong>SCHŮZKA</strong> — Domluvený konkrétní termín.</li>
            <li><strong>NABÍDKA</strong> — Předal jsi mu nabídku, čeká se na rozhodnutí.</li>
            <li><strong>ŠANCE</strong> — Vysoká pravděpodobnost uzavření, finalizujete podmínky.</li>
            <li><strong>SMLOUVA</strong> — Smlouva je sepsaná, zákazník ji ještě nepodepsal.</li>
            <li><strong>PODEPSÁNO</strong> — Smlouva je platná. Zaškrtnutím checkboxu „podpis potvrzen" se ti začne počítat BMSL.</li>
        </ul>
    </div>

    <div class="hp-section">
        <h2>🎯 Tlačítka v kartě klienta</h2>

        <div class="hp-btn-row">
            <span class="label">📅 Domluvit schůzku</span> — vyber datum a čas, napiš poznámku (povinná),
            klik. Stav se posune na SCHŮZKA, v Kalendáři se ti objeví.
        </div>

        <div class="hp-btn-row">
            <span class="label">📨 Předložit nabídku</span> — popíšeš detaily nabídky v poznámce,
            ev. nahraješ číslo nabídky pro BO. Stav → NABÍDKA.
        </div>

        <div class="hp-btn-row">
            <span class="label">⭐ Posunout na šanci</span> — když máš pocit, že se smlouva blíží,
            posuneš na ŠANCE. Tím se kontakt zobrazí ve výrazněji v dashboardu.
        </div>

        <div class="hp-btn-row">
            <span class="label">📋 Sepsat smlouvu</span> — vyplníš BMSL (hodnota smlouvy/měsíc), číslo smlouvy.
            Tím se stav posune na SMLOUVA. Smlouva čeká na podpis.
        </div>

        <div class="hp-btn-row">
            <span class="label">✅ Podpis potvrzen</span> — Checkbox v kartě. Klikneš až
            zákazník reálně podepsal a ty máš smlouvu na stole. <strong>Toto spouští počítání BMSL.</strong>
        </div>

        <div class="hp-btn-row">
            <span class="label">📤 Předat BO (backoffice)</span> — když je vše hotové (smlouva podepsaná),
            předáš to backoffice na aktivaci. Vyžaduje BMSL + číslo nabídky.
        </div>

        <div class="hp-btn-row">
            <span class="label">❌ Nezájem / Nerelevantní</span> — zákazník odmítl. Stav → NEZAJEM. Lead uzavřen.
        </div>

        <div class="hp-btn-row">
            <span class="label">📝 Jen poznámka (NOTE_ONLY)</span> — chceš poznamenat něco bez změny stavu
            (např. „volal mi, ozve se zítra"). Stav zůstává, poznámka se přidá do historie.
        </div>
    </div>

    <div class="hp-section">
        <h2>🆘 Záchrana (rescue) — když to nezvládáš</h2>
        <p>
            Pokud máš lead, kde zákazník přestal reagovat, neuzavíraj ho jako Nezájem.
            Místo toho <strong>pošli ho na záchranu navolávačce</strong> — má 14 dní pokusit se s ním znovu spojit.
        </p>
        <p>
            Najdeš to v kartě klienta jako tlačítko <strong>🆘 Poslat na záchranu</strong>.
            Vybereš důvod, klikneš → kontakt jde navolávačce. Pokud ona uspěje, zase ti ho vrátí.
        </p>
        <div class="hp-tip">
            💡 <strong>Tip:</strong> Záchrana je super pro leady, které si vyhloubily „klidnou díru".
            Navolávačka má jiný styl komunikace a často zákazníka rozhýbe.
        </div>
    </div>

    <div class="hp-section">
        <h2>🔍 Vyhledat klienta</h2>
        <p>
            V sidebaru <strong>Vyhledat klienta</strong> najdeš full-text vyhledávání napříč všemi kontakty firmy.
            Můžeš zadat IČO, telefon, jméno firmy, email.
        </p>
        <p>
            Najdeš-li klienta, který patří jinému OZ a chceš ho převzít, najdeš tam tlačítko <strong>Převzít kontakt</strong>.
            <strong>Pozor:</strong> převzetí potřebuje souhlas majitele firmy. Bez něj nech kontakt v péči původního OZ.
        </p>
    </div>

    <div class="hp-section">
        <h2>💎 Premium objednávka</h2>
        <p>
            Pokud potřebuješ <strong>cílenou skupinu vyčištěných leadů s navolávačkou</strong>,
            podej premium objednávku přes <strong>Premium → Nová objednávka</strong>.
        </p>
        <p>
            Zadáš: kolik leadů chceš, z jakých krajů, jakou navolávačku preferuješ (volitelné),
            cenu za lead a bonus za úspěšný hovor. Čistička + navolávačka pro tebe pracují prioritně.
        </p>
    </div>

    <div class="hp-section">
        <h2>📊 Můj měsíc + Výkon týmu</h2>
        <p>
            <strong>Můj měsíc</strong> — tvoje aktuální BMSL, kvóty per kraj, splnění + payout PDF.
        </p>
        <p>
            <strong>Výkon týmu</strong> — srovnání všech OZ ve firmě. Vidíš, kdo má kolik smluv,
            kdo je v top 3, kdo zaostává. Žádné drama, jen férové srovnání.
        </p>
    </div>

    <div class="hp-good">
        ✅ <strong>Pravidlo č. 1:</strong> Klikni na tlačítko jen tehdy, když děláš konkrétní akci.
        Neslouží jako poznámkový blok — od toho je textové pole. Stav posunuj jen reálně, nezasiluj
        si statistiky.
    </div>
</div>
