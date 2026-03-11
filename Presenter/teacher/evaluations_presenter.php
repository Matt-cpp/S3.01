<?php

declare(strict_types=1);

// Manages the evaluations page for teachers
// Shows only evaluations (exams) with absent students (justified or not)
class TeacherEvaluationsPresenter
{
    private Database $db;
    private int $userId;
    private string $filter;

    public function __construct(int $id)
    {
        require_once __DIR__ . '/../../Model/database.php';
        $this->db = Database::getInstance();
        $this->userId = $this->linkTeacherUser($id);
        $this->filter = 'course_slots.course_date'; // Default filter
    }

    // Enable a specific filter
    public function enableFilter(string $filter): void
    {
        $allowedFilters = ['course_slots.course_date', 'nb_justifications', 'nbabs'];
        if (in_array($filter, $allowedFilters)) {
            $this->filter = $filter;
        }
    }

    // Link the teacher ID with the connected user ID via email
    private function linkTeacherUser(int $id): int
    {
        $query = "SELECT teachers.id as id
        FROM users LEFT JOIN teachers ON teachers.email = users.email
        WHERE users.id = " . $id;

        $result = $this->db->select($query);
        return $result[0]['id'];
    }

    // Return evaluations for taught subjects (exams with absence counts, justified or not)
    public function getEvaluations(): array
    {
        $query = "SELECT resources.label, course_slots.course_date,course_slots.start_time, COUNT(*) as nbabs ,COUNT (CASE WHEN absences.justified = True THEN 1 END) as nb_justifications, course_slots.id as course_slot_id
        FROM course_slots LEFT JOIN resources ON course_slots.subject_identifier = resources.code
         LEFT JOIN absences ON course_slots.id = absences.course_slot_id
         WHERE course_slots.teacher_id = " . $this->userId . " AND course_slots.is_evaluation = True
         GROUP BY course_slots.id, resources.label, course_slots.course_date, course_slots.start_time
         ORDER BY  " . $this->filter . " DESC, course_slots.start_time DESC;";
        //course_slots.course_date
        //nb_justifications
        //nbabs

        $result = $this->db->select($query);
        return $result;
    }
}
