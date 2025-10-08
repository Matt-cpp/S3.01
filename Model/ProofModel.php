<?php
require_once __DIR__ . '/database.php';

class ProofModel
{
    private $db;

    public function __construct()
    {
        $this->db = getDatabase();
    }

    /**
     * Récupère les informations complètes d’un justificatif d’absence
     */
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

            // ✅ Correction : fetch() peut renvoyer false, on le convertit en null
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
        $sql = "UPDATE proof SET status = :status WHERE id = :id";
        try {
            $affected = $this->db->execute($sql, ['status' => $status, 'id' => $proofId]);
            echo "<pre>Résultat update : lignes affectées = " . var_export($affected, true) . "</pre>";
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

    public function setRejectionReason(int $proofId, string $reason, string $details = ''): bool
    {
        $sql = "UPDATE proof SET rejection_reason = :reason, manager_comment = :details, updated_at = NOW() WHERE id = :id";
        try {
            $this->db->execute($sql, [
                'reason' => $reason,
                'details' => $details,
                'id' => $proofId
            ]);
            return true;
        } catch (Exception $e) {
            error_log("Erreur setRejectionReason : " . $e->getMessage());
            return false;
        }
    }


}
