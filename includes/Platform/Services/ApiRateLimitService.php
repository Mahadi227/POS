<?php
declare(strict_types=1);

require_once __DIR__ . '/../Repositories/SubscriptionRepository.php';
require_once __DIR__ . '/../Repositories/UsageMeteringRepository.php';
require_once __DIR__ . '/EntitlementService.php';
require_once __DIR__ . '/UsageMeteringService.php';
require_once __DIR__ . '/../SaaSPhase7Migrator.php';

final class ApiRateLimitService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array{allowed: bool, retry_after?: int, remaining?: int, limit?: int} */
    public function check(int $tenantId): array
    {
        SaaSPhase7Migrator::ensure($this->db);
        $limits = $this->resolveLimits($tenantId);

        if ($limits['month'] <= 0) {
            return ['allowed' => false, 'limit' => 0, 'remaining' => 0];
        }

        $minuteKey = gmdate('Y-m-d\TH:i');
        $minuteHits = $this->incrementBucket($tenantId, 'minute', $minuteKey);
        if ($minuteHits > $limits['minute']) {
            return ['allowed' => false, 'retry_after' => 60, 'limit' => $limits['minute'], 'remaining' => 0];
        }

        $subs = new SubscriptionRepository($this->db);
        $metering = new UsageMeteringService(
            $this->db,
            new UsageMeteringRepository($this->db),
            new EntitlementService($this->db, $subs),
        );
        $metering->trackApiCall($tenantId);

        $period = date('Y-m-01');
        $repo = new UsageMeteringRepository($this->db);
        $monthUsed = (int) ($repo->getPeriodMetrics($tenantId, $period)['api.calls'] ?? 0);
        $remaining = max(0, $limits['month'] - $monthUsed);

        if ($monthUsed > $limits['month']) {
            return ['allowed' => false, 'retry_after' => 3600, 'limit' => $limits['month'], 'remaining' => 0];
        }

        header('X-RateLimit-Limit: ' . $limits['month']);
        header('X-RateLimit-Remaining: ' . $remaining);

        return ['allowed' => true, 'limit' => $limits['month'], 'remaining' => $remaining];
    }

    /** @return array{month: int, minute: int} */
    private function resolveLimits(int $tenantId): array
    {
        if ($tenantId === 1) {
            return ['month' => 500000, 'minute' => 1000];
        }

        $subs = new SubscriptionRepository($this->db);
        $sub = $subs->getActiveSubscription($tenantId);
        $mods = [];
        if ($sub) {
            $mods = json_decode($sub['modules_json'] ?? '{}', true) ?: [];
        }

        $month = (int) ($mods['max_api_calls_per_month'] ?? 0);
        $minute = (int) ($mods['api_burst_per_minute'] ?? 100);

        $planCode = $sub['plan_code'] ?? '';

        return match ($planCode) {
            'enterprise' => ['month' => $month ?: 500000, 'minute' => $minute ?: 1000],
            'business' => ['month' => $month ?: 50000, 'minute' => $minute ?: 100],
            default => ['month' => $month, 'minute' => $minute ?: ($month > 0 ? 100 : 0)],
        };
    }

    private function incrementBucket(int $tenantId, string $bucket, string $bucketKey): int
    {
        if (!$this->tableExists('api_rate_limit_buckets')) {
            return 1;
        }

        $this->db->prepare(
            'INSERT INTO api_rate_limit_buckets (tenant_id, bucket, bucket_key, hits)
             VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE hits = hits + 1, updated_at = NOW()'
        )->execute([$tenantId, $bucket, $bucketKey]);

        $stmt = $this->db->prepare(
            'SELECT hits FROM api_rate_limit_buckets WHERE tenant_id = ? AND bucket = ? AND bucket_key = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, $bucket, $bucketKey]);
        return (int) $stmt->fetchColumn();
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
