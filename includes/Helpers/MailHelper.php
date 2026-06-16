<?php
// MailHelper: simple HTML email sender for auth flows

require_once __DIR__ . '/../Config/config.php';
function app_base_url(): string
{
    if (defined('APP_URL') && APP_URL !== '') {
        return rtrim(str_replace(' ', '%20', APP_URL), '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $publicPos = strpos($script, '/public/');
    if ($publicPos !== false) {
        return $scheme . '://' . $host . substr($script, 0, $publicPos);
    }
    return $scheme . '://' . $host;
}

function send_app_email(string $to, string $subject, string $bodyHtml): bool
{
    $appName = defined('APP_NAME') ? APP_NAME : 'RetailPOS';
    $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$appName} <noreply@localhost>\r\n";
    return @mail($to, $subject, $bodyHtml, $headers);
}
