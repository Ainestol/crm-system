<?php
// e:\Snecinatripu\app\views\profile\2fa_done.php
declare(strict_types=1);
/** @var array<string,mixed> $actor */
/** @var list<string> $codes  Backup kódy (jen jednou viditelné!) */
/** @var string|null $flash */
?>
<style>
.tfa-done-card { max-width: 720px; }
.tfa-codes-grid {
    display: grid; grid-template-columns: repeat(2, 1fr);
    gap: 0.5rem; margin: 1rem 0;
}
.tfa-code-tile {
    background: rgba(46,204,113,0.06);
    border: 1px solid rgba(46,204,113,0.3);
    border-radius: 8px;
    padding: 0.7rem 1rem;
    font-family: monospace;
    font-size: 1.05rem; font-weight: 600;
    text-align: center; letter-spacing: 0.05em;
    user-select: all;
}
.tfa-warn-box {
    background: rgba(241,160,48,0.08);
    border: 1px solid rgba(241,160,48,0.3);
    border-left: 4px solid #f0a030;
    border-radius: 0 8px 8px 0;
    padding: 0.8rem 1rem;
    margin: 1rem 0;
    font-size: 0.85rem; line-height: 1.5;
}
@media print {
    body * { visibility: hidden; }
    .tfa-done-card, .tfa-done-card * { visibility: visible; }
    .tfa-done-card { position: absolute; left: 0; top: 0; }
    .no-print { display: none !important; }
}
</style>

<section class="card tfa-done-card">
    <h1 style="color:#2ecc71;margin-bottom:0.4rem;">✅ 2FA je aktivováno!</h1>
    <p style="color:var(--muted);font-size:0.9rem;margin:0 0 1rem;">
        Při dalším přihlášení budeš potřebovat 6-místný kód z aplikace na mobilu.
    </p>

    <div class="tfa-warn-box">
        <strong>⚠ Ulož si tyto backup kódy NA BEZPEČNÉ MÍSTO</strong> (vytiskni / password manager / heslem chráněný soubor).<br>
        Každý z těchto kódů funguje <strong>jen 1×</strong>. Použiješ je pokud ztratíš mobil a nemáš jak vygenerovat 2FA kód.<br>
        <strong style="color:#e74c3c;">Tato stránka se zobrazí JEN JEDNOU.</strong> Po refresh / zavření okna kódy už neuvidíš.
    </p>

    <div class="tfa-codes-grid">
        <?php foreach ($codes as $code) { ?>
            <div class="tfa-code-tile"><?= crm_h($code) ?></div>
        <?php } ?>
    </div>

    <div class="no-print" style="display:flex; gap:0.6rem; margin-top:1rem; flex-wrap:wrap;">
        <button onclick="window.print();" class="btn">🖨 Vytisknout (nebo „Uložit do PDF")</button>
        <button onclick="copyAllCodes()" class="btn btn-secondary" id="copy-btn">📋 Zkopírovat do schránky</button>
        <a href="<?= crm_h(crm_url('/dashboard')) ?>" class="btn btn-secondary"
           onclick="return confirm('Opravdu pokračovat? Backup kódy už neuvidíš.');">
           Pokračovat na dashboard
        </a>
    </div>

    <p class="no-print" style="font-size:0.75rem; color:var(--muted); margin-top:1.2rem;">
        💡 <strong>Pokud později ztratíš všechny backup kódy i mobil</strong>, kontaktuj admina —
        může 2FA vypnout v nastavení uživatele.
    </p>
</section>

<script>
function copyAllCodes() {
    var codes = <?= json_encode($codes, JSON_UNESCAPED_UNICODE) ?>;
    var text = '🔐 Backup kódy pro CRM Šneci na tripu (uživatel: <?= crm_h((string) ($actor['email'] ?? '')) ?>)\n\n'
             + codes.map(function (c, i) { return (i + 1) + '. ' + c; }).join('\n')
             + '\n\nKaždý kód platí jen 1× (po použití se zneplatní).';
    navigator.clipboard.writeText(text).then(function () {
        var btn = document.getElementById('copy-btn');
        var orig = btn.textContent;
        btn.textContent = '✓ Zkopírováno!';
        setTimeout(function () { btn.textContent = orig; }, 2000);
    }, function () {
        alert('Kopírování selhalo. Vyber kódy myší a zkopíruj ručně.');
    });
}
</script>
