<?php

/**
 * Fichier: student_get_info.php
 * 
 * Service de récupération d'informations étudiant - Fournit les statistiques et données d'un étudiant.
 * Fonctions principales:
 * - getStudentIdentifier(): Récupère l'identifiant d'un étudiant
 * - getAbsenceStatistics(): Calcule les stats d'absences (heures, justifiées, non justifiées)
 * - getRecentAbsences(): Récupère les dernières absences
 * - getProofsByCategory(): Récupère les justificatifs classés par statut
 * Utilisé pour le tableau de bord étudiant et le cache de session.
 */

require_once __DIR__ . '/../Model/database.php';

// Fonction pour obtenir l'identifier d'un étudiant à partir de son ID ou identifier car on récupère les infos de grâce à cela
function getStudentIdentifier($student_id_or_identifier)
{
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

    // OPTIMISATION: Requête unique pour obtenir toutes les statistiques d'absences en une fois
    // Utilisation de DISTINCT ON pour éviter les doublons si plusieurs proofs sont liés à la même absence
    $stmt = $db->prepare("
        WITH absence_stats AS (
            SELECT DISTINCT ON (a.id)
                a.id,
                cs.duration_minutes,
                cs.course_date,
                a.justified as absence_justified,
                p.status as proof_status
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            LEFT JOIN proof_absences pa ON a.id = pa.absence_id
            LEFT JOIN proof p ON pa.proof_id = p.id
            WHERE a.student_identifier = :student_id
            ORDER BY a.id,
                CASE
                    WHEN p.status = 'accepted' THEN 1
                    WHEN a.justified = TRUE THEN 2
                    ELSE 3
                END ASC
        )
        SELECT 
            -- Heures ratées ce mois
            COALESCE(SUM(CASE 
                WHEN EXTRACT(MONTH FROM course_date) = EXTRACT(MONTH FROM CURRENT_DATE)
                AND EXTRACT(YEAR FROM course_date) = EXTRACT(YEAR FROM CURRENT_DATE)
                THEN duration_minutes / 60.0 
                ELSE 0 
            END), 0) as hour_month,
            
            -- Total heures absences
            COALESCE(SUM(duration_minutes / 60.0), 0) as total_hours_absences,
            
            -- Total heures justifiées
            COALESCE(SUM(CASE 
                WHEN absence_justified = TRUE OR proof_status = 'accepted'
                THEN duration_minutes / 60.0 
                ELSE 0 
            END), 0) as hour_total_justified,
            
            -- Total nombre d'absences
            COUNT(id) as total_absences_count
        FROM absence_stats
    ");
    $stmt->execute(['student_id' => $student_identifier]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // OPTIMISATION: Requête unique pour tous les compteurs de justificatifs
    $stmt = $db->prepare("
        SELECT 
            COUNT(CASE WHEN status = 'under_review' THEN 1 END) as under_review_proofs,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_proofs,
            COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_proofs,
            COUNT(CASE WHEN status = 'accepted' THEN 1 END) as accepted_proofs
        FROM proof
        WHERE student_identifier = :student_id
    ");
    $stmt->execute(['student_id' => $student_identifier]);
    $proof_counts = $stmt->fetch(PDO::FETCH_ASSOC);

    // Calcul heures non justifiées
    $hour_total_unjustified = max(0, $stats['total_hours_absences'] - $stats['hour_total_justified']);

    return [
        'hour_month' => round($stats['hour_month'], 2),
        'hour_total_justified' => round($stats['hour_total_justified'], 4),
        'hour_total_unjustified' => round($hour_total_unjustified, 2),
        'total_hours_absences' => round($stats['total_hours_absences'], 2),
        'total_absences_count' => (int)$stats['total_absences_count'],
        'under_review_proofs' => (int)$proof_counts['under_review_proofs'],
        'pending_proofs' => (int)$proof_counts['pending_proofs'],
        'rejected_proofs' => (int)$proof_counts['rejected_proofs'],
        'accepted_proofs' => (int)$proof_counts['accepted_proofs']
    ];
}

function getRecentAbsences($student_identifier, $limit = 5)
{
    $student_identifier = getStudentIdentifier($student_identifier);
    $db = Database::getInstance()->getConnection();

    // Utiliser une sous-requête pour d'abord obtenir les absences les plus récentes,
    // puis appliquer la logique de priorité des preuves
    $stmt = $db->prepare("
        WITH recent_absences AS (
            SELECT DISTINCT ON (a.id)
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
                rm.code as room_name,
                p.status as proof_status,
                CASE 
                    WHEN p.status = 'accepted' THEN 1
                    WHEN p.status = 'under_review' THEN 2
                    WHEN p.status = 'pending' THEN 3
                    WHEN p.status = 'rejected' THEN 4
                    ELSE 5
                END as status_priority
            FROM absences a
            JOIN course_slots cs ON a.course_slot_id = cs.id
            LEFT JOIN resources r ON cs.resource_id = r.id
            LEFT JOIN teachers t ON cs.teacher_id = t.id
            LEFT JOIN rooms rm ON cs.room_id = rm.id
            LEFT JOIN proof_absences pa ON a.id = pa.absence_id
            LEFT JOIN proof p ON pa.proof_id = p.id
            WHERE a.student_identifier = :student_id
            ORDER BY a.id, status_priority ASC
        )
        SELECT * FROM recent_absences
        ORDER BY course_date DESC, start_time DESC
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
