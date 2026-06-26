<?php
// e:\Snecinatripu\app\lib\TenantAwarePDO.php
declare(strict_types=1);

/**
 * ════════════════════════════════════════════════════════════════════
 *  TENANT-AWARE PDO WRAPPER
 *
 *  Účel:
 *    Bezpečnostní vrstva, která automaticky doplňuje `tenant_id` do
 *    INSERTů na business tabulky a v dev módu loguje SELECT/UPDATE/DELETE,
 *    které ještě nefiltrují přes tenant_id. Tím sbíráme stopu, kde
 *    refactor v PHP ještě chybí.
 *
 *  Co dělá:
 *    1. INSERT INTO <tenant_table> (...) VALUES (...) → automaticky přidá
 *       sloupec `tenant_id` a hodnotu z crm_tenant_id().
 *    2. INSERT INTO <tenant_table> SET col=val → přidá ", tenant_id=:_t".
 *    3. Pokud INSERT už má `tenant_id` (explicitně v sloupcích) → no-op.
 *    4. INSERT ... SELECT → no-op + log warning (komplikované, nech ručně).
 *    5. SELECT/UPDATE/DELETE na tenant tabulku bez `tenant_id` → log warning
 *       (jen v dev módu; v produkci se nemoduluje, jen loguje).
 *
 *  Co NEDĚLÁ:
 *    - Nemodifikuje SELECT/UPDATE/DELETE. Tyto musí být refactorovány ručně.
 *      Wrapper jen loguje, aby šlo postupně dohledat.
 *    - Pokud tenant_id v session = 0 (legacy / CLI bez bootstrapu),
 *      neudělá auto-injection (vrátí původní SQL).
 *
 *  Princip:
 *    Tichá pomoc, ne tichá blokace. CRM funguje jak dřív, jen máme
 *    runtime audit a INSERT je defaultně bezpečný proti tenant=0.
 *
 *  Log:
 *    - error_log() standard PHP (jde na PHP error log)
 *    - Specifický prefix '[TenantPDO]'
 * ════════════════════════════════════════════════════════════════════
 */

final class TenantAwarePDO extends PDO
{
    /** Whitelist business tabulek, které dostaly tenant_id v migraci 032 */
    private const TENANT_TABLES = [
        'contacts', 'contact_phones', 'contact_notes', 'contact_proposals',
        'contact_oz_flags', 'contact_quality_ratings', 'contact_recycles',
        'oz_contact_actions', 'oz_contact_notes', 'oz_contact_offered_services',
        'oz_contact_offered_service_items', 'oz_contact_workflow',
        'workflow_log', 'audit_log', 'import_log', 'assignment_log', 'sms_log',
        'monthly_goals', 'daily_goals', 'cisticka_region_goals',
        'cisticka_rewards_config', 'caller_rewards_config',
        'commissions', 'commission_tiers_company', 'commission_tiers_sales',
        'monthly_salaries', 'oz_targets', 'oz_team_stages', 'oz_personal_milestones',
        'bet_campaigns', 'bet_campaign_recipients', 'bet_campaign_callers', 'bet_campaign_leads',
        'premium_orders', 'premium_lead_pool', 'rescue_requests',
        'app_settings', 'note_templates', 'dnc_list', 'alerts', 'announcements',
        'team_records', 'oz_tab_prefs', 'tickets', 'ticket_attachments',
    ];

    /**
     * Override PDO::prepare — projde SQL přes processor a vrátí prepared statement.
     */
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $processed = $this->processQuery($query);
        return parent::prepare($processed, $options);
    }

    /**
     * Override PDO::exec — projde SQL přes processor a exekuuje.
     */
    public function exec(string $statement): int|false
    {
        $processed = $this->processQuery($statement);
        return parent::exec($processed);
    }

    /**
     * Override PDO::query — projde SQL přes processor a exekuuje.
     */
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $processed = $this->processQuery($query);
        if ($fetchMode === null) {
            return parent::query($processed);
        }
        return parent::query($processed, $fetchMode, ...$fetchModeArgs);
    }

    /**
     * Centrální entry point — rozpozná INSERT vs ostatní operace a předá dál.
     */
    private function processQuery(string $sql): string
    {
        // Bezpečnostní pojistka: bez tenant kontextu nic nemodifikujeme
        // (např. CLI skripty před bootstrapem).
        $tenantId = function_exists('crm_tenant_id') ? crm_tenant_id() : 0;
        if ($tenantId <= 0) {
            return $sql;
        }

        $trimmed = ltrim($sql);
        $upper4  = strtoupper(substr($trimmed, 0, 4));
        $upper6  = strtoupper(substr($trimmed, 0, 6));
        $upper7  = strtoupper(substr($trimmed, 0, 7));
        $upper13 = strtoupper(substr($trimmed, 0, 13));

        // ── INSERT (vč. INSERT IGNORE) → auto-injection tenant_id ─────
        if ($upper6 === 'INSERT' || $upper13 === 'INSERT IGNORE') {
            return $this->processInsert($sql, $tenantId);
        }

        // ── SELECT/UPDATE/DELETE → log warning (jen dev) ─────────────
        if ($upper6 === 'SELECT' || $upper6 === 'UPDATE' || $upper6 === 'DELETE' || $upper4 === 'WITH') {
            $this->maybeLogMissingTenantFilter($sql);
        }

        return $sql;
    }

    /**
     * INSERT — automatické doplnění tenant_id sloupce a hodnoty.
     *
     * Podporované syntaxe:
     *   1. INSERT [IGNORE] INTO `tab` (col1, col2) VALUES (?, ?)         ✓ inject
     *   2. INSERT [IGNORE] INTO `tab` (col1) VALUES (?), (?)             ✓ inject (multi-row)
     *   3. INSERT [IGNORE] INTO `tab` SET col1 = :a                       ✓ inject
     *   4. INSERT INTO `tab` (col) VALUES (?) ON DUPLICATE KEY UPDATE     ✓ inject (jen do INSERT, ne ON DUP)
     *   5. INSERT INTO `tab` SELECT ... FROM ...                          ✗ skip + warning
     *
     * Pokud SQL už obsahuje `tenant_id` (kdekoli) → no-op.
     */
    private function processInsert(string $sql, int $tenantId): string
    {
        // 1. Najdi tabulku
        if (!preg_match('/^\s*INSERT\s+(?:IGNORE\s+)?INTO\s+`?(\w+)`?\b/i', $sql, $m)) {
            return $sql;
        }
        $table = strtolower($m[1]);

        // Není naše tabulka — nemodifikuj
        if (!in_array($table, self::TENANT_TABLES, true)) {
            return $sql;
        }

        // Už obsahuje tenant_id — uživatel ho předává explicitně
        if (preg_match('/\btenant_id\b/i', $sql)) {
            return $sql;
        }

        // INSERT ... SELECT — nezvládneme bezpečně, jen log
        if (preg_match('/\bINSERT\s+(?:IGNORE\s+)?INTO\s+`?\w+`?\s+SELECT\b/i', $sql)) {
            $this->logWarning("INSERT ... SELECT do `{$table}` bez tenant_id — refactor ručně.", $sql);
            return $sql;
        }

        // ── SET syntax: INSERT INTO tab SET col=val ────────────────────
        if (preg_match('/\bINSERT\s+(?:IGNORE\s+)?INTO\s+`?\w+`?\s+SET\s+/i', $sql)) {
            return $this->injectIntoSetSyntax($sql, $tenantId);
        }

        // ── Standardní syntax: INSERT INTO tab (cols) VALUES (...) ─────
        return $this->injectIntoValuesSyntax($sql, $tenantId, $table);
    }

    /**
     * Injection pro: INSERT INTO tab (col1, col2) VALUES (?, ?), (?, ?), ...
     */
    private function injectIntoValuesSyntax(string $sql, int $tenantId, string $table): string
    {
        // Najdi `INSERT ... INTO ... ( <columns> ) VALUES`
        // Pattern najde otevírací závorku sloupců.
        if (!preg_match('/\bINSERT\s+(?:IGNORE\s+)?INTO\s+`?\w+`?\s*\(/i', $sql, $m, PREG_OFFSET_CAPTURE)) {
            return $sql;
        }
        $colOpenPos = $m[0][1] + strlen($m[0][0]) - 1; // pozice '('
        $colClosePos = $this->matchParen($sql, $colOpenPos);
        if ($colClosePos === false) {
            $this->logWarning("INSERT do `{$table}`: nepodařilo se najít konec sloupců.", $sql);
            return $sql;
        }

        // Vlož ", `tenant_id`" před uzavírací )
        $modified = substr($sql, 0, $colClosePos)
                  . ', `tenant_id`'
                  . substr($sql, $colClosePos);

        // Najdi VALUES (
        if (!preg_match('/\bVALUES\s*\(/i', $modified, $vm, PREG_OFFSET_CAPTURE)) {
            $this->logWarning("INSERT do `{$table}`: chybí VALUES klauzule, skip.", $sql);
            return $sql;
        }

        // Iteruj přes všechny VALUES (...) skupiny (multi-row INSERT)
        $offset = 0;
        $result = '';
        while (preg_match('/\bVALUES\s*\(/i', $modified, $vm, PREG_OFFSET_CAPTURE, $offset)) {
            $valStart = $vm[0][1] + strlen($vm[0][0]) - 1; // pozice '('
            $result .= substr($modified, $offset, $valStart - $offset);
            $offset = $valStart;
            // Najdi všechny ( ... ) skupiny do ON DUPLICATE / konce
            while (true) {
                if ($offset >= strlen($modified) || $modified[$offset] !== '(') {
                    break;
                }
                $closePos = $this->matchParen($modified, $offset);
                if ($closePos === false) {
                    $this->logWarning("INSERT do `{$table}`: VALUES skupina nemá pár závorky.", $sql);
                    return $sql;
                }
                // Append (existing) + ", $tenantId" + ")"
                $result .= substr($modified, $offset, $closePos - $offset)
                        . ', ' . $tenantId
                        . ')';
                $offset = $closePos + 1;
                // Skip whitespace + případnou čárku
                while ($offset < strlen($modified) && ctype_space($modified[$offset])) {
                    $result .= $modified[$offset];
                    $offset++;
                }
                if ($offset < strlen($modified) && $modified[$offset] === ',') {
                    $result .= ',';
                    $offset++;
                    // Skip whitespace po čárce
                    while ($offset < strlen($modified) && ctype_space($modified[$offset])) {
                        $result .= $modified[$offset];
                        $offset++;
                    }
                    if ($offset < strlen($modified) && $modified[$offset] === '(') {
                        continue; // další skupina pokračuje
                    } else {
                        break;
                    }
                } else {
                    break;
                }
            }
        }
        // Append zbytek (ON DUPLICATE KEY UPDATE atd.)
        $result .= substr($modified, $offset);
        return $result;
    }

    /**
     * Injection pro: INSERT INTO tab SET col1 = :a, col2 = :b
     */
    private function injectIntoSetSyntax(string $sql, int $tenantId): string
    {
        // Najdi konec SET klauzule. Praktická heuristika:
        // SET pokračuje až do ON DUPLICATE, ORDER BY, LIMIT, ; nebo konce.
        // Pro INSERT ... SET je nejjednodušší prostě připojit ", tenant_id = N"
        // PŘED ON DUPLICATE KEY UPDATE nebo na konec.

        $onDuplicatePos = preg_match('/\bON\s+DUPLICATE\s+KEY\b/i', $sql, $m, PREG_OFFSET_CAPTURE)
            ? $m[0][1]
            : strlen($sql);

        // Vlož ", `tenant_id` = $tenantId " těsně před onDuplicatePos
        // (a ořež trailing whitespace, abychom neměli zbytečně 2x mezera)
        $before = rtrim(substr($sql, 0, $onDuplicatePos));
        $after  = substr($sql, $onDuplicatePos);

        return $before . ', `tenant_id` = ' . $tenantId . ' ' . $after;
    }

    /**
     * Najde uzavírací závorku odpovídající otevírací na $openPos.
     */
    private function matchParen(string $str, int $openPos): int|false
    {
        $len = strlen($str);
        $depth = 0;
        for ($i = $openPos; $i < $len; $i++) {
            $ch = $str[$i];
            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            } elseif ($ch === "'" || $ch === '"') {
                // přeskočit string literal
                $quote = $ch;
                $i++;
                while ($i < $len && $str[$i] !== $quote) {
                    if ($str[$i] === '\\' && $i + 1 < $len) {
                        $i++; // escape
                    }
                    $i++;
                }
            }
        }
        return false;
    }

    /**
     * Pokud dev mód + SELECT/UPDATE/DELETE na tenant tabulku bez tenant_id,
     * loguj warning aby šlo postupně dohledat refactor TODO.
     */
    private function maybeLogMissingTenantFilter(string $sql): void
    {
        if (!defined('CRM_APP_ENV') || CRM_APP_ENV === 'production') {
            return;
        }

        // Pokud SQL obsahuje tenant_id → OK, není to issue
        if (preg_match('/\btenant_id\b/i', $sql)) {
            return;
        }

        // Najdi všechny zmínky o našich tabulkách v FROM/UPDATE/DELETE/JOIN
        foreach (self::TENANT_TABLES as $tab) {
            if (preg_match('/\b(?:FROM|JOIN|UPDATE|DELETE\s+FROM)\s+`?' . preg_quote($tab, '/') . '`?\b/i', $sql)) {
                $this->logWarning("Query na `{$tab}` bez tenant_id filtru.", $sql);
                return; // jeden warning na query stačí
            }
        }
    }

    /**
     * Loguje varování s prefixem [TenantPDO].
     */
    private function logWarning(string $message, string $sql): void
    {
        $short = preg_replace('/\s+/', ' ', trim($sql));
        if (strlen($short) > 200) {
            $short = substr($short, 0, 197) . '...';
        }
        error_log("[TenantPDO] {$message} SQL: {$short}");
    }
}
