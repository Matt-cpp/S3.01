<!DOCTYPE html>
<html lang="fr">

<head>
    <title>Tableau de bord - Responsable Pédagogique</title>
    <meta charset="UTF-8">
    <?php include __DIR__ . '/../includes/theme-helper.php';
    renderThemeSupport(); ?>
    <link rel="stylesheet" href="../assets/css/accueil.css">
</head>

<body>
    <?php
    require_once __DIR__ . '/../../controllers/auth_guard.php';
    $user = requireRole('academic_manager');

    require_once __DIR__ . '/../../Presenter/tableauDeBord.php';
    $donnes = new backendTableauDeBord();
    ?>
    <?php include __DIR__ . '/navbar.php'; ?>

    <div class="main-content">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-title">Absences du jour</div>
                <div class="stat-number"><?php echo $donnes->todayAbs() ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Cumul des absences du mois</div>
                <div class="stat-number"><?php echo $donnes->thisMonthAbs() ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Absences non justifiées</div>
                <div class="stat-number"><?php echo $donnes->unjustifiedAbs() ?></div>
            </div>
        </div>

        <div class="absences-section">
            <h2 class="section-title">Absences Récentes</h2>
            <p class="section-subtitle">Dernières absences signalées dans le système</p>
            <?php
            if (isset($_GET['page'])) {
                $page = intval($_GET['page']);
                $donnes->setPage($page);
            }
            ?>

            <div class="pagination">
                <div>Page <?php echo ($donnes->getCurrentPage()) + 1; ?> sur <?php echo $donnes->getTotalPages(); ?>
                </div>
                <div class="pagination-buttons">
                    <a href="?page=<?php echo $donnes->getPreviousPage(); ?>">
                        <button class="btn" type="button">Précédent</button>
                    </a>
                    <a href="?page=<?php echo $donnes->getNextPage(); ?>">
                        <button class="btn" type="button">Suivant</button>
                    </a>
                </div>
            </div>

            <table class="table">
                <thead>
                    <!-- Add your table headers here if needed -->
                </thead>
                <tbody>
                    <?php
                    $f = $donnes->laTable();
                    $tabel = json_decode(json_encode($f), true);

                    foreach ($tabel as $row) {
                        echo "<tr>";
                        foreach ($row as $cell) {
                            echo "<td>" . htmlspecialchars($cell) . "</td>";
                        }
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>

            <div class="history-section">
                <a href="historique.php" class="btn-history">Consulter l'historique</a>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="footer-content">
            <div class="team-section">
                <h3 class="team-title">Équipe de développement</h3>
                <div class="team-names">
                    <p>CIPOLAT Matteo • BOLTZ Louis • NAVREZ Louis • COLLARD Yony • BISIAUX Ambroise • FOURNIER
                        Alexandre</p>
                </div>
            </div>
            <div class="footer-info">
                <p>&copy; 2025 UPHF - Système de gestion des absences</p>
            </div>
        </div>
    </footer>
    <?php renderThemeScript(); ?>
</body>

</html>