<?php
session_start();
require_once __DIR__ . '/../Model/database.php';
require_once __DIR__ . '/../Model/email.php';

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
     * Envoie un code de vérification à l'email spécifié pour réinitialiser le mot de passe
     */
    public function sendResetCode($email)
    {
        try {
            // Vérifier que l'email existe dans la base de données
            $stmt = $this->pdo->prepare("SELECT id, first_name, last_name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                return ['success' => false, 'message' => 'Aucun compte associé à cette adresse email.'];
            }

            // Générer un code de vérification à 6 chiffres
            $verificationCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

            // Supprimer les anciens codes pour cet email
            $stmt = $this->pdo->prepare("DELETE FROM email_verifications WHERE email = ?");
            $stmt->execute([$email]);

            // Insérer le nouveau code (expire dans 15 minutes)
            $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            $stmt = $this->pdo->prepare("INSERT INTO email_verifications (email, verification_code, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$email, $verificationCode, $expiresAt]);

            // Envoyer l'email
            $subject = "Réinitialisation de mot de passe - Gestion Absence UPHF";
            $body = $this->getResetEmailTemplate($verificationCode, $user['first_name'], $user['last_name']);

            // Images pour l'email
            $images = [
                'logoIUT' => __DIR__ . '/../View/img/logoIUT.png'
            ];

            $result = $this->emailService->sendEmail($email, $subject, $body, true, [], $images);

            if ($result['success']) {
                return ['success' => true, 'message' => 'Un code de vérification a été envoyé à votre adresse email.'];
            } else {
                return ['success' => false, 'message' => 'Erreur lors de l\'envoi de l\'email: ' . $result['message']];
            }
        } catch (Exception $e) {
            error_log("Error in sendResetCode: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur système: ' . $e->getMessage()];
        }
    }

    /**
     * Vérifie le code de réinitialisation
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
                // Marquer comme vérifié
                $stmt = $this->pdo->prepare("UPDATE email_verifications SET is_verified = TRUE WHERE email = ? AND verification_code = ?");
                $stmt->execute([$email, $code]);
                return ['success' => true, 'message' => 'Code vérifié avec succès.'];
            } else {
                return ['success' => false, 'message' => 'Code invalide ou expiré.'];
            }
        } catch (Exception $e) {
            error_log("Error in verifyResetCode: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur lors de la vérification.'];
        }
    }

    /**
     * Réinitialise le mot de passe après vérification du code
     */
    public function resetPassword($email, $newPassword, $confirmPassword)
    {
        try {
            // Vérifier que les mots de passe correspondent
            if ($newPassword !== $confirmPassword) {
                return ['success' => false, 'message' => 'Les mots de passe ne correspondent pas.'];
            }

            // Vérifier la longueur du mot de passe
            if (strlen($newPassword) < 8) {
                return ['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractères.'];
            }

            // Vérifier que le code a été vérifié et n'est pas expiré
            $stmt = $this->pdo->prepare("
                SELECT id FROM email_verifications 
                WHERE email = ? AND is_verified = TRUE AND expires_at > NOW()
            ");
            $stmt->execute([$email]);
            $resetCode = $stmt->fetch();

            if (!$resetCode) {
                return ['success' => false, 'message' => 'Code non vérifié ou expiré. Veuillez recommencer.'];
            }

            // Hasher le nouveau mot de passe
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            // Mettre à jour le mot de passe dans la table users
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET password_hash = ?, updated_at = CURRENT_TIMESTAMP
                WHERE email = ?
            ");
            $stmt->execute([$passwordHash, $email]);

            // Supprimer les codes de vérification utilisés
            $stmt = $this->pdo->prepare("DELETE FROM email_verifications WHERE email = ?");
            $stmt->execute([$email]);
            return ['success' => true, 'message' => 'Mot de passe réinitialisé avec succès !'];
        } catch (Exception $e) {
            error_log("Error in resetPassword: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur lors de la réinitialisation: ' . $e->getMessage()];
        }
    }

    /**
     * Template d'email pour la réinitialisation de mot de passe
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

// Traitement des requêtes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new ForgotPasswordController();

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'send_reset_code':
            $email = trim($_POST['email'] ?? '');

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = 'Email invalide.';
                header('Location: ../View/templates/forgot_password.php');
                exit;
            }

            $result = $controller->sendResetCode($email);
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
                $_SESSION['reset_email'] = $email;
                header('Location: ../View/templates/verify_reset_code.php');
            } else {
                $_SESSION['error'] = $result['message'];
                header('Location: ../View/templates/forgot_password.php');
            }
            break;

        case 'verify_reset_code':
            $email = $_SESSION['reset_email'] ?? '';
            $code = trim($_POST['reset_code'] ?? '');

            if (empty($email) || empty($code)) {
                $_SESSION['error'] = 'Données manquantes.';
                header('Location: ../View/templates/verify_reset_code.php');
                exit;
            }

            $result = $controller->verifyResetCode($email, $code);
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
                $_SESSION['reset_code_verified'] = true;
                header('Location: ../View/templates/reset_password.php');
            } else {
                $_SESSION['error'] = $result['message'];
                header('Location: ../View/templates/verify_reset_code.php');
            }
            break;

        case 'reset_password':
            $email = $_SESSION['reset_email'] ?? '';
            $codeVerified = $_SESSION['reset_code_verified'] ?? false;
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($email) || !$codeVerified) {
                $_SESSION['error'] = 'Session invalide. Veuillez recommencer.';
                header('Location: ../View/templates/forgot_password.php');
                exit;
            }

            if (empty($newPassword) || empty($confirmPassword)) {
                $_SESSION['error'] = 'Tous les champs sont requis.';
                header('Location: ../View/templates/reset_password.php');
                exit;
            }

            $result = $controller->resetPassword($email, $newPassword, $confirmPassword);
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
                // Nettoyer les variables de session
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_code_verified']);
                header('Location: ../View/templates/login.php');
            } else {
                $_SESSION['error'] = $result['message'];
                header('Location: ../View/templates/reset_password.php');
            }
            break;

        default:
            $_SESSION['error'] = 'Action invalide.';
            header('Location: ../View/templates/forgot_password.php');
            break;
    }
    exit;
}
