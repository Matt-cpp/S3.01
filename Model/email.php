<?php

/**
 * Fichier: email.php
 * 
 * Service d'envoi d'emails - Gère l'envoi d'emails via SMTP avec PHPMailer.
 * Configure automatiquement la connexion SMTP avec les paramètres du fichier .env.
 * Supporte l'envoi d'emails HTML, de pièces jointes et d'images intégrées (embedded).
 * Utilisé pour envoyer les codes de vérification, confirmations et notifications.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Model/env.php';
require_once __DIR__ . '/../Model/database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private $mail;

    public function __construct()
    {
        $this->mail = new PHPMailer(true);

        $this->mail->CharSet = 'UTF-8';
        $this->mail->Encoding = 'base64';

        // Server settings - using SSL (port 465)
        $this->mail->isSMTP();
        $this->mail->Host = env('MAIL_HOST', 'smtp.gmail.com');
        $this->mail->SMTPAuth = true;
        $this->mail->Username = env('MAIL_USER', '');
        $this->mail->Password = env('MAIL_PASSWORD', '');
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
        $this->mail->Port = 465;

        // Connection options
        $this->mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $this->mail->Timeout = 60;

        // Disable debugging for production use
        $this->mail->SMTPDebug = 0;
    }

    public function sendEmail($to, $subject, $body, $isHTML = true, $attachments = [], $images = [])
    {
        try {
            // Clear any previous addresses and attachments
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();

            $this->mail->setFrom(env('MAIL_USER', ''), 'Gestion Absence UPHF');
            $this->mail->addAddress($to);

            $this->mail->isHTML($isHTML);
            $this->mail->Subject = $subject;
            $this->mail->Body = $body;

            // Add file attachments
            if (!empty($attachments)) {
                foreach ($attachments as $attachment) {
                    if (is_array($attachment)) {
                        // Array format: ['path' => 'file/path', 'name' => 'display_name']
                        $this->mail->addAttachment($attachment['path'], $attachment['name'] ?? '');
                    } else {
                        // String format: just the file path
                        $this->mail->addAttachment($attachment);
                    }
                }
            }

            // Add embedded images
            if (!empty($images)) {
                foreach ($images as $cid => $imagePath) {
                    if (file_exists($imagePath)) {
                        $this->mail->addEmbeddedImage($imagePath, $cid);
                    }
                }
            }

            $this->mail->send();
            return ['success' => true, 'message' => 'Email sent successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => "Email could not be sent. Error: {$this->mail->ErrorInfo}"];
        }
    }

    public function addAttachment($filePath, $fileName = '')
    {
        if (file_exists($filePath)) {
            try {
                $this->mail->addAttachment($filePath, $fileName);
                return true;
            } catch (Exception $e) {
                return false;
            }
        }
        return false;
    }

    public function addEmbeddedImage($imagePath, $cid)
    {
        if (file_exists($imagePath)) {
            try {
                $this->mail->addEmbeddedImage($imagePath, $cid);
                return true;
            } catch (Exception $e) {
                return false;
            }
        }
        return false;
    }

    public function clearAttachments()
    {
        $this->mail->clearAttachments();
    }
}

if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    echo "<h2>Email Service Test</h2>";
    $emailService = new EmailService();

    $htmlBody = '
    <h1>Résumé de votre justificatif envoyé</h1>
    <p>Veuillez trouver le document récapitulatof ci-joint.</p>
    <img src="cid:logoUPHF" alt="Logo UPHF" class="logo" width="220" height="80">
    <img src="cid:logoIUT" alt="Logo IUT" class="logo" width="100" height="90">
    ';

    $attachments = [
        __DIR__ . '/../uploads/.pdf',
    ];

    $images = [
        'logoUPHF' => __DIR__ . '/../View/img/logoUPHF.png',
        'logoIUT' => __DIR__ . '/../View/img/logoIUT.png'
    ];

    $response = $emailService->sendEmail(
        'ambroise.bisiaux@uphf.fr',
        'Test Subject with Attachments',
        $htmlBody,
        true,
        $attachments,
        $images
    );

    echo '<strong>Status:</strong> ' . $response['message'];
}
