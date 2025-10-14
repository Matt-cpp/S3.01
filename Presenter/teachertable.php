<meta charset="UTF-8">
<?php
class teacherTable {
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
    // sert a faire la requete principale du tableau
public function getData($page) {
    $offset = (int)($page * 5);
    $userId = (int)$this->userId;
    
    $query = "SELECT users.first_name, users.last_name, 
              resources.label, 
              course_slots.course_date, 
              absences.status
              FROM teachers
              INNER JOIN resources ON resources.teacher_id = teachers.id
              INNER JOIN course_slots ON course_slots.resource_id = resources.id
              INNER JOIN absences ON absences.course_slot_id = course_slots.id
              LEFT JOIN users ON absences.student_identifier = users.identifier
              WHERE teachers.user_id = $userId
              AND absences.justified = 0
              ORDER BY course_slots.course_date DESC, absences.id ASC
              LIMIT 5 OFFSET $offset";
    
    return $this->db->select($query);
}
// renvoie le nombre total de pages
public function getTotalPages() {
try {
        $query = "SELECT COUNT(*) as count FROM absences";
        $result = $this->db->select($query);
        
        echo "<pre>DEBUG getTotalPages result: ";
        print_r($result);
        echo "</pre>";
        
        if (empty($result)) {
            return 1;
        }
        
        return ceil($result[0]['count'] / 5);
    } catch (Exception $e) {
        echo "ERREUR dans getTotalPages: " . $e->getMessage();
        return 1;
    }
}
    // sert a mettre a jour l'attribut page en posant des limites
    public function setPage($page) {
        if ($page >= 0 && $page < $this->nombrepages) {
            $this->page = $page;
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
    //activer le filtre pour rattrapage
    public function enableRattrapage() {
        $this->ratrapage = true;
        $this->page = 0; // Réinitialiser la page à 0
        $this->nombrepages = $this->getTotalPages(); // Mettre à jour le nombre total de pages
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
        $donnees = $this->getData($this->getCurrentPage());
        
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

$test = new teacherTable(2);



if (isset($_GET['page'])) {
    $page = intval($_GET['page']);
    $test->setPage($page);
}
?>
<a href="?page=<?php echo $test->getPreviousPage(); ?>">
    <button type="button">previous</button>
</a>
<a href="?page=<?php echo $test->getNextPage(); ?>">
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
        echo "<td>" . htmlspecialchars($cell ?? '') . "</td>";
    }
    echo "</tr>";
}
$nbpages = $test->getTotalPages();?>
<br>
Current Page: <?php echo $test->getCurrentPage() + 1; ?> /
<?php echo $nbpages; ?>