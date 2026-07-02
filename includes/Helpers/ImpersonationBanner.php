<?php
declare(strict_types=1);

/**
 * Renders support-mode banner when platform admin impersonates a tenant user.
 */
final class ImpersonationBanner
{
    public static function render(string $exitUrl = '../platform/exit-impersonation.php'): void
    {
        if (empty($_SESSION['impersonating'])) {
            return;
        }

        require_once __DIR__ . '/../languages/helpers.php';

        $tenantName = htmlspecialchars($_SESSION['impersonated_tenant_name'] ?? 'Tenant', ENT_QUOTES, 'UTF-8');
        $tenantId = (int) ($_SESSION['impersonated_tenant_id'] ?? 0);
        $exit = htmlspecialchars($exitUrl . ($tenantId ? '?tenant_id=' . $tenantId : ''), ENT_QUOTES, 'UTF-8');
        $label = __t('plat_support_mode', 'platform');
        $exitLabel = __t('plat_exit_support', 'platform');

        echo <<<HTML
<div class="plat-impersonation-banner" role="alert">
    <span class="material-icons-round">support_agent</span>
    <span><strong>{$label}</strong> — {$tenantName}</span>
    <a href="{$exit}" class="plat-impersonation-exit">{$exitLabel}</a>
</div>
HTML;
    }
}
