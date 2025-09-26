<?php

require_once __DIR__ . '/../Model/database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    session_start();

    $db = getDatabase();
    $_SESSION['reason_data'] = array(
        'datetime_start' => $_POST['datetime_start'] ?? '',
        'datetime_end' => $_POST['datetime_end'] ?? '',
        'class_involved' => $_POST['class_involved'] ?? '',
        'absence_reason' => $_POST['absence_reason'] ?? '',
        'other_reason' => $_POST['other_reason'] ?? '',
        'proof_file' => $_FILES['proof_file']['name'] ?? '',
        'comments' => $_POST['comments'] ?? '',
        'submission_date' => date('Y-m-d H:i:s')
    );

    // Check if the start and end time of the proof are corresponding to absence times

    // Fetch absences for the student

    //POUR PLUS QUAND LE SYSTEME DE CONNEXION SERA FAIT
    // $studentId = $_SESSION['user_id'];

    $studentId = 1; // Temporary hardcoded student ID for testing

    $sql = "SELECT start_time, end_time FROM absences WHERE student_id = :student_id";
    $params = ['student_id' => $studentId];
    $absences = $db->select($sql, $params);

    $datetime_start = $_SESSION['reason_data']['datetime_start'];
    $datetime_end = $_SESSION['reason_data']['datetime_end'];

    $isValid = false;

    foreach ($absences as $absence) {
        if ($datetime_start >= $absence['start_time'] && $datetime_end <= $absence['end_time']) {
            $isValid = true;
            break;
        }
    }

    // If valid, store the proof in the database and show success message
    if ($isValid) {
        $db->beginTransaction();
        try {
            $sql = "INSERT INTO proofs (student_id, datetime_start, datetime_end, class_involved, absence_reason, other_reason, proof_file, comments, submission_date) 
                    VALUES (:student_id, :datetime_start, :datetime_end, :class_involved, :
                            absence_reason, :other_reason, :proof_file, :comments, :submission_date)";
            $params = [
                'student_id' => $studentId,
                'datetime_start' => $datetime_start,
                'datetime_end' => $datetime_end,
                'class_involved' => $_SESSION['reason_data']['class_involved'],
                'absence_reason' => $_SESSION['reason_data']['absence_reason'],
                'other_reason' => $_SESSION['reason_data']['other_reason'],
                'proof_file' => $_SESSION['reason_data']['proof_file'],
                'comments' => $_SESSION['reason_data']['comments'],
                'submission_date' => $_SESSION['reason_data']['submission_date']
            ];

            // Send the email of validation to the student
            

            $db->execute($sql, $params);
            $db->commit();
            header("Location: ../View/templates/validation_student_proof.php");
            exit();
        } catch (Exception $e) {
            $db->rollBack();
            echo "Error submitting proof: " . $e->getMessage();
        }
    } else {
        echo "Invalid proof submission.";
    }
}
?>