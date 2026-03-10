<?php

declare(strict_types=1);

/**
 * File: dashboard_presenter.php
 *
 * Student dashboard presenter – handles data retrieval and calculation
 * for the student home page.
 * Provides methods to:
 * - Retrieve absence statistics (with session cache)
 * - Retrieve proofs by category
 * - Retrieve recent absences
 * - Calculate justification percentage
 * - Calculate half-points lost
 */

require_once __DIR__ . '/../shared/session_cache.php';
require_once __DIR__ . '/get_info.php';

class StudentDashboardPresenter
{
    private int $studentId;
    private array $stats;
    private array $proofsByCategory;
    private array $recentAbsences;

    public function __construct(int $studentId, bool $forceRefresh = false)
    {
        $this->studentId = $studentId;
        $this->loadData($forceRefresh);
    }

    /**
     * Load data from cache or database
     */
    private function loadData(bool $forceRefresh): void
    {
        if (
            $forceRefresh ||
            !isset($_SESSION['stats']) ||
            !isset($_SESSION['proofsByCategory']) ||
            !isset($_SESSION['recentAbsences']) ||
            !isset($_SESSION['stats']['total_absences_count']) ||
            shouldRefreshCache(20)
        ) {
            $_SESSION['stats'] = getAbsenceStatistics($this->studentId);
            $_SESSION['proofsByCategory'] = getProofsByCategory($this->studentId);
            $_SESSION['recentAbsences'] = getRecentAbsences($this->studentId, 5);
            updateCacheTimestamp();
        }

        $this->stats = $_SESSION['stats'];
        $this->proofsByCategory = $_SESSION['proofsByCategory'];
        $this->recentAbsences = $_SESSION['recentAbsences'];
    }

    /**
     * Returns absence statistics
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Returns proofs sorted by category
     */
    public function getProofsByCategory(): array
    {
        return $this->proofsByCategory;
    }

    /**
     * Returns recent absences
     */
    public function getRecentAbsences(): array
    {
        return $this->recentAbsences;
    }

    /**
     * Calculates the justification percentage
     */
    public function getJustificationPercentage(): float
    {
        return $this->stats['total_half_days'] > 0
            ? round(($this->stats['half_days_justified'] / $this->stats['total_half_days']) * 100, 1)
            : 100;
    }

    /**
     * Calculates half-points lost (5 unjustified half-days = 0.5 point lost)
     */
    public function getHalfPointsLost(): float
    {
        $raw = (int) $this->stats['half_days_unjustified'] / 10;
        $temp = 0;
        while ($raw >= 0.5) {
            $raw -= 0.5;
            $temp += 0.5;
        }
        return $temp;
    }
}
