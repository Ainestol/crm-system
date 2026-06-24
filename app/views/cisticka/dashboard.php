<?php
/** @var array $user */
/** @var array{primary_count:int, sub_a:int, sub_b:int, sub_c:int} $stats */
/** @var array{label:string, href:string, count_label:string} $primary */
?>
<?php require dirname(__DIR__) . '/_partials/dashboard_styles.php'; ?>
<div class="rd-wrap">
    <?php require dirname(__DIR__) . '/_partials/dashboard_header.php'; ?>

    <!-- Primary action: velké tlačítko "Co mám teď dělat?" -->
    <div class="rd-primary-card">
        <div class="rd-primary-left">
            <div class="rd-primary-label">🎯 Co mám teď udělat?</div>
            <div class="rd-primary-headline">Začni s čištěním kontaktů</div>
            <div class="rd-primary-sub"><?= htmlspecialchars($primary['count_label'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <a href="<?= htmlspecialchars($primary['href'], ENT_QUOTES, 'UTF-8') ?>" class="rd-primary-btn">
            <?= htmlspecialchars($primary['label'], ENT_QUOTES, 'UTF-8') ?> →
        </a>
    </div>

    <!-- 3 stat cards -->
    <div class="rd-stats">
        <div class="rd-stat">
            <div class="rd-stat-label">📋 Ve frontě</div>
            <div class="rd-stat-value"><?= (int) $stats['primary_count'] ?></div>
            <div class="rd-stat-sub">kontaktů čeká na ověření</div>
        </div>
        <div class="rd-stat">
            <div class="rd-stat-label">✓ Hotovo dnes</div>
            <div class="rd-stat-value"><?= (int) $stats['sub_a'] ?></div>
            <div class="rd-stat-sub">ověřených kontaktů</div>
        </div>
        <div class="rd-stat">
            <div class="rd-stat-label">💎 Premium</div>
            <div class="rd-stat-value">→</div>
            <div class="rd-stat-sub">
                <a href="/cisticka/premium" style="color:#2563eb;">Premium plocha</a>
            </div>
        </div>
    </div>

    <!-- Quick actions -->
    <div class="rd-quick-actions">
        <h3>⚡ Rychlé akce</h3>
        <div class="rd-quick-list">
            <a href="/cisticka" class="rd-quick-btn">🧹 Standardní čištění</a>
            <a href="/cisticka/premium" class="rd-quick-btn">💎 Premium čištění</a>
            <a href="/me/added-contacts" class="rd-quick-btn">📋 Moje doporučenky</a>
            <a href="/contacts/new" class="rd-quick-btn">➕ Nový kontakt</a>
        </div>
    </div>
</div>
