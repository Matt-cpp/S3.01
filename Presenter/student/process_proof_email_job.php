<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Model/email.php';
require_once __DIR__ . '/../../Model/NotificationModel.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$jobFile = $argv[1] ?? '';
if ($jobFile === '' || !is_file($jobFile)) {
    exit(1);
}

$raw = file_get_contents($jobFile);
$job = $raw !== false ? json_decode($raw, true) : null;
if (!is_array($job)) {
    @unlink($jobFile);
    exit(1);
}

$reasonData = $job['reason_data'] ?? null;
$studentEmail = (string) ($job['student_email'] ?? '');
$studentIdentifier = (string) ($job['student_identifier'] ?? '');
$studentId = (int) ($job['student_id'] ?? 0);

if (!is_array($reasonData) || $studentEmail === '' || $studentIdentifier === '' || $studentId <= 0) {
    @unlink($jobFile);
    exit(1);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['reason_data'] = $reasonData;
$_SESSION['id_student'] = $studentId;
$_SESSION['student_info'] = ['email' => $studentEmail];

$pdfFilename = 'Justificatif_recapitulatif_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.pdf';
$pdfPath = __DIR__ . '/../../uploads/' . $pdfFilename;

try {
    $_POST['action'] = 'download_pdf_server';
    $_POST['name_file'] = $pdfFilename;

    ob_start();
    include __DIR__ . '/../shared/generate_pdf.php';
    ob_end_clean();

    $attachments = [];

    $proofFiles = $reasonData['proof_files'] ?? [];
    if (is_array($proofFiles)) {
        foreach ($proofFiles as $fileInfo) {
            $relativePath = $fileInfo['path'] ?? '';
            if ($relativePath === '') {
                continue;
            }

            $absolutePath = __DIR__ . '/../../' . ltrim((string) $relativePath, '/\\');
            if (is_file($absolutePath)) {
                $attachments[] = [
                    'path' => $absolutePath,
                    'name' => $fileInfo['original_name'] ?? basename($absolutePath)
                ];
            }
        }
    }

    if (is_file($pdfPath)) {
        $attachments[] = ['path' => $pdfPath, 'name' => $pdfFilename];
    }

    $images = [
    ];

    $htmlBody = '
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
                <h1>Justificatif Reçu</h1>
            </div>
            <div class="content">
                <p>Votre justificatif d\'absence a été reçu avec succès et est en attente de validation.</p>
                <div class="success-box">
                    <p>Votre demande est enregistrée.</p>
                    <p>Le récapitulatif PDF de votre demande est joint à cet email.</p>
                    <p>Vos justificatifs déposés sont également joints.</p>
                </div>
                <p>Vous recevrez une notification par email une fois votre justificatif traité par l\'administration.</p>
            </div>
            <div class="footer">
                <p>Gestion des Absences - UPHF</p>
                <p>Cet email est envoyé automatiquement, merci de ne pas y répondre.</p>
            </div>
        </div>
    </body>
    </html>
    ';

    $emailService = new EmailService();
    $response = $emailService->sendEmail(
        $studentEmail,
        'Confirmation de réception - Justificatif d\'absence',
        $htmlBody,
        true,
        $attachments,
        $images
    );

    $notificationModel = new NotificationModel();
    if ($response['success']) {
        $notificationModel->createNotification(
            $studentIdentifier,
            'justification_processed',
            'Confirmation email envoyée',
            'Le récapitulatif PDF de votre justificatif vous a été envoyé par email.',
            true
        );
    } else {
        error_log('Background email error: ' . ($response['message'] ?? 'unknown'));
        $notificationModel->createNotification(
            $studentIdentifier,
            'justification_processed',
            'Email de confirmation non envoyé',
            'Votre justificatif est bien enregistré, mais l\'email de confirmation a échoué.',
            false
        );
    }
} catch (Throwable $e) {
    error_log('Background proof email worker error: ' . $e->getMessage());
} finally {
    if (is_file($pdfPath)) {
        @unlink($pdfPath);
    }
    @unlink($jobFile);
}
