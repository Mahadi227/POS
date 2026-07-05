<?php
declare(strict_types=1);

/**
 * Tenant white-label branding for cashier portal (POS terminal).
 * Reuses admin branding: accent, logo, favicon, organization name, domain.
 */
require __DIR__ . '/../../admin/includes/admin-branding.php';

if (!function_exists('cashier_theme_css_block')) {
    function cashier_theme_css_block(string $accent): string
    {
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $accent)) {
            $accent = '#2563eb';
        }
        $accentEsc = htmlspecialchars($accent, ENT_QUOTES, 'UTF-8');
        $hoverEsc = htmlspecialchars(admin_hex_darken($accent, 0.12), ENT_QUOTES, 'UTF-8');
        $deepEsc = htmlspecialchars(admin_hex_darken($accent, 0.25), ENT_QUOTES, 'UTF-8');
        $soft = admin_hex_rgba($accent, 0.12);
        $softDark = admin_hex_rgba($accent, 0.2);
        $heroShadow = admin_hex_rgba($accent, 0.25);

        $base = admin_theme_css_block($accent);

        return $base . '<style>:root {
    --pc-primary: ' . $accentEsc . ';
    --pc-primary-dark: ' . $hoverEsc . ';
    --pc-primary-soft: ' . $soft . ';
    --cd-accent-deep: ' . $deepEsc . ';
    --cd-hero-shadow: ' . $heroShadow . ';
}
[data-theme="dark"] {
    --pc-primary: ' . $accentEsc . ';
    --pc-primary-dark: ' . $hoverEsc . ';
    --pc-primary-soft: ' . $softDark . ';
    --cd-accent-deep: ' . $deepEsc . ';
    --cd-hero-shadow: rgba(0, 0, 0, 0.35);
}</style>';
    }
}
