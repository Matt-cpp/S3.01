<?php
/**
 * Fichier: get-absence-details-modal.php
 * 
 * API de détails d'absences d'un cours - Retourne les informations complètes pour un cours spécifique.
 * Fonctionnalités principales :
 * - Récupération des informations du cours (date, horaire, ressource, salle)
 * - Liste des absences détectées pour ce cours
 * - Informations des étudiants absents
 * - Statut de justification pour chaque absence
 * - Réservé aux enseignants (auth_guard)
 * Utilisé par la modal de détails d'absences dans la vue enseignant.
 */

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