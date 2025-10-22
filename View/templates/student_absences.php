<!DOCTYPE html>
<html lang="fr">
<?php
session_start();
// FIXME Force student ID to 1 POUR LINSTANT
$_SESSION['id_student'] = 1;

require_once __DIR__ . '/../../Presenter/session_cache.php';
require_once __DIR__ . '/../../Presenter/student_absences_presenter.php';
require_once __DIR__ . '/../../Presenter/student_get_info.php';

$student_identifier = getStudentIdentifier($_SESSION['id_student']);

$presenter = new StudentAbsencesPresenter($student_identifier);

// Utiliser les données en session si disponibles et récentes (défini dans session_cache.php), par défaut 30 minutes
// sinon les récupérer de la BD
if (!isset($_SESSION['Absences']) || (!isset($_SESSION['CourseTypes']) || !isset($_SESSION['Filters']) || !isset($_SESSION['ErrorMessage'])) || shouldRefreshCache(1)) {
    
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
    <?php include __DIR__ . '/student_navbar.php'; ?>
    
    <main>
        <h1 class="page-title">Mes Absences</h1>

        <?php if (!empty($errorMessage)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

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
            <strong>Nombre d'absences trouvées: <?php echo count($absences); ?></strong>
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
                            <tr>
                                <td><?php echo $presenter->formatDate($absence['course_date']); ?></td>
                                <td><?php echo $presenter->formatTime($absence['start_time'], $absence['end_time']); ?></td>
                                <td>
                                    <div>
                                        <?php echo htmlspecialchars($absence['course_name'] ?? 'Non spécifié'); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    if (!empty($absence['teacher_first_name']) && !empty($absence['teacher_last_name'])) {
                                        echo htmlspecialchars($absence['teacher_first_name'] . ' ' . $absence['teacher_last_name']);
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($absence['room_name'] ?? '-'); ?></td>
                                <td><strong><?php echo number_format($absence['duration_minutes'] / 60, 1); ?>h</strong></td>
                                <td>
                                    <?php 
                                    $courseType = $absence['course_type'] ?? 'Non spécifié';
                                    $badge_class = '';
                                    $emoji = '';
                                    
                                    switch($courseType) {
                                        case 'CM':
                                            $badge_class = 'badge-cm';
                                            $emoji = '📚';
                                            break;
                                        case 'TD':
                                            $badge_class = 'badge-td';
                                            $emoji = '✏️';
                                            break;
                                        case 'TP':
                                            $badge_class = 'badge-tp';
                                            $emoji = '💻';
                                            break;
                                        default:
                                            $badge_class = 'badge-other';
                                            $emoji = '📖';
                                    }
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo $emoji . ' ' . htmlspecialchars($courseType); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($absence['is_evaluation']): ?>
                                        <span class="badge badge-evaluation-yes">Oui</span>
                                    <?php else: ?>
                                        <span class="badge badge-evaluation-no">Non</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $presenter->translateMotif($absence['motif'], $absence['custom_motif']); ?></td>
                                <td>
                                    <?php 
                                    $status = $presenter->getProofStatus($absence);
                                    ?>
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