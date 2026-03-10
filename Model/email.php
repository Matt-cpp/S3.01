<?php

declare(strict_types=1);

/**
 * Email sending service - Manages email sending via SMTP with PHPMailer.
 * Automatically configures the SMTP connection with parameters from the .env file.
 * Supports sending HTML emails, attachments and embedded images.
 * Used to send verification codes, confirmations and notifications.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Model/env.php';
require_once __DIR__ . '/../Model/database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private PHPMailer $mail;

    public function __construct()
    {
        $this->mail = new PHPMailer(true);

        $this->mail->CharSet = 'UTF-8';
        $this->mail->Encoding = 'base64';

        // Server settings - using TLS (port 587) or SSL (port 465)
        $this->mail->isSMTP();
        $this->mail->Host = env('MAIL_HOST', 'smtp.gmail.com');
        $this->mail->SMTPAuth = true;
        $this->mail->Username = env('MAIL_USER', '');
        $this->mail->Password = env('MAIL_PASSWORD', '');

        // Use port from .env file (default 587 for TLS)
        $mailPort = (int) env('MAIL_PORT', 587);
        $this->mail->Port = $mailPort;

        // Use SSL for port 465, TLS for port 587
        if ($mailPort === 465) {
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
        } else {
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS
        }

        // Connection options with extended timeout
        $this->mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Extended timeout for slow connections
        $this->mail->Timeout = 120;
        $this->mail->SMTPKeepAlive = true;

        // Disable debug output
        $this->mail->SMTPDebug = 0;
    }

    public function sendEmail(string $to, string $subject, string $body, bool $isHTML = true, array $attachments = [], array $images = []): array
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

    public function addAttachment(string $filePath, string $fileName = ''): bool
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

    public function addEmbeddedImage(string $imagePath, string $cid): bool
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

    public function clearAttachments(): void
    {
        $this->mail->clearAttachments();
    }
}
