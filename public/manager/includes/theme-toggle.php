<?php
$themeToggleClass = trim(($themeToggleClass ?? 'mgr-header-icon ad-header-icon'));
$themeLabel = $themeLabel ?? __t('theme', 'manager');
include __DIR__ . '/../../includes/theme-toggle.php';
