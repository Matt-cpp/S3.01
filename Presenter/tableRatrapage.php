<meta charset="UTF-8">
<?php
class tableRatrapage{
    private $page;
    private $db;
    private $userId;
    private $nombrepages;
    //constructeur
    public function __construct(int $id) {
        $this->page = 0;
        require_once __DIR__ . '/../Model/database.php';
        $this->db = Database::getInstance();
        $this->userId = $id;
        $this->nombrepages = $this->getTotalPages();
    }
    // calcule le nombre de pages totales du tableau
    public function getTotalPages(){
        try {
        $query = "SELECT COUNT(*) as count FROM absences LEFT JOIN course_slots ON absences.course_slot_id = course_slots.id
        WHERE course_slots.teacher_id=".$this->userId." AND absences.status='excused'";
        $result = $this->db->select($query);    
        if (empty($result)) {
            return 1;
        }
        return ceil($result[0]['count'] / 5);
    } catch (Exception $e) {
        echo "ERREUR dans getTotalPages: " . $e->getMessage();
        return 1;
    }
}
// renvoie le nombre de pages totales sans refaire de requete
    public function getNombrePages(){
        return $this->nombrepages;
    }
    // renvoie le numéro de la page actuelle
    public function getPage(){
        return $this->page;
    }
//reqeuete principale du tableau
public function getData($page){
    $offset = (int)($page * 5);
    $userId = (int)$this->userId;
    // select a refaire
    $query = "SELECT *
    FROM absences LEFT JOIN course_slots ON absences.course_slot_id = course_slots.id
        WHERE course_slots.teacher_id=".$this->userId." AND absences.status='excused'
        ORDER BY course_slots.course_date DESC
        LIMIT 5 OFFSET $offset";
    return $this->db->select($query);
}
}
$test = new tableRatrapage(1);
echo $test->getTotalPages();
echo "<br>";
foreach($test->getData(0) as $row){
    echo "<pre>";
    print_r($row);
    echo "</pre>";
}