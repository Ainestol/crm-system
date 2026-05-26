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

    <div style="background:#fef3c7;border-left:4px solid #f59e0b;padding:0.9rem 1.1rem;border-radius:0 6px 6px 0;margin:1rem 0;">
        <strong>📌 Kdo jsem:</strong> Back-office (BO) je <strong>finální pracovník</strong> v pipeline. Když OZ předá kontakt na BO_PREDANO, BO se postará o všechno administrativní — vystaví smlouvu, pošle datovku, ověří podpis, předá k aktivaci.
    </div>

    <h2>🎯 Cíle BO</h2>
    <ul style="line-height:1.7;">
        <li>Převzít kontakty od OZ ve stavu <strong>BO_PREDANO</strong></li>
        <li>Vystavit a poslat <strong>smlouvu</strong> (datovka, papíry)</li>
        <li>Sledovat <strong>podpis</strong> ze strany zákazníka</li>
        <li>Předat informaci OZ ke schválení podpisu</li>
        <li>Kontakt do stavu <strong>UZAVRENO</strong></li>
    </ul>

    <h2>🖥️ Pracovní obrazovky BO</h2>
    <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:8px;font-size:0.9rem;">
        <thead style="background:#f3f4f6;">
            <tr><th style="text-align:left;padding:0.5rem 0.8rem;">URL</th><th style="text-align:left;padding:0.5rem 0.8rem;">Co tam je</th></tr>
        </thead>
        <tbody>
            <tr><td style="padding:0.4rem 0.8rem;"><code>/backoffice</code></td><td style="padding:0.4rem 0.8rem;">Pracovní plocha — všechny BO_PREDANO + BO_VPRACI kontakty</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;"><code>/backoffice/done</code></td><td style="padding:0.4rem 0.8rem;">Hotové smlouvy (uzavřené)</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;"><code>/backoffice/returned</code></td><td style="padding:0.4rem 0.8rem;">Vrácené BO (= problém, něco chybí, vráceno OZ)</td></tr>
        </tbody>
    </table>

    <h2>🔄 Workflow stavy v BO</h2>
    <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:8px;font-size:0.9rem;">
        <thead style="background:#f3f4f6;">
            <tr><th style="text-align:left;padding:0.5rem 0.8rem;">Stav</th><th style="text-align:left;padding:0.5rem 0.8rem;">Co znamená</th></tr>
        </thead>
        <tbody>
            <tr><td style="padding:0.4rem 0.8rem;font-weight:600;">BO_PREDANO</td><td style="padding:0.4rem 0.8rem;">OZ právě předal, BO se k tomu ještě nedostal</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;font-weight:600;">BO_VPRACI</td><td style="padding:0.4rem 0.8rem;">BO převzal, řeší smlouvu (vystavuje, posílá)</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;font-weight:600;">BO_VRACENO</td><td style="padding:0.4rem 0.8rem;">BO vrátil OZ (např. chybějící údaje, neplatné IČO)</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;font-weight:600;">SMLOUVA</td><td style="padding:0.4rem 0.8rem;">Smlouva vystavena, čeká na podpis zákazníka</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;font-weight:600;">UZAVRENO</td><td style="padding:0.4rem 0.8rem;">Podpis potvrzen, aktivace běží</td></tr>
        </tbody>
    </table>

    <h2>📋 Postup BO (krok za krokem)</h2>
    <ol style="line-height:1.7;">
        <li>BO otevře <code>/backoffice</code></li>
        <li>Vidí seznam kontaktů ve stavu BO_PREDANO (= nové) a BO_VPRACI (= rozpracované)</li>
        <li>Klikne na kontakt → otevře se detail</li>
        <li>Vidí všechny údaje + nabídku OZ + poznámky</li>
        <li>Tlačítka akcí:
            <ul>
                <li><strong>📋 Příprava smlouvy</strong> → BO_VPRACI</li>
                <li><strong>📤 Odeslat datovkou</strong> → flag <code>datovka_odeslana</code></li>
                <li><strong>📄 Smlouva vystavena</strong> → stav SMLOUVA + cislo_smlouvy</li>
                <li><strong>↩ Vrátit OZ</strong> → BO_VRACENO s důvodem</li>
            </ul>
        </li>
        <li>Až zákazník podepíše:
            <ul>
                <li>BO zaškrtne <strong>✓ Podpis potvrzen</strong> v sekci tracking</li>
                <li>OZ to také potvrdí ze své strany</li>
                <li>Když obě strany potvrdily → stav UZAVRENO</li>
            </ul>
        </li>
    </ol>

    <h2>💼 Důležité pole v BO</h2>
    <ul style="line-height:1.7;">
        <li><strong>cislo_smlouvy</strong> — interní ID smlouvy (BO ho generuje / dostává od systému Vodafone)</li>
        <li><strong>datum_uzavreni</strong> — kdy smlouva podepsána</li>
        <li><strong>smlouva_trvani_roky</strong> — default 3 roky</li>
        <li><strong>podpis_potvrzen</strong> — checkbox (0/1) — když je 1, počítá se jako platná uzavřená smlouva</li>
        <li><strong>install_*</strong> — instalační adresa (pokud služby vyžadují instalaci)</li>
    </ul>

    <h2>🆘 Co dělat při problému</h2>
    <details style="margin-bottom:0.5rem;background:#f9fafb;padding:0.6rem 0.9rem;border-radius:5px;cursor:pointer;">
        <summary style="font-weight:600;">Klient odmítl podepsat smlouvu</summary>
        <p style="margin:0.4rem 0 0;font-size:0.88rem;color:#6b7280;">
            Vrať OZ se stavem BO_VRACENO + vysvětli důvod. OZ s tím dál pracuje — buď nabídne jinou variantu nebo uzavře jako NEZAJEM.
        </p>
    </details>
    <details style="margin-bottom:0.5rem;background:#f9fafb;padding:0.6rem 0.9rem;border-radius:5px;cursor:pointer;">
        <summary style="font-weight:600;">Chybí mi BMSL nebo ID nabídky od OZ</summary>
        <p style="margin:0.4rem 0 0;font-size:0.88rem;color:#6b7280;">
            Klikni <strong>↩ Vrátit OZ</strong> s důvodem „Doplň BMSL a ID nabídky". OZ to doplní a předá zpátky.
        </p>
    </details>
    <details style="margin-bottom:0.5rem;background:#f9fafb;padding:0.6rem 0.9rem;border-radius:5px;cursor:pointer;">
        <summary style="font-weight:600;">Zákazník chce změnu po vystavení smlouvy</summary>
        <p style="margin:0.4rem 0 0;font-size:0.88rem;color:#6b7280;">
            Stav vrať zpět na BO_VPRACI, uprav podklady, znovu vystav smlouvu. cislo_smlouvy ponecháváš stejné, jen revize.
        </p>
    </details>
</div>
