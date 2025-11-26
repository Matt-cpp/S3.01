<?php

/**
 * Fichier: student_absences_presenter.php
 * 
 * Présentateur des absences étudiant - Gère l'affichage des absences pour un étudiant spécifique.
 * Fournit des méthodes pour:
 * - Filtrer les absences (dates, statut, type de cours)
 * - Récupérer les absences avec leurs justificatifs
 * - Formater les données pour l'affichage (statuts, motifs, dates)
 * - Gérer la priorité des statuts de justificatifs
 * Utilisé par la page "Mes absences" de l'étudiant.
 */

require_once __DIR__ . '/../Model/database.php';

class StudentAbsencesPresenter
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
            'course_type' => $_POST['courseTypeFilter'] ?? ''
        ];
    }

    function getStudentIdentifier($student_id_or_identifier)
    {
        if (!is_numeric($student_id_or_identifier)) {
            return $student_id_or_identifier;
        }

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT identifier, first_name, last_name FROM users WHERE id = :id");
        $stmt->execute(['id' => $student_id_or_identifier]);
        $result = $stmt->fetch();

        if ($result) {
            $_SESSION['first_name'] = $result['first_name'];
            $_SESSION['last_name'] = $result['last_name'];
            return $result['identifier'];
        }

        throw new Exception("Student not found");
    }

    public function getAbsences()
    {
        $db = Database::getInstance()->getConnection();

        // Requête avec priorité des statuts : accepted > under_review > pending > rejected > null
        $query = "
            SELECT DISTINCT ON (a.id)
                a.id as absence_id,
                cs.course_date,
                cs.start_time,
                cs.end_time,
                cs.duration_minutes,
                cs.course_type,
                cs.is_evaluation,
                a.justified,
                r.code as course_code,
                r.label as course_name,
                t.first_name as teacher_first_name,
                t.last_name as teacher_last_name,
                rm.code as room_name,
                p.main_reason as motif,
                p.custom_reason as custom_motif,
                p.file_path as file_path,
                p.status as proof_status,
                CASE 
                    WHEN p.status = 'accepted' THEN 1
                    WHEN p.status = 'under_review' THEN 2
                    WHEN p.status = 'pending' THEN 3
                    WHEN p.status = 'rejected' THEN 4
                    ELSE 5
                END as status_priority
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            LEFT JOIN resources r ON cs.resource_id = r.id
            LEFT JOIN teachers t ON cs.teacher_id = t.id
            LEFT JOIN rooms rm ON cs.room_id = rm.id
            LEFT JOIN proof_absences pa ON a.id = pa.absence_id
            LEFT JOIN proof p ON pa.proof_id = p.id
            WHERE a.student_identifier = :student_id
        ";

        $params = [':student_id' => $this->studentIdentifier];

        if (!empty($this->filters['start_date'])) {
            $query .= " AND cs.course_date >= :start_date";
            $params[':start_date'] = $this->filters['start_date'];
        }

        if (!empty($this->filters['end_date'])) {
            $query .= " AND cs.course_date <= :end_date";
            $params[':end_date'] = $this->filters['end_date'];
        }

        if (!empty($this->filters['status'])) {
            if ($this->filters['status'] === 'justifiée') {
                $query .= " AND p.status = 'accepted'";
            } elseif ($this->filters['status'] === 'en_attente') {
                $query .= " AND p.status = 'pending'";
            } elseif ($this->filters['status'] === 'en_revision') {
                $query .= " AND p.status = 'under_review'";
            } elseif ($this->filters['status'] === 'refusé') {
                $query .= " AND p.status = 'rejected'";
            } elseif ($this->filters['status'] === 'non_justifiée') {
                $query .= " AND (p.id IS NULL OR p.status IS NULL)";
            }
        }

        if (!empty($this->filters['course_type'])) {
            $query .= " AND cs.course_type = :course_type";
            $params[':course_type'] = $this->filters['course_type'];
        }

        // Ordre : d'abord par absence_id puis par priorité de statut pour éliminer les doublons
        // Ensuite, on trie par date décroissante (plus récent en premier)
        $query .= " ORDER BY a.id, status_priority ASC";

        try {
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Trier les résultats par date et heure décroissantes (plus récent en premier)
            usort($results, function ($a, $b) {
                $dateCompare = strtotime($b['course_date']) - strtotime($a['course_date']);
                if ($dateCompare !== 0) {
                    return $dateCompare;
                }
                // Si même date, trier par heure de début décroissante (14h avant 8h)
                return strcmp($b['start_time'], $a['start_time']);
            });

            return $results;
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des absences: " . $e->getMessage());
            return [];
        }
    }

    public function getCourseTypes()
    {
        // Retourner tous les types de cours standards
        return [
            ['course_type' => 'CM'],
            ['course_type' => 'TD'],
            ['course_type' => 'TP']
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

    public function translateMotif($motif, $customMotif = null)
    {
        if (!$motif) {
            return '';
        }

        $translations = [
            'illness' => 'Maladie',
            'death' => 'Décès',
            'family_obligations' => 'Obligations familiales',
            'medical_appointment' => 'Rendez-vous médical',
            'transport_issue' => 'Problème de transport',
            'personal_reasons' => 'Raisons personnelles',
            'other' => $customMotif ? htmlspecialchars($customMotif) : 'Autre'
        ];

        return isset($translations[$motif]) ? $translations[$motif] : htmlspecialchars($motif);
    }

    public function translateStatus($justified)
    {
        return $justified ? 'Justifiée' : 'Non justifiée';
    }

    public function hasProof($absence)
    {
        // Un justificatif est visible seulement s'il est accepté et qu'il a un fichier
        return !empty($absence['proof_status']) &&
            $absence['proof_status'] === 'accepted' &&
            !empty($absence['file_path']);
    }

    public function getProofStatus($absence)
    {
        $proofStatus = $absence['proof_status'] ?? null;

        if ($proofStatus === 'accepted') {
            return ['text' => 'Justifiée', 'class' => 'badge-success', 'icon' => '✅'];
        } elseif ($proofStatus === 'under_review') {
            return ['text' => 'En révision', 'class' => 'badge-warning', 'icon' => '⚠️'];
        } elseif ($proofStatus === 'pending') {
            return ['text' => 'En attente', 'class' => 'badge-info', 'icon' => '🕐'];
        } elseif ($proofStatus === 'rejected') {
            // Justificatif soumis mais rejeté
            return ['text' => 'Rejeté', 'class' => 'badge-rejected', 'icon' => '🚫'];
        } else {
            // Pas de justificatif soumis
            return ['text' => 'Non justifiée', 'class' => 'badge-danger', 'icon' => '❌'];
        }
    }

    public function getProofPath($absence)
    {
        if ($this->hasProof($absence) && isset($absence['file_path'])) {
            return '../../' . ($absence['file_path'] ?? '');
        }
        return '';
    }

    public function formatDate($date)
    {
        return date('d/m/Y', strtotime($date));
    }

    public function formatTime($startTime, $endTime)
    {
        return substr($startTime, 0, 5) . ' - ' . substr($endTime, 0, 5);
    }
}
