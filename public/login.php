<?php
require_once __DIR__ . '/../includes/Config/session.php';
// If already logged in, redirect to dashboard based on role
if (isset($_SESSION['user_id'])) {
    $role = strtolower($_SESSION['role'] ?? '');
    if ($role === 'cashier') header("Location: cashier/dashboard.php");
    else header("Location: admin/index.html");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - RetailPOS</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <h1>RetailPOS<span style="color:var(--text-primary)">.</span></h1>
            <p>Connectez-vous à votre espace</p>
        </div>

        <div id="alertBox" class="alert"></div>

        <form id="loginForm">
            <input type="hidden" id="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="form-group">
                <label>Adresse Email</label>
                <div class="input-icon-wrapper">
                    <span class="material-icons-round">email</span>
                    <input type="email" id="email" placeholder="admin@retailpos.com" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label>Mot de passe</label>
                <div class="input-icon-wrapper">
                    <span class="material-icons-round">lock</span>
                    <input type="password" id="password" placeholder="••••••••" required>
                    <button type="button" class="material-icons-round toggle-password" onclick="togglePwd('password')">visibility</button>
                </div>
            </div>

            <div class="form-options">
                <label class="remember-me">
                    <input type="checkbox" id="remember"> Se souvenir de moi
                </label>
                <a href="forgot-password.php" class="forgot-link">Mot de passe oublié ?</a>
            </div>

            <button type="submit" class="btn-primary" id="submitBtn">
                <span id="btnText">Se Connecter</span>
                <div class="spinner" id="spinner"></div>
            </button>
        </form>

        <div class="auth-footer">
            Vous n'avez pas de compte ? <a href="register.php">Inscrivez-vous</a>
        </div>
    </div>

    <script src="../assets/js/auth.js"></script>
</body>
</html>
