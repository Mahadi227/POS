<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$q = trim($_GET['q'] ?? '');
$pageTitle = $q !== '' ? __t('ecom_search_results', 'ecommerce', ['q' => $q]) : __t('ecom_search_title', 'ecommerce');
$activePage = 'search';
$bodyClass = trim(($bodyClass ?? '') . ' ecom-page-search');
$products = $q !== '' ? $catalog->listProducts($tenantId, $storeId, ['q' => $q], 48, 0) : [];
require __DIR__ . '/../includes/layout-start.php';
?>
<section class="ecom-page-head">
    <h1><?php echo __t('ecom_search_title', 'ecommerce'); ?></h1>
    <?php
    $searchFormClass = 'page';
    $searchAction = '';
    $searchValue = $q;
    $searchPanelId = 'ecom-search-panel-page';
    $searchShowBtn = true;
    include __DIR__ . '/../includes/partials/search-form.php';
    ?>
</section>

<div class="ecom-search-live" data-ecom-search-live<?php echo $q === '' ? ' hidden' : ''; ?>>
    <p class="ecom-search-meta" data-ecom-search-meta>
        <?php if ($q !== ''): ?>
        <?php echo __t('ecom_search_meta', 'ecommerce', ['count' => count($products), 'q' => $q]); ?>
        <?php endif; ?>
    </p>
    <div class="ecom-product-grid" data-ecom-search-results>
        <?php foreach ($products as $product): ?>
            <?php include __DIR__ . '/../includes/partials/product-card.php'; ?>
        <?php endforeach; ?>
        <?php if ($q !== '' && $products === []): ?>
        <p class="ecom-empty" data-ecom-search-empty><?php echo __t('ecom_no_results', 'ecommerce'); ?></p>
        <?php endif; ?>
    </div>
</div>

<p class="ecom-search-hint" data-ecom-search-hint<?php echo $q !== '' ? ' hidden' : ''; ?>>
    <?php echo __t('ecom_search_type_hint', 'ecommerce'); ?>
</p>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>
