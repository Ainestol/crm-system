<?php
declare(strict_types=1);
/** @var array<string,int> $stats */
/** @var int $ratioFirma */
/** @var int $ratioOsvc */
/** @var bool $autoMixEnabled */
/** @var string|null $flash */
/** @var string $csrf */

$totalUnmixed = $stats['unmixed_firma'] + $stats['unmixed_osvc'] + $stats['unmixed_unknown'];
$canMix = $totalUnmixed > 0;
?>

<div style="max-width:1000px;margin:0 auto;padding:1rem;">
    <h1 style="margin-bottom:0.4rem;">🎲 Mix kontaktů — cyklus <?= $ratioFirma + $ratioOsvc ?> (<?= $ratioOsvc ?>× OSVČ + <?= $ratioFirma ?>× firma)</h1>
    <p style="margin:0 0 1rem;color:#6b7280;font-size:0.9rem;">
        V každém cyklu: nejdřív <strong><?= $ratioOsvc ?>× OSVČ</strong> (jednodušší — zahřátí),
        pak <strong><?= $ratioFirma ?>× firma</strong> (těžší — finále cyklu).
        Pořadí se propaguje skrz čističku až k navolávačce. Nově importované kontakty
        se připojí na konec existující fronty (idempotentní = mix lze opakovat).
    </p>

    <?php if (!empty($flash)) { ?>
        <p style="background:#dbeafe;border:1px solid #93c5fd;padding:0.6rem 0.9rem;border-radius:6px;margin-bottom:1rem;">
            <?= crm_h($flash) ?>
        </p>
    <?php } ?>

    <!-- ── Nastavení (settings) ── -->
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:1rem;margin-bottom:1rem;">
        <details <?= !$autoMixEnabled ? 'open' : '' ?>>
            <summary style="cursor:pointer;font-weight:600;font-size:1rem;color:#1f2937;">
                ⚙️ Nastavení mixu
                <span style="font-weight:400;color:#6b7280;font-size:0.85rem;margin-left:0.5rem;">
                    Aktuální: <strong><?= $ratioOsvc ?>× OSVČ + <?= $ratioFirma ?>× firma</strong>
                    · Auto-mix po importu: <strong style="color:<?= $autoMixEnabled ? '#16a34a' : '#dc2626' ?>;">
                        <?= $autoMixEnabled ? '✓ ZAPNUTÝ' : '✗ vypnutý' ?>
                    </strong>
                </span>
            </summary>

            <form method="POST" action="<?= crm_url('/admin/contacts/mix/settings') ?>"
                  style="margin-top:0.8rem;display:flex;flex-direction:column;gap:0.6rem;">
                <input type="hidden" name="<?= crm_csrf_field_name() ?>" value="<?= crm_h($csrf) ?>">

                <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                    <label style="color:#374151;font-size:0.9rem;">Cyklus:</label>
                    <input type="number" name="ratio_osvc" value="<?= $ratioOsvc ?>" min="1" max="100"
                           style="width:55px;padding:0.4rem 0.5rem;border:1px solid #d1d5db;border-radius:5px;text-align:center;font-weight:700;">
                    <span style="color:#6b7280;font-size:0.85rem;">× OSVČ, pak</span>
                    <input type="number" name="ratio_firma" value="<?= $ratioFirma ?>" min="1" max="100"
                           style="width:55px;padding:0.4rem 0.5rem;border:1px solid #d1d5db;border-radius:5px;text-align:center;font-weight:700;">
                    <span style="color:#6b7280;font-size:0.85rem;">× firma na konec cyklu</span>
                </div>

                <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;font-size:0.9rem;">
                    <input type="checkbox" name="auto_mix" value="1" <?= $autoMixEnabled ? 'checked' : '' ?>
                           style="width:16px;height:16px;cursor:pointer;">
                    <span><strong>🤖 Auto-mix po každém importu</strong>
                    <span style="color:#6b7280;font-size:0.82rem;">— když je zapnuté, po importu se nově nahrané kontakty automaticky namíchají, žádný manuální zásah</span></span>
                </label>

                <div style="display:flex;gap:0.5rem;align-items:center;">
                    <button type="submit"
                            style="background:#2563eb;color:#fff;border:none;padding:0.5rem 1rem;
                                   border-radius:5px;cursor:pointer;font-weight:600;font-size:0.85rem;">
                        💾 Uložit nastavení
                    </button>
                    <span style="font-size:0.75rem;color:#9ca3af;">
                        Změna ovlivní budoucí mixy. Už zamíchané kontakty zůstávají ve svém pořadí.
                    </span>
                </div>
            </form>
        </details>
    </div>

    <!-- ── Statistiky ── -->
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:1rem;margin-bottom:1rem;">
        <h3 style="margin:0 0 0.6rem;font-size:1rem;">📊 Stav fronty</h3>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0.8rem;">
            <div style="background:#dbeafe;padding:0.7rem;border-radius:6px;">
                <div style="font-size:0.72rem;color:#1e40af;text-transform:uppercase;">Celkem NEW</div>
                <div style="font-size:1.6rem;font-weight:700;color:#1e40af;"><?= $stats['total_new'] ?></div>
            </div>
            <div style="background:#dcfce7;padding:0.7rem;border-radius:6px;">
                <div style="font-size:0.72rem;color:#166534;text-transform:uppercase;">✓ Už zamícháno</div>
                <div style="font-size:1.6rem;font-weight:700;color:#166534;"><?= $stats['mixed_total'] ?></div>
                <?php if ($stats['last_seq'] > 0) { ?>
                    <div style="font-size:0.7rem;color:#9ca3af;">poslední seq #<?= $stats['last_seq'] ?></div>
                <?php } ?>
            </div>
            <div style="background:#fef3c7;padding:0.7rem;border-radius:6px;">
                <div style="font-size:0.72rem;color:#92400e;text-transform:uppercase;">⏳ Čeká na mix</div>
                <div style="font-size:1.6rem;font-weight:700;color:#92400e;"><?= $totalUnmixed ?></div>
                <div style="font-size:0.7rem;color:#9ca3af;">
                    <?= $stats['unmixed_firma'] ?>× firma · <?= $stats['unmixed_osvc'] ?>× OSVČ
                    <?php if ($stats['unmixed_unknown'] > 0) { ?>
                        · <?= $stats['unmixed_unknown'] ?>× <em>neurčeno</em>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Akce ── -->
    <?php if ($canMix) { ?>
        <form method="POST" action="<?= crm_url('/admin/contacts/mix/execute') ?>"
              id="mix-form"
              onsubmit="return false;"
              style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:1.2rem;margin-bottom:1rem;">
            <input type="hidden" name="<?= crm_csrf_field_name() ?>" value="<?= crm_h($csrf) ?>">

            <h3 style="margin:0 0 0.8rem;font-size:1.1rem;">🎲 Spustit mix</h3>

            <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;margin-bottom:0.8rem;">
                <span style="color:#374151;">Cyklus:</span>
                <input type="number" name="ratio_osvc" value="<?= $ratioOsvc ?>" min="1" max="100"
                       style="width:55px;padding:0.4rem 0.5rem;border:1px solid #d1d5db;border-radius:5px;text-align:center;font-weight:700;">
                <span style="color:#6b7280;font-size:0.85rem;">× OSVČ, pak</span>
                <input type="number" name="ratio_firma" value="<?= $ratioFirma ?>" min="1" max="100"
                       style="width:55px;padding:0.4rem 0.5rem;border:1px solid #d1d5db;border-radius:5px;text-align:center;font-weight:700;">
                <span style="color:#6b7280;font-size:0.85rem;">× firma na konec cyklu</span>
                <span style="color:#9ca3af;font-size:0.78rem;margin-left:0.5rem;">
                    (default 9 + 1 = cyklus 10, navolávačka začne 9× OSVČ a finále = 1 firma)
                </span>
            </div>

            <div style="background:#f9fafb;padding:0.7rem 0.9rem;border-radius:5px;font-size:0.85rem;color:#374151;margin-bottom:0.8rem;">
                <strong>Co se stane:</strong>
                <ol style="margin:0.3rem 0 0 1.2rem;color:#6b7280;font-size:0.82rem;">
                    <li>Detekce subject_type pro <?= $stats['unmixed_unknown'] ?> kontaktů (auto z firma názvu)</li>
                    <li>Pattern: <strong><?= $ratioOsvc ?>× OSVČ + <?= $ratioFirma ?>× firma</strong> opakovaně, dokud kontakty vystačí</li>
                    <li>Když jeden typ vyčerpaný, druhý typ pokračuje sám až do konce</li>
                    <li>Sekvenční číslování <code>queue_mix_seq</code> od #<?= $stats['last_seq'] + 1 ?> do #<?= $stats['last_seq'] + $totalUnmixed ?></li>
                    <li>Čistička i navolávačka pak ve své frontě uvidí kontakty v tomto pořadí</li>
                </ol>
            </div>

            <button type="button"
                    onclick="document.getElementById('mix-confirm-modal').style.display='flex'"
                    style="background:linear-gradient(135deg,#7e22ce,#5b21b6);color:#fff;border:none;
                           padding:0.6rem 1.4rem;border-radius:6px;cursor:pointer;font-weight:700;font-size:0.95rem;">
                🎲 Spustit mix (<?= $totalUnmixed ?> kontaktů)
            </button>
        </form>

        <!-- Reklasifikace všech kontaktů (firma vs OSVČ) -->
        <form method="post" action="<?= crm_h(crm_url('/admin/contacts/mix/reclassify')) ?>"
              style="margin-top:1rem;padding-top:1rem;border-top:1px dashed #e5e7eb;"
              onsubmit="return confirm('Spustit reklasifikaci subject_type pro VŠECHNY kontakty? Použije se aktuální heuristika (firma vs OSVČ podle názvu).');">
            <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
            <div style="display:flex;align-items:center;gap:0.8rem;flex-wrap:wrap;">
                <button type="submit"
                        style="background:#0e7490;color:#fff;border:none;
                               padding:0.5rem 1rem;border-radius:6px;cursor:pointer;font-weight:600;font-size:0.85rem;">
                    🔁 Reklasifikovat všechny (firma vs OSVČ)
                </button>
                <small style="color:#6b7280;font-size:0.78rem;flex:1;min-width:200px;">
                    Spustí znovu detekci typu (s.r.o., a.s., …) pro <strong>všechny kontakty</strong>.
                    Použij po update heuristiky nebo při ručních opravách názvů. Bezpečné — nezmění
                    nic, co se nezmění typem.
                </small>
            </div>
        </form>

        <!-- Custom confirm modal -->
        <div id="mix-confirm-modal"
             style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);
                    z-index:9999;align-items:center;justify-content:center;padding:1rem;">
            <div style="background:#fff;border-radius:12px;max-width:520px;width:100%;
                        padding:1.6rem 1.8rem;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                <div style="font-size:1.3rem;font-weight:700;color:#1f2937;margin-bottom:0.4rem;">
                    🎲 Spustit mix kontaktů?
                </div>
                <div style="color:#6b7280;font-size:0.92rem;margin-bottom:1rem;">
                    Namíchá se <strong><?= $totalUnmixed ?> nezamíchaných kontaktů</strong> v cyklu
                    <strong><?= $ratioOsvc ?>× OSVČ + <?= $ratioFirma ?>× firma</strong> a připojí se na
                    konec existující fronty (sekvenční číslování od #<?= $stats['last_seq'] + 1 ?>).
                </div>

                <div style="background:#f9fafb;border-radius:8px;padding:0.9rem 1rem;margin-bottom:1rem;font-size:0.88rem;">
                    <div style="display:flex;justify-content:space-between;padding:0.2rem 0;">
                        <span style="color:#374151;">📋 K namíchání:</span>
                        <strong style="color:#7e22ce;"><?= $totalUnmixed ?> kontaktů</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:0.2rem 0;">
                        <span style="color:#374151;">🏢 Firem:</span>
                        <strong><?= $stats['unmixed_firma'] ?></strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:0.2rem 0;">
                        <span style="color:#374151;">👤 OSVČ:</span>
                        <strong><?= $stats['unmixed_osvc'] ?></strong>
                    </div>
                    <?php if ($stats['unmixed_unknown'] > 0) { ?>
                    <div style="display:flex;justify-content:space-between;padding:0.2rem 0;">
                        <span style="color:#374151;">❓ Neurčeno (auto-detekce):</span>
                        <strong style="color:#f59e0b;"><?= $stats['unmixed_unknown'] ?></strong>
                    </div>
                    <?php } ?>
                </div>

                <div style="background:#fff8e1;border-left:3px solid #f59e0b;
                            padding:0.6rem 0.8rem;border-radius:4px;font-size:0.85rem;color:#92400e;margin-bottom:1.2rem;">
                    💡 Akce je <strong>idempotentní</strong> — můžeš ji opakovat kdykoli po novém importu.
                    Už zamíchané kontakty zůstanou ve své frontě.
                </div>

                <div style="display:flex;gap:0.6rem;justify-content:flex-end;">
                    <button type="button"
                            onclick="document.getElementById('mix-confirm-modal').style.display='none'"
                            style="background:#f3f4f6;color:#374151;border:none;border-radius:6px;
                                   padding:0.6rem 1.1rem;font-weight:600;cursor:pointer;">
                        Ještě ne
                    </button>
                    <button type="button"
                            onclick="(function(){ var f=document.getElementById('mix-form'); f.onsubmit=null; f.submit(); })();"
                            style="background:linear-gradient(135deg,#7e22ce,#5b21b6);color:#fff;border:none;
                                   border-radius:6px;padding:0.6rem 1.2rem;font-weight:700;cursor:pointer;">
                        🎲 Spustit mix
                    </button>
                </div>
            </div>
        </div>
    <?php } else { ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:2rem;text-align:center;color:#6b7280;">
            <div style="font-size:2rem;margin-bottom:0.5rem;">✅</div>
            <h3 style="margin:0 0 0.5rem;color:#374151;">Žádné kontakty k zamíchání</h3>
            <p style="margin:0;font-size:0.9rem;">
                Všechny NEW kontakty jsou už zamíchané. Po importu nových kontaktů se tady objeví počitadlo a tlačítko.
            </p>
        </div>
    <?php } ?>

    <!-- ── Příklad výsledku ── -->
    <div style="background:#fef3c7;border:1px solid #fbbf24;border-radius:8px;padding:0.9rem 1.1rem;font-size:0.85rem;">
        <strong>💡 Příklad:</strong> máš 30 firem a 270 OSVČ. Po mixu <?= $ratioOsvc ?>+<?= $ratioFirma ?>:
        <br>
        <code style="background:#fff;padding:0.1rem 0.3rem;border-radius:3px;font-size:0.78rem;">
            <?= str_repeat('OSVČ → ', min(3, $ratioOsvc)) ?>… → <?= $ratioOsvc ?>. OSVČ → <strong>FIRMA</strong> → <?= $ratioOsvc ?>× OSVČ → <strong>FIRMA</strong> → ... → konec
        </code>
        <br>
        Cyklus o <?= $ratioFirma + $ratioOsvc ?> kontaktech: 1.-<?= $ratioOsvc ?>. = OSVČ, <?= $ratioFirma + $ratioOsvc ?>. = firma.
        Navolávačka začne 9× lehčí OSVČ a 10. v cyklu má firmu jako „odměnu / výzvu".
    </div>
</div>
