<?php
// LanguageMiddleware: simple include to run early in bootstrap to set active language
require_once __DIR__ . '/LanguageManager.php';
require_once __DIR__ . '/TranslationService.php';

// default config fallback
if (defined('SYSTEM_DEFAULT_LANGUAGE')) {
    LanguageManager::setDefault(SYSTEM_DEFAULT_LANGUAGE);
}

// make available languages configurable
LanguageManager::setAvailable(['en', 'fr']);
TranslationService::setBasePath(__DIR__);

$resolveOptions = [];
if (defined('I18N_SKIP_BROWSER_LANG') && I18N_SKIP_BROWSER_LANG) {
    $resolveOptions['skip_browser'] = true;
}
$ACTIVE_LANG = LanguageManager::resolve($resolveOptions);
// expose globally
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$_SESSION['lang'] = $ACTIVE_LANG;
define('ACTIVE_LANG', $ACTIVE_LANG);
