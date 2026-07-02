<?php
declare(strict_types=1);

final class UsageMeteringRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function increment(int $tenantId, string $metric, int $amount = 1, ?string $period = null): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $period = $period ?? date('Y-m-01');
        $this->db->prepare(
            'INSERT INTO usage_metrics (tenant_id, metric, period, value)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE value = value + VALUES(value)'
        )->execute([$tenantId, $metric, $period, max(0, $amount)]);
    }

    public function setValue(int $tenantId, string $metric, int $value, ?string $period = null): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $period = $period ?? date('Y-m-01');
        $this->db->prepare(
            'INSERT INTO usage_metrics (tenant_id, metric, period, value)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)'
        )->execute([$tenantId, $metric, $period, max(0, $value)]);
    }

    /** @return array<string, int> */
    public function getPeriodMetrics(int $tenantId, ?string $period = null): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $period = $period ?? date('Y-m-01');
        $stmt = $this->db->prepare(
            'SELECT metric, value FROM usage_metrics WHERE tenant_id = ? AND period = ?'
        );
        $stmt->execute([$tenantId, $period]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[$row['metric']] = (int) $row['value'];
        }
        return $out;
    }

    public function recordAlert(int $tenantId, string $metric, int $thresholdPct, ?string $period = null): bool
    {
        if (!$this->tableExists('usage_alerts')) {
            return false;
        }
        $period = $period ?? date('Y-m-01');
        try {
            $this->db->prepare(
                'INSERT IGNORE INTO usage_alerts (tenant_id, metric, threshold_pct, period) VALUES (?, ?, ?, ?)'
            )->execute([$tenantId, $metric, $thresholdPct, $period]);
            return true;
        } catch (PDOException) {
            return false;
        }
    }

    public function hasAlert(int $tenantId, string $metric, int $thresholdPct, ?string $period = null): bool
    {
        if (!$this->tableExists('usage_alerts')) {
            return false;
        }
        $period = $period ?? date('Y-m-01');
        $stmt = $this->db->prepare(
            'SELECT 1 FROM usage_alerts WHERE tenant_id = ? AND metric = ? AND threshold_pct = ? AND period = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, $metric, $thresholdPct, $period]);
        return (bool) $stmt->fetchColumn();
    }

    private function tableExists(string $table = 'usage_metrics'): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
}
