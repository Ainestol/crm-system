<?php
declare(strict_types=1);

/**
 * app/helpers/rescue.php
 *
 * Helper funkce pro feature "Záchrana leadu".
 *
 * Workflow:
 *   1. OZ → rescue_create()       : kontakt jde do RESCUE_REQUESTED, vytvoří se request
 *   2. Caller → rescue_success()  : kontakt jde k OZ (target nebo original), success+expiruje na bonus
 *   3. Caller → rescue_failure()  : kontakt jde do NEZAJEM/CALLED_BAD, failed
 *   4. Cron/lazy → rescue_expire_overdue() : pending starší než 14 dní → expired
 *   5. Hook při podpisu → rescue_finalize_bonus() : nastaví bonus_amount=bmsl, locked_at
 *   6. Admin → rescue_mark_paid() : bonus_paid_at = NOW, bonus_paid_by = admin
 */

/** Doba, jak dlouho má caller na záchranu */
const RESCUE_DEADLINE_DAYS = 14;

/**
 * Vytvoří nový rescue request. OZ musí být current assigned_sales_id.
 *
 * @param int      $contactId       Kontakt který jde na záchranu
 * @param int      $originalSalesId Současný OZ (= request author)
 * @param int|null $targetSalesId   Cílový OZ po úspěchu (NULL = řeší prefer_original)
 * @param bool     $preferOriginal  Pokud target=NULL, vrátit původnímu? True=ano, False=rotace
 * @param string   $reason          Důvod záchrany (povinný)
 *
 * @return array{id:int, contact_id:int, expires_at:string}|null
 *
 * @throws \RuntimeException Pokud kontakt už záchranu měl (UNIQUE constraint).
 */
function rescue_create(
    PDO $pdo,
    int $contactId,
    int $originalSalesId,
    ?int $targetSalesId,
    bool $preferOriginal,
    string $reason
): ?array {
    if ($contactId <= 0 || $originalSalesId <= 0 || trim($reason) === '') {
        return null;
    }

    // Najdi původní caller (= kdo lead dodal OZ s CALLED_OK) — multi-tenant
    // Použijeme současné assigned_caller_id jako "kdo to navolal".
    $tid = crm_tenant_id();
    $cStmt = $pdo->prepare(
        "SELECT assigned_caller_id, stav, assigned_sales_id FROM contacts
         WHERE id = ? AND tenant_id = ?"
    );
    $cStmt->execute([$contactId, $tid]);
    $c = $cStmt->fetch(PDO::FETCH_ASSOC);
    if (!$c) {
        throw new \RuntimeException('Kontakt neexistuje.');
    }
    // Kontakt musí být CALLED_OK nebo FOR_SALES (= u OZ v práci)
    if (!in_array((string) $c['stav'], ['CALLED_OK', 'FOR_SALES'], true)) {
        throw new \RuntimeException('Kontakt není ve stavu CALLED_OK/FOR_SALES — záchranu nelze založit.');
    }
    if ((int) $c['assigned_sales_id'] !== $originalSalesId) {
        throw new \RuntimeException('Kontakt nepatří tomuto OZ.');
    }

    $originalCallerId = (int) ($c['assigned_caller_id'] ?? 0) ?: null;

    $pdo->beginTransaction();
    try {
        // 1. INSERT rescue_request
        $expiresAt = date('Y-m-d H:i:s.v', strtotime('+' . RESCUE_DEADLINE_DAYS . ' days'));
        $ins = $pdo->prepare(
            "INSERT INTO rescue_requests
             (contact_id, original_sales_id, target_sales_id, prefer_original,
              reason, original_caller_id, requested_at, expires_at, outcome)
             VALUES (:cid, :osi, :tsi, :po, :reason, :ocid, NOW(3), :exp, 'pending')"
        );
        $ins->execute([
            'cid'    => $contactId,
            'osi'    => $originalSalesId,
            'tsi'    => $targetSalesId,
            'po'     => $preferOriginal ? 1 : 0,
            'reason' => trim($reason),
            'ocid'   => $originalCallerId,
            'exp'    => $expiresAt,
        ]);
        $rrId = (int) $pdo->lastInsertId();

        // 2. UPDATE contacts.stav = RESCUE_REQUESTED — multi-tenant
        //    (vyčistíme locked_by/until pro jistotu, ať caller v navolávačce může claimnout)
        $pdo->prepare(
            "UPDATE contacts
             SET stav = 'RESCUE_REQUESTED',
                 locked_by = NULL,
                 locked_until = NULL,
                 updated_at = NOW(3)
             WHERE id = :id AND tenant_id = :tid"
        )->execute(['id' => $contactId, 'tid' => $tid]);

        // 3. Workflow log
        $pdo->prepare(
            "INSERT INTO workflow_log (contact_id, user_id, old_status, new_status, note, created_at)
             VALUES (:cid, :uid, :old, 'RESCUE_REQUESTED', :note, NOW(3))"
        )->execute([
            'cid'  => $contactId,
            'uid'  => $originalSalesId,
            'old'  => (string) $c['stav'],
            'note' => 'OZ poslal na záchranu: ' . trim($reason),
        ]);

        $pdo->commit();

        // Activity log — OZ poslal kontakt na záchranu
        if (function_exists('crm_activity_log_record')) {
            crm_activity_log_record(
                $pdo, $originalSalesId, 'sales.rescue_requested', 'rescue_request', $rrId,
                ['contact_id' => $contactId, 'target_sales_id' => $targetSalesId]
            );
        }

        return [
            'id'         => $rrId,
            'contact_id' => $contactId,
            'expires_at' => $expiresAt,
        ];
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // UNIQUE violation = už zachráněn
        if ((int) $e->errorInfo[1] === 1062) {
            throw new \RuntimeException('Tento kontakt už záchranu měl (každý kontakt jen 1×).');
        }
        throw $e;
    }
}

/**
 * Najde aktivní rescue request pro kontakt.
 *
 * @return array<string,mixed>|null
 */
function rescue_find_active(PDO $pdo, int $contactId): ?array
{
    // Multi-tenant filter
    $stmt = $pdo->prepare(
        "SELECT rr.*, uo.jmeno AS original_sales_name, ut.jmeno AS target_sales_name
         FROM rescue_requests rr
         LEFT JOIN users uo ON uo.id = rr.original_sales_id
         LEFT JOIN users ut ON ut.id = rr.target_sales_id
         WHERE rr.contact_id = ? AND rr.outcome = 'pending' AND rr.tenant_id = ?
         LIMIT 1"
    );
    $stmt->execute([$contactId, crm_tenant_id()]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

/**
 * Najde JAKÝKOLI rescue záznam pro kontakt (i uzavřený) — pro audit/zobrazení historie.
 */
function rescue_find_any(PDO $pdo, int $contactId): ?array
{
    // Multi-tenant filter
    $stmt = $pdo->prepare(
        "SELECT rr.*, uo.jmeno AS original_sales_name, ut.jmeno AS target_sales_name,
                uc.jmeno AS rescued_by_caller_name, uf.jmeno AS final_sales_name
         FROM rescue_requests rr
         LEFT JOIN users uo ON uo.id = rr.original_sales_id
         LEFT JOIN users ut ON ut.id = rr.target_sales_id
         LEFT JOIN users uc ON uc.id = rr.rescued_by_caller_id
         LEFT JOIN users uf ON uf.id = rr.final_sales_id
         WHERE rr.contact_id = ? AND rr.tenant_id = ?
         ORDER BY rr.requested_at DESC
         LIMIT 1"
    );
    $stmt->execute([$contactId, crm_tenant_id()]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

/**
 * Úspěšná záchrana — caller se dovolal a zákazník má zájem.
 *
 * Lead se vrátí OZ (target_sales_id NEBO original podle prefer_original).
 * contacts.stav = CALLED_OK, assigned_sales_id = nový OZ.
 * rescue.outcome = success, rescued_at = NOW.
 * Bonus se zatím NEvyplní — čekáme na podpis_potvrzen v OzController.
 *
 * @return array{final_sales_id:int}|null
 */
function rescue_success(
    PDO $pdo,
    int $rescueId,
    int $callerId,
    string $note = '',
    ?int $overrideSalesId = null
): ?array {
    $tid = crm_tenant_id();
    $pdo->beginTransaction();
    try {
        // Multi-tenant: rescue patří jen aktuálnímu tenantu
        $stmt = $pdo->prepare(
            "SELECT * FROM rescue_requests
             WHERE id = ? AND outcome = 'pending' AND tenant_id = ?
             FOR UPDATE"
        );
        $stmt->execute([$rescueId, $tid]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$r) {
            $pdo->rollBack();
            return null; // už uzavřeno nebo neexistuje
        }

        // Komu lead přidělit?
        //   0. CALLER OVERRIDE — pokud caller vybrala jiného OZ při úspěchu (např.
        //      OZ ji řekl mimo systém "můžeš to pustit do světa", caller vybere
        //      jiného aktivního obchodáka).
        //   1. target_sales_id NOT NULL → tento OZ
        //   2. prefer_original=1 → original_sales_id
        //   3. jinak null (rotace — caller musí vybrat při výhře)
        $finalSalesId = 0;
        if ($overrideSalesId !== null && $overrideSalesId > 0) {
            // Validace: musí být aktivní obchodák tohoto tenantu (přes user_tenants)
            $vStmt = $pdo->prepare(
                "SELECT u.id FROM users u
                 INNER JOIN user_tenants ut
                     ON ut.user_id = u.id AND ut.tenant_id = ? AND ut.active = 1
                 WHERE u.id = ? AND u.aktivni = 1 AND (
                    u.role = 'obchodak'
                    OR JSON_CONTAINS(IFNULL(u.roles_extra, '[]'), '\"obchodak\"')
                 )"
            );
            $vStmt->execute([$tid, $overrideSalesId]);
            if ($vStmt->fetchColumn() !== false) {
                $finalSalesId = $overrideSalesId;
            }
        }
        if ($finalSalesId <= 0) {
            $finalSalesId = (int) ($r['target_sales_id'] ?? 0)
                ?: (((int) $r['prefer_original'] === 1) ? (int) $r['original_sales_id'] : 0);
        }

        if ($finalSalesId <= 0) {
            $pdo->rollBack();
            throw new \RuntimeException('Záchrana nemá target ani prefer_original — caller musí vybrat OZ ručně.');
        }

        // UPDATE contacts → CALLED_OK + assigned_sales_id (multi-tenant)
        $pdo->prepare(
            "UPDATE contacts
             SET stav = 'CALLED_OK',
                 assigned_sales_id = :sid,
                 assigned_caller_id = :cid,
                 datum_volani = NOW(3),
                 datum_predani = NOW(3),
                 updated_at = NOW(3)
             WHERE id = :id AND tenant_id = :tid"
        )->execute([
            'sid' => $finalSalesId,
            'cid' => $callerId,
            'id'  => (int) $r['contact_id'],
            'tid' => $tid,
        ]);

        // UPDATE rescue_request — uložíme i poznámku navolávačky (caller note)
        $trimmedNote = trim($note);
        $pdo->prepare(
            "UPDATE rescue_requests
             SET outcome = 'success',
                 rescued_at = NOW(3),
                 rescued_by_caller_id = :cid,
                 final_sales_id = :sid,
                 notes = :note
             WHERE id = :id AND tenant_id = :tid"
        )->execute([
            'cid'  => $callerId,
            'sid'  => $finalSalesId,
            'note' => $trimmedNote !== '' ? $trimmedNote : null,
            'id'   => $rescueId,
            'tid'  => $tid,
        ]);

        // Workflow log — pokud caller přidala poznámku, zaloguj ji (jinak generic)
        $wfNote = $trimmedNote !== ''
            ? 'Záchrana úspěšná: ' . $trimmedNote
            : 'Záchrana úspěšná';
        $pdo->prepare(
            "INSERT INTO workflow_log (contact_id, user_id, old_status, new_status, note, created_at)
             VALUES (:cid, :uid, 'RESCUE_REQUESTED', 'CALLED_OK', :note, NOW(3))"
        )->execute([
            'cid'  => (int) $r['contact_id'],
            'uid'  => $callerId,
            'note' => $wfNote,
        ]);

        // Také do contact_notes — ať OZ vidí na detailu zákazníka
        if ($trimmedNote !== '') {
            $pdo->prepare(
                "INSERT INTO contact_notes (contact_id, user_id, note, created_at)
                 VALUES (:cid, :uid, :note, NOW(3))"
            )->execute([
                'cid'  => (int) $r['contact_id'],
                'uid'  => $callerId,
                'note' => '🆘 Záchrana úspěšná: ' . $trimmedNote,
            ]);
        }

        $pdo->commit();

        // Activity log — záchrana úspěšná (25 b default)
        if (function_exists('crm_activity_log_record')) {
            crm_activity_log_record(
                $pdo, $callerId, 'rescue.success', 'rescue_request', $rescueId,
                ['contact_id' => (int) $r['contact_id'], 'final_sales_id' => $finalSalesId]
            );
        }
        return ['final_sales_id' => $finalSalesId];
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * Neúspěšná záchrana — caller se nedovolal nebo nezájem.
 *
 * contacts.stav = NEZAJEM / CALLED_BAD podle parametru.
 * rescue.outcome = failed.
 * OZ NEZAPLATÍ ~200 Kč navolávačce (= clawback z original_caller_id).
 *
 * @param string $finalStav 'NEZAJEM' | 'CALLED_BAD' | 'IZOLACE'
 */
function rescue_failure(
    PDO $pdo,
    int $rescueId,
    int $callerId,
    string $finalStav,
    string $note = ''
): bool {
    if (!in_array($finalStav, ['NEZAJEM', 'CALLED_BAD', 'IZOLACE'], true)) {
        return false;
    }

    $tid = crm_tenant_id();
    $pdo->beginTransaction();
    try {
        // Multi-tenant
        $stmt = $pdo->prepare(
            "SELECT contact_id FROM rescue_requests
             WHERE id = ? AND outcome = 'pending' AND tenant_id = ?
             FOR UPDATE"
        );
        $stmt->execute([$rescueId, $tid]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$r) {
            $pdo->rollBack();
            return false;
        }

        $pdo->prepare(
            "UPDATE contacts
             SET stav = :stav,
                 assigned_caller_id = :cid,
                 datum_volani = NOW(3),
                 updated_at = NOW(3)
             WHERE id = :id AND tenant_id = :tid"
        )->execute([
            'stav' => $finalStav,
            'cid'  => $callerId,
            'id'   => (int) $r['contact_id'],
            'tid'  => $tid,
        ]);

        $pdo->prepare(
            "UPDATE rescue_requests
             SET outcome = 'failed',
                 rescued_at = NOW(3),
                 rescued_by_caller_id = :cid,
                 notes = :note
             WHERE id = :id AND tenant_id = :tid"
        )->execute([
            'cid'  => $callerId,
            'note' => $note !== '' ? $note : null,
            'id'   => $rescueId,
            'tid'  => $tid,
        ]);

        $pdo->prepare(
            "INSERT INTO workflow_log (contact_id, user_id, old_status, new_status, note, created_at)
             VALUES (:cid, :uid, 'RESCUE_REQUESTED', :stav, :note, NOW(3))"
        )->execute([
            'cid'  => (int) $r['contact_id'],
            'uid'  => $callerId,
            'stav' => $finalStav,
            'note' => 'Záchrana neúspěšná: ' . ($note ?: $finalStav),
        ]);

        $pdo->commit();

        // Activity log — záchrana neúspěšná
        if (function_exists('crm_activity_log_record')) {
            crm_activity_log_record(
                $pdo, $callerId, 'rescue.failure', 'rescue_request', $rescueId,
                ['contact_id' => (int) $r['contact_id'], 'final_stav' => $finalStav]
            );
        }
        return true;
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * Lazy expirace — projde všechny pending záchrany s expires_at < NOW.
 * Označí outcome=expired. Lead zůstává v RESCUE_REQUESTED (= OZ vidí status).
 *
 * Pozn: kontakt se NEVRACÍ automaticky CALLED_OK — protože původní OZ ho
 * neumí provolat. Stav RESCUE_REQUESTED+outcome=expired znamená "lead je mrtvý,
 * nikdo nedostane peníze za navolávání" (clawback z original_caller_id).
 *
 * Voláno z getIndex obou callerských + OZ controllerů.
 *
 * @return int Počet expirovaných záznamů.
 */
function rescue_expire_overdue(PDO $pdo): int
{
    try {
        // Multi-tenant: expirujeme jen rescue v rámci aktivního tenantu
        $stmt = $pdo->prepare(
            "UPDATE rescue_requests
             SET outcome = 'expired',
                 expired_at = NOW(3)
             WHERE outcome = 'pending' AND expires_at < NOW(3)
               AND tenant_id = ?"
        );
        $stmt->execute([crm_tenant_id()]);
        return $stmt->rowCount();
    } catch (\PDOException $e) {
        if (function_exists('crm_db_log_error')) {
            crm_db_log_error($e, __FUNCTION__);
        }
        return 0;
    }
}

/**
 * Po podpisu smlouvy OZ → najít aktivní záchranu pro kontakt (success bez bonusu)
 * a uložit bonus_amount = bmsl.
 *
 * Voláno z OzController při potvrzení podpisu (podpis_potvrzen=1).
 */
function rescue_finalize_bonus(PDO $pdo, int $contactId, float $bmsl): bool
{
    if ($bmsl <= 0) return false;

    try {
        // Multi-tenant
        $stmt = $pdo->prepare(
            "UPDATE rescue_requests
             SET bonus_amount = :amt,
                 bonus_locked_at = NOW(3)
             WHERE contact_id = :cid
               AND outcome = 'success'
               AND bonus_amount IS NULL
               AND tenant_id = :tid"
        );
        $stmt->execute(['amt' => $bmsl, 'cid' => $contactId, 'tid' => crm_tenant_id()]);
        return $stmt->rowCount() > 0;
    } catch (\PDOException $e) {
        if (function_exists('crm_db_log_error')) {
            crm_db_log_error($e, __FUNCTION__);
        }
        return false;
    }
}

/**
 * Admin označí bonus jako vyplacený navolávačce.
 */
function rescue_mark_paid(PDO $pdo, int $rescueId, int $adminId): bool
{
    try {
        // Multi-tenant
        $stmt = $pdo->prepare(
            "UPDATE rescue_requests
             SET bonus_paid_at = NOW(3),
                 bonus_paid_by = :by
             WHERE id = :id
               AND outcome = 'success'
               AND bonus_amount IS NOT NULL
               AND bonus_paid_at IS NULL
               AND tenant_id = :tid"
        );
        $stmt->execute(['by' => $adminId, 'id' => $rescueId, 'tid' => crm_tenant_id()]);
        return $stmt->rowCount() > 0;
    } catch (\PDOException $e) {
        if (function_exists('crm_db_log_error')) {
            crm_db_log_error($e, __FUNCTION__);
        }
        return false;
    }
}

/**
 * Vrátí seznam pending záchran pro navolávačku (přístup: target_caller nebo všichni pro rotaci).
 * Caller-side filter: NE-vrací rescue, kde target_sales_id = current_caller_id (= sám sebe nemůže zachranit).
 *
 * @return list<array<string,mixed>>
 */
function rescue_list_pending_for_caller(PDO $pdo): array
{
    // Multi-tenant filter
    $stmt = $pdo->prepare(
        "SELECT rr.id AS rescue_id, rr.contact_id, rr.original_sales_id, rr.target_sales_id,
                rr.prefer_original, rr.reason, rr.requested_at, rr.expires_at,
                uo.jmeno AS original_sales_name,
                ut.jmeno AS target_sales_name,
                c.firma, c.telefon, c.region, c.operator, c.email,
                TIMESTAMPDIFF(HOUR, NOW(), rr.expires_at) AS hours_left
         FROM rescue_requests rr
         JOIN contacts c ON c.id = rr.contact_id AND c.tenant_id = rr.tenant_id
         LEFT JOIN users uo ON uo.id = rr.original_sales_id
         LEFT JOIN users ut ON ut.id = rr.target_sales_id
         WHERE rr.outcome = 'pending'
           AND c.stav = 'RESCUE_REQUESTED'
           AND rr.tenant_id = :tid
         ORDER BY rr.expires_at ASC"
    );
    $stmt->execute(['tid' => crm_tenant_id()]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
