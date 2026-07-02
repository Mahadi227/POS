<?php
declare(strict_types=1);

/**
 * HS256 JWT encode/decode (no external dependency).
 */
final class JwtService
{
    public static function encode(array $payload, ?int $ttlSeconds = null): string
    {
        $ttl = $ttlSeconds ?? (int) (defined('JWT_EXPIRATION') ? JWT_EXPIRATION : 3600);
        $now = time();

        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $claims = array_merge($payload, [
            'iat' => $now,
            'exp' => $now + $ttl,
            'iss' => 'retailpos.cloud',
        ]);

        $segments = [
            self::base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR)),
            self::base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR)),
        ];
        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, self::secret(), true);
        $segments[] = self::base64UrlEncode($signature);

        return implode('.', $segments);
    }

    public static function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $sigB64] = $parts;
        $signingInput = $headerB64 . '.' . $payloadB64;
        $expected = self::base64UrlEncode(hash_hmac('sha256', $signingInput, self::secret(), true));

        if (!hash_equals($expected, $sigB64)) {
            return null;
        }

        try {
            $payload = json_decode(self::base64UrlDecode($payloadB64), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        if (!is_array($payload)) {
            return null;
        }
        if (!empty($payload['exp']) && (int) $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    public static function bearerFromRequest(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
            return $m[1];
        }
        return null;
    }

    private static function secret(): string
    {
        return defined('JWT_SECRET') ? JWT_SECRET : 'change_me';
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'), true) ?: '';
    }
}
