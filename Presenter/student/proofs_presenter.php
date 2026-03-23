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
                // half_days_count is already calculated in SQL query, no need to recalculate
            }

            return $results;
        } catch (Exception $e) {
            error_log('Error retrieving proofs: ' . $e->getMessage());
            return [];
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
