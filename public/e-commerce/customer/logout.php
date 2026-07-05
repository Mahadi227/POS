<?php
require_once __DIR__ . '/../includes/bootstrap.php';
unset($_SESSION['ecommerce_account_id']);
header('Location: ' . ecom_href('home/'));
exit;
