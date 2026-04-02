<?php

declare(strict_types=1);
require_once __DIR__ . '/../../../Presenter/shared/auth_guard.php';
$user = requireRole('teacher');

require_once __DIR__ . '/../../../Presenter/teacher/absence_details_presenter.php';
require_once __DIR__ . '/../../../Model/format_ressource.php';
// Récupération de l'ID du cours depuis les paramètres GET
$courseSlotId = isset($_GET['course_slot_id']) ? (int) $_GET['course_slot_id'] : 0;
if ($courseSlotId <= 0) {
    die("ID de cours invalide.");
} else {
    $details = new AbsenceDetailsPresenter($courseSlotId);
    $courseInfo = $details->getAbsenceDetails();
    $absences = $details->getAbsences();
    $makeupInfo = $details->getMakeupDetails();
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
    <link rel="stylesheet" href="<?php echo __DIR__ . '/../assets/css/information_DS.css?v=' . time(); ?>">
    <link rel="stylesheet" href="../../assets/css/shared/responsive.css">
    <link rel="stylesheet" href="../../assets/css/shared/responsive-mobile.css">
    <link rel="icon" type="image/x-icon" href="../../img/logoIUT.ico">
    <style>
        <?php include __DIR__ . '/../../assets/css/teacher/information_DS.css'; ?>
    </style>
</head>

<body>
    <?php include __DIR__ . '/../shared/navbar.php'; ?>
    <main class="container">
        <a href="evaluations.php" class="back-button">← Retour</a>
        <h1>Détails des Absences pour l'Évaluation</h1>
        <div class="course-info">
            <h2>
                <?php echo htmlspecialchars(formatResourceLabel($courseInfo['label'])); ?>
            </h2>
            <p>Date :
                <?php echo htmlspecialchars($courseInfo['course_date']); ?>
            </p>
            <p>Heure :
                <?php echo htmlspecialchars(!empty($courseInfo['start_time']) ? date('H:i', strtotime((string) $courseInfo['start_time'])) : 'Non définie'); ?>
            </p>
        </div>

        <div class="section">
            <h3>Rattrapage</h3>
            <?php if (empty($makeupInfo)): ?>
                <p>Aucun rattrapage planifié pour cette évaluation.</p>
            <?php else: ?>
                <p>Date :
                    <?php echo htmlspecialchars(!empty($makeupInfo['makeup_date']) ? date('d/m/Y', strtotime((string) $makeupInfo['makeup_date'])) : 'Non définie'); ?>
                </p>
                <p>Heure de début :
                    <?php echo htmlspecialchars(!empty($makeupInfo['makeup_start_time']) ? substr((string) $makeupInfo['makeup_start_time'], 0, 5) : 'Non définie'); ?>
                </p>
                <p>Salle :
                    <?php echo htmlspecialchars(!empty($makeupInfo['room_code']) ? (string) $makeupInfo['room_code'] : 'À définir'); ?>
                </p>
                <p>Durée :
                    <?php echo htmlspecialchars(!empty($makeupInfo['duration_minutes']) ? ((string) $makeupInfo['duration_minutes'] . ' min') : 'Non définie'); ?>
                </p>
                <p>Étudiants concernés : <?php echo htmlspecialchars((string) ($makeupInfo['planned_count'] ?? '0')); ?></p>
            <?php endif; ?>
        </div>

        <div class="section">
            <h3>Liste des Absences</h3>
            <?php if (empty($absences)): ?>
                <p>Aucune absence enregistrée pour cette évaluation.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Prénom</th>
                            <th>Nom</th>
                            <th>Justifiée</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($absences as $absence): ?>
                            <tr>
                                <td data-label="Prénom">
                                    <?php echo htmlspecialchars($absence['first_name']); ?>
                                </td>
                                <td data-label="Nom">
                                    <?php echo htmlspecialchars($absence['last_name']); ?>
                                </td>
                                <td data-label="Justifiée">
                                    <?php echo $absence['justified'] ? 'Oui' : 'Non'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
    <?php renderThemeScript(); ?>
</body>

</html>