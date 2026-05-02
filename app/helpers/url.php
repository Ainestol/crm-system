<?php
// e:\Snecinatripu\app\helpers\url.php
declare(strict_types=1);

/** Relativní URL vůči public root (Apache DocumentRoot na /public). */
function crm_url(string $path): string
{
    if ($path === '' || $path[0] !== '/') {
        $path = '/' . $path;
    }
    // PHP built-in server (cli-server) nastavuje SCRIPT_NAME na požadovanou
    // URL cestu (ne na router script), dirname() by chybně ořízl segmenty cesty.
    if (PHP_SAPI === 'cli-server') {
        return $path;
    }
    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $dir = str_replace('\\', '/', dirname($scriptName));
    if ($dir === '/' || $dir === '.') {
        return $path;
    }
    return rtrim($dir, '/') . $path;
}

function crm_redirect(string $path, int $code = 302): never
{
    header('Location: ' . crm_url($path), true, $code);
    exit;
}

/**
 * Normalizuje IČO — odstraní nečíselné znaky a doplní zleva nuly na 8 znaků.
 * Např. "1234567" → "01234567", "123" → "00000123", "12345678" → "12345678".
 * Prázdné nebo větší než 8 vrátí jak je (po vyčištění).
 */
function crm_normalize_ico(?string $ico): string
{
    $clean = preg_replace('/\D+/', '', (string) $ico) ?? '';
    if ($clean === '') {
        return '';
    }
    if (strlen($clean) <= 8) {
        return str_pad($clean, 8, '0', STR_PAD_LEFT);
    }
    return $clean;
}
