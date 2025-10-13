<?php
require_once __DIR__ . '/database.php';

class ProofModel
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
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

            if ($result === false) {
                return null;
            }

            // récupération heure de début et de fin
            $sqlAbs = "SELECT cs.course_date, cs.start_time, cs.end_time
                FROM absences a
                JOIN course_slots cs ON a.course_slot_id = cs.id
                WHERE a.student_identifier = :student_identifier
                  AND cs.course_date BETWEEN :start_date AND :end_date
                ORDER BY cs.course_date ASC, cs.start_time ASC";
            $absences = $this->db->selectAll($sqlAbs, [
                'student_identifier' => $result['student_identifier'],
                'start_date' => $result['absence_start_date'],
                'end_date' => $result['absence_end_date']
            ]);
            if ($absences && count($absences) > 0) {
                $first = $absences[0];
                $last = $absences[count($absences)-1];
                $result['absence_start_datetime'] = $first['course_date'] . ' ' . $first['start_time'];
                $result['absence_end_datetime'] = $last['course_date'] . ' ' . $last['end_time'];
            } else {
                $result['absence_start_datetime'] = $result['absence_start_date'];
                $result['absence_end_datetime'] = $result['absence_end_date'];
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
// revoir la modification de la table proof pour ajouter la colonne rejection_reason
    public function setRejectionReason(int $proofId, string $reason, string $comment = '', int $userId = null): bool
    {
        $this->db->beginTransaction();
        try {
            // Mise à jour du commentaire dans proof
            $sql = "UPDATE proof
            SET manager_comment = :comment,
                updated_at = NOW()
            WHERE id = :id";
            $this->db->execute($sql, [
                'comment' => $comment,
                'id' => $proofId
            ]);

            // Récupération de l'ancien statut
            $proof = $this->getProofDetails($proofId);
            $oldStatus = $proof ? $proof['status'] : null;

            // Insertion dans decision_history avec la raison et le commentaire
            $sqlHistory = "INSERT INTO decision_history
            (proof_id, user_id, action, old_status, new_status, rejection_reason, comment, created_at)
            VALUES
            (:proof_id, :user_id, :action, :old_status, :new_status, :rejection_reason, :comment, NOW())";
            $this->db->execute($sqlHistory, [
                'proof_id' => $proofId,
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
            error_log("Erreur setRejectionReason : " . $e->getMessage());
            return false;
        }
    }

    // Récupère les motifs de rejet ou de validation en fonction du type depuis la table rejection_validation_reasons
    public function getReasons(string $type): array
    {
        $sql = "SELECT label FROM rejection_validation_reasons WHERE type_of_reason = :type ORDER BY label ASC";
        try {
            $results = $this->db->selectAll($sql, ['type' => $type]);
            return array_map(fn($row) => $row['label'], $results);
        } catch (Exception $e) {
            error_log("Erreur getReasons : " . $e->getMessage());
            return [];
        }
    }
// Ajoute un nouveau motif de rejet ou de validation dans la table rejection_validation_reasons en fonction du type
    public function addReason(string $label, string $type): bool
    {
        $sql = "INSERT IGNORE INTO rejection_validation_reasons (label, type_of_reason) VALUES (:label, :type)";
        try {
            $this->db->execute($sql, ['label' => $label, 'type' => $type]);
            return true;
        } catch (Exception $e) {
            error_log("Erreur addReason : " . $e->getMessage());
            return false;
        }



}
