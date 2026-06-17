<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';

class NotificationPreferenceRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function get(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM notification_preferences WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }
        return $this->defaults($userId);
    }

    public function save(int $userId, array $prefs): void
    {
        $existing = $this->get($userId);
        $whatsappEnabled = (int) ($prefs['whatsapp_enabled'] ?? $existing['whatsapp_enabled'] ?? 0);
        $whatsappPhone = array_key_exists('whatsapp_phone', $prefs)
            ? self::normalizePhone($prefs['whatsapp_phone'] !== '' ? (string) $prefs['whatsapp_phone'] : null)
            : self::normalizePhone($existing['whatsapp_phone'] ?? null);

        if ($whatsappEnabled && $whatsappPhone === null) {
            throw new InvalidArgumentException('WhatsApp phone number is required when WhatsApp notifications are enabled.');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO notification_preferences
                (user_id, email_enabled, sms_enabled, push_enabled, whatsapp_enabled, whatsapp_phone, browser_enabled,
                 sound_enabled, quiet_hours_start, quiet_hours_end, min_priority, language, category_filters)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                email_enabled = VALUES(email_enabled),
                sms_enabled = VALUES(sms_enabled),
                push_enabled = VALUES(push_enabled),
                whatsapp_enabled = VALUES(whatsapp_enabled),
                whatsapp_phone = VALUES(whatsapp_phone),
                browser_enabled = VALUES(browser_enabled),
                sound_enabled = VALUES(sound_enabled),
                quiet_hours_start = VALUES(quiet_hours_start),
                quiet_hours_end = VALUES(quiet_hours_end),
                min_priority = VALUES(min_priority),
                language = VALUES(language),
                category_filters = VALUES(category_filters)'
        );
        $stmt->execute([
            $userId,
            (int) ($prefs['email_enabled'] ?? $existing['email_enabled'] ?? 1),
            (int) ($prefs['sms_enabled'] ?? $existing['sms_enabled'] ?? 0),
            (int) ($prefs['push_enabled'] ?? $existing['push_enabled'] ?? 1),
            $whatsappEnabled,
            $whatsappPhone,
            (int) ($prefs['browser_enabled'] ?? $existing['browser_enabled'] ?? 1),
            (int) ($prefs['sound_enabled'] ?? $existing['sound_enabled'] ?? 1),
            $prefs['quiet_hours_start'] ?? null,
            $prefs['quiet_hours_end'] ?? null,
            $prefs['min_priority'] ?? 'low',
            $prefs['language'] ?? 'en',
            isset($prefs['category_filters']) ? json_encode($prefs['category_filters']) : null,
        ]);
    }

    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }
        $clean = preg_replace('/[^\d+]/', '', trim($phone)) ?? '';
        if ($clean === '' || !preg_match('/^\+?[0-9]{8,15}$/', $clean)) {
            return null;
        }
        return $clean;
    }

    private function defaults(int $userId): array
    {
        return [
            'user_id' => $userId,
            'email_enabled' => 1,
            'sms_enabled' => 0,
            'push_enabled' => 1,
            'whatsapp_enabled' => 0,
            'whatsapp_phone' => null,
            'browser_enabled' => 1,
            'sound_enabled' => 1,
            'quiet_hours_start' => null,
            'quiet_hours_end' => null,
            'min_priority' => 'low',
            'language' => 'en',
            'category_filters' => null,
        ];
    }
}
