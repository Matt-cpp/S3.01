<?php
session_start();

date_default_timezone_set('Europe/Paris');

// Handling of the uploaded proof file
$uploaded_file_name = '';
$file_path = '';
if (isset($_FILES['proof_reason']) && $_FILES['proof_reason']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../../uploads/';
    
    $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx'];
    $max_file_size = 5 * 1024 * 1024; // 5MB
    
    $file_extension = strtolower(pathinfo($_FILES['proof_reason']['name'], PATHINFO_EXTENSION));
    $file_size = $_FILES['proof_reason']['size'];
    
    if (!in_array($file_extension, $allowed_extensions)) {
        $uploaded_file_name = 'Erreur : Type de fichier non autorisé';
        $saved_file_name = '';
    } elseif ($file_size > $max_file_size) {
        $uploaded_file_name = 'Erreur : Fichier trop volumineux (max 5MB)';
        $saved_file_name = '';
    } else {
        // Creation of a unique file name to avoid conflicts
        $paris_time = new DateTime('now', new DateTimeZone('Europe/Paris'));
        $unique_name = uniqid() . '_' . $paris_time->format('Y-m-d_H-i-s') . '.' . $file_extension;
        $file_path = $upload_dir . $unique_name;

        // Move the uploaded file to the destination folder
        if (move_uploaded_file($_FILES['proof_reason']['tmp_name'], $file_path)) {
            $uploaded_file_name = $_FILES['proof_reason']['name'];
            $saved_file_name = $unique_name;
        } else {
            $uploaded_file_name = 'Erreur lors de la sauvegarde du fichier';
            $saved_file_name = '';
        }
    }
}

$_SESSION['reason_data'] = array(
    'datetime_start' => $_POST['datetime_start'] ?? '',
    'datetime_end' => $_POST['datetime_end'] ?? '',
    'class_involved' => $_POST['class_involved'] ?? array(),
    'absence_reason' => $_POST['absence_reason'] ?? '',
    'other_reason' => $_POST['other_reason'] ?? '',
    'proof_file' => $uploaded_file_name,
    'saved_file_name' => $saved_file_name,
    'comments' => $_POST['comments'] ?? '',
    'submission_date' => date('Y-m-d H:i:s') // Date de soumission au fuseau horaire de Paris
);
?>

<!-- Faudra rajouter les infos de l'étudiants -->
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
        <h1>Votre justificatif a été envoyé</h1>
        
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
                $datetime_start = new DateTime($_SESSION['reason_data']['datetime_start']);
                $datetime_start->setTimezone(new DateTimeZone('Europe/Paris'));
                echo $datetime_start->format('d/m/Y à H:i:s');
                ?>
            </li>
            <li><strong>Date et heure de fin :</strong> 
                <?php 
                $datetime_end = new DateTime($_SESSION['reason_data']['datetime_end']);
                $datetime_end->setTimezone(new DateTimeZone('Europe/Paris'));
                echo $datetime_end->format('d/m/Y à H:i:s');
                ?>
            </li>
            <li><strong>Cours concerné(s) :</strong> 
                <?php 
                $cours = $_SESSION['reason_data']['class_involved'];
                if (is_array($cours)) {
                    echo htmlspecialchars(implode(', ', $cours));
                } else {
                    echo htmlspecialchars($cours);
                }
                ?>
            </li>
            <li><strong>Motif de l'absence :</strong> <?php echo htmlspecialchars($_SESSION['reason_data']['absence_reason']); ?></li>
            <?php if (!empty($_SESSION['reason_data']['other_reason'])): ?>
                <li><strong>Précision du motif :</strong> <?php echo htmlspecialchars($_SESSION['reason_data']['other_reason']); ?></li>
            <?php endif; ?>
            <li><strong>Fichier justificatif :</strong> <?php echo htmlspecialchars($_SESSION['reason_data']['proof_file']); ?></li>
            <?php if (!empty($_SESSION['reason_data']['comments'])): ?>
                <li><strong>Commentaires :</strong> <?php echo nl2br(htmlspecialchars($_SESSION['reason_data']['comments'])); ?></li>
            <?php endif; ?>
            <li><strong>Date de soumission :</strong> 
                <?php 
                $submission_date = new DateTime($_SESSION['reason_data']['submission_date']);
                $submission_date->setTimezone(new DateTimeZone('Europe/Paris'));
                echo $submission_date->format('d/m/Y à H:i:s');
                ?>
            </li>
        </ul>
        
        <div style="margin-top: 30px; text-align: center; color: #6c757d;">
            <p><em>Conservez ce récapitulatif pour vos archives.</em></p>
        </div>
    </div>
</body>
</html>