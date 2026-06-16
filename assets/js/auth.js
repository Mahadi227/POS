// assets/js/auth.js

function togglePwd(inputId) {
    const input = document.getElementById(inputId);
    const btn = input.nextElementSibling;
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerText = 'visibility_off';
    } else {
        input.type = 'password';
        btn.innerText = 'visibility';
    }
}

function showAlert(message, type) {
    const alertBox = document.getElementById('alertBox');
    alertBox.className = `alert alert-${type}`;
    alertBox.innerText = message;
    alertBox.style.display = 'block';
}

function hideAlert() {
    document.getElementById('alertBox').style.display = 'none';
}

function setLoading(isLoading) {
    const btnText = document.getElementById('btnText');
    const spinner = document.getElementById('spinner');
    const submitBtn = document.getElementById('submitBtn');
    
    if (isLoading) {
        btnText.style.display = 'none';
        spinner.style.display = 'block';
        submitBtn.disabled = true;
    } else {
        btnText.style.display = 'inline';
        spinner.style.display = 'none';
        submitBtn.disabled = false;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            hideAlert();
            setLoading(true);
            
            const payload = {
                email: document.getElementById('email').value,
                password: document.getElementById('password').value,
                csrf_token: document.getElementById('csrf_token').value,
                remember: document.getElementById('remember').checked
            };

            try {
                const response = await fetch('../api/v1/index.php?request=auth/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                
                const result = await response.json();
                
                if (response.ok && result.status === 'success') {
                    window.location.href = result.redirect;
                } else {
                    const i18n = window.AUTH_I18N || {};
                    showAlert(result.message || i18n.error_generic || 'An error occurred', 'error');
                }
            } catch (error) {
                const i18n = window.AUTH_I18N || {};
                showAlert(i18n.server_error || 'Unable to connect to the server', 'error');
            } finally {
                setLoading(false);
            }
        });
    }

    if (registerForm) {
        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            hideAlert();
            setLoading(true);
            
            const payload = {
                name: document.getElementById('name').value,
                email: document.getElementById('email').value,
                password: document.getElementById('password').value,
                password_confirmation: document.getElementById('password_confirmation').value,
                csrf_token: document.getElementById('csrf_token').value
            };

            if (payload.password !== payload.password_confirmation) {
                const i18n = window.AUTH_I18N || {};
                showAlert(i18n.password_mismatch || 'Passwords do not match.', 'error');
                setLoading(false);
                return;
            }

            try {
                const response = await fetch('../api/v1/index.php?request=auth/register', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                
                const result = await response.json();
                
                if (response.ok && result.status === 'success') {
                    showAlert(result.message, 'success');
                    registerForm.reset();
                } else {
                    const i18n = window.AUTH_I18N || {};
                    showAlert(result.message || i18n.register_error || 'Registration failed', 'error');
                }
            } catch (error) {
                const i18n = window.AUTH_I18N || {};
                showAlert(i18n.server_error || 'Unable to connect to the server', 'error');
            } finally {
                setLoading(false);
            }
        });
    }

    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    if (forgotPasswordForm) {
        forgotPasswordForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            hideAlert();
            setLoading(true);

            const payload = {
                email: document.getElementById('email').value,
                csrf_token: document.getElementById('csrf_token').value
            };

            try {
                const response = await fetch('../api/v1/index.php?request=auth/forgot-password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();
                const i18n = window.AUTH_I18N || {};

                if (response.ok && result.status === 'success') {
                    showAlert(result.message || i18n.forgot_success || 'Reset link sent.', 'success');
                    forgotPasswordForm.reset();
                } else {
                    showAlert(result.message || i18n.error_generic || 'An error occurred', 'error');
                }
            } catch (error) {
                const i18n = window.AUTH_I18N || {};
                showAlert(i18n.server_error || 'Unable to connect to the server', 'error');
            } finally {
                setLoading(false);
            }
        });
    }

    const resetPasswordForm = document.getElementById('resetPasswordForm');
    if (resetPasswordForm) {
        resetPasswordForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            hideAlert();
            setLoading(true);

            const payload = {
                token: document.getElementById('token').value,
                password: document.getElementById('password').value,
                password_confirmation: document.getElementById('password_confirmation').value,
                csrf_token: document.getElementById('csrf_token').value
            };

            const i18n = window.AUTH_I18N || {};
            if (payload.password !== payload.password_confirmation) {
                showAlert(i18n.password_mismatch || 'Passwords do not match.', 'error');
                setLoading(false);
                return;
            }

            try {
                const response = await fetch('../api/v1/index.php?request=auth/reset-password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();

                if (response.ok && result.status === 'success') {
                    showAlert(result.message || i18n.reset_success || 'Password updated.', 'success');
                    resetPasswordForm.reset();
                    if (result.redirect) {
                        setTimeout(() => { window.location.href = 'login.php'; }, 2000);
                    }
                } else {
                    showAlert(result.message || i18n.reset_invalid_token || i18n.error_generic || 'An error occurred', 'error');
                }
            } catch (error) {
                showAlert(i18n.server_error || 'Unable to connect to the server', 'error');
            } finally {
                setLoading(false);
            }
        });
    }
});
