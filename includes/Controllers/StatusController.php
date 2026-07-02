<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Platform/TenantSchemaMigrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase6Migrator.php';
require_once __DIR__ . '/../Platform/Services/PlatformStatusService.php';

final class StatusController
{
    private PDO $db;
    private PlatformStatusService $status;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        TenantSchemaMigrator::ensure($this->db);
        SaaSPhase6Migrator::ensure($this->db);
        $this->status = new PlatformStatusService($this->db);
    }

    public function handleRequest(string $method, array $path): void
    {
        $action = $path[1] ?? '';

        if ($method === 'GET' && ($action === '' || $action === 'public')) {
            echo json_encode(['status' => 'success', 'data' => $this->status->getPublicStatus()]);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }
}
