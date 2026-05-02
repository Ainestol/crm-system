<?php
// app/views/layout/base.php
declare(strict_types=1);
/** @var string                   $title */
/** @var string                   $content */
/** @var array<string,mixed>|null $user   -- nemusí být nastaven na login stránce */
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
<div class="layout">
    <header class="header">

        <!-- ── Levá část: logo ── -->
        <div class="header-left">
            <span class="logo">CRM</span>
        </div>

        <!-- ── Pravá část: uživatel + Dashboard + Odhlásit ── -->
        <?php if (!empty($user)) {
            $_roleLabels = [
                'navolavacka' => 'Navolávačka',
                'cisticka'    => 'Čistička',
                'obchodak'    => 'Obchodák',
                'backoffice'  => 'Backoffice',
                'majitel'     => 'Majitel',
                'superadmin'  => 'Superadmin',
            ];
            $_roleLabel   = $_roleLabels[$user['role'] ?? ''] ?? ($user['role'] ?? '');
            $_logoutCsrf  = crm_csrf_token();
        ?>
        <div class="header-right">
            <span class="header-user">
                <strong><?= crm_h((string) ($user['jmeno'] ?? '')) ?></strong>
            </span>
            <span class="header-role-badge"><?= crm_h($_roleLabel) ?></span>
            <?php if (in_array(($user['role'] ?? ''), ['backoffice', 'majitel', 'superadmin'], true)) { ?>
            <a href="<?= crm_h(crm_url('/bo')) ?>" class="header-dash-btn"
               style="background:rgba(155,89,182,0.15);color:#9b59b6;border:1px solid rgba(155,89,182,0.4);">
                🏢 Back-office
            </a>
            <?php } ?>
            <a href="<?= crm_h(crm_url('/dashboard')) ?>" class="header-dash-btn">🏠 Dashboard</a>
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
    <main class="main">
        <?= $content ?>
    </main>
</div>
</body>
</html>
