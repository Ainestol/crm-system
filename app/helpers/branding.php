<?php
// e:\Snecinatripu\app\helpers\branding.php
declare(strict_types=1);

/**
 * Tenant branding — logo, display_name, primary_color.
 *
 * Tabulka `tenant_branding` (z migrace 031):
 *   tenant_id (PK), display_name, logo_url, primary_color, accent_color, email_signature
 */

if (!function_exists('crm_tenant_branding')) {
    /**
     * Vrátí branding pro daný tenant. Per-request cache.
     * Fallback: pokud řádek neexistuje, vrátí default barvy + name z tenants.
     *
     * @return array{display_name:?string, logo_url:?string, primary_color:string, accent_color:string}
     */
    function crm_tenant_branding(PDO $pdo, int $tenantId): array
    {
        static $cache = [];
        if (isset($cache[$tenantId])) return $cache[$tenantId];

        $defaults = [
            'display_name'  => null,
            'logo_url'      => null,
            'primary_color' => '#2563eb',
            'accent_color'  => '#7c3aed',
        ];
        try {
            $st = $pdo->prepare(
                'SELECT display_name, logo_url, primary_color, accent_color
                 FROM tenant_branding WHERE tenant_id = :tid LIMIT 1'
            );
            $st->execute(['tid' => $tenantId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $defaults = array_merge($defaults, array_filter($row, fn($v) => $v !== null && $v !== ''));
            }
        } catch (\Throwable $_) {
            // Tichý fallback
        }
        $cache[$tenantId] = $defaults;
        return $defaults;
    }
}

if (!function_exists('crm_tenant_branding_save')) {
    /**
     * Upsert branding řádku.
     */
    function crm_tenant_branding_save(
        PDO $pdo,
        int $tenantId,
        ?string $displayName,
        ?string $logoUrl,
        string $primaryColor,
        string $accentColor
    ): bool {
        try {
            $st = $pdo->prepare(
                'INSERT INTO tenant_branding (tenant_id, display_name, logo_url, primary_color, accent_color, updated_at)
                 VALUES (:tid, :dn, :url, :pc, :ac, NOW(3))
                 ON DUPLICATE KEY UPDATE
                     display_name = VALUES(display_name),
                     logo_url     = VALUES(logo_url),
                     primary_color = VALUES(primary_color),
                     accent_color  = VALUES(accent_color),
                     updated_at    = NOW(3)'
            );
            return $st->execute([
                'tid' => $tenantId,
                'dn'  => ($displayName !== null && $displayName !== '') ? $displayName : null,
                'url' => ($logoUrl !== null && $logoUrl !== '') ? $logoUrl : null,
                'pc'  => $primaryColor,
                'ac'  => $accentColor,
            ]);
        } catch (\Throwable $e) {
            if (function_exists('crm_db_log_error') && $e instanceof \PDOException) {
                crm_db_log_error($e, 'crm_tenant_branding_save');
            }
            return false;
        }
    }
}

if (!function_exists('crm_tenant_logo_upload')) {
    /**
     * Zpracuje upload loga přes $_FILES.
     * Vrátí relativní URL (např. "/uploads/tenant-2/logo.png") nebo null při chybě.
     */
    function crm_tenant_logo_upload(int $tenantId, array $fileInfo): ?string
    {
        if (!isset($fileInfo['tmp_name']) || $fileInfo['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        $maxSize = 500 * 1024; // 500 KB
        if ((int) $fileInfo['size'] > $maxSize) return null;

        $ext = strtolower(pathinfo((string) $fileInfo['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'svg', 'webp', 'gif'];
        if (!in_array($ext, $allowed, true)) return null;
        if ($ext === 'jpeg') $ext = 'jpg';

        // Validace MIME (krom SVG — SVG dovolíme, super-admin si je vědom)
        if ($ext !== 'svg') {
            $imgInfo = @getimagesize((string) $fileInfo['tmp_name']);
            if ($imgInfo === false) return null;
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (!in_array($imgInfo['mime'], $allowedMimes, true)) return null;
        } else {
            // Pro SVG aspoň ověř, že začíná <svg
            $content = (string) @file_get_contents((string) $fileInfo['tmp_name'], false, null, 0, 500);
            if (!preg_match('/<svg[\s>]/i', $content)) return null;
        }

        // Cíl: public/uploads/tenant-{id}/logo.{ext}
        $publicRoot = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public';
        $dir = $publicRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'tenant-' . $tenantId;
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0775, true)) return null;
        }

        // Smaž starší loga (jiné přípony)
        foreach ($allowed as $oldExt) {
            $oldPath = $dir . DIRECTORY_SEPARATOR . 'logo.' . $oldExt;
            if (is_file($oldPath)) @unlink($oldPath);
        }

        $target = $dir . DIRECTORY_SEPARATOR . 'logo.' . $ext;
        if (!@move_uploaded_file((string) $fileInfo['tmp_name'], $target)) {
            return null;
        }
        // Cache-bust query string aby browser hned ukázal nové logo
        return '/uploads/tenant-' . $tenantId . '/logo.' . $ext . '?v=' . time();
    }
}
