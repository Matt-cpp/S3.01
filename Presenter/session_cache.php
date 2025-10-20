<?php

// Efface les données mises en cache pour un étudiant
function clearStudentCache() {
    unset($_SESSION['stats']);
    unset($_SESSION['proofsByCategory']);
    unset($_SESSION['recentAbsences']);
    unset($_SESSION['Absences']);
    unset($_SESSION['CourseTypes']);
    unset($_SESSION['Proofs']);
}

// Vérifie si le cache doit être rafraîchi
function shouldRefreshCache($cache_duration = 1800) {
    if (!isset($_SESSION['cache_timestamp'])) {
        return true;
    }
    return (time() - $_SESSION['cache_timestamp']) > $cache_duration;
}

function updateCacheTimestamp() {
    $_SESSION['cache_timestamp'] = time();
}
