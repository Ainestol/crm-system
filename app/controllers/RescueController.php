<?php
// e:\Snecinatripu\app\controllers\RescueController.php
declare(strict_types=1);

/**
 * RescueController
 *
 * Endpointy pro feature "Záchrana leadu":
 *   POST /oz/contact/rescue        – OZ pošle kontakt na záchranu
 *   POST /caller/rescue/status     – Navolávačka uloží výsledek záchrany
 *   POST /admin/rescue/mark-paid   – Admin/majitel označí bonus jako vyplacený
 */
final class RescueController
{
    public function __construct(private PDO $pdo)
    {
    }

    /** POST /oz/contact/rescue — OZ pošle kontakt na záchranu */
    public function postCreate(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['obchodak', 'majitel', 'superadmin']);

        $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            $this->jsonOrFlash($isAjax, ['ok' => false, 'error' => 'Neplatný CSRF token.']);
        }

        $contactId       = (int) ($_POST['contact_id'] ?? 0);
        $reason          = trim((string) ($_POST['reason'] ?? ''));
        $targetMode      = (string) ($_POST['target_mode'] ?? 'me'); // me | other | rotation
        $targetSalesId   = (int) ($_POST['target_sales_id'] ?? 0);
        $originalSalesId = (int) $user['id'];

        if ($contactId <= 0 || $reason === '') {
            $this->jsonOrFlash($isAjax, ['ok' => false, 'error' => 'Vyplňte důvod záchrany.']);
        }

        // Resolve target z target_mode:
        $finalTargetId   = null;
        $preferOriginal  = false;
        if ($targetMode === 'me') {
            $preferOriginal = true;
            $finalTargetId  = null;
        } elseif ($targetMode === 'other') {
            if ($targetSalesId <= 0) {
                $this->jsonOrFlash($isAjax, ['ok' => false, 'error' => 'Pro "Předat jinému OZ" musíte vybrat konkrétního OZ.']);
            }
            // Validace: target musí být aktivní obchodák tohoto tenantu (přes user_tenants)
            $vStmt = $this->pdo->prepare(
                "SELECT u.id FROM users u
                 INNER JOIN user_tenants ut
                     ON ut.user_id = u.id AND ut.tenant_id = ? AND ut.active = 1
                 WHERE u.id = ? AND u.aktivni = 1 AND (
                    u.role = 'obchodak'
                    OR JSON_CONTAINS(IFNULL(u.roles_extra, '[]'), '\"obchodak\"')
                 )"
            );
            $vStmt->execute([crm_tenant_id(), $targetSalesId]);
            if ($vStmt->fetchColumn() === false) {
                $this->jsonOrFlash($isAjax, ['ok' => false, 'error' => 'Vybraný OZ neexistuje nebo není aktivní.']);
            }
            $finalTargetId  = $targetSalesId;
            $preferOriginal = false;
        } elseif ($targetMode === 'rotation') {
            // Rotace — caller bude muset při výhře vybrat ručně. Nepodporujeme v této fázi.
            $this->jsonOrFlash($isAjax, ['ok' => false, 'error' => 'Rotace zatím není podporována — vyber konkrétního OZ nebo "ke mně".']);
        } else {
            $this->jsonOrFlash($isAjax, ['ok' => false, 'error' => 'Neplatný target_mode.']);
        }

        try {
            $result = rescue_create(
                $this->pdo,
                $contactId,
                $originalSalesId,
                $finalTargetId,
                $preferOriginal,
                $reason
            );
        } catch (\RuntimeException $e) {
            $this->jsonOrFlash($isAjax, ['ok' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            if (function_exists('crm_db_log_error')) {
                crm_db_log_error($e, __METHOD__);
            }
            $this->jsonOrFlash($isAjax, ['ok' => false, 'error' => 'Interní chyba: ' . $e->getMessage()]);
        }

        if ($result === null) {
            $this->jsonOrFlash($isAjax, ['ok' => false, 'error' => 'Záchranu se nepodařilo založit.']);
        }

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'rescue_id'  => $result['id'],
                'expires_at' => $result['expires_at'],
            ]);
            exit;
        }

        crm_flash_set('🆘 Kontakt předán na záchranu. Deadline ' . date('d.m.Y H:i', strtotime($result['expires_at'])) . '.');
        crm_redirect('/oz/leads');
    }

    /** POST /caller/rescue/status — Navolávačka uloží výsledek záchrany */
    public function postCallerStatus(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['navolavacka', 'majitel', 'superadmin']);

        $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            $this->jsonOrFlash($isAjax, ['ok' => false, 'error' => 'Neplatný CSRF token.']);
        }

        $callerId = (int) $user['id'];
        $rescueId = (int) ($_POST['rescue_id'] ?? 0);
        $action   = (string) ($_POST['action'] ?? '');
        $note     = trim((string) ($_POST['note'] ?? ''));

        if ($rescueId <= 0 || !in_array($action, ['success', 'nezajem', 'called_bad', 'izolace'], true)) {
            $this->jsonOrFlash($isAjax, ['ok' => false, 'error' => 'Neplatný požadavek.']);
        }

        // Pro failure důvody vyžadujeme krátkou poznámku
        if (in_array($action, ['nezajem', 'called_bad', 'izolace'], true) && mb_strlen($note) < 2) {
            $this->jsonOrFlash($isAjax, ['ok' => false, 'error' => 'U neúspěšných záchran je poznámka povinná.']);
        }

        // Volitelný override OZ (caller přesměruje na někoho jiného, např. po telefonátu s OZ)
        $overrideSalesId = isset($_POST['override_sales_id']) ? (int) $_POST['override_sales_id'] : 0;

        try {
            if ($action === 'success') {
                $r = rescue_success($this->pdo, $rescueId, $callerId, $note, $overrideSalesId > 0 ? $overrideSalesId : null);
                if ($r === null) {
                    $this->jsonOrFlash($isAjax, ['ok' => false, 'error' => 'Záchrana není pending (možná už uzavřena).']);
                }
                $msg = '🎉 Úspěšně zachráněno! Lead jde OZ.';
            } else {
                $finalStav = match ($action) {
                    'nezajem'    => 'NEZAJEM',
                    'called_bad' => 'CALLED_BAD',
                    'izolace'    => 'IZOLACE',
                };
                $ok = rescue_failure($this->pdo, $rescueId, $callerId, $finalStav, $note);
                if (!$ok) {
                    $this->jsonOrFlash($isAjax, ['ok' => false, 'error' => 'Záchrana není pending (možná už uzavřena).']);
                }
                $msg = '✓ Záchrana uzavřena jako ' . $finalStav;
            }
        } catch (\Throwable $e) {
            if (function_exists('crm_db_log_error')) {
                crm_db_log_error($e, __METHOD__);
            }
            $this->jsonOrFlash($isAjax, ['ok' => false, 'error' => 'Interní chyba: ' . $e->getMessage()]);
        }

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'message' => $msg]);
            exit;
        }

        crm_flash_set($msg);
        crm_redirect('/caller?tab=rescue');
    }

    /** POST /admin/rescue/mark-paid — Admin označí bonus jako vyplacený */
    public function postMarkPaid(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný CSRF token.');
            crm_redirect('/admin/rescue');
        }

        $rescueId = (int) ($_POST['rescue_id'] ?? 0);
        if ($rescueId <= 0) {
            crm_redirect('/admin/rescue');
        }

        $ok = rescue_mark_paid($this->pdo, $rescueId, (int) $user['id']);
        crm_flash_set($ok ? 'Bonus označen jako vyplacený.' : 'Nelze označit — bonus nezískán nebo už vyplacen.');
        crm_redirect('/admin/rescue');
    }

    /** GET /admin/rescue — admin overview všech záchran */
    public function getAdminIndex(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        // Multi-tenant filter
        $stmt = $this->pdo->prepare(
            "SELECT rr.*,
                    c.firma, c.telefon, c.region,
                    uo.jmeno AS original_sales_name,
                    ut.jmeno AS target_sales_name,
                    uf.jmeno AS final_sales_name,
                    urc.jmeno AS rescued_by_caller_name,
                    uoc.jmeno AS original_caller_name
             FROM rescue_requests rr
             LEFT JOIN contacts c   ON c.id   = rr.contact_id AND c.tenant_id = rr.tenant_id
             LEFT JOIN users uo     ON uo.id  = rr.original_sales_id
             LEFT JOIN users ut     ON ut.id  = rr.target_sales_id
             LEFT JOIN users uf     ON uf.id  = rr.final_sales_id
             LEFT JOIN users urc    ON urc.id = rr.rescued_by_caller_id
             LEFT JOIN users uoc    ON uoc.id = rr.original_caller_id
             WHERE rr.tenant_id = :tid
             ORDER BY
                 (rr.outcome = 'pending') DESC,
                 (rr.outcome = 'success' AND rr.bonus_amount IS NOT NULL AND rr.bonus_paid_at IS NULL) DESC,
                 rr.requested_at DESC
             LIMIT 200"
        );
        $stmt->execute(['tid' => crm_tenant_id()]);
        $rescues = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();
        $title = '🆘 Záchrany leadů';
        ob_start();
        require dirname(__DIR__) . '/views/admin/rescue/index.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    /** Helper pro mixed AJAX/redirect odpovědi */
    private function jsonOrFlash(bool $isAjax, array $payload): never
    {
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($payload);
            exit;
        }
        crm_flash_set($payload['error'] ?? 'Chyba');
        crm_redirect('/oz/leads');
    }
}
