<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Vérification du code</title>
    <link rel="stylesheet" href="../assets/css/style_create_acc.css">
    <?php include __DIR__ . '/../includes/theme-helper.php';
    renderThemeSupport(); ?>
</head>

<body>
    <img src="../img/logoIUT.png" alt="Logo" class="logo">
    <div class="container">
        <div class="form-container">
            <h1 class="form-title">Réinitialisation du mot de passe - Étape 2/3</h1>
            <p style="text-align: center; color: #666; margin-bottom: 20px;">
                Entrez le code de vérification envoyé à votre email
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

            <?php if (!isset($_SESSION['reset_email'])): ?>
                <div class="error-message">
                    Session expirée. Veuillez recommencer le processus de réinitialisation.
                </div>
                <div class="login-link">
                    <p><a href="forgot_password.php">Retour à la réinitialisation</a></p>
                </div>
            <?php else: ?>
                <div class="info-message"
                    style="background-color: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 10px; margin-bottom: 20px; border-radius: 4px;">
                    Code envoyé à : <strong><?php echo htmlspecialchars($_SESSION['reset_email']); ?></strong>
                </div>

                <form action="../../controllers/forgot_password.php" method="POST" class="register-form">
                    <input type="hidden" name="action" value="verify_reset_code">

                    <div class="form-group">
                        <label for="reset_code">Code de vérification (6 chiffres):</label>
                        <input type="text" id="reset_code" name="reset_code" required maxlength="6" pattern="[0-9]{6}"
                            placeholder="123456"
                            style="text-align: center; font-size: 24px; letter-spacing: 5px; font-weight: bold;">
                        <small style="color: #666; font-size: 12px;">Le code expire dans 15 minutes</small>
                    </div>

                    <button type="submit" class="btn-submit">Vérifier le code</button>
                </form>

                <div class="login-link">
                    <p>Code non reçu ? <a href="#" onclick="resendCode()">Renvoyer le code</a></p>
                    <p><a href="forgot_password.php">Changer d'email</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function resendCode() {
            // Créer un formulaire invisible pour renvoyer le code
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../../controllers/forgot_password.php';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'send_reset_code';

            const emailInput = document.createElement('input');
            emailInput.type = 'hidden';
            emailInput.name = 'email';
            emailInput.value = '<?php echo $_SESSION['reset_email'] ?? ''; ?>';

            form.appendChild(actionInput);
            form.appendChild(emailInput);
            document.body.appendChild(form);
            form.submit();
        }

        // Auto-format du code (optionnel)
        document.getElementById('reset_code').addEventListener('input', function (e) {
            // Supprimer tout caractère non-numérique
            this.value = this.value.replace(/\D/g, '');
        });
    </script>
</body>

</html>