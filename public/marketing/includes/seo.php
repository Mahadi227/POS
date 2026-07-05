<?php
declare(strict_types=1);

/**
 * SEO meta tags helper for marketing pages.
 *
 * @param array{
 *   title?: string,
 *   description?: string,
 *   keywords?: string,
 *   canonical?: string,
 *   og_image?: string,
 *   type?: string,
 *   schema?: array<string, mixed>|null
 * } $meta
 */
function mkt_seo_head(array $meta): void
{
    $title = $meta['title'] ?? __t('mkt_site_title', 'marketing');
    $desc = $meta['description'] ?? __t('mkt_site_description', 'marketing');
    $keywords = $meta['keywords'] ?? __t('mkt_site_keywords', 'marketing');
    $canonical = $meta['canonical'] ?? '';
    $ogImage = $meta['og_image'] ?? '';
    $type = $meta['type'] ?? 'website';
    $schema = $meta['schema'] ?? null;

    echo '<meta name="description" content="' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '<meta name="keywords" content="' . htmlspecialchars($keywords, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '<meta name="robots" content="index, follow">' . "\n";
    echo '<meta name="author" content="RetailPOS">' . "\n";

    if ($canonical !== '') {
        echo '<link rel="canonical" href="' . htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    }

    echo '<meta property="og:title" content="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '<meta property="og:description" content="' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '<meta property="og:type" content="' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '<meta property="og:site_name" content="RetailPOS Cloud">' . "\n";
    if ($canonical !== '') {
        echo '<meta property="og:url" content="' . htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    }
    if ($ogImage !== '') {
        echo '<meta property="og:image" content="' . htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    }

    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '<meta name="twitter:description" content="' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '">' . "\n";

    if ($schema !== null) {
        echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }
}

function mkt_org_schema(): array
{
    return [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'RetailPOS Cloud',
        'url' => mkt_url(),
        'logo' => mkt_url('index.php'),
        'description' => __t('mkt_site_description', 'marketing'),
        'contactPoint' => [
            '@type' => 'ContactPoint',
            'contactType' => 'customer support',
            'email' => 'support@retailpos.local',
            'availableLanguage' => ['English', 'French'],
        ],
    ];
}

function mkt_product_schema(): array
{
    return [
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => 'RetailPOS Cloud',
        'applicationCategory' => 'BusinessApplication',
        'operatingSystem' => 'Web, Windows, Android, iOS',
        'offers' => [
            '@type' => 'Offer',
            'price' => '29',
            'priceCurrency' => 'EUR',
        ],
    ];
}
