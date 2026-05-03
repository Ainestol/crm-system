<?php
// app/views/layout/base.php
declare(strict_types=1);
/** @var string                   $title */
/** @var string                   $content */
/** @var array<string,mixed>|null $user   -- nemusí být nastaven na login stránce */

$_role = (string) ($user['role'] ?? '');
$_currentPath = $_SERVER['REQUEST_URI'] ?? '/';
$_currentPath = (string) parse_url($_currentPath, PHP_URL_PATH);

/**
 * Vrátí ' is-active' třídu pokud aktuální cesta odpovídá linku.
 * Exact match — vyhne se kolizi rodič/dítě (/caller vs /caller/calendar).
 */
$_navActive = static function (string $href) use ($_currentPath): string {
    $normalized = rtrim($_currentPath, '/');
    if ($normalized === '') {
        $normalized = '/';
    }
    return $normalized === $href ? ' is-active' : '';
};

/**
 * Sidebar nav linky podle role. Klíč = sekce, hodnota = pole linků (label, href, icon).
 * @return array<string, list<array{label: string, href: string, icon: string}>>
 */
$_navForRole = static function (string $role) use (&$_navActive): array {
    $sections = [];

    // Společné: Dashboard
    $sections['Hlavní'] = [
        ['label' => 'Dashboard', 'href' => '/dashboard', 'icon' => '🏠'],
    ];

    if ($role === 'navolavacka') {
        $sections['Práce'] = [
            ['label' => 'Pracovní plocha', 'href' => '/caller', 'icon' => '📞'],
            ['label' => 'Kalendář',         'href' => '/caller/calendar', 'icon' => '📅'],
            ['label' => 'Vyhledávání',       'href' => '/caller/search', 'icon' => '🔍'],
            ['label' => 'Statistiky',         'href' => '/caller/stats', 'icon' => '📊'],
        ];
    }
    if ($role === 'cisticka') {
        $sections['Práce'] = [
            ['label' => 'Pracovní plocha', 'href' => '/cisticka', 'icon' => '🔍'],
        ];
    }
    if ($role === 'obchodak') {
        $sections['Práce'] = [
            ['label' => 'Pracovní plocha', 'href' => '/oz/leads', 'icon' => '💼'],
            ['label' => 'Moje kvóty',       'href' => '/oz', 'icon' => '🎯'],
            ['label' => 'Výkon týmu',       'href' => '/oz/performance', 'icon' => '🏆'],
        ];
    }
    if (in_array($role, ['backoffice', 'majitel', 'superadmin'], true)) {
        $sections['Back-office'] = [
            ['label' => 'Pracovní plocha', 'href' => '/bo', 'icon' => '🏢'],
        ];
    }
    if (in_array($role, ['majitel', 'superadmin'], true)) {
        $sections['Administrace'] = [
            ['label' => 'Uživatelé',     'href' => '/admin/users', 'icon' => '👥'],
            ['label' => 'Import CSV',     'href' => '/admin/import', 'icon' => '📥'],
            ['label' => 'Kvóty OZ',       'href' => '/admin/oz-targets', 'icon' => '🎯'],
            ['label' => 'Stage cíle',     'href' => '/admin/oz-stages', 'icon' => '🪜'],
            ['label' => 'Milníky OZ',     'href' => '/admin/oz-milestones', 'icon' => '🏁'],
            ['label' => 'Denní cíle',     'href' => '/admin/daily-goals', 'icon' => '📆'],
            ['label' => 'Stat. navolávaček', 'href' => '/admin/caller-stats', 'icon' => '📞'],
            ['label' => 'Statistiky týmu', 'href' => '/admin/team-stats', 'icon' => '📊'],
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
<div class="layout<?= empty($user) ? ' layout--auth' : '' ?>">

    <?php if (!empty($user)) { ?>
    <!-- ── SIDEBAR ── -->
    <aside class="app-sidebar">
        <a href="<?= crm_h(crm_url('/dashboard')) ?>" class="app-sidebar-brand">
            <span class="app-sidebar-brand-icon">🐌</span>
            <span>CRM</span>
        </a>
        <nav class="app-sidebar-nav">
            <?php foreach ($_navForRole($_role) as $sectionLabel => $links) { ?>
                <div class="app-sidebar-section-label"><?= crm_h($sectionLabel) ?></div>
                <?php foreach ($links as $link) {
                    $activeClass = $_navActive($link['href']);
                ?>
                    <a href="<?= crm_h(crm_url($link['href'])) ?>" class="app-sidebar-link<?= $activeClass ?>">
                        <span class="app-sidebar-icon"><?= crm_h($link['icon']) ?></span>
                        <span><?= crm_h($link['label']) ?></span>
                    </a>
                <?php } ?>
            <?php } ?>
        </nav>
        <div class="app-sidebar-foot">Šneci na tripu 🐌</div>
    </aside>
    <?php } ?>

    <!-- ── TOPBAR ── -->
    <header class="header">
        <div class="header-left">
            <span class="logo">CRM</span>
        </div>

        <?php if (!empty($user)) {
            $_logoutCsrf = crm_csrf_token();
        ?>
        <div class="header-right">
            <span class="header-user">
                <strong><?= crm_h((string) ($user['jmeno'] ?? '')) ?></strong>
            </span>
            <span class="header-role-badge"><?= crm_h($_roleLabel) ?></span>
            <a href="<?= crm_h(crm_url('/account/password')) ?>" class="header-dash-btn" title="Změna hesla">🔑</a>
            <form method="post" action="<?= crm_h(crm_url('/logout')) ?>"
                  class="header-logout-form">
                <input type="hidden"
                       name="<?= crm_h(crm_csrf_field_name()) ?>"
                       value="<?= crm_h($_logoutCsrf) ?>">
                <button type="submit" class="header-logout-btn">Odhlásit</button>
            </form>
        </div>
        <?php } ?>
    </header>

    <!-- ── MAIN CONTENT ── -->
    <main class="main">
        <?= $content ?>
    </main>
</div>
</body>
</html>
