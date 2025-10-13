<?php

require_once __DIR__ . '/../Model/AbsenceModel.php';

class HistoriquePresenter
{
    private $absenceModel;
    private $filters;
    private $errorMessage;

    public function __construct()
    {
        $this->absenceModel = new AbsenceModel();
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
            'name' => $_POST['nameFilter'] ?? '',
            'start_date' => $_POST['firstDateFilter'] ?? '',
            'end_date' => $_POST['lastDateFilter'] ?? '',
            'JustificationStatus' => $_POST['JustificationStatusFilter'] ?? '',
            'course_type' => $_POST['courseTypeFilter'] ?? ''
        ];
    }


    public function getAbsences(): array
    {
        return $this->absenceModel->getAllAbsences($this->filters);
    }


    public function getCourseTypes()
    {
        return $this->absenceModel->getCourseTypes();
    }


    public function getUserName()
    {
        return $this->absenceModel->getUserName();
    }


    public function getFilters()
    {
        return $this->filters;
    }

    public function getErrorMessage()
    {
        return $this->errorMessage;
    }


    public function translateMotif($motif)
    {
        $translations = [
            'illness' => 'Maladie',
            'death' => 'Décès',
            'family_obligations' => 'Famille',
            'medical' => 'Médical',
            'transport' => 'Transport',
            'personal' => 'Personnel',
            'other' => 'Autre'
        ];

        return isset($translations[$motif]) ? $translations[$motif] : ($motif ?: '');
    }


    public function translateStatus($justified)
    {
        return $justified ? 'Justifiée' : 'Non justifiée';
    }

    public function hasProof($absence)
    {
        return !empty($absence['motif']);
    }


    public function getProofPath($absence)
    {
        if ($this->hasProof($absence) && isset($absence['file_path'])) {
            return '../../' . ($absence['file_path'] ?? '');
        }
        return '';
    }

    public function getStatus($absence)
    {
        if (!empty($absence['justification_status'])) {
            $statusTranslations = [
                'pending' => 'En attente',
                'accepted' => 'Acceptée',
                'rejected' => 'Rejetée',
                'under_review' => 'En cours d\'examen'
            ];
            return $statusTranslations[$absence['justification_status']] ?? 'Inconnu';
        }
        return $absence['status'] ? 'Justifiée' : 'Non justifiée';
    }

    public function formatDate($date)
    {
        return date('d/m/Y', strtotime($date));
    }

        public function formatTime($startTime, $endTime)
        {
            $start = date('H:i', strtotime($startTime));
            $end = date('H:i', strtotime($endTime));
            return $start . '-' . $end;
        }
}

$presenter = new HistoriquePresenter();