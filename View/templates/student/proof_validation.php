<?php

declare(strict_types=1);
/**
 * File: proof_validation.php
 *
 * Confirmation template after submitting an absence proof.
 * Main features:
 * - Success message display after submission
 * - Complete summary of the submitted proof information
 * - Student information display (name, first name, department, degree)
 * - Absence period details and affected courses
 * - Absence reason and optional comments
 * - List of uploaded proof files
 * - PDF summary download option
 * Data is retrieved from the session after validation by the Presenter.
 */

require_once __DIR__ . '/../../../Presenter/shared/auth_guard.php';
$user = requireRole('student');
require_once __DIR__ . '/../../../Model/format_ressource.php';

// Use the authenticated user's ID
if (!isset($_SESSION['id_student'])) {
    $_SESSION['id_student'] = $user['id'];
}

date_default_timezone_set('Europe/Paris');

// Check for proof data in session
// Redirect to the form if no data is available
if (!isset($_SESSION['reason_data'])) {
    // If no data in session, redirect back to form
    header("Location: student_proof.php");
    exit();
}

$studentInfo = $_SESSION['student_info'] ?? null;

// Retrieve uploaded file information from session
$uploadedFileName = $_SESSION['reason_data']['proof_file'] ?? 'Fichier non disponible';
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../../img/logoIUT.ico">
    <title>Validé</title>
    <link rel="stylesheet" href="../../assets/css/student/proof_validation.css">
    <link rel="stylesheet" href="../../assets/css/shared/responsive.css">
    <link rel="stylesheet" href="../../assets/css/shared/responsive-mobile.css">
    <?php include __DIR__ . '/../../includes/theme-helper.php';
    renderThemeSupport(); ?>
</head>

<body>
    <?php include __DIR__ . '/../shared/navbar.php'; ?>
    <div class="container">
        <h1>Votre justificatif a été envoyé</h1>

        <div class="success-message">
            <strong>Succès !</strong> Votre demande de justificatif d'absence a été enregistrée avec succès.
            Un email vous a été envoyé récapitulant les informations de votre justificatif.
        </div>
        <?php
        if (!$studentInfo) {
            echo '<div class="warning-message">';
            echo '<strong>Attention :</strong> Informations de l\'étudiant non disponibles.';
            echo '</div>';
        }
        ?>

        <!-- PDF summary generation and download form -->
        <form class="pdf-download" action="../../../Presenter/shared/generate_pdf.php" method="post" target="_blank">
            <input type="hidden" name="action" value="download_pdf_client">
            <button type="submit" class="btn-pdf">
                Télécharger le récapitulatif PDF
            </button>
        </form>

        <h3>Récapitulatif de votre demande :</h3>
        <ul id="summary-list-validation">
            <?php
            if ($studentInfo) {
                echo '<li><strong>Informations de l\'étudiant :</strong> ';
                echo '<li><strong>Nom :</strong> ' . htmlspecialchars($studentInfo['last_name']) . '</li>';
                echo '<li><strong>Prénom :</strong> ' . htmlspecialchars($studentInfo['first_name']) . '</li>';

                if (!empty($studentInfo['middle_name'])) {
                    echo '<li><strong>Deuxième prénom :</strong> ' . htmlspecialchars($studentInfo['middle_name']) . '</li>';
                }

                if (!empty($studentInfo['department'])) {
                    echo '<li><strong>Département :</strong> ' . htmlspecialchars($studentInfo['department']) . '</li>';
                }
                if (!empty($studentInfo['degrees'])) {
                    echo '<li><strong>Diplôme(s) :</strong> ' . htmlspecialchars($studentInfo['degrees']) . '</li>';
                }
                if (!empty($studentInfo['birth_date'])) {
                    $timezone = new DateTimeZone('Europe/Paris');
                    $birthDate = new DateTime($studentInfo['birth_date'], $timezone);
                    echo '<li><strong>Date de naissance :</strong> ' . $birthDate->format('d/m/Y') . '</li>';
                }
                if (!empty($studentInfo['email'])) {
                    echo '<li><strong>Email :</strong> ' . htmlspecialchars($studentInfo['email']) . '</li>';
                }
            }
            ?>
            <li><strong>Date et heure de début :</strong>
                <?php
                $timezone = new DateTimeZone('Europe/Paris');
                $datetimeStart = new DateTime($_SESSION['reason_data']['datetime_start'], $timezone);
                echo $datetimeStart->format('d/m/Y à H:i:s');
                ?>
            </li>
            <li><strong>Date et heure de fin :</strong>
                <?php
                $datetimeEnd = new DateTime($_SESSION['reason_data']['datetime_end'], $timezone);
                echo $datetimeEnd->format('d/m/Y à H:i:s');
                ?>
            </li>
            <li><strong>Motif de l'absence :</strong>
                <?php echo htmlspecialchars($_SESSION['reason_data']['absence_reason']); ?></li>
            <?php if (!empty($_SESSION['reason_data']['other_reason'])): ?>
                <li><strong>Précision du motif :</strong>
                    <?php echo htmlspecialchars($_SESSION['reason_data']['other_reason']); ?></li>
            <?php endif; ?>

            <?php
            // Display uploaded files
            $proofFiles = $_SESSION['reason_data']['proof_files'] ?? [];
            $fileCount = is_array($proofFiles) ? count($proofFiles) : 0;

            if ($fileCount > 0):
            ?>
                <li><strong>Fichier(s) justificatif(s) :</strong> <?php echo $fileCount; ?>
                    fichier<?php echo $fileCount > 1 ? 's' : ''; ?>
                    <ul style="margin-top: 5px;">
                        <?php foreach ($proofFiles as $file): ?>
                            <li>
                                <?php echo htmlspecialchars($file['original_name']); ?>
                                (<?php echo round($file['file_size'] / 1024, 2); ?> Ko)
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            <?php else: ?>
                <li><strong>Fichier(s) justificatif(s) :</strong> Aucun fichier fourni</li>
            <?php endif; ?>

            <?php if (!empty($_SESSION['reason_data']['comments'])): ?>
                <li><strong>Commentaires :</strong>
                    <?php echo nl2br(htmlspecialchars($_SESSION['reason_data']['comments'])); ?></li>
            <?php endif; ?>
            <li><strong>Date de soumission :</strong>
                <?php
                $submissionDate = new DateTime($_SESSION['reason_data']['submission_date']);
                $submissionDate->setTimezone(new DateTimeZone('Europe/Paris'));
                echo $submissionDate->format('d/m/Y à H:i:s');
                ?>
            </li>
        </ul>

        <?php
        // Display absence statistics if available
        $statsHours = floatval($_SESSION['reason_data']['stats_hours'] ?? 0);
        $statsHalfdays = floatval($_SESSION['reason_data']['stats_halfdays'] ?? 0);
        $statsEvaluations = intval($_SESSION['reason_data']['stats_evaluations'] ?? 0);
        $statsCourseTypes = json_decode($_SESSION['reason_data']['stats_course_types'] ?? '{}', true);
        $statsEvaluationDetails = json_decode($_SESSION['reason_data']['stats_evaluation_details'] ?? '[]', true);
        $cours = $_SESSION['reason_data']['class_involved'];



        // Show statistics section if we have hours data OR course data
        if ($statsHours > 0 || (!empty($cours) && $cours !== '')):
        ?>
            <div class="absence-statistics">
                <h3>Analyse détaillée des absences</h3>
                <div class="stats-container">
                    <?php if ($statsHours > 0): ?>
                        <div class="stats-summary">
                            <div class="stat-item">
                                <span class="stat-label">Nombre total d'heures :</span>
                                <span class="stat-value"><?php echo number_format($statsHours, 1); ?>h</span>
                            </div>

                            <?php if ($statsHalfdays > 0): ?>
                                <div class="stat-item">
                                    <span class="stat-label">Demi-journées :</span>
                                    <span class="stat-value"><?php echo number_format($statsHalfdays, 1); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($statsCourseTypes)): ?>
                                <div class="stat-item">
                                    <span class="stat-label">Types de cours :</span>
                                    <div class="course-types">
                                        <?php foreach ($statsCourseTypes as $type => $count): ?>
                                            <span class="course-type-tag"><?php echo htmlspecialchars($type); ?>
                                                (<?php echo $count; ?>)</span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($statsEvaluations > 0): ?>
                                <div class="stat-item evaluation-warning">
                                    <span class="stat-label">⚠️ Évaluations manquées :</span>
                                    <span class="stat-value evaluation-count"><?php echo $statsEvaluations; ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($cours) && $cours !== ''): ?>
                        <div class="courses-details">
                            <h4>Cours concernés</h4>
                            <div class="courses-list">
                                <?php
                                if (is_array($cours)) {
                                    foreach ($cours as $course) {
                                        echo '<div class="course-detail-item">' . htmlspecialchars($course) . '</div>';
                                    }
                                } else {
                                    $coursesArray = explode('; ', $cours);
                                    foreach ($coursesArray as $course) {
                                        if (trim($course)) {
                                            echo '<div class="course-detail-item">' . htmlspecialchars(trim($course)) . '</div>';
                                        }
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($statsEvaluations > 0 && !empty($statsEvaluationDetails)): ?>
                        <div class="evaluation-details">
                            <h4>⚠️ Détails des évaluations manquées</h4>
                            <div class="evaluation-list">
                                <?php foreach ($statsEvaluationDetails as $eval): ?>
                                    <div class="evaluation-detail">
                                        <div class="eval-header">
                                            <strong><?php echo htmlspecialchars(formatResourceLabel($eval['resource_label'] ?? 'Cours non spécifié')); ?></strong>
                                            <?php if (!empty($eval['resource_code'])): ?>
                                                <span class="eval-code">(<?php echo htmlspecialchars($eval['resource_code']); ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="eval-details">
                                            <?php echo htmlspecialchars($eval['course_date'] ?? ''); ?>
                                            <?php echo htmlspecialchars(substr($eval['start_time'] ?? '', 0, 5)) . ' - ' . htmlspecialchars(substr($eval['end_time'] ?? '', 0, 5)); ?></span>
                                            <?php if (!empty($eval['course_type'])): ?>
                                                <span class="eval-info"><?php echo htmlspecialchars($eval['course_type']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($eval['teacher'])): ?>
                                                <span class="eval-info"><?php echo htmlspecialchars($eval['teacher']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($eval['room'])): ?>
                                                <span class="eval-info"><?php echo htmlspecialchars($eval['room']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div style="margin-top: 30px; text-align: center; color: #6c757d;">
            <p><em>Conservez ce récapitulatif pour vos archives.</em></p>
        </div>
    </div>
    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>

</html>
