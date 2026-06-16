<?php

declare(strict_types=1);

/**
 * Build browser-facing URLs using the current request host (fixes mobile LAN vs localhost).
 */
function encode_url_path(string $path): string
{
    $segments = array_values(array_filter(
        explode('/', ltrim(str_replace('\\', '/', $path), '/')),
        static fn ($s) => $s !== ''
    ));

    return implode('/', array_map('rawurlencode', $segments));
}

function request_app_base_url(): string
{
    if (empty($_SERVER['HTTP_HOST'])) {
        return rtrim(APP_URL, '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $path = parse_url(APP_URL, PHP_URL_PATH) ?? '';
    $encoded = ($path !== '' && $path !== '/') ? '/' . encode_url_path($path) : '';

    return $scheme . '://' . $_SERVER['HTTP_HOST'] . $encoded;
}

function resolve_product_image_url(?string $stored): ?string
{
    if ($stored === null || trim($stored) === '') {
        return null;
    }

    if (preg_match('#^https?://#i', $stored) || strpos($stored, 'data:image') === 0) {
        return rebase_localhost_url($stored);
    }

    $normalized = str_replace('\\', '/', $stored);
    $normalized = preg_replace('#^(\.\./)+#', '', $normalized);
    $normalized = ltrim($normalized, '/');
    if (strpos($normalized, 'public/') === 0) {
        $normalized = substr($normalized, 7);
    }

    $filename = basename($normalized);
    if ($filename === '' || $filename === '.' || $filename === '..') {
        return null;
    }

    $relativePath = 'uploads/products/' . $filename;
    $physical = __DIR__ . '/../../public/' . $relativePath;
    if (!is_file($physical)) {
        $legacy = __DIR__ . '/../../public/' . $normalized;
        if (is_file($legacy)) {
            $relativePath = $normalized;
        }
    }

    $base = str_replace(' ', '%20', request_app_base_url());
    $segments = explode('/', 'public/' . $relativePath);
    $encoded = implode('/', array_map('rawurlencode', $segments));

    return $base . '/' . $encoded;
}

function rebase_localhost_url(string $url): string
{
    if (empty($_SERVER['HTTP_HOST'])) {
        return $url;
    }

    $parts = parse_url($url);
    if (!$parts || empty($parts['host'])) {
        return $url;
    }

    $host = strtolower($parts['host']);
    if ($host !== 'localhost' && $host !== '127.0.0.1') {
        return $url;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $path = $parts['path'] ?? '';
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';

    return $scheme . '://' . $_SERVER['HTTP_HOST'] . $path . $query;
}
