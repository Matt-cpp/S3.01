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
    private TeacherDataModel $teacherModel;
    private int $userId;
    private int $pageCount;

    public function __construct(int $id)
    {
        $this->page = 0;
        require_once __DIR__ . '/../../Model/TeacherDataModel.php';
        $this->teacherModel = new TeacherDataModel();
        $this->userId = $this->linkTeacherUser($id);
        $this->pageCount = $this->getTotalPages();
    }

    private function linkTeacherUser(int $id): int
    {
        return (int) ($this->teacherModel->getTeacherIdByUserId($id) ?? 0);
    }
    // Calculate total number of table pages
    public function getTotalPages(): int
    {
        try {
            $total = $this->teacherModel->countPendingMakeups($this->userId);
            if ($total <= 0) {
                return 1;
            }
            return (int) ceil($total / 5);
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
        return $this->teacherModel->getPendingMakeupsPage($this->userId, 5, $offset);
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