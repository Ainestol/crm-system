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
/**
 * Nová navigace — hierarchická, accordion-friendly.
 *
 * Datová struktura:
 *   [
 *     ['key' => 'unique', 'icon' => '📊', 'label' => 'Top', 'href' => '/path' | null,
 *      'children' => [ ['label' => 'Sub', 'href' => '/sub', 'icon' => '...'], ... ] | []],
 *     ...
 *   ]
 *
 * Pravidlo: pokud má položka children, je to expandable (accordion). Pokud href + žádné
 * children, je to přímý odkaz.
 *
 * @return list<array{key:string, icon:string, label:string, href:?string, children:list<array{label:string, href:string, icon:string}>}>
 */
$_navForRole = static function (string $role, int $proposalsPending = 0): array {
    $nav = [];

    // ── Dashboard / Dnes — vždy první, pro každou roli ──
    $nav[] = ['key' => 'dnes', 'icon' => '📊',
              'label' => $role === 'majitel' || $role === 'superadmin' ? 'Přehled firmy' : 'Dnes',
              'href' => '/dashboard', 'children' => []];

    if ($role === 'cisticka') {
        $nav[] = ['key' => 'plochy', 'icon' => '🧹', 'label' => 'Pracovní plochy', 'href' => null, 'children' => [
            ['label' => 'Standardní čištění', 'href' => '/cisticka',         'icon' => '🔍'],
            ['label' => 'Premium čištění',    'href' => '/cisticka/premium', 'icon' => '💎'],
        ]];
    }

    if ($role === 'navolavacka') {
        $nav[] = ['key' => 'hovory', 'icon' => '📞', 'label' => 'Hovory', 'href' => null, 'children' => [
            ['label' => 'Standardní pool',   'href' => '/caller',           'icon' => '📞'],
            ['label' => 'Premium navolávky', 'href' => '/caller/premium',   'icon' => '💎'],
            ['label' => 'Sázky (kampaně)',   'href' => '/caller/campaigns', 'icon' => '🎯'],
        ]];
        $nav[] = ['key' => 'kalendar', 'icon' => '📅', 'label' => 'Kalendář', 'href' => '/caller/calendar', 'children' => []];
        $nav[] = ['key' => 'hledat',   'icon' => '🔍', 'label' => 'Vyhledat kontakt', 'href' => '/caller/search', 'children' => []];
        $nav[] = ['key' => 'vydelek',  'icon' => '💰', 'label' => 'Můj výdělek', 'href' => '/caller/stats', 'children' => []];
    }

    if ($role === 'obchodak') {
        $nav[] = ['key' => 'leady', 'icon' => '💼', 'label' => 'Leady', 'href' => null, 'children' => [
            ['label' => 'Příchozí',         'href' => '/oz/queue',       'icon' => '📋'],
            ['label' => 'Pracovní plocha',  'href' => '/oz/leads',       'icon' => '💼'],
            ['label' => 'Email leady',      'href' => '/oz/email-leads', 'icon' => '📧'],
            ['label' => 'Moje kampaně',     'href' => '/oz/campaigns',   'icon' => '🎯'],
        ]];
        $nav[] = ['key' => 'hledat', 'icon' => '🔍', 'label' => 'Vyhledat klienta', 'href' => '/oz/search', 'children' => []];
        $nav[] = ['key' => 'premium', 'icon' => '💎', 'label' => 'Premium', 'href' => null, 'children' => [
            ['label' => 'Nová objednávka',  'href' => '/oz/premium/new', 'icon' => '➕'],
            ['label' => 'Moje objednávky',  'href' => '/oz/premium',     'icon' => '💎'],
        ]];
        $nav[] = ['key' => 'muj_mesic', 'icon' => '📅', 'label' => 'Můj měsíc', 'href' => '/oz', 'children' => []];
        $nav[] = ['key' => 'vykon_tym', 'icon' => '🏆', 'label' => 'Výkon týmu', 'href' => '/oz/performance', 'children' => []];
    }

    if ($role === 'backoffice') {
        $nav[] = ['key' => 'bo', 'icon' => '🏢', 'label' => 'Pracovní plocha', 'href' => '/bo', 'children' => []];
    }

    if (in_array($role, ['majitel', 'superadmin'], true)) {
        $kontakty = [
            ['label' => 'Přidané týmem',         'href' => '/admin/contacts/added',   'icon' => '👥'],
            ['label' => 'Recyklace',             'href' => '/admin/contacts/recycle', 'icon' => '♻'],
            ['label' => 'Mix (firma/OSVČ)',      'href' => '/admin/contacts/mix',     'icon' => '🎲'],
            ['label' => 'Smazat (filtr)',        'href' => '/admin/contacts/delete',  'icon' => '🗑'],
            ['label' => 'Audit duplicit',        'href' => '/admin/duplicates',       'icon' => '🕵'],
            ['label' => 'Import CSV',            'href' => '/admin/import',           'icon' => '📥'],
        ];
        if ($proposalsPending > 0) {
            array_unshift($kontakty, [
                'label' => 'Návrhy ke schválení (' . $proposalsPending . ')',
                'href'  => '/admin/contact-proposals', 'icon' => '⏳',
            ]);
        }
        $nav[] = ['key' => 'kontakty', 'icon' => '👥', 'label' => 'Kontakty', 'href' => null, 'children' => $kontakty];

        $nav[] = ['key' => 'tym', 'icon' => '🧑‍💼', 'label' => 'Tým', 'href' => null, 'children' => [
            ['label' => 'Uživatelé',          'href' => '/admin/users',            'icon' => '👥'],
            ['label' => 'Statistiky výkonu',  'href' => '/admin/team-stats',       'icon' => '🏆'],
            ['label' => 'Bodování aktivit',   'href' => '/admin/activity-scoring', 'icon' => '⚙️'],
            ['label' => 'Live datagrid',      'href' => '/admin/datagrid',         'icon' => '📋'],
            ['label' => 'Activity feed',      'href' => '/admin/feed',             'icon' => '📰'],
        ]];

        $nav[] = ['key' => 'cile', 'icon' => '🎯', 'label' => 'Cíle a sazby', 'href' => null, 'children' => [
            ['label' => 'Denní cíle navolávačky', 'href' => '/admin/daily-goals',     'icon' => '📆'],
            ['label' => 'Kvóty navolávaček/OZ',   'href' => '/admin/oz-targets',      'icon' => '🎯'],
            ['label' => 'Cíle čističky',          'href' => '/admin/cisticka-goals',  'icon' => '🧹'],
            ['label' => 'Týmové cíle OZ',         'href' => '/admin/oz-stages',       'icon' => '🪜'],
            ['label' => 'Osobní cíle OZ',         'href' => '/admin/oz-milestones',   'icon' => '🏁'],
        ]];

        $nav[] = ['key' => 'premium', 'icon' => '💎', 'label' => 'Premium pipeline', 'href' => null, 'children' => [
            ['label' => 'Přehled objednávek',     'href' => '/admin/premium-overview', 'icon' => '💎'],
            ['label' => 'Plocha čistička',        'href' => '/cisticka/premium',       'icon' => '🧹'],
            ['label' => 'Plocha navolávačka',     'href' => '/caller/premium',         'icon' => '📞'],
        ]];

        $nav[] = ['key' => 'sazky', 'icon' => '🎯', 'label' => 'Sázky', 'href' => null, 'children' => [
            ['label' => 'Všechny sázky', 'href' => '/admin/bet',     'icon' => '🎯'],
            ['label' => 'Nová sázka',    'href' => '/admin/bet/new', 'icon' => '➕'],
        ]];

        $nav[] = ['key' => 'zachrany', 'icon' => '🆘', 'label' => 'Záchrany leadů', 'href' => '/admin/rescue', 'children' => []];
        $nav[] = ['key' => 'backoffice','icon' => '🏢', 'label' => 'Backoffice', 'href' => '/bo', 'children' => []];

        // Super-admin extra sekce
        if (function_exists('crm_tenant_is_super_admin') && crm_tenant_is_super_admin()) {
            $nav[] = ['key' => 'saas', 'icon' => '🏢', 'label' => 'SaaS správa', 'href' => null, 'children' => [
                ['label' => 'Firmy (tenants)', 'href' => '/admin/tenants', 'icon' => '🏢'],
                ['label' => 'Debug tenant',    'href' => '/debug/tenant',  'icon' => '🔍'],
            ]];
        }
    }

    // ── Společné dole pro všechny role (mimo BO které má jen pracovní plochu) ──
    $nav[] = ['key' => 'novy_kontakt',    'icon' => '➕', 'label' => 'Nový kontakt',    'href' => '/contacts/new',      'children' => []];
    $nav[] = ['key' => 'doporucenky',     'icon' => '📋', 'label' => 'Moje doporučenky','href' => '/me/added-contacts', 'children' => []];
    // Nápověda — pro VŠECHNY role. Každá uvidí jen návod relevantní pro svou práci.
    $nav[] = ['key' => 'napoveda', 'icon' => '❓', 'label' => 'Nápověda', 'href' => '/help', 'children' => []];

    return $nav;
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
    <!-- Theme init — běží před načtením CSS aby nedošlo k FOUC -->
    <script>
    (function () {
        try {
            var t = localStorage.getItem('crm_theme');
            if (t) document.documentElement.setAttribute('data-theme', t);
        } catch (e) {}
    })();
    </script>
    <link rel="stylesheet" href="<?= crm_h(crm_url('/assets/css/app.css')) ?>">

    <!-- Theme tokens — 5 motivů přes CSS custom properties (sidebar + obsah) -->
    <style>
    :root {
        /* Default: Light */
        --sb-bg: #ffffff;
        --sb-border: #e5e7eb;
        --sb-text: #374151;
        --sb-text-muted: #9ca3af;
        --sb-hover: #f9fafb;
        --sb-active-bg: #eff6ff;
        --sb-active-border: #2563eb;
        --sb-active-text: #1d4ed8;
        --sb-group-bg: #f9fafb;
        /* Content area */
        --content-bg: #f9fafb;
        --card-bg: #ffffff;
        --card-border: #e5e7eb;
        --text-primary: #111827;
        --text-secondary: #6b7280;
        --topbar-bg: #ffffff;
        --topbar-border: #e5e7eb;
        --logo-grad-from: #2563eb;
        --logo-grad-to: #7c3aed;
    }
    [data-theme="ocean"] {
        --sb-bg: #f0f9ff;
        --sb-border: #bae6fd;
        --sb-text: #0c4a6e;
        --sb-text-muted: #0369a1;
        --sb-hover: #e0f2fe;
        --sb-active-bg: #bae6fd;
        --sb-active-border: #0284c7;
        --sb-active-text: #0c4a6e;
        --sb-group-bg: #e0f2fe;
        --content-bg: #ecfeff;
        --card-bg: #ffffff;
        --card-border: #bae6fd;
        --text-primary: #0c4a6e;
        --text-secondary: #0369a1;
        --topbar-bg: #f0f9ff;
        --topbar-border: #bae6fd;
        --logo-grad-from: #0ea5e9;
        --logo-grad-to: #0284c7;
    }
    [data-theme="forest"] {
        --sb-bg: #f0fdf4;
        --sb-border: #bbf7d0;
        --sb-text: #14532d;
        --sb-text-muted: #15803d;
        --sb-hover: #dcfce7;
        --sb-active-bg: #bbf7d0;
        --sb-active-border: #16a34a;
        --sb-active-text: #14532d;
        --sb-group-bg: #dcfce7;
        --content-bg: #f7fee7;
        --card-bg: #ffffff;
        --card-border: #bbf7d0;
        --text-primary: #14532d;
        --text-secondary: #15803d;
        --topbar-bg: #f0fdf4;
        --topbar-border: #bbf7d0;
        --logo-grad-from: #22c55e;
        --logo-grad-to: #16a34a;
    }
    [data-theme="sunset"] {
        --sb-bg: #fff7ed;
        --sb-border: #fed7aa;
        --sb-text: #7c2d12;
        --sb-text-muted: #c2410c;
        --sb-hover: #ffedd5;
        --sb-active-bg: #fed7aa;
        --sb-active-border: #ea580c;
        --sb-active-text: #7c2d12;
        --sb-group-bg: #ffedd5;
        --content-bg: #fffbeb;
        --card-bg: #ffffff;
        --card-border: #fed7aa;
        --text-primary: #7c2d12;
        --text-secondary: #c2410c;
        --topbar-bg: #fff7ed;
        --topbar-border: #fed7aa;
        --logo-grad-from: #f97316;
        --logo-grad-to: #db2777;
    }
    [data-theme="dark"] {
        --sb-bg: #1f2937;
        --sb-border: #374151;
        --sb-text: #e5e7eb;
        --sb-text-muted: #9ca3af;
        --sb-hover: #374151;
        --sb-active-bg: #1e3a8a;
        --sb-active-border: #60a5fa;
        --sb-active-text: #bfdbfe;
        --sb-group-bg: #111827;
        --content-bg: #0f172a;
        --card-bg: #1e293b;
        --card-border: #334155;
        --text-primary: #f1f5f9;
        --text-secondary: #cbd5e1;
        --topbar-bg: #1e293b;
        --topbar-border: #334155;
        --logo-grad-from: #60a5fa;
        --logo-grad-to: #a78bfa;
    }
    [data-theme="lavender"] {
        --sb-bg: #faf5ff;
        --sb-border: #ddd6fe;
        --sb-text: #4c1d95;
        --sb-text-muted: #7c3aed;
        --sb-hover: #f3e8ff;
        --sb-active-bg: #ddd6fe;
        --sb-active-border: #7c3aed;
        --sb-active-text: #4c1d95;
        --sb-group-bg: #f3e8ff;
        --content-bg: #fbf8ff;
        --card-bg: #ffffff;
        --card-border: #ddd6fe;
        --text-primary: #4c1d95;
        --text-secondary: #7c3aed;
        --topbar-bg: #faf5ff;
        --topbar-border: #ddd6fe;
        --logo-grad-from: #a78bfa;
        --logo-grad-to: #ec4899;
    }
    [data-theme="slate"] {
        --sb-bg: #f8fafc;
        --sb-border: #cbd5e1;
        --sb-text: #334155;
        --sb-text-muted: #64748b;
        --sb-hover: #f1f5f9;
        --sb-active-bg: #e2e8f0;
        --sb-active-border: #475569;
        --sb-active-text: #0f172a;
        --sb-group-bg: #f1f5f9;
        --content-bg: #f8fafc;
        --card-bg: #ffffff;
        --card-border: #cbd5e1;
        --text-primary: #0f172a;
        --text-secondary: #475569;
        --topbar-bg: #f8fafc;
        --topbar-border: #cbd5e1;
        --logo-grad-from: #475569;
        --logo-grad-to: #0f172a;
    }
    [data-theme="crimson"] {
        --sb-bg: #fff1f2;
        --sb-border: #fecaca;
        --sb-text: #7f1d1d;
        --sb-text-muted: #b91c1c;
        --sb-hover: #ffe4e6;
        --sb-active-bg: #fecaca;
        --sb-active-border: #dc2626;
        --sb-active-text: #7f1d1d;
        --sb-group-bg: #ffe4e6;
        --content-bg: #fff5f5;
        --card-bg: #ffffff;
        --card-border: #fecaca;
        --text-primary: #7f1d1d;
        --text-secondary: #b91c1c;
        --topbar-bg: #fff1f2;
        --topbar-border: #fecaca;
        --logo-grad-from: #ef4444;
        --logo-grad-to: #be185d;
    }
    /* ── Apply theme tokens to global layout ── */
    body, .crm-main, .crm-content { background: var(--content-bg) !important; transition: background 200ms; }
    .crm-topbar {
        background: var(--topbar-bg) !important;
        border-bottom: 1px solid var(--topbar-border) !important;
    }
    /* Universal card override (mnoho stránek používá tyhle třídy) */
    .od-card, .od-stat, .te-wrap .card, .tenant-card, .rd-stat, .rd-quick-actions,
    .dbg-card, .as-card, .od-row .od-card {
        background: var(--card-bg) !important;
        border-color: var(--card-border) !important;
        color: var(--text-primary);
    }
    /* Dark theme specifické úpravy (světlý text na tmavém pozadí) */
    [data-theme="dark"] .crm-sidebar-head { background: #111827 !important; border-bottom-color: #374151 !important; }
    [data-theme="dark"] .crm-logo-title { color: #f3f4f6 !important; }
    [data-theme="dark"] .crm-logo-sub { color: #9ca3af !important; }
    [data-theme="dark"] .crm-sb-collapse { border-color: #374151; color: #9ca3af; }
    [data-theme="dark"] .crm-sb-collapse:hover { background: #374151; color: #f3f4f6; }
    [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3,
    [data-theme="dark"] .od-stat .value, [data-theme="dark"] .rd-stat-value,
    [data-theme="dark"] strong { color: var(--text-primary) !important; }
    [data-theme="dark"] .od-stat .label, [data-theme="dark"] .rd-stat-label,
    [data-theme="dark"] .rd-stat-sub, [data-theme="dark"] .sub,
    [data-theme="dark"] .od-stat .delta.flat { color: var(--text-secondary) !important; }
    [data-theme="dark"] .crm-topbar-user__name { color: var(--text-primary) !important; }
    [data-theme="dark"] .crm-topbar-user__role { color: var(--text-secondary) !important; }
    [data-theme="dark"] .dh-header { background: linear-gradient(135deg, #1e293b 0%, #312e81 100%) !important; border-color: #334155 !important; }
    [data-theme="dark"] .dh-greeting { color: #bfdbfe !important; }
    [data-theme="dark"] .dh-quote, [data-theme="dark"] .dh-date, [data-theme="dark"] .dh-quote-author { color: #cbd5e1 !important; }
    [data-theme="dark"] .users-table th { background: #0f172a !important; color: #cbd5e1 !important; }
    [data-theme="dark"] .users-table td { border-bottom-color: #334155 !important; }
    [data-theme="dark"] .users-table tbody tr:hover { background: #0f172a !important; }
    [data-theme="dark"] table.dbg-table th { background: #0f172a !important; color: #cbd5e1 !important; }
    [data-theme="dark"] table.dbg-table td { border-bottom-color: #334155 !important; color: var(--text-primary); }
    [data-theme="dark"] .kv .k { color: var(--text-secondary) !important; }
    [data-theme="dark"] .kv .v { color: var(--text-primary) !important; }
    [data-theme="dark"] .kv .v code { background: #0f172a !important; color: #f1f5f9 !important; }
    [data-theme="dark"] code { background: #0f172a !important; color: #f1f5f9 !important; }
    [data-theme="dark"] .rd-quick-btn { background: #334155 !important; color: #f1f5f9 !important; }
    [data-theme="dark"] .rd-quick-btn:hover { background: #475569 !important; }

    /* Logo image varianta (per-tenant nahrané) */
    .crm-logo-img {
        display: inline-flex; align-items: center; justify-content: center;
        width: 34px; height: 34px; border-radius: 8px;
        background: #fff; border: 1px solid var(--sb-border);
        overflow: hidden; flex-shrink: 0;
    }
    .crm-logo-img img { max-width: 30px; max-height: 30px; object-fit: contain; }

    /* Theme switcher widget v topbaru — sám se barví podle motivu */
    .theme-switcher { position: relative; }
    .theme-switcher-btn {
        background: var(--card-bg, #ffffff) !important;
        border: 1px solid var(--sb-border, #e5e7eb) !important;
        border-radius: 8px;
        height: 34px; padding: 0 .7rem; cursor: pointer;
        display: inline-flex; align-items: center; gap: .4rem;
        font-size: .85rem; font-weight: 600;
        color: var(--text-primary, #111827) !important;
        transition: background 120ms, border-color 120ms, transform 120ms;
        position: relative;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    .theme-switcher-btn:hover {
        background: var(--sb-hover, #f9fafb) !important;
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(0,0,0,.08);
    }
    .theme-btn-icon { font-size: 1rem; line-height: 1; }
    .theme-btn-label {
        color: var(--text-primary, #111827) !important;
        white-space: nowrap;
    }
    /* Malý kulatý indikátor s aktuálním motivem (vpravo nahoře nad tlačítkem)
       — solidní barva přesně té samé barvy, kterou má puntík v pickeru. */
    .theme-switcher-btn .swatch {
        position: absolute; top: -4px; right: -4px;
        width: 14px; height: 14px; border-radius: 50%;
        background: var(--theme-dot-color, #ffffff);
        border: 1px solid var(--theme-dot-border, transparent);
        box-shadow: 0 0 0 2px var(--topbar-bg, #ffffff);
        transition: all 180ms;
    }
    /* Solid barva puntíku per motiv */
    :root                   { --theme-dot-color: #ffffff;  --theme-dot-border: #9ca3af; }
    [data-theme="ocean"]    { --theme-dot-color: #0284c7;  --theme-dot-border: transparent; }
    [data-theme="forest"]   { --theme-dot-color: #16a34a;  --theme-dot-border: transparent; }
    [data-theme="sunset"]   { --theme-dot-color: #ea580c;  --theme-dot-border: transparent; }
    [data-theme="lavender"] { --theme-dot-color: #7c3aed;  --theme-dot-border: transparent; }
    [data-theme="crimson"]  { --theme-dot-color: #dc2626;  --theme-dot-border: transparent; }
    [data-theme="slate"]    { --theme-dot-color: #475569;  --theme-dot-border: transparent; }
    [data-theme="dark"]     { --theme-dot-color: #111827;  --theme-dot-border: #475569; }
    /* Na užším displeji jen ikona, text se schová */
    @media (max-width: 1100px) {
        .theme-btn-label { display: none; }
        .theme-switcher-btn { padding: 0 .5rem; }
    }

    /* User info widget v topbaru — taky themable */
    .crm-topbar-user {
        background: var(--card-bg, #ffffff) !important;
        border: 1px solid var(--sb-border, #e5e7eb) !important;
        border-radius: 8px !important;
    }
    .crm-topbar-user__name {
        color: var(--text-primary, #111827) !important;
    }
    .crm-topbar-user__role {
        color: var(--text-secondary, #6b7280) !important;
    }
    .theme-popover {
        position: absolute; top: calc(100% + 6px); right: 0;
        background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
        padding: .7rem; box-shadow: 0 8px 24px rgba(0,0,0,.12);
        display: none; z-index: 100; min-width: 260px;
    }
    [data-theme="dark"] .theme-popover { background: #1e293b; border-color: #334155; }
    [data-theme="dark"] .theme-popover h4 { color: #cbd5e1; }
    [data-theme="dark"] .theme-popover .opt .name { color: #cbd5e1; }
    [data-theme="dark"] .theme-switcher-btn { background: #1e293b; border-color: #334155; }
    .theme-switcher.open .theme-popover { display: block; }
    .theme-popover h4 {
        margin: 0 0 .5rem; font-size: .72rem; color: #6b7280;
        text-transform: uppercase; letter-spacing: .06em; font-weight: 700;
    }
    .theme-popover .options { display: grid; grid-template-columns: repeat(4, 1fr); gap: .4rem; }
    .theme-popover .opt {
        cursor: pointer; padding: .25rem; border-radius: 6px; border: 2px solid transparent;
        transition: border-color 120ms, transform 120ms;
        display: flex; flex-direction: column; align-items: center; gap: .25rem;
    }
    .theme-popover .opt:hover { transform: translateY(-1px); }
    .theme-popover .opt.is-active { border-color: #2563eb; }
    .theme-popover .opt .preview {
        width: 28px; height: 28px; border-radius: 50%;
        border: 1px solid rgba(0,0,0,.08);
    }
    .theme-popover .opt .name { font-size: .7rem; color: #374151; }
    </style>
</head>
<body>
<div class="crm-shell<?= $_isAuthPage ? ' crm-shell--auth' : '' ?>">

    <?php if (!$_isAuthPage) {
        // Aktuální cesta pro detekci aktivního itemu a auto-open sekce
        $_currentPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
        $_nav = $_navForRole($_role, $_proposalsPending);

        // Branding aktivního tenanta — logo + barvy avatara
        $_brand = ['display_name' => null, 'logo_url' => null, 'primary_color' => '#2563eb', 'accent_color' => '#7c3aed'];
        if (function_exists('crm_tenant_branding') && function_exists('crm_tenant_id')) {
            try {
                $_brand = crm_tenant_branding(crm_pdo(), crm_tenant_id());
            } catch (\Throwable $_) {}
        }
        $_brandTitle = $_brand['display_name'] ?? 'Clockwork Man';
        // Iniciály pro fallback avatara (z display_name nebo "CM")
        $_brandInitials = 'CM';
        if (!empty($_brand['display_name'])) {
            $words = preg_split('/\s+/', trim((string) $_brand['display_name'])) ?: [];
            $_brandInitials = mb_strtoupper(mb_substr($words[0] ?? '', 0, 1, 'UTF-8'), 'UTF-8');
            if (isset($words[1])) $_brandInitials .= mb_strtoupper(mb_substr($words[1], 0, 1, 'UTF-8'), 'UTF-8');
        }
        // Pomocná funkce: která sekce je aktivní (= obsahuje aktuální path)?
        $_sectionActive = static function (array $item) use ($_currentPath): bool {
            if (!empty($item['children'])) {
                foreach ($item['children'] as $c) {
                    if ($_currentPath === $c['href'] || str_starts_with($_currentPath, $c['href'] . '/')) {
                        return true;
                    }
                }
            }
            return false;
        };
    ?>
    <!-- ── SIDEBAR (nové accordion / collapsible) ── -->
    <aside class="crm-sidebar" id="crm-sidebar">
        <div class="crm-sidebar-head">
            <a href="/dashboard" class="crm-sidebar-logo" title="Domů">
                <?php if (!empty($_brand['logo_url'])): ?>
                    <span class="crm-logo-img">
                        <img src="<?= crm_h((string) $_brand['logo_url']) ?>" alt="<?= crm_h($_brandTitle) ?>">
                    </span>
                <?php else: ?>
                    <span class="crm-logo-mark">
                        <?= crm_h($_brandInitials) ?>
                    </span>
                <?php endif; ?>
                <span class="crm-logo-text">
                    <span class="crm-logo-title"><?= crm_h($_brandTitle) ?></span>
                    <span class="crm-logo-sub">CRM</span>
                </span>
            </a>
            <button type="button" class="crm-sb-collapse" id="crm-sb-collapse"
                    aria-label="Sbalit / rozbalit sidebar" title="Sbalit (Ctrl+B)">
                <span class="ico-collapse">«</span>
                <span class="ico-expand">»</span>
            </button>
        </div>

        <nav class="crm-sidebar-nav" id="crm-sidebar-nav">
            <?php foreach ($_nav as $item):
                $hasChildren = !empty($item['children']);
                $isActive    = !$hasChildren && $item['href'] !== null
                               && ($_currentPath === $item['href'] || str_starts_with($_currentPath, $item['href'] . '/'));
                $sectionOpen = $hasChildren && $_sectionActive($item); // auto-open pokud uvnitř aktivní
            ?>
                <?php if ($hasChildren): ?>
                    <details class="crm-nav-group" data-key="<?= crm_h($item['key']) ?>" <?= $sectionOpen ? 'open' : '' ?>>
                        <summary class="crm-nav-summary">
                            <span class="crm-nav-icon"><?= crm_h($item['icon']) ?></span>
                            <span class="crm-nav-label"><?= crm_h($item['label']) ?></span>
                            <span class="crm-nav-chev">▾</span>
                        </summary>
                        <div class="crm-nav-children">
                            <?php foreach ($item['children'] as $c):
                                $cActive = ($_currentPath === $c['href'] || str_starts_with($_currentPath, $c['href'] . '/'));
                            ?>
                                <a href="<?= crm_h(crm_url($c['href'])) ?>"
                                   class="crm-nav-child <?= $cActive ? 'is-active' : '' ?>"
                                   title="<?= crm_h($c['label']) ?>">
                                    <span class="crm-nav-icon"><?= crm_h($c['icon']) ?></span>
                                    <span class="crm-nav-label"><?= crm_h($c['label']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php else: ?>
                    <a href="<?= crm_h(crm_url((string) $item['href'])) ?>"
                       class="crm-nav-item <?= $isActive ? 'is-active' : '' ?>"
                       title="<?= crm_h($item['label']) ?>">
                        <span class="crm-nav-icon"><?= crm_h($item['icon']) ?></span>
                        <span class="crm-nav-label"><?= crm_h($item['label']) ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </aside>

    <!-- Mobile hamburger toggle (mimo aside, ať přežije sbalený stav) -->
    <button type="button" class="crm-mobile-menu-toggle" id="crm-mobile-toggle" aria-label="Otevřít menu">☰</button>

    <style>
    /* ════════════════════════════════════════════════════════════════
       NEW SIDEBAR DESIGN — modern light SaaS look
    ════════════════════════════════════════════════════════════════ */
    :root {
        --sb-width: 244px;
        --sb-width-collapsed: 64px;
        --sb-bg: #ffffff;
        --sb-border: #e5e7eb;
        --sb-text: #374151;
        --sb-text-muted: #9ca3af;
        --sb-hover: #f9fafb;
        --sb-active-bg: #eff6ff;
        --sb-active-border: #2563eb;
        --sb-active-text: #1d4ed8;
        --sb-group-bg: #f9fafb;
    }
    body.sb-collapsed .crm-sidebar { width: var(--sb-width-collapsed); }
    body.sb-collapsed .crm-sidebar .crm-nav-label,
    body.sb-collapsed .crm-sidebar .crm-nav-chev,
    body.sb-collapsed .crm-sidebar .crm-logo-text,
    body.sb-collapsed .crm-sidebar .crm-nav-children { display: none !important; }
    body.sb-collapsed .crm-sidebar .crm-sidebar-logo { justify-content: center; }
    body.sb-collapsed .crm-sidebar .crm-nav-summary,
    body.sb-collapsed .crm-sidebar .crm-nav-item { padding-left: 0; padding-right: 0; justify-content: center; }
    body.sb-collapsed .crm-sb-collapse .ico-collapse { display: none; }
    body.sb-collapsed .crm-sb-collapse .ico-expand { display: inline; }
    .crm-sb-collapse .ico-expand { display: none; }

    .crm-sidebar {
        width: var(--sb-width);
        background: var(--sb-bg);
        border-right: 1px solid var(--sb-border);
        display: flex; flex-direction: column;
        height: 100vh; position: sticky; top: 0;
        transition: width 200ms ease;
        z-index: 10;
    }
    .crm-sidebar-head {
        display: flex !important; align-items: center !important; justify-content: space-between !important;
        padding: .85rem 1rem !important; border-bottom: 1px solid var(--sb-border) !important;
        background: #fff !important; flex-shrink: 0;
    }
    .crm-sidebar-logo {
        display: flex !important; align-items: center !important; gap: .65rem !important;
        min-width: 0; flex: 1; text-decoration: none !important;
        background: transparent !important; color: inherit !important;
        padding: 0 !important; border: 0 !important;
    }
    .crm-sidebar-logo:hover { background: transparent !important; }
    .crm-logo-mark {
        display: inline-flex !important; align-items: center; justify-content: center;
        width: 34px !important; height: 34px !important;
        background: linear-gradient(135deg, var(--logo-grad-from, #2563eb), var(--logo-grad-to, #7c3aed)) !important;
        color: #fff !important; font-weight: 800 !important; font-size: .82rem !important;
        border-radius: 8px !important; letter-spacing: .03em !important;
        flex-shrink: 0; box-shadow: 0 2px 6px rgba(0,0,0,.15);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
    }
    .crm-logo-text { display: flex !important; flex-direction: column !important; line-height: 1.15 !important; min-width: 0; overflow: hidden; }
    .crm-logo-title {
        font-size: .92rem !important; font-weight: 700 !important; color: #111827 !important;
        letter-spacing: 0 !important; white-space: nowrap !important;
        text-shadow: none !important;
    }
    .crm-logo-sub {
        font-size: .68rem !important; color: var(--sb-text-muted) !important; white-space: nowrap !important;
        letter-spacing: .12em !important; text-transform: uppercase !important; margin-top: 1px;
    }
    .crm-sb-collapse {
        background: transparent; border: 1px solid var(--sb-border); border-radius: 6px;
        width: 26px; height: 26px; cursor: pointer; padding: 0;
        color: var(--sb-text-muted); flex-shrink: 0; font-size: .85rem;
        transition: background 100ms;
    }
    .crm-sb-collapse:hover { background: var(--sb-hover); color: var(--sb-text); }

    .crm-sidebar-nav {
        flex: 1; overflow-y: auto; padding: .5rem;
        scrollbar-width: thin; scrollbar-color: #d1d5db transparent;
    }
    .crm-sidebar-nav::-webkit-scrollbar { width: 6px; }
    .crm-sidebar-nav::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 3px; }

    /* Top-level item (direct link, no children) */
    .crm-nav-item {
        display: flex; align-items: center; gap: .65rem;
        padding: .55rem .7rem; border-radius: 6px;
        font-size: .9rem; color: var(--sb-text); text-decoration: none;
        font-weight: 500; margin-bottom: 2px;
        transition: background 120ms, color 120ms;
        position: relative;
    }
    .crm-nav-item:hover { background: var(--sb-hover); color: #111827; }
    .crm-nav-item.is-active {
        background: var(--sb-active-bg); color: var(--sb-active-text); font-weight: 600;
    }
    .crm-nav-item.is-active::before {
        content: ''; position: absolute; left: -.5rem; top: 4px; bottom: 4px;
        width: 3px; background: var(--sb-active-border); border-radius: 0 3px 3px 0;
    }

    /* Accordion group (has children) */
    .crm-nav-group { margin-bottom: 2px; }
    .crm-nav-group > summary { list-style: none; cursor: pointer; }
    .crm-nav-group > summary::-webkit-details-marker { display: none; }
    .crm-nav-summary {
        display: flex; align-items: center; gap: .65rem;
        padding: .55rem .7rem; border-radius: 6px;
        font-size: .9rem; color: var(--sb-text);
        font-weight: 500;
        transition: background 120ms;
    }
    .crm-nav-summary:hover { background: var(--sb-hover); color: #111827; }
    .crm-nav-group[open] > .crm-nav-summary {
        background: var(--sb-group-bg); color: #111827; font-weight: 600;
    }
    .crm-nav-chev {
        margin-left: auto; font-size: .7rem; color: var(--sb-text-muted);
        transition: transform 180ms;
    }
    .crm-nav-group[open] > .crm-nav-summary .crm-nav-chev { transform: rotate(180deg); }

    .crm-nav-children { padding: .15rem 0 .15rem 1.7rem; border-left: 1px solid var(--sb-border); margin-left: 1.2rem; }
    .crm-nav-child {
        display: flex; align-items: center; gap: .55rem;
        padding: .42rem .6rem; border-radius: 5px; font-size: .85rem;
        color: var(--sb-text); text-decoration: none; font-weight: 500;
        position: relative;
        transition: background 100ms, color 100ms;
    }
    .crm-nav-child:hover { background: var(--sb-hover); color: #111827; }
    .crm-nav-child.is-active {
        background: var(--sb-active-bg); color: var(--sb-active-text); font-weight: 600;
    }
    .crm-nav-child.is-active::before {
        content: ''; position: absolute; left: -1.7rem; top: 0; bottom: 0;
        width: 2px; background: var(--sb-active-border); border-radius: 2px;
    }
    .crm-nav-icon { width: 18px; text-align: center; flex-shrink: 0; }
    .crm-nav-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    /* Mobile */
    .crm-mobile-menu-toggle {
        display: none; position: fixed; top: .6rem; left: .6rem; z-index: 100;
        background: #fff; border: 1px solid var(--sb-border);
        border-radius: 6px; width: 36px; height: 36px; font-size: 1.2rem;
        cursor: pointer; box-shadow: 0 1px 4px rgba(0,0,0,.1);
    }
    @media (max-width: 900px) {
        .crm-sidebar {
            position: fixed; left: -100%; top: 0; height: 100vh;
            box-shadow: 4px 0 16px rgba(0,0,0,.1);
        }
        body.sb-open .crm-sidebar { left: 0; }
        .crm-mobile-menu-toggle { display: block; }
    }
    </style>
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
                <!-- Theme switcher -->
                <div class="theme-switcher" id="crm-theme-switcher">
                    <button type="button" class="theme-switcher-btn" id="crm-theme-btn"
                            title="Změnit barevný motiv aplikace"
                            aria-label="Změnit barevný motiv">
                        <span class="theme-btn-icon">🎨</span>
                        <span class="theme-btn-label">Motiv</span>
                        <span class="swatch" aria-hidden="true"></span>
                    </button>
                    <div class="theme-popover">
                        <h4>Vyber si motiv</h4>
                        <div class="options">
                            <div class="opt" data-theme="" title="Light (výchozí)">
                                <span class="preview" style="background:#fff;border:1px solid #e5e7eb;"></span>
                                <span class="name">Light</span>
                            </div>
                            <div class="opt" data-theme="ocean" title="Ocean">
                                <span class="preview" style="background:linear-gradient(135deg,#bae6fd,#0284c7);"></span>
                                <span class="name">Ocean</span>
                            </div>
                            <div class="opt" data-theme="forest" title="Forest">
                                <span class="preview" style="background:linear-gradient(135deg,#bbf7d0,#16a34a);"></span>
                                <span class="name">Forest</span>
                            </div>
                            <div class="opt" data-theme="sunset" title="Sunset">
                                <span class="preview" style="background:linear-gradient(135deg,#fed7aa,#ea580c);"></span>
                                <span class="name">Sunset</span>
                            </div>
                            <div class="opt" data-theme="lavender" title="Lavender">
                                <span class="preview" style="background:linear-gradient(135deg,#ddd6fe,#7c3aed);"></span>
                                <span class="name">Lavender</span>
                            </div>
                            <div class="opt" data-theme="crimson" title="Crimson">
                                <span class="preview" style="background:linear-gradient(135deg,#fecaca,#dc2626);"></span>
                                <span class="name">Crimson</span>
                            </div>
                            <div class="opt" data-theme="slate" title="Slate">
                                <span class="preview" style="background:linear-gradient(135deg,#cbd5e1,#475569);"></span>
                                <span class="name">Slate</span>
                            </div>
                            <div class="opt" data-theme="dark" title="Dark">
                                <span class="preview" style="background:linear-gradient(135deg,#374151,#111827);"></span>
                                <span class="name">Dark</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User-info: jméno + role pohromadě s akcemi (Heslo / Odhlásit) -->
                <div class="crm-topbar-user" title="<?= crm_h((string) ($user['email'] ?? '')) ?>">
                    <span class="crm-topbar-user__name"><?= crm_h((string) ($user['jmeno'] ?? '')) ?></span>
                    <span class="crm-topbar-user__role"><?= crm_h($_roleLabel) ?></span>
                </div>
                <?php
                // ── Super-admin tenant switcher ─────────────────────────────────
                // Vidí JEN user co je v `super_admins` tabulce (= globální root).
                // Dropdown nabídne aktivní firmy + označí aktuální tenant kotvou.
                $_isSuperAdmin = function_exists('crm_tenant_is_super_admin')
                    ? crm_tenant_is_super_admin()
                    : false;
                if ($_isSuperAdmin) {
                    $_tenants = [];
                    $_curTid = function_exists('crm_tenant_id') ? crm_tenant_id() : 0;
                    $_curTenantName = '—';
                    try {
                        $pdo = crm_pdo();
                        $stmt = $pdo->query(
                            'SELECT id, name, subdomain FROM tenants
                             WHERE active = 1 ORDER BY name ASC'
                        );
                        $_tenants = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
                        foreach ($_tenants as $_t) {
                            if ((int) $_t['id'] === $_curTid) {
                                $_curTenantName = (string) $_t['name'];
                                break;
                            }
                        }
                    } catch (\Throwable $_) {}
                    $_switchCsrf = crm_csrf_token();
                ?>
                <div class="crm-tenant-switcher" style="position:relative;">
                    <button type="button"
                            onclick="document.getElementById('crm-tenant-menu').classList.toggle('open')"
                            style="background:linear-gradient(135deg,#8b5cf6,#6366f1);color:#fff;
                                   padding:0.4rem 0.8rem;border-radius:6px;border:1px solid #4f46e5;
                                   font-weight:700;font-size:0.85rem;cursor:pointer;
                                   display:inline-flex;align-items:center;gap:0.3rem;"
                            title="Super-admin: přepnout aktivní firmu">
                        🏢 <?= crm_h($_curTenantName) ?> ▾
                    </button>
                    <div id="crm-tenant-menu" class="crm-tenant-menu"
                         style="position:absolute;top:calc(100% + 4px);right:0;
                                background:#fff;border:1px solid #e5e7eb;border-radius:8px;
                                box-shadow:0 4px 16px rgba(0,0,0,0.12);min-width:240px;z-index:1000;
                                padding:0.4rem;display:none;">
                        <div style="font-size:0.7rem;color:#6b7280;text-transform:uppercase;
                                    padding:0.4rem 0.6rem;border-bottom:1px solid #f3f4f6;margin-bottom:0.3rem;">
                            Super-admin · přepnout firmu
                        </div>
                        <?php foreach ($_tenants as $_t):
                            $_isCur = (int) $_t['id'] === $_curTid;
                        ?>
                        <form method="post"
                              action="<?= crm_h(crm_url('/admin/tenants/switch')) ?>"
                              style="margin:0;">
                            <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>"
                                   value="<?= crm_h($_switchCsrf) ?>">
                            <input type="hidden" name="tenant_id" value="<?= (int) $_t['id'] ?>">
                            <button type="submit"
                                    style="display:flex;width:100%;justify-content:space-between;
                                           align-items:center;padding:0.5rem 0.6rem;border:none;
                                           background:<?= $_isCur ? '#ede9fe' : 'transparent' ?>;
                                           color:#111827;text-align:left;border-radius:6px;cursor:pointer;
                                           font-size:0.85rem;font-weight:<?= $_isCur ? '700' : '400' ?>;">
                                <span><?= crm_h((string) $_t['name']) ?></span>
                                <span style="color:#6b7280;font-size:0.75rem;">
                                    <?= $_isCur ? '✓ aktivní' : crm_h((string) $_t['subdomain']) ?>
                                </span>
                            </button>
                        </form>
                        <?php endforeach; ?>
                    </div>
                </div>
                <style>
                    .crm-tenant-menu.open { display:block !important; }
                    .crm-tenant-menu form button:hover { background:#f3f4f6 !important; }
                </style>
                <?php } ?>
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

        <?php
        // ── Globální flash toast — auto-dismiss zelený "success" pro ✓ zprávy ──
        // Většina views už má svůj inline flash pro chyby, ale úspěšné akce
        // se ztratí. Tady ho vytahujeme nahoru jako floating toast, který sám
        // po 4 sekundách zmizí. Hláška musí začínat ✓ aby byla zelená.
        $_globalFlash = function_exists('crm_flash_peek') ? crm_flash_peek() : null;
        if ($_globalFlash !== null && str_starts_with(trim((string) $_globalFlash), '✓')) {
            crm_flash_take(); // konzumuj, ať se nezobrazí ještě jednou v inline view
        ?>
        <div id="crm-global-toast" class="crm-toast crm-toast--success">
            <span class="crm-toast__icon">✓</span>
            <span class="crm-toast__msg"><?= crm_h($_globalFlash) ?></span>
            <button type="button" class="crm-toast__close" onclick="document.getElementById('crm-global-toast').remove()">×</button>
        </div>
        <style>
            .crm-toast {
                position: fixed;
                top: 70px;
                right: 20px;
                z-index: 10000;
                display: inline-flex;
                align-items: center;
                gap: 0.6rem;
                padding: 0.85rem 1.1rem;
                border-radius: 10px;
                box-shadow: 0 8px 24px rgba(0,0,0,0.18);
                font-weight: 500;
                max-width: 480px;
                animation: crmToastIn 0.3s ease-out, crmToastOut 0.3s ease-in 4s forwards;
            }
            .crm-toast--success {
                background: linear-gradient(135deg, #10b981, #059669);
                color: #ffffff;
                border: 1px solid #047857;
            }
            .crm-toast__icon {
                font-size: 1.4rem;
                font-weight: 700;
            }
            .crm-toast__msg {
                flex: 1;
                line-height: 1.3;
            }
            .crm-toast__close {
                background: rgba(255,255,255,0.2);
                border: none;
                color: #fff;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                cursor: pointer;
                font-size: 1.1rem;
                line-height: 1;
                display: flex;
                align-items: center;
                justify-content: center;
                opacity: 0.85;
            }
            .crm-toast__close:hover { opacity: 1; background: rgba(255,255,255,0.3); }
            @keyframes crmToastIn {
                from { transform: translateX(120%); opacity: 0; }
                to   { transform: translateX(0);    opacity: 1; }
            }
            @keyframes crmToastOut {
                from { transform: translateX(0);    opacity: 1; }
                to   { transform: translateX(120%); opacity: 0; }
            }
        </style>
        <script>
            // Po 4.3s úplně odstranit z DOM (po animaci)
            setTimeout(function() {
                var el = document.getElementById('crm-global-toast');
                if (el) el.remove();
            }, 4400);
        </script>
        <?php } ?>

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
            <?php
            // ── Tenant lifecycle banner — grace period / trial varování ──
            $_curRole = (string) ($user['role'] ?? '');
            $_isSuper = function_exists('crm_tenant_is_super_admin') && crm_tenant_is_super_admin();
            if ($_curRole === 'majitel' && !$_isSuper && function_exists('crm_tenant_lifecycle_state')) {
                try {
                    $_tid = crm_tenant_id();
                    if ($_tid > 0) {
                        $_life = crm_tenant_lifecycle_state(crm_pdo(), $_tid);
                        $_state = (string) $_life['state'];
                        $_days  = $_life['days_until_expiry'];
                        if ($_state === 'grace'):
                            ?>
                            <div style="background:#fee2e2; border:1px solid #dc2626; color:#991b1b;
                                        padding:.75rem 1.1rem; border-radius:6px; margin-bottom:1rem; font-size:.92rem;">
                                <strong>🚫 Vaše předplatné prošlo</strong> — máte <strong><?= (int) max(0, $_days) ?> dní grace</strong> period.
                                Pro pokračování platby kontaktujte podporu (support@snecinatripu.eu).
                                Po této době bude přístup automaticky pozastaven.
                            </div>
                        <?php elseif ($_state === 'trial' && $_days !== null && $_days <= 5): ?>
                            <div style="background:#fef3c7; border:1px solid #d97706; color:#92400e;
                                        padding:.6rem 1rem; border-radius:6px; margin-bottom:1rem; font-size:.9rem;">
                                <strong>🧪 Trial období končí za <?= (int) $_days ?> dní.</strong>
                                Pro pokračování si vyberte placený plán nebo kontaktujte podporu.
                            </div>
                        <?php elseif ($_state === 'active' && $_days !== null && $_days <= 7): ?>
                            <div style="background:#dbeafe; border:1px solid #2563eb; color:#1e3a8a;
                                        padding:.5rem .9rem; border-radius:6px; margin-bottom:.7rem; font-size:.85rem;">
                                💡 Vaše předplatné expiruje za <strong><?= (int) $_days ?> dní</strong>.
                                Pro prodloužení kontaktujte podporu.
                            </div>
                        <?php endif;
                    }
                } catch (\Throwable $_) { /* tichý fallback */ }
            }

            // ── Tenant limit banner — soft warning pro majitele firmy ──
            // Ukáže se majiteli (ne super-adminovi), když dosáhne 80 % limitu.
            // Žádný hard blok — jen informuje.
            if ($_curRole === 'majitel' && !$_isSuper && function_exists('crm_tenant_limit_status')) {
                try {
                    $_tid = crm_tenant_id();
                    if ($_tid > 0) {
                        $_warnings = [];
                        foreach (['users', 'contacts', 'premium_orders'] as $_res) {
                            $_s = crm_tenant_limit_status(crm_pdo(), $_tid, $_res);
                            if (in_array($_s['status'], ['warning', 'over'], true)) {
                                $_warnings[] = $_s;
                            }
                        }
                        if ($_warnings) {
                            $_topWarn = $_warnings[0];
                            $_color = $_topWarn['status'] === 'over' ? '#dc2626' : '#d97706';
                            $_bg    = $_topWarn['status'] === 'over' ? '#fee2e2' : '#fef3c7';
                            ?>
                            <div style="background:<?= $_bg ?>; border:1px solid <?= $_color ?>;
                                        color:<?= $_color ?>; padding:.6rem 1rem; border-radius:6px;
                                        margin-bottom:1rem; font-size:.9rem;">
                                <strong>
                                    <?= $_topWarn['status'] === 'over' ? '🚫 Překročen limit' : '⚠ Blíží se limit' ?>:
                                </strong>
                                <?php foreach ($_warnings as $_w): ?>
                                    <?= htmlspecialchars($_w['label'], ENT_QUOTES, 'UTF-8') ?>:
                                    <strong><?= (int) $_w['count'] ?> / <?= (int) $_w['max'] ?></strong>
                                    (<?= (int) $_w['percent'] ?> %)<?= $_w !== end($_warnings) ? ' &nbsp;·&nbsp; ' : '' ?>
                                <?php endforeach; ?>
                                <span style="margin-left:.5rem; color:#6b7280;">
                                    Kontaktujte správce systému pro navýšení.
                                </span>
                            </div>
                            <?php
                        }
                    }
                } catch (\Throwable $_) { /* tichý fallback — banner je best-effort */ }
            }
            ?>
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

<!-- ════════════════════════════════════════════════════════════════
     SIDEBAR JS — accordion state persist + collapse + mobile toggle
════════════════════════════════════════════════════════════════ -->
<script>
(function () {
    'use strict';
    var sidebar = document.getElementById('crm-sidebar');
    if (!sidebar) return;

    // ── Accordion state persistence ──
    var STORE_OPEN = 'crm_sidebar_open_groups';
    var STORE_COLLAPSED = 'crm_sidebar_collapsed';
    var openSet = new Set();
    try {
        var raw = localStorage.getItem(STORE_OPEN);
        if (raw) JSON.parse(raw).forEach(function (k) { openSet.add(k); });
    } catch (e) {}

    var groups = sidebar.querySelectorAll('.crm-nav-group');
    groups.forEach(function (g) {
        var key = g.getAttribute('data-key') || '';
        // Auto-open pokud je uvnitř aktivní (server-side) NEBO byla v localStorage
        if (g.hasAttribute('open') || openSet.has(key)) {
            g.setAttribute('open', '');
            openSet.add(key);
        }
        g.addEventListener('toggle', function () {
            if (g.open) openSet.add(key);
            else openSet.delete(key);
            try { localStorage.setItem(STORE_OPEN, JSON.stringify(Array.from(openSet))); } catch (e) {}
        });
    });

    // ── Collapse / expand sidebar (icons only) ──
    var collapseBtn = document.getElementById('crm-sb-collapse');
    function applyCollapsed(state) {
        document.body.classList.toggle('sb-collapsed', !!state);
        try { localStorage.setItem(STORE_COLLAPSED, state ? '1' : '0'); } catch (e) {}
    }
    try {
        if (localStorage.getItem(STORE_COLLAPSED) === '1') applyCollapsed(true);
    } catch (e) {}
    if (collapseBtn) {
        collapseBtn.addEventListener('click', function () {
            applyCollapsed(!document.body.classList.contains('sb-collapsed'));
        });
    }
    // Keyboard shortcut: Ctrl/Cmd + B
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 'b' || e.key === 'B')) {
            // Ignoruj v inputech/textarea
            var t = e.target;
            if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable)) return;
            e.preventDefault();
            applyCollapsed(!document.body.classList.contains('sb-collapsed'));
        }
    });

    // ── Theme switcher ──
    (function () {
        var switcher = document.getElementById('crm-theme-switcher');
        var btn = document.getElementById('crm-theme-btn');
        if (!switcher || !btn) return;

        function applyTheme(t) {
            if (t) document.documentElement.setAttribute('data-theme', t);
            else document.documentElement.removeAttribute('data-theme');
            try { localStorage.setItem('crm_theme', t || ''); } catch (e) {}
            // Označ aktivní option
            switcher.querySelectorAll('.opt').forEach(function (el) {
                el.classList.toggle('is-active', (el.getAttribute('data-theme') || '') === (t || ''));
            });
        }
        // Init z localStorage
        var saved = '';
        try { saved = localStorage.getItem('crm_theme') || ''; } catch (e) {}
        applyTheme(saved);

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            switcher.classList.toggle('open');
        });
        switcher.querySelectorAll('.opt').forEach(function (el) {
            el.addEventListener('click', function () {
                applyTheme(el.getAttribute('data-theme') || '');
                switcher.classList.remove('open');
            });
        });
        document.addEventListener('click', function (e) {
            if (!switcher.contains(e.target)) switcher.classList.remove('open');
        });
    })();

    // ── Mobile hamburger toggle ──
    var mobileBtn = document.getElementById('crm-mobile-toggle');
    if (mobileBtn) {
        mobileBtn.addEventListener('click', function () {
            document.body.classList.toggle('sb-open');
        });
        // Klik mimo sidebar zavře menu (mobile)
        document.addEventListener('click', function (e) {
            if (!document.body.classList.contains('sb-open')) return;
            if (sidebar.contains(e.target) || mobileBtn.contains(e.target)) return;
            document.body.classList.remove('sb-open');
        });
    }
})();
</script>

</body>
</html>
