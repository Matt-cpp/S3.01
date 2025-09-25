<?php
class backendTableauDeBord {
    private $page;
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

<?php
require_once __DIR__ . '/../Model/database.php';
$db = getDatabase();
$offset = $pages * 5;
$query = "SELECT student_identifier,course_slot_id,status,justified FROM absences order by updated_at desc limit 5 offset $offset";
$brutData=$db->select($query);
$nbpages = $db->select("SELECT COUNT(*) as count FROM absences");
$nbpages = ceil($nbpages[0]['count'] / 5);
$names = array_column($brutData, 'student_identifier');
$placeholders = implode(',', array_fill(0, count($names), '?'));
echo json_encode($brutData);
echo "<br>";
echo "Page: " . $pages+1;
echo "/" . $nbpages;