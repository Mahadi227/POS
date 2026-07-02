<?php
require __DIR__ . '/includes/bootstrap.php';

if (PlatformSessionAuth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$lang = htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __t('plat_login_title', 'platform'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/auth.css">
    <link rel="stylesheet" href="../../assets/css/platform-portal.css?v=1">
</head>
<body class="plat-login-page">
    <div class="auth-container">
        <div class="auth-header">
            <h1>RetailPOS<span style="color:var(--text-primary)"> Cloud</span></h1>
            <p><?php echo __t('plat_login_subtitle', 'platform'); ?></p>
        </div>
        <div id="alertBox" class="alert"></div>
        <form id="platLoginForm">
            <div class="form-group">
                <label><?php echo __t('email', 'auth'); ?></label>
                <div class="input-icon-wrapper">
                    <span class="material-icons-round">email</span>
                    <input type="email" id="email" required autofocus placeholder="platform@retailpos.local">
                </div>
            </div>
            <div class="form-group">
                <label><?php echo __t('password', 'auth'); ?></label>
                <div class="input-icon-wrapper">
                    <span class="material-icons-round">lock</span>
                    <input type="password" id="password" required>
                </div>
            </div>
            <button type="submit" class="btn-primary" id="submitBtn"><?php echo __t('submit', 'auth'); ?></button>
        </form>
        <div class="auth-footer">
            <a href="../login.php"><?php echo __t('plat_tenant_login', 'platform'); ?></a>
        </div>
    </div>
    <script>
    document.getElementById('platLoginForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const alertBox = document.getElementById('alertBox');
        alertBox.className = 'alert';
        alertBox.textContent = '';
        try {
            const res = await fetch('../../api/v1/index.php?request=platform/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    email: document.getElementById('email').value.trim(),
                    password: document.getElementById('password').value
                })
            });
            const data = await res.json();
            if (data.status === 'success') {
                window.location.href = 'index.php';
                return;
            }
            alertBox.className = 'alert error';
            alertBox.textContent = data.message || 'Login failed';
        } catch (err) {
            alertBox.className = 'alert error';
            alertBox.textContent = 'Connection error';
        }
    });
    </script>
</body>
</html>
