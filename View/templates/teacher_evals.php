<?php
// le filtre marche pas encore

require_once __DIR__ . '/../../Presenter/LesEvaluations.php';
require_once __DIR__ . '/../../Presenter/tableRatrapage.php';
// ID du professeur from session
$teacherId = 13; // À remplacer par l'ID réel du professeur connecté
$table = new pageEvalProf($teacherId);
// Gestion du filtre
if (isset($_GET['filtre']) && !empty($_GET['filtre'])) {
    $table->activerUnFiltre($_GET['filtre']);
}
else {
    $table->activerUnFiltre('course_slots.course_date');
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Tableau des Evaluations</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo __DIR__ . '/../assets/css/teacher_evals.css?v=' . time(); ?>">
    <style>
        <?php include __DIR__ . '/../assets/css/teacher_evals.css'; ?>
    </style>
</head>

<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <main class="container">
        
        <h1>Tableau des Evaluations</h1>
        
            <form method="GET" class="filter-form">
                <span class="filter-label">Trier Par : </span>
                <select>
                    <option value="course_slots.course_date">Par date du cours</option>
                    <option value ="nb_justifications">Par nombres d'absences justifiées</option>
                    <option value ="nbabs">Par nombres d'absences</option>
                </select>
                <button type="submit" name="filtre" value="apply">Appliquer le filtre</button>
            </form>
        <table>
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
                <?php
                $evaluations = $table->lesEvaluations('course_slots.course_date');
                foreach ($evaluations as $eval) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($eval['label']) . "</td>";
                    echo "<td>" . htmlspecialchars($eval['course_date']) . "</td>";
                    echo "<td>" . htmlspecialchars($eval['start_time']) . "</td>";
                    echo "<td>" . htmlspecialchars($eval['nbabs']) . "</td>";
                    echo "<td>" . htmlspecialchars($eval['nb_justifications']) . "</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </main>
</body>
</html>