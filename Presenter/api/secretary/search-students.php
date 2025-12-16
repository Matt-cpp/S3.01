<?php
/**
 * Fichier: search-students.php
 * 
 * API de recherche d'étudiants - Recherche rapide d'étudiants par nom/prénom/identifiant.
 * Fonctionnalités principales :
 * - Recherche avec minimum 2 caractères
 * - Recherche dans first_name, last_name et identifier
 * - Retourne les résultats au format JSON
 * - Utilise DashboardSecretaryPresenter pour la logique de recherche
 * Utilisé par l'autocomplétion dans les formulaires secrétaire.
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
