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
            // ── Forgot/reset hesla (bez přihlášení) ─────────────────────────
            [
                'method' => 'GET',
                'path'   => '/password/forgot',
                'auth'   => false,
                'roles'  => [],
                'handler'=> [LoginController::class, 'getForgotPassword'],
            ],
            [
                'method' => 'POST',
                'path'   => '/password/forgot',
                'auth'   => false,
                'roles'  => [],
                'handler'=> [LoginController::class, 'postForgotPassword'],
            ],
            [
                'method' => 'GET',
                'path'   => '/password/reset',
                'auth'   => false,
                'roles'  => [],
                'handler'=> [LoginController::class, 'getResetPassword'],
            ],
            [
                'method' => 'POST',
                'path'   => '/password/reset',
                'auth'   => false,
                'roles'  => [],
                'handler'=> [LoginController::class, 'postResetPassword'],
            ],
            // ── Multi-role: výběr role po loginu (jen pro multi-role users) ──
            [
                'method' => 'GET',
                'path'   => '/login/select-role',
                'auth'   => true,
                'roles'  => ['superadmin', 'majitel', 'navolavacka', 'obchodak', 'backoffice', 'cisticka'],
                'handler'=> [LoginController::class, 'getSelectRole'],
            ],
            [
                'method' => 'POST',
                'path'   => '/login/select-role',
                'auth'   => true,
                'roles'  => ['superadmin', 'majitel', 'navolavacka', 'obchodak', 'backoffice', 'cisticka'],
                'handler'=> [LoginController::class, 'postSelectRole'],
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
            // ── Profil: 2FA setup / disable / backup codes ──
            [
                'method' => 'GET',
                'path' => '/profile/2fa/setup',
                'auth' => true,
                'roles' => ['superadmin', 'majitel', 'navolavacka', 'obchodak', 'backoffice', 'cisticka'],
                'handler' => [ProfileController::class, 'getSetup'],
            ],
            [
                'method' => 'POST',
                'path' => '/profile/2fa/setup',
                'auth' => true,
                'roles' => ['superadmin', 'majitel', 'navolavacka', 'obchodak', 'backoffice', 'cisticka'],
                'handler' => [ProfileController::class, 'postSetup'],
            ],
            [
                'method' => 'GET',
                'path' => '/profile/2fa/done',
                'auth' => true,
                'roles' => ['superadmin', 'majitel', 'navolavacka', 'obchodak', 'backoffice', 'cisticka'],
                'handler' => [ProfileController::class, 'getDone'],
            ],
            [
                'method' => 'GET',
                'path' => '/profile/2fa/disable',
                'auth' => true,
                'roles' => ['superadmin', 'majitel', 'navolavacka', 'obchodak', 'backoffice', 'cisticka'],
                'handler' => [ProfileController::class, 'getDisable'],
            ],
            [
                'method' => 'POST',
                'path' => '/profile/2fa/disable',
                'auth' => true,
                'roles' => ['superadmin', 'majitel', 'navolavacka', 'obchodak', 'backoffice', 'cisticka'],
                'handler' => [ProfileController::class, 'postDisable'],
            ],
            [
                'method' => 'POST',
                'path' => '/profile/2fa/revoke-all',
                'auth' => true,
                'roles' => ['superadmin', 'majitel', 'navolavacka', 'obchodak', 'backoffice', 'cisticka'],
                'handler' => [ProfileController::class, 'postRevokeAll'],
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
                'method' => 'POST',
                'path' => '/admin/users/impersonate',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminUsersController::class, 'postImpersonate'],
            ],
            [
                'method' => 'GET',
                'path' => '/admin/users/impersonate-stop',
                'auth' => true,
                // Note: bez "roles" requirementu — impersonate-stop musí fungovat
                // i když je admin přepnutý do non-admin role
                'handler' => [AdminUsersController::class, 'getImpersonateStop'],
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
                'path' => '/admin/datagrid/edit-options',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminDatagridController::class, 'getEditOptions'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/datagrid/update',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminDatagridController::class, 'postUpdate'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/datagrid/bulk',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminDatagridController::class, 'postBulk'],
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
            [
                'method' => 'POST',
                'path' => '/admin/cisticka-goals/copy-prev',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [CistickaController::class, 'postAdminGoalsCopyPrev'],
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
            // Per-telefon ověření (nový — kontakt s víc telefony)
            [
                'method' => 'POST',
                'path' => '/cisticka/verify-phone',
                'auth' => true,
                'roles' => ['cisticka', 'majitel', 'superadmin'],
                'handler' => [CistickaController::class, 'postVerifyPhone'],
            ],
            // Jednorázový resync contact_phones (bezpečný — zachová operátory)
            [
                'method' => 'POST',
                'path' => '/admin/maintenance/resync-phones',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminDatagridController::class, 'postResyncPhones'],
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
            // ── Kampaně (sázky + budoucí objednávky) ──
            [
                'method' => 'GET',
                'path' => '/caller/campaigns',
                'auth' => true,
                'roles' => ['navolavacka', 'majitel', 'superadmin'],
                'handler' => [CallerCampaignsController::class, 'getIndex'],
            ],
            [
                'method' => 'POST',
                'path' => '/caller/campaigns/lock',
                'auth' => true,
                'roles' => ['navolavacka', 'majitel', 'superadmin'],
                'handler' => [CallerCampaignsController::class, 'postLock'],
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
            // ── Admin: Sázky (bet_campaigns) ──
            [
                'method' => 'GET',
                'path' => '/admin/bet',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminBetController::class, 'getIndex'],
            ],
            [
                'method' => 'GET',
                'path' => '/admin/bet/new',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminBetController::class, 'getNew'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/bet/create',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminBetController::class, 'postCreate'],
            ],
            [
                'method' => 'GET',
                'path' => '/admin/bet/show',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminBetController::class, 'getShow'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/bet/close',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminBetController::class, 'postClose'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/bet/cancel',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminBetController::class, 'postCancel'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/bet/add-caller',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminBetController::class, 'postAddCaller'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/bet/remove-caller',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminBetController::class, 'postRemoveCaller'],
            ],
            // ── OZ: Email leady ze sázek (delivery_type='email') ──
            [
                'method' => 'GET',
                'path' => '/oz/email-leads',
                'auth' => true,
                'roles' => ['obchodak', 'majitel', 'superadmin'],
                'handler' => [OzEmailLeadsController::class, 'getIndex'],
            ],
            [
                'method' => 'GET',
                'path' => '/oz/email-leads/export',
                'auth' => true,
                'roles' => ['obchodak', 'majitel', 'superadmin'],
                'handler' => [OzEmailLeadsController::class, 'getExport'],
            ],
            // ── OZ: dashboard kampaní s progresem a úspěšností ──
            [
                'method' => 'GET',
                'path' => '/oz/campaigns',
                'auth' => true,
                'roles' => ['obchodak', 'majitel', 'superadmin'],
                'handler' => [OzCampaignsController::class, 'getIndex'],
            ],
            // ── Záchrana leadu ──
            [
                'method' => 'POST',
                'path' => '/oz/contact/rescue',
                'auth' => true,
                'roles' => ['obchodak', 'majitel', 'superadmin'],
                'handler' => [RescueController::class, 'postCreate'],
            ],
            [
                'method' => 'POST',
                'path' => '/caller/rescue/status',
                'auth' => true,
                'roles' => ['navolavacka', 'majitel', 'superadmin'],
                'handler' => [RescueController::class, 'postCallerStatus'],
            ],
            [
                'method' => 'GET',
                'path' => '/admin/rescue',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [RescueController::class, 'getAdminIndex'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/rescue/mark-paid',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [RescueController::class, 'postMarkPaid'],
            ],
            // ── Recyklace kontaktů (vrátit VF/NEZAJEM do oběhu) ──
            [
                'method' => 'GET',
                'path' => '/admin/contacts/recycle',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminContactRecycleController::class, 'getIndex'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/contacts/recycle',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminContactRecycleController::class, 'postExecute'],
            ],
            // ── Bezpečné mazání kontaktů (s filtry + CSV backup) ──
            [
                'method' => 'GET',
                'path' => '/admin/contacts/delete',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminContactsDeleteController::class, 'getIndex'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/contacts/delete/preview',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminContactsDeleteController::class, 'postPreview'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/contacts/delete/csv',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminContactsDeleteController::class, 'postCsv'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/contacts/delete/execute',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminContactsDeleteController::class, 'postExecute'],
            ],
            // ── Mix kontaktů (1:10 firma:OSVČ) ──
            [
                'method' => 'GET',
                'path' => '/admin/contacts/mix',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminContactMixController::class, 'getIndex'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/contacts/mix/execute',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminContactMixController::class, 'postExecute'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/contacts/mix/settings',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminContactMixController::class, 'postSettings'],
            ],
            [
                'method' => 'POST',
                'path' => '/admin/contacts/mix/reclassify',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [AdminContactMixController::class, 'postReclassify'],
            ],
            // ── Nápověda (in-app dokumentace) ──
            [
                'method' => 'GET',
                'path' => '/help',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [HelpController::class, 'getIndex'],
            ],
            [
                'method' => 'GET',
                'path' => '/help/topic',
                'auth' => true,
                'roles' => ['majitel', 'superadmin'],
                'handler' => [HelpController::class, 'getDetail'],
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
            // ── OZ: vyhledávání kontaktů (search + karta + převzít) ──
            [
                'method' => 'GET',
                'path' => '/oz/search',
                'auth' => true,
                'roles' => ['obchodak', 'majitel', 'superadmin'],
                'handler' => [OzSearchController::class, 'getIndex'],
            ],
            [
                'method' => 'GET',
                'path' => '/oz/search/card',
                'auth' => true,
                'roles' => ['obchodak', 'majitel', 'superadmin'],
                'handler' => [OzSearchController::class, 'getCard'],
            ],
            [
                'method' => 'POST',
                'path' => '/oz/search/note',
                'auth' => true,
                'roles' => ['obchodak', 'majitel', 'superadmin'],
                'handler' => [OzSearchController::class, 'postNote'],
            ],
            [
                'method' => 'POST',
                'path' => '/oz/search/takeover',
                'auth' => true,
                'roles' => ['obchodak', 'majitel', 'superadmin'],
                'handler' => [OzSearchController::class, 'postTakeover'],
            ],
            [
                'method' => 'POST',
                'path' => '/oz/search/edit',
                'auth' => true,
                'roles' => ['obchodak', 'majitel', 'superadmin'],
                'handler' => [OzSearchController::class, 'postEdit'],
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
            // AJAX duplicita check (kdokoli z form rolí) — vrátí JSON
            [
                'method' => 'GET',
                'path'   => '/contacts/check-ico',
                'auth'   => true,
                'roles'  => ['navolavacka', 'cisticka', 'obchodak', 'backoffice', 'majitel', 'superadmin'],
                'handler'=> [ContactProposalsController::class, 'getCheckIco'],
            ],
            // Admin přehled: nedávno přidané kontakty (kdo kdy co)
            [
                'method' => 'GET',
                'path'   => '/admin/contacts/added',
                'auth'   => true,
                'roles'  => ['majitel', 'superadmin'],
                'handler'=> [ContactProposalsController::class, 'getAdminRecentAdditions'],
            ],
            // Per-uživatel přehled: moje doporučenky (caller/cisticka/oz/BO)
            [
                'method' => 'GET',
                'path'   => '/me/added-contacts',
                'auth'   => true,
                'roles'  => ['navolavacka', 'cisticka', 'obchodak', 'backoffice', 'majitel', 'superadmin'],
                'handler'=> [ContactProposalsController::class, 'getMyAdditions'],
            ],
            // Read-only detail mojí doporučenky (security: jen own + admin)
            [
                'method' => 'GET',
                'path'   => '/me/contact-detail',
                'auth'   => true,
                'roles'  => ['navolavacka', 'cisticka', 'obchodak', 'backoffice', 'majitel', 'superadmin'],
                'handler'=> [ContactProposalsController::class, 'getMyContactDetail'],
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
            // ════════════════════════════════════════════════════════════════
            //  PREMIUM PIPELINE — objednávky druhého čištění (OZ side)
            //  Fáze 2 implementace. Čistička / navolávačka / stats: další fáze.
            // ════════════════════════════════════════════════════════════════
            [
                'method' => 'GET',
                'path'   => '/oz/premium',
                'auth'   => true,
                'roles'  => ['obchodak', 'majitel', 'superadmin'],
                'handler'=> [PremiumOrderController::class, 'getIndex'],
            ],
            [
                'method' => 'GET',
                'path'   => '/oz/premium/new',
                'auth'   => true,
                'roles'  => ['obchodak', 'majitel', 'superadmin'],
                'handler'=> [PremiumOrderController::class, 'getNew'],
            ],
            [
                'method' => 'POST',
                'path'   => '/oz/premium/create',
                'auth'   => true,
                'roles'  => ['obchodak', 'majitel', 'superadmin'],
                'handler'=> [PremiumOrderController::class, 'postCreate'],
            ],
            [
                'method' => 'POST',
                'path'   => '/oz/premium/cancel',
                'auth'   => true,
                'roles'  => ['obchodak', 'majitel', 'superadmin'],
                'handler'=> [PremiumOrderController::class, 'postCancel'],
            ],
            [
                'method' => 'POST',
                'path'   => '/oz/premium/close',
                'auth'   => true,
                'roles'  => ['obchodak', 'cisticka', 'majitel', 'superadmin'],
                'handler'=> [PremiumOrderController::class, 'postClose'],
            ],
            [
                'method' => 'POST',
                'path'   => '/cisticka/premium/close',
                'auth'   => true,
                'roles'  => ['cisticka', 'majitel', 'superadmin'],
                'handler'=> [PremiumOrderController::class, 'postClose'],
            ],
            [
                'method' => 'POST',
                'path'   => '/oz/premium/mark-paid',
                'auth'   => true,
                'roles'  => ['obchodak', 'majitel', 'superadmin'],
                'handler'=> [PremiumOrderController::class, 'postMarkPaid'],
            ],
            // ── Premium pipeline (čistička side) — pracovní plocha 2 ──
            [
                'method' => 'GET',
                'path'   => '/cisticka/premium',
                'auth'   => true,
                'roles'  => ['cisticka', 'majitel', 'superadmin'],
                'handler'=> [PremiumCistickaController::class, 'getIndex'],
            ],
            [
                'method' => 'GET',
                'path'   => '/cisticka/premium/order',
                'auth'   => true,
                'roles'  => ['cisticka', 'majitel', 'superadmin'],
                'handler'=> [PremiumCistickaController::class, 'getOrder'],
            ],
            [
                'method' => 'POST',
                'path'   => '/cisticka/premium/verify',
                'auth'   => true,
                'roles'  => ['cisticka', 'majitel', 'superadmin'],
                'handler'=> [PremiumCistickaController::class, 'postVerify'],
            ],
            [
                'method' => 'POST',
                'path'   => '/cisticka/premium/undo',
                'auth'   => true,
                'roles'  => ['cisticka', 'majitel', 'superadmin'],
                'handler'=> [PremiumCistickaController::class, 'postUndo'],
            ],
            [
                'method' => 'POST',
                'path'   => '/cisticka/premium/accept',
                'auth'   => true,
                'roles'  => ['cisticka', 'majitel', 'superadmin'],
                'handler'=> [PremiumCistickaController::class, 'postAccept'],
            ],
            [
                'method' => 'GET',
                'path'   => '/cisticka/premium/payout/print',
                'auth'   => true,
                'roles'  => ['cisticka', 'majitel', 'superadmin'],
                'handler'=> [PremiumCistickaController::class, 'getPayoutPrint'],
            ],
            [
                'method' => 'GET',
                'path'   => '/oz/premium/payout/print',
                'auth'   => true,
                'roles'  => ['obchodak', 'majitel', 'superadmin'],
                'handler'=> [PremiumOrderController::class, 'getPayoutPrint'],
            ],
            // ── Premium pipeline (caller side) — pracovní plocha 2 navolávačky ──
            [
                'method' => 'GET',
                'path'   => '/caller/premium',
                'auth'   => true,
                'roles'  => ['navolavacka', 'majitel', 'superadmin'],
                'handler'=> [PremiumCallerController::class, 'getIndex'],
            ],
            [
                'method' => 'GET',
                'path'   => '/caller/premium/order',
                'auth'   => true,
                'roles'  => ['navolavacka', 'majitel', 'superadmin'],
                'handler'=> [PremiumCallerController::class, 'getOrder'],
            ],
            [
                'method' => 'POST',
                'path'   => '/caller/premium/status',
                'auth'   => true,
                'roles'  => ['navolavacka', 'majitel', 'superadmin'],
                'handler'=> [PremiumCallerController::class, 'postStatus'],
            ],
            [
                'method' => 'GET',
                'path'   => '/caller/premium/payout/print',
                'auth'   => true,
                'roles'  => ['navolavacka', 'majitel', 'superadmin'],
                'handler'=> [PremiumCallerController::class, 'getPayoutPrint'],
            ],
            // ── Admin: globální přehled premium pipeline ──
            [
                'method' => 'GET',
                'path'   => '/admin/premium-overview',
                'auth'   => true,
                'roles'  => ['majitel', 'superadmin'],
                'handler'=> [AdminPremiumOverviewController::class, 'getIndex'],
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
