<?php

declare(strict_types=1);

/**
 * Proof history template for the academic manager - Consultation and management.
 * Main features:
 * - Multi-criteria search and filtering:
 *   - Search by student name
 *   - Filtering by submission period (start and end date)
 *   - Filtering by proof status (pending, accepted, rejected, under review)
 *   - Filtering by absence reason
 * - Detailed display of all proofs with statistics
 * - Result count
 * - Complete table with:
 *   - Student information and absence period
 *   - Number of absences and total hours covered
 *   - Reason, status and visual badge
 *   - Link to detailed view
 * Uses ProofHistoryPresenter to manage filters and retrieve data.
 */

require_once __DIR__ . '/../../../Presenter/shared/auth_guard.php';
$user = requireRole('academic_manager');

require_once __DIR__ . '/../../../Presenter/academic_manager/historique_proof.php';

// Instantiate the presenter
$presenter = new ProofHistoryPresenter();

$proofs = $presenter->getProofs();
$reasons = $presenter->getProofReasons();
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
    <link rel="stylesheet" href="../../assets/css/shared/responsive.css">
    <link rel="stylesheet" href="../../assets/css/shared/responsive-mobile.css">
    <link rel="icon" type="image/x-icon" href="../../img/logoIUT.ico">
    <title>Historique des justificatifs</title>
    <?php include __DIR__ . '/../../includes/theme-helper.php';
    renderThemeSupport(); ?>
</head>

<body>
    <?php include __DIR__ . '/../shared/navbar.php'; ?>
    <main>
        <h1 class="page-title">Historique des justificatifs</h1>

        <?php if (!empty($errorMessage)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <!-- Multi-criteria proof filtering form -->
        <form method="POST" action="">
            <div class="filter-grid">
                <div class="filter-input">
                    <label for="nameFilter">Nom de l'étudiant</label>
                    <input type="text" name="nameFilter" id="nameFilter" placeholder="Rechercher par nom..." 
                        value="<?php echo htmlspecialchars($filters['name'] ?? ''); ?>">
                </div>
                <div class="filter-input">
                    <label for="firstDateFilter">Date de début</label>
                    <input type="date" name="firstDateFilter" id="firstDateFilter" 
                        placeholder="Date de début"
                        value="<?php echo htmlspecialchars($filters['start_date'] ?? ''); ?>">
                </div>
                <div class="filter-input">
                    <label for="lastDateFilter">Date de fin</label>
                    <input type="date" name="lastDateFilter" id="lastDateFilter" 
                        placeholder="Date de fin"
                        value="<?php echo htmlspecialchars($filters['end_date'] ?? ''); ?>">
                </div>
                <div class="filter-input">
                    <label for="statusFilter">Statut</label>
                    <select name="statusFilter" id="statusFilter">
                    <option value="">Tous les statuts</option>
                    <option value="En attente" <?php echo (($filters['status'] ?? '') === 'En attente') ? 'selected' : ''; ?>>En attente</option>
                    <option value="Acceptée" <?php echo (($filters['status'] ?? '') === 'Acceptée') ? 'selected' : ''; ?>>Acceptée</option>
                    <option value="Rejetée" <?php echo (($filters['status'] ?? '') === 'Rejetée') ? 'selected' : ''; ?>>Rejetée</option>
                    <option value="En cours d'examen" <?php echo (($filters['status'] ?? '') === 'En cours d\'examen') ? 'selected' : ''; ?>>En cours d'examen</option>
                </select>
                </div>
                <div class="filter-input">
                    <label for="reasonFilter">Motif</label>
                    <select name="reasonFilter" id="reasonFilter">
                    <option value="">Tous les motifs</option>
                    <?php foreach ($reasons as $reason): ?>
                        <option value="<?php echo htmlspecialchars($reason['main_reason']); ?>"
                            <?php echo (($filters['reason'] ?? '') === $reason['main_reason']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($presenter->translateReason($reason['main_reason'])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                </div>
            </div>
            <div class="button-container">
                <button type="submit" id="filterButton">
                    Filtrer
                </button>
                <a href="historique_proof.php" class="btn btn-secondary">
                    Réinitialiser
                </a>
            </div>
        </form>

        <div class="results-counter">
            <strong>Nombre de justificatifs trouvés: <?php echo count($proofs); ?></strong>
        </div>

        <table id="proofTable">
            <thead>
                <tr>
                    <th>Étudiant</th>
                    <th>Groupe</th>
                    <th>Date de début</th>
                    <th>Date de fin</th>
                    <th>Motif</th>
                    <th>Statut</th>
                    <th>Date de soumission</th>
                    <th>Fichier</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($proofs)): ?>
                    <tr>
                        <td colspan="9" class="no-results">
                            Aucun justificatif trouvé avec les critères sélectionnés.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($proofs as $proof): ?>
                        <tr>
                            <td data-label="Étudiant"><?php echo htmlspecialchars(($proof['last_name'] ?? '') . ' ' . ($proof['first_name'] ?? '')); ?></td>
                            <td data-label="Groupe"><?php echo htmlspecialchars($proof['group_label'] ?? 'N/A'); ?></td>
                            <td data-label="Début"><?php echo htmlspecialchars($presenter->formatDate($proof['absence_start_date'])); ?></td>
                            <td data-label="Fin"><?php echo htmlspecialchars($presenter->formatDate($proof['absence_end_date'])); ?></td>
                            <td data-label="Motif">
                                <span class="motif-value"><?php
                                echo htmlspecialchars($presenter->translateReason($proof['main_reason']));
                                if (!empty($proof['custom_reason'])) {
                                    echo '<br><small class="custom-reason-text">(' . htmlspecialchars($proof['custom_reason']) . ')</small>';
                                }
                                ?></span>
                            </td>
                            <td data-label="Statut" class="status-cell">
                                <?php
                                $status = $presenter->translateStatus($proof['status']);
                                $statusClass = '';
                                switch ($proof['status']) {
                                    case 'pending':
                                        $statusClass = 'status-en-attente';
                                        break;
                                    case 'accepted':
                                        $statusClass = 'status-acceptee';
                                        break;
                                    case 'rejected':
                                        $statusClass = 'status-rejetee';
                                        break;
                                    case 'under_review':
                                        $statusClass = 'status-en-cours';
                                        break;
                                    default:
                                        $statusClass = 'status-default';
                                }
                                ?>
                                <span class="status-text <?php echo $statusClass; ?>">
                                    <?php echo htmlspecialchars($status); ?>
                                </span>
                            </td>
                            <td data-label="Soumis le"><?php echo htmlspecialchars($presenter->formatDate($proof['submission_date'])); ?></td>
                            <td data-label="Fichier">
                                <?php
                                $files = $presenter->getProofFiles($proof);
                                if (!empty($files)): ?>
                                    <div class="file-links-container">
                                        <?php foreach ($files as $index => $file): ?>
                                            <a href="../../../Presenter/student/view_upload_proof.php?proof_id=<?php echo $proof['proof_id']; ?>&file_index=<?php echo $index; ?>"
                                                target="_blank"
                                                title="<?php echo htmlspecialchars($file['original_name'] ?? 'Fichier ' . ($index + 1)); ?>"
                                                class="file-link">
                                                📄 <?php echo ($index + 1); ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="no-proof">-</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Action">
                                <a href="<?php echo htmlspecialchars($presenter->getProofDetailsUrl($proof['proof_id'])); ?>"
                                    class="btn-view-action">
                                    Voir
                                </a>
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
