<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/../../includes/Platform/Services/PlatformImpersonationService.php';
require_once __DIR__ . '/../../includes/Platform/Repositories/PlatformAuditRepository.php';

PlatformGuard::requireLogin('login.php');

$db = Database::getInstance()->getConnection();
SaaSPhase3Migrator::ensure($db);

$service = new PlatformImpersonationService($db, new PlatformAuditRepository($db));
$returnId = (int) ($_GET['tenant_id'] ?? $_SESSION['impersonated_tenant_id'] ?? 0);
$service->exitImpersonation(PlatformSessionAuth::userId(), $_SERVER['REMOTE_ADDR'] ?? null);
$target = $returnId > 0 ? 'tenant.php?id=' . $returnId : 'tenants.php';
header('Location: ' . $target);
exit;
