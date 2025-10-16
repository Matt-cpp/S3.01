<?php
require_once __DIR__ . '/../../Presenter/ProofPresenter.php';
$translations = [
    // Statuts
        'pending' => 'En attente',
        'approved' => 'Validé',
        'rejected' => 'Refusé',
    // Motifs d’absence
        'illness' => 'Maladie',
        'death' => 'Décès',
        'family_obligations' => 'Obligations familiales',
        'other' => 'Autre',
];

function translate($value, $translations) {
    return $translations[$value] ?? $value;
}

// Fonction pour formater la date au format français
function formatDateFr($datetimeStr) {
    if (!$datetimeStr) return '';
    $date = new DateTime($datetimeStr);
    setlocale(LC_TIME, 'fr_FR.UTF-8');
    return strftime('%d/%m/%Y à %Hh%M', $date->getTimestamp());
}
?>
<?php
$presenter = new ProofPresenter();
$viewData = $presenter->handleRequest($_GET, $_POST);

if ($viewData['redirect']) {
    header('Location: ' . $viewData['redirect']);
    exit;
}
$proof = $viewData['proof'];
$showRejectForm = $viewData['showRejectForm'];
$rejectionError = $viewData['rejectionError'];
$showInfoForm = $viewData['showInfoForm'] ?? false;
$infoError = $viewData['infoError'] ?? '';

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
            <strong>Date de soumission:</strong> <?= htmlspecialchars($proof['submission_date'] ?? '') ?>
        </div>
        <div class="info-field">
            <strong>Statut:</strong> <?= htmlspecialchars($proof['status'] ?? '') ?>
        </div>
    </div>

    <div class="dates-container">
        <div class="info-field">
            <strong>Date de début :</strong> <?= htmlspecialchars(formatDateFr($proof['absence_start_datetime'] ?? '')) ?>
        </div>
        <div class="info-field">
            <strong>Date de fin :</strong> <?= htmlspecialchars(formatDateFr($proof['absence_end_datetime'] ?? '')) ?>
        </div>
    </div>

    <div class="reason-container">
        <div class="info-field">
            <strong>Motif :</strong> <?= htmlspecialchars(translate($proof['reason'] ?? '', $translations)) ?>
        </div>
        <div class="info-field">
            <strong>Détails:</strong> <?= htmlspecialchars($proof['custom_reason'] ?? '') ?>
        </div>
    </div>

    <a href="../Presenter/download.php?proof_id=<?= urlencode($proof['proof_id']) ?>" class="download-btn">
        <img src="download-icon.png" alt="Télécharger le justificatif">
    </a>


    <div class="actions">
        <?php if ($showInfoForm): ?>
            <form method="POST" class="rejection-form">
                <input type="hidden" name="proof_id" value="<?= htmlspecialchars($proof['proof_id'] ?? '') ?>">
                <div class="form-group">
                    <label for="info_message">Message à l'étudiant :</label>
                    <textarea name="info_message" id="info_message" rows="3" required></textarea>
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
                            <option value="<?= htmlspecialchars($reason) ?>" <?= (isset($_POST['rejection_reason']) && $_POST['rejection_reason'] === $reason) ? 'selected' : '' ?>><?= htmlspecialchars($reason) ?></option>
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
        <?php else: ?>
            <div class="decision-buttons">
                <form method="POST" action="view_proof.php" class="action-form">
                    <input type="hidden" name="proof_id" value="<?= htmlspecialchars($proof['proof_id'] ?? '') ?>">
                    <div class="button-container">
                        <button type="submit" name="validate" value="1" class="btn btn-validate">
                            <span class="btn-text">Valider</span>
                        </button>
                        <button type="submit" name="reject" value="1" class="btn btn-reject">
                            <span class="btn-text">Refuser</span>
                        </button>
                        <button type="submit" name="request_info" value="1" class="btn btn-info">
                            <span class="btn-text">Demander des informations</span>
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
