<?php 
session_start();
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
                    <input type="password" id="password" name="password" placeholder="********" required>
                </div>
                <button type="submit" class="btn-submit">Se connecter</button>
            </form>
            
            <div class="login-link">
                <p>Pas de compte? <a href="create_acc.php">Créer un compte</a></p>
            </div>
        </div>
    </div>
</body>
</html>