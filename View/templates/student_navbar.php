<link rel="stylesheet" href="/View/assets/css/student_navbar.css">
<?php
// Déterminer la page actuelle
$current_page = basename($_SERVER['PHP_SELF']);
?>
<header class="header">
    <div class="logo">
        <img id="logo" src="/View/img/UPHF_logo.png" alt="Logo UPHF" />
    </div>
    <nav class="nav-menu">
        <a href="student_home_page.php" class="nav-link <?php echo ($current_page == 'student_home_page.php') ? 'active' : ''; ?>">
            <span class="nav-icon">📊</span>
            <span>Tableau de bord</span>
        </a>
        <a href="student_absences.php" class="nav-link <?php echo ($current_page == 'student_absences.php') ? 'active' : ''; ?>">
            <span class="nav-icon">📅</span>
            <span>Mes absences</span>
        </a>
        <a href="student_proofs.php" class="nav-link <?php echo ($current_page == 'student_proofs.php') ? 'active' : ''; ?>">
            <span class="nav-icon">📄</span>
            <span>Mes justificatifs</span>
        </a>
                <a href="student_statistics.php" class="nav-link <?php echo ($current_page == 'student_statistics.php') ? 'active' : ''; ?>">
            <span class="nav-icon">📈</span>
            <span>Statistiques</span>
        </a>
        <a href="student_proof_submit.php" class="nav-link nav-link-primary <?php echo ($current_page == 'student_proof_submit.php') ? 'active' : ''; ?>">
            <span class="nav-icon">➕</span>
            <span>Soumettre justificatif</span>
        </a>
    </nav>
    <div class="header-icons">
        <div class="icon notification"></div>
        <div class="icon settings"></div>
        <div class="icon profile"></div>
    </div>
</header>