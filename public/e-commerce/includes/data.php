<?php
declare(strict_types=1);

/** Demo blog posts when table is empty (per tenant). */
function ecom_seed_blog_if_empty(int $tenantId): void
{
    global $db, $catalog;
    if ($catalog->listBlogPosts($tenantId, 1) !== []) {
        return;
    }
    if (!$db->query("SHOW TABLES LIKE 'ecommerce_blog_posts'")->fetch()) {
        return;
    }
    $posts = [
        ['slug' => 'welcome', 'title' => 'Welcome to our online store', 'excerpt' => 'Discover our catalog synced with our POS.'],
        ['slug' => 'new-arrivals', 'title' => 'New arrivals this week', 'excerpt' => 'Fresh products added from the warehouse.'],
    ];
    $stmt = $db->prepare(
        'INSERT INTO ecommerce_blog_posts (tenant_id, slug, title, excerpt, body, is_published, published_at)
         VALUES (?, ?, ?, ?, ?, 1, NOW())'
    );
    foreach ($posts as $p) {
        try {
            $stmt->execute([$tenantId, $p['slug'], $p['title'], $p['excerpt'], $p['excerpt']]);
        } catch (Throwable) {
            // ignore duplicate
        }
    }
}
