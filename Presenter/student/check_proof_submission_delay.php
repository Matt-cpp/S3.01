<?php

declare(strict_types=1);

/**
 * File: check_proof_submission_delay.php
 *
 * Submission delay check API – Verifies whether a student has exceeded the deadline to submit a proof.
 * Calculation logic:
 * - Retrieves the student's last unjustified absence
 * - Calculates the return-to-class date (excluding weekends)
 * - Adds 48 working hours (2 business days) after return
 * - Compares with current date
 * Returns a JSON warning if the deadline is exceeded or close to expiration.
 * Used by AJAX during proof submission to alert the student.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../Model/UserModel.php';
require_once __DIR__ . '/../../Model/AbsenceModel.php';

try {
    $userModel = new UserModel();
    $absenceModel = new AbsenceModel();

    $studentId = $_GET['student_id'] ?? 1;

    $user = $userModel->getUserById((int) $studentId);

    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'error' => 'Student not found',
            'show_warning' => false
        ]);
        exit;
    }

    $studentIdentifier = $user['identifier'];

    $lastAbsence = $absenceModel->getLastUnjustifiedAbsence($studentIdentifier);

    if (!$lastAbsence) {
        echo json_encode([
            'success' => true,
            'show_warning' => false,
            'message' => 'Aucune absence trouvée'
        ]);
        exit;
    }

    $timezone = new DateTimeZone('Europe/Paris');
    $lastAbsenceDatetime = new DateTime($lastAbsence['last_absence_datetime'], $timezone);
    $currentDatetime = new DateTime('now', $timezone);

    // Calculate the return-to-class date (skip weekends)
    $returnDate = clone $lastAbsenceDatetime;

    if ($returnDate->format('N') == 5) {
        $returnDate->modify('+3 days');
    } elseif ($returnDate->format('N') == 6) {
        $returnDate->modify('+2 days');
    } elseif ($returnDate->format('N') == 7) {
        $returnDate->modify('+1 day');
    } else {
        $returnDate->modify('+1 day');
    }

    $returnDate->setTime(8, 0, 0);

    // Calculate deadline: 2 working days after return
    $deadlineDate = clone $returnDate;
    $daysToAdd = 2;
    $daysAdded = 0;

    while ($daysAdded < $daysToAdd) {
        $deadlineDate->modify('+1 day');
        if ($deadlineDate->format('N') < 6) {
            $daysAdded++;
        }
    }

    $showWarning = $currentDatetime > $deadlineDate;

    $lastAbsenceStr = $lastAbsenceDatetime->format('d/m/Y à H:i');
    $returnStr = $returnDate->format('d/m/Y');
    $deadlineStr = $deadlineDate->format('d/m/Y à H:i');

    $warningMessage = '';
    if ($showWarning) {
        $warningMessage = "⚠️ <strong>ATTENTION - Délai de soumission dépassé</strong><br><br>";
        $warningMessage .= "Votre dernière absence <u>non justifiée</u> remonte au <strong>{$lastAbsenceStr}</strong>.<br>";
        $warningMessage .= "Vous êtes revenu en cours le <strong>{$returnStr}</strong>.<br>";
        $warningMessage .= "Le délai de 48h (2 jours ouvrés) après votre retour était le <strong>{$deadlineStr}</strong>.<br><br>";
        $warningMessage .= "<strong style='color: #d32f2f;'>⏰ Ce délai étant dépassé, il y a un risque important que votre justificatif ne soit pas pris en compte.</strong><br><br>";
        $warningMessage .= "Vous pouvez tout de même soumettre votre justificatif, mais il sera soumis à l'appréciation de l'administration.<br><br>";
        $warningMessage .= "<small style='color: #666;'>ℹ️ Note : Ce calcul ne prend en compte que vos absences non justifiées (sans justificatif déjà soumis).</small>";
    }

    echo json_encode([
        'success' => true,
        'show_warning' => $showWarning,
        'last_absence_date' => $lastAbsenceStr,
        'return_date' => $returnStr,
        'deadline_date' => $deadlineStr,
        'current_date' => $currentDatetime->format('d/m/Y à H:i'),
        'warning_message' => $warningMessage
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error: ' . $e->getMessage(),
        'show_warning' => false
    ]);
}
