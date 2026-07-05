<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Config/session.php';
require_once __DIR__ . '/../Helpers/TenantBootstrap.php';
require_once __DIR__ . '/../Platform/SaaSPhase15Migrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase16Migrator.php';
require_once __DIR__ . '/../Helpers/UrlHelper.php';
require_once __DIR__ . '/../Helpers/CurrencyHelper.php';
require_once __DIR__ . '/../Ecommerce/Services/EcommercePaystackService.php';
require_once __DIR__ . '/../Platform/Repositories/SubscriptionRepository.php';
require_once __DIR__ . '/../Platform/Services/EntitlementService.php';
require_once __DIR__ . '/../Ecommerce/Repositories/EcommerceCatalogRepository.php';
require_once __DIR__ . '/../Ecommerce/Services/EcommerceCartService.php';
require_once __DIR__ . '/../Ecommerce/Services/EcommerceOrderService.php';
require_once __DIR__ . '/../Ecommerce/Services/EcommerceAccountService.php';
require_once __DIR__ . '/../Ecommerce/Services/EcommerceWishlistService.php';

final class EcommerceApiController
{
    private PDO $db;
    private int $tenantId;
    private int $storeId;
    private EcommerceCartService $cart;
    private EcommerceWishlistService $wishlist;
    private EcommerceOrderService $orders;
    private EcommerceAccountService $accounts;
    private EcommerceCatalogRepository $catalog;
    private EcommercePaystackService $paystack;
    private string $storeCurrency = 'EUR';

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        SaaSPhase15Migrator::ensure($this->db);
        SaaSPhase16Migrator::ensure($this->db);
    }

    public function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $tenant = TenantBootstrap::resolveTenant($this->db, false);
        if (!$tenant) {
            $this->json(['error' => 'tenant_not_found'], 404);
            return;
        }
        $this->tenantId = (int) $tenant['id'];

        try {
            (new EntitlementService($this->db, new SubscriptionRepository($this->db)))->assertModule($this->tenantId, 'ecommerce');
        } catch (RuntimeException) {
            $this->json(['error' => 'ecommerce_not_enabled'], 403);
            return;
        }

        $this->catalog = new EcommerceCatalogRepository($this->db);
        $settings = $this->catalog->getSettings($this->tenantId);
        $defaultStoreId = (int) ($settings['default_store_id'] ?? 0);
        if ($defaultStoreId <= 0) {
            $defaultStoreId = $this->catalog->defaultStoreId($this->tenantId);
        }
        $stores = $this->catalog->listStores($this->tenantId);
        $sessionKey = 'ecommerce_store_' . $this->tenantId;
        $requestedStoreId = isset($_GET['store_id']) ? (int) $_GET['store_id'] : null;
        $sessionStoreId = (int) ($_SESSION[$sessionKey] ?? 0);
        $this->storeId = $this->catalog->pickStoreId(
            $this->tenantId,
            $defaultStoreId,
            $stores,
            $requestedStoreId,
            $sessionStoreId > 0 ? $sessionStoreId : null
        );
        if ($this->storeId <= 0) {
            $this->json(['error' => 'store_not_configured'], 503);
            return;
        }
        if ($sessionStoreId > 0 && $sessionStoreId !== $this->storeId) {
            unset($_SESSION['ecommerce_cart']);
        }
        $_SESSION[$sessionKey] = $this->storeId;
        $this->cart = new EcommerceCartService($this->db, $this->storeId);
        $this->wishlist = new EcommerceWishlistService($this->db, $this->tenantId, $this->storeId);
        $this->orders = new EcommerceOrderService($this->db);
        $this->accounts = new EcommerceAccountService($this->db);
        $this->paystack = new EcommercePaystackService($this->db, $this->catalog);
        $this->storeCurrency = strtoupper((string) ($settings['currency'] ?? 'EUR'));

        $path = trim($_GET['route'] ?? '', '/');
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($path === 'products' && $method === 'GET') {
            $this->listProducts();
            return;
        }
        if ($path === 'search/suggest' && $method === 'GET') {
            $this->searchSuggest();
            return;
        }
        if ($path === 'cart' && $method === 'GET') {
            $this->json(['items' => array_values($this->cart->items()), 'subtotal' => $this->cart->subtotal(), 'count' => $this->cart->count()]);
            return;
        }
        if ($path === 'cart/add' && $method === 'POST') {
            $data = $this->body();
            $this->cart->add((int) ($data['product_id'] ?? 0), (int) ($data['quantity'] ?? 1));
            $this->json(['ok' => true, 'count' => $this->cart->count()]);
            return;
        }
        if ($path === 'cart/update' && $method === 'POST') {
            $data = $this->body();
            $this->cart->update((int) ($data['product_id'] ?? 0), (int) ($data['quantity'] ?? 0));
            $this->json(['ok' => true, 'count' => $this->cart->count(), 'subtotal' => $this->cart->subtotal()]);
            return;
        }
        if ($path === 'cart/remove' && $method === 'POST') {
            $data = $this->body();
            $this->cart->remove((int) ($data['product_id'] ?? 0));
            $this->json(['ok' => true, 'count' => $this->cart->count()]);
            return;
        }
        if ($path === 'cart/clear' && $method === 'POST') {
            $this->cart->clear();
            $this->json(['ok' => true, 'count' => 0]);
            return;
        }
        if ($path === 'wishlist/toggle' && $method === 'POST') {
            $data = $this->body();
            $accountId = (int) ($_SESSION['ecommerce_account_id'] ?? 0);
            $added = $this->wishlist->toggle((int) ($data['product_id'] ?? 0), $accountId ?: null);
            $this->json(['ok' => true, 'added' => $added, 'count' => $this->wishlist->count($accountId ?: null)]);
            return;
        }
        if ($path === 'checkout' && $method === 'POST') {
            $this->checkout();
            return;
        }
        if ($path === 'checkout/paystack-init' && $method === 'POST') {
            $this->paystackInit();
            return;
        }
        if ($path === 'checkout/paystack-verify' && $method === 'POST') {
            $this->paystackVerify();
            return;
        }

        $this->json(['error' => 'not_found'], 404);
    }

    private function listProducts(): void
    {
        $filters = ['q' => trim($_GET['q'] ?? '')];
        if (!empty($_GET['category_id'])) {
            $filters['category_id'] = (int) $_GET['category_id'];
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(48, max(1, (int) ($_GET['limit'] ?? 24)));
        $offset = ($page - 1) * $limit;
        $items = $this->catalog->listProducts($this->tenantId, $this->storeId, $filters, $limit, $offset);
        $this->json([
            'items' => $items,
            'total' => $this->catalog->countProducts($this->tenantId, $this->storeId, $filters),
            'page' => $page,
        ]);
    }

    private function searchSuggest(): void
    {
        $q = trim($_GET['q'] ?? '');
        if (mb_strlen($q) < 2) {
            $this->json(['items' => [], 'total' => 0, 'q' => $q]);
            return;
        }

        $filters = ['q' => $q];
        $limit = min(8, max(1, (int) ($_GET['limit'] ?? 8)));
        $items = $this->catalog->listProducts($this->tenantId, $this->storeId, $filters, $limit, 0);
        $total = $this->catalog->countProducts($this->tenantId, $this->storeId, $filters);
        $lang = $_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en');

        $suggestions = [];
        foreach ($items as $product) {
            $slug = !empty($product['slug']) ? (string) $product['slug'] : 'p-' . (int) $product['id'];
            $suggestions[] = [
                'id' => (int) ($product['id'] ?? 0),
                'name' => (string) ($product['name'] ?? ''),
                'slug' => $slug,
                'sku' => (string) ($product['sku'] ?? ''),
                'price' => (float) ($product['price'] ?? 0),
                'price_label' => CurrencyHelper::format((float) ($product['price'] ?? 0), $this->storeCurrency, $lang),
                'category_name' => (string) ($product['category_name'] ?? ''),
                'image_url' => resolve_product_image_url($product['image_url'] ?? null),
                'stock_quantity' => max(0, (int) ($product['stock_quantity'] ?? 0)),
            ];
        }

        $this->json([
            'items' => $suggestions,
            'total' => $total,
            'q' => $q,
        ]);
    }

    private function checkout(): void
    {
        $items = array_values($this->cart->items());
        if ($items === []) {
            $this->json(['error' => 'empty_cart'], 422);
            return;
        }
        [$total, $tax] = $this->checkoutTotals();
        $data = $this->body();
        $payment = (string) ($data['payment_method'] ?? 'card');

        try {
            [$customerId] = $this->resolveCheckoutCustomer($data);
            $result = $this->orders->placeOrder(
                $this->tenantId,
                $this->storeId,
                $items,
                $total,
                $tax,
                $customerId,
                $payment
            );
            $this->cart->clear();
            $this->json(['ok' => true, 'order' => $result]);
        } catch (InvalidArgumentException $e) {
            $this->json(['error' => 'validation_failed', 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->json(['error' => 'checkout_failed', 'message' => $e->getMessage()], 500);
        }
    }

    private function paystackInit(): void
    {
        if (!$this->paystack->isEnabled($this->tenantId)) {
            $this->json(['error' => 'paystack_not_configured'], 422);
            return;
        }

        $items = array_values($this->cart->items());
        if ($items === []) {
            $this->json(['error' => 'empty_cart'], 422);
            return;
        }

        $data = $this->body();
        $payment = (string) ($data['payment_method'] ?? 'card');
        if (!in_array($payment, ['card', 'mobile_money'], true)) {
            $this->json(['error' => 'invalid_payment_method'], 422);
            return;
        }

        $accountId = (int) ($_SESSION['ecommerce_account_id'] ?? 0);
        $ecomAccount = $accountId > 0 ? $this->accounts->findById($this->tenantId, $accountId) : null;
        $phone = '';
        $customerPayload = [];

        if ($ecomAccount) {
            $phone = (string) ($ecomAccount['phone'] ?? '');
        } else {
            $phone = trim((string) ($data['phone'] ?? ''));
            $name = trim((string) ($data['name'] ?? ''));

            if ($name === '') {
                $this->json(['error' => 'name_required'], 422);
                return;
            }
            if ($phone === '') {
                $this->json(['error' => 'phone_required'], 422);
                return;
            }

            $customerPayload = [
                'email' => trim((string) ($data['email'] ?? '')),
                'phone' => $phone,
                'name' => $name,
            ];
        }

        try {
            $email = EcommerceAccountService::paystackEmail(
                $ecomAccount ? (string) ($ecomAccount['email'] ?? '') : (string) ($data['email'] ?? ''),
                $this->tenantId,
                $phone
            );
        } catch (InvalidArgumentException $e) {
            $this->json(['error' => 'email_invalid', 'message' => $e->getMessage()], 422);
            return;
        }

        [$total, $tax] = $this->checkoutTotals();

        try {
            [$customerId] = $this->resolveCheckoutCustomer($customerPayload);
            $reference = 'ECOM-' . $this->tenantId . '-' . strtoupper(bin2hex(random_bytes(6)));
            $result = $this->orders->createPaystackPendingOrder(
                $this->tenantId,
                $this->storeId,
                $items,
                $total,
                $tax,
                $customerId,
                $payment,
                $reference
            );

            $callbackUrl = $this->paystackCallbackUrl();
            $channels = $payment === 'mobile_money' ? ['mobile_money'] : ['card'];
            $currency = $this->paystack->resolveCurrency($this->tenantId, $this->storeCurrency);
            $amountMinor = $this->paystack->amountToMinorUnits($total, $currency);
            $metadata = [
                'sale_id' => (string) $result['sale_id'],
                'tenant_id' => (string) $this->tenantId,
                'payment_method' => $payment,
            ];

            $init = $this->paystack->initializeCheckout($this->tenantId, [
                'email' => $email,
                'amount' => $total,
                'currency' => $this->storeCurrency,
                'reference' => $reference,
                'callback_url' => $callbackUrl,
                'channels' => $channels,
                'metadata' => $metadata,
            ]);

            $_SESSION['ecom_paystack_sale_id'] = (int) $result['sale_id'];
            $this->cart->clear();

            $this->json([
                'ok' => true,
                'pop' => [
                    'public_key' => $this->paystack->publicKey($this->tenantId),
                    'email' => $email,
                    'amount' => $amountMinor,
                    'currency' => $currency,
                    'reference' => $reference,
                    'channels' => $channels,
                    'metadata' => $metadata,
                ],
                'redirect_url' => (string) ($init['authorization_url'] ?? ''),
                'sale_id' => (int) $result['sale_id'],
                'callback_url' => $callbackUrl,
            ]);
        } catch (Throwable $e) {
            $this->json(['error' => 'paystack_init_failed', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array{0:?int, 1:int}
     */
    private function resolveCheckoutCustomer(array $data): array
    {
        $accountId = (int) ($_SESSION['ecommerce_account_id'] ?? 0);
        $ecomAccount = $accountId > 0 ? $this->accounts->findById($this->tenantId, $accountId) : null;

        if ($ecomAccount) {
            $customerId = (int) ($ecomAccount['customer_id'] ?? 0);

            return [$customerId > 0 ? $customerId : null, $accountId];
        }

        $guest = $this->accounts->ensureGuestFromCheckout(
            $this->tenantId,
            (string) ($data['checkout_email'] ?? $data['email'] ?? ''),
            (string) ($data['checkout_phone'] ?? $data['phone'] ?? ''),
            (string) ($data['checkout_name'] ?? $data['name'] ?? ''),
        );

        if ($guest['account_id'] > 0) {
            $_SESSION['ecommerce_account_id'] = $guest['account_id'];
        }

        return [$guest['customer_id'], $guest['account_id']];
    }

    private function paystackVerify(): void
    {
        if (!$this->paystack->isEnabled($this->tenantId)) {
            $this->json(['error' => 'paystack_not_configured'], 422);
            return;
        }

        $data = $this->body();
        $reference = trim((string) ($data['reference'] ?? ''));
        if ($reference === '') {
            $this->json(['error' => 'reference_required'], 422);
            return;
        }

        try {
            $result = $this->orders->completePaystackPayment($reference, $this->tenantId, $this->storeId, $this->paystack);
            unset($_SESSION['ecom_paystack_sale_id']);
            $this->json([
                'ok' => true,
                'sale_id' => (int) ($result['sale_id'] ?? 0),
                'status' => (string) ($result['status'] ?? 'completed'),
            ]);
        } catch (Throwable $e) {
            $this->json(['error' => 'paystack_verify_failed', 'message' => $e->getMessage()], 500);
        }
    }

    /** @return array{0: float, 1: float} */
    private function checkoutTotals(): array
    {
        $subtotal = $this->cart->subtotal();
        $settings = $this->catalog->getSettings($this->tenantId);
        $taxRate = (float) ($settings['tax_rate'] ?? 0);
        $tax = round($subtotal * ($taxRate / 100), 2);

        return [$subtotal + $tax, $tax];
    }

    private function paystackCallbackUrl(): string
    {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/public/e-commerce/api/index.php');
        $checkoutDir = preg_replace('#/api/index\.php$#', '/checkout', $script) ?? '/public/e-commerce/checkout';

        return rtrim(request_app_base_url(), '/') . $checkoutDir . '/paystack-callback.php';
    }

    /** @return array<string, mixed> */
    private function body(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        return is_array($data) ? $data : ($_POST ?: []);
    }

    /** @param array<string, mixed> $data */
    private function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
