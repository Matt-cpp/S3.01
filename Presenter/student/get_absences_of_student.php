<?php

declare(strict_types=1);

/**
 * File: get_absences_of_student.php
 *
 * Absence retrieval API – Returns the list of unjustified absences for a student over a period.
 * Main features:
 * - Filters absences over a period (datetime_start and datetime_end)
 * - Excludes absences already linked to a proof (except in edit mode)
 * - Returns full course details (subject, type, schedule, teacher, room)
 * - Calculates statistics (hours, half-days, evaluations)
 * - Detects absences during evaluations
 * Used by AJAX during proof submission/edit to display concerned courses.
 * Output format: JSON with course list and statistics.
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

    $datetimeStart = $_GET['datetime_start'] ?? '';
    $datetimeEnd = $_GET['datetime_end'] ?? '';
    $studentId = $_GET['student_id'] ?? 1;
    $proofId = $_GET['proof_id'] ?? null;

    if (empty($datetimeStart) || empty($datetimeEnd)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'datetime_start and datetime_end are required',
            'courses' => []
        ]);
        exit;
    }

    // Subtract 1 minute from end date to exclude courses starting exactly at end time
    $startDate = date('Y-m-d H:i:s', strtotime($datetimeStart));
    $endDate = date('Y-m-d H:i:s', strtotime($datetimeEnd . ' -1 minute'));

    if (!$startDate || !$endDate) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid datetime format',
            'courses' => []
        ]);
        exit;
    }

    $sqlUser = 'SELECT identifier FROM users WHERE id = :student_id';
    $user = $db->selectOne($sqlUser, ['student_id' => $studentId]);

    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'error' => 'Student not found',
            'courses' => []
        ]);
        exit;
    }

    $studentIdentifier = $user['identifier'];

    // Get unjustified absences, excluding those already linked to a proof
    // (unless editing an existing proof)
    $sql = "        SELECT DISTINCT
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
                " . ($proofId ? "OR EXISTS (
                    SELECT 1 
                    FROM proof_absences pa 
                    WHERE pa.absence_id = a.id AND pa.proof_id = :proof_id
                )" : "") . "
            )
        ORDER BY cs.course_date, cs.start_time
    ";

    $params = [
        'student_identifier' => $studentIdentifier,
        'datetime_start' => $startDate,
        'datetime_end' => $endDate
    ];

    if ($proofId) {
        $params['proof_id'] = $proofId;
    }

    $absences = $db->select($sql, $params);

    // Format the results for display
    $courses = [];
    foreach ($absences as $absence) {
        $courseInfo = '';

        // Build course description
        if ($absence['resource_label']) {
            $courseInfo .= $absence['resource_label'];
            if ($absence['resource_code']) {
                $courseInfo .= ' (' . $absence['resource_code'] . ')';
            }
        } else {
            $courseInfo .= 'Cours non spécifié';
        }

        if ($absence['course_type']) {
            $courseInfo .= ' - ' . strtoupper($absence['course_type']);
        }

        $courseDate = date('d/m/Y', strtotime($absence['course_date']));
        $startTime = date('H:i', strtotime($absence['start_time']));
        $endTime = date('H:i', strtotime($absence['end_time']));
        $courseInfo .= ' - ' . $courseDate . ' (' . $startTime . '-' . $endTime . ')';

        // Add teacher if available
        if ($absence['teacher_last_name'] && $absence['teacher_first_name']) {
            $courseInfo .= ' - ' . $absence['teacher_first_name'] . ' ' . $absence['teacher_last_name'];
        }

        if ($absence['room_label']) {
            $courseInfo .= ' - ' . $absence['room_label'];
        }

        $courses[] = [
            'id' => $absence['absence_id'],
            'course_slot_id' => $absence['course_slot_id'],
            'description' => $courseInfo,
            'resource_code' => $absence['resource_code'],
            'resource_label' => $absence['resource_label'],
            'course_type' => $absence['course_type'],
            'course_date' => $courseDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'teacher' => ($absence['teacher_first_name'] && $absence['teacher_last_name'])
                ? $absence['teacher_first_name'] . ' ' . $absence['teacher_last_name'] : null,
            'room' => $absence['room_label'],
            'is_evaluation' => $absence['is_evaluation'] ?? false
        ];
    }

    echo json_encode([
        'success' => true,
        'courses' => $courses,
        'count' => count($courses)
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage(),
        'courses' => []
    ]);
}
