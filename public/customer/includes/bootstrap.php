<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/Config/session.php';
require_once __DIR__ . '/../../../includes/Helpers/RbacGuard.php';
require_once __DIR__ . '/../../../includes/Middleware/LanguageMiddleware.php';

RbacGuard::workspace('customer', '../../login.php');
LanguageMiddleware::bootstrap();

$activeLang = $_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en');
