<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../Repositories/NotificationPreferenceRepository.php';
require_once __DIR__ . '/../Repositories/NotificationQueueRepository.php';
require_once __DIR__ . '/../Repositories/NotificationLogRepository.php';
require_once __DIR__ . '/../../Helpers/MailHelper.php';

class NotificationDeliveryService
{
    private PDO $db;
    private NotificationPreferenceRepository $prefs;
    private NotificationQueueRepository $queue;
    private NotificationLogRepository $logs;

    private const PRIORITY_RANK = ['low' => 1, 'normal' => 2, 'high' => 3, 'critical' => 4];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->prefs = new NotificationPreferenceRepository($this->db);
        $this->queue = new NotificationQueueRepository($this->db);
        $this->logs = new NotificationLogRepository($this->db);
    }

    public function deliver(int $notificationId, int $userId, string $title, string $message, array $channels): void
    {
        $prefs = $this->prefs->get($userId);
        if ($this->inQuietHours($prefs)) {
            $channels = array_diff($channels, ['browser', 'push', 'sms', 'whatsapp']);
        }

        foreach ($channels as $channel) {
            if ($channel === 'in_app') {
                continue;
            }
            if (!$this->channelEnabled($prefs, $channel)) {
                continue;
            }
            $this->enqueueChannel($notificationId, $userId, $channel, $title, $message);
        }
    }

    public function processQueue(int $limit = 30): array
    {
        $processed = ['sent' => 0, 'failed' => 0];
        foreach ($this->queue->fetchPending($limit) as $item) {
            try {
                $ok = match ($item['channel_slug']) {
                    'email' => $this->sendEmail($item),
                    'sms', 'whatsapp' => $this->sendSmsStub($item),
                    'webhook' => $this->sendWebhookStub($item),
                    'browser', 'push' => true,
                    default => true,
                };
                if ($ok) {
                    $this->queue->markSent((int) $item['id']);
                    $this->logs->log(
                        (int) ($item['notification_id'] ?? 0) ?: null,
                        (int) $item['user_id'],
                        'delivered',
                        'success',
                        $item['channel_slug']
                    );
                    $processed['sent']++;
                } else {
                    throw new RuntimeException('Delivery failed');
                }
            } catch (Throwable $e) {
                $this->queue->markFailed((int) $item['id'], $e->getMessage());
                $processed['failed']++;
            }
        }
        return $processed;
    }

    private function enqueueChannel(int $notificationId, int $userId, string $channel, string $title, string $message): void
    {
        $recipient = $this->resolveRecipient($userId, $channel);
        if (in_array($channel, ['email', 'sms', 'whatsapp'], true) && empty($recipient)) {
            return;
        }
        $this->queue->enqueue([
            'notification_id' => $notificationId,
            'user_id' => $userId,
            'channel_slug' => $channel,
            'recipient' => $recipient,
            'subject' => $title,
            'body' => $message,
        ]);
    }

    private function resolveRecipient(int $userId, string $channel): ?string
    {
        if ($channel === 'whatsapp') {
            $prefs = $this->prefs->get($userId);
            $phone = NotificationPreferenceRepository::normalizePhone($prefs['whatsapp_phone'] ?? null);
            if ($phone !== null) {
                return $phone;
            }
        }

        $stmt = $this->db->prepare('SELECT email, phone FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return null;
        }
        return match ($channel) {
            'email' => $user['email'] ?? null,
            'sms', 'whatsapp' => NotificationPreferenceRepository::normalizePhone($user['phone'] ?? null),
            default => null,
        };
    }

    private function channelEnabled(array $prefs, string $channel): bool
    {
        return match ($channel) {
            'email' => (bool) ($prefs['email_enabled'] ?? 1),
            'sms' => (bool) ($prefs['sms_enabled'] ?? 0),
            'push' => (bool) ($prefs['push_enabled'] ?? 1),
            'whatsapp' => (bool) ($prefs['whatsapp_enabled'] ?? 0),
            'browser' => (bool) ($prefs['browser_enabled'] ?? 1),
            default => true,
        };
    }

    private function inQuietHours(array $prefs): bool
    {
        $start = $prefs['quiet_hours_start'] ?? null;
        $end = $prefs['quiet_hours_end'] ?? null;
        if (!$start || !$end) {
            return false;
        }
        $now = date('H:i:s');
        if ($start <= $end) {
            return $now >= $start && $now <= $end;
        }
        return $now >= $start || $now <= $end;
    }

    private function sendEmail(array $item): bool
    {
        if (empty($item['recipient'])) {
            return false;
        }
        $html = '<p>' . htmlspecialchars($item['body'], ENT_QUOTES, 'UTF-8') . '</p>';
        return send_app_email($item['recipient'], $item['subject'] ?? 'Notification', $html);
    }

    private function sendSmsStub(array $item): bool
    {
        error_log('SMS stub: ' . ($item['recipient'] ?? '') . ' — ' . $item['body']);
        return true;
    }

    private function sendWebhookStub(array $item): bool
    {
        error_log('Webhook stub for notification ' . ($item['notification_id'] ?? ''));
        return true;
    }
}
