<?php
/** @var array<string,int> $stats */
/** @var list<array<string,mixed>> $topUsersToday */
/** @var list<array{role:string,count:int,users:int}> $roleBreakdown */
/** @var list<array{date:string,count:int,label:string}> $trend7d */
/** @var int $maxTrend */
/** @var list<array<string,mixed>> $usersTable */
/** @var ?array $tenant */

// Total aktivit za 7d (pro role breakdown procento)
$totalRole = array_sum(array_column($roleBreakdown, 'count')) ?: 1;
?>
<style>
.od-wrap { padding: 1rem 1.25rem; max-width: 1400px; }
.od-wrap h1 { margin: 0 0 .35rem; font-size: 1.5rem; }
.od-wrap .sub { color: #6b7280; font-size: .85rem; margin-bottom: 1rem; }
.od-section-title { font-size: 1.1rem; font-weight: 700; margin: 1.4rem 0 .6rem; color: #111827; }
.od-flash { background:#d1fae5; color:#065f46; padding:.7rem 1rem; border-radius:6px; margin-bottom:1rem; border:1px solid #6ee7b7; }

/* Stat cards (DNES) */
.od-stats {
    display: grid; gap: 12px; grid-template-columns: repeat(2, 1fr);
}
@media (min-width: 900px) { .od-stats { grid-template-columns: repeat(5, 1fr); } }
.od-stat {
    background:#fff; border:1px solid #e5e7eb; border-radius:10px;
    padding:1rem; box-shadow:0 1px 2px rgba(0,0,0,.04);
}
.od-stat .label { color:#6b7280; font-size:.78rem; text-transform:uppercase; letter-spacing:.05em; }
.od-stat .value { font-size:2rem; font-weight:700; margin:.3rem 0 .2rem; color:#111827; line-height:1; }
.od-stat .delta { font-size:.8rem; }
.od-stat .delta.up   { color:#059669; }
.od-stat .delta.down { color:#dc2626; }
.od-stat .delta.flat { color:#6b7280; }

/* Cards row (TOP + role breakdown) */
.od-row { display:grid; gap:12px; grid-template-columns: 1fr; margin-top:.6rem; }
@media (min-width: 900px) { .od-row { grid-template-columns: 1fr 1fr; } }
.od-card {
    background:#fff; border:1px solid #e5e7eb; border-radius:10px;
    padding:1rem 1.25rem; box-shadow:0 1px 2px rgba(0,0,0,.04);
}
.od-card h3 { margin:0 0 .7rem; font-size:1rem; }

/* TOP users list */
.top-users { padding:0; margin:0; list-style:none; }
.top-users li { display:flex; align-items:center; gap:.6rem; padding:.45rem 0; border-bottom:1px dashed #f0f0f0; font-size:.92rem; }
.top-users li:last-child { border-bottom:0; }
.top-users .rank { color:#9ca3af; font-weight:700; min-width:1.6rem; font-variant-numeric: tabular-nums; }
.top-users .name { flex:1; font-weight:600; color:#111827; }
.top-users .role-pill { font-size:.7rem; padding:.1rem .45rem; border-radius:999px; background:#f3f4f6; color:#374151; }
.top-users .points { font-weight:700; color:#2563eb; font-variant-numeric: tabular-nums; }
.top-users .actions { color:#6b7280; font-size:.78rem; }

/* Role breakdown bars */
.role-bars { display:flex; flex-direction:column; gap:.55rem; }
.role-bar .info { display:flex; justify-content:space-between; font-size:.85rem; margin-bottom:.2rem; }
.role-bar .info b { color:#111827; }
.role-bar .bar  { background:#f3f4f6; border-radius:6px; height:8px; overflow:hidden; }
.role-bar .bar > div {
    height:100%; background:linear-gradient(90deg, #3b82f6, #60a5fa);
    transition: width .25s ease-out;
}

/* 7d trend chart */
.trend7d {
    display:grid; grid-template-columns: repeat(7, 1fr); gap:.5rem;
    align-items:end; height:140px; padding: .5rem 0;
}
.trend7d .day {
    display:flex; flex-direction:column; align-items:center; gap:.25rem;
    color:#6b7280; font-size:.75rem;
}
.trend7d .bar {
    width:100%; min-height:4px; background:linear-gradient(180deg, #60a5fa, #2563eb);
    border-radius:4px 4px 0 0;
}
.trend7d .bar.today { background:linear-gradient(180deg, #f59e0b, #d97706); }
.trend7d .day .count { font-weight:700; color:#111827; font-size:.85rem; }

/* Users table */
.users-table { width:100%; border-collapse:collapse; font-size:.9rem; }
.users-table th, .users-table td { padding:.5rem .65rem; text-align:left; border-bottom:1px solid #f0f0f0; }
.users-table th { background:#f9fafb; color:#374151; font-weight:600; font-size:.78rem; text-transform:uppercase; letter-spacing:.05em; }
.users-table tbody tr:hover { background:#fafafa; }
.users-table .num { text-align:right; font-variant-numeric: tabular-nums; font-weight:600; }
.users-table .num-muted { text-align:right; color:#6b7280; font-variant-numeric: tabular-nums; }
.users-table .role-pill { font-size:.72rem; padding:.1rem .45rem; border-radius:999px; background:#f3f4f6; color:#374151;}
.users-table .inactive { background:#fee2e2; color:#991b1b; padding:.1rem .4rem; border-radius:999px; font-size:.72rem; font-weight:600; }
</style>

<div class="od-wrap">
    <?php require dirname(__DIR__) . '/_partials/dashboard_header.php'; ?>

    <h1 style="margin:0 0 .35rem; font-size:1.5rem;">📊 Výkon firmy</h1>
    <div class="sub">
        <?php if (!empty($tenant['name'])): ?>
            <?= htmlspecialchars((string) $tenant['name'], ENT_QUOTES, 'UTF-8') ?>
        <?php endif; ?>
        &middot; <a href="/admin/activity-scoring" style="color:#2563eb;">⚙️ Nastavení bodování</a>
    </div>

    <?php if ($flash): ?>
        <div class="od-flash"><?= htmlspecialchars((string) $flash, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <!-- ════════════════════════════════════════════════════════════════
         SEKCE 1: DNES — 5 stat cards
    ════════════════════════════════════════════════════════════════ -->
    <div class="od-section-title">⚡ Dnes</div>
    <div class="od-stats">
        <div class="od-stat">
            <div class="label">📞 Aktivit</div>
            <div class="value"><?= number_format((int) $stats['activities_today'], 0, ',', ' ') ?></div>
            <?php
            $d = $stats['activities_delta_pct'];
            if ($d === null): ?>
                <div class="delta flat">včera <?= (int) $stats['activities_yesterday'] ?></div>
            <?php elseif ($d > 0): ?>
                <div class="delta up">↑ +<?= (int) $d ?> % vs. včera</div>
            <?php elseif ($d < 0): ?>
                <div class="delta down">↓ <?= (int) $d ?> % vs. včera</div>
            <?php else: ?>
                <div class="delta flat">≈ jako včera</div>
            <?php endif; ?>
        </div>
        <div class="od-stat">
            <div class="label">📥 Nové kontakty</div>
            <div class="value"><?= number_format((int) $stats['new_contacts_today'], 0, ',', ' ') ?></div>
            <div class="delta flat">přibyly dnes</div>
        </div>
        <div class="od-stat">
            <div class="label">✅ Zpracované</div>
            <div class="value"><?= number_format((int) $stats['processed_contacts_today'], 0, ',', ' ') ?></div>
            <div class="delta flat">unique kontaktů</div>
        </div>
        <div class="od-stat">
            <div class="label">👥 Aktivních uživatelů</div>
            <div class="value"><?= (int) $stats['active_users'] ?> / <?= (int) $stats['total_users'] ?></div>
            <div class="delta flat">dnes alespoň 1 akce</div>
        </div>
        <div class="od-stat">
            <div class="label">💰 Smluv tento týden</div>
            <div class="value"><?= (int) $stats['contracts_this_week'] ?></div>
            <div class="delta flat">podpis_potvrzen</div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════════
         TOP USERS + ROLE BREAKDOWN
    ════════════════════════════════════════════════════════════════ -->
    <div class="od-row">
        <div class="od-card">
            <h3>🏆 TOP 5 dnes</h3>
            <?php if ($topUsersToday === []): ?>
                <p style="color:#9ca3af; font-size:.85rem;">Dnes ještě nikdo nedělal nic.</p>
            <?php else: ?>
                <ul class="top-users">
                    <?php foreach ($topUsersToday as $i => $u): ?>
                        <li>
                            <span class="rank">#<?= $i + 1 ?></span>
                            <span class="name"><?= htmlspecialchars((string) $u['jmeno'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="role-pill"><?= htmlspecialchars((string) ($u['user_role'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="points"><?= (int) $u['total_points'] ?> b</span>
                            <span class="actions"><?= (int) $u['action_count'] ?> akcí</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="od-card">
            <h3>📊 Podle rolí dnes</h3>
            <?php if ($roleBreakdown === []): ?>
                <p style="color:#9ca3af; font-size:.85rem;">Dnes žádná aktivita.</p>
            <?php else: ?>
                <div class="role-bars">
                    <?php foreach ($roleBreakdown as $r):
                        $pct = (int) round((int) $r['count'] * 100 / $totalRole);
                    ?>
                        <div class="role-bar">
                            <div class="info">
                                <span>
                                    <?= htmlspecialchars((string) ($r['role'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                                    <span style="color:#9ca3af; font-size:.78rem;">(<?= (int) $r['users'] ?> lidí)</span>
                                </span>
                                <b><?= (int) $r['count'] ?> akcí</b>
                            </div>
                            <div class="bar"><div style="width:<?= $pct ?>%"></div></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════════
         SEKCE 2: TREND 7 DNÍ
    ════════════════════════════════════════════════════════════════ -->
    <div class="od-section-title">📈 Posledních 7 dní</div>
    <div class="od-card">
        <div class="trend7d">
            <?php foreach ($trend7d as $i => $d):
                $h = $maxTrend > 0 ? (int) round((int) $d['count'] * 100 / $maxTrend) : 0;
                $h = max(2, $h); // alespoň 2 % aby byl proužek vidět i pro 0
                $isToday = ((string) $d['date'] === date('Y-m-d'));
            ?>
                <div class="day">
                    <span class="count"><?= (int) $d['count'] ?></span>
                    <div class="bar <?= $isToday ? 'today' : '' ?>" style="height: <?= $h ?>%"></div>
                    <span><?= htmlspecialchars((string) $d['label'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════════
         SEKCE 3: TABULKA UŽIVATELŮ
    ════════════════════════════════════════════════════════════════ -->
    <div class="od-section-title">👥 Uživatelé</div>
    <div class="od-card" style="padding:.5rem 0;">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Uživatel</th>
                    <th>Role</th>
                    <th style="text-align:right;">Body 7d</th>
                    <th style="text-align:right;">Body dnes</th>
                    <th style="text-align:right;">Akce dnes</th>
                    <th>Poslední aktivita</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($usersTable === []): ?>
                    <tr><td colspan="6" style="color:#9ca3af; padding:1rem; text-align:center;">Žádní aktivní uživatelé ve firmě.</td></tr>
                <?php endif; ?>
                <?php foreach ($usersTable as $u):
                    $lastTs = !empty($u['last_activity']) ? strtotime((string) $u['last_activity']) : 0;
                    $daysOff = $lastTs > 0 ? (int) ((time() - $lastTs) / 86400) : 999;
                    $isInactive = $lastTs === 0 || $daysOff >= 7;
                ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars((string) $u['jmeno'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <div style="color:#9ca3af; font-size:.75rem;"><?= htmlspecialchars((string) $u['email'], ENT_QUOTES, 'UTF-8') ?></div>
                        </td>
                        <td><span class="role-pill"><?= htmlspecialchars((string) $u['role'], ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td class="num"><?= (int) $u['points_7d'] ?></td>
                        <td class="num"><?= (int) $u['points_today'] ?></td>
                        <td class="num-muted"><?= (int) $u['actions_today'] ?></td>
                        <td>
                            <?php if ($isInactive && $lastTs === 0): ?>
                                <span class="inactive">⚠ Nikdy</span>
                            <?php elseif ($isInactive): ?>
                                <span class="inactive">⚠ <?= $daysOff ?> dní</span>
                            <?php else: ?>
                                <?= htmlspecialchars(crm_activity_relative_time((string) $u['last_activity']), ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
