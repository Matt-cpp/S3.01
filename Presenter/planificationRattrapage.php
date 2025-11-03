<meta charset="UTF-8">
<?php
class tableRatrapage{
    private $db;
    private $userId;
    private $lesDs;

    //constructeur
    public function __construct(int $id) {
        require_once __DIR__ . '/../Model/database.php';
        $this->db = Database::getInstance();
        $this->userId = $id;
    }
    
    // Fonction pour récupérer les DS non rattrapés - VERSION OPTIMISÉE
    public function getLesDs(){
        $start = microtime(true);
        error_log("=== DEBUT getLesDs - userId: " . $this->userId);
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
        
        $duration = microtime(true) - $start;
        error_log("=== FIN getLesDs - Durée: " . round($duration, 2) . "s - Lignes: " . count($result));
        
        return $result;
    }
    
    // Fonction pour récupérer les élèves absents non rattrapés
    public function getLesEleves($dsId){
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
    
    public function majBdd($idAbs, $evalId, $studentId, $dateRattrapage){
        $query = "INSERT INTO makeups (absence_id, evaluation_slot_id, student_identifier, is_completed, makeup_date) 
                  VALUES (:absence_id, :evaluation_slot_id, :student_identifier, false, :makeup_date)";
        $params = [
            ':absence_id' => $idAbs,
            ':evaluation_slot_id' => $evalId,
            ':student_identifier' => $studentId,
            ':makeup_date' => $dateRattrapage
        ];
        $this->db->execute($query, $params);
    }
    public function creerRattrapagesPourDs($dsId, $makeupDate, $comment = null) {
    // Récupérer tous les élèves absents non rattrapés
    $elevesAbsents = $this->getLesEleves($dsId);
    
    if (empty($elevesAbsents)) {
        return ['success' => 0, 'errors' => 0, 'details' => ['Aucun élève absent à rattraper']];
    }
    
    $this->db->execute("BEGIN");
    $success = 0;
    $errors = 0;
    $details = [];
    
    try {
        foreach ($elevesAbsents as $eleve) {
            $result = $this->insererRattrapage(
                $eleve['id'],              // absence_id
                $dsId,                     // evaluation_slot_id
                $eleve['identifier'],      // student_identifier
                $makeupDate,
                $comment
            );
            
            if ($result) {
                $success++;
                $details[] = "✅ {$eleve['first_name']} {$eleve['last_name']}";
            } else {
                $errors++;
                $details[] = "❌ {$eleve['first_name']} {$eleve['last_name']} (déjà rattrapé ?)";
            }
        }
        
        $this->db->execute("COMMIT");
        
    } catch (Exception $e) {
        $this->db->execute("ROLLBACK");
        $details[] = "ERREUR CRITIQUE : " . $e->getMessage();
        error_log("ERREUR creerRattrapagesPourDs: " . $e->getMessage());
    }
    
    return [
        'success' => $success,
        'errors' => $errors,
        'details' => $details
    ];
}
}

// Test
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
    $lesEleves = $test->getLesEleves(92);
    
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
?>