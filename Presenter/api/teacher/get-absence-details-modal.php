<?php

declare(strict_types=1);

/**
 * API for absence details of a course - Returns complete information for a specific course.
 * Main features:
 * - Retrieval of course information (date, schedule, resource, room)
 * - List of absences detected for this course
 * - Information of absent students
 * - Justification status for each absence
 * - Reserved for teachers (auth_guard)
 * Used by the absence details modal in the teacher view.
 */

// API to retrieve absence details for a course
header('Content-Type: application/json');

require_once __DIR__ . '/../../shared/auth_guard.php';
$user = requireRole('teacher');

require_once __DIR__ . '/../../teacher/absence_details_presenter.php';

$courseSlotId = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if ($courseSlotId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de cours invalide'
    ]);
    exit;
}

try {
    $details = new AbsenceDetailsPresenter($courseSlotId);

    // Retrieve course information
    $courseInfo = $details->getAbsenceDetails();

    // Retrieve absences
    $absences = $details->getAbsences();

    echo json_encode([
        'success' => true,
        'course_info' => $courseInfo,
        'absences' => $absences
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
