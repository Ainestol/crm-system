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
            <div class="rd-primary-headline">Aktivuj čekající smlouvy</div>
            <div class="rd-primary-sub"><?= htmlspecialchars($primary['count_label'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <a href="<?= htmlspecialchars($primary['href'], ENT_QUOTES, 'UTF-8') ?>" class="rd-primary-btn">
            <?= htmlspecialchars($primary['label'], ENT_QUOTES, 'UTF-8') ?> →
        </a>
    </div>

    <div class="rd-stats">
        <div class="rd-stat">
            <div class="rd-stat-label">✓ K aktivaci</div>
            <div class="rd-stat-value"><?= (int) $stats['primary_count'] ?></div>
            <div class="rd-stat-sub">podpisy potvrzeny OZ</div>
        </div>
        <div class="rd-stat">
            <div class="rd-stat-label">🏢 Pracovní plocha</div>
            <div class="rd-stat-value">→</div>
            <div class="rd-stat-sub">
                <a href="/bo" style="color:#2563eb;">Otevřít</a>
            </div>
        </div>
        <div class="rd-stat">
            <div class="rd-stat-label">📋 Moje doporučenky</div>
            <div class="rd-stat-value">→</div>
            <div class="rd-stat-sub">
                <a href="/me/added-contacts" style="color:#2563eb;">Otevřít</a>
            </div>
        </div>
    </div>

    <div class="rd-quick-actions">
        <h3>⚡ Rychlé akce</h3>
        <div class="rd-quick-list">
            <a href="/bo" class="rd-quick-btn">🏢 Pracovní plocha</a>
            <a href="/me/added-contacts" class="rd-quick-btn">📋 Moje doporučenky</a>
            <a href="/contacts/new" class="rd-quick-btn">➕ Nový kontakt</a>
        </div>
    </div>
</div>
