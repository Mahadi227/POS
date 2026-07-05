<?php
declare(strict_types=1);

/**
 * Storefront customer accounts (separate from POS staff users).
 */
final class EcommerceAccountService
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(int $tenantId, string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM ecommerce_storefront_accounts WHERE tenant_id = ? AND email = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, strtolower(trim($email))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $tenantId, int $accountId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM ecommerce_storefront_accounts WHERE tenant_id = ? AND id = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, $accountId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findByPhone(int $tenantId, string $phone): ?array
    {
        $phone = self::normalizePhone($phone);
        if ($phone === '') {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT * FROM ecommerce_storefront_accounts WHERE tenant_id = ? AND phone = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, $phone]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Creates or reuses a storefront account for guest checkout.
     *
     * @return array{customer_id:int, account_id:int}
     */
    public function ensureGuestFromCheckout(int $tenantId, string $email, string $phone, string $name): array
    {
        $email = self::normalizeOptionalEmail($email);
        $phone = self::normalizePhone($phone);
        $name = trim($name);

        if ($phone === '') {
            throw new InvalidArgumentException('Phone is required');
        }
        if (strlen(preg_replace('/\D+/', '', $phone)) < 8) {
            throw new InvalidArgumentException('Invalid phone number');
        }
        if ($name === '') {
            throw new InvalidArgumentException('Name is required');
        }

        $byPhone = $this->findByPhone($tenantId, $phone);
        if ($byPhone) {
            $this->syncGuestAccount($tenantId, $byPhone, $phone, $name, $email);
            $customerId = (int) ($byPhone['customer_id'] ?? 0);
            if ($customerId <= 0) {
                $customerId = $this->ensureCustomer($name, $email, $phone);
                $this->db->prepare(
                    'UPDATE ecommerce_storefront_accounts SET customer_id = ? WHERE tenant_id = ? AND id = ?'
                )->execute([$customerId, $tenantId, (int) $byPhone['id']]);
            }

            return [
                'customer_id' => $customerId,
                'account_id' => (int) $byPhone['id'],
            ];
        }

        if ($email !== null) {
            $existing = $this->findByEmail($tenantId, $email);
            if ($existing) {
                $this->syncGuestAccount($tenantId, $existing, $phone, $name, $email);
                $customerId = (int) ($existing['customer_id'] ?? 0);
                if ($customerId <= 0) {
                    $customerId = $this->ensureCustomer($name, $email, $phone);
                    $this->db->prepare(
                        'UPDATE ecommerce_storefront_accounts SET customer_id = ? WHERE tenant_id = ? AND id = ?'
                    )->execute([$customerId, $tenantId, (int) $existing['id']]);
                }

                return [
                    'customer_id' => $customerId,
                    'account_id' => (int) $existing['id'],
                ];
            }
        }

        $storefrontEmail = $email ?? self::placeholderEmailForPhone($tenantId, $phone);
        $customerId = $this->ensureCustomer($name, $email, $phone);
        $stmt = $this->db->prepare(
            'INSERT INTO ecommerce_storefront_accounts (tenant_id, customer_id, email, password_hash, name, phone)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $tenantId,
            $customerId,
            $storefrontEmail,
            password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            $name,
            $phone,
        ]);

        return [
            'customer_id' => $customerId,
            'account_id' => (int) $this->db->lastInsertId(),
        ];
    }

    public static function normalizeOptionalEmail(string $email): ?string
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }

        return $email;
    }

    public static function placeholderEmailForPhone(int $tenantId, string $phone): string
    {
        $digits = preg_replace('/\D+/', '', self::normalizePhone($phone)) ?? '0';

        return 'guest+' . $digits . '@t' . $tenantId . '.checkout.local';
    }

    /** Email for Paystack when the customer did not provide one. */
    public static function paystackEmail(?string $email, int $tenantId, string $phone): string
    {
        $normalized = self::normalizeOptionalEmail((string) $email);

        return $normalized ?? self::placeholderEmailForPhone($tenantId, $phone);
    }

    public static function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        if ($phone === '') {
            return '';
        }
        $hasPlus = str_starts_with($phone, '+');
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }

        return $hasPlus ? '+' . $digits : $digits;
    }

    /** @param array<string, mixed> $account */
    private function syncGuestAccount(
        int $tenantId,
        array $account,
        string $phone,
        string $name,
        ?string $email = null
    ): void {
        $accountId = (int) ($account['id'] ?? 0);
        if ($accountId <= 0) {
            return;
        }

        $fields = ['name = ?', 'phone = ?'];
        $params = [$name, $phone];

        if ($email !== null && strtolower(trim($email)) !== strtolower(trim((string) ($account['email'] ?? '')))) {
            if (!$this->findByEmail($tenantId, $email)) {
                $fields[] = 'email = ?';
                $params[] = $email;
            }
        }

        $params[] = $tenantId;
        $params[] = $accountId;
        $this->db->prepare(
            'UPDATE ecommerce_storefront_accounts SET ' . implode(', ', $fields) . ' WHERE tenant_id = ? AND id = ?'
        )->execute($params);

        $customerId = (int) ($account['customer_id'] ?? 0);
        if ($customerId > 0) {
            $custParams = [$name, $phone];
            $custSql = 'UPDATE customers SET name = ?, phone = ?';
            if ($email !== null && !$this->findByEmail($tenantId, $email)) {
                $custSql .= ', email = ?';
                $custParams[] = $email;
            }
            $custParams[] = $customerId;
            $this->db->prepare($custSql . ' WHERE id = ?')->execute($custParams);
        }
    }

    public function register(int $tenantId, string $email, string $password, string $name, ?string $phone = null): int
    {
        $email = strtolower(trim($email));
        if ($email === '' || $password === '' || trim($name) === '') {
            throw new InvalidArgumentException('Missing registration fields');
        }
        if ($this->findByEmail($tenantId, $email)) {
            throw new RuntimeException('Email already registered');
        }

        $normalizedPhone = $phone !== null && trim($phone) !== '' ? self::normalizePhone($phone) : null;
        $customerId = $this->ensureCustomer($name, $email, $normalizedPhone);

        $stmt = $this->db->prepare(
            'INSERT INTO ecommerce_storefront_accounts (tenant_id, customer_id, email, password_hash, name, phone)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $tenantId,
            $customerId,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            trim($name),
            $normalizedPhone,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findByIdentifier(int $tenantId, string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        if (str_contains($identifier, '@')) {
            return $this->findByEmail($tenantId, $identifier);
        }

        return $this->findByPhone($tenantId, $identifier);
    }

    /** @return array<string, mixed> */
    public function login(int $tenantId, string $identifier, string $password): array
    {
        $account = $this->findByIdentifier($tenantId, $identifier);
        if (!$account || !password_verify($password, (string) $account['password_hash'])) {
            throw new RuntimeException('Invalid credentials');
        }
        $this->db->prepare('UPDATE ecommerce_storefront_accounts SET last_login = NOW() WHERE id = ?')
            ->execute([(int) $account['id']]);
        return $account;
    }

    public function updateProfile(int $tenantId, int $accountId, string $name, ?string $phone): void
    {
        $this->db->prepare(
            'UPDATE ecommerce_storefront_accounts SET name = ?, phone = ? WHERE tenant_id = ? AND id = ?'
        )->execute([trim($name), $phone, $tenantId, $accountId]);

        $account = $this->findById($tenantId, $accountId);
        if ($account && !empty($account['customer_id'])) {
            $this->db->prepare('UPDATE customers SET name = ?, phone = ? WHERE id = ?')
                ->execute([trim($name), $phone, (int) $account['customer_id']]);
        }
    }

    public function ensurePosCustomerRecord(string $name, ?string $email, ?string $phone): int
    {
        return $this->ensureCustomer($name, $email, $phone);
    }

    private function ensureCustomer(string $name, ?string $email, ?string $phone): int
    {
        $phone = $phone !== null ? self::normalizePhone($phone) : null;
        $email = $email !== null ? strtolower(trim($email)) : null;

        if ($email !== null && $email !== '') {
            $stmt = $this->db->prepare('SELECT id FROM customers WHERE email = ? AND deleted_at IS NULL LIMIT 1');
            $stmt->execute([$email]);
            $id = (int) ($stmt->fetchColumn() ?: 0);
            if ($id > 0) {
                if ($phone !== null && $phone !== '') {
                    $this->db->prepare('UPDATE customers SET name = ?, phone = ? WHERE id = ?')
                        ->execute([trim($name), $phone, $id]);
                }
                return $id;
            }
        }

        if ($phone !== null && $phone !== '') {
            $stmt = $this->db->prepare(
                'SELECT id FROM customers WHERE phone = ? AND deleted_at IS NULL LIMIT 1'
            );
            $stmt->execute([$phone]);
            $id = (int) ($stmt->fetchColumn() ?: 0);
            if ($id > 0) {
                $this->db->prepare('UPDATE customers SET name = ?' . ($email ? ', email = ?' : '') . ' WHERE id = ?')
                    ->execute($email ? [trim($name), $email, $id] : [trim($name), $id]);
                return $id;
            }
        }

        $this->db->prepare('INSERT INTO customers (name, email, phone) VALUES (?, ?, ?)')
            ->execute([trim($name), $email, $phone]);

        return (int) $this->db->lastInsertId();
    }
}
