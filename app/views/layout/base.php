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
            ['label' => 'Pracovní plocha',           'href' => '/caller',          'icon' => '📞'],
            ['label' => 'Premium navolávky',         'href' => '/caller/premium',  'icon' => '💎'],
            ['label' => 'Kalendář',                  'href' => '/caller/calendar', 'icon' => '📅'],
            ['label' => 'Vyhledávání',               'href' => '/caller/search',   'icon' => '🔍'],
            ['label' => 'Statistiky',                'href' => '/caller/stats',    'icon' => '📊'],
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
            ['label' => 'Můj měsíc',          'href' => '/oz',             'icon' => '🎯'],
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
                <a href="<?= crm_h(crm_url('/account/password')) ?>" class="btn" title="Změna hesla">🔑 Heslo</a>
                <form method="post" action="<?= crm_h(crm_url('/logout')) ?>" style="margin:0;">
                    <input type="hidden"
                           name="<?= crm_h(crm_csrf_field_name()) ?>"
                           value="<?= crm_h($_logoutCsrf) ?>">
                    <button type="submit" class="btn btn-danger">Odhlásit</button>
                </form>
            </div>
            <?php } ?>
        </div>

        <div class="crm-content">
            <?= $content ?>
        </div>
    </div>

</div>
</body>
</html>
