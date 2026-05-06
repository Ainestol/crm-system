<?php
// e:\Snecinatripu\app\views\admin\contact-proposals\index.php
declare(strict_types=1);
/** @var array<string,mixed>             $user */
/** @var string                          $csrf */
/** @var ?string                         $flash */
/** @var string                          $tab          'pending'|'approved'|'rejected' */
/** @var array<string,int>               $counts       per status */
/** @var list<array<string,mixed>>       $proposals */
/** @var list<array<string,mixed>>       $salesUsers */
?>

<style>
.cpa-wrap { max-width: 1100px; margin: 0 auto; }
.cpa-wrap h1 { margin: 0 0 0.5rem; font-size: 1.4rem; }
.cpa-wrap .lead {
    color: var(--color-text-muted);
    font-size: 0.85rem;
    margin-bottom: 1rem;
}

.cpa-tabs {
    display: flex; gap: 0.4rem; flex-wrap: wrap;
    margin-bottom: 1rem;
    border-bottom: 1px solid var(--color-border);
    padding-bottom: 0.4rem;
}
.cpa-tab {
    text-decoration: none;
    padding: 0.4rem 0.85rem;
    font-size: 0.82rem;
    color: var(--color-text-muted);
    border-radius: 5px 5px 0 0;
    border: 1px solid transparent;
    border-bottom: 0;
}
.cpa-tab:hover { color: var(--color-text); background: var(--color-surface); }
.cpa-tab.is-active {
    background: var(--color-card-bg);
    color: var(--color-text);
    border-color: var(--color-border);
    font-weight: 600;
}
.cpa-tab__count {
    background: var(--color-surface);
    border-radius: 999px;
    padding: 0.05rem 0.5rem;
    font-size: 0.7rem;
    margin-left: 0.35rem;
    color: var(--color-text-muted);
}
.cpa-tab.is-active .cpa-tab__count {
    background: var(--color-badge-nove-bg);
    color: var(--color-badge-nove-text);
}

.cpa-empty {
    padding: 2.5rem 1rem;
    text-align: center;
    color: var(--color-text-muted);
    font-size: 0.9rem;
}

.cpa-card {
    background: var(--color-card-bg);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 0.85rem 1rem;
    margin-bottom: 0.7rem;
    box-shadow: var(--shadow-card);
}
.cpa-card__head {
    display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;
    margin-bottom: 0.5rem;
}
.cpa-card__firma {
    font-size: 1.02rem; font-weight: 700; color: var(--color-text);
}
.cpa-card__meta {
    margin-left: auto;
    font-size: 0.72rem;
    color: var(--color-text-muted);
}
.cpa-card__meta strong { color: var(--color-text); }

.cpa-fields {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.4rem 1.2rem;
    font-size: 0.82rem;
    margin-bottom: 0.6rem;
}
@media (max-width: 600px) {
    .cpa-fields { grid-template-columns: 1fr; }
}
.cpa-field { display: flex; gap: 0.4rem; }
.cpa-field__lbl { color: var(--color-text-muted); min-width: 70px; }
.cpa-field__val { color: var(--color-text); flex: 1; word-break: break-word; }
.cpa-field__val a { color: var(--color-badge-nove); text-decoration: none; }
.cpa-field__val a:hover { text-decoration: underline; }

.cpa-poznamka {
    font-size: 0.82rem;
    background: var(--color-surface);
    border-left: 3px solid var(--color-badge-nove);
    padding: 0.5rem 0.75rem;
    border-radius: 0 5px 5px 0;
    margin-bottom: 0.7rem;
    white-space: pre-wrap;
    color: var(--color-text);
}

.cpa-review-info {
    font-size: 0.78rem;
    color: var(--color-text-muted);
    padding: 0.4rem 0.6rem;
    background: var(--color-surface);
    border-radius: 5px;
    margin-bottom: 0.5rem;
}

.cpa-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: flex-end; }
.cpa-actions__col {
    display: flex; flex-direction: column; gap: 0.25rem;
    flex: 1; min-width: 180px;
}
.cpa-actions__col label {
    font-size: 0.72rem; color: var(--color-text-muted); font-weight: 600;
}
.cpa-actions select, .cpa-actions input[type="text"] {
    background: #ffffff;
    color: var(--color-text);
    border: 1px solid var(--color-border-strong);
    border-radius: 5px;
    padding: 0.35rem 0.55rem;
    font-size: 0.82rem;
    font-family: var(--font-main);
}

.cpa-btn-approve {
    background: #2ecc71; color: #fff;
    border: 0; border-radius: 5px;
    padding: 0.45rem 0.9rem; font-size: 0.82rem; font-weight: 600;
    cursor: pointer; font-family: var(--font-main);
}
.cpa-btn-approve:hover { filter: brightness(0.95); }
.cpa-btn-reject {
    background: transparent;
    color: #e74c3c;
    border: 1px solid rgba(231,76,60,0.4);
    border-radius: 5px;
    padding: 0.45rem 0.9rem; font-size: 0.82rem; font-weight: 600;
    cursor: pointer; font-family: var(--font-main);
}
.cpa-btn-reject:hover { background: rgba(231,76,60,0.08); }

.cpa-status-pill {
    display: inline-block;
    padding: 0.1rem 0.5rem;
    font-size: 0.7rem; font-weight: 700;
    border-radius: 999px; letter-spacing: 0.04em;
}
.cpa-status-pill--approved { background: #d4f4dd; color: #1f7a3a; }
.cpa-status-pill--rejected { background: #fde2e2; color: #b83030; }
</style>

<section class="card cpa-wrap">
    <h1>📋 Návrhy kontaktů ke schválení</h1>
    <p class="lead">
        Návrhy nových kontaktů (manual hot leads), které čekají na schválení.
        Při schválení vyberte konkrétního OZ — kontakt se mu rovnou objeví v <strong>Příchozí leady</strong>.
    </p>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info" style="margin-bottom:1rem;"><?= crm_h($flash) ?></p>
    <?php } ?>

    <!-- Tabby per status -->
    <div class="cpa-tabs">
        <?php
        $tabsDef = [
            'pending'  => ['🕐 Čekající',  'pending'],
            'approved' => ['✅ Schválené', 'approved'],
            'rejected' => ['❌ Zamítnuté', 'rejected'],
        ];
        foreach ($tabsDef as $tabKey => [$label, $st]) {
            $isActive = $tab === $tabKey ? 'is-active' : '';
            $cnt = (int) ($counts[$st] ?? 0);
            ?>
            <a href="<?= crm_h(crm_url('/admin/contact-proposals?tab=' . $tabKey)) ?>"
               class="cpa-tab <?= $isActive ?>">
                <?= crm_h($label) ?>
                <span class="cpa-tab__count"><?= $cnt ?></span>
            </a>
        <?php } ?>
    </div>

    <?php if ($proposals === []) { ?>
        <div class="cpa-empty">
            <?php if ($tab === 'pending') { ?>
                Žádné čekající návrhy. 🎉 Až někdo navrhne nový kontakt, objeví se tady.
            <?php } elseif ($tab === 'approved') { ?>
                Žádné schválené návrhy v této kategorii.
            <?php } else { ?>
                Žádné zamítnuté návrhy.
            <?php } ?>
        </div>
    <?php } ?>

    <?php foreach ($proposals as $p) {
        $pid       = (int) ($p['id'] ?? 0);
        $firma     = (string) ($p['firma'] ?? '');
        $ico       = (string) ($p['ico'] ?? '');
        $telefon   = (string) ($p['telefon'] ?? '');
        $email     = (string) ($p['email'] ?? '');
        $adresa    = (string) ($p['adresa'] ?? '');
        $region    = (string) ($p['region'] ?? '');
        $operator  = (string) ($p['operator'] ?? '');
        $poznamka  = (string) ($p['poznamka'] ?? '');
        $proposer  = (string) ($p['proposer_name'] ?? '—');
        $sugOzId   = (int) ($p['suggested_oz_id'] ?? 0);
        $sugOzName = (string) ($p['suggested_oz_name'] ?? '');
        $createdAt = (string) ($p['created_at'] ?? '');
        $createdFmt = $createdAt !== '' ? date('d.m.Y H:i', strtotime($createdAt)) : '—';

        // Pro approved/rejected
        $reviewer  = (string) ($p['reviewer_name'] ?? '');
        $reviewedAt = (string) ($p['reviewed_at'] ?? '');
        $reviewedFmt = $reviewedAt !== '' ? date('d.m.Y H:i', strtotime($reviewedAt)) : '';
        $reviewNote = (string) ($p['review_note'] ?? '');
        $convertedId = (int) ($p['converted_contact_id'] ?? 0);
        $status = (string) ($p['status'] ?? '');
    ?>
    <div class="cpa-card">
        <div class="cpa-card__head">
            <span class="cpa-card__firma"><?= crm_h($firma) ?></span>
            <?php if ($status === 'approved') { ?>
                <span class="cpa-status-pill cpa-status-pill--approved">✓ schváleno</span>
            <?php } elseif ($status === 'rejected') { ?>
                <span class="cpa-status-pill cpa-status-pill--rejected">✗ zamítnuto</span>
            <?php } ?>
            <span class="cpa-card__meta">
                Navrhl: <strong><?= crm_h($proposer) ?></strong> · <?= crm_h($createdFmt) ?>
            </span>
        </div>

        <div class="cpa-fields">
            <div class="cpa-field">
                <span class="cpa-field__lbl">IČO</span>
                <span class="cpa-field__val">
                    <code><?= crm_h($ico) ?></code>
                    <a href="<?= crm_h('https://ares.gov.cz/ekonomicke-subjekty?ico=' . urlencode($ico)) ?>"
                       target="_blank" rel="noopener noreferrer"
                       title="Ověřit v ARES">🔗 ARES</a>
                </span>
            </div>
            <div class="cpa-field">
                <span class="cpa-field__lbl">Telefon</span>
                <span class="cpa-field__val"><code><?= crm_h($telefon) ?></code></span>
            </div>
            <div class="cpa-field">
                <span class="cpa-field__lbl">E-mail</span>
                <span class="cpa-field__val"><?= crm_h($email) ?></span>
            </div>
            <div class="cpa-field">
                <span class="cpa-field__lbl">Adresa</span>
                <span class="cpa-field__val"><?= crm_h($adresa) ?></span>
            </div>
            <div class="cpa-field">
                <span class="cpa-field__lbl">Kraj</span>
                <span class="cpa-field__val"><?= crm_h(crm_region_label($region)) ?></span>
            </div>
            <div class="cpa-field">
                <span class="cpa-field__lbl">Operátor</span>
                <span class="cpa-field__val"><?= crm_h($operator) ?></span>
            </div>
            <?php if ($sugOzId > 0) { ?>
            <div class="cpa-field">
                <span class="cpa-field__lbl">Doporučený OZ</span>
                <span class="cpa-field__val">💡 <?= crm_h($sugOzName) ?></span>
            </div>
            <?php } ?>
        </div>

        <?php if ($poznamka !== '') { ?>
            <div class="cpa-poznamka">
                <strong>📝 Poznámka:</strong><br>
                <?= crm_h($poznamka) ?>
            </div>
        <?php } ?>

        <?php if ($status === 'pending') { ?>
            <!-- Schvalovací akce — 2 formuláře vedle sebe -->
            <form method="post" action="<?= crm_h(crm_url('/admin/contact-proposals/approve')) ?>"
                  class="cpa-actions"
                  onsubmit="return confirm('Schválit a přiřadit kontakt OZ?');">
                <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                <input type="hidden" name="proposal_id" value="<?= $pid ?>">

                <div class="cpa-actions__col">
                    <label>Přiřadit OZ <span style="color:#e74c3c;">*</span></label>
                    <select name="assigned_oz_id" required>
                        <option value="">— vyberte OZ —</option>
                        <?php foreach ($salesUsers as $oz) {
                            $oid = (int) ($oz['id'] ?? 0);
                            $sel = $oid === $sugOzId ? 'selected' : '';
                        ?>
                            <option value="<?= $oid ?>" <?= $sel ?>>
                                <?= crm_h((string) ($oz['jmeno'] ?? '')) ?>
                                <?= $oid === $sugOzId ? '(doporučeno)' : '' ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="cpa-actions__col" style="flex:2;">
                    <label>Poznámka schválení (volitelně)</label>
                    <input type="text" name="review_note" maxlength="500"
                           placeholder="Např. Ověřeno v ARES, validní firma">
                </div>

                <button type="submit" class="cpa-btn-approve">✅ Schválit</button>
            </form>

            <!-- Zamítnutí -->
            <form method="post" action="<?= crm_h(crm_url('/admin/contact-proposals/reject')) ?>"
                  class="cpa-actions" style="margin-top:0.5rem;"
                  onsubmit="return confirm('Zamítnout tento návrh?');">
                <input type="hidden" name="<?= crm_h(crm_csrf_field_name()) ?>" value="<?= crm_h($csrf) ?>">
                <input type="hidden" name="proposal_id" value="<?= $pid ?>">

                <div class="cpa-actions__col" style="flex:3;">
                    <label>Důvod zamítnutí <span style="color:#e74c3c;">*</span></label>
                    <input type="text" name="reason" required maxlength="500"
                           placeholder="Např. Duplicita s existujícím kontaktem #1234">
                </div>

                <button type="submit" class="cpa-btn-reject">❌ Zamítnout</button>
            </form>
        <?php } else { ?>
            <!-- Already reviewed: zobrazit info kdo + kdy -->
            <div class="cpa-review-info">
                <strong>
                    <?= $status === 'approved' ? '✓ Schváleno' : '✗ Zamítnuto' ?>
                </strong> uživatelem
                <strong><?= crm_h($reviewer) ?></strong>
                · <?= crm_h($reviewedFmt) ?>
                <?php if ($status === 'approved' && $convertedId > 0) { ?>
                    · vytvořen kontakt <strong>#<?= $convertedId ?></strong>
                <?php } ?>
                <?php if ($reviewNote !== '') { ?>
                    <br><br>
                    <em>„<?= crm_h($reviewNote) ?>"</em>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
    <?php } ?>
</section>
