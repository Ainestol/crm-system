<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $leads */
/** @var string|null $flash */

$cnt = count($leads);
?>
<div style="max-width:1200px;margin:0 auto;padding:1rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
        <h1 style="margin:0;">📧 Email leady</h1>
        <?php if ($cnt > 0) { ?>
        <a href="<?= crm_url('/oz/email-leads/export') ?>"
           style="background:#16a34a;color:#fff;padding:0.55rem 1.1rem;border-radius:6px;
                  text-decoration:none;font-weight:600;">
            ⬇ Export do Excelu (<?= $cnt ?>)
        </a>
        <?php } ?>
    </div>

    <?php if (!empty($flash)) { ?>
        <p style="background:#dbeafe;border:1px solid #93c5fd;padding:0.5rem 0.8rem;border-radius:6px;">
            <?= crm_h($flash) ?>
        </p>
    <?php } ?>

    <p style="background:#fef3c7;border:1px solid #fbbf24;padding:0.6rem 0.9rem;border-radius:6px;font-size:0.9rem;">
        ℹ Leady ze sázek typu <strong>📧 Email</strong>. Tyto kontakty přeskočily caller pool
        — patří přímo tobě pro email kampaň. Export do XLSX otevíráš dvojklikem v Excelu
        (nebo importuješ do email marketingového nástroje).
    </p>

    <?php if ($cnt === 0) { ?>
        <p style="text-align:center;color:#9ca3af;padding:2.5rem;font-style:italic;
                  border:2px dashed #d1d5db;border-radius:8px;margin-top:1rem;">
            Žádné email leady. Po dokončení sázky se zde objeví automaticky.
        </p>
    <?php } else { ?>
        <table style="width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;
                      box-shadow:0 1px 3px rgba(0,0,0,0.05);font-size:0.9rem;margin-top:1rem;">
            <thead style="background:#f3f4f6;">
                <tr>
                    <th style="text-align:left;padding:0.55rem 0.75rem;">#</th>
                    <th style="text-align:left;padding:0.55rem 0.75rem;">Firma</th>
                    <th style="text-align:left;padding:0.55rem 0.75rem;">IČO</th>
                    <th style="text-align:left;padding:0.55rem 0.75rem;">Email</th>
                    <th style="text-align:left;padding:0.55rem 0.75rem;">Telefon</th>
                    <th style="text-align:left;padding:0.55rem 0.75rem;">Kraj</th>
                    <th style="text-align:left;padding:0.55rem 0.75rem;">Op</th>
                    <th style="text-align:left;padding:0.55rem 0.75rem;">Sázka</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leads as $i => $l) { ?>
                <tr style="border-top:1px solid #f3f4f6;">
                    <td style="padding:0.45rem 0.75rem;color:#9ca3af;font-family:monospace;"><?= $i + 1 ?></td>
                    <td style="padding:0.45rem 0.75rem;font-weight:600;"><?= crm_h((string) ($l['firma'] ?? '—')) ?></td>
                    <td style="padding:0.45rem 0.75rem;font-family:monospace;font-size:0.85rem;"><?= crm_h((string) ($l['ico'] ?? '')) ?></td>
                    <td style="padding:0.45rem 0.75rem;color:#2563eb;"><?= crm_h((string) ($l['email'] ?? '')) ?></td>
                    <td style="padding:0.45rem 0.75rem;font-family:monospace;font-size:0.85rem;"><?= crm_h((string) ($l['telefon'] ?? '')) ?></td>
                    <td style="padding:0.45rem 0.75rem;font-size:0.85rem;"><?= crm_h(crm_region_label((string) ($l['region'] ?? ''))) ?></td>
                    <td style="padding:0.45rem 0.75rem;">
                        <span style="background:#f3f4f6;padding:0.1rem 0.4rem;border-radius:3px;font-size:0.8rem;">
                            <?= crm_h((string) ($l['operator'] ?? '')) ?>
                        </span>
                    </td>
                    <td style="padding:0.45rem 0.75rem;font-size:0.8rem;color:#6b7280;">
                        <?php if (!empty($l['campaign_name'])) { ?>
                            <a href="<?= crm_url('/admin/bet/show?id=' . (int) ($l['campaign_id'] ?? 0)) ?>"
                               style="color:#6b7280;text-decoration:underline;">
                                <?= crm_h((string) $l['campaign_name']) ?>
                            </a>
                        <?php } else { ?>
                            —
                        <?php } ?>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</div>
