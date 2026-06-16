<?php
// Simple API endpoint to fetch translations for a given lang and section
require_once __DIR__ . '\..\..\..\languages\TranslationService.php';
require_once __DIR__ . '\..\..\..\languages\LanguageManager.php';

header('Content-Type: application/json; charset=utf-8');

$lang = $_GET['lang'] ?? ($_COOKIE['lang'] ?? 'en');
$section = $_GET['section'] ?? 'dashboard';

if (!in_array($lang, LanguageManager::getAvailable())) {
    $lang = LanguageManager::getDefault();
}

$translations = TranslationService::load($lang, $section);
echo json_encode(['lang' => $lang, 'section' => $section, 'translations' => $translations], JSON_UNESCAPED_UNICODE);