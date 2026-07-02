<?php
declare(strict_types=1);

require_once __DIR__ . '/../Config/session.php';
require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Platform/TenantSchemaMigrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase5Migrator.php';
require_once __DIR__ . '/../Platform/TenantScope.php';
require_once __DIR__ . '/../Platform/Services/OnboardingService.php';
require_once __DIR__ . '/../Platform/Services/EmailVerificationService.php';

/**
 * Redirect users who must verify email or complete onboarding.
 */
final class OnboardingGuard
{
    public static function enforceForAdmin(string $onboardingPath = '../onboarding.php', string $verifyPath = '../verify-email.php'): void
    {
        if (empty($_SESSION['user_id'])) {
            return;
        }

        try {
            $db = Database::getInstance()->getConnection();
            TenantSchemaMigrator::ensure($db);
            SaaSPhase5Migrator::ensure($db);
            TenantScope::loadFromSession($db);

            $userId = (int) $_SESSION['user_id'];
            $tenantId = TenantScope::id();

            if ($tenantId === 1) {
                return;
            }

            $emailSvc = new EmailVerificationService($db);
            if (!$emailSvc->isVerified($userId) && !self::isVerifyPage($verifyPath)) {
                header('Location: ' . $verifyPath);
                exit;
            }

            $onboarding = new OnboardingService($db);
            if (!$onboarding->isComplete($tenantId) && !self::isOnboardingPage($onboardingPath)) {
                header('Location: ' . $onboardingPath);
                exit;
            }
        } catch (Throwable) {
        }
    }

    private static function isOnboardingPage(string $path): bool
    {
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        return $script === 'onboarding.php' || str_contains($_SERVER['REQUEST_URI'] ?? '', 'onboarding.php');
    }

    private static function isVerifyPage(string $path): bool
    {
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        return $script === 'verify-email.php' || str_contains($_SERVER['REQUEST_URI'] ?? '', 'verify-email.php');
    }
}
