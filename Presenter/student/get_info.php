<?php

declare(strict_types=1);

/**
 * File: get_info.php
 *
 * Student information retrieval service – Provides statistics and data for the dashboard.
 * Main functions:
 * - getStudentIdentifier(): Retrieves a student's identifier from their user ID
 * - getAbsenceStatistics(): Computes full absence stats
 *   - Total absence count (missed courses)
 *   - Half-day calculation (morning/afternoon) with deduplication
 *   - Justified/unjustified/justifiable half-days
 *   - Total hours missed
 * - getRecentAbsences(): Retrieves latest absences with details
 * - getProofsByCategory(): Retrieves proofs sorted by status
 * Used for the student dashboard and session cache.
 */

require_once __DIR__ . '/../../Model/database.php';

/**
 * Get a student's identifier from their user ID or return it if already an identifier
 */
function getStudentIdentifier(mixed $studentIdOrIdentifier): string
{
    if (!is_numeric($studentIdOrIdentifier)) {
        return $studentIdOrIdentifier;
    }

    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare('SELECT identifier, first_name, last_name FROM users WHERE id = :id');
    $stmt->execute([':id' => $studentIdOrIdentifier]);
    $result = $stmt->fetch();

    if ($result) {
        $_SESSION['first_name'] = $result['first_name'];
        $_SESSION['last_name'] = $result['last_name'];
        return $result['identifier'];
    }

    throw new Exception('Student not found');
}

function getAbsenceStatistics(mixed $studentIdentifier): array
{
    $studentIdentifier = getStudentIdentifier($studentIdentifier);
    $db = Database::getInstance()->getConnection();

    // Count total absences (missed courses)
    $stmt = $db->prepare('
        SELECT COUNT(*) as total_absences_count
        FROM absences
        WHERE student_identifier = :student_id
    ');
    $stmt->execute([':student_id' => $studentIdentifier]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Calculate half-days in a separate query
    $stmt2 = $db->prepare("
        WITH absence_stats AS (
            SELECT DISTINCT ON (a.id)
                a.id,
                cs.course_date,
                cs.start_time,
                a.justified as absence_justified,
                p.status as proof_status,
                pa.proof_id as has_proof
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
        ),
        half_day_calc AS (
            SELECT 
                course_date,
                CASE 
                    WHEN start_time < '12:30:00' THEN 'morning'
                    ELSE 'afternoon'
                END as period,
                MAX(CASE WHEN proof_status = 'accepted' THEN 1 ELSE 0 END) as is_justified,
                MAX(CASE WHEN (has_proof IS NULL OR proof_status = 'under_review') THEN 1 ELSE 0 END) as is_justifiable
            FROM absence_stats
            GROUP BY course_date, period
        )
        SELECT 
            COUNT(*) as total_half_days,
            SUM(is_justified) as half_days_justified,
            SUM(1 - is_justified) as half_days_unjustified,
            SUM(is_justifiable) as half_days_justifiable,
            SUM(CASE 
                WHEN EXTRACT(MONTH FROM course_date) = EXTRACT(MONTH FROM CURRENT_DATE)
                AND EXTRACT(YEAR FROM course_date) = EXTRACT(YEAR FROM CURRENT_DATE)
                THEN 1 
                ELSE 0 
            END) as half_days_this_month
        FROM half_day_calc
    ");
    $stmt2->execute([':student_id' => $studentIdentifier]);
    $halfDayStats = $stmt2->fetch(PDO::FETCH_ASSOC);

    // Single query for all proof counters
    $stmt = $db->prepare("
        SELECT 
            COUNT(CASE WHEN status = 'under_review' THEN 1 END) as under_review_proofs,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_proofs,
            COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_proofs,
            COUNT(CASE WHEN status = 'accepted' THEN 1 END) as accepted_proofs
        FROM proof
        WHERE student_identifier = :student_id
    ");
    $stmt->execute([':student_id' => $studentIdentifier]);
    $proofCounts = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'total_absences_count' => (int) $stats['total_absences_count'],
        'total_half_days' => (int) ($halfDayStats['total_half_days'] ?? 0),
        'half_days_justified' => (int) ($halfDayStats['half_days_justified'] ?? 0),
        'half_days_unjustified' => (int) ($halfDayStats['half_days_unjustified'] ?? 0),
        'half_days_justifiable' => (int) ($halfDayStats['half_days_justifiable'] ?? 0),
        'half_days_this_month' => (int) ($halfDayStats['half_days_this_month'] ?? 0),
        'under_review_proofs' => (int) $proofCounts['under_review_proofs'],
        'pending_proofs' => (int) $proofCounts['pending_proofs'],
        'rejected_proofs' => (int) $proofCounts['rejected_proofs'],
        'accepted_proofs' => (int) $proofCounts['accepted_proofs']
    ];
}

function getRecentAbsences(mixed $studentIdentifier, int $limit = 5): array
{
    $studentIdentifier = getStudentIdentifier($studentIdentifier);
    $db = Database::getInstance()->getConnection();

    // Use subquery to get most recent absences first, then apply proof priority
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
                m.id as makeup_id,
                m.scheduled as makeup_scheduled,
                m.makeup_date as makeup_date,
                m.comment as makeup_comment,
                m.duration_minutes as makeup_duration,
                makeup_rm.code as makeup_room,
                makeup_cs.start_time as makeup_start_time,
                makeup_cs.end_time as makeup_end_time,
                makeup_r.label as makeup_resource_label,
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
            LEFT JOIN makeups m ON a.id = m.absence_id
            LEFT JOIN rooms makeup_rm ON m.room_id = makeup_rm.id
            LEFT JOIN course_slots makeup_cs ON m.evaluation_slot_id = makeup_cs.id
            LEFT JOIN resources makeup_r ON makeup_cs.resource_id = makeup_r.id
            WHERE a.student_identifier = :student_id
            ORDER BY a.id, status_priority ASC
        )
        SELECT * FROM recent_absences
        ORDER BY course_date DESC, start_time DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':student_id', $studentIdentifier, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getProofsByCategory(mixed $studentIdentifier): array
{
    $studentIdentifier = getStudentIdentifier($studentIdentifier);
    $db = Database::getInstance()->getConnection();

    // Proofs under review (additional info requested)
    $stmt = $db->prepare("
        SELECT 
            p.id as proof_id,
            p.absence_start_date,
            p.absence_end_date,
            p.main_reason,
            p.custom_reason,
            p.student_comment,
            p.submission_date,
            p.manager_comment,
            p.proof_files,
            COUNT(DISTINCT pa.absence_id) as nb_absences,
            COALESCE(SUM(cs.duration_minutes) / 60.0, 0) as total_hours_missed,
            BOOL_OR(cs.is_evaluation) as has_exam,
            STRING_AGG(DISTINCT r.code, ', ') as course_codes,
            STRING_AGG(DISTINCT r.label, ' | ') as course_names,
            COUNT(DISTINCT (cs.course_date, CASE WHEN cs.start_time < '12:30:00' THEN 'morning' ELSE 'afternoon' END)) as half_days_count,
            MIN(cs.course_date || ' ' || cs.start_time) as absence_start_datetime,
            MAX(cs.course_date || ' ' || cs.end_time) as absence_end_datetime
        FROM proof p
        LEFT JOIN proof_absences pa ON pa.proof_id = p.id
        LEFT JOIN absences a ON a.id = pa.absence_id
        LEFT JOIN course_slots cs ON cs.id = a.course_slot_id
        LEFT JOIN resources r ON r.id = cs.resource_id
        WHERE p.student_identifier = :student_id
        AND p.status = 'under_review'
        GROUP BY p.id, p.absence_start_date, p.absence_end_date, p.main_reason, 
        p.custom_reason, p.submission_date, p.manager_comment, p.proof_files
        ORDER BY p.submission_date DESC
    ");
    $stmt->execute([':student_id' => $studentIdentifier]);
    $underReview = $stmt->fetchAll();

    // Proofs pending validation
    $stmt = $db->prepare("
        SELECT 
            p.id as proof_id,
            p.absence_start_date,
            p.absence_end_date,
            p.main_reason,
            p.custom_reason,
            p.student_comment,
            p.submission_date,
            p.proof_files,
            COUNT(DISTINCT pa.absence_id) as nb_absences,
            COALESCE(SUM(cs.duration_minutes) / 60.0, 0) as total_hours_missed,
            BOOL_OR(cs.is_evaluation) as has_exam,
            COUNT(DISTINCT (cs.course_date, CASE WHEN cs.start_time < '12:30:00' THEN 'morning' ELSE 'afternoon' END)) as half_days_count,
            MIN(cs.course_date || ' ' || cs.start_time) as absence_start_datetime,
            MAX(cs.course_date || ' ' || cs.end_time) as absence_end_datetime
        FROM proof p
        LEFT JOIN proof_absences pa ON pa.proof_id = p.id
        LEFT JOIN absences a ON a.id = pa.absence_id
        LEFT JOIN course_slots cs ON cs.id = a.course_slot_id
        WHERE p.student_identifier = :student_id
        AND p.status = 'pending'
        GROUP BY p.id, p.absence_start_date, p.absence_end_date, p.main_reason, 
        p.custom_reason, p.submission_date, p.proof_files
        ORDER BY p.submission_date DESC
    ");
    $stmt->execute([':student_id' => $studentIdentifier]);
    $pending = $stmt->fetchAll();

    // Accepted proofs (most recent)
    $stmt = $db->prepare("
        SELECT 
            p.id as proof_id,
            p.absence_start_date,
            p.absence_end_date,
            p.main_reason,
            p.custom_reason,
            p.student_comment,
            p.submission_date,
            p.processing_date,
            p.proof_files,
            COUNT(DISTINCT pa.absence_id) as nb_absences,
            COALESCE(SUM(cs.duration_minutes) / 60.0, 0) as total_hours_missed,
            BOOL_OR(cs.is_evaluation) as has_exam,
            STRING_AGG(DISTINCT r.code, ', ') as course_codes,
            STRING_AGG(DISTINCT r.label, ' | ') as course_names,
            COUNT(DISTINCT (cs.course_date, CASE WHEN cs.start_time < '12:30:00' THEN 'morning' ELSE 'afternoon' END)) as half_days_count,
            MIN(cs.course_date || ' ' || cs.start_time) as absence_start_datetime,
            MAX(cs.course_date || ' ' || cs.end_time) as absence_end_datetime
        FROM proof p
        LEFT JOIN proof_absences pa ON pa.proof_id = p.id
        LEFT JOIN absences a ON a.id = pa.absence_id
        LEFT JOIN course_slots cs ON cs.id = a.course_slot_id
        LEFT JOIN resources r ON r.id = cs.resource_id
        WHERE p.student_identifier = :student_id
        AND p.status = 'accepted'
        GROUP BY p.id, p.absence_start_date, p.absence_end_date, p.main_reason, 
        p.custom_reason, p.submission_date, p.processing_date, p.proof_files
        ORDER BY p.processing_date DESC
    ");
    $stmt->execute([':student_id' => $studentIdentifier]);
    $accepted = $stmt->fetchAll();

    // Rejected proofs (most recent)
    $stmt = $db->prepare("
        SELECT 
            p.id as proof_id,
            p.absence_start_date,
            p.absence_end_date,
            p.main_reason,
            p.custom_reason,
            p.student_comment,
            p.submission_date,
            p.processing_date,
            p.manager_comment,
            p.proof_files,
            COUNT(DISTINCT pa.absence_id) as nb_absences,
            COALESCE(SUM(cs.duration_minutes) / 60.0, 0) as total_hours_missed,
            BOOL_OR(cs.is_evaluation) as has_exam,
            STRING_AGG(DISTINCT r.code, ', ') as course_codes,
            STRING_AGG(DISTINCT r.label, ' | ') as course_names,
            COUNT(DISTINCT (cs.course_date, CASE WHEN cs.start_time < '12:30:00' THEN 'morning' ELSE 'afternoon' END)) as half_days_count,
            MIN(cs.course_date || ' ' || cs.start_time) as absence_start_datetime,
            MAX(cs.course_date || ' ' || cs.end_time) as absence_end_datetime
        FROM proof p
        LEFT JOIN proof_absences pa ON pa.proof_id = p.id
        LEFT JOIN absences a ON a.id = pa.absence_id
        LEFT JOIN course_slots cs ON cs.id = a.course_slot_id
        LEFT JOIN resources r ON r.id = cs.resource_id
        WHERE p.student_identifier = :student_id
        AND p.status = 'rejected'
        GROUP BY p.id, p.absence_start_date, p.absence_end_date, p.main_reason, 
        p.custom_reason, p.submission_date, p.processing_date, p.manager_comment, p.proof_files
        ORDER BY p.processing_date DESC
    ");
    $stmt->execute([':student_id' => $studentIdentifier]);
    $rejected = $stmt->fetchAll();

    return [
        'under_review' => $underReview,
        'pending' => $pending,
        'accepted' => $accepted,
        'rejected' => $rejected
    ];
}
