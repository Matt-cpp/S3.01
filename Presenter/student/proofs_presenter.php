<?php

/**
 * Fichier: proofs_presenter.php
 * 
 * Présentateur des justificatifs étudiant - Gère l'affichage de la liste des justificatifs d'un étudiant.
 * Fournit des méthodes pour:
 * - Filtrer les justificatifs (dates d'absence, statut, motif, présence d'évaluation)
 * - Récupérer les justificatifs avec statistiques agrégées :
 *   - Nombre d'absences associées
 *   - Heures totales manquées
 *   - Détection d'évaluations ratées
 *   - Types de cours concernés (JSON)
 * - Formater les données pour l'affichage (badges de statut, dates, périodes)
 * - Traduire les motifs d'absence en français
 * - Gérer les motifs de rejet/validation depuis la base de données
 * Utilisé par la page "Mes justificatifs" de l'étudiant.
 */

require_once __DIR__ . '/../../Model/database.php';

class StudentProofsPresenter
{
    private $filters;
    private $errorMessage;
    private $studentIdentifier;

    public function __construct($studentIdentifier)
    {
        $this->studentIdentifier = $studentIdentifier;
        $this->filters = [];
        $this->errorMessage = '';
        $this->processRequest();
    }

    private function processRequest()
    {
        // Récupérer les paramètres GET pour les filtres (ex: venant de la page d'accueil)
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['status'])) {
            $this->filters['status'] = $_GET['status'] ?? '';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateAndSetFilters();
        }
    }

    private function validateAndSetFilters()
    {
        // Validation des dates
        if (!empty($_POST['firstDateFilter']) && !empty($_POST['lastDateFilter'])) {
            if ($_POST['firstDateFilter'] > $_POST['lastDateFilter']) {
                $this->errorMessage = "La première date doit être antérieure à la deuxième date.";
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

    public function getProofs()
    {
        $db = Database::getInstance()->getConnection();

        // Requête principale pour récupérer les justificatifs avec les informations de base
        $query = "
            SELECT 
                p.id as proof_id,
                p.absence_start_date,
                p.absence_end_date,
                p.main_reason,
                p.custom_reason,
                p.student_comment,
                p.submission_date,
                p.processing_date,
                p.status,
                p.manager_comment,
                p.file_path,
                p.proof_files,
                COUNT(DISTINCT pa.absence_id) as absence_count,
                SUM(cs.duration_minutes) as total_duration_minutes,
                MAX(CASE WHEN cs.is_evaluation = true THEN 1 ELSE 0 END) as has_exam,
                COUNT(DISTINCT (cs.course_date, CASE WHEN cs.start_time < '12:30:00' THEN 'morning' ELSE 'afternoon' END)) as half_days_count,
                MIN(cs.course_date || ' ' || cs.start_time) as absence_start_datetime,
                MAX(cs.course_date || ' ' || cs.end_time) as absence_end_datetime
            FROM proof p
            LEFT JOIN proof_absences pa ON p.id = pa.proof_id
            LEFT JOIN absences a ON pa.absence_id = a.id
            LEFT JOIN course_slots cs ON a.course_slot_id = cs.id
            WHERE p.student_identifier = :student_id
        ";

        $params = [':student_id' => $this->studentIdentifier];

        // Filtre par date de début d'absence
        if (!empty($this->filters['start_date'])) {
            $query .= " AND p.absence_start_date >= :start_date";
            $params[':start_date'] = $this->filters['start_date'];
        }

        // Filtre par date de fin d'absence
        if (!empty($this->filters['end_date'])) {
            $query .= " AND p.absence_end_date <= :end_date";
            $params[':end_date'] = $this->filters['end_date'];
        }

        $query .= " GROUP BY p.id";

        // Filtre par statut
        if (!empty($this->filters['status'])) {
            if ($this->filters['status'] === 'accepted') {
                $query .= " HAVING p.status = 'accepted'";
            } elseif ($this->filters['status'] === 'pending') {
                $query .= " HAVING p.status = 'pending'";
            } elseif ($this->filters['status'] === 'under_review') {
                $query .= " HAVING p.status = 'under_review'";
            } elseif ($this->filters['status'] === 'rejected') {
                $query .= " HAVING p.status = 'rejected'";
            }
        }

        // Filtre par motif
        if (!empty($this->filters['reason'])) {
            if (strpos($query, 'HAVING') !== false) {
                $query .= " AND p.main_reason = :reason";
            } else {
                $query .= " HAVING p.main_reason = :reason";
            }
            $params[':reason'] = $this->filters['reason'];
        }

        // Filtre par présence d'évaluation
        if (!empty($this->filters['has_exam'])) {
            if ($this->filters['has_exam'] === 'yes') {
                if (strpos($query, 'HAVING') !== false) {
                    $query .= " AND MAX(CASE WHEN cs.is_evaluation = true THEN 1 ELSE 0 END) = 1";
                } else {
                    $query .= " HAVING MAX(CASE WHEN cs.is_evaluation = true THEN 1 ELSE 0 END) = 1";
                }
            } elseif ($this->filters['has_exam'] === 'no') {
                if (strpos($query, 'HAVING') !== false) {
                    $query .= " AND MAX(CASE WHEN cs.is_evaluation = true THEN 1 ELSE 0 END) = 0";
                } else {
                    $query .= " HAVING MAX(CASE WHEN cs.is_evaluation = true THEN 1 ELSE 0 END) = 0";
                }
            }
        }

        // Tri : justificatifs en révision d'abord, puis par date de soumission décroissante (plus récent en premier)
        $query .= " ORDER BY 
            CASE 
                WHEN p.status = 'under_review' THEN 1
                WHEN p.status = 'pending' THEN 2
                WHEN p.status = 'rejected' THEN 3
                WHEN p.status = 'accepted' THEN 4
                ELSE 5
            END,
            p.submission_date DESC";

        try {
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculer les heures manquées et les demi-journées pour chaque justificatif
            foreach ($results as &$proof) {
                $proof['total_hours_missed'] = ($proof['total_duration_minutes'] ?? 0) / 60;
                
                // Calculer les demi-journées (>= 1h dans la période 8h-12h30 ou 12h30-18h30)
                $proof['half_days_count'] = $this->calculateHalfDaysForProof($db, $proof['proof_id']);
            }

            return $results;
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des justificatifs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Calcule le nombre de demi-journées pour un justificatif
     * Règle : 1 demi-journée comptée si >= 1 minute d'absence dans le créneau 8h-12h30 ou 12h30-18h30
     */
    private function calculateHalfDaysForProof($db, $proofId)
    {
        $query = "
            SELECT 
                cs.course_date,
                cs.start_time,
                cs.end_time,
                cs.duration_minutes
            FROM proof_absences pa
            JOIN absences a ON pa.absence_id = a.id
            JOIN course_slots cs ON a.course_slot_id = cs.id
            WHERE pa.proof_id = :proof_id
        ";

        try {
            $stmt = $db->prepare($query);
            $stmt->execute([':proof_id' => $proofId]);
            $absences = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
            error_log("Erreur lors du calcul des demi-journées pour le justificatif: " . $e->getMessage());
            return 0;
        }
    }

    public function getReasons()
    {
        // Retourner tous les motifs standards
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

    public function getFilters()
    {
        return $this->filters;
    }

    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    public function translateReason($reason, $customReason = null)
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

        return isset($translations[$reason]) ? $translations[$reason] : htmlspecialchars($reason);
    }

    public function getStatusBadge($status)
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

    public function formatDate($date)
    {
        return date('d/m/Y', strtotime($date));
    }

    public function formatDateTime($datetime)
    {
        return date('d/m/Y à H\hi', strtotime($datetime));
    }

    public function formatPeriod($startDate, $endDate)
    {
        $start = $this->formatDate($startDate);
        $end = $this->formatDate($endDate);
        return $start === $end ? $start : "$start $end";
    }
}
