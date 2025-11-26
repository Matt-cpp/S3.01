<!DOCTYPE html>
<html lang="fr">
<?php
require_once __DIR__ . '/../../controllers/auth_guard.php';
$user = requireRole('student');

// Use the authenticated user's ID
if (!isset($_SESSION['id_student'])) {
    $_SESSION['id_student'] = $user['id'];
}

require_once __DIR__ . '/../../Presenter/session_cache.php';
require_once __DIR__ . '/../../Presenter/student_absences_presenter.php';
require_once __DIR__ . '/../../Presenter/student_get_info.php';

$student_identifier = getStudentIdentifier($_SESSION['id_student']);

$presenter = new StudentAbsencesPresenter($student_identifier);

// Utiliser les données en session si disponibles et récentes
if (!isset($_SESSION['Absences']) || (!isset($_SESSION['CourseTypes']) || !isset($_SESSION['Filters']) || !isset($_SESSION['ErrorMessage'])) || shouldRefreshCache(15)) {
    
    $absences = $presenter->getAbsences();
    $courseTypes = $presenter->getCourseTypes();
    $_SESSION['Absences'] = $absences;
    $_SESSION['CourseTypes'] = $courseTypes;
    updateCacheTimestamp();
}

$absences = $_SESSION['Absences'];
$courseTypes = $_SESSION['CourseTypes'];
$filters = $presenter->getFilters();
$errorMessage = $presenter->getErrorMessage();
?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../img/logoIUT.ico">
    <title>Mes Absences</title>

    <link rel="stylesheet" href="../assets/css/student_absences.css">
</head>

<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    
    <main>
        <h1 class="page-title">Mes Absences</h1>

        <?php if (!empty($errorMessage)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <?php
        // Display success message if there's one in session
        if (isset($_SESSION['success_message'])) {
            echo '<div class="success-message" style="background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px;">';
            echo '<strong>✅ Succès:</strong> ' . htmlspecialchars($_SESSION['success_message']);
            echo '</div>';
            unset($_SESSION['success_message']); // Clear the message after displaying
        }

        // Display success message from URL parameter
        if (isset($_GET['success'])) {
            echo '<div class="success-message" style="background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px;">';
            echo '<strong>✅ Succès:</strong> Votre justificatif a été modifié avec succès et repassé en attente de validation !';
            echo '</div>';
        }
        ?>

        <form method="POST" class="filter-form">
            <div class="filter-grid">
                <div class="filter-input">
                    <label for="firstDateFilter">Date de début</label>
                    <input type="date" name="firstDateFilter" id="firstDateFilter" 
                        value="<?php echo htmlspecialchars($filters['start_date'] ?? ''); ?>">
                </div>
                
                <div class="filter-input">
                    <label for="lastDateFilter">Date de fin</label>
                    <input type="date" name="lastDateFilter" id="lastDateFilter" 
                        value="<?php echo htmlspecialchars($filters['end_date'] ?? ''); ?>">
                </div>
                
                <div class="filter-input">
                    <label for="statusFilter">Statut</label>
                    <select name="statusFilter" id="statusFilter">
                        <option value="">Tous les statuts</option>
                        <option value="justifiée" <?php echo (($filters['status'] ?? '') === 'justifiée') ? 'selected' : ''; ?>>Justifiée</option>
                        <option value="en_attente" <?php echo (($filters['status'] ?? '') === 'en_attente') ? 'selected' : ''; ?>>En attente</option>
                        <option value="en_revision" <?php echo (($filters['status'] ?? '') === 'en_revision') ? 'selected' : ''; ?>>En révision</option>
                        <option value="refusé" <?php echo (($filters['status'] ?? '') === 'refusé') ? 'selected' : ''; ?>>Refusé</option>
                        <option value="non_justifiée" <?php echo (($filters['status'] ?? '') === 'non_justifiée') ? 'selected' : ''; ?>>Non Justifiée</option>
                    </select>
                </div>
                
                <div class="filter-input">
                    <label for="courseTypeFilter">Type de cours</label>
                    <select name="courseTypeFilter" id="courseTypeFilter">
                        <option value="">Tous les types</option>
                        <?php foreach ($courseTypes as $type): ?>
                            <option value="<?php echo htmlspecialchars($type['course_type']); ?>" 
                                    <?php echo (($filters['course_type'] ?? '') === $type['course_type']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['course_type']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="button-container">
                <button type="submit">Filtrer</button>
                <a href="student_absences.php" class="reset-link">Réinitialiser</a>
            </div>
        </form>

        <div class="results-counter">
            <strong>Nombre d'absences trouvées: <?php echo count($absences); ?> • Demi-journées manquées: <?php echo $presenter->getTotalHalfDays($absences); ?></strong>
        </div>

        <div class="table-container">
            <table id="absenceTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Horaire</th>
                        <th>Cours</th>
                        <th>Enseignant</th>
                        <th>Salle</th>
                        <th>Durée</th>
                        <th>Type</th>
                        <th>Évaluation</th>
                        <th>Motif</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($absences)): ?>
                        <tr>
                            <td colspan="10" class="no-results">
                                Aucune absence trouvée avec les critères sélectionnés.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($absences as $absence): ?>
                            <?php 
                            $courseType = strtoupper($absence['course_type'] ?? 'Autre');
                            $badge_class = '';
                            
                            switch($courseType) {
                                case 'CM':
                                    $badge_class = 'badge-cm';
                                    break;
                                case 'TD':
                                    $badge_class = 'badge-td';
                                    break;
                                case 'TP':
                                    $badge_class = 'badge-tp';
                                    break;
                                default:
                                    $badge_class = 'badge-other';
                            }
                            
                            $status = $presenter->getProofStatus($absence);
                            $teacher = !empty($absence['teacher_first_name']) && !empty($absence['teacher_last_name']) 
                                ? htmlspecialchars($absence['teacher_first_name'] . ' ' . $absence['teacher_last_name']) 
                                : '-';
                            
                            // Déterminer le statut pour la couleur de la bordure
                            $modalStatus = 'none';
                            if ($absence['proof_status'] === 'accepted') $modalStatus = 'accepted';
                            elseif ($absence['proof_status'] === 'rejected') $modalStatus = 'rejected';
                            elseif ($absence['proof_status'] === 'under_review') $modalStatus = 'under_review';
                            elseif ($absence['proof_status'] === 'pending') $modalStatus = 'pending';
                            ?>
                            <tr class="absence-row" style="cursor: pointer;"
                                data-modal-status="<?php echo $modalStatus; ?>"
                                data-date="<?php echo htmlspecialchars($presenter->formatDate($absence['course_date'])); ?>"
                                data-time="<?php echo htmlspecialchars($presenter->formatTime($absence['start_time'], $absence['end_time'])); ?>"
                                data-course="<?php echo htmlspecialchars($absence['course_name'] ?? 'Non spécifié'); ?>"
                                data-course-code="<?php echo htmlspecialchars($absence['course_code'] ?? ''); ?>"
                                data-teacher="<?php echo $teacher; ?>"
                                data-room="<?php echo htmlspecialchars($absence['room_name'] ?? '-'); ?>"
                                data-duration="<?php echo number_format($absence['duration_minutes'] / 60, 1); ?>"
                                data-type="<?php echo htmlspecialchars($courseType); ?>"
                                data-type-badge="<?php echo $badge_class; ?>"
                                data-evaluation="<?php echo $absence['is_evaluation'] ? 'Oui' : 'Non'; ?>"
                                data-is-evaluation="<?php echo $absence['is_evaluation'] ? '1' : '0'; ?>"
                                data-has-makeup="<?php echo !empty($absence['makeup_id']) ? '1' : '0'; ?>"
                                data-makeup-scheduled="<?php echo !empty($absence['makeup_scheduled']) ? '1' : '0'; ?>"
                                data-makeup-date="<?php echo !empty($absence['makeup_date']) ? $presenter->formatDate($absence['makeup_date']) : ''; ?>"
                                data-makeup-time="<?php echo !empty($absence['makeup_start_time']) && !empty($absence['makeup_end_time']) ? $presenter->formatTime($absence['makeup_start_time'], $absence['makeup_end_time']) : ''; ?>"
                                data-makeup-duration="<?php echo !empty($absence['makeup_duration']) ? number_format($absence['makeup_duration'] / 60, 1) : ''; ?>"
                                data-makeup-room="<?php echo htmlspecialchars($absence['makeup_room'] ?? ''); ?>"
                                data-makeup-resource="<?php echo htmlspecialchars($absence['makeup_resource_label'] ?? ''); ?>"
                                data-makeup-comment="<?php echo htmlspecialchars($absence['makeup_comment'] ?? ''); ?>"
                                data-motif="<?php echo htmlspecialchars($presenter->translateMotif($absence['motif'], $absence['custom_motif'])); ?>"
                                data-status-text="<?php echo $status['text']; ?>"
                                data-status-icon="<?php echo $status['icon']; ?>"
                                data-status-class="<?php echo $status['class']; ?>">
                                <td><?php echo $presenter->formatDate($absence['course_date']); ?></td>
                                <td><?php echo $presenter->formatTime($absence['start_time'], $absence['end_time']); ?></td>
                                <td>
                                    <div>
                                        <?php echo htmlspecialchars($absence['course_name'] ?? 'Non spécifié'); ?>
                                    </div>
                                </td>
                                <td><?php echo $teacher; ?></td>
                                <td><?php echo htmlspecialchars($absence['room_name'] ?? '-'); ?></td>
                                <td><strong><?php echo number_format($absence['duration_minutes'] / 60, 1); ?>h</strong></td>
                                <td>
                                    <span class="course-type-badge <?php echo $badge_class; ?>">
                                        <?php echo htmlspecialchars($courseType); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($absence['is_evaluation']): ?>
                                        <span class="eval-badge">⚠️ Oui</span>
                                        <?php if (!empty($absence['makeup_id']) && !empty($absence['makeup_scheduled'])): ?>
                                            <br><span class="makeup-badge" style="background-color: #17a2b8; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-top: 4px; display: inline-block;">📝 Rattrapage prévu</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="no-eval">Non</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $presenter->translateMotif($absence['motif'], $absence['custom_motif']); ?></td>
                                <td>
                                    <span class="badge <?php echo $status['class']; ?>">
                                        <?php echo $status['icon'] . ' ' . $status['text']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Modal pour afficher les détails de l'absence -->
    <div id="absenceModal" class="modal">
        <div class="modal-overlay"></div>
        <div id="modalContent" class="modal-content">
            <button class="modal-close" id="closeModal">&times;</button>
            <h2 class="modal-title">Détails de l'Absence</h2>
            <div class="modal-body">
                <div class="modal-info-group">
                    <div class="modal-info-item">
                        <span class="modal-label">📅 Date :</span>
                        <span class="modal-value" id="modalDate"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label">🕐 Horaire :</span>
                        <span class="modal-value" id="modalTime"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label">⏱️ Durée :</span>
                        <span class="modal-value" id="modalDuration"></span>
                    </div>
                </div>

                <div class="modal-info-group">
                    <div class="modal-info-item">
                        <span class="modal-label">📚 Cours :</span>
                        <span class="modal-value" id="modalCourse"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label">👨‍🏫 Enseignant :</span>
                        <span class="modal-value" id="modalTeacher"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label">🚪 Salle :</span>
                        <span class="modal-value" id="modalRoom"></span>
                    </div>
                </div>

                <div class="modal-info-group">
                    <div class="modal-info-item">
                        <span class="modal-label">📝 Type :</span>
                        <span class="modal-value">
                            <span id="modalType" class="badge"></span>
                        </span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label">📝 Évaluation :</span>
                        <span class="modal-value" id="modalEvaluation"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label">📄 Motif :</span>
                        <span class="modal-value" id="modalMotif"></span>
                    </div>
                </div>

                <!-- Section Évaluation ratée (visible uniquement si is_evaluation) -->
                <div id="evaluationSection" class="modal-info-group" style="display: none; background-color: #fff3cd; padding: 15px; border-radius: 8px; margin-top: 15px;">
                    <h3 style="color: #856404; margin-bottom: 10px; font-size: 16px;">⚠️ Évaluation ratée</h3>
                    <div class="modal-info-item">
                        <span class="modal-label">📚 Évaluation :</span>
                        <span class="modal-value" id="evaluationCourse"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label">📅 Date :</span>
                        <span class="modal-value" id="evaluationDate"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label">🕐 Horaire :</span>
                        <span class="modal-value" id="evaluationTime"></span>
                    </div>
                </div>

                <!-- Section Rattrapage (visible uniquement si makeup existe) -->
                <div id="makeupSection" class="modal-info-group" style="display: none; background-color: #d1ecf1; padding: 15px; border-radius: 8px; margin-top: 15px;">
                    <h3 style="color: #0c5460; margin-bottom: 10px; font-size: 16px;">📝 Rattrapage prévu</h3>
                    <div class="modal-info-item">
                        <span class="modal-label">📅 Date du rattrapage :</span>
                        <span class="modal-value" id="makeupDate"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label">🕐 Horaire :</span>
                        <span class="modal-value" id="makeupTime"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label">⏱️ Durée :</span>
                        <span class="modal-value" id="makeupDuration"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label">🚪 Salle :</span>
                        <span class="modal-value" id="makeupRoom"></span>
                    </div>
                    <div class="modal-info-item" id="makeupResourceItem" style="display: none;">
                        <span class="modal-label">📚 Matière :</span>
                        <span class="modal-value" id="makeupResource"></span>
                    </div>
                    <div class="modal-info-item" id="makeupCommentItem" style="display: none;">
                        <span class="modal-label">💬 Commentaire :</span>
                        <span class="modal-value" id="makeupComment"></span>
                    </div>
                </div>

                <div class="modal-status-section">
                    <span class="modal-label">🏷️ Statut :</span>
                    <span id="modalStatus" class="badge"></span>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/absence_modal.js"></script>

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
</body>

</html>