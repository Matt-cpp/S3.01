<?php
//Page Principake du tableau de bord enseignant
require_once __DIR__ . '/../../../controllers/auth_guard.php';
$user = requireRole('teacher');

require_once __DIR__ . '/../../../Presenter/teacher/dashboard.php';
require_once __DIR__ . '/../../../Presenter/teacher/makeup_table_presenter.php';

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
    <title data-translate="page_title">Tableau de bord - Enseignant</title>
    <meta charset="UTF-8">
    <?php include __DIR__ . '/../../includes/theme-helper.php';
    renderThemeSupport(); ?>
    <link rel="icon" type="image/x-icon" href="../../img/logoIUT.ico">
    <link rel="stylesheet" href="<?php echo __DIR__ . '/../../assets/css/teacher/home.css'; ?>">
    <link rel="stylesheet" href="../../assets/css/shared/language-switcher.css">
    <style>
        <?php include __DIR__ . '/../../assets/css/teacher/home.css'; ?>
    </style>
</head>

<body data-page="teacher_home">
    <?php include __DIR__ . '/../navbar.php'; ?>

    <div class="main-content">
        <h1 class="page-title" data-translate="dashboard_title">Tableau de bord - Professeur</h1>

        <!-- Vue globale des absences -->
        <div class="section">
            <h2 class="section-title" data-translate="global_view">Vue globale des absences</h2>

            <form method="GET" class="filter-group">
                <span class="filter-label" data-translate="absent_students">Étudiants absents</span>
                <select class="select-input" name="filtre" onchange="this.form.submit()">
                    <option value="" data-translate="all_courses">Tous les cours</option>
                    <?php foreach ($ressourcesLabels as $label): ?>
                        <option value="<?php echo htmlspecialchars($label); ?>" <?php echo (isset($_GET['filtre']) && $_GET['filtre'] === $label) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($_GET['filtre']) && !empty($_GET['filtre'])): ?>
                    <a href="?reset_filtre=1" class="btn-primary"
                        style="padding: 0.5rem 1rem; font-size: 12px; text-decoration: none;"
                        data-translate="reset">Réinitialiser</a>
                <?php endif; ?>
            </form>

            <table class="table">
                <thead>
                    <tr>
                        <th data-translate="name_firstname">Nom / Prénom</th>
                        <th data-translate="group">Groupe</th>
                        <th data-translate="subject">Matière</th>
                        <th data-translate="absence_date">Date d'absence</th>
                        <th data-translate="status">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($donnees)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 2rem; color: #666;"
                                data-translate="no_absence_found">
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
                                    echo '<span data-translate="excused">Excusée</span>';
                                } elseif ($ligne['status'] == 'unjustified') {
                                    echo '<span data-translate="unjustified">Non justifiée</span>';
                                } elseif ($ligne['status'] == 'justified') {
                                    echo '<span data-translate="justified">Justifiée</span>';
                                } elseif ($ligne['status'] == 'absent') {
                                    echo '<span data-translate="absent">Absent</span>';
                                } else {
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
                        style="padding: 0.5rem 1rem; font-size: 14px; text-decoration: none; <?php echo ($table->getCurrentPage() == 0) ? 'opacity: 0.5; pointer-events: none;' : ''; ?>"
                        data-translate="previous">
                        Précédent
                    </a>
                    <span style="font-weight: 500; color: #333;">
                        <span data-translate="page">Page</span> <?php echo ($table->getCurrentPage() + 1); ?> <span
                            data-translate="of">sur</span> <?php echo $table->getNombrePages(); ?>
                    </span>
                    <a href="?page=<?php echo $table->getNextPage(); ?><?php echo isset($_GET['filtre']) ? '&filtre=' . urlencode($_GET['filtre']) : ''; ?>"
                        class="btn-primary"
                        style="padding: 0.5rem 1rem; font-size: 14px; text-decoration: none; <?php echo ($table->getCurrentPage() >= $table->getNombrePages() - 1) ? 'opacity: 0.5; pointer-events: none;' : ''; ?>"
                        data-translate="next">
                        Suivant
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Gestion des rattrapages -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title" data-translate="makeup_management">Gestion des rattrapages</h2>
                <a href="planifier_rattrapage.php" class="btn-primary" style="text-decoration: none;"
                    data-translate="schedule_makeup">Planifier un
                    rattrapage</a>
            </div>

            <h3 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem;" data-translate="students_makeup">
                Étudiants à rattraper</h3>

            <table class="table">
                <thead>
                    <tr>
                        <th data-translate="name_firstname">Nom / Prénom</th>
                        <th data-translate="subject">Matière</th>
                        <th data-translate="absence_date">Date d'absence</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($donneesRattrapage)): ?>
                        <tr>
                            <td colspan="3" style="text-align: center; padding: 2rem; color: #666;"
                                data-translate="no_student_makeup">
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
                        style="padding: 0.5rem 1rem; font-size: 14px; text-decoration: none; <?php echo ($tableRattrapage->getCurrentPage() == 0) ? 'opacity: 0.5; pointer-events: none;' : ''; ?>"
                        data-translate="previous">
                        Précédent
                    </a>
                    <span style="font-weight: 500; color: #333;">
                        <span data-translate="page">Page</span> <?php echo ($tableRattrapage->getCurrentPage() + 1); ?>
                        <span data-translate="of">sur</span>
                        <?php echo $tableRattrapage->getNombrePages(); ?>
                    </span>
                    <a href="?page_rattrapage=<?php echo $tableRattrapage->getNextPage(); ?><?php echo isset($_GET['page']) ? '&page=' . $_GET['page'] : ''; ?><?php echo isset($_GET['filtre']) ? '&filtre=' . urlencode($_GET['filtre']) : ''; ?>"
                        class="btn-primary"
                        style="padding: 0.5rem 1rem; font-size: 14px; text-decoration: none; <?php echo ($tableRattrapage->getCurrentPage() >= $tableRattrapage->getNombrePages() - 1) ? 'opacity: 0.5; pointer-events: none;' : ''; ?>"
                        data-translate="next">
                        Suivant
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../../assets/js/translations.js"></script>
    <?php include __DIR__ . '/../../includes/footer.php'; ?>
    <?php renderThemeScript(); ?>
</body>

</html>