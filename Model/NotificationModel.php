<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

class NotificationModel
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? getDatabase();
    }

    public function createNotification(
        string $studentIdentifier,
        string $notificationType,
        string $subject,
        string $message,
        bool $sent
    ): bool {
        $sql = "INSERT INTO notifications (student_identifier, notification_type, subject, message, sent, sent_date)
                VALUES (:student_identifier, :notification_type, :subject, :message, :sent::boolean,
                        CASE WHEN :sent::boolean = TRUE THEN NOW() ELSE NULL END)";

        try {
            $this->db->execute($sql, [
                'student_identifier' => $studentIdentifier,
                'notification_type' => $notificationType,
                'subject' => $subject,
                'message' => $message,
                'sent' => $sent ? 'true' : 'false'
            ]);
            return true;
        } catch (Exception $e) {
            error_log('Error createNotification: ' . $e->getMessage());
            return false;
        }
    }
}
