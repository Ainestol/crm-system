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
 * @return array<string, list<array{label: string, href: string, icon: string}>>
 */
$_navForRole = static function (string $role): array {
    $sections = [];

    $sections['Hlavní'] = [
        ['label' => 'Dashboard', 'href' => '/dashboard', 'icon' => '🏠'],
    ];

    if ($role === 'navolavacka') {
        $sections['Práce'] = [
            ['label' => 'Pracovní plocha', 'href' => '/caller',          'icon' => '📞'],
            ['label' => 'Kalendář',         'href' => '/caller/calendar', 'icon' => '📅'],
            ['label' => 'Vyhledávání',       'href' => '/caller/search',   'icon' => '🔍'],
            ['label' => 'Statistiky',         'href' => '/caller/stats',    'icon' => '📊'],
        ];
    }
    if ($role === 'cisticka') {
        $sections['Práce'] = [
            ['label' => 'Pracovní plocha', 'href' => '/cisticka', 'icon' => '🔍'],
        ];
    }
    if ($role === 'obchodak') {
        $sections['Práce'] = [
            ['label' => 'Pracovní plocha', 'href' => '/oz/leads',       'icon' => '💼'],
            ['label' => 'Moje kvóty',       'href' => '/oz',             'icon' => '🎯'],
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
            ['label' => 'Uživatelé',        'href' => '/admin/users',         'icon' => '👥'],
            ['label' => 'Import CSV',        'href' => '/admin/import',        'icon' => '📥'],
            ['label' => 'Kvóty OZ',          'href' => '/admin/oz-targets',    'icon' => '🎯'],
            ['label' => 'Stage cíle',         'href' => '/admin/oz-stages',     'icon' => '🪜'],
            ['label' => 'Milníky OZ',         'href' => '/admin/oz-milestones', 'icon' => '🏁'],
            ['label' => 'Denní cíle',         'href' => '/admin/daily-goals',   'icon' => '📆'],
            ['label' => 'Stat. navolávaček', 'href' => '/admin/caller-stats',   'icon' => '📞'],
            ['label' => 'Statistiky týmu',    'href' => '/admin/team-stats',    'icon' => '📊'],
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
            <?php foreach ($_navForRole($_role) as $sectionLabel => $links) { ?>
                <div class="crm-nav-section"><?= crm_h($sectionLabel) ?></div>
                <?php foreach ($links as $link) { ?>
                    <a href="<?= crm_h(crm_url($link['href'])) ?>" class="crm-nav-item<?= $_navActive($link['href']) ?>">
                        <span><?= crm_h($link['icon']) ?></span>
                        <span><?= crm_h($link['label']) ?></span>
                    </a>
                <?php } ?>
            <?php } ?>
        </nav>
        <div class="crm-sidebar-footer">
            <div class="crm-user-box">
                <div class="crm-user-name"><?= crm_h((string) ($user['jmeno'] ?? '')) ?></div>
                <div class="crm-user-role"><?= crm_h($_roleLabel) ?></div>
            </div>
        </div>
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
