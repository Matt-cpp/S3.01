<?php

require_once __DIR__ . '/../Model/database.php';

// Fonction pour obtenir l'identifier d'un étudiant à partir de son ID ou identifier car on récupère les infos de grâce à cela
function getStudentIdentifier($student_id_or_identifier) {
    if (!is_numeric($student_id_or_identifier)) {
        return $student_id_or_identifier;
    }
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT identifier, first_name, last_name FROM users WHERE id = :id");
    $stmt->execute(['id' => $student_id_or_identifier]);
    $result = $stmt->fetch();
    
    if ($result) {
        $_SESSION['first_name'] = $result['first_name'];
        $_SESSION['last_name'] = $result['last_name'];
        return $result['identifier'];
    }
    
    throw new Exception("Student not found");
}

function getAbsenceStatistics($student_identifier)
{
    $student_identifier = getStudentIdentifier($student_identifier);
    $db = Database::getInstance()->getConnection();
    
    // Heures ratées ce mois
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(cs.duration_minutes / 60.0), 0) as hour_month
        FROM absences a
        JOIN course_slots cs ON a.course_slot_id = cs.id
        WHERE a.student_identifier = :student_id
        AND EXTRACT(MONTH FROM cs.course_date) = EXTRACT(MONTH FROM CURRENT_DATE)
        AND EXTRACT(YEAR FROM cs.course_date) = EXTRACT(YEAR FROM CURRENT_DATE)
    ");
    $stmt->execute(['student_id' => $student_identifier]);
    $hour_month = $stmt->fetch()['hour_month'];
    
    // Total heures ratées justifiées
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(cs.duration_minutes / 60.0), 0) as hour_total_justified
        FROM absences a
        JOIN course_slots cs ON a.course_slot_id = cs.id
        WHERE a.student_identifier = :student_id
        AND a.justified = TRUE
    ");
    $stmt->execute(['student_id' => $student_identifier]);
    $hour_total_justified = $stmt->fetch()['hour_total_justified'];
    
    // Total heures ratées non justifiées (sans justificatif soumis ou avec justificatif rejeté)
    // On utilise DISTINCT ON pour éviter les doublons si une absence a plusieurs justificatifs
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(duration_minutes / 60.0), 0) as hour_total_unjustified
        FROM (
            SELECT DISTINCT ON (a.id) 
                a.id,
                cs.duration_minutes,
                p.status as proof_status
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            LEFT JOIN proof_absences pa ON a.id = pa.absence_id
            LEFT JOIN proof p ON pa.proof_id = p.id
            WHERE a.student_identifier = :student_id
            AND a.justified = FALSE
            ORDER BY a.id, 
                CASE 
                    WHEN p.status = 'accepted' THEN 1
                    WHEN p.status = 'under_review' THEN 2
                    WHEN p.status = 'pending' THEN 3
                    WHEN p.status = 'rejected' THEN 4
                    ELSE 5
                END ASC
        ) subquery
        WHERE proof_status IS NULL OR proof_status = 'rejected'
    ");
    $stmt->execute(['student_id' => $student_identifier]);
    $hour_total_unjustified = $stmt->fetch()['hour_total_unjustified'];
    
    // Demandes d'infos supplémentaires (statut "under_review")
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT p.id) as under_review_proofs
        FROM proof p
        WHERE p.student_identifier = :student_id
        AND p.status = 'under_review'
    ");
    $stmt->execute(['student_id' => $student_identifier]);
    $under_review_proofs = $stmt->fetch()['under_review_proofs'];
    
    // En attente de validation (statut "pending")
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT p.id) as pending_proofs
        FROM proof p
        WHERE p.student_identifier = :student_id
        AND p.status = 'pending'
    ");
    $stmt->execute(['student_id' => $student_identifier]);
    $pending_proofs = $stmt->fetch()['pending_proofs'];
    
    // Justificatifs refusés (statut "rejected")
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT p.id) as rejected_proofs
        FROM proof p
        WHERE p.student_identifier = :student_id
        AND p.status = 'rejected'
    ");
    $stmt->execute(['student_id' => $student_identifier]);
    $rejected_proofs = $stmt->fetch()['rejected_proofs'];
    
    // Justificatifs acceptés (statut "accepted")
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT p.id) as accepted_proofs
        FROM proof p
        WHERE p.student_identifier = :student_id
        AND p.status = 'accepted'
    ");
    $stmt->execute(['student_id' => $student_identifier]);
    $accepted_proofs = $stmt->fetch()['accepted_proofs'];
    
    // Total heures absences
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(cs.duration_minutes / 60.0), 0) as total_hours_absences
        FROM absences a
        JOIN course_slots cs ON a.course_slot_id = cs.id
        WHERE a.student_identifier = :student_id
    ");
    $stmt->execute(['student_id' => $student_identifier]);
    $total_hours_absences = $stmt->fetch()['total_hours_absences'];
    
    // Total nombre d'absences
    $stmt = $db->prepare("
        SELECT COUNT(*) as total_absences_count
        FROM absences a
        WHERE a.student_identifier = :student_id
    ");
    $stmt->execute(['student_id' => $student_identifier]);
    $total_absences_count = $stmt->fetch()['total_absences_count'];
    
    return [
        'hour_month' => round($hour_month, 2),
        'hour_total_justified' => round($hour_total_justified, 2),
        'hour_total_unjustified' => round($hour_total_unjustified, 2),
        'total_hours_absences' => round($total_hours_absences, 2),
        'total_absences_count' => $total_absences_count,
        'under_review_proofs' => $under_review_proofs,
        'pending_proofs' => $pending_proofs,
        'rejected_proofs' => $rejected_proofs,
        'accepted_proofs' => $accepted_proofs
    ];
}

function getRecentAbsences($student_identifier, $limit = 5)
{
    $student_identifier = getStudentIdentifier($student_identifier);
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("
        SELECT 
            a.id as absence_id,
            cs.course_date,
            cs.start_time,
            cs.end_time,
            cs.duration_minutes,
            cs.course_type,
            cs.is_evaluation,
            a.justified,
            r.code as course_code,
            r.label as course_name,
            t.first_name as teacher_first_name,
            t.last_name as teacher_last_name,
            rm.code as room_name
        FROM absences a
        JOIN course_slots cs ON a.course_slot_id = cs.id
        LEFT JOIN resources r ON cs.resource_id = r.id
        LEFT JOIN teachers t ON cs.teacher_id = t.id
        LEFT JOIN rooms rm ON cs.room_id = rm.id
        WHERE a.student_identifier = :student_id
        ORDER BY cs.course_date DESC, cs.start_time DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':student_id', $student_identifier, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getProofsByCategory($student_identifier)
{
    $student_identifier = getStudentIdentifier($student_identifier);
    $db = Database::getInstance()->getConnection();
    
    // Justificatifs en révision (demande d'infos supplémentaires)
    $stmt = $db->prepare("
        SELECT 
            p.id as proof_id,
            p.absence_start_date,
            p.absence_end_date,
            p.main_reason,
            p.custom_reason,
            p.submission_date,
            p.manager_comment,
            COUNT(DISTINCT pa.absence_id) as nb_absences,
            COALESCE(SUM(cs.duration_minutes) / 60.0, 0) as total_hours_missed,
            BOOL_OR(cs.is_evaluation) as has_exam,
            STRING_AGG(DISTINCT r.code, ', ') as course_codes,
            STRING_AGG(DISTINCT r.label, ' | ') as course_names
        FROM proof p
        LEFT JOIN proof_absences pa ON pa.proof_id = p.id
        LEFT JOIN absences a ON a.id = pa.absence_id
        LEFT JOIN course_slots cs ON cs.id = a.course_slot_id
        LEFT JOIN resources r ON r.id = cs.resource_id
        WHERE p.student_identifier = :student_id
        AND p.status = 'under_review'
        GROUP BY p.id, p.absence_start_date, p.absence_end_date, p.main_reason, 
                 p.custom_reason, p.submission_date, p.manager_comment
        ORDER BY p.submission_date DESC
    ");
    $stmt->execute(['student_id' => $student_identifier]);
    $under_review = $stmt->fetchAll();
    
    // Justificatifs en attente de validation
    $stmt = $db->prepare("
        SELECT 
            p.id as proof_id,
            p.absence_start_date,
            p.absence_end_date,
            p.main_reason,
            p.custom_reason,
            p.submission_date,
            COUNT(DISTINCT pa.absence_id) as nb_absences,
            COALESCE(SUM(cs.duration_minutes) / 60.0, 0) as total_hours_missed,
            BOOL_OR(cs.is_evaluation) as has_exam
        FROM proof p
        LEFT JOIN proof_absences pa ON pa.proof_id = p.id
        LEFT JOIN absences a ON a.id = pa.absence_id
        LEFT JOIN course_slots cs ON cs.id = a.course_slot_id
        WHERE p.student_identifier = :student_id
        AND p.status = 'pending'
        GROUP BY p.id, p.absence_start_date, p.absence_end_date, p.main_reason, 
                 p.custom_reason, p.submission_date
        ORDER BY p.submission_date DESC
    ");
    $stmt->execute(['student_id' => $student_identifier]);
    $pending = $stmt->fetchAll();
    
    // Justificatifs validés (les plus récents)
    $stmt = $db->prepare("
        SELECT 
            p.id as proof_id,
            p.absence_start_date,
            p.absence_end_date,
            p.main_reason,
            p.custom_reason,
            p.submission_date,
            p.processing_date,
            COUNT(DISTINCT pa.absence_id) as nb_absences,
            COALESCE(SUM(cs.duration_minutes) / 60.0, 0) as total_hours_missed,
            BOOL_OR(cs.is_evaluation) as has_exam,
            STRING_AGG(DISTINCT r.code, ', ') as course_codes,
            STRING_AGG(DISTINCT r.label, ' | ') as course_names
        FROM proof p
        LEFT JOIN proof_absences pa ON pa.proof_id = p.id
        LEFT JOIN absences a ON a.id = pa.absence_id
        LEFT JOIN course_slots cs ON cs.id = a.course_slot_id
        LEFT JOIN resources r ON r.id = cs.resource_id
        WHERE p.student_identifier = :student_id
        AND p.status = 'accepted'
        GROUP BY p.id, p.absence_start_date, p.absence_end_date, p.main_reason, 
                 p.custom_reason, p.submission_date, p.processing_date
        ORDER BY p.processing_date DESC
    ");
    $stmt->execute(['student_id' => $student_identifier]);
    $accepted = $stmt->fetchAll();
    
    // Justificatifs invalidés (les plus récents)
    $stmt = $db->prepare("
        SELECT 
            p.id as proof_id,
            p.absence_start_date,
            p.absence_end_date,
            p.main_reason,
            p.custom_reason,
            p.submission_date,
            p.processing_date,
            p.manager_comment,
            COUNT(DISTINCT pa.absence_id) as nb_absences,
            COALESCE(SUM(cs.duration_minutes) / 60.0, 0) as total_hours_missed,
            BOOL_OR(cs.is_evaluation) as has_exam,
            STRING_AGG(DISTINCT r.code, ', ') as course_codes,
            STRING_AGG(DISTINCT r.label, ' | ') as course_names
        FROM proof p
        LEFT JOIN proof_absences pa ON pa.proof_id = p.id
        LEFT JOIN absences a ON a.id = pa.absence_id
        LEFT JOIN course_slots cs ON cs.id = a.course_slot_id
        LEFT JOIN resources r ON r.id = cs.resource_id
        WHERE p.student_identifier = :student_id
        AND p.status = 'rejected'
        GROUP BY p.id, p.absence_start_date, p.absence_end_date, p.main_reason, 
                 p.custom_reason, p.submission_date, p.processing_date, p.manager_comment
        ORDER BY p.processing_date DESC
    ");
    $stmt->execute(['student_id' => $student_identifier]);
    $rejected = $stmt->fetchAll();
    
    return [
        'under_review' => $under_review,
        'pending' => $pending,
        'accepted' => $accepted,
        'rejected' => $rejected
    ];
}
?>

