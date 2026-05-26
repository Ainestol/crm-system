<?php
declare(strict_types=1);
/** @var array<string,mixed> $topic */
?>

<div style="max-width:1100px;margin:0 auto;padding:1rem;">
    <div style="margin-bottom:1rem;">
        <a href="<?= crm_url('/help') ?>" style="color:#6b7280;text-decoration:none;font-size:0.9rem;">
            ← Zpět na rozcestník nápovědy
        </a>
    </div>

    <h1 style="margin:0 0 0.4rem;"><?= $topic['icon'] ?> <?= crm_h($topic['label']) ?></h1>
    <p style="color:#6b7280;margin:0 0 1.5rem;font-size:0.95rem;"><?= crm_h($topic['short']) ?></p>

    <div style="background:#fef3c7;border:1px solid #fbbf24;border-radius:8px;padding:1.5rem;text-align:center;">
        <div style="font-size:2.5rem;margin-bottom:0.5rem;">📝</div>
        <h3 style="margin:0 0 0.5rem;color:#92400e;">Detail tohoto tématu se připravuje</h3>
        <p style="margin:0;color:#92400e;font-size:0.9rem;">
            Texty pro detailní nápovědu se průběžně doplňují. Pokud máš konkrétní otázku k této oblasti,
            obraď se na vývojáře nebo si dej žádost o popis.
        </p>
        <p style="margin-top:1rem;font-size:0.85rem;color:#92400e;">
            Mezitím — zkus <a href="<?= crm_url('/help') ?>" style="color:#92400e;font-weight:600;">jiné téma z rozcestníku</a>.
        </p>
    </div>
</div>
