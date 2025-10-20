<?php
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
            'infoError' => '',
            'is_locked' => false,
            'lock_status' => 'Déverrouillé'
        ];

        if (isset($get['proof_id'])) {
            $proofId = (int)$get['proof_id'];
            $data['proof'] = $this->model->getProofDetails($proofId);
            if ($data['proof']) {
                $data['is_locked'] = $this->model->isLocked($proofId);
                $data['lock_status'] = $data['is_locked'] ? 'Verrouillé' : 'Déverrouillé';
            }
            return $data;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($post['proof_id'])) {
            $proofId = (int)$post['proof_id'];
            $data['proof'] = $this->model->getProofDetails($proofId);
            if (!$data['proof']) {
                $data['rejectionError'] = "Justificatif introuvable.";
                $data['validationError'] = "Justificatif introuvable.";
                return $data;
            }

            // injecter état de verrouillage pour la vue
            $data['is_locked'] = $this->model->isLocked($proofId);
            $data['lock_status'] = $data['is_locked'] ? 'Verrouillé' : 'Déverrouillé';

            // --- TOGGLE LOCK depuis le bouton de la vue ---
            if (isset($post['toggle_lock']) && isset($post['lock_action'])) {
                $action = (string)$post['lock_action'];
                if ($action === 'lock') {
                    $this->model->verrouiller($proofId);
                } elseif ($action === 'unlock') {
                    $this->model->deverouiller($proofId);
                }
                $data['redirect'] = 'view_proof.php?proof_id=' . $proofId;
                return $data;
            }

            // --- REJET ---
            if (isset($post['reject']) && !isset($post['rejection_reason'])) {
                $data['showRejectForm'] = true;

            } elseif (isset($post['reject']) && isset($post['rejection_reason'])) {
                $rejectionReason  = trim((string)$post['rejection_reason']);
                $rejectionDetails = trim((string)($post['rejection_details'] ?? ''));
                $newReason        = trim((string)($post['new_rejection_reason'] ?? ''));

                if ($rejectionReason === '') {
                    $data['showRejectForm'] = true;
                    $data['rejectionError'] = "Veuillez sélectionner un motif de rejet.";
                } elseif (mb_strtolower($rejectionReason, 'UTF-8') === mb_strtolower('Autre', 'UTF-8') && $newReason === '') {
                    $data['showRejectForm'] = true;
                    $data['rejectionError'] = "Veuillez saisir un nouveau motif de rejet.";
                } else {
                    if (mb_strtolower($rejectionReason, 'UTF-8') === mb_strtolower('Autre', 'UTF-8') && $newReason !== '') {
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
                $validationReason      = trim((string)$post['validation_reason']);
                $validationDetails     = trim((string)($post['validation_details'] ?? ''));
                $newValidationReason   = trim((string)($post['new_validation_reason'] ?? ''));

                if ($validationReason === '') {
                    $data['showValidateForm'] = true;
                    $data['validationError'] = "Veuillez sélectionner un motif de validation.";
                } elseif (mb_strtolower($validationReason, 'UTF-8') === mb_strtolower('Autre', 'UTF-8') && $newValidationReason === '') {
                    $data['showValidateForm'] = true;
                    $data['validationError'] = "Veuillez saisir un nouveau motif de validation.";
                } else {
                    if (mb_strtolower($validationReason, 'UTF-8') === mb_strtolower('Autre', 'UTF-8') && $newValidationReason !== '') {
                        $this->model->addValidationReason($newValidationReason);
                        $validationReason = $newValidationReason;
                        // Rafraîchir la liste après ajout
                        $data['validationReasons'] = $this->model->getValidationReasons();
                    }

                    $this->model->updateProofStatus($proofId, 'accepted');
                    $this->model->setValidationReason($proofId, $validationReason, $validationDetails);
                    $this->model->updateAbsencesForProof(
                        $data['proof']['student_identifier'],
                        $data['proof']['absence_start_date'],
                        $data['proof']['absence_end_date'],
                        'accepted'
                    );

                    // Appliquer action de verrouillage après validation si demandée
                    if (isset($post['lock_action_after_validate'])) {
                        $after = (string)$post['lock_action_after_validate'];
                        if ($after === 'lock') {
                            $this->model->verrouiller($proofId);
                        } elseif ($after === 'unlock') {
                            $this->model->deverouiller($proofId);
                        }
                    }

                    $data['redirect'] = 'view_proof.php?proof_id=' . $proofId;
                }

                // --- DEMANDE D'INFO ---
            } elseif (isset($post['request_info']) && !isset($post['info_message'])) {
                $data['showInfoForm'] = true;
                $data['infoError'] = '';

            } elseif (isset($post['request_info']) && isset($post['info_message'])) {
                $infoMessage = trim((string)$post['info_message']);
                if ($infoMessage === '') {
                    $data['showInfoForm'] = true;
                    $data['infoError'] = "Veuillez saisir un message.";
                } else {
                    // À implémenter si besoin : enregistrement du message
                    $data['redirect'] = 'view_proof.php?proof_id=' . $proofId;
                }
            }
        }

        return $data;
    }
}
