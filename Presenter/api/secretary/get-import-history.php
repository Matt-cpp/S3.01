<?php

declare(strict_types=1);

/**
 * Import history API - Retrieves the complete history of import actions.
 * Main features:
 * - History retrieval from the import_history table
 * - Chronological list of actions (imports, creations, errors)
 * - Returns data in JSON format
 * Used by the secretary dashboard to display the import log.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../secretary/dashboard-presenter.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $presenter = new DashboardSecretaryPresenter();
    $history = $presenter->getImportHistory();
    echo json_encode($history);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
