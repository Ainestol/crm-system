<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $items */
/** @var string|null $flash */
/** @var string $csrf */
?>
<div style="max-width:1200px;margin:0 auto;padding:1rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
        <h1 style="margin:0;">🎯 Kampaně</h1>
        <a href="<?= crm_url('/caller') ?>" style="color:#6b7280;text-decoration:none;">← Zpět na pool</a>
    </div>

    <?php if (!empty($flash)) { ?>
        <p style="background:#dbeafe;border:1px solid #93c5fd;padding:0.5rem 0.8rem;border-radius:6px;margin-bottom:1rem;">
            <?= crm_h($flash) ?>
        </p>
    <?php } ?>

    <?php if ($items === []) { ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:2.5rem;text-align:center;color:#6b7280;">
            <div style="font-size:2.5rem;margin-bottom:0.5rem;">🎯</div>
            <h3 style="margin:0 0 0.5rem;color:#374151;">Žádné kampaně</h3>
            <p style="margin:0;font-size:0.9rem;">
                Buď momentálně nejsou žádné sázky / objednávky, nebo nejste přiřazen(a)
                k žádné kampani. Mluvte s admin / majitelem.
            </p>
            <p style="margin-top:1rem;">
                <a href="<?= crm_url('/caller') ?>" style="color:#2563eb;">→ Pokračujte v anonymním poolu</a>
            </p>
        </div>
    <?php } else { ?>

    <?php
    // Filtruj: skryj uzavřené sázky bez čekajících/v práci call-leadů
    // (= cleaning hotov + všechny calls dokončené → není co dělat).
    $visibleItems = array_filter($items, function ($it) {
        $status   = (string) ($it['campaign']['status'] ?? 'open');
        $waiting  = (int) ($it['stats']['waiting']  ?? 0);
        $inflight = (int) ($it['stats']['inflight'] ?? 0);
        return $status === 'open' || $waiting > 0 || $inflight > 0;
    });
    ?>

    <?php if ($visibleItems === []) { ?>
        <!-- Všechny přiřazené sázky jsou hotové → fallthrough na empty state -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:2.5rem;text-align:center;color:#6b7280;">
            <div style="font-size:2.5rem;margin-bottom:0.5rem;">✅</div>
            <h3 style="margin:0 0 0.5rem;color:#374151;">Všechny kampaně dotažené</h3>
            <p style="margin:0;font-size:0.9rem;">
                Žádné call-leady nečekají na provolání. Skvělá práce!
            </p>
            <p style="margin-top:1rem;">
                <a href="<?= crm_url('/caller') ?>" style="color:#2563eb;">→ Pokračujte v anonymním poolu</a>
            </p>
        </div>
    <?php } ?>

    <?php foreach ($visibleItems as $item) {
        $camp     = $item['campaign'];
        $cid      = (int) $camp['id'];
        $tgt      = (int) $camp['target_count'];
        $cln      = (int) $camp['cleaned_count'];
        $pct      = $tgt > 0 ? (int) round($cln * 100 / $tgt) : 0;
        $stats    = $item['stats'];
        $leads    = $item['leads'];
        $recips   = $item['recipients'];
        $campStatus = (string) ($camp['status'] ?? 'open');
        $isClosed   = $campStatus === 'closed';
    ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:1.2rem;margin-bottom:1.5rem;">
            <!-- Header sázky -->
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.8rem;flex-wrap:wrap;gap:0.5rem;">
                <h2 style="margin:0;">
                    🎯 <?= crm_h((string) $camp['name']) ?>
                    <span style="font-size:0.9rem;font-weight:400;color:#6b7280;margin-left:0.5rem;">
                        · <?= crm_h(crm_region_label((string) $camp['region'])) ?>
                    </span>
                    <?php if ($isClosed) { ?>
                        <span style="background:#dbeafe;color:#1e40af;padding:0.15rem 0.55rem;border-radius:4px;
                                     font-size:0.78rem;margin-left:0.4rem;vertical-align:middle;">
                            🔒 Cleaning hotov — zbývá provolat
                        </span>
                    <?php } else { ?>
                        <span style="background:#dcfce7;color:#166534;padding:0.15rem 0.55rem;border-radius:4px;
                                     font-size:0.78rem;margin-left:0.4rem;vertical-align:middle;">
                            ● Aktivní
                        </span>
                    <?php } ?>
                </h2>
                <div style="font-size:0.9rem;color:#6b7280;">
                    Cleaned: <strong><?= $cln ?>/<?= $tgt ?></strong>
                </div>
            </div>

            <?php if (!empty($camp['note'])) { ?>
            <p style="margin:0 0 0.8rem;font-size:0.88rem;color:#6b7280;font-style:italic;">
                📝 <?= crm_h((string) $camp['note']) ?>
            </p>
            <?php } ?>

            <!-- Progress bar celý -->
            <div style="background:#f3f4f6;border-radius:6px;height:14px;overflow:hidden;margin-bottom:0.8rem;">
                <div style="width:<?= $pct ?>%;height:100%;background:linear-gradient(90deg,#f59e0b,#16a34a);"></div>
            </div>

            <!-- Mini stats (jen pro call-typ leady) -->
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0.5rem;margin-bottom:1rem;">
                <div style="background:#fef3c7;padding:0.5rem;border-radius:5px;text-align:center;">
                    <div style="font-size:0.75rem;color:#92400e;text-transform:uppercase;">K provolání</div>
                    <div style="font-size:1.3rem;font-weight:700;color:#92400e;"><?= (int) $stats['waiting'] ?></div>
                </div>
                <div style="background:#dbeafe;padding:0.5rem;border-radius:5px;text-align:center;">
                    <div style="font-size:0.75rem;color:#1e40af;text-transform:uppercase;">V práci</div>
                    <div style="font-size:1.3rem;font-weight:700;color:#1e40af;"><?= (int) $stats['inflight'] ?></div>
                </div>
                <div style="background:#dcfce7;padding:0.5rem;border-radius:5px;text-align:center;">
                    <div style="font-size:0.75rem;color:#166534;text-transform:uppercase;">Výhry</div>
                    <div style="font-size:1.3rem;font-weight:700;color:#166534;"><?= (int) $stats['won'] ?></div>
                </div>
                <div style="background:#fee2e2;padding:0.5rem;border-radius:5px;text-align:center;">
                    <div style="font-size:0.75rem;color:#991b1b;text-transform:uppercase;">Prohry</div>
                    <div style="font-size:1.3rem;font-weight:700;color:#991b1b;"><?= (int) $stats['lost'] ?></div>
                </div>
            </div>

            <!-- Příjemci OZ (rozdělení) -->
            <details style="margin-bottom:1rem;">
                <summary style="cursor:pointer;color:#6b7280;font-size:0.88rem;font-weight:600;">
                    Příjemci OZ (<?= count($recips) ?>)
                </summary>
                <div style="display:flex;flex-wrap:wrap;gap:0.4rem;margin-top:0.5rem;">
                    <?php foreach ($recips as $r) {
                        $rt = (int) $r['target_count'];
                        $rr = (int) $r['received_count'];
                        $rp = $rt > 0 ? (int) round($rr * 100 / $rt) : 0;
                        $isEmail = $r['delivery_type'] === 'email';
                        $bg = $isEmail ? '#f3f4f6' : '#fef3c7';
                    ?>
                        <span style="background:<?= $bg ?>;padding:0.3rem 0.6rem;border-radius:14px;font-size:0.85rem;">
                            <?= $isEmail ? '📧' : '📞' ?>
                            <strong><?= crm_h((string) ($r['oz_name'] ?? '?')) ?></strong>
                            <span style="color:#6b7280;"><?= $rr ?>/<?= $rt ?></span>
                            <?php if (!$isEmail) { ?>
                                <span style="margin-left:0.3rem;color:#9ca3af;font-size:0.78rem;">(call)</span>
                            <?php } else { ?>
                                <span style="margin-left:0.3rem;color:#9ca3af;font-size:0.78rem;">(email — netýká se vás)</span>
                            <?php } ?>
                        </span>
                    <?php } ?>
                </div>
            </details>

            <!-- Tabulka leadů k volání -->
            <?php if ($leads === []) { ?>
                <p style="background:#f9fafb;padding:1rem;border-radius:6px;color:#6b7280;text-align:center;margin:0;">
                    <?php if ((int) $stats['waiting'] === 0 && (int) $stats['inflight'] === 0) { ?>
                        Žádné call-type leady nejsou momentálně připravené.
                        <br><span style="font-size:0.85rem;">Čistička je teprve čistí, nebo už jsou všechny dotažené.</span>
                    <?php } else { ?>
                        V tuto chvíli žádné leady ve stavu READY.
                    <?php } ?>
                </p>
            <?php } else { ?>
                <h3 style="margin:1rem 0 0.5rem;font-size:1rem;">📞 Leady k provolání (<?= count($leads) ?>)</h3>
                <table style="width:100%;border-collapse:collapse;font-size:0.9rem;">
                    <thead>
                        <tr style="background:#f3f4f6;">
                            <th style="text-align:left;padding:0.45rem 0.7rem;width:40px;">Pos</th>
                            <th style="text-align:left;padding:0.45rem 0.7rem;">Firma</th>
                            <th style="text-align:left;padding:0.45rem 0.7rem;">Telefon</th>
                            <th style="text-align:left;padding:0.45rem 0.7rem;width:50px;">Op</th>
                            <th style="text-align:left;padding:0.45rem 0.7rem;">→ OZ (fixně)</th>
                            <th style="text-align:right;padding:0.45rem 0.7rem;width:200px;">Akce</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leads as $l) {
                            $lockedByOther = ((int) ($l['locked_by'] ?? 0) > 0)
                                          && ((int) ($l['locked_by'] ?? 0) !== (int) ($_SESSION['user_id'] ?? 0))
                                          && !empty($l['locked_until'])
                                          && strtotime((string) $l['locked_until']) > time();
                        ?>
                        <tr style="border-top:1px solid #f3f4f6;">
                            <td style="padding:0.4rem 0.7rem;color:#9ca3af;font-family:monospace;"><?= (int) $l['position'] ?></td>
                            <td style="padding:0.4rem 0.7rem;font-weight:600;">
                                <?= crm_h((string) ($l['firma'] ?? '—')) ?>
                            </td>
                            <td style="padding:0.4rem 0.7rem;font-family:monospace;">
                                <?= crm_h((string) ($l['telefon'] ?? '')) ?>
                            </td>
                            <td style="padding:0.4rem 0.7rem;">
                                <span style="background:#e5e7eb;padding:0.1rem 0.4rem;border-radius:3px;font-size:0.78rem;font-weight:600;">
                                    <?= crm_h((string) ($l['operator'] ?? '')) ?>
                                </span>
                            </td>
                            <td style="padding:0.4rem 0.7rem;">
                                <span style="background:#fef3c7;padding:0.15rem 0.5rem;border-radius:4px;font-size:0.85rem;">
                                    🔒 <?= crm_h((string) ($l['oz_name'] ?? '?')) ?>
                                </span>
                            </td>
                            <td style="padding:0.4rem 0.7rem;text-align:right;">
                                <?php if ($lockedByOther) { ?>
                                    <span style="color:#9ca3af;font-size:0.85rem;">🔒 jiná navolávačka</span>
                                <?php } else { ?>
                                    <form method="POST" action="<?= crm_url('/caller/campaigns/lock') ?>"
                                          style="display:inline;margin:0;">
                                        <input type="hidden" name="<?= crm_csrf_field_name() ?>" value="<?= crm_h($csrf) ?>">
                                        <input type="hidden" name="contact_id" value="<?= (int) $l['id'] ?>">
                                        <button type="submit"
                                                style="background:#2563eb;color:#fff;border:none;padding:0.35rem 0.8rem;
                                                       border-radius:5px;cursor:pointer;font-size:0.85rem;font-weight:600;">
                                            📞 Volat
                                        </button>
                                    </form>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>

                <p style="margin:0.8rem 0 0;font-size:0.8rem;color:#6b7280;font-style:italic;">
                    💡 Kliknutím na <strong>📞 Volat</strong> se otevře hlavní pracovní obrazovka s tímto kontaktem.
                    Při výhře (CALLED_OK) bude OZ zafixován na pre-assigned hodnotu výše.
                </p>
            <?php } ?>
        </div>
    <?php } ?>

    <?php } ?>
</div>
