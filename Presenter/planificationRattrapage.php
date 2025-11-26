<meta charset="UTF-8">
<?php
// Classe permettant la planification des rattrapages par les professeurs
class planificationRattrapage
{
    private $db;
    private $userId;
    private $lesDs;
    private $emailService;

    //constructeur
    public function __construct(int $id)
    {
        require_once __DIR__ . '/../Model/database.php';
        require_once __DIR__ . '/../Model/email.php';
        $this->db = Database::getInstance();
        $this->userId = $this->linkTeacherUser($id);
        $this->emailService = new EmailService();
    }
    private function linkTeacherUser(int $id)
    {
        $query = "SELECT teachers.id as id
        FROM users LEFT JOIN teachers ON teachers.email = users.email
        WHERE users.id = " . $id;
        $result = $this->db->select($query);
        return $result[0]['id'];
    }

    public function getLesDs()
    {
        $query = "SELECT DISTINCT cs.id, cs.course_date, cs.start_time, r.label
        FROM course_slots cs
        INNER JOIN absences a ON a.course_slot_id = cs.id
        LEFT JOIN resources r ON cs.resource_id = r.id
        LEFT JOIN makeups m ON m.absence_id = a.id
        WHERE cs.teacher_id = " . intval($this->userId) . " 
            AND cs.is_evaluation = true
            AND a.justified = true
            AND m.id IS NULL
        ORDER BY cs.course_date DESC";

        $result = $this->db->select($query);

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
        WHERE cs.id = " . intval($dsId) . " 
            AND a.justified = true
            AND m.id IS NULL
        ORDER BY cs.course_date DESC";

        $result = $this->db->select($query);

        return $result;
    }
    public function insererRattrapage($idAbs, $evalId, $studentId, $dateRattrapage, $roomId = null, $durationMinutes = null, $comment = null)
    {
        // Insérer le nouveau rattrapage
        $insertQuery = "INSERT INTO makeups (absence_id, evaluation_slot_id, student_identifier, scheduled, makeup_date, room_id, duration_minutes, comment) 
                        VALUES (:absence_id, :evaluation_slot_id, :student_identifier, true, :makeup_date, :room_id, :duration_minutes, :comment)";
        $insertParams = [
            ':absence_id' => $idAbs,
            ':evaluation_slot_id' => $evalId,
            ':student_identifier' => $studentId,
            ':makeup_date' => $dateRattrapage,
            ':room_id' => $roomId,
            ':duration_minutes' => $durationMinutes,
            ':comment' => $comment
        ];

        $this->db->execute($insertQuery, $insertParams);

        // Send email notification to student with room and duration info
        $this->sendMakeupNotificationEmail($studentId, $evalId, $dateRattrapage, $roomId, $durationMinutes, $comment);

        return true;
    }

    /**
     * Send email notification to student about makeup session
     */
    private function sendMakeupNotificationEmail($studentId, $evalId, $makeupDate, $roomId = null, $durationMinutes = null, $comment = null)
    {
        try {
            // Get student information
            $studentQuery = "SELECT u.email, u.first_name, u.last_name 
                            FROM users u 
                            WHERE u.identifier = :identifier";
            $student = $this->db->selectOne($studentQuery, [':identifier' => $studentId]);

            if (!$student || empty($student['email'])) {
                error_log("Cannot send makeup email: no email found for student {$studentId}");
                return;
            }

            // Get course information (original evaluation)
            $courseQuery = "SELECT cs.course_date, cs.start_time, cs.end_time, cs.duration_minutes as original_duration,
                                  r.label as resource_label, cs.course_type
                           FROM course_slots cs
                           LEFT JOIN resources r ON cs.resource_id = r.id
                           WHERE cs.id = :eval_id";
            $course = $this->db->selectOne($courseQuery, [':eval_id' => $evalId]);

            if (!$course) {
                error_log("Cannot send makeup email: course not found for eval_id {$evalId}");
                return;
            }

            // Get makeup room information if roomId is provided
            $makeupRoomName = 'À définir';
            if ($roomId) {
                $roomQuery = "SELECT code FROM rooms WHERE id = :room_id";
                $room = $this->db->selectOne($roomQuery, [':room_id' => $roomId]);
                if ($room) {
                    $makeupRoomName = htmlspecialchars($room['code']);
                }
            }

            // Determine duration for the makeup (use provided duration or original course duration)
            $makeupDuration = $durationMinutes ?? $course['original_duration'] ?? null;
            $durationText = $makeupDuration ? $makeupDuration . ' minutes' : 'Non spécifiée';

            $firstName = htmlspecialchars($student['first_name']);
            $lastName = htmlspecialchars($student['last_name']);
            $resourceLabel = htmlspecialchars($course['resource_label'] ?? 'Non spécifié');
            $courseType = htmlspecialchars($course['course_type'] ?? 'DS');
            $courseDate = date('d/m/Y', strtotime($course['course_date']));
            $startTime = substr($course['start_time'], 0, 5);
            $endTime = substr($course['end_time'], 0, 5);
            $makeupDateFormatted = date('d/m/Y', strtotime($makeupDate));
            $commentText = $comment ? htmlspecialchars($comment) : '';

            $subject = "Rattrapage planifié - {$resourceLabel}";
            $body = $this->generateMakeupEmailBody(
                $firstName,
                $lastName,
                $resourceLabel,
                $courseType,
                $courseDate,
                $startTime,
                $endTime,
                $makeupDateFormatted,
                $makeupRoomName,
                $durationText,
                $commentText
            );

            $result = $this->emailService->sendEmail($student['email'], $subject, $body, true);

            if (!$result['success']) {
                error_log("Failed to send makeup email to {$student['email']}: " . $result['message']);
            }
        } catch (Exception $e) {
            error_log("Error sending makeup notification email: " . $e->getMessage());
        }
    }

    /**
     * Generate HTML email body for makeup notification
     */
    private function generateMakeupEmailBody($firstName, $lastName, $resource, $courseType, $courseDate, $startTime, $endTime, $makeupDate, $room, $duration, $comment)
    {
        $commentSection = $comment ? "<div class='info-box'><strong>Commentaire de l'enseignant :</strong><p>{$comment}</p></div>" : "";

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #1976d2; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-top: none; }
        .footer { background-color: #333; color: white; padding: 15px; text-align: center; font-size: 0.9em; border-radius: 0 0 5px 5px; }
        .info-box { background-color: #e3f2fd; border-left: 4px solid #1976d2; padding: 15px; margin: 15px 0; }
        .warning-box { background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
        .detail-table { width: 100%; margin: 15px 0; background-color: white; border: 1px solid #ddd; }
        .detail-table td { padding: 10px; border-bottom: 1px solid #eee; }
        .detail-table td:first-child { font-weight: bold; width: 40%; background-color: #f5f5f5; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📅 Rattrapage Planifié</h1>
        </div>
        <div class="content">
            <p>Bonjour {$firstName} {$lastName},</p>
            
            <p>Un rattrapage a été planifié pour l'évaluation à laquelle vous étiez absent(e).</p>
            
            <div class="info-box">
                <h3 style="margin-top: 0;">Détails de l'évaluation manquée</h3>
                <table class="detail-table">
                    <tr>
                        <td>Matière</td>
                        <td>{$resource}</td>
                    </tr>
                    <tr>
                        <td>Type</td>
                        <td>{$courseType}</td>
                    </tr>
                    <tr>
                        <td>Date</td>
                        <td>{$courseDate}</td>
                    </tr>
                    <tr>
                        <td>Horaire</td>
                        <td>{$startTime} - {$endTime}</td>
                    </tr>
                </table>
            </div>
            
            <div class="warning-box">
                <h3 style="margin-top: 0;">⚠️ Rattrapage à effectuer</h3>
                <table class="detail-table">
                    <tr>
                        <td>Date du rattrapage</td>
                        <td><strong>{$makeupDate}</strong></td>
                    </tr>
                    <tr>
                        <td>Salle</td>
                        <td>{$room}</td>
                    </tr>
                    <tr>
                        <td>Durée</td>
                        <td>{$duration}</td>
                    </tr>
                </table>
            </div>
            
            {$commentSection}
            
            <p><strong>Important :</strong></p>
            <ul>
                <li>Veuillez vous présenter à l'heure indiquée</li>
                <li>Apportez le matériel nécessaire pour l'évaluation</li>
                <li>En cas d'empêchement, contactez immédiatement votre enseignant</li>
            </ul>
            
            <p>En cas de questions, n'hésitez pas à contacter votre enseignant ou le service de scolarité.</p>
        </div>
        <div class="footer">
            <p>Gestion des Absences - UPHF</p>
            <p>Cet email est envoyé automatiquement, merci de ne pas y répondre.</p>
        </div>
    </div>
</body>
</html>
HTML;
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