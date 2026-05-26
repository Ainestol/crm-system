<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $items */
/** @var array<string,mixed>|null  $detailItem */
/** @var list<array<string,mixed>> $detailLeads */
/** @var string|null $flash */

// ─────────────────────────────────────────────────────────────────────
// Pomocné funkce pro vykreslení
// ─────────────────────────────────────────────────────────────────────
function ozcampStatusBadge(string $status): string {
    return match ($status) {
        'open'      => '<span style="background:#dcfce7;color:#166534;padding:0.15rem 0.55rem;border-radius:4px;font-size:0.78rem;">● Aktivní</span>',
        'closed'    => '<span style="background:#dbeafe;color:#1e40af;padding:0.15rem 0.55rem;border-radius:4px;font-size:0.78rem;">🔒 Cleaning hotov</span>',
        'cancelled' => '<span style="background:#fee2e2;color:#991b1b;padding:0.15rem 0.55rem;border-radius:4px;font-size:0.78rem;">❌ Zrušená</span>',
        default     => '<span style="background:#f3f4f6;color:#6b7280;padding:0.15rem 0.55rem;border-radius:4px;font-size:0.78rem;">?</span>',
    };
}
function ozcampStavBadge(string $stav): string {
    return '<code style="background:#f3f4f6;padding:0.1rem 0.4rem;border-radius:3px;font-size:0.78rem;">' . htmlspecialchars($stav, ENT_QUOTES, 'UTF-8') . '</code>';
}
?>

<div style="max-width:1200px;margin:0 auto;padding:1rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem;">
        <h1 style="margin:0;">
            <?php if ($detailItem !== null) { ?>
                🎯 <?= crm_h((string) $detailItem['name']) ?>
                <span style="font-size:0.9rem;font-weight:400;color:#6b7280;">
                    · <?= crm_h(crm_region_label((string) $detailItem['region'])) ?>
                </span>
            <?php } else { ?>
                🎯 Moje kampaně
            <?php } ?>
        </h1>
        <div style="display:flex;gap:0.6rem;">
            <?php if ($detailItem !== null) { ?>
                <a href="<?= crm_url('/oz/campaigns') ?>" style="color:#6b7280;text-decoration:none;">← Zpět na seznam</a>
            <?php } ?>
            <a href="<?= crm_url('/oz/queue') ?>" style="color:#2563eb;text-decoration:none;">📬 Příchozí leady →</a>
        </div>
    </div>

    <?php if (!empty($flash)) { ?>
        <p style="background:#dbeafe;border:1px solid #93c5fd;padding:0.5rem 0.8rem;border-radius:6px;">
            <?= crm_h($flash) ?>
        </p>
    <?php } ?>

    <?php if ($items === []) { ?>
        <!-- Empty state -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:2.5rem;text-align:center;color:#6b7280;">
            <div style="font-size:2.5rem;margin-bottom:0.5rem;">🎯</div>
            <h3 style="margin:0 0 0.5rem;color:#374151;">Zatím žádné kampaně</h3>
            <p style="margin:0;font-size:0.9rem;">
                Žádná sázka nebyla pro vás založena. Vraťte se sem, až majitel
                vytvoří kampaň s vámi jako příjemcem.
            </p>
        </div>
    <?php } elseif ($detailItem === null) { ?>
        <!-- ───────── PŘEHLED VŠECH KAMPANÍ ───────── -->

        <!-- Souhrn napříč kampaněmi -->
        <?php
        $sumTarget = 0; $sumReceived = 0; $sumWon = 0; $sumSchuzka = 0; $sumSmlouva = 0;
        foreach ($items as $i) {
            $sumTarget   += $i['my_target'];
            $sumReceived += $i['my_received'];
            $sumWon      += $i['won_call'];
            $sumSchuzka  += $i['schuzka'];
            $sumSmlouva  += $i['smlouva'];
        }
        $totalWinRate  = $sumReceived > 0 ? round($sumWon * 100 / $sumReceived, 1) : 0;
        $totalConvRate = $sumReceived > 0 ? round($sumSmlouva * 100 / $sumReceived, 1) : 0;
        ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:1.2rem;margin-bottom:1.5rem;">
            <div style="font-size:0.78rem;color:#6b7280;text-transform:uppercase;margin-bottom:0.6rem;">Souhrn napříč všemi kampaněmi</div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:0.8rem;">
                <div style="background:#f9fafb;padding:0.7rem;border-radius:6px;text-align:center;">
                    <div style="font-size:0.75rem;color:#6b7280;">Objednáno</div>
                    <div style="font-size:1.4rem;font-weight:700;color:#374151;"><?= $sumTarget ?></div>
                </div>
                <div style="background:#fef3c7;padding:0.7rem;border-radius:6px;text-align:center;">
                    <div style="font-size:0.75rem;color:#92400e;">Cleaned (TM+O2)</div>
                    <div style="font-size:1.4rem;font-weight:700;color:#92400e;"><?= $sumReceived ?></div>
                </div>
                <div style="background:#dcfce7;padding:0.7rem;border-radius:6px;text-align:center;">
                    <div style="font-size:0.75rem;color:#166534;">Výhry (call)</div>
                    <div style="font-size:1.4rem;font-weight:700;color:#166534;"><?= $sumWon ?></div>
                </div>
                <div style="background:#dbeafe;padding:0.7rem;border-radius:6px;text-align:center;">
                    <div style="font-size:0.75rem;color:#1e40af;">Schůzky</div>
                    <div style="font-size:1.4rem;font-weight:700;color:#1e40af;"><?= $sumSchuzka ?></div>
                </div>
                <div style="background:#f3e8ff;padding:0.7rem;border-radius:6px;text-align:center;">
                    <div style="font-size:0.75rem;color:#6b21a8;">Smlouvy</div>
                    <div style="font-size:1.4rem;font-weight:700;color:#6b21a8;"><?= $sumSmlouva ?></div>
                </div>
                <div style="background:#fff;border:1px solid #e5e7eb;padding:0.7rem;border-radius:6px;text-align:center;">
                    <div style="font-size:0.75rem;color:#6b7280;">Úspěšnost</div>
                    <div style="font-size:1.4rem;font-weight:700;color:#16a34a;"><?= $totalWinRate ?>%</div>
                    <div style="font-size:0.68rem;color:#9ca3af;">smlouva: <?= $totalConvRate ?>%</div>
                </div>
            </div>
        </div>

        <!-- Per-kampaň karty -->
        <?php foreach ($items as $it) {
            $myTgt = $it['my_target'];
            $myRcv = $it['my_received'];
            $progressPct = $myTgt > 0 ? min(100, (int) round($myRcv * 100 / $myTgt)) : 0;
            $isEmail = $it['delivery_type'] === 'email';
        ?>
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:1rem;margin-bottom:1rem;">
                <!-- Header -->
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.6rem;flex-wrap:wrap;gap:0.5rem;">
                    <h2 style="margin:0;font-size:1.1rem;">
                        🎯
                        <a href="<?= crm_url('/oz/campaigns?id=' . $it['id']) ?>" style="color:#1f2937;text-decoration:none;">
                            <?= crm_h($it['name']) ?>
                        </a>
                        <span style="font-size:0.85rem;font-weight:400;color:#6b7280;margin-left:0.4rem;">
                            · <?= crm_h(crm_region_label($it['region'])) ?>
                        </span>
                        <?= ozcampStatusBadge($it['status']) ?>
                        <span style="background:<?= $isEmail ? '#f3f4f6' : '#fef3c7' ?>;padding:0.15rem 0.5rem;
                                     border-radius:4px;font-size:0.75rem;margin-left:0.3rem;">
                            <?= $isEmail ? '📧 Email' : '📞 Call' ?>
                        </span>
                    </h2>
                    <div style="font-size:0.85rem;color:#6b7280;">
                        Cleaned: <strong style="color:<?= $progressPct >= 100 ? '#16a34a' : '#92400e' ?>;">
                            <?= $myRcv ?>/<?= $myTgt ?>
                        </strong>
                    </div>
                </div>

                <!-- Progress bar pro cleaning -->
                <div style="background:#f3f4f6;border-radius:6px;height:8px;overflow:hidden;margin-bottom:0.8rem;">
                    <div style="width:<?= $progressPct ?>%;height:100%;background:linear-gradient(90deg,#f59e0b,#16a34a);"></div>
                </div>

                <?php if ($isEmail) { ?>
                    <!-- Email type — jednoduchý přehled -->
                    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:0.6rem;">
                        <div style="background:#f9fafb;padding:0.6rem;border-radius:5px;text-align:center;">
                            <div style="font-size:0.7rem;color:#6b7280;">EMAIL_READY</div>
                            <div style="font-size:1.2rem;font-weight:700;color:#1f2937;"><?= $it['email_ready'] ?></div>
                            <div style="font-size:0.7rem;color:#9ca3af;">k zaslání</div>
                        </div>
                        <div style="background:#f3e8ff;padding:0.6rem;border-radius:5px;text-align:center;">
                            <div style="font-size:0.7rem;color:#6b21a8;">Ve workflow</div>
                            <div style="font-size:1.2rem;font-weight:700;color:#6b21a8;"><?= $it['schuzka'] + $it['smlouva'] ?></div>
                            <div style="font-size:0.7rem;color:#9ca3af;">přijato &amp; v práci</div>
                        </div>
                    </div>
                    <p style="margin:0.6rem 0 0;font-size:0.85rem;color:#6b7280;">
                        💡 Email leady najdete v <a href="<?= crm_url('/oz/email-leads') ?>" style="color:#2563eb;">📧 Email leady</a> (s exportem do XLSX).
                    </p>
                <?php } else {
                    $callPendingL = (int) $it['waiting'] + (int) $it['inflight'];
                    $callWonL     = (int) $it['won_call'];
                    $callLostL    = (int) $it['lost'];
                    $callTotalL   = $callPendingL + $callWonL + $callLostL;
                ?>
                    <!-- Call type — stages (sjednocené Navoláno + OZ tiles) -->
                    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0.5rem;">
                        <!-- NAVOLÁNO -->
                        <div style="background:#fff;border:1px solid #e5e7eb;padding:0.6rem;border-radius:5px;"
                             title="Navoláno celkem: <?= $callTotalL ?> · ✓ <?= $callWonL ?> výher · ✗ <?= $callLostL ?> prohra · ⏳ <?= $callPendingL ?> v práci">
                            <div style="font-size:0.65rem;color:#374151;text-transform:uppercase;">Navoláno</div>
                            <div style="font-size:1.1rem;font-weight:700;color:#1f2937;"><?= $callTotalL ?></div>
                            <div style="display:flex;gap:0.4rem;font-size:0.78rem;margin-top:0.1rem;">
                                <span style="color:#16a34a;font-weight:700;">●<?= $callWonL ?></span>
                                <span style="color:#dc2626;font-weight:700;">●<?= $callLostL ?></span>
                                <span style="color:#f59e0b;font-weight:700;">●<?= $callPendingL ?></span>
                            </div>
                        </div>
                        <!-- SCHŮZKY -->
                        <div style="background:#dbeafe;padding:0.6rem;border-radius:5px;cursor:help;"
                             title="OZ stage SCHŮZKA / NABÍDKA / ŠANCE · 📅 callbacky: <?= (int) $it['wf_callback'] ?> · ⚙ v práci: <?= (int) $it['in_processing'] ?>">
                            <div style="font-size:0.65rem;color:#1e40af;text-transform:uppercase;">Schůzky 💼</div>
                            <div style="font-size:1.1rem;font-weight:700;color:#1e40af;"><?= $it['schuzka'] ?></div>
                            <?php if ($it['wf_callback'] > 0 || $it['in_processing'] > 0) { ?>
                                <div style="font-size:0.7rem;color:#1e3a8a;">
                                    <?php if ($it['wf_callback'] > 0) { ?>📅<?= $it['wf_callback'] ?> <?php } ?>
                                    <?php if ($it['in_processing'] > 0) { ?>⚙<?= $it['in_processing'] ?><?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                        <!-- SMLOUVY -->
                        <div style="background:#f3e8ff;padding:0.6rem;border-radius:5px;cursor:help;"
                             title="Smlouvy · ⏳ ke zpracování BO: <?= (int) $it['bo_pending'] ?> · ✅ podepsané: <?= (int) $it['podpis'] ?>">
                            <div style="font-size:0.65rem;color:#6b21a8;text-transform:uppercase;">Smlouvy 📄</div>
                            <div style="font-size:1.1rem;font-weight:700;color:#6b21a8;"><?= $it['smlouva'] ?></div>
                            <?php if ($it['bo_pending'] > 0 || $it['podpis'] > 0) { ?>
                                <div style="font-size:0.7rem;color:#7e22ce;">
                                    <?php if ($it['bo_pending'] > 0) { ?>⏳<?= $it['bo_pending'] ?> <?php } ?>
                                    <?php if ($it['podpis'] > 0) { ?>✅<?= $it['podpis'] ?><?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                        <!-- ÚSPĚŠNOST -->
                        <div style="background:#fff;border:1px solid #e5e7eb;padding:0.6rem;border-radius:5px;">
                            <div style="font-size:0.65rem;color:#6b7280;text-transform:uppercase;">Úspěšnost</div>
                            <div style="font-size:1.1rem;font-weight:700;color:#16a34a;"><?= $it['win_rate'] ?>%</div>
                            <div style="font-size:0.7rem;color:#9ca3af;">smlouva: <?= $it['conv_rate'] ?>%</div>
                        </div>
                    </div>

                    <div style="margin-top:0.6rem;text-align:right;">
                        <a href="<?= crm_url('/oz/campaigns?id=' . $it['id']) ?>"
                           style="color:#2563eb;text-decoration:none;font-weight:600;font-size:0.88rem;">
                            Detail →
                        </a>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>

    <?php } else { ?>
        <!-- ───────── DETAIL JEDNÉ KAMPANĚ ───────── -->
        <?php
        $it = $detailItem;
        $myTgt = (int) $it['my_target'];
        $myRcv = (int) $it['my_received'];
        $progressPct = $myTgt > 0 ? min(100, (int) round($myRcv * 100 / $myTgt)) : 0;
        $isEmail = $it['delivery_type'] === 'email';
        ?>

        <!-- Header karta -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:1.2rem;margin-bottom:1rem;">
            <div style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap;margin-bottom:0.8rem;">
                <?= ozcampStatusBadge($it['status']) ?>
                <span style="background:<?= $isEmail ? '#f3f4f6' : '#fef3c7' ?>;padding:0.2rem 0.6rem;border-radius:4px;font-size:0.85rem;">
                    <?= $isEmail ? '📧 Email kampaň' : '📞 Call kampaň' ?>
                </span>
                <span style="color:#6b7280;font-size:0.85rem;">
                    Vytvořeno <?= date('d.m.Y H:i', strtotime((string) $it['created_at'])) ?>
                </span>
            </div>

            <?php if (!empty($it['note'])) { ?>
                <p style="background:#fef3c7;padding:0.5rem 0.8rem;border-radius:5px;color:#92400e;margin:0 0 0.8rem;font-size:0.88rem;">
                    📝 <?= crm_h((string) $it['note']) ?>
                </p>
            <?php } ?>

            <!-- Stats grid -->
            <?php
            // Pre-compute call-results tile data (jen pro call kampaně)
            // Třídy: zelená = výhra (předáno OZ), červená = navolávačka odmítla,
            //        žlutá = čeká nebo právě v práci u navolávačky
            $callWaiting  = (int) $it['waiting'];   // READY
            $callInflight = (int) $it['inflight'];  // ASSIGNED/NEDOVOLANO/CALLBACK
            $callWon      = (int) $it['won_call'];  // CALLED_OK
            $callLost     = (int) $it['lost'];      // NEZAJEM/CALLED_BAD/IZOLACE/CHYBNY
            $callPending  = $callWaiting + $callInflight; // ještě se zpracovává
            ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0.8rem;">
                <div style="background:#f9fafb;padding:0.8rem;border-radius:6px;">
                    <div style="font-size:0.75rem;color:#6b7280;text-transform:uppercase;">Objednáno pro mě</div>
                    <div style="font-size:1.6rem;font-weight:700;"><?= $myTgt ?></div>
                </div>
                <div style="background:#fef3c7;padding:0.8rem;border-radius:6px;"
                     title="Kontakty, které čistička vyhodnotila jako TM nebo O2 (= použitelné) a jsou určené pro tebe v této kampani.">
                    <div style="font-size:0.75rem;color:#92400e;text-transform:uppercase;">Cleaned (TM+O2)</div>
                    <div style="font-size:1.6rem;font-weight:700;color:#92400e;"><?= $myRcv ?></div>
                    <div style="font-size:0.75rem;color:#9ca3af;"><?= $progressPct ?>% z objednávky</div>
                </div>

                <?php if (!$isEmail) {
                    $callTotal = $callPending + $callWon + $callLost;
                ?>
                <!-- ── Navoláno (call výsledky od navolávačky) ── -->
                <div style="background:#fff;border:1px solid #e5e7eb;padding:0.8rem;border-radius:6px;"
                     title="Stav volání: zelená = předáno tobě (CALLED_OK), červená = navolávačka vyhodnotila jako nezájem nebo nedovolala, žlutá = zatím se zpracovává.">
                    <div style="font-size:0.75rem;color:#374151;text-transform:uppercase;">Navoláno</div>
                    <div style="font-size:1.6rem;font-weight:700;color:#1f2937;"><?= $callTotal ?></div>
                    <div style="display:flex;gap:0.5rem;font-size:0.85rem;margin-top:0.3rem;align-items:center;">
                        <span title="Předáno OZ (CALLED_OK)" style="color:#16a34a;font-weight:700;">
                            ● <?= $callWon ?>
                        </span>
                        <span title="Odmítnuto (NEZAJEM / nedovoláno / izolace)" style="color:#dc2626;font-weight:700;">
                            ● <?= $callLost ?>
                        </span>
                        <span title="Čeká nebo v práci u navolávačky" style="color:#f59e0b;font-weight:700;">
                            ● <?= $callPending ?>
                        </span>
                    </div>
                    <?php if ($callTotal > 0) { ?>
                        <!-- Mini bar -->
                        <div style="display:flex;height:5px;border-radius:3px;overflow:hidden;margin-top:0.4rem;background:#f3f4f6;">
                            <?php if ($callWon > 0) { ?>
                                <div style="width:<?= ($callWon / $callTotal) * 100 ?>%;background:#16a34a;"></div>
                            <?php } if ($callLost > 0) { ?>
                                <div style="width:<?= ($callLost / $callTotal) * 100 ?>%;background:#dc2626;"></div>
                            <?php } if ($callPending > 0) { ?>
                                <div style="width:<?= ($callPending / $callTotal) * 100 ?>%;background:#f59e0b;"></div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
                <?php } ?>

                <!-- Schůzky — tooltip s rozkladem -->
                <?php
                $schuzkaTooltip = 'OZ ve fázi schůzka / nabídka / šance. ';
                if ($it['wf_callback'] > 0) {
                    $schuzkaTooltip .= 'Callbacky nastavené tebou: ' . $it['wf_callback'] . '. ';
                }
                if ($it['in_processing'] > 0) {
                    $schuzkaTooltip .= 'V práci (NOVÉ / ZPRACOVÁVÁ): ' . $it['in_processing'] . '.';
                }
                ?>
                <div style="background:#dbeafe;padding:0.8rem;border-radius:6px;cursor:help;"
                     title="<?= crm_h($schuzkaTooltip) ?>">
                    <div style="font-size:0.75rem;color:#1e40af;text-transform:uppercase;">Schůzky 💼</div>
                    <div style="font-size:1.6rem;font-weight:700;color:#1e40af;"><?= $it['schuzka'] ?></div>
                    <?php if ($it['wf_callback'] > 0 || $it['in_processing'] > 0) { ?>
                        <div style="font-size:0.72rem;color:#1e3a8a;margin-top:0.2rem;">
                            <?php if ($it['wf_callback'] > 0) { ?>📅 cb: <?= $it['wf_callback'] ?> <?php } ?>
                            <?php if ($it['in_processing'] > 0) { ?>⚙ <?= $it['in_processing'] ?><?php } ?>
                        </div>
                    <?php } ?>
                </div>

                <!-- Smlouvy — tooltip s rozkladem -->
                <?php
                $smlouvaTooltip = 'Celkem ve fázi smlouva. ';
                if ($it['bo_pending'] > 0) {
                    $smlouvaTooltip .= 'Ke zpracování BO (podpis ještě nepotvrzen): ' . $it['bo_pending'] . '. ';
                }
                if ($it['podpis'] > 0) {
                    $smlouvaTooltip .= 'Podepsané a uzavřené: ' . $it['podpis'] . '.';
                }
                ?>
                <div style="background:#f3e8ff;padding:0.8rem;border-radius:6px;cursor:help;"
                     title="<?= crm_h($smlouvaTooltip) ?>">
                    <div style="font-size:0.75rem;color:#6b21a8;text-transform:uppercase;">Smlouvy 📄</div>
                    <div style="font-size:1.6rem;font-weight:700;color:#6b21a8;"><?= $it['smlouva'] ?></div>
                    <div style="font-size:0.72rem;color:#7e22ce;margin-top:0.2rem;">
                        <?php if ($it['bo_pending'] > 0) { ?>⏳ BO: <?= $it['bo_pending'] ?> <?php } ?>
                        <?php if ($it['podpis'] > 0) { ?>✅ <?= $it['podpis'] ?><?php } ?>
                        <?php if ($it['bo_pending'] === 0 && $it['podpis'] === 0) { ?>konverze: <?= $it['conv_rate'] ?>%<?php } ?>
                    </div>
                </div>
            </div>

            <!-- Progress bar -->
            <div style="margin-top:0.8rem;background:#f3f4f6;border-radius:6px;height:14px;overflow:hidden;">
                <div style="width:<?= $progressPct ?>%;height:100%;background:linear-gradient(90deg,#f59e0b,#16a34a);"></div>
            </div>
        </div>

        <!-- Detail leadů -->
        <h2 style="margin:1.5rem 0 0.6rem;">Leady této kampaně (<?= count($detailLeads) ?>)</h2>
        <?php if ($detailLeads === []) { ?>
            <p style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:1.5rem;color:#6b7280;text-align:center;">
                Žádné leady — čistička je teprve hledá.
            </p>
        <?php } else { ?>
            <table style="width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;
                          box-shadow:0 1px 3px rgba(0,0,0,0.05);font-size:0.9rem;">
                <thead style="background:#f3f4f6;">
                    <tr>
                        <th style="text-align:left;padding:0.5rem 0.7rem;">Pos</th>
                        <th style="text-align:left;padding:0.5rem 0.7rem;">Firma</th>
                        <th style="text-align:left;padding:0.5rem 0.7rem;">Telefon</th>
                        <th style="text-align:left;padding:0.5rem 0.7rem;">Region</th>
                        <th style="text-align:left;padding:0.5rem 0.7rem;">Stav</th>
                        <th style="text-align:left;padding:0.5rem 0.7rem;">Workflow</th>
                        <th style="text-align:right;padding:0.5rem 0.7rem;">Vyčištěno</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detailLeads as $l) { ?>
                        <tr style="border-top:1px solid #f3f4f6;">
                            <td style="padding:0.4rem 0.7rem;color:#9ca3af;font-family:monospace;"><?= (int) $l['position'] ?></td>
                            <td style="padding:0.4rem 0.7rem;font-weight:600;">
                                <a href="<?= crm_url('/oz/contact?id=' . (int) $l['contact_id']) ?>"
                                   style="color:#1f2937;text-decoration:none;">
                                    <?= crm_h((string) ($l['firma'] ?? '—')) ?>
                                </a>
                            </td>
                            <td style="padding:0.4rem 0.7rem;font-family:monospace;color:#6b7280;">
                                <?= crm_h((string) ($l['telefon'] ?? '')) ?>
                            </td>
                            <td style="padding:0.4rem 0.7rem;">
                                <?= crm_h(crm_region_label((string) ($l['region'] ?? ''))) ?>
                            </td>
                            <td style="padding:0.4rem 0.7rem;"><?= ozcampStavBadge((string) ($l['stav'] ?? '?')) ?></td>
                            <td style="padding:0.4rem 0.7rem;color:#6b21a8;font-weight:600;">
                                <?= !empty($l['wf_stav']) ? crm_h((string) $l['wf_stav']) : '<span style="color:#d1d5db;font-weight:400;">—</span>' ?>
                            </td>
                            <td style="padding:0.4rem 0.7rem;text-align:right;color:#9ca3af;font-size:0.85rem;">
                                <?= !empty($l['cleaned_at']) ? date('d.m. H:i', strtotime((string) $l['cleaned_at'])) : '' ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } ?>

    <?php } ?>
</div>
