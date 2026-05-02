<?php
// e:\Snecinatripu\app\helpers\api_auth.php
declare(strict_types=1);

/**
 * REST API: Bearer token (hash v api_tokens), rate limit 60 požadavků/min na token.
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'encryption.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';

if (!function_exists('api_auth_authorization_header')) {
    function api_auth_authorization_header(): string
    {
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            return (string) $_SERVER['HTTP_AUTHORIZATION'];
        }
        if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (is_array($headers)) {
                foreach ($headers as $name => $value) {
                    if (strcasecmp((string) $name, 'Authorization') === 0) {
                        return (string) $value;
                    }
                }
            }
        }
        return '';
    }
}

if (!function_exists('api_auth_parse_bearer')) {
    function api_auth_parse_bearer(string $authorizationHeader): ?string
    {
        if (preg_match('/Bearer\s+(\S+)/i', $authorizationHeader, $m)) {
            return $m[1];
        }
        return null;
    }
}

if (!function_exists('api_auth_rate_path')) {
    function api_auth_rate_path(string $tokenHash): string
    {
        $dir = CRM_STORAGE_PATH . DIRECTORY_SEPARATOR . 'ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        return $dir . DIRECTORY_SEPARATOR . 'api_' . $tokenHash . '.json';
    }
}

if (!function_exists('api_auth_rate_allow')) {
    /** Sliding okno 60 požadavků za minutu. */
    function api_auth_rate_allow(string $tokenHash): bool
    {
        $path = api_auth_rate_path($tokenHash);
        $now = time();
        $minuteAgo = $now - 60;
        $hits = [];
        if (is_readable($path)) {
            $raw = @file_get_contents($path);
            if ($raw !== false && $raw !== '') {
                $data = json_decode($raw, true);
                if (is_array($data) && isset($data['hits']) && is_array($data['hits'])) {
                    foreach ($data['hits'] as $t) {
                        if (is_int($t) && $t >= $minuteAgo) {
                            $hits[] = $t;
                        }
                    }
                }
            }
        }
        return count($hits) < CRM_API_RATE_LIMIT_PER_MINUTE;
    }
}

if (!function_exists('api_auth_rate_hit')) {
    function api_auth_rate_hit(string $tokenHash): void
    {
        $path = api_auth_rate_path($tokenHash);
        $now = time();
        $minuteAgo = $now - 60;
        $hits = [$now];
        if (is_readable($path)) {
            $raw = @file_get_contents($path);
            if ($raw !== false && $raw !== '') {
                $data = json_decode($raw, true);
                if (is_array($data) && isset($data['hits']) && is_array($data['hits'])) {
                    foreach ($data['hits'] as $t) {
                        if (is_int($t) && $t >= $minuteAgo) {
                            $hits[] = $t;
                        }
                    }
                }
            }
        }
        @file_put_contents($path, json_encode(['hits' => $hits], JSON_THROW_ON_ERROR), LOCK_EX);
    }
}

if (!function_exists('api_auth_resolve_user')) {
    /**
     * Ověří Bearer token, aktualizuje last_used_at, aplikuje rate limit.
     *
     * @return array{user: array<string, mixed>, token_id: int}|null
     */
    function api_auth_resolve_user(PDO $pdo, ?string $plainToken = null): ?array
    {
        if ($plainToken === null) {
            $plainToken = api_auth_parse_bearer(api_auth_authorization_header());
        }
        if ($plainToken === null || $plainToken === '') {
            return null;
        }

        $hash = crm_hash_api_token($plainToken);

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            'SELECT t.id AS token_id, t.user_id, u.* FROM api_tokens t
             INNER JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = :h AND t.expires_at > :now AND u.aktivni = 1
             LIMIT 1'
        );
        $stmt->execute(['h' => $hash, 'now' => $now]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }

        if (!api_auth_rate_allow($hash)) {
            return null;
        }

        $upd = $pdo->prepare('UPDATE api_tokens SET last_used_at = :n WHERE id = :id');
        $upd->execute(['n' => $now, 'id' => (int) $row['token_id']]);

        api_auth_rate_hit($hash);

        $user = $row;
        unset($user['token_id'], $user['heslo_hash'], $user['totp_secret']);
        return ['user' => $user, 'token_id' => (int) $row['token_id']];
    }
}

if (!function_exists('api_auth_issue_token')) {
    /**
     * Vytvoří API token (plain vrací jednou), uloží hash, expirace ve dnech.
     *
     * @return array{plain: string, expires_at: string}|null
     */
    function api_auth_issue_token(
        PDO $pdo,
        int $userId,
        string $deviceName,
        int $ttlDays = 30
    ): ?array {
        $plain = crm_generate_api_token_plain(32);
        $hash = crm_hash_api_token($plain);
        $expires = (new DateTimeImmutable('now'))->modify('+' . $ttlDays . ' days')->format('Y-m-d H:i:s');
        $ins = $pdo->prepare(
            'INSERT INTO api_tokens (user_id, token_hash, device_name, last_used_at, expires_at)
             VALUES (:uid, :th, :dn, NULL, :ex)'
        );
        $ins->execute([
            'uid' => $userId,
            'th' => $hash,
            'dn' => $deviceName,
            'ex' => $expires,
        ]);
        return ['plain' => $plain, 'expires_at' => $expires];
    }
}

if (!function_exists('api_auth_invalidate_user_tokens')) {
    function api_auth_invalidate_user_tokens(PDO $pdo, int $userId): void
    {
        $stmt = $pdo->prepare('DELETE FROM api_tokens WHERE user_id = :id');
        $stmt->execute(['id' => $userId]);
    }
}
