<?php

require_once __DIR__ . '/database.php';

class AbsenceMonitoringModel
{
    private $db;

    public function __construct()
    {
        $this->db = getDatabase();
    }

    /**
     * Get all students with ongoing absences (no class attendance detected yet)
     * Only considers school days (Monday-Friday) and school hours (8AM-5PM)
     * Excludes students who have already submitted justifications for their absences
     */
    public function getStudentsWithOngoingAbsences()
    {
        $query = "
            SELECT 
                a.student_identifier,
                MIN(cs.course_date) as absence_start_date,
                MAX(cs.course_date) as absence_end_date,
                MAX(cs.course_date) as last_absence_date,
                COUNT(DISTINCT a.id) as total_absences,
                u.email,
                u.first_name,
                u.last_name
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            JOIN users u ON a.student_identifier = u.identifier
            WHERE a.status IN ('absent', 'unjustified')
            AND a.justified = FALSE
            AND EXTRACT(DOW FROM cs.course_date) BETWEEN 1 AND 5
            -- Exclude students who already have a justified monitoring record
            AND NOT EXISTS (
                SELECT 1 
                FROM absence_monitoring am
                WHERE am.student_identifier = a.student_identifier
                AND am.is_justified = TRUE
                AND cs.course_date BETWEEN am.absence_period_start AND am.absence_period_end
            )
            GROUP BY a.student_identifier, u.email, u.first_name, u.last_name
            HAVING MAX(cs.course_date) >= CURRENT_DATE - INTERVAL '7 days'
        ";

        try {
            return $this->db->select($query);
        } catch (Exception $e) {
            error_log("Error fetching students with ongoing absences: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if a student has returned to class after their last absence
     * A student is considered "returned" if they have no absences after their last recorded absence
     * and we're past the end of school day (5PM)
     */
    public function hasStudentReturnedToClass($studentIdentifier, $lastAbsenceDate)
    {
        // Check if we're past school hours for the last absence date
        $now = new DateTime();
        $lastAbsence = new DateTime($lastAbsenceDate);
        $endOfSchoolDay = clone $lastAbsence;
        $endOfSchoolDay->setTime(17, 0, 0); // 5PM

        // If the last absence was today and it's not past 5PM yet, student hasn't "returned"
        if ($lastAbsence->format('Y-m-d') === $now->format('Y-m-d') && $now < $endOfSchoolDay) {
            return false;
        }

        // If last absence was today but it's after 5PM, or if it was a previous day
        // Check if there are any more recent absences
        $query = "
            SELECT COUNT(*) as absence_count
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            WHERE a.student_identifier = :student_identifier
            AND cs.course_date > :last_absence_date
            AND a.status IN ('absent', 'unjustified')
            AND EXTRACT(DOW FROM cs.course_date) BETWEEN 1 AND 5
        ";

        try {
            $result = $this->db->selectOne($query, [
                ':student_identifier' => $studentIdentifier,
                ':last_absence_date' => $lastAbsenceDate
            ]);

            // Student has returned if there are no new absences after their last absence date
            // and we're past the school day
            return $result && $result['absence_count'] == 0;
        } catch (Exception $e) {
            error_log("Error checking student return status: " . $e->getMessage());
            return false;
        }
    }

    //Record that a student has returned to class
    public function recordStudentReturn($studentIdentifier, $absenceStartDate, $absenceEndDate, $lastAbsenceDate)
    {
        $query = "
            INSERT INTO absence_monitoring 
            (student_identifier, absence_period_start, absence_period_end, last_absence_date, return_detected_at, updated_at)
            VALUES (:student_identifier, :absence_start, :absence_end, :last_absence, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON CONFLICT (student_identifier, absence_period_start, absence_period_end)
            DO UPDATE SET
                return_detected_at = CURRENT_TIMESTAMP,
                last_absence_date = EXCLUDED.last_absence_date,
                updated_at = CURRENT_TIMESTAMP
            RETURNING id
        ";

        try {
            $result = $this->db->selectOne($query, [
                ':student_identifier' => $studentIdentifier,
                ':absence_start' => $absenceStartDate,
                ':absence_end' => $absenceEndDate,
                ':last_absence' => $lastAbsenceDate
            ]);
            return $result ? $result['id'] : null;
        } catch (Exception $e) {
            error_log("Error recording student return: " . $e->getMessage());
            return null;
        }
    }

    //Get students who have returned but haven't been notified yet
    public function getStudentsAwaitingReturnNotification()
    {
        $query = "
            SELECT 
                am.id,
                am.student_identifier,
                am.absence_period_start,
                am.absence_period_end,
                am.last_absence_date,
                am.return_detected_at,
                u.email,
                u.first_name,
                u.last_name
            FROM absence_monitoring am
            JOIN users u ON am.student_identifier = u.identifier
            WHERE am.return_notification_sent = FALSE
            AND am.return_detected_at IS NOT NULL
            AND u.email IS NOT NULL
            ORDER BY am.return_detected_at ASC
        ";

        try {
            return $this->db->select($query);
        } catch (Exception $e) {
            error_log("Error fetching students awaiting return notification: " . $e->getMessage());
            return [];
        }
    }

    //Mark return notification as sent
    public function markReturnNotificationSent($monitoringId)
    {
        $query = "
            UPDATE absence_monitoring
            SET return_notification_sent = TRUE,
                return_notification_sent_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ";

        try {
            return $this->db->execute($query, [':id' => $monitoringId]) > 0;
        } catch (Exception $e) {
            error_log("Error marking return notification sent: " . $e->getMessage());
            return false;
        }
    }

    //Get students who need a reminder (24h after return notification, still not justified)
    public function getStudentsNeedingReminder()
    {
        $query = "
            SELECT 
                am.id,
                am.student_identifier,
                am.absence_period_start,
                am.absence_period_end,
                am.return_notification_sent_at,
                u.email,
                u.first_name,
                u.last_name
            FROM absence_monitoring am
            JOIN users u ON am.student_identifier = u.identifier
            WHERE am.return_notification_sent = TRUE
            AND am.reminder_notification_sent = FALSE
            AND am.is_justified = FALSE
            AND am.return_notification_sent_at < CURRENT_TIMESTAMP - INTERVAL '24 hours'
            AND u.email IS NOT NULL
            ORDER BY am.return_notification_sent_at ASC
        ";

        try {
            return $this->db->select($query);
        } catch (Exception $e) {
            error_log("Error fetching students needing reminder: " . $e->getMessage());
            return [];
        }
    }

    //Mark reminder notification as sent
    public function markReminderNotificationSent($monitoringId)
    {
        $query = "
            UPDATE absence_monitoring
            SET reminder_notification_sent = TRUE,
                reminder_notification_sent_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ";

        try {
            return $this->db->execute($query, [':id' => $monitoringId]) > 0;
        } catch (Exception $e) {
            error_log("Error marking reminder notification sent: " . $e->getMessage());
            return false;
        }
    }

    //Check if student has justified their absences for a given period
    public function updateJustificationStatus($studentIdentifier, $absenceStartDate, $absenceEndDate)
    {
        // Check if there's a proof for this period
        $query = "
            SELECT COUNT(*) as proof_count
            FROM proof p
            WHERE p.student_identifier = :student_identifier
            AND p.absence_start_date <= :absence_end
            AND p.absence_end_date >= :absence_start
            AND p.status IN ('accepted', 'pending', 'under_review')
        ";

        try {
            $result = $this->db->selectOne($query, [
                ':student_identifier' => $studentIdentifier,
                ':absence_start' => $absenceStartDate,
                ':absence_end' => $absenceEndDate
            ]);

            $isJustified = $result && $result['proof_count'] > 0;

            // Update monitoring record
            if ($isJustified) {
                $updateQuery = "
                    UPDATE absence_monitoring
                    SET is_justified = TRUE,
                        justified_at = CURRENT_TIMESTAMP,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE student_identifier = :student_identifier
                    AND absence_period_start = :absence_start
                    AND absence_period_end = :absence_end
                ";

                $this->db->execute($updateQuery, [
                    ':student_identifier' => $studentIdentifier,
                    ':absence_start' => $absenceStartDate,
                    ':absence_end' => $absenceEndDate
                ]);
            }

            return $isJustified;
        } catch (Exception $e) {
            error_log("Error updating justification status: " . $e->getMessage());
            return false;
        }
    }

    //Mark all overlapping monitoring records as justified when a proof is submitted
    //This should be called immediately after a proof is submitted
    public function markAsJustifiedByProof($studentIdentifier, $proofStartDate, $proofEndDate)
    {
        $query = "
            UPDATE absence_monitoring
            SET is_justified = TRUE,
                justified_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE student_identifier = :student_identifier
            AND (
                -- Monitoring period overlaps with proof period
                (absence_period_start <= :proof_end AND absence_period_end >= :proof_start)
                OR
                -- Monitoring period is within proof period
                (absence_period_start >= :proof_start AND absence_period_end <= :proof_end)
                OR
                -- Proof period is within monitoring period
                (:proof_start >= absence_period_start AND :proof_end <= absence_period_end)
            )
            AND is_justified = FALSE
        ";

        try {
            $updated = $this->db->execute($query, [
                ':student_identifier' => $studentIdentifier,
                ':proof_start' => $proofStartDate,
                ':proof_end' => $proofEndDate
            ]);

            if ($updated > 0) {
                error_log("Marked {$updated} monitoring records as justified for student {$studentIdentifier}");
            }

            return $updated;
        } catch (Exception $e) {
            error_log("Error marking monitoring records as justified: " . $e->getMessage());
            return 0;
        }
    }

    //Get absence details for a student and period
    public function getAbsenceDetails($studentIdentifier, $startDate, $endDate)
    {
        $query = "
            SELECT 
                cs.course_date,
                cs.start_time,
                cs.end_time,
                cs.course_type,
                r.label as course_name,
                rm.code as room_code
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            LEFT JOIN resources r ON cs.resource_id = r.id
            LEFT JOIN rooms rm ON cs.room_id = rm.id
            WHERE a.student_identifier = :student_identifier
            AND cs.course_date BETWEEN :start_date AND :end_date
            AND a.status IN ('absent', 'unjustified')
            AND a.justified = FALSE
            ORDER BY cs.course_date ASC, cs.start_time ASC
        ";

        try {
            return $this->db->select($query, [
                ':student_identifier' => $studentIdentifier,
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ]);
        } catch (Exception $e) {
            error_log("Error fetching absence details: " . $e->getMessage());
            return [];
        }
    }

    //Clean up old monitoring records (older than 30 days)
    public function cleanupOldRecords()
    {
        $query = "
            DELETE FROM absence_monitoring
            WHERE created_at < CURRENT_TIMESTAMP - INTERVAL '30 days'
            AND (is_justified = TRUE OR reminder_notification_sent = TRUE)
        ";

        try {
            $deleted = $this->db->execute($query);
            error_log("Cleaned up {$deleted} old absence monitoring records");
            return $deleted;
        } catch (Exception $e) {
            error_log("Error cleaning up old records: " . $e->getMessage());
            return 0;
        }
    }
}
