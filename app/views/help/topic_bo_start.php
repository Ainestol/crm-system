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
.hp-section p { margin: .4rem 0; line-height: 1.6; color:#374151; }
.hp-section ul { margin: .4rem 0; padding-left: 1.4rem; line-height: 1.7; color:#374151; }
.hp-section ul li { margin-bottom: .3rem; }
.hp-section strong { color: #111827; }
.hp-btn-row { background: linear-gradient(135deg, #eff6ff, #f3e8ff); border:1px solid #bfdbfe; padding:1rem 1.2rem; border-radius:10px; margin: .5rem 0 1rem; }
.hp-btn-row .label { font-weight:700; color:#1e3a8a; }
.hp-tip { background:#fef3c7; border:1px solid #fcd34d; padding:.8rem 1rem; border-radius:8px; color:#92400e; font-size:.9rem; margin: .8rem 0; }
</style>

<div class="hp-wrap">
    <a href="/help" class="back">← Zpět na rozcestník</a>
    <h1>🏢 Vítej, backoffice!</h1>
    <p class="lead">
        Tvoje role: <strong>finalizovat smlouvy</strong>, které OZ podepsali. Zkontrolovat, že je vše v pořádku,
        zapsat čísla smluv do systému operátora, aktivovat zákazníka.
    </p>

    <div class="hp-section">
        <h2>📋 Pracovní plocha</h2>
        <p>V sidebaru klikni na <strong>Pracovní plocha</strong>. Uvidíš všechny kontakty ve stavech, které tě týkají:</p>
        <ul>
            <li><strong>Předáno BO</strong> — OZ ti právě poslal smlouvu. Tady začíná tvoje práce.</li>
            <li><strong>Ve zpracování</strong> — vzal jsi to do práce, řešíš s operátorem.</li>
            <li><strong>Vráceno</strong> — vrátil jsi to OZ (něco mu chybí). On to doplní a pošle zpět.</li>
            <li><strong>Dokončeno</strong> — smlouva je aktivována, hotovo.</li>
        </ul>
    </div>

    <div class="hp-section">
        <h2>🎯 Tlačítka v kartě smlouvy</h2>

        <div class="hp-btn-row">
            <span class="label">▶ Začít zpracovávat</span> — bereš smlouvu do práce.
            Stav se posune na "Ve zpracování". OZ vidí, že to máš.
        </div>

        <div class="hp-btn-row">
            <span class="label">✅ Smlouva aktivována</span> — operátor (T-Mobile / O2) ti potvrdil aktivaci.
            Klikni → smlouva je hotová, zákazník je aktivní.
        </div>

        <div class="hp-btn-row">
            <span class="label">↩ Vrátit OZ</span> — něco chybí (špatné IČO, nepoužitelná smlouva, ...).
            Napiš důvod do poznámky, klik → OZ to dostane zpět + uvidí tvůj komentář.
        </div>

        <div class="hp-btn-row">
            <span class="label">❌ Storno</span> — zákazník už nechce, smlouva se ruší.
            Napiš důvod, klik → smlouva je uzavřena jako storno.
        </div>
    </div>

    <div class="hp-section">
        <h2>📝 Poznámky a interní komunikace</h2>
        <p>
            V kartě klienta vidíš celou historii: kdo s ním mluvil, kdy, jaký byl výsledek.
            Můžeš přidat poznámku — uvidí ji OZ, který ti smlouvu poslal.
        </p>
        <p>
            Pokud máš dotaz na OZ (např. „chybí mi BMSL"), napiš ho do poznámky.
            OZ dostane notifikaci.
        </p>
    </div>

    <div class="hp-tip">
        💡 <strong>Tip:</strong> Než smlouvu vrátíš OZ, zkus se s ním nejdřív domluvit ústně —
        rychleji to vyřešíte, než přes poznámky.
    </div>

    <div class="hp-section">
        <h2>🚨 Co dělat, když je kontakt v reklamaci</h2>
        <p>
            Reklamace = OZ označil, že se zákazníkem byl problém (nedosažitelný, špatný kontakt).
            V seznamu má červený badge. Ty jako BO máš dvě možnosti:
        </p>
        <ul>
            <li><strong>Smlouva už byla aktivována</strong> → reklamace je jen informativní, nic neměň.</li>
            <li><strong>Smlouva ještě nebyla aktivována</strong> → zruš ji jako storno (s důvodem reklamace).</li>
        </ul>
    </div>
</div>
