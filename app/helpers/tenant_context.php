<?php
// e:\Snecinatripu\app\helpers\tenant_context.php
declare(strict_types=1);

/**
 * ════════════════════════════════════════════════════════════════════
 *  TENANT CONTEXT — multi-tenant scope helper
 * ════════════════════════════════════════════════════════════════════
 *
 *  Co to je:
 *    Centrální místo pro práci s "aktivní firmou" (tenant) v session.
 *    Každý uživatel má v session uložené `tenant_id` (jen jedno),
 *    podle kterého filtruje VŠECHNY DB queries.
 *
 *  Bezpečnostní pravidla (verbatim podle Aines):
 *    1) tenant_id MUSÍ být vždy validovaný proti DB (tabulka `tenants`)
 *    2) Všechny dotazy MUSÍ filtrovat přes tenant_id
 *    3) Subdoména nebo ?tenant=X = JEN určení kontextu, NE bezpečnostní vrstva
 *    4) Query param ?tenant=X funguje JEN v dev prostředí (CRM_APP_ENV !== 'production')
 *    5) Při loginu se ověří, že user má záznam v `user_tenants` pro daný tenant
 *
 *  Jak tenant_id putuje do session:
 *    PRODUKCE:
 *      - public/index.php zavolá crm_tenant_bootstrap() ještě před router dispatch
 *      - Z HTTP_HOST se vyloupne subdoména (např. "firma1" z "firma1.snecinatripu.eu")
 *      - Lookup v `tenants.subdomain` → tenant_id
 *      - Pokud subdoména neexistuje → 404
 *
 *    DEV (localhost):
 *      - Pokud ?tenant=firma1 v URL → použít a uložit do session
 *      - Jinak pokud session už má tenant_id → použít session value
 *      - Jinak default tenant_id = 1 (Moje firma)
 *
 *  Při loginu (crm_auth_finish_login):
 *      - Ověří že user má aktivní záznam v `user_tenants` pro aktivní tenant_id
 *      - Pokud nemá → login se odmítne (i super-admin musí mít user_tenants pro
 *        konkrétní firmu, aby v ní mohl pracovat; super-admin flag jen
 *        rozšiřuje schopnost vidět přes všechny firmy)
 *
 *  Super-admin:
 *      - Záznam v `super_admins` tabulce
 *      - V SQL filtru se nepřeskakuje (i super-admin pracuje pod aktivním
 *        tenant_id), ale má dodatečné UI pro přepínání mezi firmami
 *      - super_admin flag v session se nastavuje při loginu
 * ════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'session.php';

// ─── Session klíče ───────────────────────────────────────────────────
if (!defined('CRM_SESSION_TENANT_ID')) {
    define('CRM_SESSION_TENANT_ID', 'crm_tenant_id');
}

if (!defined('CRM_SESSION_SUPER_ADMIN')) {
    define('CRM_SESSION_SUPER_ADMIN', 'crm_super_admin');
}

if (!defined('CRM_DEFAULT_TENANT_ID')) {
    // Fallback v dev prostředí, když není ?tenant=X v URL.
    define('CRM_DEFAULT_TENANT_ID', 1);
}

if (!defined('CRM_TENANT_QUERY_PARAM')) {
    define('CRM_TENANT_QUERY_PARAM', 'tenant');
}

if (!defined('CRM_TENANT_ROOT_DOMAIN')) {
    // Pomocné — část za první tečkou.
    // 'firma1.snecinatripu.eu' → root = 'snecinatripu.eu' → subdomain = 'firma1'
    // Pro localhost se subdoména neuplatňuje (jen ?tenant=X v dev).
    define('CRM_TENANT_ROOT_DOMAIN', (string) (getenv('CRM_TENANT_ROOT_DOMAIN') ?: 'snecinatripu.eu'));
}

// ═════════════════════════════════════════════════════════════════════
//  Helper: jsme v dev prostředí?
// ═════════════════════════════════════════════════════════════════════
if (!function_exists('crm_tenant_is_dev')) {
    function crm_tenant_is_dev(): bool
    {
        return defined('CRM_APP_ENV') && CRM_APP_ENV !== 'production';
    }
}

// ═════════════════════════════════════════════════════════════════════
//  Helper: vytáhne subdoménu z hosta
//  Příklad:
//    'firma1.snecinatripu.eu' → 'firma1'
//    'www.snecinatripu.eu'    → 'www'
//    'snecinatripu.eu'        → null   (root doména bez subdomény)
//    'localhost'              → null   (dev)
//    'localhost:8080'         → null
// ═════════════════════════════════════════════════════════════════════
if (!function_exists('crm_tenant_extract_subdomain')) {
    function crm_tenant_extract_subdomain(string $host): ?string
    {
        // Odřízneme port, pokud ho host obsahuje
        $host = strtolower(trim($host));
        if ($host === '') {
            return null;
        }
        $colonPos = strpos($host, ':');
        if ($colonPos !== false) {
            $host = substr($host, 0, $colonPos);
        }

        // Lokální / IP / interní hosty = bez subdomény
        if (
            $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === '::1'
            || filter_var($host, FILTER_VALIDATE_IP) !== false
        ) {
            return null;
        }

        $rootDomain = strtolower(CRM_TENANT_ROOT_DOMAIN);

        // Host neodpovídá rootdoméně → neumíme detekovat subdoménu
        if (!str_ends_with($host, $rootDomain)) {
            return null;
        }

        // Pokud je host === rootdomain (žádný prefix) → null
        if ($host === $rootDomain) {
            return null;
        }

        // Vystřihni prefix bez root domény ('firma1.snecinatripu.eu' → 'firma1.')
        $prefix = substr($host, 0, strlen($host) - strlen($rootDomain));
        $prefix = rtrim($prefix, '.');
        if ($prefix === '') {
            return null;
        }

        // 'firma1.poddomena' (víc úrovní) → vezmi první segment
        $parts = explode('.', $prefix);
        $sub = trim($parts[0]);

        // Bezpečnostní whitelist znaků (subdomény validujeme přísně)
        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,99}$/', $sub)) {
            return null;
        }

        return $sub;
    }
}

// ═════════════════════════════════════════════════════════════════════
//  Helper: subdoména k vyloučení (www, app rezervujeme pro hlavní CRM)
//  app.snecinatripu.eu = hlavní CRM doména, ne tenant slug
//  Vlastně v naší DB má tenant 1 subdomain='app' — takže to JE tenant.
//  Tahle funkce vrací TRUE pro subdomény, které NESMÍ být tenant slug
//  (rezervované pro infrastrukturu).
// ═════════════════════════════════════════════════════════════════════
if (!function_exists('crm_tenant_is_reserved_subdomain')) {
    function crm_tenant_is_reserved_subdomain(string $sub): bool
    {
        $reserved = ['www', 'admin', 'mail', 'api', 'static', 'cdn', 'public'];
        return in_array(strtolower($sub), $reserved, true);
    }
}

// ═════════════════════════════════════════════════════════════════════
//  Helper: query param ?tenant=X (JEN dev!)
// ═════════════════════════════════════════════════════════════════════
if (!function_exists('crm_tenant_extract_from_query')) {
    function crm_tenant_extract_from_query(): ?string
    {
        if (!crm_tenant_is_dev()) {
            return null; // produkce ignoruje query param
        }
        $raw = $_GET[CRM_TENANT_QUERY_PARAM] ?? null;
        if (!is_string($raw)) {
            return null;
        }
        $sub = strtolower(trim($raw));
        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,99}$/', $sub)) {
            return null;
        }
        return $sub;
    }
}

// ═════════════════════════════════════════════════════════════════════
//  Lookup tenanta podle subdomény (DB validace)
// ═════════════════════════════════════════════════════════════════════
if (!function_exists('crm_tenant_lookup_by_subdomain')) {
    /**
     * @return array<string,mixed>|null Vrátí tenant nebo null (neexistuje / neaktivní)
     */
    function crm_tenant_lookup_by_subdomain(PDO $pdo, string $subdomain): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, name, subdomain, plan_code, max_users, max_contacts, active
             FROM tenants WHERE subdomain = :s AND active = 1 LIMIT 1'
        );
        $stmt->execute(['s' => strtolower(trim($subdomain))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }
}

// ═════════════════════════════════════════════════════════════════════
//  Lookup tenanta podle ID (DB validace)
// ═════════════════════════════════════════════════════════════════════
if (!function_exists('crm_tenant_lookup_by_id')) {
    /**
     * @return array<string,mixed>|null
     */
    function crm_tenant_lookup_by_id(PDO $pdo, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $stmt = $pdo->prepare(
            'SELECT id, name, subdomain, plan_code, max_users, max_contacts, active
             FROM tenants WHERE id = :id AND active = 1 LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }
}

// ═════════════════════════════════════════════════════════════════════
//  Detekce tenanta z requestu (bootstrap)
//
//  Algoritmus:
//    1) Pokud je session už nastavená a host odpovídá → použij session
//    2) DEV: ?tenant=X má prioritu (přepne session)
//    3) PROD: subdoména z HTTP_HOST
//    4) DEV fallback: žádná subdoména, žádný ?tenant → CRM_DEFAULT_TENANT_ID
//
//  Návratová hodnota:
//    - int = tenant_id ověřený proti DB
//    - null = neumíme určit (např. produkce + neznámá subdoména)
// ═════════════════════════════════════════════════════════════════════
if (!function_exists('crm_tenant_resolve_from_request')) {
    function crm_tenant_resolve_from_request(PDO $pdo): ?int
    {
        $isDev = crm_tenant_is_dev();
        $host  = (string) ($_SERVER['HTTP_HOST'] ?? '');

        // ── DEV: ?tenant=X (přepíše session) ────────────────────────
        if ($isDev) {
            $fromQuery = crm_tenant_extract_from_query();
            if ($fromQuery !== null) {
                $tenant = crm_tenant_lookup_by_subdomain($pdo, $fromQuery);
                if ($tenant !== null) {
                    return (int) $tenant['id'];
                }
                // ?tenant=X ale neexistuje → v dev se neselže, jen warning a fallback
                error_log('[TenantContext] Dev ?tenant=' . $fromQuery . ' neexistuje, fallback na default.');
            }
        }

        // ── Subdoména z HTTP_HOST (produkce primárně) ───────────────
        $sub = crm_tenant_extract_subdomain($host);
        if ($sub !== null && !crm_tenant_is_reserved_subdomain($sub)) {
            $tenant = crm_tenant_lookup_by_subdomain($pdo, $sub);
            if ($tenant !== null) {
                return (int) $tenant['id'];
            }
            if (!$isDev) {
                // Produkce: subdoména existuje v URL ale ne v DB → neznámý tenant
                return null;
            }
        }

        // ── Session už má hodnotu? Pak ji použij ────────────────────
        crm_session_start();
        $sessTid = (int) ($_SESSION[CRM_SESSION_TENANT_ID] ?? 0);
        if ($sessTid > 0) {
            $tenant = crm_tenant_lookup_by_id($pdo, $sessTid);
            if ($tenant !== null) {
                return (int) $tenant['id'];
            }
        }

        // ── Fallback: žádná subdoména (apex doména / www) → primární firma.
        //    Pozor: NEZNÁMÁ subdoména v produkci sem nedojde — ta vrací null
        //    už výše (→ 404). Sem se dostaneme jen když host nemá použitelnou
        //    subdoménu (např. holá doména snecinatripu.eu nebo www), případně
        //    v dev bez ?tenant. V takovém případě servírujeme hlavní firmu.
        $tenant = crm_tenant_lookup_by_id($pdo, CRM_DEFAULT_TENANT_ID);
        if ($tenant !== null) {
            return (int) $tenant['id'];
        }

        return null;
    }
}

// ═════════════════════════════════════════════════════════════════════
//  Check: má user záznam v user_tenants pro daný tenant?
// ═════════════════════════════════════════════════════════════════════
if (!function_exists('crm_tenant_user_has_access')) {
    function crm_tenant_user_has_access(PDO $pdo, int $userId, int $tenantId): bool
    {
        if ($userId <= 0 || $tenantId <= 0) {
            return false;
        }
        $stmt = $pdo->prepare(
            'SELECT 1 FROM user_tenants
             WHERE user_id = :u AND tenant_id = :t AND active = 1
             LIMIT 1'
        );
        $stmt->execute(['u' => $userId, 't' => $tenantId]);
        return $stmt->fetchColumn() !== false;
    }
}

// ═════════════════════════════════════════════════════════════════════
//  Check: je user super-admin (globální root)?
// ═════════════════════════════════════════════════════════════════════
if (!function_exists('crm_tenant_user_is_super_admin')) {
    function crm_tenant_user_is_super_admin(PDO $pdo, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        $stmt = $pdo->prepare(
            'SELECT 1 FROM super_admins WHERE user_id = :u LIMIT 1'
        );
        $stmt->execute(['u' => $userId]);
        return $stmt->fetchColumn() !== false;
    }
}

// ═════════════════════════════════════════════════════════════════════
//  Setter — uloží tenant_id + super_admin flag do session
//  (volat z crm_auth_finish_login)
// ═════════════════════════════════════════════════════════════════════
if (!function_exists('crm_tenant_set')) {
    function crm_tenant_set(int $tenantId, bool $isSuperAdmin = false): void
    {
        crm_session_start();
        $_SESSION[CRM_SESSION_TENANT_ID]   = $tenantId;
        $_SESSION[CRM_SESSION_SUPER_ADMIN] = $isSuperAdmin ? 1 : 0;
    }
}

// ═════════════════════════════════════════════════════════════════════
//  Getter — vrátí aktivní tenant_id ze session
//  POZOR: nezavolá DB, neověřuje. Pro běžné dotazy stačí session value
//  protože jsme ji validovali při loginu / bootstrap. Pro jistotu jen
//  kontroluje >0.
// ═════════════════════════════════════════════════════════════════════
if (!function_exists('crm_tenant_id')) {
    function crm_tenant_id(): int
    {
        crm_session_start();
        $id = (int) ($_SESSION[CRM_SESSION_TENANT_ID] ?? 0);
        return $id > 0 ? $id : 0;
    }
}

// ═════════════════════════════════════════════════════════════════════
//  Getter — je user v session super-admin?
// ═════════════════════════════════════════════════════════════════════
if (!function_exists('crm_tenant_is_super_admin')) {
    function crm_tenant_is_super_admin(): bool
    {
        crm_session_start();
        return (int) ($_SESSION[CRM_SESSION_SUPER_ADMIN] ?? 0) === 1;
    }
}

// ═════════════════════════════════════════════════════════════════════
//  Cleanup — při logoutu
// ═════════════════════════════════════════════════════════════════════
if (!function_exists('crm_tenant_clear')) {
    function crm_tenant_clear(): void
    {
        crm_session_start();
        unset(
            $_SESSION[CRM_SESSION_TENANT_ID],
            $_SESSION[CRM_SESSION_SUPER_ADMIN]
        );
    }
}

// ═════════════════════════════════════════════════════════════════════
//  Bootstrap — voláno z public/index.php před router dispatch
//
//  Co dělá:
//    1) Vyřeší aktivní tenant_id z requestu (subdoména / ?tenant / default)
//    2) Pokud session má jiný tenant_id než resolved → CROSS-TENANT
//       situace (user přepnul firmu nebo otevřel jinou subdoménu)
//       → zahodíme login a vyžádáme nové přihlášení
//    3) Pokud nikdo není přihlášený, jen uložíme tenant_id do session
//
//  Pokud v produkci nelze určit tenant_id (neznámá subdoména) → 404.
// ═════════════════════════════════════════════════════════════════════
if (!function_exists('crm_tenant_bootstrap')) {
    function crm_tenant_bootstrap(PDO $pdo): void
    {
        $resolved = crm_tenant_resolve_from_request($pdo);

        // V produkci na neznámé subdoméně → 404
        if ($resolved === null && !crm_tenant_is_dev()) {
            http_response_code(404);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!DOCTYPE html><html lang="cs"><head><meta charset="UTF-8">'
                . '<title>Firma nenalezena</title></head><body>'
                . '<h1>Firma nenalezena</h1>'
                . '<p>Tato subdoména není přiřazena k žádné aktivní firmě.</p>'
                . '</body></html>';
            exit;
        }

        // Dev a stále null → fatal (default tenant 1 musí existovat)
        if ($resolved === null) {
            error_log('[TenantContext] FATAL: nelze určit tenant_id ani v dev (chybí tenant id=1?)');
            return;
        }

        crm_session_start();
        $sessTid = (int) ($_SESSION[CRM_SESSION_TENANT_ID] ?? 0);
        $sessUid = (int) ($_SESSION[CRM_SESSION_USER_ID] ?? 0);

        // Cross-tenant detekce: user je přihlášený, ale otevřel jinou subdoménu
        // než pro kterou má session. Bezpečné je odhlásit, ne tiše přepnout.
        if ($sessUid > 0 && $sessTid > 0 && $sessTid !== $resolved) {
            // V dev s ?tenant=X chceme rychlé přepínání, takže místo logoutu
            // jen znovu ověříme přístup. V produkci je to však bezpečnostní událost.
            if (crm_tenant_is_dev()) {
                if (crm_tenant_user_has_access($pdo, $sessUid, $resolved)) {
                    crm_tenant_set(
                        $resolved,
                        crm_tenant_user_is_super_admin($pdo, $sessUid)
                    );
                    return;
                }
                // Nemá přístup → odhlásit (i v dev)
            }
            // Produkce nebo dev bez přístupu: zahodit přihlášení
            error_log(sprintf(
                '[TenantContext] Cross-tenant: user_id=%d session_tid=%d resolved=%d → logout',
                $sessUid,
                $sessTid,
                $resolved
            ));
            if (function_exists('crm_auth_logout')) {
                crm_auth_logout($pdo);
            } else {
                crm_tenant_clear();
                unset($_SESSION[CRM_SESSION_USER_ID]);
            }
            // Po logoutu nechej resolve aktualizovat tenant_id
        }

        // Standardní cesta: ulož tenant do session
        $_SESSION[CRM_SESSION_TENANT_ID] = $resolved;
    }
}

// ═════════════════════════════════════════════════════════════════════
//  SQL pomocníci
//
//  Použití v existujícím kódu (postupný refactor):
//
//    $where = crm_tenant_where_sql('c');
//    $sql = "SELECT * FROM contacts c WHERE c.aktivni = 1 {$where}";
//    $stmt = $pdo->prepare($sql);
//    crm_tenant_bind_params($stmt);
//    $stmt->execute();
//
//  Nebo přes array:
//    $params = ['stav' => 'NEW'];
//    crm_tenant_bind_array($params);
//    $stmt->execute($params);
// ═════════════════════════════════════════════════════════════════════
if (!function_exists('crm_tenant_where_sql')) {
    /**
     * Vrátí SQL fragment " AND `alias`.`tenant_id` = :crm_tenant_id".
     * Pokud chceš tabulku bez aliasu, zavolej s prázdným stringem ''.
     *
     * Pozn.: I super-admin pracuje pod konkrétním tenant_id. Pokud
     * potřebuje cross-tenant view, musí explicitně přepnout firmu
     * (admin konzole).
     */
    function crm_tenant_where_sql(string $alias = ''): string
    {
        $prefix = $alias !== '' ? "`{$alias}`." : '';
        return ' AND ' . $prefix . '`tenant_id` = :crm_tenant_id ';
    }
}

if (!function_exists('crm_tenant_bind_params')) {
    /**
     * Naváže :crm_tenant_id na prepared statement.
     */
    function crm_tenant_bind_params(PDOStatement $stmt): void
    {
        $stmt->bindValue(':crm_tenant_id', crm_tenant_id(), PDO::PARAM_INT);
    }
}

if (!function_exists('crm_tenant_bind_array')) {
    /**
     * Přidá tenant_id do array params (pokud používáš execute($params)).
     */
    function crm_tenant_bind_array(array &$params): void
    {
        $params['crm_tenant_id'] = crm_tenant_id();
    }
}

if (!function_exists('crm_tenant_insert_data')) {
    /**
     * Vrátí ['tenant_id' => N] pro merge do INSERT pole.
     * Použití:
     *   $data = ['firma_nazev' => 'X', 'stav' => 'NEW'] + crm_tenant_insert_data();
     */
    function crm_tenant_insert_data(): array
    {
        return ['tenant_id' => crm_tenant_id()];
    }
}

if (!function_exists('crm_tenant_must_have')) {
    /**
     * Bezpečnostní guard: kdykoli se queryuje pod tenant_id, MUSÍ existovat v session.
     * Pokud chybí (npr. session expired, bootstrap failed), throw exception.
     * Tím se zabrání tichému SELECTu s tenant_id = 0 (= nic by se nevrátilo,
     * ale stále bezpečnější je zhroucení než tichý "no data" stav).
     */
    function crm_tenant_must_have(): int
    {
        $tid = crm_tenant_id();
        if ($tid <= 0) {
            throw new RuntimeException(
                'Tenant kontext chybí. Buď není přihlášený uživatel, '
                . 'nebo session vypršela. Bezpečnostní zastavení.'
            );
        }
        return $tid;
    }
}

if (!function_exists('crm_tenant_filter_array')) {
    /**
     * Helper pro vkládání tenant_id do existujícího pole parametrů.
     * Příklad:
     *   $params = ['stav' => 'NEW'];
     *   $params = crm_tenant_filter_array($params);
     *   $stmt->execute($params);
     *
     * Použito tam, kde se chce zachovat fluent assignment.
     */
    function crm_tenant_filter_array(array $params): array
    {
        $params['crm_tenant_id'] = crm_tenant_id();
        return $params;
    }
}

// ═════════════════════════════════════════════════════════════════════
//  BILLING & LIMITS — helpery pro plány, limity a kontrolu využití
// ═════════════════════════════════════════════════════════════════════

if (!function_exists('crm_tenant_get_full')) {
    /**
     * Vrátí celý záznam tenants pro $tenantId. Cached per-request.
     *
     * @return array<string,mixed>|null
     */
    function crm_tenant_get_full(PDO $pdo, int $tenantId): ?array
    {
        static $cache = [];
        if (isset($cache[$tenantId])) {
            return $cache[$tenantId];
        }
        $stmt = $pdo->prepare('SELECT * FROM tenants WHERE id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $cache[$tenantId] = $row;
        return $row;
    }
}

if (!function_exists('crm_tenant_get_usage')) {
    /**
     * Spočítá aktuální využití per resource pro daný tenant.
     *
     * @return array{
     *   users_active:int, users_max:?int,
     *   contacts_total:int, contacts_max:?int,
     *   premium_orders_this_month:int, premium_orders_max:?int,
     * }
     */
    function crm_tenant_get_usage(PDO $pdo, int $tenantId): array
    {
        $tenant = crm_tenant_get_full($pdo, $tenantId);
        if ($tenant === null) {
            return [
                'users_active' => 0, 'users_max' => 0,
                'contacts_total' => 0, 'contacts_max' => 0,
                'premium_orders_this_month' => 0, 'premium_orders_max' => 0,
            ];
        }

        // Active users in this tenant (přes user_tenants)
        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM user_tenants ut
              JOIN users u ON u.id = ut.user_id
             WHERE ut.tenant_id = ? AND ut.active = 1 AND u.aktivni = 1'
        );
        $st->execute([$tenantId]);
        $usersActive = (int) $st->fetchColumn();

        // Contacts total
        $st = $pdo->prepare('SELECT COUNT(*) FROM contacts WHERE tenant_id = ?');
        $st->execute([$tenantId]);
        $contactsTotal = (int) $st->fetchColumn();

        // Premium orders this month (created_at v aktuálním měsíci)
        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM premium_orders
             WHERE tenant_id = ?
               AND YEAR(created_at) = YEAR(CURDATE())
               AND MONTH(created_at) = MONTH(CURDATE())'
        );
        $st->execute([$tenantId]);
        $premiumThisMonth = (int) $st->fetchColumn();

        // Limity z tenants (nebo NULL = unlimited)
        $usersMax    = $tenant['max_users']    !== null ? (int) $tenant['max_users']    : null;
        $contactsMax = $tenant['max_contacts'] !== null ? (int) $tenant['max_contacts'] : null;
        $premiumMax  = $tenant['max_premium_orders_per_month'] !== null
                     ? (int) $tenant['max_premium_orders_per_month'] : null;

        return [
            'users_active'              => $usersActive,
            'users_max'                 => $usersMax,
            'contacts_total'            => $contactsTotal,
            'contacts_max'              => $contactsMax,
            'premium_orders_this_month' => $premiumThisMonth,
            'premium_orders_max'        => $premiumMax,
        ];
    }
}

if (!function_exists('crm_tenant_limit_status')) {
    /**
     * Vrátí stav limitu pro jeden resource — soft warning logika.
     *
     * Resource: 'users' | 'contacts' | 'premium_orders'
     *
     * Návrat:
     *   ['count' => N, 'max' => M|null, 'percent' => 0-100|null,
     *    'status' => 'ok'|'warning'|'over'|'unlimited',
     *    'label' => 'Lidský popis']
     */
    function crm_tenant_limit_status(PDO $pdo, int $tenantId, string $resource): array
    {
        $usage = crm_tenant_get_usage($pdo, $tenantId);
        $map = [
            'users'          => ['count' => $usage['users_active'],              'max' => $usage['users_max'],         'label' => 'uživatelů'],
            'contacts'       => ['count' => $usage['contacts_total'],            'max' => $usage['contacts_max'],      'label' => 'kontaktů'],
            'premium_orders' => ['count' => $usage['premium_orders_this_month'], 'max' => $usage['premium_orders_max'],'label' => 'premium objednávek/měsíc'],
        ];
        if (!isset($map[$resource])) {
            return ['count' => 0, 'max' => null, 'percent' => null, 'status' => 'unlimited', 'label' => $resource];
        }
        $r = $map[$resource];
        if ($r['max'] === null) {
            return ['count' => (int) $r['count'], 'max' => null, 'percent' => null,
                    'status' => 'unlimited', 'label' => $r['label']];
        }
        $count = (int) $r['count'];
        $max   = (int) $r['max'];
        $pct   = $max > 0 ? min(100, (int) round($count * 100 / $max)) : 0;
        $status = $count >= $max ? 'over' : ($pct >= 80 ? 'warning' : 'ok');
        return ['count' => $count, 'max' => $max, 'percent' => $pct,
                'status' => $status, 'label' => $r['label']];
    }
}

if (!function_exists('crm_tenant_plans_active')) {
    /**
     * Vrátí aktivní plány z `tenant_plans`, seřazené podle sort_order.
     * @return list<array<string,mixed>>
     */
    function crm_tenant_plans_active(PDO $pdo): array
    {
        try {
            $st = $pdo->query(
                'SELECT id, slug, name, description, max_users, max_contacts,
                        max_premium_orders_per_month, monthly_price_czk, trial_days
                 FROM tenant_plans
                 WHERE active = 1
                 ORDER BY sort_order ASC, id ASC'
            );
            return $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (\Throwable $_) {
            return [];
        }
    }
}

if (!function_exists('crm_tenant_apply_plan')) {
    /**
     * Aplikuje plán na tenant — nastaví limity podle katalogu.
     * Pokud plán má trial_days > 0 a tenant ještě trial neměl,
     * nastaví trial_ends_at = NOW + trial_days.
     * Vrací true při úspěchu.
     */
    function crm_tenant_apply_plan(PDO $pdo, int $tenantId, string $planSlug): bool
    {
        $p = $pdo->prepare('SELECT * FROM tenant_plans WHERE slug = ? LIMIT 1');
        $p->execute([$planSlug]);
        $plan = $p->fetch(PDO::FETCH_ASSOC);
        if (!$plan) return false;

        $trialDays = (int) ($plan['trial_days'] ?? 0);
        $stmt = $pdo->prepare(
            'UPDATE tenants
             SET plan_code = :slug,
                 max_users = :u,
                 max_contacts = :c,
                 max_premium_orders_per_month = :po,
                 monthly_price_czk = :price,
                 trial_ends_at = CASE
                    WHEN :td > 0 AND trial_ends_at IS NULL AND paid_until IS NULL
                        THEN DATE_ADD(NOW(), INTERVAL :td2 DAY)
                    ELSE trial_ends_at
                 END,
                 updated_at = NOW(3)
             WHERE id = :id'
        );
        $stmt->execute([
            'slug'  => $plan['slug'],
            'u'     => $plan['max_users'],
            'c'     => $plan['max_contacts'],
            'po'    => $plan['max_premium_orders_per_month'],
            'price' => $plan['monthly_price_czk'],
            'td'    => $trialDays,
            'td2'   => $trialDays,
            'id'    => $tenantId,
        ]);
        return true;
    }
}

if (!function_exists('crm_tenant_lifecycle_state')) {
    /**
     * Spočítá lifecycle stav tenanta z dat (active + paid_until + trial_ends_at).
     *
     * Návratové stavy:
     *   'unlimited'     — paid_until NULL & trial_ends_at NULL & active=1 (Enterprise / default)
     *   'trial'         — trial_ends_at > NOW, active=1
     *   'active'        — paid_until > NOW, active=1
     *   'grace'         — paid_until v rozsahu NOW-3d až NOW, active=1 (3denní grace period)
     *   'expired_paid'  — paid_until < NOW-3d, active=1 → mělo by být suspendováno
     *   'expired_trial' — trial_ends_at < NOW, paid_until NULL, active=1 → mělo by být suspendováno
     *   'suspended'     — active=0
     *
     * @return array{state:string, days_until_expiry:?int, can_login:bool}
     */
    function crm_tenant_lifecycle_state(PDO $pdo, int $tenantId): array
    {
        $tenant = crm_tenant_get_full($pdo, $tenantId);
        if ($tenant === null) {
            return ['state' => 'suspended', 'days_until_expiry' => null, 'can_login' => false];
        }

        $active     = (int) ($tenant['active'] ?? 0) === 1;
        $paidUntil  = !empty($tenant['paid_until'])   ? strtotime((string) $tenant['paid_until'])   : null;
        $trialEnds  = !empty($tenant['trial_ends_at']) ? strtotime((string) $tenant['trial_ends_at']) : null;
        $now        = time();
        $graceDays  = 3;
        $graceUntil = $paidUntil !== null ? $paidUntil + $graceDays * 86400 : null;

        if (!$active) {
            return ['state' => 'suspended', 'days_until_expiry' => null, 'can_login' => false];
        }

        // Unlimited: žádný paid_until ani trial → tenant 1 / Enterprise
        if ($paidUntil === null && $trialEnds === null) {
            return ['state' => 'unlimited', 'days_until_expiry' => null, 'can_login' => true];
        }

        // Trial running
        if ($trialEnds !== null && $trialEnds > $now && $paidUntil === null) {
            $days = (int) ceil(($trialEnds - $now) / 86400);
            return ['state' => 'trial', 'days_until_expiry' => $days, 'can_login' => true];
        }

        // Trial expired (a žádné placení)
        if ($trialEnds !== null && $trialEnds <= $now && $paidUntil === null) {
            return ['state' => 'expired_trial', 'days_until_expiry' => 0, 'can_login' => false];
        }

        // Paid still active
        if ($paidUntil !== null && $paidUntil > $now) {
            $days = (int) ceil(($paidUntil - $now) / 86400);
            return ['state' => 'active', 'days_until_expiry' => $days, 'can_login' => true];
        }

        // Grace period (paid_until prošlé, ale méně než 3 dny)
        if ($paidUntil !== null && $graceUntil !== null && $now <= $graceUntil) {
            $hoursLeft = max(0, (int) ceil(($graceUntil - $now) / 3600));
            return ['state' => 'grace', 'days_until_expiry' => (int) ceil($hoursLeft / 24), 'can_login' => true];
        }

        // Expired paid (více než 3 dny po splatnosti)
        return ['state' => 'expired_paid', 'days_until_expiry' => 0, 'can_login' => false];
    }
}

if (!function_exists('crm_tenant_should_auto_suspend')) {
    /**
     * Helper pro cron — vrátí true pokud tenant má být auto-suspendován teď.
     * Tj. state in [expired_paid, expired_trial] AND active=1.
     */
    function crm_tenant_should_auto_suspend(array $lifecycleState): bool
    {
        return in_array($lifecycleState['state'], ['expired_paid', 'expired_trial'], true);
    }
}
