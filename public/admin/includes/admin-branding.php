<?php
declare(strict_types=1);

/**
 * Tenant white-label branding for admin portals.
 * Sets: $adminAccent, $adminBrandName, $adminLogoUrl, $adminFaviconUrl,
 *       $adminBranding, $ecomStorefrontUrl, $adminCustomDomain
 */
if (!isset($db)) {
    require_once __DIR__ . '/../../../includes/Database/Database.php';
    $db = Database::getInstance()->getConnection();
}

require_once __DIR__ . '/../../../includes/Helpers/TenantBootstrap.php';
require_once __DIR__ . '/../../../includes/Platform/TenantScope.php';
require_once __DIR__ . '/../../../includes/Platform/TenantResolver.php';
require_once __DIR__ . '/../../../includes/Helpers/EntitlementGuard.php';

$adminBranding = TenantBootstrap::branding($db);
$adminAccent = (string) ($adminBranding['accent'] ?? '#2563eb');
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $adminAccent)) {
    $adminAccent = '#2563eb';
}
$adminBrandName = trim((string) ($adminBranding['brand_name'] ?? ''));
if ($adminBrandName === '') {
    $adminBrandName = (string) ($_SESSION['tenant_name'] ?? 'RetailPOS');
}
$adminLogoUrl = (string) ($adminBranding['logo_url'] ?? '');
$adminFaviconUrl = (string) ($adminBranding['favicon_url'] ?? '');
$adminCustomDomain = (string) ($adminBranding['custom_domain'] ?? '');
if ($adminCustomDomain === '') {
    $tenantId = TenantScope::id();
    $tenantSlug = (string) ($_SESSION['tenant_slug'] ?? '');
    if ($tenantId > 0) {
        try {
            $stmt = $db->prepare(
                'SELECT hostname FROM tenant_domains
                 WHERE tenant_id = ? AND kind = ? AND is_verified = 1
                 ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute([$tenantId, 'custom']);
            $host = $stmt->fetchColumn();
            if (is_string($host) && $host !== '') {
                $adminCustomDomain = strtolower($host);
            }
        } catch (Throwable) {
        }
    }
    if ($adminCustomDomain === '' && $tenantSlug !== '') {
        $base = TenantResolver::baseDomain();
        if ($base !== '') {
            $adminCustomDomain = $tenantSlug . '.' . $base;
        }
    }
}

$ecomStorefrontUrl = $ecomStorefrontUrl ?? '';
if ($ecomStorefrontUrl === '') {
    $saasModules = EntitlementGuard::modulesForCurrentTenant();
    $roleSlug = strtolower(str_replace(' ', '_', $_SESSION['role_slug'] ?? $_SESSION['role'] ?? ''));
    if (!empty($saasModules['ecommerce']) && in_array($roleSlug, ['super_admin', 'admin', 'manager'], true)) {
        $tenantSlug = (string) ($_SESSION['tenant_slug'] ?? '');
        $tenantId = TenantScope::id();
        if ($tenantSlug !== '' && $tenantId > 0) {
            $ecomStorefrontUrl = TenantResolver::tenantEcommerceUrl($tenantSlug, $db, $tenantId);
        }
    }
}

if (!function_exists('admin_hex_rgba')) {
    function admin_hex_rgba(string $hex, float $alpha): string
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return 'rgba(37, 99, 235, ' . $alpha . ')';
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $a = rtrim(rtrim(sprintf('%.2f', $alpha), '0'), '.');

        return sprintf('rgba(%d, %d, %d, %s)', $r, $g, $b, $a);
    }
}

if (!function_exists('admin_hex_darken')) {
    function admin_hex_darken(string $hex, float $ratio = 0.12): string
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return '#1d4ed8';
        }
        $r = max(0, min(255, (int) round(hexdec(substr($hex, 0, 2)) * (1 - $ratio))));
        $g = max(0, min(255, (int) round(hexdec(substr($hex, 2, 2)) * (1 - $ratio))));
        $b = max(0, min(255, (int) round(hexdec(substr($hex, 4, 2)) * (1 - $ratio))));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}

if (!function_exists('admin_theme_css_block')) {
    function admin_theme_css_block(string $accent): string
    {
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $accent)) {
            $accent = '#2563eb';
        }
        $accentEsc = htmlspecialchars($accent, ENT_QUOTES, 'UTF-8');
        $hoverEsc = htmlspecialchars(admin_hex_darken($accent, 0.12), ENT_QUOTES, 'UTF-8');
        $soft = admin_hex_rgba($accent, 0.12);
        $softDark = admin_hex_rgba($accent, 0.2);
        $border = admin_hex_rgba($accent, 0.35);

        return '<style>:root {
    --theme-accent: ' . $accentEsc . ';
    --theme-accent-hover: ' . $hoverEsc . ';
    --theme-accent-soft: ' . $soft . ';
    --theme-accent-border: ' . $border . ';
    --primary: ' . $accentEsc . ';
    --primary-hover: ' . $hoverEsc . ';
    --primary-light: ' . $soft . ';
    --ecom-accent: ' . $accentEsc . ';
    --ecom-accent-soft: ' . $soft . ';
    --ecom-accent-border: ' . $border . ';
}
[data-theme="dark"] {
    --theme-accent-soft: ' . $softDark . ';
    --primary-light: ' . $softDark . ';
}</style>';
    }
}
