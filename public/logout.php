<?php
// public/logout.php
require_once __DIR__ . '/../includes/Config/session.php';

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit;
?>
