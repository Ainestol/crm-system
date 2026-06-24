<?php
// e:\Snecinatripu\app\helpers\quotes.php
declare(strict_types=1);

/**
 * Quotes — náhodný motivační citát + pozdrav podle hodiny.
 * Globální pool (žádný tenant scope).
 */

if (!function_exists('crm_random_quote')) {
    /**
     * Vrátí náhodný aktivní citát.
     *
     * @return array{text:string, author:string}|null
     */
    function crm_random_quote(PDO $pdo): ?array
    {
        static $cache = null; // per-request cache (1 dotaz na request)
        if ($cache !== null) return $cache;
        try {
            $st = $pdo->query(
                'SELECT text, author FROM quotes WHERE active = 1
                 ORDER BY RAND() LIMIT 1'
            );
            $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : null;
            if ($row) {
                $cache = [
                    'text'   => (string) $row['text'],
                    'author' => (string) ($row['author'] ?? ''),
                ];
                return $cache;
            }
        } catch (\Throwable $_) {
            // Tichý fallback (např. tabulka ještě neexistuje)
        }
        $cache = null;
        return null;
    }
}

if (!function_exists('crm_greeting')) {
    /**
     * "Dobré ráno, Lucko" / "Dobré odpoledne, Pavle" / "Dobrý večer, Mirku" podle hodiny.
     */
    function crm_greeting(string $name = ''): string
    {
        $h = (int) date('H');
        if ($h < 11)      $greeting = 'Dobré ráno';
        elseif ($h < 18)  $greeting = 'Dobré odpoledne';
        elseif ($h < 22)  $greeting = 'Dobrý večer';
        else              $greeting = 'Dobrou noc';

        // Vokativ pro nejčastější česká jména — best-effort, falbback na nominativ
        $name = trim($name);
        if ($name !== '') {
            $vok = _crm_vocative($name);
            return $greeting . ', ' . $vok;
        }
        return $greeting;
    }
}

if (!function_exists('strftime_cz')) {
    /**
     * Český formát data: "čtvrtek, 19. června 2026".
     * Bez setlocale (nespoléhá na OS lokalizaci).
     */
    function strftime_cz(): string
    {
        $dayNames   = ['neděle','pondělí','úterý','středa','čtvrtek','pátek','sobota'];
        $monthNames = ['','ledna','února','března','dubna','května','června',
                       'července','srpna','září','října','listopadu','prosince'];
        $dow = (int) date('w');
        $d   = (int) date('j');
        $m   = (int) date('n');
        $y   = (int) date('Y');
        return $dayNames[$dow] . ', ' . $d . '. ' . $monthNames[$m] . ' ' . $y;
    }
}

if (!function_exists('_crm_vocative')) {
    /**
     * Jednoduchý transformer Jméno → vokativ.
     * Pokrývá většinu běžných českých jmen. Pro neznámý vzor nechá nominativ.
     */
    function _crm_vocative(string $name): string
    {
        // Vezmi jen první jméno (pokud je "Jan Novák" → "Jan")
        $first = trim(explode(' ', $name)[0]);
        if ($first === '') return $name;

        $lower = mb_strtolower($first, 'UTF-8');
        // Specialcase pravidla — nejčastější vzory
        $rules = [
            // Ženské jména -a → -o
            '/a$/u'     => 'o',
            // Mužské -slav → -slave
            '/slav$/u'  => 'slave',
            // Mužské tvrdé -k → -ku
            '/k$/u'     => 'ku',
            // -el → -le (Karel → Karle)
            '/el$/u'    => 'le',
        ];
        foreach ($rules as $pattern => $replacement) {
            if (preg_match($pattern, $lower)) {
                $vok = preg_replace($pattern, $replacement, $first) ?? $first;
                // Zachovat velké písmeno na začátku
                return mb_strtoupper(mb_substr($vok, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($vok, 1, null, 'UTF-8');
            }
        }
        // Mužské -í, -y → unchanged (Jiří, Tony)
        // Mužské -š, -č → +i (Šáša → Šášo? Šášu? — zachovat)
        return $first;
    }
}
