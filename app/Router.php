<?php
// e:\Snecinatripu\app\Router.php
declare(strict_types=1);

/**
 * Jednoduchý router: metoda + cesta, volitelná autentizace a seznam rolí.
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'middleware.php';

final class Router
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    private static function routes(): array
    {
        return [
            [
                'method' => 'GET',
                'path' => '/',
                'auth' => false,
                'roles' => [],
                'handler' => [HomeController::class, 'getRoot'],
            ],
            [
                'method' => 'GET',
                'path' => '/login',
                'auth' => false,
                'roles' => [],
                'handler' => [LoginController::class, 'getLogin'],
            ],
            [
                'method' => 'POST',
                'path' => '/login',
                'auth' => false,
                'roles' => [],
                'handler' => [LoginController::class, 'postLogin'],
            ],
            [
                'method' => 'GET',
                'path' => '/login/two-factor',
                'auth' => false,
                'roles' => [],
                'handler' => [LoginController::class, 'getTwoFactor'],
            ],
            [
                'method' => 'POST',
                'path' => '/login/two-factor',
                'auth' => false,
                'roles' => [],
                'handler' => [LoginController::class, 'postTwoFactor'],
            ],
            [
                'method' => 'GET',
                'path' => '/dashboard',
                'auth' => true,
                'roles' => ['superadmin', 'majitel', 'navolavacka', 'obchodak', 'backoffice', 'cisticka'],
                'handler' => [DashboardController::class, 'getIndex'],
            ],
            [
                'method' => 'POST',
                'path' => '/logout',
                'auth' => true,
                'roles' => ['superadmin', 'majitel', 'navolavacka', 'obchodak', 'backoffice', 'cisticka'],
                'handler' => [DashboardController::class, 'postLogout'],
            ],
            [
                'method' => 'POST',
                'path' => '/region/switch',
                'auth' => true,
                'roles' => ['obchodak'],
                'handler' => [RegionController::class, 'postSwitch'],
            ],
            // ── Self-service změna hesla (povinná po resetu adminem) ──
            [
                'method' => 'GET',
                'path' => '/account/password',
                'auth' => true,
                'roles' => ['superadmin', 'majitel', 'navolavacka', 'obchodak', 'backoffice', 'cisticka'],
                'handler' => [AccountController::class, 'getChangePassword'],
            ],
            [
                'method' => 'POST',
                'path' => '/account/password',
                'auth' => true,
                'roles' => ['superadmin', 'majitel', 'navolavacka', 'obchodak', 'backoffice', 'cisticka'],
                'handler' => [AccountController::class, 'postChangePassword'],
            ],
            [
                'method' => 'GET',
                'path' => '/admin/users',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminUsersController::class, 'getIndex'],
            ],
            [
                'method' => 'GET',
                'path' => '/admin/users/new',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminUsersController::class, 'getNew'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/users/new',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminUsersController::class, 'postNew'],
            ],
            [
                'method' => 'GET',
                'path' => '/admin/users/new-test',
                'auth' => true,
                'roles' => ['superadmin'],
                'handler' => [AdminUsersController::class, 'getNewTest'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/users/new-test',
                'auth' => true,
                'roles' => ['superadmin'],
                'handler' => [AdminUsersController::class, 'postNewTest'],
            ],
            [
                'method' => 'GET',
                'path' => '/admin/users/edit',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminUsersController::class, 'getEdit'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/users/save',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminUsersController::class, 'postSave'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/users/deactivate',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminUsersController::class, 'postDeactivate'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/users/delete',
                'auth' => true,
                'roles' => ['superadmin'],
                'handler' => [AdminUsersController::class, 'postDelete'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/users/reset-password',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminUsersController::class, 'postResetPassword'],
            ],
            [
                'method' => 'GET',
                'path' => '/admin/import',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminImportController::class, 'getIndex'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/import',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminImportController::class, 'postImport'],
            ],
            // ── Dvoufázový import: preview + commit + cancel ──
            [
                'method' => 'GET',
                'path' => '/admin/import/preview',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminImportController::class, 'getPreview'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/import/commit',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminImportController::class, 'postCommit'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/import/cancel',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminImportController::class, 'postCancel'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/import/reset',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminImportController::class, 'postReset'],
            ],
            // ── Admin: Audit duplicit ──
            [
                'method' => 'GET',
                'path' => '/admin/duplicates',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminDuplicatesController::class, 'getIndex'],
            ],
            // ── Admin: Live datagrid (Excel-like power view) ──
            [
                'method' => 'GET',
                'path' => '/admin/datagrid',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminDatagridController::class, 'getIndex'],
            ],
            [
                'method' => 'GET',
                'path' => '/admin/datagrid/data',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminDatagridController::class, 'getData'],
            ],
            [
                'method' => 'GET',
                'path' => '/admin/datagrid/feed',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminDatagridController::class, 'getFeed'],
            ],
            [
                'method' => 'GET',
                'path' => '/admin/datagrid/contact-history',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminDatagridController::class, 'getContactHistory'],
            ],
            [
                'method' => 'GET',
                'path' => '/admin/feed',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminDatagridController::class, 'getFeedPage'],
            ],
            // ── Admin: Cíle čističky podle krajů ──
            //
            // POZNÁMKA: Bývalé routes /admin/contacts/assignment* byly odstraněny
            // (auto-distribute, bulk reassign, cherry-pick), protože kolidovaly
            // s novým čističkovým workflow (NEW → cisticka → READY → navolavacka pool).
            // Hard-assign před cisticka stage byl historický přežitek z pre-cisticka éry.
            [
                'method' => 'GET',
                'path' => '/admin/cisticka-goals',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [CistickaController::class, 'getAdminGoals'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/cisticka-goals',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [CistickaController::class, 'postAdminGoals'],
            ],
            // ── Sazba odměny čističky (sdílí stránku /admin/cisticka-goals) ──
            [
                'method' => 'POST',
                'path'   => '/admin/cisticka-rewards/save',
                'auth'   => true,
                'roles'  => ['majitel', 'superadmin'],
                'handler'=> [CistickaController::class, 'postAdminRewards'],
            ],
            // ── Čistička self-print: výplata (PDF) ──
            // Hard-locked v controlleru: čistička vidí jen sebe.
            // Admin/majitel může přes ?cisticka_id=N pro konkrétní čističku.
            [
                'method' => 'GET',
                'path'   => '/cisticka/payout/print',
                'auth'   => true,
                'roles'  => ['cisticka', 'majitel', 'superadmin'],
                'handler'=> [CistickaController::class, 'getPayoutPrint'],
            ],
            // ── Čistička ──
            [
                'method' => 'GET',
                'path' => '/cisticka',
                'auth' => true,
                'roles' => ['cisticka', 'majitel', 'superadmin'],
                'handler' => [CistickaController::class, 'getIndex'],
            ],
            [
                'method' => 'POST',
                'path' => '/cisticka/verify',
                'auth' => true,
                'roles' => ['cisticka', 'majitel', 'superadmin'],
                'handler' => [CistickaController::class, 'postVerify'],
            ],
            [
                'method' => 'POST',
                'path' => '/cisticka/verify-batch',
                'auth' => true,
                'roles' => ['cisticka', 'majitel', 'superadmin'],
                'handler' => [CistickaController::class, 'postVerifyBatch'],
            ],
            [
                'method' => 'POST',
                'path' => '/cisticka/undo',
                'auth' => true,
                'roles' => ['cisticka', 'majitel', 'superadmin'],
                'handler' => [CistickaController::class, 'postUndo'],
            ],
            [
                'method' => 'POST',
                'path' => '/cisticka/reclassify',
                'auth' => true,
                'roles' => ['cisticka', 'majitel', 'superadmin'],
                'handler' => [CistickaController::class, 'postReclassify'],
            ],
            // ── Navolávačka ──
            [
                'method' => 'GET',
                'path' => '/caller',
                'auth' => true,
                'roles' => ['navolavacka'],
                'handler' => [CallerController::class, 'getIndex'],
            ],
            [
                'method' => 'POST',
                'path' => '/caller/status',
                'auth' => true,
                'roles' => ['navolavacka'],
                'handler' => [CallerController::class, 'postStatus'],
            ],
            [
                'method' => 'POST',
                'path' => '/caller/assign-sales',
                'auth' => true,
                'roles' => ['navolavacka'],
                'handler' => [CallerController::class, 'postAssignSales'],
            ],
            [
                'method' => 'GET',
                'path' => '/caller/calendar',
                'auth' => true,
                'roles' => ['navolavacka'],
                'handler' => [CallerController::class, 'getCalendar'],
            ],
            [
                'method' => 'GET',
                'path' => '/caller/callbacks.json',
                'auth' => true,
                'roles' => ['navolavacka'],
                'handler' => [CallerController::class, 'getCallbacksJson'],
            ],
            [
                'method' => 'POST',
                'path' => '/caller/set-default-sales',
                'auth' => true,
                'roles' => ['navolavacka'],
                'handler' => [CallerController::class, 'postSetDefaultSales'],
            ],
            [
                'method' => 'POST',
                'path' => '/caller/contact/edit',
                'auth' => true,
                'roles' => ['navolavacka'],
                'handler' => [CallerController::class, 'postEditContact'],
            ],
            [
                'method' => 'GET',
                'path' => '/caller/search',
                'auth' => true,
                'roles' => ['navolavacka'],
                'handler' => [CallerController::class, 'getSearch'],
            ],
            [
                'method' => 'GET',
                'path' => '/caller/stats',
                'auth' => true,
                'roles' => ['navolavacka'],
                'handler' => [CallerController::class, 'getStats'],
            ],
            [
                'method' => 'GET',
                'path' => '/caller/pool-count.json',
                'auth' => true,
                'roles' => ['navolavacka'],
                'handler' => [CallerController::class, 'getPoolCountJson'],
            ],
            [
                'method' => 'GET',
                'path' => '/caller/race.json',
                'auth' => true,
                'roles' => ['navolavacka'],
                'handler' => [CallerController::class, 'getRaceJson'],
            ],
            [
                'method' => 'POST',
                'path' => '/caller/flag-mismatch',
                'auth' => true,
                'roles' => ['navolavacka'],
                'handler' => [CallerController::class, 'postFlagMismatch'],
            ],
            // ── Navolávačka self-print: výplata od OZ-ů (PDF) ──
            // Hard-locked v controlleru: navolávačka vidí jen sebe.
            // Admin/majitel může přes ?caller_id=N pro testování.
            [
                'method' => 'GET',
                'path'   => '/caller/payout/print',
                'auth'   => true,
                'roles'  => ['navolavacka', 'majitel', 'superadmin'],
                'handler'=> [CallerController::class, 'getPayoutPrint'],
            ],
            [
                'method' => 'GET',
                'path' => '/admin/daily-goals',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminDailyGoalsController::class, 'getIndex'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/daily-goals/save',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminDailyGoalsController::class, 'postSave'],
            ],
            // ── Výkon navolávačů (majitel / superadmin) ──
            [
                'method' => 'GET',
                'path' => '/admin/caller-stats',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminCallerStatsController::class, 'getIndex'],
            ],
            // ── Sjednocený výkon týmu (všechny role) ──
            [
                'method' => 'GET',
                'path' => '/admin/team-stats',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminTeamStatsController::class, 'getIndex'],
            ],
            // ── Výkon čističky (vlastní přehled) ──
            [
                'method' => 'GET',
                'path' => '/cisticka/stats',
                'auth' => true,
                'roles' => ['cisticka', 'majitel', 'superadmin'],
                'handler' => [CistickaController::class, 'getStats'],
            ],
            // ── OZ dashboard ──
            [
                'method' => 'GET',
                'path' => '/oz',
                'auth' => true,
                'roles' => ['obchodak', 'majitel', 'superadmin'],
                'handler' => [OzController::class, 'getIndex'],
            ],
            // ── Admin: Kvóty OZ ──
            [
                'method' => 'GET',
                'path' => '/admin/oz-targets',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminOzTargetsController::class, 'getIndex'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/oz-targets/save',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminOzTargetsController::class, 'postSave'],
            ],
            [
                'method' => 'GET',
                'path' => '/admin/oz-targets/detail',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminOzTargetsController::class, 'getDetail'],
            ],
            [
                'method' => 'GET',
                'path' => '/admin/oz-targets/print',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminOzTargetsController::class, 'getPrint'],
            ],
            // ── OZ: podání reklamace ──
            [
                'method' => 'POST',
                'path' => '/oz/flag',
                'auth' => true,
                'roles' => ['obchodak', 'majitel', 'superadmin'],
                'handler' => [OzController::class, 'postFlag'],
            ],
            // ── OZ: pracovní plocha ──
            [
                'method' => 'GET',
                'path' => '/oz/leads',
                'auth' => true,
                'roles' => ['obchodak', 'majitel', 'superadmin'],
                'handler' => [OzController::class, 'getLeads'],
            ],
            // ── OZ: nová queue obrazovka (pending leady + accept) ──
            // Paralelní k /oz/leads — stará URL stále funguje. Po dokončení
            // refactoru bude /oz/leads → 301 redirect → /oz/queue.
            [
                'method' => 'GET',
                'path' => '/oz/queue',
                'auth' => true,
                'roles' => ['obchodak', 'majitel', 'superadmin'],
                'handler' => [OzController::class, 'getQueue'],
            ],
            [
                'method' => 'POST',
                'path' => '/oz/queue/accept',
                'auth' => true,
                'roles' => ['obchodak', 'majitel', 'superadmin'],
                'handler' => [OzController::class, 'postQueueAcceptLead'],
            ],
            // ── OZ: nová call screen obrazovka (Krok 2) ──
            [
                'method' => 'GET',
                'path' => '/oz/work',
                'auth' => true,
                'roles' => ['obchodak', 'majitel', 'superadmin'],
                'handler' => [OzController::class, 'getWork'],
            ],
            [
                'method' => 'POST',
                'path' => '/oz/work/quick-status',
                'auth' => true,
                'roles' => ['obchodak', 'majitel', 'superadmin'],
                'handler' => [OzController::class, 'postWorkQuickStatus'],
            ],
            [
                'method' => 'POST',
                'path' => '/oz/lead-status',
                'auth' => true,
                'roles' => ['obchodak', 'majitel', 'superadmin'],
                'handler' => [OzController::class, 'postLeadStatus'],
            ],
            [
                'method' => 'GET',
                'path' => '/oz/race.json',
                'auth' => true,
                'roles' => ['obchodak', 'majitel', 'superadmin'],
                'handler' => [OzController::class, 'getRaceJson'],
            ],
            [
                'method' => 'POST',
                'path' => '/oz/acknowledge-meeting',
                'auth' => true,
                'roles' => ['obchodak', 'majitel', 'superadmin'],
                'handler' => [OzController::class, 'postAcknowledgeMeeting'],
            ],
            // ── OZ: přijetí leadů ──
            [
                'method' => 'POST',
                'path'   => '/oz/accept-lead',
                'auth'   => true,
                'roles'  => ['obchodak', 'majitel', 'superadmin'],
                'handler'=> [OzController::class, 'postAcceptLead'],
            ],
            [
                'method' => 'POST',
                'path'   => '/oz/accept-all-leads',
                'auth'   => true,
                'roles'  => ['obchodak', 'majitel', 'superadmin'],
                'handler'=> [OzController::class, 'postAcceptAllLeads'],
            ],
            [
                'method' => 'POST',
                'path'   => '/oz/reklamace',
                'auth'   => true,
                'roles'  => ['obchodak', 'majitel', 'superadmin'],
                'handler'=> [OzController::class, 'postReklamace'],
            ],
            [
                'method' => 'POST',
                'path'   => '/oz/chybny-comment',
                'auth'   => true,
                'roles'  => ['obchodak', 'majitel', 'superadmin'],
                'handler'=> [OzController::class, 'postChybnyComment'],
            ],
            [
                'method' => 'POST',
                'path'   => '/oz/chybny-close',
                'auth'   => true,
                'roles'  => ['obchodak', 'majitel', 'superadmin'],
                'handler'=> [OzController::class, 'postChybnyClose'],
            ],
            [
                'method' => 'POST',
                'path'   => '/caller/chybny-objection',
                'auth'   => true,
                'roles'  => ['navolavacka'],
                'handler'=> [CallerController::class, 'postChybnyObjection'],
            ],
            // ── OZ: nabídnuté služby (Fáze 2 CRUD) ──
            [
                'method' => 'POST',
                'path'   => '/oz/offered-service/add',
                'auth'   => true,
                'roles'  => ['obchodak', 'majitel', 'superadmin'],
                'handler'=> [OzController::class, 'postOfferedServiceAdd'],
            ],
            [
                'method' => 'POST',
                'path'   => '/oz/offered-service/delete',
                'auth'   => true,
                'roles'  => ['obchodak', 'majitel', 'superadmin'],
                'handler'=> [OzController::class, 'postOfferedServiceDelete'],
            ],
            [
                'method' => 'POST',
                'path'   => '/oz/offered-service/edit',
                'auth'   => true,
                'roles'  => ['obchodak', 'majitel', 'superadmin'],
                'handler'=> [OzController::class, 'postOfferedServiceEdit'],
            ],
            [
                'method' => 'POST',
                'path'   => '/oz/offered-service-item/oku',
                'auth'   => true,
                'roles'  => ['obchodak', 'majitel', 'superadmin'],
                'handler'=> [OzController::class, 'postOfferedServiceItemOku'],
            ],
            // ── OZ: ID nabídky z OT (rychlý input v hlavičce karty) ──
            [
                'method' => 'POST',
                'path'   => '/oz/set-offer-id',
                'auth'   => true,
                'roles'  => ['obchodak', 'majitel', 'superadmin'],
                'handler'=> [OzController::class, 'postSetOfferId'],
            ],
            // ── OZ: editace údajů kontaktu (firma/tel/email/IČO/adresa) ──
            [
                'method' => 'POST',
                'path'   => '/oz/contact/edit',
                'auth'   => true,
                'roles'  => ['obchodak', 'majitel', 'superadmin'],
                'handler'=> [OzController::class, 'postContactEdit'],
            ],
            // ── ARES lookup proxy (vyžaduje 8-místné IČO) ──
            [
                'method' => 'GET',
                'path'   => '/oz/ares-lookup',
                'auth'   => true,
                'roles'  => ['obchodak', 'majitel', 'superadmin', 'backoffice'],
                'handler'=> [OzController::class, 'getAresLookup'],
            ],
            // ── OZ: přizpůsobitelné záložky (skrytí / připnutí) ──
            [
                'method' => 'POST',
                'path'   => '/oz/tab/hide',
                'auth'   => true,
                'roles'  => ['obchodak', 'majitel', 'superadmin'],
                'handler'=> [OzController::class, 'postTabHide'],
            ],
            [
                'method' => 'POST',
                'path'   => '/oz/tab/show',
                'auth'   => true,
                'roles'  => ['obchodak', 'majitel', 'superadmin'],
                'handler'=> [OzController::class, 'postTabShow'],
            ],
            [
                'method' => 'POST',
                'path'   => '/oz/tab/reorder',
                'auth'   => true,
                'roles'  => ['obchodak', 'majitel', 'superadmin'],
                'handler'=> [OzController::class, 'postTabReorder'],
            ],
            // ── OZ: Pracovní deník akcí (BO log) ──
            [
                'method' => 'POST',
                'path'   => '/oz/checkbox-toggle',
                'auth'   => true,
                'roles'  => ['obchodak', 'majitel', 'superadmin'],
                'handler'=> [OzController::class, 'postCheckboxToggle'],
            ],
            [
                'method' => 'POST',
                'path'   => '/oz/action/add',
                'auth'   => true,
                'roles'  => ['obchodak', 'majitel', 'superadmin'],
                'handler'=> [OzController::class, 'postActionAdd'],
            ],
            [
                'method' => 'POST',
                'path'   => '/oz/action/delete',
                'auth'   => true,
                'roles'  => ['obchodak', 'majitel', 'superadmin'],
                'handler'=> [OzController::class, 'postActionDelete'],
            ],
            // ── Back-office workspace ──
            [
                'method' => 'GET',
                'path'   => '/bo',
                'auth'   => true,
                'roles'  => ['backoffice', 'majitel', 'superadmin'],
                'handler'=> [BackofficeController::class, 'getIndex'],
            ],
            [
                'method' => 'POST',
                'path'   => '/bo/return-oz',
                'auth'   => true,
                'roles'  => ['backoffice', 'majitel', 'superadmin'],
                'handler'=> [BackofficeController::class, 'postReturnToOz'],
            ],
            [
                'method' => 'POST',
                'path'   => '/bo/start-work',
                'auth'   => true,
                'roles'  => ['backoffice', 'majitel', 'superadmin'],
                'handler'=> [BackofficeController::class, 'postStartWork'],
            ],
            [
                'method' => 'POST',
                'path'   => '/bo/close',
                'auth'   => true,
                'roles'  => ['backoffice', 'majitel', 'superadmin'],
                'handler'=> [BackofficeController::class, 'postClose'],
            ],
            [
                'method' => 'POST',
                'path'   => '/bo/reopen',
                'auth'   => true,
                'roles'  => ['backoffice', 'majitel', 'superadmin'],
                'handler'=> [BackofficeController::class, 'postReopen'],
            ],
            [
                'method' => 'POST',
                'path'   => '/bo/checkbox-toggle',
                'auth'   => true,
                'roles'  => ['backoffice', 'majitel', 'superadmin'],
                'handler'=> [BackofficeController::class, 'postCheckboxToggle'],
            ],
            [
                'method' => 'POST',
                'path'   => '/bo/nezajem',
                'auth'   => true,
                'roles'  => ['backoffice', 'majitel', 'superadmin'],
                'handler'=> [BackofficeController::class, 'postNezajem'],
            ],
            [
                'method' => 'POST',
                'path'   => '/bo/action/add',
                'auth'   => true,
                'roles'  => ['backoffice', 'majitel', 'superadmin'],
                'handler'=> [BackofficeController::class, 'postActionAdd'],
            ],
            // ── BO: editace údajů kontaktu (firma/tel/email/IČO/adresa) ──
            // Stejné UX jako u OZ /oz/contact/edit, jen pro BO pracovníka.
            [
                'method' => 'POST',
                'path'   => '/bo/contact/edit',
                'auth'   => true,
                'roles'  => ['backoffice', 'majitel', 'superadmin'],
                'handler'=> [BackofficeController::class, 'postContactEdit'],
            ],
            [
                'method' => 'POST',
                'path'   => '/bo/action/delete',
                'auth'   => true,
                'roles'  => ['backoffice', 'majitel', 'superadmin'],
                'handler'=> [BackofficeController::class, 'postActionDelete'],
            ],
            // ── OZ: výkon týmu se stages ──
            [
                'method' => 'GET',
                'path' => '/oz/performance',
                'auth' => true,
                'roles' => ['obchodak', 'majitel', 'superadmin'],
                'handler' => [OzPerformanceController::class, 'getIndex'],
            ],
            // ── OZ self-print: payout pro navolávačky (PDF) ──
            // Stejná šablona jako /admin/oz-targets/print, ale OZ vidí JEN
            // sebe (hard-locked v controlleru). Standalone stránka pro tisk.
            [
                'method' => 'GET',
                'path' => '/oz/payout/print',
                'auth' => true,
                'roles' => ['obchodak', 'majitel', 'superadmin'],
                'handler' => [AdminOzTargetsController::class, 'getOzSelfPrint'],
            ],
            // ── Návrhy nových kontaktů (manual hot leads) ──
            // Kdokoliv s rolí může navrhnout, majitel/superadmin schvaluje.
            [
                'method' => 'GET',
                'path'   => '/contacts/new',
                'auth'   => true,
                'roles'  => ['navolavacka', 'cisticka', 'obchodak', 'backoffice', 'majitel', 'superadmin'],
                'handler'=> [ContactProposalsController::class, 'getNew'],
            ],
            [
                'method' => 'POST',
                'path'   => '/contacts/new',
                'auth'   => true,
                'roles'  => ['navolavacka', 'cisticka', 'obchodak', 'backoffice', 'majitel', 'superadmin'],
                'handler'=> [ContactProposalsController::class, 'postNew'],
            ],
            [
                'method' => 'GET',
                'path'   => '/admin/contact-proposals',
                'auth'   => true,
                'roles'  => ['majitel', 'superadmin'],
                'handler'=> [ContactProposalsController::class, 'getAdminList'],
            ],
            [
                'method' => 'POST',
                'path'   => '/admin/contact-proposals/approve',
                'auth'   => true,
                'roles'  => ['majitel', 'superadmin'],
                'handler'=> [ContactProposalsController::class, 'postApprove'],
            ],
            [
                'method' => 'POST',
                'path'   => '/admin/contact-proposals/reject',
                'auth'   => true,
                'roles'  => ['majitel', 'superadmin'],
                'handler'=> [ContactProposalsController::class, 'postReject'],
            ],
            // ── Admin: Osobní milníky OZ ──
            [
                'method' => 'GET',
                'path' => '/admin/oz-milestones',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminOzMilestonesController::class, 'getIndex'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/oz-milestones/save',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminOzMilestonesController::class, 'postSave'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/oz-milestones/delete',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminOzMilestonesController::class, 'postDelete'],
            ],
            // ── Admin: OZ stage cíle ──
            [
                'method' => 'GET',
                'path' => '/admin/oz-stages',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminOzStagesController::class, 'getIndex'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/oz-stages/save',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminOzStagesController::class, 'postSave'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/oz-stages/delete',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminOzStagesController::class, 'postDelete'],
            ],
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        foreach (self::routes() as $route) {
            if ($route['method'] !== $method || $route['path'] !== $path) {
                continue;
            }
            $user = null;
            if (!empty($route['auth'])) {
                $user = crm_require_user($this->pdo);
                /** @var list<string> $roles */
                $roles = $route['roles'] ?? [];
                if ($roles !== []) {
                    crm_require_roles($user, $roles);
                }
            }
            $handler = $route['handler'];
            $class = $handler[0];
            $action = $handler[1];
            $controller = new $class($this->pdo);
            $controller->{$action}();
            return;
        }
        $this->notFound();
    }

    private function notFound(): never
    {
        http_response_code(404);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="cs"><head><meta charset="UTF-8"><title>404</title></head><body><p>Stránka nenalezena.</p></body></html>';
        exit;
    }
}
