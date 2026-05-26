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

    <div style="background:#dcfce7;border-left:4px solid #16a34a;padding:0.9rem 1.1rem;border-radius:0 6px 6px 0;margin:1rem 0;">
        <strong>📌 Kdo jsem:</strong> Navolávačka je druhá pracovnice. Bere <strong>READY kontakty</strong> (vyčištěné TM/O2), volá zákazníky a snaží se získat <strong>výhru = předání obchodákovi</strong>. Z 1 výhry dostává základní sazbu (např. 200 Kč) + bonusy.
    </div>

    <h2>🎯 Cíle navolávačky</h2>
    <ul style="line-height:1.7;">
        <li><strong>Výhra (CALLED_OK)</strong> — zákazník má zájem, předává se OZ. <span style="color:#16a34a;font-weight:600;">+200 Kč (default sazba)</span></li>
        <li><strong>Callback</strong> — zákazník teď nemá čas, domluveno na později</li>
        <li><strong>Nedovoláno</strong> — 3× nedovoláno = automaticky NEZAJEM</li>
        <li><strong>Nezájem</strong> — zákazník odmítl</li>
        <li><strong>Chybný kontakt</strong> — neexistující číslo, špatné údaje</li>
        <li><strong>Izolace</strong> — DNC (nikdy nevolat — GDPR)</li>
    </ul>

    <h2>🖥️ Pracovní obrazovka — `/caller`</h2>

    <h3 style="margin-top:1rem;color:#7e22ce;">Top karta: 🐌 Šněčí závody</h3>
    <p>Stejně jako u čističky — měsíční progress vs. ostatní navolávačky. Pásmové bonusy: po splnění X výher +5%, po Y výher +10%.</p>

    <h3 style="margin-top:1rem;color:#7e22ce;">💰 Moje peníze tento měsíc</h3>
    <p>Rozklad výdělků:</p>
    <ul style="line-height:1.7;">
        <li><strong>📞 Standard</strong> — počet výher × sazba (default 200 Kč/výhra)</li>
        <li><strong>🆘 Záchrany (earned)</strong> — úspěšné záchrany leadů, čekající na podpis</li>
        <li><strong>✓ Vyplaceno</strong> — záchrany kde už OZ poslal peníze</li>
        <li><strong>💼 Celkem</strong> — součet všeho</li>
    </ul>
    <p>📄 PDF výplata: tlačítko vpravo nahoře v widgetu.</p>

    <h3 style="margin-top:1rem;color:#7e22ce;">📊 Volné kvóty OZ</h3>
    <p>Tabulka „Kdo z OZ ještě má volnou kapacitu v daném kraji". Pomáhá navolávačce vědět, koho zvolit při výhře (rozloží práci).</p>

    <h3 style="margin-top:1rem;color:#7e22ce;">Záložky: K provolání / Callbacky / Navolané / Prohra / ...</h3>
    <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:8px;font-size:0.9rem;">
        <thead style="background:#f3f4f6;">
            <tr><th style="text-align:left;padding:0.5rem 0.8rem;">Záložka</th><th style="text-align:left;padding:0.5rem 0.8rem;">Co tam je</th></tr>
        </thead>
        <tbody>
            <tr><td style="padding:0.4rem 0.8rem;font-weight:600;">K provolání</td><td style="padding:0.4rem 0.8rem;">READY kontakty automaticky zamčené pro tuto navolávačku (10 ks na 10 min)</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;font-weight:600;">📅 Kalendář</td><td style="padding:0.4rem 0.8rem;">Časový přehled callbacků (kdy komu zavolat)</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;font-weight:600;">Callbacky</td><td style="padding:0.4rem 0.8rem;">Kontakty s domluveným zpětným hovorem</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;font-weight:600;">Nedovoláno</td><td style="padding:0.4rem 0.8rem;">Kontakty kde už zkusila 1-2×, čekají na další pokus</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;font-weight:600;">Navolané</td><td style="padding:0.4rem 0.8rem;">Historie — kontakty které úspěšně předala OZ (= výhry)</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;font-weight:600;">Prohra</td><td style="padding:0.4rem 0.8rem;">Historie odmítnutí (NEZAJEM, Bad call)</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;font-weight:600;">🆘 Záchrana</td><td style="padding:0.4rem 0.8rem;">Speciální tab — OZ vrátili kontakt na 2. šanci (14 dní deadline)</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;font-weight:600;">⚠ Chybné od OZ</td><td style="padding:0.4rem 0.8rem;">Reklamace — OZ ti nahlásil že lead byl špatný</td></tr>
        </tbody>
    </table>

    <h2>🔒 Pravidlo zámků (důležité!)</h2>
    <p>Navolávačka <strong>nemůže paralelně držet leady ve více krajích</strong>:</p>
    <ul style="line-height:1.7;">
        <li><strong>Vyber kraj</strong> → systém ti zamkne až 10 READY kontaktů na 10 min</li>
        <li><strong>Sliding window</strong> — každý refresh stránky prodlouží zámek o 10 min</li>
        <li><strong>Když přepneš na jiný kraj</strong> → zámky v původním kraji se okamžitě uvolní</li>
        <li><strong>Bez vybraného kraje</strong> — vidíš jen souhrn, žádné kontakty zamčené</li>
    </ul>

    <h2>🔄 Win flow (výhra)</h2>
    <ol style="line-height:1.7;">
        <li>Klikni <strong>✓ Výhra</strong></li>
        <li>Vyber OZ ze dropdownu (vidíš kdo má volnou kapacitu)</li>
        <li>Napiš poznámku pro OZ (povinné)</li>
        <li>Klikni <strong>✓ Potvrdit výhru</strong></li>
        <li>Lead přechází do CALLED_OK → OZ ho vidí v <code>/oz/queue</code></li>
    </ol>

    <div style="background:#fef3c7;border-left:3px solid #f59e0b;padding:0.7rem 1rem;border-radius:0 5px 5px 0;margin-top:0.6rem;font-size:0.88rem;">
        <strong>🔒 Special:</strong> Pokud je kontakt součástí sázky (žlutý badge <strong>🎯 SÁZKA #N</strong>), OZ je <strong>fixně určen</strong> sázkou. Nemůžeš vybrat — systém už ví.
    </div>

    <h2>📞 Loss flow (prohra)</h2>
    <p>4 možnosti:</p>
    <ul style="line-height:1.7;">
        <li><strong>❌ Nezájem</strong> + důvod (cena / má smlouvu / nezájem / jiné)</li>
        <li><strong>📵 Nedovoláno</strong> — počitátko +1 (po 3× automaticky NEZAJEM)</li>
        <li><strong>⛔ Bad call</strong> — agresivní / sprostý zákazník (= vlastně už ne)</li>
        <li><strong>🚫 Izolace</strong> — explicitně řekl „nikdy nevolejte"</li>
    </ul>

    <h2>📅 Callback</h2>
    <p>Pokud zákazník teď nemá čas, ale chce se mu zavolat později:</p>
    <ol style="line-height:1.7;">
        <li>Klikni <strong>📞 Callback</strong></li>
        <li>Vyber datum a čas</li>
        <li><strong>Privátní</strong> (≤30 dní) — zůstává tobě</li>
        <li><strong>Sdílený</strong> (>30 dní) — uvolní se do poolu, může ho vzít kdokoliv</li>
    </ol>

    <h2>💎 Premium navolávky</h2>
    <p>V sidebar <strong>Premium navolávky</strong> — speciální OZ objednávky s bonusem za úspěšný hovor (extra k standardní sazbě). Viz samostatná nápověda <a href="<?= crm_url('/help/topic?id=premium') ?>">💎 Premium objednávky</a>.</p>
</div>
