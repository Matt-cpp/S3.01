<meta charset="UTF-8">
<?php
class teacherTable {
    private $page;
    private $db;
    private $userId;
    private $ratrapage;
    private $nombrepages;

    //constructeur
    public function __construct(int $id) {
        $this->page = 0;
        require_once __DIR__ . '/../Model/database.php';
        $this->db = Database::getInstance();
        $this->userId = $id;
        $this->ratrapage = false;
        $this->nombrepages = $this->getTotalPages();
    }
    // sert a faire la requete principale du tableau
    public function getData($page,$ratrapage) {
        $offset = $page * 5;
        // ratrpage sert a modifier la requete pour afficher es étudiants a faire rattraper
        if (!($ratrapage)) {
            $query = "SELECT users.first_name,users.last_name,resources.label,course_slots.course_date,absences.status  
            FROM absences LEFT JOIN users ON absences.student_identifier = users.identifier 
            LEFT JOIN course_slots ON absences.course_slot_id=course_slots.id
            LEFT JOIN resources ON course_slots.resource_id=resources.id
            LEFT JOIN teachers ON resources.teacher_id=teachers.id
            WHERE teachers.user_id = :userId AND absences.justified = false
            ORDER BY course_slots.course_date DESC, absences.id ASC LIMIT 5 OFFSET :offset";
            return $this->db->select($query, ['userId' => $this->userId, 'offset' => $offset]);
        }
        else {
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
    }
// renvoie le nombre total de pages
    public function getTotalPages() {
        if (!(this->ratrapage)) {
        $result = $this->db->select("SELECT COUNT(*) as count FROM absences");
        return ceil($result[0]['count'] / 5);
        }
        else{
            $result = $this->db->select("SELECT COUNT(*) as count FROM absences 
            LeFT JOIN makeups ON absences.id = makeups.absence_id
            WHERE justified = true and makeups.scheduled = false");
            return ceil($result[0]['count'] / 5);
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

$test = new backendTableauDeBord();

echo "Nombre d'absences aujourd'hui : ";
echo $test->todayAbs();
echo "<br>";
echo "Nombre d'absences non justifiées : ";
echo $test->unjustifiedAbs();
echo "<br>";
echo "Nombre d'absences ce mois-ci : ";
echo $test->thisMonthAbs();
echo "<br>";

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
        echo "<td>" . htmlspecialchars($cell) . "</td>";
    }
    echo "</tr>";
}
$nbpages = $test->getTotalPages();?>
<br>
Current Page: <?php echo $test->getCurrentPage() + 1; ?> /
<?php echo $nbpages; ?>
