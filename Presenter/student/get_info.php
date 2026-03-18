<?php

declare(strict_types=1);

/**
 * File: get_info.php
 *
 * Student information retrieval service – Provides statistics and data for the dashboard.
 * Main functions:
 * - getStudentIdentifier(): Retrieves a student's identifier from their user ID
 * - getAbsenceStatistics(): Computes full absence stats
 *   - Total absence count (missed courses)
 *   - Half-day calculation (morning/afternoon) with deduplication
 *   - Justified/unjustified/justifiable half-days
 *   - Total hours missed
 * - getRecentAbsences(): Retrieves latest absences with details
 * - getProofsByCategory(): Retrieves proofs sorted by status
 * Used for the student dashboard and session cache.
 */

require_once __DIR__ . '/../../Model/UserModel.php';
require_once __DIR__ . '/../../Model/StatisticsModel.php';
require_once __DIR__ . '/../../Model/ProofModel.php';

/**
 * Get a student's identifier from their user ID or return it if already an identifier
 */
function getStudentIdentifier(mixed $studentIdOrIdentifier): string
{
    if (!is_numeric($studentIdOrIdentifier)) {
        return $studentIdOrIdentifier;
    }

    $result = (new UserModel())->getUserById((int) $studentIdOrIdentifier);

    if ($result) {
        $_SESSION['first_name'] = $result['first_name'];
        $_SESSION['last_name'] = $result['last_name'];
        return $result['identifier'];
    }

    throw new Exception('Student not found');
}

function getAbsenceStatistics(mixed $studentIdentifier): array
{
    $studentIdentifier = getStudentIdentifier($studentIdentifier);
    $statisticsModel = new StatisticsModel();
    $totalAbsences = $statisticsModel->getStudentAbsenceCount($studentIdentifier);
    $halfDayStats = $statisticsModel->getStudentHalfDayStats($studentIdentifier);
    $proofCounts = $statisticsModel->getStudentProofCounts($studentIdentifier);

    return [
        'total_absences_count' => $totalAbsences,
        'total_half_days' => (int) ($halfDayStats['total_half_days'] ?? 0),
        'half_days_justified' => (int) ($halfDayStats['half_days_justified'] ?? 0),
        'half_days_unjustified' => (int) ($halfDayStats['half_days_unjustified'] ?? 0),
        'half_days_justifiable' => (int) ($halfDayStats['half_days_justifiable'] ?? 0),
        'half_days_this_month' => (int) ($halfDayStats['half_days_this_month'] ?? 0),
        'under_review_proofs' => (int) $proofCounts['under_review_proofs'],
        'pending_proofs' => (int) $proofCounts['pending_proofs'],
        'rejected_proofs' => (int) $proofCounts['rejected_proofs'],
        'accepted_proofs' => (int) $proofCounts['accepted_proofs']
    ];
}

function getRecentAbsences(mixed $studentIdentifier, int $limit = 5): array
{
    $studentIdentifier = getStudentIdentifier($studentIdentifier);
    return (new StatisticsModel())->getStudentRecentAbsences($studentIdentifier, $limit);
}

function getProofsByCategory(mixed $studentIdentifier): array
{
    $studentIdentifier = getStudentIdentifier($studentIdentifier);
    $proofModel = new ProofModel();
    $underReview = $proofModel->getStudentProofsByStatus($studentIdentifier, 'under_review');
    $pending = $proofModel->getStudentProofsByStatus($studentIdentifier, 'pending');
    $accepted = $proofModel->getStudentProofsByStatus($studentIdentifier, 'accepted');
    $rejected = $proofModel->getStudentProofsByStatus($studentIdentifier, 'rejected');

    return [
        'under_review' => $underReview,
        'pending' => $pending,
        'accepted' => $accepted,
        'rejected' => $rejected
    ];
}
