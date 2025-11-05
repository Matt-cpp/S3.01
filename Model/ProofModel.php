<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/AbsenceMonitoringModel.php';

class ProofModel
{
    private $db;
    private $monitoringModel;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->monitoringModel = new AbsenceMonitoringModel();
    }

    //Récupère les informations complètes d’un justificatif d’absence
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
        p.status,
        p.submission_date,
        u.last_name,
        u.first_name,
        g.label AS group_label
    FROM proof p
    JOIN users u ON LOWER(u.identifier) = LOWER(p.student_identifier)
    LEFT JOIN user_groups ug ON ug.user_id = u.id
    LEFT JOIN groups g ON g.id = ug.group_id
    WHERE p.id = :id
";

        try {
            $result = $this->db->selectOne($sql, ['id' => $proofId]);

            if ($result === false) {
                return null;
            }

            return $result;
        } catch (Exception $e) {
            error_log("Erreur ProofModel->getProofDetails : " . $e->getMessage());
            return null;
        }
    }
    public function updateProofStatus(int $proofId, string $status): bool
    {
        try {
            // First, get the proof details to update monitoring
            $proofDetails = $this->getProofDetails($proofId);

            // Update proof status
            $sql = "UPDATE proof SET status = :status WHERE id = :id";
            $affected = $this->db->execute($sql, ['status' => $status, 'id' => $proofId]);
            echo "<pre>Résultat update : lignes affectées = " . var_export($affected, true) . "</pre>";

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
            error_log("Erreur updateProofStatus : " . $e->getMessage());
            return false;
        }
    }

    public function updateAbsencesForProof(string $studentIdentifier, string $startDate, string $endDate, string $decision)
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
        } elseif ($decision === 'rejected') {
            $sql = "UPDATE absences a
            SET status = 'absent', justified = FALSE, updated_at = NOW()
            FROM course_slots cs
            WHERE a.course_slot_id = cs.id
              AND a.student_identifier = :student_identifier
              AND cs.course_date BETWEEN :start_date AND :end_date
              AND a.status = 'excused'
              AND a.justified = TRUE";
            $this->db->execute($sql, [
                'student_identifier' => $studentIdentifier,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
        }
    }
    // revoir la modification de la table proof pour ajouter la colonne rejection_reason
    public function setRejectionReason(int $proofId, string $reason, string $comment = ''): bool
    {
        $sql = "UPDATE proof
            SET rejection_reason = :reason,
                manager_comment = :comment,
                updated_at = NOW()
            WHERE id = :id";
        try {
            $this->db->execute($sql, [
                'reason' => $reason,
                'comment' => $comment,
                'id' => $proofId
            ]);
            return true;
        } catch (\Exception $e) {
            error_log("Erreur setRejectionReason : " . $e->getMessage());
            return false;
        }
    }



}
