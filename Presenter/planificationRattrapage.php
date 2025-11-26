<meta charset="UTF-8">
<?php
/**
 * Fichier: planificationRattrapage.php
 * 
 * Présentateur de planification de rattrapage - Gère la planification des rattrapages pour les enseignants.
 * Fournit des méthodes pour:
 * - Récupérer les DS/évaluations avec des absences justifiées non rattrapées
 * - Lister les étudiants absents à un DS donné
 * - Insérer une nouvelle séance de rattrapage
 * Utilisé par les enseignants pour organiser les rattrapages d'évaluations.
 */

class planificationRattrapage
{
    private $db;
    private $userId;
    private $lesDs;

    //constructeur
    public function __construct(int $id)
    {
        require_once __DIR__ . '/../Model/database.php';
        $this->db = Database::getInstance();
        $this->userId = $id;
    }

    public function getLesDs()
    {
        $query = "SELECT DISTINCT cs.*
        FROM course_slots cs
        INNER JOIN absences a ON a.course_slot_id = cs.id
        LEFT JOIN makeups m ON m.absence_id = a.id
        WHERE cs.teacher_id = :userId 
            AND cs.is_evaluation = true
            AND a.status = 'excused'
            AND m.id IS NULL
        ORDER BY cs.course_date";

        $result = $this->db->select($query, [':userId' => $this->userId]);

        return $result;
    }

    // Fonction pour récupérer les élèves absents non rattrapés
    public function getLesEleves($dsId)
    {
        $query = "SELECT a.id, cs.id as courseId, u.identifier, 
                  u.first_name, u.last_name, r.label, cs.course_date
        FROM absences a
        INNER JOIN course_slots cs ON a.course_slot_id = cs.id
        LEFT JOIN users u ON a.student_identifier = u.identifier
        LEFT JOIN resources r ON cs.resource_id = r.id
        LEFT JOIN makeups m ON m.absence_id = a.id
        WHERE cs.id = :dsId 
            AND a.status = 'excused'
            AND m.id IS NULL
        ORDER BY cs.course_date DESC";

        $result = $this->db->select($query, [':dsId' => $dsId]);

        return $result;
    }
    public function insererRattrapage($idAbs, $evalId, $studentId, $dateRattrapage, $comment = null)
    {
        // Insérer le nouveau rattrapage
        $insertQuery = "INSERT INTO makeups (absence_id, evaluation_slot_id, student_identifier, is_completed, makeup_date, comment) 
                        VALUES (:absence_id, :evaluation_slot_id, :student_identifier, false, :makeup_date, :comment)";
        $insertParams = [
            ':absence_id' => $idAbs,
            ':evaluation_slot_id' => $evalId,
            ':student_identifier' => $studentId,
            ':makeup_date' => $dateRattrapage,
            ':comment' => $comment
        ];

        $this->db->execute($insertQuery, $insertParams);
        return true;
    }
}



/*Test
try {
    $test = new tableRatrapage(2);
    
    echo "<h3>DS à faire rattraper :</h3>";
    $data = $test->getLesDs();
    
    if (empty($data)) {
        echo "Aucun DS à rattraper<br>";
    } else {
        foreach($data as $ligne){
            echo "DS ID: " . htmlspecialchars($ligne['id']) . "<br>";
        }
    }
    
    echo "<h3>Élèves absents pour le DS #2 :</h3>";
    $lesEleves = $test->getLesEleves(2);
    
    if (empty($lesEleves)) {
        echo "Aucun élève absent non rattrapé<br>";
    } else {
        foreach($lesEleves as $eleve){
            echo htmlspecialchars($eleve['first_name']) . " " . htmlspecialchars($eleve['last_name']) . "<br>";
        }
    }
    
} catch (Exception $e) {
    error_log("ERREUR : " . $e->getMessage());
    echo "Une erreur est survenue. Consultez les logs.";
}
?>*/