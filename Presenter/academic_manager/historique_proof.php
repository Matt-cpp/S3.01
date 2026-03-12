<?php

declare(strict_types=1);

/**
 * Proof history presenter - Manages display and filtering of the full proof history.
 * Provides methods for:
 * - Filtering proofs by multiple criteria:
 *   - Search by student name
 *   - Filter by submission period
 *   - Filter by status (pending, accepted, rejected, under review)
 *   - Filter by absence reason
 * - Retrieving proofs with statistics (absence count, total hours)
 * - Translating statuses and reasons to French
 * - Checking presence of proof files
 * - Validating date consistency
 * Used by the view templates/academic_manager/historique_proof.php.
 */

// Page protection with authentication
require_once __DIR__ . '/../shared/auth_guard.php';
$user = requireAuth();

require_once __DIR__ . '/../../Model/ProofModel.php';

class ProofHistoryPresenter
{
    private ProofModel $proofModel;
    private array $filters;
    private string $errorMessage;

    public function __construct()
    {
        $this->proofModel = new ProofModel();
        $this->filters = [];
        $this->errorMessage = '';
        $this->processRequest();
    }

    private function processRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateAndSetFilters();
        }
    }

    private function validateAndSetFilters(): void
    {
        // Date validation
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

    public function translateStatus(?string $status): string
    {
        return $this->proofModel->translate('status', $status ?? '');
    }

    public function translateReason(?string $reason): string
    {
        return $this->proofModel->translate('reason', $reason ?? '');
    }

    public function hasProof(array $proof): bool
    {
        if (!empty($proof['proof_files'])) {
            $files = is_array($proof['proof_files']) ? $proof['proof_files'] : json_decode($proof['proof_files'], true);
            return is_array($files) && count($files) > 0;
        }
        return !empty($proof['file_path']);
    }

    public function getProofFiles(array $proof): array
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

    public function getProofPath(array $proof): string
    {
        if ($this->hasProof($proof)) {
            return '../../' . ($proof['file_path'] ?? '');
        }
        return '';
    }

    public function formatDate(?string $date): string
    {
        if (empty($date))
            return '';
        return date('d/m/Y', strtotime($date));
    }

    public function getProofDetailsUrl(string|int $proofId): string
    {
        return 'view_proof.php?proof_id=' . urlencode((string)$proofId);
    }
}

$presenter = new ProofHistoryPresenter();
