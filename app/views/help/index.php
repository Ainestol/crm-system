<?php
declare(strict_types=1);
/** @var array<string, array<string,mixed>> $groups */
/** @var string|null $flash */
?>

<div style="max-width:1200px;margin:0 auto;padding:1rem;">
    <div style="margin-bottom:1.5rem;">
        <h1 style="margin-bottom:0.4rem;">❓ Nápověda</h1>
        <p style="color:#6b7280;font-size:0.95rem;margin:0;">
            Klikni na kartičku níže pro detailní popis dané oblasti. Najdeš tu vysvětlení každé fíčry — co dělá, kdo to používá, jak to funguje, případně i návod krok za krokem.
        </p>
    </div>

    <?php if (!empty($flash)) { ?>
        <p style="background:#dbeafe;border:1px solid #93c5fd;padding:0.6rem 0.9rem;border-radius:6px;margin-bottom:1rem;">
            <?= crm_h($flash) ?>
        </p>
    <?php } ?>

    <?php foreach ($groups as $groupId => $group) {
        if ($group['topics'] === []) continue;
    ?>
        <div style="margin-bottom:2rem;">
            <h2 style="font-size:1.15rem;margin:0 0 0.2rem;color:#374151;border-bottom:1px solid #e5e7eb;padding-bottom:0.4rem;">
                <?= crm_h($group['label']) ?>
            </h2>
            <?php if (!empty($group['desc'])) { ?>
                <p style="margin:0 0 0.8rem;color:#6b7280;font-size:0.85rem;">
                    <?= crm_h($group['desc']) ?>
                </p>
            <?php } ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:0.8rem;">
                <?php foreach ($group['topics'] as $tid => $t) { ?>
                    <a href="<?= crm_url('/help/topic?id=' . urlencode($tid)) ?>"
                       style="text-decoration:none;color:inherit;background:#fff;
                              border:1px solid #e5e7eb;border-radius:8px;padding:1rem;
                              display:flex;flex-direction:column;gap:0.4rem;
                              transition:transform 0.15s, box-shadow 0.15s;"
                       onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)';this.style.borderColor='#7e22ce';"
                       onmouseout="this.style.transform='';this.style.boxShadow='';this.style.borderColor='#e5e7eb';">
                        <div style="font-size:1.8rem;line-height:1;">
                            <?= $t['icon'] ?>
                        </div>
                        <div style="font-weight:700;color:#1f2937;font-size:1rem;">
                            <?= crm_h($t['label']) ?>
                        </div>
                        <div style="color:#6b7280;font-size:0.85rem;line-height:1.4;">
                            <?= crm_h($t['short']) ?>
                        </div>
                        <div style="margin-top:auto;color:#7e22ce;font-size:0.78rem;font-weight:600;">
                            Otevřít →
                        </div>
                    </a>
                <?php } ?>
            </div>
        </div>
    <?php } ?>

    <div style="background:#eff6ff;border:1px solid #93c5fd;border-radius:8px;padding:0.9rem 1.1rem;font-size:0.88rem;color:#1e3a8a;">
        💡 <strong>Tip:</strong> Vidíš jen ty návody, které se týkají tvé role.
        Pokud něco potřebuješ vědět, zeptej se majitele firmy nebo napiš na
        <a href="mailto:support@snecinatripu.eu" style="color:#1d4ed8; font-weight:600;">support@snecinatripu.eu</a>.
    </div>
</div>
