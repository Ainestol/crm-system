<?php
/** @var array $user */
/** @var array{primary_count:int, sub_a:int, sub_b:int, sub_c:int} $stats */
/** @var array{label:string, href:string, count_label:string} $primary */
?>
<?php require dirname(__DIR__) . '/_partials/dashboard_styles.php'; ?>
<div class="rd-wrap">
    <?php require dirname(__DIR__) . '/_partials/dashboard_header.php'; ?>

    <div class="rd-primary-card">
        <div class="rd-primary-left">
            <div class="rd-primary-label">🎯 Co mám teď udělat?</div>
            <div class="rd-primary-headline">Otevři frontu hovorů</div>
            <div class="rd-primary-sub"><?= htmlspecialchars($primary['count_label'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <a href="<?= htmlspecialchars($primary['href'], ENT_QUOTES, 'UTF-8') ?>" class="rd-primary-btn">
            <?= htmlspecialchars($primary['label'], ENT_QUOTES, 'UTF-8') ?> →
        </a>
    </div>

    <div class="rd-stats">
        <div class="rd-stat">
            <div class="rd-stat-label">📞 K volání</div>
            <div class="rd-stat-value"><?= (int) $stats['primary_count'] ?></div>
            <div class="rd-stat-sub">leady + callbacky + nedovolano</div>
        </div>
        <div class="rd-stat">
            <div class="rd-stat-label">📅 Callbacky dnes</div>
            <div class="rd-stat-value"><?= (int) $stats['sub_a'] ?></div>
            <div class="rd-stat-sub">domluvené hovory</div>
        </div>
        <div class="rd-stat">
            <div class="rd-stat-label">💎 Premium pool</div>
            <div class="rd-stat-value"><?= (int) $stats['sub_b'] ?></div>
            <div class="rd-stat-sub">leady k volání s bonusem</div>
        </div>
    </div>

    <div class="rd-quick-actions">
        <h3>⚡ Rychlé akce</h3>
        <div class="rd-quick-list">
            <a href="/caller" class="rd-quick-btn">📞 Standardní pool</a>
            <a href="/caller/premium" class="rd-quick-btn">💎 Premium navolávky</a>
            <a href="/caller/campaigns" class="rd-quick-btn">🎯 Sázky/Kampaně</a>
            <a href="/caller/calendar" class="rd-quick-btn">📅 Kalendář</a>
            <a href="/caller/search" class="rd-quick-btn">🔍 Vyhledat kontakt</a>
            <a href="/caller/stats" class="rd-quick-btn">💰 Můj výdělek</a>
        </div>
    </div>
</div>
