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
        $query = "SELECT COUNT(*) as count 
        FROM absences LEFT JOIN course_slots 
        ON absences.course_slot_id = course_slots.id
        WHERE course_slots.teacher_id=".$this->userId."
         AND absences.status='excused'";
        
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
    $query = "SELECT users.first_name,users.last_name,COALESCE(users.degrees,'N/A') as degrees, course_slots.course_date,absences.status,resources.label
    From absences 
    Left Join users on absences.student_identifier = users.identifier
    Left Join course_slots ON absences.course_slot_id = course_slots.id
    Left Join resources ON course_slots.resource_id = resources.id
        WHERE course_slots.teacher_id=".$this->userId."
        ORDER BY course_slots.course_date DESC
        LIMIT 5 OFFSET $offset";

    return $this->db->select($query);
}
public function setPage($page){
    if($page>=0 && $page<$this->nombrepages){
        $this->page=$page;
    }
}
    // fait avancer la page de 1 si possible
    public function nextPage() {
        if ($this->page < $this->nombrepages - 1) {
            $this->page++;
        }
    }
    // fait reculer la page de 1 si possible
    public function previousPage() {
        if ($this->page > 0) {
            $this->page--;
        }
    }
    //renvoie le numéro de page actuel
    public function getCurrentPage() {
        return $this->page;
    }
    //permet l'accès a la page suivante et précédente en posant des limites
    public function getNextPage() {
        return min($this->page + 1, $this->nombrepages - 1);
    }
    public function getPreviousPage() {
        return max($this->page - 1, 0);
    }
    
    // Tableau
    public function laTable() {
        // Récupération des données brutes
        $donnees = $this->getData($this->getCurrentPage());
        $tableau=[];
        // Construction du tableau HTML
        foreach ($donnees as $ligne) {
            $tableau[] = "<tr>
            <td>" . htmlspecialchars($ligne['first_name']) . "</td>
            <td>" . htmlspecialchars($ligne['last_name']) . "</td>
            <td>" . htmlspecialchars($ligne['degrees']) . "</td>
            <td>" . htmlspecialchars($ligne['label']) . "</td>
            <td>" . htmlspecialchars($ligne['course_date']) . "</td>
            <td>" . htmlspecialchars($ligne['status']) . "</td>
            </tr>";
        }
        return "<table border='1'>  
        <tr>
        <th>First Name</th>
        <th>Last Name</th>
        <th>Degrees</th>
        <th>Resource Label</th>
        <th>Course Date</th>
        <th>Status</th>
        </tr>" . implode("", $tableau) . "</table>";

    }
}
/*
$test = new tableRatrapage(3);

if (isset($_GET['page'])) {
    $page = intval($_GET['page']);
    $test->setPage($page);
}

echo $test->laTable();  
?>
<a href="?page=<?php echo $test->getPreviousPage(); ?>">
    <button type="button">previous</button>
</a>
<a href="?page=<?php echo $test->getNextPage(); ?>">
    <button type="button">next</button>
</a>
<br>
<?php
echo "Page " . ($test->getCurrentPage() + 1) . " sur " . $test->getNombrePages() . "<br>";
?>
<br>
*/
