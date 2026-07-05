<?php
declare(strict_types=1);

final class PlatformNotificationsRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array<string, int> */
    public function stats(): array
    {
        $stats = [
            'broadcasts' => 0,
            'sent' => 0,
            'drafts' => 0,
            'templates' => 0,
            'channels' => 0,
        ];

        if ($this->tableExists('platform_broadcasts')) {
            $row = $this->db->query(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent
                 FROM platform_broadcasts"
            )->fetch(PDO::FETCH_ASSOC) ?: [];
            $stats['broadcasts'] = (int) ($row['total'] ?? 0);
            $stats['sent'] = (int) ($row['sent'] ?? 0);
            $stats['drafts'] = max(0, $stats['broadcasts'] - $stats['sent']);
        }

        if ($this->tableExists('notification_templates')) {
            $stats['templates'] = (int) $this->db->query(
                'SELECT COUNT(*) FROM notification_templates'
            )->fetchColumn();
        }
        if ($this->tableExists('notification_channels')) {
            $stats['channels'] = (int) $this->db->query(
                'SELECT COUNT(*) FROM notification_channels'
            )->fetchColumn();
        }

        return $stats;
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        return [
            'stats' => $this->stats(),
            'broadcasts' => $this->listBroadcasts(50, 0),
            'channels' => $this->listChannels(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function listBroadcasts(int $limit = 50, int $offset = 0, ?string $status = null): array
    {
        if (!$this->tableExists('platform_broadcasts')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];
        if ($status !== null && $status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        }

        $sql = 'SELECT id, title_en, title_fr, message_en, message_fr, audience, status,
                       recipient_count, sent_at, created_at, updated_at
                FROM platform_broadcasts
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?';

        $stmt = $this->db->prepare($sql);
        $i = 1;
        foreach ($params as $p) {
            $stmt->bindValue($i++, $p);
        }
        $stmt->bindValue($i++, $limit, PDO::PARAM_INT);
        $stmt->bindValue($i, $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string, mixed> $data */
    public function createBroadcast(array $data, int $platformUserId): int
    {
        if (!$this->tableExists('platform_broadcasts')) {
            throw new RuntimeException('Broadcasts not available');
        }

        $titleEn = trim((string) ($data['title_en'] ?? ''));
        $messageEn = trim((string) ($data['message_en'] ?? ''));
        if ($titleEn === '' || $messageEn === '') {
            throw new InvalidArgumentException('Title and message are required');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO platform_broadcasts
             (title_en, title_fr, message_en, message_fr, audience, status, created_by)
             VALUES (?, ?, ?, ?, ?, "draft", ?)'
        );
        $stmt->execute([
            $titleEn,
            trim((string) ($data['title_fr'] ?? $titleEn)),
            $messageEn,
            trim((string) ($data['message_fr'] ?? $messageEn)),
            $this->normalizeAudience((string) ($data['audience'] ?? 'all')),
            $platformUserId > 0 ? $platformUserId : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function sendBroadcast(int $id): bool
    {
        if (!$this->tableExists('platform_broadcasts')) {
            return false;
        }

        $stmt = $this->db->prepare('SELECT audience FROM platform_broadcasts WHERE id = ? AND status = "draft" LIMIT 1');
        $stmt->execute([$id]);
        $audience = (string) ($stmt->fetchColumn() ?: '');
        if ($audience === '') {
            return false;
        }

        $count = $this->countTenantsByAudience($audience);
        $upd = $this->db->prepare(
            'UPDATE platform_broadcasts SET status = "sent", recipient_count = ?, sent_at = NOW() WHERE id = ?'
        );
        $upd->execute([$count, $id]);

        return $upd->rowCount() > 0;
    }

    /** @return array<int, array<string, mixed>> */
    private function listChannels(): array
    {
        if (!$this->tableExists('notification_channels')) {
            return [];
        }

        return $this->db->query(
            'SELECT slug, name_en, name_fr FROM notification_channels ORDER BY slug ASC'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function countTenantsByAudience(string $audience): int
    {
        if (!$this->tableExists('tenants')) {
            return 0;
        }

        $where = ['deleted_at IS NULL'];
        if ($audience !== 'all') {
            $where[] = 'status = ?';
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM tenants WHERE ' . implode(' AND ', $where)
            );
            $stmt->execute([$audience]);
            return (int) $stmt->fetchColumn();
        }

        return (int) $this->db->query(
            'SELECT COUNT(*) FROM tenants WHERE deleted_at IS NULL'
        )->fetchColumn();
    }

    private function normalizeAudience(string $audience): string
    {
        $allowed = ['all', 'active', 'trial', 'suspended'];
        return in_array($audience, $allowed, true) ? $audience : 'all';
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
}
