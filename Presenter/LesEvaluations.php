<meta charset="UTF-8">
<?php
class pageEvalProf
{
    private $db;
    private $userId;
    //constructeur
    public function __construct(int $id)
    {
        require_once __DIR__ . '/../Model/database.php';
        $this->db = Database::getInstance();
        $this->userId = this->linkTeacherUser($id);
        
    }
    public function linkTeacherUser(int $id)
    {
        $query = "SELECT teacher.id FROM teachers left JOIN users ON teachers.email=users.email
        WHERE users.id=" . $id . ""; 
        $result = $this->db->select($query);
        return $result[0]['identifier'];
    }
    public function lesEvaluations()
    {
        // query pas finie
        $query = "SELECT resources.label, course_slots.course_date,
        course_slots.start_time,count(//absences) as nb_absences,
        count(//abs just) as nb_justifications
        
        FROM course_slots Left join resources on course_slots.subject_identifier=resources.code
        
        WHERE course_slots.teacher_id=" . $this->userId . " 
        and course_slots.is_evaluation
        group by course_slots.subject_identifier";
    }



    

}