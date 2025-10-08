<?php
require_once __DIR__ . '/../Model/ProofModel.php';
require_once __DIR__ . '/../Presenter/ProofPresenter.php';

$showRejectForm = false;
$rejectionError = '';
$proof = null;
$showInfoForm = false;
$infoError = '';

// Récupération du justificatif
if (isset($_GET['proof_id'])) {
    $proofId = (int)$_GET['proof_id'];
    $presenter = new ProofPresenter();
    $proof = $presenter->getProofDetails($proofId);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proof_id'])) {
    $proofId = (int)$_POST['proof_id'];
    $model = new ProofModel();
    $proof = $model->getProofDetails($proofId);

    // Premier clic sur "Refuser" : afficher le formulaire de motif
    if (isset($_POST['reject']) && !isset($_POST['rejection_reason'])) {
        $showRejectForm = true;
    }
    // Soumission du formulaire de rejet
    elseif (isset($_POST['reject']) && isset($_POST['rejection_reason'])) {
        $rejectionReason = trim($_POST['rejection_reason']);
        $rejectionDetails = trim($_POST['rejection_details'] ?? '');

        if ($rejectionReason === '') {
            $showRejectForm = true;
            $rejectionError = "Veuillez sélectionner un motif de rejet.";
        } else {
            $model->updateProofStatus($proofId, 'rejected');
            $model->setRejectionReason($proofId, $rejectionReason, $rejectionDetails);
            var_dump($proof['student_identifier'], $proof['absence_start_date'], $proof['absence_end_date']);
            $model->updateAbsencesForProof(
                    $proof['student_identifier'],
                    $proof['absence_start_date'],
                    $proof['absence_end_date'],
                    'rejected'
            );

            header('Location: view_proof.php?proof_id=' . $proofId);
            exit;
        }
    }
    // Validation classique
    elseif (isset($_POST['validate'])) {
        $model->updateProofStatus($proofId, 'accepted');
        var_dump($proof['student_identifier'], $proof['absence_start_date'], $proof['absence_end_date']);
        $model->updateAbsencesForProof(
                $proof['student_identifier'],
                $proof['absence_start_date'],
                $proof['absence_end_date'],
                'accepted'
        );
        header('Location: view_proof.php?proof_id=' . $proofId);
        exit;
    }elseif (isset($_POST['request_info']) && !isset($_POST['info_message'])) {
        $showInfoForm = true;
    }
// Soumission du formulaire d'information
    elseif (isset($_POST['request_info']) && isset($_POST['info_message'])) {
        $infoMessage = trim($_POST['info_message']);
        if ($infoMessage === '') {
            $showInfoForm = true;
            $infoError = "Veuillez saisir un message.";
        } else {
            // À adapter selon votre modèle
            $model->setInfoRequest($proofId, $infoMessage);
            header('Location: view_proof.php?proof_id=' . $proofId);
            exit;
        }
    }
}

// Affichage des infos du justificatif
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
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .logo {
            height: 50px;
        }

        .header-icons {
            display: flex;
            gap: 20px;
        }

        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 0 auto;
        }

        .title {
            text-align: center;
            margin-bottom: 30px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-field {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .dates-container {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .reason-container {
            margin-bottom: 20px;
        }

        .download-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            width: fit-content;
        }

        .actions {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 30px;
        }

        .validate-btn {
            background: linear-gradient(to right, #2ecc71, #27ae60);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            cursor: pointer;
        }

        .reject-btn {
            background: linear-gradient(to right, #e74c3c, #c0392b);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            cursor: pointer;
        }

        .info-btn {
            background: #f1c40f;
            color: black;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            cursor: pointer;
        }
        /* Ajouter ces styles CSS */
        .actions {
            margin-top: 40px;
        }

        .button-container {
            display: flex;
            justify-content: center;
            gap: 20px;
        }

        .btn {
            font-family: 'Segoe UI', sans-serif;
            padding: 12px 30px;
            border-radius: 30px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: transform 0.2s, box-shadow 0.2s;
            min-width: 160px;
            text-align: center;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .btn-validate {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
        }

        .btn-reject {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }

        .btn-info {
            background: linear-gradient(135deg, #f1c40f, #f39c12);
            color: black;
        }

        .btn-cancel {
            background: #95a5a6;
            color: white;
            text-decoration: none;
        }

        .rejection-form {
            max-width: 500px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .error {
            color: #e74c3c;
            margin-bottom: 15px;
            text-align: center;
        }

    </style>
    <link rel="icon" type="image/x-icon" href="../img/logoIUT.ico">
</head>
<body>
    <div class="header">
        <div class="header-icons">
            <img src="notification.png" alt="Notifications">
            <img src="settings.png" alt="Paramètres">
            <img src="profile.png" alt="Profil">
        </div>
    </div>
    <div class="logos-container">
        <img src="img/UPHF.png" alt="Logo UPHF" class="logo" width="170" height="90">
        <img src="img/logoIUT.png" alt="Logo IUT" class="logo" width="80" height="150">
    </div>

    <div class="container">
        <h1 class="title">Validation des justificatifs</h1>

        <div class="info-grid">
            <div class="info-field">
                <strong>Étudiant:</strong> <?= htmlspecialchars($proof['last_name'] . ' ' . $proof['first_name']) ?>
            </div>
            <div class="info-field">
                <strong>Classe:</strong> <?= htmlspecialchars($proof['group_label'] ?? 'Non attribuée') ?>
            </div>
            <div class="info-field">
                <strong>Date de soumission:</strong> <?= htmlspecialchars($proof['submission_date']) ?>
            </div>
            <div class="info-field">
                <strong>Statut:</strong> <?= htmlspecialchars($proof['status']) ?>
            </div>
        </div>

        <div class="dates-container">
            <div class="info-field">
                <strong>Date de début:</strong> <?= htmlspecialchars($proof['absence_start_date']) ?>
            </div>
            <div class="info-field">
                <strong>Date de fin:</strong> <?= htmlspecialchars($proof['absence_end_date']) ?>
            </div>
        </div>

        <div class="reason-container">
            <div class="info-field">
                <strong>Motif:</strong> <?= htmlspecialchars($proof['main_reason']) ?>
            </div>
            <div class="info-field">
                <strong>Détails:</strong> <?= htmlspecialchars($proof['custom_reason']) ?>
            </div>
        </div>

        <a href="#" class="download-btn">
            <img src="download-icon.png" alt="Télécharger">
            Télécharger le justificatif
        </a>

        <div class="actions">
            <?php if ($showInfoForm): ?>
                <form method="POST" class="rejection-form">
                    <input type="hidden" name="proof_id" value="<?= htmlspecialchars($proof['proof_id']) ?>">
                    <div class="form-group">
                        <label for="info_message">Message à l'étudiant :</label>
                        <textarea name="info_message" id="info_message" rows="3" required></textarea>
                    </div>
                    <?php if ($infoError): ?>
                        <div class="error"><?= htmlspecialchars($infoError) ?></div>
                    <?php endif; ?>
                    <div class="button-group">
                        <button type="submit" name="request_info" value="1" class="btn btn-info">Envoyer la demande</button>
                        <a href="view_proof.php?proof_id=<?= htmlspecialchars($proof['proof_id']) ?>" class="btn btn-cancel">Annuler</a>
                    </div>
                </form>
            <?php elseif ($showRejectForm): ?>
                <form method="POST" class="rejection-form">
                    <input type="hidden" name="proof_id" value="<?= htmlspecialchars($proof['proof_id']) ?>">
                    <div class="form-group">
                        <label for="rejection_reason">Motif du rejet :</label>
                        <select name="rejection_reason" id="rejection_reason" required>
                            <option value="">-- Sélectionner un motif --</option>
                            <option value="Justificatif illisible">Justificatif illisible</option>
                            <option value="Justificatif non valable">Justificatif non valable</option>
                            <option value="Dates non cohérentes">Dates non cohérentes</option>
                            <option value="Absence non concernée">Absence non concernée</option>
                            <option value="Autre">Autre</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="rejection_details">Détails du rejet :</label>
                        <textarea name="rejection_details" id="rejection_details" rows="3"></textarea>
                    </div>
                    <?php if ($rejectionError): ?>
                        <div class="error"><?= htmlspecialchars($rejectionError) ?></div>
                    <?php endif; ?>
                    <div class="button-group">
                        <button type="submit" name="reject" value="1" class="btn btn-reject">Confirmer le rejet</button>
                        <a href="view_proof.php?proof_id=<?= htmlspecialchars($proof['proof_id']) ?>" class="btn btn-cancel">Annuler</a>
                    </div>
                </form>
            <?php else: ?>
                <div class="decision-buttons">
                    <form method="POST" action="view_proof.php" class="action-form">
                        <input type="hidden" name="proof_id" value="<?= htmlspecialchars($proof['proof_id']) ?>">
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

