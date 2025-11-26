<?php
/**
 * ProofModel.php
 * 
 * Modèle de gestion des justificatifs d'absence.
 * 
 * Ce fichier gère toutes les opérations liées aux justificatifs d'absence :
 * - Récupération des détails des justificatifs (avec informations étudiant, dates, heures, fichiers)
 * - Validation et rejet des justificatifs
 * - Scission de justificatifs en plusieurs périodes
 * - Gestion du verrouillage/déverrouillage
 * - Mise à jour des absences associées
 * - Gestion des fichiers multiples (via JSONB proof_files)
 * - Historique des décisions
 * 
 * @package Model
 * @author Équipe de développement S3.01
 * @version 2.0
 */

require_once __DIR__ . '/database.php';

class ProofModel
{
    private $db;

    /**
     * Constructeur - Initialise la connexion à la base de données
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère les détails complets d'un justificatif par son ID
     * 
     * Cette méthode retourne toutes les informations d'un justificatif incluant :
     * - Les informations de l'étudiant (nom, prénom, groupe)
     * - Les dates et heures de début/fin des absences concernées
     * - Les fichiers justificatifs (via proof_files JSONB ou file_path)
     * - Le statut et les commentaires
     * 
     * @param int $proofId L'identifiant unique du justificatif
     * @return array|null Tableau associatif avec les détails du justificatif, null si non trouvé
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
                error_log("ProofModel->getProofDetails: Aucun résultat pour proof_id=$proofId");
                return null;
            }

            // récupération heure de début et de fin via la table proof_absences
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
            } else {
                // Fallback: chercher par étudiant et dates
                $sqlAbsFallback = "SELECT cs.course_date, cs.start_time, cs.end_time
                    FROM absences a
                    JOIN course_slots cs ON a.course_slot_id = cs.id
                    WHERE a.student_identifier = :student_identifier
                      AND cs.course_date BETWEEN :start_date AND :end_date
                    ORDER BY cs.course_date ASC, cs.start_time ASC";
                $absences = $this->db->select($sqlAbsFallback, [
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
                    $result['absence_start_datetime'] = $result['absence_start_date'] . ' 00:00:00';
                    $result['absence_end_datetime'] = $result['absence_end_date'] . ' 00:00:00';
                }
            }

            // Extraire tous les fichiers associés au justificatif
            // (gère proof_files JSONB et file_path legacy)
            $result['files'] = $this->extractProofFiles($result);

            return $result;
        } catch (Exception $e) {
            error_log("Erreur ProofModel->getProofDetails : " . $e->getMessage());
            return null;
        }
    }

    /**
     * Extrait la liste des fichiers associés à un justificatif
     * 
     * Gère plusieurs formats de stockage :
     * 1. proof_files JSONB : ["path1", "path2"] ou [{"path": "path1"}, {"file_path": "path2"}]
     * 2. file_path legacy : un seul chemin de fichier
     * 
     * @param array $proof Les données du justificatif incluant proof_files et file_path
     * @return array Tableau des chemins de fichiers (peut être vide)
     */
    private function extractProofFiles(array $proof): array
    {
        $files = [];
        
        // 1. Vérifier le champ proof_files (JSONB)
        if (!empty($proof['proof_files'])) {
            $jsonFiles = $proof['proof_files'];
            
            // Si c'est une chaîne JSON, la décoder
            if (is_string($jsonFiles)) {
                $decoded = json_decode($jsonFiles, true);
                if (is_array($decoded)) {
                    $jsonFiles = $decoded;
                }
            }
            
            // Si c'est un tableau, extraire les chemins
            if (is_array($jsonFiles)) {
                foreach ($jsonFiles as $file) {
                    if (is_string($file)) {
                        $files[] = $file;
                    } elseif (is_array($file) && isset($file['path'])) {
                        $files[] = $file['path'];
                    } elseif (is_array($file) && isset($file['file_path'])) {
                        $files[] = $file['file_path'];
                    }
                }
            }
        }
        
        // 2. Fallback sur file_path si proof_files est vide
        if (empty($files) && !empty($proof['file_path'])) {
            $files[] = $proof['file_path'];
        }
        
        return $files;
    }

    // Met à jour le statut du justificatif
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
            if (session_status() === PHP_SESSION_NONE) {@session_start();}
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
        if (!$datetimeStr) return '';
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

    /**
     * Scinde un justificatif en N périodes distinctes
     * 
     * Permet de diviser un justificatif en plusieurs justificatifs distincts avec des périodes précises.
     * Chaque période peut être :
     * - Validée automatiquement (si validate=true)
     * - Mise en attente (pending par défaut)
     * 
     * Les absences sont réassignées aux nouveaux justificatifs selon les plages horaires.
     * Le justificatif original est supprimé après la scission.
     * 
     * @param int $proofId L'identifiant du justificatif à scinder
     * @param array $periods Tableau de périodes : [['start' => 'YYYY-MM-DD HH:MM', 'end' => 'YYYY-MM-DD HH:MM', 'validate' => bool]]
     * @param string $reason Raison de la scission
     * @param int|null $userId ID de l'utilisateur effectuant la scission (pour l'historique)
     * @return bool True si la scission a réussi, false sinon
     */
    public function splitProofMultiple(int $proofId, array $periods, string $reason, ?int $userId = null): bool
    {
        $this->db->beginTransaction();
        try {
            // Récupérer le justificatif original avec toutes ses données
            $proof = $this->getProofDetails($proofId);
            if (!$proof) {
                throw new Exception("Justificatif introuvable");
            }

            $newProofIds = [];
            $sqlInsert = "INSERT INTO proof (
                student_identifier, absence_start_date, absence_end_date,
                concerned_courses, main_reason, custom_reason, file_path,
                student_comment, status, submission_date, manager_comment
            ) VALUES (
                :student_identifier, :start_date, :end_date,
                :concerned_courses, :main_reason, :custom_reason, :file_path,
                :student_comment, :status, :submission_date, :manager_comment
            )";

            // Créer un justificatif pour chaque période
            foreach ($periods as $index => $period) {
                // Définir le statut : 'validated' si validate=true, sinon 'pending'
                $status = (!empty($period['validate']) && $period['validate'] === true) ? 'validated' : 'pending';
                
                $this->db->execute($sqlInsert, [
                    'student_identifier' => $proof['student_identifier'],
                    'start_date' => substr($period['start'], 0, 10),
                    'end_date' => substr($period['end'], 0, 10),
                    'concerned_courses' => $proof['concerned_courses'] ?? null,
                    'main_reason' => $proof['main_reason'],
                    'custom_reason' => $proof['custom_reason'],
                    'file_path' => $proof['file_path'] ?? null,
                    'student_comment' => $proof['student_comment'] ?? null,
                    'status' => $status,
                    'submission_date' => $proof['submission_date'],
                    'manager_comment' => 'Scindé depuis justificatif #' . $proofId . ' (période ' . ($index + 1) . ') : ' . $reason
                ]);
                $newProofId = $this->db->lastInsertId();
                $newProofIds[] = $newProofId;
                
                // Si validé, enregistrer dans l'historique
                if ($status === 'validated' && $userId !== null) {
                    try {
                        $sqlHistoryValidation = "INSERT INTO decision_history
                            (justification_id, user_id, action, old_status, new_status, comment, created_at)
                            VALUES (:justification_id, :user_id, 'validate', 'pending', 'validated', :comment, NOW())";
                        $this->db->execute($sqlHistoryValidation, [
                            'justification_id' => $newProofId,
                            'user_id' => $userId,
                            'comment' => 'Validé automatiquement lors de la scission'
                        ]);
                    } catch (Exception $e) {
                        error_log("Erreur lors de l'enregistrement de l'historique de validation : " . $e->getMessage());
                    }
                }
            }

            // Réassigner les absences aux nouveaux justificatifs selon les périodes
            $sqlInsertAbsences = "INSERT INTO proof_absences (proof_id, absence_id)
                SELECT :new_proof_id, pa.absence_id
                FROM proof_absences pa
                JOIN absences a ON pa.absence_id = a.id
                JOIN course_slots cs ON a.course_slot_id = cs.id
                WHERE pa.proof_id = :old_proof_id
                  AND (cs.course_date || ' ' || cs.start_time)::timestamp >= :start_datetime::timestamp
                  AND (cs.course_date || ' ' || cs.end_time)::timestamp <= :end_datetime::timestamp";

            foreach ($periods as $index => $period) {
                $this->db->execute($sqlInsertAbsences, [
                    'new_proof_id' => $newProofIds[$index],
                    'old_proof_id' => $proofId,
                    'start_datetime' => $period['start'],
                    'end_datetime' => $period['end']
                ]);
            }

            // Enregistrer dans l'historique
            if ($userId !== null) {
                $sqlHistory = "INSERT INTO decision_history
                    (justification_id, user_id, action, old_status, new_status, comment, created_at)
                    VALUES (:justification_id, :user_id, 'split', :old_status, 'deleted', :comment, NOW())";
                try {
                    $this->db->execute($sqlHistory, [
                        'justification_id' => $proofId,
                        'user_id' => $userId,
                        'old_status' => $proof['status'] ?? 'pending',
                        'comment' => 'Scindé en ' . count($periods) . ' justificatifs (#' . implode(', #', $newProofIds) . ') : ' . $reason
                    ]);
                } catch (Exception $e) {
                    error_log("Erreur lors de l'enregistrement de l'historique de scission : " . $e->getMessage());
                }
            }

            // Supprimer les liens dans proof_absences de l'original
            $sqlDeleteAbsences = "DELETE FROM proof_absences WHERE proof_id = :proof_id";
            $this->db->execute($sqlDeleteAbsences, ['proof_id' => $proofId]);

            // Supprimer le justificatif original
            $sqlDelete = "DELETE FROM proof WHERE id = :id";
            $this->db->execute($sqlDelete, ['id' => $proofId]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur splitProofMultiple : " . $e->getMessage());
            if (session_status() === PHP_SESSION_NONE) {@session_start();}
            $_SESSION['last_model_error'] = "splitProofMultiple: " . $e->getMessage();
            return false;
        }
    }

    // Scinde un justificatif en deux périodes distinctes (conservé pour compatibilité)
    public function splitProof(int $proofId, string $split1Start, string $split1End, string $split2Start, string $split2End, string $reason, ?int $userId = null): bool
    {
        $this->db->beginTransaction();
        try {
            // Récupérer le justificatif original
            $proof = $this->getProofDetails($proofId);
            if (!$proof) {
                throw new Exception("Justificatif introuvable");
            }

            // Créer le premier justificatif
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
                'manager_comment' => 'Scindé depuis justificatif #' . $proofId . ' : ' . $reason
            ]);
            $newProofId1 = $this->db->lastInsertId();

            // Créer le second justificatif
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
                'manager_comment' => 'Scindé depuis justificatif #' . $proofId . ' : ' . $reason
            ]);
            $newProofId2 = $this->db->lastInsertId();

            // Réassigner les absences aux nouveaux justificatifs en tenant compte des heures
            $sqlUpdateAbs1 = "INSERT INTO proof_absences (proof_id, absence_id)
                SELECT :new_proof_id, pa.absence_id
                FROM proof_absences pa
                JOIN absences a ON pa.absence_id = a.id
                JOIN course_slots cs ON a.course_slot_id = cs.id
                WHERE pa.proof_id = :old_proof_id
                  AND (cs.course_date || ' ' || cs.start_time)::timestamp >= :start_datetime::timestamp
                  AND (cs.course_date || ' ' || cs.end_time)::timestamp <= :end_datetime::timestamp";
            
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

            // Enregistrer la scission dans l'historique avant suppression
            if ($userId !== null) {
                $sqlHistory = "INSERT INTO decision_history
                    (justification_id, user_id, action, old_status, new_status, comment, created_at)
                    VALUES (:justification_id, :user_id, 'split', :old_status, 'deleted', :comment, NOW())";
                try {
                    $this->db->execute($sqlHistory, [
                        'justification_id' => $proofId,
                        'user_id' => $userId,
                        'old_status' => $proof['status'] ?? 'pending',
                        'comment' => 'Scindé en justificatifs #' . $newProofId1 . ' et #' . $newProofId2 . ' : ' . $reason
                    ]);
                } catch (Exception $e) {
                    error_log("Erreur lors de l'enregistrement de l'historique de scission : " . $e->getMessage());
                }
            }

            // Supprimer les liens dans proof_absences de l'original
            $sqlDeleteAbsences = "DELETE FROM proof_absences WHERE proof_id = :proof_id";
            $this->db->execute($sqlDeleteAbsences, ['proof_id' => $proofId]);

            // Supprimer le justificatif original
            $sqlDelete = "DELETE FROM proof WHERE id = :id";
            $this->db->execute($sqlDelete, ['id' => $proofId]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur splitProof : " . $e->getMessage());
            if (session_status() === PHP_SESSION_NONE) {@session_start();}
            $_SESSION['last_model_error'] = "splitProof: " . $e->getMessage();
            return false;
        }
    }

    // Traduction simple
    public function translate(string $category, string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $value = (string)$value;
        $maps = [
            'status' => [
                'pending' => 'En attente',
                'approved' => 'Validé',
                'accepted' => 'Validé',
                'rejected' => 'Refusé',
                'under_review' => 'En cours d\'examen',
                'split' => 'Scindé',
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
