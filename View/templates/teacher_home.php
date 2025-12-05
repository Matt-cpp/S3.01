<?php
//Page Principake du tableau de bord enseignant
require_once __DIR__ . '/../../controllers/auth_guard.php';
$user = requireRole('teacher');

require_once __DIR__ . '/../../Presenter/teachertable.php';
require_once __DIR__ . '/../../Presenter/tableRatrapage.php';

// ID du professeur from session
$teacherId = $user['id'];
$table = new teacherTable($teacherId);
$tableRattrapage = new tableRatrapage($teacherId);

// Gestion de la pagination
if (isset($_GET['page'])) {
    $page = intval($_GET['page']);
    $table->setPage($page);
}

// Gestion de la pagination rattrapages
if (isset($_GET['page_rattrapage'])) {
    $pageRattrapage = intval($_GET['page_rattrapage']);
    $tableRattrapage->setPage($pageRattrapage);
}

// Gestion du filtre
if (isset($_GET['filtre']) && !empty($_GET['filtre'])) {
    $table->activerUnFiltre($_GET['filtre']);
} elseif (isset($_GET['reset_filtre'])) {
    $table->desactiverUnFiltre();
}

// Récupération des données
$donnees = $table->getData($table->getCurrentPage());
$ressourcesLabels = $table->getRessourcesLabels();
$donneesRattrapage = $tableRattrapage->getData($tableRattrapage->getCurrentPage());
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <title>Tableau de bord - Enseignant</title>
    <meta charset="UTF-8">
    <?php include __DIR__ . '/../includes/theme-helper.php';
    renderThemeSupport(); ?>
    <link rel="stylesheet" href="<?php echo __DIR__ . '/../assets/css/welcome_teacher.css'; ?>">
    <style>
        <?php include __DIR__ . '/../assets/css/welcome_teacher.css'; ?>
    </style>
</head>

<body>
    <?php include __DIR__ . '/navbar.php'; ?>

    <div class="main-content">
        <h1 class="page-title">Tableau de bord - Professeur</h1>

        <!-- Vue globale des absences -->
        <div class="section">
            <h2 class="section-title">Vue globale des absences</h2>

            <form method="GET" class="filter-group">
                <span class="filter-label">Étudiants absents</span>
                <select class="select-input" name="filtre" onchange="this.form.submit()">
                    <option value="">Tous les cours</option>
                    <?php foreach ($ressourcesLabels as $label): ?>
                        <option value="<?php echo htmlspecialchars($label); ?>" <?php echo (isset($_GET['filtre']) && $_GET['filtre'] === $label) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($_GET['filtre']) && !empty($_GET['filtre'])): ?>
                    <a href="?reset_filtre=1" class="btn-primary"
                        style="padding: 0.5rem 1rem; font-size: 12px; text-decoration: none;">Réinitialiser</a>
                <?php endif; ?>
            </form>

            <table class="table">
                <thead>
                    <tr>
                        <th>Nom / Prénom</th>
                        <th>Groupe</th>
                        <th>Matière</th>
                        <th>Date d'absence</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($donnees)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 2rem; color: #666;">
                                Aucune absence trouvée
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($donnees as $ligne): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ligne['last_name'] . ' ' . $ligne['first_name']); ?></td>
                                <td><?php echo htmlspecialchars($ligne['degrees']); ?></td>
                                <td><?php echo htmlspecialchars($ligne['label']); ?></td>
                                <td><?php echo htmlspecialchars($ligne['course_date']); ?></td>
                                <td><?php 
                                if ($ligne['status'] == 'excused') {
                                    echo 'Excusée';
                                } elseif ($ligne['status'] == 'unjustified') {
                                    echo 'Non justifiée';
                                } elseif ($ligne['status'] == 'justified') {
                                    echo 'Justifiée';
                                } elseif ($ligne['status'] == 'absent') {
                                    echo 'Absent';
                                }
                                else {
                                    echo htmlspecialchars($ligne['status']);
                                }
                                 ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($table->getNombrePages() > 1): ?>
                <div style="display: flex; justify-content: center; align-items: center; gap: 1rem; margin-top: 1.5rem;">
                    <a href="?page=<?php echo $table->getPreviousPage(); ?><?php echo isset($_GET['filtre']) ? '&filtre=' . urlencode($_GET['filtre']) : ''; ?>"
                        class="btn-primary"
                        style="padding: 0.5rem 1rem; font-size: 14px; text-decoration: none; <?php echo ($table->getCurrentPage() == 0) ? 'opacity: 0.5; pointer-events: none;' : ''; ?>">
                        Précédent
                    </a>
                    <span style="font-weight: 500; color: #333;">
                        Page <?php echo ($table->getCurrentPage() + 1); ?> sur <?php echo $table->getNombrePages(); ?>
                    </span>
                    <a href="?page=<?php echo $table->getNextPage(); ?><?php echo isset($_GET['filtre']) ? '&filtre=' . urlencode($_GET['filtre']) : ''; ?>"
                        class="btn-primary"
                        style="padding: 0.5rem 1rem; font-size: 14px; text-decoration: none; <?php echo ($table->getCurrentPage() >= $table->getNombrePages() - 1) ? 'opacity: 0.5; pointer-events: none;' : ''; ?>">
                        Suivant
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Gestion des rattrapages -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">Gestion des rattrapages</h2>
                <a href="planifier_rattrapage.php" class="btn-primary" style="text-decoration: none;">Planifier un
                    rattrapage</a>
            </div>

            <h3 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem;">Étudiants à rattraper</h3>

            <table class="table">
                <thead>
                    <tr>
                        <th>Nom / Prénom</th>
                        <th>Matière</th>
                        <th>Date d'absence</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($donneesRattrapage)): ?>
                        <tr>
                            <td colspan="3" style="text-align: center; padding: 2rem; color: #666;">
                                Aucun étudiant à rattraper
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($donneesRattrapage as $ligne): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ligne['last_name'] . ' ' . $ligne['first_name']); ?></td>
                                <td><?php echo htmlspecialchars($ligne['label']); ?></td>
                                <td><?php echo htmlspecialchars($ligne['course_date']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination rattrapages -->
            <?php if ($tableRattrapage->getNombrePages() > 1): ?>
                <div style="display: flex; justify-content: center; align-items: center; gap: 1rem; margin-top: 1.5rem;">
                    <a href="?page_rattrapage=<?php echo $tableRattrapage->getPreviousPage(); ?><?php echo isset($_GET['page']) ? '&page=' . $_GET['page'] : ''; ?><?php echo isset($_GET['filtre']) ? '&filtre=' . urlencode($_GET['filtre']) : ''; ?>"
                        class="btn-primary"
                        style="padding: 0.5rem 1rem; font-size: 14px; text-decoration: none; <?php echo ($tableRattrapage->getCurrentPage() == 0) ? 'opacity: 0.5; pointer-events: none;' : ''; ?>">
                        Précédent
                    </a>
                    <span style="font-weight: 500; color: #333;">
                        Page <?php echo ($tableRattrapage->getCurrentPage() + 1); ?> sur
                        <?php echo $tableRattrapage->getNombrePages(); ?>
                    </span>
                    <a href="?page_rattrapage=<?php echo $tableRattrapage->getNextPage(); ?><?php echo isset($_GET['page']) ? '&page=' . $_GET['page'] : ''; ?><?php echo isset($_GET['filtre']) ? '&filtre=' . urlencode($_GET['filtre']) : ''; ?>"
                        class="btn-primary"
                        style="padding: 0.5rem 1rem; font-size: 14px; text-decoration: none; <?php echo ($tableRattrapage->getCurrentPage() >= $tableRattrapage->getNombrePages() - 1) ? 'opacity: 0.5; pointer-events: none;' : ''; ?>">
                        Suivant
                    </a>
                </div>
            <?php endif; ?>
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