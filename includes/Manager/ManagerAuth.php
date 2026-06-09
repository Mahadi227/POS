<?php
/**
 * Manager module — authorization helpers.
 */
declare(strict_types=1);

require_once __DIR__ . '/../Helpers/StoreScope.php';

class ManagerAuth
{
    public static function roleSlug(): string
    {
        return StoreScope::roleSlug();
    }

    public static function canAccessManagerModule(): bool
    {
        return in_array(self::roleSlug(), ['manager', 'admin', 'super_admin'], true);
    }

    public static function canApprove(): bool
    {
        return self::canAccessManagerModule();
    }

    public static function requireManagerApi(): void
    {
        if (!self::canAccessManagerModule()) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Accès supervision refusé']);
            exit;
        }
    }

    public static function currentUserId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }
}
