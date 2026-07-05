<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Platform/Services/PaystackService.php';
require_once __DIR__ . '/../Repositories/EcommerceCatalogRepository.php';

/**
 * Tenant-scoped Paystack checkout for the e-commerce storefront.
 */
final class EcommercePaystackService
{
    /** @var list<string> */
    private const SUPPORTED_CURRENCIES = ['NGN', 'USD', 'GHS', 'ZAR', 'KES'];

    public function __construct(
        private PDO $db,
        private EcommerceCatalogRepository $catalog
    ) {
    }

    public function isEnabled(int $tenantId): bool
    {
        $settings = $this->catalog->getSettings($tenantId) ?? [];
        if (empty($settings['paystack_enabled'])) {
            return false;
        }

        return $this->client($tenantId)->isConfigured() && $this->publicKey($tenantId) !== '';
    }

    public function publicKey(int $tenantId): string
    {
        $settings = $this->catalog->getSettings($tenantId) ?? [];
        $key = trim((string) ($settings['paystack_public_key'] ?? ''));
        if ($key === '' && defined('PAYSTACK_PUBLIC_KEY')) {
            $key = trim((string) PAYSTACK_PUBLIC_KEY);
        }

        return $key;
    }

    public function client(int $tenantId): PaystackService
    {
        $settings = $this->catalog->getSettings($tenantId) ?? [];
        $secret = trim((string) ($settings['paystack_secret_key'] ?? ''));
        if ($secret === '' && defined('PAYSTACK_SECRET_KEY')) {
            $secret = trim((string) PAYSTACK_SECRET_KEY);
        }

        return new PaystackService($secret !== '' ? $secret : null);
    }

    public function resolveCurrency(int $tenantId, string $storeCurrency): string
    {
        $settings = $this->catalog->getSettings($tenantId) ?? [];
        $override = strtoupper(trim((string) ($settings['paystack_currency'] ?? '')));
        if ($override !== '' && in_array($override, self::SUPPORTED_CURRENCIES, true)) {
            return $override;
        }

        $storeCurrency = strtoupper(trim($storeCurrency));
        if (in_array($storeCurrency, self::SUPPORTED_CURRENCIES, true)) {
            return $storeCurrency;
        }

        return 'NGN';
    }

    public function amountToMinorUnits(float $amount, string $currency): int
    {
        unset($currency);
        return max((int) round($amount * 100), 100);
    }

    /**
     * @param array{email:string, amount:float, currency:string, reference:string, callback_url:string, channels?:list<string>, metadata?:array<string, mixed>} $opts
     * @return array<string, mixed>
     */
    public function initializeCheckout(int $tenantId, array $opts): array
    {
        $currency = $this->resolveCurrency($tenantId, (string) ($opts['currency'] ?? 'NGN'));
        $amountMinor = $this->amountToMinorUnits((float) ($opts['amount'] ?? 0), $currency);

        $params = [
            'email' => (string) $opts['email'],
            'amount' => $amountMinor,
            'currency' => $currency,
            'reference' => (string) $opts['reference'],
            'callback_url' => (string) $opts['callback_url'],
            'metadata' => $opts['metadata'] ?? [],
        ];

        if (!empty($opts['channels'])) {
            $params['channels'] = $opts['channels'];
        }

        return $this->client($tenantId)->initializeTransaction($params);
    }

    /** @return array<string, mixed> */
    public function verify(int $tenantId, string $reference): array
    {
        return $this->client($tenantId)->verifyTransaction($reference);
    }

    /** @return list<string> */
    public static function supportedCurrencies(): array
    {
        return self::SUPPORTED_CURRENCIES;
    }
}
