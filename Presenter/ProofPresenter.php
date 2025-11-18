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
        // S'assurer que la session est démarrée pour récupérer l'id utilisateur
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $currentUserId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? $_SESSION['id_user'] ?? null;

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

            // Verrouillage
            'is_locked' => false,
            'lock_status' => 'Déverrouillé'
        ];

        // Affichage par GET
        if (isset($get['proof_id'])) {
            $proofId = (int)$get['proof_id'];
            $data['proof'] = $this->model->getProofDetails($proofId);
            if ($data['proof']) {
                $data['is_locked'] = $this->model->isLocked($proofId);
                $data['lock_status'] = $data['is_locked'] ? 'Verrouillé' : 'Déverrouillé';
            }
            $this->enrichViewData($data);
            return $data;
        }

        // Actions POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($post['proof_id'])) {
            $proofId = (int)$post['proof_id'];
            $data['proof'] = $this->model->getProofDetails($proofId);
            if (!$data['proof']) {
                $data['rejectionError'] = "Justificatif introuvable.";
                $data['validationError'] = "Justificatif introuvable.";
                $this->enrichViewData($data);
                return $data;
            }

            // État de verrouillage pour la vue
            $data['is_locked'] = $this->model->isLocked($proofId);
            $data['lock_status'] = $data['is_locked'] ? 'Verrouillé' : 'Déverrouillé';

            // Verrouiller / Déverrouiller
            if (isset($post['toggle_lock']) && isset($post['lock_action'])) {
                $action = (string)$post['lock_action'];
                if ($action === 'lock') {
                    $this->model->verrouiller($proofId);
                } elseif ($action === 'unlock') {
                    $this->model->deverouiller($proofId);
                }
                $data['redirect'] = 'view_proof.php?proof_id=' . $proofId;
                $this->enrichViewData($data);
                return $data;
            }

            // Rejet
            if (isset($post['reject']) && !isset($post['rejection_reason'])) {
                $data['showRejectForm'] = true;
                $this->enrichViewData($data);
                return $data;
            } elseif (isset($post['reject']) && isset($post['rejection_reason'])) {
                $rejectionReason  = trim((string)$post['rejection_reason']);
                $rejectionDetails = trim((string)($post['rejection_details'] ?? ''));
                $newReason        = trim((string)($post['new_rejection_reason'] ?? ''));

                if ($rejectionReason === '') {
                    $data['showRejectForm'] = true;
                    $data['rejectionError'] = "Veuillez sélectionner un motif de rejet.";
                    $this->enrichViewData($data);
                    return $data;
                }
                if ($this->equalsIgnoreCase($rejectionReason, 'Autre') && $newReason === '') {
                    $data['showRejectForm'] = true;
                    $data['rejectionError'] = "Veuillez saisir un nouveau motif de rejet.";
                    $this->enrichViewData($data);
                    return $data;
                }
                if ($this->equalsIgnoreCase($rejectionReason, 'Autre') && $newReason !== '') {
                    $inserted = $this->model->addRejectionReason($newReason);
                    if (!$inserted) {
                        error_log("Échec insertion motif de rejet: {$newReason}");
                    }
                    $rejectionReason = $newReason;
                    $data['rejectionReasons'] = $this->model->getRejectionReasons();
                }

                // setRejectionReason met à jour le statut et insère l'historique dans la même transaction
                $ok = $this->model->setRejectionReason($proofId, $rejectionReason, $rejectionDetails, $currentUserId);
                if ($ok) {
                    $this->model->updateAbsencesForProof(
                        $data['proof']['student_identifier'],
                        $data['proof']['absence_start_date'],
                        $data['proof']['absence_end_date'],
                        'rejected'
                    );
                    $data['redirect'] = 'view_proof.php?proof_id=' . $proofId;
                } else {
                    $data['showRejectForm'] = true;
                    // Récupérer le message d'erreur détaillé mis en session par le modèle (si présent)
                    if (session_status() === PHP_SESSION_NONE) {@session_start();}
                    $err = $_SESSION['last_model_error'] ?? null;
                    if ($err) {
                        $data['rejectionError'] = 'Impossible d\'enregistrer la décision : ' . $err;
                        unset($_SESSION['last_model_error']);
                    } else {
                        $data['rejectionError'] = 'Impossible d\'enregistrer la décision, voir les logs.';
                    }
                }
                $this->enrichViewData($data);
                return $data;
            }

            // Validation (motif optionnel)
            if (isset($post['validate']) && !isset($post['validation_reason'])) {
                // Clic initial sur le bouton "Valider" : afficher le formulaire de validation
                $data['showValidateForm'] = true;
                $this->enrichViewData($data);
                return $data;
            } elseif (isset($post['validate']) && isset($post['validation_reason'])) {
                // Soumission du formulaire de validation (le motif est optionnel)
                $validationReason    = trim((string)($post['validation_reason'] ?? ''));
                $validationDetails   = trim((string)($post['validation_details'] ?? ''));
                $newValidationReason = trim((string)($post['new_validation_reason'] ?? ''));

                if ($this->equalsIgnoreCase($validationReason, 'Autre') && $newValidationReason !== '') {
                    $inserted = $this->model->addValidationReason($newValidationReason);
                    if (!$inserted) {
                        error_log("Échec insertion motif de validation: {$newValidationReason}");
                    }
                    $validationReason = $newValidationReason;
                    $data['validationReasons'] = $this->model->getValidationReasons();
                }

                // setValidationReason met à jour le statut et insère l'historique dans la même transaction
                $ok = $this->model->setValidationReason($proofId, $validationReason, $validationDetails, $currentUserId);
                if ($ok) {
                    $this->model->updateAbsencesForProof(
                        $data['proof']['student_identifier'],
                        $data['proof']['absence_start_date'],
                        $data['proof']['absence_end_date'],
                        'accepted'
                    );
                    $data['redirect'] = 'view_proof.php?proof_id=' . $proofId;
                } else {
                    $data['showValidateForm'] = true;
                    if (session_status() === PHP_SESSION_NONE) {@session_start();}
                    $err = $_SESSION['last_model_error'] ?? null;
                    if ($err) {
                        $data['validationError'] = 'Impossible d\'enregistrer la décision : ' . $err;
                        unset($_SESSION['last_model_error']);
                    } else {
                        $data['validationError'] = 'Impossible d\'enregistrer la décision, voir les logs.';
                    }
                }
                $this->enrichViewData($data);
                return $data;
            }

            // Demande d'info
            if (isset($post['request_info']) && !isset($post['info_message'])) {
                $data['showInfoForm'] = true;
                $data['infoError'] = '';
                $this->enrichViewData($data);
                return $data;
            } elseif (isset($post['request_info']) && isset($post['info_message'])) {
                $infoMessage = trim((string)$post['info_message']);
                if ($infoMessage === '') {
                    $data['showInfoForm'] = true;
                    $data['infoError'] = "Veuillez saisir un message.";
                    $this->enrichViewData($data);
                    return $data;
                }

                // Appel du modèle pour sauvegarder la demande d'information
                $ok = $this->model->setRequestInfo($proofId, $infoMessage, $currentUserId);
                if ($ok) {
                    $data['redirect'] = 'view_proof.php?proof_id=' . $proofId;
                } else {
                    $data['showInfoForm'] = true;
                    if (session_status() === PHP_SESSION_NONE) {@session_start();}
                    $err = $_SESSION['last_model_error'] ?? null;
                    if ($err) {
                        $data['infoError'] = 'Impossible d\'enregistrer la demande d\'information : ' . $err;
                        unset($_SESSION['last_model_error']);
                    } else {
                        $data['infoError'] = 'Impossible d\'enregistrer la demande d\'information, voir les logs.';
                    }
                }
                $this->enrichViewData($data);
                return $data;
            }
        }

        $this->enrichViewData($data);
        return $data;
    }

    private function enrichViewData(&$data)
    {
        $rejectionReasons = $data['rejectionReasons'] ?? $this->model->getRejectionReasons();
        $data['rejectionReasonsTranslated'] = array_map(fn($r) => $this->model->translate('reason', $r), $rejectionReasons);
        $data['rejectionReasons'] = $rejectionReasons;

        $validationReasons = $data['validationReasons'] ?? $this->model->getValidationReasons();
        $data['validationReasonsTranslated'] = array_map(fn($r) => $this->model->translate('reason', $r), $validationReasons);
        $data['validationReasons'] = $validationReasons;

        if (!empty($data['proof']) && is_array($data['proof'])) {
            $p = &$data['proof'];
            $p['status_label'] = $this->model->translate('status', $p['status'] ?? '');
            $mainReason = $p['main_reason'] ?? $p['reason'] ?? $p['rejection_reason'] ?? $p['validation_reason'] ?? '';
            $p['main_reason_label'] = $this->model->translate('reason', $mainReason);
            $p['custom_reason_label'] = $p['custom_reason'] ?? '';
            $p['formatted_start'] = $this->model->formatDateFr($p['absence_start_datetime'] ?? $p['absence_start_date'] ?? null);
            $p['formatted_end'] = $this->model->formatDateFr($p['absence_end_datetime'] ?? $p['absence_end_date'] ?? null);
            $p['formatted_submission'] = $this->model->formatDateFr($p['submission_date'] ?? null);
            $p['is_locked'] = $this->model->isLocked($p['proof_id'] ?? $p['id'] ?? 0);
            $p['lock_status'] = $p['is_locked'] ? 'Verrouillé' : 'Déverrouillé';
        }
    }
    private function equalsIgnoreCase(string $a, string $b): bool
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($a, 'UTF-8') === mb_strtolower($b, 'UTF-8');
        }
        return strtolower($a) === strtolower($b);
    }
}
