<?php
/**
 * Fichier: view_proof.php
 * 
 * Template de visualisation et traitement d'un justificatif pour le responsable pédagogique.
 * Fonctionnalités principales :
 * - Affichage détaillé du justificatif avec toutes ses informations :
 *   - Informations étudiant (nom, prénom, identifiant, email)
 *   - Période d'absence avec dates précises
 *   - Motif d'absence et commentaire étudiant
 *   - Fichiers justificatifs avec visualisation
 *   - Liste des absences couvertes par le justificatif
 * - Actions disponibles selon le statut :
 *   - Validation (avec motifs multiples)
 *   - Rejet (avec motifs multiples)
 *   - Demande d'informations complémentaires
 *   - Verrouillage/Déverrouillage
 * - Historique des décisions précédentes avec détails
 * - Codes couleur selon le statut (vert=accepté, rouge=rejeté, jaune=en attente, bleu=en révision)
 * - Formulaires modaux pour chaque action
 * Utilise ProofPresenter pour gérer la logique métier et les actions.
 */

// Set UTF-8 encoding for proper display of French characters
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../../../Presenter/student/ProofPresenter.php';

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
$formattedStart = '';
$formattedEnd = '';
$timezone = new DateTimeZone('Europe/Paris');

// Use absence_start_datetime and absence_end_datetime if available (from ProofModel)
if (!empty($proof['absence_start_datetime'])) {
    $dt = new DateTime($proof['absence_start_datetime'], $timezone);
    $formattedStart = $dt->format('d/m/Y \à H\hi');
} elseif (!empty($proof['absence_start_date'])) {
    $dt = new DateTime($proof['absence_start_date'], $timezone);
    $formattedStart = $dt->format('d/m/Y');
}

if (!empty($proof['absence_end_datetime'])) {
    $dt2 = new DateTime($proof['absence_end_datetime'], $timezone);
    $formattedEnd = $dt2->format('d/m/Y \à H\hi');
} elseif (!empty($proof['absence_end_date'])) {
    $dt2 = new DateTime($proof['absence_end_date'], $timezone);
    $formattedEnd = $dt2->format('d/m/Y');
}

// Override with formatted fields from presenter if they exist
if (!empty($proof['formatted_start'])) {
    $formattedStart = $proof['formatted_start'];
}
if (!empty($proof['formatted_end'])) {
    $formattedEnd = $proof['formatted_end'];
}

if (!$proof) {
    echo "<p>Aucun justificatif trouvé pour cet ID.</p>";
    echo '<a href="choose_proof.php">← Retour</a>';
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<meta charset="UTF-8">

<head>
    <title>Validation des justificatifs</title>
    <link rel="stylesheet" href="../../assets/css/academic_manager/view_proof.css">
    <?php include __DIR__ . '/../../includes/theme-helper.php';
    renderThemeSupport(); ?>
    <script src="../../assets/js/academic_manager/view_proof.js" defer></script>
</head>

<body>
    <?php include __DIR__ . '/../navbar.php'; ?>
    <div class="container">
        <h1 class="title">Validation des justificatifs</h1>

        <div class="info-grid">
            <div class="info-field">
                <strong>Étudiant:</strong>
                <?= htmlspecialchars(($proof['last_name'] ?? '') . ' ' . ($proof['first_name'] ?? '')) ?>
            </div>
            <div class="info-field">
                <strong>Classe:</strong> <?= htmlspecialchars($proof['group_label'] ?? 'Non attribuée') ?>
            </div>
            <div class="info-field">
                <strong>Date de soumission:</strong>
                <?= htmlspecialchars($proof['formatted_submission'] ?? $proof['submission_date'] ?? '') ?>
            </div>
            <div class="info-field">
                <strong>Statut:</strong> <?= htmlspecialchars($proof['status_label'] ?? $proof['status'] ?? '') ?>
            </div>
            <div class="info-field">
                <strong>Verrouillage:</strong> <?= htmlspecialchars($lockStatus) ?>
                <form method="POST" action="view_proof.php" class="lock-form">
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
                <strong>Motif :</strong>
                <?= htmlspecialchars($proof['main_reason_label'] ?? $proof['main_reason'] ?? $proof['reason'] ?? '') ?>
            </div>
            <?php if (!empty($proof['custom_reason_label']) || !empty($proof['custom_reason'])): ?>
                <div class="info-field">
                    <strong>Détails:</strong>
                    <?= htmlspecialchars($proof['custom_reason_label'] ?? $proof['custom_reason'] ?? '') ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Commentaire de l'étudiant -->
        <?php if (!empty($proof['student_comment'])): ?>
            <div class="reason-container student-comment">
                <div class="info-field">
                    <strong>Commentaire de l'étudiant :</strong> <?= htmlspecialchars($proof['student_comment']) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Section fichiers justificatifs -->
        <div class="files-section">
            <strong class="files-title">📎 Fichiers justificatifs :</strong>
            <?php
            $proofFiles = [];
            if (!empty($proof['proof_files'])) {
                $proofFiles = is_array($proof['proof_files']) ? $proof['proof_files'] : json_decode($proof['proof_files'], true);
                $proofFiles = is_array($proofFiles) ? $proofFiles : [];
            }

            if (!empty($proofFiles)): ?>
                <div class="files-list">
                    <?php foreach ($proofFiles as $index => $file): ?>
                        <a href="../../Presenter/view_upload_proof.php?proof_id=<?= urlencode($proof['proof_id']) ?>&file_index=<?= $index ?>"
                            target="_blank" rel="noopener" class="file-link"
                            title="<?= htmlspecialchars($file['original_name'] ?? 'Fichier ' . ($index + 1)) ?>">
                            📄 <?= htmlspecialchars($file['original_name'] ?? 'Fichier ' . ($index + 1)) ?>
                            <?php if (!empty($file['size'])): ?>
                                <small class="file-size">(<?= number_format($file['size'] / 1024, 1) ?> Ko)</small>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-files">Aucun fichier justificatif n'a été fourni.</p>
            <?php endif; ?>
        </div>

        <div class="actions">
            <?php if ($showInfoForm): ?>
                <form method="POST" class="rejection-form">
                    <input type="hidden" name="proof_id" value="<?= htmlspecialchars($proof['proof_id'] ?? '') ?>">
                    <div class="form-group">
                        <label for="info_message">Message à l'étudiant :</label>
                        <textarea name="info_message" id="info_message" rows="3"
                            required><?= htmlspecialchars($_POST['info_message'] ?? '') ?></textarea>
                    </div>
                    <?php if ($infoError): ?>
                        <div class="error"><?= htmlspecialchars($infoError) ?></div>
                    <?php endif; ?>
                    <div class="button-group">
                        <button type="submit" name="request_info" value="1" class="btn btn-info">Envoyer la demande</button>
                        <a href="view_proof.php?proof_id=<?= htmlspecialchars($proof['proof_id'] ?? '') ?>"
                            class="btn btn-cancel">Annuler</a>
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
                    <div class="form-group hidden" id="new-reason-group">
                        <label for="new_rejection_reason">Nouveau motif :</label>
                        <input type="text" name="new_rejection_reason" id="new_rejection_reason"
                            value="<?= htmlspecialchars($_POST['new_rejection_reason'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="rejection_details">Détails du rejet :</label>
                        <textarea name="rejection_details" id="rejection_details"
                            rows="3"><?= htmlspecialchars($_POST['rejection_details'] ?? '') ?></textarea>
                    </div>
                    <?php if ($rejectionError): ?>
                        <div class="error"><?= htmlspecialchars($rejectionError) ?></div>
                    <?php endif; ?>
                    <div class="button-group">
                        <button type="submit" name="reject" value="1" class="btn btn-reject">Confirmer le rejet</button>
                        <a href="view_proof.php?proof_id=<?= htmlspecialchars($proof['proof_id'] ?? '') ?>"
                            class="btn btn-cancel">Annuler</a>
                    </div>
                </form>

            <?php elseif ($showValidateForm): ?>
                <form method="POST" class="validation-form">
                    <input type="hidden" name="proof_id" value="<?= htmlspecialchars($proof['proof_id'] ?? '') ?>">
                    <div class="form-group">
                        <label for="validation_reason">Motif de validation :</label>
                        <select name="validation_reason" id="validation_reason">
                            <option value="">-- Sélectionner un motif --</option>
                            <?php foreach (($validationReasons ?? []) as $reason): ?>
                                <option value="<?= htmlspecialchars($reason) ?>" <?= (isset($_POST['validation_reason']) && $_POST['validation_reason'] === $reason) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($reason) ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="Autre" <?= (isset($_POST['validation_reason']) && $_POST['validation_reason'] === 'Autre') ? 'selected' : '' ?>>Autre</option>
                        </select>
                    </div>
                    <div class="form-group hidden" id="new-validation-reason-group">
                        <label for="new_validation_reason">Nouveau motif :</label>
                        <input type="text" name="new_validation_reason" id="new_validation_reason"
                            value="<?= htmlspecialchars($_POST['new_validation_reason'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="validation_details">Détails :</label>
                        <textarea name="validation_details" id="validation_details"
                            rows="3"><?= htmlspecialchars($_POST['validation_details'] ?? '') ?></textarea>
                    </div>
                    <?php if (!empty($validationError)): ?>
                        <div class="error"><?= htmlspecialchars($validationError) ?></div>
                    <?php endif; ?>
                    <div class="button-group">
                        <button type="submit" name="validate" value="1" class="btn btn-validate">Confirmer la
                            validation</button>
                        <a href="view_proof.php?proof_id=<?= htmlspecialchars($proof['proof_id'] ?? '') ?>"
                            class="btn btn-cancel">Annuler</a>
                    </div>
                </form>

            <?php elseif ($showSplitForm): ?>
                <form method="POST" class="split-form" id="splitForm">
                    <input type="hidden" name="proof_id" value="<?= htmlspecialchars($proof['proof_id'] ?? '') ?>">
                    <h3 class="split-title">Scinder le justificatif en plusieurs périodes</h3>

                    <div class="form-group">
                        <label for="num_periods">Nombre de périodes à créer :</label>
                        <select name="num_periods" id="num_periods">
                            <option value="2" selected>2 périodes</option>
                            <option value="3">3 périodes</option>
                            <option value="4">4 périodes</option>
                            <option value="5">5 périodes</option>
                        </select>
                        <small class="help-text">
                            Exemple : Si le justificatif couvre lundi-vendredi mais seul mercredi est valide,
                            créez 3 périodes (lundi-mardi, mercredi, jeudi-vendredi)
                        </small>
                    </div>

                    <div id="periodsContainer" class="periods-container">
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
                        <button type="submit" name="split_proof" value="1" class="btn btn-validate">Confirmer la
                            scission</button>
                        <a href="view_proof.php?proof_id=<?= htmlspecialchars($proof['proof_id'] ?? '') ?>"
                            class="btn btn-cancel">Annuler</a>
                    </div>
                </form>

            <?php else: ?>
                <div class="decision-buttons">
                    <form method="POST" action="view_proof.php" class="action-form">
                        <input type="hidden" name="proof_id" value="<?= htmlspecialchars($proof['proof_id'] ?? '') ?>">
                        <div class="button-container">
                            <?php
                            // Récupérer le statut actuel pour masquer le bouton correspondant
                            $currentStatus = $proof['status'] ?? '';
                            // Désactiver les boutons si le justificatif est verrouillé
                            $disabledAttr = $islocked ? 'disabled' : '';
                            $disabledStyle = $islocked ? 'opacity: 0.5; cursor: not-allowed;' : '';
                            $disabledTitle = $islocked ? 'title="Le justificatif est verrouillé"' : '';
                            ?>

                            <?php if ($currentStatus !== 'accepted'): ?>
                                <button type="submit" name="validate" value="1" class="btn btn-validate <?= $islocked ? 'btn-disabled' : '' ?>"
                                    <?= $disabledAttr ?> <?= $disabledTitle ?>>
                                    <span class="btn-text">Valider</span>
                                </button>
                            <?php endif; ?>

                            <?php if ($currentStatus !== 'rejected'): ?>
                                <button type="submit" name="reject" value="1" class="btn btn-reject <?= $islocked ? 'btn-disabled' : '' ?>"
                                    <?= $disabledAttr ?> <?= $disabledTitle ?>>
                                    <span class="btn-text">Refuser</span>
                                </button>
                            <?php endif; ?>

                            <?php if ($currentStatus !== 'under_review'): ?>
                                <button type="submit" name="request_info" value="1" class="btn btn-info <?= $islocked ? 'btn-disabled' : '' ?>"
                                    <?= $disabledAttr ?> <?= $disabledTitle ?>>
                                    <span class="btn-text">Demander des informations</span>
                                </button>
                            <?php endif; ?>

                            <button type="submit" name="split" value="1" class="btn btn-warning <?= $islocked ? 'btn-disabled' : '' ?>"
                                <?= $disabledAttr ?> <?= $disabledTitle ?>>
                                <span class="btn-text">Scinder</span>
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <script>
            // Variables globales pour les dates du justificatif
            const proofStartDate = '<?= htmlspecialchars($proof['absence_start_date'] ?? '') ?>';
            const proofEndDate = '<?= htmlspecialchars($proof['absence_end_date'] ?? '') ?>';
            
            // Fonction wrapper pour updatePeriodFields avec les dates du justificatif
            function updatePeriodFieldsWrapper() {
                if (typeof updatePeriodFields === 'function') {
                    updatePeriodFields(proofStartDate, proofEndDate);
                }
            }
            
            // Initialiser au chargement de la page si le formulaire de scission est affiché
            document.addEventListener('DOMContentLoaded', function() {
                if (document.getElementById('num_periods')) {
                    // Attendre que le script externe soit chargé
                    setTimeout(function() {
                        updatePeriodFieldsWrapper();
                    }, 50);
                    
                    // Ajouter l'événement onchange
                    document.getElementById('num_periods').addEventListener('change', updatePeriodFieldsWrapper);
                }
            });

            // Gestion des select "Autre"
            (function() {
                const rejSel = document.getElementById('rejection_reason');
                const rejGrp = document.getElementById('new-reason-group');
                if (rejSel && rejGrp) {
                    const toggle = () => rejGrp.style.display = (rejSel.value === 'Autre') ? 'block' : 'none';
                    rejSel.addEventListener('change', toggle);
                    toggle();
                }
                const valSel = document.getElementById('validation_reason');
                const valGrp = document.getElementById('new-validation-reason-group');
                if (valSel && valGrp) {
                    const toggleV = () => valGrp.style.display = (valSel.value === 'Autre') ? 'block' : 'none';
                    valSel.addEventListener('change', toggleV);
                    toggleV();
                }
            })();
        </script>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>

</html>