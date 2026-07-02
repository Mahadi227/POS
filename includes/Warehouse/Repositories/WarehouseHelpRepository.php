<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../WarehouseHelpSchema.php';
require_once __DIR__ . '/../WarehouseHelpSeeder.php';

class WarehouseHelpRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        WarehouseHelpSchema::ensure($this->db);
        WarehouseHelpSeeder::seed($this->db);
    }

    public function listCategories(?string $roleSlug = null): array
    {
        $stmt = $this->db->query(
            'SELECT * FROM help_categories WHERE is_active = 1 ORDER BY sort_order ASC, name_en ASC'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_values(array_filter($rows, fn ($r) => $this->roleAllowed($r['roles'] ?? null, $roleSlug)));
    }

    public function search(string $query, string $lang, ?string $roleSlug, int $limit = 30): array
    {
        $q = trim($query);
        if ($q === '') {
            return [];
        }
        $like = '%' . $q . '%';
        $titleCol = $lang === 'fr' ? 'title_fr' : 'title_en';
        $summaryCol = $lang === 'fr' ? 'summary_fr' : 'summary_en';
        $bodyCol = $lang === 'fr' ? 'body_fr' : 'body_en';
        $qCol = $lang === 'fr' ? 'question_fr' : 'question_en';
        $aCol = $lang === 'fr' ? 'answer_fr' : 'answer_en';

        $articles = [];
        $stmt = $this->db->prepare(
            "SELECT a.id, a.slug, a.article_type, a.{$titleCol} AS title, a.{$summaryCol} AS summary,
                    a.module, a.roles, c.slug AS category_slug, c.icon AS category_icon, 'article' AS result_type
             FROM help_articles a
             INNER JOIN help_categories c ON c.id = a.category_id
             WHERE a.is_published = 1
               AND (a.{$titleCol} LIKE ? OR a.{$summaryCol} LIKE ? OR a.{$bodyCol} LIKE ? OR a.module LIKE ? OR c.slug LIKE ?)
             ORDER BY a.sort_order ASC
             LIMIT {$limit}"
        );
        $stmt->execute([$like, $like, $like, $like, $like]);
        $articles = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if ($this->roleAllowed($row['roles'] ?? null, $roleSlug)) {
                unset($row['roles']);
                $articles[] = $row;
            }
        }

        $faqs = [];
        $fStmt = $this->db->prepare(
            "SELECT f.id, f.{$qCol} AS title, f.{$aCol} AS summary, c.slug AS category_slug, 'faq' AS result_type
             FROM help_faq f
             LEFT JOIN help_categories c ON c.id = f.category_id
             WHERE f.is_published = 1 AND (f.{$qCol} LIKE ? OR f.{$aCol} LIKE ?)
             LIMIT 15"
        );
        $fStmt->execute([$like, $like]);
        $faqs = $fStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_slice(array_merge($articles, $faqs), 0, $limit);
    }

    public function listArticles(?string $categorySlug, ?string $type, string $lang, ?string $roleSlug): array
    {
        $titleCol = $lang === 'fr' ? 'title_fr' : 'title_en';
        $summaryCol = $lang === 'fr' ? 'summary_fr' : 'summary_en';
        $sql = "SELECT a.id, a.slug, a.article_type, a.{$titleCol} AS title, a.{$summaryCol} AS summary,
                       a.module, a.roles, c.slug AS category_slug
                FROM help_articles a
                INNER JOIN help_categories c ON c.id = a.category_id
                WHERE a.is_published = 1";
        $params = [];
        if ($categorySlug) {
            $sql .= ' AND c.slug = ?';
            $params[] = $categorySlug;
        }
        if ($type) {
            $sql .= ' AND a.article_type = ?';
            $params[] = $type;
        }
        $sql .= ' ORDER BY a.sort_order ASC, a.id ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_values(array_filter(
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            fn ($r) => $this->roleAllowed($r['roles'] ?? null, $roleSlug)
        ));
    }

    public function getArticle(string $slug, string $lang): ?array
    {
        $titleCol = $lang === 'fr' ? 'title_fr' : 'title_en';
        $summaryCol = $lang === 'fr' ? 'summary_fr' : 'summary_en';
        $bodyCol = $lang === 'fr' ? 'body_fr' : 'body_en';
        $stmt = $this->db->prepare(
            "SELECT a.*, a.{$titleCol} AS title, a.{$summaryCol} AS summary, a.{$bodyCol} AS body,
                    c.slug AS category_slug, c.icon AS category_icon
             FROM help_articles a
             INNER JOIN help_categories c ON c.id = a.category_id
             WHERE a.slug = ? AND a.is_published = 1 LIMIT 1"
        );
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listFaq(?string $categorySlug, string $lang, ?string $roleSlug): array
    {
        $qCol = $lang === 'fr' ? 'question_fr' : 'question_en';
        $aCol = $lang === 'fr' ? 'answer_fr' : 'answer_en';
        $sql = "SELECT f.id, f.{$qCol} AS question, f.{$aCol} AS answer, f.roles, c.slug AS category_slug
                FROM help_faq f
                LEFT JOIN help_categories c ON c.id = f.category_id
                WHERE f.is_published = 1";
        $params = [];
        if ($categorySlug) {
            $sql .= ' AND c.slug = ?';
            $params[] = $categorySlug;
        }
        $sql .= ' ORDER BY f.sort_order ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_values(array_filter(
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            fn ($r) => $this->roleAllowed($r['roles'] ?? null, $roleSlug)
        ));
    }

    public function listVideos(?string $categorySlug, string $lang, ?string $roleSlug): array
    {
        $titleCol = $lang === 'fr' ? 'title_fr' : 'title_en';
        $descCol = $lang === 'fr' ? 'description_fr' : 'description_en';
        $sql = "SELECT v.id, v.{$titleCol} AS title, v.{$descCol} AS description, v.video_type, v.video_url,
                       v.thumbnail_url, v.duration_seconds, v.roles, c.slug AS category_slug
                FROM help_tutorial_videos v
                LEFT JOIN help_categories c ON c.id = v.category_id
                WHERE v.is_published = 1";
        $params = [];
        if ($categorySlug) {
            $sql .= ' AND c.slug = ?';
            $params[] = $categorySlug;
        }
        $sql .= ' ORDER BY v.sort_order ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_values(array_filter(
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            fn ($r) => $this->roleAllowed($r['roles'] ?? null, $roleSlug)
        ));
    }

    public function listUpdates(string $lang, int $limit = 10): array
    {
        $titleCol = $lang === 'fr' ? 'title_fr' : 'title_en';
        $bodyCol = $lang === 'fr' ? 'body_fr' : 'body_en';
        $stmt = $this->db->prepare(
            "SELECT id, version, {$titleCol} AS title, {$bodyCol} AS body, update_type, published_at
             FROM help_system_updates WHERE is_published = 1 ORDER BY published_at DESC LIMIT {$limit}"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createTicket(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO help_support_tickets
                (ticket_number, user_id, warehouse_id, name, email, role_slug, subject, category, priority,
                 description, attachment_path, ticket_type, problem_type, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['ticket_number'],
            $data['user_id'],
            $data['warehouse_id'],
            $data['name'],
            $data['email'],
            $data['role_slug'],
            $data['subject'],
            $data['category'],
            $data['priority'],
            $data['description'],
            $data['attachment_path'] ?? null,
            $data['ticket_type'],
            $data['problem_type'] ?? null,
            'open',
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function listUserTickets(int $userId, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, ticket_number, subject, category, priority, ticket_type, problem_type, status, created_at, updated_at
             FROM help_support_tickets WHERE user_id = ? ORDER BY created_at DESC LIMIT ' . (int) $limit
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function nextTicketNumber(): string
    {
        $prefix = 'WH-TKT-' . date('Ymd') . '-';
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM help_support_tickets WHERE ticket_number LIKE ?'
        );
        $stmt->execute([$prefix . '%']);
        $seq = (int) $stmt->fetchColumn() + 1;
        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function roleAllowed(?string $rolesJson, ?string $roleSlug): bool
    {
        if ($rolesJson === null || $rolesJson === '') {
            return true;
        }
        $roles = json_decode($rolesJson, true);
        if (!is_array($roles) || !$roles) {
            return true;
        }
        if (!$roleSlug) {
            return true;
        }
        return in_array($roleSlug, $roles, true)
            || in_array($roleSlug, ['super_admin', 'admin'], true);
    }
}
