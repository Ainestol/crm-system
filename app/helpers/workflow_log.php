<?php
// e:\Snecinatripu\app\helpers\workflow_log.php
declare(strict_types=1);

/**
 * Audit log pro workflow změny (tabulka `workflow_log`).
 *
 * Zápis ze všech rolí (čistička, navolávačka, OZ, BO, import, system).
 * Slouží jako kompletní historie změn stavu kontaktu — kdo, kdy, odkud → kam, proč.
 *
 * Schéma `workflow_log`:
 *   - contact_id   (FK → contacts.id)
 *   - user_id      (FK → users.id, NULL = systém / import)
 *   - old_status   (NULL pro NEW kontakty / import)
 *   - new_status   (povinný)
 *   - note         (volitelná poznámka — co se reálně stalo)
 *   - created_at   (DATETIME(3), milisekundová přesnost)
 */

if (!function_exists('crm_log_workflow_change')) {
    /**
     * Zapíše změnu workflow stavu do audit logu.
     *
     * Selhání NEMÁ shodit hlavní operaci — chytá výjimky a loguje do error logu.
     * Voláme PO úspěšném UPDATE workflow stavu, abychom nezaznamenávali fantomy.
     *
     * @param PDO     $pdo
     * @param int     $contactId  ID kontaktu (contacts.id)
     * @param ?int    $userId     Kdo změnu udělal (NULL = systém / import bez user kontextu)
     * @param ?string $oldStatus  Předchozí stav (NULL pro INSERT / nový kontakt)
     * @param string  $newStatus  Nový stav (povinný)
     * @param string  $note       Volitelná kontextová poznámka
     */
    function crm_log_workflow_change(
        PDO $pdo,
        int $contactId,
        ?int $userId,
        ?string $oldStatus,
        string $newStatus,
        string $note = ''
    ): void {
        if ($contactId <= 0 || $newStatus === '') {
            return; // Sanity guard
        }
        try {
            $pdo->prepare(
                'INSERT INTO workflow_log (contact_id, user_id, old_status, new_status, note, created_at)
                 VALUES (:cid, :uid, :old, :new, :note, NOW(3))'
            )->execute([
                'cid'  => $contactId,
                'uid'  => $userId,
                'old'  => $oldStatus,
                'new'  => $newStatus,
                'note' => mb_substr($note, 0, 1000), // TEXT, ale capnem ať není riskantní
            ]);

            // Auto-hook: zapsat akci do activity_log (best-effort).
            // Mapování workflow.new_status → action_type. Pro READY ještě
            // rozlišuje TM/O2 podle contacts.operator (dotaz navíc).
            if (function_exists('crm_activity_log_record') && $userId !== null) {
                $actionType = _crm_workflow_status_to_action($newStatus);
                if ($actionType === 'cleaning.verified') {
                    $opStmt = $pdo->prepare('SELECT operator FROM contacts WHERE id = ? LIMIT 1');
                    $opStmt->execute([$contactId]);
                    $op = strtoupper((string) ($opStmt->fetchColumn() ?: ''));
                    $actionType = $op === 'O2' ? 'cleaning.verified_o2' : 'cleaning.verified_tm';
                }
                if ($actionType !== null) {
                    crm_activity_log_record(
                        $pdo, $userId, $actionType, 'contact', $contactId,
                        ['old' => $oldStatus, 'new' => $newStatus]
                    );
                }
            }
        } catch (\PDOException $e) {
            crm_db_log_error($e, 'crm_log_workflow_change');
        }
    }
}

if (!function_exists('_crm_workflow_status_to_action')) {
    /**
     * Mapuje contacts.stav transition na activity_log action_type.
     * Vrací NULL pokud stav nemá smysl pro activity tracking (např. NEW, ASSIGNED).
     */
    function _crm_workflow_status_to_action(string $newStatus): ?string
    {
        return match ($newStatus) {
            'CALLED_OK'      => 'call.success',
            'CALLED_BAD'     => 'call.failed',
            'NEZAJEM'        => 'call.failed',
            'NEDOVOLANO'     => 'call.nedovolano',
            'CALLBACK'       => 'call.callback_scheduled',
            'IZOLACE'        => 'call.izolace',
            'READY'          => 'cleaning.verified', // dál se rozlišuje TM/O2
            'VF_SKIP'        => 'cleaning.vf_skip',
            'CHYBNY_KONTAKT' => 'cleaning.bad_contact',
            default          => null,
        };
    }
}

if (!function_exists('crm_load_contact_history')) {
    /**
     * Načte kompletní historii změn pro daný kontakt — pro UI timeline.
     *
     * Vrací řazeno od nejnovějšího (DESC). Limit 200 záznamů (typicky daleko méně).
     *
     * @return list<array{
     *   id:int,
     *   user_id:?int,
     *   user_name:string,
     *   old_status:?string,
     *   new_status:string,
     *   note:string,
     *   created_at:string
     * }>
     */
    function crm_load_contact_history(PDO $pdo, int $contactId): array
    {
        if ($contactId <= 0) return [];

        // POZOR: scopujeme JEN podle contact_id, ne podle tenant_id.
        //   Důvod: volající (getContactHistory) už ověřil, že kontakt patří
        //   do aktivního tenanta (header query s c.tenant_id = :tid). Filtr
        //   tenant_id na logu by zbytečně skrýval starší řádky, kde sloupec
        //   tenant_id zůstal NULL (přibyl až migrací 032) — což byl důvod,
        //   proč se v historii ukazovalo „jen pár akcí".
        //
        // Sloučíme dva zdroje, ať majitel vidí VŠECHNO, co se na firmě dělo:
        //   1) workflow_log     — změny stavu napříč rolemi (čistička, navolávačka, OZ, BO)
        //   2) oz_contact_actions — pracovní deník OZ/BO (úkony bez změny stavu)
        $mapRow = static fn (array $r): array => [
            'id'         => (int) $r['id'],
            'kind'       => (string) $r['kind'],
            'user_id'    => $r['user_id'] !== null ? (int) $r['user_id'] : null,
            'user_name'  => (string) $r['user_name'],
            'user_role'  => (string) $r['user_role'],
            'old_status' => $r['old_status'] !== null ? (string) $r['old_status'] : null,
            'new_status' => (string) $r['new_status'],
            'note'       => (string) $r['note'],
            'created_at' => (string) $r['created_at'],
        ];

        // Hlavní dotaz: workflow_log + oz_contact_actions (UNION, řazeno od nejnovějšího)
        $unionSql =
            "SELECT * FROM (
                (SELECT wl.id AS id, 'stav' AS kind, wl.user_id,
                        COALESCE(u.jmeno, '— systém —') AS user_name,
                        COALESCE(u.role, '')            AS user_role,
                        wl.old_status, wl.new_status,
                        COALESCE(wl.note, '')           AS note,
                        wl.created_at
                 FROM workflow_log wl
                 LEFT JOIN users u ON u.id = wl.user_id
                 WHERE wl.contact_id = :cid1)
                UNION ALL
                (SELECT a.id AS id, 'action' AS kind, a.oz_id AS user_id,
                        COALESCE(u.jmeno, '— systém —') AS user_name,
                        COALESCE(u.role, '')            AS user_role,
                        NULL AS old_status, 'AKCE' AS new_status,
                        COALESCE(a.action_text, '')     AS note,
                        a.created_at
                 FROM oz_contact_actions a
                 LEFT JOIN users u ON u.id = a.oz_id
                 WHERE a.contact_id = :cid2)
                UNION ALL
                (SELECT n.id AS id, 'note' AS kind,
                        COALESCE(n.author_user_id, n.oz_id) AS user_id,
                        COALESCE(u.jmeno, '— systém —') AS user_name,
                        COALESCE(u.role, '')            AS user_role,
                        NULL AS old_status, 'POZNÁMKA' AS new_status,
                        COALESCE(n.note, '')            AS note,
                        n.created_at
                 FROM oz_contact_notes n
                 LEFT JOIN users u ON u.id = COALESCE(n.author_user_id, n.oz_id)
                 WHERE n.contact_id = :cid3)
             ) ev
             ORDER BY ev.created_at DESC, ev.id DESC
             LIMIT 300";

        try {
            $stmt = $pdo->prepare($unionSql);
            $stmt->execute(['cid1' => $contactId, 'cid2' => $contactId, 'cid3' => $contactId]);
            return array_map($mapRow, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        } catch (\PDOException $e) {
            // Fallback: pokud oz_contact_actions na instanci chybí / má jinou
            // strukturu, vrať aspoň workflow_log (bez tenant filtru).
            crm_db_log_error($e, 'crm_load_contact_history');
            try {
                $stmt = $pdo->prepare(
                    "SELECT wl.id AS id, 'stav' AS kind, wl.user_id,
                            COALESCE(u.jmeno, '— systém —') AS user_name,
                            COALESCE(u.role, '')            AS user_role,
                            wl.old_status, wl.new_status,
                            COALESCE(wl.note, '')           AS note,
                            wl.created_at
                     FROM workflow_log wl
                     LEFT JOIN users u ON u.id = wl.user_id
                     WHERE wl.contact_id = :cid
                     ORDER BY wl.created_at DESC, wl.id DESC
                     LIMIT 300"
                );
                $stmt->execute(['cid' => $contactId]);
                return array_map($mapRow, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
            } catch (\PDOException $e2) {
                crm_db_log_error($e2, 'crm_load_contact_history.fallback');
                return [];
            }
        }
    }
}
