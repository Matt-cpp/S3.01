<?php
/**
 * Fichier: dashboard_presenter.php
 * 
 * Présentateur du tableau de bord étudiant - Gère la récupération et le calcul des données
 * pour la page d'accueil de l'étudiant.
 * Fournit des méthodes pour :
 * - Récupérer les statistiques d'absences (avec cache de session)
 * - Récupérer les justificatifs par catégorie
 * - Récupérer les absences récentes
 * - Calculer le pourcentage de justification
 * - Calculer les demi-points perdus
 */

require_once __DIR__ . '/../shared/session_cache.php';
require_once __DIR__ . '/get_info.php';

class StudentDashboardPresenter
{
    private $studentId;
    private $stats;
    private $proofsByCategory;
    private $recentAbsences;

    public function __construct($studentId, $forceRefresh = false)
    {
        $this->studentId = $studentId;
        $this->loadData($forceRefresh);
    }

    /**
     * Charge les données depuis le cache ou la BDD
     */
    private function loadData($forceRefresh)
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
     * Retourne les statistiques d'absences
     */
    public function getStats()
    {
        return $this->stats;
    }

    /**
     * Retourne les justificatifs classés par catégorie
     */
    public function getProofsByCategory()
    {
        return $this->proofsByCategory;
    }

    /**
     * Retourne les absences récentes
     */
    public function getRecentAbsences()
    {
        return $this->recentAbsences;
    }

    /**
     * Calcule le pourcentage de justification
     */
    public function getJustificationPercentage()
    {
        return $this->stats['total_half_days'] > 0
            ? round(($this->stats['half_days_justified'] / $this->stats['total_half_days']) * 100, 1)
            : 100;
    }

    /**
     * Calcule les demi-points perdus (5 demi-journées non justifiées = 0,5 point perdu)
     */
    public function getHalfPointsLost()
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
