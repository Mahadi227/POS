<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/Database/Database.php';
require_once __DIR__ . '/../includes/Platform/SaaSPhase7Migrator.php';
require_once __DIR__ . '/../includes/Platform/Services/ApiKeyService.php';
require_once __DIR__ . '/../includes/Platform/Repositories/ApiKeyRepository.php';

$db = Database::getInstance()->getConnection();
SaaSPhase7Migrator::ensure($db);

$svc = new ApiKeyService($db, new ApiKeyRepository($db));
$created = $svc->create(1, 'Phase7 test', ['tenant:read', 'stores:read'], null);
$validated = $svc->validate($created['raw_key']);

echo 'Key prefix: ' . $created['prefix'] . PHP_EOL;
echo 'Validate tenant: ' . ($validated['tenant_id'] ?? 'fail') . PHP_EOL;

SaaSPhase7Migrator::ensureAuxiliary($db);
$hasIdem = (bool) $db->query("SHOW TABLES LIKE 'api_idempotency_keys'")->fetchColumn();
echo 'Idempotency table: ' . ($hasIdem ? 'yes' : 'no') . PHP_EOL;
echo 'Tables OK' . PHP_EOL;
