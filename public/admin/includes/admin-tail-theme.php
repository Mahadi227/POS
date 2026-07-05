<?php
declare(strict_types=1);

/**
 * Tenant accent overrides — include after admin.css / page stylesheets.
 */
if (!isset($adminAccent)) {
    require __DIR__ . '/admin-branding.php';
}
echo admin_theme_css_block($adminAccent);
