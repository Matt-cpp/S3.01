<?php

declare(strict_types=1);

/**
 * File: proofs_presenter.php
 *
 * Student proofs presenter – Handles display of the student's proof list.
 * Provides methods to:
 * - Filter proofs (absence dates, status, reason, evaluation presence)
 * - Retrieve proofs with aggregated statistics:
 *   - Associated absence count
 *   - Total hours missed
 *   - Missed evaluation detection
 *   - Concerned course types (JSON)
 * - Format data for display (status badges, dates, periods)
 * - Translate absence reasons to French
 * - Manage rejection/validation reasons from the database
 * Used by the student "My proofs" page.
 */

require_once __DIR__ . '/../../Model/ProofModel.php';

class StudentProofsPresenter
{
    private array $filters;
    private string $errorMessage;
    private string $studentIdentifier;
    private ProofModel $proofModel;

    public function __construct(string $studentIdentifier)
    {
        $this->studentIdentifier = $studentIdentifier;
        $this->proofModel = new ProofModel();
        $this->filters = [];
        $this->errorMessage = '';
        $this->processRequest();
    }

    private function processRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['status'])) {
            $this->filters['status'] = $_GET['status'] ?? '';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateAndSetFilters();
        }
    }

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
            'reason' => $_POST['reasonFilter'] ?? '',
            'has_exam' => $_POST['examFilter'] ?? ''
        ];
    }

    public function getProofs(): array
    {
        try {
            $results = $this->proofModel->getStudentProofsFiltered($this->studentIdentifier, $this->filters);

            foreach ($results as &$proof) {
                $proof['total_hours_missed'] = ($proof['total_duration_minutes'] ?? 0) / 60;
                $proof['half_days_count'] = $this->calculateHalfDaysForProof((int) $proof['proof_id']);
            }

            return $results;
        } catch (Exception $e) {
            error_log('Error retrieving proofs: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate the number of half-days for a proof
     * Rule: 1 half-day counted if >= 1 minute of absence in the 8h-12h30 or 12h30-18h30 slot
     */
    private function calculateHalfDaysForProof(int $proofId): int
    {
        try {
            $absences = $this->proofModel->getAbsenceSlotsForProof($proofId);

            // Group by date and calculate duration per period
            $periodDurations = [];

            foreach ($absences as $absence) {
                $date = $absence['course_date'];
                $durationMinutes = (int) ($absence['duration_minutes'] ?? 0);

                // Parse times
                $startParts = explode(':', $absence['start_time']);
                $startInMinutes = ((int) $startParts[0] * 60) + (int) $startParts[1];

                $endParts = explode(':', $absence['end_time']);
                $endInMinutes = ((int) $endParts[0] * 60) + (int) $endParts[1];

                // Threshold: 12:30 = 750 minutes
                $afternoonThreshold = 750;

                if (!isset($periodDurations[$date])) {
                    $periodDurations[$date] = [
                        'morning_minutes' => 0,
                        'afternoon_minutes' => 0
                    ];
                }

                // Calculate time in each period (8h-12h30 morning, 12h30-18h30 afternoon)
                if ($startInMinutes < $afternoonThreshold && $endInMinutes <= $afternoonThreshold) {
                    // Entirely in the morning
                    $periodDurations[$date]['morning_minutes'] += $durationMinutes;
                } elseif ($startInMinutes >= $afternoonThreshold) {
                    // Entirely in the afternoon
                    $periodDurations[$date]['afternoon_minutes'] += $durationMinutes;
                } else {
                    // Spans both periods - split the duration
                    $morningPart = $afternoonThreshold - $startInMinutes;
                    $afternoonPart = $endInMinutes - $afternoonThreshold;
                    $periodDurations[$date]['morning_minutes'] += $morningPart;
                    $periodDurations[$date]['afternoon_minutes'] += $afternoonPart;
                }
            }

            // Count half-days (1 if >= 1 minute in that period)
            $totalHalfDays = 0;
            foreach ($periodDurations as $date => $periods) {
                if ($periods['morning_minutes'] >= 1) {
                    $totalHalfDays++;
                }
                if ($periods['afternoon_minutes'] >= 1) {
                    $totalHalfDays++;
                }
            }

            return $totalHalfDays;
        } catch (Exception $e) {
            error_log('Error calculating half-days for proof: ' . $e->getMessage());
            return 0;
        }
    }

    public function getReasons(): array
    {
        return [
            ['reason' => 'illness', 'label' => 'Maladie'],
            ['reason' => 'death', 'label' => 'Décès dans la famille'],
            ['reason' => 'family_obligations', 'label' => 'Obligations familiales'],
            ['reason' => 'medical_appointment', 'label' => 'Rendez-vous médical'],
            ['reason' => 'official_summons', 'label' => 'Convocation officielle (permis, TOIC, etc.)'],
            ['reason' => 'transport_issue', 'label' => 'Problème de transport'],
            ['reason' => 'other', 'label' => 'Autre (préciser)']
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
            'death' => 'Décès dans la famille',
            'family_obligations' => 'Obligations familiales',
            'medical_appointment' => 'Rendez-vous médical',
            'official_summons' => 'Convocation officielle (permis, TOIC, etc.)',
            'transport_issue' => 'Problème de transport',
            'personal_reasons' => 'Raisons personnelles',
            'other' => $customReason ? htmlspecialchars($customReason) : 'Autre'
        ];

        return $translations[$reason] ?? htmlspecialchars($reason);
    }

    public function getStatusBadge(string $status): array
    {
        switch ($status) {
            case 'accepted':
                return ['text' => 'Accepté', 'class' => 'badge-success', 'icon' => '✅'];
            case 'under_review':
                return ['text' => 'En révision', 'class' => 'badge-warning', 'icon' => '⚠️'];
            case 'pending':
                return ['text' => 'En attente', 'class' => 'badge-info', 'icon' => '🕐'];
            case 'rejected':
                return ['text' => 'Refusé', 'class' => 'badge-danger', 'icon' => '❌'];
            default:
                return ['text' => 'Inconnu', 'class' => 'badge-secondary', 'icon' => '❓'];
        }
    }

    public function formatDate(string $date): string
    {
        return date('d/m/Y', strtotime($date));
    }

    public function formatDateTime(string $datetime): string
    {
        return date('d/m/Y \u00e0 H\hi', strtotime($datetime));
    }

    public function formatPeriod(string $startDate, string $endDate): string
    {
        $start = $this->formatDate($startDate);
        $end = $this->formatDate($endDate);
        return $start === $end ? $start : "$start $end";
    }
}
