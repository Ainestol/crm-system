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

    <div style="background:#fef3c7;border-left:4px solid #f59e0b;padding:0.9rem 1.1rem;border-radius:0 6px 6px 0;margin:1rem 0;">
        <strong>📌 Co to dělá:</strong> Když OZ má kontakt který nezvedá / nereaguje, místo aby ho označil za chybný, pošle ho na <strong>záchranu</strong> — navolávačka má 14 dní zkusit znovu. Pokud uspěje, dostane bonus = 1× hodnota smlouvy.
    </div>

    <h2>🔄 Flow krok za krokem</h2>
    <ol style="line-height:1.7;">
        <li><strong>OZ</strong> v <code>/oz/leads</code> klikne <strong>🆘 Záchrana</strong> u problémového kontaktu</li>
        <li>Vyplní důvod (např. „4× nezvedá telefon") + zvolí komu vrátit (sobě / jinému OZ)</li>
        <li>Lead přejde do stavu <code>RESCUE_REQUESTED</code> a zmizí z OZ pracovní plochy</li>
        <li><strong>Navolávačka</strong> v <code>/caller?tab=rescue</code> vidí všechny pending záchrany s deadline countdown</li>
        <li>Volá → buď <strong>✅ Zachráněno</strong> (+ poznámka pro OZ) nebo <strong>❌ Nezájem/Bad call</strong></li>
        <li>Při úspěchu: lead jde zpět OZ (nebo komu si zvolil) ve stavu CALLED_OK</li>
        <li>OZ pracuje dál a podepíše smlouvu → <strong>bonus_amount = bmsl</strong> se uloží</li>
        <li>Admin v <code>/admin/rescue</code> označí jako <strong>💰 Vyplaceno</strong> až OZ skutečně pošle navolávačce peníze</li>
    </ol>

    <h2>💰 Peníze</h2>
    <ul style="line-height:1.7;">
        <li><strong>Záchrana úspěšná + smlouva podepsána + aktivace služeb</strong> → navolávačka dostane 1× hodnota smlouvy (bmsl)</li>
        <li><strong>Záchrana neúspěšná (nezájem)</strong> → OZ za kontakt nezaplatí ~200 Kč původní navolávačce (clawback)</li>
        <li><strong>Záchrana expirovala (14 dní)</strong> → stejné jako neúspěch, clawback z původní navolávačky</li>
        <li><strong>Záchrana úspěšná, ale smlouva neuzavřena</strong> → bonus zůstává „čeká na podpis", nikdo nedostane nic</li>
    </ul>

    <h2>📍 Kde co najdeš</h2>
    <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:8px;font-size:0.9rem;">
        <thead style="background:#f3f4f6;">
            <tr><th style="text-align:left;padding:0.5rem 0.8rem;">URL</th><th style="text-align:left;padding:0.5rem 0.8rem;">Kdo</th><th style="text-align:left;padding:0.5rem 0.8rem;">Co</th></tr>
        </thead>
        <tbody>
            <tr><td style="padding:0.4rem 0.8rem;"><code>/oz/leads</code></td><td style="padding:0.4rem 0.8rem;">OZ</td><td style="padding:0.4rem 0.8rem;">Tlačítko 🆘 Záchrana u kontaktu</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;"><code>/caller?tab=rescue</code></td><td style="padding:0.4rem 0.8rem;">Navolávačka</td><td style="padding:0.4rem 0.8rem;">Seznam pending záchran k řešení</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;"><code>/admin/rescue</code></td><td style="padding:0.4rem 0.8rem;">Admin</td><td style="padding:0.4rem 0.8rem;">Přehled všech + tlačítko Vyplaceno</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;"><code>/oz</code> (Můj měsíc)</td><td style="padding:0.4rem 0.8rem;">OZ</td><td style="padding:0.4rem 0.8rem;">Sekce „Dlužné navolávačkám za záchrany"</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;"><code>/caller</code></td><td style="padding:0.4rem 0.8rem;">Navolávačka</td><td style="padding:0.4rem 0.8rem;">Widget „Moje peníze — Záchrany earned/paid"</td></tr>
        </tbody>
    </table>
</div>
