<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

class AcademicManagerModel
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? getDatabase();
    }

    public function getAbsencePage(int $limit, int $offset): array
    {
        $sql = "SELECT
                    course_slots.course_date,
                    course_slots.start_time,
                    course_slots.end_time,
                    users.first_name,
                    users.last_name,
                    resources.label,
                    course_slots.course_type,
                    absences.status
                FROM absences
                LEFT JOIN users ON absences.student_identifier = users.identifier
                LEFT JOIN course_slots ON absences.course_slot_id = course_slots.id
                LEFT JOIN resources ON course_slots.resource_id = resources.id
                ORDER BY course_slots.course_date DESC, course_slots.start_time DESC, absences.id ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countAllAbsences(): int
    {
        $row = $this->db->selectOne("SELECT COUNT(*) as count FROM absences");
        return (int) ($row['count'] ?? 0);
    }

    public function countPendingProofs(): int
    {
        $row = $this->db->selectOne("SELECT COUNT(*) as count FROM proof WHERE status = 'pending'");
        return (int) ($row['count'] ?? 0);
    }

    public function countTodayAbsences(): int
    {
        $row = $this->db->selectOne(
            "SELECT COUNT(*) as count
             FROM absences
             LEFT JOIN course_slots ON absences.course_slot_id = course_slots.id
             WHERE DATE(course_slots.course_date) = CURRENT_DATE"
        );
        return (int) ($row['count'] ?? 0);
    }

    public function countUnjustifiedAbsences(): int
    {
        $row = $this->db->selectOne("SELECT COUNT(*) as count FROM absences WHERE justified = FALSE");
        return (int) ($row['count'] ?? 0);
    }

    public function countCurrentMonthAbsences(): int
    {
        $row = $this->db->selectOne(
            "SELECT COUNT(*) as count
             FROM absences
             LEFT JOIN course_slots ON absences.course_slot_id = course_slots.id
             WHERE EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM CURRENT_DATE)
               AND EXTRACT(MONTH FROM course_slots.course_date) = EXTRACT(MONTH FROM CURRENT_DATE)"
        );
        return (int) ($row['count'] ?? 0);
    }
}
