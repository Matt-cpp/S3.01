<?php

declare(strict_types=1);

/**
 * Academic manager dashboard presenter - Manages display and statistics for the dashboard.
 * Provides methods for:
 * - Retrieving recent absences with pagination (5 per page)
 * - Calculating real-time statistics:
 *   - Today's absences
 *   - Current month absence total
 *   - Total unjustified absences
 * - Generating formatted table for HTML display
 * - Managing page navigation (pagination)
 * - Translating absence statuses to French
 * Used by the academic manager home page (home.php).
 */

class AcademicManagerDashboardPresenter
{
    private int $page;
    private array $alldata;
    private AcademicManagerModel $academicManagerModel;
    private ProofModel $proofModel;

    public function __construct()
    {
        $this->page = 0;
        require_once __DIR__ . '/../../Model/AcademicManagerModel.php';
        require_once __DIR__ . '/../../Model/ProofModel.php';
        require_once __DIR__ . '/../../Model/format_ressource.php';
        $this->academicManagerModel = new AcademicManagerModel();
        $this->alldata = [];
        $this->proofModel = new ProofModel();
    }

    /**
     * Get the number of pending proofs
     */
    public function pendingProofsCount(): int
    {
        return $this->academicManagerModel->countPendingProofs();
    }

    /**
     * Get recent proofs
     */
    public function getRecentProofs(int $limit = 5): array
    {
        return $this->proofModel->getRecentProofs($limit);
    }

    /**
     * Translate a field via the ProofModel
     */
    public function translateProof(string $field, string $value): string
    {
        return $this->proofModel->translate($field, $value);
    }

    // Retrieve absence data with joins for a specific page
    // @param int $page - Page number (0-indexed)
    // @return array - Array of absences with full info (user, course, resource)
    public function getData(int $page): array
    {
        $offset = $page * 5;
        return $this->academicManagerModel->getAbsencePage(5, $offset);
    }

    // Translate status to French (UI text)
    private function translateStatus(string $status): string
    {
        $translations = [
            'absent' => 'Absent',
            'present' => 'Présent',
            'excused' => 'Excusé',
            'unjustified' => 'Non justifié'
        ];
        return $translations[$status] ?? ucfirst($status);
    }

    // Returns the total number of pages
    public function getTotalPages(): int
    {
        return (int) ceil($this->academicManagerModel->countAllAbsences() / 5);
    }

    // Updates the page attribute with bounds checking
    public function setPage(int $page): void
    {
        if ($page >= 0 && $page < $this->getTotalPages()) {
            $this->page = $page;
        }
    }

    // Advance the page by 1 if possible
    public function nextPage(): void
    {
        if ($this->page < $this->getTotalPages() - 1) {
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

    // Returns the current page number
    public function getCurrentPage(): int
    {
        return $this->page;
    }

    // Get next and previous page numbers with bounds
    public function getNextPage(): int
    {
        return min($this->page + 1, $this->getTotalPages() - 1);
    }

    public function getPreviousPage(): int
    {
        return max($this->page - 1, 0);
    }

    // Statistics
    public function todayAbs(): int
    {
        return $this->academicManagerModel->countTodayAbsences();
    }

    public function unjustifiedAbs(): int
    {
        return $this->academicManagerModel->countUnjustifiedAbsences();
    }

    public function thisMonthAbs(): int
    {
        return $this->academicManagerModel->countCurrentMonthAbsences();
    }

    // Build table data
    public function buildTable(): array
    {
        $rows = $this->getData($this->getCurrentPage());
        $table = [];

        foreach ($rows as $row) {
            $date = date('d/m/Y', strtotime($row['course_date']));
            $time = substr($row['start_time'], 0, 5) . ' - ' . substr($row['end_time'], 0, 5);
            $student = $row['first_name'] . ' ' . $row['last_name'];
            $course = formatResourceLabel($row['label'] ?? 'Non spécifié');
            $type = strtoupper($row['course_type'] ?? '');
            $status = $this->translateStatus($row['status']);

            $table[] = [
                $date,
                $time,
                $student,
                $course,
                $type,
                $status
            ];
        }
        return $table;
    }

    // Alias for backward compatibility
    public function laTable(): array
    {
        return $this->buildTable();
    }
}
