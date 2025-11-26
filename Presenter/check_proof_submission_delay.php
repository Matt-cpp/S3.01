<?php

/**
 * Fichier: check_proof_submission_delay.php
 * 
 * API de vérification du délai de soumission - Vérifie si un étudiant a dépassé le délai pour soumettre un justificatif.
 * Calcule le délai de 48h (2 jours ouvrés) après le retour en cours de l'étudiant.
 * Renvoie un avertissement JSON si le délai est dépassé.
 * Utilisé par AJAX lors de la soumission d'un justificatif.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../Model/database.php';

try {
    $db = getDatabase();

    // Get student ID from request
    $student_id = $_GET['student_id'] ?? 1; // Default to 1

    // Get the student's identifier from the users table
    $sql_user = "SELECT identifier FROM users WHERE id = :student_id";
    $user = $db->selectOne($sql_user, ['student_id' => $student_id]);

    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'error' => 'Student not found',
            'show_warning' => false
        ]);
        exit;
    }

    $student_identifier = $user['identifier'];

    // Get the last absence of the student that is NOT already linked to a proof (justified or in process)
    // We only consider truly unjustified absences (no proof submitted yet)
    $sql_last_absence = "
        SELECT 
            cs.course_date,
            cs.end_time,
            (cs.course_date + cs.end_time)::timestamp as last_absence_datetime
        FROM absences a
        JOIN course_slots cs ON a.course_slot_id = cs.id
        LEFT JOIN proof_absences pa ON a.id = pa.absence_id
        WHERE a.student_identifier = :student_identifier
            AND a.status = 'absent'
            AND a.justified = FALSE
            AND pa.absence_id IS NULL
        ORDER BY cs.course_date DESC, cs.end_time DESC
        LIMIT 1
    ";

    $last_absence = $db->selectOne($sql_last_absence, ['student_identifier' => $student_identifier]);

    if (!$last_absence) {
        // No absences found, no warning needed
        echo json_encode([
            'success' => true,
            'show_warning' => false,
            'message' => 'Aucune absence trouvée'
        ]);
        exit;
    }

    $last_absence_datetime = new DateTime($last_absence['last_absence_datetime']);
    $current_datetime = new DateTime();

    // Calculate the return to class date (skip weekends)
    $return_date = clone $last_absence_datetime;

    // If last absence is on Friday, return date is Monday
    if ($return_date->format('N') == 5) { // Friday
        $return_date->modify('+3 days'); // Skip to Monday
    }
    // If last absence is on Saturday (shouldn't happen normally)
    elseif ($return_date->format('N') == 6) {
        $return_date->modify('+2 days'); // Skip to Monday
    }
    // If last absence is on Sunday (shouldn't happen normally)
    elseif ($return_date->format('N') == 7) {
        $return_date->modify('+1 day'); // Skip to Monday
    }
    // For weekdays, next day is the return date
    else {
        $return_date->modify('+1 day');
    }

    // Set return date to start of the day (8:00 AM)
    $return_date->setTime(8, 0, 0);

    // Calculate 48 hours after return (in working hours)
    // 48h after return = 2 working days after return
    $deadline_date = clone $return_date;
    $days_to_add = 2;
    $days_added = 0;

    while ($days_added < $days_to_add) {
        $deadline_date->modify('+1 day');
        // Skip weekends
        if ($deadline_date->format('N') < 6) { // Not Saturday or Sunday
            $days_added++;
        }
    }

    // Check if current date is after the 48h deadline
    $show_warning = $current_datetime > $deadline_date;

    // Format dates for display
    $last_absence_str = $last_absence_datetime->format('d/m/Y à H:i');
    $return_str = $return_date->format('d/m/Y');
    $deadline_str = $deadline_date->format('d/m/Y à H:i');

    // Generate warning message
    $warning_message = '';
    if ($show_warning) {
        $warning_message = "⚠️ <strong>ATTENTION - Délai de soumission dépassé</strong><br><br>";
        $warning_message .= "Votre dernière absence <u>non justifiée</u> remonte au <strong>{$last_absence_str}</strong>.<br>";
        $warning_message .= "Vous êtes revenu en cours le <strong>{$return_str}</strong>.<br>";
        $warning_message .= "Le délai de 48h (2 jours ouvrés) après votre retour était le <strong>{$deadline_str}</strong>.<br><br>";
        $warning_message .= "<strong style='color: #d32f2f;'>⏰ Ce délai étant dépassé, il y a un risque important que votre justificatif ne soit pas pris en compte.</strong><br><br>";
        $warning_message .= "Vous pouvez tout de même soumettre votre justificatif, mais il sera soumis à l'appréciation de l'administration.<br><br>";
        $warning_message .= "<small style='color: #666;'>ℹ️ Note : Ce calcul ne prend en compte que vos absences non justifiées (sans justificatif déjà soumis).</small>";
    }

    echo json_encode([
        'success' => true,
        'show_warning' => $show_warning,
        'last_absence_date' => $last_absence_str,
        'return_date' => $return_str,
        'deadline_date' => $deadline_str,
        'current_date' => $current_datetime->format('d/m/Y à H:i'),
        'warning_message' => $warning_message,
        'debug' => [
            'last_absence_datetime' => $last_absence_datetime->format('Y-m-d H:i:s'),
            'return_date' => $return_date->format('Y-m-d H:i:s'),
            'deadline_date' => $deadline_date->format('Y-m-d H:i:s'),
            'current_datetime' => $current_datetime->format('Y-m-d H:i:s'),
            'is_after_deadline' => $show_warning
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erreur serveur: ' . $e->getMessage(),
        'show_warning' => false
    ]);
}
