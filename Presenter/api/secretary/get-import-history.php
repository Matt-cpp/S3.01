<?php
/**
 * Fichier: get-import-history.php
 * 
 * API d'historique d'importation - Récupère l'historique complet des actions d'import.
 * Fonctionnalités principales :
 * - Récupération de l'historique depuis la table import_history
 * - Liste chronologique des actions (imports, créations, erreurs)
 * - Retourne les données au format JSON
 * Utilisé par le dashboard secrétaire pour afficher le journal des importations.
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
