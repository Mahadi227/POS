<?php
declare(strict_types=1);

$mods = [
    'subscriptions', 'plans', 'billing', 'payments', 'licenses', 'modules', 'marketplace', 'domains',
    'analytics', 'reports', 'support', 'tickets', 'knowledge_base', 'notifications', 'emails', 'sms',
    'users', 'roles', 'permissions', 'integrations', 'updates', 'backups', 'security', 'audit', 'logs',
    'settings', 'profile', 'help',
];
$base = dirname(__DIR__) . '/public/platform/';
$tpl = <<<'PHP'
<?php declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');
plat_module_page('%s');

PHP;

foreach ($mods as $m) {
    file_put_contents($base . $m . '/index.php', sprintf($tpl, $m));
}
echo "Generated " . count($mods) . " stubs\n";
