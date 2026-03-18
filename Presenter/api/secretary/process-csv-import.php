<?php

declare(strict_types=1);

/**
 * Background CSV import processor - Executes the full import of a CSV file asynchronously.
 * Main features:
 * - Command-line (CLI) execution with arguments
 * - Status updates in import_jobs (processing -> completed/failed)
 * - Row-by-row CSV processing with progress updates
 * - Data parsing and validation (dates, times, identifiers)
 * - Creation/update of entities:
 *   - Users (students, teachers)
 *   - Groups and associations
 *   - Resources (subjects)
 *   - Rooms
 *   - Course slots
 *   - Absences
 * - Error handling and detailed logs in import_history
 * - Batch import for performance optimization
 * Called by import-csv.php via exec() for asynchronous processing.
 */

// Get command line arguments
$importId = $argv[1] ?? '';
$filepath = $argv[2] ?? '';

if (empty($importId) || empty($filepath)) {
    error_log("Import processor: Missing arguments");
    exit(1);
}

// Set up error logging
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../Model/database.php';
require_once __DIR__ . '/../../../Model/ImportModel.php';
require_once __DIR__ . '/../../../Model/CsvImportModel.php';
require_once __DIR__ . '/../../secretary/dashboard-presenter.php';

try {
    $importModel = new ImportModel();
    $csvImportModel = new CsvImportModel();

    // Update job status to processing
    updateJobStatus($importModel, $importId, 'processing', 0, 'Démarrage du traitement...');

    // Count total rows
    $handle = fopen($filepath, 'r');
    $totalRows = 0;
    fgetcsv($handle, 0, ';', '"', '\\'); // Skip header
    while (fgetcsv($handle, 0, ';', '"', '\\') !== false) {
        $totalRows++;
    }
    fclose($handle);

    // Update total
    $importModel->updateTotalRows($importId, $totalRows);

    // Process the CSV file using DataExtractor
    processCSVWithExtractor($importModel, $csvImportModel, $importId, $filepath, $totalRows);

    // Update job status to completed
    updateJobStatus($importModel, $importId, 'completed', $totalRows, 'Import terminé avec succès');

    // Log to history
    $presenter = new DashboardSecretaryPresenter();
    $presenter->logImportHistory(
        'Import CSV',
        "Fichier " . basename($filepath) . " importé avec succès ($totalRows lignes)",
        'success'
    );
} catch (Exception $e) {
    error_log("Import error: " . $e->getMessage());

    if (isset($importModel) && isset($importId)) {
        updateJobStatus($importModel, $importId, 'error', null, 'Erreur: ' . $e->getMessage());
    }

    // Log error to history
    if (isset($presenter)) {
        $presenter->logImportHistory(
            'Import CSV',
            "Erreur lors de l'import: " . $e->getMessage(),
            'error'
        );
    }

    exit(1);
}

/**
 * Update job status using a separate connection to bypass any active transactions.
 * This ensures progress updates are immediately visible.
 */
function updateJobStatus(ImportModel $importModel, string $importId, string $status, ?int $processedRows = null, ?string $message = null): void
{
    $importModel->updateJobStatusImmediate($importId, $status, $processedRows, $message);
}

function processCSVWithExtractor(ImportModel $importModel, CsvImportModel $csvImportModel, string $importId, string $filepath, int $totalRows): void
{
    $handle = fopen($filepath, 'r');
    if (!$handle) {
        throw new Exception("Impossible d'ouvrir le fichier CSV");
    }

    // Read header
    $header = fgetcsv($handle, 0, ';', '"', '\\');
    if (!$header) {
        fclose($handle);
        throw new Exception("Fichier CSV vide ou invalide");
    }

    // Clean BOM from header
    $header = array_map(function ($field) {
        return str_replace("\xEF\xBB\xBF", '', $field);
    }, $header);

    $processedCount = 0;
    $errorCount = 0;

    // Start transaction for the entire import
    $db = getDatabase();
    $db->beginTransaction();

    try {
        while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
            if (count($row) !== count($header)) {
                $errorCount++;
                continue;
            }

            $data = array_combine($header, $row);

            try {
                // Process using the same logic as extract_datas.php
                processRowFromExtractor($csvImportModel, $data);
                $processedCount++;

                // Update progress every 2 rows
                if ($processedCount % 2 === 0 || $processedCount === $totalRows) {
                    $message = "Traitement en cours: $processedCount/$totalRows lignes";
                    updateJobStatus($importModel, $importId, 'processing', $processedCount, $message);
                }
            } catch (Exception $e) {
                error_log("Error processing row: " . $e->getMessage());
                $errorCount++;
                // Continue processing other rows
            }
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

    fclose($handle);

    if ($processedCount === 0 && $errorCount > 0) {
        throw new Exception("Aucune ligne n'a pu être traitée. Vérifiez le format du fichier.");
    }
}

/**
 * Process a single row using the same logic as DataExtractor.
 * This is a simplified version adapted from extract_datas.php.
 */
function processRowFromExtractor(CsvImportModel $csvImportModel, array $data): void
{
    // Skip if no identifier
    if (!isset($data['Identifiant']) || empty(trim($data['Identifiant']))) {
        return;
    }

    $identifier = trim($data['Identifiant']);

    // Process user
    $userId = processUser($csvImportModel, $data, $identifier);

    // Process group
    $groupId = null;
    if (!empty($data['Groupes'] ?? '')) {
        $groupId = processGroup($csvImportModel, $data);
        linkUserToGroup($csvImportModel, $userId, $groupId);
    }

    // Process resource
    $resourceId = null;
    if (!empty($data['Identifiant matière'] ?? '')) {
        $resourceId = processResource($csvImportModel, $data);
    }

    // Process room
    $roomId = null;
    if (!empty($data['Salles'] ?? '')) {
        $roomId = processRoom($csvImportModel, $data);
    }

    // Process teacher
    $teacherId = null;
    if (!empty($data['Profs'] ?? '')) {
        $teacherId = processTeacher($csvImportModel, $data);
    }

    // Process course slot
    $courseSlotId = null;
    if ($resourceId && !empty($data['Date'] ?? '')) {
        $courseSlotId = processCourseSlot($csvImportModel, $data, $resourceId, $roomId, $teacherId, $groupId);
    }

    // Process absence if present
    if ($courseSlotId && isset($data['Absent/Présent']) && $data['Absent/Présent'] === 'Absence') {
        processAbsence($csvImportModel, $data, $identifier, $courseSlotId);
    }
}

// Helper functions adapted from extract_datas.php
function processUser(CsvImportModel $csvImportModel, array $data, string $identifier): int
{
    $existing = $csvImportModel->getUserIdByIdentifier($identifier);
    if ($existing !== null) {
        return $existing;
    }

    $birthDate = parseDate($data['Date de naissance'] ?? '');
    $lastName = trim($data['Nom'] ?? 'User_' . $identifier);
    $firstName = trim($data['Prénom'] ?? 'Unknown');

    return $csvImportModel->createUser([
        'identifier' => $identifier,
        'last_name' => $lastName,
        'first_name' => $firstName,
        'middle_name' => !empty($data['Prénom 2']) ? trim($data['Prénom 2']) : null,
        'birth_date' => $birthDate,
        'degrees' => !empty($data['Diplômes']) ? trim($data['Diplômes']) : null,
        'department' => !empty($data['Composante']) ? trim($data['Composante']) : null,
    ]);
}

function processGroup(CsvImportModel $csvImportModel, array $data): ?int
{
    $groupCode = trim($data['Groupes'] ?? '');

    if (empty($groupCode)) {
        return null;
    }

    $existing = $csvImportModel->getGroupIdByCode($groupCode);
    if ($existing !== null) {
        return $existing;
    }

    $year = null;
    if (preg_match('/BUT(\d+)/', $groupCode, $matches)) {
        $year = (int) $matches[1];
    }

    $program = (strpos($groupCode, 'INFO') !== false) ? 'Informatique' : 'Unknown';

    return $csvImportModel->createGroup($groupCode, $groupCode, $program, $year);
}

function processResource(CsvImportModel $csvImportModel, array $data): ?int
{
    $resourceCode = trim($data['Identifiant matière'] ?? '');

    if (empty($resourceCode)) {
        return null;
    }

    $existing = $csvImportModel->getResourceIdByCode($resourceCode);
    if ($existing !== null) {
        return $existing;
    }

    $courseType = mapCourseType(trim($data['Type'] ?? ''));
    $label = trim($data['Matière'] ?? $resourceCode);

    return $csvImportModel->createResource($resourceCode, $label, $courseType);
}

function processRoom(CsvImportModel $csvImportModel, array $data): ?int
{
    $roomCode = trim($data['Salles'] ?? '');

    if (empty($roomCode)) {
        return null;
    }

    $existing = $csvImportModel->getRoomIdByCode($roomCode);
    if ($existing !== null) {
        return $existing;
    }

    return $csvImportModel->createRoom($roomCode);
}

function processTeacher(CsvImportModel $csvImportModel, array $data): ?int
{
    $teacherName = trim($data['Profs'] ?? '');

    if (empty($teacherName)) {
        return null;
    }

    $existing = $csvImportModel->getTeacherIdByFullName($teacherName);
    if ($existing !== null) {
        return $existing;
    }

    $nameParts = explode(' ', $teacherName);
    $lastName = $nameParts[0];
    $firstName = isset($nameParts[1]) ? $nameParts[1] : '';

    return $csvImportModel->createTeacher($lastName, $firstName);
}

function processCourseSlot(CsvImportModel $csvImportModel, array $data, ?int $resourceId, ?int $roomId, ?int $teacherId, ?int $groupId): ?int
{
    $courseDate = parseDate($data['Date'] ?? '', 'd/m/Y');
    $startTime = parseTime($data['Heure'] ?? '');
    $durationMinutes = parseDuration($data['Durée'] ?? '');

    if (!$courseDate || !$startTime || !$durationMinutes) {
        return null;
    }

    $timezone = new DateTimeZone('Europe/Paris');
    $startDateTime = new DateTime($courseDate . ' ' . $startTime, $timezone);
    $endDateTime = clone $startDateTime;
    $endDateTime->add(new DateInterval('PT' . $durationMinutes . 'M'));

    $courseType = mapCourseType(trim($data['Type'] ?? ''));
    $controleValue = trim($data['Contrôle'] ?? '');
    $isEvaluation = (!empty($controleValue) && $controleValue === 'Oui');

    // Check if exists
    $existing = $csvImportModel->findMatchingCourseSlot(
        $courseDate,
        $startTime,
        $endDateTime->format('H:i:s'),
        $resourceId,
        $roomId,
        $teacherId,
        $groupId
    );
    if ($existing !== null) {
        return $existing;
    }

    return $csvImportModel->createCourseSlot([
        'course_date' => $courseDate,
        'start_time' => $startTime,
        'end_time' => $endDateTime->format('H:i:s'),
        'duration_minutes' => $durationMinutes,
        'course_type' => $courseType,
        'resource_id' => $resourceId,
        'room_id' => $roomId,
        'teacher_id' => $teacherId,
        'group_id' => $groupId,
        'is_evaluation' => $isEvaluation,
        'subject_identifier' => trim($data['Identifiant matière'] ?? ''),
    ]);
}

function processAbsence(CsvImportModel $csvImportModel, array $data, string $studentIdentifier, ?int $courseSlotId): void
{
    if (!$courseSlotId) {
        return;
    }

    $justificationText = trim($data['Justification'] ?? '');
    $justified = (!empty($justificationText) && $justificationText !== 'Non justifié');

    $status = 'absent';
    if ($justificationText === 'Absence justifiée') {
        $status = 'excused';
    }

    if ($csvImportModel->absenceExists($studentIdentifier, $courseSlotId)) {
        return;
    }

    $csvImportModel->createAbsence($studentIdentifier, $courseSlotId, $status, $justified);
}

function linkUserToGroup(CsvImportModel $csvImportModel, ?int $userId, ?int $groupId): void
{
    if (!$userId || !$groupId) {
        return;
    }

    if ($csvImportModel->userGroupLinkExists($userId, $groupId)) {
        return;
    }

    $csvImportModel->linkUserToGroup($userId, $groupId);
}

// Parsing helper functions
function parseDate(string $dateString, string $format = 'Y-m-d'): ?string
{
    if (empty($dateString)) {
        return null;
    }

    if ($format === 'd/m/Y') {
        $date = DateTime::createFromFormat('d/m/Y', $dateString);
    } else {
        $date = DateTime::createFromFormat('Y-m-d', $dateString);
    }

    return $date ? $date->format('Y-m-d') : null;
}

function parseTime(string $timeString): ?string
{
    if (empty($timeString)) {
        return null;
    }

    // Convert "15H30" or "15h30" to "15:30:00" (handle both uppercase and lowercase)
    $timeString = str_ireplace('H', ':', $timeString);
    if (preg_match('/^(\d{1,2}):(\d{2})$/', $timeString, $matches)) {
        return sprintf('%02d:%02d:00', $matches[1], $matches[2]);
    }

    return null;
}

function parseDuration(string $durationString): ?int
{
    if (empty($durationString)) {
        return null;
    }

    // Convert "1H30" or "1h30" to minutes (case-insensitive)
    if (preg_match('/^(\d+)[Hh](\d+)$/', $durationString, $matches)) {
        return ($matches[1] * 60) + $matches[2];
    } elseif (preg_match('/^(\d+)[Hh]$/', $durationString, $matches)) {
        return $matches[1] * 60;
    }

    return null;
}

function mapCourseType(string $type): string
{
    $typeMap = [
        'CM' => 'CM',
        'TD' => 'TD',
        'TP' => 'TP',
        'BEN' => 'BEN',
        'TPC' => 'TPC',
        'DS' => 'DS',
        'TDC' => 'TDC'
    ];

    return $typeMap[$type] ?? 'TD';
}
