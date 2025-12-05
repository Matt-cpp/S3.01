<?php
session_start();

// Vérifier si l'utilisateur est déjà connecté
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    // Rediriger selon le rôle
    if ($_SESSION['user_role'] === 'student') {
        header("Location: historique.php");
    } else {
        header("Location: admin_dashboard.php");
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Mot de passe oublié</title>
    <link rel="stylesheet" href="../../assets/css/shared/create_acc.css">
    <?php include __DIR__ . '/../../includes/theme-helper.php';
    renderThemeSupport(); ?>
</head>

<body>
    <img src="../../img/logoIUT.png" alt="Logo" class="logo">
    <div class="container">
        <div class="form-container">
            <h1 class="form-title">Réinitialisation du mot de passe - Étape 1/3</h1>
            <p style="text-align: center; color: #666; margin-bottom: 20px;">
                Entrez votre adresse email pour recevoir un code de vérification
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

            <form action="../../../controllers/forgot_password.php" method="POST" class="register-form">
                <input type="hidden" name="action" value="send_reset_code">
                <div class="form-group">
                    <label for="email">Email universitaire:</label>
                    <input type="email" id="email" name="email" required placeholder="votre.email@uphf.fr">
                    <small style="color: #666; font-size: 12px;">Un code de vérification sera envoyé à cette
                        adresse</small>
                </div>

                <button type="submit" class="btn-submit">Envoyer le code de vérification</button>
            </form>

            <div class="login-link">
                <p>Vous vous souvenez de votre mot de passe? <a href="login.php">Se connecter</a></p>
                <p>Pas encore de compte? <a href="create_acc.php">Créer un compte</a></p>
            </div>
        </div>
    </div>
</body>

</html>