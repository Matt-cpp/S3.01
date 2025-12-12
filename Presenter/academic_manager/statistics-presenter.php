<?php
/**
 * Fichier: statistics-presenter.php
 * 
 * Présentateur des statistiques responsable pédagogique - Gère les statistiques avancées et graphiques.
 * Fournit des méthodes pour:
 * - Gérer les filtres multi-critères (dates, groupe, ressource, type de cours, semestre, année, étudiant)
 * - Récupérer les statistiques générales (total, heures, demi-journées, évaluations, taux justification)
 * - Générer des données pour graphiques Chart.js :
 *   - Répartition par type de cours (camembert)
 *   - Répartition par ressource/matière (barres)
 *   - Évolution mensuelle (ligne)
 *   - Répartition par semestre
 *   - Top étudiants les plus absents
 * - Calculer le taux de justification par période
 * - Fournir des statistiques individuelles par étudiant
 * - Fournir les options de filtres (groupes, ressources, années)
 * Utilise StatisticsModel et UserModel pour les requêtes.
 */

require_once __DIR__ . '/../../Model/StatisticsModel.php';
require_once __DIR__ . '/../../Model/UserModel.php';

class AcademicManagerStatisticsPresenter
{
    private $statisticsModel;
    private $userModel;

    public function __construct()
    {
        $this->statisticsModel = new StatisticsModel();
        $this->userModel = new UserModel();
    }

    // Récupère et valide les filtres depuis les paramètres GET de l'URL
    // @return array - Tableau associatif des filtres actifs
    public function getFilters()
    {
        $filters = [];

        if (!empty($_GET['start_date'])) {
            $filters['start_date'] = $_GET['start_date'];
        }

        if (!empty($_GET['end_date'])) {
            $filters['end_date'] = $_GET['end_date'];
        }

        if (!empty($_GET['group_id'])) {
            $filters['group_id'] = intval($_GET['group_id']);
        }

        if (!empty($_GET['resource_id'])) {
            $filters['resource_id'] = intval($_GET['resource_id']);
        }

        if (!empty($_GET['course_type'])) {
            $filters['course_type'] = $_GET['course_type'];
        }

        if (!empty($_GET['semester'])) {
            $filters['semester'] = $_GET['semester'];
        }

        if (!empty($_GET['year'])) {
            $filters['year'] = intval($_GET['year']);
        }

        if (isset($_GET['justified']) && $_GET['justified'] !== '') {
            $filters['justified'] = $_GET['justified'] === '1' || $_GET['justified'] === 'true';
        }

        return $filters;
    }

    //Get general statistics
    public function getGeneralStats($filters = [])
    {
        return $this->statisticsModel->getGeneralStatistics($filters);
    }

    //Get absences by course type for pie chart
    public function getCourseTypeData($filters = [])
    {
        $data = $this->statisticsModel->getAbsencesByCourseType($filters);

        // Format for Chart.js
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
    }

    //Get absences by resource for pie chart
    public function getResourceData($filters = [])
    {
        $data = $this->statisticsModel->getAbsencesByResource($filters);

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

    //Get evaluation absences by resource for chart
    public function getEvaluationResourceData($filters = [])
    {
        $data = $this->statisticsModel->getEvaluationAbsencesByResource($filters);

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

    //Get justification rate by resource for chart
    public function getJustificationRateData($filters = [])
    {
        $data = $this->statisticsModel->getJustificationRateByResource($filters);

        $labels = [];
        $values = [];

        foreach ($data as $row) {
            $labels[] = $row['resource_label'] ?? 'N/A';
            $values[] = floatval($row['justification_rate']);
        }

        return [
            'labels' => $labels,
            'values' => $values
        ];
    }

    //Get monthly trends for line chart
    public function getMonthlyTrends($filters = [])
    {
        $data = $this->statisticsModel->getAbsencesTrendsByMonth($filters);

        $months = [];
        $total = [];
        $justified = [];
        $unjustified = [];

        foreach ($data as $row) {
            $months[] = $row['month_label'] ?? $row['month'];
            $total[] = intval($row['total_absences']);
            $justified[] = intval($row['justified_absences']);
            $unjustified[] = intval($row['unjustified_absences']);
        }

        return [
            'months' => $months,
            'total' => $total,
            'justified' => $justified,
            'unjustified' => $unjustified
        ];
    }

    //Get resource trends over time
    public function getResourceTrends($filters = [])
    {
        $rawData = $this->statisticsModel->getResourceTrendsOverTime($filters);

        // Organize data by resource
        $resources = [];
        $months = [];

        foreach ($rawData as $row) {
            $resource = $row['resource_label'];
            $month = $row['month'];
            $count = intval($row['total_absences']);

            if (!isset($resources[$resource])) {
                $resources[$resource] = [];
            }

            $resources[$resource][$month] = $count;

            if (!in_array($month, $months)) {
                $months[] = $month;
            }
        }

        sort($months);

        // Limit to top 5 resources by total absences
        $resourceTotals = [];
        foreach ($resources as $resource => $data) {
            $resourceTotals[$resource] = array_sum($data);
        }
        arsort($resourceTotals);
        $topResources = array_slice(array_keys($resourceTotals), 0, 5, true);

        // Build datasets
        $datasets = [];
        $colors = ['#4338ca', '#7c3aed', '#db2777', '#059669', '#ea580c'];
        $colorIndex = 0;

        foreach ($topResources as $resource) {
            $values = [];
            foreach ($months as $month) {
                $values[] = $resources[$resource][$month] ?? 0;
            }

            $datasets[] = [
                'label' => $resource,
                'data' => $values,
                'color' => $colors[$colorIndex % count($colors)]
            ];
            $colorIndex++;
        }

        return [
            'months' => $months,
            'datasets' => $datasets
        ];
    }

    //Get semester data
    public function getSemesterData($filters = [])
    {
        $data = $this->statisticsModel->getAbsencesBySemester($filters);

        // Organize by semester
        $semesters = [];

        foreach ($data as $row) {
            $semester = $row['semester'];
            $year = $row['year'];
            $key = $semester . ' ' . $year;

            if (!isset($semesters[$key])) {
                $semesters[$key] = [
                    'CM' => 0,
                    'TD' => 0,
                    'TP' => 0
                ];
            }

            $semesters[$key]['CM'] += intval($row['cm_count'] ?? 0);
            $semesters[$key]['TD'] += intval($row['td_count'] ?? 0);
            $semesters[$key]['TP'] += intval($row['tp_count'] ?? 0);
        }

        return $semesters;
    }

    //Get top absent students
    public function getTopAbsentStudents($limit = 10, $filters = [])
    {
        return $this->statisticsModel->getTopAbsentStudents($limit, $filters);
    }

    //Get student statistics
    public function getStudentStatistics($studentIdentifier, $filters = [])
    {
        return $this->statisticsModel->getStudentStatistics($studentIdentifier, $filters);
    }

    //Get student absences by resource
    public function getStudentResourceData($studentIdentifier, $filters = [])
    {
        $data = $this->statisticsModel->getStudentAbsencesByResource($studentIdentifier, $filters);

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

    //Get student trends
    public function getStudentTrends($studentIdentifier, $filters = [])
    {
        $data = $this->statisticsModel->getStudentAbsencesTrends($studentIdentifier, $filters);

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

    //Get all groups for filter dropdown
    public function getAllGroups()
    {
        return $this->statisticsModel->getAllGroups();
    }

    //Get all resources for filter dropdown
    public function getAllResources()
    {
        return $this->statisticsModel->getAllResources();
    }

    //Get available years
    public function getAvailableYears()
    {
        return $this->statisticsModel->getAvailableYears();
    }

    //Search students by name or identifier
    public function searchStudents($query)
    {
        // Simple implementation without using UserModel method
        $db = getDatabase();
        $sql = "
            SELECT identifier, first_name, last_name, email
            FROM users
            WHERE role = 'student'
            AND (
                LOWER(first_name) LIKE LOWER(:query)
                OR LOWER(last_name) LIKE LOWER(:query)
                OR LOWER(identifier) LIKE LOWER(:query)
            )
            LIMIT 10
        ";

        try {
            return $db->select($sql, [':query' => '%' . $query . '%']);
        } catch (Exception $e) {
            error_log("Error searching students: " . $e->getMessage());
            return [];
        }
    }

    //Export statistics to JSON for API use
    public function exportToJson($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
}
