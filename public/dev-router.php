<?php
// e:\Snecinatripu\public\dev-router.php
declare(strict_types=1);
/**
 * Router pro vestavěný server PHP (Apache mod_rewrite se nepoužije).
 * Spuštění z kořene projektu:
 *   php -S localhost:8080 -t public public/dev-router.php
 */
if (PHP_SAPI !== 'cli-server') {
    http_response_code(403);
    echo 'Pouze pro PHP built-in server (php -S).';
    exit;
}

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (!is_string($path) || $path === '') {
    $path = '/';
}
$file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $path);
if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . DIRECTORY_SEPARATOR . 'index.php';
