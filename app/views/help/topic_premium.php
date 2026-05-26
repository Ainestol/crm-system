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
        <strong>📌 Co je Premium:</strong> OZ si zaplatí <strong>druhé čištění</strong> už pročištěných leadů + speciální navolávání. Místo standardního flow:
        <br><br>
        <code>NEW → čistička → navolávačka → OZ</code>
        <br><br>
        Premium flow je:
        <br><br>
        <code>READY → premium čistička (druhé sítko) → premium navolávačka (extra bonus) → OZ</code>
    </div>

    <h2>💡 K čemu je Premium</h2>
    <p>Standardní leadové cleaning není dokonalý — některé leady jsou „obchodovatelné" jen teoreticky. OZ chce kvalitnější seznam, je ochotný zaplatit za <strong>druhé sítko</strong> (= jiná čistička detailněji zkoumá) + chce <strong>motivovaného caller</strong> (extra bonus za úspěšný hovor).</p>

    <h2>📋 Postup vytvoření premium objednávky</h2>
    <ol style="line-height:1.7;">
        <li>OZ jde na <code>/oz/premium/new</code></li>
        <li>Vyplní:
            <ul>
                <li><strong>Měsíc</strong> — pro který měsíc to platí (typicky aktuální)</li>
                <li><strong>Počet leadů</strong> — kolik chce dostat (např. 30)</li>
                <li><strong>Kraje</strong> — odkud čerpat (default: všechny)</li>
                <li><strong>Cena za vyčištěný lead</strong> — co OZ zaplatí čističce (např. 2 Kč)</li>
                <li><strong>Caller bonus per lead</strong> — extra bonus pro navolávačku (např. 50 Kč navíc k standardní výhře)</li>
                <li><strong>Preferovaná navolávačka</strong> — konkrétní caller, nebo „rotace" (=kdokoliv)</li>
                <li><strong>Poznámka</strong> — pro čističku/navolávačku co dělá speciálně</li>
            </ul>
        </li>
        <li>Klikne <strong>✓ Vytvořit objednávku</strong></li>
        <li>System automaticky rezervuje 30 READY kontaktů ze systému (z vybraných krajů)</li>
    </ol>

    <h2>🧹 Pohled čističky (`/cisticka/premium`)</h2>
    <p>Premium objednávky se zobrazí v seznamu čističce. Klikne <strong>✓ Přijmu objednávku</strong> a začne pracovat:</p>
    <ul style="line-height:1.7;">
        <li>U každého rezervovaného leadu volí: <strong>✅ Obchodovatelný</strong> nebo <strong>❌ Neobchodovatelný</strong></li>
        <li>Obchodovatelné půjdou navolávačce (= premium queue)</li>
        <li>Neobchodovatelné se OZ zaplatí, ale nebudou volány (= OZ ví, nemarňuje čas)</li>
        <li>Když dokončí všechny → klikne <strong>🏁 Uzavřít objednávku</strong></li>
        <li>Čistička dostává za vyčištěný lead cenu z objednávky (např. 2 Kč × 30 = 60 Kč)</li>
    </ul>

    <h2>📞 Pohled navolávačky (`/caller/premium`)</h2>
    <p>Premium objednávky se zobrazí jen těm navolávačkám které:</p>
    <ul style="line-height:1.7;">
        <li>Jsou <strong>preferovaná navolávačka</strong> v objednávce (= napsal je OZ ručně)</li>
        <li>NEBO objednávka je <strong>na rotaci</strong> = kdokoliv může vzít</li>
    </ul>
    <p>Po úspěšném hovoru (Výhra) navolávačka dostává <strong>standardní sazbu + premium bonus</strong> (extra např. 50 Kč navíc).</p>

    <h2>💼 Pohled OZ (`/oz/premium`)</h2>
    <p>OZ vidí:</p>
    <ul style="line-height:1.7;">
        <li><strong>Aktivní objednávky</strong> — kolik už vyčištěno / kolik zbývá</li>
        <li><strong>Pokrok</strong> — kolik tradeable (obchodovatelných), non_tradeable, pending</li>
        <li><strong>Faktury</strong> — kolik dluží čističce a navolávačce</li>
        <li><strong>Akce</strong> — uzavřít objednávku, zrušit</li>
    </ul>

    <h2>💰 Peněžní toky v Premium</h2>
    <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:8px;font-size:0.9rem;">
        <thead style="background:#f3f4f6;">
            <tr><th style="text-align:left;padding:0.5rem 0.8rem;">Kdo komu</th><th style="text-align:left;padding:0.5rem 0.8rem;">Za co</th><th style="text-align:left;padding:0.5rem 0.8rem;">Default sazba</th></tr>
        </thead>
        <tbody>
            <tr><td style="padding:0.4rem 0.8rem;">OZ → čistička</td><td style="padding:0.4rem 0.8rem;">Vyčištěný lead (tradeable + non_tradeable)</td><td style="padding:0.4rem 0.8rem;">2 Kč / lead</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;">OZ → navolávačka</td><td style="padding:0.4rem 0.8rem;">Úspěšný hovor (kromě standardní sazby od majitele)</td><td style="padding:0.4rem 0.8rem;">50 Kč / výhra</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;">Majitel → navolávačka</td><td style="padding:0.4rem 0.8rem;">Standardní výhra (jako u normálního poolu)</td><td style="padding:0.4rem 0.8rem;">200 Kč / výhra</td></tr>
        </tbody>
    </table>

    <h2>📊 Admin pohled</h2>
    <p>V <code>/admin/premium</code> vidí majitel přehled všech premium objednávek napříč OZ, kolik se zaplatilo / dluží / hotovo.</p>
</div>
