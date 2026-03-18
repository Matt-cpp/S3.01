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
    private TeacherDataModel $teacherModel;
    private int $userId;
    private int $pageCount;
    private bool $hasFilter;
    private string $filter;

    public function __construct(int $id)
    {
        $this->page = 0;
        require_once __DIR__ . '/../../Model/TeacherDataModel.php';
        $this->teacherModel = new TeacherDataModel();
        $this->userId = $this->linkTeacherUser($id);
        $this->hasFilter = false;
        $this->filter = '';
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
            $total = $this->teacherModel->countTeacherAbsences(
                $this->userId,
                $this->hasFilter ? $this->filter : null
            );
            if ($total <= 0) {
                return 1;
            }
            return (int) ceil($total / 5);
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
        return $this->teacherModel->getTeacherAbsencesPage(
            $this->userId,
            5,
            $offset,
            $this->hasFilter ? $this->filter : null
        );
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
        return $this->teacherModel->getTeacherResourceLabels($this->userId);
    }
}
