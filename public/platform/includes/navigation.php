<?php
declare(strict_types=1);

/**
 * Platform console navigation registry.
 *
 * @return array<int, array{section: string, items: array<int, array<string, mixed>>}>
 */
function plat_nav_sections(): array
{
    return [
        [
            'section' => 'plat_section_overview',
            'items' => [
                ['id' => 'dashboard', 'path' => 'dashboard.php', 'icon' => 'dashboard', 'label' => 'plat_nav_dashboard', 'ready' => true],
            ],
        ],
        [
            'section' => 'plat_section_organizations',
            'items' => [
                ['id' => 'companies', 'path' => 'companies/index.php', 'icon' => 'business', 'label' => 'plat_nav_companies', 'ready' => true],
                ['id' => 'subscriptions', 'path' => 'subscriptions/index.php', 'icon' => 'autorenew', 'label' => 'plat_nav_subscriptions', 'ready' => true],
                ['id' => 'plans', 'path' => 'plans/index.php', 'icon' => 'layers', 'label' => 'plat_nav_plans', 'ready' => true, 'phase' => 2],
                ['id' => 'licenses', 'path' => 'licenses/index.php', 'icon' => 'verified_user', 'label' => 'plat_nav_licenses', 'ready' => true, 'phase' => 3],
            ],
        ],
        [
            'section' => 'plat_section_billing',
            'items' => [
                ['id' => 'billing', 'path' => 'billing/index.php', 'icon' => 'receipt_long', 'label' => 'plat_nav_billing', 'ready' => true, 'phase' => 2],
                ['id' => 'payments', 'path' => 'payments/index.php', 'icon' => 'payments', 'label' => 'plat_nav_payments', 'ready' => true, 'phase' => 2],
            ],
        ],
        [
            'section' => 'plat_section_product',
            'items' => [
                ['id' => 'modules', 'path' => 'modules/index.php', 'icon' => 'extension', 'label' => 'plat_nav_modules', 'ready' => true, 'phase' => 2],
                ['id' => 'marketplace', 'path' => 'marketplace/index.php', 'icon' => 'storefront', 'label' => 'plat_nav_marketplace', 'ready' => true, 'phase' => 3],
                ['id' => 'domains', 'path' => 'domains/index.php', 'icon' => 'language', 'label' => 'plat_nav_domains', 'ready' => true, 'phase' => 2],
            ],
        ],
        [
            'section' => 'plat_section_insights',
            'items' => [
                ['id' => 'analytics', 'path' => 'analytics/index.php', 'icon' => 'insights', 'label' => 'plat_nav_analytics', 'ready' => true, 'phase' => 2],
                ['id' => 'reports', 'path' => 'reports/index.php', 'icon' => 'assessment', 'label' => 'plat_nav_reports', 'ready' => true, 'phase' => 2],
            ],
        ],
        [
            'section' => 'plat_section_support',
            'items' => [
                ['id' => 'support', 'path' => 'support/index.php', 'icon' => 'support_agent', 'label' => 'plat_nav_support', 'ready' => true, 'phase' => 2],
                ['id' => 'tickets', 'path' => 'tickets/index.php', 'icon' => 'confirmation_number', 'label' => 'plat_nav_tickets', 'ready' => true, 'phase' => 2],
                ['id' => 'knowledge_base', 'path' => 'knowledge_base/index.php', 'icon' => 'menu_book', 'label' => 'plat_nav_knowledge_base', 'ready' => true, 'phase' => 3],
            ],
        ],
        [
            'section' => 'plat_section_communications',
            'items' => [
                ['id' => 'notifications', 'path' => 'notifications/index.php', 'icon' => 'notifications', 'label' => 'plat_nav_notifications', 'ready' => true, 'phase' => 2],
                ['id' => 'emails', 'path' => 'emails/index.php', 'icon' => 'mail', 'label' => 'plat_nav_emails', 'ready' => true, 'phase' => 2],
                ['id' => 'sms', 'path' => 'sms/index.php', 'icon' => 'sms', 'label' => 'plat_nav_sms', 'ready' => true, 'phase' => 2],
            ],
        ],
        [
            'section' => 'plat_section_access',
            'items' => [
                ['id' => 'admin', 'path' => 'admin/index.php', 'icon' => 'admin_panel_settings', 'label' => 'plat_nav_admin', 'ready' => true, 'phase' => 2],
                ['id' => 'users', 'path' => 'users/index.php', 'icon' => 'group', 'label' => 'plat_nav_users', 'ready' => true, 'phase' => 2],
                ['id' => 'roles', 'path' => 'roles/index.php', 'icon' => 'badge', 'label' => 'plat_nav_roles', 'ready' => true, 'phase' => 2],
                ['id' => 'permissions', 'path' => 'permissions/index.php', 'icon' => 'lock', 'label' => 'plat_nav_permissions', 'ready' => true, 'phase' => 2],
            ],
        ],
        [
            'section' => 'plat_section_integrations',
            'items' => [
                ['id' => 'developers', 'path' => 'developers/index.php', 'icon' => 'code', 'label' => 'plat_nav_developers', 'ready' => true, 'external' => 'developers/index.php'],
                ['id' => 'api', 'path' => 'api/index.php', 'icon' => 'api', 'label' => 'plat_nav_api', 'ready' => true, 'external' => 'developers/openapi.php'],
                ['id' => 'integrations', 'path' => 'integrations/index.php', 'icon' => 'hub', 'label' => 'plat_nav_integrations', 'ready' => true, 'phase' => 3],
            ],
        ],
        [
            'section' => 'plat_section_operations',
            'items' => [
                ['id' => 'updates', 'path' => 'updates/index.php', 'icon' => 'system_update', 'label' => 'plat_nav_updates', 'ready' => true, 'phase' => 3],
                ['id' => 'backups', 'path' => 'backups/index.php', 'icon' => 'backup', 'label' => 'plat_nav_backups', 'ready' => true, 'phase' => 3],
            ],
        ],
        [
            'section' => 'plat_section_governance',
            'items' => [
                ['id' => 'security', 'path' => 'security/index.php', 'icon' => 'security', 'label' => 'plat_nav_security', 'ready' => true, 'phase' => 2],
                ['id' => 'audit', 'path' => 'audit/index.php', 'icon' => 'fact_check', 'label' => 'plat_nav_audit', 'ready' => true, 'phase' => 2],
            ],
        ],
        [
            'section' => 'plat_section_observability',
            'items' => [
                ['id' => 'logs', 'path' => 'logs/index.php', 'icon' => 'article', 'label' => 'plat_nav_logs', 'ready' => true, 'phase' => 2],
                ['id' => 'monitoring', 'path' => 'monitoring/index.php', 'icon' => 'monitor_heart', 'label' => 'plat_nav_monitoring', 'ready' => true],
            ],
        ],
        [
            'section' => 'plat_section_account',
            'items' => [
                ['id' => 'settings', 'path' => 'settings/index.php', 'icon' => 'settings', 'label' => 'plat_nav_settings', 'ready' => true, 'phase' => 2],
                ['id' => 'profile', 'path' => 'profile/index.php', 'icon' => 'account_circle', 'label' => 'plat_nav_profile', 'ready' => true, 'phase' => 2],
                ['id' => 'help', 'path' => 'help/index.php', 'icon' => 'help', 'label' => 'plat_nav_help', 'ready' => true, 'phase' => 2],
            ],
        ],
    ];
}

/** @return array<string, mixed>|null */
function plat_nav_find(string $moduleId): ?array
{
    foreach (plat_nav_sections() as $section) {
        foreach ($section['items'] as $item) {
            if ($item['id'] === $moduleId) {
                return $item;
            }
        }
    }
    return null;
}

/** @return array<int, array<string, mixed>> */
function plat_nav_related(string $moduleId, int $limit = 4): array
{
    $all = [];
    foreach (plat_nav_sections() as $section) {
        foreach ($section['items'] as $item) {
            if ($item['id'] !== $moduleId) {
                $all[] = $item;
            }
        }
    }

    $ready = array_values(array_filter($all, static fn(array $i): bool => !empty($i['ready'])));
    return array_slice($ready, 0, $limit);
}
