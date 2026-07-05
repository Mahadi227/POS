<?php
declare(strict_types=1);

final class PlatformSupportRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array<string, int> */
    public function ticketStats(): array
    {
        $stats = [
            'open' => 0,
            'in_progress' => 0,
            'waiting' => 0,
            'resolved_month' => 0,
            'attention' => 0,
            'total' => 0,
        ];

        if (!$this->tableExists('platform_support_tickets')) {
            $stats['attention'] = count($this->attentionQueue());
            return $stats;
        }

        $row = $this->db->query(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_cnt,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_cnt,
                    SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END) AS waiting_cnt,
                    SUM(CASE WHEN status IN ('resolved','closed')
                              AND resolved_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN 1 ELSE 0 END) AS resolved_month
             FROM platform_support_tickets"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $stats['total'] = (int) ($row['total'] ?? 0);
        $stats['open'] = (int) ($row['open_cnt'] ?? 0);
        $stats['in_progress'] = (int) ($row['in_progress_cnt'] ?? 0);
        $stats['waiting'] = (int) ($row['waiting_cnt'] ?? 0);
        $stats['resolved_month'] = (int) ($row['resolved_month'] ?? 0);
        $stats['attention'] = count($this->attentionQueue());

        return $stats;
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        return [
            'stats' => $this->ticketStats(),
            'attention_queue' => $this->attentionQueue(),
            'recent_tickets' => $this->listTickets(15, 0),
            'recent_actions' => $this->recentSupportActions(12),
            'agents' => $this->listAgents(),
            'tenants' => $this->tenantOptions(100),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function listTickets(
        int $limit = 50,
        int $offset = 0,
        ?string $search = null,
        ?string $status = null,
        ?string $priority = null
    ): array {
        if (!$this->tableExists('platform_support_tickets')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(pst.ticket_number LIKE ? OR pst.subject LIKE ? OR t.name LIKE ? OR t.slug LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($status !== null && $status !== '') {
            $where[] = 'pst.status = ?';
            $params[] = $status;
        }
        if ($priority !== null && $priority !== '') {
            $where[] = 'pst.priority = ?';
            $params[] = $priority;
        }

        $tenantJoin = $this->tableExists('tenants')
            ? 'LEFT JOIN tenants t ON t.id = pst.tenant_id'
            : 'LEFT JOIN (SELECT NULL AS id, NULL AS name, NULL AS slug) t ON 1=0';
        $userJoin = $this->tableExists('platform_users')
            ? 'LEFT JOIN platform_users pu ON pu.id = pst.assigned_to'
            : 'LEFT JOIN (SELECT NULL AS id, NULL AS name) pu ON 1=0';

        $sql = 'SELECT pst.id, pst.ticket_number, pst.tenant_id, pst.subject, pst.status,
                       pst.priority, pst.category, pst.assigned_to, pst.created_at, pst.updated_at,
                       t.name AS tenant_name, t.slug AS tenant_slug,
                       pu.name AS assignee_name
                FROM platform_support_tickets pst
                ' . $tenantJoin . '
                ' . $userJoin . '
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY FIELD(pst.priority, "urgent","high","normal","low"), pst.updated_at DESC
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

    public function countTickets(?string $search = null, ?string $status = null, ?string $priority = null): int
    {
        if (!$this->tableExists('platform_support_tickets')) {
            return 0;
        }

        $where = ['1=1'];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(pst.ticket_number LIKE ? OR pst.subject LIKE ? OR t.name LIKE ? OR t.slug LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($status !== null && $status !== '') {
            $where[] = 'pst.status = ?';
            $params[] = $status;
        }
        if ($priority !== null && $priority !== '') {
            $where[] = 'pst.priority = ?';
            $params[] = $priority;
        }

        $tenantJoin = $this->tableExists('tenants')
            ? 'LEFT JOIN tenants t ON t.id = pst.tenant_id'
            : 'LEFT JOIN (SELECT NULL AS id, NULL AS name, NULL AS slug) t ON 1=0';

        $sql = 'SELECT COUNT(*) FROM platform_support_tickets pst ' . $tenantJoin . '
                WHERE ' . implode(' AND ', $where);

        $stmt = $this->db->prepare($sql);
        foreach ($params as $i => $p) {
            $stmt->bindValue($i + 1, $p);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function findTicket(int $ticketId): ?array
    {
        if (!$this->tableExists('platform_support_tickets') || $ticketId <= 0) {
            return null;
        }

        $tenantJoin = $this->tableExists('tenants')
            ? 'LEFT JOIN tenants t ON t.id = pst.tenant_id'
            : 'LEFT JOIN (SELECT NULL AS id, NULL AS name, NULL AS slug) t ON 1=0';
        $assigneeJoin = $this->tableExists('platform_users')
            ? 'LEFT JOIN platform_users pu ON pu.id = pst.assigned_to'
            : 'LEFT JOIN (SELECT NULL AS id, NULL AS name) pu ON 1=0';
        $creatorJoin = $this->tableExists('platform_users')
            ? 'LEFT JOIN platform_users pc ON pc.id = pst.created_by'
            : 'LEFT JOIN (SELECT NULL AS id, NULL AS name) pc ON 1=0';

        $stmt = $this->db->prepare(
            'SELECT pst.id, pst.ticket_number, pst.tenant_id, pst.subject, pst.description,
                    pst.status, pst.priority, pst.category, pst.assigned_to, pst.created_by,
                    pst.created_at, pst.updated_at, pst.resolved_at,
                    t.name AS tenant_name, t.slug AS tenant_slug,
                    pu.name AS assignee_name, pc.name AS creator_name
             FROM platform_support_tickets pst
             ' . $tenantJoin . '
             ' . $assigneeJoin . '
             ' . $creatorJoin . '
             WHERE pst.id = ? LIMIT 1'
        );
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) {
            return null;
        }

        $ticket['replies'] = $this->listReplies($ticketId);
        return $ticket;
    }

    /** @return array<string, mixed> */
    public function ticketsPage(): array
    {
        return [
            'stats' => $this->ticketStats(),
            'agents' => $this->listAgents(),
            'tenants' => $this->tenantOptions(200),
        ];
    }

    public function addReply(int $ticketId, string $message, int $platformUserId, bool $internal = false): int
    {
        if (!$this->tableExists('platform_support_replies')) {
            throw new RuntimeException('Ticket replies not available');
        }

        $message = trim($message);
        if ($message === '') {
            throw new InvalidArgumentException('Message is required');
        }

        if ($this->findTicket($ticketId) === null) {
            throw new RuntimeException('Ticket not found');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO platform_support_replies (ticket_id, platform_user_id, message, is_internal)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $ticketId,
            $platformUserId > 0 ? $platformUserId : null,
            $message,
            $internal ? 1 : 0,
        ]);

        $this->db->prepare(
            'UPDATE platform_support_tickets SET updated_at = NOW() WHERE id = ?'
        )->execute([$ticketId]);

        return (int) $this->db->lastInsertId();
    }

    /** @return array<int, array<string, mixed>> */
    private function listReplies(int $ticketId): array
    {
        if (!$this->tableExists('platform_support_replies')) {
            return [];
        }

        $userJoin = $this->tableExists('platform_users')
            ? 'LEFT JOIN platform_users pu ON pu.id = psr.platform_user_id'
            : 'LEFT JOIN (SELECT NULL AS id, NULL AS name) pu ON 1=0';

        $stmt = $this->db->prepare(
            'SELECT psr.id, psr.message, psr.is_internal, psr.created_at,
                    pu.name AS author_name
             FROM platform_support_replies psr
             ' . $userJoin . '
             WHERE psr.ticket_id = ?
             ORDER BY psr.id ASC'
        );
        $stmt->execute([$ticketId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string, mixed> $data */
    public function createTicket(array $data, int $platformUserId): int
    {
        if (!$this->tableExists('platform_support_tickets')) {
            throw new RuntimeException('Support tickets not available');
        }

        $subject = trim((string) ($data['subject'] ?? ''));
        if ($subject === '') {
            throw new InvalidArgumentException('Subject is required');
        }

        $tenantId = isset($data['tenant_id']) && (int) $data['tenant_id'] > 0
            ? (int) $data['tenant_id'] : null;
        $description = trim((string) ($data['description'] ?? ''));
        $priority = $this->normalizePriority((string) ($data['priority'] ?? 'normal'));
        $category = $this->normalizeCategory((string) ($data['category'] ?? 'other'));
        $assignedTo = isset($data['assigned_to']) && (int) $data['assigned_to'] > 0
            ? (int) $data['assigned_to'] : null;

        $number = $this->nextTicketNumber();

        $stmt = $this->db->prepare(
            'INSERT INTO platform_support_tickets
             (ticket_number, tenant_id, subject, description, status, priority, category, assigned_to, created_by)
             VALUES (?, ?, ?, ?, "open", ?, ?, ?, ?)'
        );
        $stmt->execute([
            $number,
            $tenantId,
            $subject,
            $description !== '' ? $description : null,
            $priority,
            $category,
            $assignedTo,
            $platformUserId > 0 ? $platformUserId : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateStatus(int $ticketId, string $status): bool
    {
        if (!$this->tableExists('platform_support_tickets')) {
            return false;
        }

        $allowed = ['open', 'in_progress', 'waiting', 'resolved', 'closed'];
        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('Invalid status');
        }

        $resolvedAt = in_array($status, ['resolved', 'closed'], true) ? date('Y-m-d H:i:s') : null;
        $stmt = $this->db->prepare(
            'UPDATE platform_support_tickets SET status = ?, resolved_at = COALESCE(?, resolved_at) WHERE id = ?'
        );
        $stmt->execute([$status, $resolvedAt, $ticketId]);

        return $stmt->rowCount() > 0;
    }

    public function assignTicket(int $ticketId, ?int $platformUserId): bool
    {
        if (!$this->tableExists('platform_support_tickets')) {
            return false;
        }

        $stmt = $this->db->prepare(
            'UPDATE platform_support_tickets SET assigned_to = ?, status = IF(status = "open", "in_progress", status) WHERE id = ?'
        );
        $stmt->execute([$platformUserId, $ticketId]);

        return $stmt->rowCount() > 0;
    }

    /** @return array<int, array<string, mixed>> */
    private function attentionQueue(): array
    {
        $items = [];

        if ($this->tableExists('tenants')) {
            $suspended = $this->db->query(
                "SELECT id, name, slug, status, 'suspended' AS reason, NULL AS detail
                 FROM tenants WHERE deleted_at IS NULL AND status = 'suspended'
                 ORDER BY updated_at DESC LIMIT 10"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($suspended as $row) {
                $items[] = $row;
            }

            if ($this->hasColumn('tenants', 'trial_ends_at')) {
                $trials = $this->db->query(
                    "SELECT id, name, slug, status, 'trial_ending' AS reason,
                            DATE_FORMAT(trial_ends_at, '%Y-%m-%d') AS detail
                     FROM tenants
                     WHERE deleted_at IS NULL
                       AND status = 'trial'
                       AND trial_ends_at IS NOT NULL
                       AND trial_ends_at <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                     ORDER BY trial_ends_at ASC LIMIT 10"
                )->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($trials as $row) {
                    $items[] = $row;
                }
            }
        }

        if ($this->tableExists('tenant_subscriptions') && $this->tableExists('tenants')) {
            $pastDue = $this->db->query(
                "SELECT t.id, t.name, t.slug, t.status, 'past_due' AS reason, sp.name AS detail
                 FROM tenants t
                 INNER JOIN (
                     SELECT tenant_id, MAX(id) AS max_id FROM tenant_subscriptions GROUP BY tenant_id
                 ) latest ON latest.tenant_id = t.id
                 INNER JOIN tenant_subscriptions ts ON ts.id = latest.max_id
                 LEFT JOIN subscription_plans sp ON sp.id = ts.plan_id
                 WHERE t.deleted_at IS NULL AND ts.status = 'past_due'
                 ORDER BY ts.id DESC LIMIT 10"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($pastDue as $row) {
                $items[] = $row;
            }
        }

        return array_slice($items, 0, 15);
    }

    /** @return array<int, array<string, mixed>> */
    private function recentSupportActions(int $limit): array
    {
        if (!$this->tableExists('platform_audit_log')) {
            return [];
        }

        $stmt = $this->db->prepare(
            "SELECT pal.id, pal.action, pal.tenant_id, pal.created_at,
                    pu.name AS platform_user_name,
                    t.name AS tenant_name, t.slug AS tenant_slug
             FROM platform_audit_log pal
             LEFT JOIN platform_users pu ON pu.id = pal.platform_user_id
             LEFT JOIN tenants t ON t.id = pal.tenant_id
             WHERE pal.action IN (
                 'tenant.impersonate_start', 'tenant.impersonate_end',
                 'tenant.suspend', 'tenant.restore', 'tenant.extend_trial', 'tenant.change_plan'
             )
             ORDER BY pal.id DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, array{id: int, name: string}> */
    private function listAgents(): array
    {
        if (!$this->tableExists('platform_users')) {
            return [];
        }

        $rows = $this->db->query(
            "SELECT id, name FROM platform_users
             WHERE is_active = 1 AND role IN ('platform_admin','support')
             ORDER BY name ASC"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn (array $r) => [
            'id' => (int) ($r['id'] ?? 0),
            'name' => (string) ($r['name'] ?? ''),
        ], $rows);
    }

    /** @return array<int, array{id: int, name: string, slug: string}> */
    private function tenantOptions(int $limit): array
    {
        if (!$this->tableExists('tenants')) {
            return [];
        }

        $rows = $this->db->query(
            'SELECT id, name, slug FROM tenants WHERE deleted_at IS NULL ORDER BY name ASC LIMIT ' . (int) $limit
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn (array $r) => [
            'id' => (int) ($r['id'] ?? 0),
            'name' => (string) ($r['name'] ?? ''),
            'slug' => (string) ($r['slug'] ?? ''),
        ], $rows);
    }

    private function nextTicketNumber(): string
    {
        $prefix = 'SUP-' . date('Y') . '-';
        $stmt = $this->db->prepare(
            'SELECT ticket_number FROM platform_support_tickets
             WHERE ticket_number LIKE ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$prefix . '%']);
        $last = (string) ($stmt->fetchColumn() ?: '');

        $seq = 1;
        if ($last !== '' && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    private function normalizePriority(string $priority): string
    {
        $allowed = ['low', 'normal', 'high', 'urgent'];
        return in_array($priority, $allowed, true) ? $priority : 'normal';
    }

    private function normalizeCategory(string $category): string
    {
        $allowed = ['billing', 'technical', 'onboarding', 'account', 'other'];
        return in_array($category, $allowed, true) ? $category : 'other';
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }

    private function hasColumn(string $table, string $column): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }
}
