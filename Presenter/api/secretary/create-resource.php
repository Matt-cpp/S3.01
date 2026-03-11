<?php

declare(strict_types=1);

/**
 * Resource creation API - Allows the secretary to create a new resource/subject.
 * Main features:
 * - Resource code validation (required)
 * - Creation of the resource in the resources table
 * - Duplicate checking
 * - Returns the created resource information
 * Used by the manual absence creation form when a resource does not exist.
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
    echo json_encode(['success' => false, 'message' => 'Code de ressource requis']);
    exit;
}

try {
    $presenter = new DashboardSecretaryPresenter();
    $resource = $presenter->createResource($code);
    echo json_encode(['success' => true, 'resource' => $resource]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
