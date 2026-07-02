<?php
declare(strict_types=1);

require_once __DIR__ . '/../Repositories/ApiKeyRepository.php';
require_once __DIR__ . '/../SaaSPhase7Migrator.php';

final class ApiKeyService
{
    /** @var string[] */
    public const SCOPES = [
        'tenant:read',
        'stores:read',
        'products:read',
        'sales:read',
        'sales:write',
        'inventory:read',
        '*',
    ];

    private PDO $db;
    private ApiKeyRepository $keys;

    public function __construct(PDO $db, ApiKeyRepository $keys)
    {
        $this->db = $db;
        $this->keys = $keys;
    }

    /** @param string[] $scopes @return array{raw_key: string, id: int, prefix: string, scopes: string[]} */
    public function create(int $tenantId, string $name, array $scopes, ?int $createdBy): array
    {
        SaaSPhase7Migrator::ensure($this->db);
        $scopes = $this->normalizeScopes($scopes);
        if (!$scopes) {
            throw new InvalidArgumentException('At least one scope is required');
        }

        $raw = 'rp_live_' . bin2hex(random_bytes(24));
        $hash = hash('sha256', $raw);
        $prefix = substr($raw, 0, 16);

        $id = $this->keys->create($tenantId, trim($name) ?: 'API Key', $prefix, $hash, $scopes, $createdBy);

        return [
            'id' => $id,
            'raw_key' => $raw,
            'prefix' => $prefix,
            'scopes' => $scopes,
        ];
    }

    public function validate(string $rawKey): ?array
    {
        if (!str_starts_with($rawKey, 'rp_live_') || strlen($rawKey) < 40) {
            return null;
        }
        SaaSPhase7Migrator::ensure($this->db);
        $row = $this->keys->findByHash(hash('sha256', $rawKey));
        if (!$row) {
            return null;
        }
        $this->keys->touchLastUsed((int) $row['id']);
        return [
            'key_id' => (int) $row['id'],
            'tenant_id' => (int) $row['tenant_id'],
            'name' => $row['name'],
            'scopes' => $row['scopes'] ?? [],
        ];
    }

    public function hasScope(array $auth, string $scope): bool
    {
        $scopes = $auth['scopes'] ?? [];
        if (in_array('*', $scopes, true)) {
            return true;
        }
        return in_array($scope, $scopes, true);
    }

    public function list(int $tenantId): array
    {
        SaaSPhase7Migrator::ensure($this->db);
        return $this->keys->listKeys($tenantId);
    }

    public function revoke(int $tenantId, int $id): bool
    {
        return $this->keys->revoke($tenantId, $id);
    }

    /** @param string[] $scopes @return string[] */
    private function normalizeScopes(array $scopes): array
    {
        $out = [];
        foreach ($scopes as $s) {
            $s = trim((string) $s);
            if ($s !== '' && in_array($s, self::SCOPES, true)) {
                $out[] = $s;
            }
        }
        return array_values(array_unique($out));
    }
}
