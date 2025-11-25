<?php
class pageEvalProf
{
    private $db;
    private $userId;
    
    public function __construct(int $id)
    {
        require_once __DIR__ . '/../Model/database.php';
        $this->db = Database::getInstance();
        $this->userId = $this->linkTeacherUser($id);
    }
    
    // ça marche pas encore
    private function linkTeacherUser(int $id)
    {
        $query = "SELECT teachers.id as id
        FROM users LEFT JOIN teachers ON teachers.email = users.email
        WHERE users.id = " . $id; 

        $result = $this->db->select($query);
        echo "ID Teacher lié à l'utilisateur : " . $result[0]['id'] . "\n"; 
        return $result[0]['id'];
    }
    
    public function lesEvaluations($arg)
    {
        // CORRECTION: syntaxe SQL complète et correcte
        $query = "SELECT resources.label, course_slots.course_date,course_slots.start_time, COUNT(*) as nbabs ,COUNT (CASE WHEN absences.justified = True THEN 1 END) as nb_justifications
        FROM course_slots LEFT JOIN resources ON course_slots.subject_identifier = resources.code
         LEFT JOIN absences ON course_slots.id = absences.course_slot_id
         WHERE course_slots.teacher_id = " . $this->userId . " AND course_slots.is_evaluation = True
         GROUP BY course_slots.id, resources.label, course_slots.course_date, course_slots.start_time
         ORDER BY  "  .$arg." DESC, course_slots.start_time DESC;";
//course_slots.course_date
        $result = $this->db->select($query);
        return $result;
    }
    
}
$test = new pageEvalProf(13);
/*evals = $test->exemple();
foreach ($evals as $eval) {
    echo "ID Teacher: " . $eval['id'] . " - Justifications: " . $eval['cpt'] . "\n";
}
*/
$evals = $test->lesEvaluations("course_slots.course_date");
foreach ($evals as $eval) {
    echo "Matière: " . $eval['label'] . " - Date: " . $eval['course_date'] . " - Heures: " . $eval['start_time'] . " - Absences: " . $eval['nbabs'] . " - Justifications: " . $eval['nb_justifications'] . "\n";
}

?>