<?php

declare(strict_types=1);

/**
 * File: statistics_presenter.php
 *
 * Student statistics presenter – Handles display of absence statistics.
 * Provides methods to:
 * - Retrieve global statistics (total absences, hours, half-days, evaluations)
 * - Retrieve absences by course type (for pie chart)
 * - Retrieve absences by subject/resource (for bar chart)
 * - Retrieve monthly absence trends (for line chart)
 * - Manage filters (dates, course type)
 * - Retrieve recent absence list with details
 * Used by the student "My statistics" page with Chart.js.
 */

require_once __DIR__ . '/../../Model/StatisticsModel.php';
require_once __DIR__ . '/../../Model/UserModel.php';
require_once __DIR__ . '/../shared/auth_guard.php';
require_once __DIR__ . '/../../Model/format_ressource.php';

class StudentStatisticsPresenter
{
    private StatisticsModel $statisticsModel;
    private string $studentIdentifier;

    public function __construct(string $studentIdentifier)
    {
        $this->statisticsModel = new StatisticsModel();
        $this->studentIdentifier = $studentIdentifier;
    }

    /**
     * Format resource label to show "CODE : LABEL" format
     * Example: "INFFIS2-DEVELOPPEMENT ORIENTE OBJETS (T3BUTINFFI-R2.01)" => "R2.01 : DEVELOPPEMENT ORIENTE OBJETS"
     */
    private function formatResourceLabel(string $fullLabel): string
    {
        return \formatResourceLabel($fullLabel);
    }

    /**
     * Get the student's identifier from their user ID
     */
    public static function getStudentIdentifierFromUserId(int $userId): ?string
    {
        $result = (new UserModel())->getUserById($userId);
        if (!$result || ($result['role'] ?? '') !== 'student') {
            return null;
        }
        return $result ? $result['identifier'] : null;
    }

    /**
     * Get general statistics for the student
     */
    public function getGeneralStats(array $filters = []): array
    {
        return $this->statisticsModel->getStudentStatistics($this->studentIdentifier, $filters);
    }

    /**
     * Get absences by course type for pie chart
     */
    public function getCourseTypeData(array $filters = []): array
    {
        try {
            $data = $this->statisticsModel->getStudentAbsencesByCourseType($this->studentIdentifier, $filters);

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
            error_log('Error fetching course type data: ' . $e->getMessage());
            return ['labels' => [], 'values' => [], 'colors' => []];
        }
    }

    /**
     * Get absences by resource for bar chart
     */
    public function getResourceData(array $filters = []): array
    {
        $data = $this->statisticsModel->getStudentAbsencesByResource($this->studentIdentifier, $filters);

        // Limit to top 10 resources
        $data = array_slice($data, 0, 10);

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

    /**
     * Get monthly trends for line chart
     */
    public function getMonthlyTrends(array $filters = []): array
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
    public function getDetailedMonthlyTrends(array $filters = []): array
    {
        try {
            $data = $this->statisticsModel->getStudentDetailedMonthlyTrends($this->studentIdentifier, $filters);

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
            error_log('Error fetching detailed monthly trends: ' . $e->getMessage());
            return ['months' => [], 'total' => [], 'justified' => [], 'unjustified' => []];
        }
    }

    /**
     * Get filters from request
     */
    public function getFilters(): array
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
    public function getRecentAbsences(int $limit = 10): array
    {
        try {
            $absences = $this->statisticsModel->getStudentRecentAbsencesList($this->studentIdentifier, $limit);

            // Format resource labels
            foreach ($absences as &$absence) {
                if (isset($absence['resource_name'])) {
                    $absence['resource_name'] = $this->formatResourceLabel($absence['resource_name']);
                }
            }

            return $absences;
        } catch (Exception $e) {
            error_log('Error fetching recent absences: ' . $e->getMessage());
            return [];
        }
    }
}
