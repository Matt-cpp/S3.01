<?php

declare(strict_types=1);

/**
 * File: session_cache.php
 *
 * Session cache manager — handles session data caching to improve performance.
 * Provides functions to:
 * - Clear cached student data (stats, absences, proofs)
 * - Check whether the cache needs refreshing
 * - Update the cache timestamp
 * Avoids repetitive database queries for the same data.
 */

// Clears cached data for a student
function clearStudentCache(): void
{
    unset($_SESSION['stats']);
    unset($_SESSION['proofsByCategory']);
    unset($_SESSION['recentAbsences']);
    unset($_SESSION['Absences']);
    unset($_SESSION['CourseTypes']);
    unset($_SESSION['Proofs']);
}

// Vérifie si le cache doit être rafraîchi
function shouldRefreshCache($cache_duration = 90)
{
    if (!isset($_SESSION['cache_timestamp'])) {
        return true;
    }
    return (time() - $_SESSION['cache_timestamp']) > $cacheDuration;
}

function updateCacheTimestamp(): void
{
    $_SESSION['cache_timestamp'] = time();
}
