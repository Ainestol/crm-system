<?php
// app/views/layout/base.php
declare(strict_types=1);
/** @var string                   $title */
/** @var string                   $content */
/** @var array<string,mixed>|null $user   -- nemusí být nastaven na login stránce */

$_role = (string) ($user['role'] ?? '');
$_currentPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$_currentPath = rtrim($_currentPath, '/');
if ($_currentPath === '') { $_currentPath = '/'; }

/**
 * Přidá ' active' třídu pokud aktuální cesta odpovídá linku (exact match).
 * Vyhne se kolizi rodič/dítě (např. /caller vs /caller/calendar).
 */
$_navActive = static function (string $href) use ($_currentPath): string {
    return $_currentPath === $href ? ' active' : '';
};

/**
 * Sidebar nav linky podle role.
 * Labely jsou psané "lidsky" — bez zkratek, ať tomu rozumí i kdo nezná interní žargon.
 *
 * @param string $role
 * @param int    $proposalsPending  Počet pending návrhů (pro badge u majitele/superadmina)
 * @return array<string, list<array{label: string, href: string, icon: string}>>
 */
$_navForRole = static function (string $role, int $proposalsPending = 0): array {
    $sections = [];

    // Hlavní sekce: Dashboard + Nový kontakt (všem) + Návrhy ke schválení (majitelé).
    // Vše blízko sebe, ať schvalovatel vidí všechno na očích.
    $sections['Hlavní'] = [
        ['label' => 'Dashboard',       'href' => '/dashboard',     'icon' => '🏠'],
        ['label' => 'Nový kontakt',    'href' => '/contacts/new',  'icon' => '➕'],
    ];
    if (in_array($role, ['majitel', 'superadmin'], true)) {
        $proposalLabelMain = 'Kontakty ke schválení';
        if ($proposalsPending > 0) {
            $proposalLabelMain .= ' (' . $proposalsPending . ')';
        }
        $sections['Hlavní'][] = [
            'label' => $proposalLabelMain,
            'href'  => '/admin/contact-proposals',
            'icon'  => '📋',
        ];
    }

    if ($role === 'navolavacka') {
        // Dvě pracovní plochy:
        //   1) standardní pool (claim, kraje, base reward od majitele)
        //   2) 💎 premium navolávky — objednávky od OZ s bonusem za úspěšný hovor
        $sections['Práce'] = [
            ['label' => 'Pracovní plocha',           'href' => '/caller',           'icon' => '📞'],
            ['label' => 'Kampaně',                   'href' => '/caller/campaigns', 'icon' => '🎯'],
            ['label' => 'Premium navolávky',         'href' => '/caller/premium',   'icon' => '💎'],
            ['label' => 'Kalendář',                  'href' => '/caller/calendar',  'icon' => '📅'],
            ['label' => 'Vyhledávání',               'href' => '/caller/search',    'icon' => '🔍'],
            ['label' => 'Statistiky',                'href' => '/caller/stats',     'icon' => '📊'],
        ];
    }
    if ($role === 'cisticka') {
        // Čistička má dvě pracovní plochy:
        //   1) standardní — první čištění (NEW → READY/VF_SKIP, sazba od majitele)
        //   2) premium    — druhé čištění na objednávku OZ (READY → tradeable/non_tradeable, sazba od OZ)
        $sections['Práce'] = [
            ['label' => 'Pracovní plocha 1 — standard', 'href' => '/cisticka',         'icon' => '🔍'],
            ['label' => 'Pracovní plocha 2 — premium', 'href' => '/cisticka/premium', 'icon' => '💎'],
        ];
    }
    if ($role === 'obchodak') {
        // Veškerá OZ navigace JEN tady (žádné duplicitní tlačítka uvnitř stránek).
        // "Pracovní plocha" je stejný název jako title stránky /oz/leads (OzController::getLeads
        // má $title = 'Pracovní plocha') a konzistentní s navolávačkou / čističkou,
        // které také mají v sidebaru "Pracovní plocha".
        //   "Příchozí leady"   → /oz/queue        (nové leady k akceptaci)
        //   "Pracovní plocha"  → /oz/leads        (rozpracovaná práce ve všech stavech)
        //   "Můj měsíc"        → /oz              (kvóty + payout PDF + souhrn)
        //   "Výkon celého týmu"→ /oz/performance
        $sections['Práce'] = [
            ['label' => 'Příchozí leady',     'href' => '/oz/queue',       'icon' => '📋'],
            ['label' => 'Pracovní plocha',    'href' => '/oz/leads',       'icon' => '💼'],
            ['label' => 'Moje kampaně',       'href' => '/oz/campaigns',   'icon' => '🎯'],
            ['label' => 'Email leady',        'href' => '/oz/email-leads', 'icon' => '📧'],
            ['label' => 'Můj měsíc',          'href' => '/oz',             'icon' => '📅'],
            ['label' => 'Výkon celého týmu',  'href' => '/oz/performance', 'icon' => '🏆'],
        ];
        // ── Premium pipeline (druhé čištění leadů na objednávku) ──
        // "Nová objednávka" jde první v sekci, ať OZ má prominentní akci v menu.
        $sections['Premium 💎'] = [
            ['label' => 'Nová objednávka',  'href' => '/oz/premium/new', 'icon' => '➕'],
            ['label' => 'Moje objednávky',  'href' => '/oz/premium',     'icon' => '💎'],
        ];
    }
    if (in_array($role, ['backoffice', 'majitel', 'superadmin'], true)) {
        $sections['Back-office'] = [
            ['label' => 'Pracovní plocha', 'href' => '/bo', 'icon' => '🏢'],
        ];
    }
    if (in_array($role, ['majitel', 'superadmin'], true)) {
        // ── Premium pipeline (objednávky druhého čištění) — globální přehled ──
        $sections['Premium 💎'] = [
            ['label' => 'Všechny objednávky',  'href' => '/admin/premium-overview', 'icon' => '💎'],
            ['label' => 'Plocha — čistička',   'href' => '/cisticka/premium',       'icon' => '🧹'],
            ['label' => 'Plocha — navolávačka','href' => '/caller/premium',         'icon' => '📞'],
        ];
        // ── Sázky: cílené kampaně (X kontaktů na kraj, rozdělení mezi OZ) ──
        $sections['Sázky 🎯'] = [
            ['label' => 'Všechny sázky',       'href' => '/admin/bet',     'icon' => '🎯'],
            ['label' => 'Nová sázka',          'href' => '/admin/bet/new', 'icon' => '➕'],
        ];
        // ── Záchrany leadů (OZ → caller na druhou šanci) ──
        $sections['Záchrany 🆘'] = [
            ['label' => 'Přehled záchran',     'href' => '/admin/rescue',  'icon' => '🆘'],
        ];
        // ── Recyklace + Mix kontaktů ──
        $sections['Kontakty ♻'] = [
            ['label' => 'Recyklace kontaktů',     'href' => '/admin/contacts/recycle', 'icon' => '♻'],
            ['label' => 'Mix 9× OSVČ + 1× firma', 'href' => '/admin/contacts/mix',     'icon' => '🎲'],
            ['label' => 'Smazat kontakty (filtr)','href' => '/admin/contacts/delete',  'icon' => '🗑'],
        ];
        // ── Sekce sgrupované per role, na kterou nastavení míří ──
        // Logika: když chci nastavit něco pro čističky, jdu do "Čističky".
        // Když pro navolávačky, do "Navolávačky". Žádné hádání, kde co je.
        $sections['Čističky'] = [
            ['label' => 'Cíle a sazba čističky',       'href' => '/admin/cisticka-goals',       'icon' => '🧹'],
        ];
        $sections['Navolávačky'] = [
            ['label' => 'Kvóty navolávaček (per OZ)',  'href' => '/admin/oz-targets',           'icon' => '🎯'],
            ['label' => 'Denní cíle a odměny',          'href' => '/admin/daily-goals',          'icon' => '📆'],
        ];
        $sections['Obchodní zástupci'] = [
            ['label' => 'Týmové cíle OZ',              'href' => '/admin/oz-stages',            'icon' => '🪜'],
            ['label' => 'Osobní cíle OZ',              'href' => '/admin/oz-milestones',        'icon' => '🏁'],
        ];
        // ── Administrace systému: cross-role, nastavení samotného CRM ──
        $sections['Administrace systému'] = [
            ['label' => 'Uživatelé',                    'href' => '/admin/users',                'icon' => '👥'],
            ['label' => 'Import kontaktů',              'href' => '/admin/import',               'icon' => '📥'],
        ];
        // ── Statistiky: přehledy, audity, real-time pohledy do dat ──
        // (caller-stats jsou zahrnuté v team-stats — proto v menu zůstává jen "Statistiky týmu")
        $sections['Statistiky'] = [
            ['label' => 'Live datagrid',     'href' => '/admin/datagrid',    'icon' => '📊'],
            ['label' => 'Activity feed',     'href' => '/admin/feed',        'icon' => '📰'],
            ['label' => 'Audit duplicit',    'href' => '/admin/duplicates',  'icon' => '🕵'],
            ['label' => 'Statistiky týmu',   'href' => '/admin/team-stats',  'icon' => '🏆'],
        ];
    }

    return $sections;
};

$_roleLabels = [
    'navolavacka' => 'Navolávačka',
    'cisticka'    => 'Čistička',
    'obchodak'    => 'Obchodák',
    'backoffice'  => 'Backoffice',
    'majitel'     => 'Majitel',
    'superadmin'  => 'Superadmin',
];
$_roleLabel = $_roleLabels[$_role] ?? $_role;
$_isAuthPage = empty($user);

// Pending count pro badge "Návrhy kontaktů" (jen majitel/superadmin)
// Graceful fallback na 0, pokud crm_pdo() nebo třída není k dispozici.
$_proposalsPending = 0;
if (!$_isAuthPage
    && in_array($_role, ['majitel', 'superadmin'], true)
    && function_exists('crm_pdo')
    && class_exists('ContactProposalsController')) {
    try {
        $_proposalsPending = ContactProposalsController::pendingCount(crm_pdo());
    } catch (\Throwable $e) {
        $_proposalsPending = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= crm_h($title) ?> · CRM</title>
    <link rel="stylesheet" href="<?= crm_h(crm_url('/assets/css/app.css')) ?>">
</head>
<body>
<div class="crm-shell<?= $_isAuthPage ? ' crm-shell--auth' : '' ?>">

    <?php if (!$_isAuthPage) { ?>
    <!-- ── SIDEBAR ── -->
    <aside class="crm-sidebar">
        <div class="crm-sidebar-logo">
            <div class="crm-logo-title">⚙ CLOCKWORK MAN</div>
            <div class="crm-logo-sub">🐌 Šneci na tripu</div>
        </div>
        <nav class="crm-sidebar-nav">
            <?php foreach ($_navForRole($_role, $_proposalsPending) as $sectionLabel => $links) { ?>
                <div class="crm-nav-section"><?= crm_h($sectionLabel) ?></div>
                <?php foreach ($links as $link) { ?>
                    <a href="<?= crm_h(crm_url($link['href'])) ?>" class="crm-nav-item<?= $_navActive($link['href']) ?>">
                        <span><?= crm_h($link['icon']) ?></span>
                        <span><?= crm_h($link['label']) ?></span>
                    </a>
                <?php } ?>
            <?php } ?>
        </nav>
        <!-- Sidebar footer: aktuálně prázdný — user-info je teď v topbaru.
             Místo si necháváme pro budoucí status / verzi appky. -->
    </aside>
    <?php } ?>

    <!-- ── HLAVNÍ OBSAH ── -->
    <div class="crm-main">
        <div class="crm-topbar">
            <div><strong style="font-size: 13px; color: var(--color-text);"><?= crm_h($title) ?></strong></div>

            <?php if (!$_isAuthPage) {
                $_logoutCsrf = crm_csrf_token();
            ?>
            <div class="crm-topbar-right">
                <?php
                // Impersonate indikátor — pokud je admin přepnut do cizího účtu,
                // ukazujeme oranžový widget "← Zpět do admin" s jménem skutečného admina.
                $_imp = isset($_SESSION['impersonator_id']) ? (int) $_SESSION['impersonator_id'] : 0;
                if ($_imp > 0) {
                    $_impName = (string) ($_SESSION['impersonator_name'] ?? 'Admin');
                ?>
                    <a href="<?= crm_h(crm_url('/admin/users/impersonate-stop')) ?>"
                       style="background:linear-gradient(135deg,#fbbf24,#f59e0b);color:#1f2937;
                              padding:0.4rem 0.8rem;border-radius:6px;text-decoration:none;
                              font-weight:700;font-size:0.85rem;display:inline-flex;align-items:center;gap:0.3rem;
                              border:2px solid #f59e0b;animation:impPulse 2s infinite;"
                       title="Jsi přepnut do účtu jiného uživatele — klikni pro návrat do admin">
                        🎭 ← Zpět do admin (<?= crm_h($_impName) ?>)
                    </a>
                    <style>
                        @keyframes impPulse {
                            0%, 100% { box-shadow: 0 0 0 0 rgba(245,158,11,0.5); }
                            50%      { box-shadow: 0 0 0 6px rgba(245,158,11,0); }
                        }
                    </style>
                <?php } ?>
                <!-- User-info: jméno + role pohromadě s akcemi (Heslo / Odhlásit) -->
                <div class="crm-topbar-user" title="<?= crm_h((string) ($user['email'] ?? '')) ?>">
                    <span class="crm-topbar-user__name"><?= crm_h((string) ($user['jmeno'] ?? '')) ?></span>
                    <span class="crm-topbar-user__role"><?= crm_h($_roleLabel) ?></span>
                </div>
                <?php
                // Multi-role tlačítko — vidí jen user co má víc rolí
                $_allRoles = (array) ($user['all_roles'] ?? []);
                if (count($_allRoles) > 1) {
                ?>
                    <a href="<?= crm_h(crm_url('/login/select-role')) ?>"
                       class="btn"
                       style="background:#fff; border:1px solid var(--color-sidebar-accent); color:var(--color-sidebar-accent);"
                       title="Přepnout aktivní roli (máš povoleno víc rolí)">
                        🔄 Přepnout roli
                    </a>
                <?php } ?>
                <?php
                // Nápověda — jen pro admin/majitel/superadmin
                $_userRole = (string) ($user['role'] ?? '');
                $_userExtras = (array) ($user['all_roles'] ?? [$_userRole]);
                $_canHelp = !empty(array_intersect(['majitel', 'superadmin'], $_userExtras));
                if ($_canHelp) {
                ?>
                    <a href="<?= crm_h(crm_url('/help')) ?>" class="btn"
                       title="Nápověda — popis funkcí systému, importy, role, fíčry"
                       style="background:#dbeafe;border:1px solid #93c5fd;color:#1e40af;">
                        ❓ Nápověda
                    </a>
                <?php } ?>
                <a href="<?= crm_h(crm_url('/account/password')) ?>" class="btn" title="Změna hesla">🔑 Heslo</a>

                <?php
                // 2FA tlačítko — pulzuje modře pokud aktivní (= bezpečně), žluté jemně pokud ne
                $_tfaEnabled = (int) ($user['totp_enabled'] ?? 0) === 1;
                if ($_tfaEnabled) {
                ?>
                    <a href="<?= crm_h(crm_url('/profile/2fa/disable')) ?>"
                       class="btn crm-2fa-btn crm-2fa-btn--on"
                       title="Dvoufaktorové ověření je aktivní (klikni pro nastavení)">
                        🔐 2FA
                    </a>
                <?php } else { ?>
                    <a href="<?= crm_h(crm_url('/profile/2fa/setup')) ?>"
                       class="btn crm-2fa-btn crm-2fa-btn--off"
                       title="Doporučujeme zapnout dvoufaktorové ověření pro vyšší bezpečnost">
                        🔓 Zapnout 2FA
                    </a>
                <?php } ?>

                <form method="post" action="<?= crm_h(crm_url('/logout')) ?>" style="margin:0;">
                    <input type="hidden"
                           name="<?= crm_h(crm_csrf_field_name()) ?>"
                           value="<?= crm_h($_logoutCsrf) ?>">
                    <button type="submit" class="btn btn-danger">Odhlásit</button>
                </form>
            </div>
            <?php } ?>
        </div>

        <style>
        /* ── 2FA topbar tlačítko: pulzující modré když aktivní, jemně žluté když ne ── */
        .crm-2fa-btn {
            position: relative;
            border-radius: 6px;
            font-weight: 600;
        }
        .crm-2fa-btn--on {
            background: linear-gradient(135deg, #3d8bfd, #5a9eff);
            color: #fff;
            border: 1px solid rgba(61,139,253,0.6);
            box-shadow: 0 0 0 0 rgba(61,139,253, 0.5);
            animation: crm-2fa-pulse 2.4s ease-in-out infinite;
        }
        .crm-2fa-btn--on:hover {
            background: linear-gradient(135deg, #2e7ee8, #3d8bfd);
            transform: translateY(-1px);
        }
        @keyframes crm-2fa-pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(61,139,253, 0.45); }
            50%      { box-shadow: 0 0 0 6px rgba(61,139,253, 0.0); }
        }
        .crm-2fa-btn--off {
            background: rgba(241,160,48,0.10);
            color: #c47c0a;
            border: 1px dashed rgba(241,160,48,0.5);
        }
        .crm-2fa-btn--off:hover {
            background: rgba(241,160,48,0.18);
            border-style: solid;
        }
        </style>

        <div class="crm-content">
            <?= $content ?>
        </div>
    </div>

</div>

<!-- ════════════════════════════════════════════════════════════════
     CRM MODAL — pěkné custom alert/confirm dialogy
     Globální API: crmAlert(msg, opts), crmConfirm(msg, opts), crmToast(msg, type)
     Voláte odkudkoliv: const ok = await crmConfirm('Opravdu smazat?');
     ════════════════════════════════════════════════════════════════ -->
<div id="crm-modal-overlay" style="display:none;position:fixed;inset:0;
        background:rgba(15,23,42,0.55);backdrop-filter:blur(3px);
        z-index:99999;align-items:center;justify-content:center;
        animation:crmModalFadeIn 0.18s ease-out;">
    <div id="crm-modal-box" style="background:#fff;border-radius:14px;
            box-shadow:0 25px 80px rgba(0,0,0,0.35);min-width:380px;max-width:520px;
            width:90vw;overflow:hidden;transform:scale(0.96);
            animation:crmModalPop 0.22s cubic-bezier(0.18,0.89,0.32,1.28) forwards;">
        <!-- Header s gradientem (typ definuje barvu) -->
        <div id="crm-modal-header" style="padding:1rem 1.4rem;display:flex;align-items:center;gap:0.7rem;
                background:linear-gradient(135deg,#7e22ce,#a855f7);color:#fff;">
            <span id="crm-modal-icon" style="font-size:1.6rem;">🐌</span>
            <strong id="crm-modal-title" style="font-size:1rem;flex:1;">Clockwork Man</strong>
        </div>
        <!-- Body -->
        <div id="crm-modal-body" style="padding:1.4rem;color:#1f2937;font-size:0.95rem;
                line-height:1.55;white-space:pre-line;"></div>
        <!-- Footer s tlačítky -->
        <div id="crm-modal-footer" style="padding:0.85rem 1.4rem;background:#f9fafb;
                border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;
                gap:0.5rem;flex-wrap:wrap;">
            <button id="crm-modal-cancel" type="button" style="background:#fff;color:#374151;
                    border:1px solid #d1d5db;border-radius:6px;padding:0.55rem 1.1rem;
                    cursor:pointer;font-weight:500;">Zrušit</button>
            <button id="crm-modal-ok" type="button" style="background:#7e22ce;color:#fff;
                    border:0;border-radius:6px;padding:0.55rem 1.3rem;
                    cursor:pointer;font-weight:600;">OK</button>
        </div>
    </div>
</div>

<!-- Toast container (pravý dolní roh, fade-out) -->
<div id="crm-toast-container" style="position:fixed;bottom:1.2rem;right:1.2rem;
        z-index:99998;display:flex;flex-direction:column;gap:0.5rem;
        pointer-events:none;"></div>

<style>
@keyframes crmModalFadeIn { from { opacity:0; } to { opacity:1; } }
@keyframes crmModalPop {
    from { transform:scale(0.92); opacity:0; }
    to   { transform:scale(1);    opacity:1; }
}
@keyframes crmToastSlide {
    from { transform:translateX(120%); opacity:0; }
    to   { transform:translateX(0);    opacity:1; }
}
@keyframes crmToastFade {
    to { opacity:0; transform:translateX(40%); }
}
.crm-toast {
    background:#fff;border-radius:8px;padding:0.7rem 1rem;
    box-shadow:0 8px 24px rgba(0,0,0,0.18);
    min-width:240px;max-width:380px;font-size:0.88rem;
    border-left:4px solid #7e22ce;
    pointer-events:auto;display:flex;align-items:center;gap:0.55rem;
    animation:crmToastSlide 0.22s cubic-bezier(0.18,0.89,0.32,1.28);
}
.crm-toast--success { border-left-color:#22c55e; }
.crm-toast--success #crm-modal-icon { color:#22c55e; }
.crm-toast--error   { border-left-color:#ef4444; }
.crm-toast--warn    { border-left-color:#f59e0b; }
.crm-toast--info    { border-left-color:#3b82f6; }
#crm-modal-cancel:hover { background:#f3f4f6; }
#crm-modal-ok:hover { filter:brightness(1.1); }
</style>

<script>
(function() {
    const overlay = document.getElementById('crm-modal-overlay');
    const headerEl = document.getElementById('crm-modal-header');
    const iconEl   = document.getElementById('crm-modal-icon');
    const titleEl  = document.getElementById('crm-modal-title');
    const bodyEl   = document.getElementById('crm-modal-body');
    const okBtn    = document.getElementById('crm-modal-ok');
    const cancelBtn= document.getElementById('crm-modal-cancel');
    let currentResolve = null;

    // Theme presets per typ
    const THEMES = {
        default: { icon:'🐌', title:'Clockwork Man', headerBg:'linear-gradient(135deg,#7e22ce,#a855f7)', okBg:'#7e22ce' },
        info:    { icon:'ℹ️', title:'Informace',     headerBg:'linear-gradient(135deg,#1e40af,#3b82f6)', okBg:'#3b82f6' },
        success: { icon:'✅', title:'Hotovo',        headerBg:'linear-gradient(135deg,#15803d,#22c55e)', okBg:'#22c55e' },
        warn:    { icon:'⚠️', title:'Pozor',         headerBg:'linear-gradient(135deg,#b45309,#f59e0b)', okBg:'#f59e0b' },
        danger:  { icon:'🗑', title:'Nevratná akce', headerBg:'linear-gradient(135deg,#991b1b,#ef4444)', okBg:'#dc2626' },
        confirm: { icon:'❓', title:'Potvrzení',     headerBg:'linear-gradient(135deg,#7e22ce,#a855f7)', okBg:'#7e22ce' },
    };

    function applyTheme(type, customTitle) {
        const t = THEMES[type] || THEMES.default;
        iconEl.textContent  = t.icon;
        titleEl.textContent = customTitle || t.title;
        headerEl.style.background = t.headerBg;
        okBtn.style.background    = t.okBg;
    }

    function openModal(opts) {
        return new Promise(resolve => {
            currentResolve = resolve;
            applyTheme(opts.type || 'default', opts.title);
            bodyEl.textContent = opts.message || '';
            okBtn.textContent     = opts.okText     || 'OK';
            cancelBtn.textContent = opts.cancelText || 'Zrušit';
            cancelBtn.style.display = opts.alert ? 'none' : '';
            overlay.style.display = 'flex';
            setTimeout(() => okBtn.focus(), 50);
        });
    }

    function closeModal(result) {
        overlay.style.display = 'none';
        if (currentResolve) {
            currentResolve(result);
            currentResolve = null;
        }
    }

    okBtn.addEventListener('click',     () => closeModal(true));
    cancelBtn.addEventListener('click', () => closeModal(false));
    overlay.addEventListener('click', e => {
        if (e.target === overlay) closeModal(false);
    });
    document.addEventListener('keydown', e => {
        if (overlay.style.display === 'flex') {
            if (e.key === 'Escape') closeModal(false);
            if (e.key === 'Enter')  closeModal(true);
        }
    });

    // ── Public API ─────────────────────────────────────────────────
    window.crmAlert = function(message, opts) {
        return openModal(Object.assign({ alert:true, type:'info' }, opts || {}, { message }));
    };
    window.crmConfirm = function(message, opts) {
        return openModal(Object.assign({ alert:false, type:'confirm' }, opts || {}, { message }));
    };
    window.crmToast = function(message, type) {
        const c = document.getElementById('crm-toast-container');
        if (!c) return;
        const el = document.createElement('div');
        el.className = 'crm-toast crm-toast--' + (type || 'info');
        const ic = { success:'✅', error:'⚠️', warn:'⚠️', info:'ℹ️' }[type || 'info'];
        el.innerHTML = `<span style="font-size:1.1rem;">${ic}</span><span style="flex:1;">${message}</span>`;
        c.appendChild(el);
        setTimeout(() => {
            el.style.animation = 'crmToastFade 0.3s forwards';
            setTimeout(() => el.remove(), 320);
        }, 3500);
    };
})();
</script>

</body>
</html>
