<?php
// e:\Snecinatripu\app\helpers\activity_log.php
declare(strict_types=1);

/**
 * Activity Log — centrální evidence smysluplných akcí uživatelů.
 *
 * Filozofie:
 *   - Nesleduje login/logout/dobu v systému.
 *   - Eviduje JEN reálné výsledky a smysluplné akce.
 *   - Body jsou per-tenant per-role konfigurovatelné (activity_score_rules).
 *
 * API:
 *   crm_activity_log_record($pdo, $userId, 'call.success', 'contact', $cid, ['region'=>'praha']);
 *   crm_activity_today_stats($pdo, $tenantId);
 *   crm_activity_top_users($pdo, $tenantId, $period);
 *   crm_activity_role_breakdown($pdo, $tenantId, $period);
 *   crm_activity_trend_days($pdo, $tenantId, $days);
 *   crm_activity_user_score($pdo, $userId, $period);
 *   crm_activity_users_table($pdo, $tenantId);
 */

if (!function_exists('crm_activity_log_record')) {
    /**
     * Zapíše akci do activity_log se snapshotem bodů z aktivního pravidla.
     * Tichý fallback — pokud selže, error log + pokračování (nesmí shodit hlavní akci).
     *
     * @param array<string,mixed> $metadata
     */
    function crm_activity_log_record(
        PDO     $pdo,
        ?int    $userId,
        string  $actionType,
        ?string $entityType = null,
        ?int    $entityId = null,
        array   $metadata = []
    ): void {
        try {
            $tenantId = crm_tenant_id();
            if ($tenantId <= 0) return; // bez session žádný zápis

            // Resolv role + body z aktivního pravidla (cache per-request)
            [$role, $points] = _crm_activity_resolve($pdo, $tenantId, $userId, $actionType);

            $stmt = $pdo->prepare(
                'INSERT INTO activity_log
                   (tenant_id, user_id, user_role, action_type, entity_type, entity_id,
                    points_awarded, metadata, created_at)
                 VALUES (:tid, :uid, :role, :at, :et, :eid, :pts, :meta, NOW(3))'
            );
            $stmt->execute([
                'tid'  => $tenantId,
                'uid'  => $userId,
                'role' => $role,
                'at'   => $actionType,
                'et'   => $entityType,
                'eid'  => $entityId,
                'pts'  => (int) $points,
                'meta' => $metadata !== [] ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
            ]);
        } catch (\Throwable $e) {
            if (function_exists('crm_db_log_error') && $e instanceof \PDOException) {
                crm_db_log_error($e, 'crm_activity_log_record');
            } else {
                error_log('[ACTIVITY_LOG] ' . $e->getMessage());
            }
        }
    }
}

if (!function_exists('_crm_activity_resolve')) {
    /**
     * Najde primary roli usera + body z activity_score_rules.
     * Per-request cache (rules se nemění uvnitř requestu).
     *
     * @return array{0:?string, 1:int}  [role, points]
     */
    function _crm_activity_resolve(PDO $pdo, int $tenantId, ?int $userId, string $actionType): array
    {
        static $userRoleCache = [];
        static $rulesCache = []; // [tenantId][role][actionType] => points|null

        // 1) Role
        $role = null;
        if ($userId !== null && $userId > 0) {
            $key = $tenantId . ':' . $userId;
            if (!array_key_exists($key, $userRoleCache)) {
                $st = $pdo->prepare(
                    'SELECT COALESCE(ut.role, u.role) AS role
                     FROM users u
                     LEFT JOIN user_tenants ut
                         ON ut.user_id = u.id AND ut.tenant_id = :tid AND ut.active = 1
                     WHERE u.id = :id LIMIT 1'
                );
                $st->execute(['tid' => $tenantId, 'id' => $userId]);
                $userRoleCache[$key] = (string) ($st->fetchColumn() ?: '') ?: null;
            }
            $role = $userRoleCache[$key];
        }

        // 2) Body z pravidla
        if ($role === null) return [null, 0];
        if (!isset($rulesCache[$tenantId][$role])) {
            $rulesCache[$tenantId][$role] = [];
            $st = $pdo->prepare(
                'SELECT action_type, points
                 FROM activity_score_rules
                 WHERE tenant_id = :tid AND role = :role AND active = 1'
            );
            $st->execute(['tid' => $tenantId, 'role' => $role]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $rulesCache[$tenantId][$role][(string) $r['action_type']] = (int) $r['points'];
            }
        }
        $points = $rulesCache[$tenantId][$role][$actionType] ?? 0;
        return [$role, $points];
    }
}

// ═════════════════════════════════════════════════════════════════════
//  DASHBOARD HELPERY — agregované statistiky pro owner dashboard
// ═════════════════════════════════════════════════════════════════════

if (!function_exists('crm_activity_today_stats')) {
    /**
     * Stat cards pro dashboard:
     *   - akcí dnes, nové kontakty dnes, zpracované kontakty dnes,
     *     aktivní uživatelé dnes, smluv tento týden
     *   - vč. delta vs. včera (jen pro klíčové metriky)
     *
     * @return array<string,int|float>
     */
    function crm_activity_today_stats(PDO $pdo, int $tenantId): array
    {
        // Aktivit dnes
        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM activity_log
              WHERE tenant_id = :tid AND DATE(created_at) = CURDATE()'
        );
        $st->execute(['tid' => $tenantId]);
        $activitiesToday = (int) $st->fetchColumn();

        // Aktivit včera (pro delta)
        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM activity_log
              WHERE tenant_id = :tid AND DATE(created_at) = (CURDATE() - INTERVAL 1 DAY)'
        );
        $st->execute(['tid' => $tenantId]);
        $activitiesYesterday = (int) $st->fetchColumn();

        // Nové kontakty dnes (z contacts.created_at)
        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM contacts
              WHERE tenant_id = :tid AND DATE(created_at) = CURDATE()'
        );
        $st->execute(['tid' => $tenantId]);
        $newContactsToday = (int) $st->fetchColumn();

        // Zpracované kontakty dnes (= takové, na kterých dnes proběhla aspoň 1 akce
        // změny workflow — měříme jako COUNT DISTINCT entity_id z activity_log)
        $st = $pdo->prepare(
            "SELECT COUNT(DISTINCT entity_id) FROM activity_log
              WHERE tenant_id = :tid AND entity_type = 'contact'
                AND DATE(created_at) = CURDATE()"
        );
        $st->execute(['tid' => $tenantId]);
        $processedContactsToday = (int) $st->fetchColumn();

        // Aktivních uživatelů dnes (= měli alespoň 1 akci) / aktivních userů ve firmě
        $st = $pdo->prepare(
            'SELECT COUNT(DISTINCT user_id) FROM activity_log
              WHERE tenant_id = :tid AND DATE(created_at) = CURDATE() AND user_id IS NOT NULL'
        );
        $st->execute(['tid' => $tenantId]);
        $activeUsers = (int) $st->fetchColumn();

        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM user_tenants ut
              JOIN users u ON u.id = ut.user_id
              WHERE ut.tenant_id = :tid AND ut.active = 1 AND u.aktivni = 1'
        );
        $st->execute(['tid' => $tenantId]);
        $totalUsers = (int) $st->fetchColumn();

        // Smluv tento týden (action_type = sales.contract_signed)
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM activity_log
              WHERE tenant_id = :tid
                AND action_type = 'sales.contract_signed'
                AND created_at >= (CURDATE() - INTERVAL WEEKDAY(CURDATE()) DAY)"
        );
        $st->execute(['tid' => $tenantId]);
        $contractsThisWeek = (int) $st->fetchColumn();

        // Delta procentní (only meaningful when previous > 0)
        $delta = $activitiesYesterday > 0
               ? (int) round(($activitiesToday - $activitiesYesterday) / $activitiesYesterday * 100)
               : null;

        return [
            'activities_today'         => $activitiesToday,
            'activities_yesterday'     => $activitiesYesterday,
            'activities_delta_pct'     => $delta,
            'new_contacts_today'       => $newContactsToday,
            'processed_contacts_today' => $processedContactsToday,
            'active_users'             => $activeUsers,
            'total_users'              => $totalUsers,
            'contracts_this_week'      => $contractsThisWeek,
        ];
    }
}

if (!function_exists('crm_activity_top_users')) {
    /**
     * TOP N uživatelů podle bodů za období.
     *
     * $period: 'today' | 'week' (= last 7d) | 'month'
     * @return list<array<string,mixed>>
     */
    function crm_activity_top_users(PDO $pdo, int $tenantId, string $period = 'today', int $limit = 5): array
    {
        $cond = _crm_activity_period_condition($period);

        $st = $pdo->prepare(
            "SELECT u.id AS user_id, u.jmeno, al.user_role,
                    SUM(al.points_awarded) AS total_points,
                    COUNT(*) AS action_count
             FROM activity_log al
             JOIN users u ON u.id = al.user_id
             WHERE al.tenant_id = :tid {$cond}
             GROUP BY u.id, u.jmeno, al.user_role
             ORDER BY total_points DESC, action_count DESC
             LIMIT " . (int) $limit
        );
        $st->execute(['tid' => $tenantId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('crm_activity_role_breakdown')) {
    /**
     * Aktivity per role za období + počet unikátních uživatelů v té roli.
     *
     * @return list<array{role:string, count:int, users:int}>
     */
    function crm_activity_role_breakdown(PDO $pdo, int $tenantId, string $period = 'today'): array
    {
        $cond = _crm_activity_period_condition($period);
        $st = $pdo->prepare(
            "SELECT COALESCE(al.user_role, '—') AS role,
                    COUNT(*) AS count,
                    COUNT(DISTINCT al.user_id) AS users
             FROM activity_log al
             WHERE al.tenant_id = :tid {$cond}
               AND al.user_id IS NOT NULL
             GROUP BY al.user_role
             ORDER BY count DESC"
        );
        $st->execute(['tid' => $tenantId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('crm_activity_trend_days')) {
    /**
     * Aktivit per den za posledních N dní (inclusive). Pro bar chart.
     *
     * @return list<array{date:string, count:int, label:string}>
     */
    function crm_activity_trend_days(PDO $pdo, int $tenantId, int $days = 7): array
    {
        $days = max(1, min(60, $days));
        $st = $pdo->prepare(
            "SELECT DATE(created_at) AS day, COUNT(*) AS cnt
             FROM activity_log
             WHERE tenant_id = :tid
               AND created_at >= (CURDATE() - INTERVAL " . ($days - 1) . " DAY)
             GROUP BY DATE(created_at)"
        );
        $st->execute(['tid' => $tenantId]);
        $byDay = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $byDay[(string) $r['day']] = (int) $r['cnt'];
        }

        $shortNames = ['Po', 'Út', 'St', 'Čt', 'Pá', 'So', 'Ne']; // ISO: 1=Po … 7=Ne
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $ts = strtotime("-{$i} days");
            $d = date('Y-m-d', $ts);
            $out[] = [
                'date'  => $d,
                'count' => (int) ($byDay[$d] ?? 0),
                'label' => $shortNames[((int) date('N', $ts)) - 1] ?? '?',
            ];
        }
        return $out;
    }
}

if (!function_exists('crm_activity_users_table')) {
    /**
     * Tabulka VŠECH aktivních uživatelů firmy s jejich skóre.
     *
     * @return list<array<string,mixed>>
     */
    function crm_activity_users_table(PDO $pdo, int $tenantId): array
    {
        $st = $pdo->prepare(
            "SELECT u.id, u.jmeno, u.email,
                    COALESCE(ut.role, u.role) AS role,
                    (SELECT COALESCE(SUM(points_awarded), 0) FROM activity_log
                      WHERE tenant_id = :tid_a AND user_id = u.id
                        AND DATE(created_at) = CURDATE()) AS points_today,
                    (SELECT COALESCE(SUM(points_awarded), 0) FROM activity_log
                      WHERE tenant_id = :tid_b AND user_id = u.id
                        AND created_at >= (CURDATE() - INTERVAL 7 DAY)) AS points_7d,
                    (SELECT COUNT(*) FROM activity_log
                      WHERE tenant_id = :tid_c AND user_id = u.id
                        AND DATE(created_at) = CURDATE()) AS actions_today,
                    (SELECT MAX(created_at) FROM activity_log
                      WHERE tenant_id = :tid_d AND user_id = u.id) AS last_activity
             FROM user_tenants ut
             JOIN users u ON u.id = ut.user_id
             WHERE ut.tenant_id = :tid_main AND ut.active = 1 AND u.aktivni = 1
             ORDER BY points_7d DESC, u.jmeno ASC"
        );
        $st->execute([
            'tid_main' => $tenantId, 'tid_a' => $tenantId, 'tid_b' => $tenantId,
            'tid_c' => $tenantId, 'tid_d' => $tenantId,
        ]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('_crm_activity_period_condition')) {
    /**
     * Vrátí SQL fragment pro WHERE klauzuli podle období.
     */
    function _crm_activity_period_condition(string $period): string
    {
        switch ($period) {
            case 'week':
                return ' AND al.created_at >= (CURDATE() - INTERVAL 7 DAY) ';
            case 'month':
                return ' AND al.created_at >= (CURDATE() - INTERVAL 30 DAY) ';
            case 'today':
            default:
                return ' AND DATE(al.created_at) = CURDATE() ';
        }
    }
}

if (!function_exists('crm_activity_relative_time')) {
    /**
     * "před 5 min", "před 2 h", "včera", "3.5.2026" — pro UI.
     */
    function crm_activity_relative_time(?string $datetime): string
    {
        if ($datetime === null || $datetime === '') return '—';
        $ts = strtotime($datetime);
        if ($ts === false) return '—';
        $diff = time() - $ts;
        if ($diff < 60)       return 'právě teď';
        if ($diff < 3600)     return 'před ' . (int) ($diff / 60) . ' min';
        if ($diff < 86400)    return 'před ' . (int) ($diff / 3600) . ' h';
        if ($diff < 172800)   return 'včera';
        if ($diff < 604800)   return 'před ' . (int) ($diff / 86400) . ' dny';
        return date('d.m.Y', $ts);
    }
}
