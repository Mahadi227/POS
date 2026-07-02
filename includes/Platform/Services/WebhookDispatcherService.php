<?php
declare(strict_types=1);

require_once __DIR__ . '/../Repositories/WebhookRepository.php';
require_once __DIR__ . '/../SaaSPhase6Migrator.php';

final class WebhookDispatcherService
{
    /** @var string[] */
    public const SAAS_EVENTS = [
        'tenant.provisioned',
        'tenant.suspended',
        'tenant.restored',
        'subscription.updated',
        'payment.received',
        'payment.failed',
        'trial.ending_soon',
        'sale.completed',
    ];

    /** @var int[] minutes between retries */
    private const RETRY_MINUTES = [0, 5, 30, 120, 1440];

    private PDO $db;
    private WebhookRepository $webhooks;

    public function __construct(PDO $db, WebhookRepository $webhooks)
    {
        $this->db = $db;
        $this->webhooks = $webhooks;
    }

    public static function dispatch(PDO $db, int $tenantId, string $eventType, array $payload): int
    {
        try {
            SaaSPhase6Migrator::ensure($db);
            $svc = new self($db, new WebhookRepository($db));
            return $svc->queueEvent($tenantId, $eventType, $payload);
        } catch (Throwable $e) {
            error_log('Webhook dispatch failed: ' . $e->getMessage());
            return 0;
        }
    }

    public function queueEvent(int $tenantId, string $eventType, array $payload): int
    {
        $subscribers = $this->webhooks->findSubscribers($tenantId, $eventType);
        $count = 0;
        $envelope = [
            'id' => $this->uuid4(),
            'type' => $eventType,
            'created_at' => gmdate('c'),
            'data' => $payload,
        ];

        foreach ($subscribers as $endpoint) {
            $this->webhooks->queueDelivery(
                (int) $endpoint['id'],
                $tenantId,
                $this->uuid4(),
                $eventType,
                $envelope,
            );
            $count++;
        }

        return $count;
    }

    public function processPending(int $limit = 50): array
    {
        $pending = $this->webhooks->fetchPendingDeliveries($limit);
        $stats = ['processed' => 0, 'delivered' => 0, 'retried' => 0, 'failed' => 0];

        foreach ($pending as $row) {
            $stats['processed']++;
            $result = $this->deliver($row);
            if ($result === 'delivered') {
                $stats['delivered']++;
            } elseif ($result === 'retry') {
                $stats['retried']++;
            } else {
                $stats['failed']++;
            }
        }

        return $stats;
    }

    /** @param array<string, mixed> $row */
    private function deliver(array $row): string
    {
        $id = (int) $row['id'];
        $attempts = (int) ($row['attempts'] ?? 0) + 1;
        $payload = (string) ($row['payload_json'] ?? '{}');
        $secret = (string) ($row['secret'] ?? '');
        $url = (string) ($row['url'] ?? '');
        $eventType = (string) ($row['event_type'] ?? '');
        $deliveryUuid = (string) ($row['delivery_uuid'] ?? '');

        $signature = hash_hmac('sha256', $payload, $secret);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: RetailPOS-Webhooks/1.0',
                'X-RetailPOS-Event: ' . $eventType,
                'X-RetailPOS-Delivery: ' . $deliveryUuid,
                'X-RetailPOS-Signature: sha256=' . $signature,
            ],
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($status >= 200 && $status < 300) {
            $this->webhooks->markDelivered($id, $status, is_string($body) ? $body : null);
            return 'delivered';
        }

        if ($attempts >= 5) {
            $this->webhooks->markFailed($id, $status ?: 0, $curlErr ?: (is_string($body) ? $body : null));
            return 'failed';
        }

        $delayMin = self::RETRY_MINUTES[$attempts] ?? 1440;
        $next = date('Y-m-d H:i:s', strtotime('+' . $delayMin . ' minutes'));
        $this->webhooks->markRetry($id, $attempts, $next, $status ?: 0, $curlErr ?: (is_string($body) ? $body : null));
        return 'retry';
    }

    private function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
