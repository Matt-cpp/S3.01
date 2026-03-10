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

require_once __DIR__ . '/../../Model/database.php';
require_once __DIR__ . '/../../Model/email.php';
require_once __DIR__ . '/../../Model/AbsenceMonitoringModel.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    session_start();

    try {
        $db = getDatabase();

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
                $db = Database::getInstance();
                $_SESSION['student_info'] = $db->selectOne(
                    "SELECT id, identifier, last_name, first_name, middle_name, birth_date, degrees, department, email, role 
                    FROM users 
                    WHERE id = ?",
                    [$_SESSION['id_student']]
                );
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
        $studentInfo = $db->selectOne("SELECT identifier FROM users WHERE id = :student_id", ['student_id' => $studentId]);

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
        $endTimestamp = date('Y-m-d H:i:s', strtotime($datetimeEnd . ' -1 minute'));

        // Query to verify absences exist for the period
        $sqlCheck = "
            SELECT COUNT(DISTINCT a.id) as absence_count
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            LEFT JOIN resources r ON cs.resource_id = r.id
            LEFT JOIN teachers t ON cs.teacher_id = t.id
            LEFT JOIN rooms rm ON cs.room_id = rm.id
            WHERE a.student_identifier = :student_identifier
                AND a.justified = FALSE
                AND a.status = 'absent'
                AND (cs.course_date + cs.start_time)::timestamp >= :datetime_start::timestamp
                AND (cs.course_date + cs.start_time)::timestamp <= :datetime_end::timestamp
        ";

        $paramsCheck = [
            'student_identifier' => $studentIdentifier,
            'datetime_start' => $startTimestamp,
            'datetime_end' => $endTimestamp
        ];

        $absenceCheck = $db->selectOne($sqlCheck, $paramsCheck);

        // Get actual absences for the period
        $sqlAbsences = "
            SELECT DISTINCT
                cs.course_date,
                cs.start_time,
                cs.end_time,
                cs.course_type,
                r.label as resource_label,
                a.id as absence_id
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            LEFT JOIN resources r ON cs.resource_id = r.id
            LEFT JOIN teachers t ON cs.teacher_id = t.id
            LEFT JOIN rooms rm ON cs.room_id = rm.id
            WHERE a.student_identifier = :student_identifier
                AND a.justified = FALSE
                AND a.status = 'absent'
                AND (cs.course_date + cs.start_time)::timestamp >= :datetime_start::timestamp
                AND (cs.course_date + cs.start_time)::timestamp <= :datetime_end::timestamp
            ORDER BY cs.course_date, cs.start_time
        ";

        $foundAbsences = $db->select($sqlAbsences, $paramsCheck);

        if (!$absenceCheck || $absenceCheck['absence_count'] == 0) {
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

        $db->beginTransaction();
        try {
            // Insert the main proof record
            $sqlInsert = "
                INSERT INTO proof (
                    student_identifier, 
                    absence_start_date, 
                    absence_end_date, 
                    concerned_courses, 
                    main_reason, 
                    custom_reason, 
                    file_path, 
                    proof_files,
                    student_comment, 
                    status, 
                    submission_date
                ) 
                VALUES (
                    :student_identifier, 
                    :absence_start_date, 
                    :absence_end_date, 
                    :concerned_courses, 
                    :main_reason, 
                    :custom_reason, 
                    :file_path, 
                    CAST(:proof_files AS jsonb),
                    :student_comment, 
                    'pending', 
                    :submission_date
                )
            ";

            $paramsInsert = [
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

            $db->execute($sqlInsert, $paramsInsert);
            $proofId = $db->lastInsertId();

            // Insert associations between this proof and all related absences
            foreach ($absenceIds as $absenceId) {
                $sqlAssoc = "
                    INSERT INTO proof_absences (proof_id, absence_id) 
                    VALUES (:proof_id, :absence_id)
                ";
                $db->execute($sqlAssoc, [
                    'proof_id' => $proofId,
                    'absence_id' => $absenceId
                ]);
            }

            $db->commit();

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

            // Send the email of validation to the student
            $emailService = new EmailService();

            $htmlBody = '
            <h1>Confirmation de réception de votre justificatif</h1>
            <p>Votre justificatif d\'absence a été reçu avec succès et est maintenant en attente de validation.</p>
            <p>Vous trouverez ci-joint :</p>
            <ul>
                <li>Votre document justificatif original</li>
                <li>Un récapitulatif détaillé de votre demande au format PDF</li>
            </ul>
            <p>Vous recevrez une notification par email une fois que votre justificatif aura été traité par l\'administration.</p>
            <br>
            <p style="font-size:0.85em;color:#6c757d;margin-top:10px;"><small>Ce message est automatique — merci de ne pas y répondre.</small></p>
            <img src="cid:logoUPHF" alt="Logo UPHF" class="logo" width="220" height="80">
            <img src="cid:logoIUT" alt="Logo IUT" class="logo" width="100" height="90">
            ';

            // Generate PDF summary
            $pdfFilename = 'Justificatif_recapitulatif_' . date('Y-m-d_H-i-s') . '.pdf';
            $pdfPath = __DIR__ . '/../../uploads/' . $pdfFilename;

            // Simulate POST data for PDF generation
            $_POST['action'] = 'download_pdf_server';
            $_POST['name_file'] = $pdfFilename;

            // Capture the PDF output
            ob_start();
            include __DIR__ . '/../shared/generate_pdf.php';
            ob_end_clean();

            // Check if PDF was generated successfully
            if (!file_exists($pdfPath)) {
                error_log('PDF generation failed - file not found: ' . $pdfPath);
            }

            // ===== Prepare email attachments =====
            $attachments = [];

            // Add all uploaded proof files
            foreach ($uploadedFiles as $fileInfo) {
                $filePath = __DIR__ . '/../../' . $fileInfo['path'];
                if (file_exists($filePath)) {
                    $attachments[] = [
                        'path' => $filePath,
                        'name' => $fileInfo['original_name']
                    ];
                } else {
                    error_log('File not found: ' . $filePath);
                }
            }

            // Add PDF if generated successfully
            if (file_exists($pdfPath)) {
                $attachments[] = ['path' => $pdfPath, 'name' => $pdfFilename];
            }

            $images = [
                'logoUPHF' => __DIR__ . '/../../View/img/UPHF.png',
                'logoIUT' => __DIR__ . '/../../View/img/logoIUT.png'
            ];

            // Update email body to mention attached files
            $htmlBody = '
            <h1>Confirmation de réception de votre justificatif</h1>
            <p>Votre justificatif d\'absence a été reçu avec succès et est maintenant en attente de validation.</p>
            <p>Vous trouverez ci-joint :</p>
            <ul>
                <li>📄 Le récapitulatif PDF de votre demande</li>';

            if (count($uploadedFiles) > 0) {
                $htmlBody .= '<li>📎 ' . count($uploadedFiles) . ' fichier(s) justificatif(s) que vous avez soumis</li>';
            } else {
                $htmlBody .= '<li>⚠️ Aucun fichier justificatif fourni</li>';
            }

            $htmlBody .= '
            </ul>
            <p>Vous recevrez une notification par email une fois que votre justificatif aura été traité par l\'administration.</p>
            <br>
            <p style="font-size:0.85em;color:#6c757d;margin-top:10px;">
                <small>Ce message est automatique — merci de ne pas y répondre.</small>
            </p>
            <img src="cid:logoUPHF" alt="Logo UPHF" class="logo" width="220" height="80">
            <img src="cid:logoIUT" alt="Logo IUT" class="logo" width="100" height="90">
            ';

            $response = $emailService->sendEmail(
                $_SESSION['student_info']['email'] ?? $studentInfo['email'] ?? 'ambroise.bisiaux@uphf.fr',
                'Confirmation de réception - Justificatif d\'absence',
                $htmlBody,
                true,
                $attachments,
                $images
            );

            if ($response['success']) {
                // Email sent successfully
                try {
                    insert_notification(
                        $db,
                        $studentIdentifier,
                        'justification_processed',
                        'Justificatif reçu',
                        'Votre justificatif a été reçu et est en attente de validation.',
                        true
                    );
                } catch (Exception $notifError) {
                    error_log("Notification insertion failed: " . $notifError->getMessage());
                }
            } else {
                error_log('Email error: ' . $response['message']);
                try {
                    insert_notification(
                        $db,
                        $studentIdentifier,
                        'justification_processed',
                        'Justificatif reçu (email échoué)',
                        'Votre justificatif a été reçu mais l\'email de confirmation n\'a pas pu être envoyé.',
                        false
                    );
                } catch (Exception $notifError) {
                    error_log("Notification insertion failed: " . $notifError->getMessage());
                }
            }

            // Delete the generated PDF after sending the email
            if (file_exists($pdfPath)) {
                unlink($pdfPath);
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
            // Only rollback if transaction is still active
            if ($db->inTransaction()) {
                $db->rollBack();
            }

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

function insert_notification(mixed $db, string $studentIdentifier, string $notificationType, string $subject, string $message, bool $sent): bool
{
    try {
        $sql = "INSERT INTO notifications (student_identifier, notification_type, subject, message , sent, sent_date) 
                VALUES (:student_identifier, :notification_type, :subject, :message, :sent::boolean, CASE WHEN :sent::boolean = TRUE THEN NOW() ELSE NULL END)";
        $db->execute($sql, [
            'student_identifier' => $studentIdentifier,
            'notification_type' => $notificationType,
            'subject' => $subject,
            'message' => $message,
            'sent' => $sent ? 'true' : 'false'
        ]);
        return true;
    } catch (Exception $e) {
        error_log('Error inserting notification: ' . $e->getMessage());
        return false;
    }
}
