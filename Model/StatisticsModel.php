<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

class StatisticsModel
{
    private Database $db;

    public function __construct()
    {
        $this->db = getDatabase();
    }

    // Get absences grouped by course type (CM/TD/TP) for a specific period
    public function getAbsencesByCourseType(array $filters = []): array
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

    // Get absences grouped by resource/subject
    public function getAbsencesByResource(array $filters = []): array
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

    // Get evaluation absences by resource/subject
    public function getEvaluationAbsencesByResource(array $filters = []): array
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

    // Get evaluation absences count
    public function getEvaluationAbsencesCount(array $filters = []): int
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

    // Get absences trends by month
    public function getAbsencesTrendsByMonth(array $filters = []): array
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

    // Get absences trends by resource over time
    public function getResourceTrendsOverTime(array $filters = []): array
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

    // Get absences by semester
    public function getAbsencesBySemester(array $filters = []): array
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

    // Get absences statistics for a specific student
    public function getStudentStatistics(string $studentIdentifier, array $filters = []): array
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
                COUNT(DISTINCT CASE WHEN cs.course_type = 'TP' THEN a.id END) as tp_absences,
                COUNT(DISTINCT CASE WHEN cs.is_evaluation = true THEN a.id END) as evaluation_absences
            FROM absences a
            JOIN users u ON a.student_identifier = u.identifier
            JOIN course_slots cs ON a.course_slot_id = cs.id
            WHERE u.identifier = :student_identifier
        ";

        $params = [':student_identifier' => $studentIdentifier];
        $query .= $this->buildFilterConditions($filters, $params);
        $query .= " GROUP BY u.identifier, u.first_name, u.last_name";

        try {
            return $this->db->selectOne($query, $params) ?? [];
        } catch (Exception $e) {
            error_log("Error fetching student statistics: " . $e->getMessage());
            return [];
        }
    }

    // Get student absences by resource
    public function getStudentAbsencesByResource(string $studentIdentifier, array $filters = []): array
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
        $query .= $this->buildFilterConditions($filters, $params);
        $query .= " GROUP BY r.label ORDER BY total_absences DESC";

        try {
            return $this->db->select($query, $params);
        } catch (Exception $e) {
            error_log("Error fetching student absences by resource: " . $e->getMessage());
            return [];
        }
    }

    // Get student absences over time
    public function getStudentAbsencesTrends(string $studentIdentifier, array $filters = []): array
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
        $query .= $this->buildFilterConditions($filters, $params);
        $query .= " GROUP BY TO_CHAR(cs.course_date, 'YYYY-MM'), TO_CHAR(cs.course_date, 'Month YYYY')
                    ORDER BY TO_CHAR(cs.course_date, 'YYYY-MM')";

        try {
            return $this->db->select($query, $params);
        } catch (Exception $e) {
            error_log("Error fetching student trends: " . $e->getMessage());
            return [];
        }
    }

    // Get all groups for filtering
    public function getAllGroups(): array
    {
        $query = "SELECT id, label FROM groups ORDER BY label";
        try {
            return $this->db->select($query);
        } catch (Exception $e) {
            error_log("Error fetching groups: " . $e->getMessage());
            return [];
        }
    }

    // Get all resources for filtering
    public function getAllResources(): array
    {
        $query = "SELECT id, label FROM resources ORDER BY label";
        try {
            return $this->db->select($query);
        } catch (Exception $e) {
            error_log("Error fetching resources: " . $e->getMessage());
            return [];
        }
    }

    // Get top students with most absences
    public function getTopAbsentStudents(int $limit = 10, array $filters = []): array
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

    // Get general statistics summary
    public function getGeneralStatistics(array $filters = []): array
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
        $subqueryConditions = $this->buildFilterConditions($filters, $params, true, 'cs2', 'a2', 'u', false);
        $query .= $subqueryConditions . " GROUP BY a2.student_identifier
            ) as absences_per_student
            WHERE 1=1";

        $params2 = [];
        $query .= $this->buildFilterConditions($filters, $params2, true, 'cs', 'a', 'u', false);

        // Merge params
        $params = array_merge($params, $params2);

        try {
            return $this->db->selectOne($query, $params) ?? [];
        } catch (Exception $e) {
            error_log("Error fetching general statistics: " . $e->getMessage());
            return [];
        }
    }

    // Build filter conditions for queries
    private function buildFilterConditions(
        array $filters,
        array &$params,
        bool $includeAnd = true,
        string $courseSlotAlias = 'cs',
        string $absenceAlias = 'a',
        string $userAlias = 'u',
        bool $includeUserFilters = true
    ): string {
        $conditions = [];

        if (!empty($filters['start_date'])) {
            $conditions[] = "{$courseSlotAlias}.course_date >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $conditions[] = "{$courseSlotAlias}.course_date <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }

        if (!empty($filters['group_id'])) {
            $conditions[] = "{$courseSlotAlias}.group_id = :group_id";
            $params[':group_id'] = $filters['group_id'];
        }

        if (!empty($filters['resource_id'])) {
            $conditions[] = "{$courseSlotAlias}.resource_id = :resource_id";
            $params[':resource_id'] = $filters['resource_id'];
        }

        if (!empty($filters['course_type'])) {
            $conditions[] = "{$courseSlotAlias}.course_type = :course_type";
            $params[':course_type'] = $filters['course_type'];
        }

        if (isset($filters['is_evaluation'])) {
            $conditions[] = "{$courseSlotAlias}.is_evaluation = :is_evaluation";
            $params[':is_evaluation'] = $filters['is_evaluation'];
        }

        if (!empty($filters['semester'])) {
            if ($filters['semester'] === 'S1') {
                $conditions[] = "(EXTRACT(MONTH FROM {$courseSlotAlias}.course_date) BETWEEN 9 AND 12 OR EXTRACT(MONTH FROM {$courseSlotAlias}.course_date) BETWEEN 1 AND 2)";
            } elseif ($filters['semester'] === 'S2') {
                $conditions[] = "EXTRACT(MONTH FROM {$courseSlotAlias}.course_date) BETWEEN 3 AND 6";
            }
        }

        if (!empty($filters['year'])) {
            $conditions[] = "EXTRACT(YEAR FROM {$courseSlotAlias}.course_date) = :year";
            $params[':year'] = $filters['year'];
        }

        if (isset($filters['justified'])) {
            $conditions[] = "{$absenceAlias}.justified = :justified";
            $params[':justified'] = $filters['justified'];
        }

        if ($includeUserFilters && !empty($filters['student_name'])) {
            $conditions[] = "({$userAlias}.first_name ILIKE :student_name OR {$userAlias}.last_name ILIKE :student_name OR CONCAT({$userAlias}.first_name, ' ', {$userAlias}.last_name) ILIKE :student_name)";
            $params[':student_name'] = '%' . $filters['student_name'] . '%';
        }

        if (empty($conditions)) {
            return '';
        }

        return ($includeAnd ? ' AND ' : ' ') . implode(' AND ', $conditions);
    }

    // Get available years for filtering
    public function getAvailableYears(): array
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

    // Get justification rate by resource/subject
    public function getJustificationRateByResource(array $filters = []): array
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

    // ----------------------------------------------------------------
    // Student dashboard helpers (get_info.php)
    // ----------------------------------------------------------------

    /**
     * Simple total absence count for a student.
     */
    public function getStudentAbsenceCount(string $studentIdentifier): int
    {
        $sql = "SELECT COUNT(*) as total_absences_count FROM absences WHERE student_identifier = :student_id";
        try {
            $result = $this->db->selectOne($sql, ['student_id' => $studentIdentifier]);
            return (int) ($result['total_absences_count'] ?? 0);
        } catch (Exception $e) {
            error_log("Error getStudentAbsenceCount: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Half-day breakdown for a student (total, justified, unjustified, justifiable, this_month).
     */
    public function getStudentHalfDayStats(string $studentIdentifier): array
    {
        $sql = "
            WITH absence_stats AS (
                SELECT DISTINCT ON (a.id)
                    a.id,
                    cs.course_date,
                    cs.start_time,
                    a.justified as absence_justified,
                    p.status as proof_status,
                    pa.proof_id as has_proof
                FROM absences a
                JOIN course_slots cs ON a.course_slot_id = cs.id
                LEFT JOIN proof_absences pa ON a.id = pa.absence_id
                LEFT JOIN proof p ON pa.proof_id = p.id
                WHERE a.student_identifier = :student_id
                ORDER BY a.id,
                    CASE
                        WHEN p.status = 'accepted' THEN 1
                        WHEN a.justified = TRUE THEN 2
                        ELSE 3
                    END ASC
            ),
            half_day_calc AS (
                SELECT
                    course_date,
                    CASE WHEN start_time < '12:30:00' THEN 'morning' ELSE 'afternoon' END as period,
                    MAX(CASE WHEN proof_status = 'accepted' THEN 1 ELSE 0 END) as is_justified,
                    MAX(CASE WHEN (has_proof IS NULL OR proof_status = 'under_review') THEN 1 ELSE 0 END) as is_justifiable
                FROM absence_stats
                GROUP BY course_date, period
            )
            SELECT
                COUNT(*) as total_half_days,
                SUM(is_justified) as half_days_justified,
                SUM(1 - is_justified) as half_days_unjustified,
                SUM(is_justifiable) as half_days_justifiable,
                SUM(CASE
                    WHEN EXTRACT(MONTH FROM course_date) = EXTRACT(MONTH FROM CURRENT_DATE)
                    AND EXTRACT(YEAR FROM course_date) = EXTRACT(YEAR FROM CURRENT_DATE)
                    THEN 1 ELSE 0
                END) as half_days_this_month
            FROM half_day_calc
        ";
        try {
            $result = $this->db->selectOne($sql, ['student_id' => $studentIdentifier]);
            return $result ?? [
                'total_half_days' => 0,
                'half_days_justified' => 0,
                'half_days_unjustified' => 0,
                'half_days_justifiable' => 0,
                'half_days_this_month' => 0,
            ];
        } catch (Exception $e) {
            error_log("Error getStudentHalfDayStats: " . $e->getMessage());
            return [
                'total_half_days' => 0,
                'half_days_justified' => 0,
                'half_days_unjustified' => 0,
                'half_days_justifiable' => 0,
                'half_days_this_month' => 0,
            ];
        }
    }

    /**
     * Proof counts by status for a student.
     */
    public function getStudentProofCounts(string $studentIdentifier): array
    {
        $sql = "
            SELECT
                COUNT(CASE WHEN status = 'under_review' THEN 1 END) as under_review_proofs,
                COUNT(CASE WHEN status = 'pending'      THEN 1 END) as pending_proofs,
                COUNT(CASE WHEN status = 'rejected'     THEN 1 END) as rejected_proofs,
                COUNT(CASE WHEN status = 'accepted'     THEN 1 END) as accepted_proofs
            FROM proof
            WHERE student_identifier = :student_id
        ";
        try {
            $result = $this->db->selectOne($sql, ['student_id' => $studentIdentifier]);
            return $result ?? ['under_review_proofs' => 0, 'pending_proofs' => 0, 'rejected_proofs' => 0, 'accepted_proofs' => 0];
        } catch (Exception $e) {
            error_log("Error getStudentProofCounts: " . $e->getMessage());
            return ['under_review_proofs' => 0, 'pending_proofs' => 0, 'rejected_proofs' => 0, 'accepted_proofs' => 0];
        }
    }

    /**
     * Recent absences for a student with full join data (used by student dashboard).
     */
    public function getStudentRecentAbsences(string $studentIdentifier, int $limit = 5): array
    {
        $sql = "
            WITH recent_absences AS (
                SELECT DISTINCT ON (a.id)
                    a.id as absence_id,
                    cs.course_date,
                    cs.start_time,
                    cs.end_time,
                    cs.duration_minutes,
                    cs.course_type,
                    cs.is_evaluation,
                    a.justified,
                    r.code  as course_code,
                    r.label as course_name,
                    t.first_name as teacher_first_name,
                    t.last_name  as teacher_last_name,
                    rm.code as room_name,
                    p.status as proof_status,
                    m.id   as makeup_id,
                    m.scheduled  as makeup_scheduled,
                    m.makeup_date as makeup_date,
                    m.comment as makeup_comment,
                    m.duration_minutes as makeup_duration,
                    makeup_rm.code as makeup_room,
                    makeup_cs.start_time as makeup_start_time,
                    makeup_cs.end_time   as makeup_end_time,
                    makeup_r.label as makeup_resource_label,
                    CASE
                        WHEN p.status = 'accepted'     THEN 1
                        WHEN p.status = 'under_review' THEN 2
                        WHEN p.status = 'pending'      THEN 3
                        WHEN p.status = 'rejected'     THEN 4
                        ELSE 5
                    END as status_priority
                FROM absences a
                JOIN course_slots cs ON a.course_slot_id = cs.id
                LEFT JOIN resources r ON cs.resource_id = r.id
                LEFT JOIN teachers t ON cs.teacher_id = t.id
                LEFT JOIN rooms rm ON cs.room_id = rm.id
                LEFT JOIN proof_absences pa ON a.id = pa.absence_id
                LEFT JOIN proof p ON pa.proof_id = p.id
                LEFT JOIN makeups m ON a.id = m.absence_id
                LEFT JOIN rooms makeup_rm ON m.room_id = makeup_rm.id
                LEFT JOIN course_slots makeup_cs ON m.evaluation_slot_id = makeup_cs.id
                LEFT JOIN resources makeup_r ON makeup_cs.resource_id = makeup_r.id
                WHERE a.student_identifier = :student_id
                ORDER BY a.id, status_priority ASC
            )
            SELECT * FROM recent_absences
            ORDER BY course_date DESC, start_time DESC
            LIMIT :limit
        ";
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':student_id', $studentIdentifier, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getStudentRecentAbsences: " . $e->getMessage());
            return [];
        }
    }

    // ----------------------------------------------------------------
    // Student statistics presenter helpers
    // ----------------------------------------------------------------

    /**
     * Absences by course type for a specific student with optional date filters.
     */
    public function getStudentAbsencesByCourseType(string $studentIdentifier, array $filters = []): array
    {
        $query = "
            SELECT
                cs.course_type,
                COUNT(DISTINCT a.id) as total_absences
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            WHERE a.student_identifier = :student_identifier
        ";
        $params = [':student_identifier' => $studentIdentifier];

        $query .= $this->buildFilterConditions($filters, $params, true, 'cs', 'a');

        $query .= " GROUP BY cs.course_type ORDER BY cs.course_type";
        try {
            return $this->db->select($query, $params);
        } catch (Exception $e) {
            error_log("Error getStudentAbsencesByCourseType: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Monthly absence trends for a student with justified/unjustified split.
     */
    public function getStudentDetailedMonthlyTrends(string $studentIdentifier, array $filters = []): array
    {
        $query = "
            SELECT
                TO_CHAR(cs.course_date, 'YYYY-MM') as month,
                TO_CHAR(cs.course_date, 'Mon YYYY') as month_label,
                COUNT(DISTINCT a.id) as total_absences,
                COUNT(DISTINCT CASE WHEN a.justified = true  THEN a.id END) as justified,
                COUNT(DISTINCT CASE WHEN a.justified = false THEN a.id END) as unjustified
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            WHERE a.student_identifier = :student_identifier
        ";
        $params = [':student_identifier' => $studentIdentifier];

        $query .= $this->buildFilterConditions($filters, $params, true, 'cs', 'a');

        $query .= " GROUP BY TO_CHAR(cs.course_date, 'YYYY-MM'), TO_CHAR(cs.course_date, 'Mon YYYY')
                    ORDER BY TO_CHAR(cs.course_date, 'YYYY-MM')";
        try {
            return $this->db->select($query, $params);
        } catch (Exception $e) {
            error_log("Error getStudentDetailedMonthlyTrends: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Recent absences list for a student (simple, for statistics page).
     */
    public function getStudentRecentAbsencesList(string $studentIdentifier, int $limit = 10, array $filters = []): array
    {
        $sql = "
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
        ";
        $params = [':student_identifier' => $studentIdentifier, ':limit' => $limit];
        $sql .= $this->buildFilterConditions($filters, $params, true, 'cs', 'a');
        $sql .= " ORDER BY cs.course_date DESC, cs.start_time DESC
            LIMIT :limit";

        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare($sql);

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
            error_log("Error getStudentRecentAbsencesList: " . $e->getMessage());
            return [];
        }
    }

    // ----------------------------------------------------------------
    // Teacher statistics presenter helpers
    // ----------------------------------------------------------------

    /**
     * Summary stats for teacher dashboard: total absences, unique students, justified, unjustified, evaluations.
     * Supports student_name filter via buildFilterConditions.
     */
    public function getTeacherSummaryStats(array $filters = []): array
    {
        $query = "
            SELECT
                COUNT(DISTINCT a.id) as total_absences,
                COUNT(DISTINCT a.student_identifier) as total_students,
                COUNT(DISTINCT CASE WHEN a.justified = true  THEN a.id END) as justified_absences,
                COUNT(DISTINCT CASE WHEN a.justified = false THEN a.id END) as unjustified_absences,
                COUNT(DISTINCT CASE WHEN cs.is_evaluation = true THEN a.id END) as evaluation_absences
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            LEFT JOIN users u ON a.student_identifier = u.identifier
            WHERE 1=1
        ";
        $params = [];
        $query .= $this->buildFilterConditions($filters, $params);

        try {
            return $this->db->selectOne($query, $params) ?? [];
        } catch (Exception $e) {
            error_log("Error getTeacherSummaryStats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Absences by course type for teacher statistics.
     */
    public function getTeacherCourseTypeStats(array $filters = []): array
    {
        $query = "
            SELECT
                cs.course_type,
                COUNT(DISTINCT a.id) as total_absences
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            LEFT JOIN users u ON a.student_identifier = u.identifier
            WHERE 1=1
        ";
        $params = [];
        $query .= $this->buildFilterConditions($filters, $params);
        $query .= " GROUP BY cs.course_type ORDER BY cs.course_type";

        try {
            return $this->db->select($query, $params);
        } catch (Exception $e) {
            error_log("Error getTeacherCourseTypeStats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Top 10 subjects by absence count for teacher statistics.
     */
    public function getTeacherSubjectStats(array $filters = []): array
    {
        $query = "
            SELECT
                COALESCE(r.label, 'Non spécifié') as resource_label,
                COUNT(DISTINCT a.id) as total_absences
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            LEFT JOIN resources r ON cs.resource_id = r.id
            LEFT JOIN users u ON a.student_identifier = u.identifier
            WHERE 1=1
        ";
        $params = [];
        $query .= $this->buildFilterConditions($filters, $params);
        $query .= " GROUP BY r.label ORDER BY total_absences DESC LIMIT 10";

        try {
            return $this->db->select($query, $params);
        } catch (Exception $e) {
            error_log("Error getTeacherSubjectStats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Top 10 subjects by evaluation absence count for teacher statistics.
     */
    public function getTeacherEvaluationSubjectStats(array $filters = []): array
    {
        $query = "
            SELECT
                COALESCE(r.label, 'Non spécifié') as resource_label,
                COUNT(DISTINCT a.id) as total_absences
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            LEFT JOIN resources r ON cs.resource_id = r.id
            LEFT JOIN users u ON a.student_identifier = u.identifier
            WHERE cs.is_evaluation = true
        ";
        $params = [];
        $query .= $this->buildFilterConditions($filters, $params);
        $query .= " GROUP BY r.label ORDER BY total_absences DESC LIMIT 10";

        try {
            return $this->db->select($query, $params);
        } catch (Exception $e) {
            error_log("Error getTeacherEvaluationSubjectStats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Absences grouped by student group/semester (no student_name filter applied here).
     */
    public function getTeacherSemesterGroupStats(): array
    {
        $query = "
            SELECT
                g.id,
                g.label as name,
                COALESCE(SUM(CASE WHEN cs.course_type = 'CM' THEN 1 ELSE 0 END), 0) as cm,
                COALESCE(SUM(CASE WHEN cs.course_type = 'TD' THEN 1 ELSE 0 END), 0) as td,
                COALESCE(SUM(CASE WHEN cs.course_type = 'TP' THEN 1 ELSE 0 END), 0) as tp,
                COUNT(a.id) as total
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            JOIN users u ON a.student_identifier = u.identifier
            JOIN user_groups ug ON ug.user_id = u.id
            JOIN groups g ON g.id = ug.group_id
            WHERE 1=1
            GROUP BY g.id, g.label
            HAVING COUNT(a.id) > 0
            ORDER BY g.label DESC
            LIMIT 3
        ";
        try {
            return $this->db->select($query);
        } catch (Exception $e) {
            error_log("Error getTeacherSemesterGroupStats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Monthly absence trends for teacher statistics with justified/unjustified split.
     */
    public function getTeacherMonthlyStats(array $filters = []): array
    {
        $query = "
            SELECT
                TO_CHAR(cs.course_date, 'YYYY-MM') as month,
                TO_CHAR(cs.course_date, 'Month YYYY') as month_label,
                COUNT(DISTINCT a.id) as total_absences,
                COUNT(DISTINCT CASE WHEN a.justified = true  THEN a.id END) as justified_absences,
                COUNT(DISTINCT CASE WHEN a.justified = false THEN a.id END) as unjustified_absences
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            LEFT JOIN users u ON a.student_identifier = u.identifier
            WHERE 1=1
        ";
        $params = [];
        $query .= $this->buildFilterConditions($filters, $params);
        $query .= " GROUP BY TO_CHAR(cs.course_date, 'YYYY-MM'), TO_CHAR(cs.course_date, 'Month YYYY')
                    ORDER BY TO_CHAR(cs.course_date, 'YYYY-MM')";

        try {
            return $this->db->select($query, $params);
        } catch (Exception $e) {
            error_log("Error getTeacherMonthlyStats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Top 10 students by absence count for teacher statistics.
     */
    public function getTeacherTopStudents(array $filters = [], int $limit = 10): array
    {
        $query = "
            SELECT
                u.id,
                u.first_name,
                u.last_name,
                u.identifier as student_number,
                COUNT(DISTINCT a.id) as total_absences,
                COUNT(DISTINCT CASE WHEN a.justified = false THEN a.id END) as unjustified
            FROM absences a
            JOIN users u ON a.student_identifier = u.identifier
            JOIN course_slots cs ON a.course_slot_id = cs.id
            WHERE 1=1
        ";
        $params = [];
        $query .= $this->buildFilterConditions($filters, $params);
        $query .= " GROUP BY u.id, u.first_name, u.last_name, u.identifier
                    ORDER BY total_absences DESC
                    LIMIT :limit";
        $params[':limit'] = $limit;

        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare($query);
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
            error_log("Error getTeacherTopStudents: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Top 5 subjects over time (monthly trend) for teacher statistics.
     */
    public function getTeacherSubjectTrends(array $filters = []): array
    {
        try {
            $topSql = "
                SELECT COALESCE(r.label, r.code, 'Non spécifié') as resource_label,
                       COUNT(DISTINCT a.id) as total_absences
                FROM absences a
                JOIN course_slots cs ON a.course_slot_id = cs.id
                LEFT JOIN resources r ON cs.resource_id = r.id
                LEFT JOIN users u ON a.student_identifier = u.identifier
                WHERE 1=1
            ";
            $topParams = [];
            $topSql .= $this->buildFilterConditions($filters, $topParams);
            $topSql .= " GROUP BY COALESCE(r.label, r.code, 'Non spécifié')
                        ORDER BY total_absences DESC
                        LIMIT 5";

            $topRows = $this->db->select($topSql, $topParams);
            if (empty($topRows)) {
                return [];
            }

            $labels = array_map(static fn($row) => $row['resource_label'], $topRows);
            $placeholders = [];
            $params = [];
            foreach ($labels as $i => $label) {
                $key = ':subject' . $i;
                $placeholders[] = $key;
                $params[$key] = $label;
            }

            $sql = "
                SELECT
                    COALESCE(r.label, r.code, 'Non spécifié') as resource_label,
                    TO_CHAR(cs.course_date, 'YYYY-MM') as month,
                    COUNT(DISTINCT a.id) as total_absences
                FROM absences a
                JOIN course_slots cs ON a.course_slot_id = cs.id
                LEFT JOIN resources r ON cs.resource_id = r.id
                LEFT JOIN users u ON a.student_identifier = u.identifier
                WHERE COALESCE(r.label, r.code, 'Non spécifié') IN (" . implode(', ', $placeholders) . ")
            ";

            $filterParams = [];
            $sql .= $this->buildFilterConditions($filters, $filterParams);
            $params = array_merge($params, $filterParams);
            $sql .= " GROUP BY COALESCE(r.label, r.code, 'Non spécifié'), TO_CHAR(cs.course_date, 'YYYY-MM')
                     ORDER BY month, resource_label";

            return $this->db->select($sql, $params);
        } catch (Exception $e) {
            error_log("Error getTeacherSubjectTrends: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Full statistics for a single student (used in the teacher's per-student modal/detail view).
     * Returns an array with info, total, justified, course_types, subjects, evaluation_subjects.
     */
    public function getStudentDetailedStatistics(int $userId): array
    {
        try {
            // Basic student info + total counts
            $info = $this->db->selectOne("
                SELECT
                    u.identifier,
                    u.first_name,
                    u.last_name,
                    u.email,
                    g.label as group_label,
                    COUNT(DISTINCT a.id) as total_absences,
                    COUNT(DISTINCT CASE WHEN a.justified = true  THEN a.id END) as justified_absences,
                    COUNT(DISTINCT CASE WHEN a.justified = false THEN a.id END) as unjustified_absences,
                    COUNT(DISTINCT CASE WHEN cs.is_evaluation = true THEN a.id END) as evaluation_absences
                FROM users u
                LEFT JOIN absences a ON a.student_identifier = u.identifier
                LEFT JOIN course_slots cs ON a.course_slot_id = cs.id
                LEFT JOIN user_groups ug ON ug.user_id = u.id
                LEFT JOIN groups g ON g.id = ug.group_id
                WHERE u.id = :user_id
                GROUP BY u.id, u.identifier, u.first_name, u.last_name, u.email, g.label
            ", ['user_id' => $userId]);

            if (!$info) {
                return [];
            }

            $identifier = $info['identifier'];

            $courseTypes = $this->db->select("
                SELECT cs.course_type, COUNT(DISTINCT a.id) as total_absences
                FROM absences a
                JOIN course_slots cs ON a.course_slot_id = cs.id
                WHERE a.student_identifier = :identifier
                GROUP BY cs.course_type ORDER BY cs.course_type
            ", ['identifier' => $identifier]);

            $subjects = $this->db->select("
                SELECT COALESCE(r.label, 'Non spécifié') as resource_label, COUNT(DISTINCT a.id) as total_absences
                FROM absences a
                JOIN course_slots cs ON a.course_slot_id = cs.id
                LEFT JOIN resources r ON cs.resource_id = r.id
                WHERE a.student_identifier = :identifier
                GROUP BY r.label ORDER BY total_absences DESC LIMIT 10
            ", ['identifier' => $identifier]);

            $evaluationSubjects = $this->db->select("
                SELECT COALESCE(r.label, 'Non spécifié') as resource_label, COUNT(DISTINCT a.id) as total_absences
                FROM absences a
                JOIN course_slots cs ON a.course_slot_id = cs.id
                LEFT JOIN resources r ON cs.resource_id = r.id
                WHERE a.student_identifier = :identifier AND cs.is_evaluation = true
                GROUP BY r.label ORDER BY total_absences DESC LIMIT 5
            ", ['identifier' => $identifier]);

            $monthlyTrends = $this->db->select("
                SELECT
                    TO_CHAR(cs.course_date, 'YYYY-MM') as month,
                    TO_CHAR(cs.course_date, 'Month YYYY') as month_label,
                    COUNT(DISTINCT a.id) as total_absences
                FROM absences a
                JOIN course_slots cs ON a.course_slot_id = cs.id
                WHERE a.student_identifier = :identifier
                GROUP BY TO_CHAR(cs.course_date, 'YYYY-MM'), TO_CHAR(cs.course_date, 'Month YYYY')
                ORDER BY TO_CHAR(cs.course_date, 'YYYY-MM')
            ", ['identifier' => $identifier]);

            return [
                'info' => $info,
                'course_types' => $courseTypes,
                'subjects' => $subjects,
                'evaluation_subjects' => $evaluationSubjects,
                'monthly_trends' => $monthlyTrends,
            ];
        } catch (Exception $e) {
            error_log("Error getStudentDetailedStatistics: " . $e->getMessage());
            return [];
        }
    }
}
