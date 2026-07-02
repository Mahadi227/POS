<?php
declare(strict_types=1);

require_once __DIR__ . '/ApiProblem.php';
require_once __DIR__ . '/../Auth/JwtService.php';
require_once __DIR__ . '/../Platform/TenantScope.php';
require_once __DIR__ . '/../Platform/Services/EntitlementService.php';
require_once __DIR__ . '/../Platform/Services/ApiKeyService.php';
require_once __DIR__ . '/../Platform/Services/ApiRateLimitService.php';
require_once __DIR__ . '/../Platform/Repositories/SubscriptionRepository.php';
require_once __DIR__ . '/../Platform/Repositories/ApiKeyRepository.php';
require_once __DIR__ . '/../Database/Database.php';

/**
 * Authenticated API v2 context.
 * @phpstan-type ApiV2Context array{
 *   auth_type: 'jwt'|'api_key',
 *   tenant_id: int,
 *   user_id: ?int,
 *   role: ?string,
 *   email: ?string,
 *   permissions: string[],
 *   scopes: string[],
 *   store_id: ?int
 * }
 */
final class ApiV2Auth
{
    /** @var ?ApiV2Context */
    private static ?array $context = null;

  /** @return ApiV2Context */
    public static function authenticate(PDO $db): array
    {
        if (self::$context !== null) {
            return self::$context;
        }

        $apiKeyHeader = $_SERVER['HTTP_X_API_KEY'] ?? '';
        if ($apiKeyHeader !== '') {
            self::$context = self::authApiKey($db, $apiKeyHeader);
            self::afterAuth($db, self::$context);
            return self::$context;
        }

        $token = JwtService::bearerFromRequest();
        if (!$token) {
            ApiProblem::unauthorized('Bearer token or X-API-Key required');
            exit;
        }

        $claims = JwtService::decode($token);
        if (!$claims) {
            ApiProblem::unauthorized('Invalid or expired token');
            exit;
        }

        self::$context = [
            'auth_type' => 'jwt',
            'tenant_id' => (int) ($claims['tenant_id'] ?? 0),
            'user_id' => (int) ($claims['sub'] ?? 0) ?: null,
            'role' => $claims['role'] ?? null,
            'email' => $claims['email'] ?? null,
            'permissions' => $claims['permissions'] ?? [],
            'scopes' => ['*'],
            'store_id' => isset($claims['store_id']) ? (int) $claims['store_id'] : null,
        ];

        self::afterAuth($db, self::$context);
        return self::$context;
    }

    /** @return ApiV2Context */
    private static function authApiKey(PDO $db, string $rawKey): array
    {
        $svc = new ApiKeyService($db, new ApiKeyRepository($db));
        $row = $svc->validate($rawKey);
        if (!$row) {
            ApiProblem::unauthorized('Invalid API key');
            exit;
        }

        return [
            'auth_type' => 'api_key',
            'tenant_id' => (int) $row['tenant_id'],
            'user_id' => null,
            'role' => null,
            'email' => null,
            'permissions' => [],
            'scopes' => $row['scopes'] ?? [],
            'store_id' => null,
        ];
    }

    /** @param ApiV2Context $ctx */
    private static function afterAuth(PDO $db, array $ctx): void
    {
        $tenantId = (int) $ctx['tenant_id'];
        if ($tenantId <= 0) {
            ApiProblem::unauthorized('Tenant context missing');
            exit;
        }

        $tenant = TenantScope::resolveById($db, $tenantId);
        if (!$tenant) {
            ApiProblem::notFound('Tenant not found');
            exit;
        }
        if (($tenant['status'] ?? '') === 'suspended') {
            ApiProblem::forbidden('Tenant suspended');
            exit;
        }

        TenantScope::set($tenantId, $tenant);

        if ($tenantId !== 1) {
            $ent = new EntitlementService($db, new SubscriptionRepository($db));
            if (!$ent->hasModule($tenantId, 'api_access')) {
                ApiProblem::forbidden('API access not included in current plan');
                exit;
            }
        }

        $rate = (new ApiRateLimitService($db))->check($tenantId);
        if (!$rate['allowed']) {
            ApiProblem::rateLimited((int) ($rate['retry_after'] ?? 60));
            exit;
        }
    }

    public static function requireScope(PDO $db, string $scope): void
    {
        $ctx = self::authenticate($db);
        if (($ctx['scopes'] ?? []) === ['*'] || in_array('*', $ctx['scopes'] ?? [], true)) {
            return;
        }
        if (!in_array($scope, $ctx['scopes'] ?? [], true)) {
            ApiProblem::forbidden('Missing scope: ' . $scope);
            exit;
        }
    }

    /** @return array{page: int, per_page: int, offset: int} */
    public static function pagination(int $defaultPerPage = 50, int $maxPerPage = 100): array
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min($maxPerPage, max(1, (int) ($_GET['per_page'] ?? $defaultPerPage)));
        return ['page' => $page, 'per_page' => $perPage, 'offset' => ($page - 1) * $perPage];
    }

    public static function jsonSuccess(mixed $data, array $meta = []): void
    {
        $out = ['data' => $data];
        if ($meta) {
            $out['meta'] = $meta;
        }
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
    }
}
