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

    <div style="background:#dbeafe;border-left:4px solid #2563eb;padding:0.9rem 1.1rem;border-radius:0 6px 6px 0;margin:1rem 0;">
        <strong>📌 Co to dělá:</strong> Vrací staré <strong>VF_SKIP</strong> kontakty (po 2-5 letech když změnili operátora) nebo <strong>NEZAJEM</strong> kontakty (zákazník mohl změnit názor) zpět do oběhu. Použij když máš data ležící bez práce.
    </div>

    <h2>📋 Postup recyklace</h2>
    <ol style="line-height:1.7;">
        <li>Jdi na <code>/admin/contacts/recycle</code></li>
        <li>Filtruj: stav, datum (kdy se s kontaktem naposled pracovalo), kraj, operátor</li>
        <li>Klikni <strong>🔍 Filtrovat</strong> → zobrazí seznam kandidátů</li>
        <li>Vyber checkboxy (nebo Check-all)</li>
        <li>Zvol kam vrátit:
            <ul>
                <li><strong>📋 Auto</strong> — systém se rozhodne podle operatora (VF/empty → čistička, TM/O2 → navolávačka)</li>
                <li><strong>🧹 NEW</strong> — všechny do čističky (re-check operator)</li>
                <li><strong>📞 READY</strong> — všechny do navolávačky (pozor s VF kontakty!)</li>
            </ul>
        </li>
        <li>Volitelná poznámka (uloží se do audit logu)</li>
        <li>Klikni <strong>♻ Vrátit do oběhu</strong></li>
    </ol>

    <h2>🛡️ Bezpečnostní pravidla</h2>
    <ul style="line-height:1.7;">
        <li><strong>IZOLACE nelze recyklovat</strong> — DNC flag = právní problém (GDPR)</li>
        <li><strong>Cool-down 7 dní</strong> — kontakty s <code>updated_at</code> mladší než týden se nerecyklují (chrání proti náhodné recyklaci živé práce)</li>
        <li><strong>Sort na konec fronty</strong> — recyklované kontakty se v čističce/navolávačce objeví <em>na konci</em> (nepředběhnou čerstvé)</li>
        <li><strong>Audit</strong> — každá recyklace se ukládá do <code>contact_recycles</code> (kdo, kdy, z čeho do čeho, proč)</li>
        <li><strong>Recycle counter</strong> — sloupec <code>recycle_count</code> sleduje kolikrát už byl kontakt recyklován</li>
    </ul>

    <h2>💡 Typický use-case</h2>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:1rem;font-size:0.9rem;line-height:1.7;">
        <strong>„Vrátit všechny Vodafone kontakty starší než 2 roky"</strong>
        <ol style="margin:0.5rem 0 0 1.2rem;">
            <li>Stav: zaškrtni <strong>VF_SKIP</strong></li>
            <li>Datum DO: <code><?= date('Y-m-d', strtotime('-2 years')) ?></code></li>
            <li>Filtrovat → uvidíš třeba 1 500 kontaktů</li>
            <li>Check-all → recyklovat jako <strong>🧹 NEW</strong> (čistička re-checkne aktuální operátor)</li>
            <li>Hotovo — kontakty padnou na konec čistící fronty</li>
        </ol>
    </div>
</div>
