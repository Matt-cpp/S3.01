<?php 
require_once __DIR__ . '/../../Presenter/historique.php';
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
    <link rel="stylesheet" href="/View/assets/css/style_historique.css">
    <title>Historique des absences</title>
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <main>
        <?php if (!empty($errorMessage)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="../../Presenter/index.php">
            <div class="filter-grid">
                <input type="text" name="nameFilter" id="nameFilter" placeholder="Rechercher par nom..." 
                    value="<?php echo htmlspecialchars($filters['name'] ?? ''); ?>">
                <input type="date" name="firstDateFilter" id="firstDateFilter" 
                    value="<?php echo htmlspecialchars($filters['start_date'] ?? ''); ?>">
                <input type="date" name="lastDateFilter" id="lastDateFilter" 
                    value="<?php echo htmlspecialchars($filters['end_date'] ?? ''); ?>">
                <select name="statusFilter" id="statusFilter">
                    <option value="">Tous les statuts</option>
                    <option value="justifiée" <?php echo (($filters['status'] ?? '') === 'justifiée') ? 'selected' : ''; ?>>Justifiée</option>
                    <option value="non_justifiée" <?php echo (($filters['status'] ?? '') === 'non_justifiée') ? 'selected' : ''; ?>>Non Justifiée</option>
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
                <a href="../../Presenter/index.php" class="reset-link">
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
                        <td colspan="7" class="no-results">
                            Aucune absence trouvée avec les critères sélectionnés.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($absences as $absence): ?>
                        <tr class="<?php echo $absence['status'] ? 'status-justified' : 'status-unjustified'; ?>">
                            <td><?php echo htmlspecialchars($absence['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($absence['course']); ?></td>
                            <td><?php echo htmlspecialchars($presenter->formatDate($absence['date'])); ?></td>
                            <td><?php echo htmlspecialchars($presenter->formatTime($absence['start_time'], $absence['end_time'])); ?></td>
                            <td><?php echo htmlspecialchars($absence['course_type'] ?? 'Non spécifié'); ?></td>
                            <td><?php echo htmlspecialchars($presenter->translateMotif($absence['motif'])); ?></td>
                            <td>
                                <?php if ($absence['status']): ?>
                                    <span class="badge badge-success">Justifiée</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Non justifiée</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($presenter->hasProof($absence)): ?>
                                    <button onclick="window.open('<?php echo htmlspecialchars($presenter->getProofPath($absence)); ?>', '_blank')" class="btn_export               ">
                                        <img src="/View/img/export.png" alt="export-icon" class="export">
                                    </button>
                                <?php else: ?>
                                    <span class="no-proof"></span>
                                    <?php endif; ?>
                                </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

    </main>
</body>
</html>