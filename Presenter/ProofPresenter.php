<?php
// PHP
require_once __DIR__ . '/../Model/ProofModel.php';

class ProofPresenter
{
    private $model;

    public function __construct()
    {
        $this->model = new ProofModel();
    }

    public function handleRequest($get, $post)
    {
        $data = [
            'proof' => null,
            'redirect' => null,

            // Rejet
            'showRejectForm' => false,
            'rejectionError' => '',
            'rejectionReasons' => $this->model->getRejectionReasons(),

            // Validation
            'showValidateForm' => false,
            'validationError' => '',
            'validationReasons' => $this->model->getValidationReasons(),

            // Demande d'info
            'showInfoForm' => false,
            'infoError' => ''
        ];

        if (isset($get['proof_id'])) {
            $proofId = (int) $get['proof_id'];
            $data['proof'] = $this->model->getProofDetails($proofId);

        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($post['proof_id'])) {
            $proofId = (int) $post['proof_id'];
            $data['proof'] = $this->model->getProofDetails($proofId);

            // --- REJET ---
            if (isset($post['reject']) && !isset($post['rejection_reason'])) {
                $data['showRejectForm'] = true;

            } elseif (isset($post['reject']) && isset($post['rejection_reason'])) {
                $rejectionReason = trim((string) $post['rejection_reason']);
                $rejectionDetails = trim((string) ($post['rejection_details'] ?? ''));
                $newReason = trim((string) ($post['new_rejection_reason'] ?? ''));

                if ($rejectionReason === '') {
                    $data['showRejectForm'] = true;
                    $data['rejectionError'] = "Veuillez sélectionner un motif de rejet.";
                } elseif (mb_strtolower($rejectionReason) === mb_strtolower('Autre') && $newReason === '') {
                    $data['showRejectForm'] = true;
                    $data['rejectionError'] = "Veuillez saisir un nouveau motif de rejet.";
                } else {
                    if (mb_strtolower($rejectionReason) === mb_strtolower('Autre') && $newReason !== '') {
                        $this->model->addRejectionReason($newReason);
                        $rejectionReason = $newReason;
                        // Rafraîchir la liste après ajout
                        $data['rejectionReasons'] = $this->model->getRejectionReasons();
                    }

                    $this->model->updateProofStatus($proofId, 'rejected');
                    $this->model->setRejectionReason($proofId, $rejectionReason, $rejectionDetails);
                    $this->model->updateAbsencesForProof(
                        $data['proof']['student_identifier'],
                        $data['proof']['absence_start_date'],
                        $data['proof']['absence_end_date'],
                        'rejected'
                    );

                    $data['redirect'] = 'view_proof.php?proof_id=' . $proofId;
                }

                // --- VALIDATION ---
            } elseif (isset($post['validate']) && !isset($post['validation_reason'])) {
                $data['showValidateForm'] = true;

            } elseif (isset($post['validate']) && isset($post['validation_reason'])) {
                $validationReason = trim((string) $post['validation_reason']);
                $validationDetails = trim((string) ($post['validation_details'] ?? ''));
                $newValidationReason = trim((string) ($post['new_validation_reason'] ?? ''));

                if ($validationReason === '') {
                    $data['showValidateForm'] = true;
                    $data['validationError'] = "Veuillez sélectionner un motif de validation.";
                } elseif (mb_strtolower($validationReason) === mb_strtolower('Autre') && $newValidationReason === '') {
                    $data['showValidateForm'] = true;
                    $data['validationError'] = "Veuillez saisir un nouveau motif de validation.";
                } else {
                    if (mb_strtolower($validationReason) === mb_strtolower('Autre') && $newValidationReason !== '') {
                        $this->model->addValidationReason($newValidationReason);
                        $validationReason = $newValidationReason;
                        // Rafraîchir la liste après ajout
                        $data['validationReasons'] = $this->model->getValidationReasons();
                    }

                    $this->model->updateProofStatus($proofId, 'accepted');

                    // Nécessite une méthode dans le modèle:
                    // public function setValidationReason(int $proofId, string $reason, string $comment = '', int $userId = null): bool
                    $this->model->setValidationReason($proofId, $validationReason, $validationDetails);

                    $this->model->updateAbsencesForProof(
                        $data['proof']['student_identifier'],
                        $data['proof']['absence_start_date'],
                        $data['proof']['absence_end_date'],
                        'accepted'
                    );

                    $data['redirect'] = 'view_proof.php?proof_id=' . $proofId;
                }

                // --- DEMANDE D'INFO ---
            } elseif (isset($post['request_info']) && !isset($post['info_message'])) {
                $data['showInfoForm'] = true;
                $data['infoError'] = '';

            } elseif (isset($post['request_info']) && isset($post['info_message'])) {
                $infoMessage = trim((string) $post['info_message']);
                if ($infoMessage === '') {
                    $data['showInfoForm'] = true;
                    $data['infoError'] = "Veuillez saisir un message.";
                } else {
                    // À implémenter si besoin: enregistrement du message
                    $data['redirect'] = 'view_proof.php?proof_id=' . $proofId;
                }
            }
        }

        return $data;
    }
}
