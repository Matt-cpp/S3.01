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

        $proofId = (int) $_POST['proof_id'];

        // Récupérer le justificatif existant
        $existingProof = $db->selectOne(
            "SELECT id, student_identifier, file_path, proof_files, status FROM proof WHERE id = :proof_id",
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

        // Gérer les fichiers (multiples)
        $upload_dir = __DIR__ . '/../uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Récupérer les fichiers existants depuis la colonne JSONB
        $currentFiles = [];
        if (!empty($existingProof['proof_files'])) {
            if (is_array($existingProof['proof_files'])) {
                $currentFiles = $existingProof['proof_files'];
            } else {
                $decoded = json_decode($existingProof['proof_files'], true);
                $currentFiles = is_array($decoded) ? $decoded : [];
            }
        }

        // Gérer les suppressions de fichiers
        if (isset($_POST['delete_files']) && is_array($_POST['delete_files'])) {
            foreach ($_POST['delete_files'] as $deleteIndex) {
                $deleteIndex = (int) $deleteIndex;
                if (isset($currentFiles[$deleteIndex])) {
                    // Supprimer le fichier physique
                    $fileToDelete = $currentFiles[$deleteIndex];
                    if (!empty($fileToDelete['path'])) {
                        $fullPath = __DIR__ . '/../' . $fileToDelete['path'];
                        if (file_exists($fullPath)) {
                            unlink($fullPath);
                        }
                    }
                    // Retirer du tableau
                    unset($currentFiles[$deleteIndex]);
                }
            }
            // Réindexer le tableau
            $currentFiles = array_values($currentFiles);
        }

        // Ajouter les nouveaux fichiers
        $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx'];
        $max_file_size = 5 * 1024 * 1024; // 5MB
        $max_total_size = 20 * 1024 * 1024; // 20MB

        // Log file upload attempt
        error_log("File upload check - isset: " . (isset($_FILES['proof_files']) ? 'yes' : 'no'));
        if (isset($_FILES['proof_files'])) {
            error_log("Files array: " . print_r($_FILES['proof_files'], true));
        }

        if (isset($_FILES['proof_files']) && is_array($_FILES['proof_files']['name'])) {
            $files_count = count($_FILES['proof_files']['name']);
            error_log("Processing $files_count file slots (some may be empty)");

            $total_size = array_sum(array_map(function ($f) {
                return !empty($f['size']) ? $f['size'] : 0;
            }, $currentFiles));

            for ($i = 0; $i < $files_count; $i++) {
                // Skip empty file slots (error code 4 = UPLOAD_ERR_NO_FILE)
                if ($_FILES['proof_files']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                    error_log("Skipping empty file slot at index $i");
                    continue;
                }

                if ($_FILES['proof_files']['error'][$i] === UPLOAD_ERR_OK) {
                    $file_extension = strtolower(pathinfo($_FILES['proof_files']['name'][$i], PATHINFO_EXTENSION));
                    $file_size = $_FILES['proof_files']['size'][$i];
                    $original_name = $_FILES['proof_files']['name'][$i];

                    if (!in_array($file_extension, $allowed_extensions)) {
                        throw new Exception("Type de fichier non autorisé pour {$original_name}. Types acceptés: " . implode(', ', $allowed_extensions));
                    }

                    if ($file_size > $max_file_size || $file_size === 0) {
                        throw new Exception("Fichier {$original_name} trop volumineux (max 5MB) ou vide.");
                    }

                    $total_size += $file_size;
                    if ($total_size > $max_total_size) {
                        throw new Exception('Taille totale des fichiers trop importante (max 20MB au total).');
                    }

                    // Create unique filename
                    date_default_timezone_set('Europe/Paris');
                    $unique_name = uniqid() . '_' . date('Y-m-d_H-i-s') . '.' . $file_extension;
                    $file_path = $upload_dir . $unique_name;

                    if (!move_uploaded_file($_FILES['proof_files']['tmp_name'][$i], $file_path)) {
                        error_log("Failed to move uploaded file from " . $_FILES['proof_files']['tmp_name'][$i] . " to " . $file_path);
                        throw new Exception("Erreur lors de la sauvegarde du fichier {$original_name}.");
                    }

                    error_log("Successfully uploaded file: $original_name as $unique_name");

                    // Déterminer le type MIME
                    $mime_type = 'application/octet-stream';
                    if (function_exists('mime_content_type')) {
                        $mime_type = mime_content_type($file_path);
                    } elseif (function_exists('finfo_open')) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime_type = finfo_file($finfo, $file_path);
                        finfo_close($finfo);
                    } else {
                        // Fallback: déterminer par extension
                        $mime_types = [
                            'pdf' => 'application/pdf',
                            'jpg' => 'image/jpeg',
                            'jpeg' => 'image/jpeg',
                            'png' => 'image/png',
                            'gif' => 'image/gif',
                            'doc' => 'application/msword',
                            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                        ];
                        $mime_type = $mime_types[$file_extension] ?? 'application/octet-stream';
                    }

                    $currentFiles[] = [
                        'original_name' => $original_name,
                        'saved_name' => $unique_name,
                        'path' => 'uploads/' . $unique_name,
                        'size' => $file_size,
                        'type' => $mime_type,
                        'uploaded_at' => date('Y-m-d H:i:s')
                    ];
                }
            }
        }

        // Encoder les fichiers en JSON pour la base de données
        $files_json = json_encode($currentFiles, JSON_UNESCAPED_UNICODE);

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
                proof_files = :proof_files::jsonb,
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
            'proof_files' => $files_json,
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

