<?php
/**
 * get-import-history.php
 * API pour récupérer l'historique des imports CSV et actions du secrétariat
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
