<?php
// e:\Snecinatripu\app\helpers\import_csv.php
declare(strict_types=1);

/**
 * Pomůcky pro import kontaktů z CSV (DNC, duplicity v DB i v souboru).
 */

if (!function_exists('crm_import_normalize_region')) {
    /**
     * Převede český název kraje (i s diakritikou) na interní kód.
     * Přijme: 'Jihomoravský kraj', 'jihomoravský', 'Brno', 'praha', 'Praha' atd.
     */
    function crm_import_normalize_region(?string $raw): string
    {
        if ($raw === null) {
            return '';
        }
        $s = mb_strtolower(trim($raw), 'UTF-8');
        if ($s === '') {
            return '';
        }

        // Přímé kódy – pokud už přichází správný kód, vrátíme ho
        static $validCodes = [
            'praha', 'stredocesky', 'jihocesky', 'plzensky', 'karlovarsky',
            'ustecky', 'liberecky', 'kralovehradecky', 'pardubicky', 'vysocina',
            'jihomoravsky', 'olomoucky', 'zlinsky', 'moravskoslezsky',
        ];
        if (in_array($s, $validCodes, true)) {
            return $s;
        }

        // Mapování českých názvů (s diakritikou i bez) na kódy
        static $map = [
            // Praha
            'hlavní město praha' => 'praha',
            'hlavni mesto praha' => 'praha',
            'hl. m. praha' => 'praha',
            'hl.m.praha' => 'praha',
            // Středočeský
            'středočeský kraj' => 'stredocesky',
            'středočeský' => 'stredocesky',
            'stredocesky kraj' => 'stredocesky',
            // Jihočeský
            'jihočeský kraj' => 'jihocesky',
            'jihočeský' => 'jihocesky',
            'jihocesky kraj' => 'jihocesky',
            // Plzeňský
            'plzeňský kraj' => 'plzensky',
            'plzeňský' => 'plzensky',
            'plzensky kraj' => 'plzensky',
            // Karlovarský
            'karlovarský kraj' => 'karlovarsky',
            'karlovarský' => 'karlovarsky',
            'karlovarsky kraj' => 'karlovarsky',
            // Ústecký
            'ústecký kraj' => 'ustecky',
            'ústecký' => 'ustecky',
            'ustecky kraj' => 'ustecky',
            // Liberecký
            'liberecký kraj' => 'liberecky',
            'liberecký' => 'liberecky',
            'liberecky kraj' => 'liberecky',
            // Královéhradecký
            'královéhradecký kraj' => 'kralovehradecky',
            'královéhradecký' => 'kralovehradecky',
            'kralovehradecky kraj' => 'kralovehradecky',
            // Pardubický
            'pardubický kraj' => 'pardubicky',
            'pardubický' => 'pardubicky',
            'pardubicky kraj' => 'pardubicky',
            // Vysočina
            'kraj vysočina' => 'vysocina',
            'kraj vysocina' => 'vysocina',
            'vysočina' => 'vysocina',
            // Jihomoravský
            'jihomoravský kraj' => 'jihomoravsky',
            'jihomoravský' => 'jihomoravsky',
            'jihomoravsky kraj' => 'jihomoravsky',
            'brno' => 'jihomoravsky',
            // Olomoucký
            'olomoucký kraj' => 'olomoucky',
            'olomoucký' => 'olomoucky',
            'olomoucky kraj' => 'olomoucky',
            // Zlínský
            'zlínský kraj' => 'zlinsky',
            'zlínský' => 'zlinsky',
            'zlinsky kraj' => 'zlinsky',
            // Moravskoslezský
            'moravskoslezský kraj' => 'moravskoslezsky',
            'moravskoslezský' => 'moravskoslezsky',
            'moravskoslezsky kraj' => 'moravskoslezsky',
        ];

        return $map[$s] ?? $s;
    }
}

if (!function_exists('crm_import_city_to_region')) {
    /**
     * Převede název města na kód kraje.
     * Pokrývá ~120 největších měst ČR. Vrací '' pokud město nerozpozná.
     */
    function crm_import_city_to_region(?string $city): string
    {
        if ($city === null) {
            return '';
        }
        $s = mb_strtolower(trim($city), 'UTF-8');
        if ($s === '') {
            return '';
        }
        // Odstranit čísla, PSČ, "ul.", čárky – normalizace
        $s = preg_replace('/\d+/', '', $s) ?? $s;
        $s = preg_replace('/[,;\/].*$/', '', $s); // vše za čárkou pryč
        $s = trim($s, " \t\n\r\0\x0B.-");

        static $map = [
            // ── Praha ──
            'praha' => 'praha',
            'prague' => 'praha',

            // ── Středočeský kraj ──
            'příbram' => 'stredocesky',
            'pribram' => 'stredocesky',
            'kladno' => 'stredocesky',
            'mladá boleslav' => 'stredocesky',
            'mlada boleslav' => 'stredocesky',
            'kolín' => 'stredocesky',
            'kolin' => 'stredocesky',
            'kutná hora' => 'stredocesky',
            'kutna hora' => 'stredocesky',
            'mělník' => 'stredocesky',
            'melnik' => 'stredocesky',
            'benešov' => 'stredocesky',
            'benesov' => 'stredocesky',
            'beroun' => 'stredocesky',
            'nymburk' => 'stredocesky',
            'rakovník' => 'stredocesky',
            'rakovnik' => 'stredocesky',
            'brandýs nad labem' => 'stredocesky',
            'brandys nad labem' => 'stredocesky',
            'čelákovice' => 'stredocesky',
            'celakovice' => 'stredocesky',
            'kralupy nad vltavou' => 'stredocesky',
            'říčany' => 'stredocesky',
            'ricany' => 'stredocesky',
            'neratovice' => 'stredocesky',
            'lysá nad labem' => 'stredocesky',
            'lysa nad labem' => 'stredocesky',
            'poděbrady' => 'stredocesky',
            'podebrady' => 'stredocesky',
            'slaný' => 'stredocesky',
            'slany' => 'stredocesky',
            'vlašim' => 'stredocesky',
            'vlasim' => 'stredocesky',
            'čáslav' => 'stredocesky',
            'caslav' => 'stredocesky',
            'mnichovo hradiště' => 'stredocesky',
            'mnichovo hradiste' => 'stredocesky',

            // ── Jihočeský kraj ──
            'české budějovice' => 'jihocesky',
            'ceske budejovice' => 'jihocesky',
            'tábor' => 'jihocesky',
            'tabor' => 'jihocesky',
            'písek' => 'jihocesky',
            'pisek' => 'jihocesky',
            'strakonice' => 'jihocesky',
            'jindřichův hradec' => 'jihocesky',
            'jindrichuv hradec' => 'jihocesky',
            'český krumlov' => 'jihocesky',
            'cesky krumlov' => 'jihocesky',
            'prachatice' => 'jihocesky',
            'třeboň' => 'jihocesky',
            'trebon' => 'jihocesky',
            'milevsko' => 'jihocesky',
            'vimperk' => 'jihocesky',
            'soběslav' => 'jihocesky',
            'sobeslav' => 'jihocesky',

            // ── Plzeňský kraj ──
            'plzeň' => 'plzensky',
            'plzen' => 'plzensky',
            'klatovy' => 'plzensky',
            'rokycany' => 'plzensky',
            'domažlice' => 'plzensky',
            'domazlice' => 'plzensky',
            'tachov' => 'plzensky',
            'sušice' => 'plzensky',
            'susice' => 'plzensky',
            'nýřany' => 'plzensky',
            'nyrany' => 'plzensky',
            'stříbro' => 'plzensky',
            'stribro' => 'plzensky',
            'horšovský týn' => 'plzensky',
            'horsovsky tyn' => 'plzensky',

            // ── Karlovarský kraj ──
            'karlovy vary' => 'karlovarsky',
            'k. vary' => 'karlovarsky',
            'cheb' => 'karlovarsky',
            'sokolov' => 'karlovarsky',
            'mariánské lázně' => 'karlovarsky',
            'marianske lazne' => 'karlovarsky',
            'františkovy lázně' => 'karlovarsky',
            'frantiskovy lazne' => 'karlovarsky',
            'aš' => 'karlovarsky',
            'as' => 'karlovarsky',
            'ostrov' => 'karlovarsky',
            'chodov' => 'karlovarsky',
            'nejdek' => 'karlovarsky',

            // ── Ústecký kraj ──
            'ústí nad labem' => 'ustecky',
            'usti nad labem' => 'ustecky',
            'teplice' => 'ustecky',
            'děčín' => 'ustecky',
            'decin' => 'ustecky',
            'most' => 'ustecky',
            'chomutov' => 'ustecky',
            'litoměřice' => 'ustecky',
            'litomerice' => 'ustecky',
            'louny' => 'ustecky',
            'žatec' => 'ustecky',
            'zatec' => 'ustecky',
            'kadaň' => 'ustecky',
            'kadan' => 'ustecky',
            'jirkov' => 'ustecky',
            'bílina' => 'ustecky',
            'bilina' => 'ustecky',
            'roudnice nad labem' => 'ustecky',
            'rumburk' => 'ustecky',
            'varnsdorf' => 'ustecky',

            // ── Liberecký kraj ──
            'liberec' => 'liberecky',
            'jablonec nad nisou' => 'liberecky',
            'česká lípa' => 'liberecky',
            'ceska lipa' => 'liberecky',
            'turnov' => 'liberecky',
            'semily' => 'liberecky',
            'nový bor' => 'liberecky',
            'novy bor' => 'liberecky',
            'tanvald' => 'liberecky',
            'železný brod' => 'liberecky',
            'zelezny brod' => 'liberecky',
            'frýdlant' => 'liberecky',
            'frydlant' => 'liberecky',
            'jilemnice' => 'liberecky',

            // ── Královéhradecký kraj ──
            'hradec králové' => 'kralovehradecky',
            'hradec kralove' => 'kralovehradecky',
            'trutnov' => 'kralovehradecky',
            'náchod' => 'kralovehradecky',
            'nachod' => 'kralovehradecky',
            'jičín' => 'kralovehradecky',
            'jicin' => 'kralovehradecky',
            'rychnov nad kněžnou' => 'kralovehradecky',
            'rychnov nad kneznou' => 'kralovehradecky',
            'dvůr králové nad labem' => 'kralovehradecky',
            'dvur kralove nad labem' => 'kralovehradecky',
            'vrchlabí' => 'kralovehradecky',
            'vrchlabi' => 'kralovehradecky',
            'jaroměř' => 'kralovehradecky',
            'jaromer' => 'kralovehradecky',
            'nová paka' => 'kralovehradecky',
            'nova paka' => 'kralovehradecky',
            'hořice' => 'kralovehradecky',
            'horice' => 'kralovehradecky',
            'broumov' => 'kralovehradecky',

            // ── Pardubický kraj ──
            'pardubice' => 'pardubicky',
            'chrudim' => 'pardubicky',
            'svitavy' => 'pardubicky',
            'ústí nad orlicí' => 'pardubicky',
            'usti nad orlici' => 'pardubicky',
            'litomyšl' => 'pardubicky',
            'litomysl' => 'pardubicky',
            'moravská třebová' => 'pardubicky',
            'moravska trebova' => 'pardubicky',
            'vysoké mýto' => 'pardubicky',
            'vysoke myto' => 'pardubicky',
            'česká třebová' => 'pardubicky',
            'ceska trebova' => 'pardubicky',
            'lanškroun' => 'pardubicky',
            'lanskroun' => 'pardubicky',
            'polička' => 'pardubicky',
            'policka' => 'pardubicky',
            'hlinsko' => 'pardubicky',

            // ── Kraj Vysočina ──
            'jihlava' => 'vysocina',
            'třebíč' => 'vysocina',
            'trebic' => 'vysocina',
            'žďár nad sázavou' => 'vysocina',
            'zdar nad sazavou' => 'vysocina',
            'havlíčkův brod' => 'vysocina',
            'havlickuv brod' => 'vysocina',
            'pelhřimov' => 'vysocina',
            'pelhrimov' => 'vysocina',
            'nové město na moravě' => 'vysocina',
            'nove mesto na morave' => 'vysocina',
            'chotěboř' => 'vysocina',
            'chotebor' => 'vysocina',
            'velké meziříčí' => 'vysocina',
            'velke mezirici' => 'vysocina',
            'bystřice nad pernštejnem' => 'vysocina',
            'bystrice nad pernstejnem' => 'vysocina',
            'humpolec' => 'vysocina',
            'světlá nad sázavou' => 'vysocina',
            'svetla nad sazavou' => 'vysocina',
            'pacov' => 'vysocina',
            'moravské budějovice' => 'vysocina',
            'moravske budejovice' => 'vysocina',
            'náměšť nad oslavou' => 'vysocina',
            'namest nad oslavou' => 'vysocina',

            // ── Jihomoravský kraj ──
            'brno' => 'jihomoravsky',
            'znojmo' => 'jihomoravsky',
            'břeclav' => 'jihomoravsky',
            'breclav' => 'jihomoravsky',
            'hodonín' => 'jihomoravsky',
            'hodonin' => 'jihomoravsky',
            'vyškov' => 'jihomoravsky',
            'vyskov' => 'jihomoravsky',
            'blansko' => 'jihomoravsky',
            'boskovice' => 'jihomoravsky',
            'kyjov' => 'jihomoravsky',
            'veselí nad moravou' => 'jihomoravsky',
            'veseli nad moravou' => 'jihomoravsky',
            'mikulov' => 'jihomoravsky',
            'slavkov u brna' => 'jihomoravsky',
            'kuřim' => 'jihomoravsky',
            'kurim' => 'jihomoravsky',
            'tišnov' => 'jihomoravsky',
            'tisnov' => 'jihomoravsky',
            'ivančice' => 'jihomoravsky',
            'ivancice' => 'jihomoravsky',
            'hustopeče' => 'jihomoravsky',
            'hustopece' => 'jihomoravsky',
            'pohořelice' => 'jihomoravsky',
            'pohorelice' => 'jihomoravsky',

            // ── Olomoucký kraj ──
            'olomouc' => 'olomoucky',
            'přerov' => 'olomoucky',
            'prerov' => 'olomoucky',
            'prostějov' => 'olomoucky',
            'prostejov' => 'olomoucky',
            'šumperk' => 'olomoucky',
            'sumperk' => 'olomoucky',
            'jeseník' => 'olomoucky',
            'jesenik' => 'olomoucky',
            'zábřeh' => 'olomoucky',
            'zabreh' => 'olomoucky',
            'hranice' => 'olomoucky',
            'šternberk' => 'olomoucky',
            'sternberk' => 'olomoucky',
            'litovel' => 'olomoucky',
            'kojetín' => 'olomoucky',
            'kojetin' => 'olomoucky',
            'mohelnice' => 'olomoucky',
            'uničov' => 'olomoucky',
            'unicov' => 'olomoucky',

            // ── Zlínský kraj ──
            'zlín' => 'zlinsky',
            'zlin' => 'zlinsky',
            'vsetín' => 'zlinsky',
            'vsetin' => 'zlinsky',
            'kroměříž' => 'zlinsky',
            'kromeriz' => 'zlinsky',
            'uherské hradiště' => 'zlinsky',
            'uherske hradiste' => 'zlinsky',
            'valašské meziříčí' => 'zlinsky',
            'valasske mezirici' => 'zlinsky',
            'rožnov pod radhoštěm' => 'zlinsky',
            'roznov pod radhostem' => 'zlinsky',
            'otrokovice' => 'zlinsky',
            'uherský brod' => 'zlinsky',
            'uhersky brod' => 'zlinsky',
            'vizovice' => 'zlinsky',
            'holešov' => 'zlinsky',
            'holesov' => 'zlinsky',
            'luhačovice' => 'zlinsky',
            'luhacovice' => 'zlinsky',
            'napajedla' => 'zlinsky',
            'bystřice pod hostýnem' => 'zlinsky',
            'bystrice pod hostynem' => 'zlinsky',
            'hulín' => 'zlinsky',
            'hulin' => 'zlinsky',

            // ── Moravskoslezský kraj ──
            'ostrava' => 'moravskoslezsky',
            'opava' => 'moravskoslezsky',
            'havířov' => 'moravskoslezsky',
            'havirov' => 'moravskoslezsky',
            'karviná' => 'moravskoslezsky',
            'karvina' => 'moravskoslezsky',
            'frýdek-místek' => 'moravskoslezsky',
            'frydek-mistek' => 'moravskoslezsky',
            'frýdek místek' => 'moravskoslezsky',
            'frydek mistek' => 'moravskoslezsky',
            'třinec' => 'moravskoslezsky',
            'trinec' => 'moravskoslezsky',
            'nový jičín' => 'moravskoslezsky',
            'novy jicin' => 'moravskoslezsky',
            'orlová' => 'moravskoslezsky',
            'orlova' => 'moravskoslezsky',
            'český těšín' => 'moravskoslezsky',
            'cesky tesin' => 'moravskoslezsky',
            'bruntál' => 'moravskoslezsky',
            'bruntal' => 'moravskoslezsky',
            'krnov' => 'moravskoslezsky',
            'bohumín' => 'moravskoslezsky',
            'bohumin' => 'moravskoslezsky',
            'hlučín' => 'moravskoslezsky',
            'hlucin' => 'moravskoslezsky',
            'kopřivnice' => 'moravskoslezsky',
            'koprivnice' => 'moravskoslezsky',
            'příbor' => 'moravskoslezsky',
            'pribor' => 'moravskoslezsky',
            'studénka' => 'moravskoslezsky',
            'studenka' => 'moravskoslezsky',
            'bílovec' => 'moravskoslezsky',
            'bilovec' => 'moravskoslezsky',
            'frenštát pod radhoštěm' => 'moravskoslezsky',
            'frenstat pod radhostem' => 'moravskoslezsky',
            'rychvald' => 'moravskoslezsky',
            'petřvald' => 'moravskoslezsky',
            'petrvald' => 'moravskoslezsky',
        ];

        return $map[$s] ?? '';
    }
}

if (!function_exists('crm_import_normalize_ico')) {
    function crm_import_normalize_ico(?string $ico): string
    {
        if ($ico === null) {
            return '';
        }
        $s = trim($ico);
        if ($s === '') {
            return '';
        }
        return preg_replace('/\s+/', '', $s) ?? '';
    }
}

if (!function_exists('crm_import_phone_digits')) {
    /** Pouze číslice pro porovnání telefonů. */
    function crm_import_phone_digits(?string $phone): string
    {
        if ($phone === null) {
            return '';
        }
        $d = preg_replace('/\D+/', '', $phone) ?? '';
        return $d;
    }
}

if (!function_exists('crm_import_normalize_email')) {
    function crm_import_normalize_email(?string $email): string
    {
        if ($email === null) {
            return '';
        }
        return strtolower(trim($email));
    }
}

if (!function_exists('crm_import_row_hits_dnc')) {
    function crm_import_row_hits_dnc(PDO $pdo, ?string $ico, ?string $telefon, ?string $email): bool
    {
        $icoN = crm_import_normalize_ico($ico);
        if ($icoN !== '') {
            $st = $pdo->prepare(
                'SELECT id FROM dnc_list WHERE ico IS NOT NULL AND TRIM(ico) <> "" AND TRIM(ico) = :ico LIMIT 1'
            );
            $st->execute(['ico' => $icoN]);
            if ($st->fetch()) {
                return true;
            }
        }
        $em = crm_import_normalize_email($email);
        if ($em !== '') {
            $st = $pdo->prepare(
                'SELECT id FROM dnc_list WHERE email IS NOT NULL AND LOWER(TRIM(email)) = :em LIMIT 1'
            );
            $st->execute(['em' => $em]);
            if ($st->fetch()) {
                return true;
            }
        }
        $pd = crm_import_phone_digits($telefon);
        if ($pd !== '') {
            $st = $pdo->prepare(
                "SELECT id FROM dnc_list WHERE telefon IS NOT NULL AND TRIM(telefon) <> '' AND REGEXP_REPLACE(telefon, '[^0-9]+', '') = :pd LIMIT 1"
            );
            $st->execute(['pd' => $pd]);
            if ($st->fetch()) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('crm_import_row_duplicate_in_db')) {
    function crm_import_row_duplicate_in_db(PDO $pdo, ?string $ico, ?string $telefon, ?string $email): bool
    {
        $icoN = crm_import_normalize_ico($ico);
        if ($icoN !== '') {
            $st = $pdo->prepare(
                'SELECT id FROM contacts WHERE ico IS NOT NULL AND TRIM(ico) <> "" AND TRIM(ico) = :ico LIMIT 1'
            );
            $st->execute(['ico' => $icoN]);
            if ($st->fetch()) {
                return true;
            }
        }
        $em = crm_import_normalize_email($email);
        if ($em !== '') {
            $st = $pdo->prepare(
                'SELECT id FROM contacts WHERE email IS NOT NULL AND LOWER(TRIM(email)) = :em LIMIT 1'
            );
            $st->execute(['em' => $em]);
            if ($st->fetch()) {
                return true;
            }
        }
        $pd = crm_import_phone_digits($telefon);
        if ($pd !== '') {
            $st = $pdo->prepare(
                "SELECT id FROM contacts WHERE telefon IS NOT NULL AND TRIM(telefon) <> '' AND REGEXP_REPLACE(telefon, '[^0-9]+', '') = :pd LIMIT 1"
            );
            $st->execute(['pd' => $pd]);
            if ($st->fetch()) {
                return true;
            }
        }
        return false;
    }
}
