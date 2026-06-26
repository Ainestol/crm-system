<?php
// e:\Snecinatripu\app\controllers\AdminDatagridController.php
declare(strict_types=1);

/**
 * Live datagrid — power-user pohled na celou DB kontaktů.
 *
 * Routes:
 *   GET /admin/datagrid              — HTML stránka (skeleton, načítá data přes JSON endpoint)
 *   GET /admin/datagrid/data         — JSON endpoint (volá se každých 10 s + při ručním refresh)
 *
 * Použití:
 *   - Sloupce: ID, firma, telefon, region, navolávačka, OZ, stav, smlouva (číslo+datum), výročí, posl. změna
 *   - Sortování / filtry / search řeší Grid.js v prohlížeči (na 1k-5k řádků pohodové)
 *   - Auto-refresh 10 s (volitelné), highlight změn od posledního pollu
 */
final class AdminDatagridController
{
    // Max řádků v jednom JSON payloadu pro client-side rendering.
    // 50k zvládne browser pohodlně; pro produkční 350k+ DB postavíme
    // server-side search/pagination až bude potřeba (přidáme ?q= endpoint).
    // POZOR: datagrid načítá řádky NARÁZ + ke každému 6 korelovaných poddotazů.
    // Při desítkách tisíc kontaktů to server nestihl (timeout / paměť) → 500.
    // Dočasný strop, než bude hotové serverové stránkování (load po stránkách).
    // Řadí se od nejnovější aktivity, takže se ukáže nejnovějších N.
    private const MAX_ROWS = 4_000;

    public function __construct(private PDO $pdo) {}

    /**
     * POST /admin/maintenance/resync-phones — jednorázový resync contact_phones.
     *
     * Projde všechny kontakty s vícero telefony (čárka / středník) a zavolá
     * smart-sync — chybějící řádky přidá, existující (s operátorem) zachová,
     * staré (které už nejsou v contacts.telefon) smaže.
     *
     * Bezpečné — žádné existující ověřené operátory se neztratí.
     * Vrací JSON: {ok, contacts_processed, phones_before, phones_after}.
     */
    public function postResyncPhones(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            while (ob_get_level() > 0) { ob_end_clean(); }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Neplatný CSRF token.']);
            exit;
        }

        require_once dirname(__DIR__) . '/helpers/contact_phones.php';

        $tid = crm_tenant_id();

        $beforeStmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM contact_phones WHERE tenant_id = :tid'
        );
        $beforeStmt->execute(['tid' => $tid]);
        $beforeCount = (int) $beforeStmt->fetchColumn();

        // Vybereme jen kontakty s vícero telefony — single-telefon jsou OK.
        // Multi-tenant: jen kontakty z aktivního tenanta.
        $stmt = $this->pdo->prepare(
            "SELECT id, telefon, stav, operator
             FROM contacts
             WHERE telefon IS NOT NULL
               AND tenant_id = :tid
               AND (telefon LIKE '%,%' OR telefon LIKE '%;%' OR telefon LIKE '%\\n%')"
        );
        $stmt->execute(['tid' => $tid]);
        $processed = 0;
        $errors = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            try {
                crm_phone_ensure_for_contact(
                    $this->pdo,
                    (int) $row['id'],
                    (string) $row['telefon'],
                    (string) ($row['stav'] ?? ''),
                    (string) ($row['operator'] ?? '')
                );
                $processed++;
            } catch (\Throwable $e) {
                $errors++;
                if (function_exists('crm_db_log_error')) {
                    crm_db_log_error(new \PDOException($e->getMessage()), __METHOD__);
                }
            }
        }

        $afterCount = (int) $this->pdo->query('SELECT COUNT(*) FROM contact_phones')->fetchColumn();

        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'                 => true,
            'contacts_processed' => $processed,
            'errors'             => $errors,
            'phones_before'      => $beforeCount,
            'phones_after'       => $afterCount,
            'phones_added'       => $afterCount - $beforeCount,
        ]);
        exit;
    }

    public function getIndex(): void
    {
        $actor = crm_require_user($this->pdo);
        crm_require_roles($actor, ['majitel', 'superadmin']);

        $title = 'Live datagrid — power view';
        $csrf  = crm_csrf_token();
        $flash = crm_flash_take();

        ob_start();
        require dirname(__DIR__) . '/views/admin/datagrid/index.php';
        $content = (string) ob_get_clean();
        $user = $actor; // alias pro layout/base.php (sidebar + topbar)
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    /**
     * JSON endpoint — vrátí všechny kontakty (s limitem) v plochém formátu pro Grid.js.
     */
    public function getData(): void
    {
        $actor = crm_require_user($this->pdo);
        crm_require_roles($actor, ['majitel', 'superadmin']);

        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');

        // POZOR: workflow JOIN MUSÍ být omezený na aktuálního OZ (c.assigned_sales_id),
        // jinak pokud má kontakt v oz_contact_workflow víc řádků (= historie OZ-ů,
        // např. po přesunu kontaktu mezi OZ-y), vznikne kartézský součin a kontakt
        // se v datagridu zduplikuje (každý workflow řádek = jeden řádek navíc).
        $sql = "SELECT
                    c.id,
                    c.firma,
                    c.ico,
                    c.telefon,
                    c.email,
                    c.adresa,
                    c.region,
                    c.operator,
                    c.prilez,
                    c.poznamka,
                    -- Poslední poznámka z contact_notes (= timeline, kam admin přes datagrid přidává)
                    -- Použije se v gridu místo legacy c.poznamka, takže admin uvidí svou poznámku hned.
                    (SELECT cn.note FROM contact_notes cn
                       WHERE cn.contact_id = c.id ORDER BY cn.created_at DESC LIMIT 1) AS latest_note,
                    (SELECT COUNT(*) FROM contact_notes cn WHERE cn.contact_id = c.id) AS notes_count,
                    c.stav AS contact_stav,
                    c.rejection_reason,
                    c.nedovolano_count,
                    c.callback_at,
                    c.datum_volani,
                    c.datum_predani,
                    c.dnc_flag,
                    c.narozeniny_majitele,
                    c.sale_price,
                    c.activation_date,
                    c.cancellation_date,
                    c.created_at,
                    c.updated_at,
                    c.vyrocni_smlouvy,
                    COALESCE(w.stav, '—')             AS workflow_stav,
                    w.stav_changed_at,
                    w.cislo_smlouvy,
                    w.datum_uzavreni,
                    w.schuzka_at,
                    COALESCE(w.smlouva_trvani_roky, 3) AS smlouva_trvani_roky,
                    COALESCE(u_oz.jmeno, '')           AS oz_name,
                    COALESCE(u_cl.jmeno, '')           AS caller_name,
                    (SELECT COUNT(*) FROM oz_contact_actions a WHERE a.contact_id = c.id) AS denik_count,
                    (SELECT p.cleaning_status FROM premium_lead_pool p WHERE p.contact_id = c.id AND p.tenant_id = c.tenant_id LIMIT 1) AS premium_clean,
                    (SELECT p.call_status     FROM premium_lead_pool p WHERE p.contact_id = c.id AND p.tenant_id = c.tenant_id LIMIT 1) AS premium_call,
                    (SELECT p.order_id        FROM premium_lead_pool p WHERE p.contact_id = c.id AND p.tenant_id = c.tenant_id LIMIT 1) AS premium_order
                FROM contacts c
                LEFT JOIN oz_contact_workflow w
                       ON w.contact_id = c.id
                      AND w.oz_id      = c.assigned_sales_id
                LEFT JOIN users u_oz ON u_oz.id = c.assigned_sales_id
                LEFT JOIN users u_cl ON u_cl.id = c.assigned_caller_id
                WHERE c.tenant_id = :tid
                ORDER BY COALESCE(w.stav_changed_at, c.updated_at) DESC, c.id DESC
                LIMIT " . self::MAX_ROWS;

        try {
            // Multi-tenant: data jen z aktivního tenanta
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['tid' => crm_tenant_id()]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'DB chyba'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Spočítat celkový počet (i nad limit) pro info v hlavičce — per-tenant
        try {
            $tStmt = $this->pdo->prepare("SELECT COUNT(*) FROM contacts WHERE tenant_id = :tid");
            $tStmt->execute(['tid' => crm_tenant_id()]);
            $total = (int) $tStmt->fetchColumn();
        } catch (\PDOException) {
            $total = count($rows);
        }

        // Připravit ploché řádky pro Grid.js — { id, firma, ..., elapsed, vyroci_in_days }
        $now = time();
        $out = [];
        foreach ($rows as $r) {
            $changedTs = !empty($r['stav_changed_at']) ? strtotime((string) $r['stav_changed_at']) : null;
            $elapsedSec = $changedTs ? max(0, $now - $changedTs) : null;

            $vyrocniDate  = (string) ($r['vyrocni_smlouvy'] ?? '');
            $vyrociInDays = ($vyrocniDate !== '' && $vyrocniDate !== '0000-00-00')
                ? (int) floor((strtotime($vyrocniDate) - $now) / 86400)
                : null;

            $out[] = [
                'id'                  => (int) $r['id'],
                'firma'               => (string) ($r['firma']            ?? ''),
                'ico'                 => (string) ($r['ico']              ?? ''),
                'telefon'             => (string) ($r['telefon']          ?? ''),
                'email'               => (string) ($r['email']            ?? ''),
                'adresa'              => (string) ($r['adresa']           ?? ''),
                'region'              => (string) ($r['region']           ?? ''),
                'operator'            => (string) ($r['operator']         ?? ''),
                'prilez'              => (string) ($r['prilez']           ?? ''),
                'poznamka'            => (string) ($r['poznamka']         ?? ''),
                'latest_note'         => (string) ($r['latest_note']      ?? ''),
                'notes_count'         => (int)    ($r['notes_count']      ?? 0),
                'contact_stav'        => (string) ($r['contact_stav']     ?? ''),
                'rejection_reason'    => (string) ($r['rejection_reason'] ?? ''),
                'nedovolano_count'    => (int)    ($r['nedovolano_count'] ?? 0),
                'callback_at'         => (string) ($r['callback_at']      ?? ''),
                'datum_volani'        => (string) ($r['datum_volani']     ?? ''),
                'datum_predani'       => (string) ($r['datum_predani']    ?? ''),
                'dnc_flag'            => (int)    ($r['dnc_flag']         ?? 0),
                'narozeniny_majitele' => (string) ($r['narozeniny_majitele'] ?? ''),
                'sale_price'          => (string) ($r['sale_price']       ?? ''),
                'activation_date'     => (string) ($r['activation_date']  ?? ''),
                'cancellation_date'   => (string) ($r['cancellation_date'] ?? ''),
                'workflow_stav'       => (string) ($r['workflow_stav']    ?? '—'),
                'oz_name'             => (string) ($r['oz_name']          ?? ''),
                'caller_name'         => (string) ($r['caller_name']      ?? ''),
                'cislo_smlouvy'       => (string) ($r['cislo_smlouvy']    ?? ''),
                'datum_uzavreni'      => (string) ($r['datum_uzavreni']   ?? ''),
                'schuzka_at'          => (string) ($r['schuzka_at']       ?? ''),
                'smlouva_trvani_roky' => (int)    ($r['smlouva_trvani_roky'] ?? 3),
                'vyrocni_smlouvy'     => $vyrocniDate,
                'vyroci_in_days'      => $vyrociInDays,
                'elapsed_sec'         => $elapsedSec,
                'stav_changed_at'     => (string) ($r['stav_changed_at']  ?? ''),
                'denik_count'         => (int)    ($r['denik_count']      ?? 0),
                'created_at'          => (string) ($r['created_at']       ?? ''),
                'updated_at'          => (string) ($r['updated_at']       ?? ''),
                'premium_clean'       => (string) ($r['premium_clean']    ?? ''),
                'premium_call'        => (string) ($r['premium_call']     ?? ''),
                'premium_order'       => (string) ($r['premium_order']    ?? ''),
            ];
        }

        echo json_encode([
            'ok'         => true,
            'rows'       => $out,
            'total_db'   => $total,
            'returned'   => count($out),
            'truncated'  => $total > self::MAX_ROWS,
            'fetched_at' => date('c'),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Vrátí seznam přípustných hodnot pro dropdown editaci v gridu.
     * GET /admin/datagrid/edit-options
     *
     * Pro stav vrací enum všech známých stavů.
     * Pro OZ vrací aktivní obchodáky (i s multi-role).
     * Pro caller / region / operator také.
     */
    public function getEditOptions(): void
    {
        $actor = crm_require_user($this->pdo);
        crm_require_roles($actor, ['majitel', 'superadmin']);

        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');

        // Stav hodnoty (contacts.stav VARCHAR)
        $stavOptions = [
            'NEW'             => 'NEW (čerstvý, k pročištění)',
            'READY'           => 'READY (vyčištěno, čeká na navolávačku)',
            'VF_SKIP'         => 'VF_SKIP (Vodafone — přeskočeno)',
            'CHYBNY_KONTAKT'  => 'CHYBNY_KONTAKT',
            'EMAIL_READY'     => 'EMAIL_READY (sázka, email kampaň)',
            'ASSIGNED'        => 'ASSIGNED (navolávačka claimnula)',
            'CALLBACK'        => 'CALLBACK (domluveno zpět)',
            'NEDOVOLANO'      => 'NEDOVOLANO (čekání)',
            'CALLED_OK'       => 'CALLED_OK (výhra, předáno OZ)',
            'CALLED_BAD'      => 'CALLED_BAD (bad call)',
            'NEZAJEM'         => 'NEZAJEM (odmítl)',
            'IZOLACE'         => 'IZOLACE (DNC — pozor, GDPR)',
            'FOR_SALES'       => 'FOR_SALES (u OZ, rozjednaný)',
            'RESCUE_REQUESTED' => 'RESCUE_REQUESTED (na záchraně)',
            'DONE'            => 'DONE (uzavřená smlouva)',
        ];

        // OZ list — jen aktivní obchodáci (primární role nebo v roles_extra)
        $ozList = [];
        try {
            $ozStmt = $this->pdo->query(
                "SELECT id, jmeno, email FROM users
                 WHERE aktivni = 1
                   AND (role = 'obchodak'
                        OR JSON_CONTAINS(IFNULL(roles_extra, '[]'), '\"obchodak\"'))
                 ORDER BY jmeno ASC"
            );
            $ozList = $ozStmt ? ($ozStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (\Throwable $_) {}

        // Caller list — jen aktivní navolávačky
        $callerList = [];
        try {
            $clStmt = $this->pdo->query(
                "SELECT id, jmeno, email FROM users
                 WHERE aktivni = 1
                   AND (role = 'navolavacka'
                        OR JSON_CONTAINS(IFNULL(roles_extra, '[]'), '\"navolavacka\"'))
                 ORDER BY jmeno ASC"
            );
            $callerList = $clStmt ? ($clStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (\Throwable $_) {}

        // Operator (TM/O2/VF + prázdný)
        $operatorOptions = ['' => '— prázdný —', 'TM' => '🌸 TM', 'O2' => '🔵 O2', 'VF' => '🔴 VF'];

        // Region
        $regionOptions = [];
        if (function_exists('crm_region_choices')) {
            foreach (crm_region_choices() as $regionCode) {
                $regionOptions[(string) $regionCode] = function_exists('crm_region_label')
                    ? crm_region_label((string) $regionCode)
                    : (string) $regionCode;
            }
        }

        echo json_encode([
            'ok'        => true,
            'stav'      => $stavOptions,
            'oz'        => $ozList,
            'caller'    => $callerList,
            'operator'  => $operatorOptions,
            'region'    => $regionOptions,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * POST /admin/datagrid/update — inline edit jedné buňky.
     *
     * Vstup: contact_id, field, value
     * Editovatelné fieldy: stav, assigned_sales_id, assigned_caller_id,
     *                      operator, region, firma, telefon, email, ico, adresa,
     *                      mesto, poznamka, prilez
     *
     * Po editaci:
     *   - workflow_log entry (audit kdo, kdy, co)
     *   - Při změně assigned_sales_id: přesun / vytvoření oz_contact_workflow řádku
     *   - Při změně stav: pokud stav přechází mimo FOR_SALES, ponecháme workflow,
     *     uživatel ho může uklidit ručně. Pokud stav → FOR_SALES, vytvoříme workflow.
     */
    public function postUpdate(): void
    {
        $actor = crm_require_user($this->pdo);
        crm_require_roles($actor, ['majitel', 'superadmin']);

        // Output buffer guard: pokud PHP vypíše warning/notice (Laragon má display_errors=1),
        // zachytíme to do bufferu místo aby to korumpoval JSON odpověď. Před `echo` ho vyčistíme.
        ob_start();

        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            if (ob_get_length()) ob_clean();
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Neplatný CSRF token.']);
            exit;
        }

        $contactId = (int) ($_POST['contact_id'] ?? 0);
        $field     = (string) ($_POST['field'] ?? '');
        $value     = (string) ($_POST['value'] ?? '');

        if ($contactId <= 0 || $field === '') {
            if (ob_get_length()) ob_clean();
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Chybí contact_id nebo field.']);
            exit;
        }

        // Whitelist editovatelných fieldů (contacts + speciální)
        $allowed = ['stav', 'assigned_sales_id', 'assigned_caller_id',
                    'operator', 'region', 'firma', 'telefon', 'email',
                    'ico', 'adresa', 'mesto', 'poznamka', 'prilez',
                    // Datum fieldy v contacts
                    'callback_at', 'datum_volani', 'datum_predani',
                    'activation_date', 'cancellation_date', 'narozeniny_majitele',
                    'vyrocni_smlouvy',
                    // Ostatní contacts fieldy
                    'sale_price', 'dnc_flag', 'nedovolano_count', 'rejection_reason',
                    // Speciální (jiné tabulky / append)
                    'workflow_stav', 'workflow_cislo_smlouvy', 'workflow_datum_uzavreni',
                    'workflow_schuzka_at', 'workflow_bmsl',
                    'workflow_smlouva_trvani_roky',
                    'add_note'];
        if (!in_array($field, $allowed, true)) {
            if (ob_get_length()) ob_clean();
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => "Field '$field' není editovatelný."]);
            exit;
        }

        // Načti aktuální stav kontaktu (PŘED jakýmkoliv speciálním handlerem,
        // protože handler workflow_stav potřebuje $before['assigned_sales_id']).
        // Multi-tenant: filtr tenant_id, aby admin tenant 1 nečetl data tenant 2.
        $cur = $this->pdo->prepare(
            "SELECT id, stav, assigned_sales_id, assigned_caller_id, operator, region
             FROM contacts WHERE id = ? AND tenant_id = ? LIMIT 1"
        );
        $cur->execute([$contactId, crm_tenant_id()]);
        $before = $cur->fetch(PDO::FETCH_ASSOC);
        if (!$before) {
            if (ob_get_length()) ob_clean();
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Kontakt nenalezen.']);
            exit;
        }

        // ── Speciální handling pro workflow_stav (jiná tabulka) ──
        if ($field === 'workflow_stav') {
            $this->handleWorkflowStavEdit($contactId, $value, (int) $actor['id'], $before);
            return;
        }

        // ── Speciální handling pro add_note (INSERT do contact_notes) ──
        if ($field === 'add_note') {
            $this->handleAddNote($contactId, $value, $actor);
            return;
        }

        // Validace hodnot per field
        $newValue = $value;
        $sqlValue = null; // pro DB

        try {
            if ($field === 'stav') {
                $validStavs = ['NEW', 'READY', 'VF_SKIP', 'CHYBNY_KONTAKT', 'EMAIL_READY',
                               'ASSIGNED', 'CALLBACK', 'NEDOVOLANO', 'CALLED_OK', 'CALLED_BAD',
                               'NEZAJEM', 'IZOLACE', 'FOR_SALES', 'RESCUE_REQUESTED', 'DONE'];
                if (!in_array($value, $validStavs, true)) {
                    throw new \RuntimeException("Neplatný stav '$value'.");
                }
                $sqlValue = $value;
            }
            elseif ($field === 'assigned_sales_id') {
                // value = 0 nebo "" → NULL (zrušení vlastnictví)
                if ($value === '' || $value === '0') {
                    $sqlValue = null;
                } else {
                    $sid = (int) $value;
                    $vStmt = $this->pdo->prepare(
                        "SELECT id, jmeno FROM users WHERE id = ? AND aktivni = 1
                         AND (role = 'obchodak'
                              OR JSON_CONTAINS(IFNULL(roles_extra, '[]'), '\"obchodak\"'))"
                    );
                    $vStmt->execute([$sid]);
                    $ozRow = $vStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$ozRow) {
                        throw new \RuntimeException("Vybraný uživatel není aktivní OZ.");
                    }
                    $sqlValue = $sid;
                    $newValue = (string) $ozRow['jmeno']; // pro odpověď
                }
            }
            elseif ($field === 'assigned_caller_id') {
                if ($value === '' || $value === '0') {
                    $sqlValue = null;
                } else {
                    $cid = (int) $value;
                    $vStmt = $this->pdo->prepare(
                        "SELECT id, jmeno FROM users WHERE id = ? AND aktivni = 1
                         AND (role = 'navolavacka'
                              OR JSON_CONTAINS(IFNULL(roles_extra, '[]'), '\"navolavacka\"'))"
                    );
                    $vStmt->execute([$cid]);
                    $clRow = $vStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$clRow) {
                        throw new \RuntimeException("Vybraný uživatel není aktivní navolávačka.");
                    }
                    $sqlValue = $cid;
                    $newValue = (string) $clRow['jmeno'];
                }
            }
            elseif ($field === 'operator') {
                if (!in_array($value, ['', 'TM', 'O2', 'VF'], true)) {
                    throw new \RuntimeException("Neplatný operator '$value'.");
                }
                $sqlValue = $value;
            }
            elseif ($field === 'region') {
                $allowedRegions = function_exists('crm_region_choices') ? array_map('strval', crm_region_choices()) : [];
                if ($value !== '' && !in_array($value, $allowedRegions, true)) {
                    throw new \RuntimeException("Neplatný region '$value'.");
                }
                $sqlValue = $value;
            }
            // ── Datumy: callback_at, datum_volani, datum_predani, activation_date, cancellation_date, narozeniny_majitele, vyrocni_smlouvy ──
            elseif (in_array($field, ['callback_at', 'datum_volani', 'datum_predani',
                                       'activation_date', 'cancellation_date',
                                       'narozeniny_majitele', 'vyrocni_smlouvy'], true)) {
                $trimmed = trim($value);
                if ($trimmed === '' || $trimmed === '0000-00-00') {
                    $sqlValue = null;
                } else {
                    // Akceptuj YYYY-MM-DD nebo YYYY-MM-DD HH:MM[:SS]
                    $ts = strtotime($trimmed);
                    if ($ts === false) {
                        throw new \RuntimeException("Neplatný formát data '$value'. Použij YYYY-MM-DD nebo YYYY-MM-DD HH:MM.");
                    }
                    // Pro datetime fieldy (callback_at, datum_volani, datum_predani) ulož s časem
                    $datetimeFields = ['callback_at', 'datum_volani', 'datum_predani'];
                    $sqlValue = in_array($field, $datetimeFields, true)
                        ? date('Y-m-d H:i:s', $ts)
                        : date('Y-m-d', $ts);
                }
            }
            // ── Numerické: sale_price, dnc_flag, nedovolano_count ──
            elseif (in_array($field, ['sale_price', 'dnc_flag', 'nedovolano_count'], true)) {
                if ($value === '') {
                    $sqlValue = null;
                } else {
                    // sale_price = decimal
                    if ($field === 'sale_price') {
                        $clean = str_replace([' ', ','], ['', '.'], $value);
                        if (!is_numeric($clean)) {
                            throw new \RuntimeException("Cena musí být číslo (např. 2500 nebo 2500.50).");
                        }
                        $sqlValue = (float) $clean;
                    } else {
                        // dnc_flag, nedovolano_count = int
                        if (!ctype_digit(ltrim($value, '-'))) {
                            throw new \RuntimeException("'$field' musí být celé číslo.");
                        }
                        $sqlValue = (int) $value;
                    }
                }
            }
            // ── Workflow fieldy (jiná tabulka — speciální handling) ──
            elseif (in_array($field, ['workflow_cislo_smlouvy', 'workflow_datum_uzavreni',
                                       'workflow_schuzka_at', 'workflow_bmsl'], true)) {
                // Validace + delegace do speciálního handleru
                $ozId = (int) ($before['assigned_sales_id'] ?? 0);
                if ($ozId <= 0) {
                    throw new \RuntimeException('Kontakt nemá přiřazeného OZ — workflow fieldy nelze editovat. Nejdřív přiřaď OZ.');
                }
                // Smysluplnost validace
                if ($field === 'workflow_datum_uzavreni' || $field === 'workflow_schuzka_at') {
                    if ($value === '') {
                        $wfValue = null;
                    } else {
                        $ts = strtotime($value);
                        if ($ts === false) throw new \RuntimeException("Neplatný formát data.");
                        $wfValue = $field === 'workflow_schuzka_at'
                            ? date('Y-m-d H:i:s', $ts)
                            : date('Y-m-d', $ts);
                    }
                } elseif ($field === 'workflow_bmsl') {
                    if ($value === '') {
                        $wfValue = null;
                    } else {
                        $clean = str_replace([' ', ','], ['', '.'], $value);
                        if (!is_numeric($clean)) throw new \RuntimeException("BMSL musí být číslo.");
                        $wfValue = (float) $clean;
                    }
                } else {
                    // workflow_cislo_smlouvy = text
                    $wfValue = trim($value);
                    if (mb_strlen($wfValue) > 100) throw new \RuntimeException("Číslo smlouvy max 100 znaků.");
                }
                $this->handleWorkflowFieldEdit($contactId, $field, $wfValue, $ozId, (int) $actor['id'], $before);
                return;
            }
            else {
                // Text fieldy: firma, telefon, email, ico, adresa, mesto, poznamka, prilez, rejection_reason
                $trimmed = trim($value);
                if (mb_strlen($trimmed) > 500) {
                    throw new \RuntimeException("Hodnota příliš dlouhá (max 500 znaků).");
                }
                $sqlValue = $trimmed;
            }
        } catch (\RuntimeException $e) {
            if (ob_get_length()) ob_clean();
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            exit;
        }

        // ── Ochrana proti ztrátě poznámky ────────────────────────────────
        // Prázdná hodnota NESMÍ přepsat existující neprázdnou poznámku
        // (časté při editaci v gridu — uživatel klikne do buňky a omylem
        // uloží prázdno → smaže bohatou poznámku z importu/od navolávačky).
        if (in_array($field, ['poznamka', 'prilez'], true)
            && trim((string) $sqlValue) === ''
            && trim((string) ($before[$field] ?? '')) !== '') {
            if (ob_get_length()) ob_clean();
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' =>
                'Prázdnou hodnotou nelze přepsat existující poznámku (ochrana proti ztrátě dat).']);
            exit;
        }

        // Spustit UPDATE v transakci (kvůli workflow_log + workflow row přesunu)
        $this->pdo->beginTransaction();
        try {
            // 1) UPDATE contacts — multi-tenant: filtr na aktivní tenant
            $upd = $this->pdo->prepare(
                "UPDATE contacts SET `$field` = :v, updated_at = NOW(3)
                 WHERE id = :id AND tenant_id = :tid"
            );
            $upd->execute(['v' => $sqlValue, 'id' => $contactId, 'tid' => crm_tenant_id()]);

            // 1b) Při změně telefonu — synchronizovat contact_phones.
            //     Pokud admin přidá 5 telefonů "777, 602, 555, ...", funkce
            //     rozparsuje a vytvoří chybějící řádky. Existující řádky
            //     se stejným digits zachová (i s operátorem).
            if ($field === 'telefon') {
                require_once dirname(__DIR__) . '/helpers/contact_phones.php';
                try {
                    crm_phone_ensure_for_contact(
                        $this->pdo, $contactId,
                        (string) $sqlValue,
                        (string) ($before['stav']     ?? ''),
                        (string) ($before['operator'] ?? '')
                    );
                } catch (\Throwable $_) {}
            }

            // 2) workflow_log entry (audit) — pro stav nebo owner změny
            if (in_array($field, ['stav', 'assigned_sales_id', 'assigned_caller_id'], true)) {
                $oldVal = $field === 'stav' ? (string) $before['stav']
                    : (string) ($before[$field] ?? '');
                $newValStr = $field === 'stav' ? (string) $sqlValue
                    : (string) ($sqlValue ?? '');

                $note = "ADMIN EDIT (datagrid): $field změněno";
                $this->pdo->prepare(
                    "INSERT INTO workflow_log (contact_id, user_id, old_status, new_status, note, created_at)
                     VALUES (:cid, :uid, :old, :new, :note, NOW(3))"
                )->execute([
                    'cid'  => $contactId,
                    'uid'  => (int) $actor['id'],
                    'old'  => $field === 'stav' ? $oldVal : (string) $before['stav'],
                    'new'  => $field === 'stav' ? $newValStr : (string) $before['stav'],
                    'note' => $note . ' (' . $oldVal . ' → ' . $newValStr . ')',
                ]);
            }

            // 3) Při změně assigned_sales_id — kompletní auto-handling
            if ($field === 'assigned_sales_id') {
                $oldOzId = (int) ($before['assigned_sales_id'] ?? 0);
                $newOzId = (int) ($sqlValue ?? 0);
                $oldStav = (string) ($before['stav'] ?? 'NEW');

                // 3a) Pokud byl OZ → jiný OZ: přesun workflow row
                if ($oldOzId > 0 && $newOzId > 0 && $oldOzId !== $newOzId) {
                    $checkStmt = $this->pdo->prepare(
                        "SELECT id FROM oz_contact_workflow WHERE contact_id = ? AND oz_id = ?"
                    );
                    $checkStmt->execute([$contactId, $newOzId]);
                    if ($checkStmt->fetchColumn() !== false) {
                        $this->pdo->prepare(
                            "DELETE FROM oz_contact_workflow WHERE contact_id = ? AND oz_id = ?"
                        )->execute([$contactId, $oldOzId]);
                    } else {
                        $this->pdo->prepare(
                            "UPDATE oz_contact_workflow SET oz_id = ?, updated_at = NOW(3)
                             WHERE contact_id = ? AND oz_id = ?"
                        )->execute([$newOzId, $contactId, $oldOzId]);
                    }
                }

                // 3b) AUTO-PROMOTE — když admin přiřadí OZ a kontakt je v "raw" stavu
                //    (NEW / READY / VF_SKIP atd.), kontakt MUSÍ skočit do OZ queue.
                //    Bez toho ho OZ vůbec neuvidí.
                //
                //    Logika: pokud old=0 (žádný OZ) a new>0, nebo pokud se měnil mezi OZ,
                //    nastavíme contacts.stav = CALLED_OK (= „připraveno k převzetí OZ")
                //    a vytvoříme workflow row pro nového OZ (pokud neexistuje).
                if ($newOzId > 0) {
                    // Raw / mezistavy, ze kterých BUMPNEME na CALLED_OK aby OZ viděl kontakt
                    // ve své pracovní ploše (OZ filter: c.stav = 'CALLED_OK').
                    // FOR_SALES je legacy stav z importu — OZ ho taky bez bumpu nevidí.
                    $rawStavs = ['NEW', 'READY', 'VF_SKIP', 'ASSIGNED', 'NEDOVOLANO',
                                 'EMAIL_READY', 'CHYBNY_KONTAKT', 'CALLED_BAD', 'NEZAJEM',
                                 'FOR_SALES'];

                    // Pokud contacts.stav byl raw, povýšit na CALLED_OK
                    if (in_array($oldStav, $rawStavs, true)) {
                        // Multi-tenant: filtr aby admin tenant 1 nepřepsal data tenant 2
                        $this->pdo->prepare(
                            "UPDATE contacts SET stav = 'CALLED_OK', datum_predani = NOW(3), updated_at = NOW(3)
                             WHERE id = ? AND tenant_id = ?"
                        )->execute([$contactId, crm_tenant_id()]);

                        // Workflow log: stav promote (tenant_id auto-doplní wrapper)
                        $this->pdo->prepare(
                            "INSERT INTO workflow_log (contact_id, user_id, old_status, new_status, note, created_at)
                             VALUES (?, ?, ?, 'CALLED_OK', 'ADMIN EDIT: auto-promote stav po přiřazení OZ', NOW(3))"
                        )->execute([$contactId, (int) $actor['id'], $oldStav]);
                    }

                    // Vytvoř workflow řádek pro nového OZ (pokud neexistuje)
                    $wfCheck = $this->pdo->prepare(
                        "SELECT id FROM oz_contact_workflow WHERE contact_id = ? AND oz_id = ? AND tenant_id = ?"
                    );
                    $wfCheck->execute([$contactId, $newOzId, crm_tenant_id()]);
                    if ($wfCheck->fetchColumn() === false) {
                        try {
                            $this->pdo->prepare(
                                "INSERT INTO oz_contact_workflow
                                 (contact_id, oz_id, stav, started_at, stav_changed_at, updated_at)
                                 VALUES (?, ?, 'NOVE', NOW(3), NOW(3), NOW(3))"
                            )->execute([$contactId, $newOzId]);
                        } catch (\Throwable $_) {}
                    }
                }
            }

            // 4) Při změně assigned_caller_id — auto-promote stav na ASSIGNED
            //    Stejná logika jako u OZ (CALLED_OK), jen pro navolávačku.
            //    Filter v /caller vyžaduje stav = 'READY' (z poolu) NEBO 'ASSIGNED'
            //    (explicitně přiřazené). Bez bumpu na ASSIGNED by navolávačka
            //    nevidela kontakt přiřazený přes datagrid.
            if ($field === 'assigned_caller_id') {
                $newCallerId = (int) ($sqlValue ?? 0);
                $oldStavC    = (string) ($before['stav'] ?? 'NEW');

                if ($newCallerId > 0) {
                    // Raw stavy, ze kterých BUMPNEME na ASSIGNED. NEW je hlavní
                    // (čerstvý import), READY/VF_SKIP/atd. už nějakou pre-klasifikaci
                    // mají, ale admin to explicitně přiřazuje → respect.
                    $rawStavsCaller = ['NEW', 'READY', 'VF_SKIP', 'NEDOVOLANO',
                                       'EMAIL_READY', 'CHYBNY_KONTAKT', 'CALLED_BAD',
                                       'NEZAJEM', 'FOR_SALES'];
                    if (in_array($oldStavC, $rawStavsCaller, true)) {
                        // Multi-tenant filter
                        $this->pdo->prepare(
                            "UPDATE contacts SET stav = 'ASSIGNED', updated_at = NOW(3)
                             WHERE id = ? AND tenant_id = ?"
                        )->execute([$contactId, crm_tenant_id()]);

                        $this->pdo->prepare(
                            "INSERT INTO workflow_log (contact_id, user_id, old_status, new_status, note, created_at)
                             VALUES (?, ?, ?, 'ASSIGNED', 'ADMIN EDIT: auto-promote stav po přiřazení navolávačky', NOW(3))"
                        )->execute([$contactId, (int) $actor['id'], $oldStavC]);
                    }
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if (ob_get_length()) ob_clean();
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'DB chyba: ' . $e->getMessage()]);
            exit;
        }

        // Audit log do audit_log tabulky (pokud existuje crm_audit_log helper)
        if (function_exists('crm_audit_log')) {
            try {
                // $before SELECT načítá jen id/stav/assigned_*/operator/region,
                // takže pro ostatní fieldy v $before chybí klíč → použít '' jako fallback
                $oldForLog = array_key_exists($field, $before) ? (string) $before[$field] : '';
                crm_audit_log($this->pdo, (int) $actor['id'], 'datagrid_edit', 'contact', $contactId, [
                    'field' => $field,
                    'old'   => $oldForLog,
                    'new'   => (string) ($sqlValue ?? ''),
                ]);
            } catch (\Throwable $_) {}
        }

        // Vyčisti případný PHP error output co se mohl nahrnout do bufferu
        if (ob_get_length()) ob_clean();

        echo json_encode([
            'ok'         => true,
            'contact_id' => $contactId,
            'field'      => $field,
            'value'      => $newValue,
            'sql_value'  => $sqlValue,
        ], JSON_UNESCAPED_UNICODE);
        exit; // hard stop — žádný downstream kód
    }

    // ════════════════════════════════════════════════════════════════
    //  BULK AKCE — hromadné operace na vybraných kontaktech
    //  POST /admin/datagrid/bulk
    //  Body: ids[], action, value (caller_id / oz_id pro assign)
    //  Limit: 500 kontaktů per request
    // ════════════════════════════════════════════════════════════════

    private const BULK_LIMIT = 500;

    /** POST /admin/datagrid/bulk — bulk akce na vybraných kontaktech */
    public function postBulk(): void
    {
        $actor = crm_require_user($this->pdo);
        crm_require_roles($actor, ['majitel', 'superadmin']);

        ob_start();
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            if (ob_get_length()) ob_clean();
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Neplatný CSRF token.']);
            exit;
        }

        $action = (string) ($_POST['action'] ?? '');
        $value  = (string) ($_POST['value'] ?? '');
        $rawIds = isset($_POST['ids']) && is_array($_POST['ids']) ? $_POST['ids'] : [];
        $ids = array_values(array_filter(array_map('intval', $rawIds), fn($i) => $i > 0));
        $ids = array_unique($ids);

        if ($ids === []) {
            if (ob_get_length()) ob_clean();
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Žádné kontakty vybrané.']);
            exit;
        }
        if (count($ids) > self::BULK_LIMIT) {
            if (ob_get_length()) ob_clean();
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Limit ' . self::BULK_LIMIT . ' kontaktů per request (vybráno ' . count($ids) . ').']);
            exit;
        }

        $validActions = ['assign_caller', 'assign_oz', 'reset_to_pool'];
        if (!in_array($action, $validActions, true)) {
            if (ob_get_length()) ob_clean();
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => "Neznámá akce '$action'."]);
            exit;
        }

        try {
            switch ($action) {
                case 'assign_caller':
                    $this->bulkAssignCaller($ids, (int) $value, (int) $actor['id']);
                    break;
                case 'assign_oz':
                    $this->bulkAssignOz($ids, (int) $value, (int) $actor['id']);
                    break;
                case 'reset_to_pool':
                    $this->bulkResetToPool($ids, (int) $actor['id']);
                    break;
            }
        } catch (\Throwable $e) {
            if (function_exists('crm_db_log_error')) crm_db_log_error($e, __METHOD__);
            if (ob_get_length()) ob_clean();
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'DB chyba: ' . $e->getMessage()]);
            exit;
        }

        // Audit log
        if (function_exists('crm_audit_log')) {
            try {
                crm_audit_log($this->pdo, (int) $actor['id'], 'datagrid_bulk', 'contact', 0, [
                    'action' => $action,
                    'value'  => $value,
                    'count'  => count($ids),
                    'ids'    => array_slice($ids, 0, 50), // jen prvních 50 do logu
                ]);
            } catch (\Throwable $_) {}
        }

        if (ob_get_length()) ob_clean();
        echo json_encode([
            'ok'      => true,
            'action'  => $action,
            'count'   => count($ids),
            'message' => '✓ Akce dokončena pro ' . count($ids) . ' kontaktů.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Bulk: přiřaď navolávačku všem ids + auto-promote stav. */
    private function bulkAssignCaller(array $ids, int $callerId, int $adminId): void
    {
        if ($callerId <= 0) {
            throw new \RuntimeException('Neplatné ID navolávačky.');
        }
        // Ověř, že je to skutečně navolávačka
        $cs = $this->pdo->prepare(
            "SELECT id FROM users WHERE id = ? AND aktivni = 1
             AND (role = 'navolavacka' OR JSON_CONTAINS(IFNULL(roles_extra, '[]'), '\"navolavacka\"'))"
        );
        $cs->execute([$callerId]);
        if (!$cs->fetchColumn()) {
            throw new \RuntimeException('Uživatel není aktivní navolávačka.');
        }

        // VŠECHNY stavy MIMO finálních (= explicit admin přiřazení = navolávačka má volat)
        // Finalní stavy zachováme — kontakt je už uzavřený, nemělo by smysl bumpnout.
        $finalStavs = ['DONE', 'UZAVRENO'];

        $this->pdo->beginTransaction();
        try {
            // Multi-tenant filter pro bulk operace
            $tid = crm_tenant_id();
            foreach (array_chunk($ids, 250) as $chunk) {
                $ph = implode(',', array_fill(0, count($chunk), '?'));
                // 1) Přiřaď navolávačku všem — jen v rámci tenanta
                $this->pdo->prepare(
                    "UPDATE contacts SET assigned_caller_id = ?, updated_at = NOW(3)
                     WHERE id IN ($ph) AND tenant_id = ?"
                )->execute(array_merge([$callerId], $chunk, [$tid]));

                // 2) Auto-promote: VŠECHNY kontakty mimo finálních → ASSIGNED
                //    (= caller filter v /caller vyžaduje ASSIGNED nebo READY z poolu)
                $phF = implode(',', array_fill(0, count($finalStavs), '?'));
                $this->pdo->prepare(
                    "UPDATE contacts SET stav = 'ASSIGNED'
                     WHERE id IN ($ph) AND stav NOT IN ($phF) AND tenant_id = ?"
                )->execute(array_merge($chunk, $finalStavs, [$tid]));
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Bulk: přiřaď OZ všem ids + auto-promote stav + workflow row. */
    private function bulkAssignOz(array $ids, int $ozId, int $adminId): void
    {
        if ($ozId <= 0) {
            throw new \RuntimeException('Neplatné ID obchodáka.');
        }
        $os = $this->pdo->prepare(
            "SELECT id FROM users WHERE id = ? AND aktivni = 1
             AND (role = 'obchodak' OR JSON_CONTAINS(IFNULL(roles_extra, '[]'), '\"obchodak\"'))"
        );
        $os->execute([$ozId]);
        if (!$os->fetchColumn()) {
            throw new \RuntimeException('Uživatel není aktivní obchodák (OZ).');
        }

        $rawStavs = ['NEW', 'READY', 'VF_SKIP', 'ASSIGNED', 'NEDOVOLANO',
                     'EMAIL_READY', 'CHYBNY_KONTAKT', 'CALLED_BAD', 'NEZAJEM',
                     'FOR_SALES'];

        $this->pdo->beginTransaction();
        try {
            $tid = crm_tenant_id();
            foreach (array_chunk($ids, 250) as $chunk) {
                $ph = implode(',', array_fill(0, count($chunk), '?'));

                // 1) Přiřaď OZ — jen v rámci tenanta
                $this->pdo->prepare(
                    "UPDATE contacts SET assigned_sales_id = ?, updated_at = NOW(3)
                     WHERE id IN ($ph) AND tenant_id = ?"
                )->execute(array_merge([$ozId], $chunk, [$tid]));

                // 2) Auto-promote raw → CALLED_OK + datum_predani
                $phS = implode(',', array_fill(0, count($rawStavs), '?'));
                $this->pdo->prepare(
                    "UPDATE contacts SET stav = 'CALLED_OK',
                                          datum_predani = COALESCE(datum_predani, NOW(3))
                     WHERE id IN ($ph) AND stav IN ($phS) AND tenant_id = ?"
                )->execute(array_merge($chunk, $rawStavs, [$tid]));

                // 3) Vytvoř workflow row pro každého (kde neexistuje)
                foreach ($chunk as $cid) {
                    $wfCheck = $this->pdo->prepare(
                        "SELECT id FROM oz_contact_workflow
                         WHERE contact_id = ? AND oz_id = ? AND tenant_id = ?"
                    );
                    $wfCheck->execute([$cid, $ozId, $tid]);
                    if ($wfCheck->fetchColumn() === false) {
                        try {
                            $this->pdo->prepare(
                                "INSERT INTO oz_contact_workflow
                                 (contact_id, oz_id, stav, started_at, stav_changed_at, updated_at)
                                 VALUES (?, ?, 'NOVE', NOW(3), NOW(3), NOW(3))"
                            )->execute([$cid, $ozId]);
                        } catch (\Throwable $_) {}
                    }
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Bulk: vrátit kontakty do pool (reset assigned_caller_id + stav=READY + unlock). */
    private function bulkResetToPool(array $ids, int $adminId): void
    {
        $this->pdo->beginTransaction();
        try {
            $tid = crm_tenant_id();
            foreach (array_chunk($ids, 250) as $chunk) {
                $ph = implode(',', array_fill(0, count($chunk), '?'));
                // Multi-tenant filter — admin tenant 1 nesmí resetovat data tenant 2
                $this->pdo->prepare(
                    "UPDATE contacts
                     SET assigned_caller_id = NULL,
                         locked_by = NULL,
                         locked_until = NULL,
                         stav = 'READY',
                         updated_at = NOW(3)
                     WHERE id IN ($ph) AND tenant_id = ?"
                )->execute(array_merge($chunk, [$tid]));
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Speciální handling pro editaci workflow_stav (oz_contact_workflow.stav).
     *
     * Update řádek pro contact_id + oz_id = assigned_sales_id. Pokud řádek neexistuje
     * (kontakt nikdy nebyl u OZ), vytvoří se nový.
     *
     * @param array<string,mixed> $before  Hodnoty z contacts před změnou
     */
    private function handleWorkflowStavEdit(int $contactId, string $newStav, int $adminId, array $before): void
    {
        $validStavs = ['NOVE', 'ZPRACOVAVA', 'NABIDKA', 'SCHUZKA', 'SANCE', 'CALLBACK',
                       'BO_PREDANO', 'BO_VPRACI', 'BO_VRACENO', 'SMLOUVA', 'UZAVRENO',
                       'REKLAMACE', 'FOR_SALES'];
        if (!in_array($newStav, $validStavs, true)) {
            if (ob_get_length()) ob_clean();
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => "Neplatný workflow stav '$newStav'."]);
            return;
        }

        $ozId = (int) ($before['assigned_sales_id'] ?? 0);
        if ($ozId <= 0) {
            if (ob_get_length()) ob_clean();
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Kontakt nemá přiřazeného OZ — nejdřív přiřaď OZ ve sloupci „OZ", potom nastav workflow stav.']);
            return;
        }

        $this->pdo->beginTransaction();
        try {
            // Pokus o UPDATE existujícího řádku
            $upd = $this->pdo->prepare(
                "UPDATE oz_contact_workflow
                 SET stav = :stav, stav_changed_at = NOW(3), updated_at = NOW(3)
                 WHERE contact_id = :cid AND oz_id = :oid"
            );
            $upd->execute(['stav' => $newStav, 'cid' => $contactId, 'oid' => $ozId]);
            $rowsAffected = $upd->rowCount();

            // Pokud nic nedotčeno (= workflow řádek neexistuje), vytvoříme nový
            if ($rowsAffected === 0) {
                $this->pdo->prepare(
                    "INSERT INTO oz_contact_workflow
                     (contact_id, oz_id, stav, started_at, stav_changed_at, updated_at)
                     VALUES (?, ?, ?, NOW(3), NOW(3), NOW(3))"
                )->execute([$contactId, $ozId, $newStav]);
            }

            // AUTO-PROMOTE: pokud contacts.stav je v "raw" stavu (NEW/READY/atd.),
            // bumpneme ho na CALLED_OK (= připraveno pro OZ workflow). Bez toho by
            // OZ kontakt neviděl ve své pracovní ploše (filter vyžaduje CALLED_OK).
            $oldContactStav = (string) ($before['stav'] ?? 'NEW');
            // FOR_SALES je v seznamu — legacy stav z importu, OZ ho jinak nevidí.
            $rawStavs = ['NEW', 'READY', 'VF_SKIP', 'ASSIGNED', 'NEDOVOLANO',
                         'EMAIL_READY', 'CHYBNY_KONTAKT', 'CALLED_BAD', 'NEZAJEM',
                         'FOR_SALES'];
            $contactStav = $oldContactStav;
            if (in_array($oldContactStav, $rawStavs, true)) {
                // Multi-tenant filter
                $this->pdo->prepare(
                    "UPDATE contacts SET stav = 'CALLED_OK', datum_predani = COALESCE(datum_predani, NOW(3)), updated_at = NOW(3)
                     WHERE id = ? AND tenant_id = ?"
                )->execute([$contactId, crm_tenant_id()]);
                $contactStav = 'CALLED_OK';
            }

            // Workflow log — old = původní stav, new = stav PO případném auto-promote.
            // Pokud došlo k promote, log zachycuje přechod NEW → CALLED_OK (i když
            // hlavní akce byla nastavení workflow_stav).
            $logNote = 'ADMIN EDIT (datagrid): workflow_stav → ' . $newStav;
            if ($oldContactStav !== $contactStav) {
                $logNote .= ' (auto-promote contacts.stav ' . $oldContactStav . ' → ' . $contactStav . ')';
            }
            $this->pdo->prepare(
                "INSERT INTO workflow_log (contact_id, user_id, old_status, new_status, note, created_at)
                 VALUES (:cid, :uid, :old, :new, :note, NOW(3))"
            )->execute([
                'cid'  => $contactId,
                'uid'  => $adminId,
                'old'  => $oldContactStav,
                'new'  => $contactStav,
                'note' => $logNote,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if (ob_get_length()) ob_clean();
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'DB chyba: ' . $e->getMessage()]);
            return;
        }

        if (function_exists('crm_audit_log')) {
            try {
                crm_audit_log($this->pdo, $adminId, 'datagrid_edit', 'contact', $contactId, [
                    'field' => 'workflow_stav',
                    'new'   => $newStav,
                ]);
            } catch (\Throwable $_) {}
        }

        if (ob_get_length()) ob_clean();
        echo json_encode([
            'ok'         => true,
            'contact_id' => $contactId,
            'field'      => 'workflow_stav',
            'value'      => $newStav,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Editace workflow_* fieldů (cislo_smlouvy, datum_uzavreni, schuzka_at, bmsl).
     * Aktualizuje oz_contact_workflow pro contact_id + assigned_sales_id.
     */
    private function handleWorkflowFieldEdit(int $contactId, string $field, $value, int $ozId, int $adminId, array $before): void
    {
        // Mapování field → DB column
        $columnMap = [
            'workflow_cislo_smlouvy'        => 'cislo_smlouvy',
            'workflow_datum_uzavreni'       => 'datum_uzavreni',
            'workflow_schuzka_at'           => 'schuzka_at',
            'workflow_bmsl'                 => 'bmsl',
            'workflow_smlouva_trvani_roky'  => 'smlouva_trvani_roky',
        ];
        $dbColumn = $columnMap[$field] ?? null;
        if ($dbColumn === null) {
            if (ob_get_length()) ob_clean();
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => "Field '$field' není mapovaný."]);
            exit;
        }

        // Pro trvani_roky: validace 1-10, cast na int
        if ($field === 'workflow_smlouva_trvani_roky') {
            $intVal = (int) $value;
            if ($intVal < 1 || $intVal > 10) {
                if (ob_get_length()) ob_clean();
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Trvání smlouvy musí být 1-10 let.']);
                exit;
            }
            $value = $intVal;
        }

        $this->pdo->beginTransaction();
        try {
            // UPDATE existing workflow row
            $upd = $this->pdo->prepare(
                "UPDATE oz_contact_workflow SET `$dbColumn` = :v, updated_at = NOW(3)
                 WHERE contact_id = :cid AND oz_id = :oid"
            );
            $upd->execute(['v' => $value, 'cid' => $contactId, 'oid' => $ozId]);

            // Pokud workflow řádek neexistuje, vytvoříme ho s default stavem NOVE
            if ($upd->rowCount() === 0) {
                $this->pdo->prepare(
                    "INSERT INTO oz_contact_workflow
                     (contact_id, oz_id, stav, `$dbColumn`, started_at, stav_changed_at, updated_at)
                     VALUES (?, ?, 'NOVE', ?, NOW(3), NOW(3), NOW(3))"
                )->execute([$contactId, $ozId, $value]);
            }

            // ── AUTO-VÝPOČET vyrocni_smlouvy ──
            // Pokud admin změnil datum_uzavreni nebo trvani_roky (nebo cislo_smlouvy
            // — používáme jako trigger, kdy je smlouva "uložená"), přepočítat výročí.
            // Logika: vyrocni_smlouvy = datum_uzavreni + smlouva_trvani_roky let
            // To samé dělá BackofficeController při uzavření smlouvy.
            $vyrocniNew = null;
            if (in_array($field, ['workflow_datum_uzavreni', 'workflow_cislo_smlouvy', 'workflow_smlouva_trvani_roky'], true)) {
                $wfStmt = $this->pdo->prepare(
                    "SELECT datum_uzavreni, COALESCE(smlouva_trvani_roky, 3) AS trvani
                     FROM oz_contact_workflow WHERE contact_id = ? AND oz_id = ? LIMIT 1"
                );
                $wfStmt->execute([$contactId, $ozId]);
                $wf = $wfStmt->fetch(PDO::FETCH_ASSOC);
                $du = (string) ($wf['datum_uzavreni'] ?? '');
                $tr = (int) ($wf['trvani'] ?? 3);
                if ($du !== '' && $du !== '0000-00-00' && strtotime($du) !== false && $tr >= 1 && $tr <= 10) {
                    // Multi-tenant filter
                    $this->pdo->prepare(
                        "UPDATE contacts
                         SET vyrocni_smlouvy = DATE_ADD(:du, INTERVAL :tr YEAR),
                             updated_at      = NOW(3)
                         WHERE id = :cid AND tenant_id = :tid"
                    )->execute(['du' => $du, 'tr' => $tr, 'cid' => $contactId, 'tid' => crm_tenant_id()]);
                    $vyrocniNew = date('Y-m-d', strtotime("+{$tr} years", strtotime($du)));
                }
            }

            // Workflow log
            $contactStav = (string) ($before['stav'] ?? 'NEW');
            $logNote = "ADMIN EDIT (datagrid): {$dbColumn} = " . (is_null($value) ? '(prázdné)' : (string) $value);
            if ($vyrocniNew !== null) {
                $logNote .= " · vyrocni_smlouvy → {$vyrocniNew}";
            }
            $this->pdo->prepare(
                "INSERT INTO workflow_log (contact_id, user_id, old_status, new_status, note, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW(3))"
            )->execute([
                $contactId, $adminId, $contactStav, $contactStav, $logNote,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if (ob_get_length()) ob_clean();
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'DB chyba: ' . $e->getMessage()]);
            exit;
        }

        if (function_exists('crm_audit_log')) {
            try {
                crm_audit_log($this->pdo, $adminId, 'datagrid_edit', 'contact', $contactId, [
                    'field' => $field,
                    'value' => $value,
                ]);
            } catch (\Throwable $_) {}
        }

        if (ob_get_length()) ob_clean();
        $resp = [
            'ok'         => true,
            'contact_id' => $contactId,
            'field'      => $field,
            'value'      => $value,
        ];
        if ($vyrocniNew !== null) {
            $resp['vyrocni_smlouvy'] = $vyrocniNew; // pro live update sloupce "Výročí"
        }
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Speciální handling pro přidání poznámky (INSERT do contact_notes).
     *
     * Místo přepsání contacts.poznamka přidáme NOVÝ řádek do contact_notes
     * s prefixem [ADMIN: jméno]. Tento přístup zachovává historii a admin
     * poznámky se objeví v timeline u kontaktu vedle navolávačky / BO.
     *
     * @param array<string,mixed> $actor User array
     */
    private function handleAddNote(int $contactId, string $noteText, array $actor): void
    {
        $noteText = trim($noteText);
        if ($noteText === '') {
            if (ob_get_length()) ob_clean();
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Poznámka nemůže být prázdná.']);
            return;
        }
        if (mb_strlen($noteText) > 2000) {
            if (ob_get_length()) ob_clean();
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Poznámka příliš dlouhá (max 2000 znaků).']);
            return;
        }

        $adminId   = (int) $actor['id'];
        // Žádný prefix v textu — autor je v user_id (contact_notes) resp.
        // author_user_id (oz_contact_notes), UI ho ukáže přes JOIN + role-badge.

        // Zjisti, kdo má přiřazeného OZ — pokud má, poznámka půjde i do oz_contact_notes,
        // aby ji OZ viděl v leads view (OZ čte z oz_contact_notes, ne z contact_notes).
        $ozId = 0;
        try {
            // Multi-tenant: filter aby cross-tenant SELECT nevracel cizí data
            $ozStmt = $this->pdo->prepare(
                "SELECT assigned_sales_id FROM contacts WHERE id = ? AND tenant_id = ?"
            );
            $ozStmt->execute([$contactId, crm_tenant_id()]);
            $ozId = (int) ($ozStmt->fetchColumn() ?: 0);
        } catch (\Throwable $_) {}

        try {
            // 1) Globální timeline (contact_notes) — vidí admin/historie
            //    user_id = autor (admin)
            $this->pdo->prepare(
                "INSERT INTO contact_notes (contact_id, user_id, note, created_at)
                 VALUES (?, ?, ?, NOW(3))"
            )->execute([$contactId, $adminId, $noteText]);

            // 2) OZ-specifická timeline (oz_contact_notes) — vidí OZ ve své pracovní ploše.
            //    Bez tohohle by admin poznámka pro OZ nebyla viditelná (#44).
            //    oz_id          = vlastník kontaktu (Šáša) — pro filtr v jeho views
            //    author_user_id = skutečný autor (admin) — pro display v UI
            if ($ozId > 0) {
                try {
                    $this->pdo->prepare(
                        "INSERT INTO oz_contact_notes (contact_id, oz_id, author_user_id, note, created_at)
                         VALUES (?, ?, ?, ?, NOW(3))"
                    )->execute([$contactId, $ozId, $adminId, $noteText]);
                } catch (\Throwable $_) {
                    // tabulka nemusí existovat ve starší DB — selhání ignorovat (hlavní zápis prošel)
                }
            }
        } catch (\Throwable $e) {
            if (ob_get_length()) ob_clean();
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'DB chyba: ' . $e->getMessage()]);
            return;
        }

        if (function_exists('crm_audit_log')) {
            try {
                crm_audit_log($this->pdo, $adminId, 'datagrid_add_note', 'contact', $contactId, [
                    'note_preview' => mb_substr($noteText, 0, 100),
                ]);
            } catch (\Throwable $_) {}
        }

        if (ob_get_length()) ob_clean();
        echo json_encode([
            'ok'         => true,
            'contact_id' => $contactId,
            'field'      => 'add_note',
            'value'      => $fullNote,
            'message'    => $ozId > 0
                ? 'Poznámka přidána do timeline + zobrazí se i OZ.'
                : 'Poznámka přidána do timeline kontaktu (kontakt ještě nemá OZ).',
        ], JSON_UNESCAPED_UNICODE);
        exit; // hard stop — nedovolíme žádnému downstream kódu pokračovat
    }

    /**
     * Historie změn pro konkrétní kontakt — JSON timeline.
     * GET /admin/datagrid/contact-history?id=42
     */
    public function getContactHistory(): void
    {
        $actor = crm_require_user($this->pdo);
        crm_require_roles($actor, ['majitel', 'superadmin']);

        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');

        $contactId = (int) ($_GET['id'] ?? 0);
        if ($contactId <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Chybí parametr id'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Hlavička kontaktu — multi-tenant filter
        try {
            $hStmt = $this->pdo->prepare(
                "SELECT c.id, c.firma, c.telefon, c.email, c.region,
                        c.created_at AS contact_created,
                        COALESCE(w.stav, '—') AS current_stav,
                        w.stav_changed_at,
                        w.cislo_smlouvy, w.datum_uzavreni
                 FROM contacts c
                 LEFT JOIN oz_contact_workflow w ON w.contact_id = c.id
                 WHERE c.id = :cid AND c.tenant_id = :tid LIMIT 1"
            );
            $hStmt->execute(['cid' => $contactId, 'tid' => crm_tenant_id()]);
            $header = $hStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\PDOException) {
            $header = null;
        }
        if (!$header) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Kontakt nenalezen'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Historie z workflow_log (kdo, kdy, odkud → kam, proč)
        $history = crm_load_contact_history($this->pdo, $contactId);

        echo json_encode([
            'ok'      => true,
            'header'  => $header,
            'history' => $history,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Plnohodnotná activity-feed stránka.
     * GET /admin/feed — HTML view, který načítá data ze stejného JSON endpointu.
     */
    public function getFeedPage(): void
    {
        $actor = crm_require_user($this->pdo);
        crm_require_roles($actor, ['majitel', 'superadmin']);

        $title = 'Activity feed — co se právě děje';
        $csrf  = crm_csrf_token();
        $flash = crm_flash_take();

        ob_start();
        require dirname(__DIR__) . '/views/admin/feed/index.php';
        $content = (string) ob_get_clean();
        $user = $actor; // alias pro layout/base.php (sidebar + topbar)
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    /**
     * Activity feed — real-time changelog napříč CRM.
     * Sleduje změny v oz_contact_workflow + záznamy v pracovním deníku (oz_contact_actions).
     *
     * GET param ?since=<unixtime> — vrátí jen události od daného timestampu (pro polling).
     */
    public function getFeed(): void
    {
        $actor = crm_require_user($this->pdo);
        crm_require_roles($actor, ['majitel', 'superadmin']);

        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');

        $sinceParam = (string) ($_GET['since'] ?? '');
        $sinceWhere = '';
        if ($sinceParam !== '' && ctype_digit($sinceParam)) {
            $sinceTs = (int) $sinceParam;
            $sinceDt = date('Y-m-d H:i:s.v', $sinceTs);
            $sinceWhere = "WHERE event_ts > '" . $sinceDt . "'";
        }

        // Stránkování — ?page=N (default 1). 100 záznamů per stránka.
        // POZN: Při polling (since param) stránkování nemá smysl — to vrací jen nové.
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $page = min($page, 1000); // sanity max 100k záznamů
        $offset = ($page - 1) * 100;

        // UNION 3 zdrojů událostí — sjednoceně setřídíme od nejnovějšího:
        //   1) workflow_log     = KOMPLETNÍ HISTORIE změn stavu (navolávačky, čističky, OZ, BO, …)
        //   2) oz_contact_actions = záznamy v pracovním deníku OZ
        //   3) audit_log (premium) = premium pipeline akce (objednávka, čištění, navolávání)
        // Multi-tenant filter — feed jen z aktivního tenanta.
        // Filtr přes c.tenant_id v každé větvi UNION (contacts JOIN).
        $tidLiteral = (int) crm_tenant_id(); // int safe pro SQL literal
        $sql = "(
                  SELECT 'stav_change' AS kind,
                         w.contact_id,
                         w.created_at  AS event_ts,
                         w.new_status  AS payload,
                         c.firma,
                         c.region,
                         COALESCE(u.jmeno, '—') AS actor_name,
                         IFNULL(w.note, '') AS extra
                  FROM workflow_log w
                  INNER JOIN contacts c ON c.id = w.contact_id AND c.tenant_id = {$tidLiteral}
                  LEFT JOIN users u ON u.id = w.user_id
                ) UNION ALL (
                  SELECT 'action' AS kind,
                         a.contact_id,
                         a.created_at AS event_ts,
                         LEFT(a.action_text, 120) AS payload,
                         c.firma,
                         c.region,
                         COALESCE(u.jmeno, '—') AS actor_name,
                         '' AS extra
                  FROM oz_contact_actions a
                  INNER JOIN contacts c ON c.id = a.contact_id AND c.tenant_id = {$tidLiteral}
                  LEFT JOIN users u ON u.id = a.oz_id
                ) UNION ALL (
                  SELECT 'premium' AS kind,
                         COALESCE(
                             (SELECT p.contact_id FROM premium_lead_pool p
                                WHERE p.id = al.entity_id AND al.entity_type = 'premium_lead_pool'
                                LIMIT 1),
                             0
                         ) AS contact_id,
                         al.created_at AS event_ts,
                         al.action AS payload,
                         COALESCE(
                             (SELECT c2.firma FROM contacts c2
                                JOIN premium_lead_pool p2 ON p2.contact_id = c2.id
                                WHERE p2.id = al.entity_id AND al.entity_type = 'premium_lead_pool'
                                LIMIT 1),
                             ''
                         ) AS firma,
                         '' AS region,
                         COALESCE(u.jmeno, '—') AS actor_name,
                         IFNULL(CAST(al.details AS CHAR), '') AS extra
                  FROM audit_log al
                  LEFT JOIN users u ON u.id = al.user_id
                  WHERE al.action LIKE 'premium%'
                )";
        if ($sinceWhere !== '') {
            // Polling mode — jen nové od posledního pollu, bez stránkování (offset by zničil polling)
            $sql = "SELECT * FROM ({$sql}) AS evt {$sinceWhere} ORDER BY event_ts DESC LIMIT 100";
        } else {
            // Normal mode — stránkování přes ?page=N
            $sql = "SELECT * FROM ({$sql}) AS evt ORDER BY event_ts DESC LIMIT 100 OFFSET {$offset}";
        }

        $events = [];
        try {
            $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            // Fallback: pokud selže UNION s premium (např. premium_lead_pool nebo audit_log
            // má jinou strukturu na produkci), vrátíme aspoň workflow + actions bez premium.
            crm_db_log_error($e, __METHOD__);
            try {
                $fallbackSql = "(
                                  SELECT 'stav_change' AS kind, w.contact_id, w.created_at AS event_ts,
                                         w.new_status AS payload, c.firma, c.region,
                                         COALESCE(u.jmeno, '—') AS actor_name, IFNULL(w.note, '') AS extra
                                  FROM workflow_log w
                                  INNER JOIN contacts c ON c.id = w.contact_id AND c.tenant_id = {$tidLiteral}
                                  LEFT JOIN users u ON u.id = w.user_id
                                ) UNION ALL (
                                  SELECT 'action' AS kind, a.contact_id, a.created_at AS event_ts,
                                         LEFT(a.action_text, 120) AS payload, c.firma, c.region,
                                         COALESCE(u.jmeno, '—') AS actor_name, '' AS extra
                                  FROM oz_contact_actions a
                                  INNER JOIN contacts c ON c.id = a.contact_id AND c.tenant_id = {$tidLiteral}
                                  LEFT JOIN users u ON u.id = a.oz_id
                                )";
                if ($sinceWhere !== '') {
                    $fallbackSql = "SELECT * FROM ({$fallbackSql}) AS evt {$sinceWhere} ORDER BY event_ts DESC LIMIT 100";
                } else {
                    $fallbackSql = "SELECT * FROM ({$fallbackSql}) AS evt ORDER BY event_ts DESC LIMIT 100 OFFSET {$offset}";
                }
                $rows = $this->pdo->query($fallbackSql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\PDOException $e2) {
                http_response_code(500);
                echo json_encode(['ok' => false, 'error' => 'DB chyba'], JSON_UNESCAPED_UNICODE);
                return;
            }
        }

        try {
            $now  = time();
            foreach ($rows as $r) {
                $ts = strtotime((string) $r['event_ts']);
                $events[] = [
                    'kind'         => (string) $r['kind'],
                    'contact_id'   => (int)    $r['contact_id'],
                    'firma'        => (string) ($r['firma']      ?? ''),
                    'region'       => (string) ($r['region']     ?? ''),
                    'actor_name'   => (string) ($r['actor_name'] ?? '—'),
                    'payload'      => (string) ($r['payload']    ?? ''),
                    'extra'        => (string) ($r['extra']      ?? ''),
                    'event_ts'     => (string) $r['event_ts'],
                    'event_unix'   => $ts,
                    'elapsed_sec'  => $ts ? max(0, $now - $ts) : null,
                ];
            }
        } catch (\PDOException $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'DB chyba'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Total events napříč všemi 3 zdroji — pro výpočet počtu stránek.
        // Polling mode (since param) tohle nepotřebuje, ušetříme query.
        $totalEvents = 0;
        $totalPages  = 1;
        if ($sinceWhere === '') {
            try {
                $tw = (int) ($this->pdo->query("SELECT COUNT(*) FROM workflow_log")->fetchColumn() ?: 0);
                $ta = (int) ($this->pdo->query("SELECT COUNT(*) FROM oz_contact_actions")->fetchColumn() ?: 0);
                $tp = 0;
                try {
                    $tp = (int) ($this->pdo->query("SELECT COUNT(*) FROM audit_log WHERE action LIKE 'premium%'")->fetchColumn() ?: 0);
                } catch (\PDOException) { /* fallback bez premium */ }
                $totalEvents = $tw + $ta + $tp;
                $totalPages  = max(1, (int) ceil($totalEvents / 100));
            } catch (\PDOException $e) {
                crm_db_log_error($e, __METHOD__);
            }
        }

        echo json_encode([
            'ok'           => true,
            'events'       => $events,
            'page'         => $page,
            'total_pages'  => $totalPages,
            'total_events' => $totalEvents,
            'has_more'     => count($events) === 100,
            'fetched_at'   => date('c'),
            'now_unix'     => time(),
        ], JSON_UNESCAPED_UNICODE);
    }
}
