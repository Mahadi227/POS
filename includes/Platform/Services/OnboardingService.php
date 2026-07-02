<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Helpers/MailHelper.php';

final class OnboardingService
{
    public const TOTAL_STEPS = 6;

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function isComplete(int $tenantId): bool
    {
        $row = $this->getRow($tenantId);
        return !empty($row['completed_at']);
    }

    public function getState(int $tenantId): array
    {
        $row = $this->getRow($tenantId);
        if (!$row) {
            $this->init($tenantId);
            $row = $this->getRow($tenantId);
        }

        $steps = json_decode($row['steps_json'] ?? '{}', true);
        if (!is_array($steps)) {
            $steps = [];
        }

        return [
            'tenant_id' => $tenantId,
            'current_step' => (int) ($row['current_step'] ?? 1),
            'total_steps' => self::TOTAL_STEPS,
            'completed' => !empty($row['completed_at']),
            'completed_at' => $row['completed_at'] ?? null,
            'steps' => $steps,
        ];
    }

    /** @param array<string, mixed> $data */
    public function saveStep(int $tenantId, int $step, array $data, int $userId): array
    {
        if ($step < 1 || $step > self::TOTAL_STEPS) {
            throw new InvalidArgumentException('Invalid step');
        }

        $this->init($tenantId);
        $state = $this->getState($tenantId);
        $steps = $state['steps'];
        $steps['step_' . $step] = $data;

        match ($step) {
            1 => $this->applyCompanyStep($tenantId, $data),
            2 => $this->applyStoreStep($tenantId, $data, $userId),
            3 => $this->applyInvitesStep($tenantId, $data, $userId),
            4 => $this->applyTaxStep($tenantId, $data, $userId),
            5 => $this->applyProductStep($tenantId, $data, $userId),
            6 => null,
            default => null,
        };

        $nextStep = min($step + 1, self::TOTAL_STEPS);
        $completedAt = ($step >= self::TOTAL_STEPS) ? date('Y-m-d H:i:s') : null;

        $this->db->prepare(
            'UPDATE tenant_onboarding SET current_step = ?, steps_json = ?, completed_at = COALESCE(?, completed_at) WHERE tenant_id = ?'
        )->execute([
            $completedAt ? self::TOTAL_STEPS : $nextStep,
            json_encode($steps, JSON_UNESCAPED_UNICODE),
            $completedAt,
            $tenantId,
        ]);

        return $this->getState($tenantId);
    }

    public function skipToEnd(int $tenantId): void
    {
        $this->init($tenantId);
        $this->db->prepare(
            'UPDATE tenant_onboarding SET current_step = ?, completed_at = NOW() WHERE tenant_id = ?'
        )->execute([self::TOTAL_STEPS, $tenantId]);
    }

    private function init(int $tenantId): void
    {
        if (!$this->tableExists('tenant_onboarding')) {
            return;
        }
        $this->db->prepare(
            'INSERT IGNORE INTO tenant_onboarding (tenant_id, current_step) VALUES (?, 1)'
        )->execute([$tenantId]);
    }

    private function getRow(int $tenantId): ?array
    {
        if (!$this->tableExists('tenant_onboarding')) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM tenant_onboarding WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @param array<string, mixed> $data */
    private function applyCompanyStep(int $tenantId, array $data): void
    {
        $name = trim((string) ($data['org_name'] ?? ''));
        $currency = trim((string) ($data['currency'] ?? ''));
        $country = trim((string) ($data['country_code'] ?? ''));
        $address = trim((string) ($data['address'] ?? ''));

        $sets = [];
        $vals = [];
        if ($name !== '') {
            $sets[] = 'name = ?';
            $vals[] = $name;
        }
        if ($currency !== '') {
            $sets[] = 'default_currency = ?';
            $vals[] = $currency;
        }
        if ($country !== '') {
            $sets[] = 'country_code = ?';
            $vals[] = $country;
        }
        if ($sets) {
            $vals[] = $tenantId;
            $this->db->prepare('UPDATE tenants SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
        }

        if ($address !== '' && $this->hasColumn('tenants', 'settings_json')) {
            $stmt = $this->db->prepare('SELECT settings_json FROM tenants WHERE id = ?');
            $stmt->execute([$tenantId]);
            $settings = json_decode((string) ($stmt->fetchColumn() ?: '{}'), true) ?: [];
            $settings['address'] = $address;
            $this->db->prepare('UPDATE tenants SET settings_json = ? WHERE id = ?')
                ->execute([json_encode($settings, JSON_UNESCAPED_UNICODE), $tenantId]);
        }
    }

    /** @param array<string, mixed> $data */
    private function applyStoreStep(int $tenantId, array $data, int $userId): void
    {
        $storeId = (int) ($data['store_id'] ?? 0);
        if ($storeId <= 0) {
            $storeId = $this->primaryStoreId($tenantId, $userId);
        }
        if ($storeId <= 0) {
            return;
        }

        $name = trim((string) ($data['store_name'] ?? ''));
        $location = trim((string) ($data['location'] ?? ''));
        $sets = [];
        $vals = [];
        if ($name !== '') {
            $sets[] = 'name = ?';
            $vals[] = $name;
        }
        if ($location !== '') {
            $sets[] = 'location = ?';
            $vals[] = $location;
        }
        if ($sets) {
            $vals[] = $storeId;
            $this->db->prepare('UPDATE stores SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
        }
    }

    /** @param array<string, mixed> $data */
    private function applyInvitesStep(int $tenantId, array $data, int $userId): void
    {
        if (!$this->tableExists('tenant_invites')) {
            return;
        }
        $emails = $data['emails'] ?? [];
        if (!is_array($emails)) {
            $emails = preg_split('/[\s,;]+/', (string) $emails) ?: [];
        }
        $roleId = $this->resolveRoleId('cashier');

        foreach ($emails as $email) {
            $email = filter_var(trim((string) $email), FILTER_VALIDATE_EMAIL);
            if (!$email) {
                continue;
            }
            $exists = $this->db->prepare('SELECT 1 FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1');
            $exists->execute([$email]);
            if ($exists->fetchColumn()) {
                continue;
            }

            $token = bin2hex(random_bytes(24));
            $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
            $this->db->prepare(
                'INSERT INTO tenant_invites (tenant_id, email, token, role_id, invited_by, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$tenantId, $email, $token, $roleId, $userId, $expires]);

            $this->sendInviteEmail($email, $token);
        }
    }

    /** @param array<string, mixed> $data */
    private function applyTaxStep(int $tenantId, array $data, int $userId): void
    {
        $taxRate = isset($data['tax_rate']) ? (float) $data['tax_rate'] : null;
        if ($taxRate === null) {
            return;
        }
        $storeId = $this->primaryStoreId($tenantId, $userId);
        if ($storeId <= 0 || !$this->hasColumn('stores', 'tax_rate')) {
            return;
        }
        $this->db->prepare('UPDATE stores SET tax_rate = ? WHERE id = ?')->execute([$taxRate, $storeId]);
    }

    /** @param array<string, mixed> $data */
    private function applyProductStep(int $tenantId, array $data, int $userId): void
    {
        $name = trim((string) ($data['product_name'] ?? ''));
        $price = isset($data['price']) ? (float) $data['price'] : 0;
        if ($name === '' || !$this->tableExists('products')) {
            return;
        }

        $cols = ['name', 'price', 'stock_quantity', 'is_active'];
        $vals = [$name, $price, (int) ($data['stock'] ?? 10), 1];
        $sql = 'INSERT INTO products (' . implode(', ', $cols);
        if ($this->hasColumn('products', 'tenant_id')) {
            $sql .= ', tenant_id';
            $vals[] = $tenantId;
        }
        if ($this->hasColumn('products', 'sku')) {
            $sql .= ', sku';
            $vals[] = 'SKU-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        }
        $sql .= ') VALUES (' . implode(',', array_fill(0, count($vals), '?')) . ')';
        $this->db->prepare($sql)->execute($vals);
    }

    private function primaryStoreId(int $tenantId, int $userId): int
    {
        if ($this->hasColumn('stores', 'tenant_id')) {
            $stmt = $this->db->prepare(
                'SELECT id FROM stores WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY id ASC LIMIT 1'
            );
            $stmt->execute([$tenantId]);
            $id = (int) ($stmt->fetchColumn() ?: 0);
            if ($id > 0) {
                return $id;
            }
        }
        $stmt = $this->db->prepare('SELECT store_id FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function resolveRoleId(string $roleName): ?int
    {
        $stmt = $this->db->prepare('SELECT id FROM roles WHERE name = ? LIMIT 1');
        $stmt->execute([$roleName]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    private function sendInviteEmail(string $email, string $token): void
    {
        $base = function_exists('app_base_url') ? app_base_url() : (defined('APP_URL') ? APP_URL : '');
        $url = rtrim($base, '/') . '/public/register.php?invite=' . urlencode($token);
        $html = '<p>You are invited to join a RetailPOS organization.</p>'
            . '<p><a href="' . htmlspecialchars($url) . '">Accept invitation</a></p>';
        send_app_email($email, 'RetailPOS — Team invitation', $html);
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }

    private function hasColumn(string $table, string $column): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }
}
