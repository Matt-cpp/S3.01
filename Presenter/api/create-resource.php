<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../dashboard-secretary-presenter.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$code = $_POST['code'] ?? '';

if (empty($code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Code requis']);
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
