<?php
/**
 * Shared dashboard header — pozdrav podle hodiny + náhodný citát.
 *
 * Použití (uvnitř konkrétního dashboardu):
 *   require dirname(__DIR__) . '/_partials/dashboard_header.php';
 *
 * Vyžaduje proměnnou $user v contextu (z base.php / kontroleru).
 */

$_jmeno = (string) ($user['jmeno'] ?? '');
$_greeting = function_exists('crm_greeting') ? crm_greeting($_jmeno) : ('Vítejte' . ($_jmeno ? ', ' . $_jmeno : ''));
$_quote = function_exists('crm_random_quote') ? crm_random_quote(crm_pdo()) : null;
$_today = strftime_cz();
?>
<div class="dh-header">
    <div class="dh-left">
        <div class="dh-greeting">
            <?= htmlspecialchars($_greeting, ENT_QUOTES, 'UTF-8') ?>!
            <span class="dh-wave">👋</span>
        </div>
        <?php if ($_quote !== null): ?>
            <div class="dh-quote">
                „<?= htmlspecialchars((string) $_quote['text'], ENT_QUOTES, 'UTF-8') ?>"
                <?php if (!empty($_quote['author'])): ?>
                    <span class="dh-quote-author">— <?= htmlspecialchars((string) $_quote['author'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="dh-right">
        <div class="dh-date"><?= htmlspecialchars($_today, ENT_QUOTES, 'UTF-8') ?></div>
    </div>
</div>

<style>
.dh-header {
    display:flex; align-items:center; justify-content:space-between;
    gap:1rem; padding:1.1rem 1.4rem; margin-bottom:1.25rem;
    background:linear-gradient(135deg, #eff6ff 0%, #f3e8ff 100%);
    border:1px solid #dbeafe; border-radius:12px;
}
.dh-greeting { font-size:1.35rem; font-weight:700; color:#1e3a8a; margin-bottom:.3rem; }
.dh-wave { display:inline-block; animation: dh-wave 2.5s ease-in-out infinite; transform-origin: 70% 70%; }
@keyframes dh-wave { 0%,100% { transform: rotate(0); } 15% { transform: rotate(14deg); } 30% { transform: rotate(-8deg); } 45% { transform: rotate(14deg); } 60% { transform: rotate(0); } }
.dh-quote { color:#4b5563; font-style:italic; font-size:.95rem; max-width:640px; line-height:1.5; }
.dh-quote-author { color:#6b7280; font-style:normal; font-size:.85rem; margin-left:.3rem; }
.dh-date { color:#6b7280; font-size:.85rem; text-align:right; }
@media (max-width: 700px) {
    .dh-header { flex-direction:column; align-items:flex-start; }
    .dh-right { align-self:flex-end; }
}
</style>
