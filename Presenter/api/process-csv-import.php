<?php
/**
 * Background CSV Import Processor
 * This script runs in the background to process CSV imports
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

require_once __DIR__ . '/../../Model/database.php';
require_once __DIR__ . '/../dashboard-secretary-presenter.php';

try {
    $db = Database::getInstance();

    // Update job status to processing
    updateJobStatus($db, $importId, 'processing', 0, 'Démarrage du traitement...');

    // Count total rows
    $handle = fopen($filepath, 'r');
    $totalRows = 0;
    fgetcsv($handle, 0, ';', '"', '\\'); // Skip header
    while (fgetcsv($handle, 0, ';', '"', '\\') !== false) {
        $totalRows++;
    }
    fclose($handle);

    // Update total
    $db->execute(
        "UPDATE import_jobs SET total_rows = :total WHERE id = :id",
        [':total' => $totalRows, ':id' => $importId]
    );

    // Process the CSV file using DataExtractor
    processCSVWithExtractor($db, $importId, $filepath, $totalRows);

    // Update job status to completed
    updateJobStatus($db, $importId, 'completed', $totalRows, 'Import terminé avec succès');

    // Log to history
    $presenter = new DashboardSecretaryPresenter();
    $presenter->logImportHistory(
        'Import CSV',
        "Fichier " . basename($filepath) . " importé avec succès ($totalRows lignes)",
        'success'
    );

} catch (Exception $e) {
    error_log("Import error: " . $e->getMessage());

    if (isset($db) && isset($importId)) {
        updateJobStatus($db, $importId, 'error', null, 'Erreur: ' . $e->getMessage());
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

function updateJobStatus($db, $importId, $status, $processedRows = null, $message = null)
{
    $updates = ["status = :status", "updated_at = NOW()"];
    $params = [':id' => $importId, ':status' => $status];

    if ($processedRows !== null) {
        $updates[] = "processed_rows = :processed_rows";
        $params[':processed_rows'] = $processedRows;
    }

    if ($message !== null) {
        $updates[] = "message = :message";
        $params[':message'] = $message;
    }

    $sql = "UPDATE import_jobs SET " . implode(', ', $updates) . " WHERE id = :id";
    $db->execute($sql, $params);
}

function processCSVWithExtractor($db, $importId, $filepath, $totalRows)
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
                processRowFromExtractor($db, $data);
                $processedCount++;

                // Update progress every 10 rows
                if ($processedCount % 10 === 0 || $processedCount === $totalRows) {
                    $message = "Traitement en cours: $processedCount/$totalRows lignes";
                    updateJobStatus($db, $importId, 'processing', $processedCount, $message);
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
 * Process a single row using the same logic as DataExtractor
 * This is a simplified version adapted from extract_datas.php
 */
function processRowFromExtractor($db, $data)
{
    // Skip if no identifier
    if (!isset($data['Identifiant']) || empty(trim($data['Identifiant']))) {
        return;
    }

    $identifier = trim($data['Identifiant']);

    // Process user
    $userId = processUser($db, $data, $identifier);

    // Process group
    $groupId = null;
    if (!empty($data['Groupes'] ?? '')) {
        $groupId = processGroup($db, $data);
        linkUserToGroup($db, $userId, $groupId);
    }

    // Process resource
    $resourceId = null;
    if (!empty($data['Identifiant matière'] ?? '')) {
        $resourceId = processResource($db, $data);
    }

    // Process room
    $roomId = null;
    if (!empty($data['Salles'] ?? '')) {
        $roomId = processRoom($db, $data);
    }

    // Process teacher
    $teacherId = null;
    if (!empty($data['Profs'] ?? '')) {
        $teacherId = processTeacher($db, $data);
    }

    // Process course slot
    $courseSlotId = null;
    if ($resourceId && !empty($data['Date'] ?? '')) {
        $courseSlotId = processCourseSlot($db, $data, $resourceId, $roomId, $teacherId, $groupId);
    }

    // Process absence if present
    if ($courseSlotId && isset($data['Absent/Présent']) && $data['Absent/Présent'] === 'Absence') {
        processAbsence($db, $data, $identifier, $courseSlotId);
    }
}

// Helper functions adapted from extract_datas.php
function processUser($db, $data, $identifier)
{
    $existing = $db->selectOne(
        "SELECT id FROM users WHERE identifier = :identifier",
        [':identifier' => $identifier]
    );

    if ($existing) {
        return $existing['id'];
    }

    $birthDate = parseDate($data['Date de naissance'] ?? '');
    $lastName = trim($data['Nom'] ?? 'User_' . $identifier);
    $firstName = trim($data['Prénom'] ?? 'Unknown');

    $sql = "INSERT INTO users (identifier, last_name, first_name, middle_name, birth_date, degrees, department, role) 
            VALUES (:identifier, :last_name, :first_name, :middle_name, :birth_date, :degrees, :department, 'student') 
            RETURNING id";

    $result = $db->selectOne($sql, [
        ':identifier' => $identifier,
        ':last_name' => $lastName,
        ':first_name' => $firstName,
        ':middle_name' => !empty($data['Prénom 2']) ? trim($data['Prénom 2']) : null,
        ':birth_date' => $birthDate,
        ':degrees' => !empty($data['Diplômes']) ? trim($data['Diplômes']) : null,
        ':department' => !empty($data['Composante']) ? trim($data['Composante']) : null
    ]);

    return $result['id'];
}

function processGroup($db, $data)
{
    $groupCode = trim($data['Groupes'] ?? '');

    if (empty($groupCode)) {
        return null;
    }

    $existing = $db->selectOne(
        "SELECT id FROM groups WHERE code = :code",
        [':code' => $groupCode]
    );

    if ($existing) {
        return $existing['id'];
    }

    $year = null;
    if (preg_match('/BUT(\d+)/', $groupCode, $matches)) {
        $year = (int) $matches[1];
    }

    $program = (strpos($groupCode, 'INFO') !== false) ? 'Informatique' : 'Unknown';

    $sql = "INSERT INTO groups (code, label, program, year) 
            VALUES (:code, :label, :program, :year) 
            RETURNING id";

    $result = $db->selectOne($sql, [
        ':code' => $groupCode,
        ':label' => $groupCode,
        ':program' => $program,
        ':year' => $year
    ]);

    return $result['id'];
}

function processResource($db, $data)
{
    $resourceCode = trim($data['Identifiant matière'] ?? '');

    if (empty($resourceCode)) {
        return null;
    }

    $existing = $db->selectOne(
        "SELECT id FROM resources WHERE code = :code",
        [':code' => $resourceCode]
    );

    if ($existing) {
        return $existing['id'];
    }

    $courseType = mapCourseType(trim($data['Type'] ?? ''));
    $label = trim($data['Matière'] ?? $resourceCode);

    $sql = "INSERT INTO resources (code, label, teaching_type) 
            VALUES (:code, :label, :teaching_type) 
            RETURNING id";

    $result = $db->selectOne($sql, [
        ':code' => $resourceCode,
        ':label' => $label,
        ':teaching_type' => $courseType
    ]);

    return $result['id'];
}

function processRoom($db, $data)
{
    $roomCode = trim($data['Salles'] ?? '');

    if (empty($roomCode)) {
        return null;
    }

    $existing = $db->selectOne(
        "SELECT id FROM rooms WHERE code = :code",
        [':code' => $roomCode]
    );

    if ($existing) {
        return $existing['id'];
    }

    $sql = "INSERT INTO rooms (code) VALUES (:code) RETURNING id";
    $result = $db->selectOne($sql, [':code' => $roomCode]);

    return $result['id'];
}

function processTeacher($db, $data)
{
    $teacherName = trim($data['Profs'] ?? '');

    if (empty($teacherName)) {
        return null;
    }

    $existing = $db->selectOne(
        "SELECT id FROM teachers WHERE CONCAT(last_name, ' ', first_name) = :name",
        [':name' => $teacherName]
    );

    if ($existing) {
        return $existing['id'];
    }

    $nameParts = explode(' ', $teacherName);
    $lastName = $nameParts[0];
    $firstName = isset($nameParts[1]) ? $nameParts[1] : '';

    $sql = "INSERT INTO teachers (last_name, first_name) 
            VALUES (:last_name, :first_name) 
            RETURNING id";

    $result = $db->selectOne($sql, [
        ':last_name' => $lastName,
        ':first_name' => $firstName
    ]);

    return $result['id'];
}

function processCourseSlot($db, $data, $resourceId, $roomId, $teacherId, $groupId)
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
    $existing = $db->selectOne(
        "SELECT id FROM course_slots 
         WHERE course_date = :date AND start_time = :start_time AND end_time = :end_time 
         AND resource_id = :resource_id AND room_id = :room_id AND teacher_id = :teacher_id AND group_id = :group_id",
        [
            ':date' => $courseDate,
            ':start_time' => $startTime,
            ':end_time' => $endDateTime->format('H:i:s'),
            ':resource_id' => $resourceId,
            ':room_id' => $roomId,
            ':teacher_id' => $teacherId,
            ':group_id' => $groupId
        ]
    );

    if ($existing) {
        return $existing['id'];
    }

    $sql = "INSERT INTO course_slots 
            (course_date, start_time, end_time, duration_minutes, course_type, 
             resource_id, room_id, teacher_id, group_id, is_evaluation, subject_identifier) 
            VALUES (:date, :start_time, :end_time, :duration, :course_type, 
                    :resource_id, :room_id, :teacher_id, :group_id, :is_evaluation, :subject_identifier)
            RETURNING id";

    $result = $db->selectOne($sql, [
        ':date' => $courseDate,
        ':start_time' => $startTime,
        ':end_time' => $endDateTime->format('H:i:s'),
        ':duration' => $durationMinutes,
        ':course_type' => $courseType,
        ':resource_id' => $resourceId,
        ':room_id' => $roomId,
        ':teacher_id' => $teacherId,
        ':group_id' => $groupId,
        ':is_evaluation' => $isEvaluation ? 'true' : 'false',
        ':subject_identifier' => trim($data['Identifiant matière'] ?? '')
    ]);

    return $result['id'];
}

function processAbsence($db, $data, $studentIdentifier, $courseSlotId)
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

    $existing = $db->selectOne(
        "SELECT id FROM absences WHERE student_identifier = :id AND course_slot_id = :slot",
        [':id' => $studentIdentifier, ':slot' => $courseSlotId]
    );

    if ($existing) {
        return;
    }

    $sql = "INSERT INTO absences (student_identifier, course_slot_id, status, justified) 
            VALUES (:student_identifier, :course_slot_id, :status, :justified)";

    $db->execute($sql, [
        ':student_identifier' => $studentIdentifier,
        ':course_slot_id' => $courseSlotId,
        ':status' => $status,
        ':justified' => $justified ? 'true' : 'false'
    ]);
}

function linkUserToGroup($db, $userId, $groupId)
{
    if (!$userId || !$groupId) {
        return;
    }

    $existing = $db->selectOne(
        "SELECT 1 FROM user_groups WHERE user_id = :user_id AND group_id = :group_id",
        [':user_id' => $userId, ':group_id' => $groupId]
    );

    if ($existing) {
        return;
    }

    $sql = "INSERT INTO user_groups (user_id, group_id) VALUES (:user_id, :group_id)";
    $db->execute($sql, [':user_id' => $userId, ':group_id' => $groupId]);
}

// Parsing helper functions from extract_datas.php
function parseDate($dateString, $format = 'Y-m-d')
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

function parseTime($timeString)
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

function parseDuration($durationString)
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

function mapCourseType($type)
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
