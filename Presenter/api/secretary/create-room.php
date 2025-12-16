s
<?php
/**
 * Fichier: create-room.php
 * 
 * API de création de salle - Permet au secrétaire de créer une nouvelle salle.
 * Fonctionnalités principales :
 * - Validation du code de salle (requis)
 * - Création de la salle dans la table rooms
 * - Vérification des doublons
 * - Retourne les informations de la salle créée
 * Utilisé par le formulaire de création manuelle d'absence quand une salle n'existe pas.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../secretary/dashboard-presenter.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$code = $_POST['code'] ?? '';

if (empty($code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Code de salle requis']);
    exit;
}

try {
    $presenter = new DashboardSecretaryPresenter();
    $room = $presenter->createRoom($code);
    echo json_encode(['success' => true, 'room' => $room]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
