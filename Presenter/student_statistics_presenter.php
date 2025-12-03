<?php
/**
 * Fichier: student_statistics_presenter.php
 * 
 * Présentateur des statistiques étudiant - Gère l'affichage des statistiques d'absences pour l'étudiant connecté.
 * Fournit des méthodes pour:
 * - Récupérer les statistiques globales de l'étudiant
 * - Récupérer les absences par type de cours
 * - Récupérer les absences par matière
 * - Récupérer l'évolution des absences dans le temps
 * Utilisé par la page "Mes statistiques" de l'étudiant.
 */

require_once __DIR__ . '/../Model/StatisticsModel.php';
require_once __DIR__ . '/../Model/database.php';
require_once __DIR__ . '/../controllers/auth_guard.php';

class StudentStatisticsPresenter
{
    private $statisticsModel;
    private $db;
    private $studentIdentifier;

    public function __construct($studentIdentifier)
    {
        $this->statisticsModel = new StatisticsModel();
        $this->db = getDatabase();
        $this->studentIdentifier = $studentIdentifier;
    }

    /**
     * Get the student's identifier from their user ID
     */
    public static function getStudentIdentifierFromUserId($userId)
    {
        $db = getDatabase();
        $result = $db->selectOne(
            "SELECT identifier FROM users WHERE id = :id AND role = 'student'",
            [':id' => $userId]
        );
        return $result ? $result['identifier'] : null;
    }

    /**
     * Get general statistics for the student
     */
    public function getGeneralStats($filters = [])
    {
        return $this->statisticsModel->getStudentStatistics($this->studentIdentifier, $filters);
    }

    /**
     * Get absences by course type for pie chart
     */
    public function getCourseTypeData($filters = [])
    {
        $query = "
            SELECT 
                cs.course_type,
                COUNT(DISTINCT a.id) as total_absences
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            WHERE a.student_identifier = :student_identifier
        ";

        $params = [':student_identifier' => $this->studentIdentifier];

        if (!empty($filters['start_date'])) {
            $query .= " AND cs.course_date >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $query .= " AND cs.course_date <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }

        $query .= " GROUP BY cs.course_type ORDER BY cs.course_type";

        try {
            $data = $this->db->select($query, $params);

            $labels = [];
            $values = [];
            $colors = [
                'CM' => '#4338ca',
                'TD' => '#7c3aed',
                'TP' => '#db2777',
                'BEN' => '#059669',
                'TPC' => '#dc2626',
                'DS' => '#ea580c',
                'TDC' => '#0891b2'
            ];

            foreach ($data as $row) {
                $labels[] = $row['course_type'] ?? 'N/A';
                $values[] = intval($row['total_absences']);
            }

            return [
                'labels' => $labels,
                'values' => $values,
                'colors' => array_map(function ($label) use ($colors) {
                    return $colors[$label] ?? '#6b7280';
                }, $labels)
            ];
        } catch (Exception $e) {
            error_log("Error fetching course type data: " . $e->getMessage());
            return ['labels' => [], 'values' => [], 'colors' => []];
        }
    }

    /**
     * Get absences by resource for bar chart
     */
    public function getResourceData($filters = [])
    {
        $data = $this->statisticsModel->getStudentAbsencesByResource($this->studentIdentifier, $filters);

        // Limit to top 10 resources
        $data = array_slice($data, 0, 10);

        $labels = [];
        $values = [];

        foreach ($data as $row) {
            $labels[] = $row['resource_label'] ?? 'N/A';
            $values[] = intval($row['total_absences']);
        }

        return [
            'labels' => $labels,
            'values' => $values
        ];
    }

    /**
     * Get monthly trends for line chart
     */
    public function getMonthlyTrends($filters = [])
    {
        $data = $this->statisticsModel->getStudentAbsencesTrends($this->studentIdentifier, $filters);

        $months = [];
        $values = [];

        foreach ($data as $row) {
            $months[] = $row['month_label'] ?? $row['month'];
            $values[] = intval($row['total_absences']);
        }

        return [
            'months' => $months,
            'values' => $values
        ];
    }

    /**
     * Get absences with justified/unjustified breakdown by month
     */
    public function getDetailedMonthlyTrends($filters = [])
    {
        $query = "
            SELECT 
                TO_CHAR(cs.course_date, 'YYYY-MM') as month,
                TO_CHAR(cs.course_date, 'Mon YYYY') as month_label,
                COUNT(DISTINCT a.id) as total_absences,
                COUNT(DISTINCT CASE WHEN a.justified = true THEN a.id END) as justified,
                COUNT(DISTINCT CASE WHEN a.justified = false THEN a.id END) as unjustified
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            WHERE a.student_identifier = :student_identifier
        ";

        $params = [':student_identifier' => $this->studentIdentifier];

        if (!empty($filters['start_date'])) {
            $query .= " AND cs.course_date >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $query .= " AND cs.course_date <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }

        $query .= " GROUP BY TO_CHAR(cs.course_date, 'YYYY-MM'), TO_CHAR(cs.course_date, 'Mon YYYY')
                    ORDER BY TO_CHAR(cs.course_date, 'YYYY-MM')";

        try {
            $data = $this->db->select($query, $params);

            $months = [];
            $total = [];
            $justified = [];
            $unjustified = [];

            foreach ($data as $row) {
                $months[] = trim($row['month_label']);
                $total[] = intval($row['total_absences']);
                $justified[] = intval($row['justified']);
                $unjustified[] = intval($row['unjustified']);
            }

            return [
                'months' => $months,
                'total' => $total,
                'justified' => $justified,
                'unjustified' => $unjustified
            ];
        } catch (Exception $e) {
            error_log("Error fetching detailed monthly trends: " . $e->getMessage());
            return ['months' => [], 'total' => [], 'justified' => [], 'unjustified' => []];
        }
    }

    /**
     * Get filters from request
     */
    public function getFilters()
    {
        $filters = [];

        if (!empty($_GET['start_date'])) {
            $filters['start_date'] = $_GET['start_date'];
        }

        if (!empty($_GET['end_date'])) {
            $filters['end_date'] = $_GET['end_date'];
        }

        if (!empty($_GET['course_type'])) {
            $filters['course_type'] = $_GET['course_type'];
        }

        return $filters;
    }

    /**
     * Get recent absences for the student (last 10)
     */
    public function getRecentAbsences($limit = 10)
    {
        $query = "
            SELECT 
                a.id,
                cs.course_date,
                cs.start_time,
                cs.end_time,
                cs.course_type,
                cs.is_evaluation,
                a.justified,
                COALESCE(r.label, r.code) as resource_name,
                CONCAT(t.first_name, ' ', t.last_name) as teacher_name
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            LEFT JOIN resources r ON cs.resource_id = r.id
            LEFT JOIN teachers t ON cs.teacher_id = t.id
            WHERE a.student_identifier = :student_identifier
            ORDER BY cs.course_date DESC, cs.start_time DESC
            LIMIT :limit
        ";

        try {
            $stmt = $this->db->getConnection()->prepare($query);
            $stmt->bindValue(':student_identifier', $this->studentIdentifier);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error fetching recent absences: " . $e->getMessage());
            return [];
        }
    }
}
