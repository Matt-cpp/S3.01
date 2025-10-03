<?php

require_once __DIR__ . '/database.php';

class DataExtractor
{
    private $db;
    private $csvDirectory;
    private $processedUsers = [];
    private $processedTeachers = [];
    private $processedRooms = [];
    private $processedResources = [];
    private $processedGroups = [];

    public function __construct($csvDirectory = 'export_vt/')
    {
        $this->db = Database::getInstance();
        $this->csvDirectory = $csvDirectory;
    }

    /**
     * Main extraction method
     */
    public function extractAllData()
    {
        try {
            $this->db->beginTransaction();

            echo "Starting data extraction from CSV files...\n";

            // Get all CSV files
            $csvFiles = glob($this->csvDirectory . '*.CSV');

            if (empty($csvFiles)) {
                throw new Exception("No CSV files found in directory: " . $this->csvDirectory);
            }

            foreach ($csvFiles as $csvFile) {
                echo "Processing file: " . basename($csvFile) . "\n";
                $this->processCsvFile($csvFile);
            }

            $this->db->commit();
            echo "Data extraction completed successfully!\n";

            // Print statistics
            $this->printStatistics();

        } catch (Exception $e) {
            $this->db->rollBack();
            echo "Error during extraction: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    /**
     * Process individual CSV file
     */
    private function processCsvFile($csvFile)
    {
        $handle = fopen($csvFile, 'r');
        if (!$handle) {
            throw new Exception("Cannot open CSV file: $csvFile");
        }

        // Read header
        $header = fgetcsv($handle, 0, ';', '"', '\\');
        if (!$header) {
            throw new Exception("Cannot read CSV header from: $csvFile");
        }

        // Clean BOM from header fields (especially the first one)
        $header = array_map(function ($field) {
            // Remove UTF-8 BOM if present
            return str_replace("\xEF\xBB\xBF", '', $field);
        }, $header);

        $rowCount = 0;
        while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
            if (count($row) !== count($header)) {
                continue; // Skip malformed rows
            }

            $data = array_combine($header, $row);
            $this->processRow($data);
            $rowCount++;
        }

        fclose($handle);
        echo "Processed $rowCount rows from " . basename($csvFile) . "\n";
    }

    /**
     * Process individual row of data
     */
    private function processRow($data)
    {
        // Debug: print the keys for the first few rows
        static $debugCount = 0;
        if ($debugCount < 3) {
            echo "Row keys: " . implode(', ', array_keys($data)) . "\n";
            echo "Row data sample: Identifiant=" . ($data['Identifiant'] ?? 'MISSING') . "\n";
            echo "Row data: Motif absence='" . ($data['Motif absence'] ?? 'MISSING') . "' Commentaire='" . ($data['Commentaire'] ?? 'MISSING') . "'\n";
            echo "Row data: Contrôle='" . ($data['Contrôle'] ?? 'MISSING') . "'\n";
            $debugCount++;
        }

        // Only validate the most essential field - Identifiant
        if (!isset($data['Identifiant']) || empty(trim($data['Identifiant']))) {
            echo "Skipping row with missing Identifiant\n";
            return;
        }

        try {
            // Extract and process entities
            echo "Processing user...\n";
            $userId = $this->processUser($data);
            echo "User ID: $userId\n";

            echo "Processing group...\n";
            $groupId = $this->processGroup($data);
            echo "Group ID: " . ($groupId ?? 'NULL') . "\n";

            echo "Processing resource...\n";
            $resourceId = $this->processResource($data);
            echo "Resource ID: " . ($resourceId ?? 'NULL') . "\n";

            echo "Processing room...\n";
            $roomId = $this->processRoom($data);
            echo "Room ID: " . ($roomId ?? 'NULL') . "\n";

            echo "Processing teacher...\n";
            $teacherId = $this->processTeacher($data);
            echo "Teacher ID: " . ($teacherId ?? 'NULL') . "\n";

            echo "Processing course slot...\n";
            $courseSlotId = $this->processCourseSlot($data, $resourceId, $roomId, $teacherId, $groupId);
            echo "Course Slot ID: " . ($courseSlotId ?? 'NULL') . "\n";

            // Link user to group
            echo "Linking user to group...\n";
            $this->linkUserToGroup($userId, $groupId);

            // Process absence if present
            if (isset($data['Absent/Présent']) && $data['Absent/Présent'] === 'Absence') {
                echo "Processing absence...\n";
                $this->processAbsence($data, $userId, $courseSlotId);
            }
            echo "Row processed successfully!\n";
        } catch (Exception $e) {
            echo "Error processing row with Identifiant " . ($data['Identifiant'] ?? 'UNKNOWN') . ": " . $e->getMessage() . "\n";
            echo "Row data dump: " . print_r($data, true) . "\n";
            throw $e; // Re-throw to stop execution and show the full error
        }
    }

    /**
     * Process user data
     */
    private function processUser($data)
    {
        $identifier = trim($data['Identifiant']);

        if (isset($this->processedUsers[$identifier])) {
            return $this->processedUsers[$identifier];
        }

        // Check if user already exists
        $existingUser = $this->db->selectOne(
            "SELECT id FROM users WHERE identifier = ?",
            [$identifier]
        );

        if ($existingUser) {
            $this->processedUsers[$identifier] = $existingUser['id'];
            return $existingUser['id'];
        }

        // Create new user with fallback values for missing fields
        $birthDate = $this->parseDate($data['Date de naissance'] ?? '');
        $lastName = trim($data['Nom'] ?? '');
        $firstName = trim($data['Prénom'] ?? '');

        // Use identifier as name fallback if names are empty
        if (empty($lastName)) {
            $lastName = 'User_' . $identifier;
        }
        if (empty($firstName)) {
            $firstName = 'Unknown';
        }

        $this->db->execute(
            "INSERT INTO users (identifier, last_name, first_name, middle_name, birth_date, degrees, department, role) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $identifier,
                $lastName,
                $firstName,
                !empty($data['Prénom 2']) ? trim($data['Prénom 2']) : null,
                $birthDate,
                !empty($data['Diplômes']) ? trim($data['Diplômes']) : null,
                !empty($data['Composante']) ? trim($data['Composante']) : null,
                'student'
            ]
        );

        $userId = $this->db->lastInsertId();
        $this->processedUsers[$identifier] = $userId;

        return $userId;
    }

    /**
     * Process group data
     */
    private function processGroup($data)
    {
        $groupCode = trim($data['Groupes'] ?? '');

        if (empty($groupCode) || isset($this->processedGroups[$groupCode])) {
            return $this->processedGroups[$groupCode] ?? null;
        }

        // Check if group already exists
        $existingGroup = $this->db->selectOne(
            "SELECT id FROM groups WHERE code = ?",
            [$groupCode]
        );

        if ($existingGroup) {
            $this->processedGroups[$groupCode] = $existingGroup['id'];
            return $existingGroup['id'];
        }

        // Extract year from group code (e.g., BUT1, BUT2, BUT3)
        $year = null;
        if (preg_match('/BUT(\d+)/', $groupCode, $matches)) {
            $year = (int) $matches[1];
        }

        // Create new group
        $this->db->execute(
            "INSERT INTO groups (code, label, program, year) VALUES (?, ?, ?, ?)",
            [
                $groupCode,
                $groupCode, // Use code as label for now
                $this->extractProgram($groupCode),
                $year
            ]
        );

        $groupId = $this->db->lastInsertId();
        $this->processedGroups[$groupCode] = $groupId;

        return $groupId;
    }

    /**
     * Process resource/subject data
     */
    private function processResource($data)
    {
        $resourceCode = trim($data['Identifiant matière'] ?? '');

        if (empty($resourceCode)) {
            return null;
        }

        if (isset($this->processedResources[$resourceCode])) {
            return $this->processedResources[$resourceCode];
        }

        // Check if resource already exists
        $existingResource = $this->db->selectOne(
            "SELECT id FROM resources WHERE code = ?",
            [$resourceCode]
        );

        if ($existingResource) {
            $this->processedResources[$resourceCode] = $existingResource['id'];
            return $existingResource['id'];
        }

        // Create new resource
        $courseType = $this->mapCourseType(trim($data['Type'] ?? ''));

        $this->db->execute(
            "INSERT INTO resources (code, label, teaching_type) VALUES (?, ?, ?)",
            [
                $resourceCode,
                trim($data['Matière'] ?? ''),
                $courseType
            ]
        );

        $resourceId = $this->db->lastInsertId();
        $this->processedResources[$resourceCode] = $resourceId;

        return $resourceId;
    }

    /**
     * Process room data
     */
    private function processRoom($data)
    {
        $roomCode = trim($data['Salles'] ?? '');

        if (empty($roomCode)) {
            return null;
        }

        if (isset($this->processedRooms[$roomCode])) {
            return $this->processedRooms[$roomCode];
        }

        // Check if room already exists
        $existingRoom = $this->db->selectOne(
            "SELECT id FROM rooms WHERE code = ?",
            [$roomCode]
        );

        if ($existingRoom) {
            $this->processedRooms[$roomCode] = $existingRoom['id'];
            return $existingRoom['id'];
        }

        // Create new room
        $this->db->execute(
            "INSERT INTO rooms (code) VALUES (?)",
            [$roomCode]
        );

        $roomId = $this->db->lastInsertId();
        $this->processedRooms[$roomCode] = $roomId;

        return $roomId;
    }

    /**
     * Process teacher data
     */
    private function processTeacher($data)
    {
        $teacherName = trim($data['Profs'] ?? '');

        if (empty($teacherName)) {
            return null;
        }

        if (isset($this->processedTeachers[$teacherName])) {
            return $this->processedTeachers[$teacherName];
        }

        // Check if teacher already exists
        $existingTeacher = $this->db->selectOne(
            "SELECT id FROM teachers WHERE CONCAT(last_name, ' ', first_name) = ?",
            [$teacherName]
        );

        if ($existingTeacher) {
            $this->processedTeachers[$teacherName] = $existingTeacher['id'];
            return $existingTeacher['id'];
        }

        // Parse teacher name (assuming "LASTNAME FIRSTNAME" format)
        $nameParts = explode(' ', $teacherName);
        $lastName = $nameParts[0];
        $firstName = isset($nameParts[1]) ? $nameParts[1] : '';

        // Create new teacher
        $this->db->execute(
            "INSERT INTO teachers (last_name, first_name) VALUES (?, ?)",
            [$lastName, $firstName]
        );

        $teacherId = $this->db->lastInsertId();
        $this->processedTeachers[$teacherName] = $teacherId;

        return $teacherId;
    }

    /**
     * Process course slot data
     */
    private function processCourseSlot($data, $resourceId, $roomId, $teacherId, $groupId)
    {
        $courseDate = $this->parseDate($data['Date'] ?? '', 'd/m/Y');
        $startTime = $this->parseTime($data['Heure'] ?? '');
        $duration = $this->parseDuration($data['Durée'] ?? '');

        if (!$courseDate || !$startTime || !$duration) {
            return null;
        }

        // Calculate end time
        $startDateTime = new DateTime($courseDate . ' ' . $startTime);
        $endDateTime = clone $startDateTime;
        $endDateTime->add(new DateInterval('PT' . $duration . 'M'));

        $courseType = $this->mapCourseType(trim($data['Type'] ?? ''));
        $controleValue = trim($data['Contrôle'] ?? '');
        $isEvaluation = (!empty($controleValue) && $controleValue === 'Oui');

        // Check if course slot already exists
        $existingSlot = $this->db->selectOne(
            "SELECT id FROM course_slots 
             WHERE course_date = ? AND start_time = ? AND end_time = ? 
             AND resource_id = ? AND room_id = ? AND teacher_id = ? AND group_id = ?",
            [
                $courseDate,
                $startTime,
                $endDateTime->format('H:i:s'),
                $resourceId,
                $roomId,
                $teacherId,
                $groupId
            ]
        );

        if ($existingSlot) {
            return $existingSlot['id'];
        }

        // Create new course slot
        $this->db->execute(
            "INSERT INTO course_slots (course_date, start_time, end_time, duration_minutes, course_type, resource_id, room_id, teacher_id, group_id, is_evaluation, subject_identifier) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $courseDate,
                $startTime,
                $endDateTime->format('H:i:s'),
                $duration,
                $courseType,
                $resourceId,
                $roomId,
                $teacherId,
                $groupId,
                $isEvaluation ? 'true' : 'false', // Convert boolean to string for PostgreSQL
                trim($data['Identifiant matière'] ?? '')
            ]
        );

        return $this->db->lastInsertId();
    }

    /**
     * Process absence data
     */
    private function processAbsence($data, $userId, $courseSlotId)
    {
        if (!$courseSlotId) {
            return;
        }

        $studentIdentifier = trim($data['Identifiant']);
        $justificationText = trim($data['Justification'] ?? '');
        $justified = (!empty($justificationText) && $justificationText !== 'Non justifié');

        // Map justification status
        $status = 'absent';
        if ($justificationText === 'Absence justifiée') {
            $status = 'excused';
        }

        // Check if absence already exists
        $existingAbsence = $this->db->selectOne(
            "SELECT id FROM absences WHERE student_identifier = ? AND course_slot_id = ?",
            [$studentIdentifier, $courseSlotId]
        );

        if ($existingAbsence) {
            return;
        }

        // Create new absence
        $this->db->execute(
            "INSERT INTO absences (student_identifier, course_slot_id, status, justified) 
             VALUES (?, ?, ?, ?)",
            [
                $studentIdentifier,
                $courseSlotId,
                $status,
                $justified ? 'true' : 'false' // Convert boolean to string for PostgreSQL
            ]
        );
    }

    /**
     * Link user to group
     */
    private function linkUserToGroup($userId, $groupId)
    {
        if (!$userId || !$groupId) {
            return;
        }

        // Check if link already exists
        $existingLink = $this->db->selectOne(
            "SELECT 1 FROM user_groups WHERE user_id = ? AND group_id = ?",
            [$userId, $groupId]
        );

        if ($existingLink) {
            return;
        }

        // Create new link
        $this->db->execute(
            "INSERT INTO user_groups (user_id, group_id) VALUES (?, ?)",
            [$userId, $groupId]
        );
    }

    /**
     * Helper methods
     */
    private function parseDate($dateString, $format = 'Y-m-d')
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

    private function parseTime($timeString)
    {
        if (empty($timeString)) {
            return null;
        }

        // Convert "15H30" to "15:30:00"
        $timeString = str_replace('H', ':', $timeString);
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $timeString, $matches)) {
            return sprintf('%02d:%02d:00', $matches[1], $matches[2]);
        }

        return null;
    }

    private function parseDuration($durationString)
    {
        if (empty($durationString)) {
            return null;
        }

        // Convert "1H30" to minutes
        if (preg_match('/^(\d+)H(\d+)$/', $durationString, $matches)) {
            return ($matches[1] * 60) + $matches[2];
        } elseif (preg_match('/^(\d+)H$/', $durationString, $matches)) {
            return $matches[1] * 60;
        }

        return null;
    }

    private function mapCourseType($type)
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

    private function extractProgram($groupCode)
    {
        if (strpos($groupCode, 'INFO') !== false) {
            return 'Informatique';
        }
        return 'Unknown';
    }

    /**
     * Print extraction statistics
     */
    private function printStatistics()
    {
        echo "\n=== EXTRACTION STATISTICS ===\n";
        echo "Users processed: " . count($this->processedUsers) . "\n";
        echo "Groups processed: " . count($this->processedGroups) . "\n";
        echo "Resources processed: " . count($this->processedResources) . "\n";
        echo "Rooms processed: " . count($this->processedRooms) . "\n";
        echo "Teachers processed: " . count($this->processedTeachers) . "\n";

        // Get database counts
        $userCount = $this->db->selectOne("SELECT COUNT(*) as count FROM users")['count'];
        $absenceCount = $this->db->selectOne("SELECT COUNT(*) as count FROM absences")['count'];
        $courseSlotCount = $this->db->selectOne("SELECT COUNT(*) as count FROM course_slots")['count'];

        echo "Total users in database: $userCount\n";
        echo "Total course slots in database: $courseSlotCount\n";
        echo "Total absences in database: $absenceCount\n";
        echo "===============================\n";
    }
}

// Usage example
if (php_sapi_name() === 'cli') {
    try {
        $extractor = new DataExtractor();
        $extractor->extractAllData();
    } catch (Exception $e) {
        echo "Extraction failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}
