<?php
// e:\Snecinatripu\app\views\partials\notes_timeline.php
declare(strict_types=1);
/**
 * Sjednocená poznámková osa kontaktu — nejnovější nahoře.
 * Vstup: $timeline = list<array{author_name,author_role,created_at,text,source}>
 *        $timelineCompact (bool, volitelné) — menší varianta do úzké karty
 *
 * Bez ikonky: každý řádek = jméno + datum/čas + text (dle přání).
 */
/** @var list<array<string,mixed>> $timeline */
$timeline = $timeline ?? [];
$_ntCompact = !empty($timelineCompact);
?>
<div class="notes-timeline<?= $_ntCompact ? ' notes-timeline--compact' : '' ?>">
    <?php if ($timeline === []) { ?>
        <div class="notes-timeline__empty">Zatím žádné poznámky.</div>
    <?php } else {
        foreach ($timeline as $n) {
            $when = !empty($n['created_at'])
                ? date('d.m.Y H:i', (int) strtotime((string) $n['created_at']))
                : '';
            $name = (string) ($n['author_name'] ?? '—');
            $text = (string) ($n['text'] ?? '');
            if (trim($text) === '') { continue; }
    ?>
        <div class="notes-timeline__item">
            <div class="notes-timeline__head">
                <span class="notes-timeline__author"><?= crm_h($name) ?></span>
                <?php if ($when !== '') { ?>
                    <span class="notes-timeline__time"><?= crm_h($when) ?></span>
                <?php } ?>
            </div>
            <div class="notes-timeline__text"><?= nl2br(crm_h($text)) ?></div>
        </div>
    <?php }
    } ?>
</div>

<?php if (empty($GLOBALS['__notes_timeline_css'])) { $GLOBALS['__notes_timeline_css'] = true; ?>
<style>
.notes-timeline { display:flex; flex-direction:column; gap:0.5rem; }
.notes-timeline__empty { font-size:0.8rem; color:var(--muted,#9ca3af); font-style:italic; }
.notes-timeline__item {
    background: rgba(0,0,0,0.025);
    border-left: 3px solid rgba(37,99,235,0.35);
    border-radius: 0 6px 6px 0;
    padding: 0.4rem 0.6rem;
}
.notes-timeline__head {
    display:flex; align-items:baseline; gap:0.5rem; justify-content:space-between;
    margin-bottom:0.15rem;
}
.notes-timeline__author { font-weight:700; font-size:0.82rem; color:var(--text,#111); }
.notes-timeline__time   { font-size:0.7rem; color:var(--muted,#6b7280); white-space:nowrap; }
.notes-timeline__text   { font-size:0.85rem; line-height:1.45; color:var(--text,#111); white-space:pre-wrap; }
.notes-timeline--compact .notes-timeline__text { font-size:0.8rem; }
.notes-timeline--compact .notes-timeline__item { padding:0.3rem 0.5rem; }
</style>
<?php } ?>
