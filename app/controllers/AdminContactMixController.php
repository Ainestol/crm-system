<?php
// e:\Snecinatripu\app\controllers\AdminContactMixController.php
declare(strict_types=1);

/**
 * AdminContactMixController
 *
 * Mix kontaktů 1 firma : 10 OSVČ — psychologicky příjemnější fronta pro navolávačku
 * (firmy se těžko obvolávají, OSVČ jsou rychlejší).
 *
 * Routes:
 *   GET  /admin/contacts/mix         → náhled + statistiky + tlačítko
 *   POST /admin/contacts/mix/execute → spustí mix (append na konec existující fronty)
 */
final class AdminContactMixController
{
    /** Fallback cyklus, pokud nejsou settings v DB. Settings se ale insertují migrací. */
    private const RATIO_FIRMA = 1;
    private const RATIO_OSVC  = 9;

    public function __construct(private PDO $pdo)
    {
    }

    /** Vrátí aktuální ratio z app_settings (s fallback default). */
    public static function getRatio(): array
    {
        return [
            'firma' => max(1, crm_setting_get_int('mix_ratio_firma', self::RATIO_FIRMA)),
            'osvc'  => max(1, crm_setting_get_int('mix_ratio_osvc',  self::RATIO_OSVC)),
        ];
    }

    /**
     * Statická "engine" funkce pro mixování. Volaná z UI i z auto-mix po importu.
     *
     * @return array{mixed:int, firma:int, osvc:int, backfilled:int, next_seq:int}
     */
    public static function runMix(PDO $pdo, ?int $ratioFirma = null, ?int $ratioOsvc = null): array
    {
        $r = self::getRatio();
        $ratioFirma = $ratioFirma ?? $r['firma'];
        $ratioOsvc  = $ratioOsvc  ?? $r['osvc'];

        // 1) Backfill subject_type
        $bfStmt = $pdo->prepare(
            "SELECT id, firma FROM contacts
             WHERE stav = 'NEW' AND queue_mix_seq IS NULL AND subject_type = 'unknown'"
        );
        $bfStmt->execute();
        $upd = $pdo->prepare("UPDATE contacts SET subject_type = :t WHERE id = :id");
        $backfilled = 0;
        foreach ($bfStmt->fetchAll(PDO::FETCH_ASSOC) as $r2) {
            $type = crm_detect_subject_type((string) ($r2['firma'] ?? ''));
            $upd->execute(['t' => $type, 'id' => (int) $r2['id']]);
            $backfilled++;
        }

        // 2) Načti unmixed
        $loadStmt = $pdo->prepare(
            "SELECT id FROM contacts
             WHERE stav = 'NEW' AND queue_mix_seq IS NULL AND subject_type = :t
             ORDER BY id ASC"
        );
        $loadStmt->execute(['t' => 'firma']);
        $firmaIds = array_map('intval', $loadStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        $loadStmt->execute(['t' => 'osvc']);
        $osvcIds = array_map('intval', $loadStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

        // 3) Append start
        $nextSeq = (int) $pdo->query(
            "SELECT COALESCE(MAX(queue_mix_seq), 0) + 1 FROM contacts"
        )->fetchColumn();

        // 4) Interleave (OSVČ first, firma na konec cyklu)
        $mixed = [];
        $iF = 0; $iO = 0;
        while ($iF < count($firmaIds) || $iO < count($osvcIds)) {
            for ($k = 0; $k < $ratioOsvc && $iO < count($osvcIds); $k++, $iO++) {
                $mixed[] = $osvcIds[$iO];
            }
            for ($k = 0; $k < $ratioFirma && $iF < count($firmaIds); $k++, $iF++) {
                $mixed[] = $firmaIds[$iF];
            }
            if ($iF >= count($firmaIds) && $iO >= count($osvcIds)) break;
        }

        // 5) UPDATE seq
        $assignedCount = 0;
        if ($mixed !== []) {
            $pdo->beginTransaction();
            try {
                $seqUpd = $pdo->prepare("UPDATE contacts SET queue_mix_seq = :seq WHERE id = :id");
                foreach ($mixed as $cid) {
                    $seqUpd->execute(['seq' => $nextSeq, 'id' => $cid]);
                    $nextSeq++;
                    $assignedCount++;
                }
                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
        }

        return [
            'mixed'      => $assignedCount,
            'firma'      => count($firmaIds),
            'osvc'       => count($osvcIds),
            'backfilled' => $backfilled,
            'next_seq'   => $nextSeq,
        ];
    }

    /** GET /admin/contacts/mix */
    public function getIndex(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        // ── Statistiky ──
        $stats = [
            'total_new'         => 0,  // všechen NEW
            'unmixed_firma'     => 0,  // NEW + subject_type=firma + queue_mix_seq IS NULL
            'unmixed_osvc'      => 0,
            'unmixed_unknown'   => 0,
            'mixed_total'       => 0,  // contacts s queue_mix_seq IS NOT NULL
            'last_seq'          => 0,
        ];

        try {
            // Per subject_type rozpad pro NEW nezamíchaných
            $row = $this->pdo->query(
                "SELECT
                    SUM(CASE WHEN stav = 'NEW' THEN 1 ELSE 0 END) AS total_new,
                    SUM(CASE WHEN stav = 'NEW' AND queue_mix_seq IS NULL AND subject_type = 'firma'   THEN 1 ELSE 0 END) AS unmixed_firma,
                    SUM(CASE WHEN stav = 'NEW' AND queue_mix_seq IS NULL AND subject_type = 'osvc'    THEN 1 ELSE 0 END) AS unmixed_osvc,
                    SUM(CASE WHEN stav = 'NEW' AND queue_mix_seq IS NULL AND subject_type = 'unknown' THEN 1 ELSE 0 END) AS unmixed_unknown,
                    SUM(CASE WHEN queue_mix_seq IS NOT NULL THEN 1 ELSE 0 END) AS mixed_total,
                    COALESCE(MAX(queue_mix_seq), 0) AS last_seq
                 FROM contacts"
            )->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                foreach ($stats as $k => $_) {
                    $stats[$k] = (int) ($row[$k] ?? 0);
                }
            }
        } catch (\PDOException $e) {
            if (function_exists('crm_db_log_error')) crm_db_log_error($e, __METHOD__);
        }

        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();
        $title = '🎲 Mix kontaktů';
        // Live values from settings (admin si mohl změnit defaults)
        $r = self::getRatio();
        $ratioFirma = $r['firma'];
        $ratioOsvc  = $r['osvc'];
        $autoMixEnabled = crm_setting_get_bool('mix_auto_after_import', true);
        ob_start();
        require dirname(__DIR__) . '/views/admin/contacts/mix.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    /** POST /admin/contacts/mix/execute — manuální mix s aktuálně uloženým ratio */
    public function postExecute(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/contacts/mix');
        }

        try {
            // Aktuální saved ratio (admin si ho mohl změnit přes settings form)
            $r = self::getRatio();
            $res = self::runMix($this->pdo, $r['firma'], $r['osvc']);

            if ($res['mixed'] === 0) {
                crm_flash_set('Žádné nezamíchané NEW kontakty. Možná musíš nejdřív importovat?');
            } else {
                $msg = '✓ Namícháno: ' . $res['mixed'] . ' kontaktů ('
                    . $res['firma'] . '× firma, ' . $res['osvc'] . '× OSVČ) cyklem '
                    . $r['osvc'] . '× OSVČ + ' . $r['firma'] . '× firma.';
                if ($res['backfilled'] > 0) {
                    $msg .= ' Auto-detekováno typ pro ' . $res['backfilled'] . ' kontaktů.';
                }
                crm_flash_set($msg);
            }
        } catch (\Throwable $e) {
            if (function_exists('crm_db_log_error')) crm_db_log_error($e, __METHOD__);
            crm_flash_set('⚠ Chyba při míchání: ' . $e->getMessage());
        }

        crm_redirect('/admin/contacts/mix');
    }

    /** POST /admin/contacts/mix/settings — admin uloží nový default ratio */
    public function postSettings(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/contacts/mix');
        }

        $ratioFirma = max(1, min(100, (int) ($_POST['ratio_firma'] ?? 1)));
        $ratioOsvc  = max(1, min(100, (int) ($_POST['ratio_osvc']  ?? 9)));
        $autoMix    = !empty($_POST['auto_mix']) ? '1' : '0';

        $userId = (int) $user['id'];
        crm_setting_set('mix_ratio_firma', $ratioFirma, $userId);
        crm_setting_set('mix_ratio_osvc',  $ratioOsvc,  $userId);
        crm_setting_set('mix_auto_after_import', $autoMix, $userId);

        crm_flash_set('✓ Nastavení uloženo: cyklus ' . $ratioOsvc . '× OSVČ + ' . $ratioFirma . '× firma, '
                    . 'auto-mix po importu: ' . ($autoMix === '1' ? 'ZAPNUTÝ' : 'vypnutý') . '.');
        crm_redirect('/admin/contacts/mix');
    }
}
