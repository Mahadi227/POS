<?php
$themeToggleClass = trim($themeToggleClass ?? 'cu-header-icon');
$themeLabel = $themeLabel ?? __t('theme', 'cashier');
include __DIR__ . '/../../includes/theme-toggle.php';
