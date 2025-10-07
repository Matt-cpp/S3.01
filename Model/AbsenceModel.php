<?php

require_once __DIR__ . '/database.php';

class AbsenceModel {
    private $db;
    
    public function __construct() {
        $this->db = getDatabase();
    }
    
    /**
     * Récupère toutes les absences avec des filtres optionnels
     */
    public function getAllAbsences($filters = []) {
        $query = "
            SELECT DISTINCT ON (a.id)
                a.id as absence_id,
                CONCAT(u.first_name, ' ', u.last_name) as student_name,
                u.identifier as student_identifier,
                COALESCE(r.label, 'Non spécifié') as course,
                cs.course_date as date,
                cs.start_time::text as start_time,
                cs.end_time::text as end_time,
                cs.course_type,
                a.justified as status,
                p.main_reason as motif,
                p.file_path as file_path
            FROM absences a
            JOIN users u ON a.student_identifier = u.identifier
            JOIN course_slots cs ON a.course_slot_id = cs.id
            LEFT JOIN resources r ON cs.resource_id = r.id
            LEFT JOIN proof_absences pa ON a.id = pa.absence_id
            LEFT JOIN proof p ON pa.proof_id = p.id
            WHERE 1=1
        ";
        
        $params = [];
        $conditions = [];
        
        if (!empty($filters['name'])) {
            $conditions[] = "(u.first_name ILIKE :name OR u.last_name ILIKE :name)";
            $params[':name'] = '%' . $filters['name'] . '%';
        }
        
        if (!empty($filters['start_date'])) {
            $conditions[] = "cs.course_date >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $conditions[] = "cs.course_date <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }
        
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'justifiée') {
                $conditions[] = "a.justified = true";
            } elseif ($filters['status'] === 'non_justifiée') {
                $conditions[] = "a.justified = false";
            }
        }
        
        if (!empty($filters['course_type'])) {
            $conditions[] = "cs.course_type = :course_type";
            $params[':course_type'] = $filters['course_type'];
        }
        
        if (!empty($conditions)) {
            $query .= " AND " . implode(" AND ", $conditions);
        }
        
        $query .= " ORDER BY a.id, cs.course_date DESC, cs.start_time DESC";
        
        try {
            return $this->db->select($query, $params);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des absences: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Récupère tous les types de cours disponibles
     */
    public function getCourseTypes() {
        $query = "SELECT DISTINCT course_type FROM course_slots WHERE course_type IS NOT NULL ORDER BY course_type";
        try {
            return $this->db->select($query);
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des types de cours: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Récupère le nom d'un utilisateur
     */
    public function getUserName() {
        $result = $this->db->select("SELECT first_name, last_name FROM users");
        $name = !empty($result) ? $result[0] : null;
        return $name ? $name['first_name'] . ' ' . $name['last_name'] : '';
    }
}