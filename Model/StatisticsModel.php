<?php

require_once __DIR__ . '/database.php';

class StatisticsModel
{
    private $db;

    public function __construct()
    {
        $this->db = getDatabase();
    }

    //Get absences grouped by course type (CM/TD/TP) for a specific period
    public function getAbsencesByCourseType($filters = [])
    {
        $query = "
            SELECT 
                cs.course_type,
                COUNT(DISTINCT a.id) as total_absences
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            WHERE 1=1
        ";

        $params = [];
        $query .= $this->buildFilterConditions($filters, $params);
        $query .= " GROUP BY cs.course_type ORDER BY cs.course_type";

        try {
            return $this->db->select($query, $params);
        } catch (Exception $e) {
            error_log("Error fetching absences by course type: " . $e->getMessage());
            return [];
        }
    }

    //Get absences grouped by resource/subject
    public function getAbsencesByResource($filters = [])
    {
        $query = "
            SELECT 
                COALESCE(r.label, 'Non spécifié') as resource_label,
                COUNT(DISTINCT a.id) as total_absences
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            LEFT JOIN resources r ON cs.resource_id = r.id
            WHERE 1=1
        ";

        $params = [];
        $query .= $this->buildFilterConditions($filters, $params);
        $query .= " GROUP BY r.label ORDER BY total_absences DESC";

        try {
            return $this->db->select($query, $params);
        } catch (Exception $e) {
            error_log("Error fetching absences by resource: " . $e->getMessage());
            return [];
        }
    }

    //Get evaluation absences by resource/subject
    public function getEvaluationAbsencesByResource($filters = [])
    {
        $query = "
            SELECT 
                COALESCE(r.label, 'Non spécifié') as resource_label,
                COUNT(DISTINCT a.id) as total_absences
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            LEFT JOIN resources r ON cs.resource_id = r.id
            WHERE cs.is_evaluation = true
        ";

        $params = [];
        $query .= $this->buildFilterConditions($filters, $params);
        $query .= " GROUP BY r.label ORDER BY total_absences DESC LIMIT 10";

        try {
            return $this->db->select($query, $params);
        } catch (Exception $e) {
            error_log("Error fetching evaluation absences by resource: " . $e->getMessage());
            return [];
        }
    }

    //Get evaluation absences count
    public function getEvaluationAbsencesCount($filters = [])
    {
        $query = "
            SELECT COUNT(DISTINCT a.id) as total
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            WHERE cs.is_evaluation = true
        ";

        $params = [];
        $query .= $this->buildFilterConditions($filters, $params);

        try {
            $result = $this->db->selectOne($query, $params);
            return $result['total'] ?? 0;
        } catch (Exception $e) {
            error_log("Error fetching evaluation absences count: " . $e->getMessage());
            return 0;
        }
    }

    //Get absences trends by month
    public function getAbsencesTrendsByMonth($filters = [])
    {
        $query = "
            SELECT 
                TO_CHAR(cs.course_date, 'YYYY-MM') as month,
                TO_CHAR(cs.course_date, 'Month YYYY') as month_label,
                COUNT(DISTINCT a.id) as total_absences,
                COUNT(DISTINCT CASE WHEN a.justified = true THEN a.id END) as justified_absences,
                COUNT(DISTINCT CASE WHEN a.justified = false THEN a.id END) as unjustified_absences
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            WHERE 1=1
        ";

        $params = [];
        $query .= $this->buildFilterConditions($filters, $params);
        $query .= " GROUP BY TO_CHAR(cs.course_date, 'YYYY-MM'), TO_CHAR(cs.course_date, 'Month YYYY')
                    ORDER BY TO_CHAR(cs.course_date, 'YYYY-MM')";

        try {
            return $this->db->select($query, $params);
        } catch (Exception $e) {
            error_log("Error fetching absences trends by month: " . $e->getMessage());
            return [];
        }
    }

    //Get absences trends by resource over time
    public function getResourceTrendsOverTime($filters = [])
    {
        $query = "
            SELECT 
                COALESCE(r.label, 'Non spécifié') as resource_label,
                TO_CHAR(cs.course_date, 'YYYY-MM') as month,
                COUNT(DISTINCT a.id) as total_absences
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            LEFT JOIN resources r ON cs.resource_id = r.id
            WHERE 1=1
        ";

        $params = [];
        $query .= $this->buildFilterConditions($filters, $params);
        $query .= " GROUP BY r.label, TO_CHAR(cs.course_date, 'YYYY-MM')
                    ORDER BY TO_CHAR(cs.course_date, 'YYYY-MM'), r.label";

        try {
            return $this->db->select($query, $params);
        } catch (Exception $e) {
            error_log("Error fetching resource trends: " . $e->getMessage());
            return [];
        }
    }

    //Get absences by semester
    public function getAbsencesBySemester($filters = [])
    {
        $query = "
            SELECT 
                CASE 
                    WHEN EXTRACT(MONTH FROM cs.course_date) BETWEEN 9 AND 12 THEN 'S1'
                    WHEN EXTRACT(MONTH FROM cs.course_date) BETWEEN 1 AND 2 THEN 'S1'
                    WHEN EXTRACT(MONTH FROM cs.course_date) BETWEEN 3 AND 6 THEN 'S2'
                    ELSE 'Hors semestre'
                END as semester,
                EXTRACT(YEAR FROM cs.course_date) as year,
                COUNT(DISTINCT a.id) as total_absences,
                cs.course_type,
                COUNT(DISTINCT CASE WHEN cs.course_type = 'CM' THEN a.id END) as cm_count,
                COUNT(DISTINCT CASE WHEN cs.course_type = 'TD' THEN a.id END) as td_count,
                COUNT(DISTINCT CASE WHEN cs.course_type = 'TP' THEN a.id END) as tp_count
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            WHERE 1=1
        ";

        $params = [];
        $query .= $this->buildFilterConditions($filters, $params);
        $query .= " GROUP BY semester, year, cs.course_type
                    ORDER BY year DESC, semester";

        try {
            return $this->db->select($query, $params);
        } catch (Exception $e) {
            error_log("Error fetching absences by semester: " . $e->getMessage());
            return [];
        }
    }

    //Get absences statistics for a specific student
    public function getStudentStatistics($studentIdentifier, $filters = [])
    {
        $query = "
            SELECT 
                u.identifier,
                CONCAT(u.first_name, ' ', u.last_name) as student_name,
                COUNT(DISTINCT a.id) as total_absences,
                COUNT(DISTINCT CASE WHEN a.justified = true THEN a.id END) as justified_absences,
                COUNT(DISTINCT CASE WHEN a.justified = false THEN a.id END) as unjustified_absences,
                COUNT(DISTINCT CASE WHEN cs.course_type = 'CM' THEN a.id END) as cm_absences,
                COUNT(DISTINCT CASE WHEN cs.course_type = 'TD' THEN a.id END) as td_absences,
                COUNT(DISTINCT CASE WHEN cs.course_type = 'TP' THEN a.id END) as tp_absences
            FROM absences a
            JOIN users u ON a.student_identifier = u.identifier
            JOIN course_slots cs ON a.course_slot_id = cs.id
            WHERE u.identifier = :student_identifier
        ";

        $params = [':student_identifier' => $studentIdentifier];
        $query .= $this->buildFilterConditions($filters, $params, false);
        $query .= " GROUP BY u.identifier, u.first_name, u.last_name";

        try {
            return $this->db->selectOne($query, $params);
        } catch (Exception $e) {
            error_log("Error fetching student statistics: " . $e->getMessage());
            return null;
        }
    }

    //Get student absences by resource
    public function getStudentAbsencesByResource($studentIdentifier, $filters = [])
    {
        $query = "
            SELECT 
                COALESCE(r.label, 'Non spécifié') as resource_label,
                COUNT(DISTINCT a.id) as total_absences
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            LEFT JOIN resources r ON cs.resource_id = r.id
            WHERE a.student_identifier = :student_identifier
        ";

        $params = [':student_identifier' => $studentIdentifier];
        $query .= $this->buildFilterConditions($filters, $params, false);
        $query .= " GROUP BY r.label ORDER BY total_absences DESC";

        try {
            return $this->db->select($query, $params);
        } catch (Exception $e) {
            error_log("Error fetching student absences by resource: " . $e->getMessage());
            return [];
        }
    }

    //Get student absences over time
    public function getStudentAbsencesTrends($studentIdentifier, $filters = [])
    {
        $query = "
            SELECT 
                TO_CHAR(cs.course_date, 'YYYY-MM') as month,
                TO_CHAR(cs.course_date, 'Month YYYY') as month_label,
                COUNT(DISTINCT a.id) as total_absences
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            WHERE a.student_identifier = :student_identifier
        ";

        $params = [':student_identifier' => $studentIdentifier];
        $query .= $this->buildFilterConditions($filters, $params, false);
        $query .= " GROUP BY TO_CHAR(cs.course_date, 'YYYY-MM'), TO_CHAR(cs.course_date, 'Month YYYY')
                    ORDER BY TO_CHAR(cs.course_date, 'YYYY-MM')";

        try {
            return $this->db->select($query, $params);
        } catch (Exception $e) {
            error_log("Error fetching student trends: " . $e->getMessage());
            return [];
        }
    }

    //Get all groups for filtering
    public function getAllGroups()
    {
        $query = "SELECT id, label FROM groups ORDER BY label";
        try {
            return $this->db->select($query);
        } catch (Exception $e) {
            error_log("Error fetching groups: " . $e->getMessage());
            return [];
        }
    }

    //Get all resources for filtering
    public function getAllResources()
    {
        $query = "SELECT id, label FROM resources ORDER BY label";
        try {
            return $this->db->select($query);
        } catch (Exception $e) {
            error_log("Error fetching resources: " . $e->getMessage());
            return [];
        }
    }

    //Get top students with most absences
    public function getTopAbsentStudents($limit = 10, $filters = [])
    {
        $query = "
            SELECT 
                u.identifier,
                CONCAT(u.first_name, ' ', u.last_name) as student_name,
                COUNT(DISTINCT a.id) as total_absences,
                COUNT(DISTINCT CASE WHEN a.justified = false THEN a.id END) as unjustified_absences
            FROM absences a
            JOIN users u ON a.student_identifier = u.identifier
            JOIN course_slots cs ON a.course_slot_id = cs.id
            WHERE 1=1
        ";

        $params = [];
        $query .= $this->buildFilterConditions($filters, $params);
        $query .= " GROUP BY u.identifier, u.first_name, u.last_name
                    ORDER BY total_absences DESC
                    LIMIT :limit";

        $params[':limit'] = $limit;

        try {
            $stmt = $this->db->getConnection()->prepare($query);
            foreach ($params as $key => $value) {
                if ($key === ':limit') {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $value);
                }
            }
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error fetching top absent students: " . $e->getMessage());
            return [];
        }
    }

    //Get general statistics summary
    public function getGeneralStatistics($filters = [])
    {
        $query = "
            SELECT 
                COUNT(DISTINCT a.id) as total_absences,
                COUNT(DISTINCT a.student_identifier) as total_students,
                COUNT(DISTINCT CASE WHEN a.justified = true THEN a.id END) as justified_absences,
                COUNT(DISTINCT CASE WHEN a.justified = false THEN a.id END) as unjustified_absences,
                COUNT(DISTINCT CASE WHEN cs.is_evaluation = true THEN a.id END) as evaluation_absences,
                ROUND(AVG(absences_per_student.count), 2) as avg_absences_per_student
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            CROSS JOIN (
                SELECT COUNT(*) as count
                FROM absences a2
                JOIN course_slots cs2 ON a2.course_slot_id = cs2.id
                WHERE 1=1
        ";

        $params = [];
        $subqueryConditions = $this->buildFilterConditions($filters, $params);
        $query .= $subqueryConditions . " GROUP BY a2.student_identifier
            ) as absences_per_student
            WHERE 1=1";

        $params2 = [];
        $query .= $this->buildFilterConditions($filters, $params2);

        // Merge params
        $params = array_merge($params, $params2);

        try {
            return $this->db->selectOne($query, $params);
        } catch (Exception $e) {
            error_log("Error fetching general statistics: " . $e->getMessage());
            return null;
        }
    }

    //Build filter conditions for queries
    private function buildFilterConditions($filters, &$params, $includeAnd = true)
    {
        $conditions = [];

        if (!empty($filters['start_date'])) {
            $conditions[] = "cs.course_date >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $conditions[] = "cs.course_date <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }

        if (!empty($filters['group_id'])) {
            $conditions[] = "cs.group_id = :group_id";
            $params[':group_id'] = $filters['group_id'];
        }

        if (!empty($filters['resource_id'])) {
            $conditions[] = "cs.resource_id = :resource_id";
            $params[':resource_id'] = $filters['resource_id'];
        }

        if (!empty($filters['course_type'])) {
            $conditions[] = "cs.course_type = :course_type";
            $params[':course_type'] = $filters['course_type'];
        }

        if (!empty($filters['semester'])) {
            if ($filters['semester'] === 'S1') {
                $conditions[] = "(EXTRACT(MONTH FROM cs.course_date) BETWEEN 9 AND 12 OR EXTRACT(MONTH FROM cs.course_date) BETWEEN 1 AND 2)";
            } elseif ($filters['semester'] === 'S2') {
                $conditions[] = "EXTRACT(MONTH FROM cs.course_date) BETWEEN 3 AND 6";
            }
        }

        if (!empty($filters['year'])) {
            $conditions[] = "EXTRACT(YEAR FROM cs.course_date) = :year";
            $params[':year'] = $filters['year'];
        }

        if (isset($filters['justified'])) {
            $conditions[] = "a.justified = :justified";
            $params[':justified'] = $filters['justified'];
        }

        if (empty($conditions)) {
            return '';
        }

        return ($includeAnd ? ' AND ' : ' ') . implode(' AND ', $conditions);
    }

    //Get available years for filtering
    public function getAvailableYears()
    {
        $query = "
            SELECT DISTINCT EXTRACT(YEAR FROM cs.course_date) as year
            FROM course_slots cs
            JOIN absences a ON a.course_slot_id = cs.id
            ORDER BY year DESC
        ";

        try {
            return $this->db->select($query);
        } catch (Exception $e) {
            error_log("Error fetching available years: " . $e->getMessage());
            return [];
        }
    }

    //Get justification rate by resource/subject
    public function getJustificationRateByResource($filters = [])
    {
        $query = "
            SELECT 
                COALESCE(r.label, 'Non spécifié') as resource_label,
                COUNT(DISTINCT a.id) as total_absences,
                COUNT(DISTINCT CASE WHEN a.justified = true THEN a.id END) as justified_absences,
                ROUND(
                    CASE 
                        WHEN COUNT(DISTINCT a.id) > 0 
                        THEN (COUNT(DISTINCT CASE WHEN a.justified = true THEN a.id END)::numeric / COUNT(DISTINCT a.id)::numeric) * 100
                        ELSE 0 
                    END, 1
                ) as justification_rate
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            LEFT JOIN resources r ON cs.resource_id = r.id
            WHERE 1=1
        ";

        $params = [];
        $query .= $this->buildFilterConditions($filters, $params);
        $query .= " GROUP BY r.label HAVING COUNT(DISTINCT a.id) >= 3 ORDER BY justification_rate ASC LIMIT 10";

        try {
            return $this->db->select($query, $params);
        } catch (Exception $e) {
            error_log("Error fetching justification rate by resource: " . $e->getMessage());
            return [];
        }
    }
}
