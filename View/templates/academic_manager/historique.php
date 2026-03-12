<?php
/**
 * Fichier: historique.php
 * 
 * Template de l'historique des absences pour le responsable pédagogique - Consultation avancée.
 * Fonctionnalités principales :
 * - Recherche et filtrage multi-critères :
 *   - Recherche par nom d'étudiant
 *   - Filtrage par période (date de début et fin)
 *   - Filtrage par statut de justification
 *   - Filtrage par type de cours
 * - Affichage détaillé de toutes les absences avec justificatifs associés
 * - Compteur du nombre de résultats trouvés
 * - Tableau complet avec toutes les informations (date, heure, étudiant, cours, type, évaluation, statut)
 * Utilise HistoriquePresenter pour gérer les filtres et récupérer les données.
 */

require_once __DIR__ . '/../../../Presenter/shared/auth_guard.php';
$user = requireRole('academic_manager');

require_once __DIR__ . '/../../../Presenter/academic_manager/historique.php';
require_once __DIR__ . '/../../../Model/format_ressource.php';

// Instantiate the presenter
$presenter = new HistoriquePresenter();

$absences = $presenter->getAbsences();
$courseTypes = $presenter->getCourseTypes();
$filters = $presenter->getFilters();
$errorMessage = $presenter->getErrorMessage();

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/academic_manager/historique.css">
    <link rel="icon" type="image/x-icon" href="../../img/logoIUT.ico">
    <title>Historique des absences</title>
    <?php include __DIR__ . '/../../includes/theme-helper.php';
    renderThemeSupport(); ?>
</head>

<body>
    <?php include __DIR__ . '/../navbar.php'; ?>
    <main>
        <?php if (!empty($errorMessage)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire de filtrage multi-critères -->
        <form method="POST" action="">
            <div class="filter-grid">
                <input type="text" name="nameFilter" id="nameFilter" placeholder="Rechercher par nom..."
                    value="<?php echo htmlspecialchars($filters['name'] ?? ''); ?>">
                <input type="date" name="firstDateFilter" id="firstDateFilter"
                    value="<?php echo htmlspecialchars($filters['start_date'] ?? ''); ?>">
                <input type="date" name="lastDateFilter" id="lastDateFilter"
                    value="<?php echo htmlspecialchars($filters['end_date'] ?? ''); ?>">
                <select name="JustificationStatusFilter" id="JustificationStatusFilter">
                    <option value="">Tous les statuts</option>
                    <option value="En attente" <?php echo (($filters['JustificationStatus'] ?? '') === 'En attente') ? 'selected' : ''; ?>>En attente</option>
                    <option value="Acceptée" <?php echo (($filters['JustificationStatus'] ?? '') === 'Acceptée') ? 'selected' : ''; ?>>Acceptée</option>
                    <option value="Rejetée" <?php echo (($filters['JustificationStatus'] ?? '') === 'Rejetée') ? 'selected' : ''; ?>>Rejetée</option>
                    <option value="En cours d'examen" <?php echo (($filters['JustificationStatus'] ?? '') === 'En cours d\'examen') ? 'selected' : ''; ?>>En cours d'examen</option>
                    <option value="Non justifiée" <?php echo (($filters['JustificationStatus'] ?? '') === 'Non justifiée') ? 'selected' : ''; ?>>Non justifiée</option>
                </select>
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
            <div class="button-container">
                <button type="submit" id="filterButton">
                    Filtrer
                </button>
                <a href="historique.php" class="reset-link">
                    Réinitialiser
                </a>
            </div>
        </form>

        <div class="results-counter">
            <strong>Nombre d'absences trouvées: <?php echo count($absences); ?></strong>
        </div>

        <table id="absenceTable">
            <thead>
                <tr>
                    <th>Étudiant</th>
                    <th>Cours</th>
                    <th>Date</th>
                    <th>Horaire</th>
                    <th>Type</th>
                    <th>Motif</th>
                    <th>Statut</th>
                    <th>Preuve</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($absences)): ?>
                    <tr>
                        <td colspan="8" class="no-results">
                            Aucune absence trouvée avec les critères sélectionnés.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($absences as $absence): ?>
                        <tr class="<?php echo $absence['status'] ? 'status-justified' : 'status-unjustified'; ?>">
                            <td><?php echo htmlspecialchars($absence['student_name']); ?></td>
                            <td><?php echo htmlspecialchars(formatResourceLabel($absence['course'] ?? 'Non spécifié')); ?></td>
                            <td><?php echo htmlspecialchars($presenter->formatDate($absence['date'])); ?></td>
                            <td><?php echo htmlspecialchars($presenter->formatTime($absence['start_time'], $absence['end_time'])); ?></td>
                            <td><?php echo htmlspecialchars($absence['course_type'] ?? 'Non spécifié'); ?></td>
                            <td><?php echo htmlspecialchars($presenter->translateMotif($absence['motif'])); ?></td>
                            <td class="status-cell">
                                <?php
                                $status = $presenter->getStatus($absence);
                                $statusClass = '';
                                switch ($status) {
                                    case 'En attente':
                                        $statusClass = 'status-en-attente';
                                        break;
                                    case 'Acceptée':
                                        $statusClass = 'status-acceptee';
                                        break;
                                    case 'Rejetée':
                                        $statusClass = 'status-rejetee';
                                        break;
                                    case 'En cours d\'examen':
                                        $statusClass = 'status-en-cours';
                                        break;
                                    case 'Justifiée':
                                        $statusClass = 'status-justifiee';
                                        break;
                                    case 'Non justifiée':
                                        $statusClass = 'status-non-justifiee';
                                        break;
                                    default:
                                        $statusClass = 'status-default';
                                }
                                ?>
                                <span class="status-text <?php echo $statusClass; ?>">
                                    <?php echo htmlspecialchars($status); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $files = $presenter->getProofFiles($absence);
                                if (!empty($files)): ?>
                                    <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                        <?php foreach ($files as $index => $file): ?>
                                            <a href="../../../Presenter/student/view_upload_proof.php?proof_id=<?php echo $absence['proof_id']; ?>&file_index=<?php echo $index; ?>"
                                                target="_blank"
                                                title="<?php echo htmlspecialchars($file['original_name'] ?? 'Fichier ' . ($index + 1)); ?>"
                                                style="padding: 4px 8px; background: #4338ca; color: white; text-decoration: none; border-radius: 3px; font-size: 12px;">
                                                📄 <?php echo ($index + 1); ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="no-proof">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

    </main>
    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>

</html>