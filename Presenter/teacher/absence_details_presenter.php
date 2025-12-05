<?php
class detailsAbs
{
    private $db;
    private $courseSlotId;

    public function __construct($courseId)
    {
        require_once __DIR__ . '/../../Model/database.php';
        $this->db = Database::getInstance();
        $this->courseSlotId = $courseId;
    }
    public function getCourseId()
    {
        return $this->courseSlotId;
    }

    public function getAbsenceDetails()
    {
        $query = "SELECT resources.label, course_slots.course_date, course_slots.start_time
        FROM course_slots LEFT JOIN resources ON course_slots.subject_identifier = resources.code
        WHERE course_slots.id = " . $this->getCourseId() . ";";
        $result = $this->db->select($query);
        return $result[0];
    }
    public function getAbsences()
    {
        $query = "SELECT users.first_name, users.last_name, absences.justified
        FROM absences LEFT JOIN users ON absences.student_identifier = users.identifier
        WHERE absences.course_slot_id = " . $this->getCourseId() . ";";



        $result = $this->db->select($query);
        return $result;
    }

}