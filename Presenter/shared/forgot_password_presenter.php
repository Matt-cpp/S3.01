<?php

declare(strict_types=1);

/**
 * File: forgot_password_presenter.php
 *
 * Password reset controller — handles account recovery.
 * 3-step process:
 * 1. Sends a 6-digit verification code by email
 * 2. Verifies the code (expires after 15 minutes)
 * 3. Resets the password
 * Sends HTML emails with the university logo.
 */

session_start();
require_once __DIR__ . '/../../Model/UserModel.php';
require_once __DIR__ . '/../../Model/EmailVerificationModel.php';
require_once __DIR__ . '/../../Model/email.php';

class ForgotPasswordController
{
    private UserModel $userModel;
    private EmailVerificationModel $verificationModel;
    private EmailService $emailService;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->verificationModel = new EmailVerificationModel();
        $this->emailService = new EmailService();
    }

    /**
     * Sends a verification code to the specified email for password reset
     */
    public function sendResetCode(string $email): array
    {
        try {
            $email = strtolower(trim($email));
            $user = $this->userModel->getUserByEmail($email);

            // For security, always return a success message
            // even if the email doesn't exist, to avoid revealing account existence
            if (!$user) {
                // Simulate a delay to prevent timing attacks
                usleep(random_int(100000, 300000)); // 0.1 to 0.3 seconds
                return ['success' => true, 'message' => 'Un email a été envoyé si le compte existe.'];
            }

            // Generate a 6-digit verification code
            $verificationCode = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            $this->verificationModel->createVerification($email, $verificationCode, $expiresAt);

            // Send the email
            $subject = 'Réinitialisation de mot de passe - Gestion Absence UPHF';
            $body = $this->getResetEmailTemplate($verificationCode, $user['first_name'], $user['last_name']);

            // Images for the email
            $images = [
                'logoIUT' => __DIR__ . '/../../View/img/logoIUT.png'
            ];

            $result = $this->emailService->sendEmail($email, $subject, $body, true, [], $images);

            if ($result['success']) {
                return ['success' => true, 'message' => 'Un email a été envoyé si le compte existe.'];
            } else {
                // Do not reveal the sending error for security reasons
                error_log('Email sending failed: ' . $result['message']);
                return ['success' => true, 'message' => 'Un email a été envoyé si le compte existe.'];
            }
        } catch (Exception $e) {
            error_log('Error in sendResetCode: ' . $e->getMessage());
            // For security, always return success even on error
            return ['success' => true, 'message' => 'Un email a été envoyé si le compte existe.'];
        }
    }

    /**
     * Verifies the reset code
     */
    public function verifyResetCode(string $email, string $code): array
    {
        try {
            if ($this->verificationModel->getValidVerification($email, $code)) {
                $this->verificationModel->markVerified($email, $code);
                return ['success' => true, 'message' => 'Code vérifié avec succès.'];
            } else {
                return ['success' => false, 'message' => 'Code invalide ou expiré.'];
            }
        } catch (Exception $e) {
            error_log('Error in verifyResetCode: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur lors de la vérification.'];
        }
    }

    /**
     * Resets the password after code verification
     */
    public function resetPassword(string $email, string $newPassword, string $confirmPassword): array
    {
        try {
            // Check that passwords match
            if ($newPassword !== $confirmPassword) {
                return ['success' => false, 'message' => 'Les mots de passe ne correspondent pas.'];
            }

            // Check password length
            if (strlen($newPassword) < 8) {
                return ['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractères.'];
            }

            if (!$this->verificationModel->getVerifiedAndActive($email)) {
                return ['success' => false, 'message' => 'Code non vérifié ou expiré. Veuillez recommencer.'];
            }

            // Hash the new password
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $this->userModel->updatePasswordByEmail($email, $passwordHash);
            $this->verificationModel->deleteVerifications($email);
            return ['success' => true, 'message' => 'Mot de passe réinitialisé avec succès !'];
        } catch (Exception $e) {
            error_log('Error in resetPassword: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur lors de la réinitialisation: ' . $e->getMessage()];
        }
    }

    /**
     * Email template for password reset
     */
    private function getResetEmailTemplate(string $code, string $firstName, string $lastName): string
    {
        $name = trim($firstName . ' ' . $lastName);
        return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='text-align: center; margin-bottom: 30px;'>
                    <img src='cid:logoIUT' alt='Logo IUT' style='height: 90px;'>
                </div>
                
                <h2 style='color: #2c3e50; text-align: center;'>Réinitialisation de mot de passe</h2>
                
                <p>Bonjour {$name},</p>
                
                <p>Vous avez demandé à réinitialiser votre mot de passe sur la plateforme de Gestion des Absences UPHF.</p>
                
                <div style='background-color: #f8f9fa; border: 2px solid #dc3545; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0;'>
                    <p style='margin: 0; font-size: 18px; font-weight: bold; color: #dc3545;'>Votre code de vérification :</p>
                    <p style='font-size: 32px; font-weight: bold; color: #dc3545; margin: 10px 0; letter-spacing: 3px;'>{$code}</p>
                </div>
                
                <p><strong>Ce code expire dans 15 minutes.</strong></p>
                
                <p>Entrez ce code sur la page de réinitialisation pour continuer.</p>
                
                <div style='background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 20px 0;'>
                    <p style='margin: 0;'><strong>⚠️ Important :</strong> Si vous n'avez pas demandé cette réinitialisation, ignorez cet email et votre mot de passe restera inchangé.</p>
                </div>
                
                <hr style='border: 1px solid #eee; margin: 30px 0;'>
                <p style='font-size: 12px; color: #666; text-align: center;'>
                    Gestion des Absences - UPHF<br>
                    Cet email a été envoyé automatiquement, merci de ne pas y répondre.
                </p>
            </div>
        </body>
        </html>";
    }
}

function isAjaxRequest(): bool
{
    return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos((string) $_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
}

function respondAjax(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// Request processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new ForgotPasswordController();
    $isAjax = isAjaxRequest();

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'send_reset_code':
            $email = trim($_POST['email'] ?? '');

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                if ($isAjax) {
                    respondAjax(['success' => false, 'message' => 'Email invalide.'], 422);
                }
                $_SESSION['error'] = 'Email invalide.';
                header('Location: ../../View/templates/shared/forgot_password.php');
                exit;
            }

            $result = $controller->sendResetCode($email);
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
                $_SESSION['reset_email'] = $email;
                if ($isAjax) {
                    respondAjax([
                        'success' => true,
                        'message' => $result['message'],
                        'redirectUrl' => '/View/templates/shared/verify_reset_code.php'
                    ]);
                }
                header('Location: ../../View/templates/shared/verify_reset_code.php');
            } else {
                if ($isAjax) {
                    respondAjax(['success' => false, 'message' => $result['message']], 500);
                }
                $_SESSION['error'] = $result['message'];
                header('Location: ../../View/templates/shared/forgot_password.php');
            }
            break;

        case 'verify_reset_code':
            $email = $_SESSION['reset_email'] ?? '';
            $code = trim($_POST['reset_code'] ?? '');

            if (empty($email) || empty($code)) {
                if ($isAjax) {
                    respondAjax(['success' => false, 'message' => 'Données manquantes.'], 422);
                }
                $_SESSION['error'] = 'Données manquantes.';
                header('Location: ../../View/templates/shared/verify_reset_code.php');
                exit;
            }

            $result = $controller->verifyResetCode($email, $code);
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
                $_SESSION['reset_code_verified'] = true;
                if ($isAjax) {
                    respondAjax([
                        'success' => true,
                        'message' => $result['message'],
                        'redirectUrl' => '/View/templates/shared/reset_password.php'
                    ]);
                }
                header('Location: ../../View/templates/shared/reset_password.php');
            } else {
                if ($isAjax) {
                    respondAjax(['success' => false, 'message' => $result['message']], 422);
                }
                $_SESSION['error'] = $result['message'];
                header('Location: ../../View/templates/shared/verify_reset_code.php');
            }
            break;

        case 'reset_password':
            $email = $_SESSION['reset_email'] ?? '';
            $codeVerified = $_SESSION['reset_code_verified'] ?? false;
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($email) || !$codeVerified) {
                if ($isAjax) {
                    respondAjax(['success' => false, 'message' => 'Session invalide. Veuillez recommencer.'], 422);
                }
                $_SESSION['error'] = 'Session invalide. Veuillez recommencer.';
                header('Location: ../../View/templates/shared/forgot_password.php');
                exit;
            }

            if (empty($newPassword) || empty($confirmPassword)) {
                if ($isAjax) {
                    respondAjax(['success' => false, 'message' => 'Tous les champs sont requis.'], 422);
                }
                $_SESSION['error'] = 'Tous les champs sont requis.';
                header('Location: ../../View/templates/shared/reset_password.php');
                exit;
            }

            $result = $controller->resetPassword($email, $newPassword, $confirmPassword);
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
                // Clean up session variables
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_code_verified']);
                if ($isAjax) {
                    respondAjax([
                        'success' => true,
                        'message' => $result['message'],
                        'redirectUrl' => '/View/templates/shared/login.php'
                    ]);
                }
                header('Location: ../../View/templates/shared/login.php');
            } else {
                if ($isAjax) {
                    respondAjax(['success' => false, 'message' => $result['message']], 422);
                }
                $_SESSION['error'] = $result['message'];
                header('Location: ../../View/templates/shared/reset_password.php');
            }
            break;

        default:
            if ($isAjax) {
                respondAjax(['success' => false, 'message' => 'Action invalide.'], 400);
            }
            $_SESSION['error'] = 'Action invalide.';
            header('Location: ../../View/templates/shared/forgot_password.php');
            break;
    }
    exit;
}
