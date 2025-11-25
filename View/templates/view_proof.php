<?php
require_once __DIR__ . '/../../Presenter/ProofPresenter.php';

$presenter = new ProofPresenter();
$viewData = $presenter->handleRequest($_GET, $_POST);

if ($viewData['redirect']) {
    header('Location: ' . $viewData['redirect']);
    exit;
}

$proof = $viewData['proof'];
$showRejectForm = $viewData['showRejectForm'] ?? false;
$rejectionError = $viewData['rejectionError'] ?? '';
$showValidateForm = $viewData['showValidateForm'] ?? false;
$validationError = $viewData['validationError'] ?? '';
$showInfoForm = $viewData['showInfoForm'] ?? false;
$infoError = $viewData['infoError'] ?? '';
$showSplitForm = $viewData['showSplitForm'] ?? false;
$splitError = $viewData['splitError'] ?? '';
$rejectionReasons = $viewData['rejectionReasons'] ?? [];
$validationReasons = $viewData['validationReasons'] ?? [];
$islocked = $viewData['is_locked'] ?? false;
$lockStatus = $viewData['lock_status'] ?? ($islocked ? 'Verrouillé' : 'Déverrouillé');

// Dates formatées: priorité aux champs fournis par le presenter
$formattedStart = $proof['formatted_start'] ?? '';
$formattedEnd = $proof['formatted_end'] ?? '';
if (!$formattedStart && !empty($proof['absence_start_datetime'])) {
    $dt = new DateTime($proof['absence_start_datetime']);
    $formattedStart = $dt->format('d/m/Y \à H\hi');
}
if (!$formattedEnd && !empty($proof['absence_end_datetime'])) {
    $dt2 = new DateTime($proof['absence_end_datetime']);
    $formattedEnd = $dt2->format('d/m/Y \à H\hi');
}

if (!$proof) {
    echo "<p>Aucun justificatif trouvé pour cet ID.</p>";
    echo '<a href="choose_proof.php">← Retour</a>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Validation des justificatifs</title>
    <link rel="stylesheet" href="../assets/css/view_proof.css">
    <link rel="icon" type="image/x-icon" href="../img/logoIUT.ico">
    <script src="../assets/js/view_proof.js" defer></script>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>

<div class="container">
    <h1 class="title">Validation des justificatifs</h1>

    <div class="info-grid">
        <div class="info-field">
            <strong>Étudiant:</strong> <?= htmlspecialchars(($proof['last_name'] ?? '') . ' ' . ($proof['first_name'] ?? '')) ?>
        </div>
        <div class="info-field">
            <strong>Classe:</strong> <?= htmlspecialchars($proof['group_label'] ?? 'Non attribuée') ?>
        </div>
        <div class="info-field">
            <strong>Date de soumission:</strong> <?= htmlspecialchars($proof['formatted_submission'] ?? $proof['submission_date'] ?? '') ?>
        </div>
        <div class="info-field">
            <strong>Statut:</strong> <?= htmlspecialchars($proof['status_label'] ?? $proof['status'] ?? '') ?>
        </div>
        <div class="info-field">
            <strong>Verrouillage:</strong> <?= htmlspecialchars($lockStatus) ?>
            <form method="POST" action="view_proof.php" style="display:inline-block; margin-left:10px;">
                <input type="hidden" name="proof_id" value="<?= htmlspecialchars($proof['proof_id'] ?? '') ?>">
                <?php if ($islocked): ?>
                    <input type="hidden" name="lock_action" value="unlock">
                    <button type="submit" name="toggle_lock" value="1" class="btn btn-unlock">Déverrouiller</button>
                <?php else: ?>
                    <input type="hidden" name="lock_action" value="lock">
                    <button type="submit" name="toggle_lock" value="1" class="btn btn-lock">Verrouiller</button>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="dates-container">
        <div class="info-field">
            <strong>Date de début :</strong> <?= htmlspecialchars($formattedStart) ?>
        </div>
        <div class="info-field">
            <strong>Date de fin :</strong> <?= htmlspecialchars($formattedEnd) ?>
        </div>
    </div>

    <div class="reason-container">
        <div class="info-field">
            <strong>Motif :</strong> <?= htmlspecialchars($proof['main_reason_label'] ?? $proof['main_reason'] ?? $proof['reason'] ?? '') ?>
        </div>
        <div class="info-field">
            <strong>Détails:</strong> <?= htmlspecialchars($proof['custom_reason_label'] ?? $proof['custom_reason'] ?? '') ?>
        </div>
    </div>

    <!-- Lien vers le presenter de prévisualisation (ouvre dans un nouvel onglet) -->
    <a href="../../Presenter/view_upload_proof.php?proof_id=<?= urlencode($proof['proof_id']) ?>" class="download-btn" target="_blank" rel="noopener">
        <img src="download-icon.png" alt="Consulter le justificatif">
    </a>

    <div class="actions">
        <?php if ($showInfoForm): ?>
            <form method="POST" class="rejection-form">
                <input type="hidden" name="proof_id" value="<?= htmlspecialchars($proof['proof_id'] ?? '') ?>">
                <div class="form-group">
                    <label for="info_message">Message à l'étudiant :</label>
                    <textarea name="info_message" id="info_message" rows="3" required><?= htmlspecialchars($_POST['info_message'] ?? '') ?></textarea>
                </div>
                <?php if ($infoError): ?>
                    <div class="error"><?= htmlspecialchars($infoError) ?></div>
                <?php endif; ?>
                <div class="button-group">
                    <button type="submit" name="request_info" value="1" class="btn btn-info">Envoyer la demande</button>
                    <a href="view_proof.php?proof_id=<?= htmlspecialchars($proof['proof_id'] ?? '') ?>" class="btn btn-cancel">Annuler</a>
                </div>
            </form>

        <?php elseif ($showRejectForm): ?>
            <form method="POST" class="rejection-form">
                <input type="hidden" name="proof_id" value="<?= htmlspecialchars($proof['proof_id'] ?? '') ?>">
                <div class="form-group">
                    <label for="rejection_reason">Motif du rejet :</label>
                    <select name="rejection_reason" id="rejection_reason" required>
                        <option value="">-- Sélectionner un motif --</option>
                        <?php foreach (($rejectionReasons ?? []) as $reason): ?>
                            <option value="<?= htmlspecialchars($reason) ?>" <?= (isset($_POST['rejection_reason']) && $_POST['rejection_reason'] === $reason) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($reason) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="Autre" <?= (isset($_POST['rejection_reason']) && $_POST['rejection_reason'] === 'Autre') ? 'selected' : '' ?>>Autre</option>
                    </select>
                </div>
                <div class="form-group" id="new-reason-group" style="display: none;">
                    <label for="new_rejection_reason">Nouveau motif :</label>
                    <input type="text" name="new_rejection_reason" id="new_rejection_reason" value="<?= htmlspecialchars($_POST['new_rejection_reason'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="rejection_details">Détails du rejet :</label>
                    <textarea name="rejection_details" id="rejection_details" rows="3"><?= htmlspecialchars($_POST['rejection_details'] ?? '') ?></textarea>
                </div>
                <?php if ($rejectionError): ?>
                    <div class="error"><?= htmlspecialchars($rejectionError) ?></div>
                <?php endif; ?>
                <div class="button-group">
                    <button type="submit" name="reject" value="1" class="btn btn-reject">Confirmer le rejet</button>
                    <a href="view_proof.php?proof_id=<?= htmlspecialchars($proof['proof_id'] ?? '') ?>" class="btn btn-cancel">Annuler</a>
                </div>
            </form>

        <?php elseif ($showValidateForm): ?>
            <form method="POST" class="validation-form">
                <input type="hidden" name="proof_id" value="<?= htmlspecialchars($proof['proof_id'] ?? '') ?>">
                <div class="form-group">
                    <label for="validation_reason">Motif de validation :</label>
                    <select name="validation_reason" id="validation_reason" >
                        <option value="">-- Sélectionner un motif --</option>
                        <?php foreach (($validationReasons ?? []) as $reason): ?>
                            <option value="<?= htmlspecialchars($reason) ?>" <?= (isset($_POST['validation_reason']) && $_POST['validation_reason'] === $reason) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($reason) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="Autre" <?= (isset($_POST['validation_reason']) && $_POST['validation_reason'] === 'Autre') ? 'selected' : '' ?>>Autre</option>
                    </select>
                </div>
                <div class="form-group" id="new-validation-reason-group" style="display: none;">
                    <label for="new_validation_reason">Nouveau motif :</label>
                    <input type="text" name="new_validation_reason" id="new_validation_reason" value="<?= htmlspecialchars($_POST['new_validation_reason'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="validation_details">Détails :</label>
                    <textarea name="validation_details" id="validation_details" rows="3"><?= htmlspecialchars($_POST['validation_details'] ?? '') ?></textarea>
                </div>
                <?php if (!empty($validationError)): ?>
                    <div class="error"><?= htmlspecialchars($validationError) ?></div>
                <?php endif; ?>
                <div class="button-group">
                    <button type="submit" name="validate" value="1" class="btn btn-validate">Confirmer la validation</button>
                    <a href="view_proof.php?proof_id=<?= htmlspecialchars($proof['proof_id'] ?? '') ?>" class="btn btn-cancel">Annuler</a>
                </div>
            </form>

        <?php elseif ($showSplitForm): ?>
            <form method="POST" class="split-form" id="splitForm">
                <input type="hidden" name="proof_id" value="<?= htmlspecialchars($proof['proof_id'] ?? '') ?>">
                <h3 style="margin-bottom: 20px;">Scinder le justificatif en plusieurs périodes</h3>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="num_periods">Nombre de périodes à créer :</label>
                    <select name="num_periods" id="num_periods" onchange="updatePeriodFields()" style="padding: 8px; font-size: 16px;">
                        <option value="2" selected>2 périodes</option>
                        <option value="3">3 périodes</option>
                        <option value="4">4 périodes</option>
                        <option value="5">5 périodes</option>
                    </select>
                    <small style="color: #666; display: block; margin-top: 5px;">
                        💡 Exemple : Si le justificatif couvre lundi-vendredi mais seul mercredi est valide, 
                        créez 3 périodes (lundi-mardi, mercredi, jeudi-vendredi)
                    </small>
                </div>
                
                <div id="periodsContainer" style="display: grid; gap: 20px; margin-bottom: 20px;">
                    <!-- Les périodes seront générées dynamiquement par JavaScript -->
                </div>
                
                <div class="form-group">
                    <label for="split_reason">Raison de la scission :</label>
                    <textarea name="split_reason" id="split_reason" rows="2" required 
                              placeholder="Ex: Dates non continues, période intermédiaire non justifiée..."></textarea>
                </div>
                
                <?php if ($splitError): ?>
                    <div class="error"><?= htmlspecialchars($splitError) ?></div>
                <?php endif; ?>
                
                <div class="button-group">
                    <button type="submit" name="split_proof" value="1" class="btn btn-validate">Confirmer la scission</button>
                    <a href="view_proof.php?proof_id=<?= htmlspecialchars($proof['proof_id'] ?? '') ?>" class="btn btn-cancel">Annuler</a>
                </div>
            </form>

        <?php else: ?>
            <div class="decision-buttons">
                <form method="POST" action="view_proof.php" class="action-form">
                    <input type="hidden" name="proof_id" value="<?= htmlspecialchars($proof['proof_id'] ?? '') ?>">
                    <div class="button-container">
                        <button type="submit" name="validate" value="1" class="btn btn-validate">
                            <span class="btn-text">Valider</span>
                        </button>
                        <button type="submit" name="reject" value="1" class="btn btn-reject" style="margin-left:10px;">
                            <span class="btn-text">Refuser</span>
                        </button>
                        <button type="submit" name="request_info" value="1" class="btn btn-info" style="margin-left:10px;">
                            <span class="btn-text">Demander des informations</span>
                        </button>
                        <button type="submit" name="split" value="1" class="btn btn-warning" style="margin-left:10px; background-color: #FF9800;">
                            <span class="btn-text">Scinder</span>
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Initialiser au chargement de la page si le formulaire de scission est affiché
    if (document.getElementById('num_periods')) {
        const startDate = '<?= htmlspecialchars($proof['absence_start_date'] ?? '') ?>';
        const endDate = '<?= htmlspecialchars($proof['absence_end_date'] ?? '') ?>';
        updatePeriodFields(startDate, endDate);
    }
</script>

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
