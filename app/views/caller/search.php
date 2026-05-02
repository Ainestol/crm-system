<?php
// e:\Snecinatripu\app\views\caller\search.php
declare(strict_types=1);
/** @var array<string,mixed>                       $user */
/** @var list<array<string,mixed>>                 $results */
/** @var string                                    $q */
/** @var string                                    $csrf */
/** @var string|null                               $flash */
/** @var array<string,list<array<string,mixed>>>   $salesByRegion */
/** @var list<array<string,mixed>>                 $allSalesList */
/** @var int                                       $defaultSalesId */

$callerId = (int) ($user['id'] ?? 0);
?>
<section class="card">
    <h1>🔍 Hledání kontaktu</h1>
    <p class="muted">Prohledá celou databázi – telefon nebo název firmy (min. 3 znaky)</p>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info"><?= crm_h($flash) ?></p>
    <?php } ?>

    <form method="get" action="<?= crm_h(crm_url('/caller/search')) ?>" class="search-form">
        <input type="text" name="q" value="<?= crm_h($q) ?>"
               placeholder="Telefon nebo název firmy..."
               class="input-search" autofocus autocomplete="off">
        <button type="submit" class="btn btn-primary">Hledat</button>
    </form>

    <?php if ($q !== '' && strlen($q) < 3) { ?>
        <p class="muted" style="margin-top:1rem;">Zadejte alespoň 3 znaky.</p>
    <?php } elseif ($q !== '' && $results === []) { ?>
        <p class="muted" style="margin-top:1rem;">Žádný kontakt nenalezen pro: <strong><?= crm_h($q) ?></strong></p>
    <?php } elseif ($results !== []) { ?>
        <p class="muted" style="margin:0.5rem 0;">Nalezeno: <?= count($results) ?> kontaktů (max 50)</p>
        <div class="contact-list" style="margin-top:0.5rem;">
            <?php foreach ($results as $c) {
                $cId    = (int) $c['id'];
                $stav   = (string) ($c['stav'] ?? '');
                $region = (string) ($c['region'] ?? '');
                $cbAt   = (string) ($c['callback_at'] ?? '');
                $nedCnt = (int) ($c['nedovolano_count'] ?? 0);
                $opRaw  = strtoupper(trim((string) ($c['operator'] ?? '')));
                $opClass = match ($opRaw) { 'TM' => 'op-tm', 'O2' => 'op-o2', 'VF' => 'op-vf', default => '' };

                // Má navolávačka právo zasáhnout?
                $assignedCaller = $c['assigned_caller_id'];
                $canAct = in_array($stav, ['NEW','ASSIGNED','CALLBACK','NEDOVOLANO','READY'], true)
                       && ($assignedCaller === null || (int) $assignedCaller === $callerId);

                $stavClass = match ($stav) {
                    'CALLED_OK'      => 'status--win',
                    'CALLED_BAD'     => 'status--loss',
                    'NEZAJEM'        => 'status--loss',
                    'CALLBACK'       => 'status--callback',
                    'IZOLACE'        => 'status--izolace',
                    'CHYBNY_KONTAKT' => 'status--chybny',
                    'FOR_SALES'      => 'status--forsales',
                    'NEDOVOLANO'     => 'status--nedovolano',
                    default          => 'status--new',
                };

                $regionSales = $salesByRegion[$region] ?? [];
                $salesList   = $regionSales !== [] ? $regionSales : $allSalesList;
                $preselect   = $defaultSalesId > 0 ? $defaultSalesId : 0;
                $noSales     = $salesList === [];
            ?>
            <div class="contact-row <?= $stavClass ?>">
                <div class="contact-info">
                    <div class="contact-name">
                        <?= crm_h((string) ($c['firma'] ?? '—')) ?>
                        <?php if ($opClass !== '') { ?>
                            <span class="cist-op-badge <?= $opClass ?>" style="font-size:0.68rem;padding:0.1rem 0.35rem;margin-left:0.4rem;opacity:0.75;"><?= $opRaw ?></span>
                        <?php } ?>
                    </div>
                    <div class="contact-details">
                        <?php if (!empty($c['telefon'])) { ?>
                            <a href="tel:<?= crm_h((string) $c['telefon']) ?>" class="contact-phone"><?= crm_h((string) $c['telefon']) ?></a>
                        <?php } ?>
                        <?php if (!empty($c['email'])) { ?>
                            <span class="contact-email"><?= crm_h((string) $c['email']) ?></span>
                        <?php } ?>
                        <?php if (!empty($c['adresa'])) { ?>
                            <span class="contact-city">Město: <?= crm_h((string) $c['adresa']) ?></span>
                        <?php } ?>
                        <?php if ($region !== '') { ?>
                            <span class="muted" style="font-size:0.8rem;"><?= crm_h($region) ?></span>
                        <?php } ?>
                    </div>
                    <?php if (!empty($c['poznamka'])) { ?>
                        <div class="contact-note"><?= crm_h((string) $c['poznamka']) ?></div>
                    <?php } ?>
                    <?php if ($stav === 'CALLBACK' && $cbAt !== '') { ?>
                        <div class="contact-callback">Callback: <?= crm_h(date('d.m.Y H:i', strtotime($cbAt))) ?></div>
                    <?php } ?>
                    <?php if (!empty($c['sales_name'])) { ?>
                        <div class="contact-sales">Obchodák: <?= crm_h((string) $c['sales_name']) ?></div>
                    <?php } ?>
                </div>

                <?php if ($canAct) { ?>
                <div class="contact-actions">
                    <form method="post" action="<?= crm_h(crm_url('/caller/status')) ?>"
                          class="action-form" onsubmit="return callerValidate(this)">
                        <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                        <input type="hidden" name="contact_id" value="<?= $cId ?>">

                        <div class="action-note">
                            <input type="text" name="poznamka" placeholder="Poznámka (povinná)..."
                                   class="input-note" autocomplete="off">
                        </div>
                        <div class="action-buttons">
                            <button type="button" class="btn-status btn-win" onclick="crmShowWinPanel(this)" title="Výhra">✓</button>
                            <button type="button" class="btn-status btn-loss" onclick="crmShowLossMenu(this)" title="Prohra">✗</button>
                            <button type="button" class="btn-status btn-cb" onclick="crmHideOthers(this,'.callback-fields')" title="Callback">↻</button>
                            <button type="submit" name="new_status" value="NEDOVOLANO"
                                    class="btn-status btn-nedovolano" onclick="return crmNedovolano(this)" title="Nedovoláno">📵</button>
                        </div>

                        <!-- Win panel -->
                        <div class="win-panel hidden">
                            <?php if ($noSales) { ?>
                                <span class="loss-menu-label" style="color:#e74c3c;">⚠ Žádní obchodáci v tomto kraji.</span>
                            <?php } else { ?>
                                <span class="loss-menu-label">Předat výhru:</span>
                                <select name="sales_id" class="input-sales">
                                    <?php foreach ($salesList as $s) {
                                        $sel = ((int)$s['id'] === $preselect) ? ' selected' : '';
                                    ?>
                                        <option value="<?= (int)$s['id'] ?>"<?= $sel ?>><?= crm_h((string)$s['jmeno']) ?></option>
                                    <?php } ?>
                                </select>
                                <button type="submit" name="new_status" value="CALLED_OK" class="btn-win-confirm">✓ Potvrdit výhru</button>
                                <button type="button" onclick="this.closest('.win-panel').classList.add('hidden')" class="btn-loss-zpet">← Zpět</button>
                            <?php } ?>
                        </div>

                        <!-- Loss menu -->
                        <div class="loss-menu hidden">
                            <span class="loss-menu-label">Důvod zamítnutí:</span>
                            <div class="loss-btn-row">
                                <button type="button" class="btn-loss-sub" onclick="crmShowNezajemPanel(this)">Nezájem</button>
                                <button type="submit" name="new_status" value="IZOLACE" class="btn-loss-sub btn-loss-izolace">🚫 Izolace</button>
                                <button type="submit" name="new_status" value="CHYBNY_KONTAKT" class="btn-loss-sub btn-loss-chybny">✗ Chybný kontakt</button>
                                <button type="button" onclick="this.closest('.loss-menu').classList.add('hidden')" class="btn-loss-sub btn-loss-zpet">← Zpět</button>
                            </div>
                            <div class="nezajem-panel hidden">
                                <span class="loss-menu-label">Upřesni důvod nezájmu:</span>
                                <select name="rejection_reason" class="input-rejection">
                                    <option value="">— vyberte —</option>
                                    <option value="nezajem">Obecný nezájem</option>
                                    <option value="cena">Cena</option>
                                    <option value="ma_smlouvu">Má smlouvu jinde</option>
                                    <option value="spatny_kontakt">Špatný kontakt</option>
                                    <option value="jine">Jiné</option>
                                </select>
                                <button type="submit" name="new_status" value="NEZAJEM" class="btn-loss-sub">✓ Potvrdit nezájem</button>
                                <button type="button" onclick="this.closest('.nezajem-panel').classList.add('hidden')" class="btn-loss-sub btn-loss-zpet">← Zpět</button>
                            </div>
                        </div>

                        <!-- Callback -->
                        <div class="callback-fields hidden">
                            <label class="label-sm">Zavolat zpět:</label>
                            <input type="datetime-local" name="callback_at" class="input-cb">
                            <button type="submit" name="new_status" value="CALLBACK" class="btn btn-secondary btn-sm">Nastavit callback</button>
                        </div>
                    </form>
                </div>
                <?php } else { ?>
                <div class="contact-status-label">
                    <?= match ($stav) {
                        'CALLED_OK'      => '<span class="status-tag tag-win">✓ Výhra</span>',
                        'CALLED_BAD'     => '<span class="status-tag tag-loss">Prohra</span>',
                        'NEZAJEM'        => '<span class="status-tag tag-loss">Nezájem</span>',
                        'IZOLACE'        => '<span class="status-tag tag-izolace">🚫 Izolace</span>',
                        'CHYBNY_KONTAKT' => '<span class="status-tag tag-chybny">Chybný</span>',
                        'FOR_SALES'      => '<span class="status-tag tag-forsales">Předáno OZ</span>',
                        'NEDOVOLANO'     => '<span class="status-tag tag-nedovolano">📵 Nedovoláno</span>',
                        'READY'          => '<span class="status-tag" style="background:rgba(61,139,253,0.15);color:var(--accent);">Připraveno</span>',
                        default          => crm_h($stav),
                    } ?>
                    <?php if (in_array($stav, ['CALLED_OK','CALLED_BAD','NEZAJEM','IZOLACE','CHYBNY_KONTAKT','FOR_SALES'], true)) { ?>
                        <span class="muted" style="font-size:0.75rem; display:block; margin-top:0.2rem;">
                            Přiřazeno jiné navolávačce
                        </span>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>
            <?php } ?>
        </div>
    <?php } ?>

    <div style="margin-top:1.5rem;">
        <a href="<?= crm_h(crm_url('/caller')) ?>" class="btn btn-secondary">← Zpět na kontakty</a>
    </div>
</section>

<script>
var CRM_CSRF     = <?= json_encode($csrf) ?>;
var CRM_CSRF_KEY = <?= json_encode(crm_csrf_field_name()) ?>;

function callerValidate(form) {
    var note = form.querySelector('input[name="poznamka"]');
    if (!note || !note.value.trim()) {
        if (note) { note.style.borderColor='#e74c3c'; note.placeholder='POZNÁMKA JE POVINNÁ!'; note.focus(); }
        return false;
    }
    return true;
}
function crmHideOthers(btn, sel) {
    var a = btn.closest('.contact-actions');
    ['.win-panel','.loss-menu','.callback-fields'].forEach(function(s) {
        var el=a.querySelector(s); if(el) el.classList.add('hidden');
    });
    var t=a.querySelector(sel); if(t) t.classList.toggle('hidden');
}
function crmShowWinPanel(btn) {
    var f=btn.closest('.action-form');
    var n=f.querySelector('input[name="poznamka"]');
    if(!n.value.trim()){n.style.borderColor='#e74c3c';n.placeholder='POZNÁMKA JE POVINNÁ!';n.focus();return;}
    crmHideOthers(btn,'.win-panel');
}
function crmShowLossMenu(btn) {
    var f=btn.closest('.action-form');
    var n=f.querySelector('input[name="poznamka"]');
    if(!n.value.trim()){n.style.borderColor='#e74c3c';n.placeholder='POZNÁMKA JE POVINNÁ!';n.focus();return;}
    crmHideOthers(btn,'.loss-menu');
}
function crmNedovolano(btn) {
    var f=btn.closest('.action-form');
    var n=f.querySelector('input[name="poznamka"]');
    if(n && n.value.trim()==='') n.value='Nedovoláno';
    return true;
}
function crmShowNezajemPanel(btn) {
    var m=btn.closest('.loss-menu');
    var p=m.querySelector('.nezajem-panel');
    if(p) p.classList.toggle('hidden');
}
</script>
