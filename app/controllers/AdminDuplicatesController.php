<?php
// e:\Snecinatripu\app\controllers\AdminDuplicatesController.php
declare(strict_types=1);

/**
 * Admin nástroj pro detekci duplicit v `contacts` tabulce.
 *
 * Routes:
 *   GET /admin/duplicates              — index (přehled počtu duplicit)
 *   GET /admin/duplicates?key=telefon  — detail per klíč (telefon|email|ico)
 *
 * Read-only audit. Žádné mazání ani slučování (low-risk before deploy).
 * Pokud chce uživatel duplicity řešit, klikne na odkaz na konkrétní kontakt
 * a v BO/OZ workflow tam označí buď NEZAJEM nebo ručně překontaktuje.
 */
final class AdminDuplicatesController
{
    public function __construct(private PDO $pdo) {}

    public function getIndex(): void
    {
        $actor = crm_require_user($this->pdo);
        crm_require_roles($actor, ['majitel', 'superadmin']);

        $key = (string) ($_GET['key'] ?? '');
        if (!in_array($key, ['telefon', 'email', 'ico'], true)) {
            $key = '';
        }

        // ── Souhrnné počty (pro přehled na vrcholu stránky) ──
        $summary = [
            'telefon' => $this->countDuplicates('telefon'),
            'email'   => $this->countDuplicates('email'),
            'ico'     => $this->countDuplicates('ico'),
        ];

        // ── Detail vybraného klíče (skupiny duplicit) ──
        $groups = [];
        if ($key !== '') {
            $groups = $this->loadDuplicateGroups($key, 200);
        }

        $title = 'Audit duplicit — admin';
        $flash = crm_flash_take();
        $csrf  = crm_csrf_token();

        ob_start();
        require dirname(__DIR__) . '/views/admin/duplicates/index.php';
        $content = (string) ob_get_clean();
        $user = $actor; // alias pro layout/base.php (sidebar + topbar)
        require dirname(__DIR__) . '/views/layout/base.php';
    }

    /**
     * Vrátí počet duplicitních hodnot pro daný sloupec.
     * (např. "kolik různých čísel má 2+ kontakty navíc")
     *
     * @return array{distinct_keys:int, total_extra_rows:int}
     */
    private function countDuplicates(string $col): array
    {
        $col = $this->safeCol($col);
        $sql = "SELECT COUNT(*) AS distinct_keys,
                       COALESCE(SUM(c - 1), 0) AS total_extra_rows
                FROM (
                    SELECT $col AS k, COUNT(*) AS c
                    FROM contacts
                    WHERE $col IS NOT NULL AND $col <> ''
                    GROUP BY $col
                    HAVING COUNT(*) > 1
                ) AS d";
        try {
            $row = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException) {
            $row = [];
        }
        return [
            'distinct_keys'    => (int) ($row['distinct_keys']    ?? 0),
            'total_extra_rows' => (int) ($row['total_extra_rows'] ?? 0),
        ];
    }

    /**
     * Načte skupiny duplicit pro daný sloupec.
     * Limit = max počet skupin (každá může mít 2-N kontaktů).
     *
     * @return list<array{key_value:string, count:int, contacts:list<array<string,mixed>>}>
     */
    private function loadDuplicateGroups(string $col, int $limit = 200): array
    {
        $col = $this->safeCol($col);

        // Nejdřív zjisti, které hodnoty mají duplicity
        $stmt = $this->pdo->prepare(
            "SELECT $col AS k, COUNT(*) AS c
             FROM contacts
             WHERE $col IS NOT NULL AND $col <> ''
             GROUP BY $col
             HAVING c > 1
             ORDER BY c DESC, $col ASC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $keys = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (!$keys) return [];

        $groups = [];
        foreach ($keys as $k) {
            $groups[(string) $k['k']] = [
                'key_value' => (string) $k['k'],
                'count'     => (int) $k['c'],
                'contacts'  => [],
            ];
        }

        // Načti všechny kontakty s těmito hodnotami (jednou DB roundtripem)
        $values = array_column($keys, 'k');
        $place  = implode(',', array_fill(0, count($values), '?'));
        $sql = "SELECT c.id, c.firma, c.ico, c.telefon, c.email, c.region, c.adresa,
                       c.stav AS contact_stav,
                       c.created_at, c.updated_at,
                       COALESCE(w.stav, '—')      AS workflow_stav,
                       COALESCE(u_oz.jmeno, '')   AS oz_name,
                       COALESCE(u_cl.jmeno, '')   AS caller_name
                FROM contacts c
                LEFT JOIN oz_contact_workflow w ON w.contact_id = c.id
                LEFT JOIN users u_oz ON u_oz.id = c.assigned_sales_id
                LEFT JOIN users u_cl ON u_cl.id = c.assigned_caller_id
                WHERE c.$col IN ($place)
                ORDER BY c.$col, c.id ASC";
        $detail = $this->pdo->prepare($sql);
        $detail->execute($values);

        foreach ($detail->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $colName = $col;
            $val = (string) $row[$colName];
            if (isset($groups[$val])) {
                $groups[$val]['contacts'][] = $row;
            }
        }

        return array_values($groups);
    }

    /**
     * Whitelist sloupců — chrání před SQL injection v dynamickém ORDER/WHERE.
     */
    private function safeCol(string $col): string
    {
        return match ($col) {
            'telefon' => 'telefon',
            'email'   => 'email',
            'ico'     => 'ico',
            default   => 'telefon',
        };
    }
}
