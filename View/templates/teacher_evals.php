<?php


require_once __DIR__ . '/../../Presenter/LesEvaluations.php';
require_once __DIR__ . '/../../Presenter/tableRatrapage.php';
// ID du professeur from session
$teacherId = 13; // À remplacer par l'ID réel du professeur connecté
$table = new pageEvalProf($teacherId);
?>

<!DOCTYPE html>
<html lang="fr">s
<head>
    <title>Tableau des Evaluations</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <link rel="stylesheet" href="<?php echo __DIR__ . '/../assets/css/teacher_evals.css'; ?>">
</head>

<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <main class="main-content">
        <h1>Tableau des Evaluations</h1>
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
                $evaluations = $table->lesEvaluations("course_slots.course_date");
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
</body>
</html>