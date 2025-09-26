<?php

require_once __DIR__ . '/../Model/database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    session_start();

    try {
        $db = getDatabase();

        // Handle file upload first
        $uploaded_file_name = '';
        $saved_file_path = '';

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

            $uploaded_file_name = $_FILES['proof_file']['name'];
            $saved_file_path = 'uploads/' . $unique_name; // Relative path for storage
        } else {
            throw new Exception('Aucun fichier justificatif fourni ou erreur lors du téléchargement.');
        }

        // Store form data in session
        $_SESSION['reason_data'] = array(
            'datetime_start' => $_POST['datetime_start'] ?? '',
            'datetime_end' => $_POST['datetime_end'] ?? '',
            'class_involved' => $_POST['class_involved'] ?? '',
            'absence_reason' => $_POST['absence_reason'] ?? '',
            'other_reason' => $_POST['other_reason'] ?? '',
            'proof_file' => $uploaded_file_name,
            'saved_file_path' => $saved_file_path,
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

        // Debug: Let's also get the actual absences to see what we're finding
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

        // Convert absence reason to match enum
        $absence_reason_mapped = match ($_SESSION['reason_data']['absence_reason']) {
            'maladie' => 'illness',
            'deces' => 'death',
            'obligations_familiales' => 'family_obligations',
            'rdv_medical' => 'other',
            'convocation_officielle' => 'other',
            'transport' => 'other',
            'autre' => 'other',
            default => 'other'
        };

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
                'file_path' => $saved_file_path,
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

            // Redirect to success page
            header("Location: ../View/templates/validation_student_proof.php");
            exit();

        } catch (Exception $e) {
            $db->rollBack();
            // Clean up uploaded file on database error
            if ($saved_file_path && file_exists(__DIR__ . '/../' . $saved_file_path)) {
                unlink(__DIR__ . '/../' . $saved_file_path);
            }
            throw new Exception("Erreur lors de l'enregistrement: " . $e->getMessage());
        }

    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: ../View/templates/student_proof.php?error=1");
        exit();
    }
} else {
    // Redirect if not POST request
    header("Location: ../View/templates/student_proof.php");
    exit();
}
?>