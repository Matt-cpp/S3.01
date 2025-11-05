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
    // Récupère les détails d'un justificatif par son ID
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
            $absences = $this->db->select($sqlAbs, [
                'student_identifier' => $result['student_identifier'],
                'start_date' => $result['absence_start_date'],
                'end_date' => $result['absence_end_date']
            ]);
            if ($absences && count($absences) > 0) {
                $first = $absences[0];
                $last = $absences[count($absences) - 1];
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

    // Met à jour le statut du justificatif
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

    // Met à jour les absences associées au justificatif en fonction de la décision prise
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


    // Enregistre la raison de rejet et le commentaire dans decision_history
    public function setRejectionReason(int $proofId, string $reason, string $comment = '', ?int $userId = null): bool
    {
        $this->db->beginTransaction();
        try {
            // Récupération de l'ancien statut avant modification
            $proof = $this->getProofDetails($proofId);
            $oldStatus = $proof ? $proof['status'] : null;

            // Si aucun userId fourni, essayer d'utiliser processed_by_user_id du justificatif
            if ($userId === null) {
                try {
                    $row = $this->db->selectOne("SELECT processed_by_user_id FROM proof WHERE id = :id", ['id' => $proofId]);
                    if ($row && !empty($row['processed_by_user_id'])) {
                        $userId = $row['processed_by_user_id'];
                    }
                } catch (Exception $e) {
                    // ignore, userId restera null
                }
            }

            // Si toujours aucun user_id après tentative, utiliser un fallback configurable (SYSTEM_USER_ID) ou 1
            if ($userId === null) {
                $fallback = (int) (env('SYSTEM_USER_ID', '1') ?? 1);
                error_log("setRejectionReason: aucun user_id trouvé, utilisation du fallback SYSTEM_USER_ID={$fallback}");
                $userId = $fallback;
            }
            // Vérifier que le userId existe dans la table users ; sinon, essayer de récupérer le premier user existant
            try {
                $exists = $this->db->selectOne("SELECT id FROM users WHERE id = :id", ['id' => $userId]);
                if (!$exists) {
                    $first = $this->db->selectOne("SELECT id FROM users ORDER BY id LIMIT 1");
                    if ($first && isset($first['id'])) {
                        error_log("setRejectionReason: user_id {$userId} introuvable, fallback vers user_id {$first['id']}");
                        $userId = $first['id'];
                    } else {
                        throw new \Exception('Aucun utilisateur trouvé dans la table users ; créer au moins un utilisateur avant d\'enregistrer une décision');
                    }
                }
            } catch (Exception $e) {
                throw $e; // sera capturé par le catch de la transaction
            }
            // Mise à jour du statut et du commentaire dans proof (dans la même transaction)
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

            // Insertion dans decision_history : stocker le motif dans la colonne rejection_reason
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
            error_log("Erreur setRejectionReason : " . $e->getMessage());
            // Stocker le message d'erreur en session pour affichage côté presenter (dev)
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            $_SESSION['last_model_error'] = "setRejectionReason: " . $e->getMessage();
            return false;
        }
    }

    public function setValidationReason(int $proofId, string $reason, string $comment = '', ?int $userId = null): bool
    {
        $this->db->beginTransaction();
        try {
            // Récupération de l'ancien statut avant modification
            $proof = $this->getProofDetails($proofId);
            $oldStatus = $proof ? $proof['status'] : null;

            // Si aucun userId fourni, essayer d'utiliser processed_by_user_id du justificatif
            if ($userId === null) {
                try {
                    $row = $this->db->selectOne("SELECT processed_by_user_id FROM proof WHERE id = :id", ['id' => $proofId]);
                    if ($row && !empty($row['processed_by_user_id'])) {
                        $userId = $row['processed_by_user_id'];
                    }
                } catch (Exception $e) {
                    // ignore, userId restera null
                }
            }

            // Si toujours aucun user_id après tentative, utiliser un fallback configurable (SYSTEM_USER_ID) ou 1
            if ($userId === null) {
                $fallback = (int) (env('SYSTEM_USER_ID', '1') ?? 1);
                error_log("setValidationReason: aucun user_id trouvé, utilisation du fallback SYSTEM_USER_ID={$fallback}");
                $userId = $fallback;
            }
            // Vérifier que le userId existe dans la table users ; sinon, essayer de récupérer le premier user existant
            try {
                $exists = $this->db->selectOne("SELECT id FROM users WHERE id = :id", ['id' => $userId]);
                if (!$exists) {
                    $first = $this->db->selectOne("SELECT id FROM users ORDER BY id LIMIT 1");
                    if ($first && isset($first['id'])) {
                        error_log("setValidationReason: user_id {$userId} introuvable, fallback vers user_id {$first['id']}");
                        $userId = $first['id'];
                    } else {
                        throw new \Exception('Aucun utilisateur trouvé dans la table users ; créer au moins un utilisateur avant d\'enregistrer une décision');
                    }
                }
            } catch (Exception $e) {
                throw $e; // sera capturé par le catch de la transaction
            }
            // Mise à jour du statut et du commentaire dans proof (dans la même transaction)
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

            // Insertion dans decision_history : stocker le motif de validation dans la colonne rejection_reason
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

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur setValidationReason : " . $e->getMessage());
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            $_SESSION['last_model_error'] = "setValidationReason: " . $e->getMessage();
            return false;
        }
    }

    // Enregistre une demande d'information (commentaire obligatoire) et insère une ligne dans decision_history
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
                error_log("setRequestInfo: aucun user_id trouvé, utilisation du fallback SYSTEM_USER_ID={$fallback}");
                $userId = $fallback;
            }

            // Vérifier existence user
            try {
                $exists = $this->db->selectOne("SELECT id FROM users WHERE id = :id", ['id' => $userId]);
                if (!$exists) {
                    $first = $this->db->selectOne("SELECT id FROM users ORDER BY id LIMIT 1");
                    if ($first && isset($first['id'])) {
                        error_log("setRequestInfo: user_id {$userId} introuvable, fallback vers user_id {$first['id']}");
                        $userId = $first['id'];
                    } else {
                        throw new \Exception('Aucun utilisateur trouvé dans la table users ; créer au moins un utilisateur avant d\'enregistrer une décision');
                    }
                }
            } catch (Exception $e) {
                throw $e;
            }

            // Mise à jour du commentaire et du statut en under_review
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

            // Insertion dans decision_history avec action request_info
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
            error_log("Erreur setRequestInfo : " . $e->getMessage());
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            $_SESSION['last_model_error'] = "setRequestInfo: " . $e->getMessage();
            return false;
        }
    }

    // Récupère les motifs de rejet ou de validation en fonction du type depuis la table rejection_validation_reasons
    public function getReasons(string $type): array
    {
        $sql = "SELECT label FROM rejection_validation_reasons WHERE type_of_reason = :type ORDER BY label ASC";
        try {
            $results = $this->db->select($sql, ['type' => $type]);
            return array_map(fn($row) => $row['label'], $results);
        } catch (Exception $e) {
            error_log("Erreur getReasons : " . $e->getMessage());
            return [];
        }
    }

    // Ajoute un nouveau motif de rejet ou de validation dans la table rejection_validation_reasons en fonction du type
    public function addReason(string $label, string $type): bool
    {
        // insère seulement si le couple (label, type_of_reason) n'existe pas
        // ON CONFLICT doit correspondre à un index existant: le schéma a une contrainte UNIQUE(label)
        // on utilise ON CONFLICT (label) DO NOTHING pour éviter une erreur si aucun index composite n'existe
        $sql = "INSERT INTO rejection_validation_reasons (label, type_of_reason)
                VALUES (:label, :type)
                ON CONFLICT (label) DO NOTHING";
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare($sql);
            $stmt->execute(['label' => $label, 'type' => $type]);
            // Si la requête a réussi, vérifier si l'enregistrement existe (fallback)
            $checkSql = "SELECT id FROM rejection_validation_reasons WHERE label = :label AND type_of_reason = :type LIMIT 1";
            $exists = $this->db->select($checkSql, ['label' => $label, 'type' => $type]);
            return !empty($exists);
        } catch (Exception $e) {
            error_log("Erreur addReason : " . $e->getMessage());
            return false;
        }
    }

    // méthodes getter pour les motifs de rejet et de validation et méthodes pour ajouter un motif de rejet ou de validation directement avec le type (rejet ou validation)
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

    public function deverouiller(int $proofId): bool
    {
        $sql = "UPDATE proof SET locked = 'false' WHERE id = :id";
        try {
            $affected = $this->db->execute($sql, ['id' => $proofId]);
            return $affected > 0;
        } catch (Exception $e) {
            error_log("Erreur deverouiller : " . $e->getMessage());
            return false;
        }
    }

    public function verrouiller(int $proofId): bool
    {
        $sql = "UPDATE proof SET locked = 'true' WHERE id = :id";
        try {
            $affected = $this->db->execute($sql, ['id' => $proofId]);
            return $affected > 0;
        } catch (Exception $e) {
            error_log("Erreur verrouiller : " . $e->getMessage());
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
            error_log("Erreur isLocked : " . $e->getMessage());
            return false;
        }
    }

    // Fonction pour formater la date au format français
    public function formatDateFr($datetimeStr)
    {
        if (!$datetimeStr)
            return '';
        try {
            $date = new DateTime($datetimeStr);
            // Utiliser IntlDateFormatter si disponible (recommandé depuis PHP 8.1)
            if (class_exists('\IntlDateFormatter')) {
                // pattern : 02/01/2025 à 14h30
                $pattern = "dd/MM/yyyy 'à' HH'h'mm";
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
                    return $date->format('d/m/Y \à H\hi');
                }
                return $formatted;
            }

            // Fallback simple si Intl non disponible
            return $date->format('d/m/Y \à H\hi');
        } catch (Exception $e) {
            error_log("Erreur formatDateFr: " . $e->getMessage());
            return '';
        }
    }

    // Traduction simple
    public function translate(string $category, string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $value = (string) $value;
        $maps = [
            'status' => [
                'pending' => 'En attente',
                'approved' => 'Validé',
                'accepted' => 'Validé',
                'rejected' => 'Refusé',
                'under_review' => 'En cours d\'examen',
            ],
            'reason' => [
                'illness' => 'Maladie',
                'death' => 'Décès',
                'family_obligations' => 'Obligations familiales',
                'other' => 'Autre',
            ],
        ];
        return $maps[$category][$value] ?? $value;
    }
}
