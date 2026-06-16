<?php

/**
 * Manager module — session & role guard.
 * Allowed: manager, admin, super_admin
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/Config/session.php';
require_once __DIR__ . '/../../../includes/Config/config.php';

requireLogin();

require_once __DIR__ . '/../../../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../../../languages/helpers.php';

$roleSlug = strtolower(str_replace(' ', '_', $_SESSION['role'] ?? ''));
$MANAGER_ALLOWED_ROLES = ['manager', 'admin', 'super_admin'];

if (!in_array($roleSlug, $MANAGER_ALLOWED_ROLES, true)) {
    header('Location: ' . ($roleSlug === 'cashier' ? '../cashier/dashboard.php' : '../login.php'));
    exit;
}

$managerConfig = require __DIR__ . '/manager-config.php';

$activeLang = $_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en');
$locale = $activeLang === 'fr' ? 'fr-FR' : 'en-US';
$managerConfig['lang'] = $activeLang;
$managerConfig['locale'] = $locale;

$pageTitle   = $pageTitle ?? __t('dashboard_title', 'manager');
$activePage  = $activePage ?? '';
$pageCss     = $pageCss ?? [];
$pageScripts = $pageScripts ?? [];
$pageI18n    = $pageI18n ?? [];
$bodyClass   = $bodyClass ?? 'mgr-page';
$initial     = strtoupper(substr($_SESSION['name'] ?? 'M', 0, 1));

$scriptFolder = basename(dirname($_SERVER['SCRIPT_FILENAME']));
$mgrPrefix    = ($scriptFolder === 'manager') ? '' : '../';
$changeUrl    = $mgrPrefix . '../change_language.php';
$managerConfig['api']['base'] = $mgrPrefix . '../../api/v1/index.php';
