<?php

declare(strict_types=1);

/**
 * Room creation API - Allows the secretary to create a new room.
 * Main features:
 * - Room code validation (required)
 * - Creation of the room in the rooms table
 * - Duplicate checking
 * - Returns the created room information
 * Used by the manual absence creation form when a room does not exist.
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
