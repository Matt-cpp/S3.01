<meta charset="UTF-8">
<?php
class tableRatrapage{
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
}
//reqeuete principale du tableau
public function getData(){
    $userId = (int)$this->userId;

    $query = "SELECT users.first_name, users.last_name,resources.label, course_slots.course_date
    FROM absences LEFT JOIN course_slots ON absences.course_slot_id = course_slots.id
    LEFT JOIN users ON absences.student_identifier = users.identifier
    LefT JOIN resources ON course_slots.resource_id = resources.id
    WHERE course_slots.teacher_id=".$this->userId." AND absences.status='excused' 
        AND course_slots.is_evaluation=true
        ORDER BY course_slots.course_date DESC";
    return $this->db->select($query);
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
        $tableau = "<table border='1'>  
        <tr>
            <th>First Name</th>
            <th>Last Name</th>
            <th>Resource</th>
            <th>Course Date</th>
        </tr>";
        foreach ($donnees as $ligne) {
            $tableau .= "<tr>
                <td>" . htmlspecialchars($ligne['first_name']) . "</td>
                <td>" . htmlspecialchars($ligne['last_name']) . "</td>
                <td>" . htmlspecialchars($ligne['label']) . "</td>
                <td>" . htmlspecialchars($ligne['course_date']) . "</td>
            </tr>";
        }
        $tableau .= "</table>";
        return $tableau;

    }
}
/*
$test = new tableRatrapage(4);

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
*/