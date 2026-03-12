<?php

declare(strict_types=1);

/**
 * Teacher Dashboard Presenter - Handles absence display for a specific teacher.
 * Provides methods for:
 * - Retrieving absences for a teacher's courses with pagination
 * - Filtering by resource/subject
 * - Generating an HTML table with absent student information
 * - Handling page navigation (5 entries per page)
 * Allows teachers to track absences in their courses.
 */

class TeacherDashboardPresenter
{
    private int $page;
    private Database $db;
    private int $userId;
    private int $pageCount;
    private bool $hasFilter;
    private string $filter;

    public function __construct(int $id)
    {
        $this->page = 0;
        require_once __DIR__ . '/../../Model/database.php';
        $this->db = Database::getInstance();
        $this->userId = $this->linkTeacherUser($id);
        $this->hasFilter = false;
        $this->filter = '';
        $this->pageCount = $this->getTotalPages();
    }

    private function linkTeacherUser(int $id): int
    {
        $query = "SELECT teachers.id as id
        FROM users LEFT JOIN teachers ON teachers.email = users.email
        WHERE users.id = " . $id;
        $result = $this->db->select($query);
        return $result[0]['id'];
    }
    // Calculate total number of table pages
    public function getTotalPages(): int
    {
        try {
            if ($this->hasFilter === false) {
                $query = "SELECT COUNT(*) as count 
                FROM absences LEFT JOIN course_slots 
                ON absences.course_slot_id = course_slots.id
                WHERE course_slots.teacher_id = " . intval($this->userId);
            } else {
                $query = "SELECT COUNT(*) as count 
                FROM absences LEFT JOIN course_slots 
                ON absences.course_slot_id = course_slots.id
                LEFT JOIN resources ON course_slots.resource_id = resources.id
                WHERE course_slots.teacher_id = " . intval($this->userId) . "
                AND resources.label = '" . addslashes($this->filter) . "'";
            }

            $result = $this->db->select($query);
            if (empty($result)) {
                return 1;
            }
            return (int) ceil($result[0]['count'] / 5);
        } catch (Exception $e) {
            error_log('Error in getTotalPages: ' . $e->getMessage());
            return 1;
        }
    }

    // Return total page count without re-querying
    public function getPageCount(): int
    {
        return $this->pageCount;
    }

    public function getNombrePages(): int
    {
        return $this->pageCount;
    }

    // Return current page number
    public function getPage(): int
    {
        return $this->page;
    }
    // Main table query
    public function getData(int $page): array
    {
        $offset = (int) ($page * 5);
        $userId = intval($this->userId);
        if ($this->hasFilter === true) {
            $query = "SELECT users.first_name, users.last_name, COALESCE(groups.label, 'N/A') as degrees, course_slots.course_date, absences.status, resources.label
            FROM absences 
            LEFT JOIN users ON absences.student_identifier = users.identifier
            LEFT JOIN course_slots ON absences.course_slot_id = course_slots.id
            LEFT JOIN resources ON course_slots.resource_id = resources.id
            LEFT JOIN user_groups ON users.id = user_groups.user_id
            LEFT JOIN groups ON user_groups.group_id = groups.id
            WHERE course_slots.teacher_id = " . $userId . "
            AND resources.label = '" . addslashes($this->filter) . "'
            ORDER BY course_slots.course_date DESC
            LIMIT 5 OFFSET " . $offset;
        } else {
            $query = "SELECT users.first_name, users.last_name, COALESCE(groups.label, 'N/A') as degrees, course_slots.course_date, absences.status, resources.label
            FROM absences 
            LEFT JOIN users ON absences.student_identifier = users.identifier
            LEFT JOIN course_slots ON absences.course_slot_id = course_slots.id
            LEFT JOIN resources ON course_slots.resource_id = resources.id
            LEFT JOIN user_groups ON users.id = user_groups.user_id
            LEFT JOIN groups ON user_groups.group_id = groups.id
            WHERE course_slots.teacher_id = " . $userId . "
            ORDER BY course_slots.course_date DESC
            LIMIT 5 OFFSET " . $offset;
        }

        return $this->db->select($query);
    }

    public function setPage(int $page): void
    {
        if ($page >= 0 && $page < $this->pageCount) {
            $this->page = $page;
        }
    }
    // Advance page by 1 if possible
    public function nextPage(): void
    {
        if ($this->page < $this->pageCount - 1) {
            $this->page++;
        }
    }
    // Go back one page if possible
    public function previousPage(): void
    {
        if ($this->page > 0) {
            $this->page--;
        }
    }
    // Return current page number
    public function getCurrentPage(): int
    {
        return $this->page;
    }
    // Access next and previous pages with boundary limits
    public function getNextPage(): int
    {
        return min($this->page + 1, $this->pageCount - 1);
    }
    public function getPreviousPage(): int
    {
        return max($this->page - 1, 0);
    }

    // Build HTML table
    public function buildTable(): string
    {
        // Retrieve raw data
        $rows = $this->getData($this->getCurrentPage());
        $table = [];
        // Build HTML table
        foreach ($rows as $row) {
            $table[] = "<tr>
            <td>" . htmlspecialchars($row['first_name']) . "</td>
            <td>" . htmlspecialchars($row['last_name']) . "</td>
            <td>" . htmlspecialchars($row['degrees']) . "</td>
            <td>" . htmlspecialchars($row['label']) . "</td>
            <td>" . htmlspecialchars($row['course_date']) . "</td>
            <td>" . htmlspecialchars($row['status']) . "</td>
            </tr>";
        }
        return "<table border='1'>  
        <tr>
        <th>First Name</th>
        <th>Last Name</th>
        <th>Degrees</th>
        <th>Resource Label</th>
        <th>Course Date</th>
        <th>Status</th>
        </tr>" . implode('', $table) . "</table>";
    }

    public function enableFilter(string $name): void
    {
        $this->hasFilter = true;
        $this->filter = $name;
        $this->pageCount = $this->getTotalPages();
        $this->page = 0;
    }

    public function disableFilter(): void
    {
        $this->hasFilter = false;
        $this->filter = '';
        $this->pageCount = $this->getTotalPages();
        $this->page = 0;
    }

    public function getResourceLabels(): array
    {
        $query = "SELECT DISTINCT resources.label
        From course_slots 
        Left Join resources ON course_slots.resource_id = resources.id
        WHERE course_slots.teacher_id=" . $this->userId;
        $result = $this->db->select($query);
        $labels = [];
        foreach ($result as $row) {
            $labels[] = $row['label'];
        }
        return $labels;
    }
}
