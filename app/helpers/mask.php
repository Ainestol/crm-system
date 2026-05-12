<?php
declare(strict_types=1);

/**
 * app/helpers/mask.php
 *
 * Maskování citlivých údajů pro tiskové (PDF) výplaty.
 * Cíl: čistička / navolávačka nesmí přes svou výplatu vytisknout
 *      plnou databázi kontaktů (telefon, firma).
 *
 * Plný view zůstává pro role: majitel, superadmin, backoffice, obchodak.
 *
 * Pravidla:
 *   - telefon  →  `724 *** 379` (první 3 + last 3, prostředek hvězdičky)
 *               vícero čísel oddělených ';' se maskují každé zvlášť
 *   - firma    →  první znak prvního slova + `***`, zbytek netknutý
 *               např. "THUASNE SHOPS s.r.o." → "T*** SHOPS s.r.o."
 *               např. "Dřevokrov, spol. s r.o." → "D***, spol. s r.o."
 */

/**
 * Vrací true, pokud má být obsah pro tuto roli maskovaný.
 */
function crm_should_mask_for_role(?string $role): bool
{
    $masked = ['cisticka', 'cistic', 'navolavacka', 'caller'];
    return in_array(strtolower((string) $role), $masked, true);
}

/**
 * Maskování jednoho telefonního čísla (CZ formát).
 * Vrací první 3 a poslední 3 číslice, prostředek ` *** `.
 */
function crm_mask_phone_single(string $phone): string
{
    $phone = trim($phone);
    if ($phone === '') {
        return '—';
    }

    // Vytáhni jen digits (zahodí mezery, +, závorky, ...)
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (strlen($digits) < 6) {
        return '***';
    }

    // Posledních 9 cifer (CZ čísla bez prefixu)
    $core  = strlen($digits) >= 9 ? substr($digits, -9) : $digits;
    $first = substr($core, 0, 3);
    $last  = substr($core, -3);

    return $first . ' *** ' . $last;
}

/**
 * Maskování telefonu — podporuje vícero čísel oddělených ';'
 * (`contacts.telefon` je VARCHAR(200) a po merge importu může obsahovat až 6 čísel).
 */
function crm_mask_phone(string $phone): string
{
    if (trim($phone) === '') {
        return '—';
    }

    if (str_contains($phone, ';')) {
        $parts  = array_map('trim', explode(';', $phone));
        $masked = array_map('crm_mask_phone_single', $parts);
        return implode('; ', $masked);
    }

    return crm_mask_phone_single($phone);
}

/**
 * Maskování názvu firmy / zákazníka.
 * Nahradí PRVNÍ slovo: jeho 1. znak + `***`. Ostatní text zachová
 * (interpunkce, právní forma s.r.o. apod. zůstanou viditelné).
 */
function crm_mask_firma(string $firma): string
{
    $firma = trim($firma);
    if ($firma === '') {
        return '—';
    }

    // ^ (volitelná interpunkce/whitespace) (první slovo)
    $masked = preg_replace_callback(
        '/^(\W*)(\w+)/u',
        function (array $m): string {
            $prefix = (string) $m[1];
            $word   = (string) $m[2];
            $first  = mb_substr($word, 0, 1);
            return $prefix . $first . '***';
        },
        $firma,
        1
    );

    return is_string($masked) ? $masked : $firma;
}
