<?php

declare(strict_types=1);

/**
 * CSV file import API - Handles upload and initial registration of a CSV file.
 * Main features:
 * - Validation of the uploaded file (CSV type only)
 * - Generation of a unique import ID
 * - Saving the file to the uploads/imports/ folder
 * - Counting the total number of rows in the CSV
 * - Creating a record in import_jobs with 'pending' status
 * - Returns the import ID for asynchronous processing
 * Used by the secretary dashboard to start an import process.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../../Model/database.php';
require_once __DIR__ . '/../../secretary/dashboard-presenter.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Aucun fichier téléchargé ou erreur de téléchargement']);
    exit;
}

$file = $_FILES['csv_file'];

// Validate file type
$allowedExtensions = ['csv'];
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($extension, $allowedExtensions)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Type de fichier non autorisé. Seuls les fichiers CSV sont acceptés.']);
    exit;
}

// Create uploads directory if it doesn't exist
$uploadDir = __DIR__ . '/../../uploads/imports/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$importId = uniqid('import_', true);
$filename = $importId . '.csv';
$filepath = $uploadDir . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la sauvegarde du fichier']);
    exit;
}

try {
    // Create import record in database
    $db = Database::getInstance();

    // Count total rows in CSV (excluding header)
    $handle = fopen($filepath, 'r');
    $totalRows = 0;
    fgetcsv($handle, 0, ';', '"', '\\'); // Skip header
    while (fgetcsv($handle, 0, ';', '"', '\\') !== false) {
        $totalRows++;
    }
    fclose($handle);

    // Insert job record
    $sql = "INSERT INTO import_jobs (id, filename, filepath, status, total_rows) 
            VALUES (:id, :filename, :filepath, 'pending', :total_rows)";

    $db->execute($sql, [
        ':id' => $importId,
        ':filename' => $file['name'],
        ':filepath' => $filepath,
        ':total_rows' => $totalRows
    ]);

    // Start background processing
    startBackgroundImport($importId, $filepath);

    echo json_encode([
        'success' => true,
        'import_id' => $importId,
        'message' => 'Import démarré'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Start the import process in the background
function startBackgroundImport(string $importId, string $filepath): void
{
    // For Windows, use start /B to run in background
    // For Unix/Linux, use & at the end

    $phpBinary = PHP_BINARY;
    $scriptPath = __DIR__ . '/process-csv-import.php';

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows
        $command = "start /B \"\" \"$phpBinary\" \"$scriptPath\" \"$importId\" \"$filepath\" > NUL 2>&1";
        pclose(popen($command, 'r'));
    } else {
        // Unix/Linux/Mac
        $command = "$phpBinary \"$scriptPath\" \"$importId\" \"$filepath\" > /dev/null 2>&1 &";
        exec($command);
    }
}
