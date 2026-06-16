<?php
// change_language handler: accept POST lang, optional user_id (if logged in)
require_once __DIR__ . '/../languages/LanguageManager.php';
require_once __DIR__ . '/../includes/Config/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$lang = $_POST['lang'] ?? $_GET['lang'] ?? null;
$userId = $_POST['user_id'] ?? null;

if ($lang && in_array($lang, ['en', 'fr'])) {
    // try to save to DB if user id and DB available
    if (!empty($userId) && class_exists('Database')) {
        try {
            $pdo = (new Database())->getConnection();
            LanguageManager::apply($lang, $pdo, $userId);
        } catch (Exception $e) {
            LanguageManager::apply($lang);
        }
    } else {
        LanguageManager::apply($lang);
    }
}

// redirect back
$back = $_SERVER['HTTP_REFERER'] ?? '/';
header('Location: ' . $back);
exit;