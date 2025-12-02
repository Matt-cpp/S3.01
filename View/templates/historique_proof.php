<?php 
require_once __DIR__ . '/../../controllers/auth_guard.php';
$user = requireRole('academic_manager');

require_once __DIR__ . '/../../Presenter/historique_proof.php';

// Instantiate the presenter
$presenter = new HistoriqueProofPresenter();

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
    <link rel="stylesheet" href="../assets/css/style_historique.css">
    <link rel="icon" type="image/x-icon" href="../img/logoIUT.ico">
    <title>Historique des justificatifs</title>
    <?php include __DIR__ . '/../includes/theme-helper.php';
    renderThemeSupport(); ?>
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <main>
        <h1 style="text-align: center; margin-bottom: 30px; font-size: 2rem; font-weight: 700; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">Historique des justificatifs</h1>
        
        <?php if (!empty($errorMessage)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="filter-grid">
                <input type="text" name="nameFilter" id="nameFilter" placeholder="Rechercher par nom..." 
                    value="<?php echo htmlspecialchars($filters['name'] ?? ''); ?>">
                <input type="date" name="firstDateFilter" id="firstDateFilter" 
                    placeholder="Date de début"
                    value="<?php echo htmlspecialchars($filters['start_date'] ?? ''); ?>">
                <input type="date" name="lastDateFilter" id="lastDateFilter" 
                    placeholder="Date de fin"
                    value="<?php echo htmlspecialchars($filters['end_date'] ?? ''); ?>">
                <select name="statusFilter" id="statusFilter">
                    <option value="">Tous les statuts</option>
                    <option value="En attente" <?php echo (($filters['status'] ?? '') === 'En attente') ? 'selected' : ''; ?>>En attente</option>
                    <option value="Acceptée" <?php echo (($filters['status'] ?? '') === 'Acceptée') ? 'selected' : ''; ?>>Acceptée</option>
                    <option value="Rejetée" <?php echo (($filters['status'] ?? '') === 'Rejetée') ? 'selected' : ''; ?>>Rejetée</option>
                    <option value="En cours d'examen" <?php echo (($filters['status'] ?? '') === 'En cours d\'examen') ? 'selected' : ''; ?>>En cours d'examen</option>
                </select>
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
            <div class="button-container">
                <button type="submit" id="filterButton">
                    Filtrer
                </button>
                <a href="historique_proof.php" class="reset-link">
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
                            <td><?php echo htmlspecialchars(($proof['last_name'] ?? '') . ' ' . ($proof['first_name'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars($proof['group_label'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($presenter->formatDate($proof['absence_start_date'])); ?></td>
                            <td><?php echo htmlspecialchars($presenter->formatDate($proof['absence_end_date'])); ?></td>
                            <td>
                                <?php 
                                    echo htmlspecialchars($presenter->translateReason($proof['main_reason']));
                                    if (!empty($proof['custom_reason'])) {
                                        echo '<br><small style="color: #6c757d;">(' . htmlspecialchars($proof['custom_reason']) . ')</small>';
                                    }
                                ?>
                            </td>
                            <td class="status-cell">
                                <?php 
                                    $status = $presenter->translateStatus($proof['status']);
                                    $statusClass = '';
                                    switch($proof['status']) {
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
                            <td><?php echo htmlspecialchars($presenter->formatDate($proof['submission_date'])); ?></td>
                            <td>
                                <?php 
                                $files = $presenter->getProofFiles($proof);
                                if (!empty($files)): ?>
                                    <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                        <?php foreach ($files as $index => $file): ?>
                                            <a href="../../Presenter/view_upload_proof.php?proof_id=<?php echo $proof['proof_id']; ?>&file_index=<?php echo $index; ?>" 
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
                            <td>
                                <a href="<?php echo htmlspecialchars($presenter->getProofDetailsUrl($proof['proof_id'])); ?>" 
                                   class="btn-view" 
                                   style="display: inline-block; padding: 6px 12px; background-color: #4338ca; color: white; text-decoration: none; border-radius: 4px; font-size: 14px;">
                                    Voir
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

    </main>

    <footer class="footer">
        <div class="footer-content">
            <div class="team-section">
                <h3 class="team-title">Équipe de développement</h3>
                <div class="team-names">
                    <p>CIPOLAT Matteo • BOLTZ Louis • NAVREZ Louis • COLLARD Yony • BISIAUX Ambroise • FOURNIER Alexandre</p>
                </div>
            </div>
            <div class="footer-info">
                <p>&copy; 2025 UPHF - Système de gestion des absences</p>
            </div>
        </div>
    </footer>
</body>
</html>
