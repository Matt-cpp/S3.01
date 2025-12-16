<?php
// Page affichant les évaluations avec des élèves absents pour les professeurs
require_once __DIR__ . '/../../../controllers/auth_guard.php';
$user = requireRole('teacher');

require_once __DIR__ . '/../../../Presenter/teacher/evaluations_presenter.php';

// ID du professeur from session
$teacherId = $user['id'];
$table = new pageEvalProf($teacherId);

// Gestion du filtre
if (isset($_GET['filtre']) && !empty($_GET['filtre'])) {
    $table->activerUnFiltre($_GET['filtre']);
} else {
    $table->activerUnFiltre('course_slots.course_date');
}

// Récupération des évaluations avec le filtre actif
$evaluations = $table->lesEvaluations();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    
    <title>Tableau des Evaluations</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/../../includes/theme-helper.php';
    renderThemeSupport(); ?>
    <link rel="stylesheet" href="<?php echo __DIR__ . '/../../assets/css/teacher/evaluations.css?v=' . time(); ?>">
    <link rel="icon" type="image/x-icon" href="../../img/logoIUT.ico">
    <style>
        <?php include __DIR__ . '/../../assets/css/teacher/evaluations.css'; ?>
    </style>
</head>

<body>
    <?php include __DIR__ . '/../navbar.php'; ?>
    <main class="container">
        <h1>Tableau des Evaluations</h1>
        <div class="section">
            <!--Selection du filtre -->
            <form method="GET" class="filter-group">
                <span class="filter-label">Trier Par :</span>
                <select class="select-input" name="filtre" onchange="this.form.submit()">
                    <option value="course_slots.course_date" <?php echo (isset($_GET['filtre']) && $_GET['filtre'] === 'course_slots.course_date') ? 'selected' : ''; ?>>Par date du cours</option>
                    <option value="nb_justifications" <?php echo (isset($_GET['filtre']) && $_GET['filtre'] === 'nb_justifications') ? 'selected' : ''; ?>>Par nombres d'absences justifiées
                    </option>
                    <option value="nbabs" <?php echo (isset($_GET['filtre']) && $_GET['filtre'] === 'nbabs') ? 'selected' : ''; ?>>Par nombres d'absences</option>
                </select>
            </form>

            <table class="table">
                <thead>
                    <tr>
                        <th>Matière</th>
                        <th>Date</th>
                        <th>Heures</th>
                        <th>Nombre d'Absences</th>
                        <th>Nombre de Justifications</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Affiche si aucune évaluations -->
                    <?php if (empty($evaluations)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 2rem; color: #666;">
                                Aucune évaluation trouvée
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($evaluations as $eval): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($eval['label']); ?></td>
                                <td><?php echo htmlspecialchars($eval['course_date']); ?></td>
                                <td><?php echo htmlspecialchars($eval['start_time']); ?></td>
                                <td><?php echo htmlspecialchars($eval['nbabs']); ?></td>
                                <td><?php echo htmlspecialchars($eval['nb_justifications']); ?></td>
                                <td>
                                    <button class="info-button" onclick="window.location.href='information_DS.php?course_slot_id=<?php echo $eval['course_slot_id']; ?>'">Voir les détails</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </tbody>
            </table>
        </div>
    </main>
    
    <?php renderThemeScript(); ?>
</body>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

</html>