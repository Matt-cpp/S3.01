<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Model/env.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService {   
    private $mail;
    
    public function __construct() {
        $this->mail = new PHPMailer(true);
        
        // Server settings - using SSL (port 465) as it works
        $this->mail->isSMTP();
        $this->mail->Host       = env('MAIL_HOST', 'smtp.gmail.com');
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = env('MAIL_USER', '');
        $this->mail->Password   = env('MAIL_PASSWORD', '');
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
        $this->mail->Port       = 465;
        
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
    
    public function sendEmail($to, $subject, $body, $isHTML = true) {
        try {
            // Clear any previous addresses
            $this->mail->clearAddresses();
            
            $this->mail->setFrom(env('MAIL_USER', ''), 'Gestion Absence UPHF');
            $this->mail->addAddress($to);
            
            $this->mail->isHTML($isHTML);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $body;
            
            $this->mail->send();
            return ['success' => true, 'message' => 'Email sent successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => "Email could not be sent. Error: {$this->mail->ErrorInfo}"];
        }
    }
}

// Example usage (remove this section when integrating into your application)
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    echo "<h2>Email Service Test</h2>";
    $emailService = new EmailService();
    $response = $emailService->sendEmail('bisiauxambroise@gmail.com', 'Test Subject', 'This is a test email body.');
    echo '<strong>Status:</strong> ' . $response['message'];
}

// $emailService = new EmailService();
// $result = $emailService->sendEmail(
//     'user@example.com', 
//     'Subject here', 
//     'Your email content here'
// );

// if ($result['success']) {
//     echo "Email sent successfully!";
// } else {
//     echo "Failed: " . $result['message'];
// }