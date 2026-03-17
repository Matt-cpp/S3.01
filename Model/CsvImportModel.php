<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

class CsvImportModel
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? getDatabase();
    }

    public function getUserIdByIdentifier(string $identifier): ?int
    {
        $row = $this->db->selectOne("SELECT id FROM users WHERE identifier = :identifier", [':identifier' => $identifier]);
        return $row ? (int) $row['id'] : null;
    }

    public function createUser(array $data): int
    {
        $sql = "INSERT INTO users (identifier, last_name, first_name, middle_name, birth_date, degrees, department, role)
                VALUES (:identifier, :last_name, :first_name, :middle_name, :birth_date, :degrees, :department, 'student')
                RETURNING id";
        $row = $this->db->selectOne($sql, [
            ':identifier' => $data['identifier'],
            ':last_name' => $data['last_name'],
            ':first_name' => $data['first_name'],
            ':middle_name' => $data['middle_name'],
            ':birth_date' => $data['birth_date'],
            ':degrees' => $data['degrees'],
            ':department' => $data['department'],
        ]);
        return (int) $row['id'];
    }

    public function getGroupIdByCode(string $code): ?int
    {
        $row = $this->db->selectOne("SELECT id FROM groups WHERE code = :code", [':code' => $code]);
        return $row ? (int) $row['id'] : null;
    }

    public function createGroup(string $code, string $label, string $program, ?int $year): int
    {
        $row = $this->db->selectOne(
            "INSERT INTO groups (code, label, program, year)
             VALUES (:code, :label, :program, :year)
             RETURNING id",
            [':code' => $code, ':label' => $label, ':program' => $program, ':year' => $year]
        );
        return (int) $row['id'];
    }

    public function userGroupLinkExists(int $userId, int $groupId): bool
    {
        $row = $this->db->selectOne(
            "SELECT 1 FROM user_groups WHERE user_id = :user_id AND group_id = :group_id",
            [':user_id' => $userId, ':group_id' => $groupId]
        );
        return $row !== null;
    }

    public function linkUserToGroup(int $userId, int $groupId): void
    {
        $this->db->execute(
            "INSERT INTO user_groups (user_id, group_id) VALUES (:user_id, :group_id)",
            [':user_id' => $userId, ':group_id' => $groupId]
        );
    }

    public function getResourceIdByCode(string $code): ?int
    {
        $row = $this->db->selectOne("SELECT id FROM resources WHERE code = :code", [':code' => $code]);
        return $row ? (int) $row['id'] : null;
    }

    public function createResource(string $code, string $label, string $teachingType): int
    {
        $row = $this->db->selectOne(
            "INSERT INTO resources (code, label, teaching_type)
             VALUES (:code, :label, :teaching_type)
             RETURNING id",
            [':code' => $code, ':label' => $label, ':teaching_type' => $teachingType]
        );
        return (int) $row['id'];
    }

    public function getRoomIdByCode(string $code): ?int
    {
        $row = $this->db->selectOne("SELECT id FROM rooms WHERE code = :code", [':code' => $code]);
        return $row ? (int) $row['id'] : null;
    }

    public function createRoom(string $code): int
    {
        $row = $this->db->selectOne("INSERT INTO rooms (code) VALUES (:code) RETURNING id", [':code' => $code]);
        return (int) $row['id'];
    }

    public function getTeacherIdByFullName(string $teacherName): ?int
    {
        $row = $this->db->selectOne(
            "SELECT id FROM teachers WHERE CONCAT(last_name, ' ', first_name) = :name",
            [':name' => $teacherName]
        );
        return $row ? (int) $row['id'] : null;
    }

    public function createTeacher(string $lastName, string $firstName): int
    {
        $row = $this->db->selectOne(
            "INSERT INTO teachers (last_name, first_name) VALUES (:last_name, :first_name) RETURNING id",
            [':last_name' => $lastName, ':first_name' => $firstName]
        );
        return (int) $row['id'];
    }

    public function findMatchingCourseSlot(
        string $courseDate,
        string $startTime,
        string $endTime,
        ?int $resourceId,
        ?int $roomId,
        ?int $teacherId,
        ?int $groupId
    ): ?int {
        $row = $this->db->selectOne(
            "SELECT id FROM course_slots
             WHERE course_date = :date
               AND start_time = :start_time
               AND end_time = :end_time
               AND resource_id = :resource_id
               AND room_id = :room_id
               AND teacher_id = :teacher_id
               AND group_id = :group_id",
            [
                ':date' => $courseDate,
                ':start_time' => $startTime,
                ':end_time' => $endTime,
                ':resource_id' => $resourceId,
                ':room_id' => $roomId,
                ':teacher_id' => $teacherId,
                ':group_id' => $groupId,
            ]
        );
        return $row ? (int) $row['id'] : null;
    }

    public function createCourseSlot(array $data): int
    {
        $row = $this->db->selectOne(
            "INSERT INTO course_slots
                (course_date, start_time, end_time, duration_minutes, course_type,
                 resource_id, room_id, teacher_id, group_id, is_evaluation, subject_identifier)
             VALUES
                (:date, :start_time, :end_time, :duration, :course_type,
                 :resource_id, :room_id, :teacher_id, :group_id, :is_evaluation, :subject_identifier)
             RETURNING id",
            [
                ':date' => $data['course_date'],
                ':start_time' => $data['start_time'],
                ':end_time' => $data['end_time'],
                ':duration' => $data['duration_minutes'],
                ':course_type' => $data['course_type'],
                ':resource_id' => $data['resource_id'],
                ':room_id' => $data['room_id'],
                ':teacher_id' => $data['teacher_id'],
                ':group_id' => $data['group_id'],
                ':is_evaluation' => $data['is_evaluation'] ? 'true' : 'false',
                ':subject_identifier' => $data['subject_identifier'],
            ]
        );
        return (int) $row['id'];
    }

    public function absenceExists(string $studentIdentifier, int $courseSlotId): bool
    {
        $row = $this->db->selectOne(
            "SELECT id FROM absences WHERE student_identifier = :id AND course_slot_id = :slot",
            [':id' => $studentIdentifier, ':slot' => $courseSlotId]
        );
        return $row !== null;
    }

    public function createAbsence(string $studentIdentifier, int $courseSlotId, string $status, bool $justified): void
    {
        $this->db->execute(
            "INSERT INTO absences (student_identifier, course_slot_id, status, justified)
             VALUES (:student_identifier, :course_slot_id, :status, :justified)",
            [
                ':student_identifier' => $studentIdentifier,
                ':course_slot_id' => $courseSlotId,
                ':status' => $status,
                ':justified' => $justified ? 'true' : 'false',
            ]
        );
    }
}
