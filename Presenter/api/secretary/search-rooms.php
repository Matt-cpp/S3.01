<?php
/**
 * Fichier: search-rooms.php
 * 
 * API de recherche de salles - Recherche rapide de salles par code.
 * Fonctionnalités principales :
 * - Recherche par code de salle
 * - Retourne les résultats au format JSON
 * - Utilise DashboardSecretaryPresenter pour la logique de recherche
 * Utilisé par l'autocomplétion dans le formulaire de création manuelle d'absence.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../secretary/dashboard-presenter.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$query = $_GET['q'] ?? '';

try {
    $presenter = new DashboardSecretaryPresenter();
    $results = $presenter->searchRooms($query);
    echo json_encode($results);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
