<?php
// e:\Snecinatripu\app\controllers\TicketController.php
declare(strict_types=1);

/**
 * TicketController — interní ticket systém (pomoc / požadavky).
 *
 * Workflow:
 *   - Kdokoli přihlášený může založit ticket (problém / požadavek).
 *   - Stavy: open → in_progress → resolved (s časovými značkami).
 *   - Stav mění JEN majitel/superadmin (řešitel).
 *
 * Viditelnost:
 *   - Běžný uživatel  → jen svoje tickety (v rámci své firmy).
 *   - Majitel         → všechny tickety své firmy + filtry.
 *   - Superadmin      → všechny tickety napříč firmami + filtr na firmu.
 *
 * Routes:
 *   GET  /tickets          → seznam + formulář na nový ticket
 *   POST /tickets/create   → založení ticketu (kdokoli)
 *   POST /tickets/status   → změna stavu (jen majitel/superadmin)
 */
final class TicketController
{
    private const PRIORITIES = ['low', 'medium', 'high'];
    private const STATUSES    = ['open', 'in_progress', 'resolved'];
    private const LIST_LIMIT  = 500;

    public function __construct(private PDO $pdo)
    {
    }

    /** Je aktuální uživatel řešitel (admin)? */
    private function isAdmin(array $user): bool
    {
        $role = (string) ($user['role'] ?? '');
        if (in_array($role, ['majitel', 'superadmin'], true)) {
            return true;
        }
        return function_exists('crm_tenant_is_super_admin') && crm_tenant_is_super_admin();
    }

    /** Vidí napříč firmami (cross-tenant)? */
    private function isSuper(): bool
    {
        return function_exists('crm_tenant_is_super_admin') && crm_tenant_is_super_admin();
    }

    // ────────────────────────────────────────────────────────────────
    //  GET /tickets
    // ────────────────────────────────────────────────────────────────
    public function getIndex(): void
    {
        $user = crm_require_user($this->pdo);
        $uid  = (int) ($user['id'] ?? 0);
        $tid  = function_exists('crm_tenant_id') ? (int) crm_tenant_id() : 0;
        $isAdmin = $this->isAdmin($user);
        $isSuper = $this->isSuper();

        // ── WHERE podle role ──
        $where  = [];
        $params = [];

        if ($isSuper) {
            // superadmin → napříč firmami; volitelný filtr na firmu
            $fTenant = (int) ($_GET['tenant'] ?? 0);
            if ($fTenant > 0) {
                $where[]  = 't.tenant_id = :ftenant';
                $params[':ftenant'] = $fTenant;
            }
        } elseif ($isAdmin) {
            // majitel → jen svoje firma, všichni zakladatelé
            $where[] = 't.tenant_id = :tid';
            $params[':tid'] = $tid;
        } else {
            // běžný uživatel → jen svoje tickety ve své firmě
            $where[] = 't.tenant_id = :tid';
            $where[] = 't.created_by = :uid';
            $params[':tid'] = $tid;
            $params[':uid'] = $uid;
        }

        // ── Volitelné filtry (hlavně pro admina) ──
        $fStatus   = (string) ($_GET['status'] ?? '');
        $fPriority = (string) ($_GET['priority'] ?? '');
        $fRole     = (string) ($_GET['role'] ?? '');
        $fQuery    = trim((string) ($_GET['q'] ?? ''));
        $fFrom     = trim((string) ($_GET['from'] ?? ''));
        $fTo       = trim((string) ($_GET['to'] ?? ''));

        if (in_array($fStatus, self::STATUSES, true)) {
            $where[] = 't.status = :fstatus';
            $params[':fstatus'] = $fStatus;
        }
        if (in_array($fPriority, self::PRIORITIES, true)) {
            $where[] = 't.priority = :fpriority';
            $params[':fpriority'] = $fPriority;
        }
        if ($fRole !== '') {
            $where[] = 't.creator_role = :frole';
            $params[':frole'] = $fRole;
        }
        if ($fQuery !== '') {
            $where[] = '(u.jmeno LIKE :fq OR u.email LIKE :fq OR t.subject LIKE :fq)';
            $params[':fq'] = '%' . $fQuery . '%';
        }
        if ($fFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fFrom)) {
            $where[] = 't.created_at >= :ffrom';
            $params[':ffrom'] = $fFrom . ' 00:00:00';
        }
        if ($fTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fTo)) {
            $where[] = 't.created_at <= :fto';
            $params[':fto'] = $fTo . ' 23:59:59';
        }

        $whereSql = $where === [] ? '1=1' : implode(' AND ', $where);

        $sql = "SELECT t.*,
                       u.jmeno AS creator_name, u.email AS creator_email,
                       a.jmeno AS assignee_name,
                       tn.name AS tenant_name
                FROM tickets t
                LEFT JOIN users   u  ON u.id  = t.created_by
                LEFT JOIN users   a  ON a.id  = t.assigned_to
                LEFT JOIN tenants tn ON tn.id = t.tenant_id
                WHERE $whereSql
                ORDER BY FIELD(t.status, 'in_progress', 'open', 'resolved'),
                         FIELD(t.priority, 'high', 'medium', 'low'),
                         t.created_at DESC
                LIMIT " . self::LIST_LIMIT;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // ── Souhrn počtů (ve viditelném scope, bez stavového filtru) ──
        $counts = ['open' => 0, 'in_progress' => 0, 'resolved' => 0];
        foreach ($tickets as $t) {
            $s = (string) ($t['status'] ?? '');
            if (isset($counts[$s])) {
                $counts[$s]++;
            }
        }

        // ── Pro superadmin filtr na firmu — seznam firem ──
        $tenantsList = [];
        if ($isSuper) {
            try {
                $tenantsList = $this->pdo->query(
                    "SELECT id, name FROM tenants ORDER BY name"
                )->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $_) {
                $tenantsList = [];
            }
        }

        // ── Role pro filtr ──
        $roleOptions = [
            'cisticka'    => 'Čistička',
            'navolavacka' => 'Navolávačka',
            'obchodak'    => 'Obchodák',
            'backoffice'  => 'Backoffice',
            'majitel'     => 'Majitel',
            'superadmin'  => 'Superadmin',
        ];

        $filters = [
            'status'   => $fStatus,
            'priority' => $fPriority,
            'role'     => $fRole,
            'q'        => $fQuery,
            'from'     => $fFrom,
            'to'       => $fTo,
            'tenant'   => (int) ($_GET['tenant'] ?? 0),
        ];

        // Přílohy (obrázky) k zobrazeným ticketům
        $attByTicket = $this->loadAttachments(array_map(fn($t) => (int) ($t['id'] ?? 0), $tickets));

        $title = '🎫 Tickety';
        $flash = crm_flash_take();
        ob_start();
        require dirname(__DIR__) . '/views/tickets/index.php';
        $content = (string) ob_get_clean();
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /tickets/create — kdokoli přihlášený
    // ────────────────────────────────────────────────────────────────
    public function postCreate(): void
    {
        $user = crm_require_user($this->pdo);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný bezpečnostní token. Zkus to znovu.');
            crm_redirect('/tickets');
        }

        $subject  = trim((string) ($_POST['subject'] ?? ''));
        $body     = trim((string) ($_POST['body'] ?? ''));
        $priority = (string) ($_POST['priority'] ?? 'medium');

        if ($subject === '') {
            crm_flash_set('Vyplň prosím předmět ticketu.');
            crm_redirect('/tickets');
        }
        if (!in_array($priority, self::PRIORITIES, true)) {
            $priority = 'medium';
        }
        if (mb_strlen($subject) > 200) {
            $subject = mb_substr($subject, 0, 200);
        }

        $tid  = function_exists('crm_tenant_id') ? (int) crm_tenant_id() : 0;
        $uid  = (int) ($user['id'] ?? 0);
        $role = (string) ($user['role'] ?? '');

        $stmt = $this->pdo->prepare(
            "INSERT INTO tickets
                (tenant_id, created_by, creator_role, subject, body, priority, status, created_at, updated_at)
             VALUES
                (:tenant_id, :created_by, :creator_role, :subject, :body, :priority, 'open', NOW(3), NOW(3))"
        );
        $stmt->execute([
            ':tenant_id'    => $tid,
            ':created_by'   => $uid,
            ':creator_role' => $role,
            ':subject'      => $subject,
            ':body'         => ($body === '' ? null : $body),
            ':priority'     => $priority,
        ]);
        $ticketId = (int) $this->pdo->lastInsertId();

        // Spáruj staged přílohy (nahrané přes Ctrl+V během psaní) k tomuto ticketu.
        // Bezpečnost: jen vlastní (uploaded_by = já), správný tenant a zatím
        // nespárované (ticket_id IS NULL). Páruje se podle explicitního seznamu
        // ID (uživatel mohl některé v UI odebrat).
        $rawAtt = isset($_POST['attachment_ids']) && is_array($_POST['attachment_ids'])
            ? $_POST['attachment_ids'] : [];
        $attIds = array_values(array_unique(array_filter(array_map('intval', $rawAtt), fn($i) => $i > 0)));
        if ($ticketId > 0 && $attIds !== []) {
            try {
                $ph = implode(',', array_fill(0, count($attIds), '?'));
                $this->pdo->prepare(
                    "UPDATE ticket_attachments
                        SET ticket_id = ?
                      WHERE id IN ($ph)
                        AND uploaded_by = ?
                        AND tenant_id   = ?
                        AND ticket_id IS NULL"
                )->execute(array_merge([$ticketId], $attIds, [$uid, $tid]));
            } catch (\Throwable $_) {}
        }

        crm_flash_set('✓ Ticket založen. Vyřešíme co nejdřív.');
        crm_redirect('/tickets');
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /tickets/upload — nahrání obrázku (Ctrl+V / drag&drop / výběr)
    //  Vrací JSON {ok, id, url, name}
    // ────────────────────────────────────────────────────────────────
    public function postUpload(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');

        $user = crm_require_user($this->pdo);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Neplatný bezpečnostní token.']);
            exit;
        }

        if (!isset($_FILES['image']) || !is_array($_FILES['image'])
            || (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Žádný soubor.']);
            exit;
        }

        $file    = $_FILES['image'];
        $tmp     = (string) ($file['tmp_name'] ?? '');
        $size    = (int) ($file['size'] ?? 0);
        $maxSize = 8 * 1024 * 1024; // 8 MB

        if ($size <= 0 || $size > $maxSize) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Obrázek je příliš velký (max 8 MB).']);
            exit;
        }

        // Validace, že je to opravdu obrázek
        $imgInfo = @getimagesize($tmp);
        $mimeMap = [
            'image/png'  => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];
        $mime = is_array($imgInfo) ? (string) ($imgInfo['mime'] ?? '') : '';
        if (!isset($mimeMap[$mime])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Povolené jsou jen obrázky (PNG, JPG, WEBP, GIF).']);
            exit;
        }
        $ext = $mimeMap[$mime];

        $tid = function_exists('crm_tenant_id') ? (int) crm_tenant_id() : 0;
        $uid = (int) ($user['id'] ?? 0);

        // Cíl: storage/tickets/<tenant>/<random>.<ext> (PRIVÁTNĚ, mimo web root)
        $dir = CRM_STORAGE_PATH . DIRECTORY_SEPARATOR . 'tickets' . DIRECTORY_SEPARATOR . $tid;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Nelze vytvořit úložiště příloh.']);
            exit;
        }

        try {
            $rand     = bin2hex(random_bytes(16));
        } catch (\Throwable $_) {
            $rand = (string) time() . (string) random_int(1000, 9999);
        }
        $filename = $rand . '.' . $ext;
        $target   = $dir . DIRECTORY_SEPARATOR . $filename;

        if (!@move_uploaded_file($tmp, $target)) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Uložení obrázku selhalo.']);
            exit;
        }

        $token    = trim((string) ($_POST['upload_token'] ?? ''));
        if ($token === '' || strlen($token) > 64) {
            $token = bin2hex(random_bytes(8));
        }
        $origName = mb_substr((string) ($file['name'] ?? 'screenshot.' . $ext), 0, 255);

        $ins = $this->pdo->prepare(
            "INSERT INTO ticket_attachments
                (tenant_id, ticket_id, upload_token, uploaded_by, filename, orig_name, mime, size_bytes, created_at)
             VALUES
                (:tenant_id, NULL, :token, :uid, :filename, :orig, :mime, :size, NOW(3))"
        );
        $ins->execute([
            ':tenant_id' => $tid,
            ':token'     => $token,
            ':uid'       => $uid,
            ':filename'  => $filename,
            ':orig'      => $origName,
            ':mime'      => $mime,
            ':size'      => $size,
        ]);
        $attId = (int) $this->pdo->lastInsertId();

        echo json_encode([
            'ok'   => true,
            'id'   => $attId,
            'url'  => crm_url('/tickets/attachment?id=' . $attId),
            'name' => $origName,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ────────────────────────────────────────────────────────────────
    //  GET /tickets/attachment?id=N — servíruje obrázek s kontrolou práv
    // ────────────────────────────────────────────────────────────────
    public function getAttachment(): void
    {
        $user = crm_require_user($this->pdo);
        $uid  = (int) ($user['id'] ?? 0);
        $tid  = function_exists('crm_tenant_id') ? (int) crm_tenant_id() : 0;
        $isAdmin = $this->isAdmin($user);
        $isSuper = $this->isSuper();

        $attId = (int) ($_GET['id'] ?? 0);
        if ($attId <= 0) {
            http_response_code(404);
            exit;
        }

        $sel = $this->pdo->prepare(
            "SELECT a.*, t.created_by AS ticket_creator
               FROM ticket_attachments a
               LEFT JOIN tickets t ON t.id = a.ticket_id
              WHERE a.id = :id"
        );
        $sel->execute([':id' => $attId]);
        $att = $sel->fetch(PDO::FETCH_ASSOC);
        if (!$att) {
            http_response_code(404);
            exit;
        }

        // Kontrola práv:
        //  - superadmin → vše
        //  - staged (ticket_id NULL) → jen ten, kdo nahrál
        //  - spárované → vlastník ticketu, admin stejné firmy
        $allowed = false;
        if ($isSuper) {
            $allowed = true;
        } elseif ($att['ticket_id'] === null) {
            $allowed = ((int) $att['uploaded_by'] === $uid);
        } else {
            $sameTenant = ((int) $att['tenant_id'] === $tid);
            if ($sameTenant && ($isAdmin || (int) $att['ticket_creator'] === $uid)) {
                $allowed = true;
            }
        }
        if (!$allowed) {
            http_response_code(403);
            exit;
        }

        $path = CRM_STORAGE_PATH . DIRECTORY_SEPARATOR . 'tickets'
              . DIRECTORY_SEPARATOR . (int) $att['tenant_id']
              . DIRECTORY_SEPARATOR . basename((string) $att['filename']);
        if (!is_file($path)) {
            http_response_code(404);
            exit;
        }

        header('Content-Type: ' . (string) $att['mime']);
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: inline; filename="' . basename((string) $att['filename']) . '"');
        header('Cache-Control: private, max-age=86400');
        readfile($path);
        exit;
    }

    /** Načte přílohy pro dané ticket_id (batch) → [ticket_id => list]. */
    private function loadAttachments(array $ticketIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $ticketIds), fn($i) => $i > 0));
        if ($ids === []) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT id, ticket_id, orig_name FROM ticket_attachments
              WHERE ticket_id IN ($ph) ORDER BY id ASC"
        );
        $stmt->execute($ids);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[(int) $r['ticket_id']][] = $r;
        }
        return $out;
    }

    // ────────────────────────────────────────────────────────────────
    //  POST /tickets/status — jen majitel/superadmin
    // ────────────────────────────────────────────────────────────────
    public function postStatus(): void
    {
        $user = crm_require_user($this->pdo);
        crm_require_roles($user, ['majitel', 'superadmin']);

        if (!crm_csrf_validate($_POST[crm_csrf_field_name()] ?? null)) {
            crm_flash_set('Neplatný bezpečnostní token. Zkus to znovu.');
            crm_redirect('/tickets');
        }

        $ticketId   = (int) ($_POST['ticket_id'] ?? 0);
        $newStatus  = (string) ($_POST['status'] ?? '');
        $resolution = trim((string) ($_POST['resolution'] ?? ''));

        if ($ticketId <= 0 || !in_array($newStatus, self::STATUSES, true)) {
            crm_flash_set('Neplatný požadavek.');
            crm_redirect('/tickets');
        }

        // Načti ticket (a ověř tenant — majitel jen svou firmu)
        $tid     = function_exists('crm_tenant_id') ? (int) crm_tenant_id() : 0;
        $isSuper = $this->isSuper();

        $sel = $this->pdo->prepare("SELECT id, tenant_id, status FROM tickets WHERE id = :id");
        $sel->execute([':id' => $ticketId]);
        $ticket = $sel->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            crm_flash_set('Ticket nenalezen.');
            crm_redirect('/tickets');
        }
        if (!$isSuper && (int) $ticket['tenant_id'] !== $tid) {
            crm_flash_set('Tento ticket nepatří tvé firmě.');
            crm_redirect('/tickets');
        }

        $uid = (int) ($user['id'] ?? 0);

        // Sestav UPDATE podle cílového stavu + časové značky
        $set    = ['status = :status', 'updated_at = NOW(3)'];
        $params = [':status' => $newStatus, ':id' => $ticketId];

        if ($newStatus === 'in_progress') {
            // založ in_progress_at pokud ještě není; přiřaď řešitele
            $set[] = 'in_progress_at = COALESCE(in_progress_at, NOW(3))';
            $set[] = 'assigned_to = COALESCE(assigned_to, :uid)';
            $set[] = 'resolved_at = NULL';
            $params[':uid'] = $uid;
        } elseif ($newStatus === 'resolved') {
            $set[] = 'resolved_at = NOW(3)';
            $set[] = 'in_progress_at = COALESCE(in_progress_at, NOW(3))';
            $set[] = 'assigned_to = COALESCE(assigned_to, :uid)';
            $set[] = 'resolution = :resolution';
            $params[':uid'] = $uid;
            $params[':resolution'] = ($resolution === '' ? null : $resolution);
        } else { // open (znovuotevření)
            $set[] = 'resolved_at = NULL';
        }

        $sql = "UPDATE tickets SET " . implode(', ', $set) . " WHERE id = :id";
        $this->pdo->prepare($sql)->execute($params);

        $labels = ['open' => 'znovuotevřen', 'in_progress' => 'v řešení', 'resolved' => 'vyřešen'];
        crm_flash_set('✓ Ticket #' . $ticketId . ' — ' . ($labels[$newStatus] ?? $newStatus) . '.');
        crm_redirect('/tickets');
    }
}
