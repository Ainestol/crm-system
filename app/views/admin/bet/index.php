<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $campaigns */
/** @var string|null $flash */
?>
<div style="max-width:1100px;margin:0 auto;padding:1rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
        <h1 style="margin:0;">🎯 Sázky</h1>
        <a href="<?= crm_url('/admin/bet/new') ?>" class="btn btn-primary"
           style="background:#16a34a;color:#fff;padding:0.55rem 1.1rem;border-radius:6px;text-decoration:none;font-weight:600;">
            ➕ Nová sázka
        </a>
    </div>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info" style="background:#dbeafe;border:1px solid #93c5fd;padding:0.5rem 0.8rem;border-radius:6px;">
            <?= crm_h($flash) ?>
        </p>
    <?php } ?>

    <?php if ($campaigns === []) { ?>
        <p style="text-align:center;color:#9ca3af;padding:2rem;font-style:italic;
                  border:2px dashed #d1d5db;border-radius:8px;">
            Žádné sázky. Klikni na „➕ Nová sázka" pro vytvoření.
        </p>
    <?php } else { ?>
        <table style="width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;
                      box-shadow:0 1px 3px rgba(0,0,0,0.05);">
            <thead style="background:#f3f4f6;">
                <tr>
                    <th style="text-align:left;padding:0.6rem 0.8rem;">#</th>
                    <th style="text-align:left;padding:0.6rem 0.8rem;">Název</th>
                    <th style="text-align:left;padding:0.6rem 0.8rem;">Kraj</th>
                    <th style="text-align:right;padding:0.6rem 0.8rem;">Progress</th>
                    <th style="text-align:center;padding:0.6rem 0.8rem;">Stav</th>
                    <th style="text-align:left;padding:0.6rem 0.8rem;">Vytvořeno</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($campaigns as $c) {
                    $id     = (int) $c['id'];
                    $tgt    = (int) $c['target_count'];
                    $cln    = (int) $c['cleaned_count'];
                    $pct    = $tgt > 0 ? (int) round($cln * 100 / $tgt) : 0;
                    $status = (string) $c['status'];
                    $statusColor = match ($status) {
                        'open'      => ['#dcfce7', '#166534', 'Aktivní'],
                        'closed'    => ['#dbeafe', '#1e40af', 'Uzavřená'],
                        'cancelled' => ['#fee2e2', '#991b1b', 'Zrušená'],
                        default     => ['#f3f4f6', '#374151', $status],
                    };
                ?>
                <tr style="border-top:1px solid #f3f4f6;">
                    <td style="padding:0.55rem 0.8rem;color:#9ca3af;"><?= $id ?></td>
                    <td style="padding:0.55rem 0.8rem;font-weight:600;"><?= crm_h((string) $c['name']) ?></td>
                    <td style="padding:0.55rem 0.8rem;"><?= crm_h(crm_region_label((string) $c['region'])) ?></td>
                    <td style="padding:0.55rem 0.8rem;text-align:right;font-family:monospace;">
                        <?= $cln ?> / <?= $tgt ?>
                        <div style="background:#e5e7eb;border-radius:3px;height:5px;margin-top:3px;overflow:hidden;width:120px;display:inline-block;">
                            <div style="width:<?= $pct ?>%;height:100%;background:#16a34a;"></div>
                        </div>
                    </td>
                    <td style="padding:0.55rem 0.8rem;text-align:center;">
                        <span style="background:<?= $statusColor[0] ?>;color:<?= $statusColor[1] ?>;
                                     padding:0.2rem 0.6rem;border-radius:4px;font-size:0.85rem;font-weight:600;">
                            <?= $statusColor[2] ?>
                        </span>
                    </td>
                    <td style="padding:0.55rem 0.8rem;font-size:0.85rem;color:#6b7280;">
                        <?= date('d.m.Y H:i', strtotime((string) $c['created_at'])) ?>
                        <?php if (!empty($c['creator_name'])) { ?>
                            <br><span style="font-size:0.78rem;"><?= crm_h((string) $c['creator_name']) ?></span>
                        <?php } ?>
                    </td>
                    <td style="padding:0.55rem 0.8rem;">
                        <a href="<?= crm_url('/admin/bet/show?id=' . $id) ?>"
                           style="color:#2563eb;text-decoration:none;font-weight:600;">Detail →</a>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</div>
