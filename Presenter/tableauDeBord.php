<?php
class backendTableauDeBord {
    private $page;
    private $alldata;
    private $db;

    public function __construct() {
        $this->page = 0;
        require_once __DIR__ . '/../Model/database.php';
        $this->db = Database::getInstance();
        $this->alldata = $this->db->select('SELECT * FROM absences');
    }
    public function getData($page) {
        $offset = $page * 5;
        $query = "SELECT id,student_identifier,course_slot_id,status,justified FROM absences ORDER BY updated_at DESC, id ASC LIMIT 5 OFFSET :offset";
        return $this->db->select($query, ['offset' => $offset]);
    }

    public function getTotalPages() {
        $result = $this->db->select("SELECT COUNT(*) as count FROM absences");
        return ceil($result[0]['count'] / 5);
    }
    public function setPage($page) {
        if ($page >= 0 && $page < $this->getTotalPages()) {
            $this->page = $page;
        }
    }
    public function nextPage() {
        if ($this->page < $this->getTotalPages() - 1) {
            $this->page++;
        }
    }
    public function previousPage() {
        if ($this->page > 0) {
            $this->page--;
        }
    }
    public function getCurrentPage() {
        return $this->page;
    }

    public function getNextPage() {
        return min($this->page + 1, $this->getTotalPages() - 1);
    }

    public function getPreviousPage() {
        return max($this->page - 1, 0);
    }
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

$f=$test->getData($test->getCurrentPage());
echo json_encode($f);
$nbpages = $test->getTotalPages();?>
<br>
Current Page: <?php echo $test->getCurrentPage() + 1; ?> /
<?php echo $nbpages; ?>