<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Helpers/MailHelper.php';

final class EmailVerificationService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function isVerified(int $userId): bool
    {
        if (!$this->hasColumn('users', 'email_verified_at')) {
            return true;
        }
        $stmt = $this->db->prepare('SELECT email_verified_at FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $val = $stmt->fetchColumn();
        return $val !== false && $val !== null && $val !== '';
    }

    public function createAndSend(int $userId, string $email, string $name, string $lang = 'en'): string
    {
        if (!$this->tableExists('email_verification_tokens')) {
            return '';
        }

        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+48 hours'));

        $this->db->prepare('UPDATE email_verification_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')
            ->execute([$userId]);

        $this->db->prepare(
            'INSERT INTO email_verification_tokens (user_id, token, expires_at) VALUES (?, ?, ?)'
        )->execute([$userId, $token, $expires]);

        $url = $this->verifyUrl($token);
        $this->sendEmail($email, $name, $url, $lang);

        if (defined('APP_DEBUG') && APP_DEBUG) {
            error_log('Email verify link for ' . $email . ': ' . $url);
        }

        return $token;
    }

    public function verify(string $token): ?array
    {
        if ($token === '' || !$this->tableExists('email_verification_tokens')) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT evt.user_id, evt.expires_at, evt.used_at, u.email, u.name
             FROM email_verification_tokens evt
             INNER JOIN users u ON u.id = evt.user_id
             WHERE evt.token = ? LIMIT 1'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['used_at']) {
            return null;
        }
        if (strtotime($row['expires_at']) < time()) {
            return null;
        }

        $userId = (int) $row['user_id'];
        if ($this->hasColumn('users', 'email_verified_at')) {
            $this->db->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ?')->execute([$userId]);
        }
        $this->db->prepare('UPDATE email_verification_tokens SET used_at = NOW() WHERE token = ?')->execute([$token]);

        return ['user_id' => $userId, 'email' => $row['email'], 'name' => $row['name']];
    }

    public function resend(int $userId, string $lang = 'en'): bool
    {
        $stmt = $this->db->prepare('SELECT email, name FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return false;
        }
        if ($this->isVerified($userId)) {
            return true;
        }
        $this->createAndSend($userId, $user['email'], $user['name'] ?? '', $lang);
        return true;
    }

    private function verifyUrl(string $token): string
    {
        $base = function_exists('app_base_url') ? app_base_url() : (defined('APP_URL') ? APP_URL : '');
        return rtrim($base, '/') . '/public/verify-email.php?token=' . urlencode($token);
    }

    private function sendEmail(string $to, string $name, string $url, string $lang): void
    {
        $isFr = $lang === 'fr';
        $subject = $isFr ? 'Vérifiez votre email — RetailPOS Cloud' : 'Verify your email — RetailPOS Cloud';
        $greet = $isFr ? 'Bonjour' : 'Hello';
        $body = $isFr
            ? "Cliquez pour vérifier votre adresse email et activer votre compte."
            : 'Click to verify your email address and activate your account.';
        $btn = $isFr ? 'Vérifier mon email' : 'Verify my email';

        $html = '<div style="font-family:Inter,sans-serif;max-width:520px;margin:0 auto;padding:24px;">'
            . '<h2>RetailPOS Cloud</h2>'
            . '<p>' . htmlspecialchars($greet . ' ' . $name) . ',</p>'
            . '<p>' . htmlspecialchars($body) . '</p>'
            . '<p><a href="' . htmlspecialchars($url) . '" style="display:inline-block;padding:12px 20px;background:#2563eb;color:#fff;text-decoration:none;border-radius:8px;">'
            . htmlspecialchars($btn) . '</a></p>'
            . '<p style="font-size:12px;color:#666;">' . htmlspecialchars($url) . '</p></div>';

        send_app_email($to, $subject, $html);
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
