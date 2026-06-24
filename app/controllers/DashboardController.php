<?php
// e:\Snecinatripu\app\controllers\DashboardController.php
declare(strict_types=1);

final class DashboardController
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getIndex(): void
    {
        $user = crm_require_user($this->pdo);

        // Role-based dashboard routing — každá role má vlastní view.
        // Majitel/superadmin používají owner-dashboard (krásnější), takže redirect.
        $role = (string) ($user['role'] ?? '');
        if (in_array($role, ['majitel', 'superadmin'], true)) {
            crm_redirect('/owner-dashboard');
        }

        // Pro výkonné role (cisticka/navolavacka/obchodak/backoffice) → vlastní lehký dashboard
        $roleDashboards = [
            'cisticka'    => '/views/cisticka/dashboard.php',
            'navolavacka' => '/views/caller/dashboard.php',
            'obchodak'    => '/views/oz/dashboard.php',
            'backoffice'  => '/views/bo/dashboard.php',
        ];
        if (isset($roleDashboards[$role])) {
            $this->renderRoleDashboard($user, $roleDashboards[$role]);
            return;
        }

        // Fallback — původní generický dashboard (s widgets pro majitele, ale ten už redirectoval)
        $allowedRegions = [];
        $activeRegion = null;
        if (($user['role'] ?? '') === 'obchodak') {
            $allowedRegions = crm_regions_allowed($this->pdo, (int) $user['id']);
            $activeRegion = crm_active_region_get($this->pdo, $user);
        }

        // ── Widget „Blíží se výročí" — pro majitele / superadmin ──
        // Zobrazí top 10 smluv s výročím v příštích 180 dnech, sortováno od nejbližšího
        $upcomingAnniversaries = [];
        $anniversaryStats = ['days_30' => 0, 'days_60' => 0, 'days_90' => 0, 'days_180' => 0];
        if (in_array(($user['role'] ?? ''), ['majitel', 'superadmin'], true)) {
            try {
                // Multi-tenant: w.tenant_id se váže na c.tenant_id (def-in-depth)
                $stmt = $this->pdo->prepare(
                    "SELECT c.id, c.firma, c.telefon, c.region,
                            c.vyrocni_smlouvy,
                            DATEDIFF(c.vyrocni_smlouvy, CURDATE()) AS days_until,
                            COALESCE(u_oz.jmeno, '—') AS oz_name,
                            w.cislo_smlouvy
                     FROM contacts c
                     LEFT JOIN oz_contact_workflow w
                            ON w.contact_id = c.id AND w.tenant_id = c.tenant_id
                     LEFT JOIN users u_oz ON u_oz.id = c.assigned_sales_id
                     WHERE c.vyrocni_smlouvy IS NOT NULL
                       AND c.vyrocni_smlouvy >= CURDATE()
                       AND c.vyrocni_smlouvy <= CURDATE() + INTERVAL 180 DAY
                       AND c.tenant_id = :tid
                     ORDER BY c.vyrocni_smlouvy ASC
                     LIMIT 10"
                );
                $stmt->execute(['tid' => crm_tenant_id()]);
                $upcomingAnniversaries = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                // Multi-tenant filter
                $statStmt = $this->pdo->prepare(
                    "SELECT
                        SUM(CASE WHEN vyrocni_smlouvy <= CURDATE() + INTERVAL 30 DAY  THEN 1 ELSE 0 END) AS days_30,
                        SUM(CASE WHEN vyrocni_smlouvy <= CURDATE() + INTERVAL 60 DAY  THEN 1 ELSE 0 END) AS days_60,
                        SUM(CASE WHEN vyrocni_smlouvy <= CURDATE() + INTERVAL 90 DAY  THEN 1 ELSE 0 END) AS days_90,
                        SUM(CASE WHEN vyrocni_smlouvy <= CURDATE() + INTERVAL 180 DAY THEN 1 ELSE 0 END) AS days_180
                     FROM contacts
                     WHERE vyrocni_smlouvy IS NOT NULL AND vyrocni_smlouvy >= CURDATE()
                       AND tenant_id = :tid"
                );
                $statStmt->execute(['tid' => crm_tenant_id()]);
                $row = $statStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $anniversaryStats = [
                    'days_30'  => (int) ($row['days_30']  ?? 0),
                    'days_60'  => (int) ($row['days_60']  ?? 0),
                    'days_90'  => (int) ($row['days_90']  ?? 0),
                    'days_180' => (int) ($row['days_180'] ?? 0),
                ];
            } catch (\PDOException) {
                // Ignoruj — sloupec/tabulka může chybět na čerstvé instanci
            }
        }

        // ── Widget „🎂 Narozeniny majitelů" — pro majitele / superadmin ──
        // Zobrazí kontakty s blížícími se narozeninami (do 30 dní), s tel číslem,
        // aby OZ / admin mohl popřát. Narozeniny jsou každoroční — počítá se
        // "next birthday" (nejbližší výskyt měsíc/den od dneška).
        $upcomingBirthdays = [];
        $birthdayStats     = ['today' => 0, 'days_7' => 0, 'days_14' => 0, 'days_30' => 0];
        if (in_array(($user['role'] ?? ''), ['majitel', 'superadmin'], true)) {
            try {
                // Načti všechny kontakty s vyplněnými narozeninami (typicky stovky řádků max)
                // Multi-tenant filter
                $bStmt = $this->pdo->prepare(
                    "SELECT c.id, c.firma, c.telefon, c.region,
                            c.narozeniny_majitele,
                            COALESCE(u_oz.jmeno, '—') AS oz_name
                     FROM contacts c
                     LEFT JOIN users u_oz ON u_oz.id = c.assigned_sales_id
                     WHERE c.narozeniny_majitele IS NOT NULL
                       AND c.narozeniny_majitele <> '0000-00-00'
                       AND c.tenant_id = :tid"
                );
                $bStmt->execute(['tid' => crm_tenant_id()]);
                {
                    $all = $bStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    $today = new DateTime('today');
                    foreach ($all as $r) {
                        $nar = (string) ($r['narozeniny_majitele'] ?? '');
                        if ($nar === '' || $nar === '0000-00-00') continue;
                        try {
                            $bd = new DateTime($nar);
                        } catch (\Throwable $_) { continue; }
                        // Next birthday: v letošním roce; pokud už byl, pak v příštím
                        $nextBd = (new DateTime($today->format('Y') . '-' . $bd->format('m-d')));
                        if ($nextBd < $today) {
                            $nextBd->modify('+1 year');
                        }
                        $daysUntil = (int) $today->diff($nextBd)->days;
                        if ($daysUntil > 30) continue;  // jen do 30 dní
                        // Věk po narozeninách
                        $age = (int) $nextBd->format('Y') - (int) $bd->format('Y');

                        $upcomingBirthdays[] = [
                            'id'         => (int) $r['id'],
                            'firma'      => (string) ($r['firma']   ?? ''),
                            'telefon'    => (string) ($r['telefon'] ?? ''),
                            'region'     => (string) ($r['region']  ?? ''),
                            'oz_name'    => (string) ($r['oz_name'] ?? '—'),
                            'narozeniny' => $nar,                 // YYYY-MM-DD
                            'next_bd'    => $nextBd->format('Y-m-d'),
                            'days_until' => $daysUntil,
                            'age'        => $age,
                        ];

                        // Stats
                        if ($daysUntil === 0) $birthdayStats['today']++;
                        if ($daysUntil <= 7)  $birthdayStats['days_7']++;
                        if ($daysUntil <= 14) $birthdayStats['days_14']++;
                        if ($daysUntil <= 30) $birthdayStats['days_30']++;
                    }
                    // Sort podle days_until ASC
                    usort($upcomingBirthdays, static fn($a, $b) => $a['days_until'] <=> $b['days_until']);
                    // Top 15 pro UI (statistiky obsahují všechny)
                    $upcomingBirthdays = array_slice($upcomingBirthdays, 0, 15);
                }
            } catch (\PDOException $e) {
                if (function_exists('crm_db_log_error')) crm_db_log_error($e, __METHOD__);
            }
        }

        $flash = crm_flash_take();
        $title = 'Dashboard';
        $csrf = crm_csrf_token();
        ob_start();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'dashboard' . DIRECTORY_SEPARATOR . 'index.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'base.php';
    }

    /**
     * Render role-specific dashboard. Lehký — jen načte vstupní data pro velké tlačítko
     * a 3 stat karty. Detail je vždy na "Pracovní ploše" dané role.
     *
     * @param array<string,mixed> $user
     */
    private function renderRoleDashboard(array $user, string $viewPath): void
    {
        $tenantId = function_exists('crm_tenant_id') ? crm_tenant_id() : 0;
        $userId   = (int) $user['id'];
        $role     = (string) ($user['role'] ?? '');
        $stats    = ['primary_count' => 0, 'sub_a' => 0, 'sub_b' => 0, 'sub_c' => 0];
        $primary  = ['label' => 'Otevřít pracovní plochu', 'href' => '/dashboard', 'count_label' => ''];

        try {
            if ($role === 'cisticka') {
                // Kolik NEW kontaktů je ve frontě (v jejích krajích) — nutno použít user_regions
                $st = $this->pdo->prepare(
                    "SELECT COUNT(*) FROM contacts c
                     WHERE c.stav = 'NEW' AND c.tenant_id = :tid
                       AND c.region IN (SELECT region FROM user_regions WHERE user_id = :uid)"
                );
                $st->execute(['tid' => $tenantId, 'uid' => $userId]);
                $stats['primary_count'] = (int) $st->fetchColumn();
                $primary = ['label' => '🧹 Začít čištění', 'href' => '/cisticka',
                            'count_label' => $stats['primary_count'] . ' kontaktů ve frontě'];

                // Dnes hotovo
                $st = $this->pdo->prepare(
                    "SELECT COUNT(*) FROM workflow_log
                     WHERE user_id = :uid AND tenant_id = :tid
                       AND new_status IN ('READY','VF_SKIP','CHYBNY_KONTAKT')
                       AND DATE(created_at) = CURDATE()"
                );
                $st->execute(['tid' => $tenantId, 'uid' => $userId]);
                $stats['sub_a'] = (int) $st->fetchColumn();

            } elseif ($role === 'navolavacka') {
                // Callable (READY assigned to me OR free OR my callbacks)
                $st = $this->pdo->prepare(
                    "SELECT COUNT(*) FROM contacts
                     WHERE tenant_id = :tid AND stav IN ('READY','CALLBACK','NEDOVOLANO')
                       AND (assigned_caller_id IS NULL OR assigned_caller_id = :uid)"
                );
                $st->execute(['tid' => $tenantId, 'uid' => $userId]);
                $stats['primary_count'] = (int) $st->fetchColumn();
                $primary = ['label' => '📞 Otevřít frontu hovorů', 'href' => '/caller',
                            'count_label' => $stats['primary_count'] . ' kontaktů k volání'];

                // Callbacky today
                $st = $this->pdo->prepare(
                    "SELECT COUNT(*) FROM contacts
                     WHERE tenant_id = :tid AND stav = 'CALLBACK'
                       AND assigned_caller_id = :uid AND DATE(callback_at) = CURDATE()"
                );
                $st->execute(['tid' => $tenantId, 'uid' => $userId]);
                $stats['sub_a'] = (int) $st->fetchColumn();

                // Premium k volání
                $st = $this->pdo->prepare(
                    "SELECT COUNT(*) FROM premium_lead_pool p
                     JOIN premium_orders po ON po.id = p.order_id
                     WHERE p.tenant_id = :tid AND p.cleaning_status = 'tradeable'
                       AND p.call_status = 'pending' AND po.status IN ('open','closed')
                       AND (po.preferred_caller_id IS NULL OR po.preferred_caller_id = :uid)"
                );
                $st->execute(['tid' => $tenantId, 'uid' => $userId]);
                $stats['sub_b'] = (int) $st->fetchColumn();

            } elseif ($role === 'obchodak') {
                // Nové leady k akceptaci
                $st = $this->pdo->prepare(
                    "SELECT COUNT(*) FROM contacts
                     WHERE tenant_id = :tid AND assigned_sales_id = :uid AND stav = 'CALLED_OK'"
                );
                $st->execute(['tid' => $tenantId, 'uid' => $userId]);
                $stats['primary_count'] = (int) $st->fetchColumn();
                $primary = ['label' => '💼 Otevřít moje leady', 'href' => '/oz/leads',
                            'count_label' => $stats['primary_count'] . ' aktivních leadů'];

                // Schůzky tento týden
                $st = $this->pdo->prepare(
                    "SELECT COUNT(*) FROM oz_contact_workflow
                     WHERE tenant_id = :tid AND oz_id = :uid
                       AND stav = 'SCHUZKA'
                       AND schuzka_at >= (CURDATE() - INTERVAL WEEKDAY(CURDATE()) DAY)
                       AND schuzka_at <= (CURDATE() + INTERVAL (6 - WEEKDAY(CURDATE())) DAY)"
                );
                $st->execute(['tid' => $tenantId, 'uid' => $userId]);
                $stats['sub_a'] = (int) $st->fetchColumn();

                // Čeká BO
                $st = $this->pdo->prepare(
                    "SELECT COUNT(*) FROM oz_contact_workflow
                     WHERE tenant_id = :tid AND oz_id = :uid AND stav = 'BO_PREDANO'"
                );
                $st->execute(['tid' => $tenantId, 'uid' => $userId]);
                $stats['sub_b'] = (int) $st->fetchColumn();

            } elseif ($role === 'backoffice') {
                // Smlouvy k aktivaci
                $st = $this->pdo->prepare(
                    "SELECT COUNT(*) FROM oz_contact_workflow
                     WHERE tenant_id = :tid AND stav = 'BO_PREDANO' AND COALESCE(podpis_potvrzen, 0) = 1"
                );
                $st->execute(['tid' => $tenantId]);
                $stats['primary_count'] = (int) $st->fetchColumn();
                $primary = ['label' => '🏢 Otevřít pracovní plochu', 'href' => '/bo',
                            'count_label' => $stats['primary_count'] . ' smluv k aktivaci'];
            }
        } catch (\Throwable $e) {
            if (function_exists('crm_db_log_error') && $e instanceof \PDOException) {
                crm_db_log_error($e, __METHOD__);
            }
        }

        $title = 'Dashboard';
        $flash = function_exists('crm_flash_take') ? crm_flash_take() : null;
        $csrf  = function_exists('crm_csrf_token') ? crm_csrf_token() : '';

        ob_start();
        require dirname(__DIR__) . str_replace('/', DIRECTORY_SEPARATOR, $viewPath);
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    public function postLogout(): void
    {
        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/dashboard');
        }
        // Předáváme PDO ať se zruší i trusted device cookie + DB záznam
        crm_auth_logout($this->pdo);
        crm_flash_set('Byli jste odhlášeni.');
        crm_redirect('/login');
    }
}
