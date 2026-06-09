<?php

/**
 * Manager module — session & role guard.
 * Allowed: manager, admin, super_admin
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/Config/session.php';
require_once __DIR__ . '/../../../includes/Config/config.php';

requireLogin();

$roleSlug = strtolower(str_replace(' ', '_', $_SESSION['role'] ?? ''));
$MANAGER_ALLOWED_ROLES = ['manager', 'admin', 'super_admin'];

if (!in_array($roleSlug, $MANAGER_ALLOWED_ROLES, true)) {
    header('Location: ' . ($roleSlug === 'cashier' ? '../cashier/dashboard.php' : '../login.php'));
    exit;
}

$managerConfig = require __DIR__ . '/manager-config.php';

$pageTitle   = $pageTitle ?? 'Supervision';
$activePage  = $activePage ?? '';
$pageCss     = $pageCss ?? [];
$pageScripts = $pageScripts ?? [];
$initial     = strtoupper(substr($_SESSION['name'] ?? 'M', 0, 1));

$scriptFolder = basename(dirname($_SERVER['SCRIPT_FILENAME']));
$mgrPrefix    = ($scriptFolder === 'manager') ? '' : '../';
