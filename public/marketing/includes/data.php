<?php
declare(strict_types=1);

/**
 * Structured marketing site content.
 * Text labels reference i18n keys where possible.
 */

function mkt_nav_items(): array
{
    return [
        ['href' => 'features.php', 'label' => 'mkt_nav_features'],
        ['href' => 'solutions.php', 'label' => 'mkt_nav_solutions'],
        ['href' => 'industries.php', 'label' => 'mkt_nav_industries'],
        ['href' => 'pricing.php', 'label' => 'mkt_nav_pricing'],
        ['href' => 'integrations.php', 'label' => 'mkt_nav_integrations'],
        ['href' => 'resources/index.php', 'label' => 'mkt_nav_resources'],
    ];
}

function mkt_feature_modules(): array
{
    return [
        ['icon' => 'point_of_sale', 'key' => 'pos', 'color' => '#4f46e5'],
        ['icon' => 'inventory_2', 'key' => 'inventory', 'color' => '#0891b2'],
        ['icon' => 'warehouse', 'key' => 'warehouse', 'color' => '#7c3aed'],
        ['icon' => 'account_balance', 'key' => 'accounting', 'color' => '#059669'],
        ['icon' => 'groups', 'key' => 'hr', 'color' => '#d97706'],
        ['icon' => 'contacts', 'key' => 'crm', 'color' => '#db2777'],
        ['icon' => 'local_shipping', 'key' => 'fleet', 'color' => '#2563eb'],
        ['icon' => 'shopping_cart', 'key' => 'procurement', 'color' => '#65a30d'],
        ['icon' => 'notifications', 'key' => 'notifications', 'color' => '#ea580c'],
        ['icon' => 'assessment', 'key' => 'reports', 'color' => '#4f46e5'],
        ['icon' => 'storefront', 'key' => 'ecommerce', 'color' => '#e11d48', 'highlights' => ['h1', 'h2', 'h3']],
    ];
}

function mkt_module_highlights(array $mod): array
{
    return $mod['highlights'] ?? ['cloud', 'offline', 'security'];
}

function mkt_module_highlight_label(array $mod, string $highlight): string
{
    $key = $mod['key'];
    if (in_array($highlight, ['cloud', 'offline', 'security', 'multistore', 'ecommerce'], true)) {
        return __t('mkt_benefit_' . $highlight, 'marketing');
    }
    return __t('mkt_feat_' . $key . '_' . $highlight, 'marketing');
}

function mkt_ecommerce_flow_steps(): array
{
    return ['catalog', 'storefront', 'pos'];
}

function mkt_ecommerce_payment_methods(): array
{
    return ['card', 'mobile', 'cod'];
}

function mkt_feature_module_by_key(string $key): ?array
{
    foreach (mkt_feature_modules() as $mod) {
        if ($mod['key'] === $key) {
            return $mod;
        }
    }
    return null;
}

function mkt_feature_modules_except(string $excludeKey): array
{
    return array_values(array_filter(
        mkt_feature_modules(),
        static fn(array $mod): bool => $mod['key'] !== $excludeKey
    ));
}

function mkt_industries(): array
{
    return [
        ['icon' => 'storefront', 'key' => 'supermarket'],
        ['icon' => 'medication', 'key' => 'pharmacy'],
        ['icon' => 'checkroom', 'key' => 'boutique'],
        ['icon' => 'restaurant', 'key' => 'restaurant'],
        ['icon' => 'hardware', 'key' => 'hardware'],
        ['icon' => 'devices', 'key' => 'electronics'],
        ['icon' => 'styler', 'key' => 'fashion'],
        ['icon' => 'local_shipping', 'key' => 'wholesale'],
    ];
}

function mkt_benefits(): array
{
    return [
        'offline', 'multistore', 'multiwarehouse', 'ecommerce', 'security', 'cloud', 'mobile',
    ];
}

function mkt_screenshots(): array
{
    return [
        ['key' => 'dashboard', 'gradient' => 'linear-gradient(135deg,#1e1b4b,#312e81)'],
        ['key' => 'pos', 'gradient' => 'linear-gradient(135deg,#0c4a6e,#0369a1)'],
        ['key' => 'warehouse', 'gradient' => 'linear-gradient(135deg,#3b0764,#6b21a8)'],
        ['key' => 'accounting', 'gradient' => 'linear-gradient(135deg,#064e3b,#047857)'],
        ['key' => 'reports', 'gradient' => 'linear-gradient(135deg,#1e3a5f,#2563eb)'],
        ['key' => 'ecommerce', 'gradient' => 'linear-gradient(135deg,#881337,#e11d48)'],
    ];
}

function mkt_pricing_plans(): array
{
    return MarketingPricingService::plans();
}

function mkt_pricing_plan_codes(): array
{
    return array_map(
        static fn(array $plan): string => (string) ($plan['marketing_code'] ?? $plan['code']),
        mkt_pricing_plans()
    );
}

function mkt_format_price(float $price, string $currency = 'EUR'): string
{
    return MarketingPricingService::formatPrice($price, $currency);
}

function mkt_signup_url(string $planCode): string
{
    global $publicRoot;
    return MarketingPricingService::signupUrl($publicRoot ?? '../', $planCode);
}

function mkt_plan_label_key(array $plan): string
{
    return MarketingPricingService::planLabelKey($plan);
}

function mkt_pricing_faq_items(): array
{
    return ['trial', 'pricing', 'ecommerce', 'multistore', 'support', 'integrations'];
}

function mkt_pricing_features(): array
{
    return [
        'pos', 'inventory', 'warehouse', 'accounting', 'hr', 'crm', 'reports', 'ecommerce',
        'offline', 'api', 'support', 'multistore', 'analytics',
    ];
}

function mkt_pricing_preview_features(): array
{
    return ['pos', 'inventory', 'warehouse', 'accounting', 'ecommerce', 'reports', 'offline'];
}

function mkt_plan_has_feature(array $plan, string $feature): bool
{
    return MarketingPricingService::planHasFeature($plan, $feature);
}

function mkt_plan_has_module(string $planCode, string $module): bool
{
    foreach (mkt_pricing_plans() as $plan) {
        $marketingCode = (string) ($plan['marketing_code'] ?? $plan['code']);
        if ($marketingCode === $planCode || (string) $plan['code'] === $planCode) {
            return mkt_plan_has_feature($plan, $module);
        }
    }
    return false;
}

function mkt_plan_by_marketing_code(string $planCode): ?array
{
    foreach (mkt_pricing_plans() as $plan) {
        $marketingCode = (string) ($plan['marketing_code'] ?? $plan['code']);
        if ($marketingCode === $planCode || (string) $plan['code'] === $planCode) {
            return $plan;
        }
    }
    return null;
}

function mkt_integrations(): array
{
    return [
        ['icon' => 'credit_card', 'key' => 'stripe', 'color' => '#635bff'],
        ['icon' => 'payments', 'key' => 'paypal', 'color' => '#003087'],
        ['icon' => 'phone_android', 'key' => 'mtn_momo', 'color' => '#ffcc00'],
        ['icon' => 'phone_android', 'key' => 'orange_money', 'color' => '#ff6600'],
        ['icon' => 'account_balance_wallet', 'key' => 'wave', 'color' => '#1dc8f2'],
        ['icon' => 'chat', 'key' => 'whatsapp', 'color' => '#25d366'],
        ['icon' => 'mail', 'key' => 'email', 'color' => '#4f46e5'],
    ];
}

function mkt_trusted_logos(): array
{
    return ['TCL Hub', 'Demo Shop', 'Rayunkamshi', 'Mahadi Global', 'FreshMart'];
}

function mkt_testimonials(): array
{
    return [
        ['name' => 'Amadou Diallo', 'role' => 'mkt_testimonial_1_role', 'company' => 'FreshMart Dakar', 'quote' => 'mkt_testimonial_1_quote', 'rating' => 5],
        ['name' => 'Marie Kouassi', 'role' => 'mkt_testimonial_2_role', 'company' => 'PharmaPlus CI', 'quote' => 'mkt_testimonial_2_quote', 'rating' => 5],
        ['name' => 'Jean-Pierre N.', 'role' => 'mkt_testimonial_3_role', 'company' => 'ElectroCity', 'quote' => 'mkt_testimonial_3_quote', 'rating' => 5],
    ];
}

function mkt_faq_items(): array
{
    return [
        'trial', 'offline', 'migration', 'security', 'pricing', 'support', 'integrations', 'multistore',
    ];
}

function mkt_blog_posts(): array
{
    return [
        ['slug' => 'offline-pos-africa', 'date' => '2026-06-15', 'category' => 'mkt_blog_cat_retail', 'title' => 'mkt_blog_post_1_title', 'excerpt' => 'mkt_blog_post_1_excerpt'],
        ['slug' => 'inventory-best-practices', 'date' => '2026-06-01', 'category' => 'mkt_blog_cat_inventory', 'title' => 'mkt_blog_post_2_title', 'excerpt' => 'mkt_blog_post_2_excerpt'],
        ['slug' => 'digital-transformation-sme', 'date' => '2026-05-20', 'category' => 'mkt_blog_cat_digital', 'title' => 'mkt_blog_post_3_title', 'excerpt' => 'mkt_blog_post_3_excerpt'],
        ['slug' => 'accounting-for-retail', 'date' => '2026-05-05', 'category' => 'mkt_blog_cat_accounting', 'title' => 'mkt_blog_post_4_title', 'excerpt' => 'mkt_blog_post_4_excerpt'],
        ['slug' => 'unified-online-store-pos', 'date' => '2026-06-28', 'category' => 'mkt_blog_cat_ecommerce', 'title' => 'mkt_blog_post_5_title', 'excerpt' => 'mkt_blog_post_5_excerpt'],
    ];
}

function mkt_case_studies(): array
{
    return [
        ['slug' => 'tcl-hub', 'company' => 'TCL Hub', 'industry' => 'electronics', 'result' => 'mkt_case_1_result', 'summary' => 'mkt_case_1_summary'],
        ['slug' => 'freshmart', 'company' => 'FreshMart', 'industry' => 'supermarket', 'result' => 'mkt_case_2_result', 'summary' => 'mkt_case_2_summary'],
        ['slug' => 'pharmaplus', 'company' => 'PharmaPlus', 'industry' => 'pharmacy', 'result' => 'mkt_case_3_result', 'summary' => 'mkt_case_3_summary'],
    ];
}

function mkt_doc_sections(): array
{
    return [
        ['icon' => 'rocket_launch', 'key' => 'getting_started'],
        ['icon' => 'point_of_sale', 'key' => 'pos_guide'],
        ['icon' => 'warehouse', 'key' => 'warehouse_guide'],
        ['icon' => 'account_balance', 'key' => 'accounting_guide'],
        ['icon' => 'api', 'key' => 'api_guide'],
        ['icon' => 'storefront', 'key' => 'ecommerce_guide'],
        ['icon' => 'help', 'key' => 'troubleshooting'],
    ];
}

function mkt_support_channels(): array
{
    return [
        ['icon' => 'mail', 'key' => 'email'],
        ['icon' => 'chat', 'key' => 'whatsapp'],
        ['icon' => 'phone', 'key' => 'phone'],
        ['icon' => 'menu_book', 'key' => 'docs'],
        ['icon' => 'forum', 'key' => 'community'],
    ];
}

function mkt_social_links(): array
{
    return [
        ['icon' => 'language', 'label' => 'LinkedIn', 'href' => '#'],
        ['icon' => 'tag', 'label' => 'Twitter/X', 'href' => '#'],
        ['icon' => 'photo_camera', 'label' => 'Instagram', 'href' => '#'],
        ['icon' => 'play_circle', 'label' => 'YouTube', 'href' => '#'],
    ];
}
