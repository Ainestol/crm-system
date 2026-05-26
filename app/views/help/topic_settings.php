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

    <div style="background:#dbeafe;border-left:4px solid #2563eb;padding:0.9rem 1.1rem;border-radius:0 6px 6px 0;margin:1rem 0;">
        <strong>📌 Přehled:</strong> Tady jsou všechna nastavení systému — cíle pro role, sazby výplat, kvóty pro OZ, mix poměr atd. Změny zde ovlivňují celý workflow.
    </div>

    <h2>🎯 Cíle &amp; sazby per role</h2>

    <h3 style="color:#7e22ce;margin-top:1rem;">🧹 Čističky (`/admin/cisticka-goals`)</h3>
    <p>Nastavení pro role <code>cisticka</code>:</p>
    <ul style="line-height:1.7;">
        <li><strong>Měsíční cíle per kraj</strong> — kolik kontaktů má kraj X vyčistit za měsíc (priorita ⭐1–10)</li>
        <li><strong>Sazba per kontakt</strong> — co dostane čistička za 1 ověřený kontakt (např. 2 Kč)</li>
        <li><strong>Pásmové bonusy</strong> — po splnění cíle +5%, +10%</li>
    </ul>

    <h3 style="color:#7e22ce;margin-top:1rem;">📞 Navolávačky</h3>
    <p>Per OZ kvóty (`/admin/caller-stats` nebo per OZ tile):</p>
    <ul style="line-height:1.7;">
        <li><strong>Kolik leadů má dostat OZ</strong> v každém kraji</li>
        <li><strong>Sazba per výhra</strong> (caller_rewards_config) — default 200 Kč/CALLED_OK</li>
        <li><strong>Šněčí závody</strong> — měsíční cíl výher, pásmové bonusy</li>
    </ul>

    <h3 style="color:#7e22ce;margin-top:1rem;">💼 Obchodní zástupci (`/admin/oz-targets`)</h3>
    <p>Per OZ:</p>
    <ul style="line-height:1.7;">
        <li><strong>Týmové cíle</strong> — kolik smluv má kraj uzavřít</li>
        <li><strong>Osobní milníky</strong> — pro každého OZ specifické bonusy (např. „za 10 smluv +1000 Kč")</li>
        <li><strong>Etapy postupu</strong> — definice workflow fází</li>
    </ul>

    <h2>♻ Kontakty &amp; mix (`/admin/contacts/mix`)</h2>
    <ul style="line-height:1.7;">
        <li><strong>Mix poměr</strong> — default 9× OSVČ + 1× firma per cyklus. Změna přes ⚙️ Nastavení mixu.</li>
        <li><strong>🤖 Auto-mix po importu</strong> — checkbox. Default ZAPNUTO.</li>
        <li><strong>♻ Recyklace</strong> — `/admin/contacts/recycle` — manuální navrácení starých kontaktů</li>
    </ul>

    <h2>🌍 Kraje a regiony</h2>
    <p>Seznam krajů je hardcoded v <code>app/helpers/region.php</code> (= 14 krajů ČR + případně oddělené části jako Praha 1-10). Standardní mapování probíhá automaticky podle obce / okresu při importu.</p>

    <h2>👥 Uživatelé (`/admin/users`)</h2>
    <p>Viz samostatný topic <a href="<?= crm_url('/help/topic?id=users') ?>">👥 Správa uživatelů</a>.</p>

    <h2>📊 Statistiky a reporty</h2>
    <ul style="line-height:1.7;">
        <li><strong>/admin/team-stats</strong> — výkon celého týmu</li>
        <li><strong>/admin/caller-stats</strong> — detail navolávaček</li>
        <li><strong>/admin/premium</strong> — premium objednávky</li>
        <li><strong>/admin/rescue</strong> — záchrany</li>
        <li><strong>/admin/bet</strong> — sázky</li>
    </ul>

    <h2>🔐 Bezpečnost</h2>
    <ul style="line-height:1.7;">
        <li><strong>2FA</strong> — uživatel si zapne v <code>/profile/2fa/setup</code> (TOTP přes Google Authenticator)</li>
        <li><strong>Trusted devices</strong> — 30denní cookie pro auto-login po 2FA</li>
        <li><strong>Custom heslo</strong> — admin může nastavit konkrétní heslo (viz <a href="<?= crm_url('/help/topic?id=users') ?>">Správa uživatelů</a>)</li>
        <li><strong>Audit log</strong> — všechny důležité akce (impersonate, reset hesla, smazání) jsou v DB tabulce <code>audit_log</code></li>
    </ul>

    <h2>🗄️ Databázová struktura — hlavní tabulky</h2>
    <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:8px;font-size:0.85rem;">
        <thead style="background:#f3f4f6;">
            <tr><th style="text-align:left;padding:0.4rem 0.7rem;">Tabulka</th><th style="text-align:left;padding:0.4rem 0.7rem;">Co obsahuje</th></tr>
        </thead>
        <tbody>
            <tr><td style="padding:0.3rem 0.7rem;font-family:monospace;">contacts</td><td style="padding:0.3rem 0.7rem;">Hlavní tabulka kontaktů — firma, ico, telefon, stav, operator</td></tr>
            <tr><td style="padding:0.3rem 0.7rem;font-family:monospace;">users</td><td style="padding:0.3rem 0.7rem;">Uživatelé + role + roles_extra</td></tr>
            <tr><td style="padding:0.3rem 0.7rem;font-family:monospace;">oz_contact_workflow</td><td style="padding:0.3rem 0.7rem;">Workflow stavy pro OZ pracovní plochu</td></tr>
            <tr><td style="padding:0.3rem 0.7rem;font-family:monospace;">workflow_log</td><td style="padding:0.3rem 0.7rem;">Audit log všech přechodů stavů</td></tr>
            <tr><td style="padding:0.3rem 0.7rem;font-family:monospace;">bet_campaigns / _recipients / _leads</td><td style="padding:0.3rem 0.7rem;">Sázky a jejich příjemci/kontakty</td></tr>
            <tr><td style="padding:0.3rem 0.7rem;font-family:monospace;">rescue_requests</td><td style="padding:0.3rem 0.7rem;">Záchrany leadů</td></tr>
            <tr><td style="padding:0.3rem 0.7rem;font-family:monospace;">premium_orders / _lead_pool</td><td style="padding:0.3rem 0.7rem;">Premium objednávky</td></tr>
            <tr><td style="padding:0.3rem 0.7rem;font-family:monospace;">app_settings</td><td style="padding:0.3rem 0.7rem;">Konfigurace (mix poměr, auto-mix)</td></tr>
            <tr><td style="padding:0.3rem 0.7rem;font-family:monospace;">dnc_list</td><td style="padding:0.3rem 0.7rem;">Do not call list — kontakty se blokují při importu</td></tr>
        </tbody>
    </table>
</div>
