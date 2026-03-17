<?php

declare(strict_types=1);

/**
 * File: get_proof_for_edit.php
 *
 * Proof edit preparation – Loads proof data for modification.
 * Main features:
 * - Authorization check (proof must be under review and belong to the student)
 * - Retrieves proof information from the database
 * - Loads existing files (from proof_files JSONB)
 * - Formats dates for the HTML5 datetime-local form input
 * - Stores data in session for the edit form
 * Redirects to the proof_edit.php form with prepared data.
 */

session_start();

require_once __DIR__ . '/../../Model/ProofModel.php';
require_once __DIR__ . '/../../Model/UserModel.php';

if (!isset($_GET['proof_id'])) {
    $_SESSION['error_message'] = 'Aucun justificatif spécifié.';
    header('Location: ../../View/templates/student/proofs.php');
    exit();
}

$proofId = (int) $_GET['proof_id'];

try {
    $proofModel = new ProofModel();
    $userModel = new UserModel();
    $proof = $proofModel->getProofForEdit($proofId);

    if (!$proof) {
        $_SESSION['error_message'] = 'Justificatif non trouvé.';
        header('Location: ../../View/templates/student/proofs.php');
        exit();
    }

    if ($proof['status'] !== 'under_review') {
        $_SESSION['error_message'] = 'Seuls les justificatifs en révision peuvent être modifiés.';
        header('Location: ../../View/templates/student/proofs.php');
        exit();
    }

    if (!isset($_SESSION['id_student'])) {
        $_SESSION['error_message'] = 'Veuillez vous connecter pour accéder à cette page.';
        header('Location: ../../View/templates/shared/login.php');
        exit();
    }

    $studentId = $_SESSION['id_student'];
    $studentInfo = $userModel->getUserById((int) $studentId);

    if (!$studentInfo || $proof['student_identifier'] !== $studentInfo['identifier']) {
        $_SESSION['error_message'] = "Vous n'êtes pas autorisé à modifier ce justificatif.";
        header('Location: ../../View/templates/student/proofs.php');
        exit();
    }

    // Format dates for datetime-local input
    $startDate = $proof['absence_start_date'];
    $endDate = $proof['absence_end_date'];

    if (strlen($startDate) === 10) {
        $startDate .= 'T08:00';
    } else {
        $startDate = date('Y-m-d\TH:i', strtotime($startDate));
    }

    if (strlen($endDate) === 10) {
        $endDate .= 'T18:00';
    } else {
        $endDate = date('Y-m-d\TH:i', strtotime($endDate));
    }

    $proofFiles = [];
    if (!empty($proof['proof_files'])) {
        if (is_array($proof['proof_files'])) {
            $proofFiles = $proof['proof_files'];
        } else {
            $decoded = json_decode($proof['proof_files'], true);
            $proofFiles = is_array($decoded) ? $decoded : [];
        }
    }

    $_SESSION['edit_proof'] = [
        'proof_id' => $proof['id'],
        'datetime_start' => $startDate,
        'datetime_end' => $endDate,
        'absence_reason' => $proof['main_reason'],
        'other_reason' => $proof['custom_reason'] ?? '',
        'comments' => $proof['student_comment'] ?? '',
        'manager_comment' => $proof['manager_comment'] ?? '',
        'class_involved' => $proof['concerned_courses'] ?? '',
        'existing_file_path' => $proof['file_path'],
        'existing_files' => $proofFiles
    ];

    header('Location: ../../View/templates/student/proof_edit.php');
    exit();
} catch (Exception $e) {
    error_log('Error in get_proof_for_edit.php: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Une erreur est survenue lors du chargement du justificatif.';
    header('Location: ../../View/templates/student/proofs.php');
    exit();
}
