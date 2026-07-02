<?php
declare(strict_types=1);

final class PlatformStatusService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getPublicStatus(): array
    {
        $components = $this->listComponents();
        $incidents = $this->listActiveIncidents();
        $overall = $this->computeOverall($components);

        return [
            'overall' => $overall,
            'components' => $components,
            'incidents' => $incidents,
            'updated_at' => gmdate('c'),
        ];
    }

    public function listComponents(): array
    {
        if (!$this->tableExists('platform_status_components')) {
            return [];
        }
        return $this->db->query(
            'SELECT code, name, status, updated_at FROM platform_status_components ORDER BY sort_order ASC'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listActiveIncidents(): array
    {
        if (!$this->tableExists('platform_incidents')) {
            return [];
        }
        return $this->db->query(
            "SELECT id, title, message, severity, status, affects_json, started_at, resolved_at
             FROM platform_incidents WHERE status != 'resolved' ORDER BY started_at DESC LIMIT 20"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listRecentIncidents(int $limit = 30): array
    {
        if (!$this->tableExists('platform_incidents')) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT id, title, message, severity, status, affects_json, started_at, resolved_at
             FROM platform_incidents ORDER BY started_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createIncident(array $data, ?int $platformUserId = null): int
    {
        $this->db->prepare(
            'INSERT INTO platform_incidents (title, message, severity, status, affects_json, created_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            trim((string) ($data['title'] ?? 'Incident')),
            trim((string) ($data['message'] ?? '')),
            in_array($data['severity'] ?? '', ['minor', 'major', 'critical'], true) ? $data['severity'] : 'minor',
            in_array($data['status'] ?? '', ['investigating', 'identified', 'monitoring', 'resolved'], true)
                ? $data['status'] : 'investigating',
            isset($data['affects']) ? json_encode($data['affects'], JSON_UNESCAPED_UNICODE) : null,
            $platformUserId,
        ]);

        $id = (int) $this->db->lastInsertId();
        $this->syncComponentStatusFromIncident($data['affects'] ?? [], $data['severity'] ?? 'minor');
        return $id;
    }

    public function resolveIncident(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE platform_incidents SET status = 'resolved', resolved_at = NOW() WHERE id = ? AND status != 'resolved'"
        );
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            $this->resetComponentsOperational();
            return true;
        }
        return false;
    }

    public function updateComponentStatus(string $code, string $status): void
    {
        if (!$this->tableExists('platform_status_components')) {
            return;
        }
        $allowed = ['operational', 'degraded', 'partial_outage', 'major_outage', 'maintenance'];
        if (!in_array($status, $allowed, true)) {
            return;
        }
        $this->db->prepare('UPDATE platform_status_components SET status = ? WHERE code = ?')
            ->execute([$status, $code]);
    }

    /** @param array<int, array<string, mixed>> $components */
    private function computeOverall(array $components): string
    {
        $priority = [
            'major_outage' => 4,
            'partial_outage' => 3,
            'degraded' => 2,
            'maintenance' => 1,
            'operational' => 0,
        ];
        $worst = 'operational';
        $worstScore = 0;
        foreach ($components as $c) {
            $s = $c['status'] ?? 'operational';
            $score = $priority[$s] ?? 0;
            if ($score > $worstScore) {
                $worstScore = $score;
                $worst = $s;
            }
        }
        return $worst;
    }

    /** @param array<int, string> $affects */
    private function syncComponentStatusFromIncident(array $affects, string $severity): void
    {
        $status = match ($severity) {
            'critical' => 'major_outage',
            'major' => 'partial_outage',
            default => 'degraded',
        };
        foreach ($affects as $code) {
            $this->updateComponentStatus((string) $code, $status);
        }
    }

    private function resetComponentsOperational(): void
    {
        if ($this->listActiveIncidents()) {
            return;
        }
        if ($this->tableExists('platform_status_components')) {
            $this->db->exec("UPDATE platform_status_components SET status = 'operational'");
        }
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
