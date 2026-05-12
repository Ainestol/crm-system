<?php
declare(strict_types=1);

/**
 * app/helpers/xlsx_writer.php
 *
 * Minimální XLSX writer (Excel 2007+ formát) pomocí čistého PHP + ZipArchive.
 * Bez externí knihovny / composeru.
 *
 * Limitace:
 *   - 1 sheet per soubor
 *   - žádné formátování (jen inline strings)
 *   - bez stylů, vzorců, sloučených buněk
 *
 * Pro běžný „seznam kontaktů → Excel" je to přesně to, co potřebujeme.
 *
 * Použití:
 *   crm_xlsx_send_download(
 *       'leady.xlsx',
 *       ['Firma', 'Telefon', 'Email'],
 *       $iteratorOfRows  // pole nebo generátor pole skalárních hodnot
 *   );
 *   // funkce sama nastaví HTTP headers + ukončí PHP přes exit
 */

if (!function_exists('crm_xlsx_col_letter')) {
    /**
     * Konvertuje číslo sloupce (1-based) na Excel písmena: 1→A, 26→Z, 27→AA.
     */
    function crm_xlsx_col_letter(int $col): string
    {
        $s = '';
        while ($col > 0) {
            $col--;
            $s = chr(65 + ($col % 26)) . $s;
            $col = (int) ($col / 26);
        }
        return $s;
    }
}

if (!function_exists('crm_xlsx_escape')) {
    /**
     * Escape pro XML inline string content.
     */
    function crm_xlsx_escape(string $v): string
    {
        // ENT_XML1 ošetří <, >, &, ", ' — vše co XML potřebuje.
        // Plus odstraníme control znaky, které XML nedovoluje (0x00–0x1F kromě \t\n\r).
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v) ?? $v;
        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('crm_xlsx_send_download')) {
    /**
     * Pošle XLSX soubor jako HTTP download.
     *
     * @param string                    $filename Název souboru (s příponou .xlsx)
     * @param list<string>              $headers  Hlavička sloupců
     * @param iterable<list<scalar|null>> $rows   Řádky dat (každý je array hodnot)
     */
    function crm_xlsx_send_download(string $filename, array $headers, iterable $rows): void
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('PHP extension "zip" není k dispozici — XLSX export nelze sestavit.');
        }

        // Dočasný soubor pro ZIP
        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        if ($tmpFile === false) {
            throw new \RuntimeException('Nelze vytvořit dočasný soubor pro XLSX.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpFile, ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Nelze otevřít ZIP archiv pro XLSX.');
        }

        // ── 1. [Content_Types].xml ────────────────────────────────────
        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '</Types>'
        );

        // ── 2. _rels/.rels ────────────────────────────────────────────
        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>'
        );

        // ── 3. xl/_rels/workbook.xml.rels ─────────────────────────────
        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
            '</Relationships>'
        );

        // ── 4. xl/workbook.xml ────────────────────────────────────────
        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" ' .
            'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheets><sheet name="Data" sheetId="1" r:id="rId1"/></sheets>' .
            '</workbook>'
        );

        // ── 5. xl/worksheets/sheet1.xml ───────────────────────────────
        // Sestavujeme v paměti (pro 5000 řádků OK; pro extrémně velké by chtělo streaming).
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
               '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
               '<sheetData>';

        $rowNum = 1;

        // Hlavička
        $xml .= '<row r="' . $rowNum . '">';
        foreach (array_values($headers) as $idx => $h) {
            $col = crm_xlsx_col_letter($idx + 1);
            $xml .= '<c r="' . $col . $rowNum . '" t="inlineStr"><is><t>' .
                    crm_xlsx_escape((string) $h) . '</t></is></c>';
        }
        $xml .= '</row>';
        $rowNum++;

        // Data řádky
        foreach ($rows as $row) {
            $xml .= '<row r="' . $rowNum . '">';
            foreach (array_values((array) $row) as $idx => $v) {
                if ($v === null || $v === '') {
                    continue; // prázdné buňky se vynechávají
                }
                $col = crm_xlsx_col_letter($idx + 1);
                $xml .= '<c r="' . $col . $rowNum . '" t="inlineStr"><is><t>' .
                        crm_xlsx_escape((string) $v) . '</t></is></c>';
            }
            $xml .= '</row>';
            $rowNum++;
        }

        $xml .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);

        $zip->close();

        // ── HTTP download ─────────────────────────────────────────────
        if (function_exists('header_remove')) {
            header_remove('Content-Type');
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string) filesize($tmpFile));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        readfile($tmpFile);
        @unlink($tmpFile);
        exit;
    }
}
