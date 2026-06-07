<?php
// e:\Snecinatripu\app\views\my-additions\detail.php
declare(strict_types=1);
/** @var array<string,mixed>       $user */
/** @var array<string,mixed>       $contact */
/** @var list<array<string,mixed>> $ozNotes  posledních 10 poznámek OZ */
/** @var ?string                   $flash */

// Stav badge — barevný
function md_stavBadge(string $stav): array {
    return match (true) {
        $stav === 'UZAVRENO' => ['✓ Uzavřeno — smlouva podepsaná', '#dcfce7', '#166534'],
        in_array($stav, ['SMLOUVA','BO_PREDANO','BO_VPRACI'], true)
                              => ['🏢 U back-office — připravuje se smlouva', '#ede9fe', '#5b21b6'],
        $stav === 'BO_VRACENO' => ['↩ BO vrátil — OZ doplňuje údaje', '#fef3c7', '#92400e'],
        $stav === 'SCHUZKA'   => ['📅 Schůzka domluvená', '#cffafe', '#155e75'],
        $stav === 'CALLBACK'  => ['↻ Callback — OZ se ozve znovu', '#fed7aa', '#9a3412'],
        $stav === 'SANCE'     => ['💡 Šance — čeká na podklady', '#fef9c3', '#854d0e'],
        $stav === 'NABIDKA'   => ['📨 OZ odeslal nabídku', '#cffafe', '#155e75'],
        in_array($stav, ['NOVE','OBVOLANO','ZPRACOVAVA'], true)
                              => ['📋 OZ na tom pracuje', '#e0e7ff', '#3730a3'],
        in_array($stav, ['NEZAJEM','NERELEVANTNI'], true)
                              => ['✗ Zákazník nemá zájem', '#f3f4f6', '#6b7280'],
        $stav === 'CALLED_OK' => ['🆕 Čeká v Příchozí leady OZ', '#dbeafe', '#1e40af'],
        default               => [$stav, '#f3f4f6', '#374151'],
    };
}

[$bText, $bBg, $bFg] = md_stavBadge((string) ($contact['effective_stav'] ?? '—'));
$createdAt   = (string) ($contact['created_at'] ?? '');
$workflowAt  = (string) ($contact['workflow_updated_at'] ?? '');
$callbackAt  = (string) ($contact['callback_at'] ?? '');
$schuzkaAt   = (string) ($contact['schuzka_at'] ?? '');
$prilez      = trim((string) ($contact['prilez'] ?? ''));
$prilezDo    = (string) ($contact['prilez_do'] ?? '');
?>

<style>
.md-wrap { max-width: 900px; margin: 0 auto; }
.md-back { font-size: 0.82rem; color: var(--color-text-muted); text-decoration: none; }
.md-back:hover { color: var(--color-text); }
.md-stav {
    display: flex; align-items: center; gap: 0.8rem; flex-wrap: wrap;
    background: #fff; border: 1px solid var(--color-border);
    border-radius: 10px; padding: 1rem 1.2rem; margin-bottom: 1rem;
}
.md-stav__main { display: flex; flex-direction: column; gap: 0.3rem; flex: 1 1 280px; }
.md-stav__badge {
    display: inline-block; padding: 0.4rem 0.8rem; border-radius: 18px;
    font-size: 0.92rem; font-weight: 700; align-self: flex-start;
}
.md-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;
}
@media (max-width: 700px) { .md-grid { grid-template-columns: 1fr; } }
.md-card {
    background: #fff; border: 1px solid var(--color-border);
    border-radius: 8px; padding: 0.9rem 1rem;
}
.md-card h3 { margin: 0 0 0.6rem; font-size: 0.78rem; text-transform: uppercase;
              letter-spacing: 0.04em; color: var(--color-text-muted); }
.md-row { display: grid; grid-template-columns: 100px 1fr; gap: 0.4rem; padding: 0.25rem 0;
          font-size: 0.85rem; border-bottom: 1px dashed rgba(0,0,0,0.06); }
.md-row:last-child { border-bottom: 0; }
.md-row__lbl { color: var(--color-text-muted); }
.md-row__val { color: var(--color-text); word-break: break-word; }
.md-notes { background: #fff; border: 1px solid var(--color-border);
            border-radius: 8px; padding: 0.9rem 1rem; }
.md-notes h3 { margin: 0 0 0.6rem; font-size: 0.78rem; text-transform: uppercase;
               letter-spacing: 0.04em; color: var(--color-text-muted); }
.md-note { background: rgba(14, 116, 144, 0.05); border-left: 3px solid #0e7490;
           padding: 0.5rem 0.7rem; border-radius: 0 5px 5px 0; margin-bottom: 0.4rem; }
.md-note__meta { font-size: 0.7rem; color: var(--color-text-muted); margin-bottom: 0.2rem; }
.md-note__text { font-size: 0.85rem; color: var(--color-text); line-height: 1.4; }
.md-empty { font-size: 0.82rem; color: var(--color-text-muted); font-style: italic;
            padding: 0.6rem; text-align: center; }
.md-ro { background: #f0fdf4; border: 1px solid #86efac; border-radius: 6px;
         padding: 0.55rem 0.8rem; font-size: 0.78rem; color: #166534; margin-bottom: 1rem; }
</style>

<section class="md-wrap">
    <p style="margin-bottom:0.5rem;">
        <a href="<?= crm_h(crm_url('/me/added-contacts')) ?>" class="md-back">
            ← Zpět na moje doporučenky
        </a>
    </p>

    <h1 style="margin:0 0 0.5rem;"><?= crm_h((string) ($contact['firma'] ?? 'Detail')) ?></h1>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info" style="margin-bottom:1rem;"><?= crm_h($flash) ?></p>
    <?php } ?>

    <div class="md-ro">
        👀 Toto je <strong>náhled bez možnosti editace</strong>.
        Údaje, stav a poznámky spravuje OZ, kterému je kontakt přiřazen.
    </div>

    <!-- Aktuální stav -->
    <div class="md-stav">
        <div class="md-stav__main">
            <span class="md-row__lbl" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.04em;">
                Aktuální stav
            </span>
            <span class="md-stav__badge" style="background:<?= $bBg ?>;color:<?= $bFg ?>;">
                <?= crm_h($bText) ?>
            </span>
            <?php if ($workflowAt !== '') { ?>
                <span style="font-size:0.72rem;color:var(--color-text-muted);">
                    Poslední změna stavu: <?= crm_h(date('d.m.Y H:i', strtotime($workflowAt) ?: 0)) ?>
                </span>
            <?php } ?>
        </div>
        <div style="text-align:right;font-size:0.78rem;color:var(--color-text-muted);">
            Přiřazený OZ:<br>
            <strong style="color:var(--color-text);font-size:0.95rem;">
                <?= crm_h((string) ($contact['oz_name'] ?? '—')) ?>
            </strong>
        </div>
    </div>

    <!-- Údaje kontaktu + meta -->
    <div class="md-grid">
        <div class="md-card">
            <h3>📞 Kontaktní údaje</h3>
            <div class="md-row">
                <span class="md-row__lbl">Firma</span>
                <span class="md-row__val"><?= crm_h((string)($contact['firma'] ?? '—')) ?></span>
            </div>
            <div class="md-row">
                <span class="md-row__lbl">IČO</span>
                <span class="md-row__val" style="font-family:monospace;">
                    <?= crm_h((string)($contact['ico'] ?? '—')) ?>
                </span>
            </div>
            <div class="md-row">
                <span class="md-row__lbl">Telefon</span>
                <span class="md-row__val" style="font-family:monospace;">
                    <?= crm_h((string)($contact['telefon'] ?? '—')) ?>
                </span>
            </div>
            <div class="md-row">
                <span class="md-row__lbl">E-mail</span>
                <span class="md-row__val"><?= crm_h((string)($contact['email'] ?? '—')) ?></span>
            </div>
            <div class="md-row">
                <span class="md-row__lbl">Adresa</span>
                <span class="md-row__val"><?= crm_h((string)($contact['adresa'] ?? '—')) ?></span>
            </div>
            <div class="md-row">
                <span class="md-row__lbl">Kraj</span>
                <span class="md-row__val">
                    <?= crm_h(function_exists('crm_region_label')
                            ? crm_region_label((string) $contact['region'])
                            : (string) $contact['region']) ?>
                </span>
            </div>
            <?php if (!empty($contact['operator'])) { ?>
                <div class="md-row">
                    <span class="md-row__lbl">Operátor</span>
                    <span class="md-row__val"><?= crm_h((string) $contact['operator']) ?></span>
                </div>
            <?php } ?>
        </div>

        <div class="md-card">
            <h3>📅 Časová osa</h3>
            <div class="md-row">
                <span class="md-row__lbl">Přidáno</span>
                <span class="md-row__val">
                    <?= crm_h($createdAt !== '' ? date('d.m.Y H:i', strtotime($createdAt) ?: 0) : '—') ?>
                </span>
            </div>
            <?php if ($callbackAt !== '') { ?>
                <div class="md-row">
                    <span class="md-row__lbl">Callback</span>
                    <span class="md-row__val">
                        <?= crm_h(date('d.m.Y H:i', strtotime($callbackAt) ?: 0)) ?>
                    </span>
                </div>
            <?php } ?>
            <?php if ($schuzkaAt !== '') { ?>
                <div class="md-row">
                    <span class="md-row__lbl">Schůzka</span>
                    <span class="md-row__val">
                        <?= crm_h(date('d.m.Y H:i', strtotime($schuzkaAt) ?: 0)) ?>
                    </span>
                </div>
            <?php } ?>

            <?php if ($prilez !== '') { ?>
                <h3 style="margin-top:0.85rem;">💡 Příležitost</h3>
                <div class="md-row">
                    <span class="md-row__lbl">Co chce</span>
                    <span class="md-row__val">
                        <?= crm_h($prilez === 'ano' ? 'má příležitost' : $prilez) ?>
                    </span>
                </div>
                <?php if ($prilezDo !== '' && $prilezDo !== '0000-00-00') { ?>
                    <div class="md-row">
                        <span class="md-row__lbl">Do kdy</span>
                        <span class="md-row__val">
                            <?= crm_h(date('d.m.Y', strtotime($prilezDo) ?: 0)) ?>
                        </span>
                    </div>
                <?php } ?>
            <?php } ?>

            <?php if (!empty($contact['poznamka'])) { ?>
                <h3 style="margin-top:0.85rem;">📝 Tvá původní poznámka</h3>
                <p style="font-size:0.85rem;line-height:1.4;margin:0;
                          background:rgba(0,0,0,0.03);padding:0.5rem 0.7rem;border-radius:5px;">
                    <?= nl2br(crm_h((string) $contact['poznamka'])) ?>
                </p>
            <?php } ?>
        </div>
    </div>

    <!-- Poznámky OZ — co OZ se zákazníkem řešil -->
    <div class="md-notes">
        <h3>💬 Co OZ se zákazníkem zatím řešil (posledních 10 poznámek)</h3>
        <?php if ($ozNotes === []) { ?>
            <div class="md-empty">
                Zatím žádné poznámky. OZ s kontaktem ještě nepracoval, nebo neuložil žádnou poznámku.
            </div>
        <?php } else { ?>
            <?php foreach ($ozNotes as $n) { ?>
                <div class="md-note">
                    <div class="md-note__meta">
                        <strong><?= crm_h((string) $n['author']) ?></strong> ·
                        <?= crm_h(date('d.m.Y H:i', strtotime((string) $n['created_at']) ?: 0)) ?>
                    </div>
                    <div class="md-note__text"><?= nl2br(crm_h((string) $n['note'])) ?></div>
                </div>
            <?php } ?>
        <?php } ?>
    </div>
</section>
