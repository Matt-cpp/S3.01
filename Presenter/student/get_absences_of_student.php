<?php

/**
 * Fichier: get_absences_of_student.php
 * 
 * API de récupération des absences - Renvoie la liste des absences non justifiées d'un étudiant sur une période.
 * Fonctionnalités principales :
 * - Filtre les absences sur une période (datetime_start et datetime_end)
 * - Exclut les absences déjà liées à un justificatif (sauf en mode édition)
 * - Renvoie les détails complets des cours (matière, type, horaires, enseignant, salle)
 * - Calcule les statistiques (heures, demi-journées, évaluations)
 * - Détecte les absences aux évaluations
 * Utilisé par AJAX lors de la soumission/modification d'un justificatif pour afficher les cours concernés.
 * Format de sortie : JSON avec liste des cours et statistiques.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../Model/database.php';

try {
    $db = getDatabase();

    // Get parameters from request
    $datetime_start = $_GET['datetime_start'] ?? '';
    $datetime_end = $_GET['datetime_end'] ?? '';
    $student_id = $_GET['student_id'] ?? 1; // Default to 1 as requested
    $proof_id = $_GET['proof_id'] ?? null; // For editing mode

    // Validate required parameters
    if (empty($datetime_start) || empty($datetime_end)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'datetime_start and datetime_end are required',
            'courses' => []
        ]);
        exit;
    }

    // Convert datetime format if needed and validate
    $start_date = date('Y-m-d H:i:s', strtotime($datetime_start));
    // Subtract 1 minute from end date to exclude courses starting exactly at the end time
    // Example: if end is 11:00, we want to exclude the course starting at 11:00
    $end_date = date('Y-m-d H:i:s', strtotime($datetime_end . ' -1 minute'));

    if (!$start_date || !$end_date) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid datetime format',
            'courses' => []
        ]);
        exit;
    }

    // First, get the student's identifier from the users table
    $sql_user = "SELECT identifier FROM users WHERE id = :student_id";
    $user = $db->selectOne($sql_user, ['student_id' => $student_id]);

    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'error' => 'Student not found',
            'courses' => []
        ]);
        exit;
    }

    $student_identifier = $user['identifier'];

    // Query to get non-justified absences with course information
    // Excludes absences already linked to a proof in proof_absences table
    // EXCEPT if we're editing and the proof_id is provided (then include absences for that proof)
    $sql = "
        SELECT DISTINCT
            cs.course_date,
            cs.start_time,
            cs.end_time,
            cs.course_type,
            cs.is_evaluation,
            r.label as resource_label,
            r.code as resource_code,
            t.last_name as teacher_last_name,
            t.first_name as teacher_first_name,
            rm.code as room_label,
            a.id as absence_id,
            cs.id as course_slot_id
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
            AND (
                NOT EXISTS (
                    SELECT 1 
                    FROM proof_absences pa 
                    WHERE pa.absence_id = a.id
                )
                " . ($proof_id ? "OR EXISTS (
                    SELECT 1 
                    FROM proof_absences pa 
                    WHERE pa.absence_id = a.id AND pa.proof_id = :proof_id
                )" : "") . "
            )
        ORDER BY cs.course_date, cs.start_time
    ";

    $params = [
        'student_identifier' => $student_identifier,
        'datetime_start' => $start_date,
        'datetime_end' => $end_date
    ];

    if ($proof_id) {
        $params['proof_id'] = $proof_id;
    }

    $absences = $db->select($sql, $params);

    // Format the results for display
    $courses = [];
    foreach ($absences as $absence) {
        $course_info = '';

        // Build course description
        if ($absence['resource_label']) {
            $course_info .= $absence['resource_label'];
            if ($absence['resource_code']) {
                $course_info .= ' (' . $absence['resource_code'] . ')';
            }
        } else {
            $course_info .= 'Cours non spécifié';
        }

        // Add course type
        if ($absence['course_type']) {
            $course_info .= ' - ' . strtoupper($absence['course_type']);
        }

        // Add date and time
        $course_date = date('d/m/Y', strtotime($absence['course_date']));
        $start_time = date('H:i', strtotime($absence['start_time']));
        $end_time = date('H:i', strtotime($absence['end_time']));
        $course_info .= ' - ' . $course_date . ' (' . $start_time . '-' . $end_time . ')';

        // Add teacher if available
        if ($absence['teacher_last_name'] && $absence['teacher_first_name']) {
            $course_info .= ' - ' . $absence['teacher_first_name'] . ' ' . $absence['teacher_last_name'];
        }

        // Add room if available
        if ($absence['room_label']) {
            $course_info .= ' - ' . $absence['room_label'];
        }

        $courses[] = [
            'id' => $absence['absence_id'],
            'course_slot_id' => $absence['course_slot_id'],
            'description' => $course_info,
            'resource_code' => $absence['resource_code'],
            'resource_label' => $absence['resource_label'],
            'course_type' => $absence['course_type'],
            'course_date' => $course_date,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'teacher' => ($absence['teacher_first_name'] && $absence['teacher_last_name'])
                ? $absence['teacher_first_name'] . ' ' . $absence['teacher_last_name'] : null,
            'room' => $absence['room_label'],
            'is_evaluation' => $absence['is_evaluation'] ?? false
        ];
    }

    echo json_encode([
        'success' => true,
        'courses' => $courses,
        'count' => count($courses),
        'query_params' => [
            'datetime_start' => $start_date,
            'datetime_end' => $end_date,
            'student_identifier' => $student_identifier
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage(),
        'courses' => [],
        'debug' => [
            'student_id' => $student_id ?? 'not set',
            'datetime_start' => $datetime_start ?? 'not set',
            'datetime_end' => $datetime_end ?? 'not set',
            'start_date' => $start_date ?? 'not set',
            'end_date' => $end_date ?? 'not set'
        ]
    ]);
}
