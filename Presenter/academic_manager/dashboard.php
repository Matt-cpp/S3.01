<meta charset="UTF-8">
<?php
/**
 * Fichier: dashboard.php
 * 
 * Présentateur du tableau de bord - Gère l'affichage du tableau de bord principal des absences.
 * Fournit des méthodes pour:
 * - Récupérer les absences récentes avec pagination (5 par page)
 * - Calculer les statistiques (absences du jour, du mois, non justifiées)
 * - Générer un tableau formaté pour l'affichage
 * - Gérer la navigation entre les pages
 * Utilisé par la page d'accueil des gestionnaires/administrateurs.
 */

class AcademicManagerDashboardPresenter
{
    private $page;
    private $alldata;
    private $db;
    //constructeur
    public function __construct()
    {
        $this->page = 0;
        require_once __DIR__ . '/../../Model/database.php';
        $this->db = Database::getInstance();
        $this->alldata = $this->db->select('SELECT * FROM absences');
    }
    // sert a faire la requete principale du tableau
    public function getData($page)
    {
        $offset = $page * 5;
        $query = "SELECT 
            course_slots.course_date,
            course_slots.start_time,
            course_slots.end_time,
            users.first_name,
            users.last_name,
            resources.label,
            course_slots.course_type,
            absences.status  
        FROM absences 
        LEFT JOIN users ON absences.student_identifier = users.identifier 
        LEFT JOIN course_slots ON absences.course_slot_id=course_slots.id
        LEFT JOIN resources ON course_slots.resource_id=resources.id
        ORDER BY course_slots.course_date DESC, course_slots.start_time DESC, absences.id ASC 
        LIMIT 5 OFFSET :offset";
        return $this->db->select($query, ['offset' => $offset]);
    }

    // Traduit le statut en français
    private function translateStatus($status)
    {
        $translations = [
            'absent' => 'Absent',
            'present' => 'Présent',
            'excused' => 'Excusé',
            'unjustified' => 'Non justifié'
        ];
        return $translations[$status] ?? ucfirst($status);
    }
    // renvoie le nombre total de pages
    public function getTotalPages()
    {
        $result = $this->db->select("SELECT COUNT(*) as count FROM absences");
        return ceil($result[0]['count'] / 5);
    }
    // sert a mettre a jour l'attribut page en posant des limites
    public function setPage($page)
    {
        if ($page >= 0 && $page < $this->getTotalPages()) {
            $this->page = $page;
        }
    }
    // fait avancer la page de 1 si possible
    public function nextPage()
    {
        if ($this->page < $this->getTotalPages() - 1) {
            $this->page++;
        }
    }
    // fait reculer la page de 1 si possible
    public function previousPage()
    {
        if ($this->page > 0) {
            $this->page--;
        }
    }
    //renvoie le numéro de page actuel
    public function getCurrentPage()
    {
        return $this->page;
    }
    //permet l'accès a la page suivante et précédente en posant des limites
    public function getNextPage()
    {
        return min($this->page + 1, $this->getTotalPages() - 1);
    }
    public function getPreviousPage()
    {
        return max($this->page - 1, 0);
    }
    // Statistiques
    public function todayAbs()
    {
        $query = "SELECT COUNT(*) as count FROM absences LEFT JOIN course_slots ON absences.course_slot_id=course_slots.id WHERE DATE(course_slots.course_date      ) = CURRENT_DATE";
        $res = $this->db->select($query);
        return $res[0]['count'];
    }
    public function unjustifiedAbs()
    {
        $query = "SELECT COUNT(*) as count FROM absences WHERE justified = false";
        $res = $this->db->select($query);
        return $res[0]['count'];
    }
    public function thisMonthAbs()
    {
        $query = "SELECT COUNT(*) as count FROM absences LEFT JOIN course_slots ON absences.course_slot_id=course_slots.id WHERE EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM CURRENT_DATE) AND EXTRACT(MONTH FROM course_slots.course_date) = EXTRACT(MONTH FROM CURRENT_DATE)";
        $res = $this->db->select($query);
        return $res[0]['count'];
    }
    // Tableau
    public function laTable()
    {
        // Récupération des données brutes
        $donnees = $this->getData($this->getCurrentPage());

        // Création du tableau final (sans les en-têtes, car ils sont déjà dans le HTML)
        $tableau = [];

        // Remplissage des données
        foreach ($donnees as $ligne) {
            // Format date (YYYY-MM-DD to DD/MM/YYYY)
            $date = date('d/m/Y', strtotime($ligne['course_date']));

            // Format time (HH:MM:SS to HH:MM)
            $time = substr($ligne['start_time'], 0, 5) . ' - ' . substr($ligne['end_time'], 0, 5);

            // Student name
            $student = $ligne['first_name'] . ' ' . $ligne['last_name'];

            // Course
            $course = $ligne['label'] ?? 'Non spécifié';

            // Course type (uppercase)
            $type = strtoupper($ligne['course_type'] ?? '');

            // Status translated
            $status = $this->translateStatus($ligne['status']);

            $tableau[] = [
                $date,
                $time,
                $student,
                $course,
                $type,
                $status
            ];
        }
        return $tableau;
    }
}
/*
$test = new AcademicManagerDashboardPresenter ();

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
*/