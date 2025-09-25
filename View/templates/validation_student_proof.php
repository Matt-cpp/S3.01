<?php
session_start();

date_default_timezone_set('Europe/Paris');
require_once __DIR__ . '/../../Model/database.php';

// Retrieve student information from database
$student_info = null;
if (isset($_SESSION['id_student'])) {
    try {
        $db = Database::getInstance();
        $student_info = $db->selectOne(
            "SELECT id, identifier, last_name, first_name, middle_name, birth_date, degrees, department, email, role 
             FROM users 
             WHERE id = ?",
            [$_SESSION['id_student']]
        );
    } catch (Exception $e) {
        error_log("Error retrieving student information: " . $e->getMessage());
    }
}

// Handling of the uploaded proof file
$uploaded_file_name = '';
$file_path = '';
if (isset($_FILES['proof_reason'])) {
    $upload_dir = '../../uploads/';

    $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx'];
    $max_file_size = 5 * 1024 * 1024; // 5MB
    

    $file_extension = strtolower(pathinfo($_FILES['proof_reason']['name'], PATHINFO_EXTENSION));
    $file_size = $_FILES['proof_reason']['size'];
    error_log($file_size);
    if (!in_array($file_extension, $allowed_extensions)) {
        $uploaded_file_name = 'Erreur : Type de fichier non autorisé';
        $saved_file_name = '';
    } elseif ($file_size > $max_file_size || $file_size === 0) {
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
    'submission_date' => date('Y-m-d H:i:s'),
);

if ($_SESSION['reason_data']['absence_reason'] === 'maladie') {
    $_SESSION['reason_data']['absence_reason'] = 'Maladie';
} else if ($_SESSION['reason_data']['absence_reason'] === 'deces') {
    $_SESSION['reason_data']['absence_reason'] = 'Décès dans la famille';
} else if ($_SESSION['reason_data']['absence_reason'] === 'obligations_familiales') {
    $_SESSION['reason_data']['absence_reason'] = 'Obligations familiales';
} else if ($_SESSION['reason_data']['absence_reason'] === 'rdv_medical') {
    $_SESSION['reason_data']['absence_reason'] = 'Rendez-vous médical';
} else if ($_SESSION['reason_data']['absence_reason'] === 'convocation_officielle') {
    $_SESSION['reason_data']['absence_reason'] = 'Convocation officielle (permis, TOIC, etc.)';
} else if ($_SESSION['reason_data']['absence_reason'] === 'transport') {
    $_SESSION['reason_data']['absence_reason'] = 'Problème de transport';
}
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
        <?php 
        if (!$student_info) {
            echo '<div class="warning-message">';
            echo '<strong>Attention :</strong> Informations de l\'étudiant non disponibles.';
            echo '</div>';
        }
        ?>

        <div class="pdf-download">
            <a href="../../Presenter/generate_pdf.php" class="btn-pdf" target="_blank">
                Télécharger le récapitulatif PDF
            </a>
        </div>

        <h3>Récapitulatif de votre demande :</h3>
        <ul>
            <?php
            if ($student_info) {
                echo '<li><strong>Informations de l\'étudiant :</strong> ';
                echo '<li><strong>Nom :</strong> ' . htmlspecialchars($student_info['last_name']) . '</li>';
                echo '<li><strong>Prénom :</strong> ' . htmlspecialchars($student_info['first_name']) . '</li>';
                
                if (!empty($student_info['middle_name'])) {
                    echo '<li><strong>Deuxième prénom :</strong> ' . htmlspecialchars($student_info['middle_name']) . '</li>';
                }
                
                if (!empty($student_info['department'])) {
                    echo '<li><strong>Département :</strong> ' . htmlspecialchars($student_info['department']) . '</li>';
                }
                if (!empty($student_info['degrees'])) {
                    echo '<li><strong>Diplôme(s) :</strong> ' . htmlspecialchars($student_info['degrees']) . '</li>';
                }
                if (!empty($student_info['birth_date'])) {
                    $birth_date = new DateTime($student_info['birth_date']);
                    echo '<li><strong>Date de naissance :</strong> ' . $birth_date->format('d/m/Y') . '</li>';
                }
                if (!empty($student_info['email'])) {
                    echo '<li><strong>Email :</strong> ' . htmlspecialchars($student_info['email']) . '</li>';
                }
            }
            ?>
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