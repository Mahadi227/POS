<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/Config/session.php';
require_once __DIR__ . '/../../../includes/Config/config.php';
require_once __DIR__ . '/../../../includes/Helpers/RbacGuard.php';
require_once __DIR__ . '/../../../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../../../languages/helpers.php';

requireLogin('../login.php');
require_once __DIR__ . '/../../../includes/Middleware/ActivityMiddleware.php';
ActivityMiddleware::touch();

$activeLang = $_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en');
$locale = $activeLang === 'fr' ? 'fr-FR' : 'en-US';
$changeUrl = '../change_language.php';
$initial = strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1));
$apiBase = '../../api/v1/index.php';
$assetsBase = '../../assets';
$roleSlug = strtolower(str_replace(' ', '_', $_SESSION['role_slug'] ?? $_SESSION['role'] ?? ''));
$isAdmin = in_array($roleSlug, ['super_admin', 'admin'], true);

function notif_i18n(array $keys): array
{
    $out = [];
    foreach ($keys as $key) {
        $out[$key] = __t($key, 'notifications');
    }
    return $out;
}
