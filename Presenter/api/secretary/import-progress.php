<?php
/**
 * Fichier: import-progress.php
 * 
 * API de suivi de progression d'import - Retourne l'état en temps réel d'une importation CSV.
 * Fonctionnalités principales :
 * - Récupération de l'état depuis import_jobs par ID
 * - Informations retournées : status, total_rows, processed_rows, message
 * - Permet le polling côté client pour suivre la progression
 * Utilisé par le dashboard secrétaire pour afficher la barre de progression d'import.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../../Model/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$importId = $_GET['import_id'] ?? '';

if (empty($importId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Import ID requis']);
    exit;
}

try {
    $db = Database::getInstance();

    $sql = "SELECT id, status, total_rows, processed_rows, message, updated_at 
            FROM import_jobs 
            WHERE id = :id";

    $job = $db->selectOne($sql, [':id' => $importId]);

    if (!$job) {
        http_response_code(404);
        echo json_encode(['error' => 'Import non trouvé']);
        exit;
    }

    echo json_encode([
        'status' => $job['status'],
        'total' => $job['total_rows'],
        'processed' => $job['processed_rows'],
        'message' => $job['message'] ?? 'En cours...',
        'total_processed' => $job['processed_rows']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
