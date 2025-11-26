<?php
// Protection de la page avec authentification simple
require_once __DIR__ . '/../controllers/auth_guard.php';
$user = requireAuth();

require_once __DIR__ . '/../Model/ProofModel.php';

class HistoriqueProofPresenter
{
    private $proofModel;
    private $filters;
    private $errorMessage;

    public function __construct()
    {
        $this->proofModel = new ProofModel();
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
            'status' => $_POST['statusFilter'] ?? '',
            'reason' => $_POST['reasonFilter'] ?? ''
        ];
    }

    public function getProofs(): array
    {
        return $this->proofModel->getAllProofs($this->filters);
    }

    public function getProofReasons(): array
    {
        return $this->proofModel->getProofReasons();
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    public function translateStatus($status): string
    {
        return $this->proofModel->translate('status', $status);
    }

    public function translateReason($reason): string
    {
        return $this->proofModel->translate('reason', $reason);
    }

    public function hasProof($proof): bool
    {
        if (!empty($proof['proof_files'])) {
            $files = is_array($proof['proof_files']) ? $proof['proof_files'] : json_decode($proof['proof_files'], true);
            return is_array($files) && count($files) > 0;
        }
        return !empty($proof['file_path']);
    }

    public function getProofFiles($proof): array
    {
        if (!empty($proof['proof_files'])) {
            if (is_array($proof['proof_files'])) {
                return $proof['proof_files'];
            }
            $decoded = json_decode($proof['proof_files'], true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    public function getProofPath($proof): string
    {
        if ($this->hasProof($proof)) {
            return '../../' . ($proof['file_path'] ?? '');
        }
        return '';
    }

    public function formatDate($date): string
    {
        if (empty($date))
            return '';
        return date('d/m/Y', strtotime($date));
    }

    public function getProofDetailsUrl($proofId): string
    {
        return 'view_proof.php?proof_id=' . urlencode($proofId);
    }
}

$presenter = new HistoriqueProofPresenter();
