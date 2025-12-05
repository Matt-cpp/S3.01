<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Nouveau mot de passe</title>
    <link rel="stylesheet" href="../../assets/css/shared/create_acc.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__ . '/../../includes/theme-helper.php';
    renderThemeSupport(); ?>
</head>

<body>
    <img src="../../img/logoIUT.png" alt="Logo" class="logo">
    <div class="container">
        <div class="form-container">
            <h1 class="form-title">Réinitialisation du mot de passe - Étape 3/3</h1>
            <p style="text-align: center; color: #666; margin-bottom: 20px;">
                Choisissez votre nouveau mot de passe
            </p>

            <?php
            session_start();
            if (isset($_SESSION['success'])): ?>
                <div class="success-message">
                    <?php echo $_SESSION['success'];
                    unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="error-message">
                    <?php echo $_SESSION['error'];
                    unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <?php if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_code_verified'])): ?>
                <div class="error-message">
                    Session expirée ou code non vérifié. Veuillez recommencer le processus de réinitialisation.
                </div>
                <div class="login-link">
                    <p><a href="forgot_password.php">Retour à la réinitialisation</a></p>
                </div>
            <?php else: ?>
                <div class="success-message" style="margin-bottom: 20px;">
                    ✓ Code vérifié pour : <strong><?php echo htmlspecialchars($_SESSION['reset_email']); ?></strong>
                </div>

                <form action="../../../controllers/forgot_password.php" method="POST" class="register-form">
                    <input type="hidden" name="action" value="reset_password">

                    <div class="form-group">
                        <label for="new_password">Nouveau mot de passe:</label>
                        <div class="password-wrapper">
                            <input type="password" id="new_password" name="new_password" required minlength="8"
                                placeholder="Au moins 8 caractères">
                            <i class="fas fa-eye toggle-password" onclick="togglePassword('new_password', this)"></i>
                        </div>
                        <small style="color: #666; font-size: 12px;">Minimum 8 caractères</small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirmer le nouveau mot de passe:</label>
                        <div class="password-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                            <i class="far fa-eye toggle-password" onclick="togglePassword('confirm_password', this)"></i>
                        </div>
                    </div>

                    <div id="password-match-message" style="margin-top: 10px; font-size: 12px;"></div>

                    <button type="submit" class="btn-submit" id="submit-btn">Réinitialiser le mot de passe</button>
                </form>

                <div class="login-link">
                    <p><a href="login.php">Retour à la connexion</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('far', 'fa-eye');
                icon.classList.add('far', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('far', 'fa-eye-slash');
                icon.classList.add('far', 'fa-eye');
            }
        }

        const password = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const message = document.getElementById('password-match-message');
        const submitBtn = document.getElementById('submit-btn');

        function checkPasswords() {
            if (password.value && confirmPassword.value) {
                if (password.value === confirmPassword.value) {
                    message.textContent = '✓ Les mots de passe correspondent';
                    message.style.color = 'green';
                    submitBtn.disabled = false;
                } else {
                    message.textContent = '✗ Les mots de passe ne correspondent pas';
                    message.style.color = 'red';
                    submitBtn.disabled = true;
                }
            } else {
                message.textContent = '';
                submitBtn.disabled = false;
            }
        }

        password.addEventListener('input', checkPasswords);
        confirmPassword.addEventListener('input', checkPasswords);

        // Validation de la force du mot de passe
        password.addEventListener('input', function () {
            const strength = document.getElementById('password-strength');
            if (!strength) {
                const strengthDiv = document.createElement('div');
                strengthDiv.id = 'password-strength';
                strengthDiv.style.fontSize = '12px';
                strengthDiv.style.marginTop = '5px';
                password.parentNode.appendChild(strengthDiv);
            }

            const strengthElement = document.getElementById('password-strength');
            const value = this.value;

            if (value.length < 8) {
                strengthElement.textContent = 'Trop court (minimum 8 caractères)';
                strengthElement.style.color = 'red';
            } else if (value.length >= 8 && value.length < 12) {
                strengthElement.textContent = 'Mot de passe acceptable';
                strengthElement.style.color = 'orange';
            } else {
                strengthElement.textContent = 'Mot de passe fort';
                strengthElement.style.color = 'green';
            }
        });
    </script>
</body>

</html>