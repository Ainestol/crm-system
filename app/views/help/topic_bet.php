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

    <div style="background:#dcfce7;border-left:4px solid #16a34a;padding:0.9rem 1.1rem;border-radius:0 6px 6px 0;margin:1rem 0;">
        <strong>📌 Co to dělá:</strong> Sázka = cílená kampaň. Admin/majitel řekne „chci v Praze vyčistit 300 kontaktů, prvních 100 půjde OZ A (call), dalších 200 OZ B (email)". Čistička dostane tyto kraje prioritně a auto-přiřadí leady chronologicky.
    </div>

    <h2>📋 Postup vytvoření sázky</h2>
    <ol style="line-height:1.7;">
        <li>Jdi na <code>/admin/bet/new</code></li>
        <li>Vyplň: název sázky, kraj, cíl (počet TM+O2)</li>
        <li>Přidej příjemce (OZ) — kdo dostane kolik a jak:
            <ul>
                <li><strong>Call</strong> — kontakt jde standardní cestou přes navolávačku</li>
                <li><strong>Email</strong> — kontakt přeskočí navolávačku, jde rovnou OZ pro email kampaň</li>
            </ul>
        </li>
        <li>Přidej navolávačky které budou pracovat na call-type leadech</li>
        <li>Klikni <strong>✅ Vytvořit sázku</strong></li>
    </ol>

    <h2>🔄 Co se stane v workflow</h2>
    <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:8px;font-size:0.9rem;">
        <thead style="background:#f3f4f6;">
            <tr><th style="text-align:left;padding:0.5rem 0.8rem;">Role</th><th style="text-align:left;padding:0.5rem 0.8rem;">Co vidí / dělá</th></tr>
        </thead>
        <tbody>
            <tr><td style="padding:0.4rem 0.8rem;"><strong>🧹 Čistička</strong></td><td style="padding:0.4rem 0.8rem;">STRICT MODE: vidí jen kraje sázky. Po každém TM/O2 verify se kontakt automaticky zařadí do sázky (chronologicky).</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;"><strong>📞 Navolávačka</strong></td><td style="padding:0.4rem 0.8rem;">V <code>/caller/campaigns</code> vidí přiřazené sázky. Call-type leady má v separátní záložce.</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;"><strong>💼 OZ</strong></td><td style="padding:0.4rem 0.8rem;">Email leady v <code>/oz/email-leads</code> (export do XLSX). Call leady přijdou standardně přes <code>/oz/queue</code>.</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;"><strong>👁 Admin</strong></td><td style="padding:0.4rem 0.8rem;"><code>/admin/bet</code> — přehled, progress per kampaň, možnost zavřít/zrušit.</td></tr>
        </tbody>
    </table>

    <h2>💡 Důležité pravidla</h2>
    <ul style="line-height:1.7;">
        <li><strong>Jeden kraj = jedna sázka</strong> — v Praze nemůžou běžet 2 sázky najednou</li>
        <li><strong>Chronologie</strong>: prvních N leadů jde 1. příjemci, dalších M druhému atd.</li>
        <li><strong>Tvrdě zamčený OZ</strong>: u call-type leadu navolávačka nemůže výhru předat jinému OZ — OZ je fixně určen sázkou</li>
        <li><strong>Auto-close</strong>: sázka se uzavře automaticky po dosažení cíle, nebo manuálně adminem</li>
        <li><strong>Sázkové kontakty jsou vyjmuty z anonymního poolu</strong> — jen vybrané navolávačky je mohou volat</li>
    </ul>
</div>
