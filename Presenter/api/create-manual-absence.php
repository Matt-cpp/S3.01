<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../dashboard-secretary-presenter.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Validate required fields
$required = ['student_id', 'absence_date', 'start_time', 'end_time', 'resource_id', 'room_id', 'course_type'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Champ requis: $field"]);
        exit;
    }
}

try {
    $presenter = new DashboardSecretaryPresenter();
    $absenceId = $presenter->createManualAbsence($_POST);
    echo json_encode(['success' => true, 'absence_id' => $absenceId]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
