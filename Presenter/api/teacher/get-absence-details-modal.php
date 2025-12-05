<?php
// API pour récupérer les détails des absences d'un cours
header('Content-Type: application/json');

require_once __DIR__ . '/../../../controllers/auth_guard.php';
$user = requireRole('teacher');

require_once __DIR__ . '/../../teacher/evaluations_presenter.php';

$courseSlotId = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if ($courseSlotId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de cours invalide'
    ]);
    exit;
}

try {
    $teacherId = $user['id'];
    $table = new pageEvalProf($teacherId);

    // Récupérer les informations du cours
    $courseInfo = $table->infoCours($courseSlotId);

    // Récupérer les absences
    $absences = $table->listeAbsences($courseSlotId);

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
?>