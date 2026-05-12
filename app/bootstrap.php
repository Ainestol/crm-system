<?php
// e:\Snecinatripu\app\bootstrap.php
declare(strict_types=1);

/**
 * Bootstrap webové aplikace: konfigurace, PDO, helpery, escapování.
 */

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'constants.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'session.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'csrf.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'encryption.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'totp.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'flash.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'url.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'middleware.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'region.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'services_catalog.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'mask.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'bet_campaign.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'xlsx_writer.php';

if (!function_exists('crm_h')) {
    function crm_h(?string $s): string
    {
        if ($s === null) {
            return '';
        }
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('crm_request_uri_path')) {
    /** Normalizovaná cesta bez query, relativní k public root. */
    function crm_request_uri_path(): string
    {
        $uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        if (!is_string($uri) || $uri === '') {
            $uri = '/';
        }

        // PHP built-in server (cli-server) nastavuje SCRIPT_NAME na požadovanou
        // URL cestu (ne na router script), takže dirname() by nesprávně ořízl
        // část cesty. Při cli-server používáme REQUEST_URI přímo.
        if (PHP_SAPI !== 'cli-server') {
            $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
            $base = str_replace('\\', '/', dirname($scriptName));
            $base = rtrim($base === '/' || $base === '.' ? '' : $base, '/');
            if ($base !== '' && str_starts_with($uri, $base)) {
                $uri = substr($uri, strlen($base)) ?: '/';
            }
        }

        if ($uri === '' || $uri[0] !== '/') {
            $uri = '/' . $uri;
        }
        $uri = rtrim($uri, '/');
        $uri = $uri === '' ? '/' : $uri;
        return strtolower($uri);
    }
}
