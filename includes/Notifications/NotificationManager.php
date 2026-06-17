<?php
declare(strict_types=1);

require_once __DIR__ . '/NotificationSchemaMigrator.php';
require_once __DIR__ . '/Repositories/NotificationRepository.php';
require_once __DIR__ . '/Repositories/NotificationTemplateRepository.php';
require_once __DIR__ . '/Repositories/NotificationPreferenceRepository.php';
require_once __DIR__ . '/Repositories/NotificationQueueRepository.php';
require_once __DIR__ . '/Repositories/NotificationLogRepository.php';
require_once __DIR__ . '/Services/NotificationDeliveryService.php';
require_once __DIR__ . '/Services/NotificationAnalyticsService.php';
require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Auth/RoleRedirect.php';

/**
 * Central notification dispatcher — integrates with all ERP modules.
 */
class NotificationManager
{
    private static ?PDO $db = null;
    private static ?NotificationRepository $repo = null;
    private static ?NotificationTemplateRepository $templates = null;
    private static ?NotificationDeliveryService $delivery = null;
    private static ?NotificationLogRepository $logs = null;

    private static function boot(): void
    {
        if (self::$db === null) {
            self::$db = Database::getInstance()->getConnection();
            NotificationSchemaMigrator::ensure(self::$db);
            self::$repo = new NotificationRepository(self::$db);
            self::$templates = new NotificationTemplateRepository(self::$db);
            self::$delivery = new NotificationDeliveryService(self::$db);
            self::$logs = new NotificationLogRepository(self::$db);
        }
    }

    /**
     * Dispatch notification to targeted users.
     *
     * @param array{
     *   template?: string,
     *   title?: string,
     *   message?: string,
     *   title_en?: string, title_fr?: string,
     *   message_en?: string, message_fr?: string,
     *   type?: string,
     *   category?: string,
     *   module?: string,
     *   severity?: string,
     *   priority?: string,
     *   params?: array,
     *   roles?: string[],
     *   user_ids?: int[],
     *   store_id?: int|null,
     *   branch_id?: int|null,
     *   warehouse_id?: int|null,
     *   entity_type?: string,
     *   entity_id?: int,
     *   action_url?: string,
     *   channels?: string[],
     *   payload?: array
     * } $options
     * @return int[] notification IDs created
     */
    public static function dispatch(array $options): array
    {
        self::boot();
        if (!NotificationSchemaMigrator::isReady(self::$db)) {
            return [];
        }

        $userIds = self::resolveRecipients($options);
        if (!$userIds) {
            return [];
        }

        $rendered = self::renderMessage($options);
        $ids = [];

        foreach ($userIds as $userId) {
            $lang = self::userLanguage($userId);
            $uuid = self::uuid();
            $title = $lang === 'fr' ? ($rendered['title_fr'] ?? $rendered['title']) : ($rendered['title_en'] ?? $rendered['title']);
            $message = $lang === 'fr' ? ($rendered['message_fr'] ?? $rendered['message']) : ($rendered['message_en'] ?? $rendered['message']);

            $id = self::$repo->create([
                'uuid' => $uuid,
                'user_id' => $userId,
                'template_slug' => $options['template'] ?? null,
                'type_slug' => $rendered['type_slug'],
                'category_slug' => $rendered['category_slug'],
                'module' => $rendered['module'],
                'priority' => $rendered['priority'],
                'severity' => $rendered['severity'],
                'title' => $title,
                'message' => $message,
                'payload' => array_merge($options['payload'] ?? [], [
                    'title_en' => $rendered['title_en'] ?? $title,
                    'title_fr' => $rendered['title_fr'] ?? $title,
                    'message_en' => $rendered['message_en'] ?? $message,
                    'message_fr' => $rendered['message_fr'] ?? $message,
                    'params' => $options['params'] ?? [],
                ]),
                'action_url' => $options['action_url'] ?? null,
                'entity_type' => $options['entity_type'] ?? null,
                'entity_id' => $options['entity_id'] ?? null,
                'store_id' => $options['store_id'] ?? null,
                'branch_id' => $options['branch_id'] ?? null,
                'warehouse_id' => $options['warehouse_id'] ?? null,
            ]);

            self::$logs->log($id, $userId, 'created', 'success', 'in_app');
            self::$delivery->deliver($id, $userId, $title, $message, $options['channels'] ?? ['in_app']);
            $ids[] = $id;
        }

        return $ids;
    }

    /** Quick helper for a single user. */
    public static function notifyUser(int $userId, array $options): ?int
    {
        $options['user_ids'] = [$userId];
        $ids = self::dispatch($options);
        return $ids[0] ?? null;
    }

    private static function resolveRecipients(array $options): array
    {
        if (!empty($options['user_ids'])) {
            return array_values(array_unique(array_map('intval', $options['user_ids'])));
        }

        $roles = $options['roles'] ?? ['admin', 'super_admin'];
        $roleSlugs = array_map([RoleRedirect::class, 'slug'], $roles);
        $placeholders = implode(',', array_fill(0, count($roleSlugs), '?'));
        $params = $roleSlugs;

        $sql = "SELECT u.id FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                WHERE LOWER(REPLACE(r.name, ' ', '_')) IN ({$placeholders})
                  AND u.deleted_at IS NULL
                  AND (u.status = 'active' OR u.is_active = 1)";

        if (!empty($options['store_id'])) {
            $sql .= ' AND (u.store_id = ? OR u.branch_id = ? OR u.store_id IS NULL)';
            $params[] = $options['store_id'];
            $params[] = $options['store_id'];
        }
        if (!empty($options['warehouse_id'])) {
            $sql .= ' AND (u.warehouse_id = ? OR u.warehouse_id IS NULL)';
            $params[] = $options['warehouse_id'];
        }

        $stmt = self::$db->prepare($sql);
        $stmt->execute($params);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    private static function renderMessage(array $options): array
    {
        $params = $options['params'] ?? [];
        $template = null;
        if (!empty($options['template'])) {
            $template = self::$templates->findBySlug($options['template']);
        }

        $replace = static function (string $text) use ($params): string {
            foreach ($params as $k => $v) {
                $text = str_replace('{' . $k . '}', (string) $v, $text);
            }
            return $text;
        };

        if ($template) {
            return [
                'type_slug' => $template['type_slug'] ?? $options['type'] ?? 'info',
                'category_slug' => $options['category'] ?? ($template['category_slug'] ?? 'system'),
                'module' => $options['module'] ?? ($template['module'] ?? 'system'),
                'priority' => $options['priority'] ?? $template['default_priority'] ?? 'normal',
                'severity' => $options['severity'] ?? $template['type_slug'] ?? 'info',
                'title_en' => $replace($template['title_en']),
                'title_fr' => $replace($template['title_fr']),
                'message_en' => $replace($template['body_en']),
                'message_fr' => $replace($template['body_fr']),
                'title' => $replace($template['title_en']),
                'message' => $replace($template['body_en']),
            ];
        }

        return [
            'type_slug' => $options['type'] ?? 'info',
            'category_slug' => $options['category'] ?? 'system',
            'module' => $options['module'] ?? 'system',
            'priority' => $options['priority'] ?? 'normal',
            'severity' => $options['severity'] ?? $options['type'] ?? 'info',
            'title_en' => $options['title_en'] ?? $options['title'] ?? 'Notification',
            'title_fr' => $options['title_fr'] ?? $options['title'] ?? 'Notification',
            'message_en' => $options['message_en'] ?? $options['message'] ?? '',
            'message_fr' => $options['message_fr'] ?? $options['message'] ?? '',
            'title' => $options['title'] ?? 'Notification',
            'message' => $options['message'] ?? '',
        ];
    }

    private static function userLanguage(int $userId): string
    {
        $stmt = self::$db->prepare('SELECT language FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $lang = $stmt->fetchColumn();
        return in_array($lang, ['en', 'fr'], true) ? $lang : ($_SESSION['lang'] ?? 'en');
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
