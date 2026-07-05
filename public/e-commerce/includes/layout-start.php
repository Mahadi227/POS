<?php
/** @var string $pageTitle @var string $activePage @var array $extraStyles @var bool $hideHero @var string $bodyClass */
$pageTitle = $pageTitle ?? __t('ecom_site_title', 'ecommerce');
$activePage = $activePage ?? '';
$extraStyles = $extraStyles ?? [];
$hideHero = $hideHero ?? false;
$bodyClass = $bodyClass ?? '';
$storeName = $ecomBrandName ?? (string) ($tenant['name'] ?? 'Store');
$primaryColor = $ecomPrimary ?? '#2563eb';
$accentColor = $ecomAccent ?? $primaryColor;
$accentSoft = ecom_hex_rgba($accentColor, 0.12);
$accentGlow = ecom_hex_rgba($accentColor, 0.28);
$accentBorder = ecom_hex_rgba($accentColor, 0.35);
$accentBorderSoft = ecom_hex_rgba($accentColor, 0.2);
$primarySoft = ecom_hex_rgba($primaryColor, 0.12);
$heroAccentGlow = ecom_hex_rgba($accentColor, 0.1);
$faviconHref = ($ecomFaviconUrl ?? '') !== '' ? $ecomFaviconUrl : $assetsBase . '/images/favicon.ico';
$accentEsc = htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light" data-portal="ecommerce" data-theme-accent="<?php echo $accentEsc; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?php echo $accentEsc; ?>">
    <meta name="theme-accent" content="<?php echo $accentEsc; ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars(str_replace(' ', '%20', $ecomStorefrontUrl ?? ecom_href('home/')), ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo htmlspecialchars($pageTitle); ?> — <?php echo htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php if (!empty($pageMetaDescription)): ?>
    <meta name="description" content="<?php echo htmlspecialchars($pageMetaDescription, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <link rel="icon" href="<?php echo htmlspecialchars($faviconHref, ENT_QUOTES, 'UTF-8'); ?>" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/ecommerce.css?v=10">
    <?php foreach ($extraStyles as $css): ?>
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/<?php echo htmlspecialchars($css, ENT_QUOTES, 'UTF-8'); ?>?v=1">
    <?php endforeach; ?>
    <style>:root {
        --ecom-primary: <?php echo htmlspecialchars($primaryColor, ENT_QUOTES, 'UTF-8'); ?>;
        --ecom-accent: <?php echo $accentEsc; ?>;
        --ecom-accent-soft: <?php echo $accentSoft; ?>;
        --ecom-accent-glow: <?php echo $accentGlow; ?>;
        --ecom-accent-border: <?php echo $accentBorder; ?>;
        --ecom-accent-border-soft: <?php echo $accentBorderSoft; ?>;
        --ecom-primary-soft: <?php echo $primarySoft; ?>;
        --ecom-hero-accent-glow: <?php echo $heroAccentGlow; ?>;
    }</style>
</head>
<body class="ecom-body<?php echo $bodyClass !== '' ? ' ' . htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') : ''; ?>" data-tenant="<?php echo (int) $tenantId; ?>" data-store="<?php echo (int) $storeId; ?>" data-tenant-slug="<?php echo htmlspecialchars($tenantSlug ?? '', ENT_QUOTES, 'UTF-8'); ?>">
<a href="#ecom-main" class="ecom-skip"><?php echo __t('ecom_skip', 'ecommerce'); ?></a>

<header class="ecom-header">
    <div class="ecom-header__inner">
        <a href="<?php echo ecom_href('home/'); ?>" class="ecom-logo">
            <?php if (($ecomLogoUrl ?? '') !== ''): ?>
            <img src="<?php echo htmlspecialchars($ecomLogoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="" class="ecom-logo__img">
            <?php else: ?>
            <span class="material-icons-round" aria-hidden="true">storefront</span>
            <?php endif; ?>
            <span><?php echo htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8'); ?></span>
        </a>

        <?php
        $searchFormClass = 'header';
        $searchAction = ecom_href('search/');
        $searchValue = (string) ($_GET['q'] ?? '');
        $searchPanelId = 'ecom-search-panel-header';
        include __DIR__ . '/partials/search-form.php';
        ?>

        <nav class="ecom-nav" aria-label="<?php echo __t('ecom_nav_main', 'ecommerce'); ?>">
            <?php foreach (ecom_nav_items() as $item): ?>
            <a href="<?php echo ecom_href($item['href']); ?>" class="ecom-nav__link<?php echo $activePage === rtrim($item['href'], '/') ? ' is-active' : ''; ?>">
                <?php echo __t($item['label'], 'ecommerce'); ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="ecom-header__actions">
            <div class="ecom-lang">
                <a href="<?php echo $changeUrl; ?>?lang=en&amp;redirect=<?php echo urlencode($_SERVER['REQUEST_URI'] ?? ''); ?>" class="<?php echo $activeLang === 'en' ? 'is-active' : ''; ?>">EN</a>
                <a href="<?php echo $changeUrl; ?>?lang=fr&amp;redirect=<?php echo urlencode($_SERVER['REQUEST_URI'] ?? ''); ?>" class="<?php echo $activeLang === 'fr' ? 'is-active' : ''; ?>">FR</a>
            </div>
            <a href="<?php echo ecom_href('wishlist/'); ?>" class="ecom-icon-btn" title="<?php echo __t('ecom_nav_wishlist', 'ecommerce'); ?>">
                <span class="material-icons-round">favorite_border</span>
                <span class="ecom-badge" id="ecom-wishlist-count"><?php echo $wishlist->count($ecomAccountId ?: null); ?></span>
            </a>
            <a href="<?php echo ecom_href('cart/'); ?>" class="ecom-icon-btn" title="<?php echo __t('ecom_nav_cart', 'ecommerce'); ?>">
                <span class="material-icons-round">shopping_cart</span>
                <span class="ecom-badge" id="ecom-cart-count"><?php echo $cart->count(); ?></span>
            </a>
            <?php if ($ecomAccount): ?>
            <a href="<?php echo ecom_href('account/'); ?>" class="ecom-btn ecom-btn--ghost"><?php echo htmlspecialchars($ecomAccount['name'], ENT_QUOTES, 'UTF-8'); ?></a>
            <?php else: ?>
            <a href="<?php echo ecom_href('customer/login.php'); ?>" class="ecom-btn ecom-btn--ghost"><?php echo __t('ecom_nav_login', 'ecommerce'); ?></a>
            <?php endif; ?>
            <button type="button" class="ecom-menu-toggle" id="ecom-menu-toggle" aria-expanded="false">
                <span class="material-icons-round">menu</span>
            </button>
        </div>
    </div>
</header>

<main id="ecom-main" class="ecom-main">
