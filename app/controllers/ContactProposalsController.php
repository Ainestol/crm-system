<?php
// e:\Snecinatripu\app\controllers\ContactProposalsController.php
declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'audit.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'region.php';

/**
 * Návrhy nových kontaktů (manual hot leads).
 *
 * Workflow:
 *   1) Kdokoliv s rolí klikne v sidebaru "➕ Nový kontakt" → vyplní
 *      formulář (firma, IČO, tel, email, adresa, region, operátor,
 *      poznámka, doporučený OZ) → návrh se uloží do `contact_proposals`
 *      se status='pending'.
 *   2) Majitel/superadmin klikne v sidebaru "📋 Návrhy ke schválení (N)"
 *      → vidí seznam pending návrhů → buď schválí (zvolí OZ-a) nebo
 *      zamítne (s důvodem).
 *   3) Při schválení atomicky: INSERT contacts (stav='CALLED_OK',
 *      assigned_sales_id=zvolený OZ) + UPDATE contact_proposals
 *      (status='approved', converted_contact_id, reviewed_by/at).
 *      Kontakt se objeví OZ-ovi v Příchozí leady jako každý jiný.
 *
 * Po schválení je `contacts.id` jediný zdroj pravdy. `contact_proposals`
 * je jen audit historie / čekárna.
 */
final class ContactProposalsController
{
    public function __construct(private PDO $pdo)
    {
    }

    // ────────────────────────────────────────────────────────────────
    //  Helpery
    // ────────────────────────────────────────────────────────────────

    /**
     * Auto-create tabulky při prvním requestu (kdyby DBA migraci ještě
     * nepustil). Idempotentní (CREATE IF NOT EXISTS).
     */
    private function ensureTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS `contact_proposals` (
              `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `proposed_by_user_id`   BIGINT UNSIGNED NOT NULL,
              `firma`                 VARCHAR(500) NOT NULL DEFAULT '',
              `email`                 VARCHAR(255) NULL DEFAULT NULL,
              `telefon`               VARCHAR(50)  NULL DEFAULT NULL,
              `ico`                   VARCHAR(20)  NULL DEFAULT NULL,
              `adresa`                VARCHAR(500) NULL DEFAULT NULL,
              `region`                VARCHAR(64)  NOT NULL,
              `operator`              VARCHAR(100) NULL DEFAULT NULL,
              `poznamka`              TEXT         NULL,
              `suggested_oz_id`       BIGINT UNSIGNED NULL DEFAULT NULL,
              `status`                ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
              `reviewed_by_user_id`   BIGINT UNSIGNED NULL DEFAULT NULL,
              `reviewed_at`           DATETIME(3)     NULL DEFAULT NULL,
              `review_note`           TEXT            NULL,
              `converted_contact_id`  BIGINT UNSIGNED NULL DEFAULT NULL,
              `created_at`            DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
              `updated_at`            DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
              PRIMARY KEY (`id`),
              KEY `idx_cp_status`        (`status`),
              KEY `idx_cp_proposed_by`   (`proposed_by_user_id`),
              KEY `idx_cp_suggested_oz`  (`suggested_oz_id`),
              KEY `idx_cp_converted`     (`converted_contact_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** Seznam aktivních OZ pro dropdown. Vrací i obchodáky v roles_extra (multi-role). */
    private function activeSalesUsers(): array
    {
        $rows = $this->pdo->query(
            "SELECT id, jmeno FROM users
             WHERE aktivni = 1
               AND (role = 'obchodak'
                    OR JSON_SEARCH(COALESCE(roles_extra, '[]'), 'one', 'obchodak') IS NOT NULL)
             ORDER BY jmeno ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /** True pokud má user roli 'obchodak' (primárně i v roles_extra). */
    private static function userIsOz(array $user): bool
    {
        if ((string) ($user['role'] ?? '') === 'obchodak') return true;
        $extra = $user['roles_extra'] ?? null;
        if (is_string($extra)) {
            $decoded = json_decode($extra, true);
            $extra   = is_array($decoded) ? $decoded : [];
        }
        return is_array($extra) && in_array('obchodak', $extra, true);
    }

    /** Whitelist operátorů — synced s tím, co používá zbytek aplikace. */
    private const OPERATOR_OPTIONS = ['O2', 'Vodafone', 'T-Mobile', 'jiný'];

    /**
     * Pomocný COUNT pending návrhů (pro sidebar badge).
     * Statická aby ji mohl volat base.php přes obyčejné PDO.
     */
    public static function pendingCount(PDO $pdo): int
    {
        try {
            $cnt = $pdo->query(
                "SELECT COUNT(*) FROM contact_proposals WHERE status = 'pending'"
            )->fetchColumn();
            return (int) $cnt;
        } catch (\PDOException $e) {
            // Tabulka ještě neexistuje (migrace nepuštěná) → 0
            return 0;
        }
    }

    // ════════════════════════════════════════════════════════════════
    //  GET /contacts/new — formulář pro nový návrh
    // ════════════════════════════════════════════════════════════════
    public function getNew(): void
    {
        $user = crm_require_user($this->pdo);
        $this->ensureTable();

        $title       = 'Nový kontakt';
        $csrf        = crm_csrf_token();
        $flash       = crm_flash_take();
        $regions     = crm_region_choices();   // list<string> kódů krajů
        $salesUsers  = $this->activeSalesUsers();
        $operators   = self::OPERATOR_OPTIONS;

        // Re-fill v případě validační chyby (server-side bounce)
        $form = $_SESSION['cp_form_data'] ?? [];
        unset($_SESSION['cp_form_data']);

        ob_start();
        require dirname(__DIR__) . '/views/contact-proposals/new.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    // ════════════════════════════════════════════════════════════════
    //  POST /contacts/new — uložení návrhu
    // ════════════════════════════════════════════════════════════════
    public function postNew(): void
    {
        $user = crm_require_user($this->pdo);
        $this->ensureTable();

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/contacts/new');
        }

        // Načíst + trim data
        $firma     = trim((string) ($_POST['firma']    ?? ''));
        $email     = trim((string) ($_POST['email']    ?? ''));
        $telefon   = trim((string) ($_POST['telefon']  ?? ''));
        $icoRaw    = trim((string) ($_POST['ico']      ?? ''));
        $adresa    = trim((string) ($_POST['adresa']   ?? ''));
        $region    = trim((string) ($_POST['region']   ?? ''));
        $operator  = trim((string) ($_POST['operator'] ?? ''));
        $poznamka  = trim((string) ($_POST['poznamka'] ?? ''));
        $suggested = (int) ($_POST['suggested_oz_id'] ?? 0);

        // Re-fill při bounce (uložíme do session a předáme formuláři)
        $bounce = function (string $msg) use (
            $firma, $email, $telefon, $icoRaw, $adresa,
            $region, $operator, $poznamka, $suggested
        ): void {
            $_SESSION['cp_form_data'] = [
                'firma'    => $firma, 'email'    => $email, 'telefon'  => $telefon,
                'ico'      => $icoRaw, 'adresa'  => $adresa, 'region'  => $region,
                'operator' => $operator, 'poznamka' => $poznamka,
                'suggested_oz_id' => $suggested,
            ];
            crm_flash_set($msg);
            crm_redirect('/contacts/new');
        };

        // ── Validace ──
        if (mb_strlen($firma) < 2)             { $bounce('⚠ Název firmy je povinný (min. 2 znaky).'); }
        if (mb_strlen($firma) > 500)           { $firma = mb_substr($firma, 0, 500); }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $bounce('⚠ E-mail je povinný a musí mít platný formát.');
        }
        if (mb_strlen($email) > 255)           { $email = mb_substr($email, 0, 255); }

        $phoneDigits = preg_replace('/\D/', '', $telefon) ?? '';
        if (mb_strlen($phoneDigits) < 9)       { $bounce('⚠ Telefon je povinný (min. 9 číslic).'); }
        if (mb_strlen($telefon) > 50)          { $telefon = mb_substr($telefon, 0, 50); }

        $ico = crm_normalize_ico($icoRaw);
        if (mb_strlen($ico) !== 8 || !ctype_digit($ico)) {
            $bounce('⚠ IČO je povinné a musí mít přesně 8 číslic.');
        }

        if (mb_strlen($adresa) < 5)            { $bounce('⚠ Adresa je povinná (min. 5 znaků).'); }
        if (mb_strlen($adresa) > 500)          { $adresa = mb_substr($adresa, 0, 500); }

        // crm_region_choices() vrací list of strings (kódy krajů), ne assoc array
        if (!in_array($region, crm_region_choices(), true)) {
            $bounce('⚠ Vyberte platný kraj.');
        }

        if (!in_array($operator, self::OPERATOR_OPTIONS, true)) {
            $bounce('⚠ Vyberte operátora.');
        }

        if (mb_strlen($poznamka) < 3)          { $bounce('⚠ Poznámka je povinná — popište proč je to hot lead.'); }
        if (mb_strlen($poznamka) > 1000)       { $poznamka = mb_substr($poznamka, 0, 1000); }

        // Zkontrolovat platnost zvoleného OZ (pokud byl zvolen)
        if ($suggested > 0) {
            $check = $this->pdo->prepare(
                "SELECT 1 FROM users WHERE id = :id AND role = 'obchodak' AND aktivni = 1 LIMIT 1"
            );
            $check->execute(['id' => $suggested]);
            if (!$check->fetchColumn()) { $suggested = 0; }
        }

        // ── Default OZ: pokud user JE OZ → preselect sebe (ale může změnit) ──
        // Pokud user není OZ → výběr je povinný.
        $userIsOz = self::userIsOz($user);
        if ($suggested <= 0 && $userIsOz) {
            $suggested = (int) $user['id'];
        }
        if ($suggested <= 0) {
            $bounce('⚠ Vyberte OZ — komu má kontakt patřit.');
        }

        // ── Duplicita: pokud user nepotvrdil přidání duplicity → bounce ──
        // (Default UX: form ukáže warning po blur na IČO. User pak buď
        //  klikne na existující kontakt, nebo zaškrtne "Přidat přesto".)
        $allowDup = !empty($_POST['allow_duplicate']);
        if (!$allowDup) {
            $dupStmt = $this->pdo->prepare(
                "SELECT id, firma FROM contacts WHERE ico = :ico LIMIT 1"
            );
            $dupStmt->execute(['ico' => $ico]);
            $dup = $dupStmt->fetch(PDO::FETCH_ASSOC);
            if ($dup) {
                // Bounce s konkrétní hláškou — server-side ochrana, klient ji uvidí
                // a může pak buď zaškrtnout "Přidat přesto" nebo otevřít existující.
                $bounce(sprintf(
                    '⚠ Kontakt s IČO %s už existuje (firma "%s", #%d). '
                    . 'Zaškrtni „Přidat přesto i jako duplicitu", pokud opravdu chceš.',
                    $ico, $dup['firma'], (int) $dup['id']
                ));
            }
        }

        // ── INSERT do contacts ──
        // Stav = CALLED_OK → skip navolávačku, jde rovnou OZ-ovi do queue.
        // created_by_user_id = kdo přidal (pro audit /admin/contacts/added).
        try {
            $this->pdo->prepare(
                "INSERT INTO contacts
                   (firma, email, telefon, ico, adresa, region, operator, poznamka,
                    stav, assigned_sales_id, created_by_user_id,
                    datum_volani, datum_predani,
                    created_at, updated_at)
                 VALUES
                   (:firma, :email, :tel, :ico, :adresa, :reg, :op, :poz,
                    'CALLED_OK', :ozid, :cby,
                    NOW(3), NOW(3),
                    NOW(3), NOW(3))"
            )->execute([
                'firma'  => $firma,
                'email'  => $email,
                'tel'    => $telefon,
                'ico'    => $ico,
                'adresa' => $adresa,
                'reg'    => $region,
                'op'     => $operator,
                'poz'    => $poznamka,
                'ozid'   => $suggested,
                'cby'    => (int) $user['id'],
            ]);
            $newContactId = (int) $this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
            $bounce('⚠ Chyba při vytváření kontaktu. Zkuste to prosím znovu.');
        }

        crm_audit_log(
            $this->pdo, (int) $user['id'],
            'contact_create_direct', 'contact', $newContactId,
            [
                'firma'        => $firma,
                'ico'          => $ico,
                'region'       => $region,
                'assigned_oz'  => $suggested,
                'via'          => 'manual_form',
                'self_assign'  => $userIsOz && $suggested === (int) $user['id'],
            ]
        );

        // Hláška podle toho, komu kontakt patří
        $assignedToSelf = $userIsOz && $suggested === (int) $user['id'];
        crm_flash_set($assignedToSelf
            ? '✓ Kontakt přidán — najdeš ho v Příchozí leady (klikni „Přijmout").'
            : '✓ Kontakt přidán a přiřazen OZ — uvidí ho v Příchozí leady.'
        );
        crm_redirect('/contacts/new');
    }

    // ════════════════════════════════════════════════════════════════
    //  GET /contacts/check-ico — AJAX duplicita check
    //
    //  Vrátí JSON: { found: bool, contact: {id, firma, oz_name, region, stav} }
    //  Použito v /contacts/new formuláři pro live varování při vyplnění IČO.
    // ════════════════════════════════════════════════════════════════
    public function getCheckIco(): void
    {
        $user = crm_require_user($this->pdo);

        // Vyčistit jakýkoli buffer aby JSON odpověď byla čistá (žádné <br/> bordel)
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/json; charset=utf-8');

        $ico = crm_normalize_ico((string) ($_GET['ico'] ?? ''));
        if (mb_strlen($ico) !== 8 || !ctype_digit($ico)) {
            echo json_encode(['found' => false, 'reason' => 'invalid_ico']);
            exit;
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT c.id, c.firma, c.region, c.stav,
                        COALESCE(w.stav, c.stav)   AS effective_stav,
                        COALESCE(su.jmeno, '—')    AS oz_name,
                        c.created_at
                 FROM contacts c
                 LEFT JOIN users su ON su.id = c.assigned_sales_id
                 LEFT JOIN oz_contact_workflow w ON w.contact_id = c.id AND w.oz_id = c.assigned_sales_id
                 WHERE c.ico = :ico
                 LIMIT 1"
            );
            $stmt->execute(['ico' => $ico]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
            echo json_encode(['found' => false, 'reason' => 'db_error']);
            exit;
        }

        if (!$row) {
            echo json_encode(['found' => false]);
            exit;
        }

        echo json_encode([
            'found'   => true,
            'contact' => [
                'id'         => (int) $row['id'],
                'firma'      => (string) $row['firma'],
                'region'     => (string) $row['region'],
                'stav'       => (string) $row['effective_stav'],
                'oz_name'    => (string) $row['oz_name'],
                'created_at' => (string) ($row['created_at'] ?? ''),
            ],
        ]);
        exit;
    }

    // ════════════════════════════════════════════════════════════════
    //  GET /me/added-contacts — Moje doporučenky (per zaměstnanec)
    //
    //  Každý zaměstnanec si může otevřít přehled vlastních doporučenek
    //  — kolik přidal, kdy a v jakém stavu jsou. Důkaz vlastní práce
    //  + konverzní stats (aktivní vs. uzavřené vs. nezájem).
    // ════════════════════════════════════════════════════════════════
    public function getMyAdditions(): void
    {
        $user  = crm_require_user($this->pdo);
        $myId  = (int) $user['id'];

        $title = 'Moje doporučenky';
        $csrf  = crm_csrf_token();
        $flash = crm_flash_take();

        $period = (string) ($_GET['period'] ?? '30d');
        $valid  = ['today', '7d', '30d', '90d', 'all'];
        if (!in_array($period, $valid, true)) { $period = '30d'; }

        $periodWhere = match ($period) {
            'today' => "AND c.created_at >= CURDATE()",
            '7d'    => "AND c.created_at >= NOW() - INTERVAL 7 DAY",
            '30d'   => "AND c.created_at >= NOW() - INTERVAL 30 DAY",
            '90d'   => "AND c.created_at >= NOW() - INTERVAL 90 DAY",
            default => "",
        };

        // Hlavní seznam — moje doporučenky v daném období
        $sql = "SELECT c.id, c.firma, c.ico, c.telefon, c.email, c.region, c.stav,
                       c.created_at,
                       COALESCE(oz.jmeno, '—')   AS oz_name,
                       COALESCE(w.stav, c.stav)  AS effective_stav
                FROM contacts c
                LEFT JOIN users oz ON oz.id = c.assigned_sales_id
                LEFT JOIN oz_contact_workflow w
                       ON w.contact_id = c.id AND w.oz_id = c.assigned_sales_id
                WHERE c.created_by_user_id = :uid
                  {$periodWhere}
                ORDER BY c.created_at DESC
                LIMIT 500";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['uid' => $myId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Konverzní statistiky — jen z mých doporučenek (bez period filteru)
        $statStmt = $this->pdo->prepare(
            "SELECT
                COUNT(*)                                                      AS total,
                SUM(CASE WHEN c.created_at >= CURDATE()              THEN 1 ELSE 0 END) AS today,
                SUM(CASE WHEN c.created_at >= NOW() - INTERVAL 7 DAY THEN 1 ELSE 0 END) AS last7,
                SUM(CASE WHEN COALESCE(w.stav, c.stav) IN ('UZAVRENO')                                  THEN 1 ELSE 0 END) AS uzavreno,
                SUM(CASE WHEN COALESCE(w.stav, c.stav) IN ('NABIDKA','SCHUZKA','CALLBACK','SANCE',
                                                          'SMLOUVA','BO_PREDANO','BO_VPRACI','BO_VRACENO',
                                                          'NOVE','OBVOLANO','ZPRACOVAVA')              THEN 1 ELSE 0 END) AS aktivni,
                SUM(CASE WHEN COALESCE(w.stav, c.stav) IN ('NEZAJEM','NERELEVANTNI')                   THEN 1 ELSE 0 END) AS nezajem,
                SUM(CASE WHEN c.stav = 'CALLED_OK' AND w.id IS NULL                                     THEN 1 ELSE 0 END) AS ceka
             FROM contacts c
             LEFT JOIN oz_contact_workflow w
                    ON w.contact_id = c.id AND w.oz_id = c.assigned_sales_id
             WHERE c.created_by_user_id = :uid"
        );
        $statStmt->execute(['uid' => $myId]);
        $stats = $statStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        ob_start();
        require dirname(__DIR__) . '/views/my-additions/index.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    // ════════════════════════════════════════════════════════════════
    //  GET /me/contact-detail?id=X — Read-only detail mojí doporučenky
    //
    //  Zaměstnanec uvidí jen kontakty, které sám přidal (created_by_user_id).
    //  Bez edit, bez akcí, jen info: stav, OZ, poznámky OZ, timeline.
    //  Admin/majitel uvidí cokoli (full audit).
    // ════════════════════════════════════════════════════════════════
    public function getMyContactDetail(): void
    {
        $user  = crm_require_user($this->pdo);
        $myId  = (int) $user['id'];
        $cid   = (int) ($_GET['id'] ?? 0);

        if ($cid <= 0) {
            crm_flash_set('Chybí ID kontaktu.');
            crm_redirect('/me/added-contacts');
        }

        // Načti kontakt + ověř ownership (created_by_user_id) nebo admin role
        $isAdmin = in_array((string) ($user['role'] ?? ''), ['majitel', 'superadmin'], true);

        $stmt = $this->pdo->prepare(
            "SELECT c.id, c.firma, c.ico, c.telefon, c.email, c.adresa, c.region,
                    c.operator, c.stav, c.poznamka, c.created_at, c.created_by_user_id,
                    c.assigned_sales_id, c.prilez, c.prilez_do,
                    COALESCE(oz.jmeno, '—')   AS oz_name,
                    COALESCE(w.stav, c.stav)  AS effective_stav,
                    w.updated_at              AS workflow_updated_at,
                    w.callback_at, w.schuzka_at
             FROM contacts c
             LEFT JOIN users oz ON oz.id = c.assigned_sales_id
             LEFT JOIN oz_contact_workflow w
                    ON w.contact_id = c.id AND w.oz_id = c.assigned_sales_id
             WHERE c.id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $cid]);
        $contact = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$contact) {
            crm_flash_set('⚠ Kontakt nenalezen.');
            crm_redirect('/me/added-contacts');
        }

        // Security: smí vidět jen pokud ho přidal, nebo je admin
        $addedByMe = ((int) ($contact['created_by_user_id'] ?? 0) === $myId);
        if (!$addedByMe && !$isAdmin) {
            crm_flash_set('⚠ Tento kontakt jsi nepřidal — vidíš jen své doporučenky.');
            crm_redirect('/me/added-contacts');
        }

        // Poslední 10 poznámek OZ k tomu kontaktu (aby zaměstnanec viděl,
        // co OZ se zákazníkem řešil — důležité pro feedback loop)
        $ozNotes = [];
        try {
            $nStmt = $this->pdo->prepare(
                "SELECT n.note, n.created_at, COALESCE(u.jmeno, '—') AS author
                 FROM oz_contact_notes n
                 LEFT JOIN users u ON u.id = n.oz_id
                 WHERE n.contact_id = :cid
                 ORDER BY n.created_at DESC
                 LIMIT 10"
            );
            $nStmt->execute(['cid' => $cid]);
            $ozNotes = $nStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException) {}

        $title = '📄 ' . ($contact['firma'] ?: 'Detail #' . $cid);
        $csrf  = crm_csrf_token();
        $flash = crm_flash_take();

        ob_start();
        require dirname(__DIR__) . '/views/my-additions/detail.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    // ════════════════════════════════════════════════════════════════
    //  GET /admin/contacts/added — Audit: nedávno přidané kontakty
    //
    //  Přehled kdo kdy přidal jaký kontakt přes /contacts/new.
    //  Filtr: dle uživatele (kdo přidal), období.
    // ════════════════════════════════════════════════════════════════
    public function getAdminRecentAdditions(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        $title = 'Přidané kontakty (doporučenky)';
        $csrf  = crm_csrf_token();
        $flash = crm_flash_take();

        // Filtry z GET
        $byUser   = (int) ($_GET['by'] ?? 0);
        $period   = (string) ($_GET['period'] ?? '30d');
        $valid    = ['today', '7d', '30d', '90d', 'all'];
        if (!in_array($period, $valid, true)) { $period = '30d'; }

        $periodWhere = match ($period) {
            'today' => "AND c.created_at >= CURDATE()",
            '7d'    => "AND c.created_at >= NOW() - INTERVAL 7 DAY",
            '30d'   => "AND c.created_at >= NOW() - INTERVAL 30 DAY",
            '90d'   => "AND c.created_at >= NOW() - INTERVAL 90 DAY",
            default => "",
        };

        $params = [];
        $userWhere = '';
        if ($byUser > 0) {
            $userWhere = 'AND c.created_by_user_id = :byuid';
            $params['byuid'] = $byUser;
        }

        // Hlavní dotaz — jen kontakty s vyplněným created_by_user_id
        $sql = "SELECT c.id, c.firma, c.ico, c.telefon, c.email, c.region, c.stav,
                       c.created_at, c.created_by_user_id,
                       COALESCE(adder.jmeno, '—')      AS adder_name,
                       COALESCE(adder.role, '')        AS adder_role,
                       COALESCE(oz.jmeno, '—')         AS oz_name,
                       COALESCE(w.stav, c.stav)        AS effective_stav
                FROM contacts c
                LEFT JOIN users adder ON adder.id = c.created_by_user_id
                LEFT JOIN users oz    ON oz.id    = c.assigned_sales_id
                LEFT JOIN oz_contact_workflow w
                       ON w.contact_id = c.id AND w.oz_id = c.assigned_sales_id
                WHERE c.created_by_user_id IS NOT NULL
                  {$periodWhere}
                  {$userWhere}
                ORDER BY c.created_at DESC
                LIMIT 500";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Top přidávači (pro filtr-dropdown a stats)
        $topStmt = $this->pdo->query(
            "SELECT c.created_by_user_id AS uid,
                    COALESCE(u.jmeno, '—') AS jmeno,
                    COALESCE(u.role, '')   AS role,
                    COUNT(*)               AS cnt
             FROM contacts c
             LEFT JOIN users u ON u.id = c.created_by_user_id
             WHERE c.created_by_user_id IS NOT NULL
             GROUP BY c.created_by_user_id
             ORDER BY cnt DESC
             LIMIT 50"
        );
        $topAdders = $topStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Total counts pro hlavičku
        $totalStmt = $this->pdo->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN created_at >= CURDATE()              THEN 1 ELSE 0 END) AS today,
                SUM(CASE WHEN created_at >= NOW() - INTERVAL 7 DAY THEN 1 ELSE 0 END) AS last7
             FROM contacts
             WHERE created_by_user_id IS NOT NULL"
        );
        $totals = $totalStmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'today' => 0, 'last7' => 0];

        ob_start();
        require dirname(__DIR__) . '/views/admin/contacts-added/index.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    // ════════════════════════════════════════════════════════════════
    //  GET /admin/contact-proposals — seznam pending návrhů
    // ════════════════════════════════════════════════════════════════
    public function getAdminList(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);
        $this->ensureTable();

        $tab = (string) ($_GET['tab'] ?? 'pending');
        if (!in_array($tab, ['pending', 'approved', 'rejected'], true)) {
            $tab = 'pending';
        }

        $stmt = $this->pdo->prepare(
            "SELECT cp.*,
                    pu.jmeno AS proposer_name,
                    su.jmeno AS suggested_oz_name,
                    ru.jmeno AS reviewer_name
             FROM contact_proposals cp
             LEFT JOIN users pu ON pu.id = cp.proposed_by_user_id
             LEFT JOIN users su ON su.id = cp.suggested_oz_id
             LEFT JOIN users ru ON ru.id = cp.reviewed_by_user_id
             WHERE cp.status = :st
             ORDER BY cp.created_at DESC"
        );
        $stmt->execute(['st' => $tab]);
        $proposals = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Counts per status pro tabby
        $countsStmt = $this->pdo->query(
            "SELECT status, COUNT(*) AS c FROM contact_proposals GROUP BY status"
        );
        $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($countsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[(string) $row['status']] = (int) $row['c'];
        }

        $salesUsers = $this->activeSalesUsers();

        $title = 'Návrhy kontaktů ke schválení';
        $csrf  = crm_csrf_token();
        $flash = crm_flash_take();

        ob_start();
        require dirname(__DIR__) . '/views/admin/contact-proposals/index.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    // ════════════════════════════════════════════════════════════════
    //  POST /admin/contact-proposals/approve
    //  Atomická transakce: INSERT contacts + UPDATE contact_proposals
    // ════════════════════════════════════════════════════════════════
    public function postApprove(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);
        $this->ensureTable();

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/contact-proposals');
        }

        $proposalId = (int) ($_POST['proposal_id'] ?? 0);
        $assignedOz = (int) ($_POST['assigned_oz_id'] ?? 0);
        $reviewNote = trim((string) ($_POST['review_note'] ?? ''));

        if ($proposalId <= 0) {
            crm_flash_set('⚠ Neplatné ID návrhu.');
            crm_redirect('/admin/contact-proposals');
        }

        // OZ musí být zvolen při schválení (povinné — kontakt nemůže existovat bez vlastníka)
        if ($assignedOz <= 0) {
            crm_flash_set('⚠ Při schválení musíte přiřadit kontakt konkrétnímu OZ.');
            crm_redirect('/admin/contact-proposals');
        }

        // Ověřit, že OZ je validní + aktivní
        $ozCheck = $this->pdo->prepare(
            "SELECT 1 FROM users WHERE id = :id AND role = 'obchodak' AND aktivni = 1 LIMIT 1"
        );
        $ozCheck->execute(['id' => $assignedOz]);
        if (!$ozCheck->fetchColumn()) {
            crm_flash_set('⚠ Vybraný OZ není aktivní.');
            crm_redirect('/admin/contact-proposals');
        }

        // Načíst návrh
        $pStmt = $this->pdo->prepare(
            "SELECT * FROM contact_proposals
             WHERE id = :id AND status = 'pending' LIMIT 1"
        );
        $pStmt->execute(['id' => $proposalId]);
        $proposal = $pStmt->fetch(PDO::FETCH_ASSOC);
        if (!$proposal) {
            crm_flash_set('⚠ Návrh nenalezen, nebo už byl zpracován.');
            crm_redirect('/admin/contact-proposals');
        }

        // ── Atomická transakce ──
        $this->pdo->beginTransaction();
        try {
            // 1) INSERT do contacts (stav CALLED_OK = prošel ověřením, jde rovnou OZ-ovi)
            $insertContact = $this->pdo->prepare(
                "INSERT INTO contacts
                   (firma, email, telefon, ico, adresa, region, operator, poznamka,
                    stav, assigned_sales_id, datum_volani, datum_predani,
                    created_at, updated_at)
                 VALUES
                   (:firma, :email, :tel, :ico, :adresa, :reg, :op, :poz,
                    'CALLED_OK', :ozid, NOW(3), NOW(3),
                    NOW(3), NOW(3))"
            );
            $insertContact->execute([
                'firma'  => (string) $proposal['firma'],
                'email'  => (string) $proposal['email'],
                'tel'    => (string) $proposal['telefon'],
                'ico'    => (string) $proposal['ico'],
                'adresa' => (string) $proposal['adresa'],
                'reg'    => (string) $proposal['region'],
                'op'     => (string) $proposal['operator'],
                'poz'    => (string) $proposal['poznamka'],
                'ozid'   => $assignedOz,
            ]);
            $newContactId = (int) $this->pdo->lastInsertId();

            // 2) UPDATE návrh — označit jako approved + link na nový contact
            $updateProposal = $this->pdo->prepare(
                "UPDATE contact_proposals
                 SET status               = 'approved',
                     reviewed_by_user_id  = :rid,
                     reviewed_at          = NOW(3),
                     review_note          = :note,
                     converted_contact_id = :cid
                 WHERE id = :pid"
            );
            $updateProposal->execute([
                'rid'  => (int) $user['id'],
                'note' => $reviewNote === '' ? null : $reviewNote,
                'cid'  => $newContactId,
                'pid'  => $proposalId,
            ]);

            $this->pdo->commit();
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            crm_db_log_error($e, __METHOD__);
            crm_flash_set('⚠ Chyba při schvalování. Zkuste to prosím znovu.');
            crm_redirect('/admin/contact-proposals');
        }

        // Audit log
        crm_audit_log(
            $this->pdo, (int) $user['id'],
            'contact_proposal_approve', 'contact_proposal', $proposalId,
            [
                'contact_id'    => $newContactId,
                'assigned_oz'   => $assignedOz,
                'firma'         => (string) $proposal['firma'],
            ]
        );

        crm_flash_set('✓ Návrh schválen — kontakt přiřazen OZ.');
        crm_redirect('/admin/contact-proposals');
    }

    // ════════════════════════════════════════════════════════════════
    //  POST /admin/contact-proposals/reject — Zamítnutí návrhu
    // ════════════════════════════════════════════════════════════════
    public function postReject(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);
        $this->ensureTable();

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/contact-proposals');
        }

        $proposalId = (int) ($_POST['proposal_id'] ?? 0);
        $reason     = trim((string) ($_POST['reason'] ?? ''));

        if ($proposalId <= 0) {
            crm_flash_set('⚠ Neplatné ID návrhu.');
            crm_redirect('/admin/contact-proposals');
        }
        if (mb_strlen($reason) < 3) {
            crm_flash_set('⚠ Důvod zamítnutí je povinný (min. 3 znaky).');
            crm_redirect('/admin/contact-proposals');
        }
        if (mb_strlen($reason) > 500) { $reason = mb_substr($reason, 0, 500); }

        $update = $this->pdo->prepare(
            "UPDATE contact_proposals
             SET status              = 'rejected',
                 reviewed_by_user_id = :rid,
                 reviewed_at         = NOW(3),
                 review_note         = :note
             WHERE id = :pid AND status = 'pending'"
        );
        $update->execute([
            'rid'  => (int) $user['id'],
            'note' => $reason,
            'pid'  => $proposalId,
        ]);

        if ($update->rowCount() === 0) {
            crm_flash_set('⚠ Návrh nenalezen, nebo už byl zpracován.');
            crm_redirect('/admin/contact-proposals');
        }

        crm_audit_log(
            $this->pdo, (int) $user['id'],
            'contact_proposal_reject', 'contact_proposal', $proposalId,
            ['reason' => $reason]
        );

        crm_flash_set('✓ Návrh zamítnut.');
        crm_redirect('/admin/contact-proposals?tab=rejected');
    }
}
