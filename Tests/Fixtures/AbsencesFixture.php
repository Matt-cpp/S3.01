<?php

namespace Tests\Fixtures;

/**
 * Test fixtures for creating absences and course slots
 */
class AbsencesFixture
{
    /**
     * Create a test group
     */
    public static function createGroup(\PDO $pdo, array $overrides = []): array
    {
        $data = array_merge([
            'code' => 'TEST-' . uniqid(),
            'label' => 'Test Group',
            'program' => 'BUT Informatique',
            'year' => date('Y')
        ], $overrides);

        $sql = "INSERT INTO groups (code, label, program, year) 
                VALUES (:code, :label, :program, :year)
                RETURNING id, code, label, program, year";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Create a test resource
     */
    public static function createResource(\PDO $pdo, array $overrides = []): array
    {
        $data = array_merge([
            'code' => 'R' . uniqid(),
            'label' => 'Test Resource',
            'teaching_type' => 'CM'
        ], $overrides);

        $sql = "INSERT INTO resources (code, label, teaching_type) 
                VALUES (:code, :label, :teaching_type)
                RETURNING id, code, label, teaching_type";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Create a test room
     */
    public static function createRoom(\PDO $pdo, array $overrides = []): array
    {
        $data = array_merge([
            'code' => 'ROOM' . uniqid()
        ], $overrides);

        $sql = "INSERT INTO rooms (code) 
                VALUES (:code)
                RETURNING id, code";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Create a test teacher (in teachers table, not users)
     */
    public static function createTeacherRecord(\PDO $pdo, array $overrides = []): array
    {
        $data = array_merge([
            'last_name' => 'Dupont',
            'first_name' => 'Jean',
            'email' => 'jean.dupont' . rand(1000, 9999) . '@test.com'
        ], $overrides);

        $sql = "INSERT INTO teachers (last_name, first_name, email) 
                VALUES (:last_name, :first_name, :email)
                RETURNING id, last_name, first_name, email";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Create a test course slot
     */
    public static function createCourseSlot(\PDO $pdo, array $overrides = []): array
    {
        $defaults = [
            'course_date' => date('Y-m-d'),
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'duration_minutes' => 120,
            'course_type' => 'CM',
            'is_evaluation' => false
        ];

        // Create dependencies if not provided
        if (!isset($overrides['group_id'])) {
            $group = self::createGroup($pdo);
            $overrides['group_id'] = $group['id'];
        }

        if (!isset($overrides['resource_id'])) {
            $resource = self::createResource($pdo);
            $overrides['resource_id'] = $resource['id'];
        }

        if (!isset($overrides['room_id'])) {
            $room = self::createRoom($pdo);
            $overrides['room_id'] = $room['id'];
        }

        if (!isset($overrides['teacher_id'])) {
            $teacher = self::createTeacherRecord($pdo);
            $overrides['teacher_id'] = $teacher['id'];
        }

        $data = array_merge($defaults, $overrides);

        // Ensure boolean columns are properly typed
        $data['is_evaluation'] = $data['is_evaluation'] === '' ? false : (bool) $data['is_evaluation'];

        $sql = "INSERT INTO course_slots (course_date, start_time, end_time, duration_minutes, course_type, 
                    resource_id, room_id, teacher_id, group_id, is_evaluation) 
                VALUES (:course_date, :start_time, :end_time, :duration_minutes, :course_type, 
                    :resource_id, :room_id, :teacher_id, :group_id, :is_evaluation)
                RETURNING id, course_date, start_time, end_time, duration_minutes, course_type, 
                    resource_id, room_id, teacher_id, group_id, is_evaluation";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':course_date', $data['course_date']);
        $stmt->bindValue(':start_time', $data['start_time']);
        $stmt->bindValue(':end_time', $data['end_time']);
        $stmt->bindValue(':duration_minutes', $data['duration_minutes']);
        $stmt->bindValue(':course_type', $data['course_type']);
        $stmt->bindValue(':resource_id', $data['resource_id']);
        $stmt->bindValue(':room_id', $data['room_id']);
        $stmt->bindValue(':teacher_id', $data['teacher_id']);
        $stmt->bindValue(':group_id', $data['group_id']);
        $stmt->bindValue(':is_evaluation', $data['is_evaluation'], \PDO::PARAM_BOOL);
        $stmt->execute();

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Create a test absence
     */
    public static function createAbsence(\PDO $pdo, string $studentIdentifier, int $courseSlotId, array $overrides = []): array
    {
        $data = array_merge([
            'student_identifier' => $studentIdentifier,
            'course_slot_id' => $courseSlotId,
            'status' => 'absent',
            'justified' => false
        ], $overrides);

        // Ensure boolean columns are properly typed
        $data['justified'] = $data['justified'] === '' ? false : (bool) $data['justified'];

        $sql = "INSERT INTO absences (student_identifier, course_slot_id, status, justified, import_date, created_at, updated_at) 
                VALUES (:student_identifier, :course_slot_id, :status, :justified, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                RETURNING id, student_identifier, course_slot_id, status, justified";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':student_identifier', $data['student_identifier']);
        $stmt->bindValue(':course_slot_id', $data['course_slot_id']);
        $stmt->bindValue(':status', $data['status']);
        $stmt->bindValue(':justified', $data['justified'], \PDO::PARAM_BOOL);
        $stmt->execute();

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Create multiple course slots for a date range
     */
    public static function createCourseSlots(\PDO $pdo, string $startDate, string $endDate, array $options = []): array
    {
        $slots = [];
        $current = strtotime($startDate);
        $end = strtotime($endDate);

        // Create shared dependencies
        $group = self::createGroup($pdo);
        $resource = self::createResource($pdo);
        $room = self::createRoom($pdo);
        $teacher = self::createTeacherRecord($pdo);

        while ($current <= $end) {
            // Only create slots for weekdays (Monday-Friday)
            if (date('N', $current) <= 5) {
                $slots[] = self::createCourseSlot($pdo, array_merge([
                    'course_date' => date('Y-m-d', $current),
                    'group_id' => $group['id'],
                    'resource_id' => $resource['id'],
                    'room_id' => $room['id'],
                    'teacher_id' => $teacher['id']
                ], $options));
            }
            $current = strtotime('+1 day', $current);
        }

        return $slots;
    }

    /**
     * Create absences for a student across multiple course slots
     */
    public static function createAbsencesForStudent(\PDO $pdo, string $studentIdentifier, array $courseSlotIds, array $overrides = []): array
    {
        $absences = [];
        foreach ($courseSlotIds as $slotId) {
            $absences[] = self::createAbsence($pdo, $studentIdentifier, $slotId, $overrides);
        }
        return $absences;
    }

    /**
     * Create a complete absence scenario (course slot + absence)
     */
    public static function createAbsenceScenario(\PDO $pdo, string $studentIdentifier, array $courseSlotData = [], array $absenceData = []): array
    {
        $courseSlot = self::createCourseSlot($pdo, $courseSlotData);
        $absence = self::createAbsence($pdo, $studentIdentifier, $courseSlot['id'], $absenceData);

        return [
            'course_slot' => $courseSlot,
            'absence' => $absence
        ];
    }

    /**
     * Create an evaluation absence (is_evaluation = true)
     */
    public static function createEvaluationAbsence(\PDO $pdo, string $studentIdentifier, array $overrides = []): array
    {
        $courseSlot = self::createCourseSlot($pdo, array_merge([
            'is_evaluation' => true,
            'course_type' => 'DS'
        ], $overrides));

        $absence = self::createAbsence($pdo, $studentIdentifier, $courseSlot['id']);

        return [
            'course_slot' => $courseSlot,
            'absence' => $absence
        ];
    }

    /**
     * Create absences with different statuses
     */
    public static function createAbsencesWithStatuses(\PDO $pdo, string $studentIdentifier): array
    {
        $statuses = ['absent', 'present', 'excused', 'unjustified'];
        $absences = [];

        foreach ($statuses as $status) {
            $scenario = self::createAbsenceScenario($pdo, $studentIdentifier, [], ['status' => $status]);
            $absences[$status] = $scenario['absence'];
        }

        return $absences;
    }
}
