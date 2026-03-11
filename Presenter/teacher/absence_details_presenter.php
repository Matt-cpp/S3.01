<?php

declare(strict_types=1);

// Manages the absence details page for teachers
// Shows absence details (justified or not) for a specific evaluation
// Used after clicking on an evaluation on the evaluations page
class AbsenceDetailsPresenter
{
    private Database $db;
    private int $courseSlotId;

    public function __construct(int $courseId)
    {
        require_once __DIR__ . '/../../Model/database.php';
        $this->db = Database::getInstance();
        $this->courseSlotId = $courseId;
    }

    public function getCourseId(): int
    {
        return $this->courseSlotId;
    }

    // Retrieve details of the specific evaluation (subject, date, time)
    public function getAbsenceDetails(): array
    {
        $query = "SELECT resources.label, course_slots.course_date, course_slots.start_time
        FROM course_slots LEFT JOIN resources ON course_slots.subject_identifier = resources.code
        WHERE course_slots.id = " . $this->getCourseId() . ";";
        $result = $this->db->select($query);
        return $result[0];
    }
    // Retrieve the list of absences (justified or not) for the specific evaluation
    public function getAbsences(): array
    {
        $query = "SELECT users.first_name, users.last_name, absences.justified
        FROM absences LEFT JOIN users ON absences.student_identifier = users.identifier
        WHERE absences.course_slot_id = " . $this->getCourseId() . ";";
        $result = $this->db->select($query);
        return $result;
    }
}
