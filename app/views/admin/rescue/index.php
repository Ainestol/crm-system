<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $rescues */
/** @var string|null $flash */
/** @var string $csrf */

function rescue_status_badge(array $r): string {
    return match ((string) $r['outcome']) {
        'pending' => '<span style="background:#fef3c7;color:#92400e;padding:0.15rem 0.55rem;border-radius:4px;font-size:0.78rem;">⏳ Pending</span>',
        'success' => '<span style="background:#dcfce7;color:#166534;padding:0.15rem 0.55rem;border-radius:4px;font-size:0.78rem;">✅ Úspěch</span>',
        'failed'  => '<span style="background:#fee2e2;color:#991b1b;padding:0.15rem 0.55rem;border-radius:4px;font-size:0.78rem;">❌ Neúspěch</span>',
        'expired' => '<span style="background:#f3f4f6;color:#6b7280;padding:0.15rem 0.55rem;border-radius:4px;font-size:0.78rem;">⌛ Expirováno</span>',
        default   => '?',
    };
}
?>

<div style="max-width:1300px;margin:0 auto;padding:1rem;">
    <h1 style="margin-bottom:1rem;">🆘 Záchrany leadů</h1>

    <?php if (!empty($flash)) { ?>
        <p style="background:#dbeafe;border:1px solid #93c5fd;padding:0.5rem 0.8rem;border-radius:6px;">
            <?= crm_h($flash) ?>
        </p>
    <?php } ?>

    <?php if ($rescues === []) { ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:2.5rem;text-align:center;color:#6b7280;">
            <div style="font-size:2.5rem;margin-bottom:0.5rem;">🆘</div>
            <h3 style="margin:0 0 0.5rem;color:#374151;">Zatím žádné záchrany</h3>
            <p style="margin:0;font-size:0.9rem;">
                OZ může předat lead na záchranu z detailu kontaktu (tlačítko „🆘 Záchrana").
            </p>
        </div>
    <?php } else {
        // Souhrn
        $sumPending   = 0;
        $sumSuccess   = 0;
        $sumFailed    = 0;
        $sumExpired   = 0;
        $sumOwedToCallers = 0.0;
        $sumPaid          = 0.0;
        $sumClawbackFromOriginalCallers = 0;
        foreach ($rescues as $r) {
            $oc = (string) $r['outcome'];
            if ($oc === 'pending') $sumPending++;
            if ($oc === 'success') $sumSuccess++;
            if ($oc === 'failed')  $sumFailed++;
            if ($oc === 'expired') {
                $sumExpired++;
                if (!empty($r['original_caller_id'])) $sumClawbackFromOriginalCallers++;
            }
            if (!empty($r['bonus_amount'])) {
                if (empty($r['bonus_paid_at'])) {
                    $sumOwedToCallers += (float) $r['bonus_amount'];
                } else {
                    $sumPaid += (float) $r['bonus_amount'];
                }
            }
        }
    ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0.8rem;margin-bottom:1.5rem;">
            <div style="background:#fef3c7;padding:0.8rem;border-radius:6px;">
                <div style="font-size:0.72rem;color:#92400e;text-transform:uppercase;">⏳ Pending</div>
                <div style="font-size:1.6rem;font-weight:700;color:#92400e;"><?= $sumPending ?></div>
            </div>
            <div style="background:#dcfce7;padding:0.8rem;border-radius:6px;">
                <div style="font-size:0.72rem;color:#166534;text-transform:uppercase;">✅ Úspěch</div>
                <div style="font-size:1.6rem;font-weight:700;color:#166534;"><?= $sumSuccess ?></div>
            </div>
            <div style="background:#fee2e2;padding:0.8rem;border-radius:6px;">
                <div style="font-size:0.72rem;color:#991b1b;text-transform:uppercase;">❌ Neúspěch</div>
                <div style="font-size:1.6rem;font-weight:700;color:#991b1b;"><?= $sumFailed ?></div>
            </div>
            <div style="background:#f3f4f6;padding:0.8rem;border-radius:6px;">
                <div style="font-size:0.72rem;color:#6b7280;text-transform:uppercase;">⌛ Expirováno</div>
                <div style="font-size:1.6rem;font-weight:700;color:#6b7280;"><?= $sumExpired ?></div>
                <?php if ($sumClawbackFromOriginalCallers > 0) { ?>
                    <div style="font-size:0.7rem;color:#dc2626;margin-top:0.2rem;font-weight:600;">
                        z toho <?= $sumClawbackFromOriginalCallers ?>× clawback caller
                    </div>
                <?php } ?>
            </div>
            <div style="background:#f3e8ff;padding:0.8rem;border-radius:6px;">
                <div style="font-size:0.72rem;color:#6b21a8;text-transform:uppercase;">💰 Dluží OZ navolávačkám</div>
                <div style="font-size:1.6rem;font-weight:700;color:#6b21a8;"><?= number_format($sumOwedToCallers, 0, ',', ' ') ?> Kč</div>
                <div style="font-size:0.7rem;color:#7e22ce;margin-top:0.2rem;">earned, nevyplaceno</div>
            </div>
            <div style="background:#dcfce7;padding:0.8rem;border-radius:6px;">
                <div style="font-size:0.72rem;color:#166534;text-transform:uppercase;">✓ Už vyplaceno</div>
                <div style="font-size:1.6rem;font-weight:700;color:#166534;"><?= number_format($sumPaid, 0, ',', ' ') ?> Kč</div>
            </div>
        </div>

        <!-- Tabulka -->
        <table style="width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;
                      box-shadow:0 1px 3px rgba(0,0,0,0.05);font-size:0.88rem;">
            <thead style="background:#f3f4f6;">
                <tr>
                    <th style="text-align:left;padding:0.5rem 0.7rem;">Stav</th>
                    <th style="text-align:left;padding:0.5rem 0.7rem;">Firma</th>
                    <th style="text-align:left;padding:0.5rem 0.7rem;">Původní OZ</th>
                    <th style="text-align:left;padding:0.5rem 0.7rem;">→ Komu</th>
                    <th style="text-align:left;padding:0.5rem 0.7rem;">Caller</th>
                    <th style="text-align:left;padding:0.5rem 0.7rem;">Důvod</th>
                    <th style="text-align:left;padding:0.5rem 0.7rem;">Požádáno</th>
                    <th style="text-align:left;padding:0.5rem 0.7rem;">Deadline</th>
                    <th style="text-align:right;padding:0.5rem 0.7rem;">Bonus</th>
                    <th style="text-align:left;padding:0.5rem 0.7rem;">Akce</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rescues as $r) {
                    $hasBonusPending = !empty($r['bonus_amount']) && empty($r['bonus_paid_at']);
                    $isPending       = $r['outcome'] === 'pending';
                    $rowBg = $hasBonusPending ? '#fef3c7' : ($isPending ? '#fff7e6' : '#fff');
                ?>
                    <tr style="border-top:1px solid #f3f4f6;background:<?= $rowBg ?>;">
                        <td style="padding:0.45rem 0.7rem;"><?= rescue_status_badge($r) ?></td>
                        <td style="padding:0.45rem 0.7rem;font-weight:600;">
                            <?= crm_h((string) ($r['firma'] ?? '—')) ?>
                            <div style="font-size:0.75rem;color:#6b7280;">
                                <?= crm_h(crm_region_label((string) ($r['region'] ?? ''))) ?>
                            </div>
                        </td>
                        <td style="padding:0.45rem 0.7rem;">
                            <?= crm_h((string) ($r['original_sales_name'] ?? '?')) ?>
                        </td>
                        <td style="padding:0.45rem 0.7rem;">
                            <?php if (!empty($r['final_sales_name'])) { ?>
                                <strong><?= crm_h((string) $r['final_sales_name']) ?></strong>
                            <?php } elseif (!empty($r['target_sales_name'])) { ?>
                                <?= crm_h((string) $r['target_sales_name']) ?>
                                <span style="color:#9ca3af;font-size:0.78rem;">(plánovaný)</span>
                            <?php } elseif ((int) $r['prefer_original'] === 1) { ?>
                                <span style="color:#9ca3af;font-size:0.78rem;font-style:italic;">↩ zpět původnímu</span>
                            <?php } else { ?>
                                <span style="color:#9ca3af;font-size:0.78rem;font-style:italic;">rotace</span>
                            <?php } ?>
                        </td>
                        <td style="padding:0.45rem 0.7rem;">
                            <?php if (!empty($r['rescued_by_caller_name'])) { ?>
                                <?= crm_h((string) $r['rescued_by_caller_name']) ?>
                            <?php } else { ?>
                                <span style="color:#9ca3af;font-size:0.78rem;">—</span>
                            <?php } ?>
                            <?php if (!empty($r['original_caller_name']) && $r['outcome'] === 'expired') { ?>
                                <div style="font-size:0.72rem;color:#dc2626;margin-top:0.15rem;">
                                    💸 clawback z: <?= crm_h((string) $r['original_caller_name']) ?>
                                </div>
                            <?php } ?>
                        </td>
                        <td style="padding:0.45rem 0.7rem;color:#374151;max-width:200px;font-size:0.82rem;">
                            <?= crm_h(mb_substr((string) $r['reason'], 0, 80)) ?>
                            <?= mb_strlen((string) $r['reason']) > 80 ? '…' : '' ?>
                        </td>
                        <td style="padding:0.45rem 0.7rem;color:#6b7280;font-size:0.82rem;">
                            <?= date('d.m. H:i', strtotime((string) $r['requested_at'])) ?>
                        </td>
                        <td style="padding:0.45rem 0.7rem;color:#6b7280;font-size:0.82rem;">
                            <?php if ($isPending) {
                                $exp = strtotime((string) $r['expires_at']);
                                $now = time();
                                $hoursLeft = max(0, (int) (($exp - $now) / 3600));
                                $color = $hoursLeft < 24 ? '#dc2626' : ($hoursLeft < 72 ? '#f59e0b' : '#6b7280');
                            ?>
                                <span style="color:<?= $color ?>;">
                                    <?= date('d.m. H:i', $exp) ?>
                                    <br><small>(<?= $hoursLeft ?> h)</small>
                                </span>
                            <?php } else { ?>
                                <span style="color:#9ca3af;">—</span>
                            <?php } ?>
                        </td>
                        <td style="padding:0.45rem 0.7rem;text-align:right;">
                            <?php if (!empty($r['bonus_amount'])) {
                                $isPaid = !empty($r['bonus_paid_at']);
                            ?>
                                <strong style="color:<?= $isPaid ? '#16a34a' : '#7e22ce' ?>;font-size:0.95rem;">
                                    <?= number_format((float) $r['bonus_amount'], 0, ',', ' ') ?> Kč
                                </strong>
                                <?php if ($isPaid) { ?>
                                    <div style="font-size:0.7rem;color:#16a34a;">✓ vyplaceno</div>
                                <?php } else { ?>
                                    <div style="font-size:0.7rem;color:#7e22ce;">earned, nevyplaceno</div>
                                <?php } ?>
                            <?php } elseif ($r['outcome'] === 'success') { ?>
                                <span style="font-size:0.78rem;color:#9ca3af;">čeká na podpis</span>
                            <?php } else { ?>
                                <span style="color:#d1d5db;">—</span>
                            <?php } ?>
                        </td>
                        <td style="padding:0.45rem 0.7rem;">
                            <?php if (!empty($r['bonus_amount']) && empty($r['bonus_paid_at'])) { ?>
                                <form method="POST" action="<?= crm_url('/admin/rescue/mark-paid') ?>"
                                      onsubmit="return confirm('Označit bonus <?= number_format((float) $r['bonus_amount'], 0, ',', ' ') ?> Kč jako vyplacený navolávačce <?= crm_h((string) $r['rescued_by_caller_name']) ?>?');"
                                      style="margin:0;">
                                    <input type="hidden" name="<?= crm_csrf_field_name() ?>" value="<?= crm_h($csrf) ?>">
                                    <input type="hidden" name="rescue_id" value="<?= (int) $r['id'] ?>">
                                    <button type="submit"
                                            style="background:#16a34a;color:#fff;border:none;padding:0.3rem 0.7rem;
                                                   border-radius:4px;cursor:pointer;font-size:0.78rem;font-weight:600;">
                                        💰 Vyplaceno
                                    </button>
                                </form>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</div>
