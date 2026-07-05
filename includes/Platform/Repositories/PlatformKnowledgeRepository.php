<?php
declare(strict_types=1);

final class PlatformKnowledgeRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array<string, int> */
    public function stats(): array
    {
        $stats = [
            'categories' => 0,
            'articles' => 0,
            'published' => 0,
            'drafts' => 0,
        ];

        if (!$this->tableExists('platform_kb_categories')) {
            return $stats;
        }

        $stats['categories'] = (int) $this->db->query(
            'SELECT COUNT(*) FROM platform_kb_categories WHERE is_active = 1'
        )->fetchColumn();

        if (!$this->tableExists('platform_kb_articles')) {
            return $stats;
        }

        $row = $this->db->query(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN is_published = 1 THEN 1 ELSE 0 END) AS published
             FROM platform_kb_articles'
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $stats['articles'] = (int) ($row['total'] ?? 0);
        $stats['published'] = (int) ($row['published'] ?? 0);
        $stats['drafts'] = max(0, $stats['articles'] - $stats['published']);

        return $stats;
    }

    /** @return array<string, mixed> */
    public function catalog(): array
    {
        return [
            'stats' => $this->stats(),
            'categories' => $this->listCategories(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function listCategories(): array
    {
        if (!$this->tableExists('platform_kb_categories')) {
            return [];
        }

        $rows = $this->db->query(
            'SELECT c.id, c.slug, c.icon, c.name_en, c.name_fr, c.sort_order,
                    (SELECT COUNT(*) FROM platform_kb_articles a WHERE a.category_id = c.id) AS article_count
             FROM platform_kb_categories c
             WHERE c.is_active = 1
             ORDER BY c.sort_order ASC, c.name_en ASC'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn (array $r) => [
            'id' => (int) ($r['id'] ?? 0),
            'slug' => (string) ($r['slug'] ?? ''),
            'icon' => (string) ($r['icon'] ?? 'menu_book'),
            'name_en' => (string) ($r['name_en'] ?? ''),
            'name_fr' => (string) ($r['name_fr'] ?? ''),
            'article_count' => (int) ($r['article_count'] ?? 0),
        ], $rows);
    }

    /** @return array<int, array<string, mixed>> */
    public function listArticles(
        int $limit = 100,
        int $offset = 0,
        ?string $search = null,
        ?string $categorySlug = null,
        ?string $audience = null,
        ?string $published = null
    ): array {
        if (!$this->tableExists('platform_kb_articles')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(a.slug LIKE ? OR a.title_en LIKE ? OR a.title_fr LIKE ? OR a.summary_en LIKE ? OR a.summary_fr LIKE ?)';
            $like = '%' . $search . '%';
            for ($i = 0; $i < 5; $i++) {
                $params[] = $like;
            }
        }
        if ($categorySlug !== null && $categorySlug !== '') {
            $where[] = 'c.slug = ?';
            $params[] = $categorySlug;
        }
        if ($audience !== null && $audience !== '') {
            $where[] = 'a.audience = ?';
            $params[] = $audience;
        }
        if ($published === 'yes') {
            $where[] = 'a.is_published = 1';
        } elseif ($published === 'no') {
            $where[] = 'a.is_published = 0';
        }

        $sql = 'SELECT a.id, a.slug, a.article_type, a.title_en, a.title_fr,
                       a.summary_en, a.summary_fr, a.audience, a.is_published,
                       a.sort_order, a.created_at, a.updated_at,
                       c.slug AS category_slug, c.icon AS category_icon,
                       c.name_en AS category_name_en, c.name_fr AS category_name_fr
                FROM platform_kb_articles a
                INNER JOIN platform_kb_categories c ON c.id = a.category_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY a.sort_order ASC, a.updated_at DESC
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

    public function countArticles(
        ?string $search = null,
        ?string $categorySlug = null,
        ?string $audience = null,
        ?string $published = null
    ): int {
        if (!$this->tableExists('platform_kb_articles')) {
            return 0;
        }

        $where = ['1=1'];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(a.slug LIKE ? OR a.title_en LIKE ? OR a.title_fr LIKE ? OR a.summary_en LIKE ? OR a.summary_fr LIKE ?)';
            $like = '%' . $search . '%';
            for ($i = 0; $i < 5; $i++) {
                $params[] = $like;
            }
        }
        if ($categorySlug !== null && $categorySlug !== '') {
            $where[] = 'c.slug = ?';
            $params[] = $categorySlug;
        }
        if ($audience !== null && $audience !== '') {
            $where[] = 'a.audience = ?';
            $params[] = $audience;
        }
        if ($published === 'yes') {
            $where[] = 'a.is_published = 1';
        } elseif ($published === 'no') {
            $where[] = 'a.is_published = 0';
        }

        $sql = 'SELECT COUNT(*) FROM platform_kb_articles a
                INNER JOIN platform_kb_categories c ON c.id = a.category_id
                WHERE ' . implode(' AND ', $where);

        $stmt = $this->db->prepare($sql);
        foreach ($params as $i => $p) {
            $stmt->bindValue($i + 1, $p);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function findArticle(int $id): ?array
    {
        if (!$this->tableExists('platform_kb_articles') || $id <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT a.*, c.slug AS category_slug, c.icon AS category_icon,
                    c.name_en AS category_name_en, c.name_fr AS category_name_fr
             FROM platform_kb_articles a
             INNER JOIN platform_kb_categories c ON c.id = a.category_id
             WHERE a.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @param array<string, mixed> $data */
    public function createArticle(array $data, int $platformUserId): int
    {
        if (!$this->tableExists('platform_kb_articles')) {
            throw new RuntimeException('Knowledge base not available');
        }

        $categoryId = (int) ($data['category_id'] ?? 0);
        $titleEn = trim((string) ($data['title_en'] ?? ''));
        $bodyEn = trim((string) ($data['body_en'] ?? ''));

        if ($categoryId <= 0 || $titleEn === '' || $bodyEn === '') {
            throw new InvalidArgumentException('Category, English title and body are required');
        }

        $slug = $this->normalizeSlug((string) ($data['slug'] ?? $titleEn));
        $slug = $this->uniqueSlug($slug);

        $stmt = $this->db->prepare(
            'INSERT INTO platform_kb_articles
             (category_id, slug, article_type, title_en, title_fr, summary_en, summary_fr,
              body_en, body_fr, audience, sort_order, is_published, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $categoryId,
            $slug,
            $this->normalizeType((string) ($data['article_type'] ?? 'article')),
            $titleEn,
            trim((string) ($data['title_fr'] ?? $titleEn)),
            trim((string) ($data['summary_en'] ?? '')) ?: null,
            trim((string) ($data['summary_fr'] ?? '')) ?: null,
            $bodyEn,
            trim((string) ($data['body_fr'] ?? $bodyEn)),
            $this->normalizeAudience((string) ($data['audience'] ?? 'tenant')),
            max(0, (int) ($data['sort_order'] ?? 0)),
            !empty($data['is_published']) ? 1 : 0,
            $platformUserId > 0 ? $platformUserId : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function updateArticle(int $id, array $data): bool
    {
        if ($this->findArticle($id) === null) {
            return false;
        }

        $fields = [];
        $params = [];

        $map = [
            'category_id' => 'category_id',
            'article_type' => 'article_type',
            'title_en' => 'title_en',
            'title_fr' => 'title_fr',
            'summary_en' => 'summary_en',
            'summary_fr' => 'summary_fr',
            'body_en' => 'body_en',
            'body_fr' => 'body_fr',
            'audience' => 'audience',
            'sort_order' => 'sort_order',
            'is_published' => 'is_published',
        ];

        foreach ($map as $key => $col) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $val = $data[$key];
            if ($key === 'article_type') {
                $val = $this->normalizeType((string) $val);
            } elseif ($key === 'audience') {
                $val = $this->normalizeAudience((string) $val);
            } elseif ($key === 'is_published') {
                $val = !empty($val) ? 1 : 0;
            } elseif ($key === 'category_id' || $key === 'sort_order') {
                $val = (int) $val;
            } else {
                $val = trim((string) $val);
            }
            $fields[] = $col . ' = ?';
            $params[] = $val;
        }

        if (isset($data['slug'])) {
            $fields[] = 'slug = ?';
            $params[] = $this->uniqueSlug($this->normalizeSlug((string) $data['slug']), $id);
        }

        if (!$fields) {
            return false;
        }

        $params[] = $id;
        $stmt = $this->db->prepare(
            'UPDATE platform_kb_articles SET ' . implode(', ', $fields) . ' WHERE id = ?'
        );
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function setPublished(int $id, bool $published): bool
    {
        if (!$this->tableExists('platform_kb_articles')) {
            return false;
        }

        $stmt = $this->db->prepare('UPDATE platform_kb_articles SET is_published = ? WHERE id = ?');
        $stmt->execute([$published ? 1 : 0, $id]);

        return $stmt->rowCount() > 0;
    }

    private function uniqueSlug(string $slug, ?int $excludeId = null): string
    {
        $base = $slug !== '' ? $slug : 'article';
        $candidate = $base;
        $n = 1;

        while ($this->slugExists($candidate, $excludeId)) {
            $n++;
            $candidate = $base . '-' . $n;
        }

        return $candidate;
    }

    private function slugExists(string $slug, ?int $excludeId): bool
    {
        $sql = 'SELECT 1 FROM platform_kb_articles WHERE slug = ?';
        $params = [$slug];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    private function normalizeSlug(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        return trim($slug, '-') ?: 'article';
    }

    private function normalizeType(string $type): string
    {
        $allowed = ['article', 'guide', 'faq'];
        return in_array($type, $allowed, true) ? $type : 'article';
    }

    private function normalizeAudience(string $audience): string
    {
        $allowed = ['tenant', 'support', 'public'];
        return in_array($audience, $allowed, true) ? $audience : 'tenant';
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
