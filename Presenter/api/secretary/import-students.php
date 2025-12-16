<?php
/**
 * Fichier: import-students.php
 * 
 * API d'importation d'étudiants - Traite un fichier CSV pour importer des étudiants dans le système.
 * Fonctionnalités principales :
 * - Validation du fichier CSV uploadé (format, structure)
 * - Parsing du CSV avec gestion du BOM et des encodages
 * - Mapping des colonnes (case-insensitive)
 * - Création/mise à jour des étudiants dans la table users
 * - Gestion des groupes et associations
 * - Logs des succès et erreurs
 * - Import en batch pour de meilleures performances
 * Utilisé par le dashboard secrétaire pour l'import massif d'étudiants.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../Model/database.php';
require_once __DIR__ . '/../../secretary/dashboard-presenter.php';

try {
    // Validate file upload
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Aucun fichier n'a été téléchargé ou une erreur s'est produite");
    }

    $file = $_FILES['csv_file'];

    // Validate file type
    $fileInfo = pathinfo($file['name']);
    if (strtolower($fileInfo['extension']) !== 'csv') {
        throw new Exception("Le fichier doit être au format CSV");
    }

    // Open the CSV file
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        throw new Exception("Impossible de lire le fichier CSV");
    }

    // Read header
    $header = fgetcsv($handle, 0, ';', '"', '\\');
    if (!$header) {
        fclose($handle);
        throw new Exception("Le fichier CSV est vide ou invalide");
    }

    // Clean BOM from header
    $header = array_map(function ($field) {
        return trim(str_replace("\xEF\xBB\xBF", '', $field));
    }, $header);

    // Create a case-insensitive mapping for header columns
    $headerMap = [];
    foreach ($header as $index => $column) {
        $headerMap[strtolower($column)] = $index;
    }

    // Helper function to get column value (case-insensitive)
    $getColumn = function ($data, $columnName) use ($headerMap) {
        $key = strtolower($columnName);
        if (isset($headerMap[$key])) {
            return $data[$headerMap[$key]] ?? '';
        }
        return '';
    };

    // Validate required columns (case-insensitive)
    $requiredColumns = ['nom', 'prénom', 'mail', 'code_nip'];
    foreach ($requiredColumns as $column) {
        if (!isset($headerMap[$column])) {
            fclose($handle);
            throw new Exception("Colonne requise manquante: $column");
        }
    }

    // Get database instance
    $db = Database::getInstance();
    $presenter = new DashboardSecretaryPresenter();

    $createdCount = 0;
    $skippedCount = 0;
    $errors = [];

    // Start transaction
    $db->beginTransaction();

    try {
        // Process each row
        while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            // Extract student data using case-insensitive column names
            $lastName = trim($getColumn($row, 'Nom'));
            $firstName = trim($getColumn($row, 'Prénom'));
            $email = trim($getColumn($row, 'Mail'));
            $identifier = trim($getColumn($row, 'code_nip'));

            // Validate required fields
            if (empty($lastName) || empty($firstName) || empty($email) || empty($identifier)) {
                $errors[] = "Ligne ignorée: données manquantes (Nom: $lastName, Prénom: $firstName)";
                $skippedCount++;
                continue;
            }

            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Ligne ignorée: email invalide ($email)";
                $skippedCount++;
                continue;
            }

            // Check if student already exists
            $existing = $db->selectOne(
                "SELECT id FROM users WHERE identifier = :identifier",
                [':identifier' => $identifier]
            );

            if ($existing) {
                $skippedCount++;
                continue;
            }
            $email = strtolower($email);
            // Insert new student
            $sql = "INSERT INTO users (identifier, last_name, first_name, email, role, created_at) 
                    VALUES (:identifier, :last_name, :first_name, :email, 'student', NOW())";

            $db->execute($sql, [
                ':identifier' => $identifier,
                ':last_name' => $lastName,
                ':first_name' => $firstName,
                ':email' => $email
            ]);

            $createdCount++;
        }

        $db->commit();
        fclose($handle);

        // Log to history
        $presenter->logImportHistory(
            'Import étudiants',
            "$createdCount étudiant(s) créé(s), $skippedCount ignoré(s)",
            'success'
        );

        echo json_encode([
            'success' => true,
            'created' => $createdCount,
            'skipped' => $skippedCount,
            'errors' => $errors,
            'message' => "Import terminé avec succès"
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        fclose($handle);
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);

    // Log error to history
    if (isset($presenter)) {
        $presenter->logImportHistory(
            'Import étudiants',
            'Erreur: ' . $e->getMessage(),
            'error'
        );
    }
}
