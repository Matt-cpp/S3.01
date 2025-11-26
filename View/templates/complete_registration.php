<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Finaliser l'inscription</title>
    <link rel="stylesheet" href="../assets/css/style_create_acc.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <img src="../img/logoIUT.png" alt="Logo" class="logo">
    <div class="container">
        <div class="form-container">
            <h1 class="form-title">Créer un compte - Étape 3/3</h1>
            <p style="text-align: center; color: #666; margin-bottom: 20px;">
                Choisissez votre mot de passe pour finaliser votre compte
            </p>
            
            <?php 
            session_start();
            if (isset($_SESSION['success'])): ?>
                <div class="success-message">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="error-message">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <?php if (!isset($_SESSION['email_verified'])): ?>
                <div class="error-message">
                    Session expirée ou email non vérifié. Veuillez recommencer le processus d'inscription.
                </div>
                <div class="login-link">
                    <p><a href="create_acc.php">Retour à l'inscription</a></p>
                </div>
            <?php else: ?>
                <div class="success-message" style="margin-bottom: 20px;">
                    ✓ Email vérifié : <strong><?php echo htmlspecialchars($_SESSION['email_verified']); ?></strong>
                </div>

                <form action="../../controllers/register.php" method="POST" class="register-form">
                    <input type="hidden" name="action" value="complete_registration">
                    
                    <div class="form-group">
                        <label for="password">Mot de passe:</label>
                        <div class="password-wrapper">
                            <input type="password" id="password" name="password" required minlength="8" placeholder="Au moins 8 caractères">
                            <i class="fas fa-eye toggle-password" onclick="togglePassword('password', this)"></i>
                        </div>
                        <small style="color: #666; font-size: 12px;">Minimum 8 caractères</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirmer le mot de passe:</label>
                        <div class="password-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="8" placeholder="Au moins 8 caractères">
                            <i class="far fa-eye toggle-password" onclick="togglePassword('confirm_password', this)"></i>
                        </div>
                    </div>
                    
                    <div id="password-match-message" style="margin-top: 10px; font-size: 12px;"></div>
                    
                    <button type="submit" class="btn-submit" id="submit-btn">Créer le compte</button>
                </form>

                <div class="login-link">
                    <p><a href="create_acc.php">Recommencer avec un autre email</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('far fa-eye');
                icon.classList.add('far fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('far fa-eye-slash');
                icon.classList.add('far fa-eye');
            }
        }

        const password = document.getElementById('password');
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
        password.addEventListener('input', function() {
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