<?php
// e:\Snecinatripu\app\views\oz\search.php
declare(strict_types=1);
/** @var string $q */
/** @var array<int,array<string,mixed>> $results */
/** @var bool $hasSearched */
/** @var string|null $flash */
/** @var string $csrf */
/** @var int $ozId */

$q = $q ?? '';
$results = $results ?? [];
$hasSearched = $hasSearched ?? false;

// Helper pro statusový badge pro každý řádek
function ozSearchRowBadge(array $r): array {
    $stav = (string) ($r['contact_stav'] ?? '');
    $wf   = (string) ($r['wf_stav'] ?? '');
    $sales = (string) ($r['sales_name'] ?? '');
    $caller = (string) ($r['caller_name'] ?? '');
    if ((int) ($r['dnc_flag'] ?? 0) === 1) return ['🚫 DNC', '#dc2626'];
    if ($wf === 'UZAVRENO') return ['✓ Uzavřená smlouva', '#16a34a'];
    if (in_array($wf, ['BO_PREDANO','BO_VPRACI','BO_VRACENO','SMLOUVA'], true)) return ['🏢 BO ' . $wf, '#7c3aed'];
    if ($wf && $wf !== '—' && $sales) return ['🎯 ' . $sales . ' · ' . $wf, '#0e7490'];
    if (in_array($stav, ['ASSIGNED','NEDOVOLANO','CALLBACK'], true) && $caller)
        return ['📞 ' . $caller . ' · ' . $stav, '#ea580c'];
    if ($stav === 'CALLED_OK' && $sales) return ['🎯 ' . $sales, '#0e7490'];
    if ($stav === 'READY') return ['📞 V poolu', '#3b82f6'];
    if ($stav === 'NEW') return ['🧹 Čeká čističku', '#6b7280'];
    if ($stav === 'NEZAJEM') return ['❌ NEZAJEM', '#9ca3af'];
    return [$stav ?: '—', '#6b7280'];
}
?>
<section class="card">
    <h1>🔍 Vyhledávání kontaktů</h1>
    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info"><?= crm_h($flash) ?></p>
    <?php } ?>

    <p style="font-size:0.88rem;color:var(--bo-text-3, #6b7280);margin-bottom:1rem;">
        Hledej kontakt podle <strong>firmy, IČO, telefonu, e-mailu nebo adresy</strong>.
        Uvidíš jeho aktuální stav (čistička / navolávačka / OZ / BO), můžeš si otevřít kartu, přidat poznámku
        nebo si ho převzít (pokud nemá jiný OZ).
    </p>

    <form method="get" action="<?= crm_h(crm_url('/oz/search')) ?>"
          style="display:flex;gap:0.5rem;margin-bottom:1.2rem;">
        <input type="text" name="q" value="<?= crm_h($q) ?>" autofocus
               placeholder="Firma · IČO · telefon · e-mail · adresa…"
               style="flex:1;padding:0.6rem 0.9rem;font-size:0.95rem;border:1px solid #d1d5db;border-radius:6px;">
        <button type="submit"
                style="background:#0e7490;color:#fff;border:0;border-radius:6px;
                       padding:0.6rem 1.4rem;cursor:pointer;font-weight:600;font-size:0.92rem;">
            🔍 Hledat
        </button>
    </form>

    <?php if (!$hasSearched) { ?>
        <p style="font-size:0.88rem;color:#9ca3af;text-align:center;padding:2rem 0;">
            Začni psát do vyhledávacího pole nahoře.
        </p>
    <?php } elseif ($results === []) { ?>
        <p style="font-size:0.92rem;color:#9ca3af;text-align:center;padding:2rem 0;">
            Žádný kontakt neodpovídá hledanému výrazu „<strong><?= crm_h($q) ?></strong>".<br>
            <small>Zkus zúžit dotaz nebo zkontroluj překlepy.</small>
        </p>
    <?php } else { ?>
        <p style="font-size:0.82rem;color:#6b7280;margin-bottom:0.6rem;">
            Nalezeno <strong><?= count($results) ?></strong> kontaktů (max 100). Klikni na řádek pro detail.
        </p>
        <div style="display:flex;flex-direction:column;gap:0.4rem;">
            <?php foreach ($results as $r) {
                $badge = ozSearchRowBadge($r);
                $cid   = (int) $r['id'];
            ?>
                <a href="<?= crm_h(crm_url('/oz/search/card?id=' . $cid)) ?>"
                   style="display:flex;align-items:center;gap:0.7rem;padding:0.7rem 1rem;
                          background:#fff;border:1px solid #e5e7eb;border-left:3px solid <?= crm_h($badge[1]) ?>;
                          border-radius:6px;text-decoration:none;color:#1f2937;
                          transition:all 0.1s;flex-wrap:wrap;"
                   onmouseover="this.style.background='#f9fafb';this.style.transform='translateX(2px)';"
                   onmouseout="this.style.background='#fff';this.style.transform='translateX(0)';">
                    <strong style="font-size:0.95rem;min-width:200px;flex:1 1 200px;">
                        <?= crm_h((string)($r['firma'] ?? '')) ?>
                    </strong>
                    <span style="font-family:monospace;font-size:0.82rem;color:#6b7280;min-width:90px;">
                        IČO: <?= crm_h((string)($r['ico'] ?? '')) ?: '—' ?>
                    </span>
                    <span style="font-family:monospace;font-size:0.85rem;color:#1e40af;min-width:120px;">
                        <?= crm_h((string)($r['telefon'] ?? '')) ?: '—' ?>
                    </span>
                    <span style="font-size:0.78rem;color:#6b7280;min-width:80px;">
                        <?= crm_h((string)($r['region'] ?? '')) ?>
                    </span>
                    <?php $pr = (string)($r['prilez'] ?? ''); ?>
                    <?php if ($pr !== '') { ?>
                        <span style="background:#ddd6fe;color:#5b21b6;padding:0.12rem 0.5rem;
                                     border-radius:6px;font-size:0.72rem;font-weight:600;white-space:nowrap;"
                              title="Příležitost">
                            💡 <?= crm_h($pr) ?>
                        </span>
                    <?php } else { ?>
                        <span style="background:#f3f4f6;color:#9ca3af;padding:0.12rem 0.5rem;
                                     border-radius:6px;font-size:0.72rem;font-weight:500;white-space:nowrap;font-style:italic;"
                              title="Příležitost není vyplněna">
                            💡 bez příležitosti
                        </span>
                    <?php } ?>
                    <span style="background:<?= crm_h($badge[1]) ?>;color:#fff;border-radius:10px;
                                 padding:0.15rem 0.6rem;font-size:0.72rem;font-weight:600;white-space:nowrap;">
                        <?= crm_h($badge[0]) ?>
                    </span>
                </a>
            <?php } ?>
        </div>
    <?php } ?>
</section>
