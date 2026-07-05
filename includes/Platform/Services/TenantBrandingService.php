<?php
declare(strict_types=1);

require_once __DIR__ . '/../Repositories/SubscriptionRepository.php';
require_once __DIR__ . '/../Repositories/TenantDomainRepository.php';
require_once __DIR__ . '/EntitlementService.php';
require_once __DIR__ . '/../TenantResolver.php';

final class TenantBrandingService
{
    private PDO $db;
    private EntitlementService $entitlements;
    private TenantDomainRepository $domains;

    public function __construct(PDO $db, EntitlementService $entitlements, TenantDomainRepository $domains)
    {
        $this->db = $db;
        $this->entitlements = $entitlements;
        $this->domains = $domains;
    }

    public function canCustomize(int $tenantId): bool
    {
        if ($tenantId === 1) {
            return true;
        }
        return $this->entitlements->hasModule($tenantId, 'white_label');
    }

    /** @return array<string, mixed> */
    public function getBranding(int $tenantId): array
    {
        $settings = $this->loadSettings($tenantId);
        $theme = is_array($settings['theme'] ?? null) ? $settings['theme'] : [];
        $brand = is_array($settings['brand'] ?? null) ? $settings['brand'] : [];

        $accent = (string) ($theme['accent'] ?? '#2563eb');
        $brandName = (string) ($brand['name'] ?? 'RetailPOS');
        $logoUrl = $this->resolveAssetUrl($tenantId, 'logo');
        $faviconUrl = $this->resolveAssetUrl($tenantId, 'favicon');

        if (!empty($brand['logo_path'])) {
            $logoUrl = $this->publicUrl($brand['logo_path']);
        }
        if (!empty($brand['favicon_path'])) {
            $faviconUrl = $this->publicUrl($brand['favicon_path']);
        }

        $slug = $this->tenantSlug($tenantId);
        $loginUrl = $slug !== '' ? TenantResolver::tenantLoginUrl($slug) : 'login.php';

        return [
            'tenant_id' => $tenantId,
            'can_customize' => $this->canCustomize($tenantId),
            'accent' => $accent,
            'brand_name' => $brandName,
            'logo_url' => $logoUrl,
            'favicon_url' => $faviconUrl,
            'login_url' => $loginUrl,
            'custom_domain' => (string) ($settings['custom_domain'] ?? ''),
            'domains' => $this->domains->listForTenant($tenantId),
        ];
    }

    /** @param array<string, mixed> $payload */
    public function saveSettings(int $tenantId, array $payload): array
    {
        if (!$this->canCustomize($tenantId)) {
            throw new RuntimeException('White-label not available on current plan');
        }

        $settings = $this->loadSettings($tenantId);
        if (!isset($settings['theme']) || !is_array($settings['theme'])) {
            $settings['theme'] = [];
        }
        if (!isset($settings['brand']) || !is_array($settings['brand'])) {
            $settings['brand'] = [];
        }

        if (!empty($payload['accent']) && preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $payload['accent'])) {
            $settings['theme']['accent'] = $payload['accent'];
        }
        if (isset($payload['brand_name']) && trim((string) $payload['brand_name']) !== '') {
            $settings['brand']['name'] = trim((string) $payload['brand_name']);
        }
        if (isset($payload['custom_domain'])) {
            $domain = strtolower(trim((string) $payload['custom_domain']));
            $settings['custom_domain'] = $domain;
            if ($domain !== '') {
                $this->domains->addCustomDomain($tenantId, $domain);
            }
        }

        $this->persistSettings($tenantId, $settings);
        return $this->getBranding($tenantId);
    }

    /** @param array<string, mixed> $file $_FILES entry */
    public function uploadLogo(int $tenantId, array $file, string $type = 'logo'): array
    {
        if (!$this->canCustomize($tenantId)) {
            throw new RuntimeException('White-label not available on current plan');
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed');
        }

        $allowed = ['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/x-icon', 'image/vnd.microsoft.icon'];
        $mime = mime_content_type($file['tmp_name']) ?: ($file['type'] ?? '');
        if (!in_array($mime, $allowed, true)) {
            throw new RuntimeException('Invalid image type');
        }

        $ext = $type === 'favicon' ? 'ico' : (str_contains($mime, 'png') ? 'png' : 'jpg');
        $dir = $this->uploadDir($tenantId);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create upload directory');
        }

        $filename = $type . '.' . $ext;
        $dest = $dir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new RuntimeException('Failed to save file');
        }

        $relative = 'uploads/branding/' . $tenantId . '/' . $filename;
        $settings = $this->loadSettings($tenantId);
        if (!isset($settings['brand']) || !is_array($settings['brand'])) {
            $settings['brand'] = [];
        }
        $key = $type === 'favicon' ? 'favicon_path' : 'logo_path';
        $settings['brand'][$key] = $relative;
        $this->persistSettings($tenantId, $settings);

        return $this->getBranding($tenantId);
    }

    public function deleteLogo(int $tenantId, string $type = 'logo'): array
    {
        if (!$this->canCustomize($tenantId)) {
            throw new RuntimeException('White-label not available on current plan');
        }

        $dir = $this->uploadDir($tenantId);
        if (is_dir($dir)) {
            foreach (glob($dir . DIRECTORY_SEPARATOR . $type . '.*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }

        $settings = $this->loadSettings($tenantId);
        if (isset($settings['brand']) && is_array($settings['brand'])) {
            $key = $type === 'favicon' ? 'favicon_path' : 'logo_path';
            unset($settings['brand'][$key]);
        }
        $this->persistSettings($tenantId, $settings);

        return $this->getBranding($tenantId);
    }

    /** @return array<string, mixed> */
    private function loadSettings(int $tenantId): array
    {
        if (!$this->hasColumn('tenants', 'settings_json')) {
            return [];
        }
        $stmt = $this->db->prepare('SELECT settings_json FROM tenants WHERE id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $raw = $stmt->fetchColumn();
        if (!$raw) {
            return [];
        }
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $settings */
    private function persistSettings(int $tenantId, array $settings): void
    {
        if (!$this->hasColumn('tenants', 'settings_json')) {
            return;
        }
        $this->db->prepare('UPDATE tenants SET settings_json = ?, updated_at = NOW() WHERE id = ?')
            ->execute([json_encode($settings, JSON_UNESCAPED_UNICODE), $tenantId]);
    }

    private function tenantSlug(int $tenantId): string
    {
        $stmt = $this->db->prepare('SELECT slug FROM tenants WHERE id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        return (string) ($stmt->fetchColumn() ?: '');
    }

    private function uploadDir(int $tenantId): string
    {
        return dirname(__DIR__, 3) . '/public/uploads/branding/' . $tenantId;
    }

    private function resolveAssetUrl(int $tenantId, string $type): ?string
    {
        $dir = $this->uploadDir($tenantId);
        if (!is_dir($dir)) {
            return null;
        }
        foreach (glob($dir . DIRECTORY_SEPARATOR . $type . '.*') ?: [] as $file) {
            $relative = 'uploads/branding/' . $tenantId . '/' . basename($file);
            return $this->publicUrl($relative);
        }
        return null;
    }

    private function publicUrl(string $relative): string
    {
        require_once __DIR__ . '/../../Helpers/UrlHelper.php';
        $base = str_replace(' ', '%20', request_app_base_url());
        $segments = explode('/', 'public/' . ltrim($relative, '/'));
        return $base . '/' . implode('/', array_map('rawurlencode', $segments));
    }

    private function hasColumn(string $table, string $column): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }
}
