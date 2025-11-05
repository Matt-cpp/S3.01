<?php
// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Récupérer les informations de l'utilisateur ou utiliser des valeurs par défaut
$user_first_name = $_SESSION['user_first_name'] ?? 'Utilisateur';
$user_last_name = $_SESSION['user_last_name'] ?? '';
$user_email = $_SESSION['user_email'] ?? 'email@example.com';
$user_role = $_SESSION['user_role'] ?? 'student';

// Get home page URL based on role
require_once __DIR__ . '/../../controllers/auth_guard.php';
$home_url = getUserHomePage($user_role);

// Déterminer la page actuelle pour les étudiants
$current_page = basename($_SERVER['PHP_SELF']);
?>
<link rel="stylesheet" href="../assets/css/navbar.css">
<header class="header">
    <div class="logo">
        <img id="logo" src="../img/UPHF_logo.png" alt="Logo UPHF" />
    </div>

    <?php if ($user_role === 'student'): ?>
        <!-- Student Navigation Menu -->
        <nav class="nav-menu">
            <a href="student_home_page.php"
                class="nav-link <?php echo ($current_page == 'student_home_page.php') ? 'active' : ''; ?>">
                <span class="nav-icon">📊</span>
                <span>Tableau de bord</span>
            </a>
            <a href="student_absences.php"
                class="nav-link <?php echo ($current_page == 'student_absences.php') ? 'active' : ''; ?>">
                <span class="nav-icon">📅</span>
                <span>Mes absences</span>
            </a>
            <a href="student_proofs.php"
                class="nav-link <?php echo ($current_page == 'student_proofs.php') ? 'active' : ''; ?>">
                <span class="nav-icon">📄</span>
                <span>Mes justificatifs</span>
            </a>
            <a href="student_statistics.php"
                class="nav-link <?php echo ($current_page == 'student_statistics.php') ? 'active' : ''; ?>">
                <span class="nav-icon">📈</span>
                <span>Statistiques</span>
            </a>
            <a href="student_proof_submit.php"
                class="nav-link nav-link-primary <?php echo ($current_page == 'student_proof_submit.php') ? 'active' : ''; ?>">
                <span class="nav-icon">➕</span>
                <span>Soumettre justificatif</span>
            </a>
        </nav>
    <?php endif; ?>

    <div class="header-icons">
        <a href="<?php echo htmlspecialchars($home_url); ?>" class="icon-link" title="Accueil">
            <div class="icon home">🏠</div>
        </a>
        <?php if ($user_role === 'student'): ?>
            <a href="student_info.php" class="icon info-icon" title="Informations et procédure">
                <span>❓</span>
            </a>
        <?php else: ?>
            <div class="icon notification" title="Notifications"></div>
        <?php endif; ?>
        <a href="/View/templates/settings.php" class="icon-link">
            <div class="icon settings" title="Paramètres"></div>
        </a>
        <div class="icon profile" title="Profil" id="profileIcon">
            <div class="profile-dropdown" id="profileDropdown">
                <div class="dropdown-header">
                    <div class="dropdown-user-info">
                        <span
                            class="user-name"><?php echo htmlspecialchars(trim($user_first_name . ' ' . $user_last_name)); ?></span>
                        <span class="user-email"><?php echo htmlspecialchars($user_email); ?></span>
                    </div>
                </div>
                <div class="dropdown-divider"></div>
                <a href="../../controllers/logout.php" class="dropdown-item logout">
                    <span class="dropdown-icon">🚪</span>
                    <span>Déconnexion</span>
                </a>
            </div>
        </div>
    </div>
</header>

<script>
    // Toggle du menu déroulant du profil
    document.addEventListener('DOMContentLoaded', function () {
        const profileIcon = document.getElementById('profileIcon');
        const profileDropdown = document.getElementById('profileDropdown');

        if (profileIcon && profileDropdown) {
            profileIcon.addEventListener('click', function (e) {
                e.stopPropagation();
                profileDropdown.classList.toggle('show');
            });

            // Fermer le menu si on clique ailleurs
            document.addEventListener('click', function (e) {
                if (!profileIcon.contains(e.target)) {
                    profileDropdown.classList.remove('show');
                }
            });

            // Empêcher la fermeture si on clique dans le menu
            profileDropdown.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        }
    });
</script>

<?php
