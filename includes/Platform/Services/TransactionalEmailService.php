<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Helpers/MailHelper.php';
require_once __DIR__ . '/WebhookDispatcherService.php';

final class TransactionalEmailService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function sendWelcome(int $tenantId, int $userId): bool
    {
        $user = $this->getUser($userId);
        if (!$user) {
            return false;
        }
        $tenant = $this->getTenant($tenantId);
        $lang = $user['language'] ?? 'en';
        $isFr = $lang === 'fr';

        $subject = $isFr
            ? 'Bienvenue sur RetailPOS Cloud — ' . ($tenant['name'] ?? '')
            : 'Welcome to RetailPOS Cloud — ' . ($tenant['name'] ?? '');

        $html = $this->wrap($isFr
            ? '<p>Votre organisation <strong>' . htmlspecialchars($tenant['name'] ?? '') . '</strong> est prête.</p>
               <p>Complétez la configuration, puis lancez votre premier ticket de caisse.</p>'
            : '<p>Your organization <strong>' . htmlspecialchars($tenant['name'] ?? '') . '</strong> is ready.</p>
               <p>Finish setup, then run your first sale on the POS.</p>', $isFr);

        return $this->sendOnce($tenantId, $userId, 'welcome', $user['email'], $subject, $html);
    }

    public function sendTrialEnding(int $tenantId, int $daysLeft): int
    {
        $sent = 0;
        $key = 'trial_ending_' . $daysLeft;
        foreach ($this->getTenantAdmins($tenantId) as $user) {
            $lang = $user['language'] ?? 'en';
            $isFr = $lang === 'fr';
            $tenant = $this->getTenant($tenantId);
            $subject = $isFr
                ? "Essai RetailPOS — {$daysLeft} jour(s) restant(s)"
                : "RetailPOS trial — {$daysLeft} day(s) left";
            $html = $this->wrap($isFr
                ? '<p>Votre essai pour <strong>' . htmlspecialchars($tenant['name'] ?? '') . '</strong> se termine dans <strong>' . $daysLeft . '</strong> jour(s).</p>
                   <p><a href="' . htmlspecialchars($this->billingUrl()) . '">Choisir une formule</a></p>'
                : '<p>Your trial for <strong>' . htmlspecialchars($tenant['name'] ?? '') . '</strong> ends in <strong>' . $daysLeft . '</strong> day(s).</p>
                   <p><a href="' . htmlspecialchars($this->billingUrl()) . '">Choose a plan</a></p>', $isFr);

            if ($this->sendOnce($tenantId, (int) $user['id'], $key, $user['email'], $subject, $html)) {
                $sent++;
            }
        }

        WebhookDispatcherService::dispatch($this->db, $tenantId, 'trial.ending_soon', [
            'tenant_id' => $tenantId,
            'days_left' => $daysLeft,
        ]);

        return $sent;
    }

    public function sendPaymentFailed(int $tenantId): int
    {
        $sent = 0;
        foreach ($this->getTenantAdmins($tenantId) as $user) {
            $lang = $user['language'] ?? 'en';
            $isFr = $lang === 'fr';
            $subject = $isFr ? 'Échec de paiement — RetailPOS' : 'Payment failed — RetailPOS';
            $html = $this->wrap($isFr
                ? '<p>Le dernier paiement a échoué. Mettez à jour votre moyen de paiement pour éviter une interruption.</p>'
                : '<p>Your latest payment failed. Update your payment method to avoid service interruption.</p>', $isFr);

            if ($this->sendOnce($tenantId, (int) $user['id'], 'payment_failed', $user['email'], $subject, $html)) {
                $sent++;
            }
        }

        WebhookDispatcherService::dispatch($this->db, $tenantId, 'payment.failed', [
            'tenant_id' => $tenantId,
        ]);

        return $sent;
    }

    public function processTrialReminders(): array
    {
        if (!$this->hasColumn('tenants', 'trial_ends_at')) {
            return ['sent' => 0];
        }
        $sent = 0;
        $rows = $this->db->query(
            "SELECT id, trial_ends_at FROM tenants
             WHERE deleted_at IS NULL AND status = 'trial' AND trial_ends_at IS NOT NULL"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as $row) {
            $tenantId = (int) $row['id'];
            $ends = strtotime((string) $row['trial_ends_at']);
            if ($ends === false) {
                continue;
            }
            $daysLeft = (int) ceil(($ends - time()) / 86400);
            if (in_array($daysLeft, [7, 3, 1], true)) {
                $sent += $this->sendTrialEnding($tenantId, $daysLeft);
            }
        }

        return ['sent' => $sent];
    }

    private function sendOnce(
        ?int $tenantId,
        ?int $userId,
        string $templateKey,
        string $email,
        string $subject,
        string $html,
    ): bool {
        if ($this->wasSentRecently($tenantId, $templateKey, $email)) {
            return false;
        }
        $ok = send_app_email($email, $subject, $html);
        if ($ok && $this->tableExists('transactional_email_log')) {
            $this->db->prepare(
                'INSERT INTO transactional_email_log (tenant_id, user_id, template_key, recipient) VALUES (?, ?, ?, ?)'
            )->execute([$tenantId, $userId, $templateKey, $email]);
        }
        return $ok;
    }

    private function wasSentRecently(?int $tenantId, string $key, string $email): bool
    {
        if (!$this->tableExists('transactional_email_log')) {
            return false;
        }
        $stmt = $this->db->prepare(
            'SELECT 1 FROM transactional_email_log
             WHERE tenant_id <=> ? AND template_key = ? AND recipient = ?
               AND sent_at >= DATE_SUB(NOW(), INTERVAL 20 HOUR) LIMIT 1'
        );
        $stmt->execute([$tenantId, $key, $email]);
        return (bool) $stmt->fetchColumn();
    }

    private function wrap(string $body, bool $isFr): string
    {
        $footer = $isFr ? 'RetailPOS Cloud' : 'RetailPOS Cloud';
        return '<div style="font-family:Inter,sans-serif;max-width:560px;margin:0 auto;padding:24px;">'
            . '<h2 style="color:#2563eb;">' . $footer . '</h2>' . $body
            . '<p style="font-size:12px;color:#64748b;margin-top:24px;">' . $footer . '</p></div>';
    }

    private function billingUrl(): string
    {
        $base = function_exists('app_base_url') ? app_base_url() : (defined('APP_URL') ? APP_URL : '');
        return rtrim($base, '/') . '/public/billing.php';
    }

    private function getUser(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, email, name, language FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function getTenant(int $tenantId): array
    {
        $stmt = $this->db->prepare('SELECT id, name, slug FROM tenants WHERE id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    private function getTenantAdmins(int $tenantId): array
    {
        if (!$this->hasColumn('users', 'tenant_id')) {
            return [];
        }
        $stmt = $this->db->prepare(
            "SELECT u.id, u.email, u.language FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.tenant_id = ? AND u.deleted_at IS NULL
               AND r.name IN ('super_admin', 'Admin', 'admin')"
        );
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
