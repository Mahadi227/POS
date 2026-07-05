<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/Database/Database.php';
require_once __DIR__ . '/../../../includes/Config/session.php';
require_once __DIR__ . '/../../../includes/Config/config.php';
require_once __DIR__ . '/../../../includes/Middleware/LanguageMiddleware.php';
require_once __DIR__ . '/../../../languages/helpers.php';
require_once __DIR__ . '/../../../includes/Helpers/TenantBootstrap.php';
require_once __DIR__ . '/../../../includes/Platform/SaaSPhase15Migrator.php';
require_once __DIR__ . '/../../../includes/Platform/SaaSPhase16Migrator.php';
require_once __DIR__ . '/../../../includes/Platform/Repositories/SubscriptionRepository.php';
require_once __DIR__ . '/../../../includes/Platform/Services/EntitlementService.php';
require_once __DIR__ . '/../../../includes/Ecommerce/Repositories/EcommerceCatalogRepository.php';
require_once __DIR__ . '/../../../includes/Ecommerce/Services/EcommerceCartService.php';
require_once __DIR__ . '/../../../includes/Ecommerce/Services/EcommerceOrderService.php';
require_once __DIR__ . '/../../../includes/Ecommerce/Services/EcommercePaystackService.php';
require_once __DIR__ . '/../../../includes/Ecommerce/Services/EcommerceAccountService.php';
require_once __DIR__ . '/../../../includes/Helpers/UrlHelper.php';
require_once __DIR__ . '/../../../includes/Helpers/CurrencyHelper.php';
require_once __DIR__ . '/../../../includes/Ecommerce/Services/EcommerceWishlistService.php';

LanguageMiddleware::bootstrap();

$db = Database::getInstance()->getConnection();
SaaSPhase15Migrator::ensure($db);
SaaSPhase16Migrator::ensure($db);

$tenant = TenantBootstrap::resolveTenant($db);
if (!$tenant) {
    http_response_code(404);
    $param = defined('SAAS_TENANT_PARAM') ? SAAS_TENANT_PARAM : 'tenant';
    $appBase = rtrim(APP_URL, '/');
    $storePath = 'public/e-commerce/home/';
    $sessionSlug = trim((string) ($_SESSION['tenant_slug'] ?? ''));
    $exampleSlug = $sessionSlug !== '' ? $sessionSlug : 'your-tenant-slug';
    $exampleHref = str_replace(' ', '%20', $appBase . '/' . $storePath . '?' . $param . '=' . rawurlencode($exampleSlug));
    $exampleUrl = htmlspecialchars($exampleHref, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Store not found</title></head>'
        . '<body style="font-family:sans-serif;padding:2rem;max-width:640px;line-height:1.5">'
        . '<h1>Store not found</h1>'
        . '<p>This storefront needs a tenant. Open it with <code>?' . htmlspecialchars($param, ENT_QUOTES, 'UTF-8') . '=your-slug</code> in the URL, or sign in to the admin panel first.</p>'
        . '<p><strong>Example:</strong><br><a href="' . $exampleUrl . '">' . $exampleUrl . '</a></p>'
        . '<p style="color:#64748b;font-size:0.9rem">Server runs on <code>' . htmlspecialchars(parse_url(APP_URL, PHP_URL_HOST) . (parse_url(APP_URL, PHP_URL_PORT) ? ':' . parse_url(APP_URL, PHP_URL_PORT) : ''), ENT_QUOTES, 'UTF-8') . '</code> — use that port if <code>localhost</code> alone does not connect.</p>'
        . '</body></html>';
    exit;
}

$tenantId = (int) $tenant['id'];
$subs = new SubscriptionRepository($db);
$entitlements = new EntitlementService($db, $subs);

try {
    $entitlements->assertModule($tenantId, 'ecommerce');
} catch (RuntimeException) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:2rem"><h1>E-commerce not available</h1><p>Upgrade your plan to enable the online store.</p></body></html>';
    exit;
}

$branding = TenantBootstrap::branding($db);
$tenantSlug = (string) ($tenant['slug'] ?? '');

$ecomBrandName = trim((string) ($branding['brand_name'] ?? ''));
if ($ecomBrandName === '') {
    $ecomBrandName = (string) ($tenant['name'] ?? 'Store');
}

$ecomAccent = (string) ($branding['accent'] ?? '#2563eb');
if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $ecomAccent)) {
    $ecomAccent = '#2563eb';
}
$ecomPrimary = $ecomAccent;
$ecomLogoUrl = (string) ($branding['logo_url'] ?? '');
$ecomFaviconUrl = (string) ($branding['favicon_url'] ?? '');
$ecomStorefrontUrl = TenantResolver::tenantEcommerceUrl($tenantSlug, $db, $tenantId);
$ecomCustomDomain = (string) ($branding['custom_domain'] ?? '');

$catalog = new EcommerceCatalogRepository($db);
$settings = $catalog->getSettings($tenantId);
$defaultStoreId = (int) ($settings['default_store_id'] ?? 0);
if ($defaultStoreId <= 0) {
    $defaultStoreId = $catalog->defaultStoreId($tenantId);
}
$ecomStores = $catalog->listStores($tenantId);
$ecomStoreSessionKey = 'ecommerce_store_' . $tenantId;
$requestedStoreId = isset($_GET['store_id']) ? (int) $_GET['store_id'] : null;
$previousStoreId = (int) ($_SESSION[$ecomStoreSessionKey] ?? 0);
$storeId = $catalog->pickStoreId(
    $tenantId,
    $defaultStoreId,
    $ecomStores,
    $requestedStoreId,
    $previousStoreId > 0 ? $previousStoreId : null
);
if ($storeId <= 0) {
    http_response_code(503);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:2rem"><h1>Store not configured</h1><p>No store linked to this tenant.</p></body></html>';
    exit;
}
if ($previousStoreId > 0 && $previousStoreId !== $storeId) {
    unset($_SESSION['ecommerce_cart']);
}
$_SESSION[$ecomStoreSessionKey] = $storeId;

$ecomHasMultipleStores = count($ecomStores) > 1;
$ecomActiveStore = null;
foreach ($ecomStores as $ecomStoreRow) {
    if ((int) ($ecomStoreRow['id'] ?? 0) === $storeId) {
        $ecomActiveStore = $ecomStoreRow;
        break;
    }
}
$ecomStoreName = (string) ($ecomActiveStore['name'] ?? '');
$ecomStoreLocation = (string) ($ecomActiveStore['location'] ?? '');

$storeContext = CurrencyHelper::portalContext($db, $storeId, false);
$currency = $storeContext['currency'];
$currencyMeta = $storeContext['meta'];
$taxRate = (float) ($settings['tax_rate'] ?? 0);

function ecom_path_depth(): int
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (!preg_match('#/e-commerce/(.+)$#', $script, $m)) {
        return 0;
    }
    $sub = trim(dirname($m[1]), '.');
    if ($sub === '' || $sub === '.') {
        return 0;
    }
    return substr_count($sub, '/') + 1;
}

$ecomDepth = ecom_path_depth();
$ecomRootUp = str_repeat('../', $ecomDepth);
$assetsBase = str_repeat('../', $ecomDepth + 2) . 'assets';
$changeUrl = str_repeat('../', $ecomDepth + 1) . 'change_language.php';
$activeLang = $_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en');

$cart = new EcommerceCartService($db, $storeId);
$wishlist = new EcommerceWishlistService($db, $tenantId, $storeId);
$accounts = new EcommerceAccountService($db);
$orders = new EcommerceOrderService($db);
$paystack = new EcommercePaystackService($db, $catalog);
$paystackEnabled = $paystack->isEnabled($tenantId);
$loadPaystackInline = false;
$checkoutAccountEmail = '';

$ecomAccountId = (int) ($_SESSION['ecommerce_account_id'] ?? 0);
$ecomAccount = $ecomAccountId > 0 ? $accounts->findById($tenantId, $ecomAccountId) : null;
if ($ecomAccountId > 0 && !$ecomAccount) {
    unset($_SESSION['ecommerce_account_id']);
    $ecomAccountId = 0;
}

function ecom_href(string $path): string
{
    global $ecomRootUp, $tenantSlug, $ecomCustomDomain, $ecomHasMultipleStores, $storeId;

    $href = $ecomRootUp . ltrim($path, '/');

    $slug = trim((string) ($tenantSlug ?? ''));
    $useTenantParam = $slug !== ''
        && trim((string) ($ecomCustomDomain ?? '')) === ''
        && TenantResolver::baseDomain() === '';
    if ($useTenantParam) {
        $param = defined('SAAS_TENANT_PARAM') ? SAAS_TENANT_PARAM : 'tenant';
        if (!preg_match('/[?&]' . preg_quote($param, '/') . '=/', $href)) {
            $href .= (str_contains($href, '?') ? '&' : '?') . $param . '=' . rawurlencode($slug);
        }
    }

    if (!empty($ecomHasMultipleStores) && $storeId > 0 && !preg_match('/[?&]store_id=\d/', $href)) {
        $href .= (str_contains($href, '?') ? '&' : '?') . 'store_id=' . $storeId;
    }

    return $href;
}

/** @return array<string, scalar> */
function ecom_query_params(array $overrides = [], array $omit = []): array
{
    global $tenantSlug, $storeId, $ecomHasMultipleStores;

    $params = [];
    if (ecom_uses_tenant_param()) {
        $param = defined('SAAS_TENANT_PARAM') ? SAAS_TENANT_PARAM : 'tenant';
        $params[$param] = $tenantSlug;
    }
    if (!empty($ecomHasMultipleStores) && $storeId > 0 && !array_key_exists('store_id', $overrides)) {
        $params['store_id'] = $storeId;
    }
    foreach (['category_id', 'brand_id', 'q', 'page'] as $key) {
        if (isset($_GET[$key]) && $_GET[$key] !== '' && !in_array($key, $omit, true)) {
            $params[$key] = $_GET[$key];
        }
    }

    foreach ($omit as $key) {
        unset($params[$key]);
    }

    return array_merge($params, $overrides);
}

function ecom_uses_tenant_param(): bool
{
    global $tenantSlug, $ecomCustomDomain;

    return trim((string) ($tenantSlug ?? '')) !== ''
        && trim((string) ($ecomCustomDomain ?? '')) === ''
        && TenantResolver::baseDomain() === '';
}

function ecom_hex_rgba(string $hex, float $alpha): string
{
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
        return 'rgba(37, 99, 235, ' . $alpha . ')';
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $a = rtrim(rtrim(sprintf('%.2f', $alpha), '0'), '.');

    return sprintf('rgba(%d, %d, %d, %s)', $r, $g, $b, $a);
}

function ecom_money(float $amount): string
{
    global $currency, $activeLang;
    return CurrencyHelper::format($amount, $currency, $activeLang);
}

function ecom_product_slug(array $product): string
{
    if (!empty($product['slug'])) {
        return (string) $product['slug'];
    }
    return 'p-' . (int) $product['id'];
}

function ecom_product_image(array|string|null $productOrUrl): ?string
{
    $stored = is_array($productOrUrl) ? ($productOrUrl['image_url'] ?? null) : $productOrUrl;
    if ($stored === null || trim((string) $stored) === '') {
        return null;
    }
    return resolve_product_image_url((string) $stored);
}

function ecom_brand_logo(array|string|null $brandOrUrl): ?string
{
    $stored = is_array($brandOrUrl) ? ($brandOrUrl['logo_url'] ?? null) : $brandOrUrl;
    if ($stored === null || trim((string) $stored) === '') {
        return null;
    }
    return resolve_product_image_url((string) $stored);
}

/** @param array<string, mixed> $brand */
function ecom_brand_href(array $brand): string
{
    $id = (int) ($brand['id'] ?? 0);
    $slug = trim((string) ($brand['slug'] ?? ''));
    if ($slug !== '') {
        return ecom_href('brands/view.php?slug=' . rawurlencode($slug));
    }

    return ecom_href('brands/view.php?id=' . $id);
}

function ecom_format_datetime(?string $datetime): string
{
    global $activeLang;
    if ($datetime === null || trim($datetime) === '') {
        return '';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '';
    }
    if (($activeLang ?? 'en') === 'fr') {
        return date('d/m/Y', $ts);
    }

    return date('F j, Y', $ts);
}

/** @param array<string, mixed> $post */
function ecom_blog_date(array $post): string
{
    return ecom_format_datetime((string) ($post['published_at'] ?? $post['created_at'] ?? ''));
}

/** @param array<string, mixed> $post */
function ecom_blog_datetime(array $post): string
{
    $raw = (string) ($post['published_at'] ?? $post['created_at'] ?? '');
    if ($raw === '') {
        return '';
    }
    $ts = strtotime($raw);

    return $ts !== false ? date('Y-m-d', $ts) : '';
}

/** @param array<string, mixed> $post */
function ecom_blog_href(array $post): string
{
    $slug = trim((string) ($post['slug'] ?? ''));
    if ($slug !== '') {
        return ecom_href('blog/view.php?slug=' . rawurlencode($slug));
    }

    return ecom_href('blog/view.php?id=' . (int) ($post['id'] ?? 0));
}

function ecom_blog_reading_time(?string $text): int
{
    $words = str_word_count(strip_tags((string) $text));
    return max(1, (int) ceil($words / 200));
}

function ecom_nav_items(): array
{
    return [
        ['href' => 'home/', 'label' => 'ecom_nav_home', 'icon' => 'home'],
        ['href' => 'shop/', 'label' => 'ecom_nav_shop', 'icon' => 'store'],
        ['href' => 'categories/', 'label' => 'ecom_nav_categories', 'icon' => 'category'],
        ['href' => 'brands/', 'label' => 'ecom_nav_brands', 'icon' => 'sell'],
        ['href' => 'blog/', 'label' => 'ecom_nav_blog', 'icon' => 'article'],
    ];
}

/** Customer-facing status label for a web order. */
function ecom_order_status_label(array $order): string
{
    $status = (string) ($order['status'] ?? 'completed');
    $method = (string) ($order['checkout_method'] ?? '');

    if ($status === 'pending' && $method === 'cash_on_delivery') {
        return __t('ecom_status_pending_cod', 'ecommerce');
    }
    if ($status === 'pending' && ($order['payment_provider'] ?? '') === 'paystack') {
        return __t('ecom_status_pending_payment', 'ecommerce');
    }
    if ($status === 'completed') {
        return match ($method) {
            'cash_on_delivery' => __t('ecom_status_completed_cod', 'ecommerce'),
            'mobile_money' => __t('ecom_status_completed_mobile', 'ecommerce'),
            'card' => __t('ecom_status_completed_card', 'ecommerce'),
            default => __t('ecom_status_completed', 'ecommerce'),
        };
    }
    if ($status === 'cancelled') {
        return __t('ecom_status_cancelled', 'ecommerce');
    }
    if ($status === 'pending') {
        return __t('ecom_status_pending', 'ecommerce');
    }

    return __t('ecom_status_' . $status, 'ecommerce');
}

/** Success banner after checkout. */
function ecom_order_placed_message(array $order): string
{
    $status = (string) ($order['status'] ?? 'completed');
    $method = (string) ($order['checkout_method'] ?? '');

    if ($status === 'pending' && $method === 'cash_on_delivery') {
        return __t('ecom_order_placed_cod', 'ecommerce');
    }
    if ($status === 'pending' && ($order['payment_provider'] ?? '') === 'paystack') {
        return __t('ecom_order_placed_paystack_pending', 'ecommerce');
    }
    if ($status === 'completed' && in_array($method, ['card', 'mobile_money'], true)) {
        return __t('ecom_order_placed_paid', 'ecommerce');
    }

    return __t('ecom_order_placed', 'ecommerce');
}

/**
 * Resolves POS customer + storefront account for checkout (guest auto-registration).
 *
 * @param array<string, mixed> $input
 * @return array{0:?int, 1:int} [customer_id, account_id]
 */
function ecom_resolve_checkout_customer(
    EcommerceAccountService $accounts,
    int $tenantId,
    ?array $ecomAccount,
    array $input
): array {
    if ($ecomAccount) {
        $customerId = (int) ($ecomAccount['customer_id'] ?? 0);

        return [$customerId > 0 ? $customerId : null, (int) ($ecomAccount['id'] ?? 0)];
    }

    $guest = $accounts->ensureGuestFromCheckout(
        $tenantId,
        (string) ($input['checkout_email'] ?? $input['email'] ?? ''),
        (string) ($input['checkout_phone'] ?? $input['phone'] ?? ''),
        (string) ($input['checkout_name'] ?? $input['name'] ?? ''),
    );

    return [$guest['customer_id'], $guest['account_id']];
}

require_once __DIR__ . '/data.php';
