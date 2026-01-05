<?php

/**
 * Fichier: ProofPresenter.php
 * 
 * Présentateur de justificatif - Gère la logique métier pour l'affichage et le traitement des justificatifs.
 * Fournit des méthodes pour:
 * - Gérer les actions sur un justificatif :
 *   - Validation (acceptation du justificatif)
 *   - Rejet (avec sélection de motifs prédéfinis)
 *   - Demande d'informations complémentaires (passage en révision)
 *   - Verrouillage/déverrouillage du justificatif
 * - Préparer les données pour l'affichage (formulaires, détails)
 * - Enregistrer l'historique des décisions dans decision_history
 * - Envoyer des emails de notification aux étudiants
 * - Gérer les formulaires de rejet/validation avec motifs multiples
 * Utilisé par les responsables académiques pour traiter les justificatifs soumis.
 */

require_once __DIR__ . '/../../Model/ProofModel.php';
require_once __DIR__ . '/../../Model/email.php';

class ProofPresenter
{
    private $model;
    private $emailService;

    public function __construct()
    {
        $this->model = new ProofModel();
        $this->emailService = new EmailService();
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
            $proofId = (int) $get['proof_id'];
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
            $proofId = (int) $post['proof_id'];
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
                $action = (string) $post['lock_action'];
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
                $rejectionReason = trim((string) $post['rejection_reason']);
                $rejectionDetails = trim((string) ($post['rejection_details'] ?? ''));
                $newReason = trim((string) ($post['new_rejection_reason'] ?? ''));

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

                    // Verrouiller automatiquement le justificatif après le rejet
                    $this->model->verrouiller($proofId);

                    // Send email notification to student
                    $this->sendProofRejectionEmail($data['proof'], $rejectionReason, $rejectionDetails);

                    $data['redirect'] = 'view_proof.php?proof_id=' . $proofId;
                } else {
                    $data['showRejectForm'] = true;
                    // Récupérer le message d'erreur détaillé mis en session par le modèle (si présent)
                    if (session_status() === PHP_SESSION_NONE) {
                        @session_start();
                    }
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
                $validationReason = trim((string) ($post['validation_reason'] ?? ''));
                $validationDetails = trim((string) ($post['validation_details'] ?? ''));
                $newValidationReason = trim((string) ($post['new_validation_reason'] ?? ''));

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

                    // Verrouiller automatiquement le justificatif après la validation
                    $this->model->verrouiller($proofId);

                    // Send email notification to student
                    $this->sendProofAcceptedEmail($data['proof'], $validationReason, $validationDetails);

                    $data['redirect'] = 'view_proof.php?proof_id=' . $proofId;
                } else {
                    $data['showValidateForm'] = true;
                    if (session_status() === PHP_SESSION_NONE) {
                        @session_start();
                    }
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
                $numPeriods = (int) ($post['num_periods'] ?? 2);
                $splitReason = trim((string) ($post['split_reason'] ?? ''));

                if (empty($splitReason)) {
                    $data['showSplitForm'] = true;
                    $data['splitError'] = "La raison de la scission est obligatoire.";
                    $this->enrichViewData($data);
                    return $data;
                }

                // Collecter toutes les périodes
                $periods = [];
                for ($i = 1; $i <= $numPeriods; $i++) {
                    $startDate = trim((string) ($post["period{$i}_start_date"] ?? ''));
                    $startTime = trim((string) ($post["period{$i}_start_time"] ?? ''));
                    $endDate = trim((string) ($post["period{$i}_end_date"] ?? ''));
                    $endTime = trim((string) ($post["period{$i}_end_time"] ?? ''));

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

                // Vérification que les périodes ne se chevauchent pas et sont dans l'ordre
                for ($i = 0; $i < count($periods) - 1; $i++) {
                    if (strtotime($periods[$i]['end']) > strtotime($periods[$i + 1]['start'])) {
                        $data['showSplitForm'] = true;
                        $data['splitError'] = "Les périodes " . ($i + 1) . " et " . ($i + 2) . " se chevauchent. Chaque période doit se terminer avant le début de la suivante.";
                        $this->enrichViewData($data);
                        return $data;
                    }
                }

                // Vérification que les périodes ne coupent pas un créneau de cours en plein milieu
                $splitValidation = $this->model->validateSplitPeriods($proofId, $periods);
                if (!$splitValidation['valid']) {
                    $data['showSplitForm'] = true;
                    $data['splitError'] = $splitValidation['error'];
                    $this->enrichViewData($data);
                    return $data;
                }

                // Debug: afficher les périodes
                error_log("DEBUG splitProofMultiple - periods: " . json_encode($periods));

                // Appel au modèle pour créer les justificatifs
                $ok = $this->model->splitProofMultiple($proofId, $periods, $splitReason, $currentUserId);
                if ($ok) {
                    $data['redirect'] = 'home.php?message=split_success';
                } else {
                    $data['showSplitForm'] = true;
                    if (session_status() === PHP_SESSION_NONE) {
                        @session_start();
                    }
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
                $infoMessage = trim((string) $post['info_message']);
                if ($infoMessage === '') {
                    $data['showInfoForm'] = true;
                    $data['infoError'] = "Veuillez saisir un message.";
                    $this->enrichViewData($data);
                    return $data;
                }

                // Appel du modèle pour sauvegarder la demande d'information
                $ok = $this->model->setRequestInfo($proofId, $infoMessage, $currentUserId);
                if ($ok) {
                    // Reset absences to 'absent' and 'justified=false' when requesting info
                    $this->model->updateAbsencesForProof(
                        $data['proof']['student_identifier'],
                        $data['proof']['absence_start_date'],
                        $data['proof']['absence_end_date'],
                        'request_info'
                    );

                    // Send email notification to student
                    $this->sendProofInfoRequestEmail($data['proof'], $infoMessage);

                    $data['redirect'] = 'view_proof.php?proof_id=' . $proofId;
                } else {
                    $data['showInfoForm'] = true;
                    if (session_status() === PHP_SESSION_NONE) {
                        @session_start();
                    }
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

    /**
     * Send email notification when proof is rejected
     */
    private function sendProofRejectionEmail($proof, $reason, $details)
    {
        if (!$proof || empty($proof['first_name'])) {
            error_log("Cannot send rejection email: invalid proof data");
            return;
        }

        $studentEmail = $this->getStudentEmail($proof['student_identifier']);
        if (!$studentEmail) {
            error_log("Cannot send rejection email: no email found for student " . $proof['student_identifier']);
            return;
        }

        $firstName = htmlspecialchars($proof['first_name']);
        $lastName = htmlspecialchars($proof['last_name']);
        $formattedStart = $proof['formatted_start'] ?? $proof['absence_start_date'];
        $formattedEnd = $proof['formatted_end'] ?? $proof['absence_end_date'];
        $reasonText = htmlspecialchars($reason);
        $detailsText = htmlspecialchars($details);

        $subject = "Justificatif refusé - Action requise";
        $body = $this->generateRejectionEmailBody($firstName, $lastName, $formattedStart, $formattedEnd, $reasonText, $detailsText);

        $result = $this->emailService->sendEmail($studentEmail, $subject, $body, true);

        if (!$result['success']) {
            error_log("Failed to send rejection email to {$studentEmail}: " . $result['message']);
        }
    }

    /**
     * Send email notification when proof is accepted
     */
    private function sendProofAcceptedEmail($proof, $reason, $details)
    {
        if (!$proof || empty($proof['first_name'])) {
            error_log("Cannot send acceptance email: invalid proof data");
            return;
        }

        $studentEmail = $this->getStudentEmail($proof['student_identifier']);
        if (!$studentEmail) {
            error_log("Cannot send acceptance email: no email found for student " . $proof['student_identifier']);
            return;
        }

        $firstName = htmlspecialchars($proof['first_name']);
        $lastName = htmlspecialchars($proof['last_name']);
        $formattedStart = $proof['formatted_start'] ?? $proof['absence_start_date'];
        $formattedEnd = $proof['formatted_end'] ?? $proof['absence_end_date'];
        $reasonText = $reason ? htmlspecialchars($reason) : '';
        $detailsText = $details ? htmlspecialchars($details) : '';

        $subject = "Justificatif accepté";
        $body = $this->generateAcceptanceEmailBody($firstName, $lastName, $formattedStart, $formattedEnd, $reasonText, $detailsText);

        $result = $this->emailService->sendEmail($studentEmail, $subject, $body, true);

        if (!$result['success']) {
            error_log("Failed to send acceptance email to {$studentEmail}: " . $result['message']);
        }
    }

    /**
     * Send email notification when additional information is requested
     */
    private function sendProofInfoRequestEmail($proof, $message)
    {
        if (!$proof || empty($proof['first_name'])) {
            error_log("Cannot send info request email: invalid proof data");
            return;
        }

        $studentEmail = $this->getStudentEmail($proof['student_identifier']);
        if (!$studentEmail) {
            error_log("Cannot send info request email: no email found for student " . $proof['student_identifier']);
            return;
        }

        $firstName = htmlspecialchars($proof['first_name']);
        $lastName = htmlspecialchars($proof['last_name']);
        $formattedStart = $proof['formatted_start'] ?? $proof['absence_start_date'];
        $formattedEnd = $proof['formatted_end'] ?? $proof['absence_end_date'];
        $requestMessage = htmlspecialchars($message);

        $subject = "Informations complémentaires requises pour votre justificatif";
        $body = $this->generateInfoRequestEmailBody($firstName, $lastName, $formattedStart, $formattedEnd, $requestMessage);

        $result = $this->emailService->sendEmail($studentEmail, $subject, $body, true);

        if (!$result['success']) {
            error_log("Failed to send info request email to {$studentEmail}: " . $result['message']);
        }
    }

    /**
     * Get student email from database
     */
    private function getStudentEmail($studentIdentifier)
    {
        require_once __DIR__ . '/../../Model/database.php';
        $db = getDatabase();

        try {
            $result = $db->selectOne(
                "SELECT email FROM users WHERE LOWER(identifier) = LOWER(:identifier)",
                ['identifier' => $studentIdentifier]
            );
            return $result ? $result['email'] : null;
        } catch (Exception $e) {
            error_log("Error fetching student email: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate HTML email body for rejection notification
     */
    private function generateRejectionEmailBody($firstName, $lastName, $start, $end, $reason, $details)
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #d32f2f; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-top: none; }
        .footer { background-color: #333; color: white; padding: 15px; text-align: center; font-size: 0.9em; border-radius: 0 0 5px 5px; }
        .info-box { background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
        .button { display: inline-block; padding: 12px 24px; background-color: #1976d2; color: white; text-decoration: none; border-radius: 5px; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Justificatif Refusé</h1>
        </div>
        <div class="content">
            <p>Bonjour {$firstName} {$lastName},</p>
            
            <p>Votre justificatif d'absence pour la période du <strong>{$start}</strong> au <strong>{$end}</strong> a été <strong style="color: #d32f2f;">refusé</strong>.</p>
            
            <div class="info-box">
                <strong>Motif du refus :</strong> {$reason}
HTML;
        if ($details) {
            $body .= "<br><br><strong>Détails :</strong> {$details}";
        }
        $body .= <<<HTML
            </div>
            
            <p>Veuillez soumettre un nouveau justificatif avec les corrections nécessaires dans les plus brefs délais.</p>
            
            <p>Si vous avez des questions concernant cette décision, n'hésitez pas à contacter le service de scolarité.</p>
        </div>
        <div class="footer">
            <p>Gestion des Absences - UPHF</p>
            <p>Cet email est envoyé automatiquement, merci de ne pas y répondre.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Generate HTML email body for acceptance notification
     */
    private function generateAcceptanceEmailBody($firstName, $lastName, $start, $end, $reason, $details)
    {
        $reasonSection = $reason ? "<p><strong>Motif :</strong> {$reason}</p>" : "";
        $detailsSection = $details ? "<p><strong>Détails :</strong> {$details}</p>" : "";

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #4caf50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-top: none; }
        .footer { background-color: #333; color: white; padding: 15px; text-align: center; font-size: 0.9em; border-radius: 0 0 5px 5px; }
        .success-box { background-color: #d4edda; border-left: 4px solid #4caf50; padding: 15px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Justificatif Accepté</h1>
        </div>
        <div class="content">
            <p>Bonjour {$firstName} {$lastName},</p>
            
            <p>Votre justificatif d'absence pour la période du <strong>{$start}</strong> au <strong>{$end}</strong> a été <strong style="color: #4caf50;">accepté</strong>.</p>
            
            <div class="success-box">
                <p>✓ Vos absences ont été justifiées avec succès.</p>
                {$reasonSection}
                {$detailsSection}
            </div>
            
            <p>Votre dossier a été mis à jour. Aucune action supplémentaire n'est requise de votre part.</p>
        </div>
        <div class="footer">
            <p>Gestion des Absences - UPHF</p>
            <p>Cet email est envoyé automatiquement, merci de ne pas y répondre.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Generate HTML email body for information request
     */
    private function generateInfoRequestEmailBody($firstName, $lastName, $start, $end, $message)
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #ff9800; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-top: none; }
        .footer { background-color: #333; color: white; padding: 15px; text-align: center; font-size: 0.9em; border-radius: 0 0 5px 5px; }
        .warning-box { background-color: #fff3cd; border-left: 4px solid #ff9800; padding: 15px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Informations Complémentaires Requises</h1>
        </div>
        <div class="content">
            <p>Bonjour {$firstName} {$lastName},</p>
            
            <p>Votre justificatif d'absence pour la période du <strong>{$start}</strong> au <strong>{$end}</strong> est en cours d'examen.</p>
            
            <div class="warning-box">
                <strong>Message du responsable pédagogique :</strong>
                <p style="margin-top: 10px; white-space: pre-wrap;">{$message}</p>
            </div>
            
            <p>Merci de fournir les informations demandées dans les plus brefs délais en soumettant un nouveau justificatif ou en contactant le service de scolarité.</p>
        </div>
        <div class="footer">
            <p>Gestion des Absences - UPHF</p>
            <p>Cet email est envoyé automatiquement, merci de ne pas y répondre.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
