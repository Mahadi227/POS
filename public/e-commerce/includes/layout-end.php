</main>

<footer class="ecom-footer">
    <div class="ecom-footer__grid">
        <div>
            <strong><?php echo htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8'); ?></strong>
            <p><?php echo __t('ecom_footer_tagline', 'ecommerce'); ?></p>
        </div>
        <div>
            <h4><?php echo __t('ecom_footer_shop', 'ecommerce'); ?></h4>
            <a href="<?php echo ecom_href('shop/'); ?>"><?php echo __t('ecom_nav_shop', 'ecommerce'); ?></a>
            <a href="<?php echo ecom_href('categories/'); ?>"><?php echo __t('ecom_nav_categories', 'ecommerce'); ?></a>
            <a href="<?php echo ecom_href('brands/'); ?>"><?php echo __t('ecom_nav_brands', 'ecommerce'); ?></a>
        </div>
        <div>
            <h4><?php echo __t('ecom_footer_account', 'ecommerce'); ?></h4>
            <a href="<?php echo ecom_href('account/'); ?>"><?php echo __t('ecom_nav_account', 'ecommerce'); ?></a>
            <a href="<?php echo ecom_href('orders/'); ?>"><?php echo __t('ecom_nav_orders', 'ecommerce'); ?></a>
            <a href="<?php echo ecom_href('wishlist/'); ?>"><?php echo __t('ecom_nav_wishlist', 'ecommerce'); ?></a>
        </div>
        <div>
            <h4><?php echo __t('ecom_footer_info', 'ecommerce'); ?></h4>
            <a href="<?php echo ecom_href('blog/'); ?>"><?php echo __t('ecom_nav_blog', 'ecommerce'); ?></a>
        </div>
    </div>
    <p class="ecom-footer__copy">© <?php echo date('Y'); ?> <?php echo htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8'); ?><?php if (empty($branding['can_customize'])): ?> · Powered by RetailPOS<?php endif; ?></p>
</footer>

<script>
window.ECOM = {
    apiBase: '<?php echo ecom_href('api/'); ?>',
    tenantSlug: <?php echo json_encode($tenantSlug ?? '', JSON_UNESCAPED_UNICODE); ?>,
    tenantParam: <?php echo json_encode(defined('SAAS_TENANT_PARAM') ? SAAS_TENANT_PARAM : 'tenant', JSON_UNESCAPED_UNICODE); ?>,
    storeId: <?php echo (int) $storeId; ?>,
    csrf: '<?php echo htmlspecialchars(session_id(), ENT_QUOTES, 'UTF-8'); ?>',
    currency: <?php echo json_encode($currency, JSON_UNESCAPED_UNICODE); ?>,
    currencyMeta: <?php echo json_encode($currencyMeta, JSON_UNESCAPED_UNICODE); ?>,
    lang: <?php echo json_encode($activeLang, JSON_UNESCAPED_UNICODE); ?>,
    storefrontUrl: <?php echo json_encode($ecomStorefrontUrl ?? '', JSON_UNESCAPED_UNICODE); ?>,
    brandName: <?php echo json_encode($ecomBrandName ?? '', JSON_UNESCAPED_UNICODE); ?>,
    accent: <?php echo json_encode($ecomAccent ?? '#2563eb', JSON_UNESCAPED_UNICODE); ?>,
    checkout: <?php echo json_encode([
        'isCheckout' => ($activePage ?? '') === 'checkout',
        'isGuest' => empty($ecomAccount),
        'accountEmail' => $checkoutAccountEmail ?? '',
        'orderViewUrl' => ecom_href('orders/view.php'),
    ], JSON_UNESCAPED_UNICODE); ?>,
    paystack: <?php echo json_encode([
        'enabled' => !empty($paystackEnabled),
        'publicKey' => !empty($paystackEnabled) ? $paystack->publicKey($tenantId) : '',
    ], JSON_UNESCAPED_UNICODE); ?>,
    search: <?php echo json_encode([
        'minChars' => 2,
        'debounceMs' => 280,
        'limit' => 8,
        'pageUrl' => ecom_href('search/'),
        'productUrl' => ecom_href('products/view.php'),
        'addCartLabel' => __t('ecom_add_cart', 'ecommerce'),
        'metaTemplate' => __t('ecom_search_meta', 'ecommerce', ['count' => '{count}', 'q' => '{q}']),
    ], JSON_UNESCAPED_UNICODE); ?>,
    i18n: <?php echo json_encode([
        'terms_required' => __t('ecom_terms_required', 'ecommerce'),
        'place_order' => __t('ecom_place_order', 'ecommerce'),
        'pay_with_paystack' => __t('ecom_pay_with_paystack', 'ecommerce'),
        'checkout_email_invalid' => __t('ecom_checkout_email_invalid', 'ecommerce'),
        'checkout_name_required' => __t('ecom_checkout_name_required', 'ecommerce'),
        'checkout_phone_required' => __t('ecom_checkout_phone_required', 'ecommerce'),
        'checkout_phone_invalid' => __t('ecom_checkout_phone_invalid', 'ecommerce'),
        'paystack_loading' => __t('ecom_paystack_loading', 'ecommerce'),
        'paystack_closed' => __t('ecom_paystack_closed', 'ecommerce'),
        'paystack_error' => __t('ecom_paystack_error', 'ecommerce'),
        'paystack_verifying' => __t('ecom_paystack_verifying', 'ecommerce'),
        'search_loading' => __t('ecom_search_loading', 'ecommerce'),
        'search_no_results' => __t('ecom_no_results', 'ecommerce'),
        'search_view_all' => __t('ecom_search_view_all', 'ecommerce'),
        'search_min_chars' => __t('ecom_search_min_chars', 'ecommerce'),
        'search_in_stock' => __t('ecom_search_in_stock', 'ecommerce'),
        'added_cart' => __t('ecom_added_cart', 'ecommerce'),
        'error' => __t('ecom_error', 'ecommerce'),
        'brands_filter_status' => __t('ecom_brands_filter_status', 'ecommerce'),
        'blog_filter_status' => __t('ecom_blog_filter_status', 'ecommerce'),
    ], JSON_UNESCAPED_UNICODE); ?>
};
</script>
<?php if (!empty($loadPaystackInline)): ?>
<script src="https://js.paystack.co/v1/inline.js"></script>
<?php endif; ?>
<script src="<?php echo $assetsBase; ?>/js/ecommerce/ecommerce.js?v=6"></script>
<?php
$extraScripts = $extraScripts ?? [];
foreach ($extraScripts as $script):
    $scriptPath = str_contains($script, '/') ? $script : 'ecommerce/' . $script;
?>
<script src="<?php echo $assetsBase; ?>/js/<?php echo htmlspecialchars($scriptPath, ENT_QUOTES, 'UTF-8'); ?>?v=2"></script>
<?php endforeach; ?>
</body>
</html>
