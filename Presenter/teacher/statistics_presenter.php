<?php

declare(strict_types=1);

// Teacher statistics presenter

require_once __DIR__ . '/../../Model/StatisticsModel.php';
require_once __DIR__ . '/../../Model/format_ressource.php';

// Get filter parameters
$studentFilter = $_GET['student'] ?? '';
$semesterFilter = $_GET['semester'] ?? '';
$courseTypeFilter = $_GET['type'] ?? '';
$resourceFilter = $_GET['resource'] ?? '';

$statisticsModel = new StatisticsModel();

$filters = [];
if ($studentFilter !== '') {
    $filters['student_name'] = $studentFilter;
}
if ($courseTypeFilter !== '') {
    $filters['course_type'] = $courseTypeFilter;
}
if ($resourceFilter !== '') {
    $filters['resource_id'] = $resourceFilter;
}

// Function to get student statistics (for detail view)
function getStudentStatistics(int $studentId): ?array
{
    global $statisticsModel;

    try {
        $data = $statisticsModel->getStudentDetailedStatistics($studentId);
        if (empty($data) || empty($data['info'])) {
            return null;
        }

        $info = $data['info'];
        $total = (int) ($info['total_absences'] ?? 0);
        $justified = (int) ($info['justified_absences'] ?? 0);
        $unjustified = (int) ($info['unjustified_absences'] ?? 0);
        $rate = $total > 0 ? round(($justified / $total) * 100) : 0;

        $courseTypes = [];
        foreach (($data['course_types'] ?? []) as $row) {
            if (!empty($row['course_type'])) {
                $courseTypes[$row['course_type']] = (int) ($row['total_absences'] ?? 0);
            }
        }

        $subjects = [];
        foreach (($data['subjects'] ?? []) as $row) {
            if (!empty($row['resource_label'])) {
                $subjects[$row['resource_label']] = (int) ($row['total_absences'] ?? 0);
            }
        }

        return [
            'name' => ($info['first_name'] ?? '') . ' ' . ($info['last_name'] ?? ''),
            'student_number' => $info['identifier'] ?? 'N/A',
            'total' => (int) $total,
            'justified' => (int) $justified,
            'unjustified' => (int) $unjustified,
            'rate' => $rate,
            'courseTypes' => $courseTypes,
            'subjects' => $subjects
        ];
    } catch (Exception $e) {
        error_log("getStudentStatistics error: " . $e->getMessage());
        return null;
    }
}

// Initialize default values
$stats = [
    'total_absences' => 0,
    'total_students' => 0,
    'justified' => 0,
    'unjustified' => 0,
    'average' => 0
];
$courseTypeStats = [];
$subjectStats = [];
$semesterStats = [];
$monthlyStats = [];
$subjectTrends = [];
$topStudents = [];
$resources = [];
$semesters = [];

try {
    $resourcesRows = $statisticsModel->getAllResources();
    foreach ($resourcesRows as $row) {
        $resources[] = [
            'id' => $row['id'],
            'code' => $row['label'],
            'label' => $row['label']
        ];
    }

    $groupRows = $statisticsModel->getAllGroups();
    foreach ($groupRows as $row) {
        $semesters[] = ['code' => $row['label']];
    }

    $summary = $statisticsModel->getTeacherSummaryStats($filters);
    $totalAbsences = (int) ($summary['total_absences'] ?? 0);
    $totalStudents = (int) ($summary['total_students'] ?? 0);
    $justified = (int) ($summary['justified_absences'] ?? 0);
    $unjustified = (int) ($summary['unjustified_absences'] ?? 0);
    $evaluationAbsences = (int) ($summary['evaluation_absences'] ?? 0);
    $average = $totalStudents > 0 ? $totalAbsences / $totalStudents : 0;

    $stats = [
        'total_absences' => $totalAbsences,
        'total_students' => $totalStudents,
        'justified' => $justified,
        'unjustified' => $unjustified,
        'average' => $average,
        'evaluation_absences' => $evaluationAbsences
    ];

    foreach ($statisticsModel->getTeacherCourseTypeStats($filters) as $row) {
        if (!empty($row['course_type'])) {
            $courseTypeStats[$row['course_type']] = (int) ($row['total_absences'] ?? 0);
        }
    }

    foreach ($statisticsModel->getTeacherSubjectStats($filters) as $row) {
        if (!empty($row['resource_label'])) {
            $subjectStats[formatResourceLabel($row['resource_label'])] = (int) ($row['total_absences'] ?? 0);
        }
    }

    $evaluationSubjectStats = [];
    foreach ($statisticsModel->getTeacherEvaluationSubjectStats($filters) as $row) {
        if (!empty($row['resource_label'])) {
            $evaluationSubjectStats[formatResourceLabel($row['resource_label'])] = (int) ($row['total_absences'] ?? 0);
        }
    }

    $semesterStats = $statisticsModel->getTeacherSemesterGroupStats();

    $monthlyResults = $statisticsModel->getTeacherMonthlyStats($filters);

    $monthlyStats = [
        'labels' => [],
        'total' => [],
        'justified' => [],
        'unjustified' => []
    ];

    foreach ($monthlyResults as $row) {
        $total = (int) ($row['total_absences'] ?? 0);
        $justifiedCount = (int) ($row['justified_absences'] ?? 0);
        $monthlyStats['labels'][] = trim((string) ($row['month_label'] ?? $row['month'] ?? ''));
        $monthlyStats['total'][] = $total;
        $monthlyStats['justified'][] = $justifiedCount;
        $monthlyStats['unjustified'][] = $total - $justifiedCount;
    }

    $topStudents = $statisticsModel->getTeacherTopStudents($filters, 10);

    $trendsResults = $statisticsModel->getTeacherSubjectTrends($filters);

    // Process trends data
    $months = [];
    $subjectData = [];

    foreach ($trendsResults as $row) {
        if (!in_array($row['month'], $months)) {
            $months[] = $row['month'];
        }
        if (!empty($row['resource_label'])) {
            $formattedLabel = formatResourceLabel($row['resource_label']);
            if (!isset($subjectData[$formattedLabel])) {
                $subjectData[$formattedLabel] = [];
            }
            $subjectData[$formattedLabel][$row['month']] = (int) ($row['total_absences'] ?? 0);
        }
    }

    // Get top 5 subjects by total absences
    $subjectTotals = [];
    foreach ($subjectData as $subject => $data) {
        $subjectTotals[$subject] = array_sum($data);
    }
    arsort($subjectTotals);
    $top5Subjects = array_slice(array_keys($subjectTotals), 0, 5);

    // Build datasets for Chart.js
    $trendColors = [
        ['border' => '#5c6bc0', 'bg' => 'rgba(92, 107, 192, 0.15)'],
        ['border' => '#9575cd', 'bg' => 'rgba(149, 117, 205, 0.15)'],
        ['border' => '#f44336', 'bg' => 'rgba(244, 67, 54, 0.1)'],
        ['border' => '#4caf50', 'bg' => 'rgba(76, 175, 80, 0. 1)'],
        ['border' => '#ff9800', 'bg' => 'rgba(255, 152, 0, 0.1)']
    ];

    $subjectTrends = [
        'labels' => $months,
        'datasets' => []
    ];

    foreach ($top5Subjects as $index => $subject) {
        $data = [];
        foreach ($months as $month) {
            $data[] = $subjectData[$subject][$month] ?? 0;
        }
        $subjectTrends['datasets'][] = [
            'label' => $subject,
            'data' => $data,
            'borderColor' => $trendColors[$index]['border'],
            'backgroundColor' => $trendColors[$index]['bg'],
            'fill' => 'origin',
            'tension' => 0.4,
            'pointRadius' => 4,
            'pointBackgroundColor' => $trendColors[$index]['border']
        ];
    }
} catch (Exception $e) {
    // Log error for debugging
    error_log("Statistics error: " . $e->getMessage());
}
