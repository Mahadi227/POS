<?php
declare(strict_types=1);

final class WebhookRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function listEndpoints(int $tenantId): array
    {
        if (!$this->tableExists('webhook_endpoints')) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT id, url, description, events_json, is_active, created_at, updated_at
             FROM webhook_endpoints WHERE tenant_id = ? ORDER BY id DESC'
        );
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['events'] = json_decode($row['events_json'] ?? '[]', true) ?: [];
            unset($row['events_json']);
        }
        return $rows;
    }

    public function findEndpoint(int $tenantId, int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, tenant_id, url, secret, description, events_json, is_active
             FROM webhook_endpoints WHERE id = ? AND tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['events'] = json_decode($row['events_json'] ?? '[]', true) ?: [];
        return $row;
    }

    /** @param string[] $events */
    public function createEndpoint(int $tenantId, string $url, array $events, ?string $description = null): array
    {
        $secret = bin2hex(random_bytes(32));
        $this->db->prepare(
            'INSERT INTO webhook_endpoints (tenant_id, url, secret, events_json, description)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $tenantId,
            $url,
            $secret,
            json_encode(array_values($events), JSON_UNESCAPED_UNICODE),
            $description,
        ]);
        $id = (int) $this->db->lastInsertId();
        return [
            'id' => $id,
            'secret' => $secret,
            'url' => $url,
            'events' => $events,
        ];
    }

    public function updateEndpoint(int $tenantId, int $id, array $data): bool
    {
        $endpoint = $this->findEndpoint($tenantId, $id);
        if (!$endpoint) {
            return false;
        }
        $sets = [];
        $vals = [];
        if (isset($data['url'])) {
            $sets[] = 'url = ?';
            $vals[] = trim((string) $data['url']);
        }
        if (isset($data['events']) && is_array($data['events'])) {
            $sets[] = 'events_json = ?';
            $vals[] = json_encode(array_values($data['events']), JSON_UNESCAPED_UNICODE);
        }
        if (isset($data['is_active'])) {
            $sets[] = 'is_active = ?';
            $vals[] = $data['is_active'] ? 1 : 0;
        }
        if (isset($data['description'])) {
            $sets[] = 'description = ?';
            $vals[] = $data['description'];
        }
        if (!$sets) {
            return true;
        }
        $vals[] = $id;
        $vals[] = $tenantId;
        $this->db->prepare(
            'UPDATE webhook_endpoints SET ' . implode(', ', $sets) . ' WHERE id = ? AND tenant_id = ?'
        )->execute($vals);
        return true;
    }

    public function deleteEndpoint(int $tenantId, int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM webhook_endpoints WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);
        return $stmt->rowCount() > 0;
    }

    /** @return array<int, array<string, mixed>> */
    public function findSubscribers(int $tenantId, string $eventType): array
    {
        if (!$this->tableExists('webhook_endpoints')) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT id, tenant_id, url, secret, events_json FROM webhook_endpoints
             WHERE tenant_id = ? AND is_active = 1'
        );
        $stmt->execute([$tenantId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $events = json_decode($row['events_json'] ?? '[]', true) ?: [];
            if (in_array($eventType, $events, true) || in_array('*', $events, true)) {
                $out[] = $row;
            }
        }
        return $out;
    }

    public function queueDelivery(
        int $endpointId,
        int $tenantId,
        string $deliveryUuid,
        string $eventType,
        array $payload,
    ): int {
        $this->db->prepare(
            'INSERT INTO webhook_deliveries (endpoint_id, tenant_id, delivery_uuid, event_type, payload_json, next_retry_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        )->execute([
            $endpointId,
            $tenantId,
            $deliveryUuid,
            $eventType,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function listDeliveries(int $tenantId, int $limit = 50): array
    {
        if (!$this->tableExists('webhook_deliveries')) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT wd.id, wd.delivery_uuid, wd.event_type, wd.response_status, wd.attempts,
                    wd.delivered_at, wd.failed_at, wd.created_at, we.url AS endpoint_url
             FROM webhook_deliveries wd
             INNER JOIN webhook_endpoints we ON we.id = wd.endpoint_id
             WHERE wd.tenant_id = ?
             ORDER BY wd.id DESC LIMIT ?'
        );
        $stmt->bindValue(1, $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchPendingDeliveries(int $limit = 50): array
    {
        if (!$this->tableExists('webhook_deliveries')) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT wd.*, we.url, we.secret
             FROM webhook_deliveries wd
             INNER JOIN webhook_endpoints we ON we.id = wd.endpoint_id
             WHERE wd.delivered_at IS NULL AND wd.failed_at IS NULL
               AND (wd.next_retry_at IS NULL OR wd.next_retry_at <= NOW())
               AND wd.attempts < 5
             ORDER BY wd.id ASC LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function markDelivered(int $id, int $statusCode, ?string $body): void
    {
        $this->db->prepare(
            'UPDATE webhook_deliveries SET response_status = ?, response_body = ?, delivered_at = NOW(), attempts = attempts + 1 WHERE id = ?'
        )->execute([$statusCode, $body !== null ? substr($body, 0, 4000) : null, $id]);
    }

    public function markRetry(int $id, int $attempts, string $nextRetryAt, int $statusCode, ?string $body): void
    {
        $this->db->prepare(
            'UPDATE webhook_deliveries SET attempts = ?, next_retry_at = ?, response_status = ?, response_body = ? WHERE id = ?'
        )->execute([$attempts, $nextRetryAt, $statusCode, $body !== null ? substr($body, 0, 4000) : null, $id]);
    }

    public function markFailed(int $id, int $statusCode, ?string $body): void
    {
        $this->db->prepare(
            'UPDATE webhook_deliveries SET failed_at = NOW(), response_status = ?, response_body = ?, attempts = attempts + 1 WHERE id = ?'
        )->execute([$statusCode, $body !== null ? substr($body, 0, 4000) : null, $id]);
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
