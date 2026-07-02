<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/Platform/PlatformSessionAuth.php';

PlatformSessionAuth::clear();
header('Location: login.php');
exit;
