<?php
// Root index.php
require_once __DIR__ . '/includes/Config/session.php';

// If user is already logged in, redirect them to their dashboard
if (isset($_SESSION['user_id'])) {
    $role = strtolower($_SESSION['role'] ?? '');
    if ($role === 'cashier') {
        header("Location: public/pos.html");
    } else {
        header("Location: public/admin/index.html");
    }
    exit;
}

// Otherwise, redirect to login page
header("Location: public/login.php");
exit;
?>
