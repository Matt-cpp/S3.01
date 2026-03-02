<?php

/**
 * Fichier: forgot_password.php
 * 
 * ContrÃ´leur de rÃ©initialisation de mot de passe - GÃ¨re la rÃ©cupÃ©ration de compte.
 * Processus en 3 Ã©tapes:
 * 1. Envoi d'un code de vÃ©rification Ã  6 chiffres par email
 * 2. VÃ©rification du code (expire aprÃ¨s 15 minutes)
 * 3. RÃ©initialisation du mot de passe
 * Envoie des emails HTML avec le logo de l'universitÃ©.
 */

session_start();
require_once __DIR__ . '/../../Model/database.php';
require_once __DIR__ . '/../../Model/email.php';

class ForgotPasswordController
{
    private $pdo;
    private $emailService;

    public function __construct()
    {
        $this->pdo = getConnection();
        $this->emailService = new EmailService();
    }

    /**
     * Envoie un code de vÃ©rification Ã  l'email spÃ©cifiÃ© pour rÃ©initialiser le mot de passe
     */
    public function sendResetCode($email)
    {
        try {
            $email = strtolower(trim($email));
            // VÃ©rifier que l'email existe dans la base de donnÃ©es
            $stmt = $this->pdo->prepare("SELECT id, first_name, last_name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Pour des raisons de sÃ©curitÃ©, on retourne toujours un message de succÃ¨s
            // mÃªme si l'email n'existe pas, pour ne pas rÃ©vÃ©ler l'existence du compte
            if (!$user) {
                // Simuler un dÃ©lai pour Ã©viter les attaques par timing
                usleep(random_int(100000, 300000)); // 0.1 Ã  0.3 secondes
                return ['success' => true, 'message' => 'Un email a Ã©tÃ© envoyÃ© si le compte existe.'];
            }

            // GÃ©nÃ©rer un code de vÃ©rification Ã  6 chiffres
            $verificationCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

            // Supprimer les anciens codes pour cet email
            $stmt = $this->pdo->prepare("DELETE FROM email_verifications WHERE email = ?");
            $stmt->execute([$email]);

            // InsÃ©rer le nouveau code (expire dans 15 minutes)
            $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            $stmt = $this->pdo->prepare("INSERT INTO email_verifications (email, verification_code, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$email, $verificationCode, $expiresAt]);

            // Envoyer l'email
            $subject = "RÃ©initialisation de mot de passe - Gestion Absence UPHF";
            $body = $this->getResetEmailTemplate($verificationCode, $user['first_name'], $user['last_name']);

            // Images pour l'email
            $images = [
                'logoIUT' => __DIR__ . '/../../View/img/logoIUT.png'
            ];

            $result = $this->emailService->sendEmail($email, $subject, $body, true, [], $images);

            if ($result['success']) {
                return ['success' => true, 'message' => 'Un email a Ã©tÃ© envoyÃ© si le compte existe.'];
            } else {
                // Ne pas rÃ©vÃ©ler l'erreur d'envoi pour des raisons de sÃ©curitÃ©
                error_log('Email sending failed: ' . $result['message']);
                return ['success' => true, 'message' => 'Un email a Ã©tÃ© envoyÃ© si le compte existe.'];
            }
        } catch (Exception $e) {
            error_log("Error in sendResetCode: " . $e->getMessage());
            // Pour la sÃ©curitÃ©, on retourne toujours un succÃ¨s mÃªme en cas d'erreur
            return ['success' => true, 'message' => 'Un email a Ã©tÃ© envoyÃ© si le compte existe.'];
        }
    }

    /**
     * VÃ©rifie le code de rÃ©initialisation
     */
    public function verifyResetCode($email, $code)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM email_verifications 
                WHERE email = ? AND verification_code = ? AND expires_at > NOW() AND is_verified = FALSE
            ");
            $stmt->execute([$email, $code]);

            if ($stmt->fetch()) {
                // Marquer comme vÃ©rifiÃ©
                $stmt = $this->pdo->prepare("UPDATE email_verifications SET is_verified = TRUE WHERE email = ? AND verification_code = ?");
                $stmt->execute([$email, $code]);
                return ['success' => true, 'message' => 'Code vÃ©rifiÃ© avec succÃ¨s.'];
            } else {
                return ['success' => false, 'message' => 'Code invalide ou expirÃ©.'];
            }
        } catch (Exception $e) {
            error_log("Error in verifyResetCode: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur lors de la vÃ©rification.'];
        }
    }

    /**
     * RÃ©initialise le mot de passe aprÃ¨s vÃ©rification du code
     */
    public function resetPassword($email, $newPassword, $confirmPassword)
    {
        try {
            // VÃ©rifier que les mots de passe correspondent
            if ($newPassword !== $confirmPassword) {
                return ['success' => false, 'message' => 'Les mots de passe ne correspondent pas.'];
            }

            // VÃ©rifier la longueur du mot de passe
            if (strlen($newPassword) < 8) {
                return ['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractÃ¨res.'];
            }

            // VÃ©rifier que le code a Ã©tÃ© vÃ©rifiÃ© et n'est pas expirÃ©
            $stmt = $this->pdo->prepare("
                SELECT id FROM email_verifications 
                WHERE email = ? AND is_verified = TRUE AND expires_at > NOW()
            ");
            $stmt->execute([$email]);
            $resetCode = $stmt->fetch();

            if (!$resetCode) {
                return ['success' => false, 'message' => 'Code non vÃ©rifiÃ© ou expirÃ©. Veuillez recommencer.'];
            }

            // Hasher le nouveau mot de passe
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            // Mettre Ã  jour le mot de passe dans la table users
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET password_hash = ?, updated_at = CURRENT_TIMESTAMP
                WHERE email = ?
            ");
            $stmt->execute([$passwordHash, $email]);

            // Supprimer les codes de vÃ©rification utilisÃ©s
            $stmt = $this->pdo->prepare("DELETE FROM email_verifications WHERE email = ?");
            $stmt->execute([$email]);
            return ['success' => true, 'message' => 'Mot de passe rÃ©initialisÃ© avec succÃ¨s !'];
        } catch (Exception $e) {
            error_log("Error in resetPassword: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur lors de la rÃ©initialisation: ' . $e->getMessage()];
        }
    }

    /**
     * Template d'email pour la rÃ©initialisation de mot de passe
     */
    private function getResetEmailTemplate($code, $firstName, $lastName)
    {
        $name = trim($firstName . ' ' . $lastName);
        return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='text-align: center; margin-bottom: 30px;'>
                    <img src='cid:logoIUT' alt='Logo IUT' style='height: 90px;'>
                </div>
                
                <h2 style='color: #2c3e50; text-align: center;'>RÃ©initialisation de mot de passe</h2>
                
                <p>Bonjour {$name},</p>
                
                <p>Vous avez demandÃ© Ã  rÃ©initialiser votre mot de passe sur la plateforme de Gestion des Absences UPHF.</p>
                
                <div style='background-color: #f8f9fa; border: 2px solid #dc3545; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0;'>
                    <p style='margin: 0; font-size: 18px; font-weight: bold; color: #dc3545;'>Votre code de vÃ©rification :</p>
                    <p style='font-size: 32px; font-weight: bold; color: #dc3545; margin: 10px 0; letter-spacing: 3px;'>{$code}</p>
                </div>
                
                <p><strong>Ce code expire dans 15 minutes.</strong></p>
                
                <p>Entrez ce code sur la page de rÃ©initialisation pour continuer.</p>
                
                <div style='background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 20px 0;'>
                    <p style='margin: 0;'><strong>âš ï¸ Important :</strong> Si vous n'avez pas demandÃ© cette rÃ©initialisation, ignorez cet email et votre mot de passe restera inchangÃ©.</p>
                </div>
                
                <hr style='border: 1px solid #eee; margin: 30px 0;'>
                <p style='font-size: 12px; color: #666; text-align: center;'>
                    Gestion des Absences - UPHF<br>
                    Cet email a Ã©tÃ© envoyÃ© automatiquement, merci de ne pas y rÃ©pondre.
                </p>
            </div>
        </body>
        </html>";
    }
}

// Traitement des requÃªtes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new ForgotPasswordController();

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'send_reset_code':
            $email = trim($_POST['email'] ?? '');

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = 'Email invalide.';
                header('Location: ../../View/templates/shared/forgot_password.php');
                exit;
            }

            $result = $controller->sendResetCode($email);
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
                $_SESSION['reset_email'] = $email;
                header('Location: ../../View/templates/shared/verify_reset_code.php');
            } else {
                $_SESSION['error'] = $result['message'];
                header('Location: ../../View/templates/shared/forgot_password.php');
            }
            break;

        case 'verify_reset_code':
            $email = $_SESSION['reset_email'] ?? '';
            $code = trim($_POST['reset_code'] ?? '');

            if (empty($email) || empty($code)) {
                $_SESSION['error'] = 'DonnÃ©es manquantes.';
                header('Location: ../../View/templates/shared/verify_reset_code.php');
                exit;
            }

            $result = $controller->verifyResetCode($email, $code);
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
                $_SESSION['reset_code_verified'] = true;
                header('Location: ../../View/templates/shared/reset_password.php');
            } else {
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
                $_SESSION['error'] = 'Session invalide. Veuillez recommencer.';
                header('Location: ../../View/templates/shared/forgot_password.php');
                exit;
            }

            if (empty($newPassword) || empty($confirmPassword)) {
                $_SESSION['error'] = 'Tous les champs sont requis.';
                header('Location: ../../View/templates/shared/reset_password.php');
                exit;
            }

            $result = $controller->resetPassword($email, $newPassword, $confirmPassword);
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
                // Nettoyer les variables de session
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_code_verified']);
                header('Location: ../../View/templates/shared/login.php');
            } else {
                $_SESSION['error'] = $result['message'];
                header('Location: ../../View/templates/shared/reset_password.php');
            }
            break;

        default:
            $_SESSION['error'] = 'Action invalide.';
            header('Location: ../../View/templates/shared/forgot_password.php');
            break;
    }
    exit;
}
