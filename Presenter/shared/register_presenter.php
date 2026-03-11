<?php

declare(strict_types=1);

/**
 * File: register_presenter.php
 *
 * Registration controller — handles new user account creation.
 * Implements a 3-step registration process:
 * 1. Sends a verification code by email
 * 2. Verifies the code entered by the user
 * 3. Creates the account with a password
 * Automatically extracts first and last name from the email address.
 */

session_start();
require_once __DIR__ . '/../../Model/database.php';
require_once __DIR__ . '/../../Model/email.php';

class RegistrationController
{
    private PDO $pdo;
    private EmailService $emailService;

    public function __construct()
    {
        $this->pdo = getConnection();
        $this->emailService = new EmailService();
    }

    public function sendVerificationCode(string $email): array
    {
        try {
            // Generate a 6-digit verification code
            $verificationCode = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

            // Delete old codes for this email
            $stmt = $this->pdo->prepare('DELETE FROM email_verifications WHERE email = ?');
            $stmt->execute([$email]);

            // Insert the new code (expires in 15 minutes)
            $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            $stmt = $this->pdo->prepare('INSERT INTO email_verifications (email, verification_code, expires_at) VALUES (?, ?, ?)');
            $stmt->execute([$email, $verificationCode, $expiresAt]);

            // Send the email
            $subject = 'Code de vérification - Gestion Absence UPHF';
            $body = $this->getVerificationEmailTemplate($verificationCode);

            // Images for the email
            $images = [
                'logoIUT' => __DIR__ . '/../../View/img/logoIUT.png'
            ];

            $result = $this->emailService->sendEmail($email, $subject, $body, true, [], $images);

            if ($result['success']) {
                return ['success' => true, 'message' => 'Code de vérification envoyé par email.'];
            } else {
                return ['success' => false, 'message' => 'Erreur lors de l\'envoi de l\'email.'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur système: ' . $e->getMessage()];
        }
    }

    public function verifyCode(string $email, string $code): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM email_verifications 
                WHERE email = ? AND verification_code = ? AND expires_at > NOW() AND is_verified = FALSE
            ");
            $stmt->execute([$email, $code]);

            if ($stmt->fetch()) {
                // Mark as verified
                $stmt = $this->pdo->prepare('UPDATE email_verifications SET is_verified = TRUE WHERE email = ? AND verification_code = ?');
                $stmt->execute([$email, $code]);
                return ['success' => true, 'message' => 'Code vérifié avec succès.'];
            } else {
                return ['success' => false, 'message' => 'Code invalide ou expiré.'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur lors de la vérification.'];
        }
    }

    public function setToLowerMail(string $email): string
    {
        return strtolower($email);
    }

    public function createAccount(string $email, string $password, string $confirmPassword): array
    {
        $email = $this->setToLowerMail($email);
        try {
            // Check that passwords match
            if ($password !== $confirmPassword) {
                return ['success' => false, 'message' => 'Les mots de passe ne correspondent pas.'];
            }

            // Check that the email has been verified
            $stmt = $this->pdo->prepare("
                SELECT id FROM email_verifications 
                WHERE email = ? AND is_verified = TRUE AND expires_at > NOW()
            ");
            $stmt->execute([$email]);
            if (!$stmt->fetch()) {
                return ['success' => false, 'message' => 'Email non vérifié ou code expiré.'];
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // Extract first and last name from email
            list($firstName, $lastName) = $this->extractNameFromEmail($email);

            // Check if email already exists in users
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $existingUser = $stmt->fetch();

            $successMessage = '';

            if ($existingUser) {
                // Email already exists, update the password
                $stmt = $this->pdo->prepare("
                    UPDATE users 
                    SET password_hash = ?, email_verified = TRUE, updated_at = CURRENT_TIMESTAMP
                    WHERE email = ?
                ");
                $stmt->execute([$passwordHash, $email]);

                $successMessage = 'Mot de passe mis à jour avec succès !';
            } else {
                // Email does not exist, create a new account
                $stmt = $this->pdo->prepare("
                    INSERT INTO users (email, password_hash, role, email_verified, last_name, first_name) 
                    VALUES (?, ?, 'student', TRUE, ?, ?)
                ");
                $stmt->execute([$email, $passwordHash, $lastName, $firstName]);

                $successMessage = 'Nouveau compte créé avec succès !';
            }

            // Delete used verification codes
            $stmt = $this->pdo->prepare('DELETE FROM email_verifications WHERE email = ?');
            $stmt->execute([$email]);

            return ['success' => true, 'message' => $successMessage];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur lors de la création du compte: ' . $e->getMessage()];
        }
    }

    /**
     * Extracts first and last name from the email address
     * Expected format: firstname.lastname@domain.com
     */
    protected function extractNameFromEmail(string $email): array
    {
        // Get the part before @
        $emailParts = explode('@', $email);
        $localPart = $emailParts[0];

        // Split by dot
        $nameParts = explode('.', $localPart);

        if (count($nameParts) >= 2) {
            $firstName = ucfirst(strtolower($nameParts[0]));

            // For last name, take everything after the first dot
            $lastNameParts = array_slice($nameParts, 1);
            $lastName = implode('.', $lastNameParts);

            // Remove digits from the last name
            $lastName = preg_replace('/\d+/', '', $lastName);
            $lastName = ucfirst(strtolower($lastName));
        } else {
            // If no dot, use the email as first name
            $firstName = ucfirst(strtolower($localPart));
            $lastName = 'À compléter';
        }

        return [$firstName, $lastName];
    }

    private function getVerificationEmailTemplate(string $code): string
    {
        return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='text-align: center; margin-bottom: 30px;'>
                    <img src='cid:logoIUT' alt='Logo IUT' style='height: 90px;'>
                </div>
                
                <h2 style='color: #2c3e50; text-align: center;'>Code de vérification</h2>
                
                <p>Bonjour,</p>
                
                <p>Vous avez demandé à créer un compte sur la plateforme de Gestion des Absences UPHF.</p>
                
                <div style='background-color: #f8f9fa; border: 2px solid #007bff; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0;'>
                    <p style='margin: 0; font-size: 18px; font-weight: bold; color: #007bff;'>Votre code de vérification :</p>
                    <p style='font-size: 32px; font-weight: bold; color: #007bff; margin: 10px 0; letter-spacing: 3px;'>{$code}</p>
                </div>
                
                <p><strong>Ce code expire dans 15 minutes.</strong></p>
                
                <p>Si vous n'avez pas demandé la création de ce compte, vous pouvez ignorer cet email.</p>
                
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

// Request processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new RegistrationController();

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'send_code':
            $email = trim($_POST['email'] ?? '');

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = 'Email invalide.';
                header('Location: ../../View/templates/shared/create_acc.php');
                exit;
            }

            $result = $controller->sendVerificationCode($email);
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
                $_SESSION['email_to_verify'] = $email;
                header('Location: ../../View/templates/shared/verify_email.php');
            } else {
                $_SESSION['error'] = $result['message'];
                header('Location: ../../View/templates/shared/create_acc.php');
            }
            break;

        case 'verify_code':
            $email = $_SESSION['email_to_verify'] ?? '';
            $code = trim($_POST['verification_code'] ?? '');

            if (empty($email) || empty($code)) {
                $_SESSION['error'] = 'Données manquantes.';
                header('Location: ../../View/templates/shared/verify_email.php');
                exit;
            }

            $result = $controller->verifyCode($email, $code);
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
                $_SESSION['email_verified'] = $email;
                unset($_SESSION['email_to_verify']);
                header('Location: ../../View/templates/shared/complete_registration.php');
            } else {
                $_SESSION['error'] = $result['message'];
                header('Location: ../../View/templates/shared/verify_email.php');
            }
            break;

        case 'complete_registration':
            $email = $_SESSION['email_verified'] ?? '';
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($email) || empty($password) || empty($confirmPassword)) {
                $_SESSION['error'] = 'Tous les champs sont requis.';
                header('Location: ../../View/templates/shared/complete_registration.php');
                exit;
            }

            $result = $controller->createAccount($email, $password, $confirmPassword);
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
                unset($_SESSION['email_verified']);
                header('Location: ../../View/templates/shared/login.php');
            } else {
                $_SESSION['error'] = $result['message'];
                header('Location: ../../View/templates/shared/complete_registration.php');
            }
            break;

        default:
            $_SESSION['error'] = 'Action invalide.';
            header('Location: ../../View/templates/shared/create_acc.php');
            break;
    }
    exit;
}
