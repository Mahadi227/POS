<?php
declare(strict_types=1);

/**
 * Minimal Paystack REST client (Initialize Transaction).
 */
final class PaystackService
{
    private string $secretKey;

    public function __construct(?string $secretKey = null)
    {
        $this->secretKey = $secretKey ?? (defined('PAYSTACK_SECRET_KEY') ? PAYSTACK_SECRET_KEY : '');
    }

    public function isConfigured(): bool
    {
        return $this->secretKey !== '' && str_starts_with($this->secretKey, 'sk_');
    }

    /** @param array<string, mixed> $params */
    public function initializeTransaction(array $params): array
    {
        return $this->request('POST', 'transaction/initialize', $params);
    }

    public function verifyTransaction(string $reference): array
    {
        return $this->request('GET', 'transaction/verify/' . rawurlencode($reference));
    }

    /** @param array<string, mixed> $params */
    private function request(string $method, string $path, array $params = []): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Paystack is not configured. Set PAYSTACK_SECRET_KEY.');
        }

        $url = 'https://api.paystack.co/' . ltrim($path, '/');
        $ch = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Type: application/json',
        ];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params, JSON_UNESCAPED_UNICODE));
        }

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($body ?: '{}', true);
        if ($code >= 400 || !is_array($decoded) || empty($decoded['status'])) {
            $msg = is_array($decoded) ? ($decoded['message'] ?? 'Paystack error') : 'Paystack error';
            throw new RuntimeException($msg);
        }
        return $decoded['data'] ?? $decoded;
    }
}
