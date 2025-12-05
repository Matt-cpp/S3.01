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
<link rel="stylesheet" href="/View/assets/css/shared/navbar.css">
<header class="header">
    <div class="logo">
        <img id="logo" src="/View/img/UPHF_logo.png" alt="Logo UPHF" />
    </div>

    <?php if ($user_role === 'student'): ?>
        <!-- Student Navigation Menu -->
        <nav class="nav-menu">
            <a href="/View/templates/student/home.php"
                class="nav-link <?php echo ($current_page == 'home.php' && strpos($_SERVER['PHP_SELF'], '/student/') !== false) ? 'active' : ''; ?>">
                <span>Tableau de bord</span>
            </a>
            <a href="/View/templates/student/absences.php"
                class="nav-link <?php echo ($current_page == 'absences.php') ? 'active' : ''; ?>">
                <span>Mes absences</span>
            </a>
            <a href="/View/templates/student/proofs.php"
                class="nav-link <?php echo ($current_page == 'proofs.php') ? 'active' : ''; ?>">
                <span>Mes justificatifs</span>
            </a>
            <a href="/View/templates/student/statistics.php"
                class="nav-link <?php echo ($current_page == 'statistics.php' && strpos($_SERVER['PHP_SELF'], '/student/') !== false) ? 'active' : ''; ?>">
                <span>Statistiques</span>
            </a>
            <a href="/View/templates/student/proof_submit.php"
                class="nav-link nav-link-primary <?php echo ($current_page == 'proof_submit.php') ? 'active' : ''; ?>">
                <span class="nav-icon">➕</span>
                <span>Soumettre justificatif</span>
            </a>
        </nav>
    <?php elseif ($user_role === 'academic_manager'): ?>
        <!-- Academic Manager Navigation Menu -->
        <nav class="nav-menu">
            <a href="/View/templates/academic_manager/home.php"
                class="nav-link <?php echo ($current_page == 'home.php' && strpos($_SERVER['PHP_SELF'], '/academic_manager/') !== false) ? 'active' : ''; ?>">
                <span>Tableau de bord</span>
            </a>
            <a href="/View/templates/academic_manager/absences.php"
                class="nav-link <?php echo ($current_page == 'absences.php' && strpos($_SERVER['PHP_SELF'], '/academic_manager/') !== false) ? 'active' : ''; ?>">
                <span>Absences</span>
            </a>
            <a href="/View/templates/academic_manager/historique_proof.php"
                class="nav-link <?php echo ($current_page == 'historique_proof.php') ? 'active' : ''; ?>">
                <span>Justificatifs</span>
            </a>
            <a href="/View/templates/academic_manager/statistics.php"
                class="nav-link <?php echo ($current_page == 'statistics.php' && strpos($_SERVER['PHP_SELF'], '/academic_manager/') !== false) ? 'active' : ''; ?>">
                <span>Statistiques</span>
            </a>
        </nav>
    <?php elseif ($user_role === 'teacher'): ?>
        <!-- Teacher Navigation Menu -->
        <nav class="nav-menu">
            <a href="/View/templates/teacher/home.php"
                class="nav-link <?php echo ($current_page == 'home.php' && strpos($_SERVER['PHP_SELF'], '/teacher/') !== false) ? 'active' : ''; ?>">
                <span>Tableau de bord</span>
            </a>
            <a href="/View/templates/teacher/planifier_rattrapage.php"
                class="nav-link <?php echo ($current_page == 'planifier_rattrapage.php') ? 'active' : ''; ?>">
                <span>Planifier rattrapage</span>
            </a>
            <a href="/View/templates/teacher/evaluations.php"
                class="nav-link <?php echo ($current_page == 'evaluations.php') ? 'active' : ''; ?>">
                <span>Mes évaluations</span>
            </a>
            <a href="/View/templates/teacher/statistics.php"
                class="nav-link <?php echo ($current_page == 'statistics.php' && strpos($_SERVER['PHP_SELF'], '/teacher/') !== false) ? 'active' : ''; ?>">
                <span>Statistiques</span>
            </a>
        </nav>
    <?php endif; ?>

    <?php if ($user_role === 'secretary'): ?>
        <div class="header-icons">
            <a href="<?php echo htmlspecialchars($home_url); ?>" class="icon-link" title="Accueil">
                <div class="icon home">🏠</div>
            </a>
        <?php endif; ?>
        <?php if ($user_role === 'student'): ?>
            <a href="/View/templates/student/info.php" class="icon-link" title="Informations et procédure">
                <div class="icon info-icon">
                    <span>❓</span>
                </div>
            </a>
        <?php endif; ?>
        <a href="/View/templates/shared/settings.php" class="icon-link">
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
                <a href="../../../controllers/logout.php" class="dropdown-item logout">
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
