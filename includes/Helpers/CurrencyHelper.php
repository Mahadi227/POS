<?php
declare(strict_types=1);

/**
 * Multi-country currency metadata and formatting (store-scoped).
 */
class CurrencyHelper
{
    /** @var array<string, array{code: string, symbol: string, name: string, locale: string, decimals: int}> */
    private static array $catalog = [
        'FCFA' => ['code' => 'FCFA', 'symbol' => 'FCFA', 'name' => 'West African CFA franc', 'locale' => 'fr-FR', 'decimals' => 0],
        'XOF'  => ['code' => 'XOF',  'symbol' => 'FCFA', 'name' => 'West African CFA franc', 'locale' => 'fr-FR', 'decimals' => 0],
        'XAF'  => ['code' => 'XAF',  'symbol' => 'FCFA', 'name' => 'Central African CFA franc', 'locale' => 'fr-FR', 'decimals' => 0],
        'EUR'  => ['code' => 'EUR',  'symbol' => '€',    'name' => 'Euro', 'locale' => 'fr-FR', 'decimals' => 2],
        'USD'  => ['code' => 'USD',  'symbol' => '$',    'name' => 'US Dollar', 'locale' => 'en-US', 'decimals' => 2],
        'GBP'  => ['code' => 'GBP',  'symbol' => '£',    'name' => 'British Pound', 'locale' => 'en-GB', 'decimals' => 2],
        'NGN'  => ['code' => 'NGN',  'symbol' => '₦',    'name' => 'Nigerian Naira', 'locale' => 'en-NG', 'decimals' => 2],
        'GHS'  => ['code' => 'GHS',  'symbol' => 'GH₵',  'name' => 'Ghanaian Cedi', 'locale' => 'en-GH', 'decimals' => 2],
        'KES'  => ['code' => 'KES',  'symbol' => 'KSh',  'name' => 'Kenyan Shilling', 'locale' => 'en-KE', 'decimals' => 2],
        'MAD'  => ['code' => 'MAD',  'symbol' => 'MAD',  'name' => 'Moroccan Dirham', 'locale' => 'fr-MA', 'decimals' => 2],
        'ZAR'  => ['code' => 'ZAR',  'symbol' => 'R',    'name' => 'South African Rand', 'locale' => 'en-ZA', 'decimals' => 2],
        'CDF'  => ['code' => 'CDF',  'symbol' => 'FC',   'name' => 'Congolese Franc', 'locale' => 'fr-CD', 'decimals' => 0],
    ];

    public static function normalize(?string $code): string
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return 'FCFA';
        }
        if ($code === 'CFA') {
            return 'FCFA';
        }
        return $code;
    }

    /** @return array{code: string, symbol: string, name: string, locale: string, decimals: int} */
    public static function meta(?string $code): array
    {
        $norm = self::normalize($code);
        if (isset(self::$catalog[$norm])) {
            return self::$catalog[$norm];
        }
        return [
            'code' => $norm,
            'symbol' => $norm,
            'name' => $norm,
            'locale' => 'fr-FR',
            'decimals' => 0,
        ];
    }

    /** @return array<string, array{code: string, symbol: string, name: string, locale: string, decimals: int}> */
    public static function catalogForJs(array $codes): array
    {
        $out = [];
        foreach ($codes as $code) {
            $norm = self::normalize($code);
            $out[$norm] = self::meta($norm);
        }
        return $out;
    }

    public static function format(float $amount, ?string $code, ?string $lang = null): string
    {
        $meta = self::meta($code);
        $locale = $meta['locale'];
        if ($lang === 'en') {
            $locale = str_starts_with($meta['code'], 'USD') || in_array($meta['code'], ['USD', 'GBP', 'NGN', 'GHS', 'KES', 'ZAR'], true)
                ? 'en-US' : $locale;
        }
        $formatted = number_format($amount, $meta['decimals'], $lang === 'en' ? '.' : ',', $lang === 'en' ? ',' : ' ');
        return $formatted . ' ' . $meta['symbol'];
    }

    /** @return array{currency: string, meta: array, store_id: ?int, store_name: string, is_global_view: bool} */
    public static function portalContext(PDO $db, ?int $storeId, bool $isGlobalView): array
    {
        $currency = 'FCFA';
        $storeName = '';
        if ($storeId) {
            $stmt = $db->prepare(
                'SELECT id, name, currency FROM stores WHERE id = ? AND deleted_at IS NULL LIMIT 1'
            );
            $stmt->execute([$storeId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $currency = self::normalize($row['currency'] ?? 'FCFA');
                $storeName = (string) ($row['name'] ?? '');
            }
        }
        return [
            'currency' => $currency,
            'meta' => self::meta($currency),
            'store_id' => $storeId,
            'store_name' => $storeName,
            'is_global_view' => $isGlobalView,
        ];
    }
}
