<?php

/**
 * Fichier: session_cache.php
 * 
 * Gestionnaire de cache de session - Gère le cache des données en session pour améliorer les performances.
 * Fournit des fonctions pour:
 * - Effacer le cache des données étudiant (stats, absences, justificatifs)
 * - Vérifier si le cache doit être rafraîci
 * - Mettre à jour le timestamp du cache
 * Permet d'éviter des requêtes BDD répétitives pour les mêmes données.
 */

// Efface les données mises en cache pour un étudiant
function clearStudentCache()
{
    unset($_SESSION['stats']);
    unset($_SESSION['proofsByCategory']);
    unset($_SESSION['recentAbsences']);
    unset($_SESSION['Absences']);
    unset($_SESSION['CourseTypes']);
    unset($_SESSION['Proofs']);
}

// Vérifie si le cache doit être rafraîchi
function shouldRefreshCache($cache_duration = 60)
{
    if (!isset($_SESSION['cache_timestamp'])) {
        return true;
    }
    return (time() - $_SESSION['cache_timestamp']) > $cache_duration;
}

function updateCacheTimestamp()
{
    $_SESSION['cache_timestamp'] = time();
}
