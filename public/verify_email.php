<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/Config/session.php';
require_once __DIR__ . '/../includes/Database/Database.php';
require_once __DIR__ . '/../includes/Auth/AuditLogger.php';

$token = trim($_GET['token'] ?? '');
$message = '';
$success = false;

if ($token !== '') {
    try {
        $db = Database::getInstance()->getConnection();
        $hash = hash('sha256', $token);
        $stmt = $db->prepare(
            'SELECT user_id FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $db->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ?')
                ->execute([(int) $row['user_id']]);
            AuditLogger::log((int) $row['user_id'], 'email_verified', 'success');
            $success = true;
            $message = 'Your email has been verified. You may now sign in.';
        } else {
            $message = 'Invalid or expired verification link.';
        }
    } catch (Throwable $e) {
        $message = 'Verification could not be completed. Please contact support.';
    }
} else {
    $message = 'Missing verification token.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification — RetailPOS</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <h1>RetailPOS<span style="color:var(--text-primary)">.</span></h1>
            <p>Email Verification</p>
        </div>
        <div class="alert alert-<?php echo $success ? 'success' : 'error'; ?>" style="display:block">
            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <p style="text-align:center;margin-top:24px">
            <a href="login.php">Return to Login</a>
        </p>
    </div>
</body>
</html>
