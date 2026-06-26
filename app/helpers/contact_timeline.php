<?php
// e:\Snecinatripu\app\helpers\contact_timeline.php
declare(strict_types=1);

/**
 * ════════════════════════════════════════════════════════════════════
 *  SJEDNOCENÁ POZNÁMKOVÁ OSA KONTAKTU
 * ════════════════════════════════════════════════════════════════════
 *
 *  Účel:
 *    Jedna chronologická osa poznámek kontaktu (nejnovější nahoře),
 *    kterou vidí VŠECHNY role (navolávačka, OZ, BO, …). Slučuje více
 *    zdrojů poznámek, ANIŽ by cokoliv mazala nebo přesouvala:
 *
 *      1) contact_notes      — obecná osa (navolávačka, admin, BO)
 *      2) oz_contact_notes   — poznámky OZ (bez ohledu na to, který OZ je psal)
 *      3) contacts.poznamka  — legacy / importní poznámka (jen pokud kontakt
 *                              nemá žádný contact_notes záznam → jinak by se
 *                              duplikovala, protože navolávačka ji píše do obojího)
 *
 *  Tím je vyřešené i přehození OZ v datagridu: osa ukazuje poznámky
 *  nezávisle na aktuálním assigned_sales_id, takže se po přehození nic
 *  „neztratí".
 *
 *  Výkon:
 *    Dávková funkce (batch) — pro seznam karet (navolávačka, OZ leady)
 *    načte poznámky všech zobrazených kontaktů v pár dotazech, ne N+1.
 *
 *  Návratový tvar (per kontakt, seřazeno DESC = nejnovější první):
 *    [
 *      'author_name' => string,
 *      'author_role' => string,
 *      'created_at'  => string (Y-m-d H:i:s.v),
 *      'text'        => string,
 *      'source'      => 'note'|'oznote'|'legacy',
 *    ]
 */

if (!function_exists('crm_contact_timeline_batch')) {
    /**
     * @param list<int> $contactIds
     * @return array<int, list<array<string,mixed>>>  contactId => záznamy (DESC)
     */
    function crm_contact_timeline_batch(PDO $pdo, array $contactIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $contactIds), static fn($v) => $v > 0)));
        if ($ids === []) {
            return [];
        }
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $out = array_fill_keys($ids, []);
        $hasContactNote = []; // cid => true (kvůli deduplikaci legacy poznámky)

        // 1) contact_notes (navolávačka / admin / BO)
        try {
            $st = $pdo->prepare(
                "SELECT cn.contact_id, cn.note AS text, cn.created_at,
                        COALESCE(u.jmeno, '—') AS author_name,
                        COALESCE(u.role, '')   AS author_role
                 FROM contact_notes cn
                 LEFT JOIN users u ON u.id = cn.user_id
                 WHERE cn.contact_id IN ($ph)"
            );
            $st->execute($ids);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $cid = (int) $r['contact_id'];
                $r['source'] = 'note';
                $out[$cid][] = $r;
                $hasContactNote[$cid] = true;
            }
        } catch (\PDOException $e) {
            if (function_exists('crm_db_log_error')) crm_db_log_error($e, 'timeline.contact_notes');
        }

        // 2) oz_contact_notes (OZ — VŠECHNY, bez filtru na aktuálního OZ)
        try {
            $st = $pdo->prepare(
                "SELECT cn.contact_id, cn.note AS text, cn.created_at,
                        COALESCE(au.jmeno, '—') AS author_name,
                        COALESCE(au.role, '')   AS author_role
                 FROM oz_contact_notes cn
                 LEFT JOIN users au ON au.id = COALESCE(cn.author_user_id, cn.oz_id)
                 WHERE cn.contact_id IN ($ph)"
            );
            $st->execute($ids);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $r['source'] = 'oznote';
                $out[(int) $r['contact_id']][] = $r;
            }
        } catch (\PDOException $e) {
            if (function_exists('crm_db_log_error')) crm_db_log_error($e, 'timeline.oz_contact_notes');
        }

        // 3) legacy contacts.poznamka — jen pokud kontakt nemá žádný contact_notes
        //    (jinak duplicita: navolávačka píše do contact_notes i contacts.poznamka).
        //    Autor = aktuálně přiřazená navolávačka (orientačně), čas = vytvoření kontaktu.
        try {
            $st = $pdo->prepare(
                "SELECT c.id AS contact_id, c.poznamka AS text, c.created_at,
                        COALESCE(u.jmeno, '—') AS author_name,
                        COALESCE(u.role, '')   AS author_role
                 FROM contacts c
                 LEFT JOIN users u ON u.id = c.assigned_caller_id
                 WHERE c.id IN ($ph)
                   AND c.poznamka IS NOT NULL AND TRIM(c.poznamka) <> ''"
            );
            $st->execute($ids);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $cid = (int) $r['contact_id'];
                if (isset($hasContactNote[$cid])) {
                    continue; // už je v contact_notes → neduplikovat
                }
                $r['source'] = 'legacy';
                $out[$cid][] = $r;
            }
        } catch (\PDOException $e) {
            if (function_exists('crm_db_log_error')) crm_db_log_error($e, 'timeline.legacy_poznamka');
        }

        // Seřadit každý kontakt: nejnovější nahoře
        foreach ($out as $cid => $rows) {
            usort($rows, static fn($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
            $out[$cid] = $rows;
        }

        return $out;
    }
}

if (!function_exists('crm_contact_timeline')) {
    /**
     * Pohodlná varianta pro jeden kontakt (detail / work screen).
     * @return list<array<string,mixed>>
     */
    function crm_contact_timeline(PDO $pdo, int $contactId): array
    {
        $batch = crm_contact_timeline_batch($pdo, [$contactId]);
        return $batch[$contactId] ?? [];
    }
}
