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
require_once __DIR__ . '/../../Presenter/student_proofs_presenter.php';
require_once __DIR__ . '/../../Presenter/student_get_info.php';

$student_identifier = getStudentIdentifier($_SESSION['id_student']);

$presenter = new StudentProofsPresenter($student_identifier);

// Ne pas utiliser le cache si on a des paramètres GET (filtres depuis la page d'accueil)
$useCache = empty($_GET['status']);

// Utiliser les données en session si disponibles et récentes (défini dans session_cache.php), par défaut 1 minutes
// sinon les récupérer de la BD
if (!$useCache || !isset($_SESSION['Proofs']) || !isset($_SESSION['Reasons']) || !isset($_SESSION['ProofsFilters']) || !isset($_SESSION['ProofsErrorMessage']) || shouldRefreshCache(15)) {
    $proofs = $presenter->getProofs();
    $reasons = $presenter->getReasons();
    if ($useCache) {
        $_SESSION['Proofs'] = $proofs;
        $_SESSION['Reasons'] = $reasons;
        updateCacheTimestamp();
    }
} else {
    $proofs = $_SESSION['Proofs'];
    $reasons = $_SESSION['Reasons'];
}

$filters = $presenter->getFilters();
$errorMessage = $presenter->getErrorMessage();
?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../img/logoIUT.ico">
    <title>Mes Justificatifs</title>

    <link rel="stylesheet" href="../assets/css/student_proofs.css">
</head>

<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    
    <main>
        <h1 class="page-title">Mes Justificatifs</h1>

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
        ?>

        <form method="POST" class="filter-form">
            <div class="filter-grid">
                <div class="filter-input">
                    <label for="firstDateFilter">Date de début d'absence</label>
                    <input type="date" name="firstDateFilter" id="firstDateFilter" 
                        value="<?php echo htmlspecialchars($filters['start_date'] ?? ''); ?>">
                </div>
                
                <div class="filter-input">
                    <label for="lastDateFilter">Date de fin d'absence</label>
                    <input type="date" name="lastDateFilter" id="lastDateFilter" 
                        value="<?php echo htmlspecialchars($filters['end_date'] ?? ''); ?>">
                </div>
                
                <div class="filter-input">
                    <label for="statusFilter">Statut</label>
                    <select name="statusFilter" id="statusFilter">
                        <option value="">Tous les statuts</option>
                        <option value="accepted" <?php echo (($filters['status'] ?? '') === 'accepted') ? 'selected' : ''; ?>>Accepté</option>
                        <option value="pending" <?php echo (($filters['status'] ?? '') === 'pending') ? 'selected' : ''; ?>>En attente</option>
                        <option value="under_review" <?php echo (($filters['status'] ?? '') === 'under_review') ? 'selected' : ''; ?>>En révision</option>
                        <option value="rejected" <?php echo (($filters['status'] ?? '') === 'rejected') ? 'selected' : ''; ?>>Refusé</option>
                    </select>
                </div>
                
                <div class="filter-input">
                    <label for="reasonFilter">Motif</label>
                    <select name="reasonFilter" id="reasonFilter">
                        <option value="">Tous les motifs</option>
                        <?php foreach ($reasons as $reason): ?>
                            <option value="<?php echo htmlspecialchars($reason['reason']); ?>" 
                                    <?php echo (($filters['reason'] ?? '') === $reason['reason']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($reason['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-input">
                    <label for="examFilter">Évaluation manquée</label>
                    <select name="examFilter" id="examFilter">
                        <option value="">Tous</option>
                        <option value="yes" <?php echo (($filters['has_exam'] ?? '') === 'yes') ? 'selected' : ''; ?>>Oui</option>
                        <option value="no" <?php echo (($filters['has_exam'] ?? '') === 'no') ? 'selected' : ''; ?>>Non</option>
                    </select>
                </div>
            </div>
            
            <div class="button-container">
                <button type="submit">Filtrer</button>
                <a href="student_proofs.php" class="reset-link">Réinitialiser</a>
            </div>
        </form>

        <div class="results-counter">
            <strong>Nombre de justificatifs trouvés: <?php echo count($proofs); ?></strong>
        </div>

        <div class="table-container">
            <table id="proofsTable">
                <thead>
                    <tr>
                        <th>Période</th>
                        <th>Motif</th>
                        <th>Heures ratées</th>
                        <th>Date soumission</th>
                        <th>Date traitement</th>
                        <th>Évaluation</th>
                        <th>Statut</th>
                        <th>Commentaire</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($proofs)): ?>
                        <tr>
                            <td colspan="8" class="no-results">
                                Aucun justificatif trouvé avec les critères sélectionnés.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($proofs as $proof): ?>
                            <?php 
                            $status = $presenter->getStatusBadge($proof['status']);
                            $proofFiles = [];
                            if (!empty($proof['proof_files'])) {
                                $proofFiles = is_array($proof['proof_files']) ? $proof['proof_files'] : json_decode($proof['proof_files'], true);
                                $proofFiles = is_array($proofFiles) ? $proofFiles : [];
                            }
                            ?>
                            <tr class="proof-row" data-proof-id="<?php echo $proof['proof_id']; ?>" 
                                data-status="<?php echo $proof['status']; ?>"
                                data-period="<?php echo htmlspecialchars($presenter->formatPeriod($proof['absence_start_date'], $proof['absence_end_date'])); ?>"
                                data-reason="<?php echo htmlspecialchars($presenter->translateReason($proof['main_reason'], $proof['custom_reason'])); ?>"
                                data-custom-reason="<?php echo htmlspecialchars($proof['custom_reason'] ?? ''); ?>"
                                data-hours="<?php echo number_format($proof['total_hours_missed'], 1); ?>"
                                data-absences="<?php echo $proof['absence_count']; ?>"
                                data-half-days="<?php echo $proof['half_days_count'] ?? 0; ?>"
                                data-submission="<?php echo htmlspecialchars($presenter->formatDateTime($proof['submission_date'])); ?>"
                                data-processing="<?php echo $proof['processing_date'] ? htmlspecialchars($presenter->formatDateTime($proof['processing_date'])) : '-'; ?>"
                                data-status-text="<?php echo $status['text']; ?>"
                                data-status-icon="<?php echo $status['icon']; ?>"
                                data-status-class="<?php echo $status['class']; ?>"
                                data-exam="<?php echo $proof['has_exam'] ? 'Oui' : 'Non'; ?>"
                                data-comment="<?php echo htmlspecialchars($proof['manager_comment'] ?? ''); ?>"
                                data-files="<?php echo htmlspecialchars(json_encode($proofFiles)); ?>"
                                style="cursor: pointer;">
                                <td>
                                    <strong><?php echo $presenter->formatPeriod($proof['absence_start_date'], $proof['absence_end_date']); ?></strong>
                                </td>
                                <td>
                                    <div>
                                        <?php echo $presenter->translateReason($proof['main_reason'], $proof['custom_reason']); ?>
                                    </div>
                                    <?php if ($proof['custom_reason'] && $proof['main_reason'] !== 'other'): ?>
                                        <small class="course-code"><?php echo htmlspecialchars($proof['custom_reason']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo number_format($proof['total_hours_missed'], 1); ?>h</strong>
                                </td>
                                <td>
                                    <?php echo $presenter->formatDateTime($proof['submission_date']); ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($proof['processing_date']) {
                                        echo $presenter->formatDateTime($proof['processing_date']);
                                    } else {
                                        echo '<span class="course-code">-</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($proof['has_exam']): ?>
                                        <span class="eval-badge">⚠️ Oui</span>
                                    <?php else: ?>
                                        <span class="no-eval">Non</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $status['class']; ?>">
                                        <?php echo $status['icon'] . ' ' . $status['text']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($proof['manager_comment']): ?>
                                        <span class="comment-preview" title="<?php echo htmlspecialchars($proof['manager_comment']); ?>">
                                            <?php 
                                            echo htmlspecialchars(substr($proof['manager_comment'], 0, 50));
                                            echo strlen($proof['manager_comment']) > 50 ? '...' : '';
                                            ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="course-code">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Modal pour afficher les détails du justificatif -->
    <div id="proofModal" class="modal">
        <div class="modal-overlay"></div>
        <div id="modalContent" class="modal-content">
            <button class="modal-close" id="closeModal">&times;</button>
            <h2 class="modal-title">Détails du Justificatif</h2>
            <div class="modal-body">
                <div class="modal-info-group">
                    <div class="modal-info-item">
                        <span class="modal-label">📅 Période d'absence :</span>
                        <span class="modal-value" id="modalPeriod"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label">📝 Motif :</span>
                        <span class="modal-value" id="modalReason"></span>
                    </div>
                    <div class="modal-info-item" id="customReasonItem" style="display: none;">
                        <span class="modal-label">ℹ️ Précision :</span>
                        <span class="modal-value" id="modalCustomReason"></span>
                    </div>
                </div>

                <div class="modal-info-group">
                    <div class="modal-info-item">
                        <span class="modal-label">⏱️ Heures ratées :</span>
                        <span class="modal-value" id="modalHours"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label">📊 Absences concernées :</span>
                        <span class="modal-value" id="modalAbsences"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label">📅 Demi-journées concernées :</span>
                        <span class="modal-value" id="modalHalfDays"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label">📝 Évaluation manquée :</span>
                        <span class="modal-value" id="modalExam"></span>
                    </div>
                </div>

                <div class="modal-info-group">
                    <div class="modal-info-item">
                        <span class="modal-label">📤 Date de soumission :</span>
                        <span class="modal-value" id="modalSubmission"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-label">✅ Date de traitement :</span>
                        <span class="modal-value" id="modalProcessing"></span>
                    </div>
                </div>

                <div class="modal-status-section">
                    <span class="modal-label">🏷️ Statut :</span>
                    <span id="modalStatus" class="badge"></span>
                </div>

                <div class="modal-files-section" id="filesSection" style="display: none; margin-top: 20px;">
                    <span class="modal-label">📎 Fichiers justificatifs :</span>
                    <div id="modalFiles" style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px;"></div>
                </div>

                <div class="modal-comment-section" id="commentSection" style="display: none;">
                    <span class="modal-label">💬 Commentaire du responsable :</span>
                    <div class="modal-comment-box" id="modalComment"></div>
                </div>

                <!-- Bouton Modifier (visible uniquement pour les justificatifs en révision) -->
                <div class="modal-action-section" id="actionSection" style="display: none; margin-top: 20px; text-align: center;">
                    <a href="#" id="modalEditBtn" class="btn-add-info" style="display: inline-block; padding: 12px 24px; text-decoration: none; background-color: #ffc107; color: #000; font-weight: bold;">
                        ✏️ Modifier le justificatif
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/proof_modal.js"></script>

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