<?php

declare(strict_types=1);

/**
 * Resource search API - Quick search for resources/subjects by code or label.
 * Main features:
 * - Search with minimum 2 characters
 * - Search in resource code and label
 * - Returns results in JSON format
 * - Uses DashboardSecretaryPresenter for search logic
 * Used by autocomplete in the manual absence creation form.
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
    $results = $presenter->searchResources($query);
    echo json_encode($results);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
