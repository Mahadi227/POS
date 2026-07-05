<?php
declare(strict_types=1);

final class PlatformUserRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array<int, array<string, mixed>> */
    public function listUsers(
        int $limit = 50,
        int $offset = 0,
        ?string $search = null,
        ?string $role = null,
        ?string $active = null
    ): array {
        if (!$this->tableExists()) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(pu.name LIKE ? OR pu.email LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if ($role !== null && $role !== '') {
            $where[] = 'pu.role = ?';
            $params[] = $role;
        }
        if ($active === 'yes') {
            $where[] = 'pu.is_active = 1';
        } elseif ($active === 'no') {
            $where[] = 'pu.is_active = 0';
        }

        $sql = 'SELECT pu.id, pu.email, pu.name, pu.role, pu.is_active, pu.last_login, pu.created_at
                FROM platform_users pu
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY pu.id ASC
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

    /** @return array<string, int> */
    public function userStats(): array
    {
        $stats = [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'platform_admin' => 0,
            'support' => 0,
        ];

        if (!$this->tableExists()) {
            return $stats;
        }

        $row = $this->db->query(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive,
                    SUM(CASE WHEN role = 'platform_admin' THEN 1 ELSE 0 END) AS platform_admin,
                    SUM(CASE WHEN role = 'support' THEN 1 ELSE 0 END) AS support
             FROM platform_users"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $stats['total'] = (int) ($row['total'] ?? 0);
        $stats['active'] = (int) ($row['active'] ?? 0);
        $stats['inactive'] = (int) ($row['inactive'] ?? 0);
        $stats['platform_admin'] = (int) ($row['platform_admin'] ?? 0);
        $stats['support'] = (int) ($row['support'] ?? 0);

        return $stats;
    }

    public function findById(int $id): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT id, email, name, role, is_active, last_login, created_at FROM platform_users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT id FROM platform_users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(string $name, string $email, string $passwordHash, string $role): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO platform_users (email, password_hash, name, role, is_active) VALUES (?, ?, ?, ?, 1)'
        );
        $stmt->execute([$email, $passwordHash, $name, $role]);
        return (int) $this->db->lastInsertId();
    }

    public function setActive(int $userId, bool $active): bool
    {
        if (!$this->tableExists()) {
            return false;
        }
        $stmt = $this->db->prepare('UPDATE platform_users SET is_active = ? WHERE id = ?');
        $stmt->execute([$active ? 1 : 0, $userId]);
        return $stmt->rowCount() > 0;
    }

    public function countActiveAdmins(): int
    {
        if (!$this->tableExists()) {
            return 0;
        }
        return (int) $this->db->query(
            "SELECT COUNT(*) FROM platform_users WHERE role = 'platform_admin' AND is_active = 1"
        )->fetchColumn();
    }

    public function emailTakenByOther(int $userId, string $email): bool
    {
        if (!$this->tableExists()) {
            return false;
        }
        $stmt = $this->db->prepare(
            'SELECT id FROM platform_users WHERE email = ? AND id != ? LIMIT 1'
        );
        $stmt->execute([strtolower(trim($email)), $userId]);
        return (bool) $stmt->fetchColumn();
    }

    public function getPasswordHash(int $userId): ?string
    {
        if (!$this->tableExists()) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT password_hash FROM platform_users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();
        return is_string($hash) && $hash !== '' ? $hash : null;
    }

    public function updateProfile(int $userId, string $name, string $email): bool
    {
        if (!$this->tableExists()) {
            return false;
        }
        $stmt = $this->db->prepare(
            'UPDATE platform_users SET name = ?, email = ? WHERE id = ?'
        );
        $stmt->execute([trim($name), strtolower(trim($email)), $userId]);
        return $stmt->rowCount() > 0;
    }

    public function updatePassword(int $userId, string $passwordHash): bool
    {
        if (!$this->tableExists()) {
            return false;
        }
        $stmt = $this->db->prepare('UPDATE platform_users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$passwordHash, $userId]);
        return $stmt->rowCount() > 0;
    }

    private function tableExists(): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute(['platform_users']);
        return (bool) $stmt->fetchColumn();
    }
}
