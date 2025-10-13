<meta charset="UTF-8">
<?php
class teacherTable {
    private $page1;
    private $page2;
    private $db;
    private $userId;
    private $nombrepages1;
    private $nombrepages2;

    //constructeur
    public function __construct(int $id) {
        $this->page1 = 0;
        $this->page2 = 0;
        require_once __DIR__ . '/../Model/database.php';
        $this->db = Database::getInstance();
        $this->userId = $id;
        $this->nombrepages1 = $this->getTotalPages1();
        $this->nombrepages2 = $this->getTotalPages2();
    }
    // sert a faire la requete principale du tableau
    public function getData($page1) {
        $offset = $page1 * 5;
        echo 'test1';
        
        // ratrpage sert a modifier la requete pour afficher es étudiants a faire rattraper
            $query = "SELECT users.first_name,users.last_name,resources.label,course_slots.course_date,absences.status  
            FROM absences LEFT JOIN users ON absences.student_identifier = users.identifier 
            LEFT JOIN course_slots ON absences.course_slot_id=course_slots.id
            LEFT JOIN resources ON course_slots.resource_id=resources.id

            ORDER BY course_slots.course_date DESC, absences.id ASC LIMIT 5 OFFSET :offset";
            return $this->db->select($query, ['userId' => $this->userId, 'offset' => $offset]);
        }
    
    public function rattrapeTable($page2){
        $offset = $page2 * 5;
                    // Requête modifiée pour les étudiants à rattraper
            $query = "SELECT users.first_name,users.last_name,resources.label,course_slots.course_date,absences.status  
            FROM absences LEFT JOIN users ON absences.student_identifier = users.identifier
            LEFT JOIN course_slots ON absences.course_slot_id=course_slots.id
            LEFT JOIN resources ON course_slots.resource_id=resources.id
            LEFT JOIN teachers ON resources.teacher_id=teachers.id
            LEFT JOIN makeups ON absences.id = makeups.absence_id
            WHERE teachers.user_id = :userId AND absences.justified = true and makeups.scheduled = false
            ORDER BY course_slots.course_date DESC, absences.id ASC LIMIT 5 OFFSET :offset";
            return $this->db->select($query, ['userId' => $this->userId, 'offset' => $offset]);

    }
 //Gestion des pages du premier tableau
// renvoie le nombre total de pages
    public function getTotalPages1() {
    
        $result = $this->db->select("SELECT COUNT(*) as count FROM absences");
        return ceil($result[0]['count'] / 5);   
    }
    // sert a mettre a jour l'attribut page en posant des limites
    public function setPage($page1) {
        if ($page1 >= 0 && $page1 < $this->nombrepages1) {
            $this->page1 = $page1;
        }
    }
    // fait avancer la page de 1 si possible
    public function nextPage() {
        if ($this->page1 < $this->nombrepages1 - 1) {
            $this->page1++;
        }
    }
    // fait reculer la page de 1 si possible
    public function previousPage() {
        if ($this->page1 > 0) {
            $this->page1--;
        }
    }

    //renvoie le numéro de page actuel
    public function getCurrentPage() {
        return $this->page1;
    }
    //permet l'accès a la page suivante et précédente en posant des limites
    public function getNextPage() {
        return min($this->page1 + 1, $this->nombrepages1 - 1);
    }
    public function getPreviousPage() {
        return max($this->page1 - 1, 0);
    }


    // gestion du deuxime tabeau
    // renvoie le nombre total de pages
    public function getTotalPages2() {
            $result = $this->db->select("SELECT COUNT(*) as count FROM absences 
            LeFT JOIN makeups ON absences.id = makeups.absence_id
            WHERE justified = true and makeups.scheduled = false");
            return ceil($result[0]['count'] / 5);
    }
    // sert a mettre a jour l'attribut page en posant des limites
    public function setPage2($page2) {
        if ($page2 >= 0 && $page2 < $this->nombrepages2) {
            $this->page2 = $page2;
        }
    }
    // fait avancer la page de 1 si possible
    public function nextPage2() {
        if ($this->page2 < $this->nombrepages2 - 1) {
            $this->page2++;
        }
    }
    // fait reculer la page de 1 si possible
    public function previousPage2() {
        if ($this->page2 > 0) {
            $this->page2--;
        }
    }

    //renvoie le numéro de page actuel
    public function getCurrentPage2() {
        return $this->page2;
    }
    //permet l'accès a la page suivante et précédente en posant des limites
    public function getNextPage2() {
        return min($this->page2 + 1, $this->nombrepages2 - 1);
    }
    public function getPreviousPage2() {
        return max($this->page2 - 1, 0);
    }


    // Statistiques
    public function todayAbs() {
        $query = "SELECT COUNT(*) as count FROM absences WHERE DATE(updated_at) = CURRENT_DATE";
        $res = $this->db->select($query);
        return $res[0]['count'];
    }
    public function unjustifiedAbs() {
        $query = "SELECT COUNT(*) as count FROM absences WHERE justified = false";
        $res = $this->db->select($query);
        return $res[0]['count'];
    }
    public function thisMonthAbs() {
        $query = "SELECT COUNT(*) as count FROM absences WHERE EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM CURRENT_DATE) AND EXTRACT(MONTH FROM created_at) = EXTRACT(MONTH FROM CURRENT_DATE)";
        $res = $this->db->select($query);
        return $res[0]['count'];
    }
    // Tableau
    public function laTable() {
        
        // Récupération des données brutes
        $donnees = $this->getData($this->getCurrentPage1());
        
        // Création du tableau final
        $tableau = [];
        
        // Ajout des en-têtes comme première ligne
        $tableau[] = ['Prénom', 'Nom', 'Cours', 'Date', 'Status'];
        
        // Remplissage des données
        foreach ($donnees as $ligne) {
            $tableau[] = [
                $ligne['first_name'],
                $ligne['last_name'],
                $ligne['label'],
                $ligne['course_date'],
                $ligne['status']
            ];
        }
        return $tableau;
    }
}

$test = new teachertable(5);


if (isset($_GET['page1'])) {
    $page1 = intval($_GET['page1']);
    $test->setPage1($page1);
}

?>
<a href="?page1=<?php echo $test->getPreviousPage1(); ?>">
    <button type="button">previous</button>
</a>
<a href="?page1=<?php echo $test->getNextPage1(); ?>">
    <button type="button">next</button>
</a>

<br>


<?php
require_once __DIR__ . '/../Model/database.php';

$f=$test->laTable();
$tabel= json_decode(json_encode($f),true);
echo "<table border='1'>";
foreach ($tabel as $row) {
    echo "<tr>";
    foreach ($row as $cell) {
        echo "<td>" . htmlspecialchars($cell) . "</td>";
    }
    echo "</tr>";
}
$nbpages1 = $test->getTotalPages1();?>
<br>
Current Page: <?php echo $test->getCurrentPage1() + 1; ?> /
<?php echo $nbpages1; ?>
