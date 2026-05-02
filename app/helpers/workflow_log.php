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
        } catch (\PDOException $e) {
            crm_db_log_error($e, 'crm_log_workflow_change');
        }
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
        try {
            $stmt = $pdo->prepare(
                "SELECT wl.id,
                        wl.user_id,
                        COALESCE(u.jmeno, '— systém —') AS user_name,
                        COALESCE(u.role, '')            AS user_role,
                        wl.old_status,
                        wl.new_status,
                        COALESCE(wl.note, '')           AS note,
                        wl.created_at
                 FROM workflow_log wl
                 LEFT JOIN users u ON u.id = wl.user_id
                 WHERE wl.contact_id = :cid
                 ORDER BY wl.created_at DESC, wl.id DESC
                 LIMIT 200"
            );
            $stmt->execute(['cid' => $contactId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return array_map(static fn ($r) => [
                'id'         => (int)    $r['id'],
                'user_id'    => $r['user_id'] !== null ? (int) $r['user_id'] : null,
                'user_name'  => (string) $r['user_name'],
                'user_role'  => (string) $r['user_role'],
                'old_status' => $r['old_status'] !== null ? (string) $r['old_status'] : null,
                'new_status' => (string) $r['new_status'],
                'note'       => (string) $r['note'],
                'created_at' => (string) $r['created_at'],
            ], $rows);
        } catch (\PDOException $e) {
            crm_db_log_error($e, 'crm_load_contact_history');
            return [];
        }
    }
}
