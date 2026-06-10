<?php
// e:\Snecinatripu\app\controllers\AdminImportController.php
declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'import_csv.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'import_xlsx.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'audit.php';

/**
 * Import kontaktů — DVOUFÁZOVÝ flow:
 *   1) /admin/import           GET   → formulář
 *   2) /admin/import           POST  → upload + ANALÝZA (nic nezapisuje do contacts)
 *                                       → redirect na preview
 *   3) /admin/import/preview/{id}     → uživatel vidí stats + chyby + duplicity
 *   4) /admin/import/commit    POST  → skutečně vloží/aktualizuje řádky
 *   5) /admin/import/cancel    POST  → smaže nahraný soubor + preview
 *
 * Podporované formáty: CSV (UTF-8, ; nebo , delimiter), XLSX, XLS (přes XMLReader).
 *
 * Duplicity vs. DB:
 *   - Default akce 'update'  → aktualizuje stávající záznam novými daty
 *   - Volba         'skip'   → přeskočí, nechá DB beze změny
 *   - Volba         'add'    → vždy přidá nový (vzniká dvojitý záznam — pro výjimky)
 */
final class AdminImportController
{
    private const MAX_BYTES        = 209_715_200;   // 200 MB (XLSX bývá větší)
    private const MAX_ROWS         = 300_000;       // 300k řádků
    private const BATCH_SIZE       = 500;           // INSERT/UPDATE po 500
    private const MAX_ERRORS_KEPT  = 1_000;         // Cap pro detail seznam errors v preview
    private const MAX_DUPS_KEPT    = 5_000;         // Cap pro detail seznam duplicit (snapshoty)
                                                    // POZN: počet duplicit je neomezený (counter `*Total`)
                                                    //       cap je jen na detail s side-by-side snapshoty.

    public function __construct(private PDO $pdo) {}

    // ─────────────────────────────────────────────────────────────────
    //  XHR-aware response helpers
    //  Pokud klient posílá X-Requested-With: XMLHttpRequest, vracíme JSON.
    //  Jinak používáme klasický flash + redirect (běžné non-JS submit).
    // ─────────────────────────────────────────────────────────────────
    private function isXhr(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    /**
     * Při chybě: pošle 422 + JSON s "error" hláškou (XHR), nebo flash + redirect (non-XHR).
     * Tato metoda volá exit — controller dál nepokračuje.
     */
    private function failOrRedirect(string $message, string $fallbackPath = '/admin/import'): void
    {
        if ($this->isXhr()) {
            http_response_code(422);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
            exit;
        }
        crm_flash_set($message);
        crm_redirect($fallbackPath);
    }

    /**
     * Při úspěchu se redirectem: pošle 200 + JSON s "redirect" URL (XHR),
     * nebo provede klasický redirect (non-XHR).
     */
    private function successRedirect(string $path, ?string $flashMessage = null): void
    {
        if ($flashMessage !== null) crm_flash_set($flashMessage);
        if ($this->isXhr()) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'ok'       => true,
                'redirect' => $path,
                'flash'    => $flashMessage,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        crm_redirect($path);
    }

    // ─────────────────────────────────────────────────────────────────
    //  GET /admin/import — Formulář
    // ─────────────────────────────────────────────────────────────────
    public function getIndex(): void
    {
        $actor = crm_require_user($this->pdo);
        crm_require_roles($actor, ['majitel', 'superadmin']);
        $flash = crm_flash_take();
        $title = 'Import kontaktů (CSV / XLSX)';
        $csrf  = crm_csrf_token();
        ob_start();
        require dirname(__DIR__) . '/views/admin/import/form.php';
        $content = (string) ob_get_clean();
        $user = $actor; // alias pro layout/base.php (sidebar + topbar)
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    // ─────────────────────────────────────────────────────────────────
    //  POST /admin/import — Upload + ANALÝZA (žádný DB write)
    // ─────────────────────────────────────────────────────────────────
    public function postImport(): void
    {
        $actor = crm_require_user($this->pdo);
        crm_require_roles($actor, ['majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            $this->failOrRedirect('Neplatný CSRF token.');
        }

        // ── Validace nahraného souboru ──
        if (!isset($_FILES['csv']) || !is_array($_FILES['csv'])) {
            $this->failOrRedirect('Chybí soubor.');
        }
        $file = $_FILES['csv'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->failOrRedirect('Nahrávání souboru selhalo (PHP error kód ' . (int)$file['error'] . ').');
        }
        if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > self::MAX_BYTES) {
            $this->failOrRedirect('Soubor je prázdný nebo příliš velký (max ' . (self::MAX_BYTES / 1024 / 1024) . ' MB).');
        }

        $origName = (string) ($file['name'] ?? 'import.csv');
        $tmpUp    = (string) ($file['tmp_name'] ?? '');
        if ($tmpUp === '' || !is_uploaded_file($tmpUp)) {
            $this->failOrRedirect('Neplatný upload.');
        }
        $ext = strtolower((string) pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx', 'xls'], true)) {
            $this->failOrRedirect('Podporované formáty: .csv, .xlsx, .xls (přípona souboru: "' . $ext . '")');
        }

        // ── Vytvoř pracovní adresář pro tento import ──
        $importId = 'imp_' . bin2hex(random_bytes(8));
        $importDir = CRM_STORAGE_PATH . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . $importId;
        if (!@mkdir($importDir, 0700, true) && !is_dir($importDir)) {
            $this->failOrRedirect('Nelze vytvořit pracovní adresář pro import. Zkontrolujte zápisová práva ve "storage/imports/".');
        }

        // ── Uložit nahraný soubor ──
        $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '_', basename($origName)) ?? 'import';
        $rawPath  = $importDir . DIRECTORY_SEPARATOR . 'raw_' . $safeName;
        if (!@move_uploaded_file($tmpUp, $rawPath)) {
            $this->cleanupImport($importDir);
            $this->failOrRedirect('Nelze uložit nahraný soubor (move_uploaded_file selhalo).');
        }

        // ── Pokud XLSX/XLS, převeď na CSV (streaming) ──
        $csvPath = $importDir . DIRECTORY_SEPARATOR . 'data.csv';
        $sheetInfo = ['name' => '', 'count' => 0, 'all' => []]; // pro preview
        if ($ext === 'xlsx' || $ext === 'xls') {
            $conv = crm_xlsx_to_csv($rawPath, $csvPath);
            if (!($conv['ok'] ?? false)) {
                $this->cleanupImport($importDir);
                $this->failOrRedirect('XLSX nelze přečíst: ' . (string) ($conv['error'] ?? 'neznámá chyba'));
            }
            $sheetInfo = [
                'name'  => (string) ($conv['sheet_name']  ?? ''),
                'count' => (int)    ($conv['sheet_count'] ?? 0),
                'all'   => (array)  ($conv['sheet_names'] ?? []),
            ];
            @unlink($rawPath);
        } else {
            if (!@rename($rawPath, $csvPath)) {
                @copy($rawPath, $csvPath);
                @unlink($rawPath);
            }
        }

        // ── Spustit analýzu (NIC NEZAPISUJE) ──
        $defaultRegion = strtolower(trim((string) ($_POST['default_region'] ?? '')));
        set_time_limit(0);
        ini_set('memory_limit', '768M');

        try {
            $analysis = $this->analyzeFile($csvPath, $origName, $defaultRegion, $ext);
        } catch (\Throwable $e) {
            error_log('[CRM Import] analyze failed for "' . $origName . '": ' . $e->getMessage());
            $this->cleanupImport($importDir);
            $this->failOrRedirect('Analýza souboru selhala: ' . $e->getMessage());
        }

        if (!($analysis['ok'] ?? false)) {
            $this->cleanupImport($importDir);
            $this->failOrRedirect((string) ($analysis['error'] ?? 'Analýza selhala.'));
        }

        // ── Uložit preview JSON ──
        $analysis['import_id']      = $importId;
        $analysis['filename']       = $origName;
        $analysis['format']         = $ext;
        $analysis['default_region'] = $defaultRegion;
        $analysis['uploaded_at']    = date('Y-m-d H:i:s');
        $analysis['admin_id']       = (int) $actor['id'];
        $analysis['sheet_name']     = $sheetInfo['name'];
        $analysis['sheet_count']    = $sheetInfo['count'];
        $analysis['sheet_names']    = $sheetInfo['all'];

        $previewPath = $importDir . DIRECTORY_SEPARATOR . 'preview.json';
        if (file_put_contents($previewPath, json_encode($analysis, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) === false) {
            $this->cleanupImport($importDir);
            $this->failOrRedirect('Nelze uložit preview soubor.');
        }

        // ── Redirect na preview ──
        $this->successRedirect('/admin/import/preview?id=' . urlencode($importId));
    }

    // ─────────────────────────────────────────────────────────────────
    //  GET /admin/import/preview?id=imp_xxx — Preview obrazovka
    // ─────────────────────────────────────────────────────────────────
    public function getPreview(): void
    {
        $actor = crm_require_user($this->pdo);
        crm_require_roles($actor, ['majitel', 'superadmin']);

        $importId = (string) ($_GET['id'] ?? '');
        $analysis = $this->loadPreview($importId, (int) $actor['id']);
        if ($analysis === null) {
            crm_flash_set('Preview nenalezen nebo vypršel.');
            crm_redirect('/admin/import');
        }

        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();
        $title = 'Náhled importu — ' . (string) ($analysis['filename'] ?? '');
        ob_start();
        require dirname(__DIR__) . '/views/admin/import/preview.php';
        $content = (string) ob_get_clean();
        $user = $actor; // alias pro layout/base.php (sidebar + topbar)
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    // ─────────────────────────────────────────────────────────────────
    //  POST /admin/import/commit — Skutečné vložení/aktualizace
    // ─────────────────────────────────────────────────────────────────
    public function postCommit(): void
    {
        $actor = crm_require_user($this->pdo);
        crm_require_roles($actor, ['majitel', 'superadmin']);
        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/import');
        }

        $importId = (string) ($_POST['import_id'] ?? '');
        $analysis = $this->loadPreview($importId, (int) $actor['id']);
        if ($analysis === null) {
            crm_flash_set('Preview nenalezen nebo vypršel.');
            crm_redirect('/admin/import');
        }

        // Globální volba pro DB duplicity
        $dupAction = (string) ($_POST['dup_action'] ?? 'update');
        if (!in_array($dupAction, ['update', 'skip', 'add'], true)) {
            $dupAction = 'update';
        }

        // Per-row overrides pro DB duplicity (POST pole row_action[<rowNum>])
        // Hodnoty: 'skip' / 'update' / 'add' / 'default' (= použít globální $dupAction)
        $rowOverrides = [];
        if (isset($_POST['row_action']) && is_array($_POST['row_action'])) {
            foreach ($_POST['row_action'] as $r => $a) {
                $rowNum = (int) $r;
                $action = (string) $a;
                if ($rowNum > 0 && in_array($action, ['skip', 'update', 'add'], true)) {
                    $rowOverrides[$rowNum] = $action;
                }
            }
        }

        // Per-row overrides pro file duplicity (POST pole file_dup_action[<rowNum>])
        // Default = 'skip' (původní chování — druhý výskyt v souboru se přeskočí).
        // 'add' = přepíše dedup, druhý výskyt se přidá jako nový kontakt.
        $fileDupOverrides = [];
        if (isset($_POST['file_dup_action']) && is_array($_POST['file_dup_action'])) {
            foreach ($_POST['file_dup_action'] as $r => $a) {
                $rowNum = (int) $r;
                $action = (string) $a;
                if ($rowNum > 0 && $action === 'add') {
                    $fileDupOverrides[$rowNum] = 'add';
                }
            }
        }

        $importDir = CRM_STORAGE_PATH . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . $importId;
        $csvPath   = $importDir . DIRECTORY_SEPARATOR . 'data.csv';
        if (!is_file($csvPath)) {
            $this->cleanupImport($importDir);
            crm_flash_set('Datový soubor importu chybí — nahrajte prosím znovu.');
            crm_redirect('/admin/import');
        }

        set_time_limit(0);
        ini_set('memory_limit', '768M');

        try {
            $stats = $this->commitFile(
                $csvPath,
                (string) ($analysis['filename'] ?? ''),
                (string) ($analysis['default_region'] ?? ''),
                (int) $actor['id'],
                $dupAction,
                $rowOverrides,
                $fileDupOverrides
            );
        } catch (\Throwable $e) {
            error_log('[CRM Import] commit failed: ' . $e->getMessage());
            crm_flash_set('Import selhal: ' . $e->getMessage());
            crm_redirect('/admin/import/preview?id=' . urlencode($importId));
        }

        // Audit + import_log
        $log = $this->pdo->prepare(
            'INSERT INTO import_log (admin_id, filename, total_rows, imported, skipped_duplicates, skipped_dnc, errors, created_at)
             VALUES (:aid, :fn, :tot, :imp, :sdup, :sdnc, :err, NOW(3))'
        );
        $log->execute([
            'aid'  => (int) $actor['id'],
            'fn'   => substr((string) ($analysis['filename'] ?? ''), 0, 500),
            'tot'  => $stats['total'],
            'imp'  => $stats['imported'],
            'sdup' => $stats['skipped_dup_file'] + ($dupAction === 'skip' ? $stats['db_dup'] : 0),
            'sdnc' => $stats['skipped_dnc'],
            'err'  => $stats['errors'],
        ]);
        try {
            crm_audit_log($this->pdo, (int) $actor['id'], 'contacts_csv_import', 'import_log',
                (int) $this->pdo->lastInsertId(), [
                    'filename'      => (string) ($analysis['filename'] ?? ''),
                    'format'        => (string) ($analysis['format'] ?? ''),
                    'dup_action'    => $dupAction,
                    'total'         => $stats['total'],
                    'imported'      => $stats['imported'],
                    'updated'       => $stats['updated'],
                    'skipped_dup_file' => $stats['skipped_dup_file'],
                    'skipped_dnc'   => $stats['skipped_dnc'],
                    'errors'        => $stats['errors'],
                ], 'web');
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }

        // Cleanup
        $this->cleanupImport($importDir);

        // ── AUTO-MIX po importu ──
        // Pokud admin v nastavení zapnul auto-mix (default: ZAPNUTÉ), nově importované
        // NEW kontakty se automaticky namíchají v poměru z app_settings a připojí
        // na konec existující fronty. Bez auto-mixu by admin musel manuálně klikat.
        $autoMixMsg = '';
        if ($stats['imported'] > 0
            && function_exists('crm_setting_get_bool')
            && crm_setting_get_bool('mix_auto_after_import', true)
            && class_exists('AdminContactMixController')
        ) {
            try {
                $mixResult = AdminContactMixController::runMix($this->pdo);
                if ($mixResult['mixed'] > 0) {
                    $autoMixMsg = sprintf(
                        ' 🎲 Auto-mix: %d kontaktů (%d firma + %d OSVČ).',
                        $mixResult['mixed'],
                        $mixResult['firma'],
                        $mixResult['osvc']
                    );
                }
            } catch (\Throwable $e) {
                crm_db_log_error($e, __METHOD__ . '_automix');
                $autoMixMsg = ' ⚠ Auto-mix selhal — spusť ručně v /admin/contacts/mix.';
            }
        }

        crm_flash_set(sprintf(
            '✓ Import dokončen: vloženo %d, aktualizováno %d, sloučeno %d, přeskočeno (DB-dup) %d, přeskočeno (DNC) %d, chyby %d.%s',
            $stats['imported'],
            $stats['updated'],
            $stats['merged'] ?? 0,
            $dupAction === 'skip' ? $stats['db_dup'] : 0,
            $stats['skipped_dnc'],
            $stats['errors'],
            $autoMixMsg
        ));
        crm_redirect('/admin/import');
    }

    // ─────────────────────────────────────────────────────────────────
    //  POST /admin/import/cancel — Uživatel zrušil preview, smaž soubor
    // ─────────────────────────────────────────────────────────────────
    public function postCancel(): void
    {
        $actor = crm_require_user($this->pdo);
        crm_require_roles($actor, ['majitel', 'superadmin']);
        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_redirect('/admin/import');
        }

        $importId  = (string) ($_POST['import_id'] ?? '');
        if (preg_match('/^imp_[a-f0-9]{16}$/', $importId)) {
            $importDir = CRM_STORAGE_PATH . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . $importId;
            $this->cleanupImport($importDir);
        }
        crm_flash_set('Import zrušen.');
        crm_redirect('/admin/import');
    }

    // ─────────────────────────────────────────────────────────────────
    //  POST /admin/import/reset — Smaže VŠECHNY kontakty + závislé záznamy.
    //  Vyžaduje:
    //    - role majitel / superadmin
    //    - CSRF
    //    - pole "confirm_text" musí být přesně "RESET" (anti-misclick)
    //  Vše audit-logované.
    // ─────────────────────────────────────────────────────────────────
    public function postReset(): void
    {
        $actor = crm_require_user($this->pdo);
        crm_require_roles($actor, ['majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/import');
        }

        // Anti-misclick: uživatel musí napsat "RESET" do pole
        $typed = trim((string) ($_POST['confirm_text'] ?? ''));
        if ($typed !== 'RESET') {
            crm_flash_set('⚠ Pro reset musíte do pole napsat přesně "RESET". Akce zrušena.');
            crm_redirect('/admin/import');
        }

        $truncateOrder = [
            'commissions', 'contact_quality_ratings', 'contact_notes',
            'workflow_log', 'assignment_log', 'sms_log',
            'oz_contact_workflow', 'oz_contact_notes', 'oz_contact_actions',
            'contact_oz_flags',
            'contacts',
        ];
        $cleanupAlso = ['import_log'];

        // Spočti řádky před mazáním (pro audit log)
        $rowsBefore = [];
        $totalBefore = 0;
        foreach (array_merge($truncateOrder, $cleanupAlso) as $t) {
            if (!$this->tableExists($t)) {
                $rowsBefore[$t] = 'missing';
                continue;
            }
            try {
                $cnt = (int) $this->pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
                $rowsBefore[$t] = $cnt;
                $totalBefore += $cnt;
            } catch (\PDOException $e) {
                crm_db_log_error($e, __METHOD__);
                $rowsBefore[$t] = 'error';
            }
        }

        // Skutečný reset
        $errors = [];
        try {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach ($truncateOrder as $t) {
                if (!$this->tableExists($t)) continue;
                try {
                    $this->pdo->exec("TRUNCATE TABLE `{$t}`");
                } catch (\PDOException $e) {
                    $errors[] = $t . ': ' . $e->getMessage();
                    crm_db_log_error($e, __METHOD__ . '/' . $t);
                }
            }
            foreach ($cleanupAlso as $t) {
                if (!$this->tableExists($t)) continue;
                try {
                    $this->pdo->exec("DELETE FROM `{$t}`");
                } catch (\PDOException $e) {
                    $errors[] = $t . ': ' . $e->getMessage();
                    crm_db_log_error($e, __METHOD__ . '/' . $t);
                }
            }
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
            crm_flash_set('Reset selhal: ' . $e->getMessage());
            crm_redirect('/admin/import');
        }

        // Audit log (KRITICKÉ — admin smazal všechna data)
        try {
            crm_audit_log(
                $this->pdo,
                (int) $actor['id'],
                'contacts.reset_all',
                'contacts',
                null,
                [
                    'rows_before'  => $rowsBefore,
                    'total_before' => $totalBefore,
                    'errors'       => $errors,
                ],
                'web'
            );
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }

        if ($errors !== []) {
            crm_flash_set(sprintf(
                '⚠ Reset proběhl, ale s chybami u %d tabulek. Smazáno %s řádků. Chyby v error logu.',
                count($errors), number_format($totalBefore, 0, ',', ' ')
            ));
        } else {
            crm_flash_set(sprintf(
                '✓ Reset hotov. Smazáno %s řádků kontaktů a závislých dat. Nyní můžete nahrát nový soubor.',
                number_format($totalBefore, 0, ',', ' ')
            ));
        }
        crm_redirect('/admin/import');
    }

    /** @internal — zda tabulka existuje v aktuální DB. */
    private function tableExists(string $tableName): bool
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = :t'
            );
            $st->execute(['t' => $tableName]);
            return ((int) $st->fetchColumn()) > 0;
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Privátní: ANALÝZA (čtení CSV → strukturovaný report)
    // ─────────────────────────────────────────────────────────────────
    /**
     * @return array{
     *   ok: bool,
     *   error?: string,
     *   total_rows?: int,
     *   ok_rows?: int,
     *   header?: list<string>,
     *   errors?: list<array<string,mixed>>,
     *   duplicates_in_file?: list<array<string,mixed>>,
     *   duplicates_in_db?: list<array<string,mixed>>,
     *   dnc?: list<array<string,mixed>>,
     *   counts?: array<string,int>
     * }
     */
    private function analyzeFile(string $csvPath, string $origName, string $defaultRegion, string $sourceFormat): array
    {
        $fh = fopen($csvPath, 'rb');
        if ($fh === false) {
            return ['ok' => false, 'error' => 'CSV nelze otevřít.'];
        }

        // Detekce delimiteru (jen pro CSV — XLSX→CSV používá ; vždy)
        $delimiter = ';';
        $firstLine = fgets($fh);
        if ($firstLine === false) {
            fclose($fh);
            return ['ok' => false, 'error' => 'Soubor je prázdný.'];
        }
        if ($sourceFormat === 'csv') {
            $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
        }
        rewind($fh);

        // ── Smart header detection ──
        // Některé soubory mají nahoře titulek, prázdné řádky nebo metadata.
        // Projdeme prvních 5 řádků a najdeme ten, kterým se "firma" / "nazev_firmy"
        // nebo aspoň region/kraj alias rozpozná. Předchozí řádky se přeskočí.
        $headerRow      = null;
        $map            = [];
        $skippedRows    = [];   // co jsme zahodili (pro chybovou hlášku)
        $headerRowIndex = 0;     // 1-based pozice nalezeného hlavičkového řádku v souboru

        for ($probe = 0; $probe < 5; $probe++) {
            $candidate = fgetcsv($fh, 0, $delimiter, '"', '\\');
            if ($candidate === false) break;

            // přeskočíme úplně prázdné řádky bez hlášení
            if ($this->rowIsEmpty($candidate)) {
                $skippedRows[] = ['row' => $probe + 1, 'preview' => '(prázdný)'];
                continue;
            }
            $candidateMap = $this->buildHeaderMap($candidate);
            $looksLikeHeader = isset($candidateMap['firma'])
                || isset($candidateMap['nazev_firmy'])
                || isset($candidateMap['kraj'])
                || isset($candidateMap['region']);

            if ($looksLikeHeader) {
                $headerRow      = $candidate;
                $map            = $candidateMap;
                $headerRowIndex = $probe + 1;
                break;
            }

            // Uložíme náhled (prvních pár polí) do skipped
            $preview = implode(' | ', array_slice(
                array_map(static fn ($v) => is_string($v) ? trim($v) : '', $candidate),
                0, 6
            ));
            $skippedRows[] = ['row' => $probe + 1, 'preview' => mb_substr($preview, 0, 120)];
        }

        if ($headerRow === null) {
            fclose($fh);
            $skipMsg = '';
            if ($skippedRows !== []) {
                $skipMsg = "\n\nCo parser viděl v prvních řádcích:\n";
                foreach ($skippedRows as $s) {
                    $skipMsg .= sprintf("  Řádek %d: %s\n", $s['row'], $s['preview']);
                }
            }
            return ['ok' => false,
                'error' => 'Hlavičku se nepodařilo rozpoznat. V žádném z prvních 5 řádků nebyl sloupec "firma", "nazev_firmy", "kraj" ani "region". Zkontrolujte, že první řádek souboru obsahuje názvy sloupců.' . $skipMsg];
        }

        $hasFirma  = isset($map['firma']) || isset($map['nazev_firmy']);
        $hasRegion = isset($map['region']) || isset($map['kraj']) || isset($map['mesto']) || $defaultRegion !== '';
        if (!$hasFirma) {
            fclose($fh);
            $foundCols = implode('", "', array_keys($map));
            return ['ok' => false,
                'error' => 'V hlavičce chybí povinný sloupec "firma" (nebo "nazev_firmy"). Nalezené sloupce: "' . $foundCols . '". Zkontrolujte první řádek souboru.'];
        }
        if (!$hasRegion) {
            fclose($fh);
            return ['ok' => false,
                'error' => 'Chybí zdroj kraje — ani sloupec "kraj"/"region"/"mesto", ani jste nevybrali výchozí kraj.'];
        }

        // ── Předem načti DNC a existující contacts hashe (pro dedupe) ──
        [$dncIco, $dncPhone, $dncEmail] = $this->loadDncHashes();
        [$dbIco, $dbPhone, $dbEmail]    = $this->loadExistingContactHashes();
        // Mapu chráněných kontaktů (UZAVRENO / DNC / recent NEZAJEM) — hard-skip
        // bez ohledu na admin's volbu. Bezpečnost > pohodlí.
        $protectedDb = $this->loadProtectedContacts();
        // Mapa email → user_id pro validaci oz_email v uzavřených smlouvách
        $usersByEmail   = $this->loadUsersByEmail();
        // Mapa email → user_id pro validaci caller_email (přiřazení navolávačky)
        $callersByEmail = $this->loadCallersByEmail();

        $errors          = [];
        $duplicatesFile  = [];
        $duplicatesDb    = [];
        $dnc             = [];
        // Total counters — neztratíme reálný počet i když pole capnem na MAX_DUPS_KEPT (sample)
        $duplicatesFileTotal = 0;
        $duplicatesDbTotal   = 0;
        $dncTotal            = 0;

        // Chráněné DB kontakty, na které import narazil (UZAVRENO / DNC / recent NEZAJEM)
        // Tyto se VŽDY skipnou, bez ohledu na admin's volbu strategie.
        $protectedTotal = 0;
        $protectedSamples = [];   // max 50 pro preview
        $protectedByReason = ['Aktivní zákazník (UZAVRENO)' => 0,
                              'Na DNC listu (zákaz volat)' => 0,
                              'Nedávný NEZAJEM (< 180 dní)' => 0];

        // Per-typ breakdown counters (kolik je shod podle IČO / Tel / Email zvlášť).
        // Slouží jen pro UI breakdown v preview — nezahrnuje sample cap.
        $dupFileByMatch = ['ico' => 0, 'telefon' => 0, 'email' => 0];
        $dupDbByMatch   = ['ico' => 0, 'telefon' => 0, 'email' => 0];
        $dncByMatch     = ['ico' => 0, 'telefon' => 0, 'email' => 0];

        $seenIco   = []; // ico -> firstRow
        $seenEmail = []; // email -> firstRow
        $seenPhone = []; // phone -> firstRow

        $totalRows = 0;
        $okRows    = 0;
        // Smart header detection mohla přeskočit úvodní titulky/prázdné řádky.
        // $rowNum začíná na pozici hlavičky, takže čísla v chybách souhlasí s tím,
        // co uživatel vidí v Excelu (řádek 2 = první datový řádek pod hlavičkou).
        $rowNum    = $headerRowIndex;

        while (($row = fgetcsv($fh, 0, $delimiter, '"', '\\')) !== false) {
            $rowNum++;
            if ($this->rowIsEmpty($row)) {
                continue;
            }
            if ($totalRows >= self::MAX_ROWS) {
                $errors[] = ['row' => $rowNum, 'col' => '*',
                    'reason' => 'Limit ' . self::MAX_ROWS . ' řádků dosažen — zbylé řádky byly ignorovány.'];
                break;
            }
            $totalRows++;

            // Snapshot řádku — co parser reálně viděl (pro debug + per-dup zobrazení)
            $rowSnap = $this->rowSnapshot($row, $map);

            // Vytáhni hlavní pole
            $firma = trim($this->cell($row, $map, 'firma'));
            if ($firma === '') {
                $firma = trim($this->cell($row, $map, 'nazev_firmy'));
            }
            $ico   = $this->cell($row, $map, 'ico');
            $tel   = $this->cell($row, $map, 'telefon');
            $email = $this->cell($row, $map, 'email');
            $mesto = $this->cell($row, $map, 'mesto');

            $regionRaw = $this->cell($row, $map, 'region') ?: $this->cell($row, $map, 'kraj');
            $region    = crm_import_normalize_region($regionRaw);
            if ($region === '' || !in_array($region, crm_region_choices(), true)) {
                $cityForRegion = $mesto !== '' ? $mesto : $this->cell($row, $map, 'adresa');
                $fromCity = crm_import_city_to_region($cityForRegion);
                if ($fromCity !== '') {
                    $region = $fromCity;
                }
            }
            if ($region === '' || !in_array($region, crm_region_choices(), true)) {
                if ($defaultRegion !== '' && in_array($defaultRegion, crm_region_choices(), true)) {
                    $region = $defaultRegion;
                }
            }

            // Validace povinných polí
            if ($firma === '') {
                if (count($errors) < self::MAX_ERRORS_KEPT) {
                    $errors[] = ['row' => $rowNum, 'col' => 'firma', 'value' => '',
                        'reason'    => 'Chybí název firmy (povinné pole).',
                        'snapshot'  => $rowSnap];
                }
                continue;
            }
            if ($region === '') {
                if (count($errors) < self::MAX_ERRORS_KEPT) {
                    $errors[] = ['row' => $rowNum, 'col' => 'kraj', 'value' => $regionRaw,
                        'reason'    => 'Nelze určit kraj — zkontrolujte sloupec "kraj" / "region" / "město", nebo nastavte výchozí kraj.',
                        'snapshot'  => $rowSnap];
                }
                continue;
            }

            // ── Validace oz_email pro uzavřené smlouvy a FOR_SALES ────────
            // Pravidla:
            //   • Pokud řádek má `datum_uzavreni`           → oz_email POVINNÝ (uzavřená smlouva)
            //   • Pokud řádek má `stav` = FOR_SALES (CHCE)  → oz_email POVINNÝ (rozjednané)
            //   • Pokud řádek má oz_email                   → musí existovat v `users.email`
            //   • Pokud řádek nemá ani jedno → OK, půjde standardním pipeline (NEW)
            $ozEmailRaw  = $this->cell($row, $map, 'oz_email');
            $ozEmailNorm = strtolower(trim($ozEmailRaw));
            $datumUzavRaw = $this->cell($row, $map, 'datum_uzavreni');
            $hasClosedDate = trim($datumUzavRaw) !== '';

            // ── Validace stav (Ne/Chce sloupec) ────────────────────────────
            $stavRaw    = $this->cell($row, $map, 'stav');
            $stavMapped = self::mapStavValue($stavRaw);
            if ($stavMapped === '__INVALID__') {
                if (count($errors) < self::MAX_ERRORS_KEPT) {
                    $errors[] = ['row' => $rowNum, 'col' => 'stav', 'value' => $stavRaw,
                        'reason'    => 'Neznámý stav "' . $stavRaw . '". Použijte: prázdné, NECHCE, CHCE, NEDOVOLAL, NEBERE, TÍPL TO, CALLBACK.',
                        'snapshot'  => $rowSnap];
                }
                continue;
            }

            if ($hasClosedDate && $ozEmailNorm === '') {
                if (count($errors) < self::MAX_ERRORS_KEPT) {
                    $errors[] = ['row' => $rowNum, 'col' => 'oz_email', 'value' => '',
                        'reason'    => 'Uzavřená smlouva (vyplněné datum_uzavreni) musí mít sloupec oz_email s emailem obchodníka.',
                        'snapshot'  => $rowSnap];
                }
                continue;
            }
            if ($stavMapped === 'FOR_SALES' && $ozEmailNorm === '') {
                if (count($errors) < self::MAX_ERRORS_KEPT) {
                    $errors[] = ['row' => $rowNum, 'col' => 'oz_email', 'value' => '',
                        'reason'    => 'Stav "CHCE" (rozpracovaný kontakt) musí mít vyplněný sloupec oz_email — kontakt se přiřazuje konkrétnímu OZ.',
                        'snapshot'  => $rowSnap];
                }
                continue;
            }
            if ($ozEmailNorm !== '' && !isset($usersByEmail[$ozEmailNorm])) {
                if (count($errors) < self::MAX_ERRORS_KEPT) {
                    $errors[] = ['row' => $rowNum, 'col' => 'oz_email', 'value' => $ozEmailRaw,
                        'reason'    => 'OZ email "' . $ozEmailRaw . '" v systému neexistuje. Zkontrolujte přesný zápis (case-insensitive) nebo uživatele založte v /admin/users.',
                        'snapshot'  => $rowSnap];
                }
                continue;
            }

            // ── Validace caller_email (volitelný — přiřazení navolávačky) ──
            $callerEmailRaw  = $this->cell($row, $map, 'caller_email');
            $callerEmailNorm = strtolower(trim($callerEmailRaw));
            if ($callerEmailNorm !== '' && !isset($callersByEmail[$callerEmailNorm])) {
                if (count($errors) < self::MAX_ERRORS_KEPT) {
                    $errors[] = ['row' => $rowNum, 'col' => 'caller_email', 'value' => $callerEmailRaw,
                        'reason'    => 'caller_email "' . $callerEmailRaw . '" buď neexistuje, nebo nemá roli navolávačka. Použijte email aktivního uživatele s rolí navolavacka.',
                        'snapshot'  => $rowSnap];
                }
                continue;
            }

            $icoN = crm_import_normalize_ico($ico);
            $emN  = crm_import_normalize_email($email);
            $pd   = crm_import_phone_digits($tel);

            // Duplicita v rámci souboru
            if ($icoN !== '' && isset($seenIco[$icoN])) {
                $duplicatesFileTotal++;
                $dupFileByMatch['ico']++;
                if (count($duplicatesFile) < self::MAX_DUPS_KEPT) {
                    $duplicatesFile[] = ['row' => $rowNum, 'first_seen_row' => $seenIco[$icoN]['row'],
                        'match' => 'ico', 'value' => $icoN, 'firma' => $firma,
                        'snapshot_dup' => $rowSnap, 'snapshot_orig' => $seenIco[$icoN]['snap']];
                }
                continue;
            }
            if ($emN !== '' && isset($seenEmail[$emN])) {
                $duplicatesFileTotal++;
                $dupFileByMatch['email']++;
                if (count($duplicatesFile) < self::MAX_DUPS_KEPT) {
                    $duplicatesFile[] = ['row' => $rowNum, 'first_seen_row' => $seenEmail[$emN]['row'],
                        'match' => 'email', 'value' => $emN, 'firma' => $firma,
                        'snapshot_dup' => $rowSnap, 'snapshot_orig' => $seenEmail[$emN]['snap']];
                }
                continue;
            }
            if ($pd !== '' && isset($seenPhone[$pd])) {
                $duplicatesFileTotal++;
                $dupFileByMatch['telefon']++;
                if (count($duplicatesFile) < self::MAX_DUPS_KEPT) {
                    $duplicatesFile[] = ['row' => $rowNum, 'first_seen_row' => $seenPhone[$pd]['row'],
                        'match' => 'telefon', 'value' => $pd, 'firma' => $firma,
                        'snapshot_dup' => $rowSnap, 'snapshot_orig' => $seenPhone[$pd]['snap']];
                }
                continue;
            }

            // DNC kontrola (in-memory)
            if (($icoN !== '' && isset($dncIco[$icoN])) ||
                ($pd   !== '' && isset($dncPhone[$pd])) ||
                ($emN  !== '' && isset($dncEmail[$emN]))) {
                $matchOn = '';
                if ($icoN !== '' && isset($dncIco[$icoN]))    { $matchOn = 'ico';     }
                elseif ($pd   !== '' && isset($dncPhone[$pd])) { $matchOn = 'telefon'; }
                elseif ($emN  !== '' && isset($dncEmail[$emN])) { $matchOn = 'email';   }
                $dncTotal++;
                if ($matchOn !== '' && isset($dncByMatch[$matchOn])) $dncByMatch[$matchOn]++;
                if (count($dnc) < self::MAX_DUPS_KEPT) {
                    $dnc[] = ['row' => $rowNum, 'match' => $matchOn,
                        'value' => $matchOn === 'ico' ? $icoN : ($matchOn === 'telefon' ? $pd : $emN),
                        'firma' => $firma, 'snapshot' => $rowSnap];
                }
                continue;
            }

            // Duplicita v DB
            $dbDupId   = null;
            $dbDupOn   = '';
            $dbDupVal  = '';
            if ($icoN !== '' && isset($dbIco[$icoN]))      { $dbDupId = $dbIco[$icoN];   $dbDupOn = 'ico';     $dbDupVal = $icoN; }
            elseif ($emN  !== '' && isset($dbEmail[$emN])) { $dbDupId = $dbEmail[$emN];  $dbDupOn = 'email';   $dbDupVal = $emN;  }
            elseif ($pd   !== '' && isset($dbPhone[$pd]))  { $dbDupId = $dbPhone[$pd];   $dbDupOn = 'telefon'; $dbDupVal = $pd;   }

            if ($dbDupId !== null) {
                // ── HARD SKIP pro chráněné kontakty ──
                // UZAVRENO / DNC / recent NEZAJEM se NIKDY nepřepíše importem,
                // bez ohledu na admin's volbu strategie. Bezpečnostní pojistka.
                if (isset($protectedDb[$dbDupId])) {
                    $protectedTotal++;
                    $reason = (string) ($protectedDb[$dbDupId]['reason'] ?? '—');
                    if (isset($protectedByReason[$reason])) $protectedByReason[$reason]++;
                    if (count($protectedSamples) < 50) {
                        $protectedSamples[] = [
                            'row'         => $rowNum,
                            'existing_id' => $dbDupId,
                            'match'       => $dbDupOn,
                            'firma'       => $firma,
                            'reason'      => $reason,
                        ];
                    }
                    $okRows++;
                    continue;
                }

                $duplicatesDbTotal++;
                if (isset($dupDbByMatch[$dbDupOn])) $dupDbByMatch[$dbDupOn]++;
                if (count($duplicatesDb) < self::MAX_DUPS_KEPT) {
                    // Načti snapshot existující DB karty pro side-by-side porovnání
                    $existing = $this->loadContactSnapshot($dbDupId);
                    $duplicatesDb[] = ['row' => $rowNum, 'existing_id' => $dbDupId,
                        'match' => $dbDupOn, 'value' => $dbDupVal,
                        'new_firma'      => $firma,
                        'snapshot_new'   => $rowSnap,
                        'snapshot_db'    => $existing];
                }
                $okRows++;
            } else {
                $okRows++;
            }

            // Označit jako viděné v souboru — uložit i snapshot pro side-by-side
            $seenEntry = ['row' => $rowNum, 'snap' => $rowSnap];
            if ($icoN !== '') $seenIco[$icoN]   = $seenEntry;
            if ($emN  !== '') $seenEmail[$emN]  = $seenEntry;
            if ($pd   !== '') $seenPhone[$pd]   = $seenEntry;
        }
        fclose($fh);

        return [
            'ok'                  => true,
            'total_rows'          => $totalRows,
            'ok_rows'             => $okRows,
            'header'              => array_values(array_filter(
                array_map('strval', is_array($headerRow) ? $headerRow : []),
                static fn ($s) => $s !== ''
            )),
            'errors'              => $errors,
            'duplicates_in_file'  => $duplicatesFile,
            'duplicates_in_db'    => $duplicatesDb,
            'dnc'                 => $dnc,
            // Chráněné kontakty (UZAVRENO / DNC / recent NEZAJEM) — hard-skip
            'protected_samples'   => $protectedSamples,
            'counts'              => [
                'errors'                       => count($errors),
                'duplicates_in_file'           => $duplicatesFileTotal,
                'duplicates_in_file_shown'     => count($duplicatesFile),
                'duplicates_in_file_by_match'  => $dupFileByMatch,
                'duplicates_in_db'             => $duplicatesDbTotal,
                'duplicates_in_db_shown'       => count($duplicatesDb),
                'duplicates_in_db_by_match'    => $dupDbByMatch,
                'dnc'                          => $dncTotal,
                'dnc_shown'                    => count($dnc),
                'dnc_by_match'                 => $dncByMatch,
                'protected_total'              => $protectedTotal,
                'protected_by_reason'          => $protectedByReason,
                'dups_truncated'               => ($duplicatesFileTotal > self::MAX_DUPS_KEPT)
                                                  || ($duplicatesDbTotal > self::MAX_DUPS_KEPT),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    //  Privátní: COMMIT (skutečný insert/update)
    // ─────────────────────────────────────────────────────────────────
    /**
     * @return array{total:int, imported:int, updated:int, skipped_dup_file:int,
     *               skipped_dnc:int, db_dup:int, errors:int}
     */
    /**
     * @param array<int,string> $rowOverrides     Override pro DB duplicity:
     *                                             rowNum → 'skip'/'update'/'add'.
     * @param array<int,string> $fileDupOverrides Override pro file duplicity:
     *                                             rowNum → 'add' (jinak default 'skip').
     */
    private function commitFile(
        string $csvPath, string $origName, string $defaultRegion,
        int $adminId, string $dupAction,
        array $rowOverrides = [], array $fileDupOverrides = []
    ): array {
        $fh = fopen($csvPath, 'rb');
        if ($fh === false) {
            throw new RuntimeException('CSV nelze otevřít.');
        }
        $delimiter = ';';
        $firstLine = fgets($fh);
        if ($firstLine !== false) {
            // CSV → autodetekce, XLSX-converted → vždy ; (ale bezpečné)
            $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
            rewind($fh);
        }
        // Smart header detection (stejná logika jako analyzeFile —
        // přeskočí úvodní titulky/prázdné řádky, najde řádek s "firma" / "kraj")
        $headerRow = null;
        $map       = [];
        for ($probe = 0; $probe < 5; $probe++) {
            $candidate = fgetcsv($fh, 0, $delimiter, '"', '\\');
            if ($candidate === false) break;
            if ($this->rowIsEmpty($candidate)) continue;
            $candidateMap = $this->buildHeaderMap($candidate);
            if (isset($candidateMap['firma']) || isset($candidateMap['nazev_firmy'])
                || isset($candidateMap['kraj']) || isset($candidateMap['region'])) {
                $headerRow = $candidate;
                $map       = $candidateMap;
                break;
            }
        }
        if ($headerRow === null) {
            fclose($fh);
            throw new RuntimeException('Hlavička v souboru nenalezena (commit).');
        }

        [$dncIco, $dncPhone, $dncEmail] = $this->loadDncHashes();
        [$dbIco, $dbPhone, $dbEmail]    = $this->loadExistingContactHashes();
        // Chráněné kontakty (UZAVRENO / DNC / recent NEZAJEM) — hard-skip
        // bez ohledu na admin's volbu strategie.
        $protectedDb     = $this->loadProtectedContacts();
        $usersByEmail    = $this->loadUsersByEmail();
        $callersByEmail  = $this->loadCallersByEmail();

        $stats = ['total' => 0, 'imported' => 0, 'updated' => 0,
                  'merged' => 0,
                  'skipped_dup_file' => 0, 'skipped_dnc' => 0,
                  'db_dup' => 0, 'errors' => 0];
        $seenIco = $seenEmail = $seenPhone = [];

        $insertBatch = []; // řádky k insertu
        $updateBatch = []; // [id, data] páry pro update
        $mergeQueue  = []; // [icoN, emN, pd, tel, targetId|null] — sloučení po hlavním importu

        // rowNum musí korelovat s preview — začíná na pozici hlavičky
        // (zde 1 = default; pro soubory s úvodními prázdnými řádky to může být víc).
        // Smart header detection výše už hlavičku přečetla, takže jsme za ní.
        $rowNum = 1; // přibližná hodnota; pro malou diskrepanci s preview tolerujeme

        $this->pdo->beginTransaction();

        while (($row = fgetcsv($fh, 0, $delimiter, '"', '\\')) !== false) {
            $rowNum++;
            if ($this->rowIsEmpty($row)) continue;
            if ($stats['total'] >= self::MAX_ROWS) break;
            $stats['total']++;

            $firma = trim($this->cell($row, $map, 'firma'));
            if ($firma === '') $firma = trim($this->cell($row, $map, 'nazev_firmy'));
            $ico   = $this->cell($row, $map, 'ico');
            $adresa = $this->cell($row, $map, 'adresa');
            $mesto  = $this->cell($row, $map, 'mesto');
            if ($adresa === '' && $mesto !== '') $adresa = $mesto;
            $tel       = $this->cell($row, $map, 'telefon');
            $email     = $this->cell($row, $map, 'email');
            $regionRaw = $this->cell($row, $map, 'region') ?: $this->cell($row, $map, 'kraj');
            $region    = crm_import_normalize_region($regionRaw);
            if ($region === '' || !in_array($region, crm_region_choices(), true)) {
                $fromCity = crm_import_city_to_region($mesto !== '' ? $mesto : $adresa);
                if ($fromCity !== '') $region = $fromCity;
            }
            if ($region === '' || !in_array($region, crm_region_choices(), true)) {
                if ($defaultRegion !== '' && in_array($defaultRegion, crm_region_choices(), true)) {
                    $region = $defaultRegion;
                }
            }
            $poz        = $this->cell($row, $map, 'poznamka');
            $operator   = $this->cell($row, $map, 'operator');
            $prilez     = $this->cell($row, $map, 'prilez');
            $narozeniny = $this->cell($row, $map, 'narozeniny_majitele');
            $vyrocni    = $this->cell($row, $map, 'vyrocni_smlouvy');
            $datumUzav  = $this->cell($row, $map, 'datum_uzavreni');
            $ozEmailRaw = $this->cell($row, $map, 'oz_email');
            $salePriceRaw = $this->cell($row, $map, 'sale_price');
            // Nové sloupce: stav, datum_volani, navolavacka_name (info do poznámky)
            $stavRaw      = $this->cell($row, $map, 'stav');
            $datumVolani  = $this->cell($row, $map, 'datum_volani');
            $navolavacka  = $this->cell($row, $map, 'navolavacka_name');
            // caller_email = email navolávačky → skutečné PŘIŘAZENÍ kontaktu
            $callerEmailRaw = $this->cell($row, $map, 'caller_email');
            // BMSL + cislo_smlouvy — info do oz_contact_workflow (smlouvy/rozjednané)
            $bmslRaw       = $this->cell($row, $map, 'bmsl');
            $cisloSmlouvy  = $this->cell($row, $map, 'cislo_smlouvy');

            if ($firma === '' || $region === '') {
                $stats['errors']++;
                continue;
            }

            // ── Validace stav + oz_email (stejná pravidla jako v analyzeFile) ──
            // Pojistka pro případ, že někdo upravil CSV mezi preview a commitem.
            $stavMapped = self::mapStavValue($stavRaw);
            if ($stavMapped === '__INVALID__') {
                $stats['errors']++;
                continue;
            }
            $ozEmailNorm = strtolower(trim($ozEmailRaw));
            $hasClosedDate = trim($datumUzav) !== '';
            $ozUserId = null;
            if ($ozEmailNorm !== '') {
                $ozUserId = $usersByEmail[$ozEmailNorm] ?? null;
                if ($ozUserId === null) {
                    $stats['errors']++;
                    continue;
                }
            }
            if ($hasClosedDate && $ozUserId === null) {
                $stats['errors']++;
                continue;
            }
            if ($stavMapped === 'FOR_SALES' && $ozUserId === null) {
                $stats['errors']++;
                continue;
            }
            // caller_email validace — pokud zadán, MUSÍ být navolávačka v users
            $callerEmailNorm = strtolower(trim($callerEmailRaw));
            $callerUserId = null;
            if ($callerEmailNorm !== '') {
                $callerUserId = $callersByEmail[$callerEmailNorm] ?? null;
                if ($callerUserId === null) {
                    $stats['errors']++;
                    continue;
                }
            }
            // Pokud řádek má datum_uzavreni, stav přepíše na DONE bez ohledu na CSV
            if ($hasClosedDate) $stavMapped = 'DONE';

            // ── Sestavit poznámku s prefixem [Navolávačka <Dne>] ──────────
            // Aby user viděl historii bez ztráty původní poznámky
            $datumVolaniParsed = $this->parseDate($datumVolani);
            $navolPrefix = '';
            if (trim($navolavacka) !== '' || $datumVolaniParsed !== null) {
                $navolPrefix = '[';
                if (trim($navolavacka) !== '') $navolPrefix .= trim($navolavacka);
                if ($datumVolaniParsed !== null) {
                    if (trim($navolavacka) !== '') $navolPrefix .= ' ';
                    // Convert YYYY-MM-DD → DD.MM.YYYY pro lidi
                    $ts = strtotime($datumVolaniParsed);
                    $navolPrefix .= $ts !== false ? date('j.n.Y', $ts) : $datumVolaniParsed;
                }
                $navolPrefix .= '] ';
            }
            $pozFinal = $navolPrefix . $poz;
            // Sale price: prefer numeric, akceptuj "14999" / "14 999" / "14999,50"
            $salePrice = null;
            if (trim($salePriceRaw) !== '') {
                $clean = preg_replace('/[^0-9.,\-]/', '', $salePriceRaw) ?? '';
                $clean = str_replace(',', '.', $clean);
                if ($clean !== '' && is_numeric($clean)) {
                    $salePrice = (float) $clean;
                }
            }
            // BMSL: pozitivní integer (0 = neuvedeno)
            $bmslInt = null;
            if (trim($bmslRaw) !== '') {
                $bmslClean = preg_replace('/\D+/', '', $bmslRaw) ?? '';
                if ($bmslClean !== '') $bmslInt = (int) $bmslClean;
            }
            $cisloSmlouvyClean = trim($cisloSmlouvy);
            if (mb_strlen($cisloSmlouvyClean) > 100) $cisloSmlouvyClean = mb_substr($cisloSmlouvyClean, 0, 100);

            $icoN = crm_import_normalize_ico($ico);
            $emN  = crm_import_normalize_email($email);
            $pd   = crm_import_phone_digits($tel);

            // Per-soubor dedupe — uživatel může pro konkrétní řádek vybrat:
            //   - 'add'   = přidat oba (přeskočit dedupe)
            //   - 'merge' = sloučit (zachovat 1×, přidat tel z druhého)
            //   - jinak (default) = přeskočit duplicitu
            $isFileDupHit = (($icoN !== '' && isset($seenIco[$icoN]))   ||
                             ($emN  !== '' && isset($seenEmail[$emN]))  ||
                             ($pd   !== '' && isset($seenPhone[$pd])));
            $fileAction = $fileDupOverrides[$rowNum] ?? 'skip';

            if ($isFileDupHit && $fileAction === 'merge') {
                // Zařadíme do merge queue — zpracuje se po hlavním importu
                $mergeQueue[] = [
                    'icoN' => $icoN, 'emN' => $emN, 'pd' => $pd,
                    'tel'  => $tel,  'em' => $email, 'targetId' => null, // najdeme později podle dedup keys
                    'rowNum' => $rowNum, 'source' => 'file',
                ];
                continue;
            }
            if ($isFileDupHit && $fileAction !== 'add') {
                $stats['skipped_dup_file']++;
                continue;
            }

            // DNC
            if (($icoN !== '' && isset($dncIco[$icoN])) ||
                ($pd   !== '' && isset($dncPhone[$pd])) ||
                ($emN  !== '' && isset($dncEmail[$emN]))) {
                $stats['skipped_dnc']++;
                continue;
            }

            if ($icoN !== '') $seenIco[$icoN]   = true;
            if ($emN  !== '') $seenEmail[$emN]  = true;
            if ($pd   !== '') $seenPhone[$pd]   = true;

            // DB duplicita?
            $dbId = null;
            if ($icoN !== '' && isset($dbIco[$icoN]))         $dbId = $dbIco[$icoN];
            elseif ($emN  !== '' && isset($dbEmail[$emN]))    $dbId = $dbEmail[$emN];
            elseif ($pd   !== '' && isset($dbPhone[$pd]))     $dbId = $dbPhone[$pd];

            // ── HARD SKIP pro chráněné kontakty ──
            // UZAVRENO / DNC / recent NEZAJEM se NIKDY nepřepíše importem,
            // bez ohledu na admin's volbu strategie. Bezpečnostní pojistka,
            // aby aktivní zákazník Honzy neskončil v navolávačce kvůli omylem
            // nahranému CSV.
            if ($dbId !== null && isset($protectedDb[$dbId])) {
                $stats['skipped_protected'] = ($stats['skipped_protected'] ?? 0) + 1;
                continue;
            }

            $rowData = [
                'ico'      => $icoN === '' ? null : $icoN,
                'firma'    => $firma,
                'adr'      => $adresa === '' ? null : $adresa,
                'tel'      => $tel === '' ? null : $tel,
                'em'       => $emN === '' ? null : $emN,
                'operator' => $operator === '' ? null : $operator,
                'prilez'   => $prilez === '' ? null : $prilez,
                'reg'      => $region,
                'poz'      => $pozFinal === '' ? null : $pozFinal,
                'naroz'         => $this->parseDate($narozeniny),
                'vyroci'        => $this->parseDate($vyrocni),
                'datum_uzavreni' => $this->parseDate($datumUzav),
                // Nová pole pro uzavřené smlouvy
                'oz_user_id' => $ozUserId,    // null pokud sloupec oz_email prázdný
                'sale_price' => $salePrice,   // null pokud sloupec sale_price prázdný
                // Nová pole pro provolané kontakty (NEZAJEM, FOR_SALES, atd.)
                'stav_mapped'  => $stavMapped,         // NEW / NEZAJEM / FOR_SALES / CALLBACK / …
                'datum_volani' => $datumVolaniParsed,  // null nebo Y-m-d
                // Přiřazení konkrétní navolávačky (pokud zadán caller_email)
                'caller_user_id' => $callerUserId,     // null pokud sloupec prázdný
                // BMSL + cislo_smlouvy — pro oz_contact_workflow při uzavřené smlouvě
                'bmsl'          => $bmslInt,           // int nebo null
                'cislo_smlouvy' => $cisloSmlouvyClean === '' ? null : $cisloSmlouvyClean,
            ];

            if ($dbId !== null) {
                $stats['db_dup']++;
                // Per-row override má prioritu nad globální volbou
                $thisAction = $rowOverrides[$rowNum] ?? $dupAction;
                if ($thisAction === 'skip') {
                    continue;
                }
                if ($thisAction === 'merge') {
                    // Zařadíme do merge queue (cíl známe — $dbId)
                    $mergeQueue[] = [
                        'icoN' => $icoN, 'emN' => $emN, 'pd' => $pd,
                        'tel'  => $tel,  'em' => $email, 'targetId' => $dbId,
                        'rowNum' => $rowNum, 'source' => 'db',
                    ];
                    continue;
                }
                if ($thisAction === 'update') {
                    $updateBatch[] = ['id' => $dbId, 'data' => $rowData];
                    if (count($updateBatch) >= self::BATCH_SIZE) {
                        $stats['updated'] += $this->flushUpdates($updateBatch, $adminId, $origName);
                        $updateBatch = [];
                    }
                    continue;
                }
                // 'add' = vždy nový — pokračuje do insert batch
            }

            $insertBatch[] = $rowData;
            if (count($insertBatch) >= self::BATCH_SIZE) {
                $stats['imported'] += $this->flushInserts($insertBatch, $adminId, $origName);
                $insertBatch = [];
            }
        }
        fclose($fh);

        if ($insertBatch !== []) $stats['imported'] += $this->flushInserts($insertBatch, $adminId, $origName);
        if ($updateBatch !== []) $stats['updated']  += $this->flushUpdates($updateBatch, $adminId, $origName);

        // ── Merge queue post-processing ────────────────────────────────
        // Po hlavním importu zpracujeme všechny řádky s akcí "merge".
        // Pro každý: najdeme cílový záznam (buď přímo přes targetId, nebo
        // přes dedup keys IČO/email/telefon) a přidáme jeho telefon
        // k existujícímu telefonu (s dedup přes pouze-čísla).
        if ($mergeQueue !== []) {
            foreach ($mergeQueue as $m) {
                $targetId = $m['targetId'] ?? null;
                if ($targetId === null || $targetId <= 0) {
                    // Najdi nejprve podle IČO, pak email, pak telefon
                    $targetId = $this->findExistingByDedupKeys(
                        $m['icoN'] ?: null,
                        $m['emN']  ?: null,
                        $m['pd']   ?: null
                    );
                }
                if ($targetId !== null && $targetId > 0) {
                    if ($this->mergeContactFields($targetId, $m['tel'] ?? null, $m['em'] ?? null)) {
                        $stats['merged']++;
                    }
                } else {
                    // Cíl nenalezen — pravděpodobně chyba (1. výskyt selhal při insertu)
                    error_log('[CRM Import] Merge target not found for row ' . $m['rowNum']);
                }
            }
        }

        $this->pdo->commit();
        return $stats;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Privátní: in-memory hashe DNC + existing contacts
    // ─────────────────────────────────────────────────────────────────
    /** @return array{0: array<string,bool>, 1: array<string,bool>, 2: array<string,bool>} */
    private function loadDncHashes(): array
    {
        $ico = $phone = $email = [];
        try {
            $st = $this->pdo->query('SELECT ico, telefon, email FROM dnc_list');
            if ($st) {
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $d) {
                    if (!empty($d['ico']))     $ico[crm_import_normalize_ico((string)$d['ico'])] = true;
                    if (!empty($d['telefon'])) $phone[crm_import_phone_digits((string)$d['telefon'])] = true;
                    if (!empty($d['email']))   $email[crm_import_normalize_email((string)$d['email'])] = true;
                }
            }
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }
        return [$ico, $phone, $email];
    }

    /** @return array{0: array<string,int>, 1: array<string,int>, 2: array<string,int>} */
    private function loadExistingContactHashes(): array
    {
        // Vrátí mapy hash -> contacts.id pro rychlý lookup
        $ico = $phone = $email = [];
        try {
            $st = $this->pdo->query('SELECT id, ico, telefon, email FROM contacts');
            if ($st) {
                while ($d = $st->fetch(PDO::FETCH_ASSOC)) {
                    $id = (int) $d['id'];
                    if (!empty($d['ico'])) {
                        $h = crm_import_normalize_ico((string)$d['ico']);
                        if ($h !== '') $ico[$h] = $id;
                    }
                    if (!empty($d['telefon'])) {
                        $h = crm_import_phone_digits((string)$d['telefon']);
                        if ($h !== '') $phone[$h] = $id;
                    }
                    if (!empty($d['email'])) {
                        $h = crm_import_normalize_email((string)$d['email']);
                        if ($h !== '') $email[$h] = $id;
                    }
                }
            }
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }
        return [$ico, $phone, $email];
    }

    /**
     * Vrátí mapu contact_id => protected_info pro VŠECHNY kontakty, které jsou
     * chráněné (UZAVRENO / DNC / recent NEZAJEM). Použito pro hard-skip
     * při importu — bez ohledu na admin's volbu duplicit.
     *
     * Returns: [contact_id => ['reason' => string]]
     */
    private function loadProtectedContacts(): array
    {
        $protected = [];
        try {
            $sql = "SELECT c.id, c.stav, c.dnc_flag, c.stav_changed_at, c.updated_at,
                           (SELECT w.stav FROM oz_contact_workflow w
                              WHERE w.contact_id = c.id
                              ORDER BY w.updated_at DESC LIMIT 1) AS wf_stav
                    FROM contacts c
                    WHERE c.stav IN ('DONE','UZAVRENO','NEZAJEM','CALLED_BAD')
                       OR c.dnc_flag = 1
                       OR EXISTS (SELECT 1 FROM oz_contact_workflow w2
                                  WHERE w2.contact_id = c.id AND w2.stav = 'UZAVRENO')";
            $st = $this->pdo->query($sql);
            if ($st) {
                while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                    [$isProtected, $reason] = crm_import_is_protected_contact($row);
                    if ($isProtected) {
                        $protected[(int) $row['id']] = ['reason' => $reason];
                    }
                }
            }
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }
        return $protected;
    }

    /**
     * Mapuje hodnotu sloupce `stav` z importu na interní DB stav.
     *
     * Akceptované varianty (case-insensitive, s diakritikou i bez):
     *   • prázdné / "new" / "nový" / "nove"            → NEW (default — jde do čističky)
     *   • "nechce" / "nezájem" / "nedovolal" /
     *     "nebere" / "típl to" / "nedovolano" /
     *     "called_bad"                                 → NEZAJEM (per žádost zákazníka — zjednodušení)
     *   • "chce" / "for_sales" / "pro_oz" /
     *     "rozjednané"                                 → FOR_SALES (objeví se OZ-ovi v panelu)
     *   • "callback" / "volat zpět"                    → CALLBACK
     *   • "called_ok" / "ok" / "obvoláno"              → CALLED_OK
     *   • "chybný" / "spatny_kontakt"                  → CHYBNY_KONTAKT
     *   • "izolace"                                    → IZOLACE
     *   • Direct DB stavy (NEW/NEZAJEM/READY/DONE/UZAVRENO/FOR_SALES…) → ponechá jak je
     *
     * Pro neznámou hodnotu vrátí '__INVALID__' — caller pak zaznamená chybu v preview.
     */
    private static function mapStavValue(string $raw): string
    {
        $rawTrim = trim($raw);
        if ($rawTrim === '') return 'NEW';

        // Přímá shoda na DB enum (uppercase, např. "FOR_SALES")
        $upper = strtoupper($rawTrim);
        static $validInternal = [
            // contacts.stav
            'NEW', 'NEZAJEM', 'NEDOVOLANO', 'CALLED_OK', 'CALLED_BAD',
            'CALLBACK', 'CHYBNY_KONTAKT', 'FOR_SALES', 'VF_SKIP',
            'READY', 'DONE', 'UZAVRENO', 'IZOLACE',
            // oz_contact_workflow.stav — když importuješ rozjednané leady
            'NOVE', 'ZPRACOVAVA', 'NABIDKA', 'SCHUZKA', 'SANCE',
            'BO_PREDANO', 'BO_VPRACI', 'BO_VRACENO', 'SMLOUVA', 'REKLAMACE',
        ];
        if (in_array($upper, $validInternal, true)) return $upper;

        // Lower-case + remove diacritics + collapse whitespace
        $s = mb_strtolower($rawTrim, 'UTF-8');
        $from = ['á','č','ď','é','ě','í','ň','ó','ř','š','ť','ú','ů','ý','ž'];
        $to   = ['a','c','d','e','e','i','n','o','r','s','t','u','u','y','z'];
        $s = str_replace($from, $to, $s);
        $s = (string) preg_replace('/\s+/', ' ', $s);

        static $map = [
            // → NEW
            'new' => 'NEW', 'nove' => 'NEW', 'novy' => 'NEW',
            // → NEZAJEM (per zákaznické pravidlo: nedovolal/nebere/típl_to/nezajem všechno do nezájmu)
            'nechce'      => 'NEZAJEM', 'nechci'      => 'NEZAJEM',
            'nezajem'     => 'NEZAJEM',
            'nedovolal'   => 'NEZAJEM', 'nedovolala'  => 'NEZAJEM',
            'nedovolano'  => 'NEZAJEM',
            'nebere'      => 'NEZAJEM',
            'tipl to'     => 'NEZAJEM', 'tiplto'      => 'NEZAJEM',
            'typl to'     => 'NEZAJEM', 'typlto'      => 'NEZAJEM',
            'called_bad'  => 'NEZAJEM',
            // → FOR_SALES
            'chce'        => 'FOR_SALES',
            'pro oz'      => 'FOR_SALES', 'pro_oz'     => 'FOR_SALES',
            'rozjednany'  => 'FOR_SALES', 'rozjednana' => 'FOR_SALES',
            'rozjednane'  => 'FOR_SALES',
            // → CALLED_OK (úspěšně obvoláno bez předání OZ)
            'called_ok'   => 'CALLED_OK',
            'ok'          => 'CALLED_OK',
            'obvolano'    => 'CALLED_OK',
            // → CALLBACK
            'callback'    => 'CALLBACK',
            'volat zpet'  => 'CALLBACK',
            // → CHYBNY_KONTAKT
            'chybny'           => 'CHYBNY_KONTAKT',
            'chybny_kontakt'   => 'CHYBNY_KONTAKT',
            'spatny'           => 'CHYBNY_KONTAKT',
            'spatny_kontakt'   => 'CHYBNY_KONTAKT',
            // → IZOLACE
            'izolace' => 'IZOLACE',
            // → VF_SKIP
            'vf_skip' => 'VF_SKIP', 'vf'   => 'VF_SKIP',
            // → DONE / UZAVRENO
            'uzavreno' => 'DONE', 'done' => 'DONE',
            // → workflow stavy (oz_contact_workflow.stav) — rozjednané leady u OZ
            'nove'       => 'NOVE',
            'zpracovava' => 'ZPRACOVAVA',
            'zpracovavá' => 'ZPRACOVAVA',
            'nabidka'    => 'NABIDKA',
            'nabidka odeslana' => 'NABIDKA',
            'schuzka'    => 'SCHUZKA',
            'sance'      => 'SANCE',
            'bo predano' => 'BO_PREDANO',
            'bo_predano' => 'BO_PREDANO',
            'predano bo' => 'BO_PREDANO',
            'bo vpraci'  => 'BO_VPRACI',
            'bo_vpraci'  => 'BO_VPRACI',
            'bo vraceno' => 'BO_VRACENO',
            'bo_vraceno' => 'BO_VRACENO',
            'smlouva'    => 'SMLOUVA',
            'reklamace'  => 'REKLAMACE',
        ];

        if (isset($map[$s])) return $map[$s];

        return '__INVALID__';
    }

    /**
     * Načte všechny aktivní uživatele do mapy email → id.
     * Slouží k validaci sloupce `oz_email` v importu uzavřených smluv.
     *
     * @return array<string,int>  ['lowercase@email' => user_id]
     */
    /**
     * Vrátí mapu email → user_id JEN pro uživatele s rolí obchodak
     * (primární `role='obchodak'` NEBO `obchodak` v `roles_extra`).
     *
     * Vč. neaktivních (= legacy import smluv od bývalých OZ).
     * Filtrace na obchodak zabraňuje omylem přiřadit lead navolávačce jako OZ.
     */
    private function loadUsersByEmail(): array
    {
        $map = [];
        try {
            $st = $this->pdo->query(
                "SELECT id, email FROM users
                 WHERE email IS NOT NULL AND email <> ''
                   AND (role = 'obchodak'
                        OR JSON_CONTAINS(IFNULL(roles_extra, '[]'), '\"obchodak\"'))"
            );
            if ($st) {
                while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                    $em = strtolower(trim((string) $r['email']));
                    if ($em !== '') $map[$em] = (int) $r['id'];
                }
            }
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }
        return $map;
    }

    /**
     * Vrátí mapu email → user_id JEN pro navolávačky
     * (primární `role='navolavacka'` NEBO `navolavacka` v `roles_extra`).
     *
     * Vč. neaktivních (= legacy). Filtrace zabraňuje omylem přiřadit kontakt
     * uživateli, který není navolávačka.
     */
    private function loadCallersByEmail(): array
    {
        $map = [];
        try {
            $st = $this->pdo->query(
                "SELECT id, email FROM users
                 WHERE email IS NOT NULL AND email <> ''
                   AND (role = 'navolavacka'
                        OR JSON_CONTAINS(IFNULL(roles_extra, '[]'), '\"navolavacka\"'))"
            );
            if ($st) {
                while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                    $em = strtolower(trim((string) $r['email']));
                    if ($em !== '') $map[$em] = (int) $r['id'];
                }
            }
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }
        return $map;
    }

    /** @param list<array<string,mixed>> $batch */
    private function flushInserts(array $batch, int $adminId, string $origName): int
    {
        if ($batch === []) return 0;
        $placeholders = []; $values = [];

        // Připrav data: pokud řádek má datum_uzavreni → DONE; jinak použij stav_mapped (NEW default)
        //  - dopočítej vyrocni_smlouvy = datum + 3 roky (pokud uzavřená smlouva a vyrocni není v CSV)
        //  - assigned_sales_id z oz_email (pro DONE i FOR_SALES)
        //  - rejection_reason='nezajem' pro NEZAJEM (audit trail)
        //  - datum_volani z `Dne` v CSV (historický záznam, nezapočítá se do aktuálního měsíce)
        foreach ($batch as $i => $r) {
            $p = $i * 17; // 17 placeholderů per row (přibyl rejection_reason + datum_volani)

            // Stav: priorita:
            //   1) datum_uzavreni → DONE (= uzavřená smlouva)
            //   2) workflow stage / CALLBACK / FOR_SALES + má OZ → CALLED_OK
            //      (= "OZ ho má vidět v pracovní ploše" — filter vyžaduje CALLED_OK)
            //   3) workflow stage bez OZ → FOR_SALES (legacy fallback)
            //   4) NEW (default), nebo specific contact-level stav (NEZAJEM, atd.)
            $stavRaw = (string) ($r['stav_mapped'] ?? 'NEW');
            $workflowStages = ['NOVE','ZPRACOVAVA','NABIDKA','SCHUZKA','SANCE',
                               'BO_PREDANO','BO_VPRACI','BO_VRACENO','SMLOUVA','REKLAMACE'];
            // Pro účely "kontakt je rozjednaný" počítáme i CALLBACK a FOR_SALES
            $activeStates = array_merge($workflowStages, ['CALLBACK', 'FOR_SALES']);
            $hasOz     = !empty($r['oz_user_id']);
            $hasCaller = !empty($r['caller_user_id']);

            // Pokud admin přiřadil konkrétní navolávačku (caller_email) a stav je
            // default NEW (= nezpracovaný), promote na ASSIGNED. Caller view filtruje
            // svoji listu na READY+ASSIGNED — bez ASSIGNED by ji Evička neviděla.
            if ($hasCaller && $stavRaw === 'NEW') {
                $stavRaw = 'ASSIGNED';
            }

            if (!empty($r['datum_uzavreni'])) {
                $stav = 'DONE';
            } elseif (in_array($stavRaw, $activeStates, true) && $hasOz) {
                // Rozjednaný kontakt s přiřazeným OZ — kontakt MUSÍ mít CALLED_OK
                // aby ho OZ viděl ve své pracovní ploše. Toto je stejný auto-promote
                // pattern jako v datagrid editaci (Fix #43).
                $stav = 'CALLED_OK';
            } elseif (in_array($stavRaw, $workflowStages, true)) {
                // Workflow stage ale bez OZ → fallback FOR_SALES (legacy)
                $stav = 'FOR_SALES';
            } else {
                $stav = $stavRaw;
            }

            $vyroci = $r['vyroci'];
            if (!empty($r['datum_uzavreni']) && $vyroci === null) {
                $ts = strtotime((string) $r['datum_uzavreni'] . ' +3 years');
                if ($ts !== false) $vyroci = date('Y-m-d', $ts);
            }

            // rejection_reason: 'nezajem' pro NEZAJEM stav (jinak NULL)
            $rejReason = ($stav === 'NEZAJEM') ? 'nezajem' : null;

            $placeholders[] = "(:ico{$p},:firma{$p},:adr{$p},:tel{$p},:em{$p},:op{$p},:prilez{$p},:reg{$p},:stav{$p},:poz{$p},:rej{$p},:naroz{$p},:vyroci{$p},:asid{$p},:acid{$p},:sp{$p},:actd{$p},:dvol{$p},:st{$p},0,NOW(3),NOW(3))";
            $values["ico{$p}"]    = $r['ico'];
            $values["firma{$p}"]  = $r['firma'];
            $values["adr{$p}"]    = $r['adr'];
            $values["tel{$p}"]    = $r['tel'];
            $values["em{$p}"]     = $r['em'];
            $values["op{$p}"]     = $r['operator'];
            $values["prilez{$p}"] = $r['prilez'];
            $values["reg{$p}"]    = $r['reg'];
            $values["stav{$p}"]   = $stav;
            $values["poz{$p}"]    = $r['poz'];
            $values["rej{$p}"]    = $rejReason;
            $values["naroz{$p}"]  = $r['naroz'];
            $values["vyroci{$p}"] = $vyroci;
            $values["asid{$p}"]   = $r['oz_user_id'] ?? null;     // assigned_sales_id
            $values["acid{$p}"]   = $r['caller_user_id'] ?? null; // assigned_caller_id
            $values["sp{$p}"]     = $r['sale_price'] ?? null;
            $values["actd{$p}"]   = !empty($r['datum_uzavreni']) ? $r['datum_uzavreni'] : null;
            // datum_volani — historický záznam kdy navolávačka volala (z `Dne` sloupce)
            $values["dvol{$p}"]   = !empty($r['datum_volani'])
                ? ((string) $r['datum_volani']) . ' 00:00:00'
                : null;
            // subject_type — auto-detekce z názvu (firma vs OSVČ).
            // Mix backfilluje 'unknown' kdykoli, ale když to nastavíme rovnou tady,
            // máme správný typ od první vteřiny po importu (žádné 'unknown' mezi
            // importem a mixem).
            $values["st{$p}"]     = function_exists('crm_detect_subject_type')
                ? crm_detect_subject_type((string) $r['firma'])
                : 'unknown';
        }
        $sql = 'INSERT INTO contacts (ico, firma, adresa, telefon, email, operator, prilez, region, stav, poznamka, rejection_reason, narozeniny_majitele, vyrocni_smlouvy, assigned_sales_id, assigned_caller_id, sale_price, activation_date, datum_volani, subject_type, dnc_flag, created_at, updated_at) VALUES '
            . implode(',', $placeholders);
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($values);
            $inserted = $stmt->rowCount();
        } catch (\Throwable $e) {
            error_log('[CRM Import] flushInserts: ' . $e->getMessage());
            return 0;
        }

        if ($inserted > 0) {
            // POZOR: PDO::lastInsertId() po multi-row INSERTu vrací ID PRVNÍHO
            // vloženého řádku (MariaDB/MySQL specifikace), ne posledního.
            // Předchozí kód předpokládal opak → workflow_log + oz_contact_workflow
            // se zakládaly pro neexistující contact_id (BUG: Ester pak nic neviděla).
            $firstId = (int) $this->pdo->lastInsertId();
            $lastId  = $firstId + $inserted - 1;
            $wfPlaceholders = [];
            for ($id = $firstId; $id <= $lastId; $id++) {
                $wfPlaceholders[] = "({$id},{$adminId},NULL,'NEW',:wfnote,NOW(3))";
            }
            $note = 'Import: ' . mb_substr($origName, 0, 200);
            try {
                $this->pdo->prepare(
                    'INSERT INTO workflow_log (contact_id, user_id, old_status, new_status, note, created_at) VALUES '
                    . implode(',', $wfPlaceholders)
                )->execute(['wfnote' => $note]);
            } catch (\PDOException $e) {
                crm_db_log_error($e, __METHOD__);
            }

            // ── BONUS: Pro řádky s datum_uzavreni vytvořit i řádek v oz_contact_workflow ──
            // Tím se kontakt rovnou objeví v BO/UZAVRENO tabu a v dashboardu výročí.
            //
            // Také: pokud stav je workflow stage (ZPRACOVAVA, SCHUZKA, NABIDKA, SANCE,
            // BO_PREDANO, SMLOUVA atd.), vytvoříme workflow řádek s tímto stavem
            // + contacts.stav se nastaví na FOR_SALES.
            $closedRows = [];
            $activeRows = [];
            // Workflow stavy = stage v oz_contact_workflow (= rozjednané kontakty)
            $workflowStages = ['NOVE','ZPRACOVAVA','NABIDKA','SCHUZKA','SANCE',
                               'CALLBACK','BO_PREDANO','BO_VPRACI','BO_VRACENO','SMLOUVA','FOR_SALES'];
            foreach ($batch as $i => $r) {
                $stavMapped = (string) ($r['stav_mapped'] ?? 'NEW');
                if (!empty($r['datum_uzavreni'])) {
                    $closedRows[] = [
                        'id'             => $firstId + $i,
                        'datum_uzavreni' => (string) $r['datum_uzavreni'],
                        'oz_user_id'     => (int) ($r['oz_user_id'] ?? $adminId),
                        // Volitelné — z importu (mohou být NULL)
                        'bmsl'           => $r['bmsl']          ?? null,
                        'cislo_smlouvy'  => $r['cislo_smlouvy'] ?? null,
                    ];
                } elseif (in_array($stavMapped, $workflowStages, true) && !empty($r['oz_user_id'])) {
                    // Rozjednaný kontakt — vytvoříme workflow s konkrétním stavem
                    $activeRows[] = [
                        'id'           => $firstId + $i,
                        'oz_user_id'   => (int) $r['oz_user_id'],
                        'wf_stav'      => $stavMapped, // bulkInsertActiveWorkflow ho použije
                        'started_at'   => !empty($r['datum_volani']) ? ((string) $r['datum_volani']) . ' 00:00:00' : null,
                        // Volitelné — pokud OZ už zná BMSL nebo má číslo objednávky
                        'bmsl'          => $r['bmsl']          ?? null,
                        'cislo_smlouvy' => $r['cislo_smlouvy'] ?? null,
                    ];
                }
            }
            if ($closedRows !== []) {
                $this->bulkInsertClosedWorkflow($closedRows, $adminId);
            }
            if ($activeRows !== []) {
                $this->bulkInsertActiveWorkflow($activeRows, $adminId);
            }
        }
        return $inserted;
    }

    /**
     * Bulk INSERT do oz_contact_workflow pro FOR_SALES rozpracované kontakty.
     * Vznikne řádek se `stav='FOR_SALES'` a OZ-ovo `oz_id` — kontakt se objeví
     * v jeho panelu „Aktivní/Přijaté" a může s ním pracovat (SCHUZKA, NABIDKA…).
     *
     * @param list<array{id:int,oz_user_id:int,started_at:?string}> $rows
     */
    private function bulkInsertActiveWorkflow(array $rows, int $adminId): void
    {
        // Schema `oz_contact_workflow.stav_changed_at` je v migraci 017.
        // Workflow stav přebíráme PER ROW z importu — pokud admin specifikoval
        // konkrétní stav (ZPRACOVAVA, SCHUZKA, NABIDKA, ...), použijeme ten.
        // Default FOR_SALES (legacy CHCE).

        $placeholders = []; $values = [];
        foreach ($rows as $i => $r) {
            $p = $i;
            $startedSql = $r['started_at'] !== null ? ":sa{$p}" : 'NOW(3)';
            $changedSql = $r['started_at'] !== null ? ":sb{$p}" : 'NOW(3)';
            $updatedSql = $r['started_at'] !== null ? ":sc{$p}" : 'NOW(3)';

            $placeholders[] = "(:cid{$p},:uid{$p},:stav{$p},{$startedSql},{$changedSql},{$updatedSql},:bmsl{$p},:cis{$p})";
            $values["cid{$p}"]  = $r['id'];
            $values["uid{$p}"]  = $r['oz_user_id'];
            // Pokud admin specifikoval workflow stav (NOVE/ZPRACOVAVA/...), použij ho
            // Jinak default FOR_SALES
            $wfStav = (string) ($r['wf_stav'] ?? 'FOR_SALES');
            $validWf = ['NOVE','ZPRACOVAVA','NABIDKA','SCHUZKA','SANCE','CALLBACK',
                        'BO_PREDANO','BO_VPRACI','BO_VRACENO','SMLOUVA','FOR_SALES'];
            if (!in_array($wfStav, $validWf, true)) $wfStav = 'FOR_SALES';
            $values["stav{$p}"] = $wfStav;
            if ($r['started_at'] !== null) {
                $values["sa{$p}"] = $r['started_at'];
                $values["sb{$p}"] = $r['started_at'];
                $values["sc{$p}"] = $r['started_at'];
            }
            // BMSL + číslo objednávky (volitelné z importu)
            $values["bmsl{$p}"] = isset($r['bmsl']) && $r['bmsl'] !== null ? (int) $r['bmsl'] : null;
            $values["cis{$p}"]  = $r['cislo_smlouvy'] ?? null;
        }
        $sql = "INSERT INTO oz_contact_workflow
                  (contact_id, oz_id, stav, started_at, stav_changed_at, updated_at, bmsl, cislo_smlouvy)
                VALUES " . implode(',', $placeholders);
        try {
            $this->pdo->prepare($sql)->execute($values);
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }
    }

    /**
     * Bulk INSERT do oz_contact_workflow pro legacy uzavřené smlouvy.
     * Auto-set: stav=UZAVRENO, podpis_potvrzen=1, datum_uzavreni z CSV, trvani=3.
     *
     * `oz_user_id` v každém řádku = skutečný OZ co smlouvu uzavřel
     * (z oz_email sloupce). Pokud chybí, fallback na adminId.
     *
     * @param list<array{id:int,datum_uzavreni:string,oz_user_id:int}> $rows
     */
    private function bulkInsertClosedWorkflow(array $rows, int $adminId): void
    {
        // Schema sloupců `oz_contact_workflow` (cislo_smlouvy, datum_uzavreni,
        // smlouva_trvani_roky, stav_changed_at, closed_at, podpis_potvrzen*)
        // je teď v migraci 017 (žádný runtime ALTER).

        $placeholders = []; $values = [];
        foreach ($rows as $i => $r) {
            $p = $i;
            // oz_user_id = skutečný OZ co smlouvu uzavřel (z oz_email v CSV).
            // Fallback na adminId jen pokud něco prošlo bez oz_email (nemělo by se stát,
            // analyzeFile + commitFile to validuje, ale pojistka pro DB integritu).
            $ozId = (int) ($r['oz_user_id'] ?? $adminId);
            // BMSL + cislo_smlouvy z importu (volitelné)
            $bmslVal = isset($r['bmsl']) && $r['bmsl'] !== null ? (int) $r['bmsl'] : null;
            $cisloVal = isset($r['cislo_smlouvy']) ? $r['cislo_smlouvy'] : null;
            $placeholders[] = "(:cid{$p},:uid{$p},'UZAVRENO',:du{$p},3,1,:dudt{$p},:uid2{$p},:dudt2{$p},:dudt3{$p},:dudt4{$p},NOW(3),:bmsl{$p},:cis{$p})";
            $values["cid{$p}"]   = $r['id'];
            $values["uid{$p}"]   = $ozId;       // OZ co smlouvu uzavřel
            $values["uid2{$p}"]  = $ozId;       // podpis_potvrzen_by — také OZ
            $values["du{$p}"]    = $r['datum_uzavreni'];
            // Pro DATETIME potřebujeme datum + 00:00:00 čas
            $values["dudt{$p}"]  = $r['datum_uzavreni'] . ' 00:00:00';
            $values["dudt2{$p}"] = $r['datum_uzavreni'] . ' 00:00:00';
            $values["dudt3{$p}"] = $r['datum_uzavreni'] . ' 00:00:00';
            $values["dudt4{$p}"] = $r['datum_uzavreni'] . ' 00:00:00';
            $values["bmsl{$p}"]  = $bmslVal;
            $values["cis{$p}"]   = $cisloVal;
        }
        $sql = "INSERT INTO oz_contact_workflow
                  (contact_id, oz_id, stav,
                   datum_uzavreni, smlouva_trvani_roky,
                   podpis_potvrzen, podpis_potvrzen_at, podpis_potvrzen_by,
                   started_at, closed_at, stav_changed_at, updated_at,
                   bmsl, cislo_smlouvy)
                VALUES " . implode(',', $placeholders);
        try {
            $this->pdo->prepare($sql)->execute($values);
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }
    }

    /**
     * @param list<array{id:int, data:array<string,mixed>}> $batch
     *
     * SMART UPDATE — delší vyhrává.
     *
     * Místo COALESCE(:new, existing) — což přepíše vše neprázdné — používáme
     * helper `crm_import_smart_field()` který v PHP porovná existující a nové
     * pole a vybere "lepší":
     *   - prázdné → vyhraje neprázdné
     *   - oba vyplněné a stejný text → zachováno existující (žádná změna)
     *   - oba vyplněné a různé → vyhraje DELŠÍ (víc info)
     *
     * Pro telefony používáme `crm_import_merge_phone()` který spojí dva různé
     * telefony čárkou.
     *
     * Výhoda: existující data se neztratí, když import obsahuje zkrácenou
     * verzi (např. "J. Novák" se nepřepíše na "Jan Novák").
     */
    private function flushUpdates(array $batch, int $adminId, string $origName): int
    {
        if ($batch === []) return 0;

        // Načti existující pole pro každé contact_id v batch — potřebujeme
        // je pro chytré porovnání před UPDATEm.
        $ids = array_map(static fn($b) => (int) $b['id'], $batch);
        $idsPh = implode(',', array_fill(0, count($ids), '?'));
        $existing = [];
        try {
            $est = $this->pdo->prepare(
                "SELECT id, firma, ico, adresa, telefon, email, operator, prilez,
                        poznamka, narozeniny_majitele, vyrocni_smlouvy
                 FROM contacts WHERE id IN ({$idsPh})"
            );
            $est->execute($ids);
            foreach ($est->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $existing[(int) $row['id']] = $row;
            }
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__ . '/load');
        }

        $sql = 'UPDATE contacts SET
                  firma                = :firma,
                  ico                  = :ico,
                  adresa               = :adr,
                  telefon              = :tel,
                  email                = :em,
                  operator             = :op,
                  prilez               = :prilez,
                  region               = :reg,
                  poznamka             = :poz,
                  narozeniny_majitele  = :naroz,
                  vyrocni_smlouvy      = :vyroci,
                  updated_at           = NOW(3)
                WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $updated = 0;
        $note = 'Import update (smart merge): ' . mb_substr($origName, 0, 200);
        $wfStmt = $this->pdo->prepare(
            "INSERT INTO workflow_log (contact_id, user_id, old_status, new_status, note, created_at)
             VALUES (:cid, :uid, NULL, 'UPDATED_BY_IMPORT', :note, NOW(3))"
        );
        foreach ($batch as $b) {
            $r  = $b['data'];
            $ex = $existing[$b['id']] ?? [];
            try {
                $stmt->execute([
                    // firma: pokud nová delší/lepší → použít, jinak zachovat starou
                    'firma'  => crm_import_smart_field((string) ($ex['firma'] ?? ''), (string) $r['firma']) ?: $r['firma'],
                    // ico: pokud nový vyplněný → použij; jinak starý
                    'ico'    => crm_import_smart_field((string) ($ex['ico'] ?? ''), (string) ($r['ico'] ?? '')),
                    'adr'    => crm_import_smart_field((string) ($ex['adresa'] ?? ''), (string) ($r['adr'] ?? '')),
                    // telefon: merge (dva různé telefony → "777111, 602222")
                    'tel'    => crm_import_merge_phone((string) ($ex['telefon'] ?? ''), (string) ($r['tel'] ?? '')),
                    'em'     => crm_import_smart_field((string) ($ex['email'] ?? ''), (string) ($r['em'] ?? '')),
                    'op'     => crm_import_smart_field((string) ($ex['operator'] ?? ''), (string) ($r['operator'] ?? '')),
                    'prilez' => crm_import_smart_field((string) ($ex['prilez'] ?? ''), (string) ($r['prilez'] ?? '')),
                    'reg'    => $r['reg'],
                    'poz'    => crm_import_smart_field((string) ($ex['poznamka'] ?? ''), (string) ($r['poz'] ?? '')),
                    'naroz'  => $r['naroz'] !== null ? $r['naroz'] : ($ex['narozeniny_majitele'] ?? null),
                    'vyroci' => $r['vyroci'] !== null ? $r['vyroci'] : ($ex['vyrocni_smlouvy'] ?? null),
                    'id'     => $b['id'],
                ]);
                if ($stmt->rowCount() > 0) {
                    $updated++;
                    try {
                        $wfStmt->execute(['cid' => $b['id'], 'uid' => $adminId, 'note' => $note]);
                    } catch (\PDOException $e) {
                        crm_db_log_error($e, __METHOD__ . '/wf');
                    }
                }
            } catch (\PDOException $e) {
                crm_db_log_error($e, __METHOD__);
            }
        }
        return $updated;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Privátní: utility
    // ─────────────────────────────────────────────────────────────────
    private function loadPreview(string $importId, int $adminId): ?array
    {
        if (!preg_match('/^imp_[a-f0-9]{16}$/', $importId)) {
            return null;
        }
        $previewPath = CRM_STORAGE_PATH . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR
                     . $importId . DIRECTORY_SEPARATOR . 'preview.json';
        if (!is_file($previewPath)) {
            return null;
        }
        $raw = @file_get_contents($previewPath);
        if ($raw === false || $raw === '') return null;
        $data = json_decode($raw, true);
        if (!is_array($data)) return null;
        // Bezpečnostní kontrola: preview patří aktuálnímu adminovi
        if ((int) ($data['admin_id'] ?? 0) !== $adminId) {
            return null;
        }
        return $data;
    }

    private function cleanupImport(string $importDir): void
    {
        if (!is_dir($importDir)) return;
        $files = glob($importDir . DIRECTORY_SEPARATOR . '*');
        if (is_array($files)) {
            foreach ($files as $f) {
                if (is_file($f)) @unlink($f);
            }
        }
        @rmdir($importDir);
    }

    /** @param list<string|null>|list<string> $headerRow */
    /** @return array<string, int> */
    private function buildHeaderMap(array $headerRow): array
    {
        static $aliases = [
            'ico' => 'ico', 'ic' => 'ico', 'ic_' => 'ico', 'i_c_' => 'ico', 'i_c_o_' => 'ico',
            'nazev_firmy' => 'nazev_firmy', 'nazev firmy' => 'nazev_firmy', 'firma' => 'firma',
            'mobil' => 'telefon', 'mobile' => 'telefon', 'tel' => 'telefon', 'tel_' => 'telefon',
            'telefonni_cislo' => 'telefon', 'telefonni cislo' => 'telefon', 'phone' => 'telefon',
            'e_mail' => 'email', 'mail' => 'email', 'email' => 'email',
            'mesto' => 'mesto', 'ulice' => 'adresa', 'adresa' => 'adresa',
            // 'okres' — primárně se použije pro auto-detekci kraje (jako mesto),
            // sekundárně jako součást adresy (pokud sloupec adresa neexistuje).
            'okres' => 'mesto', 'okresy' => 'mesto', 'district' => 'mesto',
            'kraj' => 'kraj', 'region' => 'region',
            'poznamka' => 'poznamka', 'poznamky' => 'poznamka', 'note' => 'poznamka',
            'operator' => 'operator', 'operator_site' => 'operator', 'operator site' => 'operator',
            'sit' => 'operator', 'sit_' => 'operator', 'carrier' => 'operator',
            'prilezitost' => 'prilez', 'prilez' => 'prilez', 'prilezitosti' => 'prilez',
            'opportunity' => 'prilez', 'produkt' => 'prilez',
            // OZ identifikace pro uzavřené smlouvy — email obchodáka/prodejce
            'oz_email' => 'oz_email', 'oz' => 'oz_email',
            'obchodak_email' => 'oz_email', 'obchodak' => 'oz_email',
            'prodejce_email' => 'oz_email', 'prodejce' => 'oz_email',
            'sales_email' => 'oz_email', 'sales' => 'oz_email',
            // Cena smlouvy
            'sale_price' => 'sale_price', 'cena' => 'sale_price',
            'cena_smlouvy' => 'sale_price', 'cena smlouvy' => 'sale_price',
            'price' => 'sale_price', 'castka' => 'sale_price',
            // BMSL — Báze Měsíčních Smluvních Linek (objem smlouvy v jednotkách)
            'bmsl' => 'bmsl', 'b_m_s_l' => 'bmsl', 'pocet_linek' => 'bmsl',
            'pocet_smluv' => 'bmsl', 'units' => 'bmsl',
            // Číslo objednávky / smlouvy (= referenční číslo z OT)
            'cislo_smlouvy'    => 'cislo_smlouvy',
            'cislo_objednavky' => 'cislo_smlouvy',
            'cislo_objednavk'  => 'cislo_smlouvy',
            'cislo_ot'         => 'cislo_smlouvy',
            'cislo'            => 'cislo_smlouvy',
            'contract_number'  => 'cislo_smlouvy',
            'order_number'     => 'cislo_smlouvy',
            // Anglické / alternativní názvy pro existující sloupce
            'mobile' => 'telefon',
            'subject_name' => 'firma', 'subject' => 'firma',
            'jmeno' => 'firma', 'name' => 'firma',
            'municipality' => 'mesto', 'obec' => 'mesto',
            // Stav kontaktu (Ne/Chce, Status, atd.)
            'stav' => 'stav', 'status' => 'stav', 'stav_kontaktu' => 'stav',
            'ne_chce' => 'stav', 'nechce' => 'stav', 'chce' => 'stav',
            'vysledek' => 'stav', 'result' => 'stav',
            // Datum kdy se naposledy volalo
            'dne' => 'datum_volani', 'datum_volani' => 'datum_volani',
            'datum_telefonatu' => 'datum_volani', 'volano_dne' => 'datum_volani',
            'date_called' => 'datum_volani',
            // Kdo volal — pouze info do poznámky (jméno, ne email)
            'navolavacka' => 'navolavacka_name', 'caller_name' => 'navolavacka_name',
            'volala' => 'navolavacka_name', 'volal' => 'navolavacka_name',
            // Kdo volal — email navolávačky pro skutečné PŘIŘAZENÍ (assigned_caller_id)
            'caller_email'      => 'caller_email',
            'navolavacka_email' => 'caller_email',
            'volajici_email'    => 'caller_email',
            'volajici'          => 'caller_email',
        ];

        $map = [];
        foreach ($headerRow as $i => $name) {
            if (!is_string($name)) continue;
            if ($i === 0) {
                $name = preg_replace('/^\xEF\xBB\xBF/', '', $name) ?? $name;
            }
            $norm = $this->normalizeHeader($name);
            if ($norm === '') continue;
            $canonical = $aliases[$norm] ?? $norm;
            if (!isset($map[$canonical])) $map[$canonical] = (int) $i;
            if (!isset($map[$norm]))      $map[$norm]      = (int) $i;
        }
        return $map;
    }

    private function normalizeHeader(string $h): string
    {
        $h = mb_strtolower(trim($h), 'UTF-8');
        $from = ['á','č','ď','é','ě','í','ň','ó','ř','š','ť','ú','ů','ý','ž',
                 'à','â','ä','è','ê','ë','î','ï','ô','ù','û','ü','ÿ','æ','œ','ç'];
        $to   = ['a','c','d','e','e','i','n','o','r','s','t','u','u','y','z',
                 'a','a','a','e','e','e','i','i','o','u','u','u','y','ae','oe','c'];
        $h = str_replace($from, $to, $h);
        $h = (string) preg_replace('/[\s.\-\/\\\\]+/', '_', $h);
        $h = (string) preg_replace('/[^a-z0-9_]/', '', $h);
        return trim($h, '_');
    }

    /** @param array<string, int> $map */
    private function cell(array $row, array $map, string $key): string
    {
        if (!isset($map[$key])) return '';
        $i = $map[$key];
        $v = $row[$i] ?? '';
        if ($v === null) return '';
        return is_string($v) ? trim($v) : trim((string) $v);
    }

    private function parseDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') return null;
        if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $raw)) {
            $d = \DateTimeImmutable::createFromFormat('Y-m-d', $raw);
            return $d ? $d->format('Y-m-d') : null;
        }
        if (preg_match('/^\d{1,2}\.\d{1,2}\.\d{4}$/', $raw)) {
            $d = \DateTimeImmutable::createFromFormat('d.m.Y', $raw);
            return $d ? $d->format('Y-m-d') : null;
        }
        // Excel serial date (číslo dní od 1900-01-01) — pokud z XLSX přijde číslo
        if (preg_match('/^\d{4,6}(\.\d+)?$/', $raw)) {
            $serial = (int) floor((float) $raw);
            if ($serial > 60 && $serial < 80_000) {
                // Excel epoch = 1899-12-30 (kompenzace bug se 1900 leap year)
                try {
                    $d = (new \DateTimeImmutable('1899-12-30'))->modify('+' . $serial . ' days');
                    return $d->format('Y-m-d');
                } catch (\Throwable) {}
            }
        }
        return null;
    }

    /** @param list<string|null>|list<string> $row */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $c) {
            if (is_string($c) && trim($c) !== '') return false;
            if ($c !== null && (string) $c !== '') return false;
        }
        return true;
    }

    /**
     * Snapshot řádku — co parser viděl v klíčových sloupcích.
     * Vrátí asociativní pole pro rychlé zobrazení v preview.
     *
     * @param array<int|string,mixed> $row
     * @param array<string,int>       $map
     * @return array<string,string>
     */
    private function rowSnapshot(array $row, array $map): array
    {
        return [
            'firma'   => (string) $this->cell($row, $map, 'firma') ?: $this->cell($row, $map, 'nazev_firmy'),
            'ico'     => $this->cell($row, $map, 'ico'),
            'tel'     => $this->cell($row, $map, 'telefon'),
            'email'   => $this->cell($row, $map, 'email'),
            'kraj'    => $this->cell($row, $map, 'kraj') ?: $this->cell($row, $map, 'region'),
            'adresa'  => $this->cell($row, $map, 'adresa') ?: $this->cell($row, $map, 'mesto'),
        ];
    }

    /** Maximální počet telefonů / emailů, které se po merge zachovají. */
    private const MERGE_MAX_PHONES = 6;
    private const MERGE_MAX_EMAILS = 6;

    /**
     * Sloučí dva telefonní řetězce přes "; ". Deduplikuje podle pouze-čísel,
     * takže "605 580 813" a "605580813" se nepřidá dvakrát.
     * Pokud by po sloučení bylo víc než MERGE_MAX_PHONES, nový telefon
     * se zahodí a zaloguje (overflow je signalizován voláním funkce).
     */
    private static function mergePhones(?string $existing, ?string $incoming): ?string
    {
        $existing = trim((string) $existing);
        $incoming = trim((string) $incoming);
        if ($incoming === '') return $existing === '' ? null : $existing;
        if ($existing === '') return $incoming;

        // Rozsekej existující na jednotlivé telefony (oddělovač ; nebo ,)
        $parts = preg_split('/\s*[;,]\s*/', $existing) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));

        // Normalize for dedup: jen číslice
        $existingDigits = array_map(fn ($p) => preg_replace('/\D+/', '', $p), $parts);
        $incomingDigits = preg_replace('/\D+/', '', $incoming);

        if ($incomingDigits !== '' && in_array($incomingDigits, $existingDigits, true)) {
            return $existing; // už je tam
        }

        // Limit: max MERGE_MAX_PHONES — pokud už je 6, neuložíme 7. číslo
        if (count($parts) >= self::MERGE_MAX_PHONES) {
            error_log('[CRM Import] Merge phone dropped — limit ' . self::MERGE_MAX_PHONES . ' reached: incoming="' . $incoming . '"');
            return $existing;
        }
        return $existing . '; ' . $incoming;
    }

    /**
     * Sloučí dva email řetězce přes "; ". Deduplikuje podle lower-case
     * (case-insensitive). Limit MERGE_MAX_EMAILS jako u telefonů.
     */
    private static function mergeEmails(?string $existing, ?string $incoming): ?string
    {
        $existing = trim((string) $existing);
        $incoming = trim((string) $incoming);
        if ($incoming === '') return $existing === '' ? null : $existing;
        if ($existing === '') return strtolower($incoming);

        $parts = preg_split('/\s*[;,]\s*/', $existing) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));

        // Dedup case-insensitive
        $existingLower = array_map('strtolower', $parts);
        $incomingLower = strtolower($incoming);

        if (in_array($incomingLower, $existingLower, true)) {
            return $existing; // už je tam
        }

        if (count($parts) >= self::MERGE_MAX_EMAILS) {
            error_log('[CRM Import] Merge email dropped — limit ' . self::MERGE_MAX_EMAILS . ' reached: incoming="' . $incoming . '"');
            return $existing;
        }
        return $existing . '; ' . $incomingLower;
    }

    /**
     * SELECT existující kontakt → merge phone + email do něj → UPDATE.
     * Vrací true pokud byl záznam aktualizován (alespoň jedno pole změněno).
     *
     * Pro merge platí:
     *   • telefon — sloučení přes "; ", dedup digits-only, max 6
     *   • email   — sloučení přes "; ", dedup case-insensitive, max 6
     *   • ostatní pole (firma, ico, adresa, poznamka, …) se NEMĚNÍ
     */
    private function mergeContactFields(int $contactId, ?string $newPhone, ?string $newEmail): bool
    {
        $hasPhone = $newPhone !== null && trim($newPhone) !== '';
        $hasEmail = $newEmail !== null && trim($newEmail) !== '';
        if (!$hasPhone && !$hasEmail) return false;

        try {
            $st = $this->pdo->prepare('SELECT telefon, email FROM contacts WHERE id = :id LIMIT 1');
            $st->execute(['id' => $contactId]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: ['telefon' => '', 'email' => ''];

            $existingPhone = (string) ($row['telefon'] ?? '');
            $existingEmail = (string) ($row['email']   ?? '');

            $mergedPhone = $hasPhone ? self::mergePhones($existingPhone, $newPhone) : $existingPhone;
            $mergedEmail = $hasEmail ? self::mergeEmails($existingEmail, $newEmail) : $existingEmail;

            $phoneChanged = $hasPhone && $mergedPhone !== $existingPhone;
            $emailChanged = $hasEmail && $mergedEmail !== $existingEmail;
            if (!$phoneChanged && !$emailChanged) return false; // nic nového

            $upd = $this->pdo->prepare(
                'UPDATE contacts SET telefon = :tel, email = :em, updated_at = NOW(3) WHERE id = :id'
            );
            $upd->execute([
                'tel' => $mergedPhone,
                'em'  => $mergedEmail,
                'id'  => $contactId,
            ]);
            return $upd->rowCount() > 0;
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
            return false;
        }
    }

    /**
     * Najde existující kontakt podle IČO/email/telefonu (v DB nebo právě vloženého).
     * Vrátí ID nebo null.
     */
    private function findExistingByDedupKeys(?string $icoN, ?string $emN, ?string $pd): ?int
    {
        try {
            if ($icoN !== '' && $icoN !== null) {
                $st = $this->pdo->prepare(
                    'SELECT id FROM contacts WHERE ico IS NOT NULL AND TRIM(ico) = :v LIMIT 1'
                );
                $st->execute(['v' => $icoN]);
                $id = (int) ($st->fetchColumn() ?: 0);
                if ($id > 0) return $id;
            }
            if ($emN !== '' && $emN !== null) {
                $st = $this->pdo->prepare(
                    'SELECT id FROM contacts WHERE email IS NOT NULL AND LOWER(TRIM(email)) = :v LIMIT 1'
                );
                $st->execute(['v' => $emN]);
                $id = (int) ($st->fetchColumn() ?: 0);
                if ($id > 0) return $id;
            }
            if ($pd !== '' && $pd !== null) {
                $st = $this->pdo->prepare(
                    "SELECT id FROM contacts WHERE telefon IS NOT NULL
                     AND REGEXP_REPLACE(telefon, '[^0-9]+', '') = :v LIMIT 1"
                );
                $st->execute(['v' => $pd]);
                $id = (int) ($st->fetchColumn() ?: 0);
                if ($id > 0) return $id;
            }
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }
        return null;
    }

    /**
     * Načte snapshot existujícího kontaktu z DB (pro side-by-side porovnání v preview).
     *
     * @return array<string,string>
     */
    private function loadContactSnapshot(int $contactId): array
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT firma, ico, telefon, email, region, adresa
                 FROM contacts WHERE id = :id LIMIT 1'
            );
            $st->execute(['id' => $contactId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                return [
                    'firma'  => (string) ($row['firma']   ?? ''),
                    'ico'    => (string) ($row['ico']     ?? ''),
                    'tel'    => (string) ($row['telefon'] ?? ''),
                    'email'  => (string) ($row['email']   ?? ''),
                    'kraj'   => (string) ($row['region']  ?? ''),
                    'adresa' => (string) ($row['adresa']  ?? ''),
                ];
            }
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }
        return ['firma' => '', 'ico' => '', 'tel' => '', 'email' => '', 'kraj' => '', 'adresa' => ''];
    }
}
