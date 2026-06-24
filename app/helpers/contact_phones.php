<?php
// e:\Snecinatripu\app\helpers\contact_phones.php
declare(strict_types=1);

/**
 * Helpers pro contact_phones — per-telefon evidence + operátor.
 *
 * Centralizuje 3 odpovědnosti:
 *   1. Naparsování "777111, 602222" na list jednotlivých telefonů
 *   2. Načtení telefonů kontaktu (vč. statusu ověření)
 *   3. Vyhodnocení finálního stavu kontaktu po ověření VŠECH telefonů
 *
 * Logika vyhodnocení (čistička dokončí):
 *   - Žádný telefon ověřený  → kontakt zůstává NEW (čeká dál)
 *   - Aspoň 1 telefon NE-VF a NE-CHYBNY → READY
 *   - Všechny ověřené, ale všechny VF → VF_SKIP
 *   - Všechny ověřené, ale všechny CHYBNY → CHYBNY_KONTAKT
 *   - Mix VF + CHYBNY → READY pokud aspoň jeden NE-VF NE-CHYBNY
 */

if (!function_exists('crm_parse_phones')) {
    /**
     * Rozparsuje string "777111, 602222" / "777111;602222" / "777111\n602222"
     * na list jednotlivých telefonů (originální format zachován).
     *
     * @return list<string>  pole telefonů, prázdné pokud nic
     */
    function crm_parse_phones(?string $raw): array
    {
        $s = trim((string) ($raw ?? ''));
        if ($s === '') return [];
        // Rozdělit podle , ; nebo newline
        $parts = preg_split('/[,;\n\r]+/u', $s) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') continue;
            // Musí mít aspoň 5 číslic, jinak ignorovat
            $digits = preg_replace('/\D+/', '', $p) ?? '';
            if (mb_strlen($digits) < 5) continue;
            $out[] = $p;
        }
        return $out;
    }
}

if (!function_exists('crm_phone_digits')) {
    function crm_phone_digits(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) ($phone ?? '')) ?? '';
    }
}

if (!function_exists('crm_phones_for_contact')) {
    /**
     * Vrátí všechny telefony kontaktu seřazené podle position.
     *
     * @return list<array{id:int, phone:string, phone_digits:string, operator:?string,
     *                    verified_at:?string, verified_by:?int, position:int}>
     */
    function crm_phones_for_contact(PDO $pdo, int $contactId): array
    {
        try {
            // Multi-tenant filter
            $st = $pdo->prepare(
                'SELECT id, phone, phone_digits, operator, verified_at, verified_by, position
                 FROM contact_phones
                 WHERE contact_id = :cid AND tenant_id = :tid
                 ORDER BY position ASC, id ASC'
            );
            $st->execute(['cid' => $contactId, 'tid' => crm_tenant_id()]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as &$r) {
                $r['id']        = (int) $r['id'];
                $r['position']  = (int) $r['position'];
                $r['verified_by'] = $r['verified_by'] !== null ? (int) $r['verified_by'] : null;
            }
            return $rows;
        } catch (\PDOException $_) {
            return [];
        }
    }
}

if (!function_exists('crm_phone_ensure_for_contact')) {
    /**
     * Pojistka: zajistí že contact_phones je synchronizovaný s contacts.telefon.
     *
     * Algoritmus (smart sync):
     *   1. Naparsuje contacts.telefon na list aktuálních telefonů (digits-only key)
     *   2. Načte existující contact_phones a vytvoří map(digits → row)
     *   3. Pro každý telefon z parseru:
     *      - Pokud už existuje v contact_phones (digits match) → ponechat (zachováme operator+verified_at!)
     *      - Pokud nový → vytvořit nový řádek (operator=NULL, čeká čističku)
     *   4. Pro každý existující contact_phones řádek:
     *      - Pokud digits NENÍ v aktuálním seznamu → smazat (telefon byl odstraněn z kontaktu)
     *
     * Tj. pokud admin přes datagrid přidá 4 nové telefony k existujícímu kontaktu,
     * funkce přidá 4 nové řádky v contact_phones (původní 1 zachová s operátorem).
     */
    function crm_phone_ensure_for_contact(PDO $pdo, int $contactId, ?string $rawTelefon, ?string $contactStav = null, ?string $contactOperator = null): void
    {
        $phones = crm_parse_phones($rawTelefon);
        if ($phones === []) return;

        // Pokud kontakt už NENÍ v stavu NEW (= byl ověřen čističkou v minulosti),
        // nastavíme operátora rovnou na contacts.operator. Tj. pro NEW kontakt
        // nový telefon → operator=NULL (čistička doplní).
        $alreadyVerified = ($contactStav !== null && $contactStav !== 'NEW' && $contactStav !== '');
        $opUpper = $alreadyVerified && $contactOperator !== null && $contactOperator !== ''
            ? strtoupper(trim($contactOperator))
            : null;

        // 1) Načti existující řádky — multi-tenant
        $tid = crm_tenant_id();
        $exStmt = $pdo->prepare(
            'SELECT id, phone, phone_digits, operator, verified_at, position
             FROM contact_phones WHERE contact_id = :cid AND tenant_id = :tid'
        );
        $exStmt->execute(['cid' => $contactId, 'tid' => $tid]);
        $existing = $exStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $existingByDigits = [];
        foreach ($existing as $e) {
            $d = (string) ($e['phone_digits'] ?? '');
            if ($d !== '') $existingByDigits[$d] = $e;
        }

        // 2) Najít aktuální telefon digits
        $currentDigits = [];
        foreach ($phones as $p) {
            $d = crm_phone_digits($p);
            if ($d !== '') $currentDigits[$d] = $p;
        }

        $ins = $pdo->prepare(
            'INSERT INTO contact_phones (contact_id, phone, phone_digits, operator,
                                          verified_at, position, created_at)
             VALUES (:cid, :phone, :digits, :op, :vat, :pos, NOW(3))'
        );
        // INSERT — wrapper auto-injektuje tenant_id; DELETE explicitně per tenant
        $del = $pdo->prepare('DELETE FROM contact_phones WHERE id = :id AND tenant_id = :tid');

        // 3) Přidat nové telefony (digits, které ještě neexistují)
        $i = 0;
        foreach ($phones as $p) {
            $d = crm_phone_digits($p);
            if ($d === '') continue;
            if (isset($existingByDigits[$d])) { $i++; continue; } // už existuje → skip

            $isFirst = ($i === 0);
            $ins->execute([
                'cid'    => $contactId,
                'phone'  => $p,
                'digits' => $d,
                // Primární (první v listu) dostane operator pokud kontakt byl ověřen.
                // Sekundární s NULL → čistička doplní.
                'op'     => $isFirst ? $opUpper : null,
                'vat'    => $isFirst && $opUpper !== null ? date('Y-m-d H:i:s') : null,
                'pos'    => $i,
            ]);
            $i++;
        }

        // 4) Smazat řádky, jejichž telefon už není v aktuálním seznamu
        foreach ($existing as $e) {
            $d = (string) ($e['phone_digits'] ?? '');
            if ($d === '' || !isset($currentDigits[$d])) {
                $del->execute(['id' => (int) $e['id'], 'tid' => $tid]);
            }
        }
    }
}

if (!function_exists('crm_phone_evaluate_contact_status')) {
    /**
     * Vyhodnotí finální stav kontaktu na základě ověřených telefonů.
     *
     * Vrací:
     *   ['decision' => 'pending'|'READY'|'VF_SKIP'|'CHYBNY_KONTAKT',
     *    'operator' => 'TM'|'O2'|'VF'|''      kdo se má uložit do contacts.operator,
     *    'verified_count' => N,
     *    'total_count'    => N]
     *
     * Pravidla (jen pokud VŠECHNY telefony ověřené):
     *   - aspoň 1 NE-VF NE-CHYBNY → READY (operátor = první NE-VF NE-CHYBNY)
     *   - všechny VF              → VF_SKIP (operátor = VF)
     *   - všechny CHYBNY          → CHYBNY_KONTAKT (operátor = '')
     *   - mix VF + CHYBNY         → VF_SKIP (preferujeme VF)
     */
    function crm_phone_evaluate_contact_status(PDO $pdo, int $contactId): array
    {
        $phones = crm_phones_for_contact($pdo, $contactId);
        $total = count($phones);
        if ($total === 0) {
            return ['decision' => 'pending', 'operator' => '', 'verified_count' => 0, 'total_count' => 0];
        }
        $verified = 0;
        $hasGood = false;      // TM nebo O2
        $goodOperator = '';
        $allVf = true;
        $allChybny = true;
        $anyVf = false;
        foreach ($phones as $p) {
            $op = (string) ($p['operator'] ?? '');
            if ($op === '' || $p['verified_at'] === null) continue;
            $verified++;
            $opU = strtoupper($op);
            if ($opU !== 'VF') $allVf = false;
            if ($opU !== 'CHYBNY') $allChybny = false;
            if (in_array($opU, ['TM', 'O2'], true)) {
                $hasGood = true;
                if ($goodOperator === '') $goodOperator = $opU; // první dobrý
            }
            if ($opU === 'VF') $anyVf = true;
        }
        if ($verified < $total) {
            return ['decision' => 'pending', 'operator' => '',
                    'verified_count' => $verified, 'total_count' => $total];
        }
        // Všechny ověřené
        if ($hasGood) {
            return ['decision' => 'READY', 'operator' => $goodOperator,
                    'verified_count' => $verified, 'total_count' => $total];
        }
        if ($allChybny) {
            return ['decision' => 'CHYBNY_KONTAKT', 'operator' => '',
                    'verified_count' => $verified, 'total_count' => $total];
        }
        // Mix VF + CHYBNY → preferujeme VF_SKIP (telefon zákazníka VF, druhý špatný)
        return ['decision' => 'VF_SKIP', 'operator' => 'VF',
                'verified_count' => $verified, 'total_count' => $total];
    }
}
