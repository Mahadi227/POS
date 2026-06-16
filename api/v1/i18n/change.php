<?php
// API: change language (AJAX friendly)
require_once __DIR__ . '/../../..//languages/LanguageManager.php';
require_once __DIR__ . '/../../..//languages/TranslationService.php';

header('Content-Type: application/json; charset=utf-8');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$lang = $_POST['lang'] ?? $_GET['lang'] ?? null;
if (!$lang) {
    echo json_encode(['success' => false, 'message' => 'No lang provided']);
    exit;
}

if (!in_array($lang, LanguageManager::getAvailable())) {
    echo json_encode(['success' => false, 'message' => 'Invalid lang']);
    exit;
}

// set session + cookie
$_SESSION['lang'] = $lang;
setcookie('lang', $lang, time() + 60 * 60 * 24 * 365, '/');

// if user logged in, try to persist to DB
if (!empty($_SESSION['user_id'])) {
    try {
        if (class_exists('Database')) {
            $db = new Database();
            $pdo = $db->getConnection();
            $stmt = $pdo->prepare('UPDATE users SET language = ? WHERE id = ?');
            $stmt->execute([$lang, (int)$_SESSION['user_id']]);
        }
    } catch (Throwable $e) { /* ignore persistence errors */
    }
}

echo json_encode(['success' => true, 'lang' => $lang]);
exit;