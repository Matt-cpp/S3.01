<?php
session_start();
require_once __DIR__ . '/../../controllers/auth_guard.php';
redirectIfAuthenticated();

$errors = $_SESSION['login_errors'] ?? [];
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['login_errors'], $_SESSION['form_data']);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Connection</title>
    <link rel="stylesheet" href="../assets/css/style_create_acc.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <img src="../img/logoIUT.png" alt="Logo" class="logo">
    <div class="container">
        <div class="form-container">
            <h1 class="form-title">Se connecter</h1>

            <?php if (!empty($errors)): ?>
                <div class="error-messages" style="color: red; margin-bottom: 15px;">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="../../controllers/login.php" method="POST" class="login-form">
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" placeholder="prenom.nom@uhpf.fr"
                        value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="password">Mot de passe:</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" placeholder="********" required>
                        <i class="far fa-eye toggle-password" onclick="togglePassword('password', this)"></i>
                    </div>
                </div>
                <button type="submit" class="btn-submit">Se connecter</button>
            </form>

            <div class="login-link">
                <p>Pas de compte? <a href="create_acc.php">Créer un compte</a></p>
                <p>Mot de passe oublié? <a href="forgot_password.php">Réinitialiser</a></p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>

</html>