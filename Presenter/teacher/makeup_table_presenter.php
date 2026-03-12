<?php

declare(strict_types=1);

/**
 * Makeup Table Presenter - Handles the makeup table display for teachers.
 * Provides methods for:
 * - Retrieving justified absences from evaluations with pagination
 * - Generating an HTML table of students needing makeup exams
 * - Handling page navigation (5 entries per page)
 * - Filtering absences for a specific teacher
 * Used by teachers to see makeups to organize.
 */

class MakeupTablePresenter
{
    private int $page;
    private Database $db;
    private int $userId;
    private int $pageCount;

    public function __construct(int $id)
    {
        $this->page = 0;
        require_once __DIR__ . '/../../Model/database.php';
        $this->db = Database::getInstance();
        $this->userId = $this->linkTeacherUser($id);
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
            $query = "SELECT COUNT(*) as count FROM absences 
            LEFT JOIN course_slots ON absences.course_slot_id = course_slots.id
            LEFT JOIN makeups ON absences.id = makeups.absence_id
            WHERE course_slots.teacher_id=" . $this->userId . " AND absences.justified=TRUE 
            AND course_slots.is_evaluation=true AND makeups.id IS NULL";
            $result = $this->db->select($query);
            if (empty($result)) {
                return 1;
            }
            return (int) ceil($result[0]['count'] / 5);
        } catch (Exception $e) {
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
        $userId = (int) $this->userId;

        $query = "SELECT users.first_name, users.last_name, resources.label, course_slots.course_date, 
                     course_slots.id as course_slot_id, course_slots.duration_minutes
    FROM absences 
    LEFT JOIN course_slots ON absences.course_slot_id = course_slots.id
    LEFT JOIN users ON absences.student_identifier = users.identifier
    LEFT JOIN resources ON course_slots.resource_id = resources.id
    LEFT JOIN makeups ON absences.id = makeups.absence_id
    WHERE course_slots.teacher_id=" . $this->userId . " AND absences.justified=TRUE 
    AND course_slots.is_evaluation=true AND makeups.id IS NULL
    ORDER BY course_slots.course_date DESC
    LIMIT 5 OFFSET $offset";
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
        // Build HTML table
        $table = "<table border='1'>  
        <tr>
            <th>First Name</th>
            <th>Last Name</th>
            <th>Resource</th>
            <th>Course Date</th>
            <th>Duration (minutes)</th>
        </tr>";
        foreach ($rows as $row) {
            $table .= "<tr>
                <td>" . htmlspecialchars($row['first_name']) . "</td>
                <td>" . htmlspecialchars($row['last_name']) . "</td>
                <td>" . htmlspecialchars($row['label']) . "</td>
                <td>" . htmlspecialchars($row['course_date']) . "</td>
                <td>" . htmlspecialchars($row['duration_minutes']) . "</td>
            </tr>";
        }
        $table .= "</table>";
        return $table;
    }
}
/*s
$test = new tableRatrapage(2);

if (isset($_GET['page'])) {
    $page = intval($_GET['page']);
    $test->setPage($page);
}
echo $test->laTable();
?>
<a href="?page=<?php echo $test->getPreviousPage(); ?>">
    <button type="button">previous</button>
</a>
<a href="?page=<?php echo $test->getNextPage(); ?>">
    <button type="button">next</button>
</a>

<br>
*/