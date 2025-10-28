<meta charset="UTF-8">
<?php
class tableRatrapage{
    private $db;
    private $userId;
    private $data;
    //constructeur
    public function __construct(int $id) {
        require_once __DIR__ . '/../Model/database.php';
        $this->db = Database::getInstance();
        $this->userId = $id;
        $this->data = $this->getData();
    }

//reqeuete principale du tableau
public function getData(){
    $userId = (int)$this->userId;

    $query = "SELECT absences.id ,course_slots.id as courseId,users.identifier,users.first_name, users.last_name,resources.label, course_slots.course_date
    FROM absences LEFT JOIN course_slots ON absences.course_slot_id = course_slots.id
    LEFT JOIN users ON absences.student_identifier = users.identifier
    LefT JOIN resources ON course_slots.resource_id = resources.id
    WHERE course_slots.teacher_id=".$this->userId." AND absences.status='excused' 
        AND course_slots.is_evaluation=true
        ORDER BY course_slots.course_date DESC";
    return $this->db->select($query);
}


    // Tableau
    public function laTable() {
        // Récupération des données brutes
        $donnees = $this->data;
        $tableau=[];
        // Construction du tableau HTML
        $tableau = "<table border='1'>  
        <tr>
        <th> absid</th>
            <th>First Name</th>
            <th>Last Name</th>
            <th>Resource</th>
            <th>Course Date</th>
        </tr>";
        foreach ($donnees as $ligne) {
            $absId = htmlspecialchars($ligne['id']);
            $tableau .= "
            <tr>
                <td>$absId</td>
                <td>" . htmlspecialchars($ligne['first_name']) . "</td>
                <td>" . htmlspecialchars($ligne['last_name']) . "</td>
                <td>" . htmlspecialchars($ligne['label']) . "</td>
                <td>" . htmlspecialchars($ligne['course_date']) . "</td>
                <td>
                    <form method='POST'>
                        <input type='hidden' name='id' value='$absId'>
                        <button type='submit'>Planfier le rattrapage</button>
                    </form>
                </td>
            </tr>";
        }
        $tableau .= "</table>";
        return $tableau;
    }

    public function creationRattrapage($abs_id){
        echo "Création d'un rattrapage pour l'absence ID : " . htmlspecialchars($abs_id);
    }
}

$test = new tableRatrapage(3);
echo $test->laTable();


if (isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $test->creationRattrapage($id);
    ?>
    <form method="POST" action="">
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <label for="date">Date :</label>
        <input 
            type="date" 
            id="date" 
            name="date" 
            min="<?php echo date('Y-m-d'); ?>" 
            required
        >
        <button type="submit">Valider</button>
    </form>
    <?php

    // 🔽 On met la vérification de la date ici, pas dehors
    if (isset($_POST['date'])) {
        $date = $_POST['date'];
        echo "Rattrapage planifié pour l'absence ID : " . htmlspecialchars($id) .
             " à la date : " . htmlspecialchars($date);
    }
}
?>