<?php
declare(strict_types=1);

/**
 * Warehouse workspace bootstrap — shared guard for /public/warehouse/
 */
require_once __DIR__ . '/../../../includes/Config/session.php';
require_once __DIR__ . '/../../../includes/Config/config.php';
require_once __DIR__ . '/../../../includes/Helpers/RbacGuard.php';
require_once __DIR__ . '/../../../includes/Middleware/LanguageMiddleware.php';
require_once __DIR__ . '/../../../includes/Database/Database.php';

RbacGuard::workspace('warehouse', '../../login.php');
LanguageMiddleware::bootstrap();

$roleSlug = strtolower(str_replace(' ', '_', $_SESSION['role_slug'] ?? $_SESSION['role'] ?? ''));
$activeLang = $_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en');
$locale = $activeLang === 'fr' ? 'fr-FR' : 'en-US';
$changeUrl = '../change_language.php';
$initial = strtoupper(substr($_SESSION['name'] ?? 'W', 0, 1));
$warehouseId = (int) ($_SESSION['warehouse_id'] ?? 0);

$wmsBase = '../admin/warehouse/';
$assetsBase = '../../assets';
$apiBase = '../../api/v1/index.php';

$canManageWms = in_array($roleSlug, ['super_admin', 'admin', 'warehouse_manager'], true);
