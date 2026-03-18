<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

class TeacherDataModel
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? getDatabase();
    }

    public function getTeacherIdByUserId(int $userId): ?int
    {
        $sql = "SELECT teachers.id
                FROM users
                LEFT JOIN teachers ON teachers.email = users.email
                WHERE users.id = :user_id
                LIMIT 1";
        $row = $this->db->selectOne($sql, [':user_id' => $userId]);
        return $row ? (int) $row['id'] : null;
    }

    public function countTeacherAbsences(int $teacherId, ?string $resourceLabel = null): int
    {
        $sql = "SELECT COUNT(*) as count
                FROM absences
                LEFT JOIN course_slots ON absences.course_slot_id = course_slots.id
                LEFT JOIN resources ON course_slots.resource_id = resources.id
                WHERE course_slots.teacher_id = :teacher_id";
        $params = [':teacher_id' => $teacherId];

        if ($resourceLabel !== null && $resourceLabel !== '') {
            $sql .= " AND resources.label = :resource_label";
            $params[':resource_label'] = $resourceLabel;
        }

        $row = $this->db->selectOne($sql, $params);
        return (int) ($row['count'] ?? 0);
    }

    public function getTeacherAbsencesPage(int $teacherId, int $limit, int $offset, ?string $resourceLabel = null): array
    {
        $sql = "SELECT users.first_name, users.last_name, COALESCE(groups.label, 'N/A') as degrees,
                       course_slots.course_date, absences.status, resources.label
                FROM absences
                LEFT JOIN users ON absences.student_identifier = users.identifier
                LEFT JOIN course_slots ON absences.course_slot_id = course_slots.id
                LEFT JOIN resources ON course_slots.resource_id = resources.id
                LEFT JOIN user_groups ON users.id = user_groups.user_id
                LEFT JOIN groups ON user_groups.group_id = groups.id
                WHERE course_slots.teacher_id = :teacher_id";

        $params = [':teacher_id' => $teacherId, ':limit' => $limit, ':offset' => $offset];

        if ($resourceLabel !== null && $resourceLabel !== '') {
            $sql .= " AND resources.label = :resource_label";
            $params[':resource_label'] = $resourceLabel;
        }

        $sql .= " ORDER BY course_slots.course_date DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bindValue(':teacher_id', $teacherId, PDO::PARAM_INT);
        if (isset($params[':resource_label'])) {
            $stmt->bindValue(':resource_label', $params[':resource_label'], PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getTeacherResourceLabels(int $teacherId): array
    {
        $rows = $this->db->select(
            "SELECT DISTINCT resources.label
             FROM course_slots
             LEFT JOIN resources ON course_slots.resource_id = resources.id
             WHERE course_slots.teacher_id = :teacher_id",
            [':teacher_id' => $teacherId]
        );

        return array_values(array_filter(array_map(static fn($row) => $row['label'] ?? null, $rows)));
    }

    public function getTeacherEvaluations(int $teacherId, string $orderBy): array
    {
        $allowed = ['course_slots.course_date', 'nb_justifications', 'nbabs'];
        $safeOrderBy = in_array($orderBy, $allowed, true) ? $orderBy : 'course_slots.course_date';

        $sql = "SELECT resources.label, course_slots.course_date, course_slots.start_time,
                       COUNT(*) as nbabs,
                       COUNT(CASE WHEN absences.justified = TRUE THEN 1 END) as nb_justifications,
                       course_slots.id as course_slot_id
                FROM course_slots
                LEFT JOIN resources ON course_slots.subject_identifier = resources.code
                LEFT JOIN absences ON course_slots.id = absences.course_slot_id
                WHERE course_slots.teacher_id = :teacher_id AND course_slots.is_evaluation = TRUE
                GROUP BY course_slots.id, resources.label, course_slots.course_date, course_slots.start_time
                ORDER BY {$safeOrderBy} DESC, course_slots.start_time DESC";

        return $this->db->select($sql, [':teacher_id' => $teacherId]);
    }

    public function getCourseSlotSummary(int $courseSlotId): ?array
    {
        return $this->db->selectOne(
            "SELECT resources.label, course_slots.course_date, course_slots.start_time
             FROM course_slots
             LEFT JOIN resources ON course_slots.subject_identifier = resources.code
             WHERE course_slots.id = :course_slot_id",
            [':course_slot_id' => $courseSlotId]
        );
    }

    public function getCourseSlotAbsences(int $courseSlotId): array
    {
        return $this->db->select(
            "SELECT users.first_name, users.last_name, absences.justified
             FROM absences
             LEFT JOIN users ON absences.student_identifier = users.identifier
             WHERE absences.course_slot_id = :course_slot_id",
            [':course_slot_id' => $courseSlotId]
        );
    }

    public function countPendingMakeups(int $teacherId): int
    {
        $row = $this->db->selectOne(
            "SELECT COUNT(*) as count
             FROM absences
             LEFT JOIN course_slots ON absences.course_slot_id = course_slots.id
             LEFT JOIN makeups ON absences.id = makeups.absence_id
             WHERE course_slots.teacher_id = :teacher_id
               AND absences.justified = TRUE
               AND course_slots.is_evaluation = TRUE
               AND makeups.id IS NULL",
            [':teacher_id' => $teacherId]
        );
        return (int) ($row['count'] ?? 0);
    }

    public function getPendingMakeupsPage(int $teacherId, int $limit, int $offset): array
    {
        $sql = "SELECT users.first_name, users.last_name, resources.label, course_slots.course_date,
                       course_slots.id as course_slot_id, course_slots.duration_minutes
                FROM absences
                LEFT JOIN course_slots ON absences.course_slot_id = course_slots.id
                LEFT JOIN users ON absences.student_identifier = users.identifier
                LEFT JOIN resources ON course_slots.resource_id = resources.id
                LEFT JOIN makeups ON absences.id = makeups.absence_id
                WHERE course_slots.teacher_id = :teacher_id
                  AND absences.justified = TRUE
                  AND course_slots.is_evaluation = TRUE
                  AND makeups.id IS NULL
                ORDER BY course_slots.course_date DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bindValue(':teacher_id', $teacherId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getMakeupEligibleExams(int $teacherId): array
    {
        return $this->db->select(
            "SELECT DISTINCT cs.id, cs.course_date, cs.start_time,
                    r.label as resource_label, r.code as resource_code,
                    g.code as group_code
             FROM course_slots cs
             INNER JOIN absences a ON a.course_slot_id = cs.id
             LEFT JOIN resources r ON cs.resource_id = r.id
             LEFT JOIN groups g ON cs.group_id = g.id
             LEFT JOIN makeups m ON m.absence_id = a.id
             WHERE cs.teacher_id = :teacher_id
               AND cs.is_evaluation = TRUE
               AND a.justified = TRUE
               AND m.id IS NULL
             ORDER BY cs.course_date DESC, cs.start_time DESC",
            [':teacher_id' => $teacherId]
        );
    }

    public function getMakeupEligibleStudents(int $examId): array
    {
        return $this->db->select(
            "SELECT a.id, cs.id as courseId, u.identifier,
                    u.first_name, u.last_name, r.label, cs.course_date
             FROM absences a
             INNER JOIN course_slots cs ON a.course_slot_id = cs.id
             LEFT JOIN users u ON a.student_identifier = u.identifier
             LEFT JOIN resources r ON cs.resource_id = r.id
             LEFT JOIN makeups m ON m.absence_id = a.id
             WHERE cs.id = :exam_id
               AND a.justified = TRUE
               AND m.id IS NULL
             ORDER BY cs.course_date DESC",
            [':exam_id' => $examId]
        );
    }

    public function getAllRooms(): array
    {
        return $this->db->select("SELECT id, code FROM rooms ORDER BY code ASC");
    }

    public function findRoomIdByCode(string $roomCode): ?int
    {
        $row = $this->db->selectOne("SELECT id FROM rooms WHERE code = :code", [':code' => $roomCode]);
        return $row ? (int) $row['id'] : null;
    }

    public function createRoom(string $roomCode): int
    {
        $this->db->execute("INSERT INTO rooms (code) VALUES (:code)", [':code' => $roomCode]);
        return (int) $this->db->lastInsertId();
    }

    public function createMakeupSession(
        int $absenceId,
        int $evaluationSlotId,
        string $studentIdentifier,
        string $makeupDate,
        ?int $roomId,
        ?int $durationMinutes,
        ?string $comment
    ): void {
        $this->db->execute(
            "INSERT INTO makeups (absence_id, evaluation_slot_id, student_identifier, scheduled, makeup_date, room_id, duration_minutes, comment)
             VALUES (:absence_id, :evaluation_slot_id, :student_identifier, TRUE, :makeup_date, :room_id, :duration_minutes, :comment)",
            [
                ':absence_id' => $absenceId,
                ':evaluation_slot_id' => $evaluationSlotId,
                ':student_identifier' => $studentIdentifier,
                ':makeup_date' => $makeupDate,
                ':room_id' => $roomId,
                ':duration_minutes' => $durationMinutes,
                ':comment' => $comment,
            ]
        );
    }

    public function getStudentContactByIdentifier(string $studentIdentifier): ?array
    {
        return $this->db->selectOne(
            "SELECT email, first_name, last_name FROM users WHERE identifier = :identifier",
            [':identifier' => $studentIdentifier]
        );
    }

    public function getCourseSlotForMakeupEmail(int $evaluationSlotId): ?array
    {
        return $this->db->selectOne(
            "SELECT cs.course_date, cs.start_time, cs.end_time, cs.duration_minutes as original_duration,
                    r.label as resource_label, cs.course_type
             FROM course_slots cs
             LEFT JOIN resources r ON cs.resource_id = r.id
             WHERE cs.id = :eval_id",
            [':eval_id' => $evaluationSlotId]
        );
    }

    public function getRoomCodeById(int $roomId): ?string
    {
        $room = $this->db->selectOne("SELECT code FROM rooms WHERE id = :room_id", [':room_id' => $roomId]);
        return $room['code'] ?? null;
    }
}
