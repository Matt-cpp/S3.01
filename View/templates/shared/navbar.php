<?php

declare(strict_types=1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Retrieve user information or use default values
$userFirstName = $_SESSION['user_first_name'] ?? 'Utilisateur';
$userLastName = $_SESSION['user_last_name'] ?? '';
$userEmail = $_SESSION['user_email'] ?? 'email@example.com';
$userRole = $_SESSION['user_role'] ?? 'student';

// Get home page URL based on role
require_once __DIR__ . '/../../../Presenter/shared/auth_guard.php';
$homeUrl = getUserHomePage($userRole);

// Determine the current page for students
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<link rel="stylesheet" href="/View/assets/css/shared/navbar.css">
<header class="header">
    <!-- Hamburger Menu Button (mobile) -->
    <?php if ($userRole !== 'secretary'): ?>
    <button class="hamburger-btn" id="hamburgerBtn" aria-label="Menu" aria-expanded="false">
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
    </button>
    <?php endif; ?>

    <div class="logo">
        <img id="logo" src="/View/img/UPHF_logo.png" alt="Logo UPHF" />
    </div>

    <?php if ($userRole === 'student'): ?>
        <!-- Student Navigation Menu -->
        <nav class="nav-menu">
            <a href="/View/templates/student/home.php"
                class="nav-link <?php echo ($currentPage == 'home.php' && strpos($_SERVER['PHP_SELF'], '/student/') !== false) ? 'active' : ''; ?>">Tableau
                de bord</a>
            <a href="/View/templates/student/absences.php"
                class="nav-link <?php echo ($currentPage == 'absences.php') ? 'active' : ''; ?>">Mes absences</a>
            <a href="/View/templates/student/proofs.php"
                class="nav-link <?php echo ($currentPage == 'proofs.php') ? 'active' : ''; ?>">Mes justificatifs</a>
            <a href="/View/templates/student/statistics.php"
                class="nav-link <?php echo ($currentPage == 'statistics.php' && strpos($_SERVER['PHP_SELF'], '/student/') !== false) ? 'active' : ''; ?>">Statistiques</a>
            <a href="/View/templates/student/proof_submit.php"
                class="nav-link nav-link-primary <?php echo ($currentPage == 'proof_submit.php') ? 'active' : ''; ?>">+
                Soumettre justificatif</a>
        </nav>
    <?php elseif ($userRole === 'academic_manager'): ?>
        <!-- Academic Manager Navigation Menu -->
        <nav class="nav-menu">
            <a href="/View/templates/academic_manager/home.php"
                class="nav-link <?php echo ($currentPage == 'home.php' && strpos($_SERVER['PHP_SELF'], '/academic_manager/') !== false) ? 'active' : ''; ?>">
                <span>Tableau de bord</span>
            </a>
            <a href="/View/templates/academic_manager/historique.php"
                class="nav-link <?php echo ($currentPage == 'historique.php' && strpos($_SERVER['PHP_SELF'], '/academic_manager/') !== false) ? 'active' : ''; ?>">
                <span>Absences</span>
            </a>
            <a href="/View/templates/academic_manager/historique_proof.php"
                class="nav-link <?php echo ($currentPage == 'historique_proof.php') ? 'active' : ''; ?>">Justificatifs</a>
            <a href="/View/templates/academic_manager/statistics.php"
                class="nav-link <?php echo ($currentPage == 'statistics.php' && strpos($_SERVER['PHP_SELF'], '/academic_manager/') !== false) ? 'active' : ''; ?>">Statistiques</a>
        </nav>
    <?php elseif ($userRole === 'teacher'): ?>
        <!-- Teacher Navigation Menu -->
        <nav class="nav-menu">
            <a href="/View/templates/teacher/home.php"
                class="nav-link <?php echo ($currentPage == 'home.php' && strpos($_SERVER['PHP_SELF'], '/teacher/') !== false) ? 'active' : ''; ?>">Tableau
                de bord</a>
            <a href="/View/templates/teacher/planifier_rattrapage.php"
                class="nav-link <?php echo ($currentPage == 'planifier_rattrapage.php') ? 'active' : ''; ?>">Planifier
                rattrapage</a>
            <a href="/View/templates/teacher/evaluations.php"
                class="nav-link <?php echo ($currentPage == 'evaluations.php') ? 'active' : ''; ?>">Mes évaluations</a>
            <a href="/View/templates/teacher/statistics.php"
                class="nav-link <?php echo ($currentPage == 'statistics.php' && strpos($_SERVER['PHP_SELF'], '/teacher/') !== false) ? 'active' : ''; ?>">Statistiques</a>
        </nav>
    <?php endif; ?>

    <div class="header-icons">
        <?php if ($userRole === 'secretary'): ?>
            <a href="<?php echo htmlspecialchars($homeUrl); ?>" class="icon-link" title="Accueil">
                <div class="icon home">🏠</div>
            </a>
            <a href="../../../Presenter/shared/logout_presenter.php" class="icon-link" title="Se déconnecter">
                <div class="icon logout-icon"></div>
            </a>
        <?php endif; ?>
        <?php if ($userRole === 'student'): ?>
            <a href="/View/templates/student/info.php" class="icon-link" title="Informations et procédure">
                <div class="icon info-icon">
                    <span>❓</span>
                </div>
            </a>
        <?php endif; ?>
        <a href="/View/templates/shared/settings.php" class="icon-link">
            <div class="icon settings" title="Paramètres"></div>
        </a>
        <?php if ($userRole !== 'secretary'): ?>
        <div class="icon profile" title="Profil" id="profileIcon">
            <div class="profile-dropdown" id="profileDropdown">
                <div class="dropdown-header">
                    <div class="dropdown-user-info">
                        <span
                            class="user-name"><?php echo htmlspecialchars(trim($userFirstName . ' ' . $userLastName)); ?></span>
                        <span class="user-email"><?php echo htmlspecialchars($userEmail); ?></span>
                    </div>
                </div>
                <div class="dropdown-divider"></div>
                <a href="../../../Presenter/shared/logout_presenter.php" class="dropdown-item logout">
                    <span>Déconnexion</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</header>

<script>
    // Profile dropdown toggle
    document.addEventListener('DOMContentLoaded', function () {
        const profileIcon = document.getElementById('profileIcon');
        const profileDropdown = document.getElementById('profileDropdown');

        if (profileIcon && profileDropdown) {
            profileIcon.addEventListener('click', function (e) {
                e.stopPropagation();
                profileDropdown.classList.toggle('show');
            });

            // Close menu when clicking elsewhere
            document.addEventListener('click', function (e) {
                if (!profileIcon.contains(e.target)) {
                    profileDropdown.classList.remove('show');
                }
            });

            // Prevent closing when clicking inside the menu
            profileDropdown.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        }

        // Hamburger menu
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const navMenu = document.querySelector('.nav-menu');
        const overlay = document.createElement('div');
        overlay.className = 'mobile-menu-overlay';
        document.body.appendChild(overlay);

        if (hamburgerBtn && navMenu) {
            hamburgerBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                hamburgerBtn.classList.toggle('active');
                navMenu.classList.toggle('mobile-open');
                overlay.classList.toggle('active');
                document.body.classList.toggle('menu-open');

                // Accessibility
                const isExpanded = hamburgerBtn.classList.contains('active');
                hamburgerBtn.setAttribute('aria-expanded', isExpanded);
            });

            // Close menu when clicking the overlay
            overlay.addEventListener('click', function () {
                hamburgerBtn.classList.remove('active');
                navMenu.classList.remove('mobile-open');
                overlay.classList.remove('active');
                document.body.classList.remove('menu-open');
                hamburgerBtn.setAttribute('aria-expanded', 'false');
            });

            // Close menu when clicking a link
            navMenu.querySelectorAll('.nav-link').forEach(function (link) {
                link.addEventListener('click', function () {
                    hamburgerBtn.classList.remove('active');
                    navMenu.classList.remove('mobile-open');
                    overlay.classList.remove('active');
                    document.body.classList.remove('menu-open');
                    hamburgerBtn.setAttribute('aria-expanded', 'false');
                });
            });
        }
    });
</script>

<?php
