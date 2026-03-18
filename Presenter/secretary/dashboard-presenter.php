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
require_once __DIR__ . '/../../Model/UserModel.php';
require_once __DIR__ . '/../../Model/ResourceModel.php';
require_once __DIR__ . '/../../Model/ImportModel.php';
require_once __DIR__ . '/../../Model/AbsenceModel.php';

class DashboardSecretaryPresenter
{
    private Database $db;
    private UserModel $userModel;
    private ResourceModel $resourceModel;
    private ImportModel $importModel;
    private AbsenceModel $absenceModel;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->userModel = new UserModel($this->db);
        $this->resourceModel = new ResourceModel();
        $this->importModel = new ImportModel();
        $this->absenceModel = new AbsenceModel();
    }

    // Retrieves the list of students matching a search query by name or identifier
    public function searchStudents(string $query): array
    {
        return $this->userModel->searchStudents($query);
    }

    // Retrieves the list of resources matching a search query by code or label
    public function searchResources(string $query): array
    {
        return $this->resourceModel->searchResources($query);
    }

    // Retrieves the list of rooms matching a search query by name
    public function searchRooms(string $query): array
    {
        return $this->resourceModel->searchRooms($query);
    }

    // Creates a new resource in the database
    public function createResource(string $code): array
    {
        if ($this->resourceModel->resourceExists($code)) {
            throw new Exception("Une matière avec ce code existe déjà");
        }

        $this->resourceModel->createResource($code, $code);
        $resources = $this->resourceModel->searchResources($code);
        return $resources[0] ?? [];
    }

    // Creates a new room in the database
    public function createRoom(string $code): array
    {
        if ($this->resourceModel->roomExists($code)) {
            throw new Exception("Une salle avec ce code existe déjà");
        }

        $this->resourceModel->createRoom($code);
        $rooms = $this->resourceModel->searchRooms($code);
        return $rooms[0] ?? [];
    }

    // Creates an absence manually with course slot creation
    public function createManualAbsence(array $data): int
    {
        try {
            $this->db->beginTransaction();

            // Retrieve the student identifier
            $student = $this->userModel->getUserById((int) $data['student_id']);

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

            $data['start_time'] = $startTime;
            $data['end_time'] = $endTime;
            $data['duration_minutes'] = $duration;

            $courseSlotId = $this->absenceModel->createCourseSlot($data);
            if (!$courseSlotId) {
                throw new Exception('Impossible de créer le créneau de cours');
            }

            $absenceId = $this->absenceModel->createAbsence($student['identifier'], $courseSlotId);
            if (!$absenceId) {
                throw new Exception('Impossible de créer l\'absence');
            }

            $this->db->commit();

            // Log the action to history
            $this->logImportHistory(
                'Saisie manuelle',
                "Absence créée pour {$student['identifier']} le {$data['absence_date']} ({$startTime} - {$endTime})",
                'success'
            );

            return $absenceId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // Logs an action to the import history
    public function logImportHistory(string $action, string $details, string $status = 'success'): void
    {
        try {
            $this->importModel->logAction($action, $details, $status);
        } catch (Exception $e) {
            $this->importModel->ensureTable();
            $this->importModel->logAction($action, $details, $status);
        }
    }

    // Retrieves the import and recent actions history
    public function getImportHistory(int $limit = 50): array
    {
        try {
            return $this->importModel->getHistory($limit);
        } catch (Exception $e) {
            $this->importModel->ensureTable();
            return [];
        }
    }

    // Compatibility wrapper for legacy calls
    private function createImportHistoryTable(): void
    {
        $this->importModel->ensureTable();
    }
}
