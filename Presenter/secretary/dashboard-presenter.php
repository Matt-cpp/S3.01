<?php

declare(strict_types=1);

/**
 * Secretary dashboard presenter.
 * Handles the business logic of the secretary dashboard.
 *
 * Main features:
 * - Student search with name/identifier filtering
 * - Resource (subject) search and creation
 * - Room search and creation
 * - Manual absence entry with course slot creation
 * - Import and action history management
 */

require_once __DIR__ . '/../../Model/database.php';

class DashboardSecretaryPresenter
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // Retrieves the list of students matching a search query by name or identifier
    public function searchStudents(string $query): array
    {
        $sql = "SELECT id, identifier, first_name, last_name, email 
                FROM users 
                WHERE role = 'student' 
                AND (first_name ILIKE :query OR last_name ILIKE :query OR identifier ILIKE :query)
                ORDER BY last_name, first_name
                LIMIT 20";

        return $this->db->select($sql, [':query' => "%$query%"]);
    }

    // Retrieves the list of resources matching a search query by code or label
    public function searchResources(string $query): array
    {
        $sql = "SELECT id, code, label, teaching_type 
                FROM resources 
                WHERE code ILIKE :query OR label ILIKE :query
                ORDER BY label
                LIMIT 20";

        return $this->db->select($sql, [':query' => "%$query%"]);
    }

    // Retrieves the list of rooms matching a search query by name
    public function searchRooms(string $query): array
    {
        $sql = "SELECT id, code 
                FROM rooms 
                WHERE code ILIKE :query
                ORDER BY code
                LIMIT 20";

        return $this->db->select($sql, [':query' => "%$query%"]);
    }

    // Creates a new resource in the database
    public function createResource(string $code): array
    {
        // Check if the resource already exists
        $existing = $this->db->selectOne(
            "SELECT id FROM resources WHERE code = :code",
            [':code' => $code]
        );

        if ($existing) {
            throw new Exception("Une matière avec ce code existe déjà");
        }

        $sql = "INSERT INTO resources (code, label) 
                VALUES (:code, :label) 
                RETURNING id, code, label, teaching_type";

        $result = $this->db->selectOne($sql, [
            ':code' => $code,
            ':label' => $code  // Use the code as label
        ]);

        return $result;
    }

    // Creates a new room in the database
    public function createRoom(string $code): array
    {
        // Check if the room already exists
        $existing = $this->db->selectOne(
            "SELECT id FROM rooms WHERE code = :code",
            [':code' => $code]
        );

        if ($existing) {
            throw new Exception("Une salle avec ce code existe déjà");
        }

        $sql = "INSERT INTO rooms (code) 
                VALUES (:code) 
                RETURNING id, code";

        $result = $this->db->selectOne($sql, [':code' => $code]);

        return $result;
    }

    // Creates an absence manually with course slot creation
    public function createManualAbsence(array $data): int
    {
        try {
            $this->db->beginTransaction();

            // Retrieve the student identifier
            $student = $this->db->selectOne(
                "SELECT identifier FROM users WHERE id = :id",
                [':id' => $data['student_id']]
            );

            if (!$student) {
                throw new Exception("Étudiant non trouvé");
            }

            // Get start and end times
            $startTime = trim($data['start_time']);
            $endTime = trim($data['end_time']);

            // Calculate duration in minutes
            $timezone = new DateTimeZone('Europe/Paris');
            $start = new DateTime($startTime, $timezone);
            $end = new DateTime($endTime, $timezone);
            $interval = $start->diff($end);
            $duration = ($interval->h * 60) + $interval->i;

            // Create the course slot in the database
            $courseSlotSql = "INSERT INTO course_slots 
                (course_date, start_time, end_time, duration_minutes, course_type, 
                 resource_id, room_id, is_evaluation) 
                VALUES (:date, :start_time, :end_time, :duration, :course_type, 
                        :resource_id, :room_id, :is_evaluation)
                RETURNING id";

            $courseSlotResult = $this->db->selectOne($courseSlotSql, [
                ':date' => $data['absence_date'],
                ':start_time' => $startTime,
                ':end_time' => $endTime,
                ':duration' => $duration,
                ':course_type' => $data['course_type'],
                ':resource_id' => $data['resource_id'],
                ':room_id' => $data['room_id'],
                ':is_evaluation' => isset($data['is_evaluation']) ? 'true' : 'false'
            ]);

            $courseSlotId = $courseSlotResult['id'];

            // Create the absence in the database
            $absenceSql = "INSERT INTO absences 
                (student_identifier, course_slot_id, status, justified) 
                VALUES (:student_identifier, :course_slot_id, 'absent', false)
                RETURNING id";

            $absenceResult = $this->db->selectOne($absenceSql, [
                ':student_identifier' => $student['identifier'],
                ':course_slot_id' => $courseSlotId
            ]);

            $this->db->commit();

            // Log the action to history
            $this->logImportHistory(
                'Saisie manuelle',
                "Absence créée pour {$student['identifier']} le {$data['absence_date']} ({$startTime} - {$endTime})",
                'success'
            );

            return $absenceResult['id'];
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // Logs an action to the import history
    public function logImportHistory(string $action, string $details, string $status = 'success'): void
    {
        $sql = "INSERT INTO import_history (action_type, description, status, created_at) 
                VALUES (:action, :details, :status, NOW())";

        try {
            $this->db->execute($sql, [
                ':action' => $action,
                ':details' => $details,
                ':status' => $status
            ]);
        } catch (Exception $e) {
            // Create the table if it doesn't exist yet
            $this->createImportHistoryTable();
            // Retry the insertion
            $this->db->execute($sql, [
                ':action' => $action,
                ':details' => $details,
                ':status' => $status
            ]);
        }
    }

    // Retrieves the import and recent actions history
    public function getImportHistory(int $limit = 50): array
    {
        $sql = "SELECT action_type as action, description as details, status, created_at 
                FROM import_history 
                ORDER BY created_at DESC 
                LIMIT :limit";

        try {
            return $this->db->select($sql, [':limit' => $limit]);
        } catch (Exception $e) {
            // The table may not exist yet
            $this->createImportHistoryTable();
            return [];
        }
    }

    // Creates the import history table if it doesn't exist
    private function createImportHistoryTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS import_history (
            id SERIAL PRIMARY KEY,
            action VARCHAR(255) NOT NULL,
            details TEXT,
            status VARCHAR(50) DEFAULT 'success',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";

        $this->db->getConnection()->exec($sql);
    }
}
