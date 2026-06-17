<?php
declare(strict_types=1);

/**
 * RBAC language middleware — delegates to languages/LanguageMiddleware.php.
 */
class LanguageMiddleware
{
    public static function bootstrap(bool $skipBrowser = false): void
    {
        if ($skipBrowser && !defined('I18N_SKIP_BROWSER_LANG')) {
            define('I18N_SKIP_BROWSER_LANG', true);
        }
        require_once __DIR__ . '/../../languages/LanguageMiddleware.php';
    }

    public static function applyFromSession(): void
    {
        if (!empty($_SESSION['lang'])) {
            LanguageManager::apply($_SESSION['lang']);
        }
    }
}
