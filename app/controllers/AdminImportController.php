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

        crm_flash_set(sprintf(
            '✓ Import dokončen: vloženo %d, aktualizováno %d, sloučeno %d, přeskočeno (DB-dup) %d, přeskočeno (DNC) %d, chyby %d.',
            $stats['imported'],
            $stats['updated'],
            $stats['merged'] ?? 0,
            $dupAction === 'skip' ? $stats['db_dup'] : 0,
            $stats['skipped_dnc'],
            $stats['errors']
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
        // Mapa email → user_id pro validaci oz_email v uzavřených smlouvách
        $usersByEmail = $this->loadUsersByEmail();

        $errors          = [];
        $duplicatesFile  = [];
        $duplicatesDb    = [];
        $dnc             = [];
        // Total counters — neztratíme reálný počet i když pole capnem na MAX_DUPS_KEPT (sample)
        $duplicatesFileTotal = 0;
        $duplicatesDbTotal   = 0;
        $dncTotal            = 0;

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

            // ── Validace oz_email pro uzavřené smlouvy ────────────────────
            // Pravidlo:
            //   • Pokud řádek má `datum_uzavreni` → oz_email JE POVINNÝ
            //     (uzavřená smlouva musí mít zaznamenaného OZ co ji uzavřel)
            //   • Pokud řádek má oz_email → musí existovat v `users.email`
            //     (jinak chyba — ať to user opraví v Excelu)
            //   • Pokud řádek nemá ani datum_uzavreni ani oz_email → OK,
            //     půjde standardním pipeline pro nový lead
            $ozEmailRaw  = $this->cell($row, $map, 'oz_email');
            $ozEmailNorm = strtolower(trim($ozEmailRaw));
            $datumUzavRaw = $this->cell($row, $map, 'datum_uzavreni');
            $hasClosedDate = trim($datumUzavRaw) !== '';

            if ($hasClosedDate && $ozEmailNorm === '') {
                if (count($errors) < self::MAX_ERRORS_KEPT) {
                    $errors[] = ['row' => $rowNum, 'col' => 'oz_email', 'value' => '',
                        'reason'    => 'Uzavřená smlouva (vyplněné datum_uzavreni) musí mít sloupec oz_email s emailem obchodníka.',
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
        $usersByEmail = $this->loadUsersByEmail();

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

            if ($firma === '' || $region === '') {
                $stats['errors']++;
                continue;
            }

            // ── oz_email validace (stejné pravidlo jako v analyzeFile) ──
            // Pokud preview neselhal, sem se dostanou jen platné řádky.
            // Přesto v commit fázi pravidlo zopakujeme — bezpečnostní pojistka
            // pro případ, že někdo upravil CSV mezi preview a commitem.
            $ozEmailNorm = strtolower(trim($ozEmailRaw));
            $hasClosedDate = trim($datumUzav) !== '';
            $ozUserId = null;
            if ($ozEmailNorm !== '') {
                $ozUserId = $usersByEmail[$ozEmailNorm] ?? null;
                if ($ozUserId === null) {
                    // OZ email neexistuje — řádek odmítneme (stejná logika jako preview)
                    $stats['errors']++;
                    continue;
                }
            }
            if ($hasClosedDate && $ozUserId === null) {
                // Uzavřená smlouva bez OZ — chyba
                $stats['errors']++;
                continue;
            }
            // Sale price: prefer numeric, akceptuj "14999" / "14 999" / "14999,50"
            $salePrice = null;
            if (trim($salePriceRaw) !== '') {
                $clean = preg_replace('/[^0-9.,\-]/', '', $salePriceRaw) ?? '';
                $clean = str_replace(',', '.', $clean);
                if ($clean !== '' && is_numeric($clean)) {
                    $salePrice = (float) $clean;
                }
            }

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

            $rowData = [
                'ico'      => $icoN === '' ? null : $icoN,
                'firma'    => $firma,
                'adr'      => $adresa === '' ? null : $adresa,
                'tel'      => $tel === '' ? null : $tel,
                'em'       => $emN === '' ? null : $emN,
                'operator' => $operator === '' ? null : $operator,
                'prilez'   => $prilez === '' ? null : $prilez,
                'reg'      => $region,
                'poz'      => $poz === '' ? null : $poz,
                'naroz'         => $this->parseDate($narozeniny),
                'vyroci'        => $this->parseDate($vyrocni),
                'datum_uzavreni' => $this->parseDate($datumUzav),
                // Nová pole pro uzavřené smlouvy
                'oz_user_id' => $ozUserId,    // null pokud sloupec oz_email prázdný
                'sale_price' => $salePrice,   // null pokud sloupec sale_price prázdný
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
     * Načte všechny aktivní uživatele do mapy email → id.
     * Slouží k validaci sloupce `oz_email` v importu uzavřených smluv.
     *
     * @return array<string,int>  ['lowercase@email' => user_id]
     */
    private function loadUsersByEmail(): array
    {
        $map = [];
        try {
            // Včetně neaktivních — admin může chtít naimportovat smlouvy uzavřené
            // OZ-em který už ve firmě nepracuje (historická data).
            $st = $this->pdo->query('SELECT id, email FROM users WHERE email IS NOT NULL AND email <> ""');
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

        // Připrav data: pokud řádek má datum_uzavreni, automaticky:
        //  - dopočítej vyrocni_smlouvy = datum + 3 roky (pokud není explicitně v CSV)
        //  - nastav contacts.stav = 'DONE' (legacy stav pro uzavřené)
        //  - nastav assigned_sales_id na OZ co smlouvu uzavřel (z oz_email)
        foreach ($batch as $i => $r) {
            $p = $i * 14; // 14 placeholderů per row (přibyl assigned_sales_id, sale_price)

            $stav = 'NEW';
            $vyroci = $r['vyroci'];
            if (!empty($r['datum_uzavreni'])) {
                $stav = 'DONE';
                if ($vyroci === null) {
                    // Auto-vypočet: datum_uzavreni + 3 roky (default trvání smlouvy)
                    $ts = strtotime((string) $r['datum_uzavreni'] . ' +3 years');
                    if ($ts !== false) $vyroci = date('Y-m-d', $ts);
                }
            }

            $placeholders[] = "(:ico{$p},:firma{$p},:adr{$p},:tel{$p},:em{$p},:op{$p},:prilez{$p},:reg{$p},:stav{$p},:poz{$p},:naroz{$p},:vyroci{$p},:asid{$p},:sp{$p},:actd{$p},0,NOW(3),NOW(3))";
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
            $values["naroz{$p}"]  = $r['naroz'];
            $values["vyroci{$p}"] = $vyroci;
            // Nová pole pro uzavřené smlouvy
            $values["asid{$p}"]   = $r['oz_user_id'] ?? null;       // assigned_sales_id
            $values["sp{$p}"]     = $r['sale_price'] ?? null;       // cena smlouvy
            // activation_date = stejné datum jako datum_uzavreni (pokud uzavřená smlouva)
            $values["actd{$p}"]   = !empty($r['datum_uzavreni']) ? $r['datum_uzavreni'] : null;
        }
        $sql = 'INSERT INTO contacts (ico, firma, adresa, telefon, email, operator, prilez, region, stav, poznamka, narozeniny_majitele, vyrocni_smlouvy, assigned_sales_id, sale_price, activation_date, dnc_flag, created_at, updated_at) VALUES '
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
            $lastId  = (int) $this->pdo->lastInsertId();
            $firstId = $lastId - $inserted + 1;
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
            // oz_user_id přebíráme přímo z $r — to je skutečný OZ co smlouvu uzavřel
            // (z oz_email sloupce v importu). Pokud chybí, fallback na admina.
            $closedRows = [];
            foreach ($batch as $i => $r) {
                if (!empty($r['datum_uzavreni'])) {
                    $closedRows[] = [
                        'id'             => $firstId + $i,
                        'datum_uzavreni' => (string) $r['datum_uzavreni'],
                        'oz_user_id'     => (int) ($r['oz_user_id'] ?? $adminId),
                    ];
                }
            }
            if ($closedRows !== []) {
                $this->bulkInsertClosedWorkflow($closedRows, $adminId);
            }
        }
        return $inserted;
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
        // Nejdřív zaručit, že nové sloupce existují (legacy DB instance)
        try { $this->pdo->exec('ALTER TABLE `oz_contact_workflow` ADD COLUMN `cislo_smlouvy` VARCHAR(50) NULL DEFAULT NULL'); } catch (\PDOException) {}
        try { $this->pdo->exec('ALTER TABLE `oz_contact_workflow` ADD COLUMN `datum_uzavreni` DATE NULL DEFAULT NULL'); } catch (\PDOException) {}
        try { $this->pdo->exec('ALTER TABLE `oz_contact_workflow` ADD COLUMN `smlouva_trvani_roky` TINYINT UNSIGNED NULL DEFAULT 3'); } catch (\PDOException) {}
        try { $this->pdo->exec('ALTER TABLE `oz_contact_workflow` ADD COLUMN `stav_changed_at` DATETIME(3) NULL DEFAULT NULL'); } catch (\PDOException) {}
        try { $this->pdo->exec('ALTER TABLE `oz_contact_workflow` ADD COLUMN `closed_at` DATETIME(3) NULL DEFAULT NULL'); } catch (\PDOException) {}
        try { $this->pdo->exec('ALTER TABLE `oz_contact_workflow` ADD COLUMN `podpis_potvrzen` TINYINT(1) NOT NULL DEFAULT 0'); } catch (\PDOException) {}
        try { $this->pdo->exec('ALTER TABLE `oz_contact_workflow` ADD COLUMN `podpis_potvrzen_at` DATETIME(3) NULL DEFAULT NULL'); } catch (\PDOException) {}
        try { $this->pdo->exec('ALTER TABLE `oz_contact_workflow` ADD COLUMN `podpis_potvrzen_by` INT UNSIGNED NULL DEFAULT NULL'); } catch (\PDOException) {}

        $placeholders = []; $values = [];
        foreach ($rows as $i => $r) {
            $p = $i;
            // oz_user_id = skutečný OZ co smlouvu uzavřel (z oz_email v CSV).
            // Fallback na adminId jen pokud něco prošlo bez oz_email (nemělo by se stát,
            // analyzeFile + commitFile to validuje, ale pojistka pro DB integritu).
            $ozId = (int) ($r['oz_user_id'] ?? $adminId);
            $placeholders[] = "(:cid{$p},:uid{$p},'UZAVRENO',:du{$p},3,1,:dudt{$p},:uid2{$p},:dudt2{$p},:dudt3{$p},:dudt4{$p},NOW(3))";
            $values["cid{$p}"]   = $r['id'];
            $values["uid{$p}"]   = $ozId;       // OZ co smlouvu uzavřel
            $values["uid2{$p}"]  = $ozId;       // podpis_potvrzen_by — také OZ
            $values["du{$p}"]    = $r['datum_uzavreni'];
            // Pro DATETIME potřebujeme datum + 00:00:00 čas
            $values["dudt{$p}"]  = $r['datum_uzavreni'] . ' 00:00:00';
            $values["dudt2{$p}"] = $r['datum_uzavreni'] . ' 00:00:00';
            $values["dudt3{$p}"] = $r['datum_uzavreni'] . ' 00:00:00';
            $values["dudt4{$p}"] = $r['datum_uzavreni'] . ' 00:00:00';
        }
        $sql = "INSERT INTO oz_contact_workflow
                  (contact_id, oz_id, stav,
                   datum_uzavreni, smlouva_trvani_roky,
                   podpis_potvrzen, podpis_potvrzen_at, podpis_potvrzen_by,
                   started_at, closed_at, stav_changed_at, updated_at)
                VALUES " . implode(',', $placeholders);
        try {
            $this->pdo->prepare($sql)->execute($values);
        } catch (\PDOException $e) {
            crm_db_log_error($e, __METHOD__);
        }
    }

    /** @param list<array{id:int, data:array<string,mixed>}> $batch */
    private function flushUpdates(array $batch, int $adminId, string $origName): int
    {
        if ($batch === []) return 0;
        // Bezpečný UPDATE per-row v jedné transakci.
        // (Bulk UPDATE přes CASE WHEN by byl rychlejší, ale složitější — pro 300k rows
        //  se reálně updatuje jen pár tisíc; per-row je dostatečně rychlé.)
        $sql = 'UPDATE contacts SET
                  firma                = :firma,
                  ico                  = COALESCE(:ico, ico),
                  adresa               = COALESCE(:adr, adresa),
                  telefon              = COALESCE(:tel, telefon),
                  email                = COALESCE(:em, email),
                  operator             = COALESCE(:op, operator),
                  prilez               = COALESCE(:prilez, prilez),
                  region               = :reg,
                  poznamka             = COALESCE(:poz, poznamka),
                  narozeniny_majitele  = COALESCE(:naroz, narozeniny_majitele),
                  vyrocni_smlouvy      = COALESCE(:vyroci, vyrocni_smlouvy),
                  updated_at           = NOW(3)
                WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $updated = 0;
        $note = 'Import update: ' . mb_substr($origName, 0, 200);
        $wfStmt = $this->pdo->prepare(
            "INSERT INTO workflow_log (contact_id, user_id, old_status, new_status, note, created_at)
             VALUES (:cid, :uid, NULL, 'UPDATED_BY_IMPORT', :note, NOW(3))"
        );
        foreach ($batch as $b) {
            $r = $b['data'];
            try {
                $stmt->execute([
                    'firma'  => $r['firma'],
                    'ico'    => $r['ico'],
                    'adr'    => $r['adr'],
                    'tel'    => $r['tel'],
                    'em'     => $r['em'],
                    'op'     => $r['operator'],
                    'prilez' => $r['prilez'],
                    'reg'    => $r['reg'],
                    'poz'    => $r['poz'],
                    'naroz'  => $r['naroz'],
                    'vyroci' => $r['vyroci'],
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
            'mesto' => 'mesto', 'ulice' => 'adresa', 'adresa' => 'adresa', 'okres' => 'adresa',
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
