<?php
// public/cashier/checkout.php
require_once '../../includes/Config/session.php';
requireLogin();

$role = strtolower($_SESSION['role'] ?? '');
if ($role !== 'cashier' && $role !== 'admin') {
    header('Location: ../../index.php');
    exit;
}

// Since checkout is primarily handled by the POS interface and modal (via AJAX to api/v1),
// this file is reserved for future non-JS fallback checkout processing or external payment gateways integration.
header('Location: pos.php');
exit;
?>
