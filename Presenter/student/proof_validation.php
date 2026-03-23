<!-- Student proof validation backend with file upload, email sending and submission condition verification -->

<?php

/**
 * File: proof_validation.php
 *
 * Proof submission handler – Processes complete proof of absence submission.
 * Full multi-step process:
 * 1. Upload and validate proof files (multi-file support)
 *    - Format validation (PDF, JPG, PNG, DOC, DOCX, GIF)
 *    - Size validation (5MB per file, 20MB total)
 *    - Storage in JSONB in proof_files
 * 2. Check for absences in the specified period
 * 3. Record proof in the proof table
 * 4. Link proof to concerned absences (proof_absences table)
 * 5. Update absence monitoring system
 * 6. Generate summary PDF
 * 7. Send confirmation email with attachments
 * 8. Create notification in the database
 * Stores data in session for the confirmation page.
 */

require_once __DIR__ . '/../../Model/UserModel.php';
require_once __DIR__ . '/../../Model/AbsenceModel.php';
require_once __DIR__ . '/../../Model/ProofModel.php';
require_once __DIR__ . '/../../Model/NotificationModel.php';
require_once __DIR__ . '/../../Model/email.php';
require_once __DIR__ . '/../../Model/AbsenceMonitoringModel.php';

function enqueueProofEmailJob(array $jobPayload): string
{
    $jobsDir = __DIR__ . '/../../uploads/jobs';
    if (!is_dir($jobsDir)) {
        mkdir($jobsDir, 0755, true);
    }

    $jobFile = $jobsDir . '/proof_email_job_' . uniqid('', true) . '.json';
    $encoded = json_encode($jobPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        throw new Exception('Impossible de sérialiser la tâche email en arrière-plan.');
    }

    if (file_put_contents($jobFile, $encoded) === false) {
        throw new Exception('Impossible de créer le fichier de tâche email.');
    }

    return $jobFile;
}

function triggerProofEmailWorker(string $jobFile): void
{
    $workerScript = __DIR__ . '/process_proof_email_job.php';
    $phpBinary = PHP_BINARY ?: 'php';

    if (stripos(PHP_OS, 'WIN') === 0) {
        $command = 'start /B "" ' . escapeshellarg($phpBinary) . ' ' . escapeshellarg($workerScript) . ' ' . escapeshellarg($jobFile);
        @pclose(@popen($command, 'r'));
        return;
    }

    $command = escapeshellarg($phpBinary) . ' ' . escapeshellarg($workerScript) . ' ' . escapeshellarg($jobFile) . ' > /dev/null 2>&1 &';
    @exec($command);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    session_start();

    try {
        $absenceModel = new AbsenceModel();
        $proofModel = new ProofModel();
        $notificationModel = new NotificationModel();

        // Multi-file proof upload with full validation
        $uploadedFiles = [];
        $maxFileSize = 5 * 1024 * 1024; // 5MB
        $maxTotalSize = 20 * 1024 * 1024; // 20MB
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx'];
        $uploadDir = __DIR__ . '/../../uploads/';

        // Create upload folder if needed
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Check if files were uploaded
        if (isset($_FILES['proof_files']) && !empty($_FILES['proof_files']['name'][0])) {
            $filesCount = count($_FILES['proof_files']['name']);
            $totalSize = 0;

            // Process all files
            for ($i = 0; $i < $filesCount; $i++) {
                // Check for upload errors
                if ($_FILES['proof_files']['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }

                $originalName = $_FILES['proof_files']['name'][$i];
                $tmpName = $_FILES['proof_files']['tmp_name'][$i];
                $fileSize = $_FILES['proof_files']['size'][$i];
                $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

                // Extension validation
                if (!in_array($fileExtension, $allowedExtensions)) {
                    throw new Exception("Format de fichier non autorisé : $originalName");
                }

                // Size validation
                if ($fileSize > $maxFileSize || $fileSize === 0) {
                    throw new Exception("Taille de fichier invalide : $originalName");
                }

                $totalSize += $fileSize;

                // Check total size
                if ($totalSize > $maxTotalSize) {
                    throw new Exception("La taille totale des fichiers dépasse 20MB");
                }

                // Create unique filename
                $uniqueName = uniqid() . '_' . date('Y-m-d_H-i-s') . '.' . $fileExtension;
                $filePath = $uploadDir . $uniqueName;

                // Move uploaded file
                if (!move_uploaded_file($tmpName, $filePath)) {
                    throw new Exception("Erreur lors de l'upload de : $originalName");
                }

                // Determine MIME type based on extension
                $mimeTypes = [
                    'pdf' => 'application/pdf',
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'doc' => 'application/msword',
                    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                ];
                $mimeType = $mimeTypes[$fileExtension] ?? 'application/octet-stream';

                $uploadedFiles[] = [
                    'original_name' => $originalName,
                    'saved_name' => $uniqueName,
                    'path' => 'uploads/' . $uniqueName,
                    'file_size' => $fileSize,
                    'mime_type' => $mimeType,
                    'uploaded_at' => date('Y-m-d H:i:s')
                ];
            }
        }

        // Store file information as JSON for the session
        $filesJson = json_encode($uploadedFiles, JSON_UNESCAPED_UNICODE);

        // Retrieve student information from database
        if (isset($_SESSION['id_student'])) {
            try {
                $userModel = new UserModel();
                $_SESSION['student_info'] = $userModel->getUserById((int) $_SESSION['id_student']);
            } catch (Exception $e) {
                error_log("Error retrieving student information: " . $e->getMessage());
            }
        }

        // Store form data in session
        $_SESSION['reason_data'] = array(
            'datetime_start' => $_POST['datetime_start'] ?? '',
            'datetime_end' => $_POST['datetime_end'] ?? '',
            'class_involved' => $_POST['class_involved'] ?? '',
            'absence_reason' => $_POST['absence_reason'] ?? '',
            'other_reason' => $_POST['other_reason'] ?? '',

            // Multi-file management
            'proof_files' => $uploadedFiles,
            'proof_files_json' => $filesJson,

            'comments' => $_POST['comments'] ?? '',
            'submission_date' => date('Y-m-d H:i:s'),
            'stats_hours' => $_POST['absence_stats_hours'] ?? '0',
            'stats_halfdays' => $_POST['absence_stats_halfdays'] ?? '0',
            'stats_evaluations' => $_POST['absence_stats_evaluations'] ?? '0',
            'stats_course_types' => $_POST['absence_stats_course_types'] ?? '{}',
            'stats_evaluation_details' => $_POST['absence_stats_evaluation_details'] ?? '[]'
        );

        // Get student ID (for now hardcoded, should come from session when authentication is implemented)
        $studentId = $_SESSION['id_student'] ?? 1; // Default from your session

        // Get student identifier from database
        $studentInfo = (new UserModel())->getUserById((int) $studentId);
        if (!$studentInfo) {
            throw new Exception('Étudiant non trouvé dans le système.');
        }
        $studentIdentifier = $studentInfo['identifier'];

        // Check if there are absences for the specified period
        $datetimeStart = $_SESSION['reason_data']['datetime_start'];
        $datetimeEnd = $_SESSION['reason_data']['datetime_end'];

        // Convert datetime formats to ensure consistency
        $startTimestamp = date('Y-m-d H:i:s', strtotime($datetimeStart));
        // Subtract 1 minute from end date to exclude courses starting exactly at the end time
        $endTimestamp = date('Y-m-d H:i:s', strtotime($datetimeEnd));

        $foundAbsences = $absenceModel->getAbsencesForProofSubmission(
            $studentIdentifier,
            $startTimestamp,
            $endTimestamp
        );

        if (count($foundAbsences) === 0) {
            // Clean up uploaded files if no absences found
            foreach ($uploadedFiles as $fileInfo) {
                $fileToDelete = __DIR__ . '/../' . $fileInfo['path'];
                if (file_exists($fileToDelete)) {
                    unlink($fileToDelete);
                }
            }

            // Create detailed error message
            $errorMsg = "Aucune absence non justifiée trouvée pour cette période. ";
            $errorMsg .= "Période recherchée: du " . date('d/m/Y H:i', strtotime($startTimestamp));
            $errorMsg .= " au " . date('d/m/Y H:i', strtotime($endTimestamp));
            $errorMsg .= " pour l'étudiant " . $studentIdentifier . ". ";
            $errorMsg .= " Vérifiez que les absences sont bien enregistrées dans le système et correspondent exactement aux dates sélectionnées.";

            throw new Exception($errorMsg);
        }

        // Store the absence IDs for the associative table
        $absenceIds = array_column($foundAbsences, 'absence_id');

        // Centralized absence reason mapping
        $absenceReasons = [
            'maladie' => [
                'db_value' => 'illness',
                'display_name' => 'Maladie'
            ],
            'deces' => [
                'db_value' => 'death',
                'display_name' => 'Décès dans l\'entourage'
            ],
            'obligations_familiales' => [
                'db_value' => 'family_obligations',
                'display_name' => 'Obligations familiales'
            ],
            'rdv_medical' => [
                'db_value' => 'rdv_medical',
                'display_name' => 'Rendez-vous médical'
            ],
            'convocation_officielle' => [
                'db_value' => 'official_summons',
                'display_name' => 'Convocation officielle (permis, TOIC, etc.)'
            ],
            'transport' => [
                'db_value' => 'transport_issue',
                'display_name' => 'Problème de transport'
            ],
            'autre' => [
                'db_value' => 'other',
                'display_name' => 'Autre'
            ]
        ];

        $formReason = $_SESSION['reason_data']['absence_reason'];
        $absenceReasonMapped = $absenceReasons[$formReason]['db_value'] ?? 'other';

        try {
            $proofData = [
                'student_identifier' => $studentIdentifier,
                'absence_start_date' => date('Y-m-d', strtotime($datetimeStart)),
                'absence_end_date' => date('Y-m-d', strtotime($datetimeEnd)),
                'concerned_courses' => $_SESSION['reason_data']['class_involved'],
                'main_reason' => $absenceReasonMapped,
                'custom_reason' => $_SESSION['reason_data']['other_reason'] ?: null,
                'file_path' => !empty($uploadedFiles) ? $uploadedFiles[0]['path'] : null,
                'proof_files' => $filesJson,
                'student_comment' => $_SESSION['reason_data']['comments'] ?: null,
                'submission_date' => $_SESSION['reason_data']['submission_date']
            ];

            $proofModel->createPendingProofWithAbsences($proofData, $absenceIds);

            // Update absence monitoring to mark as justified
            try {
                $monitoringModel = new AbsenceMonitoringModel();
                $monitoringModel->markAsJustifiedByProof(
                    $studentIdentifier,
                    date('Y-m-d', strtotime($datetimeStart)),
                    date('Y-m-d', strtotime($datetimeEnd))
                );
            } catch (Exception $e) {
                error_log('Failed to update absence monitoring: ' . $e->getMessage());
            }

            // Queue PDF generation + confirmation email in a background worker.
            try {
                $jobPayload = [
                    'student_id' => (int) $studentId,
                    'student_identifier' => $studentIdentifier,
                    'student_email' => $_SESSION['student_info']['email'] ?? $studentInfo['email'] ?? '',
                    'reason_data' => $_SESSION['reason_data'],
                    'queued_at' => date('c')
                ];

                $jobFile = enqueueProofEmailJob($jobPayload);
                triggerProofEmailWorker($jobFile);
            } catch (Exception $queueError) {
                error_log('Background email queue error: ' . $queueError->getMessage());
            }

            // Immediate in-app notification (email status handled by worker logs).
            try {
                $notificationModel->createNotification(
                    $studentIdentifier,
                    'justification_processed',
                    'Justificatif reçu',
                    'Votre justificatif a été reçu et est en attente de validation.',
                    true
                );
            } catch (Exception $notifError) {
                error_log("Notification insertion failed: " . $notifError->getMessage());
            }

            // Convert reason back to French for display
            $_SESSION['reason_data']['absence_reason'] = match ($_POST['absence_reason']) {
                'maladie' => 'Maladie',
                'deces' => 'Décès dans la famille',
                'obligations_familiales' => 'Obligations familiales',
                'rdv_medical' => 'Rendez-vous médical',
                'convocation_officielle' => 'Convocation officielle (permis, TOIC, etc.)',
                'transport' => 'Problème de transport',
                'autre' => 'Autre',
                default => $_POST['absence_reason']
            };

            // Send response to user immediately
            header("Location: ../../View/templates/student/proof_validation.php");
            exit();
        } catch (Exception $e) {
            // ===== Clean up all files on error =====
            foreach ($uploadedFiles as $fileInfo) {
                $fileToDelete = __DIR__ . '/../../' . $fileInfo['path'];
                if (file_exists($fileToDelete)) {
                    unlink($fileToDelete);
                }
            }

            throw new Exception("Erreur lors de l'enregistrement: " . $e->getMessage());
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: ../../View/templates/student/proof_submit.php?error=1");
        exit();
    }
} else {
    // Redirect if not POST request
    header("Location: ../../View/templates/student/proof_submit.php");
    exit();
}
