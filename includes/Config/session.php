<?php
// includes/Config/session.php

// Secure Session Configuration
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);

session_set_cookie_params([
    'lifetime' => 1800, // 30 minutes
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Set a unique session name to prevent conflicts with other XAMPP projects
session_name('retailpos_session');
session_start();

// Restore session from remember-me cookie (PWA / persistent login)
if (empty($_SESSION['user_id'])) {
    $authBootstrap = __DIR__ . '/../Auth/AuthBootstrap.php';
    if (is_readable($authBootstrap)) {
        require_once $authBootstrap;
        AuthBootstrap::tryRememberMe();
    }
}

// Regenerate Session ID to prevent session fixation attacks
if (!isset($_SESSION['last_regeneration'])) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
} else {
    $interval = 60 * 15; // 15 minutes
    if (time() - $_SESSION['last_regeneration'] >= $interval) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// Generate CSRF Token if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Function to verify CSRF token
function verify_csrf_token($token) {
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }
    return false;
}

// Session Timeout Management
function check_session_timeout() {
    $timeout_duration = 1800; // 30 minutes
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
        // User was inactive too long
        session_unset();
        session_destroy();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

// Function to ensure user is logged in
function requireLogin($redirect_path = '../login.php') {
    if (!isset($_SESSION['user_id']) || !check_session_timeout()) {
        header("Location: " . $redirect_path);
        exit;
    }
}

