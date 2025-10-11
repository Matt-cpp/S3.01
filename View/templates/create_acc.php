<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Créer un compte</title>
    <link rel="stylesheet" href="../assets/css/style_create_acc.css">
</head>
<body>
    <img src="../img/logoIUT.png" alt="Logo" class="logo">
    <div class="container">
        <div class="form-container">
            <h1 class="form-title">Créer un compte</h1>
            <form action="../controllers/register.php" method="POST" class="register-form">
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Mot de passe:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirmer le mot de passe:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <button type="submit" class="btn-submit">Créer le compte</button>
            </form>
            
            <div class="login-link">
                <p>Déjà un compte? <a href="login.php">Se connecter</a></p>
            </div>
        </div>
    </div>
</body>
</html>