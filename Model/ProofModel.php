<?php

declare(strict_types=1);

/**
 * Proof model - Manages absence justifications in the database.
 * Provides methods to:
 * - Retrieve proof details
 * - Update proof status (accepted, rejected, under review)
 * - Update linked absences based on the decision
 * - Record rejection reasons
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/AbsenceMonitoringModel.php';

class ProofModel
{
    private Database $db;
    private AbsenceMonitoringModel $monitoringModel;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->monitoringModel = new AbsenceMonitoringModel();
    }

    //RÃ©cupÃ¨re les informations complÃ¨tes dâ€™un justificatif dâ€™absence
    // RÃ©cupÃ¨re les dÃ©tails d'un justificatif par son ID
    public function getProofDetails(int $proofId): ?array
    {
        $sql = "
    SELECT 
        p.id AS proof_id,
        p.student_identifier,
        p.absence_start_date,
        p.absence_end_date,
        p.main_reason,
        p.custom_reason,
        p.student_comment,
        p.status,
        p.submission_date,
        p.file_path,
        p.proof_files,
        u.last_name,
        u.first_name,
        g.label AS group_label
    FROM proof p
    LEFT JOIN users u ON LOWER(u.identifier) = LOWER(p.student_identifier)
    LEFT JOIN user_groups ug ON ug.user_id = u.id
    LEFT JOIN groups g ON g.id = ug.group_id
    WHERE p.id = :id
";

        try {
            $result = $this->db->selectOne($sql, ['id' => $proofId]);

            if ($result === false || $result === null || empty($result)) {
                error_log('ProofModel->getProofDetails: No result for proof_id=' . $proofId);
                return null;
            }

            // Retrieve start and end times via proof_absences table
            $sqlAbs = "SELECT cs.course_date, cs.start_time, cs.end_time
                FROM proof_absences pa
                JOIN absences a ON pa.absence_id = a.id
                JOIN course_slots cs ON a.course_slot_id = cs.id
                WHERE pa.proof_id = :proof_id
                ORDER BY cs.course_date ASC, cs.start_time ASC";
            $absences = $this->db->select($sqlAbs, [
                'proof_id' => $proofId
            ]);

            if ($absences && count($absences) > 0) {
                $first = $absences[0];
                $last = $absences[count($absences) - 1];
                $result['absence_start_datetime'] = $first['course_date'] . ' ' . $first['start_time'];
                $result['absence_end_datetime'] = $last['course_date'] . ' ' . $last['end_time'];
            }

            // Extract files from proof_files (JSONB) or file_path
            $result['files'] = $this->extractProofFiles($result);

            return $result;
        } catch (Exception $e) {
            error_log('Error ProofModel->getProofDetails: ' . $e->getMessage());
            return null;
        }
    }

    // Extract file list from proof_files (JSONB) or file_path
    private function extractProofFiles(array $proof): array
    {
        $files = [];

        // 1. Check the proof_files field (JSONB)
        if (!empty($proof['proof_files'])) {
            $jsonFiles = $proof['proof_files'];

            // If it's a JSON string, decode it
            if (is_string($jsonFiles)) {
                $decoded = json_decode($jsonFiles, true);
                if (is_array($decoded)) {
                    $jsonFiles = $decoded;
                }
            }

            // If it's an array, extract files with path and name
            if (is_array($jsonFiles)) {
                foreach ($jsonFiles as $file) {
                    if (is_array($file) && isset($file['path'])) {
                        // File with complete structure {path, name}
                        $files[] = [
                            'path' => $file['path'],
                            'name' => $file['name'] ?? basename($file['path'])
                        ];
                    } elseif (is_array($file) && isset($file['file_path'])) {
                        // File with alternative structure {file_path}
                        $files[] = [
                            'path' => $file['file_path'],
                            'name' => $file['name'] ?? basename($file['file_path'])
                        ];
                    } elseif (is_string($file)) {
                        // Simple file (just the path)
                        $files[] = [
                            'path' => $file,
                            'name' => basename($file)
                        ];
                    }
                }
            }
        }

        // 2. Fallback to file_path if proof_files is empty
        if (empty($files) && !empty($proof['file_path'])) {
            $files[] = [
                'path' => $proof['file_path'],
                'name' => basename($proof['file_path'])
            ];
        }

        return $files;
    }

    // Update proof status
    public function updateProofStatus(int $proofId, string $status): bool
    {
        try {
            // First, get the proof details to update monitoring
            $proofDetails = $this->getProofDetails($proofId);

            // Update proof status
            $sql = "UPDATE proof SET status = :status WHERE id = :id";
            $this->db->execute($sql, ['status' => $status, 'id' => $proofId]);

            // Update absence monitoring based on proof status
            if ($proofDetails && in_array($status, ['accepted', 'pending', 'under_review'])) {
                // Mark as justified
                $this->monitoringModel->markAsJustifiedByProof(
                    $proofDetails['student_identifier'],
                    $proofDetails['absence_start_date'],
                    $proofDetails['absence_end_date']
                );
            } elseif ($proofDetails && $status === 'rejected') {
                // If rejected, we need to reset the justified flag
                // so the student can receive reminders again
                $resetQuery = "
                    UPDATE absence_monitoring
                    SET is_justified = FALSE,
                        justified_at = NULL,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE student_identifier = :student_identifier
                    AND (
                        (absence_period_start <= :proof_end AND absence_period_end >= :proof_start)
                        OR (absence_period_start >= :proof_start AND absence_period_end <= :proof_end)
                        OR (:proof_start >= absence_period_start AND :proof_end <= absence_period_end)
                    )
                ";
                $this->db->execute($resetQuery, [
                    ':student_identifier' => $proofDetails['student_identifier'],
                    ':proof_start' => $proofDetails['absence_start_date'],
                    ':proof_end' => $proofDetails['absence_end_date']
                ]);
            }

            return true;
        } catch (Exception $e) {
            error_log('Error updateProofStatus: ' . $e->getMessage());
            return false;
        }
    }

    // Update absences linked to the proof based on the decision
    public function updateAbsencesForProof(string $studentIdentifier, string $startDate, string $endDate, string $decision): void
    {
        if ($decision === 'accepted') {
            $sql = "UPDATE absences a
            SET status = 'excused', justified = TRUE, updated_at = NOW()
            FROM course_slots cs
            WHERE a.course_slot_id = cs.id
              AND a.student_identifier = :student_identifier
              AND cs.course_date BETWEEN :start_date AND :end_date";
            $this->db->execute($sql, [
                'student_identifier' => $studentIdentifier,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
        } elseif ($decision === 'rejected' || $decision === 'request_info') {
            $sql = "UPDATE absences a
            SET status = 'absent', justified = FALSE, updated_at = NOW()
            FROM course_slots cs
            WHERE a.course_slot_id = cs.id
              AND a.student_identifier = :student_identifier
              AND cs.course_date BETWEEN :start_date AND :end_date";
            $this->db->execute($sql, [
                'student_identifier' => $studentIdentifier,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
        }
    }


    // Record rejection reason and comment in decision_history
    public function setRejectionReason(int $proofId, string $reason, string $comment = '', ?int $userId = null): bool
    {
        $this->db->beginTransaction();
        try {
            // Retrieve old status before modification
            $proof = $this->getProofDetails($proofId);
            $oldStatus = $proof ? $proof['status'] : null;

            // If no userId provided, try to use processed_by_user_id from the proof
            if ($userId === null) {
                try {
                    $row = $this->db->selectOne("SELECT processed_by_user_id FROM proof WHERE id = :id", ['id' => $proofId]);
                    if ($row && !empty($row['processed_by_user_id'])) {
                        $userId = $row['processed_by_user_id'];
                    }
                } catch (Exception $e) {
                    // ignore, userId will remain null
                }
            }

            // If still no user_id after attempt, use a configurable fallback (SYSTEM_USER_ID) or 1
            if ($userId === null) {
                $fallback = (int) (env('SYSTEM_USER_ID', '1') ?? 1);
                error_log('setRejectionReason: no user_id found, using fallback SYSTEM_USER_ID=' . $fallback);
                $userId = $fallback;
            }
            // Verify that userId exists in users table; otherwise try to get the first existing user
            try {
                $exists = $this->db->selectOne("SELECT id FROM users WHERE id = :id", ['id' => $userId]);
                if (!$exists) {
                    $first = $this->db->selectOne("SELECT id FROM users ORDER BY id LIMIT 1");
                    if ($first && isset($first['id'])) {
                        error_log('setRejectionReason: user_id ' . $userId . ' not found, falling back to user_id ' . $first['id']);
                        $userId = $first['id'];
                    } else {
                        throw new \Exception('No user found in users table; create at least one user before recording a decision');
                    }
                }
            } catch (Exception $e) {
                throw $e; // will be caught by the transaction catch
            }
            // Update status and comment in proof (within the same transaction)
            $sql = "UPDATE proof
            SET status = :status,
                manager_comment = :comment,
                processing_date = NOW(),
                updated_at = NOW()
            WHERE id = :id";
            $this->db->execute($sql, [
                'status' => 'rejected',
                'comment' => $comment,
                'id' => $proofId
            ]);

            // Insert into decision_history: store reason in rejection_reason column
            $sqlHistory = "INSERT INTO decision_history
            (justification_id, user_id, action, old_status, new_status, rejection_reason, comment, created_at)
            VALUES
            (:justification_id, :user_id, :action, :old_status, :new_status, :rejection_reason, :comment, NOW())";
            $this->db->execute($sqlHistory, [
                'justification_id' => $proofId,
                'user_id' => $userId,
                'action' => 'reject',
                'old_status' => $oldStatus,
                'new_status' => 'rejected',
                'rejection_reason' => $reason,
                'comment' => $comment
            ]);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log('Error setRejectionReason: ' . $e->getMessage());
            // Store error message in session for display on presenter side (dev)
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            $_SESSION['last_model_error'] = 'setRejectionReason: ' . $e->getMessage();
            return false;
        }
    }

    public function setValidationReason(int $proofId, string $reason, string $comment = '', ?int $userId = null): bool
    {
        $this->db->beginTransaction();
        try {
            // Retrieve old status before modification
            $proof = $this->getProofDetails($proofId);
            $oldStatus = $proof ? $proof['status'] : null;

            // If no userId provided, try to use processed_by_user_id from the proof
            if ($userId === null) {
                try {
                    $row = $this->db->selectOne("SELECT processed_by_user_id FROM proof WHERE id = :id", ['id' => $proofId]);
                    if ($row && !empty($row['processed_by_user_id'])) {
                        $userId = $row['processed_by_user_id'];
                    }
                } catch (Exception $e) {
                    // ignore, userId will remain null
                }
            }

            // If still no user_id after attempt, use a configurable fallback (SYSTEM_USER_ID) or 1
            if ($userId === null) {
                $fallback = (int) (env('SYSTEM_USER_ID', '1') ?? 1);
                error_log('setValidationReason: no user_id found, using fallback SYSTEM_USER_ID=' . $fallback);
                $userId = $fallback;
            }
            // Verify that userId exists in users table; otherwise try to get the first existing user
            try {
                $exists = $this->db->selectOne("SELECT id FROM users WHERE id = :id", ['id' => $userId]);
                if (!$exists) {
                    $first = $this->db->selectOne("SELECT id FROM users ORDER BY id LIMIT 1");
                    if ($first && isset($first['id'])) {
                        error_log('setValidationReason: user_id ' . $userId . ' not found, falling back to user_id ' . $first['id']);
                        $userId = $first['id'];
                    } else {
                        throw new \Exception('No user found in users table; create at least one user before recording a decision');
                    }
                }
            } catch (Exception $e) {
                throw $e; // will be caught by the transaction catch
            }
            // Update status and comment in proof (within the same transaction)
            $sql = "UPDATE proof
                SET status = :status,
                    manager_comment = :comment,
                    processing_date = NOW(),
                    updated_at = NOW()
                WHERE id = :id";
            $this->db->execute($sql, [
                'status' => 'accepted',
                'comment' => $comment,
                'id' => $proofId
            ]);

            // Insert into decision_history: store validation reason in rejection_reason column
            $sqlHistory = "INSERT INTO decision_history
            (justification_id, user_id, action, old_status, new_status, rejection_reason, comment, created_at)
            VALUES
            (:justification_id, :user_id, :action, :old_status, :new_status, :rejection_reason, :comment, NOW())";
            $this->db->execute($sqlHistory, [
                'justification_id' => $proofId,
                'user_id' => $userId,
                'action' => 'accept',
                'old_status' => $oldStatus,
                'new_status' => 'accepted',
                'rejection_reason' => $reason,
                'comment' => $comment
            ]);

            // Update linked absences to mark them as justified
            $this->updateAbsencesForProof(
                $proof['student_identifier'],
                $proof['absence_start_date'],
                $proof['absence_end_date'],
                'accepted'
            );

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Error setValidationReason: ' . $e->getMessage());
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            $_SESSION['last_model_error'] = "setValidationReason: " . $e->getMessage();
            return false;
        }
    }

    // Record an information request (mandatory comment) and insert a row in decision_history
    public function setRequestInfo(int $proofId, string $message, ?int $userId = null): bool
    {
        $this->db->beginTransaction();
        try {
            $proof = $this->getProofDetails($proofId);
            $oldStatus = $proof ? $proof['status'] : null;

            if ($userId === null) {
                try {
                    $row = $this->db->selectOne("SELECT processed_by_user_id FROM proof WHERE id = :id", ['id' => $proofId]);
                    if ($row && !empty($row['processed_by_user_id'])) {
                        $userId = $row['processed_by_user_id'];
                    }
                } catch (Exception $e) {
                    // ignore
                }
            }

            if ($userId === null) {
                $fallback = (int) (env('SYSTEM_USER_ID', '1') ?? 1);
                error_log('setRequestInfo: no user_id found, using fallback SYSTEM_USER_ID=' . $fallback);
                $userId = $fallback;
            }

            // Verify user exists
            try {
                $exists = $this->db->selectOne("SELECT id FROM users WHERE id = :id", ['id' => $userId]);
                if (!$exists) {
                    $first = $this->db->selectOne("SELECT id FROM users ORDER BY id LIMIT 1");
                    if ($first && isset($first['id'])) {
                        error_log('setRequestInfo: user_id ' . $userId . ' not found, falling back to user_id ' . $first['id']);
                        $userId = $first['id'];
                    } else {
                        throw new \Exception('No user found in users table; create at least one user before recording a decision');
                    }
                }
            } catch (Exception $e) {
                throw $e;
            }

            // Update comment and status to under_review
            $sql = "UPDATE proof
                SET status = :status,
                    manager_comment = :comment,
                    processing_date = NOW(),
                    updated_at = NOW()
                WHERE id = :id";
            $this->db->execute($sql, [
                'status' => 'under_review',
                'comment' => $message,
                'id' => $proofId
            ]);

            // Insert into decision_history with action request_info
            $sqlHistory = "INSERT INTO decision_history
            (justification_id, user_id, action, old_status, new_status, rejection_reason, comment, created_at)
            VALUES
            (:justification_id, :user_id, :action, :old_status, :new_status, :rejection_reason, :comment, NOW())";
            $this->db->execute($sqlHistory, [
                'justification_id' => $proofId,
                'user_id' => $userId,
                'action' => 'request_info',
                'old_status' => $oldStatus,
                'new_status' => 'under_review',
                'rejection_reason' => null,
                'comment' => $message
            ]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Error setRequestInfo: ' . $e->getMessage());
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            $_SESSION['last_model_error'] = "setRequestInfo: " . $e->getMessage();
            return false;
        }
    }

    // Retrieve rejection or validation reasons by type from the rejection_validation_reasons table
    public function getReasons(string $type): array
    {
        $sql = "SELECT label FROM rejection_validation_reasons WHERE type_of_reason = :type ORDER BY label ASC";
        try {
            $results = $this->db->select($sql, ['type' => $type]);
            return array_map(fn($row) => $row['label'], $results);
        } catch (Exception $e) {
            error_log('Error getReasons: ' . $e->getMessage());
            return [];
        }
    }

    // Add a new rejection or validation reason to the rejection_validation_reasons table by type
    public function addReason(string $label, string $type): bool
    {
        // Insert only if the (label, type_of_reason) pair doesn't exist
        // ON CONFLICT must match an existing index: the schema has a UNIQUE(label) constraint
        // Using ON CONFLICT (label) DO NOTHING to avoid errors if no composite index exists
        $sql = "INSERT INTO rejection_validation_reasons (label, type_of_reason)
                VALUES (:label, :type)
                ON CONFLICT (label) DO NOTHING";
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare($sql);
            $stmt->execute(['label' => $label, 'type' => $type]);
            // If query succeeded, verify the record exists (fallback)
            $checkSql = "SELECT id FROM rejection_validation_reasons WHERE label = :label AND type_of_reason = :type LIMIT 1";
            $exists = $this->db->select($checkSql, ['label' => $label, 'type' => $type]);
            return !empty($exists);
        } catch (Exception $e) {
            error_log('Error addReason: ' . $e->getMessage());
            return false;
        }
    }

    // Getter methods for rejection and validation reasons, and methods to add a reason by type directly
    public function getRejectionReasons(): array
    {
        return $this->getReasons('rejection');
    }

    public function getValidationReasons(): array
    {
        return $this->getReasons('validation');
    }

    public function addRejectionReason(string $label): bool
    {
        return $this->addReason($label, 'rejection');
    }

    public function addValidationReason(string $label): bool
    {
        return $this->addReason($label, 'validation');
    }

    public function unlock(int $proofId): bool
    {
        $sql = "UPDATE proof SET locked = 'false' WHERE id = :id";
        try {
            $affected = $this->db->execute($sql, ['id' => $proofId]);
            return $affected > 0;
        } catch (Exception $e) {
            error_log('Error unlock: ' . $e->getMessage());
            return false;
        }
    }

    public function lock(int $proofId): bool
    {
        $sql = "UPDATE proof SET locked = 'true' WHERE id = :id";
        try {
            $affected = $this->db->execute($sql, ['id' => $proofId]);
            return $affected > 0;
        } catch (Exception $e) {
            error_log('Error lock: ' . $e->getMessage());
            return false;
        }
    }

    public function isLocked(int $proofId): bool
    {
        $sql = "SELECT locked FROM proof WHERE id = :id";
        try {
            $result = $this->db->selectOne($sql, ['id' => $proofId]);
            return $result && ($result['locked'] === 'true' || $result['locked'] === true);
        } catch (Exception $e) {
            error_log('Error isLocked: ' . $e->getMessage());
            return false;
        }
    }

    // Format date to French format
    public function formatDateFr($datetimeStr)
    {
        if (!$datetimeStr)
            return '';
        try {
            $timezone = new DateTimeZone('Europe/Paris');
            $date = new DateTime($datetimeStr, $timezone);
            // Use IntlDateFormatter if available (recommended since PHP 8.1)
            if (class_exists('\IntlDateFormatter')) {
                // pattern: 02/01/2025 Ã  14h30
                $pattern = "dd/MM/yyyy 'Ã ' HH'h'mm";
                $formatter = new \IntlDateFormatter(
                    'fr_FR',
                    \IntlDateFormatter::NONE,
                    \IntlDateFormatter::NONE,
                    $date->getTimezone()->getName(),
                    \IntlDateFormatter::GREGORIAN,
                    $pattern
                );
                $formatted = $formatter->format($date);
                if ($formatted === false) {
                    // fallback
                    return $date->format('d/m/Y \Ã  H\hi');
                }
                return $formatted;
            }

            // Simple fallback if Intl not available
            return $date->format('d/m/Y \Ã  H\hi');
        } catch (Exception $e) {
            error_log('Error formatDateFr: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Validates that split periods don't cut a course slot in the middle
     * and that each period will have at least one assigned absence
     */
    public function validateSplitPeriods(int $proofId, array $periods): array
    {
        try {
            // Retrieve all course slots linked to this proof
            $sqlSlots = "SELECT DISTINCT 
                            cs.id as slot_id,
                            cs.course_date,
                            cs.start_time,
                            cs.end_time,
                            (cs.course_date || ' ' || cs.start_time)::timestamp as slot_start,
                            (cs.course_date || ' ' || cs.end_time)::timestamp as slot_end
                        FROM proof_absences pa
                        JOIN absences a ON pa.absence_id = a.id
                        JOIN course_slots cs ON a.course_slot_id = cs.id
                        WHERE pa.proof_id = :proof_id
                        ORDER BY cs.course_date, cs.start_time";

            $slots = $this->db->select($sqlSlots, ['proof_id' => $proofId]);

            if (empty($slots)) {
                return [
                    'valid' => false,
                    'error' => 'No absence is linked to this proof. Splitting is impossible.'
                ];
            }

            if (count($slots) < 2) {
                return [
                    'valid' => false,
                    'error' => 'This proof has only one linked course slot. Splitting is impossible as there is only one absence to distribute.'
                ];
            }

            // Check that each period boundary (end of one period / start of next)
            // doesn't cut a slot in the middle
            for ($i = 0; $i < count($periods) - 1; $i++) {
                $periodEnd = strtotime($periods[$i]['end']);
                $periodNextStart = strtotime($periods[$i + 1]['start']);

                foreach ($slots as $slot) {
                    $slotStart = strtotime($slot['slot_start']);
                    $slotEnd = strtotime($slot['slot_end']);

                    // Check if period end cuts the slot
                    if ($periodEnd > $slotStart && $periodEnd < $slotEnd) {
                        return [
                            'valid' => false,
                            'error' => 'The end of period ' . ($i + 1) . ' (' . $periods[$i]['end'] . ') cuts the course slot on '
                                . $slot['course_date'] . ' (' . substr($slot['start_time'], 0, 5) . ' - ' . substr($slot['end_time'], 0, 5) . ') in the middle. '
                                . 'Please adjust the times to end before ' . substr($slot['start_time'], 0, 5)
                                . ' or after ' . substr($slot['end_time'], 0, 5) . '.'
                        ];
                    }
                }
            }

            // Check that each period will have at least one absence
            foreach ($periods as $index => $period) {
                $periodStart = strtotime($period['start']);
                $periodEnd = strtotime($period['end']);
                $hasAbsence = false;

                foreach ($slots as $slot) {
                    $slotStart = strtotime($slot['slot_start']);
                    $slotEnd = strtotime($slot['slot_end']);

                    // A slot belongs to the period if its start is >= period start and < period end
                    if ($slotStart >= $periodStart && $slotStart < $periodEnd) {
                        $hasAbsence = true;
                        break;
                    }
                }

                if (!$hasAbsence) {
                    return [
                        'valid' => false,
                        'error' => 'Period ' . ($index + 1) . ' (' . $period['start'] . ' â†’ ' . $period['end'] . ') contains no course slot. Each period must cover at least one absence.'
                    ];
                }
            }

            return ['valid' => true, 'error' => null];
        } catch (Exception $e) {
            error_log('Error validateSplitPeriods: ' . $e->getMessage());
            return [
                'valid' => false,
                'error' => 'Validation error: ' . $e->getMessage()
            ];
        }
    }

    // Split a proof into N distinct periods
    public function splitProofMultiple(int $proofId, array $periods, string $reason, ?int $userId = null): bool
    {
        $this->db->beginTransaction();
        try {
            // Validate input
            if (empty($periods)) {
                throw new Exception('At least one period is required to split a proof');
            }

            // Retrieve original proof
            $proof = $this->getProofDetails($proofId);
            if (!$proof) {
                throw new Exception('Proof not found');
            }

            $newProofIds = [];
            $periodDates = []; // Will store actual dates based on courses

            // First, determine actual dates based on courses for each period
            foreach ($periods as $index => $period) {
                // Get min and max date/time of courses in this period
                $sqlGetDates = "
                    SELECT 
                        MIN(cs.course_date || ' ' || cs.start_time) as real_start,
                        MAX(cs.course_date || ' ' || cs.end_time) as real_end
                    FROM proof_absences pa
                    JOIN absences a ON pa.absence_id = a.id
                    JOIN course_slots cs ON a.course_slot_id = cs.id
                    WHERE pa.proof_id = :proof_id
                      AND (cs.course_date || ' ' || cs.end_time)::timestamp >= :start_datetime::timestamp
                      AND (cs.course_date || ' ' || cs.start_time)::timestamp <= :end_datetime::timestamp
                ";

                $result = $this->db->selectOne($sqlGetDates, [
                    'proof_id' => $proofId,
                    'start_datetime' => $period['start'],
                    'end_datetime' => $period['end']
                ]);

                if ($result && $result['real_start'] && $result['real_end']) {
                    $periodDates[$index] = [
                        'start_date' => substr($result['real_start'], 0, 10),
                        'end_date' => substr($result['real_end'], 0, 10)
                    ];
                } else {
                    // Fallback to entered dates if no courses found
                    $periodDates[$index] = [
                        'start_date' => substr($period['start'], 0, 10),
                        'end_date' => substr($period['end'], 0, 10)
                    ];
                }
            }

            $sqlInsert = "INSERT INTO proof (
                student_identifier, absence_start_date, absence_end_date,
                concerned_courses, main_reason, custom_reason, file_path,
                student_comment, status, submission_date, manager_comment
            ) VALUES (
                :student_identifier, :start_date, :end_date,
                :concerned_courses, :main_reason, :custom_reason, :file_path,
                :student_comment, :status, :submission_date, :manager_comment
            )";

            // Create a proof for each period
            foreach ($periods as $index => $period) {
                // Build start and end dates from provided fields
                // Support format 1: 'start' and 'end' (full datetime)
                // Support format 2: 'startDate', 'endDate', 'startTime', 'endTime' (separate)
                $startDatetime = null;
                $endDatetime = null;

                if (isset($period['start']) && !empty($period['start'])) {
                    $startDatetime = $period['start'];
                } elseif (isset($period['startDate']) && isset($period['startTime'])) {
                    $startDatetime = $period['startDate'] . ' ' . $period['startTime'];
                } elseif (isset($period['startDate'])) {
                    $startDatetime = $period['startDate'] . ' 00:00:00';
                }

                if (isset($period['end']) && !empty($period['end'])) {
                    $endDatetime = $period['end'];
                } elseif (isset($period['endDate']) && isset($period['endTime'])) {
                    $endDatetime = $period['endDate'] . ' ' . $period['endTime'];
                } elseif (isset($period['endDate'])) {
                    $endDatetime = $period['endDate'] . ' 23:59:59';
                }

                // Extract dates only (YYYY-MM-DD) for absence_start_date and absence_end_date
                $startDate = $startDatetime ? substr($startDatetime, 0, 10) : null;
                $endDate = $endDatetime ? substr($endDatetime, 0, 10) : null;

                // Determine the reason for this proof
                $periodReason = isset($period['reason']) ? $period['reason'] : $proof['main_reason'];

                // Set status: 'accepted' if validate=true, otherwise 'pending'
                $status = (!empty($period['validate']) && $period['validate'] === true) ? 'accepted' : 'pending';

                $this->db->execute($sqlInsert, [
                    'student_identifier' => $proof['student_identifier'],
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'concerned_courses' => $proof['concerned_courses'] ?? null,
                    'main_reason' => $periodReason,
                    'custom_reason' => $proof['custom_reason'],
                    'file_path' => $proof['file_path'] ?? null,
                    'student_comment' => $proof['student_comment'] ?? null,
                    'status' => $status,
                    'submission_date' => $proof['submission_date'],
                    'manager_comment' => 'Split from proof #' . $proofId . ' (period ' . ($index + 1) . '): ' . $reason
                ]);
                $newProofId = $this->db->lastInsertId();
                $newProofIds[] = ['id' => $newProofId, 'start' => $startDatetime, 'end' => $endDatetime];

                // If validated, record in history with action 'accept' and lock
                if ($status === 'accepted' && $userId !== null) {
                    $sqlHistoryValidation = "INSERT INTO decision_history
                        (justification_id, user_id, action, old_status, new_status, comment, created_at)
                        VALUES (:justification_id, :user_id, 'accept', 'pending', 'accepted', :comment, NOW())";
                    $this->db->execute($sqlHistoryValidation, [
                        'justification_id' => $newProofId,
                        'user_id' => $userId,
                        'comment' => 'Automatically validated during split'
                    ]);

                    // Automatically lock the validated proof during split
                    $sqlLock = "UPDATE proof SET locked = 'true' WHERE id = :id";
                    $this->db->execute($sqlLock, ['id' => $newProofId]);
                }
            }

            // Reassign absences to new proofs based on periods
            // A slot is assigned to the period where its START time falls
            // Condition: start_time >= period_start AND start_time < period_end
            $sqlInsertAbsences = "INSERT INTO proof_absences (proof_id, absence_id)
                SELECT :new_proof_id, pa.absence_id
                FROM proof_absences pa
                JOIN absences a ON pa.absence_id = a.id
                JOIN course_slots cs ON a.course_slot_id = cs.id
                WHERE pa.proof_id = :old_proof_id
                  AND (cs.course_date || ' ' || cs.start_time)::timestamp >= :start_datetime::timestamp
                  AND (cs.course_date || ' ' || cs.start_time)::timestamp < :end_datetime::timestamp";

            foreach ($newProofIds as $proofData) {
                $this->db->execute($sqlInsertAbsences, [
                    'new_proof_id' => $proofData['id'],
                    'old_proof_id' => $proofId,
                    'start_datetime' => $proofData['start'],
                    'end_datetime' => $proofData['end']
                ]);
            }

            // Update dates of new proofs based on actually assigned courses
            $sqlUpdateDates = "
                UPDATE proof SET
                    absence_start_date = (
                        SELECT MIN(cs.course_date)
                        FROM proof_absences pa
                        JOIN absences a ON pa.absence_id = a.id
                        JOIN course_slots cs ON a.course_slot_id = cs.id
                        WHERE pa.proof_id = :proof_id
                    ),
                    absence_end_date = (
                        SELECT MAX(cs.course_date)
                        FROM proof_absences pa
                        JOIN absences a ON pa.absence_id = a.id
                        JOIN course_slots cs ON a.course_slot_id = cs.id
                        WHERE pa.proof_id = :proof_id
                    )
                WHERE id = :proof_id
            ";

            foreach ($newProofIds as $proofData) {
                $this->db->execute($sqlUpdateDates, ['proof_id' => $proofData['id']]);
            }

            // Note: Split history is not recorded because 'split' is not a valid action
            // New proofs contain the information in manager_comment

            // Delete decision history of original proof (before deleting the proof)
            $sqlDeleteHistory = "DELETE FROM decision_history WHERE justification_id = :proof_id";
            $this->db->execute($sqlDeleteHistory, ['proof_id' => $proofId]);

            // Delete proof_absences links from original
            $sqlDeleteAbsences = "DELETE FROM proof_absences WHERE proof_id = :proof_id";
            $this->db->execute($sqlDeleteAbsences, ['proof_id' => $proofId]);

            // Delete original proof
            $sqlDelete = "DELETE FROM proof WHERE id = :id";
            $this->db->execute($sqlDelete, ['id' => $proofId]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Error splitProofMultiple: ' . $e->getMessage());
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            $_SESSION['last_model_error'] = "splitProofMultiple: " . $e->getMessage();
            return false;
        }
    }

    // Split a proof into two distinct periods (kept for backward compatibility)
    public function splitProof(int $proofId, string $split1Start, string $split1End, string $split2Start, string $split2End, string $reason, ?int $userId = null): bool
    {
        $this->db->beginTransaction();
        try {
            // Retrieve original proof
            $proof = $this->getProofDetails($proofId);
            if (!$proof) {
                throw new Exception('Proof not found');
            }

            // Create first proof
            $sql1 = "INSERT INTO proof (
                student_identifier, absence_start_date, absence_end_date,
                concerned_courses, main_reason, custom_reason, file_path,
                student_comment, status, submission_date, manager_comment
            ) VALUES (
                :student_identifier, :start_date, :end_date,
                :concerned_courses, :main_reason, :custom_reason, :file_path,
                :student_comment, 'pending', :submission_date, :manager_comment
            )";

            $this->db->execute($sql1, [
                'student_identifier' => $proof['student_identifier'],
                'start_date' => $split1Start,
                'end_date' => $split1End,
                'concerned_courses' => $proof['concerned_courses'] ?? null,
                'main_reason' => $proof['main_reason'],
                'custom_reason' => $proof['custom_reason'],
                'file_path' => $proof['file_path'] ?? null,
                'student_comment' => $proof['student_comment'] ?? null,
                'submission_date' => $proof['submission_date'],
                'manager_comment' => 'Split from proof #' . $proofId . ': ' . $reason
            ]);
            $newProofId1 = $this->db->lastInsertId();

            // Create second proof
            $this->db->execute($sql1, [
                'student_identifier' => $proof['student_identifier'],
                'start_date' => $split2Start,
                'end_date' => $split2End,
                'concerned_courses' => $proof['concerned_courses'] ?? null,
                'main_reason' => $proof['main_reason'],
                'custom_reason' => $proof['custom_reason'],
                'file_path' => $proof['file_path'] ?? null,
                'student_comment' => $proof['student_comment'] ?? null,
                'submission_date' => $proof['submission_date'],
                'manager_comment' => 'Split from proof #' . $proofId . ': ' . $reason
            ]);
            $newProofId2 = $this->db->lastInsertId();

            // Reassign absences to new proofs considering times
            // A slot is included if it overlaps the period (not necessarily fully contained)
            $sqlUpdateAbs1 = "INSERT INTO proof_absences (proof_id, absence_id)
                SELECT :new_proof_id, pa.absence_id
                FROM proof_absences pa
                JOIN absences a ON pa.absence_id = a.id
                JOIN course_slots cs ON a.course_slot_id = cs.id
                WHERE pa.proof_id = :old_proof_id
                  AND (cs.course_date || ' ' || cs.end_time)::timestamp >= :start_datetime::timestamp
                  AND (cs.course_date || ' ' || cs.start_time)::timestamp <= :end_datetime::timestamp";

            $this->db->execute($sqlUpdateAbs1, [
                'new_proof_id' => $newProofId1,
                'old_proof_id' => $proofId,
                'start_datetime' => $split1Start,
                'end_datetime' => $split1End
            ]);

            $this->db->execute($sqlUpdateAbs1, [
                'new_proof_id' => $newProofId2,
                'old_proof_id' => $proofId,
                'start_datetime' => $split2Start,
                'end_datetime' => $split2End
            ]);

            // Note: Split history is not recorded because 'split' is not a valid action
            // New proofs contain the information in manager_comment

            // Delete decision history of original proof (before deleting the proof)
            $sqlDeleteHistory = "DELETE FROM decision_history WHERE justification_id = :proof_id";
            $this->db->execute($sqlDeleteHistory, ['proof_id' => $proofId]);

            // Delete proof_absences links from original
            $sqlDeleteAbsences = "DELETE FROM proof_absences WHERE proof_id = :proof_id";
            $this->db->execute($sqlDeleteAbsences, ['proof_id' => $proofId]);

            // Delete original proof
            $sqlDelete = "DELETE FROM proof WHERE id = :id";
            $this->db->execute($sqlDelete, ['id' => $proofId]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Error splitProof: ' . $e->getMessage());
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            $_SESSION['last_model_error'] = "splitProof: " . $e->getMessage();
            return false;
        }
    }

    // Simple translation (UI labels)
    public function translate(string $category, string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $value = (string) $value;
        $maps = [
            'status' => [
                'pending' => 'En attente',
                'approved' => 'Accepté',
                'accepted' => 'Accepté',
                'rejected' => 'Rejeté',
                'under_review' => 'En révision',
                'split' => 'Scindé',
            ],
            'reason' => [
                'illness' => 'Maladie',
                'death' => 'Décès',
                'family_obligations' => 'Obligations familiales',
                'rdv_medical' => 'Rendez-vous médical',
                'official_summons' => 'Convocation officielle',
                'transport_issue' => 'Problème de transport',
                'other' => 'Autre',
            ],
        ];
        return $maps[$category][$value] ?? $value;
    }

    // Get recent proofs for dashboard (limit to recent submissions)
    public function getRecentProofs(int $limit = 10): array
    {
        $sql = "
            SELECT 
                p.id AS proof_id,
                p.student_identifier,
                p.absence_start_date,
                p.absence_end_date,
                p.main_reason,
                p.custom_reason,
                p.status,
                p.submission_date,
                u.last_name,
                u.first_name,
                g.label AS group_label
            FROM proof p
            JOIN users u ON LOWER(u.identifier) = LOWER(p.student_identifier)
            LEFT JOIN user_groups ug ON ug.user_id = u.id
            LEFT JOIN groups g ON g.id = ug.group_id
            ORDER BY p.submission_date DESC
            LIMIT :limit
        ";

        try {
            return $this->db->select($sql, ['limit' => $limit]);
        } catch (Exception $e) {
            error_log('Error getRecentProofs: ' . $e->getMessage());
            return [];
        }
    }

    // Get all proofs with filters for history page
    public function getAllProofs(array $filters = []): array
    {
        $sql = "
            SELECT 
                p.id AS proof_id,
                p.id AS id,
                p.student_identifier,
                p.absence_start_date,
                p.absence_end_date,
                p.main_reason,
                p.custom_reason,
                p.student_comment,
                p.status,
                p.submission_date,
                p.file_path,
                p.proof_files,
                u.last_name,
                u.first_name,
                g.label AS group_label
            FROM proof p
            JOIN users u ON LOWER(u.identifier) = LOWER(p.student_identifier)
            LEFT JOIN user_groups ug ON ug.user_id = u.id
            LEFT JOIN groups g ON g.id = ug.group_id
            WHERE 1=1
        ";

        $params = [];

        // Filter by student name
        if (!empty($filters['name'])) {
            $sql .= " AND (LOWER(u.last_name) LIKE LOWER(:name) OR LOWER(u.first_name) LIKE LOWER(:name))";
            $params['name'] = '%' . $filters['name'] . '%';
        }

        // Filter by start date
        if (!empty($filters['start_date'])) {
            $sql .= " AND p.absence_start_date >= :start_date";
            $params['start_date'] = $filters['start_date'];
        }

        // Filter by end date
        if (!empty($filters['end_date'])) {
            $sql .= " AND p.absence_end_date <= :end_date";
            $params['end_date'] = $filters['end_date'];
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $statusMap = [
                'En attente' => 'pending',
                'Acceptée' => 'accepted',
                'Rejetée' => 'rejected',
                'En cours d\'examen' => 'under_review'
            ];
            $dbStatus = $statusMap[$filters['status']] ?? $filters['status'];
            $sql .= " AND p.status = :status";
            $params['status'] = $dbStatus;
        }

        // Filter by reason
        if (!empty($filters['reason'])) {
            $reasonMap = [
                'Maladie' => 'illness',
                'Décès' => 'death',
                'Obligations familiales' => 'family_obligations',
                'Autre' => 'other'
            ];
            $dbReason = $reasonMap[$filters['reason']] ?? $filters['reason'];
            $sql .= " AND p.main_reason = :reason";
            $params['reason'] = $dbReason;
        }

        $sql .= " ORDER BY p.submission_date DESC";

        try {
            return $this->db->select($sql, $params);
        } catch (Exception $e) {
            error_log('Error getAllProofs: ' . $e->getMessage());
            return [];
        }
    }

    // Get list of unique reasons for filter dropdown
    public function getProofReasons(): array
    {
        $sql = "SELECT DISTINCT main_reason FROM proof WHERE main_reason IS NOT NULL ORDER BY main_reason";
        try {
            return $this->db->select($sql);
        } catch (Exception $e) {
            error_log('Error getProofReasons: ' . $e->getMessage());
            return [];
        }
    }

    // Get proof files from JSONB column
    public function getProofFiles(int $proofId): array
    {
        try {
            $result = $this->db->selectOne("SELECT proof_files FROM proof WHERE id = :id", ['id' => $proofId]);
            if (!$result || empty($result['proof_files'])) {
                return [];
            }

            // If it's already an array (PDO might decode JSONB automatically)
            if (is_array($result['proof_files'])) {
                return $result['proof_files'];
            }

            // Otherwise decode JSON string
            $files = json_decode($result['proof_files'], true);
            return is_array($files) ? $files : [];
        } catch (Exception $e) {
            error_log('Error getProofFiles: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Return only file_path and proof_files for a proof (used by view_upload_proof.php).
     */
    public function getProofFilePaths(int $proofId): ?array
    {
        $sql = "SELECT file_path, proof_files FROM proof WHERE id = :id LIMIT 1";
        try {
            return $this->db->selectOne($sql, ['id' => $proofId]) ?: null;
        } catch (Exception $e) {
            error_log('Error getProofFilePaths: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Return lightweight proof data needed for the update/status check (proof_update.php).
     */
    public function getProofForUpdate(int $proofId): ?array
    {
        $sql = "SELECT id, student_identifier, file_path, proof_files, status
                FROM proof WHERE id = :proof_id";
        try {
            return $this->db->selectOne($sql, ['proof_id' => $proofId]) ?: null;
        } catch (Exception $e) {
            error_log('Error getProofForUpdate: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Return full proof data for the student edit form (get_proof_for_edit.php).
     */
    public function getProofForEdit(int $proofId): ?array
    {
        $sql = "SELECT p.id, p.student_identifier,
                       p.absence_start_date, p.absence_end_date,
                       p.main_reason, p.custom_reason, p.status,
                       p.student_comment, p.manager_comment,
                       p.file_path, p.proof_files, p.concerned_courses
                FROM proof p
                WHERE p.id = :proof_id";
        try {
            return $this->db->selectOne($sql, ['proof_id' => $proofId]) ?: null;
        } catch (Exception $e) {
            error_log('Error getProofForEdit: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Retrieve a student's proofs with aggregated absence/hour/half-day stats and optional filters.
     * Supports status, reason, has_exam, start_date, end_date filters.
     * Used by proofs_presenter.php::getProofs().
     */
    public function getStudentProofsFiltered(string $studentIdentifier, array $filters = []): array
    {
        $query = "
            SELECT
                p.id as proof_id,
                p.absence_start_date,
                p.absence_end_date,
                p.main_reason,
                p.custom_reason,
                p.student_comment,
                p.submission_date,
                p.processing_date,
                p.status,
                p.manager_comment,
                p.file_path,
                p.proof_files,
                COUNT(DISTINCT pa.absence_id) as absence_count,
                SUM(cs.duration_minutes) as total_duration_minutes,
                MAX(CASE WHEN cs.is_evaluation = true THEN 1 ELSE 0 END) as has_exam,
                COUNT(DISTINCT (cs.course_date, CASE WHEN cs.start_time < '12:30:00' THEN 'morning' ELSE 'afternoon' END)) as half_days_count,
                MIN(cs.course_date || ' ' || cs.start_time) as absence_start_datetime,
                MAX(cs.course_date || ' ' || cs.end_time) as absence_end_datetime
            FROM proof p
            LEFT JOIN proof_absences pa ON p.id = pa.proof_id
            LEFT JOIN absences a ON pa.absence_id = a.id
            LEFT JOIN course_slots cs ON a.course_slot_id = cs.id
            WHERE p.student_identifier = :student_id
        ";

        $params = [':student_id' => $studentIdentifier];

        if (!empty($filters['start_date'])) {
            $query .= " AND p.absence_start_date >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $query .= " AND p.absence_end_date <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }

        $query .= " GROUP BY p.id";

        if (!empty($filters['status'])) {
            $query .= " HAVING p.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['reason'])) {
            $query .= (strpos($query, 'HAVING') !== false ? ' AND' : ' HAVING') . " p.main_reason = :reason";
            $params[':reason'] = $filters['reason'];
        }

        if (!empty($filters['has_exam'])) {
            $examCond = $filters['has_exam'] === 'yes'
                ? " MAX(CASE WHEN cs.is_evaluation = true THEN 1 ELSE 0 END) = 1"
                : " MAX(CASE WHEN cs.is_evaluation = true THEN 1 ELSE 0 END) = 0";
            $query .= (strpos($query, 'HAVING') !== false ? ' AND' : ' HAVING') . $examCond;
        }

        $query .= "
            ORDER BY
                CASE
                    WHEN p.status = 'under_review' THEN 1
                    WHEN p.status = 'pending'      THEN 2
                    WHEN p.status = 'rejected'     THEN 3
                    WHEN p.status = 'accepted'     THEN 4
                    ELSE 5
                END,
                p.submission_date DESC
        ";

        try {
            return $this->db->select($query, $params);
        } catch (Exception $e) {
            error_log('Error getStudentProofsFiltered: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Return the course slot data for each absence in a proof (for half-day calculation).
     * Used by proofs_presenter.php::calculateHalfDaysForProof().
     */
    public function getAbsenceSlotsForProof(int $proofId): array
    {
        $sql = "
            SELECT
                cs.course_date,
                cs.start_time,
                cs.end_time,
                cs.duration_minutes
            FROM proof_absences pa
            JOIN absences a ON pa.absence_id = a.id
            JOIN course_slots cs ON a.course_slot_id = cs.id
            WHERE pa.proof_id = :proof_id
        ";
        try {
            return $this->db->select($sql, ['proof_id' => $proofId]);
        } catch (Exception $e) {
            error_log('Error getAbsenceSlotsForProof: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get decision history with filtering capabilities
     * Retrieves all decisions made on proofs by academic managers
     */
    public function getDecisionHistory(array $filters = []): array
    {
        $sql = "
            SELECT 
                dh.id,
                dh.created_at AS decision_date,
                dh.action,
                dh.old_status,
                dh.new_status,
                dh.comment,
                p.student_identifier,
                p.absence_start_date,
                p.absence_end_date,
                p.main_reason,
                p.custom_reason AS rejection_reason,
                u.first_name,
                u.last_name,
                manager.first_name AS manager_first_name,
                manager.last_name AS manager_last_name
            FROM decision_history dh
            JOIN proof p ON p.id = dh.justification_id
            JOIN users u ON LOWER(u.identifier) = LOWER(p.student_identifier)
            JOIN users manager ON manager.id = dh.user_id
            WHERE 1=1
        ";

        // Apply filters
        if (!empty($filters['student_name'])) {
            $sql .= " AND (u.first_name ILIKE :student_name OR u.last_name ILIKE :student_name)";
        }
        if (!empty($filters['start_date'])) {
            $sql .= " AND DATE(dh.created_at) >= :start_date";
        }
        if (!empty($filters['end_date'])) {
            $sql .= " AND DATE(dh.created_at) <= :end_date";
        }
        if (!empty($filters['action'])) {
            $sql .= " AND dh.action = :action";
        }
        if (!empty($filters['status'])) {
            $sql .= " AND dh.new_status = :status";
        }

        $sql .= " ORDER BY dh.created_at DESC";

        try {
            $params = [];
            if (!empty($filters['student_name'])) {
                $params['student_name'] = '%' . $filters['student_name'] . '%';
            }
            if (!empty($filters['start_date'])) {
                $params['start_date'] = $filters['start_date'];
            }
            if (!empty($filters['end_date'])) {
                $params['end_date'] = $filters['end_date'];
            }
            if (!empty($filters['action'])) {
                $params['action'] = $filters['action'];
            }
            if (!empty($filters['status'])) {
                $params['status'] = $filters['status'];
            }

            return $this->db->select($sql, $params);
        } catch (Exception $e) {
            error_log('Error getDecisionHistory: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Retrieve proofs for a student filtered by a specific status with full aggregated stats.
     * Used by get_info.php::getProofsByCategory() (four calls: under_review, pending, accepted, rejected).
     */
    public function getStudentProofsByStatus(string $studentIdentifier, string $status): array
    {
        $includeResource = in_array($status, ['accepted', 'rejected'], true);
        $includeManagerComment = in_array($status, ['under_review', 'rejected'], true);
        $includeProcessingDate = in_array($status, ['accepted', 'rejected'], true);

        $selectExtra = '';
        if ($includeResource) {
            $selectExtra .= ",\n                STRING_AGG(DISTINCT r.code, ', ') as course_codes,
                STRING_AGG(DISTINCT r.label, ' | ') as course_names";
        }
        if ($includeManagerComment) {
            $selectExtra .= ",\n                p.manager_comment";
        }
        if ($includeProcessingDate) {
            $selectExtra .= ",\n                p.processing_date";
        }

        $groupExtra = '';
        if ($includeManagerComment) {
            $groupExtra .= ', p.manager_comment';
        }
        if ($includeProcessingDate) {
            $groupExtra .= ', p.processing_date';
        }

        $joinExtra = $includeResource
            ? "\n        LEFT JOIN resources r ON r.id = cs.resource_id"
            : '';

        $orderBy = $includeProcessingDate ? 'p.processing_date DESC' : 'p.submission_date DESC';

        $sql = "
            SELECT
                p.id as proof_id,
                p.absence_start_date,
                p.absence_end_date,
                p.main_reason,
                p.custom_reason,
                p.student_comment,
                p.submission_date,
                p.proof_files,
                COUNT(DISTINCT pa.absence_id) as nb_absences,
                COALESCE(SUM(cs.duration_minutes) / 60.0, 0) as total_hours_missed,
                BOOL_OR(cs.is_evaluation) as has_exam,
                COUNT(DISTINCT (cs.course_date, CASE WHEN cs.start_time < '12:30:00' THEN 'morning' ELSE 'afternoon' END)) as half_days_count,
                MIN(cs.course_date || ' ' || cs.start_time) as absence_start_datetime,
                MAX(cs.course_date || ' ' || cs.end_time) as absence_end_datetime
                {$selectExtra}
            FROM proof p
            LEFT JOIN proof_absences pa ON pa.proof_id = p.id
            LEFT JOIN absences a ON a.id = pa.absence_id
            LEFT JOIN course_slots cs ON cs.id = a.course_slot_id
            {$joinExtra}
            WHERE p.student_identifier = :student_id
              AND p.status = :status
            GROUP BY p.id, p.absence_start_date, p.absence_end_date, p.main_reason,
                     p.custom_reason, p.submission_date, p.proof_files{$groupExtra}
            ORDER BY {$orderBy}
        ";

        try {
            return $this->db->select($sql, [
                'student_id' => $studentIdentifier,
                'status' => $status,
            ]);
        } catch (Exception $e) {
            error_log('Error getStudentProofsByStatus: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Update proof fields after a student edit and set it back to pending review.
     */
    public function updateProofAfterStudentEdit(
        int $proofId,
        string $mainReason,
        ?string $customReason,
        string $proofFilesJson,
        string $studentComment,
        string $concernedCourses
    ): bool {
        $sql = "
            UPDATE proof
            SET
                main_reason = :main_reason,
                custom_reason = :custom_reason,
                proof_files = :proof_files::jsonb,
                student_comment = :student_comment,
                concerned_courses = :concerned_courses,
                status = 'pending',
                processing_date = NULL,
                manager_comment = NULL,
                updated_at = NOW()
            WHERE id = :proof_id
        ";

        try {
            $affected = $this->db->execute($sql, [
                'main_reason' => $mainReason,
                'custom_reason' => $customReason,
                'proof_files' => $proofFilesJson,
                'student_comment' => $studentComment,
                'concerned_courses' => $concernedCourses,
                'proof_id' => $proofId,
            ]);
            return $affected > 0;
        } catch (Exception $e) {
            error_log('Error updateProofAfterStudentEdit: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Reset absences linked to a proof back to unjustified absent state.
     */
    public function resetLinkedAbsencesToUnjustified(int $proofId): bool
    {
        $sql = "
            UPDATE absences a
            SET
                justified = FALSE,
                status = 'absent',
                updated_at = NOW()
            FROM proof_absences pa
            WHERE pa.proof_id = :proof_id
              AND a.id = pa.absence_id
        ";

        try {
            $this->db->execute($sql, ['proof_id' => $proofId]);
            return true;
        } catch (Exception $e) {
            error_log('Error resetLinkedAbsencesToUnjustified: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a pending proof and link it to all provided absence IDs in one transaction.
     * Returns the created proof ID.
     */
    public function createPendingProofWithAbsences(array $proofData, array $absenceIds): int
    {
        $this->db->beginTransaction();
        try {
            $sqlInsert = "
                INSERT INTO proof (
                    student_identifier,
                    absence_start_date,
                    absence_end_date,
                    concerned_courses,
                    main_reason,
                    custom_reason,
                    file_path,
                    proof_files,
                    student_comment,
                    status,
                    submission_date
                )
                VALUES (
                    :student_identifier,
                    :absence_start_date,
                    :absence_end_date,
                    :concerned_courses,
                    :main_reason,
                    :custom_reason,
                    :file_path,
                    CAST(:proof_files AS jsonb),
                    :student_comment,
                    'pending',
                    :submission_date
                )
            ";

            $this->db->execute($sqlInsert, $proofData);
            $proofId = (int) $this->db->lastInsertId();
            if ($proofId <= 0) {
                throw new Exception('Unable to create proof record');
            }

            $sqlAssoc = "INSERT INTO proof_absences (proof_id, absence_id)
                         VALUES (:proof_id, :absence_id)";
            foreach ($absenceIds as $absenceId) {
                $this->db->execute($sqlAssoc, [
                    'proof_id' => $proofId,
                    'absence_id' => (int) $absenceId,
                ]);
            }

            $this->db->commit();
            return $proofId;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Error createPendingProofWithAbsences: ' . $e->getMessage());
            throw $e;
        }
    }
}
