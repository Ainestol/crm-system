<?php
// e:\Snecinatripu\app\controllers\AdminOzTargetsController.php
declare(strict_types=1);

/**
 * Správa měsíčních kvót obchodních zástupců per region.
 * Přístup: majitel, superadmin.
 */
final class AdminOzTargetsController
{
    public function __construct(private PDO $pdo)
    {
    }

    /** GET /admin/oz-targets */
    public function getIndex(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);
        $this->ensureTable();
        $this->ensureFlagsTable();

        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();

        $year  = max(2024, min(2030, (int) ($_GET['year']  ?? date('Y'))));
        $month = max(1,    min(12,   (int) ($_GET['month'] ?? date('n'))));

        // Všichni aktivní OZ
        $ozList = $this->pdo->query(
            "SELECT id, jmeno FROM users
             WHERE role = 'obchodak' AND aktivni = 1 ORDER BY jmeno ASC"
        );
        $ozList = $ozList ? $ozList->fetchAll(PDO::FETCH_ASSOC) : [];

        // Regiony každého OZ z user_regions
        $ozRegions = [];
        foreach ($ozList as $oz) {
            $rs = $this->pdo->prepare(
                'SELECT region FROM user_regions WHERE user_id = :id ORDER BY region'
            );
            $rs->execute(['id' => (int) $oz['id']]);
            $ozRegions[(int) $oz['id']] = $rs->fetchAll(PDO::FETCH_COLUMN) ?: [];
        }

        // Uložené kvóty pro daný měsíc
        $savedTargets = [];
        $tStmt = $this->pdo->prepare(
            'SELECT user_id, region, target_count FROM oz_targets
             WHERE year = :y AND month = :m'
        );
        $tStmt->execute(['y' => $year, 'm' => $month]);
        foreach ($tStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $savedTargets[(int) $row['user_id']][(string) $row['region']] = (int) $row['target_count'];
        }

        // Přijaté leady tento měsíc per OZ per region
        $received = [];
        $rStmt = $this->pdo->prepare(
            "SELECT c.assigned_sales_id AS uid, c.region, COUNT(*) AS cnt
             FROM contacts c
             WHERE c.stav = 'CALLED_OK'
               AND c.assigned_sales_id IS NOT NULL
               AND YEAR(c.datum_volani) = :y
               AND MONTH(c.datum_volani) = :m
             GROUP BY c.assigned_sales_id, c.region"
        );
        $rStmt->execute(['y' => $year, 'm' => $month]);
        foreach ($rStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $received[(int) $row['uid']][(string) $row['region']] = (int) $row['cnt'];
        }

        // Počet reklamací per OZ per region
        $flagged = [];
        $fStmt = $this->pdo->prepare(
            "SELECT c.assigned_sales_id AS uid, c.region, COUNT(*) AS cnt
             FROM contact_oz_flags f
             JOIN contacts c ON c.id = f.contact_id
             WHERE YEAR(c.datum_volani) = :y AND MONTH(c.datum_volani) = :m
             GROUP BY c.assigned_sales_id, c.region"
        );
        $fStmt->execute(['y' => $year, 'm' => $month]);
        foreach ($fStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $flagged[(int) $row['uid']][(string) $row['region']] = (int) $row['cnt'];
        }

        $title = 'Kvóty OZ';
        ob_start();
        require dirname(__DIR__) . '/views/admin/oz_targets.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    /** GET /admin/oz-targets/detail */
    public function getDetail(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);
        $this->ensureTable();
        $this->ensureFlagsTable();

        $ozId  = (int) ($_GET['oz_id'] ?? 0);
        $year  = max(2024, min(2030, (int) ($_GET['year']  ?? date('Y'))));
        $month = max(1,    min(12,   (int) ($_GET['month'] ?? date('n'))));

        [$oz, $targets, $contacts, $byCaller, $rewardPerWin] =
            $this->loadDetailData($ozId, $year, $month);

        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();

        $title = 'Detail — ' . (string) $oz['jmeno'];
        ob_start();
        require dirname(__DIR__) . '/views/admin/oz_targets_detail.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    /**
     * GET /oz/payout/print
     *
     * OZ-friendly varianta tiskové stránky pro PDF. Stejná šablona jako
     * getPrint, ale:
     *   • Přístupná i pro role 'obchodak' (nejen majitel/superadmin)
     *   • OZ vždy vidí JEN sebe (oz_id = $user['id']) — nemůže si vybrat
     *     cizího OZ ani podstrčit URL parametr
     *   • Admin/majitel může pro pohodlí předat ?oz_id=N (např. pro testování)
     *
     * Použití: OZ si na konci měsíce vyjede PDF s detaily payout pro
     * své navolávačky, místo aby čekal na admina.
     */
    public function getOzSelfPrint(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);
        $this->ensureTable();
        $this->ensureFlagsTable();

        // Hard-lock pro OZ: vidí jen sebe, žádný oz_id z URL nepustíme.
        // Admin/majitel volitelně přes ?oz_id (default = sebe).
        if ((string) ($user['role'] ?? '') === 'obchodak') {
            $ozId = (int) $user['id'];
        } else {
            $ozId = (int) ($_GET['oz_id'] ?? $user['id']);
        }

        $year     = max(2024, min(2030, (int) ($_GET['year']  ?? date('Y'))));
        $month    = max(1,    min(12,   (int) ($_GET['month'] ?? date('n'))));
        $callerId = (int) ($_GET['caller_id'] ?? 0);

        [$oz, $targets, $contacts, $byCaller, $rewardPerWin] =
            $this->loadDetailData($ozId, $year, $month);

        // Filtrovat na jednu navolávačku pokud je caller_id zadáno
        if ($callerId > 0 && isset($byCaller[$callerId])) {
            $byCaller = [$callerId => $byCaller[$callerId]];
            $contacts = array_filter(
                $contacts,
                fn($c) => (int) ($c['caller_id'] ?? 0) === $callerId
            );
            $contacts = array_values($contacts);
        }

        // Standalone stránka — neprojde přes base layout (čistá pro tisk/PDF)
        header('Content-Type: text/html; charset=UTF-8');
        require dirname(__DIR__) . '/views/admin/oz_targets_print.php';
        exit;
    }

    /** GET /admin/oz-targets/print – čistá tisková stránka pro PDF */
    public function getPrint(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);
        $this->ensureTable();
        $this->ensureFlagsTable();

        $ozId     = (int) ($_GET['oz_id']     ?? 0);
        $year     = max(2024, min(2030, (int) ($_GET['year']  ?? date('Y'))));
        $month    = max(1,    min(12,   (int) ($_GET['month'] ?? date('n'))));
        $callerId = (int) ($_GET['caller_id'] ?? 0); // 0 = všichni

        [$oz, $targets, $contacts, $byCaller, $rewardPerWin] =
            $this->loadDetailData($ozId, $year, $month);

        // Filtrovat na jednu navolávačku pokud je caller_id zadáno
        if ($callerId > 0 && isset($byCaller[$callerId])) {
            $byCaller = [$callerId => $byCaller[$callerId]];
            $contacts = array_filter(
                $contacts,
                fn($c) => (int) ($c['caller_id'] ?? 0) === $callerId
            );
            $contacts = array_values($contacts);
        }

        // Standalone stránka – neprojde přes base layout
        header('Content-Type: text/html; charset=UTF-8');
        require dirname(__DIR__) . '/views/admin/oz_targets_print.php';
        exit;
    }

    /** POST /admin/oz-targets/save */
    public function postSave(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/oz-targets');
        }

        $this->ensureTable();

        $year  = max(2024, min(2030, (int) ($_POST['year']  ?? date('Y'))));
        $month = max(1,    min(12,   (int) ($_POST['month'] ?? date('n'))));

        $targetsPost = $_POST['targets'] ?? [];
        if (!is_array($targetsPost)) {
            crm_redirect('/admin/oz-targets?year=' . $year . '&month=' . $month);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO oz_targets (user_id, region, target_count, year, month)
             VALUES (:uid, :reg, :cnt, :y, :m)
             ON DUPLICATE KEY UPDATE target_count = :cnt2'
        );

        foreach ($targetsPost as $userId => $regions) {
            if (!is_array($regions)) {
                continue;
            }
            foreach ($regions as $region => $count) {
                $count = max(0, (int) $count);
                $stmt->execute([
                    'uid'  => (int) $userId,
                    'reg'  => (string) $region,
                    'cnt'  => $count,
                    'y'    => $year,
                    'm'    => $month,
                    'cnt2' => $count,
                ]);
            }
        }

        crm_flash_set('Kvóty uloženy.');
        crm_redirect('/admin/oz-targets?year=' . $year . '&month=' . $month);
    }

    /**
     * Načte data pro detail/print stránku.
     * @return array{array<string,mixed>, array<string,int>, list<array<string,mixed>>, array<int,array<string,mixed>>, float}
     */
    /**
     * Načte data pro detail/print stránku jednoho OZ — kvóty per region,
     * kontakty CALLED_OK přidělené OZ za daný měsíc, sgrupované per
     * navolávačka, plus aktuální odměna za výhru.
     *
     * Public, aby ji mohly volat i jiné role (OZ self-print v getOzSelfPrint).
     * Žádný role check zde — to je odpovědnost volajícího.
     *
     * @return array{0:array<string,mixed>,1:array<string,int>,2:list<array<string,mixed>>,3:array<int,array<string,mixed>>,4:float}
     */
    public function loadDetailData(int $ozId, int $year, int $month): array
    {
        // Info o OZ
        $ozStmt = $this->pdo->prepare(
            "SELECT id, jmeno FROM users WHERE id = :id AND role = 'obchodak'"
        );
        $ozStmt->execute(['id' => $ozId]);
        $oz = $ozStmt->fetch(PDO::FETCH_ASSOC);
        if (!$oz) {
            http_response_code(404);
            echo 'Obchodní zástupce nenalezen.';
            exit;
        }

        // Kvóty tohoto OZ
        $tgtStmt = $this->pdo->prepare(
            'SELECT region, target_count FROM oz_targets
             WHERE user_id = :uid AND year = :y AND month = :m'
        );
        $tgtStmt->execute(['uid' => $ozId, 'y' => $year, 'm' => $month]);
        $targets = [];
        foreach ($tgtStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $targets[(string) $row['region']] = (int) $row['target_count'];
        }

        // Kontakty CALLED_OK přidělené tomuto OZ tento měsíc + caller info + flag
        $cStmt = $this->pdo->prepare(
            "SELECT c.id, c.firma, c.telefon, c.region, c.datum_volani, c.poznamka,
                    u.id AS caller_id, u.jmeno AS caller_name,
                    CASE WHEN f.id IS NOT NULL THEN 1 ELSE 0 END AS flagged,
                    COALESCE(f.reason, '') AS flag_reason,
                    f.flagged_at
             FROM contacts c
             LEFT JOIN users u ON u.id = c.assigned_caller_id
             LEFT JOIN contact_oz_flags f ON f.contact_id = c.id AND f.oz_id = :oz_id
             WHERE c.stav = 'CALLED_OK'
               AND c.assigned_sales_id = :oz_id2
               AND YEAR(c.datum_volani) = :y
               AND MONTH(c.datum_volani) = :m
             ORDER BY u.jmeno ASC, c.region ASC, c.datum_volani ASC"
        );
        $cStmt->execute(['oz_id' => $ozId, 'oz_id2' => $ozId, 'y' => $year, 'm' => $month]);
        $contacts = $cStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Seskupit per navolávačka
        $byCaller = [];
        foreach ($contacts as $c) {
            $cid  = (int) ($c['caller_id'] ?? 0);
            $name = $cid > 0 ? (string) $c['caller_name'] : '— neznámý —';
            if (!isset($byCaller[$cid])) {
                $byCaller[$cid] = [
                    'name'     => $name,
                    'total'    => 0,
                    'flagged'  => 0,
                    'byRegion' => [],
                ];
            }
            $byCaller[$cid]['total']++;
            if ((int) $c['flagged']) {
                $byCaller[$cid]['flagged']++;
            }
            $byCaller[$cid]['byRegion'][(string) $c['region']][] = $c;
        }

        // Odměna za výhru (základní sazba)
        $rewardRow = $this->pdo->query(
            'SELECT amount_czk FROM caller_rewards_config
             WHERE valid_from <= CURDATE() AND (valid_to IS NULL OR valid_to >= CURDATE())
             ORDER BY valid_from DESC LIMIT 1'
        );
        $rewardPerWin = $rewardRow ? (float) ($rewardRow->fetchColumn() ?: 0) : 0.0;

        return [$oz, $targets, $contacts, $byCaller, $rewardPerWin];
    }

    private function ensureTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS `oz_targets` (
              `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `user_id`      INT UNSIGNED NOT NULL,
              `region`       VARCHAR(64) NOT NULL DEFAULT '',
              `target_count` INT UNSIGNED NOT NULL DEFAULT 0,
              `year`         SMALLINT UNSIGNED NOT NULL,
              `month`        TINYINT UNSIGNED NOT NULL,
              `created_at`   DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_oz_targets` (`user_id`, `region`, `year`, `month`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function ensureFlagsTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS `contact_oz_flags` (
              `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `contact_id` BIGINT UNSIGNED NOT NULL,
              `oz_id`      INT UNSIGNED NOT NULL,
              `reason`     VARCHAR(500) NOT NULL DEFAULT '',
              `flagged_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_flag` (`contact_id`, `oz_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}
