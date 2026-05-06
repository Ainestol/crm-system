<?php
// e:\Snecinatripu\app\views\dashboard\index.php
declare(strict_types=1);
/** @var array<string, mixed> $user */
/** @var string|null $flash */
/** @var string $csrf */
/** @var list<string> $allowedRegions */
/** @var string|null $activeRegion */
/** @var list<array<string,mixed>> $upcomingAnniversaries */
/** @var array{days_30:int,days_60:int,days_90:int,days_180:int} $anniversaryStats */
$role = (string) ($user['role'] ?? '');
$upcomingAnniversaries = $upcomingAnniversaries ?? [];
$anniversaryStats      = $anniversaryStats ?? ['days_30' => 0, 'days_60' => 0, 'days_90' => 0, 'days_180' => 0];
?>
<section class="card">
    <h1>Dashboard</h1>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info"><?= crm_h($flash) ?></p>
    <?php } ?>

    <p class="dash-welcome">Vítejte, <strong><?= crm_h((string) ($user['jmeno'] ?? '')) ?></strong>.</p>
    <?php // Notifikace "Je vyžadována změna hesla" odstraněna —
          // middleware uživatele s must_change_password=1 automaticky přesměruje
          // na /account/password a sem se vůbec nedostane. ?>

    <!-- ══════════════════════════════════════════════════════════
         WIDGET: Blíží se výročí smluv (jen pro majitele/superadmin)
         Top 10 nejbližších + agregátní statistiky 30/60/90/180 dní
    ══════════════════════════════════════════════════════════ -->
    <?php if (in_array($role, ['majitel', 'superadmin'], true) && $anniversaryStats['days_180'] > 0) { ?>
        <div class="anniv-widget">
            <details <?= $anniversaryStats['days_30'] > 0 ? 'open' : '' ?>>
                <summary class="anniv-widget__summary">
                    🎂 <strong>Blíží se výročí smluv</strong>
                    <span class="anniv-widget__pills">
                        <?php if ($anniversaryStats['days_30'] > 0) { ?>
                            <span class="anniv-pill anniv-pill--urgent"><?= $anniversaryStats['days_30'] ?> do 30 dní</span>
                        <?php } ?>
                        <?php if ($anniversaryStats['days_60'] > $anniversaryStats['days_30']) { ?>
                            <span class="anniv-pill anniv-pill--soon"><?= $anniversaryStats['days_60'] ?> do 60 dní</span>
                        <?php } ?>
                        <?php if ($anniversaryStats['days_90'] > $anniversaryStats['days_60']) { ?>
                            <span class="anniv-pill"><?= $anniversaryStats['days_90'] ?> do 90 dní</span>
                        <?php } ?>
                        <?php if ($anniversaryStats['days_180'] > $anniversaryStats['days_90']) { ?>
                            <span class="anniv-pill"><?= $anniversaryStats['days_180'] ?> do 180 dní</span>
                        <?php } ?>
                    </span>
                    <a href="<?= crm_h(crm_url('/admin/datagrid?vyroci=1')) ?>" class="anniv-widget__cta">
                        Zobrazit všechny →
                    </a>
                </summary>
                <div class="anniv-widget__body">
                    <p class="anniv-widget__hint">
                        Top 10 nejbližších výročí — proaktivně oslovit klienta a nabídnout obnovení.
                    </p>
                    <table class="anniv-table">
                        <thead>
                            <tr>
                                <th>Firma</th>
                                <th>Telefon</th>
                                <th>Kraj</th>
                                <th>OZ</th>
                                <th>Č. smlouvy</th>
                                <th>Výročí</th>
                                <th>Za</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcomingAnniversaries as $a) {
                                $days = (int) ($a['days_until'] ?? 0);
                                $cls  = $days <= 30 ? 'anniv-row--urgent' : ($days <= 60 ? 'anniv-row--soon' : '');
                                $vyroc = (string) ($a['vyrocni_smlouvy'] ?? '');
                                $vyrocFmt = $vyroc !== '' ? date('j. n. Y', strtotime($vyroc)) : '—';
                            ?>
                                <tr class="<?= $cls ?>">
                                    <td><strong><?= crm_h((string)($a['firma'] ?? '')) ?></strong></td>
                                    <td><?= crm_h((string)($a['telefon'] ?? '')) ?></td>
                                    <td><?= crm_h((string)($a['region'] ?? '')) ?></td>
                                    <td><?= crm_h((string)($a['oz_name'] ?? '—')) ?></td>
                                    <td><?= crm_h((string)($a['cislo_smlouvy'] ?? '—')) ?></td>
                                    <td><?= crm_h($vyrocFmt) ?></td>
                                    <td>
                                        <span class="anniv-days-pill <?= $days <= 30 ? 'anniv-days-pill--urgent' : ($days <= 60 ? 'anniv-days-pill--soon' : '') ?>">
                                            <?= $days ?> d
                                        </span>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </details>
        </div>
    <?php } ?>

    <!-- ══════════════════════════════════════════════════════════
         ADMIN / MAJITEL — role-grouped přehled (5 sekcí podle role,
         koho daná funkce ovlivňuje). Každá sekce má vlastní barvu
         odpovídající role-tématu zbytku appky.
    ══════════════════════════════════════════════════════════ -->
    <?php if (in_array($role, ['majitel', 'superadmin'], true)) { ?>

        <!-- 1) ADMINISTRACE SYSTÉMU (cross-role) -->
        <div class="dash-section">
            <div class="dash-section__title dash-section__title--admin">
                🛠 Administrace systému
                <span class="dash-section__hint">správa, přidělení, import, přehled</span>
            </div>
            <div class="dash-cards">
                <a href="<?= crm_h(crm_url('/admin/users')) ?>" class="dash-card">
                    <span class="dash-card__icon">👥</span>
                    <span class="dash-card__title">Správa uživatelů</span>
                    <span class="dash-card__desc">Účty, role, hesla</span>
                </a>
                <a href="<?= crm_h(crm_url('/admin/import')) ?>" class="dash-card">
                    <span class="dash-card__icon">📥</span>
                    <span class="dash-card__title">CSV import</span>
                    <span class="dash-card__desc">Naimportovat kontakty</span>
                </a>
                <a href="<?= crm_h(crm_url('/admin/duplicates')) ?>" class="dash-card">
                    <span class="dash-card__icon">🕵</span>
                    <span class="dash-card__title">Audit duplicit</span>
                    <span class="dash-card__desc">Kontaktů se stejným tel/email/IČO</span>
                </a>
                <a href="<?= crm_h(crm_url('/admin/datagrid')) ?>" class="dash-card">
                    <span class="dash-card__icon">📊</span>
                    <span class="dash-card__title">Live datagrid</span>
                    <span class="dash-card__desc">Excel-like přehled s auto-refresh</span>
                </a>
                <a href="<?= crm_h(crm_url('/admin/feed')) ?>" class="dash-card">
                    <span class="dash-card__icon">📰</span>
                    <span class="dash-card__title">Activity feed</span>
                    <span class="dash-card__desc">Co se právě teď děje napříč CRM</span>
                </a>
                <a href="<?= crm_h(crm_url('/admin/team-stats')) ?>" class="dash-card">
                    <span class="dash-card__icon">🏆</span>
                    <span class="dash-card__title">Týmové statistiky</span>
                    <span class="dash-card__desc">Cross-role přehled</span>
                </a>
            </div>
        </div>

        <!-- 2) ČISTIČKY -->
        <div class="dash-section">
            <div class="dash-section__title dash-section__title--clean">
                🧹 Čističky
                <span class="dash-section__hint">ověřování operátorů + měsíční cíle</span>
            </div>
            <div class="dash-cards">
                <a href="<?= crm_h(crm_url('/cisticka')) ?>" class="dash-card">
                    <span class="dash-card__icon">🧹</span>
                    <span class="dash-card__title">Ověřování kontaktů</span>
                    <span class="dash-card__desc">Čistička databáze</span>
                </a>
                <a href="<?= crm_h(crm_url('/admin/cisticka-goals')) ?>" class="dash-card">
                    <span class="dash-card__icon">🎯</span>
                    <span class="dash-card__title">Cíle podle krajů</span>
                    <span class="dash-card__desc">Denní target per region</span>
                </a>
            </div>
        </div>

        <!-- 3) NAVOLÁVAČKY -->
        <div class="dash-section">
            <div class="dash-section__title dash-section__title--call">
                📞 Navolávačky
                <span class="dash-section__hint">výkon, cíle, odměny</span>
            </div>
            <div class="dash-cards">
                <a href="<?= crm_h(crm_url('/admin/caller-stats')) ?>" class="dash-card">
                    <span class="dash-card__icon">📈</span>
                    <span class="dash-card__title">Výkon navolávaček</span>
                    <span class="dash-card__desc">Statistika a pořadí</span>
                </a>
                <a href="<?= crm_h(crm_url('/admin/daily-goals')) ?>" class="dash-card">
                    <span class="dash-card__icon">⚙️</span>
                    <span class="dash-card__title">Denní cíle &amp; odměny</span>
                    <span class="dash-card__desc">Motivační systém</span>
                </a>
            </div>
        </div>

        <!-- 4) OZ (OBCHODNÍ ZÁSTUPCI) -->
        <div class="dash-section">
            <div class="dash-section__title dash-section__title--oz">
                💼 Obchodní zástupci (OZ)
                <span class="dash-section__hint">kvóty, stages, milníky, výkon</span>
            </div>
            <div class="dash-cards">
                <a href="<?= crm_h(crm_url('/admin/oz-targets')) ?>" class="dash-card">
                    <span class="dash-card__icon">🎯</span>
                    <span class="dash-card__title">Kvóty OZ</span>
                    <span class="dash-card__desc">Cíle per měsíc &amp; region</span>
                </a>
                <a href="<?= crm_h(crm_url('/oz/performance')) ?>" class="dash-card">
                    <span class="dash-card__icon">🏅</span>
                    <span class="dash-card__title">Výkon OZ týmu</span>
                    <span class="dash-card__desc">BMSL + stage progress</span>
                </a>
                <a href="<?= crm_h(crm_url('/admin/oz-stages')) ?>" class="dash-card">
                    <span class="dash-card__icon">⬆</span>
                    <span class="dash-card__title">Stage cíle OZ</span>
                    <span class="dash-card__desc">Týmové milníky &amp; provize</span>
                </a>
                <a href="<?= crm_h(crm_url('/admin/oz-milestones')) ?>" class="dash-card">
                    <span class="dash-card__icon">⭐</span>
                    <span class="dash-card__title">Osobní milníky OZ</span>
                    <span class="dash-card__desc">Odměny per obchodák</span>
                </a>
            </div>
        </div>

        <!-- 5) BACK-OFFICE -->
        <div class="dash-section">
            <div class="dash-section__title dash-section__title--bo">
                🏢 Back-office
                <span class="dash-section__hint">zpracování smluv</span>
            </div>
            <div class="dash-cards">
                <a href="<?= crm_h(crm_url('/bo')) ?>" class="dash-card">
                    <span class="dash-card__icon">🏢</span>
                    <span class="dash-card__title">Back-office</span>
                    <span class="dash-card__desc">Příprava smluv, datovka, OKU</span>
                </a>
            </div>
        </div>

    <?php } ?>

    <!-- (Role-specific dash cards pro navolávačku / čističku / OZ / BO byly odstraněny —
         jejich navigace je v sidebaru. Dashboard pro tyto role zobrazí jen welcome zprávu
         + případné insights. User-info / Změnit heslo / Odhlásit jsou v topbaru. ) -->
</section>
