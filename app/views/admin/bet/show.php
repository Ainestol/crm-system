<?php
declare(strict_types=1);
/** @var array<string,mixed>       $campaign */
/** @var list<array<string,mixed>> $recipients */
/** @var list<array<string,mixed>> $sampleLeads */
/** @var string|null               $flash */
/** @var string                    $csrf */

$id     = (int) $campaign['id'];
$tgt    = (int) $campaign['target_count'];
$cln    = (int) $campaign['cleaned_count'];
$pct    = $tgt > 0 ? (int) round($cln * 100 / $tgt) : 0;
$status = (string) $campaign['status'];
$isOpen = $status === 'open';
?>
<div style="max-width:1100px;margin:0 auto;padding:1rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
        <h1 style="margin:0;">🎯 <?= crm_h((string) $campaign['name']) ?></h1>
        <a href="<?= crm_url('/admin/bet') ?>" style="color:#6b7280;text-decoration:none;">← Zpět na seznam</a>
    </div>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info" style="background:#dbeafe;border:1px solid #93c5fd;padding:0.5rem 0.8rem;border-radius:6px;">
            <?= crm_h($flash) ?>
        </p>
    <?php } ?>

    <!-- ── Souhrn ── -->
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:1.2rem;margin-bottom:1rem;">
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1rem;">
            <div>
                <div style="font-size:0.78rem;color:#6b7280;text-transform:uppercase;">Stav</div>
                <div style="font-size:1.1rem;font-weight:700;margin-top:0.2rem;">
                    <?php if ($isOpen) { ?>
                        <span style="background:#dcfce7;color:#166534;padding:0.15rem 0.6rem;border-radius:4px;">Aktivní</span>
                    <?php } elseif ($status === 'closed') { ?>
                        <span style="background:#dbeafe;color:#1e40af;padding:0.15rem 0.6rem;border-radius:4px;">Uzavřená</span>
                    <?php } else { ?>
                        <span style="background:#fee2e2;color:#991b1b;padding:0.15rem 0.6rem;border-radius:4px;">Zrušená</span>
                    <?php } ?>
                </div>
            </div>
            <div>
                <div style="font-size:0.78rem;color:#6b7280;text-transform:uppercase;">Kraj</div>
                <div style="font-size:1.1rem;font-weight:700;margin-top:0.2rem;">
                    <?= crm_h(crm_region_label((string) $campaign['region'])) ?>
                </div>
            </div>
            <div>
                <div style="font-size:0.78rem;color:#6b7280;text-transform:uppercase;">Vyčištěno</div>
                <div style="font-size:1.1rem;font-weight:700;margin-top:0.2rem;color:#16a34a;">
                    <?= $cln ?> / <?= $tgt ?>
                </div>
            </div>
            <div>
                <div style="font-size:0.78rem;color:#6b7280;text-transform:uppercase;">Vytvořeno</div>
                <div style="font-size:0.95rem;margin-top:0.2rem;">
                    <?= date('d.m.Y H:i', strtotime((string) $campaign['created_at'])) ?>
                    <?php if (!empty($campaign['creator_name'])) { ?>
                        <br><span style="font-size:0.78rem;color:#6b7280;"><?= crm_h((string) $campaign['creator_name']) ?></span>
                    <?php } ?>
                </div>
            </div>
        </div>

        <!-- Progress bar -->
        <div style="background:#f3f4f6;border-radius:6px;height:18px;overflow:hidden;">
            <div style="width:<?= $pct ?>%;height:100%;background:linear-gradient(90deg,#f59e0b,#16a34a);
                        transition:width 0.4s;display:flex;align-items:center;justify-content:flex-end;padding-right:0.5rem;
                        color:#fff;font-size:0.8rem;font-weight:700;">
                <?= $pct ?>%
            </div>
        </div>

        <?php if (!empty($campaign['note'])) { ?>
        <p style="margin-top:0.8rem;font-size:0.88rem;color:#6b7280;font-style:italic;">
            📝 <?= crm_h((string) $campaign['note']) ?>
        </p>
        <?php } ?>

        <?php if ($isOpen) { ?>
        <div style="margin-top:1rem;display:flex;gap:0.6rem;">
            <form method="POST" action="<?= crm_url('/admin/bet/close') ?>"
                  onsubmit="return confirm('Opravdu uzavřít sázku? Po uzavření se neaccept dalších kontaktů.');"
                  style="display:inline;">
                <input type="hidden" name="<?= crm_csrf_field_name() ?>" value="<?= crm_h($csrf) ?>">
                <input type="hidden" name="id" value="<?= $id ?>">
                <button type="submit"
                        style="background:#2563eb;color:#fff;border:none;padding:0.5rem 1rem;
                               border-radius:5px;cursor:pointer;font-weight:600;">
                    ✅ Uzavřít sázku
                </button>
            </form>
            <form method="POST" action="<?= crm_url('/admin/bet/cancel') ?>"
                  onsubmit="return confirm('Opravdu ZRUŠIT sázku? Kontakty, které už byly přiřazeny, zůstanou OZ.');"
                  style="display:inline;">
                <input type="hidden" name="<?= crm_csrf_field_name() ?>" value="<?= crm_h($csrf) ?>">
                <input type="hidden" name="id" value="<?= $id ?>">
                <button type="submit"
                        style="background:#dc2626;color:#fff;border:none;padding:0.5rem 1rem;
                               border-radius:5px;cursor:pointer;font-weight:600;">
                    ❌ Zrušit sázku
                </button>
            </form>
        </div>
        <?php } else { ?>
        <p style="margin-top:1rem;color:#6b7280;font-size:0.88rem;">
            <?= $status === 'closed' ? '🔒 Uzavřena' : '❌ Zrušena' ?>
            <?= !empty($campaign['closed_at']) ? ' · ' . date('d.m.Y H:i', strtotime((string) $campaign['closed_at'])) : '' ?>
        </p>
        <?php } ?>
    </div>

    <!-- ── Příjemci ── -->
    <h2 style="margin:1.5rem 0 0.6rem;">Příjemci</h2>
    <table style="width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;
                  box-shadow:0 1px 3px rgba(0,0,0,0.05);">
        <thead style="background:#f3f4f6;">
            <tr>
                <th style="text-align:left;padding:0.55rem 0.8rem;">#</th>
                <th style="text-align:left;padding:0.55rem 0.8rem;">OZ</th>
                <th style="text-align:left;padding:0.55rem 0.8rem;">Typ</th>
                <th style="text-align:right;padding:0.55rem 0.8rem;">Cíl</th>
                <th style="text-align:right;padding:0.55rem 0.8rem;">Dostal</th>
                <th style="text-align:right;padding:0.55rem 0.8rem;">Progress</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recipients as $r) {
                $rTgt = (int) $r['target_count'];
                $rRcv = (int) $r['received_count'];
                $rPct = $rTgt > 0 ? (int) round($rRcv * 100 / $rTgt) : 0;
            ?>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="padding:0.5rem 0.8rem;font-weight:700;color:#6b7280;"><?= (int) $r['sort_order'] ?>.</td>
                <td style="padding:0.5rem 0.8rem;font-weight:600;"><?= crm_h((string) ($r['oz_name'] ?? '?')) ?></td>
                <td style="padding:0.5rem 0.8rem;">
                    <?= $r['delivery_type'] === 'email' ? '📧 Email' : '📞 Call' ?>
                </td>
                <td style="padding:0.5rem 0.8rem;text-align:right;font-family:monospace;"><?= $rTgt ?></td>
                <td style="padding:0.5rem 0.8rem;text-align:right;font-family:monospace;
                           color:<?= $rPct >= 100 ? '#16a34a' : '#6b7280' ?>;font-weight:600;">
                    <?= $rRcv ?>
                </td>
                <td style="padding:0.5rem 0.8rem;text-align:right;">
                    <div style="background:#f3f4f6;border-radius:3px;height:6px;width:120px;display:inline-block;overflow:hidden;">
                        <div style="width:<?= $rPct ?>%;height:100%;background:#16a34a;"></div>
                    </div>
                    <span style="margin-left:0.4rem;font-size:0.85rem;"><?= $rPct ?>%</span>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>

    <!-- ── Posledních 50 leadů ── -->
    <?php if ($sampleLeads !== []) { ?>
    <h2 style="margin:1.5rem 0 0.6rem;">Posledních <?= count($sampleLeads) ?> leadů</h2>
    <table style="width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;
                  box-shadow:0 1px 3px rgba(0,0,0,0.05);font-size:0.9rem;">
        <thead style="background:#f3f4f6;">
            <tr>
                <th style="text-align:left;padding:0.5rem 0.7rem;">Pos</th>
                <th style="text-align:left;padding:0.5rem 0.7rem;">Firma</th>
                <th style="text-align:left;padding:0.5rem 0.7rem;">Op</th>
                <th style="text-align:left;padding:0.5rem 0.7rem;">Příjemce</th>
                <th style="text-align:left;padding:0.5rem 0.7rem;">Typ</th>
                <th style="text-align:left;padding:0.5rem 0.7rem;">Stav</th>
                <th style="text-align:left;padding:0.5rem 0.7rem;">Vyčištěno</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sampleLeads as $l) { ?>
            <tr style="border-top:1px solid #f3f4f6;">
                <td style="padding:0.4rem 0.7rem;color:#9ca3af;font-family:monospace;"><?= (int) $l['position'] ?></td>
                <td style="padding:0.4rem 0.7rem;font-weight:600;"><?= crm_h((string) ($l['firma'] ?? '—')) ?></td>
                <td style="padding:0.4rem 0.7rem;"><?= crm_h((string) ($l['operator'] ?? '')) ?></td>
                <td style="padding:0.4rem 0.7rem;"><?= crm_h((string) ($l['recipient_name'] ?? '?')) ?></td>
                <td style="padding:0.4rem 0.7rem;"><?= $l['delivery_type'] === 'email' ? '📧' : '📞' ?></td>
                <td style="padding:0.4rem 0.7rem;">
                    <span style="background:#f3f4f6;padding:0.1rem 0.4rem;border-radius:3px;font-size:0.78rem;font-family:monospace;">
                        <?= crm_h((string) ($l['stav'] ?? '?')) ?>
                    </span>
                </td>
                <td style="padding:0.4rem 0.7rem;color:#6b7280;font-size:0.85rem;">
                    <?= date('d.m. H:i', strtotime((string) $l['cleaned_at'])) ?>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
    <?php } ?>
</div>
