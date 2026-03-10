<?php

declare(strict_types=1);

/**
 * File: proof_update.php
 *
 * Proof update handler – Processes modification of an existing proof.
 * Main features:
 * - Authorization check (proof must be under review and belong to the student)
 * - Proof file management:
 *   - Deletion of files selected by the student
 *   - Addition of new files with validation (size, format)
 *   - Storage in JSONB in the proof_files column
 * - Proof update (reason, concerned courses, comment)
 * - Status change to 'pending' after modification
 * - Updated summary PDF generation
 * - Confirmation email sending
 */

require_once __DIR__ . '/../../Model/database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    session_start();

    try {
        $db = Database::getInstance();

        // Check that the proof ID is provided
        if (!isset($_POST['proof_id'])) {
            throw new Exception('Aucun justificatif spécifié.');
        }

        $proofId = (int) $_POST['proof_id'];

        // Retrieve the existing proof
        $existingProof = $db->selectOne(
            "SELECT id, student_identifier, file_path, proof_files, status FROM proof WHERE id = :proof_id",
            ['proof_id' => $proofId]
        );

        if (!$existingProof) {
            throw new Exception('Justificatif non trouvé.');
        }

        // Check that the proof is under review
        if ($existingProof['status'] !== 'under_review') {
            throw new Exception('Seuls les justificatifs en révision peuvent être modifiés.');
        }

        // Check that the proof belongs to the logged-in student
        if (!isset($_SESSION['id_student'])) {
            throw new Exception('Veuillez vous connecter pour effectuer cette action.');
        }
        $studentId = $_SESSION['id_student'];
        $studentInfo = $db->selectOne("SELECT identifier FROM users WHERE id = :student_id", ['student_id' => $studentId]);

        if (!$studentInfo || $existingProof['student_identifier'] !== $studentInfo['identifier']) {
            throw new Exception('Vous n\'êtes pas autorisé à modifier ce justificatif.');
        }

        // Proof file management (upload, deletion, retention)
        $uploadDir = __DIR__ . '/../../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Retrieve existing files from the JSONB column
        $currentFiles = [];
        if (!empty($existingProof['proof_files'])) {
            if (is_array($existingProof['proof_files'])) {
                $currentFiles = $existingProof['proof_files'];
            } else {
                $decoded = json_decode($existingProof['proof_files'], true);
                $currentFiles = is_array($decoded) ? $decoded : [];
            }
        }

        // Process files to delete (checked by the student)
        if (isset($_POST['delete_files']) && is_array($_POST['delete_files'])) {
            foreach ($_POST['delete_files'] as $deleteIndex) {
                $deleteIndex = (int) $deleteIndex;
                if (isset($currentFiles[$deleteIndex])) {
                    // Delete the physical file
                    $fileToDelete = $currentFiles[$deleteIndex];
                    if (!empty($fileToDelete['path'])) {
                        $fullPath = __DIR__ . '/../' . $fileToDelete['path'];
                        if (file_exists($fullPath)) {
                            unlink($fullPath);
                        }
                    }
                    // Remove from array
                    unset($currentFiles[$deleteIndex]);
                }
            }
            // Reindex array
            $currentFiles = array_values($currentFiles);
        }

        // Add new files
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB
        $maxTotalSize = 20 * 1024 * 1024; // 20MB

        if (isset($_FILES['proof_files']) && is_array($_FILES['proof_files']['name'])) {
            $filesCount = count($_FILES['proof_files']['name']);

            $totalSize = array_sum(array_map(function ($f) {
                return !empty($f['size']) ? $f['size'] : 0;
            }, $currentFiles));

            for ($i = 0; $i < $filesCount; $i++) {
                // Skip empty file slots (error code 4 = UPLOAD_ERR_NO_FILE)
                if ($_FILES['proof_files']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                if ($_FILES['proof_files']['error'][$i] === UPLOAD_ERR_OK) {
                    $fileExtension = strtolower(pathinfo($_FILES['proof_files']['name'][$i], PATHINFO_EXTENSION));
                    $fileSize = $_FILES['proof_files']['size'][$i];
                    $originalName = $_FILES['proof_files']['name'][$i];

                    if (!in_array($fileExtension, $allowedExtensions)) {
                        throw new Exception("Type de fichier non autorisé pour {$originalName}. Types acceptés: " . implode(', ', $allowedExtensions));
                    }

                    if ($fileSize > $maxFileSize || $fileSize === 0) {
                        throw new Exception("Fichier {$originalName} trop volumineux (max 5MB) ou vide.");
                    }

                    $totalSize += $fileSize;
                    if ($totalSize > $maxTotalSize) {
                        throw new Exception('Taille totale des fichiers trop importante (max 20MB au total).');
                    }

                    // Create unique filename
                    date_default_timezone_set('Europe/Paris');
                    $uniqueName = uniqid() . '_' . date('Y-m-d_H-i-s') . '.' . $fileExtension;
                    $filePath = $uploadDir . $uniqueName;

                    if (!move_uploaded_file($_FILES['proof_files']['tmp_name'][$i], $filePath)) {
                        throw new Exception("Erreur lors de la sauvegarde du fichier {$originalName}.");
                    }

                    // Determine MIME type
                    $mimeType = 'application/octet-stream';
                    if (function_exists('mime_content_type')) {
                        $mimeType = mime_content_type($filePath);
                    } elseif (function_exists('finfo_open')) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mimeType = finfo_file($finfo, $filePath);
                        finfo_close($finfo);
                    } else {
                        // Fallback: determine by extension
                        $mimeTypes = [
                            'pdf' => 'application/pdf',
                            'jpg' => 'image/jpeg',
                            'jpeg' => 'image/jpeg',
                            'png' => 'image/png',
                            'gif' => 'image/gif',
                            'doc' => 'application/msword',
                            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                        ];
                        $mimeType = $mimeTypes[$fileExtension] ?? 'application/octet-stream';
                    }

                    $currentFiles[] = [
                        'original_name' => $originalName,
                        'saved_name' => $uniqueName,
                        'path' => 'uploads/' . $uniqueName,
                        'size' => $fileSize,
                        'type' => $mimeType,
                        'uploaded_at' => date('Y-m-d H:i:s')
                    ];
                }
            }
        }

        // Encode files as JSON for the database
        $filesJson = json_encode($currentFiles, JSON_UNESCAPED_UNICODE);

        // Retrieve and process form data
        $datetimeStart = $_POST['datetime_start'] ?? '';
        $datetimeEnd = $_POST['datetime_end'] ?? '';
        $absenceReason = $_POST['absence_reason'] ?? '';
        $otherReason = $_POST['other_reason'] ?? '';
        $comments = $_POST['comments'] ?? '';
        $classInvolved = $_POST['class_involved'] ?? '';

        // Absence reason mapping
        $absenceReasons = [
            'maladie' => 'illness',
            'deces' => 'death',
            'obligations_familiales' => 'family_obligations',
            'rdv_medical' => 'medical_appointment',
            'convocation_officielle' => 'official_summons',
            'transport' => 'transportation',
            'autre' => 'other'
        ];

        $mainReason = $absenceReasons[$absenceReason] ?? 'other';
        $customReason = ($mainReason === 'other' || $absenceReason === 'rdv_medical') ? $otherReason : null;

        // Update the proof in the database
        $sqlUpdate = "
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

        $paramsUpdate = [
            'main_reason' => $mainReason,
            'custom_reason' => $customReason,
            'proof_files' => $filesJson,
            'student_comment' => $comments,
            'concerned_courses' => $classInvolved,
            'proof_id' => $proofId
        ];

        $db->execute($sqlUpdate, $paramsUpdate);

        // Update associated absences to set them back to unjustified
        $sqlUpdateAbsences = "
            UPDATE absences a
            SET 
                justified = FALSE,
                status = 'absent',
                updated_at = NOW()
            FROM proof_absences pa
            WHERE pa.proof_id = :proof_id
              AND a.id = pa.absence_id
        ";

        $db->execute($sqlUpdateAbsences, ['proof_id' => $proofId]);

        // Clean session
        unset($_SESSION['edit_proof']);

        // Redirect with success message
        $_SESSION['success_message'] = 'Votre justificatif a été modifié avec succès et repassé en attente de validation.';
        header('Location: ../../View/templates/student/proofs.php');
        exit();
    } catch (Exception $e) {
        error_log('Error in student_proof_update.php: ' . $e->getMessage());
        $_SESSION['error_message'] = $e->getMessage();

        // If we have edit data, return to the edit page
        if (isset($_SESSION['edit_proof'])) {
            header('Location: ../../View/templates/student/proof_edit.php');
        } else {
            header('Location: ../../View/templates/student/proofs.php');
        }
        exit();
    }
} else {
    // If the request is not POST, redirect
    header('Location: ../../View/templates/student/proofs.php');
    exit();
}
