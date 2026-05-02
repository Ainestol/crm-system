<?php
// e:\Snecinatripu\app\helpers\totp.php
declare(strict_types=1);

/**
 * TOTP (RFC 6238) – HMAC-SHA1, 30 s okno, 6 číslic. Kompatibilní s Google Authenticator.
 */

if (!function_exists('totp_base32_alphabet')) {
    function totp_base32_alphabet(): string
    {
        return 'ABCDEFGHIJKLMNOPQRSTUVW234567';
    }
}

if (!function_exists('totp_base32_decode')) {
    /** Dekóduje Base32 secret (bez mezer, case-insensitive). */
    function totp_base32_decode(string $secret): string
    {
        $secret = strtoupper(str_replace('=', '', preg_replace('/\s+/', '', $secret)));
        if ($secret === '') {
            return '';
        }
        $alphabet = totp_base32_alphabet();
        $bits = '';
        $secretLength = strlen($secret);
        for ($i = 0; $i < $secretLength; $i++) {
            $pos = strpos($alphabet, $secret[$i]);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        $lenBits = strlen($bits);
        for ($i = 0; $i + 8 <= $lenBits; $i += 8) {
            $out .= chr((int) bindec(substr($bits, $i, 8)));
        }
        return $out;
    }
}

if (!function_exists('totp_hotp')) {
    /** RFC 4226 HOTP – binární klíč, čítač 64 bitů big endian. */
    function totp_hotp(string $keyBinary, int $counter): string
    {
        $hi = ($counter >> 32) & 0xFFFFFFFF;
        $lo = $counter & 0xFFFFFFFF;
        $counterBin = pack('N2', $hi, $lo);
        $hash = hash_hmac('sha1', $counterBin, $keyBinary, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $truncated = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % 1000000;
        return str_pad((string) $truncated, 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('totp_counter')) {
    /** Čítač TOTP: floor((UnixTime - T0) / 30). */
    function totp_counter(int $unixTimestamp, int $t0 = 0, int $step = 30): int
    {
        return (int) floor(($unixTimestamp - $t0) / $step);
    }
}

if (!function_exists('totp_code_at')) {
    /** 6místný kód pro daný čas (UTC sekundy). */
    function totp_code_at(string $secretBase32, int $unixTimestamp, int $t0 = 0, int $step = 30): string
    {
        $key = totp_base32_decode($secretBase32);
        if ($key === '') {
            return '000000';
        }
        $counter = totp_counter($unixTimestamp, $t0, $step);
        return totp_hotp($key, $counter);
    }
}

if (!function_exists('totp_verify')) {
    /**
     * Ověří uživatelský kód vůči secretu (Base32).
     * $window = počet kroků ± aktuální perioda (1 = ±30 s).
     */
    function totp_verify(string $secretBase32, string $userCode, int $window = 1, int $t0 = 0, int $step = 30): bool
    {
        $userCode = preg_replace('/\s+/', '', $userCode) ?? '';
        if (!preg_match('/^\d{6}$/', $userCode)) {
            return false;
        }
        $key = totp_base32_decode($secretBase32);
        if ($key === '') {
            return false;
        }
        $now = time();
        $current = totp_counter($now, $t0, $step);
        for ($i = -$window; $i <= $window; $i++) {
            $c = $current + $i;
            if ($c < 0) {
                continue;
            }
            if (hash_equals(totp_hotp($key, $c), $userCode)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('totp_random_secret_base32')) {
    /** Náhodný Base32 secret (160 bitů → 32 znaků A–Z2–7). */
    function totp_random_secret_base32(int $bytes = 20): string
    {
        $raw = random_bytes($bytes);
        $alphabet = totp_base32_alphabet();
        $bits = '';
        foreach (str_split($raw) as $ch) {
            $bits .= str_pad(decbin(ord($ch)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        for ($i = 0; $i + 5 <= strlen($bits); $i += 5) {
            $out .= $alphabet[(int) bindec(substr($bits, $i, 5))];
        }
        return $out;
    }
}

if (!function_exists('totp_provisioning_uri')) {
    /** otpauth URI pro QR kód (issuer + account). */
    function totp_provisioning_uri(string $issuer, string $accountEmail, string $secretBase32): string
    {
        $label = rawurlencode($issuer . ':' . $accountEmail);
        $issuerEnc = rawurlencode($issuer);
        $secretEnc = rawurlencode(strtoupper(str_replace(' ', '', $secretBase32)));
        return 'otpauth://totp/' . $label . '?secret=' . $secretEnc . '&issuer=' . $issuerEnc . '&period=30&digits=6&algorithm=SHA1';
    }
}
