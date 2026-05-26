<?php
declare(strict_types=1);
/** @var array<string,mixed> $topic */
?>

<div style="max-width:1000px;margin:0 auto;padding:1rem;">
    <div style="margin-bottom:1rem;">
        <a href="<?= crm_url('/help') ?>" style="color:#6b7280;text-decoration:none;font-size:0.9rem;">← Zpět na rozcestník</a>
    </div>

    <h1><?= $topic['icon'] ?> <?= crm_h($topic['label']) ?></h1>
    <p style="color:#6b7280;"><?= crm_h($topic['short']) ?></p>

    <div style="background:#f3e8ff;border-left:4px solid #7e22ce;padding:0.9rem 1.1rem;border-radius:0 6px 6px 0;margin:1rem 0;">
        <strong>📌 Co to dělá:</strong> Mix přerovnává frontu kontaktů tak, aby navolávačka v sérii dostala <strong>9× OSVČ</strong> (jednodušší) a pak <strong>1× firmu</strong> (těžší). Cyklus 10 kontaktů. Psychologicky lepší než fronta plná firem za sebou.
    </div>

    <h2>🔄 Jak to funguje</h2>
    <ol style="line-height:1.7;">
        <li>Po importu (nebo manuálně) systém vezme všechny <strong>NEW</strong> kontakty</li>
        <li>Detekuje typ: <strong>firma</strong> (název obsahuje s.r.o./a.s./...) nebo <strong>OSVČ</strong> (jinak)</li>
        <li>Interleave podle nastaveného poměru: 9 OSVČ, pak 1 firma, opakovaně</li>
        <li>Když dojde jeden typ, druhý pokračuje sám</li>
        <li>Přiřadí sekvenční číslo <code>queue_mix_seq</code></li>
        <li>Pool queries (čistička + navolávačka) řadí podle tohoto čísla</li>
    </ol>

    <h2>⚙️ Nastavení</h2>
    <p>V <code>/admin/contacts/mix</code> → rozbal <strong>⚙️ Nastavení mixu</strong>:</p>
    <ul style="line-height:1.7;">
        <li><strong>Cyklus</strong>: 2 čísla, default <code>9 × OSVČ + 1 × firma</code>. Můžeš si přizpůsobit.</li>
        <li><strong>🤖 Auto-mix po importu</strong>: default ZAPNUTÝ. Po každém importu se nové kontakty automaticky zamíchají.</li>
    </ul>

    <h2>🎯 Propagace skrz pipeline</h2>
    <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #e5e7eb;border-radius:8px;font-size:0.9rem;">
        <thead style="background:#f3f4f6;">
            <tr><th style="text-align:left;padding:0.5rem 0.8rem;">Pozice</th><th style="text-align:left;padding:0.5rem 0.8rem;">Co vidí čistička</th><th style="text-align:left;padding:0.5rem 0.8rem;">Co vidí navolávačka</th></tr>
        </thead>
        <tbody>
            <tr><td style="padding:0.4rem 0.8rem;font-family:monospace;">1.-9.</td><td style="padding:0.4rem 0.8rem;">OSVČ × 9</td><td style="padding:0.4rem 0.8rem;">(po čištění) OSVČ × 9</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;font-family:monospace;">10.</td><td style="padding:0.4rem 0.8rem;">FIRMA</td><td style="padding:0.4rem 0.8rem;">FIRMA</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;font-family:monospace;">11.-19.</td><td style="padding:0.4rem 0.8rem;">OSVČ × 9</td><td style="padding:0.4rem 0.8rem;">OSVČ × 9</td></tr>
            <tr><td style="padding:0.4rem 0.8rem;font-family:monospace;">20.</td><td style="padding:0.4rem 0.8rem;">FIRMA</td><td style="padding:0.4rem 0.8rem;">FIRMA</td></tr>
        </tbody>
    </table>

    <h2>💡 Tipy</h2>
    <ul style="line-height:1.7;">
        <li><strong>Detekce není 100%</strong> — heuristika z názvu. „Pavel Novák s.r.o." se detekuje jako firma, ale „IT služby Pavel Novák" jako OSVČ. Funguje na ~95% kontaktech.</li>
        <li><strong>Re-mix</strong>: pokud změníš poměr nebo chceš přemíchat všechno, klikni <strong>🎲 Spustit mix</strong>. Idempotentní = bezpečné.</li>
        <li><strong>Nezamíchané kontakty</strong>: jsou v seznamu „⏳ Čeká na mix" v admin pohledu. Auto-mix je zařadí po dalším importu.</li>
    </ul>
</div>
