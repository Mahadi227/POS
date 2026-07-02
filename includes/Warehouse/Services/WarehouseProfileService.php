<?php
declare(strict_types=1);

require_once __DIR__ . '/../Repositories/WarehouseProfileRepository.php';
require_once __DIR__ . '/../WarehouseProfileSchema.php';
require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../../Auth/AuditLogger.php';
require_once __DIR__ . '/../../Auth/RememberMeService.php';
require_once __DIR__ . '/../../Helpers/WarehousePortalAuth.php';
require_once __DIR__ . '/../../Notifications/Repositories/NotificationPreferenceRepository.php';
require_once __DIR__ . '/../../Auth/RoleRedirect.php';

class WarehouseProfileService
{
    private WarehouseProfileRepository $repo;
    private NotificationPreferenceRepository $notifPrefs;

    public function __construct(?PDO $db = null)
    {
        $this->repo = new WarehouseProfileRepository($db);
        $this->notifPrefs = new NotificationPreferenceRepository($db);
    }

    public function moduleReady(): bool
    {
        $db = Database::getInstance()->getConnection();
        return WarehouseProfileSchema::ready($db);
    }

    public function canViewProfile(int $viewerId, int $targetId): bool
    {
        if ($viewerId === $targetId) {
            return true;
        }
        $role = WarehousePortalAuth::roleSlug();
        if (in_array($role, ['super_admin', 'admin'], true)) {
            return true;
        }
        if ($role === 'warehouse_manager' && WarehousePortalAuth::canManage()) {
            $viewer = $this->repo->findUser($viewerId);
            $target = $this->repo->findUser($targetId);
            if (!$viewer || !$target) {
                return false;
            }
            return (int) ($viewer['warehouse_id'] ?? 0) === (int) ($target['warehouse_id'] ?? 0)
                && (int) ($target['warehouse_id'] ?? 0) > 0;
        }
        return false;
    }

    public function canEditProfile(int $viewerId, int $targetId): bool
    {
        if (WarehousePortalAuth::isReadOnly()) {
            return false;
        }
        if ($viewerId === $targetId) {
            return true;
        }
        return in_array(WarehousePortalAuth::roleSlug(), ['super_admin', 'admin'], true);
    }

    public function getProfile(int $viewerId, ?int $targetId = null): array
    {
        $targetId = $targetId ?: $viewerId;
        if (!$this->canViewProfile($viewerId, $targetId)) {
            throw new RuntimeException('Access denied');
        }

        $user = $this->repo->findUser($targetId);
        if (!$user) {
            throw new RuntimeException('User not found');
        }

        $first = trim((string) ($user['first_name'] ?? ''));
        $last = trim((string) ($user['last_name'] ?? ''));
        if ($first === '' && $last === '') {
            $parts = preg_split('/\s+/', trim((string) ($user['full_name'] ?: $user['name'] ?? '')), 2) ?: [];
            $first = $parts[0] ?? '';
            $last = $parts[1] ?? '';
        }

        $roleSlug = RoleRedirect::slug((string) ($user['role_name'] ?? ''));
        $prefs = $this->repo->getPreferences($targetId);
        $notif = $this->notifPrefs->get($targetId);
        $warehouseId = (int) ($user['warehouse_id'] ?? 0) ?: null;

        return [
            'user' => [
                'id' => (int) $user['id'],
                'employee_id' => $user['employee_id'] ?? null,
                'username' => $user['email'],
                'email' => $user['email'],
                'first_name' => $first,
                'last_name' => $last,
                'name' => trim($user['full_name'] ?: $user['name'] ?? ''),
                'phone' => $user['phone'] ?? null,
                'address' => $user['address'] ?? null,
                'emergency_contact' => $user['emergency_contact'] ?? null,
                'language' => $user['language'] ?? 'en',
                'timezone' => $user['timezone'] ?? 'UTC',
                'avatar_url' => $this->avatarUrl($user['avatar_path'] ?? null),
                'role' => $user['role_name'],
                'role_slug' => $roleSlug,
                'department' => $user['department'] ?? null,
                'warehouse_id' => $warehouseId,
                'warehouse_name' => $user['warehouse_name'] ?? null,
                'warehouse_code' => $user['warehouse_code'] ?? null,
                'store_id' => $user['store_id'] ? (int) $user['store_id'] : null,
                'store_name' => $user['store_name'] ?? null,
                'branch_id' => $user['branch_id'] ? (int) $user['branch_id'] : null,
                'branch_name' => $user['branch_name'] ?? ($user['store_name'] ?? null),
                'supervisor_name' => $user['supervisor_name'] ?? null,
                'employment_status' => ($user['status'] ?? 'active') === 'active' ? 'active' : 'inactive',
                'is_active' => ($user['status'] ?? 'active') === 'active' || (int) ($user['is_active'] ?? 0) === 1,
                'member_since' => $user['created_at'],
                'date_joined' => $user['created_at'],
                'last_login' => $user['last_login'],
                'last_activity' => $user['last_activity'] ?? null,
            ],
            'account' => $this->currentSessionInfo(),
            'preferences' => $this->formatPreferences($prefs),
            'notifications' => $this->formatNotifications($notif, $prefs),
            'performance' => $this->safePerformanceStats($targetId, $roleSlug, $warehouseId),
            'permissions' => [
                'can_edit' => $this->canEditProfile($viewerId, $targetId),
                'is_own_profile' => $viewerId === $targetId,
            ],
        ];
    }

    public function updateProfile(int $viewerId, int $targetId, array $input): array
    {
        if (!$this->canEditProfile($viewerId, $targetId)) {
            throw new RuntimeException('Access denied');
        }

        $first = trim((string) ($input['first_name'] ?? ''));
        $last = trim((string) ($input['last_name'] ?? ''));
        if ($first === '' || strlen($first) < 2) {
            throw new InvalidArgumentException('First name must be at least 2 characters');
        }
        if ($last === '' || strlen($last) < 2) {
            throw new InvalidArgumentException('Last name must be at least 2 characters');
        }

        $name = trim($first . ' ' . $last);
        $phone = trim((string) ($input['phone'] ?? ''));
        if ($phone !== '' && !preg_match('/^\+?[0-9\s\-().]{7,20}$/', $phone)) {
            throw new InvalidArgumentException('Invalid phone number');
        }

        $language = in_array($input['language'] ?? 'en', ['en', 'fr'], true) ? $input['language'] : 'en';
        $timezone = trim((string) ($input['timezone'] ?? 'UTC'));
        if (strlen($timezone) > 64) {
            $timezone = 'UTC';
        }

        $this->repo->updateProfile($targetId, [
            'first_name' => $first,
            'last_name' => $last,
            'name' => $name,
            'phone' => $phone !== '' ? $phone : null,
            'address' => trim((string) ($input['address'] ?? '')) ?: null,
            'emergency_contact' => trim((string) ($input['emergency_contact'] ?? '')) ?: null,
            'language' => $language,
            'timezone' => $timezone ?: 'UTC',
        ]);

        if ($viewerId === $targetId) {
            $_SESSION['name'] = $name;
            $_SESSION['full_name'] = $name;
            $_SESSION['lang'] = $language;
        }

        AuditLogger::log($viewerId, 'profile_updated', 'success', 'user', $targetId);
        return ['name' => $name, 'first_name' => $first, 'last_name' => $last];
    }

    public function changePassword(int $userId, array $input): void
    {
        if (!$this->canEditProfile($userId, $userId)) {
            throw new RuntimeException('Access denied');
        }

        $current = (string) ($input['current_password'] ?? '');
        $new = (string) ($input['new_password'] ?? '');
        $confirm = (string) ($input['confirm_password'] ?? '');

        if (strlen($new) < 6) {
            throw new InvalidArgumentException('New password must be at least 6 characters');
        }
        if ($new !== $confirm) {
            throw new InvalidArgumentException('Passwords do not match');
        }
        if ($current === '') {
            throw new InvalidArgumentException('Current password is required');
        }

        $hash = $this->repo->passwordHash($userId);
        if (!$hash || !password_verify($current, $hash)) {
            throw new InvalidArgumentException('Current password is incorrect');
        }

        $this->repo->updatePassword($userId, password_hash($new, PASSWORD_DEFAULT));
        AuditLogger::log($userId, 'password_changed', 'success', 'user', $userId);
    }

    public function savePreferences(int $userId, array $input): void
    {
        if (!$this->canEditProfile($userId, $userId)) {
            throw new RuntimeException('Access denied');
        }

        $prefs = [
            'theme' => in_array($input['theme'] ?? 'system', ['light', 'dark', 'system'], true) ? $input['theme'] : 'system',
            'date_format' => in_array($input['date_format'] ?? 'Y-m-d', ['Y-m-d', 'd/m/Y', 'm/d/Y'], true) ? $input['date_format'] : 'Y-m-d',
            'time_format' => ($input['time_format'] ?? '24h') === '12h' ? '12h' : '24h',
            'items_per_page' => max(10, min(200, (int) ($input['items_per_page'] ?? 50))),
            'dashboard_layout' => in_array($input['dashboard_layout'] ?? 'standard', ['compact', 'standard', 'expanded'], true)
                ? $input['dashboard_layout'] : 'standard',
            'default_warehouse_view' => ($input['default_warehouse_view'] ?? 'assigned') === 'all' ? 'all' : 'assigned',
            'two_factor_enabled' => !empty($input['two_factor_enabled']) ? 1 : 0,
        ];
        foreach (['warehouse_notif_dashboard', 'warehouse_notif_low_stock', 'warehouse_notif_transfer', 'warehouse_notif_receiving', 'warehouse_notif_dispatch'] as $key) {
            if (array_key_exists($key, $input)) {
                $prefs[$key] = !empty($input[$key]) ? 1 : 0;
            }
        }
        $this->repo->savePreferences($userId, $prefs);
        AuditLogger::log($userId, 'preferences_updated', 'success', 'user', $userId);
    }

    public function saveNotificationPreferences(int $userId, array $input): void
    {
        if (!$this->canEditProfile($userId, $userId)) {
            throw new RuntimeException('Access denied');
        }

        $payload = [];
        foreach (['email_enabled', 'sms_enabled', 'push_enabled', 'whatsapp_enabled', 'browser_enabled', 'sound_enabled'] as $key) {
            if (array_key_exists($key, $input)) {
                $payload[$key] = !empty($input[$key]) ? 1 : 0;
            }
        }
        if (array_key_exists('whatsapp_phone', $input)) {
            $payload['whatsapp_phone'] = $input['whatsapp_phone'];
        }
        if (array_key_exists('language', $input)) {
            $payload['language'] = in_array($input['language'], ['en', 'fr'], true) ? $input['language'] : 'en';
        }
        $this->notifPrefs->save($userId, $payload);
        AuditLogger::log($userId, 'notification_settings_updated', 'success', 'user', $userId);
    }

    public function uploadAvatar(int $userId, array $file): string
    {
        if (!$this->canEditProfile($userId, $userId)) {
            throw new RuntimeException('Access denied');
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Upload failed');
        }
        if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
            throw new InvalidArgumentException('Image must be under 2 MB');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
        $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extMap[$mime])) {
            throw new InvalidArgumentException('Only JPG, PNG, and WEBP are allowed');
        }
        $ext = $extMap[$mime];

        $dir = dirname(__DIR__, 3) . '/public/uploads/avatars';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create upload directory');
        }

        $filename = 'user_' . $userId . '_' . time() . '.' . $ext;
        $dest = $dir . '/' . $filename;
        if (!$this->resizeImage($file['tmp_name'], $dest, $mime, 256, 256)) {
            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                throw new RuntimeException('Failed to save avatar');
            }
        }

        $user = $this->repo->findUser($userId);
        if (!empty($user['avatar_path'])) {
            $old = dirname(__DIR__, 3) . '/public/' . ltrim((string) $user['avatar_path'], '/');
            if (is_file($old)) {
                @unlink($old);
            }
        }

        $relative = 'uploads/avatars/' . $filename;
        $this->repo->updateAvatar($userId, $relative);
        AuditLogger::log($userId, 'avatar_updated', 'success', 'user', $userId);
        return $this->avatarUrl($relative) ?? '';
    }

    public function deleteAvatar(int $userId): void
    {
        if (!$this->canEditProfile($userId, $userId)) {
            throw new RuntimeException('Access denied');
        }
        $user = $this->repo->findUser($userId);
        if (!empty($user['avatar_path'])) {
            $old = dirname(__DIR__, 3) . '/public/' . ltrim((string) $user['avatar_path'], '/');
            if (is_file($old)) {
                @unlink($old);
            }
        }
        $this->repo->updateAvatar($userId, null);
        AuditLogger::log($userId, 'avatar_removed', 'success', 'user', $userId);
    }

    public function logoutOtherDevices(int $userId): void
    {
        if (!$this->canEditProfile($userId, $userId)) {
            throw new RuntimeException('Access denied');
        }
        RememberMeService::revoke($userId);
        AuditLogger::log($userId, 'sessions_revoked', 'success', 'user', $userId);
    }

    public function loginHistory(int $viewerId, int $targetId, ?string $search, int $limit, int $offset): array
    {
        if (!$this->canViewProfile($viewerId, $targetId)) {
            throw new RuntimeException('Access denied');
        }
        return [
            'data' => $this->repo->listLoginHistory($targetId, $search, $limit, $offset),
            'total' => $this->repo->countLoginHistory($targetId, $search),
        ];
    }

    public function activities(int $viewerId, int $targetId, int $limit, int $offset): array
    {
        if (!$this->canViewProfile($viewerId, $targetId)) {
            throw new RuntimeException('Access denied');
        }
        return ['data' => $this->repo->listActivities($targetId, $limit, $offset)];
    }

    private function safePerformanceStats(int $userId, string $roleSlug, ?int $warehouseId): array
    {
        try {
            return $this->repo->performanceStats($userId, $roleSlug, $warehouseId);
        } catch (Throwable $e) {
            error_log('WarehouseProfile performanceStats: ' . $e->getMessage());
            return ['role' => $roleSlug, 'period_days' => 30, 'metrics' => []];
        }
    }

    private function avatarUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        return '../' . ltrim($path, '/');
    }

    private function currentSessionInfo(): array
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $parsed = $this->parseUa($ua);
        return [
            'session_id' => substr(session_id(), 0, 12) . '…',
            'login_time' => $_SESSION['login_time'] ?? null,
            'last_activity' => $_SESSION['last_activity'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr($ua, 0, 200),
            'browser' => $parsed['browser'],
            'os' => $parsed['os'],
            'device' => $parsed['device'],
            'online' => true,
        ];
    }

    private function formatPreferences(array $prefs): array
    {
        return [
            'theme' => $prefs['theme'] ?? 'system',
            'date_format' => $prefs['date_format'] ?? 'Y-m-d',
            'time_format' => $prefs['time_format'] ?? '24h',
            'items_per_page' => (int) ($prefs['items_per_page'] ?? 50),
            'dashboard_layout' => $prefs['dashboard_layout'] ?? 'standard',
            'default_warehouse_view' => $prefs['default_warehouse_view'] ?? 'assigned',
            'two_factor_enabled' => !empty($prefs['two_factor_enabled']),
        ];
    }

    private function formatNotifications(array $notif, array $prefs): array
    {
        return [
            'email_enabled' => !empty($notif['email_enabled']),
            'sms_enabled' => !empty($notif['sms_enabled']),
            'push_enabled' => !empty($notif['push_enabled']),
            'whatsapp_enabled' => !empty($notif['whatsapp_enabled']),
            'whatsapp_phone' => $notif['whatsapp_phone'] ?? null,
            'browser_enabled' => !empty($notif['browser_enabled']),
            'sound_enabled' => !empty($notif['sound_enabled']),
            'dashboard_alerts' => !empty($prefs['warehouse_notif_dashboard']),
            'low_stock_alerts' => !empty($prefs['warehouse_notif_low_stock']),
            'transfer_alerts' => !empty($prefs['warehouse_notif_transfer']),
            'receiving_alerts' => !empty($prefs['warehouse_notif_receiving']),
            'dispatch_alerts' => !empty($prefs['warehouse_notif_dispatch']),
        ];
    }

    private function resizeImage(string $src, string $dest, string $mime, int $w, int $h): bool
    {
        if (!function_exists('imagecreatetruecolor')) {
            return false;
        }
        $createFn = match ($mime) {
            'image/jpeg' => 'imagecreatefromjpeg',
            'image/png' => 'imagecreatefrompng',
            'image/webp' => function_exists('imagecreatefromwebp') ? 'imagecreatefromwebp' : null,
            default => null,
        };
        if (!$createFn || !function_exists($createFn)) {
            return false;
        }
        $img = @$createFn($src);
        if (!$img) {
            return false;
        }
        $sw = imagesx($img);
        $sh = imagesy($img);
        $scale = min($w / max(1, $sw), $h / max(1, $sh));
        $nw = (int) max(1, round($sw * $scale));
        $nh = (int) max(1, round($sh * $scale));
        $canvas = imagecreatetruecolor($nw, $nh);
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
        }
        imagecopyresampled($canvas, $img, 0, 0, 0, 0, $nw, $nh, $sw, $sh);
        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($canvas, $dest, 88),
            'image/png' => imagepng($canvas, $dest, 8),
            'image/webp' => function_exists('imagewebp') ? imagewebp($canvas, $dest, 88) : false,
            default => false,
        };
        imagedestroy($img);
        imagedestroy($canvas);
        return (bool) $ok;
    }

    /** @return array{browser: string, os: string, device: string} */
    private function parseUa(string $ua): array
    {
        $browser = 'Unknown';
        $os = 'Unknown';
        $device = 'desktop';
        if (preg_match('/Mobile|Android|iPhone/i', $ua)) {
            $device = 'mobile';
        } elseif (preg_match('/Tablet|iPad/i', $ua)) {
            $device = 'tablet';
        }
        if (preg_match('/Firefox/i', $ua)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Chrome/i', $ua)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Safari/i', $ua)) {
            $browser = 'Safari';
        } elseif (preg_match('/Edge/i', $ua)) {
            $browser = 'Edge';
        }
        if (preg_match('/Windows/i', $ua)) {
            $os = 'Windows';
        } elseif (preg_match('/Mac OS/i', $ua)) {
            $os = 'macOS';
        } elseif (preg_match('/Linux/i', $ua)) {
            $os = 'Linux';
        } elseif (preg_match('/Android/i', $ua)) {
            $os = 'Android';
        } elseif (preg_match('/iPhone|iPad/i', $ua)) {
            $os = 'iOS';
        }
        return compact('browser', 'os', 'device');
    }
}
