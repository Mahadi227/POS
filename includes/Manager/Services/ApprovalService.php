<?php
declare(strict_types=1);

require_once __DIR__ . '/../Repositories/ApprovalRepository.php';
require_once __DIR__ . '/ReturnApprovalService.php';
require_once __DIR__ . '/../ManagerAuth.php';
require_once __DIR__ . '/AuditService.php';

class ApprovalService
{
    private ApprovalRepository $repo;
    private ReturnApprovalService $returns;

    public function __construct(?ApprovalRepository $repo = null, ?ReturnApprovalService $returns = null)
    {
        $this->repo = $repo ?? new ApprovalRepository();
        $this->returns = $returns ?? new ReturnApprovalService();
    }

    public function listPending(?int $storeId, ?string $type = null): array
    {
        return $this->repo->listPending($storeId, $type);
    }

    public function approve(int $id, ?string $note = null): array
    {
        $row = $this->repo->findPendingById($id);
        if (!$row) {
            return ['status' => 'error', 'message' => 'Demande introuvable ou déjà traitée'];
        }

        $execMessage = 'Demande approuvée';
        if (($row['type'] ?? '') === 'return') {
            $result = $this->returns->executePendingReturn($row);
            if (($result['status'] ?? '') !== 'success') {
                return $result;
            }
            $execMessage = (string) ($result['message'] ?? $execMessage);
        }

        $ok = $this->repo->review($id, ManagerAuth::currentUserId(), 'approved', $note);
        if ($ok) {
            AuditService::log('approval_approved', 'manager_approval', $id);
            return ['status' => 'success', 'message' => $execMessage];
        }
        return ['status' => 'error', 'message' => 'Impossible d\'approuver'];
    }

    public function reject(int $id, ?string $note = null): array
    {
        $ok = $this->repo->review($id, ManagerAuth::currentUserId(), 'rejected', $note);
        if ($ok) {
            AuditService::log('approval_rejected', 'manager_approval', $id);
            return ['status' => 'success', 'message' => 'Demande rejetée'];
        }
        return ['status' => 'error', 'message' => 'Impossible de rejeter'];
    }
}
