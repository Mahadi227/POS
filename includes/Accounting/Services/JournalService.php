<?php
declare(strict_types=1);

require_once __DIR__ . '/../Repositories/AccountRepository.php';
require_once __DIR__ . '/../Repositories/JournalRepository.php';
require_once __DIR__ . '/../Repositories/AccountingAuditRepository.php';
require_once __DIR__ . '/../AccountingSchema.php';

class JournalService
{
    private PDO $db;
    private AccountRepository $accounts;
    private JournalRepository $journal;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->accounts = new AccountRepository($this->db);
        $this->journal = new JournalRepository($this->db);
    }

    public function post(int $storeId, array $entry, array $lines, int $userId): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return ['status' => 'error', 'message' => 'Accounting module not installed'];
        }
        $debit = 0.0;
        $credit = 0.0;
        foreach ($lines as $line) {
            $debit += (float) ($line['debit'] ?? 0);
            $credit += (float) ($line['credit'] ?? 0);
        }
        if (round($debit, 2) !== round($credit, 2) || $debit <= 0) {
            return ['status' => 'error', 'message' => 'Journal entry must balance (debits = credits)'];
        }
        try {
            $this->db->beginTransaction();
            $entryNo = $this->journal->nextEntryNo($storeId);
            $entryId = $this->journal->createEntry(array_merge($entry, [
                'store_id' => $storeId,
                'entry_no' => $entryNo,
                'created_by' => $userId,
            ]), $lines);
            $this->db->commit();
            AccountingAuditRepository::log('journal_posted', $storeId, $userId, 'journal_entry', $entryId, [
                'entry_no' => $entryNo,
                'description' => $entry['description'] ?? '',
            ]);
            return ['status' => 'success', 'data' => ['id' => $entryId, 'entry_no' => $entryNo]];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function list(?int $storeId, array $filters = []): array
    {
        return $this->journal->list($storeId, $filters);
    }

    public function stats(?int $storeId, array $filters = []): array
    {
        return $this->journal->stats($storeId, $filters);
    }

    public function referenceTypes(?int $storeId): array
    {
        return $this->journal->referenceTypes($storeId);
    }
}
