<?php
session_start();
$_SESSION['reason_data'] = array(
    'datetime_start' => $_POST['datetime_start'] ?? '',
    'datetime_end' => $_POST['datetime_end'] ?? '',
    'class_involved' => $_POST['class_involved'] ?? array(),
    'absence_reason' => $_POST['absence_reason'] ?? '',
    'other_reason' => $_POST['other_reason'] ?? '',
    'proof_file' => $_FILES['proof_file']['name'] ?? '',
    'comments' => $_POST['comments'] ?? '',
    'submission_date' => date('Y-m-d H:i:s')
);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="/logoIUT.ico">
    <title>Validé</title>
    <link rel="stylesheet" href="../assets/css/validation-student-proof.css">
</head>

<body>
    <div class="container">
        <h1>Votre justificatif a été validé</h1>

        <div class="success-message">
            <strong>Succès !</strong> Votre demande de justificatif d'absence a été enregistrée avec succès.
            Un email vous a été envoyé récapitulant les informations de votre justificatif.
        </div>

        <div class="pdf-download">
            <a href="../../Presenter/generate_pdf.php" class="btn-pdf" target="_blank">
                Télécharger le récapitulatif PDF
            </a>
        </div>

        <h3>Récapitulatif de votre demande :</h3>
        <ul>
            <li><strong>Date et heure de début :</strong>
                <?php
                $datetime_start = $_SESSION['reason_data']['datetime_start'];
                echo date('d/m/Y à H:i', strtotime($datetime_start));
                ?>
            </li>
            <li><strong>Date et heure de fin :</strong>
                <?php
                $datetime_end = $_SESSION['reason_data']['datetime_end'];
                echo date('d/m/Y à H:i', strtotime($datetime_end));
                ?>
            </li>
            <li><strong>Cours concerné(s) :</strong>
                <?php
                $cours = $_SESSION['reason_data']['class_involved'];
                echo htmlspecialchars($cours);
                ?>
            </li>
            <li><strong>Motif de l'absence :</strong>
                <?php echo htmlspecialchars($_SESSION['reason_data']['absence_reason']); ?></li>
            <?php if (!empty($_SESSION['reason_data']['other_reason'])): ?>
                <li><strong>Précision du motif :</strong>
                    <?php echo htmlspecialchars($_SESSION['reason_data']['other_reason']); ?></li>
            <?php endif; ?>
            <li><strong>Fichier justificatif :</strong>
                <?php echo htmlspecialchars($_SESSION['reason_data']['proof_file']); ?></li>
            <?php if (!empty($_SESSION['reason_data']['comments'])): ?>
                <li><strong>Commentaires :</strong>
                    <?php echo nl2br(htmlspecialchars($_SESSION['reason_data']['comments'])); ?></li>
            <?php endif; ?>
            <li><strong>Date de soumission :</strong>
                <?php echo htmlspecialchars($_SESSION['reason_data']['submission_date']); ?></li>
        </ul>

        <div style="margin-top: 30px; text-align: center; color: #6c757d;">
            <p><em>Conservez ce récapitulatif pour vos archives.</em></p>
        </div>
    </div>
</body>

</html>