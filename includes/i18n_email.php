<?php
// i18n_email.php: example helper to fetch email template in user's language and send (simple)
require_once __DIR__ . '\..\languages\TranslationService.php';

function getEmailTemplate(PDO $pdo, string $slug, string $lang)
{
    $stmt = $pdo->prepare('SELECT subject, body FROM email_templates WHERE slug = ? AND language = ? LIMIT 1');
    $stmt->execute([$slug, $lang]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

function sendLocalizedEmail(PDO $pdo, string $slug, string $toEmail, string $lang, array $vars = [])
{
    $tpl = getEmailTemplate($pdo, $slug, $lang);
    if (!$tpl) {
        // fallback to default language
        $default = defined('I18N_DEFAULT') ? I18N_DEFAULT : 'en';
        $tpl = getEmailTemplate($pdo, $slug, $default);
    }
    if (!$tpl) return false;
    $subject = $tpl['subject'];
    $body = $tpl['body'];
    foreach ($vars as $k => $v) {
        $subject = str_replace('{{' . $k . '}}', $v, $subject);
        $body = str_replace('{{' . $k . '}}', $v, $body);
    }

    // send email - integrate with your mailer (PHPMailer, mail(), etc.)
    // Example using mail():
    $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: noreply@yourdomain.com\r\n";
    return mail($toEmail, $subject, $body, $headers);
}
