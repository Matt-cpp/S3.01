<?php
/**
 * Fichier: create-manual-absence.php
 * 
 * API de création manuelle d'absence - Permet au secrétaire de créer une absence manuellement.
 * Fonctionnalités principales :
 * - Validation de tous les champs requis (student_id, absence_date, start_time, end_time, resource_id, room_id, course_type)
 * - Création d'un créneau de cours (course_slot) si nécessaire
 * - Enregistrement de l'absence dans la table absences
 * - Retourne l'ID de l'absence créée
 * Utilisé par le dashboard secrétaire pour ajouter des absences manuellement.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../secretary/dashboard-presenter.php';

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
