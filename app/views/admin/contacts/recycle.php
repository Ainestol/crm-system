<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $contacts */
/** @var int   $totalCount */
/** @var array<string,int>  $stavCounts */
/** @var array<string,string> $recyclableStavs */
/** @var list<string|int> $regionChoices */
/** @var string|null $flash */
/** @var string $csrf */

$stavFilter   = (array) ($_GET['stav'] ?? []);
$dateFrom     = (string) ($_GET['date_from'] ?? '');
$dateTo       = (string) ($_GET['date_to'] ?? '');
$regionFilter = (string) ($_GET['region'] ?? '');
$operFilter   = (string) ($_GET['operator'] ?? '');
?>

<div style="max-width:1300px;margin:0 auto;padding:1rem;">
    <h1 style="margin-bottom:0.4rem;">♻ Recyklace kontaktů</h1>
    <p style="margin:0 0 1rem;color:#6b7280;font-size:0.9rem;">
        Vrať starší kontakty zpět do oběhu (např. VF_SKIP po 2-5 letech když změnili operátora,
        nebo NEZAJEM po čase). Recyklovaný kontakt si zachová původní ID a historii,
        ale na vrcholu fronty se objeví jako „čerstvý" (řazení dle <code>last_recycled_at</code>).
    </p>

    <?php if (!empty($flash)) { ?>
        <p style="background:#dbeafe;border:1px solid #93c5fd;padding:0.6rem 0.9rem;border-radius:6px;margin-bottom:1rem;">
            <?= crm_h($flash) ?>
        </p>
    <?php } ?>

    <!-- ── Filtr ── -->
    <form method="get" action="<?= crm_url('/admin/contacts/recycle') ?>"
          style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:1rem;margin-bottom:1rem;">
        <h3 style="margin:0 0 0.6rem;font-size:1rem;">🔍 Filtr</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:0.8rem;margin-bottom:0.8rem;">
            <!-- Stav (multi-select) -->
            <div>
                <label style="display:block;font-size:0.78rem;color:#6b7280;font-weight:600;margin-bottom:0.3rem;">
                    Stav kontaktu
                </label>
                <div style="display:flex;flex-direction:column;gap:0.2rem;">
                    <?php foreach ($recyclableStavs as $stavKey => $stavLbl) {
                        $checked = in_array($stavKey, $stavFilter, true);
                    ?>
                        <label style="display:flex;align-items:center;gap:0.3rem;cursor:pointer;font-size:0.85rem;">
                            <input type="checkbox" name="stav[]" value="<?= crm_h($stavKey) ?>" <?= $checked ? 'checked' : '' ?>>
                            <?= crm_h($stavLbl) ?>
                        </label>
                    <?php } ?>
                </div>
            </div>

            <!-- Datum (kdy se s kontaktem pracovalo) -->
            <div>
                <label style="display:block;font-size:0.78rem;color:#6b7280;font-weight:600;margin-bottom:0.3rem;">
                    Datum poslední změny (updated_at)
                </label>
                <div style="display:flex;flex-direction:column;gap:0.3rem;">
                    <input type="date" name="date_from" value="<?= crm_h($dateFrom) ?>"
                           style="padding:0.4rem 0.6rem;border:1px solid #d1d5db;border-radius:5px;font-size:0.85rem;"
                           title="Od">
                    <input type="date" name="date_to" value="<?= crm_h($dateTo) ?>"
                           style="padding:0.4rem 0.6rem;border:1px solid #d1d5db;border-radius:5px;font-size:0.85rem;"
                           title="Do">
                    <div style="font-size:0.72rem;color:#9ca3af;">
                        Např. „před 2 roky" → vyplň přesně <strong><?= date('Y-m-d', strtotime('-2 years')) ?></strong> do.
                    </div>
                </div>
            </div>

            <!-- Region -->
            <div>
                <label style="display:block;font-size:0.78rem;color:#6b7280;font-weight:600;margin-bottom:0.3rem;">
                    Kraj
                </label>
                <select name="region"
                        style="width:100%;padding:0.4rem 0.6rem;border:1px solid #d1d5db;border-radius:5px;font-size:0.85rem;">
                    <option value="">— všechny kraje —</option>
                    <?php foreach ($regionChoices as $regCode) {
                        $regCode = (string) $regCode;
                    ?>
                        <option value="<?= crm_h($regCode) ?>" <?= $regCode === $regionFilter ? 'selected' : '' ?>>
                            <?= crm_h(crm_region_label($regCode)) ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <!-- Operator -->
            <div>
                <label style="display:block;font-size:0.78rem;color:#6b7280;font-weight:600;margin-bottom:0.3rem;">
                    Operátor
                </label>
                <select name="operator"
                        style="width:100%;padding:0.4rem 0.6rem;border:1px solid #d1d5db;border-radius:5px;font-size:0.85rem;">
                    <option value="">— všichni —</option>
                    <?php foreach (['TM' => '🌸 TM', 'O2' => '🔵 O2', 'VF' => '🔴 VF', 'empty' => '— prázdný —'] as $opVal => $opLbl) { ?>
                        <option value="<?= crm_h($opVal) ?>" <?= $opVal === $operFilter ? 'selected' : '' ?>>
                            <?= crm_h($opLbl) ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>

        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <button type="submit"
                    style="background:#2563eb;color:#fff;border:none;padding:0.55rem 1.2rem;
                           border-radius:6px;cursor:pointer;font-weight:600;">
                🔍 Filtrovat
            </button>
            <a href="<?= crm_url('/admin/contacts/recycle') ?>"
               style="background:#f3f4f6;color:#374151;padding:0.55rem 1rem;
                      border-radius:6px;text-decoration:none;font-size:0.9rem;">
                Vymazat filtr
            </a>
        </div>
    </form>

    <!-- ── Výsledky ── -->
    <?php if ($totalCount === 0 && ($stavFilter !== [] || $dateFrom !== '' || $dateTo !== '' || $regionFilter !== '' || $operFilter !== '')) { ?>
        <p style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:2rem;text-align:center;color:#6b7280;">
            Žádné kontakty odpovídající filtru.
        </p>
    <?php } elseif ($totalCount === 0) { ?>
        <p style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:2rem;text-align:center;color:#6b7280;">
            Vyplň filtr nahoře a klikni <strong>🔍 Filtrovat</strong>.
        </p>
    <?php } else { ?>

        <!-- Souhrn nalezených -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:1rem;margin-bottom:1rem;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.6rem;">
                <div>
                    <strong style="font-size:1.2rem;color:#2563eb;"><?= $totalCount ?></strong> nalezených kontaktů
                    <?php if (count($contacts) < $totalCount) { ?>
                        <span style="color:#6b7280;font-size:0.85rem;">(zobrazeno prvních 200)</span>
                    <?php } ?>
                </div>
                <div style="display:flex;gap:0.3rem;flex-wrap:wrap;">
                    <?php foreach ($stavCounts as $s => $cnt) { ?>
                        <span style="background:#f3f4f6;padding:0.2rem 0.55rem;border-radius:14px;font-size:0.78rem;">
                            <?= crm_h($recyclableStavs[$s] ?? $s) ?>: <strong><?= $cnt ?></strong>
                        </span>
                    <?php } ?>
                </div>
            </div>
        </div>

        <!-- Bulk recycle form -->
        <form method="post" action="<?= crm_url('/admin/contacts/recycle') ?>"
              onsubmit="return confirm('Opravdu recyklovat vybrané kontakty zpět do oběhu?');">
            <input type="hidden" name="<?= crm_csrf_field_name() ?>" value="<?= crm_h($csrf) ?>">

            <!-- Akční panel -->
            <div style="background:#dbeafe;border:1px solid #93c5fd;border-radius:8px;padding:0.8rem 1rem;margin-bottom:0.8rem;
                        display:flex;align-items:center;gap:0.8rem;flex-wrap:wrap;">
                <span style="font-weight:600;">♻ Recyklovat vybrané jako:</span>
                <select name="target_mode"
                        style="padding:0.4rem 0.6rem;border:1px solid #93c5fd;border-radius:5px;font-size:0.85rem;background:#fff;">
                    <option value="auto">📋 Auto (VF→čistička, TM/O2→navolávačka)</option>
                    <option value="new">🧹 NEW (vše do čističky)</option>
                    <option value="ready">📞 READY (vše do navolávačky)</option>
                </select>
                <input type="text" name="note" maxlength="500"
                       placeholder="poznámka (volitelně) — důvod recyklace…"
                       style="flex:1;min-width:200px;padding:0.4rem 0.6rem;border:1px solid #93c5fd;border-radius:5px;font-size:0.85rem;">
                <button type="submit"
                        style="background:#16a34a;color:#fff;border:none;padding:0.55rem 1.2rem;
                               border-radius:6px;cursor:pointer;font-weight:700;">
                    ♻ Vrátit do oběhu
                </button>
            </div>

            <!-- Tabulka -->
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                    <thead style="background:#f3f4f6;">
                        <tr>
                            <th style="padding:0.5rem 0.7rem;text-align:left;">
                                <input type="checkbox" id="check-all"
                                       onclick="document.querySelectorAll('input[name=\'contact_ids[]\']').forEach(c=>c.checked=this.checked);">
                            </th>
                            <th style="padding:0.5rem 0.7rem;text-align:left;">ID</th>
                            <th style="padding:0.5rem 0.7rem;text-align:left;">Firma</th>
                            <th style="padding:0.5rem 0.7rem;text-align:left;">Telefon</th>
                            <th style="padding:0.5rem 0.7rem;text-align:left;">Kraj</th>
                            <th style="padding:0.5rem 0.7rem;text-align:left;">Op</th>
                            <th style="padding:0.5rem 0.7rem;text-align:left;">Stav</th>
                            <th style="padding:0.5rem 0.7rem;text-align:left;">Důvod</th>
                            <th style="padding:0.5rem 0.7rem;text-align:left;">Poslední úprava</th>
                            <th style="padding:0.5rem 0.7rem;text-align:center;">Recyklováno</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contacts as $c) {
                            $cId = (int) $c['id'];
                            $recCnt = (int) ($c['recycle_count'] ?? 0);
                        ?>
                            <tr style="border-top:1px solid #f3f4f6;">
                                <td style="padding:0.4rem 0.7rem;">
                                    <input type="checkbox" name="contact_ids[]" value="<?= $cId ?>">
                                </td>
                                <td style="padding:0.4rem 0.7rem;font-family:monospace;color:#9ca3af;">
                                    <?= $cId ?>
                                </td>
                                <td style="padding:0.4rem 0.7rem;font-weight:600;">
                                    <?= crm_h((string) ($c['firma'] ?? '—')) ?>
                                </td>
                                <td style="padding:0.4rem 0.7rem;font-family:monospace;">
                                    <?= crm_h((string) ($c['telefon'] ?? '')) ?>
                                </td>
                                <td style="padding:0.4rem 0.7rem;color:#6b7280;font-size:0.8rem;">
                                    <?= crm_h(crm_region_label((string) ($c['region'] ?? ''))) ?>
                                </td>
                                <td style="padding:0.4rem 0.7rem;">
                                    <span style="background:#e5e7eb;padding:0.1rem 0.4rem;border-radius:3px;font-size:0.75rem;font-weight:600;">
                                        <?= crm_h((string) ($c['operator'] ?? '—')) ?>
                                    </span>
                                </td>
                                <td style="padding:0.4rem 0.7rem;font-size:0.78rem;">
                                    <?= crm_h($recyclableStavs[(string) $c['stav']] ?? (string) $c['stav']) ?>
                                </td>
                                <td style="padding:0.4rem 0.7rem;color:#6b7280;font-size:0.78rem;max-width:160px;">
                                    <?= crm_h(mb_substr((string) ($c['rejection_reason'] ?? ''), 0, 40)) ?>
                                </td>
                                <td style="padding:0.4rem 0.7rem;color:#9ca3af;font-size:0.78rem;">
                                    <?= !empty($c['updated_at']) ? date('d.m.Y H:i', strtotime((string) $c['updated_at'])) : '—' ?>
                                </td>
                                <td style="padding:0.4rem 0.7rem;text-align:center;">
                                    <?php if ($recCnt > 0) { ?>
                                        <span title="Recyklováno už <?= $recCnt ?>× — naposled <?= !empty($c['last_recycled_at']) ? date('d.m.Y', strtotime((string) $c['last_recycled_at'])) : '' ?>"
                                              style="background:#fef3c7;color:#92400e;padding:0.1rem 0.45rem;border-radius:3px;font-size:0.75rem;font-weight:600;">
                                            ♻ <?= $recCnt ?>×
                                        </span>
                                    <?php } else { ?>
                                        <span style="color:#d1d5db;font-size:0.75rem;">—</span>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </form>

        <p style="font-size:0.78rem;color:#9ca3af;margin-top:0.8rem;">
            💡 Tip: Pokud chceš recyklovat všechny VF_SKIP starší než 2 roky, vyfiltruj VF_SKIP + date_to = <?= date('Y-m-d', strtotime('-2 years')) ?>,
            zaškrtni Check-all a klikni „♻ Vrátit do oběhu".
            Cool-down 7 dní brání recyklaci právě uzavřených kontaktů.
        </p>
    <?php } ?>
</div>
