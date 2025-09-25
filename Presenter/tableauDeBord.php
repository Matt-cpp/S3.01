<?php
class backendTableauDeBord {
    private $page;
    private $alldata;
        public
    function __construct() {
            require_once __DIR__ . '/../Model/database.php';
            $this->page = 0;
            $db = getDatabase();
            $allData = $db->select('SELECT * FROM absences');
        }
    public function getData($page) {
        require_once __DIR__ . '/../Model/database.php';
        $db = getDatabase();
        $offset = $page * 5;
        $query = "SELECT student_identifier,course_slot_id,status,justified FROM absences order by updated_at desc limit 5 offset $offset";
        return $db->select($query);
    }

    public function getTotalPages() {
        require_once __DIR__ . '/../Model/database.php';
        $db = getDatabase();
        $result = $db->select("SELECT COUNT(*) as count FROM absences");
        return ceil($result[0]['count'] / 5);
    }
    public function setPage($page) {
        if ($page >= 0 && $page < $this->getTotalPages()) {
            $this->page = $page;
        }
    }
}
?>

<! a incorporer dans les fonctions php >
<?php  

$pages = isset($_GET['page']) ? (int)$_GET['page'] : 0;  
$nextPage = $pages + 1;
$prevPage = $pages > 0 ? $pages - 1 : 0;
?>

<a href="?page=<?php echo $nextPage; ?>">
    <button type="button">next</button>
</a>
<a href="?page=<?php echo $prevPage; ?>">
    <button type="button">previous</button>
</a>
<br>

<! les fonctions qui fonctionnent >
<?php
require_once __DIR__ . '/../Model/database.php';
$test=new backendTableauDeBord();
$f=$test->getData(0);
echo json_encode($f);
$nbpages = $test->getTotalPages();