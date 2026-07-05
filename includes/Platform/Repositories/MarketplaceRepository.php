<?php
declare(strict_types=1);

final class MarketplaceRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array<int, array<string, mixed>> */
    public function listApps(
        int $limit = 50,
        int $offset = 0,
        ?string $search = null,
        ?string $category = null,
        ?string $status = null
    ): array {
        if (!$this->tableExists('marketplace_apps')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(ma.name LIKE ? OR ma.slug LIKE ? OR ma.vendor LIKE ? OR ma.short_description LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($category !== null && $category !== '') {
            $where[] = 'ma.category = ?';
            $params[] = $category;
        }
        if ($status !== null && $status !== '') {
            if ($status !== 'all') {
                $where[] = 'ma.status = ?';
                $params[] = $status;
            }
        } else {
            $where[] = "ma.status = 'published'";
        }

        $installJoin = $this->tableExists('tenant_marketplace_installs')
            ? 'LEFT JOIN (
                SELECT app_id, COUNT(DISTINCT tenant_id) AS install_count
                FROM tenant_marketplace_installs
                WHERE status = \'active\'
                GROUP BY app_id
            ) ins ON ins.app_id = ma.id'
            : 'LEFT JOIN (SELECT NULL AS app_id, 0 AS install_count) ins ON 1=0';

        $sql = 'SELECT ma.id, ma.slug, ma.name, ma.short_description, ma.description, ma.category,
                       ma.icon, ma.vendor, ma.status, ma.is_official, ma.pricing,
                       ma.website_url, ma.docs_url, ma.modules_json, ma.sort_order,
                       COALESCE(ins.install_count, 0) AS install_count
                FROM marketplace_apps ma
                ' . $installJoin . '
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY ma.sort_order ASC, ma.name ASC
                LIMIT ? OFFSET ?';

        $stmt = $this->db->prepare($sql);
        $i = 1;
        foreach ($params as $p) {
            $stmt->bindValue($i++, $p);
        }
        $stmt->bindValue($i++, $limit, PDO::PARAM_INT);
        $stmt->bindValue($i, $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $mods = json_decode((string) ($row['modules_json'] ?? '[]'), true);
            $row['modules_required'] = is_array($mods) ? $mods : [];
            $row['is_official'] = (int) ($row['is_official'] ?? 0);
            $row['install_count'] = (int) ($row['install_count'] ?? 0);
            unset($row['modules_json']);
        }
        unset($row);

        return $rows;
    }

    /** @return array<string, int> */
    public function marketplaceStats(): array
    {
        $stats = [
            'total' => 0,
            'published' => 0,
            'official' => 0,
            'installs' => 0,
            'categories' => 0,
        ];

        if (!$this->tableExists('marketplace_apps')) {
            return $stats;
        }

        $row = $this->db->query(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published,
                    SUM(CASE WHEN is_official = 1 THEN 1 ELSE 0 END) AS official,
                    COUNT(DISTINCT category) AS categories
             FROM marketplace_apps"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $stats['total'] = (int) ($row['total'] ?? 0);
        $stats['published'] = (int) ($row['published'] ?? 0);
        $stats['official'] = (int) ($row['official'] ?? 0);
        $stats['categories'] = (int) ($row['categories'] ?? 0);

        if ($this->tableExists('tenant_marketplace_installs')) {
            $stats['installs'] = (int) $this->db->query(
                "SELECT COUNT(*) FROM tenant_marketplace_installs WHERE status = 'active'"
            )->fetchColumn();
        }

        return $stats;
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
