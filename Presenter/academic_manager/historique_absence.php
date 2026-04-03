<?php

declare(strict_types=1);

/**
 * Absence history presenter - Manages display and filtering of the full absence history.
 * Provides methods for:
 * - Filtering absences by multiple criteria:
 *   - Search by student name (first_name, last_name)
 *   - Filter by period (start and end date)
 *   - Filter by justification status (pending, accepted, rejected, under review, unjustified)
 *   - Filter by course type (CM, TD, TP, DS, etc.)
 * - Retrieving formatted data with joins (user, course, proofs)
 * - Translating statuses and reasons to French for display
 * - Validating date consistency
 * Used by the view templates/academic_manager/historique_absence.php.
 */

// Page protection with authentication
require_once __DIR__ . '/../shared/auth_guard.php';
$user = requireAuth();

require_once __DIR__ . '/../../Model/AbsenceModel.php';

class AbsenceHistoryPresenter
{
    private AbsenceModel $absenceModel;
    private array $filters;
    private string $errorMessage;

    public function __construct()
    {
        $this->absenceModel = new AbsenceModel();
        $this->filters = [];
        $this->errorMessage = '';
        $this->processRequest();
    }

    private function processRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateAndSetFilters();
        }
    }

    private function validateAndSetFilters(): void
    {
        // Date validation
        if (!empty($_POST['firstDateFilter']) && !empty($_POST['lastDateFilter'])) {
            if ($_POST['firstDateFilter'] > $_POST['lastDateFilter']) {
                $this->errorMessage = "La première date doit être antérieure à la deuxième date.";
                return;
            }
        }

        $this->filters = [
            'name' => $_POST['nameFilter'] ?? '',
            'start_date' => $_POST['firstDateFilter'] ?? '',
            'end_date' => $_POST['lastDateFilter'] ?? '',
            'JustificationStatus' => $_POST['JustificationStatusFilter'] ?? '',
            'course_type' => $_POST['courseTypeFilter'] ?? ''
        ];
    }

    public function getAbsences(): array
    {
        return $this->absenceModel->getAllAbsences($this->filters);
    }

    public function getCourseTypes(): array
    {
        return $this->absenceModel->getCourseTypes();
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    // Translate reason to French (UI text)
    public function translateMotif(?string $motif): string
    {
        $translations = [
            'illness' => 'Maladie',
            'death' => 'Décès',
            'family_obligations' => 'Famille',
            'medical' => 'Médical',
            'transport' => 'Transport',
            'personal' => 'Personnel',
            'other' => 'Autre'
        ];

        return isset($translations[$motif]) ? $translations[$motif] : ($motif ?: '');
    }

    // Translate justified status to French (UI text)
    public function translateStatus(bool $justified): string
    {
        return $justified ? 'Justifiée' : 'Non justifiée';
    }

    public function hasProof(array $absence): bool
    {
        if (!empty($absence['proof_files'])) {
            $files = is_array($absence['proof_files']) ? $absence['proof_files'] : json_decode($absence['proof_files'], true);
            return is_array($files) && count($files) > 0;
        }
        return !empty($absence['motif']) || !empty($absence['file_path']);
    }

    public function getProofFiles(array $absence): array
    {
        if (!empty($absence['proof_files'])) {
            if (is_array($absence['proof_files'])) {
                return $absence['proof_files'];
            }
            $decoded = json_decode($absence['proof_files'], true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    public function getProofPath(array $absence): string
    {
        if ($this->hasProof($absence) && isset($absence['file_path'])) {
            return '../../' . ($absence['file_path'] ?? '');
        }
        return '';
    }

    // Get status label in French (UI text)
    public function getStatus(array $absence): string
    {
        if (!empty($absence['justification_status'])) {
            $statusTranslations = [
                'pending' => 'En attente',
                'accepted' => 'Acceptée',
                'rejected' => 'Rejetée',
                'under_review' => 'En cours d\'examen'
            ];
            return $statusTranslations[$absence['justification_status']] ?? 'Inconnu';
        }
        return $absence['status'] ? 'Justifiée' : 'Non justifiée';
    }

    public function formatDate(string $date): string
    {
        return date('d/m/Y', strtotime($date));
    }

    public function formatTime(string $startTime, string $endTime): string
    {
        $start = date('H:i', strtotime($startTime));
        $end = date('H:i', strtotime($endTime));
        return $start . ' - ' . $end;
    }
}

$presenter = new AbsenceHistoryPresenter();
