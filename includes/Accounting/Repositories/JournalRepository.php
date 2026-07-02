<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../AccountingSchema.php';

class JournalRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function nextEntryNo(int $storeId): string
    {
        $prefix = 'JE-' . $storeId . '-' . date('Ymd') . '-';
        $stmt = $this->db->prepare(
            "SELECT entry_no FROM acc_journal_entries WHERE entry_no LIKE ? ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$prefix . '%']);
        $last = $stmt->fetchColumn();
        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', (string) $last, $m)) {
            $seq = (int) $m[1] + 1;
        }
        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function createEntry(array $entry, array $lines): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO acc_journal_entries (store_id, entry_no, entry_date, reference_type, reference_id, description, status, created_by, posted_at, sync_status, local_uuid)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)'
        );
        $stmt->execute([
            (int) $entry['store_id'],
            $entry['entry_no'],
            $entry['entry_date'] ?? date('Y-m-d'),
            $entry['reference_type'] ?? null,
            $entry['reference_id'] ?? null,
            $entry['description'],
            $entry['status'] ?? 'posted',
            $entry['created_by'] ?? null,
            $entry['sync_status'] ?? 'synced',
            $entry['local_uuid'] ?? null,
        ]);
        $entryId = (int) $this->db->lastInsertId();
        $lineStmt = $this->db->prepare(
            'INSERT INTO acc_journal_lines (journal_entry_id, account_id, debit, credit, memo, line_order) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $order = 0;
        foreach ($lines as $line) {
            $lineStmt->execute([
                $entryId,
                (int) $line['account_id'],
                round((float) ($line['debit'] ?? 0), 2),
                round((float) ($line['credit'] ?? 0), 2),
                $line['memo'] ?? null,
                $order++,
            ]);
        }
        return $entryId;
    }

    public function list(?int $storeId, array $filters = [], int $limit = 100): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return [];
        }
        $sql = 'SELECT je.*, u.name AS created_by_name
                FROM acc_journal_entries je
                LEFT JOIN users u ON u.id = je.created_by
                WHERE 1=1';
        $params = [];
        $this->applyJournalFilters($sql, $params, $storeId, $filters);
        $sql .= ' ORDER BY je.entry_date DESC, je.id DESC LIMIT ' . (int) $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$entries) {
            return [];
        }
        $ids = array_column($entries, 'id');
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $lineStmt = $this->db->prepare(
            "SELECT jl.*, a.code AS account_code, a.name AS account_name
             FROM acc_journal_lines jl
             INNER JOIN acc_accounts a ON a.id = jl.account_id
             WHERE jl.journal_entry_id IN ($ph)
             ORDER BY jl.journal_entry_id, jl.line_order"
        );
        $lineStmt->execute($ids);
        $linesByEntry = [];
        foreach ($lineStmt->fetchAll(PDO::FETCH_ASSOC) as $line) {
            $linesByEntry[(int) $line['journal_entry_id']][] = $line;
        }
        foreach ($entries as &$e) {
            $lines = $linesByEntry[(int) $e['id']] ?? [];
            $e['lines'] = $lines;
            $e['total_debit'] = round(array_sum(array_column($lines, 'debit')), 2);
            $e['total_credit'] = round(array_sum(array_column($lines, 'credit')), 2);
        }
        return $entries;
    }

    public function stats(?int $storeId, array $filters = []): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return [
                'total_count' => 0, 'total_volume' => 0,
                'posted_count' => 0, 'manual_count' => 0, 'auto_count' => 0,
            ];
        }
        $where = '1=1';
        $params = [];
        if ($storeId !== null) {
            $where .= ' AND je.store_id = ?';
            $params[] = $storeId;
        }
        if (!empty($filters['from'])) {
            $where .= ' AND je.entry_date >= ?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where .= ' AND je.entry_date <= ?';
            $params[] = $filters['to'];
        }
        $sql = "SELECT
                    COUNT(DISTINCT je.id) AS total_count,
                    COALESCE(SUM(jl.debit), 0) AS total_volume,
                    COUNT(DISTINCT CASE WHEN je.status = 'posted' THEN je.id END) AS posted_count,
                    COUNT(DISTINCT CASE WHEN je.reference_type IS NULL OR je.reference_type = 'manual' THEN je.id END) AS manual_count,
                    COUNT(DISTINCT CASE WHEN je.reference_type IS NOT NULL AND je.reference_type != 'manual' THEN je.id END) AS auto_count
                FROM acc_journal_entries je
                LEFT JOIN acc_journal_lines jl ON jl.journal_entry_id = je.id
                WHERE {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total_count' => (int) ($row['total_count'] ?? 0),
            'total_volume' => round((float) ($row['total_volume'] ?? 0), 2),
            'posted_count' => (int) ($row['posted_count'] ?? 0),
            'manual_count' => (int) ($row['manual_count'] ?? 0),
            'auto_count' => (int) ($row['auto_count'] ?? 0),
        ];
    }

    public function referenceTypes(?int $storeId): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return [];
        }
        $sql = 'SELECT DISTINCT reference_type FROM acc_journal_entries WHERE 1=1';
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND store_id = ?';
            $params[] = $storeId;
        }
        $sql .= ' ORDER BY reference_type';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $types = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $type) {
            $types[] = $type === null || $type === '' ? 'manual' : (string) $type;
        }
        return array_values(array_unique($types));
    }

    private function applyJournalFilters(string &$sql, array &$params, ?int $storeId, array $filters): void
    {
        if ($storeId !== null) {
            $sql .= ' AND je.store_id = ?';
            $params[] = $storeId;
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND je.entry_date >= ?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND je.entry_date <= ?';
            $params[] = $filters['to'];
        }
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $sql .= ' AND je.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['reference_type']) && $filters['reference_type'] !== 'all') {
            if ($filters['reference_type'] === 'manual') {
                $sql .= " AND (je.reference_type IS NULL OR je.reference_type = 'manual')";
            } else {
                $sql .= ' AND je.reference_type = ?';
                $params[] = $filters['reference_type'];
            }
        }
        if (!empty($filters['source'])) {
            if ($filters['source'] === 'manual') {
                $sql .= " AND (je.reference_type IS NULL OR je.reference_type = 'manual')";
            } elseif ($filters['source'] === 'auto') {
                $sql .= " AND je.reference_type IS NOT NULL AND je.reference_type != 'manual'";
            }
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND (je.entry_no LIKE ? OR je.description LIKE ? OR je.reference_type LIKE ? OR u.name LIKE ?)';
            $q = '%' . $filters['q'] . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }
    }

    public function revenueTotal(?int $storeId, ?string $from, ?string $to): float
    {
        return $this->sumByAccountType($storeId, 'revenue', $from, $to);
    }

    public function expenseTotal(?int $storeId, ?string $from, ?string $to): float
    {
        return $this->sumByAccountType($storeId, 'expense', $from, $to);
    }

    private function sumByAccountType(?int $storeId, string $type, ?string $from, ?string $to): float
    {
        if (!AccountingSchema::ready($this->db)) {
            return 0.0;
        }
        $sql = "SELECT COALESCE(SUM(jl.credit - jl.debit), 0) AS total
                FROM acc_journal_lines jl
                INNER JOIN acc_journal_entries je ON je.id = jl.journal_entry_id
                INNER JOIN acc_accounts a ON a.id = jl.account_id
                WHERE je.status = 'posted' AND a.account_type = ? AND a.account_subtype != 'header'";
        $params = [$type];
        if ($storeId !== null) {
            $sql .= ' AND je.store_id = ?';
            $params[] = $storeId;
        }
        if ($from) {
            $sql .= ' AND je.entry_date >= ?';
            $params[] = $from;
        }
        if ($to) {
            $sql .= ' AND je.entry_date <= ?';
            $params[] = $to;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return round((float) $stmt->fetchColumn(), 2);
    }

    public function dailyTrend(?int $storeId, string $type, int $days = 30, ?string $from = null, ?string $to = null): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return [];
        }
        if ($from === null) {
            $from = date('Y-m-d', strtotime('-' . max(1, $days - 1) . ' days'));
        }
        if ($to === null) {
            $to = date('Y-m-d');
        }
        $sql = "SELECT je.entry_date AS day, COALESCE(SUM(
                    CASE WHEN a.account_type = 'revenue' THEN jl.credit - jl.debit
                         WHEN a.account_type = 'expense' THEN jl.debit - jl.credit ELSE 0 END
                ), 0) AS amount
                FROM acc_journal_entries je
                INNER JOIN acc_journal_lines jl ON jl.journal_entry_id = je.id
                INNER JOIN acc_accounts a ON a.id = jl.account_id
                WHERE je.status = 'posted' AND a.account_type = ? AND a.account_subtype != 'header'
                AND je.entry_date >= ? AND je.entry_date <= ?";
        $params = [$type, $from, $to];
        if ($storeId !== null) {
            $sql .= ' AND je.store_id = ?';
            $params[] = $storeId;
        }
        $sql .= ' GROUP BY je.entry_date ORDER BY day';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map(static fn ($r) => [
            'day' => $r['day'],
            'amount' => round((float) $r['amount'], 2),
        ], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function revenuesPage(?int $storeId, array $filters = []): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return ['module_ready' => false];
        }

        $from = $filters['from'] ?? date('Y-m-01');
        $to = $filters['to'] ?? date('Y-m-d');
        $stats = $this->revenueStats($storeId, $filters);
        $periodTotal = (float) ($stats['period_total'] ?? 0);
        $days = max(1, (int) ((strtotime($to) - strtotime($from)) / 86400) + 1);

        return [
            'module_ready' => true,
            'rows' => $this->listRevenueLines($storeId, $filters),
            'stats' => $stats,
            'charts' => [
                'trend' => $this->dailyTrend($storeId, 'revenue', 30, $from, $to),
                'by_account' => $this->revenueByAccount($storeId, $filters),
                'by_source' => $this->revenueBySource($storeId, $filters),
            ],
            'insights' => [
                'line_count' => (int) ($stats['line_count'] ?? 0),
                'avg_daily' => round($periodTotal / $days, 2),
                'auto_pct' => $periodTotal > 0
                    ? round(((float) ($stats['sale_total'] ?? 0)) / $periodTotal * 100, 1)
                    : 0,
                'top_account' => $stats['top_account'] ?? '—',
            ],
            'accounts' => $this->revenueAccounts($storeId, $filters),
        ];
    }

    public function listRevenueLines(?int $storeId, array $filters = [], int $limit = 500): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return [];
        }

        $sql = "SELECT jl.id, jl.journal_entry_id, je.entry_date, je.entry_no, je.reference_type, je.reference_id,
                       je.description, a.id AS account_id, a.code AS account_code, a.name AS account_name,
                       ROUND(jl.credit - jl.debit, 2) AS amount, u.name AS created_by_name, jl.memo
                FROM acc_journal_lines jl
                INNER JOIN acc_journal_entries je ON je.id = jl.journal_entry_id
                INNER JOIN acc_accounts a ON a.id = jl.account_id
                LEFT JOIN users u ON u.id = je.created_by
                WHERE je.status = 'posted'
                  AND a.account_type = 'revenue'
                  AND a.account_subtype != 'header'
                  AND (jl.credit - jl.debit) > 0";
        $params = [];
        $this->applyRevenueFilters($sql, $params, $storeId, $filters);
        $sql .= ' ORDER BY je.entry_date DESC, je.id DESC, jl.line_order LIMIT ' . (int) $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['amount'] = round((float) ($row['amount'] ?? 0), 2);
            $row['reference_type'] = $row['reference_type'] === null || $row['reference_type'] === ''
                ? 'manual'
                : (string) $row['reference_type'];
        }
        return $rows;
    }

    public function revenueStats(?int $storeId, array $filters = []): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return [
                'period_total' => 0, 'today_total' => 0, 'sale_total' => 0, 'manual_total' => 0,
                'line_count' => 0, 'top_account' => '—',
            ];
        }

        $from = $filters['from'] ?? date('Y-m-01');
        $to = $filters['to'] ?? date('Y-m-d');
        $today = date('Y-m-d');

        return [
            'period_total' => $this->sumRevenueAmount($storeId, $from, $to, ''),
            'today_total' => $this->sumRevenueAmount($storeId, $today, $today, ''),
            'sale_total' => $this->sumRevenueAmount($storeId, $from, $to, 'sale'),
            'manual_total' => $this->sumRevenueAmount($storeId, $from, $to, 'manual'),
            'line_count' => $this->countRevenueLines($storeId, $filters),
            'top_account' => $this->topRevenueAccountLabel($storeId, $from, $to),
        ];
    }

    private function sumRevenueAmount(?int $storeId, string $from, string $to, string $source = ''): float
    {
        $sql = "SELECT COALESCE(SUM(jl.credit - jl.debit), 0) AS total
                FROM acc_journal_lines jl
                INNER JOIN acc_journal_entries je ON je.id = jl.journal_entry_id
                INNER JOIN acc_accounts a ON a.id = jl.account_id
                WHERE je.status = 'posted'
                  AND a.account_type = 'revenue'
                  AND a.account_subtype != 'header'
                  AND (jl.credit - jl.debit) > 0
                  AND je.entry_date >= ? AND je.entry_date <= ?";
        $params = [$from, $to];
        if ($storeId !== null) {
            $sql .= ' AND je.store_id = ?';
            $params[] = $storeId;
        }
        if ($source === 'sale') {
            $sql .= " AND je.reference_type = 'sale'";
        } elseif ($source === 'manual') {
            $sql .= " AND (je.reference_type IS NULL OR je.reference_type = 'manual')";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return round((float) $stmt->fetchColumn(), 2);
    }

    private function countRevenueLines(?int $storeId, array $filters): int
    {
        $sql = "SELECT COUNT(*) FROM acc_journal_lines jl
                INNER JOIN acc_journal_entries je ON je.id = jl.journal_entry_id
                INNER JOIN acc_accounts a ON a.id = jl.account_id
                LEFT JOIN users u ON u.id = je.created_by
                WHERE je.status = 'posted'
                  AND a.account_type = 'revenue'
                  AND a.account_subtype != 'header'
                  AND (jl.credit - jl.debit) > 0";
        $params = [];
        $this->applyRevenueFilters($sql, $params, $storeId, $filters);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function topRevenueAccountLabel(?int $storeId, string $from, string $to): string
    {
        $sql = "SELECT a.code, a.name, COALESCE(SUM(jl.credit - jl.debit), 0) AS amount
                FROM acc_journal_lines jl
                INNER JOIN acc_journal_entries je ON je.id = jl.journal_entry_id
                INNER JOIN acc_accounts a ON a.id = jl.account_id
                WHERE je.status = 'posted'
                  AND a.account_type = 'revenue'
                  AND a.account_subtype != 'header'
                  AND (jl.credit - jl.debit) > 0
                  AND je.entry_date >= ? AND je.entry_date <= ?";
        $params = [$from, $to];
        if ($storeId !== null) {
            $sql .= ' AND je.store_id = ?';
            $params[] = $storeId;
        }
        $sql .= ' GROUP BY a.id, a.code, a.name ORDER BY amount DESC LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || (float) ($row['amount'] ?? 0) <= 0) {
            return '—';
        }
        return trim((string) $row['code'] . ' — ' . (string) $row['name']);
    }

    private function revenueByAccount(?int $storeId, array $filters): array
    {
        $sql = "SELECT a.code AS account_code, a.name AS account_name,
                       COALESCE(SUM(jl.credit - jl.debit), 0) AS amount
                FROM acc_journal_lines jl
                INNER JOIN acc_journal_entries je ON je.id = jl.journal_entry_id
                INNER JOIN acc_accounts a ON a.id = jl.account_id
                LEFT JOIN users u ON u.id = je.created_by
                WHERE je.status = 'posted'
                  AND a.account_type = 'revenue'
                  AND a.account_subtype != 'header'
                  AND (jl.credit - jl.debit) > 0";
        $params = [];
        $this->applyRevenueFilters($sql, $params, $storeId, $filters);
        $sql .= ' GROUP BY a.id, a.code, a.name ORDER BY amount DESC LIMIT 10';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map(static fn ($r) => [
            'account_code' => $r['account_code'],
            'account_name' => $r['account_name'],
            'amount' => round((float) $r['amount'], 2),
        ], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private function revenueBySource(?int $storeId, array $filters): array
    {
        $sql = "SELECT COALESCE(NULLIF(je.reference_type, ''), 'manual') AS source_key,
                       COALESCE(SUM(jl.credit - jl.debit), 0) AS amount,
                       COUNT(*) AS count
                FROM acc_journal_lines jl
                INNER JOIN acc_journal_entries je ON je.id = jl.journal_entry_id
                INNER JOIN acc_accounts a ON a.id = jl.account_id
                LEFT JOIN users u ON u.id = je.created_by
                WHERE je.status = 'posted'
                  AND a.account_type = 'revenue'
                  AND a.account_subtype != 'header'
                  AND (jl.credit - jl.debit) > 0";
        $params = [];
        $chartFilters = $filters;
        unset($chartFilters['source'], $chartFilters['account_id']);
        $this->applyRevenueFilters($sql, $params, $storeId, $chartFilters);
        $sql .= ' GROUP BY source_key ORDER BY amount DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map(static fn ($r) => [
            'source' => $r['source_key'],
            'amount' => round((float) $r['amount'], 2),
            'count' => (int) $r['count'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private function revenueAccounts(?int $storeId, array $filters): array
    {
        $sql = "SELECT DISTINCT a.id, a.code, a.name
                FROM acc_journal_lines jl
                INNER JOIN acc_journal_entries je ON je.id = jl.journal_entry_id
                INNER JOIN acc_accounts a ON a.id = jl.account_id
                LEFT JOIN users u ON u.id = je.created_by
                WHERE je.status = 'posted'
                  AND a.account_type = 'revenue'
                  AND a.account_subtype != 'header'
                  AND (jl.credit - jl.debit) > 0";
        $params = [];
        $acctFilters = $filters;
        unset($acctFilters['account_id']);
        $this->applyRevenueFilters($sql, $params, $storeId, $acctFilters);
        $sql .= ' ORDER BY a.code';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function applyRevenueFilters(string &$sql, array &$params, ?int $storeId, array $filters): void
    {
        if ($storeId !== null) {
            $sql .= ' AND je.store_id = ?';
            $params[] = $storeId;
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND je.entry_date >= ?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND je.entry_date <= ?';
            $params[] = $filters['to'];
        }
        if (!empty($filters['source']) && $filters['source'] !== 'all') {
            if ($filters['source'] === 'sale') {
                $sql .= " AND je.reference_type = 'sale'";
            } elseif ($filters['source'] === 'manual') {
                $sql .= " AND (je.reference_type IS NULL OR je.reference_type = 'manual')";
            }
        }
        if (!empty($filters['account_id']) && $filters['account_id'] !== 'all') {
            $sql .= ' AND a.id = ?';
            $params[] = (int) $filters['account_id'];
        }
        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (je.entry_no LIKE ? OR je.description LIKE ? OR a.code LIKE ? OR a.name LIKE ? OR u.name LIKE ? OR jl.memo LIKE ?)';
            $q = '%' . $search . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }
    }
}
