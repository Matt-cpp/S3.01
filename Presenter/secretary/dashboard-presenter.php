<?php
/**
 * Fichier: dashboard-presenter.php 
 * Ce fichier gère la logique métier du tableau de bord secrétariat.
 * 
 * Fonctionnalités principales :
 * - Recherche d'étudiants avec filtrage par nom/identifiant
 * - Recherche et création de matières (ressources)
 * - Recherche et création de salles
 * - Saisie manuelle d'absences avec création de créneaux de cours
 * - Gestion de l'historique des imports et actions
 * 
 * @author Équipe S3.01
 * @version 1.0
 */

require_once __DIR__ . '/../../Model/database.php';

class DashboardSecretaryPresenter
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // Récupère la liste des étudiants avec recherche par nom ou identifiant
    public function searchStudents($query)
    {
        $sql = "SELECT id, identifier, first_name, last_name, email 
                FROM users 
                WHERE role = 'student' 
                AND (first_name ILIKE :query OR last_name ILIKE :query OR identifier ILIKE :query)
                ORDER BY last_name, first_name
                LIMIT 20";

        return $this->db->select($sql, [':query' => "%$query%"]);
    }

    // Récupère la liste des matières avec recherche par code ou libellé
    public function searchResources($query)
    {
        $sql = "SELECT id, code, label, teaching_type 
                FROM resources 
                WHERE code ILIKE :query OR label ILIKE :query
                ORDER BY label
                LIMIT 20";

        return $this->db->select($sql, [':query' => "%$query%"]);
    }

    // Récupère la liste des salles avec recherche par nom
    public function searchRooms($query)
    {
        $sql = "SELECT id, code 
                FROM rooms 
                WHERE code ILIKE :query
                ORDER BY code
                LIMIT 20";

        return $this->db->select($sql, [':query' => "%$query%"]);
    }

    // Crée une nouvelle matière dans la base de données
    public function createResource($code)
    {
        // Vérifie si la matière existe déjà
        $existing = $this->db->selectOne(
            "SELECT id FROM resources WHERE code = :code",
            [':code' => $code]
        );

        if ($existing) {
            throw new Exception("Une matière avec ce code existe déjà");
        }

        $sql = "INSERT INTO resources (code, label) 
                VALUES (:code, :label) 
                RETURNING id, code, label, teaching_type";

        $result = $this->db->selectOne($sql, [
            ':code' => $code,
            ':label' => $code  // Utilise le code comme libellé
        ]);

        return $result;
    }

    // Crée une nouvelle salle dans la base de données
    public function createRoom($code)
    {
        // Vérifie si la salle existe déjà
        $existing = $this->db->selectOne(
            "SELECT id FROM rooms WHERE code = :code",
            [':code' => $code]
        );

        if ($existing) {
            throw new Exception("Une salle avec ce code existe déjà");
        }

        $sql = "INSERT INTO rooms (code) 
                VALUES (:code) 
                RETURNING id, code";

        $result = $this->db->selectOne($sql, [':code' => $code]);

        return $result;
    }

    // Crée une absence manuellement avec création du créneau de cours associé
    public function createManualAbsence($data)
    {
        try {
            $this->db->beginTransaction();

            // Récupère l'identifiant de l'étudiant
            $student = $this->db->selectOne(
                "SELECT identifier FROM users WHERE id = :id",
                [':id' => $data['student_id']]
            );

            if (!$student) {
                throw new Exception("Étudiant non trouvé");
            }

            // Récupère les heures de début et de fin
            $startTime = trim($data['start_time']);
            $endTime = trim($data['end_time']);

            // Calcule la durée en minutes
            $timezone = new DateTimeZone('Europe/Paris');
            $start = new DateTime($startTime, $timezone);
            $end = new DateTime($endTime, $timezone);
            $interval = $start->diff($end);
            $duration = ($interval->h * 60) + $interval->i;

            // Crée le créneau de cours dans la base de données
            $courseSlotSql = "INSERT INTO course_slots 
                (course_date, start_time, end_time, duration_minutes, course_type, 
                 resource_id, room_id, is_evaluation) 
                VALUES (:date, :start_time, :end_time, :duration, :course_type, 
                        :resource_id, :room_id, :is_evaluation)
                RETURNING id";

            $courseSlotResult = $this->db->selectOne($courseSlotSql, [
                ':date' => $data['absence_date'],
                ':start_time' => $startTime,
                ':end_time' => $endTime,
                ':duration' => $duration,
                ':course_type' => $data['course_type'],
                ':resource_id' => $data['resource_id'],
                ':room_id' => $data['room_id'],
                ':is_evaluation' => isset($data['is_evaluation']) ? 'true' : 'false'
            ]);

            $courseSlotId = $courseSlotResult['id'];

            // Crée l'absence dans la base de données
            $absenceSql = "INSERT INTO absences 
                (student_identifier, course_slot_id, status, justified) 
                VALUES (:student_identifier, :course_slot_id, 'absent', false)
                RETURNING id";

            $absenceResult = $this->db->selectOne($absenceSql, [
                ':student_identifier' => $student['identifier'],
                ':course_slot_id' => $courseSlotId
            ]);

            $this->db->commit();

            // Enregistre l'action dans l'historique
            $this->logImportHistory(
                'Saisie manuelle',
                "Absence créée pour {$student['identifier']} le {$data['absence_date']} ({$startTime} - {$endTime})",
                'success'
            );

            return $absenceResult['id'];

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // Enregistre une action dans l'historique des imports
    public function logImportHistory($action, $details, $status = 'success')
    {
        $sql = "INSERT INTO import_history (action_type, description, status, created_at) 
                VALUES (:action, :details, :status, NOW())";

        try {
            $this->db->execute($sql, [
                ':action' => $action,
                ':details' => $details,
                ':status' => $status
            ]);
        } catch (Exception $e) {
            // Crée la table si elle n'existe pas encore
            $this->createImportHistoryTable();
            // Réessaie l'insertion
            $this->db->execute($sql, [
                ':action' => $action,
                ':details' => $details,
                ':status' => $status
            ]);
        }
    }

    // Récupère l'historique des imports et actions récentes
    public function getImportHistory($limit = 50)
    {
        $sql = "SELECT action_type as action, description as details, status, created_at 
                FROM import_history 
                ORDER BY created_at DESC 
                LIMIT :limit";

        try {
            return $this->db->select($sql, [':limit' => $limit]);
        } catch (Exception $e) {
            // La table n'existe peut-être pas encore
            $this->createImportHistoryTable();
            return [];
        }
    }

    // Crée la table d'historique des imports si elle n'existe pas
    private function createImportHistoryTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS import_history (
            id SERIAL PRIMARY KEY,
            action VARCHAR(255) NOT NULL,
            details TEXT,
            status VARCHAR(50) DEFAULT 'success',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";

        $this->db->getConnection()->exec($sql);
    }
}
