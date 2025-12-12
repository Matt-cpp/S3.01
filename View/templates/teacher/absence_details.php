<?php
require_once __DIR__ . '/../../../controllers/auth_guard.php';
$user = requireRole('teacher');

require_once __DIR__ . '/../../../Presenter/teacher/absence_details_presenter.php';
// Récupération de l'ID du cours depuis les paramètres GET
$courseSlotId = isset($_GET['course_slot_id']) ? (int) $_GET['course_slot_id'] : 0;
if ($courseSlotId <= 0) {
    die("ID de cours invalide.");
} else {
    $detailsAbs = new detailsAbs($courseSlotId);
    $courseInfo = $detailsAbs->getAbsenceDetails();
    $absences = $detailsAbs->getAbsences();
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <title>Détails des Absences</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/../../includes/theme-helper.php';
    renderThemeSupport(); ?>
    <link rel="stylesheet" href="<?php echo __DIR__ . '/../../assets/css/teacher/absence_details.css?v=' . time(); ?>">
    <style>
        <?php include __DIR__ . '/../../assets/css/teacher/absence_details.css'; ?>
    </style>
</head>

<body>
    <?php include __DIR__ . '/../navbar.php'; ?>
    <main class="container">
        <h1>Détails des Absences</h1>

        <div class="course-info">
            <h2><?php echo htmlspecialchars($courseInfo['label'] ?? 'Non défini'); ?></h2>
            <p>Date: <?php echo htmlspecialchars($courseInfo['course_date'] ?? 'Non définie'); ?></p>
            <p>Heure: <?php echo htmlspecialchars($courseInfo['start_time'] ?? 'Non définie'); ?></p>
        </div>

        <div class="absences-list">
            <h3>Liste des absences</h3>
            <?php if (empty($absences)): ?>
                <p>Aucune absence enregistrée pour ce cours.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Justifié</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($absences as $absence): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($absence['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($absence['first_name']); ?></td>
                                <td><?php echo $absence['justified'] ? 'Oui' : 'Non'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <a href="home.php" class="back-link">← Retour au tableau de bord</a>
    </main>
    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>

</html>