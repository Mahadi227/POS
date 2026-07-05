<?php
declare(strict_types=1);

require_once __DIR__ . '/TenantScope.php';

/**
 * Resolve tenant from hostname, query param, or cookie (subdomain routing).
 */
final class TenantResolver
{
    public static function resolve(PDO $db, bool $persistCookie = true): ?array
    {
        if (!TenantScope::isReady($db)) {
            return null;
        }

        $tenant = self::resolveFromHost($db)
            ?? self::resolveFromQuery($db)
            ?? self::resolveFromCookie($db)
            ?? self::resolveFromSession($db);

        if ($tenant && $persistCookie && !empty($tenant['slug'])) {
            self::persistSlugCookie($tenant['slug']);
        }

        return $tenant;
    }

    public static function resolveFromHost(PDO $db): ?array
    {
        $host = self::normalizeHost($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') {
            return null;
        }

        if (self::tableExists($db, 'tenant_domains')) {
            $stmt = $db->prepare(
                'SELECT t.id, t.uuid, t.slug, t.name, t.status
                 FROM tenant_domains td
                 INNER JOIN tenants t ON t.id = td.tenant_id
                 WHERE td.hostname = ? AND t.deleted_at IS NULL AND td.is_verified = 1
                 LIMIT 1'
            );
            $stmt->execute([$host]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }

        $baseDomain = self::baseDomain();
        if ($baseDomain !== '' && str_ends_with($host, '.' . $baseDomain)) {
            $slug = substr($host, 0, -(strlen($baseDomain) + 1));
            if ($slug !== '' && !in_array($slug, ['www', 'app', 'api', 'platform'], true)) {
                return TenantScope::resolveBySlug($db, $slug);
            }
        }

        if (self::tableExists($db, 'tenant_domains')) {
            $parts = explode('.', $host);
            if (count($parts) >= 2) {
                $slug = $parts[0];
                $stmt = $db->prepare(
                    'SELECT t.id, t.uuid, t.slug, t.name, t.status
                     FROM tenant_domains td
                     INNER JOIN tenants t ON t.id = td.tenant_id
                     WHERE td.hostname = ? AND td.kind = ? AND t.deleted_at IS NULL
                     LIMIT 1'
                );
                $stmt->execute([$slug, 'subdomain']);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    return $row;
                }
            }
        }

        return null;
    }

    public static function resolveFromQuery(PDO $db): ?array
    {
        $param = defined('SAAS_TENANT_PARAM') ? SAAS_TENANT_PARAM : 'tenant';
        $slug = trim((string) ($_GET[$param] ?? ''));
        if ($slug === '') {
            return null;
        }
        return TenantScope::resolveBySlug($db, $slug);
    }

    public static function resolveFromCookie(PDO $db): ?array
    {
        $slug = trim((string) ($_COOKIE['tenant_slug'] ?? ''));
        if ($slug === '') {
            return null;
        }
        return TenantScope::resolveBySlug($db, $slug);
    }

    /** Resolve tenant from admin/POS session (logged-in user context). */
    public static function resolveFromSession(PDO $db): ?array
    {
        $slug = trim((string) ($_SESSION['tenant_slug'] ?? ''));
        if ($slug !== '') {
            return TenantScope::resolveBySlug($db, $slug);
        }

        $tenantId = (int) ($_SESSION['tenant_id'] ?? 0);
        if ($tenantId > 0) {
            return TenantScope::resolveById($db, $tenantId);
        }

        return null;
    }

    public static function baseDomain(): string
    {
        if (defined('SAAS_BASE_DOMAIN') && SAAS_BASE_DOMAIN !== '') {
            return strtolower(SAAS_BASE_DOMAIN);
        }
        return '';
    }

    public static function tenantLoginUrl(string $slug): string
    {
        return self::tenantPublicUrl($slug, 'login.php', null, 0);
    }

    public static function tenantEcommerceUrl(string $slug, ?PDO $db = null, int $tenantId = 0): string
    {
        return self::tenantPublicUrl($slug, 'e-commerce/home/', $db, $tenantId);
    }

    public static function tenantPublicUrl(string $slug, string $relativePath, ?PDO $db = null, int $tenantId = 0): string
    {
        require_once __DIR__ . '/../Helpers/UrlHelper.php';

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $appPath = parse_url(APP_URL, PHP_URL_PATH) ?? '';
        $appPath = rtrim(str_replace('\\', '/', (string) $appPath), '/');
        $publicSegment = 'public/' . ltrim(str_replace('\\', '/', $relativePath), '/');
        $fullPath = ($appPath !== '' ? $appPath . '/' : '/') . $publicSegment;

        $host = self::resolvePreferredHost($db, $tenantId, $slug);
        if ($host !== null) {
            return $scheme . '://' . $host . str_replace(' ', '%20', $fullPath);
        }

        $base = rtrim(APP_URL, '/');
        $param = defined('SAAS_TENANT_PARAM') ? SAAS_TENANT_PARAM : 'tenant';
        $join = str_contains($relativePath, '?') ? '&' : '?';
        $path = str_replace('\\', '/', $publicSegment);
        $path = str_replace(' ', '%20', $path);

        return $base . '/' . ltrim($path, '/') . $join . $param . '=' . rawurlencode($slug);
    }

    private static function resolvePreferredHost(?PDO $db, int $tenantId, string $slug): ?string
    {
        if ($db !== null && $tenantId > 0 && self::tableExists($db, 'tenant_domains')) {
            $stmt = $db->prepare(
                'SELECT hostname FROM tenant_domains
                 WHERE tenant_id = ? AND kind = ? AND is_verified = 1
                 ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute([$tenantId, 'custom']);
            $custom = $stmt->fetchColumn();
            if (is_string($custom) && $custom !== '') {
                return strtolower($custom);
            }
        }

        $base = self::baseDomain();
        if ($base !== '' && $slug !== '') {
            return $slug . '.' . $base;
        }

        return null;
    }

    private static function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        return preg_replace('/:\d+$/', '', $host) ?? $host;
    }

    private static function persistSlugCookie(string $slug): void
    {
        if (headers_sent()) {
            $_COOKIE['tenant_slug'] = $slug;
            return;
        }
        setcookie('tenant_slug', $slug, [
            'expires' => time() + 60 * 60 * 24 * 365,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    private static function tableExists(PDO $db, string $table): bool
    {
        $stmt = $db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
}
