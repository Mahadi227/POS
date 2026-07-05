<?php
declare(strict_types=1);

require_once __DIR__ . '/../Repositories/SubscriptionRepository.php';

/**
 * Loads subscription plans for the public marketing site from the database,
 * with a static fallback when the catalog is unavailable.
 */
final class MarketingPricingService
{
    /** @var array<int, array<string, mixed>>|null */
    private static ?array $cache = null;

    private const ANNUAL_DISCOUNT = 0.85;

    private const CODE_ALIASES = [
        'business' => 'professional',
    ];

    /** Marketing feature keys that may appear directly in modules_json */
    private const MARKETING_MODULE_KEYS = [
        'pos', 'inventory', 'warehouse', 'accounting', 'hr', 'crm', 'reports', 'ecommerce', 'api',
    ];

    public static function annualDiscount(): float
    {
        return self::ANNUAL_DISCOUNT;
    }

    public static function discountedMonthly(float $monthly): float
    {
        return round($monthly * self::ANNUAL_DISCOUNT, 2);
    }

    public static function annualTotal(float $monthly): float
    {
        return round($monthly * 12 * self::ANNUAL_DISCOUNT, 2);
    }

    private const DB_MODULE_MAP = [
        'pos' => 'pos',
        'inventory' => 'inventory',
        'warehouse' => 'warehouse',
        'accounting' => 'accounting',
        'hr' => 'hr',
        'crm' => 'crm',
        'reports' => 'reports',
        'ecommerce' => 'ecommerce',
        'api_access' => 'api',
    ];

    public static function bootstrap(PDO $db): void
    {
        self::$cache = null;
        self::plans($db);
    }

    public static function resetCache(): void
    {
        self::$cache = null;
    }

    /** @return array<int, array<string, mixed>> */
    public static function plans(?PDO $db = null): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        if ($db !== null) {
            $loaded = self::loadFromDatabase($db);
            if ($loaded !== []) {
                self::$cache = $loaded;
                return self::$cache;
            }
        }

        self::$cache = self::fallbackPlans();
        return self::$cache;
    }

    public static function planLabelKey(array $plan): string
    {
        $code = (string) ($plan['marketing_code'] ?? $plan['code'] ?? '');
        return 'mkt_plan_' . $code;
    }

    public static function formatPrice(float $price, string $currency = 'EUR'): string
    {
        $symbols = ['EUR' => '€', 'USD' => '$', 'XOF' => 'CFA ', 'GBP' => '£'];
        $symbol = $symbols[strtoupper($currency)] ?? (strtoupper($currency) . ' ');
        $formatted = number_format($price, fmod($price, 1.0) === 0.0 ? 0 : 2, '.', ',');
        if (str_ends_with($symbol, ' ')) {
            return $symbol . $formatted;
        }
        return $symbol . $formatted;
    }

    public static function signupUrl(string $publicRoot, string $planCode): string
    {
        return $publicRoot . 'signup-organization.php?plan_code=' . rawurlencode($planCode);
    }

    public static function planHasFeature(array $plan, string $feature): bool
    {
        $modules = $plan['modules'] ?? [];
        $marketingCode = (string) ($plan['marketing_code'] ?? $plan['code'] ?? '');

        if ($feature === 'offline') {
            return true;
        }
        if (in_array($feature, $modules, true)) {
            return true;
        }
        if ($feature === 'multistore') {
            $stores = $plan['stores'] ?? null;
            return $stores === null || (int) $stores > 1;
        }
        if ($feature === 'analytics') {
            return in_array('reports', $modules, true)
                && in_array($marketingCode, ['professional', 'enterprise'], true);
        }
        if ($feature === 'support') {
            return $marketingCode !== 'starter';
        }
        return false;
    }

    /** @return array<int, array<string, mixed>> */
    private static function loadFromDatabase(PDO $db): array
    {
        try {
            $repo = new SubscriptionRepository($db);
            $rows = $repo->listActivePlans();
        } catch (Throwable) {
            return [];
        }

        if ($rows === []) {
            return [];
        }

        $plans = [];
        foreach ($rows as $row) {
            $dbCode = (string) ($row['code'] ?? '');
            if ($dbCode === '') {
                continue;
            }
            $modulesRaw = json_decode((string) ($row['modules_json'] ?? '{}'), true);
            $modulesRaw = is_array($modulesRaw) ? $modulesRaw : [];
            $plans[] = [
                'id' => (int) ($row['id'] ?? 0),
                'code' => $dbCode,
                'marketing_code' => self::CODE_ALIASES[$dbCode] ?? $dbCode,
                'name' => (string) ($row['name'] ?? $dbCode),
                'price' => (float) ($row['price_monthly'] ?? 0),
                'currency' => strtoupper((string) ($row['currency'] ?? 'EUR')),
                'stores' => isset($row['max_stores']) && $row['max_stores'] !== null
                    ? (int) $row['max_stores'] : null,
                'users' => isset($row['max_users']) && $row['max_users'] !== null
                    ? (int) $row['max_users'] : null,
                'featured' => $dbCode === 'business',
                'modules' => self::mapModules($modulesRaw),
                'modules_raw' => $modulesRaw,
            ];
        }

        usort($plans, static fn(array $a, array $b): int => $a['price'] <=> $b['price']);

        if ($plans !== [] && !in_array(true, array_column($plans, 'featured'), true)) {
            $mid = (int) floor((count($plans) - 1) / 2);
            $plans[$mid]['featured'] = true;
        }

        return $plans;
    }

    /** @param array<string, bool> $modulesRaw */
    private static function mapModules(array $modulesRaw): array
    {
        $out = [];
        foreach ($modulesRaw as $key => $enabled) {
            if (!$enabled) {
                continue;
            }
            $mapped = self::DB_MODULE_MAP[$key] ?? null;
            if ($mapped === null && in_array($key, self::MARKETING_MODULE_KEYS, true)) {
                $mapped = $key;
            }
            if ($mapped !== null && !in_array($mapped, $out, true)) {
                $out[] = $mapped;
            }
        }

        $order = ['pos', 'inventory', 'warehouse', 'accounting', 'hr', 'crm', 'ecommerce', 'reports', 'api'];
        usort($out, static function (string $a, string $b) use ($order): int {
            $ia = array_search($a, $order, true);
            $ib = array_search($b, $order, true);
            return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib);
        });

        return $out;
    }

    /** @return array<int, array<string, mixed>> */
    private static function fallbackPlans(): array
    {
        return [
            [
                'id' => 0,
                'code' => 'starter',
                'marketing_code' => 'starter',
                'name' => 'Starter',
                'price' => 29.0,
                'currency' => 'EUR',
                'stores' => 1,
                'users' => 5,
                'featured' => false,
                'modules' => ['pos', 'inventory', 'reports'],
                'modules_raw' => [],
            ],
            [
                'id' => 0,
                'code' => 'business',
                'marketing_code' => 'professional',
                'name' => 'Professional',
                'price' => 99.0,
                'currency' => 'EUR',
                'stores' => 5,
                'users' => 25,
                'featured' => true,
                'modules' => ['pos', 'inventory', 'warehouse', 'accounting', 'crm', 'reports', 'ecommerce'],
                'modules_raw' => [],
            ],
            [
                'id' => 0,
                'code' => 'enterprise',
                'marketing_code' => 'enterprise',
                'name' => 'Enterprise',
                'price' => 299.0,
                'currency' => 'EUR',
                'stores' => null,
                'users' => null,
                'featured' => false,
                'modules' => ['pos', 'inventory', 'warehouse', 'accounting', 'hr', 'crm', 'reports', 'ecommerce', 'api'],
                'modules_raw' => [],
            ],
        ];
    }
}
