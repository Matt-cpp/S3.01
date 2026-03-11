<?php

declare(strict_types=1);

/**
 * Academic manager statistics presenter - Manages advanced statistics and chart data.
 * Provides methods for:
 * - Managing multi-criteria filters (dates, group, resource, course type, semester, year, student)
 * - Retrieving general statistics (total, hours, half-days, evaluations, justification rate)
 * - Generating Chart.js data:
 *   - Distribution by course type (pie chart)
 *   - Distribution by resource/subject (bar chart)
 *   - Monthly evolution (line chart)
 *   - Distribution by semester
 *   - Top absent students
 * - Calculating justification rate by period
 * - Providing individual student statistics
 * - Providing filter options (groups, resources, years)
 * Uses StatisticsModel and UserModel for queries.
 */

require_once __DIR__ . '/../../Model/StatisticsModel.php';
require_once __DIR__ . '/../../Model/UserModel.php';

class AcademicManagerStatisticsPresenter
{
    private StatisticsModel $statisticsModel;
    private UserModel $userModel;

    public function __construct()
    {
        $this->statisticsModel = new StatisticsModel();
        $this->userModel = new UserModel();
    }

    /**
     * Format resource label to show "CODE : LABEL" format
     * Example: "INFFIS2-DEVELOPPEMENT ORIENTE OBJETS (T3BUTINFFI-R2.01)" => "R2.01 : DEVELOPPEMENT ORIENTE OBJETS"
     */
    private function formatResourceLabel(string $fullLabel): string
    {
        if (empty($fullLabel) || $fullLabel === 'N/A') {
            return 'N/A';
        }

        if (preg_match('/\(([^)]+)\)/', $fullLabel, $matches)) {
            $fullCode = $matches[1];
            $codeParts = explode('-', $fullCode);
            $code = end($codeParts);

            if (preg_match('/^[^-]+-(.+?)\s*\(/', $fullLabel, $labelMatches)) {
                $label = trim($labelMatches[1]);
                return $code . ' : ' . $label;
            }
        }

        return $fullLabel;
    }

    // Retrieve and validate filters from GET URL parameters
    // @return array Associative array of active filters
    public function getFilters(): array
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

    // Get general statistics
    public function getGeneralStats(array $filters = []): array
    {
        return $this->statisticsModel->getGeneralStatistics($filters);
    }

    // Get absences by course type for pie chart
    public function getCourseTypeData(array $filters = []): array
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

    // Get absences by resource for pie chart
    public function getResourceData(array $filters = []): array
    {
        $data = $this->statisticsModel->getAbsencesByResource($filters);

        // Limit to top 10 resources
        $data = array_slice($data, 0, 10);

        $labels = [];
        $values = [];

        foreach ($data as $row) {
            $labels[] = $this->formatResourceLabel($row['resource_label']) ?? 'N/A';
            $values[] = intval($row['total_absences']);
        }

        return [
            'labels' => $labels,
            'values' => $values
        ];
    }

    // Get evaluation absences by resource for chart
    public function getEvaluationResourceData(array $filters = []): array
    {
        $data = $this->statisticsModel->getEvaluationAbsencesByResource($filters);

        $labels = [];
        $values = [];

        foreach ($data as $row) {
            $labels[] = $this->formatResourceLabel($row['resource_label'] ?? 'N/A');
            $values[] = intval($row['total_absences']);
        }

        return [
            'labels' => $labels,
            'values' => $values
        ];
    }

    // Get justification rate by resource for chart
    public function getJustificationRateData(array $filters = []): array
    {
        $data = $this->statisticsModel->getJustificationRateByResource($filters);

        $labels = [];
        $values = [];

        foreach ($data as $row) {
            $labels[] = $this->formatResourceLabel($row['resource_label'] ?? 'N/A');
            $values[] = floatval($row['justification_rate']);
        }

        return [
            'labels' => $labels,
            'values' => $values
        ];
    }

    // Get monthly trends for line chart
    public function getMonthlyTrends(array $filters = []): array
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

    // Get resource trends over time
    public function getResourceTrends(array $filters = []): array
    {
        $rawData = $this->statisticsModel->getResourceTrendsOverTime($filters);

        // Organize data by resource
        $resources = [];
        $months = [];

        foreach ($rawData as $row) {
            $resource = $this->formatResourceLabel($row['resource_label']);
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

    // Get semester data
    public function getSemesterData(array $filters = []): array
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

    // Get top absent students
    public function getTopAbsentStudents(int $limit = 10, array $filters = []): array
    {
        return $this->statisticsModel->getTopAbsentStudents($limit, $filters);
    }

    // Get student statistics
    public function getStudentStatistics(string $studentIdentifier, array $filters = []): array
    {
        return $this->statisticsModel->getStudentStatistics($studentIdentifier, $filters);
    }

    // Get student absences by resource
    public function getStudentResourceData(string $studentIdentifier, array $filters = []): array
    {
        $data = $this->statisticsModel->getStudentAbsencesByResource($studentIdentifier, $filters);

        $labels = [];
        $values = [];

        foreach ($data as $row) {
            $labels[] = $this->formatResourceLabel($row['resource_label']) ?? 'N/A';
            $values[] = intval($row['total_absences']);
        }

        return [
            'labels' => $labels,
            'values' => $values
        ];
    }

    // Get student trends
    public function getStudentTrends(string $studentIdentifier, array $filters = []): array
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

    // Get all groups for filter dropdown
    public function getAllGroups(): array
    {
        return $this->statisticsModel->getAllGroups();
    }

    // Get all resources for filter dropdown
    public function getAllResources(): array
    {
        return $this->statisticsModel->getAllResources();
    }

    // Get available years
    public function getAvailableYears(): array
    {
        return $this->statisticsModel->getAvailableYears();
    }

    // Search students by name or identifier
    public function searchStudents(string $query): array
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

    // Export statistics to JSON for API use
    public function exportToJson(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
}
