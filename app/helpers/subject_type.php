<?php
declare(strict_types=1);

/**
 * app/helpers/subject_type.php
 *
 * Detekce typu subjektu (firma vs OSVČ) z názvu firmy.
 *
 * Princip:
 *   • V názvu se hledají Czech / international právní formy (s.r.o., a.s., ...)
 *   • Pokud match → 'firma' (právnická osoba)
 *   • Jinak → 'osvc' (typicky jméno + příjmení, případně + obor)
 *
 * Heuristika není 100% — některé OSVČ se pojmenovaly jako firmy bez s.r.o.,
 * ale na 95% Czech databází funguje. Pro 100% správnost je nutné ARES.
 */

if (!function_exists('crm_detect_subject_type')) {

    /**
     * Vrátí 'firma' nebo 'osvc' podle obsahu názvu firmy.
     *
     * @param string $firmaName Název firmy / jméno OSVČ
     * @return string 'firma' | 'osvc'
     */
    function crm_detect_subject_type(string $firmaName): string
    {
        $name = trim($firmaName);
        if ($name === '') {
            return 'osvc'; // empty/unknown → fallback OSVČ
        }

        // Normalizace na lowercase + odstranění hluboké interpunkce
        $normalized = mb_strtolower($name, 'UTF-8');

        // Vzory právních forem — robustní detekce.
        // \W*  = jakékoliv non-word znaky mezi písmeny (tečka, mezera, čárka)
        // \b   = slovní hranice (pro krátké zkratky jako a.s.)
        // Pokrývá: s.r.o., s. r. o., s r.o., s,r.o, S.R.O., atd.
        $patterns = [
            's\W*r\W*o',              // s.r.o. všech variant (i bez tečky před r)
            'spol\W*s\W*r\W*o',       // spol. s r.o.
            'spole[čc]nost',          // "společnost s ručením omezeným" (i bez háčku)
            'a\W*s\b',                // a.s., a. s., a s
            'v\W*o\W*s\b',            // v.o.s.
            'k\W*s\b',                // k.s.
            'o\W*p\W*s\b',            // o.p.s.
            'z\W*s\b',                // z.s.
            'z\W*ú\b',                // z.ú.
            'dru[žz]stvo',            // družstvo i druzstvo (bez háčku)
            'spolek',
            'fond\b',
            'nadace',
            // International (občas v ČR)
            'gmbh',
            'ltd\b',
            'limited',
            'inc\b',
            'corp\b',
            'plc\b',
            'gesellschaft',
            'company',
        ];

        foreach ($patterns as $p) {
            // Hledáme s tolerantními okrajovými znaky (mezery, pomlčky, čárky, tečky)
            if (preg_match('/(^|[\s\-,\.])' . $p . '($|[\s\-,\.])/iu', $normalized)) {
                return 'firma';
            }
        }

        // Fallback heuristika: kontakty jejichž název vypadá jako "Jméno Příjmení"
        // (= 2-4 slova, žádné speciální znaky firem) zařadíme do OSVČ.
        // Tj. všechno, co nepřipomíná firmu, je OSVČ.
        return 'osvc';
    }


    /**
     * Bulk-detekce + UPDATE pro kontakty, kde subject_type='unknown'.
     * Vrací počet aktualizovaných řádků.
     *
     * Voláno buď manuálně z admin tool nebo automaticky při mix akci.
     */
    function crm_backfill_subject_type(PDO $pdo, ?int $limit = null): int
    {
        $sql = "SELECT id, firma FROM contacts WHERE subject_type = 'unknown'";
        if ($limit !== null && $limit > 0) {
            $sql .= " LIMIT " . (int) $limit;
        }
        $rows = $pdo->query($sql);
        if (!$rows) return 0;

        $upd = $pdo->prepare("UPDATE contacts SET subject_type = :t WHERE id = :id");
        $updated = 0;
        foreach ($rows as $r) {
            $type = crm_detect_subject_type((string) ($r['firma'] ?? ''));
            $upd->execute(['t' => $type, 'id' => (int) $r['id']]);
            $updated++;
        }
        return $updated;
    }

    /**
     * Reklasifikace VŠECH kontaktů — přepočítá subject_type podle aktuální heuristiky.
     * Vrací počet kontaktů, kterým se hodnota ZMĚNILA (= reálná oprava).
     *
     * Použít po update heuristiky (nový regex pattern) — opraví dříve špatně
     * klasifikované kontakty. Batchované po 500 pro výkon.
     */
    function crm_reclassify_all_subject_types(PDO $pdo): array
    {
        $rows = $pdo->query("SELECT id, firma, subject_type FROM contacts WHERE firma IS NOT NULL AND firma <> ''");
        if (!$rows) return ['total' => 0, 'changed' => 0, 'firma' => 0, 'osvc' => 0];

        $changes = ['firma' => [], 'osvc' => []]; // batchy IDs per nový typ
        $total = 0;
        $changedCount = 0;

        while ($r = $rows->fetch(PDO::FETCH_ASSOC)) {
            $total++;
            $newType = crm_detect_subject_type((string) ($r['firma'] ?? ''));
            $oldType = (string) ($r['subject_type'] ?? '');
            if ($newType !== $oldType) {
                $changes[$newType][] = (int) $r['id'];
                $changedCount++;
            }
        }

        // Batch UPDATE po 500 pro každý typ
        $byType = ['firma' => 0, 'osvc' => 0];
        foreach ($changes as $type => $ids) {
            if ($ids === []) continue;
            foreach (array_chunk($ids, 500) as $chunk) {
                $ph = implode(',', array_fill(0, count($chunk), '?'));
                $upd = $pdo->prepare("UPDATE contacts SET subject_type = ? WHERE id IN ($ph)");
                $upd->execute(array_merge([$type], $chunk));
                $byType[$type] += count($chunk);
            }
        }

        return [
            'total'   => $total,
            'changed' => $changedCount,
            'firma'   => $byType['firma'],
            'osvc'    => $byType['osvc'],
        ];
    }
}
