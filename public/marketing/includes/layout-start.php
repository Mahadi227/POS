<?php
/** @var string $pageTitle @var string $activePage @var array $pageMeta @var array $extraStyles @var bool $darkHero */
$pageTitle = $pageTitle ?? __t('mkt_site_title', 'marketing');
$activePage = $activePage ?? '';
$extraStyles = $extraStyles ?? [];
$pageMeta = $pageMeta ?? [];
$darkHero = $darkHero ?? false;
$depthPrefix = str_repeat('../', mkt_path_depth());
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#4f46e5">
    <title><?php echo htmlspecialchars($pageTitle); ?> — RetailPOS Cloud</title>
    <link rel="manifest" href="<?php echo $depthPrefix; ?>manifest.json">
    <link rel="icon" href="<?php echo $assetsBase; ?>/images/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/marketing.css?v=4">
<?php foreach ($extraStyles as $css): ?>
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/<?php echo htmlspecialchars($css, ENT_QUOTES, 'UTF-8'); ?>?v=9">
<?php endforeach; ?>
<?php mkt_seo_head(array_merge([
    'title' => $pageTitle,
], $pageMeta)); ?>
</head>
<body class="mkt-body<?php echo $darkHero ? ' mkt-body--dark-hero' : ''; ?>">
<a href="#mkt-main" class="mkt-skip"><?php echo __t('mkt_skip', 'marketing'); ?></a>

<header class="mkt-header" id="mkt-header">
    <div class="mkt-header__inner">
        <a href="<?php echo $depthPrefix; ?>index.php" class="mkt-logo" aria-label="RetailPOS Cloud">
            <span class="mkt-logo__icon" aria-hidden="true">
                <span class="material-icons-round">storefront</span>
            </span>
            <span class="mkt-logo__text">Retail<span>POS</span></span>
        </a>

        <nav class="mkt-nav" id="mkt-nav" aria-label="<?php echo __t('mkt_nav_main', 'marketing'); ?>">
            <?php foreach (mkt_nav_items() as $item): ?>
            <a href="<?php echo $depthPrefix . htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>"
               class="mkt-nav__link<?php echo ($activePage === basename($item['href'], '.php') || $activePage === rtrim($item['href'], '/')) ? ' is-active' : ''; ?>">
                <?php echo __t($item['label'], 'marketing'); ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="mkt-header__actions">
            <div class="mkt-lang" role="group" aria-label="Language">
                <a href="<?php echo $changeUrl; ?>?lang=en&amp;redirect=<?php echo urlencode($_SERVER['REQUEST_URI'] ?? ''); ?>"
                   class="mkt-lang__btn<?php echo $activeLang === 'en' ? ' is-active' : ''; ?>">EN</a>
                <a href="<?php echo $changeUrl; ?>?lang=fr&amp;redirect=<?php echo urlencode($_SERVER['REQUEST_URI'] ?? ''); ?>"
                   class="mkt-lang__btn<?php echo $activeLang === 'fr' ? ' is-active' : ''; ?>">FR</a>
            </div>
            <a href="<?php echo $publicRoot; ?>login.php" class="mkt-btn mkt-btn--ghost"><?php echo __t('mkt_nav_login', 'marketing'); ?></a>
            <a href="<?php echo $publicRoot; ?>register.php" class="mkt-btn mkt-btn--primary"><?php echo __t('mkt_nav_trial', 'marketing'); ?></a>
            <button type="button" class="mkt-menu-toggle" id="mkt-menu-toggle" aria-expanded="false" aria-controls="mkt-nav">
                <span class="material-icons-round" aria-hidden="true">menu</span>
            </button>
        </div>
    </div>
</header>

<main id="mkt-main" class="mkt-main">
