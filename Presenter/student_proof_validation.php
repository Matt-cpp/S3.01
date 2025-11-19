<!-- Backend de validation des justificatifs étudiants avec upload des fichiers, envoie de l'email et vérification des conditions d'envoie du justificatif -->

<?php

require_once __DIR__ . '/../Model/database.php';
require_once __DIR__ . '/../Model/email.php';
require_once __DIR__ . '/../Model/AbsenceMonitoringModel.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    session_start();

    try {
        $db = getDatabase();

        // Gestion de multiples fichiers
        $uploaded_files = [];
        $max_file_size = 5 * 1024 * 1024; // 5MB
        $max_total_size = 20 * 1024 * 1024; // 20MB
        $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx'];
        $upload_dir = __DIR__ . '/../uploads/';

        // Créer le dossier d'upload si nécessaire
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Vérifier si des fichiers ont été uploadés
        if (isset($_FILES['proof_files']) && !empty($_FILES['proof_files']['name'][0])) {
            $files_count = count($_FILES['proof_files']['name']);
            $total_size = 0;

            // Parcourir tous les fichiers
            for ($i = 0; $i < $files_count; $i++) {
                // Vérifier les erreurs d'upload
                if ($_FILES['proof_files']['error'][$i] !== UPLOAD_ERR_OK) {
                    continue; // Ignorer les fichiers avec erreurs
                }

                $original_name = $_FILES['proof_files']['name'][$i];
                $tmp_name = $_FILES['proof_files']['tmp_name'][$i];
                $file_size = $_FILES['proof_files']['size'][$i];
                $file_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

                // Validation de l'extension
                if (!in_array($file_extension, $allowed_extensions)) {
                    throw new Exception("Format de fichier non autorisé : $original_name");
                }

                // Validation de la taille
                if ($file_size > $max_file_size || $file_size === 0) {
                    throw new Exception("Taille de fichier invalide : $original_name");
                }

                $total_size += $file_size;

                // Vérifier la taille totale
                if ($total_size > $max_total_size) {
                    throw new Exception("La taille totale des fichiers dépasse 20MB");
                }

                // Créer un nom unique pour le fichier
                date_default_timezone_set('Europe/Paris');
                $unique_name = uniqid() . '_' . date('Y-m-d_H-i-s') . '.' . $file_extension;
                $file_path = $upload_dir . $unique_name;

                // Déplacer le fichier uploadé
                if (!move_uploaded_file($tmp_name, $file_path)) {
                    throw new Exception("Erreur lors de l'upload de : $original_name");
                }

                // Ajouter les informations du fichier au tableau
                // Déterminer le type MIME basé sur l'extension
                // MIME = id de format de fichier
                $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                $mime_types = [
                    'pdf' => 'application/pdf',
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'doc' => 'application/msword',
                    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                ];
                $mime_type = $mime_types[$extension] ?? 'application/octet-stream';

                $uploaded_files[] = [
                    'original_name' => $original_name,
                    'saved_name' => $unique_name,
                    'path' => 'uploads/' . $unique_name,
                    'file_size' => $file_size,
                    'mime_type' => $mime_type,
                    'uploaded_at' => date('Y-m-d H:i:s')
                ];
            }
        }

        // Stocker les informations des fichiers en JSON pour la session
        $files_json = json_encode($uploaded_files, JSON_UNESCAPED_UNICODE);

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

            // Gestion de multiples fichiers
            'proof_files' => $uploaded_files,  // Array de fichiers
            'proof_files_json' => $files_json,  // JSON pour la BD

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

        $student_identifier = $studentInfo['identifier'];

        // Check if there are absences for the specified period
        $datetime_start = $_SESSION['reason_data']['datetime_start'];
        $datetime_end = $_SESSION['reason_data']['datetime_end'];

        // Convert datetime formats to ensure consistency
        $start_timestamp = date('Y-m-d H:i:s', strtotime($datetime_start));
        $end_timestamp = date('Y-m-d H:i:s', strtotime($datetime_end));

        // Query to verify absences exist for the period (use same logic as AJAX call)
        $sql_check = "
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

        $params_check = [
            'student_identifier' => $student_identifier,
            'datetime_start' => $start_timestamp,
            'datetime_end' => $end_timestamp
        ];

        $absence_check = $db->selectOne($sql_check, $params_check);

        // Debug: get the actual absences to see what we're finding
        $sql_debug = "
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

        $debug_absences = $db->select($sql_debug, $params_check);

        if (!$absence_check || $absence_check['absence_count'] == 0) {
            // Clean up uploaded file if no absences found
            if ($saved_file_path && file_exists(__DIR__ . '/../' . $saved_file_path)) {
                unlink(__DIR__ . '/../' . $saved_file_path);
            }

            // Create detailed error message with debug info
            $error_msg = "Aucune absence non justifiée trouvée pour cette période. ";
            $error_msg .= "Période recherchée: du " . date('d/m/Y H:i', strtotime($start_timestamp));
            $error_msg .= " au " . date('d/m/Y H:i', strtotime($end_timestamp));
            $error_msg .= " pour l'étudiant " . $student_identifier . ". ";

            // Add debug information about what we found
            if (!empty($debug_absences)) {
                $error_msg .= "CEPENDANT, la requête de débogage a trouvé " . count($debug_absences) . " absences: ";
                foreach ($debug_absences as $abs) {
                    $error_msg .= "[" . $abs['course_date'] . " " . $abs['start_time'] . "-" . $abs['end_time'] . " " . ($abs['resource_label'] ?? 'N/A') . "] ";
                }
            } else {
                $error_msg .= "La requête de débogage n'a également trouvé aucune absence.";
            }

            $error_msg .= " Vérifiez que les absences sont bien enregistrées dans le système et correspondent exactement aux dates sélectionnées.";

            throw new Exception($error_msg);
        }

        // Store the absence IDs for the associative table
        $absence_ids = array_column($debug_absences, 'absence_id');

        // Centralized absence reason mapping
        $absence_reasons = [
            'maladie' => [
                'db_value' => 'illness',
                'display_name' => 'Maladie'
            ],
            'deces' => [
                'db_value' => 'death',
                'display_name' => 'Décès dans la famille'
            ],
            'obligations_familiales' => [
                'db_value' => 'family_obligations',
                'display_name' => 'Obligations familiales'
            ],
            'rdv_medical' => [
                'db_value' => 'other',
                'display_name' => 'Rendez-vous médical'
            ],
            'convocation_officielle' => [
                'db_value' => 'other',
                'display_name' => 'Convocation officielle (permis, TOIC, etc.)'
            ],
            'transport' => [
                'db_value' => 'other',
                'display_name' => 'Problème de transport'
            ],
            'autre' => [
                'db_value' => 'other',
                'display_name' => 'Autre'
            ]
        ];

        $form_reason = $_SESSION['reason_data']['absence_reason'];
        $absence_reason_mapped = $absence_reasons[$form_reason]['db_value'] ?? 'other';

        // Insert the proof into database
        $db->beginTransaction();
        try {
            // First, insert the main proof record
            $sql_insert = "
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
                    :proof_files::jsonb,
                    :student_comment, 
                    'pending', 
                    :submission_date
                )
            ";

            $params_insert = [
                'student_identifier' => $student_identifier,
                'absence_start_date' => date('Y-m-d', strtotime($datetime_start)),
                'absence_end_date' => date('Y-m-d', strtotime($datetime_end)),
                'concerned_courses' => $_SESSION['reason_data']['class_involved'],
                'main_reason' => $absence_reason_mapped,
                'custom_reason' => $_SESSION['reason_data']['other_reason'],
                'file_path' => !empty($uploaded_files) ? $uploaded_files[0]['path'] : null, // Garder pour compatibilité
                'proof_files' => $files_json, // NOUVEAU : JSON des fichiers
                'student_comment' => $_SESSION['reason_data']['comments'],
                'submission_date' => $_SESSION['reason_data']['submission_date']
            ];

            $db->execute($sql_insert, $params_insert);
            $proof_id = $db->lastInsertId();

            // Then, insert the associations between this proof and all related absences
            foreach ($absence_ids as $absence_id) {
                $sql_assoc = "
                    INSERT INTO proof_absences (proof_id, absence_id) 
                    VALUES (:proof_id, :absence_id)
                ";
                $db->execute($sql_assoc, [
                    'proof_id' => $proof_id,
                    'absence_id' => $absence_id
                ]);
            }

            $db->commit();

            // Update absence monitoring to mark as justified
            // This prevents reminder emails from being sent
            try {
                $monitoringModel = new AbsenceMonitoringModel();
                $monitoringModel->markAsJustifiedByProof(
                    $student_identifier,
                    date('Y-m-d', strtotime($datetime_start)),
                    date('Y-m-d', strtotime($datetime_end))
                );
            } catch (Exception $e) {
                // Log but don't fail the proof submission
                error_log("Failed to update absence monitoring: " . $e->getMessage());
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

            // Generate PDF summary using the generate_pdf.php logic
            $pdf_filename = 'Justificatif_recapitulatif_' . date('Y-m-d_H-i-s') . '.pdf';
            $pdf_path = __DIR__ . '/../uploads/' . $pdf_filename;

            // Simulate POST data for PDF generation
            $_POST['action'] = 'download_pdf_server';
            $_POST['name_file'] = $pdf_filename;

            // Capture the PDF output by including the generate_pdf.php file
            ob_start();
            include __DIR__ . '/generate_pdf.php';
            ob_end_clean();

            // Check if PDF was generated successfully
            if (!file_exists($pdf_path)) {
                error_log("Background email script: PDF generation failed - file not found: " . $pdf_path);
                // Continue with email sending even if PDF generation fails
            }

            // ===== MODIFIÉ : Préparation des pièces jointes pour l'email =====
            $attachments = [];

            // Ajouter tous les fichiers justificatifs uploadés
            foreach ($uploaded_files as $file_info) {
                $file_path = __DIR__ . '/../' . $file_info['path'];
                if (file_exists($file_path)) {
                    $attachments[] = [
                        'path' => $file_path,
                        'name' => $file_info['original_name']
                    ];
                } else {
                    error_log("Fichier non trouvé : " . $file_path);
                }
            }

            // Add PDF if it was generated successfully
            if (file_exists($pdf_path)) {
                $attachments[] = ['path' => $pdf_path, 'name' => $pdf_filename];
            }

            $images = [
                'logoUPHF' => __DIR__ . '/../View/img/UPHF.png',
                'logoIUT' => __DIR__ . '/../View/img/logoIUT.png'
            ];

            // Modifier le corps de l'email pour mentionner les fichiers
            $htmlBody = '
            <h1>Confirmation de réception de votre justificatif</h1>
            <p>Votre justificatif d\'absence a été reçu avec succès et est maintenant en attente de validation.</p>
            <p>Vous trouverez ci-joint :</p>
            <ul>
                <li>📄 Le récapitulatif PDF de votre demande</li>';

            if (count($uploaded_files) > 0) {
                $htmlBody .= '<li>📎 ' . count($uploaded_files) . ' fichier(s) justificatif(s) que vous avez soumis</li>';
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
                //TODO mettre mail de l'étudiant donc $SESSION['student_info']['email']
                'ambroise.bisiaux@uphf.fr',
                'Confirmation de réception - Justificatif d\'absence',
                $htmlBody,
                true,
                $attachments,
                $images
            );

            if ($response['success']) {
                // Email sent successfully
            } else {
                // Log the email error but do not fail the whole process
                error_log("Email error: " . $response['message']);
            }

            // Delete the generated PDF after sending the email
            if (file_exists($pdf_path)) {
                unlink($pdf_path);
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

            // Send response to user immediately, then handle emails in background
            header("Location: ../View/templates/student_proof_validation.php");

            // Now handle email operations in background (user already redirected)
            try {
                $emailService = new EmailService();

                $htmlBody = '
                <h1>Résumé de votre justificatif envoyé</h1>
                <p>Veuillez trouver le document récapitulatif ci-joint.</p>
                <img src="cid:logoUPHF" alt="Logo UPHF" class="logo" width="220" height="80">
                <img src="cid:logoIUT" alt="Logo IUT" class="logo" width="100" height="90">
                ';

                $attachments = [
                    __DIR__ . '/../' . $saved_file_path,
                ];

                $images = [
                    'logoUPHF' => __DIR__ . '/../View/img/UPHF.png',
                    'logoIUT' => __DIR__ . '/../View/img/logoIUT.png'
                ];

                $response = $emailService->sendEmail(
                    'ambroise.bisiaux@uphf.fr',
                    'Justificatif d\'absence - Confirmation',
                    $htmlBody,
                    true,
                    $attachments,
                    $images
                );

                if ($response['success']) {
                    // Log successful email notification
                    insert_notification(
                        $db,
                        $student_identifier,
                        'justification_processed',
                        'Justificatif reçu',
                        'Votre justificatif a été reçu et est en cours de traitement.',
                        true
                    );
                } else {
                    // Log the email error but do not fail the whole process
                    insert_notification(
                        $db,
                        $student_identifier,
                        'justification_processed',
                        'Justificatif reçu (email échoué)',
                        'Votre justificatif n\'a pas pu être envoyé.',
                        false
                    );
                    error_log("Email error: " . $response['message']);
                }
            } catch (Exception $email_error) {
                // Log email error but don't fail the main process since the proof was saved
                error_log("Email sending failed: " . $email_error->getMessage());
                // Still try to insert a notification about the email failure
                try {
                    insert_notification(
                        $db,
                        $student_identifier,
                        'justification_processed',
                        'Justificatif reçu (email échoué)',
                        'Votre justificatif a été reçu mais l\'email de confirmation n\'a pas pu être envoyé.',
                        false
                    );
                } catch (Exception $notification_error) {
                    error_log("Notification insertion failed: " . $notification_error->getMessage());
                }
            }

            exit();

        } catch (Exception $e) {
            // Only rollback if transaction is still active
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            // ===== MODIFIÉ : Nettoyer tous les fichiers en cas d'erreur =====
            foreach ($uploaded_files as $file_info) {
                $file_to_delete = __DIR__ . '/../' . $file_info['path'];
                if (file_exists($file_to_delete)) {
                    unlink($file_to_delete);
                }
            }

            throw new Exception("Erreur lors de l'enregistrement: " . $e->getMessage());
        }

    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: ../View/templates/student_proof_submit.php?error=1");
        exit();
    }
} else {
    // Redirect if not POST request
    header("Location: ../View/templates/student_proof_submit.php");
    exit();
}

function insert_notification($db, $student_identifier, $notification_type, $subject, $message, $sent): bool
{
    try {
        $sql = "INSERT INTO notifications (student_identifier, notification_type, subject, message , sent, sent_date) 
                VALUES (:student_identifier, :notification_type, :subject, :message, :sent, CASE WHEN :sent = TRUE THEN NOW() ELSE NULL END)";
        $db->execute($sql, [
            'student_identifier' => $student_identifier,
            'notification_type' => $notification_type,
            'subject' => $subject,
            'message' => $message,
            'sent' => $sent
        ]);
        return true;
    } catch (Exception $e) {
        error_log("Error inserting notification: " . $e->getMessage());
        return false;
    }
}
?>