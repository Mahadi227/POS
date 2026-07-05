<?php
declare(strict_types=1);

final class PlatformPermissionRepository
{
    /**
     * Platform console capabilities.
     *
     * @var array<string, array{category: string, icon: string, actions: array<int, string>, access: array<string, string>}>
     */
    public const CAPABILITIES = [
        'organizations' => [
            'category' => 'core',
            'icon' => 'business',
            'actions' => ['view', 'manage'],
            'access' => ['platform_admin' => 'full', 'support' => 'view'],
        ],
        'subscriptions' => [
            'category' => 'billing',
            'icon' => 'autorenew',
            'actions' => ['view', 'manage'],
            'access' => ['platform_admin' => 'full', 'support' => 'view'],
        ],
        'billing' => [
            'category' => 'billing',
            'icon' => 'receipt_long',
            'actions' => ['view', 'manage'],
            'access' => ['platform_admin' => 'full', 'support' => 'view'],
        ],
        'payments' => [
            'category' => 'billing',
            'icon' => 'payments',
            'actions' => ['view', 'manage'],
            'access' => ['platform_admin' => 'full', 'support' => 'view'],
        ],
        'licenses' => [
            'category' => 'billing',
            'icon' => 'vpn_key',
            'actions' => ['view', 'manage'],
            'access' => ['platform_admin' => 'full', 'support' => 'view'],
        ],
        'domains' => [
            'category' => 'product',
            'icon' => 'language',
            'actions' => ['view', 'manage'],
            'access' => ['platform_admin' => 'full', 'support' => 'view'],
        ],
        'marketplace' => [
            'category' => 'product',
            'icon' => 'storefront',
            'actions' => ['view', 'manage'],
            'access' => ['platform_admin' => 'full', 'support' => 'view'],
        ],
        'modules' => [
            'category' => 'product',
            'icon' => 'extension',
            'actions' => ['view', 'manage'],
            'access' => ['platform_admin' => 'full', 'support' => 'view'],
        ],
        'monitoring' => [
            'category' => 'operations',
            'icon' => 'monitor_heart',
            'actions' => ['view', 'manage'],
            'access' => ['platform_admin' => 'full', 'support' => 'view'],
        ],
        'incidents' => [
            'category' => 'operations',
            'icon' => 'report_problem',
            'actions' => ['view', 'manage'],
            'access' => ['platform_admin' => 'full', 'support' => 'view'],
        ],
        'users' => [
            'category' => 'security',
            'icon' => 'group',
            'actions' => ['view', 'manage'],
            'access' => ['platform_admin' => 'full', 'support' => 'none'],
        ],
        'impersonation' => [
            'category' => 'security',
            'icon' => 'switch_account',
            'actions' => ['manage'],
            'access' => ['platform_admin' => 'full', 'support' => 'none'],
        ],
        'audit' => [
            'category' => 'security',
            'icon' => 'history',
            'actions' => ['view'],
            'access' => ['platform_admin' => 'view', 'support' => 'view'],
        ],
        'settings' => [
            'category' => 'security',
            'icon' => 'settings',
            'actions' => ['manage'],
            'access' => ['platform_admin' => 'full', 'support' => 'none'],
        ],
    ];

    /** @return array<string, array<string, string>> */
    public static function roleAccessMatrix(): array
    {
        $matrix = [];
        foreach (self::CAPABILITIES as $key => $meta) {
            $matrix[$key] = $meta['access'];
        }
        return $matrix;
    }

    /** @return array{permissions: array<int, array<string, mixed>>, roles: array<int, string>, categories: array<int, string>} */
    public function catalog(): array
    {
        $permissions = [];

        foreach (self::CAPABILITIES as $capability => $meta) {
            foreach ($meta['actions'] as $action) {
                $roles = $this->rolesForAction($meta['access'], $action);
                $permissions[] = [
                    'key' => 'platform.' . $capability . '.' . $action,
                    'capability' => $capability,
                    'action' => $action,
                    'category' => $meta['category'],
                    'icon' => $meta['icon'],
                    'roles' => $roles,
                ];
            }
        }

        $categories = [];
        foreach (self::CAPABILITIES as $meta) {
            $categories[$meta['category']] = true;
        }

        return [
            'permissions' => $permissions,
            'roles' => ['platform_admin', 'support'],
            'categories' => array_keys($categories),
        ];
    }

    /** @return array<string, int> */
    public function permissionStats(): array
    {
        $catalog = $this->catalog();
        $view = 0;
        $manage = 0;
        $grants = 0;

        foreach ($catalog['permissions'] as $perm) {
            if (($perm['action'] ?? '') === 'view') {
                $view++;
            } elseif (($perm['action'] ?? '') === 'manage') {
                $manage++;
            }
            $grants += count($perm['roles'] ?? []);
        }

        return [
            'permissions' => count($catalog['permissions']),
            'categories' => count($catalog['categories']),
            'view' => $view,
            'manage' => $manage,
            'grants' => $grants,
        ];
    }

    /**
     * @param array<string, string> $access
     * @return array<int, string>
     */
    private function rolesForAction(array $access, string $action): array
    {
        $roles = [];
        foreach ($access as $role => $level) {
            if ($action === 'view' && in_array($level, ['full', 'view'], true)) {
                $roles[] = $role;
            } elseif ($action === 'manage' && $level === 'full') {
                $roles[] = $role;
            }
        }
        return $roles;
    }
}
