<?php
// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Récupérer les informations de l'utilisateur ou utiliser des valeurs par défaut
$user_first_name = $_SESSION['user_first_name'] ?? 'Utilisateur';
$user_last_name = $_SESSION['user_last_name'] ?? '';
$user_email = $_SESSION['user_email'] ?? 'email@example.com';
?>
<link rel="stylesheet" href="../assets/css/navbar.css">
<header class="header">
    <div class="logo">
        <img id="logo" src="../img/UPHF_logo.png" alt="Logo UPHF" />
    </div>
    <div class="header-icons">
        <div class="icon notification"></div>
        <div class="icon settings"></div>
        <div class="icon profile" id="profileIcon">
            <div class="profile-dropdown" id="profileDropdown">
                <div class="dropdown-header">
                    <div class="dropdown-user-info">
                        <span class="user-name"><?php echo htmlspecialchars(trim($user_first_name . ' ' . $user_last_name)); ?></span>
                        <span class="user-email"><?php echo htmlspecialchars($user_email); ?></span>
                    </div>
                </div>
                <div class="dropdown-divider"></div>
                <a href="/S3.01/controllers/logout.php" class="dropdown-item logout">
                    <span class="dropdown-icon">🚪</span>
                    <span>Déconnexion</span>
                </a>
            </div>
        </div>
    </div>
</header>

<script>
    // Toggle du menu déroulant du profil
    document.addEventListener('DOMContentLoaded', function() {
        const profileIcon = document.getElementById('profileIcon');
        const profileDropdown = document.getElementById('profileDropdown');
        
        profileIcon.addEventListener('click', function(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        });
        
        // Fermer le menu si on clique ailleurs
        document.addEventListener('click', function(e) {
            if (!profileIcon.contains(e.target)) {
                profileDropdown.classList.remove('show');
            }
        });
        
        // Empêcher la fermeture si on clique dans le menu
        profileDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
</script>

<?php
