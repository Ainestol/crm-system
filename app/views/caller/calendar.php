<?php
// e:\Snecinatripu\app\views\caller\calendar.php
declare(strict_types=1);
/** @var array<string, mixed>              $user */
/** @var int                               $year */
/** @var int                               $month */
/** @var array<string, list<array<string,mixed>>> $callbacksByDate  date → callbacks */
/** @var list<array<string,mixed>>         $upcoming */
/** @var string                            $csrf */
/** @var string|null                       $flash */

$today      = date('Y-m-d');
$firstDay   = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth= (int) date('t', $firstDay);
$startDow   = (int) date('N', $firstDay); // 1=Po … 7=Ne

$prevMonth  = $month - 1 < 1  ? 12 : $month - 1;
$prevYear   = $month - 1 < 1  ? $year - 1 : $year;
$nextMonth  = $month + 1 > 12 ? 1  : $month + 1;
$nextYear   = $month + 1 > 12 ? $year + 1 : $year;

$monthNames = ['', 'Leden','Únor','Březen','Duben','Květen','Červen',
               'Červenec','Srpen','Září','Říjen','Listopad','Prosinec'];
$dayNames   = ['Po','Út','St','Čt','Pá','So','Ne'];
?>

<section class="card">
    <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:0.25rem;">
        <h1 style="margin:0;">📅 Kalendář callbacků</h1>
        <a href="<?= crm_h(crm_url('/caller')) ?>" class="btn btn-secondary btn-sm">← Zpět na kontakty</a>
    </div>
    <p class="muted">Přihlášena: <strong><?= crm_h((string)($user['jmeno']??'')) ?></strong></p>

    <?php if (!empty($flash)) { ?>
        <p class="alert alert-info"><?= crm_h($flash) ?></p>
    <?php } ?>

    <!-- Navigace měsícem -->
    <div class="cal-nav">
        <a href="<?= crm_h(crm_url("/caller/calendar?y={$prevYear}&m={$prevMonth}")) ?>" class="cal-nav-btn">‹</a>
        <span class="cal-month-label"><?= $monthNames[$month] ?> <?= $year ?></span>
        <a href="<?= crm_h(crm_url("/caller/calendar?y={$nextYear}&m={$nextMonth}")) ?>" class="cal-nav-btn">›</a>
    </div>

    <!-- Mřížka kalendáře -->
    <div class="cal-grid">
        <?php foreach ($dayNames as $dn) { ?>
            <div class="cal-header-cell"><?= $dn ?></div>
        <?php } ?>

        <?php
        // Prázdné buňky před 1. dnem
        for ($i = 1; $i < $startDow; $i++) {
            echo '<div class="cal-cell cal-cell--empty"></div>';
        }

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $hasCb   = isset($callbacksByDate[$dateStr]);
            $isToday = $dateStr === $today;
            $isPast  = $dateStr < $today;
            $cbCount = $hasCb ? count($callbacksByDate[$dateStr]) : 0;

            $cls = 'cal-cell';
            if ($isToday) $cls .= ' cal-cell--today';
            if ($isPast && !$isToday) $cls .= ' cal-cell--past';
            if ($hasCb) $cls .= ' cal-cell--has-cb';
        ?>
            <div class="<?= $cls ?>"
                 <?= $hasCb ? 'onclick="crmCalDay(\'' . $dateStr . '\')"' : '' ?>
                 <?= $hasCb ? 'title="' . $cbCount . ' callback(ů)"' : '' ?>>
                <span class="cal-day-num"><?= $day ?></span>
                <?php if ($hasCb) { ?>
                    <span class="cal-cb-dot"><?= $cbCount ?></span>
                <?php } ?>
            </div>
        <?php } ?>
    </div>

    <!-- Detail callbacků pro vybraný den -->
    <div id="cal-day-detail" class="cal-day-detail hidden"></div>

    <!-- Nadcházející callbacky (seznam) -->
    <div class="cal-upcoming">
        <h2 class="cal-section-title">Nadcházející callbacky</h2>
        <?php if (empty($upcoming)) { ?>
            <p class="muted">Žádné naplánované callbacky.</p>
        <?php } else { ?>
            <div class="cal-cb-list">
            <?php foreach ($upcoming as $cb) {
                $ts       = strtotime((string)$cb['callback_at']);
                $diff     = $ts - time();
                $isUrgent = $diff <= 600; // do 10 minut
                if ($diff <= 0) {
                    $diffTxt = '<span style="color:#e74c3c;font-weight:700;">PROŠLÝ</span>';
                } elseif ($diff < 3600) {
                    $diffTxt = '<span style="color:#f0a030;">za ' . (int) ceil($diff / 60) . ' min</span>';
                } else {
                    $dMon  = (int) floor($diff / (30 * 86400));
                    $dDay  = (int) floor(($diff % (30 * 86400)) / 86400);
                    $dHour = (int) floor(($diff % 86400) / 3600);
                    $dMin  = (int) floor(($diff % 3600) / 60);
                    $parts = [];
                    if ($dMon  > 0) $parts[] = $dMon  . ' měs.';
                    if ($dDay  > 0) $parts[] = $dDay  . ' ' . ($dDay === 1 ? 'den' : ($dDay < 5 ? 'dny' : 'dní'));
                    if ($dHour > 0) $parts[] = $dHour . ' hod.';
                    if ($dMin  > 0 && $dMon === 0) $parts[] = $dMin . ' min'; // minuty jen do měsíce
                    $color = $diff < 86400 ? ' style="color:#f0a030;"' : '';
                    $diffTxt = '<span' . $color . '>za ' . implode(' ', $parts) . '</span>';
                }
            ?>
                <div class="cal-cb-item <?= $isUrgent ? 'cal-cb-item--urgent' : '' ?>">
                    <div class="cal-cb-time">
                        <div class="cal-cb-date"><?= crm_h(date('d.m.', $ts)) ?></div>
                        <div class="cal-cb-hour"><?= crm_h(date('H:i', $ts)) ?></div>
                    </div>
                    <div class="cal-cb-info">
                        <div class="cal-cb-name"><?= crm_h((string)$cb['firma']) ?></div>
                        <?php if (!empty($cb['telefon'])) { ?>
                            <a href="tel:<?= crm_h((string)$cb['telefon']) ?>" class="contact-phone"><?= crm_h((string)$cb['telefon']) ?></a>
                        <?php } ?>
                    </div>
                    <div class="cal-cb-diff"><?= $diffTxt ?></div>
                </div>
            <?php } ?>
            </div>
        <?php } ?>
    </div>
</section>

<script>
// Data callbacků pro klik na den
var CAL_DATA = <?= json_encode($callbacksByDate, JSON_UNESCAPED_UNICODE) ?>;

function crmCalDay(date) {
    var detail = document.getElementById('cal-day-detail');
    var cbs = CAL_DATA[date] || [];
    if (!cbs.length) { detail.classList.add('hidden'); return; }

    var html = '<h3 class="cal-detail-title">📅 ' + date.split('-').reverse().join('.') + '</h3><div class="cal-cb-list">';
    cbs.forEach(function(cb) {
        var time = cb.callback_at ? cb.callback_at.substring(11,16) : '';
        html += '<div class="cal-cb-item">' +
            '<div class="cal-cb-time"><div class="cal-cb-hour">' + time + '</div></div>' +
            '<div class="cal-cb-info"><div class="cal-cb-name">' + escHtml(cb.firma) + '</div>' +
            (cb.telefon ? '<a href="tel:'+escHtml(cb.telefon)+'" class="contact-phone">'+escHtml(cb.telefon)+'</a>' : '') +
            '</div></div>';
    });
    html += '</div>';
    detail.innerHTML = html;
    detail.classList.remove('hidden');
    detail.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function escHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>

<?php require __DIR__ . '/_notifications.php'; ?>
