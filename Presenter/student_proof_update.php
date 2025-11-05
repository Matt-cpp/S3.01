<?php

require_once __DIR__ . '/../Model/database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    session_start();

    try {
        $db = Database::getInstance();

        // Vérifier que l'ID du justificatif est fourni
        if (!isset($_POST['proof_id'])) {
            throw new Exception('Aucun justificatif spécifié.');
        }

        $proofId = (int)$_POST['proof_id'];

        // Récupérer le justificatif existant
        $existingProof = $db->selectOne(
            "SELECT id, student_identifier, file_path, status FROM proof WHERE id = :proof_id",
            ['proof_id' => $proofId]
        );

        if (!$existingProof) {
            throw new Exception('Justificatif non trouvé.');
        }

        // Vérifier que le justificatif est bien en révision
        if ($existingProof['status'] !== 'under_review') {
            throw new Exception('Seuls les justificatifs en révision peuvent être modifiés.');
        }

        // Vérifier que le justificatif appartient bien à l'étudiant connecté
        $studentId = $_SESSION['id_student'] ?? 1;
        $studentInfo = $db->selectOne("SELECT identifier FROM users WHERE id = :student_id", ['student_id' => $studentId]);

        if (!$studentInfo || $existingProof['student_identifier'] !== $studentInfo['identifier']) {
            throw new Exception('Vous n\'êtes pas autorisé à modifier ce justificatif.');
        }

        // Gérer le fichier si un nouveau fichier est téléchargé
        $saved_file_path = $existingProof['file_path']; // Conserver le fichier actuel par défaut

        if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/';

            // Create upload directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx'];
            $max_file_size = 5 * 1024 * 1024; // 5MB

            $file_extension = strtolower(pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION));
            $file_size = $_FILES['proof_file']['size'];

            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception('Type de fichier non autorisé. Types acceptés: ' . implode(', ', $allowed_extensions));
            }

            if ($file_size > $max_file_size || $file_size === 0) {
                throw new Exception('Fichier trop volumineux (max 5MB) ou fichier vide.');
            }

            // Create unique filename
            date_default_timezone_set('Europe/Paris');
            $unique_name = uniqid() . '_' . date('Y-m-d_H-i-s') . '.' . $file_extension;
            $file_path = $upload_dir . $unique_name;

            if (!move_uploaded_file($_FILES['proof_file']['tmp_name'], $file_path)) {
                throw new Exception('Erreur lors de la sauvegarde du fichier.');
            }

            // Supprimer l'ancien fichier si un nouveau est téléchargé
            if (!empty($existingProof['file_path'])) {
                $old_file = __DIR__ . '/../' . $existingProof['file_path'];
                if (file_exists($old_file)) {
                    unlink($old_file);
                }
            }

            $saved_file_path = 'uploads/' . $unique_name; // Relative path for storage
        }

        // Récupérer et traiter les données du formulaire
        $datetime_start = $_POST['datetime_start'] ?? '';
        $datetime_end = $_POST['datetime_end'] ?? '';
        $absence_reason = $_POST['absence_reason'] ?? '';
        $other_reason = $_POST['other_reason'] ?? '';
        $comments = $_POST['comments'] ?? '';
        $class_involved = $_POST['class_involved'] ?? '';

        // Mapping des raisons d'absence
        $absence_reasons = [
            'maladie' => 'illness',
            'deces' => 'death',
            'obligations_familiales' => 'family_obligations',
            'rdv_medical' => 'medical_appointment',
            'convocation_officielle' => 'official_summons',
            'transport' => 'transportation',
            'autre' => 'other'
        ];

        $main_reason = $absence_reasons[$absence_reason] ?? 'other';
        $custom_reason = ($main_reason === 'other' || $absence_reason === 'rdv_medical') ? $other_reason : null;

        // Mettre à jour le justificatif dans la base de données
        $sql_update = "
            UPDATE proof 
            SET 
                main_reason = :main_reason,
                custom_reason = :custom_reason,
                file_path = :file_path,
                student_comment = :student_comment,
                concerned_courses = :concerned_courses,
                status = 'pending',
                processing_date = NULL,
                manager_comment = NULL,
                updated_at = NOW()
            WHERE id = :proof_id
        ";

        $params_update = [
            'main_reason' => $main_reason,
            'custom_reason' => $custom_reason,
            'file_path' => $saved_file_path,
            'student_comment' => $comments,
            'concerned_courses' => $class_involved,
            'proof_id' => $proofId
        ];

        $db->execute($sql_update, $params_update);

        // Mettre à jour les absences associées pour qu'elles repassent en non justifiées
        $sql_update_absences = "
            UPDATE absences a
            SET 
                justified = FALSE,
                status = 'absent',
                updated_at = NOW()
            FROM proof_absences pa
            WHERE pa.proof_id = :proof_id
              AND a.id = pa.absence_id
        ";

        $db->execute($sql_update_absences, ['proof_id' => $proofId]);

        // Nettoyer la session
        unset($_SESSION['edit_proof']);

        // Rediriger avec un message de succès
        $_SESSION['success_message'] = 'Votre justificatif a été modifié avec succès et repassé en attente de validation.';
        header('Location: ../View/templates/student_proofs.php');
        exit();

    } catch (Exception $e) {
        error_log("Erreur dans student_proof_update.php : " . $e->getMessage());
        $_SESSION['error_message'] = $e->getMessage();

        // Si on a des données d'édition, retourner à la page d'édition
        if (isset($_SESSION['edit_proof'])) {
            header('Location: ../View/templates/student_proof_edit.php');
        } else {
            header('Location: ../View/templates/student_proofs.php');
        }
        exit();
    }
} else {
    // Si la requête n'est pas POST, rediriger
    header('Location: ../View/templates/student_proofs.php');
    exit();
}

