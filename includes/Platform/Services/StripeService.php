<?php
declare(strict_types=1);

/**
 * Minimal Stripe REST client (Checkout Sessions).
 */
final class StripeService
{
    private string $secretKey;

    public function __construct(?string $secretKey = null)
    {
        $this->secretKey = $secretKey ?? (defined('STRIPE_SECRET_KEY') ? STRIPE_SECRET_KEY : '');
    }

    public function isConfigured(): bool
    {
        return $this->secretKey !== '' && str_starts_with($this->secretKey, 'sk_');
    }

    /** @param array<string, mixed> $params */
    public function createCheckoutSession(array $params): array
    {
        return $this->request('POST', 'checkout/sessions', $params);
    }

    public function retrieveSession(string $sessionId): array
    {
        return $this->request('GET', 'checkout/sessions/' . urlencode($sessionId), ['expand' => ['subscription']]);
    }

    /** @param array<string, mixed> $params */
    private function request(string $method, string $path, array $params = []): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Stripe is not configured. Set STRIPE_SECRET_KEY in config.');
        }

        $url = 'https://api.stripe.com/v1/' . ltrim($path, '/');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $this->secretKey . ':',
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($this->flatten($params)));
        } elseif ($params) {
            $url .= '?' . http_build_query($this->flatten($params));
            curl_setopt($ch, CURLOPT_URL, $url);
        }

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($body ?: '{}', true);
        if ($code >= 400 || !is_array($decoded)) {
            $msg = is_array($decoded) ? ($decoded['error']['message'] ?? 'Stripe error') : 'Stripe error';
            throw new RuntimeException($msg);
        }
        return $decoded;
    }

    /** @param array<string, mixed> $params @return array<string, string> */
    private function flatten(array $params, string $prefix = ''): array
    {
        $out = [];
        foreach ($params as $key => $value) {
            $k = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';
            if (is_array($value)) {
                $out += $this->flatten($value, $k);
            } else {
                $out[$k] = (string) $value;
            }
        }
        return $out;
    }
}
