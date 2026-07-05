<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/Config/session.php';
require_once __DIR__ . '/../../includes/Config/config.php';
require_once __DIR__ . '/../../includes/Database/Database.php';
require_once __DIR__ . '/../../includes/Helpers/TenantBootstrap.php';
require_once __DIR__ . '/../../includes/Platform/TenantResolver.php';

$db = Database::getInstance()->getConnection();
$tenant = TenantBootstrap::resolveTenant($db, false);

$query = '';
if (!$tenant) {
    $slug = trim((string) ($_GET[defined('SAAS_TENANT_PARAM') ? SAAS_TENANT_PARAM : 'tenant'] ?? ''));
    if ($slug === '') {
        $slug = trim((string) ($_SESSION['tenant_slug'] ?? $_COOKIE['tenant_slug'] ?? ''));
    }
    if ($slug !== '') {
        $param = defined('SAAS_TENANT_PARAM') ? SAAS_TENANT_PARAM : 'tenant';
        $query = '?' . $param . '=' . rawurlencode($slug);
    }
} elseif (!empty($tenant['slug'])) {
    $param = defined('SAAS_TENANT_PARAM') ? SAAS_TENANT_PARAM : 'tenant';
    $query = '?' . $param . '=' . rawurlencode((string) $tenant['slug']);
}

header('Location: home/' . $query, true, 302);
exit;
