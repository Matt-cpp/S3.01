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
            'showRejectForm' => false,
            'rejectionError' => '',
            'redirect' => null,
            'showInfoForm' => false,
            'infoError' => '',
            'rejectionReasons' => $this->model->getRejectionReasons(),
            'validationReasons' => $this->model->getValidationReasons()
        ];

        if (isset($get['proof_id'])) {
            $proofId = (int)$get['proof_id'];
            $data['proof'] = $this->model->getProofDetails($proofId);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($post['proof_id'])) {
            $proofId = (int)$post['proof_id'];
            $data['proof'] = $this->model->getProofDetails($proofId);

            if (isset($post['reject']) && !isset($post['rejection_reason'])) {
                $data['showRejectForm'] = true;
            } elseif (isset($post['reject']) && isset($post['rejection_reason'])) {
                $rejectionReason = trim($post['rejection_reason']);
                $rejectionDetails = trim($post['rejection_details'] ?? '');
                $newReason = trim($post['new_rejection_reason'] ?? '');

                if ($rejectionReason === '') {
                    $data['showRejectForm'] = true;
                    $data['rejectionError'] = "Veuillez sélectionner un motif de rejet.";
                } elseif ($rejectionReason === 'Autre' && $newReason !== '') {
                    // Ajout du nouveau motif
                    $this->model->addRejectionReason($newReason);
                    $rejectionReason = $newReason;
                    $this->model->updateProofStatus($proofId, 'rejected');
                    $this->model->setRejectionReason($proofId, $rejectionReason, $rejectionDetails);
                    $this->model->updateAbsencesForProof(
                        $data['proof']['student_identifier'],
                        $data['proof']['absence_start_date'],
                        $data['proof']['absence_end_date'],
                        'rejected'
                    );
                    $data['redirect'] = 'view_proof.php?proof_id=' . $proofId;
                } elseif ($rejectionReason === 'Autre' && $newReason === '') {
                    $data['showRejectForm'] = true;
                    $data['rejectionError'] = "Veuillez saisir un nouveau motif de rejet.";
                } else {
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
                // Mise à jour de la liste des motifs au cas où un nouveau a été ajouté
                $data['rejectionReasons'] = $this->model->getRejectionReasons();
            } elseif (isset($post['validate'])) {
                $this->model->updateProofStatus($proofId, 'accepted');
                $this->model->updateAbsencesForProof(
                    $data['proof']['student_identifier'],
                    $data['proof']['absence_start_date'],
                    $data['proof']['absence_end_date'],
                    'accepted'
                );
                $data['redirect'] = 'view_proof.php?proof_id=' . $proofId;
            } elseif (isset($post['request_info']) && !isset($post['info_message'])) {
                $data['showInfoForm'] = true;
                $data['infoError'] = '';
            } elseif (isset($post['request_info']) && isset($post['info_message'])) {
                $infoMessage = trim($post['info_message']);
                if ($infoMessage === '') {
                    $data['showInfoForm'] = true;
                    $data['infoError'] = "Veuillez saisir un message.";
                } else {
                    // À implémenter : enregistrement du message dans le modèle si besoin
                    $data['redirect'] = 'view_proof.php?proof_id=' . $proofId;
                }
            }
        }
        return $data;
    }
}
