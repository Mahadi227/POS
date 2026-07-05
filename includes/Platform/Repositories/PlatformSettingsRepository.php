<?php
declare(strict_types=1);

final class PlatformSettingsRepository
{
    /** @var array<string, array{category: string, description: string, type: string}> */
    public const REGISTRY = [
        'product_name' => ['category' => 'general', 'description' => 'Platform product display name', 'type' => 'string'],
        'support_email' => ['category' => 'general', 'description' => 'Support contact email', 'type' => 'email'],
        'default_locale' => ['category' => 'general', 'description' => 'Default locale for new tenants', 'type' => 'select'],
        'lockout_threshold' => ['category' => 'security', 'description' => 'Failed login attempts before lockout', 'type' => 'number'],
        'lockout_window_minutes' => ['category' => 'security', 'description' => 'Lockout window in minutes', 'type' => 'number'],
        'email_from' => ['category' => 'communications', 'description' => 'Default transactional email sender', 'type' => 'email'],
        'trial_days' => ['category' => 'billing', 'description' => 'Default trial period in days', 'type' => 'number'],
    ];

    /** @var array<string, mixed> */
    private const DEFAULTS = [
        'product_name' => 'RetailPOS Cloud',
        'support_email' => 'support@retailpos.local',
        'default_locale' => 'en',
        'lockout_threshold' => 5,
        'lockout_window_minutes' => 15,
        'email_from' => 'noreply@retailpos.local',
        'trial_days' => 14,
    ];

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function dashboard(): array
    {
        return [
            'stats' => $this->stats(),
            'settings' => $this->groupedSettings(),
            'feature_flags' => $this->featureFlags(),
        ];
    }

    /** @return array<string, int> */
    public function stats(): array
    {
        $total = 0;
        $categories = 0;
        if ($this->tableExists('platform_settings')) {
            $total = (int) $this->db->query('SELECT COUNT(*) FROM platform_settings')->fetchColumn();
            $categories = (int) $this->db->query(
                'SELECT COUNT(DISTINCT category) FROM platform_settings'
            )->fetchColumn();
        }

        $flags = 0;
        if ($this->tableExists('feature_flags')) {
            $flags = (int) $this->db->query('SELECT COUNT(*) FROM feature_flags')->fetchColumn();
        }

        return [
            'settings' => $total,
            'categories' => $categories,
            'feature_flags' => $flags,
        ];
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public function groupedSettings(): array
    {
        $stored = $this->allStored();
        $grouped = [];

        foreach (self::REGISTRY as $key => $meta) {
            $category = $meta['category'];
            $grouped[$category][] = [
                'key' => $key,
                'value' => $stored[$key] ?? self::DEFAULTS[$key] ?? null,
                'category' => $category,
                'description' => $meta['description'],
                'type' => $meta['type'],
                'updated_at' => $stored[$key . '__updated_at'] ?? null,
            ];
        }

        return $grouped;
    }

    /** @return array<int, array<string, mixed>> */
    public function featureFlags(): array
    {
        if (!$this->tableExists('feature_flags')) {
            return [];
        }

        return $this->db->query(
            'SELECT key_name, description, default_enabled FROM feature_flags ORDER BY key_name ASC'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function updateMany(array $values, ?int $platformUserId = null): array
    {
        if (!$this->tableExists('platform_settings')) {
            return [];
        }

        $updated = [];
        foreach ($values as $key => $value) {
            if (!isset(self::REGISTRY[(string) $key])) {
                continue;
            }
            $meta = self::REGISTRY[(string) $key];
            $normalized = $this->normalizeValue((string) $key, $value);

            $this->db->prepare(
                'INSERT INTO platform_settings (key_name, value_json, category, description, updated_by)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE value_json = VALUES(value_json), updated_by = VALUES(updated_by)'
            )->execute([
                $key,
                json_encode($normalized, JSON_UNESCAPED_UNICODE),
                $meta['category'],
                $meta['description'],
                $platformUserId,
            ]);

            $updated[(string) $key] = $normalized;
        }

        return $updated;
    }

    /**
     * @param array<int, array{key_name: string, default_enabled: int}> $flags
     */
    public function updateFeatureFlags(array $flags): int
    {
        if (!$this->tableExists('feature_flags')) {
            return 0;
        }

        $stmt = $this->db->prepare(
            'UPDATE feature_flags SET default_enabled = ? WHERE key_name = ?'
        );
        $count = 0;
        foreach ($flags as $row) {
            $key = (string) ($row['key_name'] ?? '');
            if ($key === '' || !array_key_exists('default_enabled', $row)) {
                continue;
            }
            $stmt->execute([(int) $row['default_enabled'], $key]);
            $count += $stmt->rowCount();
        }

        return $count;
    }

    public function getString(string $key, string $default = ''): string
    {
        $val = $this->getValue($key);
        return is_string($val) ? $val : $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $val = $this->getValue($key);
        return is_numeric($val) ? (int) $val : $default;
    }

    /** @return mixed */
    public function getValue(string $key)
    {
        $stored = $this->allStored();
        if (array_key_exists($key, $stored)) {
            return $stored[$key];
        }

        return self::DEFAULTS[$key] ?? null;
    }

    /** @return array<string, mixed> */
    private function allStored(): array
    {
        if (!$this->tableExists('platform_settings')) {
            return [];
        }

        $rows = $this->db->query(
            'SELECT key_name, value_json, updated_at FROM platform_settings'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $key = (string) ($row['key_name'] ?? '');
            $raw = $row['value_json'] ?? null;
            if ($raw !== null) {
                $decoded = json_decode((string) $raw, true);
                $out[$key] = $decoded;
            }
            $out[$key . '__updated_at'] = $row['updated_at'] ?? null;
        }

        return $out;
    }

    /** @return mixed */
    private function normalizeValue(string $key, mixed $value)
    {
        $type = self::REGISTRY[$key]['type'] ?? 'string';
        if ($type === 'number') {
            return max(0, (int) $value);
        }
        if ($type === 'email') {
            return filter_var((string) $value, FILTER_SANITIZE_EMAIL) ?: (string) $value;
        }

        return trim((string) $value);
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
