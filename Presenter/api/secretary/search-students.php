<?php

declare(strict_types=1);

/**
 * Student search API - Quick search for students by name/identifier.
 * Main features:
 * - Search with minimum 2 characters
 * - Search in first_name, last_name and identifier
 * - Returns results in JSON format
 * - Uses DashboardSecretaryPresenter for search logic
 * Used by autocomplete in secretary forms.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../secretary/dashboard-presenter.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $presenter = new DashboardSecretaryPresenter();
    $results = $presenter->searchStudents($query);
    echo json_encode($results);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
