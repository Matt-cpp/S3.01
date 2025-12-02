<?php
// teacher_statistics_presenter.php

require_once __DIR__ . '/../Model/Database.php';
require_once __DIR__ . '/../controllers/auth_guard.php';

// Require teacher role authentication
$user = requireRole('teacher');

// Get the teacher_id linked to this user
$db = Database::getInstance()->getConnection();
$teacherQuery = "SELECT teachers.id as teacher_id
                 FROM users 
                 LEFT JOIN teachers ON teachers.email = users.email
                 WHERE users.id = :user_id";
$stmt = $db->prepare($teacherQuery);
$stmt->execute([':user_id' => $user['id']]);
$teacherResult = $stmt->fetch(PDO::FETCH_ASSOC);
$teacherId = $teacherResult['teacher_id'] ?? null;

// Get filter parameters
$studentFilter = $_GET['student'] ?? '';
$semesterFilter = $_GET['semester'] ?? '';
$courseTypeFilter = $_GET['type'] ?? '';
$resourceFilter = $_GET['resource'] ?? '';

// Build base query conditions for absences - ALWAYS filter by teacher
$conditions = [];
$params = [];

// Always filter by teacher_id if available
if ($teacherId) {
    $conditions[] = "cs.teacher_id = :teacher_id";
    $params[':teacher_id'] = $teacherId;
}

if ($studentFilter) {
    $conditions[] = "(u.first_name ILIKE :student OR u.last_name ILIKE :student)";
    $params[':student'] = "%$studentFilter%";
}

if ($courseTypeFilter) {
    $conditions[] = "cs.course_type = :courseType";
    $params[':courseType'] = $courseTypeFilter;
}

if ($resourceFilter) {
    $conditions[] = "cs.resource_id = :resource";
    $params[':resource'] = $resourceFilter;
}

$whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Function to get student statistics (for detail view) - filtered by teacher
function getStudentStatistics($studentId, $teacherId)
{
    global $db;

    try {
        // Get student info from users table
        $studentQuery = "SELECT first_name, last_name, identifier as student_number 
                         FROM users 
                         WHERE id = :id AND role = 'student'";
        $stmt = $db->prepare($studentQuery);
        $stmt->execute([':id' => $studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            return null;
        }

        // Build teacher filter condition
        $teacherCondition = $teacherId ? " AND cs.teacher_id = :teacher_id" : "";
        $teacherParam = $teacherId ? [':teacher_id' => $teacherId] : [];

        // Get total absences (only for teacher's courses)
        $totalQuery = "SELECT COUNT(*) as total 
                       FROM absences a
                       INNER JOIN course_slots cs ON a.course_slot_id = cs.id
                       WHERE a.student_identifier = :identifier" . $teacherCondition;
        $stmt = $db->prepare($totalQuery);
        $stmt->execute(array_merge([':identifier' => $student['student_number']], $teacherParam));
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Get justified absences (where proof status is 'accepted')
        $justifiedQuery = "SELECT COUNT(DISTINCT a.id) as count 
                           FROM absences a 
                           INNER JOIN course_slots cs ON a.course_slot_id = cs.id
                           INNER JOIN proof_absences pa ON a.id = pa.absence_id
                           INNER JOIN proof p ON pa.proof_id = p.id
                           WHERE a.student_identifier = :identifier 
                           AND p.status = 'accepted'" . $teacherCondition;
        $stmt = $db->prepare($justifiedQuery);
        $stmt->execute(array_merge([':identifier' => $student['student_number']], $teacherParam));
        $justified = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // Get evaluation absences (DS type or is_evaluation = true)
        $evalQuery = "SELECT COUNT(*) as count 
                      FROM absences a
                      INNER JOIN course_slots cs ON a.course_slot_id = cs.id
                      WHERE a.student_identifier = :identifier 
                      AND (cs.course_type = 'DS' OR cs.is_evaluation = true)" . $teacherCondition;
        $stmt = $db->prepare($evalQuery);
        $stmt->execute(array_merge([':identifier' => $student['student_number']], $teacherParam));
        $evaluationAbsences = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        $unjustified = $total - $justified;
        $rate = $total > 0 ? round(($justified / $total) * 100) : 0;

        // Get absences by course type
        $courseTypeQuery = "SELECT cs.course_type, COUNT(*) as count 
                            FROM absences a
                            INNER JOIN course_slots cs ON a.course_slot_id = cs.id
                            WHERE a.student_identifier = :identifier" . $teacherCondition . "
                            GROUP BY cs.course_type";
        $stmt = $db->prepare($courseTypeQuery);
        $stmt->execute(array_merge([':identifier' => $student['student_number']], $teacherParam));
        $courseTypes = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $courseTypes[$row['course_type']] = (int) $row['count'];
        }

        // Get absences by subject (Top 10)
        $subjectQuery = "SELECT 
                            COALESCE(r.label, r.code) as subject_name, 
                            COUNT(*) as count 
                         FROM absences a 
                         INNER JOIN course_slots cs ON a.course_slot_id = cs.id
                         INNER JOIN resources r ON cs.resource_id = r.id
                         WHERE a.student_identifier = :identifier" . $teacherCondition . "
                         GROUP BY COALESCE(r.label, r.code)
                         ORDER BY count DESC 
                         LIMIT 10";
        $stmt = $db->prepare($subjectQuery);
        $stmt->execute(array_merge([':identifier' => $student['student_number']], $teacherParam));
        $subjects = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['subject_name']) {
                $subjects[$row['subject_name']] = (int) $row['count'];
            }
        }

        return [
            'name' => $student['first_name'] . ' ' . $student['last_name'],
            'student_number' => $student['student_number'] ?? 'N/A',
            'total' => (int) $total,
            'justified' => (int) $justified,
            'unjustified' => (int) $unjustified,
            'evaluation_absences' => (int) $evaluationAbsences,
            'rate' => $rate,
            'courseTypes' => $courseTypes,
            'subjects' => $subjects
        ];

    } catch (PDOException $e) {
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
    'evaluation_absences' => 0,
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
$evaluationBySubject = [];

try {
    // Get all resources for filter dropdown
    $resourcesQuery = "SELECT id, code, COALESCE(label, code) as label FROM resources ORDER BY label";
    $stmt = $db->query($resourcesQuery);
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get groups as "semesters" for filter
    $semestersQuery = "SELECT DISTINCT code FROM groups ORDER BY code";
    $stmt = $db->query($semestersQuery);
    $semesters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Total absences
    $totalQuery = "SELECT COUNT(*) as total 
                   FROM absences a 
                   INNER JOIN users u ON a.student_identifier = u.identifier
                   LEFT JOIN course_slots cs ON a.course_slot_id = cs.id
                   $whereClause";
    $stmt = $db->prepare($totalQuery);
    $stmt->execute($params);
    $totalAbsences = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Total students with absences
    $studentsQuery = "SELECT COUNT(DISTINCT a.student_identifier) as total 
                      FROM absences a 
                      INNER JOIN users u ON a.student_identifier = u.identifier
                      LEFT JOIN course_slots cs ON a. course_slot_id = cs.id
                      $whereClause";
    $stmt = $db->prepare($studentsQuery);
    $stmt->execute($params);
    $totalStudents = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Justified absences (with accepted proof)
    $justifiedQuery = "SELECT COUNT(DISTINCT a.id) as total 
                       FROM absences a 
                       INNER JOIN users u ON a.student_identifier = u.identifier
                       LEFT JOIN course_slots cs ON a.course_slot_id = cs.id
                       INNER JOIN proof_absences pa ON a.id = pa.absence_id
                       INNER JOIN proof p ON pa.proof_id = p.id
                       " . ($whereClause ? $whereClause . " AND" : "WHERE") . " p.status = 'accepted'";
    $stmt = $db->prepare($justifiedQuery);
    $stmt->execute($params);
    $justified = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Evaluation absences (DS type or is_evaluation = true)
    $evalQuery = "SELECT COUNT(*) as total 
                  FROM absences a 
                  INNER JOIN users u ON a.student_identifier = u.identifier
                  LEFT JOIN course_slots cs ON a.course_slot_id = cs.id
                  " . ($whereClause ? $whereClause . " AND" : "WHERE") . " (cs.course_type = 'DS' OR cs.is_evaluation = true)";
    $stmt = $db->prepare($evalQuery);
    $stmt->execute($params);
    $evaluationAbsences = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $unjustified = $totalAbsences - $justified;
    $average = $totalStudents > 0 ? $totalAbsences / $totalStudents : 0;

    $stats = [
        'total_absences' => $totalAbsences,
        'total_students' => $totalStudents,
        'justified' => $justified,
        'unjustified' => $unjustified,
        'evaluation_absences' => $evaluationAbsences,
        'average' => $average
    ];

    // Course type statistics
    $courseTypeQuery = "SELECT cs.course_type, COUNT(*) as count 
                        FROM absences a 
                        INNER JOIN users u ON a.student_identifier = u. identifier
                        INNER JOIN course_slots cs ON a.course_slot_id = cs.id
                        " . ($conditions ? 'WHERE ' . implode(' AND ', $conditions) : '') . "
                        GROUP BY cs.course_type";
    $stmt = $db->prepare($courseTypeQuery);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($row['course_type']) {
            $courseTypeStats[$row['course_type']] = (int) $row['count'];
        }
    }

    // Subject statistics (Top 10)
    $subjectQuery = "SELECT 
                        COALESCE(r.label, r.code) as subject_name, 
                        COUNT(*) as count 
                     FROM absences a 
                     INNER JOIN course_slots cs ON a.course_slot_id = cs.id
                     INNER JOIN resources r ON cs.resource_id = r.id
                     INNER JOIN users u ON a.student_identifier = u.identifier
                     " . ($conditions ? 'WHERE ' . implode(' AND ', $conditions) : '') . "
                     GROUP BY COALESCE(r.label, r.code)
                     ORDER BY count DESC 
                     LIMIT 10";
    $stmt = $db->prepare($subjectQuery);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($row['subject_name']) {
            $subjectStats[$row['subject_name']] = (int) $row['count'];
        }
    }

    // Evaluation absences by subject (Top 10)
    $evalSubjectQuery = "SELECT 
                            COALESCE(r.label, r.code) as subject_name, 
                            COUNT(*) as count 
                         FROM absences a 
                         INNER JOIN course_slots cs ON a.course_slot_id = cs.id
                         INNER JOIN resources r ON cs.resource_id = r.id
                         INNER JOIN users u ON a.student_identifier = u.identifier
                         " . ($conditions ? 'WHERE ' . implode(' AND ', $conditions) . ' AND' : 'WHERE') . " (cs.course_type = 'DS' OR cs.is_evaluation = true)
                         GROUP BY COALESCE(r.label, r.code)
                         ORDER BY count DESC 
                         LIMIT 10";
    $stmt = $db->prepare($evalSubjectQuery);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($row['subject_name']) {
            $evaluationBySubject[$row['subject_name']] = (int) $row['count'];
        }
    }

    // Group/Semester statistics (using groups table)
    $semesterQuery = "SELECT 
                        g.id,
                        g. code as name,
                        COALESCE(SUM(CASE WHEN cs.course_type = 'CM' THEN 1 ELSE 0 END), 0) as cm,
                        COALESCE(SUM(CASE WHEN cs.course_type = 'TD' THEN 1 ELSE 0 END), 0) as td,
                        COALESCE(SUM(CASE WHEN cs.course_type = 'TP' THEN 1 ELSE 0 END), 0) as tp,
                        COUNT(a.id) as total
                      FROM groups g 
                      LEFT JOIN course_slots cs ON g.id = cs.group_id
                      LEFT JOIN absences a ON cs.id = a.course_slot_id
                      GROUP BY g.id, g.code 
                      HAVING COUNT(a. id) > 0
                      ORDER BY g.code DESC 
                      LIMIT 3";
    $stmt = $db->query($semesterQuery);
    $semesterStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Monthly evolution
    $monthlyQuery = "SELECT 
                       TO_CHAR(cs.course_date, 'Month YYYY') as month,
                       DATE_TRUNC('month', cs.course_date) as month_date,
                       COUNT(*) as total,
                       SUM(CASE WHEN p.status = 'accepted' THEN 1 ELSE 0 END) as justified
                     FROM absences a 
                     INNER JOIN course_slots cs ON a.course_slot_id = cs.id
                     INNER JOIN users u ON a.student_identifier = u. identifier
                     LEFT JOIN proof_absences pa ON a.id = pa.absence_id
                     LEFT JOIN proof p ON pa.proof_id = p.id
                     " . ($conditions ? 'WHERE ' . implode(' AND ', $conditions) : '') . "
                     GROUP BY TO_CHAR(cs.course_date, 'Month YYYY'), DATE_TRUNC('month', cs.course_date)
                     ORDER BY month_date";
    $stmt = $db->prepare($monthlyQuery);
    $stmt->execute($params);
    $monthlyResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $monthlyStats = [
        'labels' => [],
        'total' => [],
        'justified' => [],
        'unjustified' => []
    ];

    foreach ($monthlyResults as $row) {
        $monthlyStats['labels'][] = trim($row['month']);
        $monthlyStats['total'][] = (int) $row['total'];
        $monthlyStats['justified'][] = (int) $row['justified'];
        $monthlyStats['unjustified'][] = (int) $row['total'] - (int) $row['justified'];
    }

    // ===== TOP STUDENTS (LEADERBOARD) =====
    $topStudentsQuery = "SELECT 
                            u.id,
                            u.first_name,
                            u. last_name,
                            u.identifier as student_number,
                            COUNT(a.id) as total_absences,
                            COUNT(a.id) - COALESCE(SUM(CASE WHEN p.status = 'accepted' THEN 1 ELSE 0 END), 0) as unjustified
                         FROM users u
                         INNER JOIN absences a ON u.identifier = a.student_identifier
                         LEFT JOIN course_slots cs ON a.course_slot_id = cs.id
                         LEFT JOIN proof_absences pa ON a.id = pa.absence_id
                         LEFT JOIN proof p ON pa. proof_id = p.id
                         WHERE u.role = 'student'
                         " . ($conditions ? 'AND ' . implode(' AND ', $conditions) : '') . "
                         GROUP BY u.id, u. first_name, u.last_name, u.identifier
                         ORDER BY total_absences DESC, unjustified DESC
                         LIMIT 10";
    $stmt = $db->prepare($topStudentsQuery);
    $stmt->execute($params);
    $topStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ===== SUBJECT TRENDS (Top 5 subjects over time) =====
    $subjectTrendsQuery = "SELECT 
                              COALESCE(r.label, r.code) as subject,
                              TO_CHAR(cs.course_date, 'YYYY-MM') as month,
                              COUNT(*) as count
                           FROM absences a
                           INNER JOIN course_slots cs ON a.course_slot_id = cs.id
                           INNER JOIN resources r ON cs.resource_id = r. id
                           INNER JOIN users u ON a.student_identifier = u.identifier
                           " . ($conditions ? 'WHERE ' . implode(' AND ', $conditions) : '') . "
                           GROUP BY COALESCE(r.label, r. code), TO_CHAR(cs.course_date, 'YYYY-MM')
                           ORDER BY month";
    $stmt = $db->prepare($subjectTrendsQuery);
    $stmt->execute($params);
    $trendsResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process trends data
    $months = [];
    $subjectData = [];

    foreach ($trendsResults as $row) {
        if (!in_array($row['month'], $months)) {
            $months[] = $row['month'];
        }
        if ($row['subject'] && !isset($subjectData[$row['subject']])) {
            $subjectData[$row['subject']] = [];
        }
        if ($row['subject']) {
            $subjectData[$row['subject']][$row['month']] = (int) $row['count'];
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

} catch (PDOException $e) {
    // Log error for debugging
    error_log("Statistics error: " . $e->getMessage());
}