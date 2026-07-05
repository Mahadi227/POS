<?php
declare(strict_types=1);

final class PlatformRoleRepository
{
    /** @var array<string, array{icon: string, scope: string}> */
    public const REGISTRY = [
        'platform_admin' => ['icon' => 'shield', 'scope' => 'full'],
        'support' => ['icon' => 'support_agent', 'scope' => 'limited'],
    ];

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array{roles: array<int, array<string, mixed>>, matrix: array<int, array<string, mixed>>} */
    public function catalog(): array
    {
        $counts = $this->userCountsByRole();

        $roles = [];
        foreach (self::REGISTRY as $key => $meta) {
            $row = $counts[$key] ?? ['total' => 0, 'active' => 0];
            $roles[] = [
                'key' => $key,
                'icon' => $meta['icon'],
                'scope' => $meta['scope'],
                'users_total' => (int) $row['total'],
                'users_active' => (int) $row['active'],
            ];
        }

        $matrix = [];
        foreach (PlatformPermissionRepository::roleAccessMatrix() as $capability => $access) {
            $matrix[] = [
                'key' => $capability,
                'access' => $access,
            ];
        }

        return [
            'roles' => $roles,
            'matrix' => $matrix,
        ];
    }

    /** @return array<string, int> */
    public function roleStats(): array
    {
        $counts = $this->userCountsByRole();
        $totalUsers = 0;
        $activeUsers = 0;

        foreach ($counts as $row) {
            $totalUsers += (int) ($row['total'] ?? 0);
            $activeUsers += (int) ($row['active'] ?? 0);
        }

        return [
            'roles' => count(self::REGISTRY),
            'permissions' => count(PlatformPermissionRepository::CAPABILITIES),
            'users' => $totalUsers,
            'active_users' => $activeUsers,
        ];
    }

    /** @return array<string, array{total: int, active: int}> */
    private function userCountsByRole(): array
    {
        $counts = [];
        foreach (array_keys(self::REGISTRY) as $role) {
            $counts[$role] = ['total' => 0, 'active' => 0];
        }

        if (!$this->tableExists('platform_users')) {
            return $counts;
        }

        $rows = $this->db->query(
            "SELECT role,
                    COUNT(*) AS total,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active
             FROM platform_users
             GROUP BY role"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as $row) {
            $role = (string) ($row['role'] ?? '');
            if (!isset($counts[$role])) {
                continue;
            }
            $counts[$role] = [
                'total' => (int) ($row['total'] ?? 0),
                'active' => (int) ($row['active'] ?? 0),
            ];
        }

        return $counts;
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
