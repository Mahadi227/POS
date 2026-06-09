<?php
require_once __DIR__ . '/../includes/Config/session.php';
if (isset($_SESSION['user_id'])) {
    header("Location: admin/index.html");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - RetailPOS</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <h1>Créer un compte</h1>
            <p>Rejoignez la plateforme RetailPOS</p>
        </div>

        <div id="alertBox" class="alert"></div>

        <form id="registerForm">
            <input type="hidden" id="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="form-group">
                <label>Nom complet</label>
                <div class="input-icon-wrapper">
                    <span class="material-icons-round">person</span>
                    <input type="text" id="name" placeholder="Jean Dupont" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label>Adresse Email</label>
                <div class="input-icon-wrapper">
                    <span class="material-icons-round">email</span>
                    <input type="email" id="email" placeholder="jean@example.com" required>
                </div>
            </div>

            <div class="form-group">
                <label>Mot de passe</label>
                <div class="input-icon-wrapper">
                    <span class="material-icons-round">lock</span>
                    <input type="password" id="password" placeholder="••••••••" required minlength="8">
                    <button type="button" class="material-icons-round toggle-password" onclick="togglePwd('password')">visibility</button>
                </div>
            </div>

            <div class="form-group">
                <label>Confirmer le mot de passe</label>
                <div class="input-icon-wrapper">
                    <span class="material-icons-round">lock</span>
                    <input type="password" id="password_confirmation" placeholder="••••••••" required minlength="8">
                </div>
            </div>

            <button type="submit" class="btn-primary" id="submitBtn">
                <span id="btnText">S'inscrire</span>
                <div class="spinner" id="spinner"></div>
            </button>
        </form>

        <div class="auth-footer">
            Vous avez déjà un compte ? <a href="login.php">Connectez-vous</a>
        </div>
    </div>

    <script src="../assets/js/auth.js"></script>
</body>
</html>
