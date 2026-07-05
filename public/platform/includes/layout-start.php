<?php
/** @var string $pageTitle @var string $activePlatPage @var array $pageI18n @var array $extraScripts @var array $extraStyles */
if (!isset($pageTitle)) {
    $pageTitle = __t('plat_title', 'platform');
}
$extraScripts = $extraScripts ?? ['platform-common.js'];
$extraStyles = $extraStyles ?? [];
$pageI18n = $pageI18n ?? [];
$platI18n = array_merge(plat_i18n(plat_common_i18n_keys()), $pageI18n);
$themeAccent = $themeAccent ?? plat_layout('themeAccent', '#7c3aed');
$themePortal = $themePortal ?? plat_layout('themePortal', 'platform');
$activeLang = plat_layout('activeLang', 'en');
$assetsBase = plat_layout('assetsBase', '../../assets');
$apiBase = plat_layout('apiBase', '../../api/v1/index.php');
$apiV2Base = plat_layout('apiV2Base', '../../api/v2/index.php');
$changeUrl = plat_layout('changeUrl', '../change_language.php');
$initial = plat_layout('initial', 'P');
$accentEsc = htmlspecialchars((string) $themeAccent, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light" data-portal="platform" data-theme-accent="<?php echo $accentEsc; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?php echo $accentEsc; ?>">
    <meta name="theme-accent" content="<?php echo $accentEsc; ?>">
    <title><?php echo htmlspecialchars($pageTitle); ?> — RetailPOS Cloud</title>
    <?php include __DIR__ . '/../../includes/theme-head.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/admin.css?v=2">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/admin-dashboard.css?v=5">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/platform-portal.css?v=3">
<?php foreach ($extraStyles as $css): ?>
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/<?php echo htmlspecialchars($css, ENT_QUOTES, 'UTF-8'); ?>?v=1">
<?php endforeach; ?>
</head>
<body class="plat-page ad-page">
<div class="admin-layout">
    <aside class="sidebar plat-sidebar" id="platSidebar">
        <div class="sidebar-header">
            <div class="logo">
                <span class="material-icons-round">cloud</span>
                <h2>Cloud<span class="dot">.</span></h2>
            </div>
        </div>
        <?php include __DIR__ . '/sidebar.php'; ?>
        <div class="user-profile-widget">
            <div class="avatar"><?php echo htmlspecialchars($initial); ?></div>
            <div class="user-info">
                <p class="name"><?php echo htmlspecialchars($_SESSION['platform_name'] ?? ''); ?></p>
                <p class="role"><?php echo htmlspecialchars($_SESSION['platform_role'] ?? ''); ?></p>
            </div>
        </div>
    </aside>
    <main class="main-content">
        <header class="top-header ad-page-header plat-page-header">
            <div class="header-left ad-header-left">
                <button type="button" class="icon-btn mobile-menu-btn" id="platMenuBtn" aria-label="Menu">
                    <span class="material-icons-round">menu</span>
                </button>
                <div class="header-title-group">
                    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <div class="header-subline">
                        <span class="ih-last-updated" id="platLastUpdated"></span>
                    </div>
                </div>
            </div>
            <div class="header-actions ad-header-actions">
                <?php include __DIR__ . '/../../includes/language_switcher.php'; ?>
                <button type="button" class="icon-btn theme-toggle ad-header-icon" id="platThemeToggle" data-theme-toggle
                        title="<?php echo htmlspecialchars(__t('theme', 'platform'), ENT_QUOTES, 'UTF-8'); ?>"
                        aria-label="<?php echo htmlspecialchars(__t('theme', 'platform'), ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="material-icons-round">dark_mode</span>
                </button>
                <button type="button" class="ad-refresh-btn ad-header-icon" id="platRefreshBtn" title="<?php echo htmlspecialchars(__t('refresh', 'platform'), ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="material-icons-round">refresh</span>
                </button>
            </div>
        </header>
        <div class="dashboard-scroll-area">
