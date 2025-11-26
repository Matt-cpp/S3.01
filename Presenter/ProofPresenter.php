<?php
/**
 * ProofPresenter.php
 * 
 * Presenter pour la gestion des justificatifs d'absence (architecture MVC).
 * 
 * Ce fichier fait le lien entre la vue (view_proof.php) et le modèle (ProofModel.php).
 * Il gère toute la logique métier et les interactions utilisateur :
 * 
 * Fonctionnalités principales :
 * - Affichage des détails d'un justificatif (GET)
 * - Validation de justificatifs avec motif
 * - Rejet de justificatifs avec raison
 * - Demande d'informations complémentaires
 * - Scission de justificatifs en plusieurs périodes (avec validation partielle)
 * - Verrouillage/déverrouillage de justificatifs
 * 
 * Validation des données :
 * - Vérification de la cohérence des périodes (chronologie, non-chevauchement)
 * - Validation des champs obligatoires
 * - Gestion des erreurs avec messages explicites
 * 
 * @package Presenter
 * @author Équipe de développement S3.01
 * @version 2.0
 */

require_once __DIR__ . '/../Model/ProofModel.php';

class ProofPresenter
{
    private $model;

    /**
     * Constructeur - Initialise le modèle de gestion des justificatifs
     */
    public function __construct()
    {
        $this->model = new ProofModel();
    }

    /**
     * Gère les requêtes GET et POST pour l'interface de validation
     * 
     * Cette méthode orchestre toutes les actions possibles sur un justificatif :
     * - Affichage (GET)
     * - Validation, rejet, demande d'info, scission, verrouillage (POST)
     * 
     * @param array $get Paramètres GET ($_GET)
     * @param array $post Paramètres POST ($_POST)
     * @return array Données à afficher dans la vue
     */
    public function handleRequest($get, $post)
    {
        // Récupération de l'ID utilisateur depuis la session
        // (nécessaire pour l'historique des actions)
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

            // Scission du justificatif
            if (isset($post['split']) && !isset($post['num_periods'])) {
                $data['showSplitForm'] = true;
                $data['splitError'] = '';
                $this->enrichViewData($data);
                return $data;
            } elseif (isset($post['split_proof']) && isset($post['num_periods'])) {
                // =========================================
                // TRAITEMENT DE LA SCISSION DE JUSTIFICATIF
                // =========================================
                
                $numPeriods = (int)($post['num_periods'] ?? 2);
                $splitReason = trim((string)($post['split_reason'] ?? ''));
                
                // Validation : la raison est obligatoire
                if (empty($splitReason)) {
                    $data['showSplitForm'] = true;
                    $data['splitError'] = "La raison de la scission est obligatoire.";
                    $this->enrichViewData($data);
                    return $data;
                }

                // Collecte de toutes les périodes depuis le formulaire
                // Chaque période contient : start (date+heure), end (date+heure), validate (bool)
                $periods = [];
                for ($i = 1; $i <= $numPeriods; $i++) {
                    $startDate = trim((string)($post["period{$i}_start_date"] ?? ''));
                    $startTime = trim((string)($post["period{$i}_start_time"] ?? ''));
                    $endDate = trim((string)($post["period{$i}_end_date"] ?? ''));
                    $endTime = trim((string)($post["period{$i}_end_time"] ?? ''));

                    if (empty($startDate) || empty($startTime) || empty($endDate) || empty($endTime)) {
                        $data['showSplitForm'] = true;
                        $data['splitError'] = "Tous les champs de la période {$i} sont obligatoires.";
                        $this->enrichViewData($data);
                        return $data;
                    }

                    $validate = isset($post["period{$i}_validate"]) && $post["period{$i}_validate"] == '1';
                    
                    $periods[] = [
                        'start' => $startDate . ' ' . $startTime,
                        'end' => $endDate . ' ' . $endTime,
                        'validate' => $validate
                    ];
                }

                // Validation : vérifier que les périodes sont chronologiques et ne se chevauchent pas
                // Exemple valide : Période 1 (Lun 8h-12h), Période 2 (Lun 14h-18h)
                // Exemple invalide : Période 1 (Lun 8h-14h), Période 2 (Lun 12h-16h) <- chevauchement
                for ($i = 0; $i < count($periods) - 1; $i++) {
                    if (strtotime($periods[$i]['end']) >= strtotime($periods[$i + 1]['start'])) {
                        $data['showSplitForm'] = true;
                        $data['splitError'] = "Les périodes " . ($i + 1) . " et " . ($i + 2) . " se chevauchent. Chaque période doit se terminer avant le début de la suivante.";
                        $this->enrichViewData($data);
                        return $data;
                    }
                }

                // Appel au modèle pour créer les justificatifs
                $ok = $this->model->splitProofMultiple($proofId, $periods, $splitReason, $currentUserId);
                if ($ok) {
                    $data['redirect'] = 'choose_proof.php?message=split_success';
                } else {
                    $data['showSplitForm'] = true;
                    if (session_status() === PHP_SESSION_NONE) {@session_start();}
                    $err = $_SESSION['last_model_error'] ?? null;
                    if ($err) {
                        $data['splitError'] = 'Impossible de scinder le justificatif : ' . $err;
                        unset($_SESSION['last_model_error']);
                    } else {
                        $data['splitError'] = 'Impossible de scinder le justificatif, voir les logs.';
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
