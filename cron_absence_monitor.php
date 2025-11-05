<?php
/**
 * Absence Monitoring Cron Job
 * 
 * This script should be run every hour to:
 * 1. Detect when students return to class after absences
 * 2. Send absences notifications asking them to justify absences
 * 3. Send reminder notifications 24h later if still not justified
 * 
 * Schedule this script to run hourly using Windows Task Scheduler or cron
 * Example (Windows Task Scheduler): Run at minute 0 of every hour during school days
 * Example (Linux cron): 0 * * * 1-5 php /path/to/cron_absence_monitor.php
 */

require_once __DIR__ . '/Model/AbsenceMonitoringModel.php';
require_once __DIR__ . '/Model/email.php';
require_once __DIR__ . '/Model/env.php';

// Set up logging
$logFile = __DIR__ . '/logs/absence_monitor_' . date('Y-m-d') . '.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

function logMessage($message, $logFile)
{
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    echo $logMessage;
}

// Check if we're in school hours (Monday-Friday, 8AM-6PM)
// We run until 6PM to catch the end-of-day (5PM) detections
function isSchoolTime()
{
    $now = new DateTime();
    $dayOfWeek = (int) $now->format('N'); // 1 (Monday) to 7 (Sunday)
    $hour = (int) $now->format('G');

    return ($dayOfWeek >= 1 && $dayOfWeek <= 5) && ($hour >= 8 && $hour <= 18);
}

logMessage("=== Absence Monitor Cron Job Started ===", $logFile);

// Check if we should run
if (!isSchoolTime()) {
    logMessage("Outside of school hours. Exiting.", $logFile);
    exit(0);
}

try {
    $monitoringModel = new AbsenceMonitoringModel();
    $emailService = new EmailService();

    //Detect students who have returned to class
    logMessage("Step 1: Detecting student returns to class...", $logFile);

    $studentsWithAbsences = $monitoringModel->getStudentsWithOngoingAbsences();
    logMessage("Found " . count($studentsWithAbsences) . " students with ongoing absences", $logFile);

    $returnsDetected = 0;
    foreach ($studentsWithAbsences as $student) {
        $hasReturned = $monitoringModel->hasStudentReturnedToClass(
            $student['student_identifier'],
            $student['last_absence_date']
        );

        if ($hasReturned) {
            logMessage(
                "Return detected: {$student['first_name']} {$student['last_name']} ({$student['student_identifier']})",
                $logFile
            );

            $monitoringModel->recordStudentReturn(
                $student['student_identifier'],
                $student['absence_start_date'],
                $student['absence_end_date'],
                $student['last_absence_date']
            );

            $returnsDetected++;
        }
    }

    logMessage("Detected {$returnsDetected} student returns", $logFile);

    // Send absences notifications
    logMessage("Step 2: Sending absences notifications...", $logFile);

    $studentsAwaitingNotification = $monitoringModel->getStudentsAwaitingReturnNotification();
    logMessage("Found " . count($studentsAwaitingNotification) . " students awaiting notification", $logFile);

    $notificationsSent = 0;
    foreach ($studentsAwaitingNotification as $student) {
        // Check if they've already justified
        $isJustified = $monitoringModel->updateJustificationStatus(
            $student['student_identifier'],
            $student['absence_period_start'],
            $student['absence_period_end']
        );

        if ($isJustified) {
            logMessage(
                "Student {$student['first_name']} {$student['last_name']} has already justified. Skipping notification.",
                $logFile
            );
            $monitoringModel->markReturnNotificationSent($student['id']);
            continue;
        }

        // Get absence details for the email
        $absenceDetails = $monitoringModel->getAbsenceDetails(
            $student['student_identifier'],
            $student['absence_period_start'],
            $student['absence_period_end']
        );

        // Prepare email content
        $subject = "Retour en cours détecté - Justification d'absences requise";
        $body = generateReturnNotificationEmail($student, $absenceDetails);

        // Send email
        $result = $emailService->sendEmail(
            $student['email'],
            $subject,
            $body,
            true,
            [],
            [
                'logoUPHF' => __DIR__ . '/View/img/UPHF_logo.png',
                'logoIUT' => __DIR__ . '/View/img/logoIUT.png'
            ]
        );

        if ($result['success']) {
            $monitoringModel->markReturnNotificationSent($student['id']);
            logMessage(
                "Return notification sent to {$student['first_name']} {$student['last_name']} ({$student['email']})",
                $logFile
            );
            $notificationsSent++;
        } else {
            logMessage(
                "Failed to send notification to {$student['email']}: {$result['message']}",
                $logFile
            );
        }

        // Small delay to avoid overwhelming the email server
        sleep(1);
    }

    logMessage("Sent {$notificationsSent} return notifications", $logFile);

    //Send 24h reminder notifications

    logMessage("Step 3: Sending 24h reminder notifications...", $logFile);

    $studentsNeedingReminder = $monitoringModel->getStudentsNeedingReminder();
    logMessage("Found " . count($studentsNeedingReminder) . " students needing reminders", $logFile);

    $remindersSent = 0;
    foreach ($studentsNeedingReminder as $student) {
        // Double-check if they've justified in the meantime
        $isJustified = $monitoringModel->updateJustificationStatus(
            $student['student_identifier'],
            $student['absence_period_start'],
            $student['absence_period_end']
        );

        if ($isJustified) {
            logMessage(
                "Student {$student['first_name']} {$student['last_name']} has justified. Skipping reminder.",
                $logFile
            );
            $monitoringModel->markReminderNotificationSent($student['id']);
            continue;
        }

        // Get absence details for the email
        $absenceDetails = $monitoringModel->getAbsenceDetails(
            $student['student_identifier'],
            $student['absence_period_start'],
            $student['absence_period_end']
        );

        // Prepare reminder email content
        $subject = "Rappel - Justification d'absences requise";
        $body = generateReminderEmail($student, $absenceDetails);

        // Send email
        $result = $emailService->sendEmail(
            $student['email'],
            $subject,
            $body,
            true,
            [],
            [
                'logoUPHF' => __DIR__ . '/View/img/logoUPHF.png',
                'logoIUT' => __DIR__ . '/View/img/logoIUT.png'
            ]
        );

        if ($result['success']) {
            $monitoringModel->markReminderNotificationSent($student['id']);
            logMessage(
                "Reminder sent to {$student['first_name']} {$student['last_name']} ({$student['email']})",
                $logFile
            );
            $remindersSent++;
        } else {
            logMessage(
                "Failed to send reminder to {$student['email']}: {$result['message']}",
                $logFile
            );
        }

        // Small delay to avoid overwhelming the email server
        sleep(1);
    }

    logMessage("Sent {$remindersSent} reminder notifications", $logFile);

    // Cleanup old records (run once a day at 6PM)
    $currentHour = (int) date('G');
    if ($currentHour == 18) {
        logMessage("Step 4: Cleaning up old monitoring records...", $logFile);
        $deletedCount = $monitoringModel->cleanupOldRecords();
        logMessage("Cleaned up {$deletedCount} old records", $logFile);
    }

    logMessage("=== Absence Monitor Cron Job Completed Successfully ===", $logFile);

} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage(), $logFile);
    logMessage("Stack trace: " . $e->getTraceAsString(), $logFile);
    exit(1);
}

//Generate the HTML email for return-to-class notification
function generateReturnNotificationEmail($student, $absenceDetails)
{
    $firstName = htmlspecialchars($student['first_name']);
    $lastName = htmlspecialchars($student['last_name']);

    $absenceList = '';
    foreach ($absenceDetails as $absence) {
        $date = date('d/m/Y', strtotime($absence['course_date']));
        $startTime = substr($absence['start_time'], 0, 5);
        $endTime = substr($absence['end_time'], 0, 5);
        $courseName = htmlspecialchars($absence['course_name'] ?? 'Non spécifié');
        $courseType = htmlspecialchars($absence['course_type']);

        $absenceList .= "<li><strong>{$date}</strong> - {$startTime} à {$endTime} - {$courseName} ({$courseType})</li>";
    }

    $totalAbsences = count($absenceDetails);

    $html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #003366;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo {
            margin: 10px;
        }
        .content {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-box {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
        }
        .absence-list {
            background-color: white;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .absence-list ul {
            list-style-type: none;
            padding-left: 0;
        }
        .absence-list li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .button {
            display: inline-block;
            background-color: #003366;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            text-align: center;
        }
        .footer {
            text-align: center;
            font-size: 0.9em;
            color: #666;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        h1 {
            color: #003366;
        }
        .deadline {
            color: #d9534f;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="cid:logoUPHF" alt="Logo UPHF" class="logo" width="220" height="80">
        <img src="cid:logoIUT" alt="Logo IUT" class="logo" width="100" height="90">
    </div>
    
    <div class="content">
        <h1>Retour en cours détecté</h1>
        
        <p>Bonjour {$firstName} {$lastName},</p>
        
        <p>Nous avons détecté votre retour en cours après une période d'absence. Selon notre système de gestion des absences, 
        vous avez été absent(e) lors des cours suivants :</p>
        
        <div class="absence-list">
            <h3>Vos absences non justifiées ({$totalAbsences} cours) :</h3>
            <ul>
                {$absenceList}
            </ul>
        </div>
        
        <div class="alert-box">
            <strong>⚠️ Action requise :</strong> Vous devez justifier ces absences dans les plus brefs délais.
        </div>
        
        <p>Pour justifier vos absences, veuillez :</p>
        <ol>
            <li>Vous connecter à votre espace étudiant</li>
            <li>Accéder à la section "Justifier une absence"</li>
            <li>Soumettre les justificatifs nécessaires (certificat médical, attestation, etc.)</li>
        </ol>
        
        <div style="text-align: center;">
            <a href="#" class="button">Justifier mes absences</a>
        </div>
        
        <p><span class="deadline">Important :</span> Si vous ne soumettez pas de justificatif, 
        vous recevrez un rappel dans 24 heures.</p>
    </div>
    
    <div class="footer">
        <p>Cet email a été envoyé automatiquement par le système de gestion des absences de l'UPHF.<br>
        Pour toute question, veuillez contacter le secrétariat pédagogique.</p>
    </div>
</body>
</html>
HTML;

    return $html;
}

/**
 * Generate the HTML email for 24h reminder notification
 */
function generateReminderEmail($student, $absenceDetails)
{
    $firstName = htmlspecialchars($student['first_name']);
    $lastName = htmlspecialchars($student['last_name']);

    $absenceList = '';
    foreach ($absenceDetails as $absence) {
        $date = date('d/m/Y', strtotime($absence['course_date']));
        $startTime = substr($absence['start_time'], 0, 5);
        $endTime = substr($absence['end_time'], 0, 5);
        $courseName = htmlspecialchars($absence['course_name'] ?? 'Non spécifié');
        $courseType = htmlspecialchars($absence['course_type']);

        $absenceList .= "<li><strong>{$date}</strong> - {$startTime} à {$endTime} - {$courseName} ({$courseType})</li>";
    }

    $totalAbsences = count($absenceDetails);

    $html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #003366;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo {
            margin: 10px;
        }
        .content {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-box {
            background-color: #f8d7da;
            border-left: 4px solid #d9534f;
            padding: 15px;
            margin: 20px 0;
        }
        .absence-list {
            background-color: white;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .absence-list ul {
            list-style-type: none;
            padding-left: 0;
        }
        .absence-list li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .button {
            display: inline-block;
            background-color: #d9534f;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            text-align: center;
        }
        .footer {
            text-align: center;
            font-size: 0.9em;
            color: #666;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        h1 {
            color: #d9534f;
        }
        .urgent {
            color: #d9534f;
            font-weight: bold;
            font-size: 1.1em;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="cid:logoUPHF" alt="Logo UPHF" class="logo" width="220" height="80">
        <img src="cid:logoIUT" alt="Logo IUT" class="logo" width="100" height="90">
    </div>
    
    <div class="content">
        <h1>⚠️ Rappel - Justification d'absences urgente</h1>
        
        <p>Bonjour {$firstName} {$lastName},</p>
        
        <div class="alert-box">
            <strong>RAPPEL URGENT :</strong> Il y a 24 heures, nous vous avons informé(e) de votre retour en cours 
            et de la nécessité de justifier vos absences. À ce jour, nous n'avons toujours pas reçu de justificatif.
        </div>
        
        <p>Pour rappel, vous avez les absences non justifiées suivantes :</p>
        
        <div class="absence-list">
            <h3>Absences à justifier ({$totalAbsences} cours) :</h3>
            <ul>
                {$absenceList}
            </ul>
        </div>
        
        <p class="urgent">⚠️ Veuillez justifier ces absences au plus vite pour éviter toute sanction disciplinaire.</p>
        
        <p>Pour justifier vos absences :</p>
        <ol>
            <li>Connectez-vous à votre espace étudiant</li>
            <li>Accédez à "Justifier une absence"</li>
            <li>Soumettez les documents justificatifs (certificat médical, attestation, etc.)</li>
        </ol>
        
        <div style="text-align: center;">
            <a href="#" class="button">Justifier mes absences maintenant</a>
        </div>
        
        <p><strong>Note importante :</strong> Les absences non justifiées peuvent avoir un impact sur votre scolarité 
        et votre validation de semestre. En cas de difficulté pour obtenir un justificatif, contactez rapidement 
        le secrétariat pédagogique.</p>
    </div>
    
    <div class="footer">
        <p>Cet email est un rappel automatique du système de gestion des absences de l'UPHF.<br>
        Pour toute question ou difficulté, veuillez contacter le secrétariat pédagogique dans les plus brefs délais.</p>
    </div>
</body>
</html>
HTML;

    return $html;
}
