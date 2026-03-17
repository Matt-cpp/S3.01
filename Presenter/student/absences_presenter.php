<?php

declare(strict_types=1);

/**
 * File: absences_presenter.php
 *
 * Student absences presenter – handles display and filtering of absences for a specific student.
 * Provides methods to:
 * - Filter absences (dates, status, course type)
 * - Retrieve absences with associated proofs
 * - Format data for display (statuses, reasons, dates)
 * - Manage proof status priority (accepted > justified > pending)
 * - Calculate total half-days of absence
 * - Translate absence reasons to French
 * Used by the student "My absences" page.
 */

require_once __DIR__ . '/../../Model/UserModel.php';
require_once __DIR__ . '/../../Model/AbsenceModel.php';

class StudentAbsencesPresenter
{
    private array $filters;
    private string $errorMessage;
    private string $studentIdentifier;
    private UserModel $userModel;
    private AbsenceModel $absenceModel;

    public function __construct(string $studentIdentifier)
    {
        $this->studentIdentifier = $studentIdentifier;
        $this->userModel = new UserModel();
        $this->absenceModel = new AbsenceModel();
        $this->filters = [];
        $this->errorMessage = '';
        $this->processRequest();
    }

    // Process request: extract and validate POST filters
    private function processRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateAndSetFilters();
        }
    }

    // Validate and store filters: check date consistency
    private function validateAndSetFilters(): void
    {
        if (!empty($_POST['firstDateFilter']) && !empty($_POST['lastDateFilter'])) {
            if ($_POST['firstDateFilter'] > $_POST['lastDateFilter']) {
                $this->errorMessage = 'La première date doit être antérieure à la deuxième date.';
                return;
            }
        }

        $this->filters = [
            'start_date' => $_POST['firstDateFilter'] ?? '',
            'end_date' => $_POST['lastDateFilter'] ?? '',
            'status' => $_POST['statusFilter'] ?? '',
            'course_type' => $_POST['courseTypeFilter'] ?? ''
        ];
    }

    public function getStudentIdentifier(mixed $studentIdOrIdentifier): string
    {
        if (!is_numeric($studentIdOrIdentifier)) {
            return $studentIdOrIdentifier;
        }

        $result = $this->userModel->getUserById((int) $studentIdOrIdentifier);

        if ($result) {
            $_SESSION['first_name'] = $result['first_name'];
            $_SESSION['last_name'] = $result['last_name'];
            return $result['identifier'];
        }

        throw new Exception('Student not found');
    }

    public function getAbsences(): array
    {
        try {
            $results = $this->absenceModel->getStudentAbsencesDetailed($this->studentIdentifier, $this->filters);

            // Sort results by date and time descending (most recent first)
            usort($results, function ($a, $b) {
                $dateCompare = strtotime($b['course_date']) - strtotime($a['course_date']);
                if ($dateCompare !== 0) {
                    return $dateCompare;
                }
                return strcmp($b['start_time'], $a['start_time']);
            });

            return $results;
        } catch (Exception $e) {
            error_log('Error retrieving absences: ' . $e->getMessage());
            return [];
        }
    }

    public function getCourseTypes(): array
    {
        return [
            ['course_type' => 'CM'],
            ['course_type' => 'TD'],
            ['course_type' => 'TP']
        ];
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    public function translateReason(?string $reason, ?string $customReason = null): string
    {
        if (!$reason) {
            return '';
        }

        $translations = [
            'illness' => 'Maladie',
            'death' => 'Décès',
            'family_obligations' => 'Obligations familiales',
            'official_summons' => 'Convocation officielle',
            'transport_issue' => 'Problème de transport',
            'rdv_medical' => 'Rendez-vous médical',
            'other' => $customReason ? htmlspecialchars($customReason) : 'Autre'
        ];

        return $translations[$reason] ?? htmlspecialchars($reason);
    }

    public function translateStatus(bool $justified): string
    {
        return $justified ? 'Justifiée' : 'Non justifiée';
    }

    public function hasProof(array $absence): bool
    {
        return !empty($absence['proof_status']) &&
            $absence['proof_status'] === 'accepted' &&
            !empty($absence['file_path']);
    }

    public function getProofStatus(array $absence): array
    {
        $proofStatus = $absence['proof_status'] ?? null;

        if ($proofStatus === 'accepted') {
            return ['text' => 'Justifiée', 'class' => 'badge-success', 'icon' => '✅'];
        } elseif ($proofStatus === 'under_review') {
            return ['text' => 'En révision', 'class' => 'badge-warning', 'icon' => '⚠️'];
        } elseif ($proofStatus === 'pending') {
            return ['text' => 'En attente', 'class' => 'badge-info', 'icon' => '🕐'];
        } elseif ($proofStatus === 'rejected') {
            return ['text' => 'Rejeté', 'class' => 'badge-rejected', 'icon' => '🚫'];
        } else {
            return ['text' => 'Non justifiée', 'class' => 'badge-danger', 'icon' => '❌'];
        }
    }

    public function getProofPath(array $absence): string
    {
        if ($this->hasProof($absence) && isset($absence['file_path'])) {
            return '../../' . ($absence['file_path'] ?? '');
        }
        return '';
    }

    public function formatDate(string $date): string
    {
        return date('d/m/Y', strtotime($date));
    }

    public function formatTime(string $startTime, string $endTime): string
    {
        return substr($startTime, 0, 5) . ' - ' . substr($endTime, 0, 5);
    }

    public function getTotalHalfDays(array $absences): int
    {
        $halfDays = [];

        foreach ($absences as $absence) {
            $date = $absence['course_date'];
            $startTime = $absence['start_time'];

            // Determine period (morning if < 12:30, otherwise afternoon)
            $period = (strtotime($startTime) < strtotime('12:30:00')) ? 'morning' : 'afternoon';

            $key = $date . '_' . $period;
            $halfDays[$key] = true;
        }

        return count($halfDays);
    }
}
