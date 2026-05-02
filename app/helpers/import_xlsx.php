<?php
// e:\Snecinatripu\app\helpers\import_xlsx.php
declare(strict_types=1);

/**
 * Minimalistický XLSX → CSV converter (streaming).
 * Bez Composeru, bez PhpSpreadsheet — používá jen PHP built-in:
 *   - ZipArchive    (XLSX = ZIP archiv s XML soubory)
 *   - XMLReader     (streaming XML parser, low-memory)
 *
 * Pokrývá běžný export z Excel / LibreOffice:
 *   - text strings (sharedStrings + inline strings)
 *   - čísla (jako string, decimal . podle locale)
 *   - data jako čísla (Excel serial date) — převedeme na YYYY-MM-DD
 *   - boolean (TRUE/FALSE)
 *   - prázdné buňky → prázdný string ('')
 *
 * Limity (záměrné, kvůli jednoduchosti):
 *   - Bere POUZE první list (sheet1)
 *   - Vzorce se neevaluují — bere se uložená hodnota cache (Excel/LibreOffice ji ukládá)
 *   - Žádné formátování (barvy, fonty) se nepřevádí
 *   - Žádné podporu mergeCells (sloučené buňky se berou jen levá horní)
 */

if (!function_exists('crm_xlsx_to_csv')) {
    /**
     * Konvertuje XLSX soubor do CSV (stejný delimiter ; jako default v projektu).
     *
     * @param string $xlsxPath  Cesta ke vstupnímu .xlsx
     * @param string $csvPath   Cesta k výstupnímu .csv (přepíše pokud existuje)
     * @return array{
     *   ok: bool, rows?: int, error?: string,
     *   sheet_name?: string,           // Jméno zpracovaného listu (Excel tab name)
     *   sheet_count?: int,             // Kolik listů soubor obsahuje (pro UI varování)
     *   sheet_names?: list<string>     // Všechna jména listů (pro UI ukázku)
     * }
     */
    function crm_xlsx_to_csv(string $xlsxPath, string $csvPath): array
    {
        if (!is_file($xlsxPath)) {
            return ['ok' => false, 'error' => 'XLSX soubor nenalezen.'];
        }
        if (!class_exists(\ZipArchive::class)) {
            return ['ok' => false, 'error' => 'PHP rozšíření ZipArchive není dostupné — XLSX nelze otevřít.'];
        }
        if (!class_exists(\XMLReader::class)) {
            return ['ok' => false, 'error' => 'PHP rozšíření XMLReader není dostupné — XLSX nelze parsovat.'];
        }

        $zip = new \ZipArchive();
        $opened = $zip->open($xlsxPath, \ZipArchive::RDONLY);
        if ($opened !== true) {
            return ['ok' => false, 'error' => 'Soubor není platný XLSX (ZIP open code ' . (string) $opened . ').'];
        }

        // ── 1) Načíst sharedStrings (string lookup table) ────────────
        // Excel ukládá texty centralizovaně — buňky drží jen index.
        $sharedStrings = [];
        $ssIdx = $zip->locateName('xl/sharedStrings.xml');
        if ($ssIdx !== false) {
            $ssXml = $zip->getFromIndex($ssIdx);
            if ($ssXml !== false && $ssXml !== '') {
                $sharedStrings = crm_xlsx_parse_shared_strings($ssXml);
            }
        }

        // ── 2) Načíst seznam VŠECH listů a vybrat PRVNÍ podle Excel záložek ──
        // Důležité: sheet1.xml v ZIPu NEMUSÍ být první podle Excel záložek!
        // Workbook.xml definuje pořadí přes <sheet> elementy + r:id,
        // takže primárně používáme tu definici.
        $sheetsInfo = crm_xlsx_list_sheets($zip);  // ['names' => [...], 'firstIdx' => int|false]
        $sheetIdx   = $sheetsInfo['firstIdx'];
        $sheetNames = $sheetsInfo['names'];
        $sheetName  = $sheetNames[0] ?? '';

        if ($sheetIdx === false) {
            // Fallback: zkusit "sheet1.xml" když workbook.xml chybí
            $sheetIdx = $zip->locateName('xl/worksheets/sheet1.xml');
            if ($sheetIdx !== false && $sheetName === '') {
                $sheetName = '(Sheet1)';
            }
        }
        if ($sheetIdx === false) {
            $zip->close();
            return ['ok' => false, 'error' => 'V XLSX nebyl nalezen žádný list dat.'];
        }

        $sheetXml = $zip->getFromIndex($sheetIdx);
        $zip->close();
        if ($sheetXml === false || $sheetXml === '') {
            return ['ok' => false, 'error' => 'List XLSX je prázdný nebo nečitelný.'];
        }

        // ── 3) Stream parser sheetu, zápis do CSV ──────────────────────
        $out = fopen($csvPath, 'wb');
        if ($out === false) {
            return ['ok' => false, 'error' => 'Nelze vytvořit dočasný CSV soubor.'];
        }

        $reader = new \XMLReader();
        if (!$reader->XML($sheetXml, 'UTF-8', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            fclose($out);
            @unlink($csvPath);
            return ['ok' => false, 'error' => 'Nelze inicializovat XML reader pro list.'];
        }

        $rowCount = 0;
        $maxCol   = 0; // pro vyplnění chybějících sloupců prázdnými hodnotami

        // POZOR: NEVOLEJ $reader->next() po readOuterXML — způsobí přeskočení
        // každého druhého řádku, protože next() advance + read() v dalším iter
        // skočí DOVNITŘ dalšího <row> místo na něj. readOuterXML cursor neposouvá,
        // takže read() v dalším iter projde dovnitř aktuálního row (nepoškozeně),
        // zpracuje cells (ignoruje je), dojde na další <row> START a tu zpracujeme.
        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'row') {
                $rowXml  = $reader->readOuterXML();
                $rowData = crm_xlsx_parse_row($rowXml, $sharedStrings, $maxCol);
                if ($maxCol < count($rowData)) {
                    $maxCol = count($rowData);
                }
                fputcsv($out, $rowData, ';', '"', '\\');
                $rowCount++;
            }
        }
        $reader->close();
        fclose($out);

        return [
            'ok'          => true,
            'rows'        => $rowCount,
            'sheet_name'  => $sheetName,
            'sheet_count' => count($sheetNames),
            'sheet_names' => $sheetNames,
        ];
    }
}

if (!function_exists('crm_xlsx_list_sheets')) {
    /**
     * Vyparsuje seznam listů z workbook.xml + workbook.xml.rels a vrátí:
     *  - names:    pořadí jmen listů (jako v Excel záložkách)
     *  - firstIdx: ZIP index XML souboru prvního listu (false pokud nelze určit)
     *
     * @return array{names: list<string>, firstIdx: int|false}
     */
    function crm_xlsx_list_sheets(\ZipArchive $zip): array
    {
        $out = ['names' => [], 'firstIdx' => false];
        $wbXml = $zip->getFromName('xl/workbook.xml');
        if (!is_string($wbXml) || $wbXml === '') {
            return $out;
        }
        // Načti všechny <sheet name="X" r:id="rIdN"/> v pořadí
        if (!preg_match_all('/<sheet\b([^\/>]*)\/?>/i', $wbXml, $matches)) {
            return $out;
        }
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        $hasRels = is_string($relsXml) && $relsXml !== '';

        $firstSet = false;
        foreach ($matches[1] as $attrs) {
            $name = '';
            if (preg_match('/\bname="([^"]*)"/', $attrs, $nm)) {
                $name = crm_xlsx_decode_xml_entities($nm[1]);
            }
            $out['names'][] = $name;

            if (!$firstSet && $hasRels && preg_match('/\br:id="(rId\d+)"/', $attrs, $rm)) {
                $rId = $rm[1];
                if (preg_match('/<Relationship[^>]*Id="' . preg_quote($rId, '/') . '"[^>]*Target="([^"]+)"/i', $relsXml, $tm)) {
                    $target = ltrim($tm[1], '/');
                    if (!str_starts_with($target, 'xl/')) {
                        $target = 'xl/' . $target;
                    }
                    $idx = $zip->locateName($target);
                    if ($idx !== false) {
                        $out['firstIdx'] = $idx;
                        $firstSet = true;
                    }
                }
            }
        }
        return $out;
    }
}

if (!function_exists('crm_xlsx_locate_first_sheet')) {
    /**
     * Vrátí ZIP index XML souboru pro PRVNÍ list (podle pořadí Excel záložek).
     * Tenký wrapper okolo crm_xlsx_list_sheets() — kept pro zpětnou kompatibilitu.
     */
    function crm_xlsx_locate_first_sheet(\ZipArchive $zip): int|false
    {
        return crm_xlsx_list_sheets($zip)['firstIdx'];
    }
}

if (!function_exists('crm_xlsx_parse_shared_strings')) {
    /**
     * Parser xl/sharedStrings.xml — vrátí pole indexovaných stringů.
     * Každý <si> může obsahovat <t>...</t> (jednoduchý) nebo <r><t>part</t></r>... (s formátovanými částmi).
     *
     * @return list<string>
     */
    function crm_xlsx_parse_shared_strings(string $xml): array
    {
        $out = [];
        $reader = new \XMLReader();
        if (!$reader->XML($xml, 'UTF-8', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            return $out;
        }
        // Stejný princip jako u řádků sheet — žádné next(), aby se nepřeskakovaly
        // každé druhé <si> elementy. read() projde i děti, ignoruje je
        // (localName != 'si'), a postupně dojede na další <si>.
        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'si') {
                continue;
            }
            $siXml = $reader->readInnerXML();
            // Extrahuj VŠECHNY <t>...</t> (i uvnitř <r>) a sloč
            $text = '';
            if (preg_match_all('/<t[^>]*>(.*?)<\/t>/su', $siXml, $matches)) {
                $text = implode('', $matches[1]);
            }
            $out[] = crm_xlsx_decode_xml_entities($text);
        }
        $reader->close();
        return $out;
    }
}

if (!function_exists('crm_xlsx_decode_xml_entities')) {
    /** Dekódování XML entit + numerických referencí (&#x20;, &lt; atd.). */
    function crm_xlsx_decode_xml_entities(string $s): string
    {
        // html_entity_decode pokrývá ENT_XML1 přesně (&amp; &lt; &gt; &quot; &apos;)
        // i numerické (&#39; &#x20;)
        return html_entity_decode($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('crm_xlsx_parse_row')) {
    /**
     * Parse <row>...</row> XML kus na pole hodnot (po sloupcích).
     * Cell reference (např. "C5") určuje pozici, takže prázdné buňky vyplníme ''.
     *
     * @param list<string> $sharedStrings
     * @param int $minCols  Minimální počet sloupců (kvůli zarovnání s předchozími řádky)
     * @return list<string>
     */
    function crm_xlsx_parse_row(string $rowXml, array $sharedStrings, int $minCols = 0): array
    {
        $cells = []; // index => value

        // Iterace přes <c r="A1" t="s" s="2"><v>0</v></c> elementy
        // r = reference (A1, B1, ...), t = type (s|b|str|inlineStr|n|d|...), v = value
        if (preg_match_all('/<c\b([^>]*)>(.*?)<\/c>|<c\b([^>]*)\/>/su', $rowXml, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $attrs   = $m[1] !== '' ? $m[1] : ($m[3] ?? '');
                $inner   = $m[2] ?? '';
                $ref     = '';
                $type    = '';
                if (preg_match('/\br="([^"]+)"/', $attrs, $rm)) {
                    $ref = $rm[1];
                }
                if (preg_match('/\bt="([^"]+)"/', $attrs, $tm)) {
                    $type = $tm[1];
                }
                $colIdx = crm_xlsx_col_index($ref);

                $value = '';
                // Inline string: <c t="inlineStr"><is><t>X</t></is></c>
                if ($type === 'inlineStr') {
                    if (preg_match_all('/<t[^>]*>(.*?)<\/t>/su', $inner, $tm)) {
                        $value = crm_xlsx_decode_xml_entities(implode('', $tm[1]));
                    }
                } elseif ($type === 'str') {
                    // Formula string result: <c t="str"><v>...</v></c>
                    if (preg_match('/<v[^>]*>(.*?)<\/v>/su', $inner, $vm)) {
                        $value = crm_xlsx_decode_xml_entities($vm[1]);
                    }
                } elseif ($type === 's') {
                    // Shared string — index do sharedStrings
                    if (preg_match('/<v[^>]*>(.*?)<\/v>/su', $inner, $vm)) {
                        $idx = (int) $vm[1];
                        $value = $sharedStrings[$idx] ?? '';
                    }
                } elseif ($type === 'b') {
                    // Boolean: 1 / 0
                    if (preg_match('/<v[^>]*>(.*?)<\/v>/su', $inner, $vm)) {
                        $value = $vm[1] === '1' ? 'TRUE' : 'FALSE';
                    }
                } else {
                    // Number / date / default — bere se hodnota tak jak je
                    if (preg_match('/<v[^>]*>(.*?)<\/v>/su', $inner, $vm)) {
                        $value = $vm[1];
                    }
                }

                if ($colIdx >= 0) {
                    $cells[$colIdx] = $value;
                }
            }
        }

        // Vyplnit pole od 0 do max(maxIdx, minCols-1) — chybějící sloupce = ''
        if ($cells === []) {
            return $minCols > 0 ? array_fill(0, $minCols, '') : [];
        }
        $maxIdx = max(array_keys($cells));
        if ($minCols > 0 && $minCols - 1 > $maxIdx) {
            $maxIdx = $minCols - 1;
        }
        $out = [];
        for ($i = 0; $i <= $maxIdx; $i++) {
            $out[] = $cells[$i] ?? '';
        }
        return $out;
    }
}

if (!function_exists('crm_xlsx_col_index')) {
    /**
     * Konverze cell reference (např. "AB12") na 0-based index sloupce.
     * "A" → 0, "B" → 1, ..., "Z" → 25, "AA" → 26, "AB" → 27, ...
     */
    function crm_xlsx_col_index(string $ref): int
    {
        if ($ref === '') {
            return -1;
        }
        // Vyber jen písmenovou část (před prvním číslem)
        $letters = '';
        for ($i = 0, $n = strlen($ref); $i < $n; $i++) {
            $ch = $ref[$i];
            if ($ch >= 'A' && $ch <= 'Z') {
                $letters .= $ch;
            } elseif ($ch >= 'a' && $ch <= 'z') {
                $letters .= strtoupper($ch);
            } else {
                break;
            }
        }
        if ($letters === '') {
            return -1;
        }
        $idx = 0;
        for ($i = 0, $n = strlen($letters); $i < $n; $i++) {
            $idx = $idx * 26 + (ord($letters[$i]) - ord('A') + 1);
        }
        return $idx - 1;
    }
}
