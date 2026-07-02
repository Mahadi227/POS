<?php
declare(strict_types=1);

final class ApiProblem
{
    /** @param array<string, string[]>|null $errors */
    public static function send(
        int $status,
        string $title,
        string $detail,
        ?string $type = null,
        ?array $errors = null,
    ): void {
        http_response_code($status);
        $body = [
            'type' => $type ?? 'https://api.retailpos.cloud/errors/' . self::slug($title),
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
        ];
        if ($errors) {
            $body['errors'] = $errors;
        }
        echo json_encode($body, JSON_UNESCAPED_UNICODE);
    }

    public static function unauthorized(string $detail = 'Authentication required'): void
    {
        self::send(401, 'Unauthorized', $detail);
    }

    public static function forbidden(string $detail = 'Forbidden'): void
    {
        self::send(403, 'Forbidden', $detail);
    }

    public static function notFound(string $detail = 'Resource not found'): void
    {
        self::send(404, 'Not Found', $detail);
    }

    public static function rateLimited(int $retryAfter, string $detail = 'Rate limit exceeded'): void
    {
        header('Retry-After: ' . $retryAfter);
        self::send(429, 'Too Many Requests', $detail);
    }

    private static function slug(string $title): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/', '-', $title) ?? 'error');
    }
}
