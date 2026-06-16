<?php
// helpers.php: convenient helpers for templates and backend
require_once __DIR__ . '/TranslationService.php';
require_once __DIR__ . '/LanguageManager.php';

function __t(string $key, string $section = 'dashboard', array $replace = [])
{
    if (defined('ACTIVE_LANG')) {
        $lang = ACTIVE_LANG;
    } else {
        $lang = 'en';
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (!empty($_SESSION['lang'])) $lang = $_SESSION['lang'];
    }
    return TranslationService::get($key, $section, $lang, $replace);
}

function t(string $key, string $section = 'dashboard', array $replace = [])
{
    echo __t($key, $section, $replace);
}

function available_languages()
{
    return LanguageManager::getAvailable();
}

function set_user_language($lang, $pdo = null, $userId = null)
{
    return LanguageManager::apply($lang, $pdo, $userId);
}
