<?php
require_once __DIR__ . '/includes/bootstrap.php';

PlatformSessionAuth::clear();
header('Location: login.php');
exit;
