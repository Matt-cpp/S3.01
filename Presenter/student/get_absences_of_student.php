<?php

declare(strict_types=1);

// Buffer all output to prevent any stray HTML before JSON
ob_start();

// Catch any errors and convert to JSON
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'error' => "PHP Error: $errstr on line $errline",
        'courses' => [],
        'file' => $errfile,
        'line' => $errline
    ]);
    exit;
});

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

// Start session and require authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON headers BEFORE any includes that might output HTML
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../Model/UserModel.php';
require_once __DIR__ . '/../../Model/AbsenceModel.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

try {
    // Clean any output that might have been buffered
    ob_end_clean();
    ob_start();

    $userModel = new UserModel();
    $absenceModel = new AbsenceModel();

    // Get parameters from request
    $datetime_start = $_GET['datetime_start'] ?? '';
    $datetime_end = $_GET['datetime_end'] ?? '';

    // Get student_id from session (current user) instead of default to 1
    if (!isset($_SESSION['id_student'])) {
        ob_end_clean();
        http_response_code(401);
        echo json_encode([
            'error' => 'User not authenticated',
            'courses' => []
        ]);
        exit;
    }

    $student_id = $_SESSION['id_student'];
    $proof_id = $_GET['proof_id'] ?? null;

    if (empty($datetime_start) || empty($datetime_end)) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode([
            'error' => 'datetime_start and datetime_end are required',
            'courses' => []
        ]);
        exit;
    }

    // Convert datetime format - try multiple formats
    $start_date = null;
    $end_date = null;

    // Try format: dd/mm/yyyy hh:mm
    $parsed_start = \DateTime::createFromFormat('d/m/Y H:i', $datetime_start);
    if ($parsed_start) {
        $start_date = $parsed_start->format('Y-m-d H:i:s');
    } else {
        // Try format: yyyy-mm-dd hh:mm
        $parsed_start = \DateTime::createFromFormat('Y-m-d H:i', $datetime_start);
        if ($parsed_start) {
            $start_date = $parsed_start->format('Y-m-d H:i:s');
        } else {
            // Try PHP's strtotime as fallback
            $timestamp = strtotime($datetime_start);
            if ($timestamp) {
                $start_date = date('Y-m-d H:i:s', $timestamp);
            }
        }
    }

    $parsed_end = \DateTime::createFromFormat('d/m/Y H:i', $datetime_end);
    if ($parsed_end) {
        $end_date = $parsed_end->format('Y-m-d H:i:s');
    } else {
        $parsed_end = \DateTime::createFromFormat('Y-m-d H:i', $datetime_end);
        if ($parsed_end) {
            $end_date = $parsed_end->format('Y-m-d H:i:s');
        } else {
            $timestamp = strtotime($datetime_end);
            if ($timestamp) {
                $end_date = date('Y-m-d H:i:s', $timestamp);
            }
        }
    }

    if (!$start_date || !$end_date) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid datetime format',
            'courses' => [],
            'debug' => [
                'received_start' => $datetime_start,
                'received_end' => $datetime_end,
                'parsed_start' => $start_date,
                'parsed_end' => $end_date
            ]
        ]);
        exit;
    }

    $user = $userModel->getUserById((int) $student_id);

    if (!$user) {
        ob_end_clean();
        http_response_code(404);
        echo json_encode([
            'error' => 'Student not found',
            'courses' => []
        ]);
        exit;
    }

    $studentIdentifier = $user['identifier'];

    $absences = $absenceModel->getAbsencesForProofSubmission(
        $studentIdentifier,
        $start_date,
        $end_date,
        $proof_id ? (int) $proof_id : null
    );

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
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'courses' => $courses,
        'count' => count($courses),
        'query_params' => [
            'datetime_start' => $start_date,
            'datetime_end' => $end_date,
            'student_id' => $student_id,
            'student_identifier' => $studentIdentifier
        ]
    ]);
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    error_log("GET_ABSENCES ERROR: " . $e->getMessage());
    error_log("SQL: " . ($sql ?? 'N/A'));
    error_log("Params: " . json_encode(($params ?? [])));
    echo json_encode([
        'error' => $e->getMessage(),
        'courses' => [],
        'sql' => ($sql ?? 'N/A'),
        'params' => ($params ?? [])
    ]);
}
