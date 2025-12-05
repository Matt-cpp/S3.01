<?php
session_start();
require_once __DIR__ . '/../../../controllers/auth_guard.php';
redirectIfAuthenticated();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Créer un compte</title>
    <link rel="stylesheet" href="../../assets/css/shared/create_acc.css">
    <?php include __DIR__ . '/../../includes/theme-helper.php';
    renderThemeSupport(); ?>
</head>

<body>
    <img src="../../img/logoIUT.png" alt="Logo" class="logo">
    <div class="container">
        <div class="form-container">
            <h1 class="form-title">Créer un compte - Étape 1/3</h1>
            <p style="text-align: center; color: #666; margin-bottom: 20px;">
                Entrez votre adresse email pour commencer l'inscription
            </p>

            <?php if (isset($_SESSION['success'])): ?>
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

            <form action="../../../controllers/register.php" method="POST" class="register-form">
                <input type="hidden" name="action" value="send_code">
                <div class="form-group">
                    <label for="email">Email universitaire:</label>
                    <input type="email" id="email" name="email" required placeholder="votre.email@uphf.fr">
                    <small style="color: #666; font-size: 12px;">Un code de vérification sera envoyé à cette
                        adresse</small>
                </div>

                <button type="submit" class="btn-submit">Envoyer le code de vérification</button>
            </form>

            <div class="login-link">
                <p>Déjà un compte? <a href="login.php">Se connecter</a></p>
            </div>
        </div>
    </div>
</body>

</html>