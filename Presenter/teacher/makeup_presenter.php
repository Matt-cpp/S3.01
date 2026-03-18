<?php

declare(strict_types=1);

// Handles makeup scheduling for teachers
class MakeupSchedulingPresenter
{
    private TeacherDataModel $teacherModel;
    private UserModel $userModel;
    private int $userId;
    private EmailService $emailService;

    public function __construct(int $id)
    {
        require_once __DIR__ . '/../../Model/TeacherDataModel.php';
        require_once __DIR__ . '/../../Model/UserModel.php';
        require_once __DIR__ . '/../../Model/email.php';
        $this->teacherModel = new TeacherDataModel();
        $this->userModel = new UserModel();
        $this->userId = $this->linkTeacherUser($id);
        $this->emailService = new EmailService();
    }

    // Link the teacher ID with the connected user ID via email
    private function linkTeacherUser(int $id): int
    {
        return (int) ($this->teacherModel->getTeacherIdByUserId($id) ?? 0);
    }

    // Retrieve exams with justified absences that haven't been made up yet
    public function getExams(): array
    {
        return $this->teacherModel->getMakeupEligibleExams($this->userId);
    }

    // Retrieve absent students who haven't made up yet
    public function getStudents(int $examId): array
    {
        return $this->teacherModel->getMakeupEligibleStudents($examId);
    }

    /**
     * Retrieve all available rooms
     */
    public function getAllRooms(): array
    {
        return $this->teacherModel->getAllRooms();
    }

    /**
     * Retrieve or create a room by code
     * @return int|null The room ID
     */
    public function getOrCreateRoom(?string $roomId, ?string $newRoomCode): ?int
    {
        if ($roomId && $roomId !== 'new') {
            return intval($roomId);
        }

        if (!$newRoomCode) {
            return null;
        }

        $existingRoomId = $this->teacherModel->findRoomIdByCode($newRoomCode);
        if ($existingRoomId !== null) {
            return $existingRoomId;
        }

        return $this->teacherModel->createRoom($newRoomCode);
    }

    /**
     * Schedule makeups for all students of an exam
     * @return array ['success' => bool, 'message' => string, 'count' => int]
     */
    public function scheduleMakeups(int $examId, string $date, string $duration, ?string $roomId, ?string $newRoomCode, ?string $comment): array
    {
        if (!$examId || !$date || !$duration) {
            return ['success' => false, 'message' => 'Please fill in all required fields.', 'count' => 0];
        }

        $roomResolvedId = $this->getOrCreateRoom($roomId, $newRoomCode);
        $students = $this->getStudents($examId);
        $count = 0;

        foreach ($students as $student) {
            $this->insertMakeupSession(
                $student['id'],
                $examId,
                $student['identifier'],
                $date,
                $roomResolvedId,
                intval($duration),
                $comment
            );
            $count++;
        }

        return ['success' => true, 'message' => "Makeup scheduled successfully for {$count} student(s)!", 'count' => $count];
    }

    public function insertMakeupSession(int $absenceId, int $evalId, string $studentId, string $makeupDate, ?int $roomId = null, ?int $durationMinutes = null, ?string $comment = null): bool
    {
        $this->teacherModel->createMakeupSession(
            $absenceId,
            $evalId,
            $studentId,
            $makeupDate,
            $roomId,
            $durationMinutes,
            $comment
        );

        // Send email notification to student with room and duration info
        $this->sendMakeupNotificationEmail($studentId, $evalId, $makeupDate, $roomId, $durationMinutes, $comment);

        return true;
    }

    /**
     * Send email notification to student about makeup session
     */
    private function sendMakeupNotificationEmail(string $studentId, int $evalId, string $makeupDate, ?int $roomId = null, ?int $durationMinutes = null, ?string $comment = null): void
    {
        try {
            // Get student information
            $student = $this->teacherModel->getStudentContactByIdentifier($studentId);

            if (!$student || empty($student['email'])) {
                error_log("Cannot send makeup email: no email found for student {$studentId}");
                return;
            }

            // Get course information (original evaluation)
            $course = $this->teacherModel->getCourseSlotForMakeupEmail($evalId);

            if (!$course) {
                error_log("Cannot send makeup email: course not found for eval_id {$evalId}");
                return;
            }

            // Get makeup room information if roomId is provided
            $makeupRoomName = 'À définir';
            if ($roomId) {
                $roomCode = $this->teacherModel->getRoomCodeById($roomId);
                if ($roomCode) {
                    $makeupRoomName = htmlspecialchars($roomCode);
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
    private function generateMakeupEmailBody(string $firstName, string $lastName, string $resource, string $courseType, string $courseDate, string $startTime, string $endTime, string $makeupDate, string $room, string $duration, string $commentText): string
    {
        $commentSection = $commentText ? "<div class='info-box'><strong>Commentaire de l'enseignant :</strong><p>{$commentText}</p></div>" : "";

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
