<?php

declare(strict_types=1);

/**
 * Absence model - Manages absence data in the database.
 * Provides methods to retrieve absences with filters (name, date, status, course type).
 * Allows retrieving available course types and user information.
 * Primarily used by the absence history page.
 */

require_once __DIR__ . '/database.php';

class AbsenceModel
{
    private Database $db;

    public function __construct()
    {
        $this->db = getDatabase();
    }

    // Retrieve all absences with optional filters
    public function getAllAbsences(array $filters = []): array
    {
        $query = "
            SELECT
                a.id as absence_id,
                CONCAT(u.first_name, ' ', u.last_name) as student_name,
                u.identifier as student_identifier,
                COALESCE(r.label, 'Non spécifié') as course,
                cs.course_date as date,
                cs.start_time::text as start_time,
                cs.end_time::text as end_time,
                cs.course_type,
                a.justified as status,
                p.main_reason as motif,
                p.file_path as file_path,
                p.proof_files as proof_files,
                p.id as proof_id,
                p.status as justification_status
            FROM absences a
            JOIN users u ON a.student_identifier = u.identifier
            JOIN course_slots cs ON a.course_slot_id = cs.id
            LEFT JOIN resources r ON cs.resource_id = r.id
            LEFT JOIN proof_absences pa ON a.id = pa.absence_id
            LEFT JOIN proof p ON pa.proof_id = p.id
            WHERE 1=1
        ";

        $params = [];
        $conditions = [];

        if (!empty($filters['name'])) {
            $conditions[] = "(u.first_name ILIKE :name OR u.last_name ILIKE :name)";
            $params[':name'] = '%' . $filters['name'] . '%';
        }

        if (!empty($filters['start_date'])) {
            $conditions[] = "cs.course_date >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $conditions[] = "cs.course_date <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }

        if (!empty($filters['JustificationStatus'])) {
            if ($filters['JustificationStatus'] === 'En attente') {
                $conditions[] = "p.status = 'pending'";
            } elseif ($filters['JustificationStatus'] === 'Acceptée') {
                $conditions[] = "p.status = 'accepted'";
            } elseif ($filters['JustificationStatus'] === 'Rejetée') {
                $conditions[] = "p.status = 'rejected'";
            } elseif ($filters['JustificationStatus'] === 'En cours d\'examen') {
                $conditions[] = "p.status = 'under_review'";
            } elseif ($filters['JustificationStatus'] === 'Non justifiée') {
                $conditions[] = "p.status IS NULL";
            }
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'justifiée') {
                $conditions[] = "a.justified = true";
            } elseif ($filters['status'] === 'non_justifiée') {
                $conditions[] = "a.justified = false";
            }
        }

        if (!empty($filters['course_type'])) {
            $conditions[] = "cs.course_type = :course_type";
            $params[':course_type'] = $filters['course_type'];
        }

        if (!empty($conditions)) {
            $query .= " AND " . implode(" AND ", $conditions);
        }

        $query .= " ORDER BY cs.course_date DESC, cs.start_time DESC, a.id";

        try {
            return $this->db->select($query, $params);
        } catch (Exception $e) {
            error_log('Error retrieving absences: ' . $e->getMessage());
            return [];
        }
    }


    // Retrieve all available course types
    public function getCourseTypes(): array
    {
        $query = "SELECT DISTINCT course_type FROM course_slots WHERE course_type IS NOT NULL ORDER BY course_type";
        try {
            return $this->db->select($query);
        } catch (Exception $e) {
            error_log('Error retrieving course types: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Retrieve a student's full absence list with all related data (course, teacher, room, proof, makeup).
     * Supports optional filters: start_date, end_date, status (proof status), course_type.
     */
    public function getStudentAbsencesDetailed(string $studentIdentifier, array $filters = []): array
    {
        $query = "
            SELECT
                a.id as absence_id,
                cs.course_date,
                cs.start_time,
                cs.end_time,
                cs.duration_minutes,
                cs.course_type,
                cs.is_evaluation,
                a.justified,
                r.code as course_code,
                r.label as course_name,
                t.first_name as teacher_first_name,
                t.last_name as teacher_last_name,
                rm.code as room_name,
                p.id as proof_id,
                p.main_reason as motif,
                p.custom_reason as custom_motif,
                p.file_path as file_path,
                p.status as proof_status,
                p.manager_comment,
                m.id as makeup_id,
                m.scheduled as makeup_scheduled,
                m.makeup_date as makeup_date,
                m.comment as makeup_comment,
                m.duration_minutes as makeup_duration,
                makeup_rm.code as makeup_room,
                makeup_cs.start_time as makeup_start_time,
                makeup_cs.end_time as makeup_end_time,
                makeup_r.label as makeup_resource_label
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            LEFT JOIN resources r ON cs.resource_id = r.id
            LEFT JOIN teachers t ON cs.teacher_id = t.id
            LEFT JOIN rooms rm ON cs.room_id = rm.id
            LEFT JOIN proof_absences pa ON a.id = pa.absence_id
            LEFT JOIN proof p ON pa.proof_id = p.id
            LEFT JOIN makeups m ON a.id = m.absence_id
            LEFT JOIN rooms makeup_rm ON m.room_id = makeup_rm.id
            LEFT JOIN course_slots makeup_cs ON m.evaluation_slot_id = makeup_cs.id
            LEFT JOIN resources makeup_r ON makeup_cs.resource_id = makeup_r.id
            WHERE a.student_identifier = :student_id
        ";

        $params = [':student_id' => $studentIdentifier];

        if (!empty($filters['start_date'])) {
            $query .= " AND cs.course_date >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $query .= " AND cs.course_date <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'justifiée') {
                $query .= " AND p.status = 'accepted'";
            } elseif ($filters['status'] === 'en_attente') {
                $query .= " AND p.status = 'pending'";
            } elseif ($filters['status'] === 'en_revision') {
                $query .= " AND p.status = 'under_review'";
            } elseif ($filters['status'] === 'refusé') {
                $query .= " AND p.status = 'rejected'";
            } elseif ($filters['status'] === 'non_justifiée') {
                $query .= " AND (p.id IS NULL OR p.status IS NULL)";
            }
        }

        if (!empty($filters['course_type'])) {
            $query .= " AND cs.course_type = :course_type";
            $params[':course_type'] = $filters['course_type'];
        }

        $query .= " ORDER BY cs.course_date DESC, cs.start_time DESC";

        try {
            return $this->db->select($query, $params);
        } catch (Exception $e) {
            error_log('Error retrieving student absences: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Retrieve unjustified absences for a student within a datetime range for proof submission.
     * In edit mode (proofId provided), also includes absences already linked to that proof.
     */
    public function getAbsencesForProofSubmission(
        string $studentIdentifier,
        string $datetimeStart,
        string $datetimeEnd,
        ?int $proofId = null
    ): array {
        if ($proofId !== null) {
            $sql = "SELECT DISTINCT
                cs.course_date,
                cs.start_time,
                cs.end_time,
                cs.course_type,
                cs.is_evaluation,
                r.label as resource_label,
                r.code as resource_code,
                t.last_name as teacher_last_name,
                t.first_name as teacher_first_name,
                rm.code as room_label,
                a.id as absence_id,
                cs.id as course_slot_id
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            LEFT JOIN resources r ON cs.resource_id = r.id
            LEFT JOIN teachers t ON cs.teacher_id = t.id
            LEFT JOIN rooms rm ON cs.room_id = rm.id
            WHERE a.student_identifier = :student_identifier
                AND a.justified = FALSE
                AND a.status = 'absent'
                AND cs.course_date + cs.start_time >= :datetime_start::timestamp
                AND cs.course_date + cs.start_time < :datetime_end::timestamp
                AND (
                    NOT EXISTS (SELECT 1 FROM proof_absences pa WHERE pa.absence_id = a.id)
                    OR EXISTS (SELECT 1 FROM proof_absences pa WHERE pa.absence_id = a.id AND pa.proof_id = :proof_id)
                )
            ORDER BY cs.course_date, cs.start_time";

            $params = [
                'student_identifier' => $studentIdentifier,
                'datetime_start' => $datetimeStart,
                'datetime_end' => $datetimeEnd,
                'proof_id' => $proofId,
            ];
        } else {
            $sql = "SELECT DISTINCT
                cs.course_date,
                cs.start_time,
                cs.end_time,
                cs.course_type,
                cs.is_evaluation,
                r.label as resource_label,
                r.code as resource_code,
                t.last_name as teacher_last_name,
                t.first_name as teacher_first_name,
                rm.code as room_label,
                a.id as absence_id,
                cs.id as course_slot_id
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            LEFT JOIN resources r ON cs.resource_id = r.id
            LEFT JOIN teachers t ON cs.teacher_id = t.id
            LEFT JOIN rooms rm ON cs.room_id = rm.id
            WHERE a.student_identifier = :student_identifier
                AND a.justified = FALSE
                AND a.status = 'absent'
                AND cs.course_date + cs.start_time >= :datetime_start::timestamp
                AND cs.course_date + cs.start_time < :datetime_end::timestamp 
                AND NOT EXISTS (SELECT 1 FROM proof_absences pa WHERE pa.absence_id = a.id)
            ORDER BY cs.course_date, cs.start_time";

            $params = [
                'student_identifier' => $studentIdentifier,
                'datetime_start' => $datetimeStart,
                'datetime_end' => $datetimeEnd,
            ];
        }

        try {
            return $this->db->select($sql, $params);
        } catch (Exception $e) {
            error_log('Error retrieving absences for proof submission: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Return the last unjustified absence (not linked to any proof) for a student.
     * Used to calculate the proof submission deadline.
     */
    public function getLastUnjustifiedAbsence(string $studentIdentifier): ?array
    {
        $sql = "
            SELECT
                cs.course_date,
                cs.end_time,
                (cs.course_date + cs.end_time)::timestamp as last_absence_datetime
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            LEFT JOIN proof_absences pa ON a.id = pa.absence_id
            WHERE a.student_identifier = :student_identifier
                AND a.status = 'absent'
                AND a.justified = FALSE
                AND pa.absence_id IS NULL
            ORDER BY cs.course_date DESC, cs.end_time DESC
            LIMIT 1
        ";
        try {
            return $this->db->selectOne($sql, ['student_identifier' => $studentIdentifier]);
        } catch (Exception $e) {
            error_log('Error retrieving last unjustified absence: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Insert a course slot and return its id.
     * Used when creating a manual absence from the secretary dashboard.
     */
    public function createCourseSlot(array $data): ?int
    {
        $sql = "INSERT INTO course_slots
                    (course_date, start_time, end_time, duration_minutes, course_type,
                     resource_id, room_id, is_evaluation)
                VALUES
                    (:date, :start_time, :end_time, :duration, :course_type,
                     :resource_id, :room_id, :is_evaluation)
                RETURNING id";
        try {
            $result = $this->db->selectOne($sql, [
                ':date' => $data['absence_date'],
                ':start_time' => $data['start_time'],
                ':end_time' => $data['end_time'],
                ':duration' => $data['duration_minutes'],
                ':course_type' => $data['course_type'],
                ':resource_id' => $data['resource_id'],
                ':room_id' => $data['room_id'],
                ':is_evaluation' => isset($data['is_evaluation']) ? 'true' : 'false',
            ]);
            return $result ? (int) $result['id'] : null;
        } catch (Exception $e) {
            error_log('Error creating course slot: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Insert an absence record and return its id.
     * Used when creating a manual absence from the secretary dashboard.
     */
    public function createAbsence(string $studentIdentifier, int $courseSlotId): ?int
    {
        $sql = "INSERT INTO absences (student_identifier, course_slot_id, status, justified)
                VALUES (:student_identifier, :course_slot_id, 'absent', false)
                RETURNING id";
        try {
            $result = $this->db->selectOne($sql, [
                ':student_identifier' => $studentIdentifier,
                ':course_slot_id' => $courseSlotId,
            ]);
            return $result ? (int) $result['id'] : null;
        } catch (Exception $e) {
            error_log('Error creating absence: ' . $e->getMessage());
            return null;
        }
    }
}
