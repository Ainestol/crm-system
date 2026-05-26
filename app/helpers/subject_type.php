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

        // Vzory právních forem — typicky se píší se / bez teček a mezer
        // Hledáme jako "celé slovo" (boundary), aby se nematchovalo třeba "kasoso"
        $patterns = [
            's\.\s?r\.\s?o',          // s.r.o., s. r. o., s.r. o.
            'spol\.\s?s\s?r\.\s?o',   // spol. s r.o.
            's\.r\.o\.?',             // bez mezer
            'a\.\s?s\b',              // a.s., a. s.
            'a\.s\.?',
            'v\.\s?o\.\s?s\b',        // v.o.s., v. o. s.
            'v\.o\.s\.?',
            'k\.\s?s\b',              // k.s. (komanditní společnost)
            'k\.s\.?',
            'družstvo',
            'spolek',
            'fond',                   // investiční fond, sluneční fond
            'o\.\s?p\.\s?s\b',        // o.p.s. (obecně prospěšná spol.)
            'o\.p\.s\.?',
            'z\.\s?s\b',              // z.s. (zapsaný spolek)
            'z\.s\.?',
            'z\.\s?ú\b',              // z.ú. (zapsaný ústav)
            'z\.ú\.?',
            'zoo\b',                  // zoo = může být firma (např. ZOO Praha) — opatrně
            'nadace',
            // International (občas v ČR)
            'gmbh',
            'ltd\.?',
            'limited',
            'inc\.?',
            'corp\.?',
            'plc\b',
            'sa\b',                   // S.A., AG, atd. — opatrně, krátké
            'gesellschaft',
            'company',
        ];

        foreach ($patterns as $p) {
            // Hledáme jako fragment slova (\b... \b nebo na konci řetězce)
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
}
