<?php
// e:\Snecinatripu\app\views\cisticka\premium\order.php
declare(strict_types=1);
/** @var array<string,mixed>             $user */
/** @var string                          $csrf */
/** @var ?string                         $flash */
/** @var array<string,mixed>             $order   hlavička objednávky */
/** @var list<array<string,mixed>>       $leads   řádky pool + contacts JOIN */

$_czechMonth = static fn(int $m): string => [
    1=>'Leden',2=>'Únor',3=>'Březen',4=>'Duben',5=>'Květen',6=>'Červen',
    7=>'Červenec',8=>'Srpen',9=>'Září',10=>'Říjen',11=>'Listopad',12=>'Prosinec'
][$m] ?? (string)$m;

$pending  = 0; $tradeable = 0; $nontrad = 0;
foreach ($leads as $l) {
    $cs = (string) $l['cleaning_status'];
    if ($cs === 'pending')       $pending++;
    if ($cs === 'tradeable')     $tradeable++;
    if ($cs === 'non_tradeable') $nontrad++;
}
$price = (float) $order['price_per_lead'];
?>

<style>
.po-detail-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}
.po-detail-header h1 { margin: 0; font-size: 1.3rem; }
.po-back {
    text-decoration: none;
    color: var(--color-text-muted);
    font-size: 0.85rem;
    border: 1px solid var(--color-border);
    padding: 0.4rem 0.8rem;
    border-radius: 5px;
    background: #fff;
}
.po-back:hover { background: #fafafa; }

.po-meta-bar {
    background: #f5f0fc;
    border: 1px solid #d8c5fa;
    border-left: 4px solid #7e3ff2;
    border-radius: 0 6px 6px 0;
    padding: 0.7rem 1rem;
    margin-bottom: 1rem;
    display: flex;
    flex-wrap: wrap;
    gap: 1.2rem;
    font-size: 0.85rem;
}
.po-meta-bar > div { line-height: 1.5; }
.po-meta-bar strong { color: #4a2480; }

.lead-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border: 1px solid var(--color-border-strong);
    border-radius: 6px;
    overflow: hidden;
    font-size: 0.88rem;
}
.lead-table th, .lead-table td {
    padding: 0.55rem 0.75rem;
    text-align: left;
    border-bottom: 1px solid var(--color-border);
    vertical-align: middle;
}
.lead-table thead { background: #f8f6fb; }

.lead-row--tradeable     { background: #ebfaee; }
.lead-row--non_tradeable { background: #fbe9e9; }

.btn-tradeable {
    background: #2e7d32;
    color: #fff;
    border: none;
    border-radius: 4px;
    padding: 0.35rem 0.75rem;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    margin-right: 4px;
}
.btn-tradeable:hover { background: #1b5e20; }
.btn-non_tradeable {
    background: #c62828;
    color: #fff;
    border: none;
    border-radius: 4px;
    padding: 0.35rem 0.75rem;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
}
.btn-non_tradeable:hover { background: #8e1c1c; }
.btn-undo {
    background: transparent;
    border: 1px solid var(--color-border-strong);
    color: var(--color-text-muted);
    padding: 0.3rem 0.6rem;
    font-size: 0.72rem;
    border-radius: 4px;
    cursor: pointer;
}
.btn-undo:hover { background: #f0f0f0; }

.lead-status-tradeable {
    color: #2e7d32;
    font-weight: 700;
    font-size: 0.78rem;
}
.lead-status-non_tradeable {
    color: #c62828;
    font-weight: 700;
    font-size: 0.78rem;
}

.po-summary-stats {
    display: inline-flex;
    gap: 0.8rem;
    align-items: center;
    flex-wrap: wrap;
}
.po-pill {
    background: #fff;
    border: 1px solid var(--color-border-strong);
    border-radius: 12px;
    padding: 0.2rem 0.7rem;
    font-size: 0.78rem;
}
.po-pill--earn {
    background: linear-gradient(135deg,#7e3ff2 0%,#a056ff 100%);
    color: #fff;
    border-color: transparent;
    font-weight: 700;
}
</style>

<section class="card">
    <div class="po-detail-header">
        <h1>💎 Objednávka #<?= (int) $order['id'] ?> — <?= crm_h((string) $order['oz_name']) ?></h1>
        <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
            <?php if (($order['status'] ?? '') === 'open') { ?>
                <form method="post" action="<?= crm_h(crm_url('/cisticka/premium/close')) ?>"
                      onsubmit="return confirm('🏁 Uzavřít objednávku #<?= (int)$order['id'] ?> jako dokončenou?\n\n• Pending leady se uvolní zpět do poolu (OZ je nezaplatí)\n• Vyčištěné leady zůstávají k fakturaci\n• OZ uvidí objednávku jako uzavřenou');"
                      style="margin:0;">
                    <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                    <button type="submit"
                            style="background: #2e7d32; color:#fff; border:none; border-radius:5px; padding:0.5rem 1rem; font-weight:700; font-size:0.88rem; cursor:pointer;">
                        🏁 Uzavřít objednávku
                    </button>
                </form>
            <?php } ?>
            <a href="<?= crm_h(crm_url('/cisticka/premium')) ?>" class="po-back">← Zpět na seznam</a>
        </div>
    </div>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info" style="margin-bottom:1rem;"><?= crm_h($flash) ?></p>
    <?php } ?>

    <?php
    // Dekódovat regiony z JSON sloupce — víc krajů per objednávka možné
    $orderRegions = [];
    $rawRegJson = $order['regions_json'] ?? null;
    if (is_string($rawRegJson) && $rawRegJson !== '') {
        $decoded = json_decode($rawRegJson, true);
        if (is_array($decoded)) {
            $orderRegions = array_values(array_filter($decoded, 'is_string'));
        }
    }
    // Lidsky čitelné labely (jihocesky → "Jihočeský kraj")
    $regionLabels = array_map(
        static fn(string $r): string => function_exists('crm_region_label') ? crm_region_label($r) : $r,
        $orderRegions
    );
    ?>

    <div class="po-meta-bar">
        <div>
            📅 <strong><?= crm_h($_czechMonth((int)$order['month'])) ?> <?= (int) $order['year'] ?></strong>
        </div>
        <div>
            <?php if ($regionLabels !== []) { ?>
                🗺 Kraje: <strong><?= crm_h(implode(', ', $regionLabels)) ?></strong>
            <?php } else { ?>
                🗺 <span style="color:#c0392b;">⚠ Kraj neuveden — všechny</span>
            <?php } ?>
        </div>
        <div>
            💰 <strong><?= number_format($price, 2, ',', ' ') ?> Kč</strong> za vyčištěný lead
        </div>
        <div>
            🎯 Cíl: <strong><?= (int) $order['requested_count'] ?> leadů</strong>
            (zarezervováno <?= (int) $order['reserved_count'] ?>)
        </div>
        <?php if (!empty($order['preferred_caller_name'])) { ?>
            <div>
                📞 Volá: <strong><?= crm_h((string) $order['preferred_caller_name']) ?></strong>
            </div>
        <?php } else { ?>
            <div>📞 Rotace navolávaček</div>
        <?php } ?>
        <?php if (!empty($order['note'])) { ?>
            <div>📝 <?= crm_h((string) $order['note']) ?></div>
        <?php } ?>
    </div>

    <div style="margin-bottom: 0.8rem;">
        <div class="po-summary-stats">
            <span class="po-pill">⏳ Pending: <strong><?= $pending ?></strong></span>
            <span class="po-pill">✅ Obchodovatelné: <strong><?= $tradeable ?></strong></span>
            <span class="po-pill">❌ Neobchodovatelné: <strong><?= $nontrad ?></strong></span>
            <span class="po-pill po-pill--earn">
                💰 Vyděláš za tuto objednávku:
                <?= number_format(($tradeable + $nontrad) * $price, 2, ',', ' ') ?> Kč
            </span>
        </div>
    </div>

    <?php if ($leads === []) { ?>
        <p style="text-align:center; color:var(--color-text-muted); padding:1.5rem;">
            Žádné leady v této objednávce zatím nejsou.
        </p>
    <?php } else { ?>
        <table class="lead-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Firma</th>
                    <th>IČO</th>
                    <th>Telefon</th>
                    <th>Kraj</th>
                    <th>Operátor</th>
                    <th>Příležitost</th>
                    <th>Stav</th>
                    <th>Akce</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($leads as $l) {
                $cs = (string) $l['cleaning_status'];
                $rowClass = match ($cs) {
                    'tradeable'     => 'lead-row--tradeable',
                    'non_tradeable' => 'lead-row--non_tradeable',
                    default         => '',
                };
                $_ico = trim((string) ($l['ico'] ?? ''));
                $_tel = trim((string) ($l['telefon'] ?? ''));
            ?>
                <tr class="<?= crm_h($rowClass) ?>">
                    <td><?= (int) $l['contact_id'] ?></td>
                    <td><strong><?= crm_h((string) $l['firma']) ?></strong></td>
                    <td>
                        <?php if ($_ico !== '') { ?>
                            <span class="cist-copy"
                                  data-copy="<?= crm_h($_ico) ?>"
                                  data-copy-label="IČO"
                                  title="Klikni — zkopíruje IČO do schránky (Ctrl+V kamkoliv)"
                                  style="font-family:monospace;"><?= crm_h($_ico) ?></span>
                        <?php } else { ?>
                            <span style="color:var(--color-text-muted);">—</span>
                        <?php } ?>
                    </td>
                    <td>
                        <?php if ($_tel !== '') { ?>
                            <span class="cist-copy"
                                  data-copy="<?= crm_h($_tel) ?>"
                                  data-copy-label="Telefon"
                                  title="Klikni — zkopíruje telefon do schránky"
                                  style="font-family:monospace;"><?= crm_h($_tel) ?></span>
                        <?php } else { ?>
                            <span style="color:var(--color-text-muted);">—</span>
                        <?php } ?>
                    </td>
                    <td><?= crm_h(crm_region_label((string) $l['region'])) ?></td>
                    <td><?= crm_h((string) ($l['operator'] ?? '—')) ?></td>
                    <td style="max-width: 260px; font-size: 0.82rem; line-height: 1.35;">
                        <?php
                        $_prilez = trim((string) ($l['prilez'] ?? ''));
                        if ($_prilez === '') {
                            echo '<em style="color:var(--color-text-muted);">—</em>';
                        } else {
                            echo crm_h($_prilez);
                        }
                        ?>
                    </td>
                    <td>
                        <?php if ($cs === 'pending') { ?>
                            <em style="color:#a06800;">čeká</em>
                        <?php } elseif ($cs === 'tradeable') { ?>
                            <span class="lead-status-tradeable">✅ Obchodovatelný</span>
                        <?php } else { ?>
                            <span class="lead-status-non_tradeable">❌ Neobchodovatelný</span>
                        <?php } ?>
                    </td>
                    <td>
                        <?php if ($cs === 'pending') { ?>
                            <form method="post" action="<?= crm_h(crm_url('/cisticka/premium/verify')) ?>" style="display:inline; margin:0;">
                                <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                                <input type="hidden" name="pool_id" value="<?= (int) $l['pool_id'] ?>">
                                <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                <input type="hidden" name="action" value="tradeable">
                                <button type="submit" class="btn-tradeable">✅ Obchod.</button>
                            </form>
                            <form method="post" action="<?= crm_h(crm_url('/cisticka/premium/verify')) ?>" style="display:inline; margin:0;">
                                <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                                <input type="hidden" name="pool_id" value="<?= (int) $l['pool_id'] ?>">
                                <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                <input type="hidden" name="action" value="non_tradeable">
                                <button type="submit" class="btn-non_tradeable">❌ Neobch.</button>
                            </form>
                        <?php } else { ?>
                            <form method="post" action="<?= crm_h(crm_url('/cisticka/premium/undo')) ?>" style="display:inline; margin:0;"
                                  onsubmit="return confirm('Vrátit lead zpět na pending a znovu rozhodnout?');">
                                <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                                <input type="hidden" name="pool_id" value="<?= (int) $l['pool_id'] ?>">
                                <button type="submit" class="btn-undo">↩ Vrátit</button>
                            </form>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</section>

<script>
// ── Click-to-copy: IČO / telefon → schránka (sdílí CSS .cist-copy z Plochy 1) ──
// Bez frameworku, vanilla JS. Buňky s .cist-copy + data-copy="VALUE" jsou klikací.
// Po kliknutí se hodnota zkopíruje do clipboardu, krátce se zobrazí toast a buňka
// problikne zeleně.
(function () {
    document.addEventListener('click', function (e) {
        var copyEl = e.target.closest('.cist-copy');
        if (!copyEl) return;

        var val = (copyEl.dataset.copy || '').trim();
        var label = copyEl.dataset.copyLabel || 'Hodnota';
        if (val === '') return;

        var doneOk = function () { showToast('✓ ' + label + ' zkopírováno: ' + truncate(val, 30), copyEl); };
        var doneFail = function () { alert('Kopírování selhalo. Vyber a Ctrl+C ručně.'); };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(val).then(doneOk).catch(execFallback);
        } else {
            execFallback();
        }

        function execFallback() {
            var ta = document.createElement('textarea');
            ta.value = val;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); doneOk(); }
            catch (_) { doneFail(); }
            document.body.removeChild(ta);
        }

        e.stopPropagation();
    });

    function truncate(s, n) { return s.length > n ? s.slice(0, n) + '…' : s; }

    function showToast(msg, srcEl) {
        var t = document.getElementById('cist-copy-toast');
        if (!t) {
            t = document.createElement('div');
            t.id = 'cist-copy-toast';
            t.className = 'cist-copy-toast';
            document.body.appendChild(t);
        }
        t.textContent = msg;
        t.classList.add('is-visible');
        clearTimeout(t._hideTimer);
        t._hideTimer = setTimeout(function () { t.classList.remove('is-visible'); }, 1600);

        if (srcEl) {
            srcEl.classList.add('cist-copy--copied');
            setTimeout(function () { srcEl.classList.remove('cist-copy--copied'); }, 600);
        }
    }
})();
</script>
