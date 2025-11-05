-- =============================================
-- Data Integrity Tests
-- =============================================
-- Tests for constraints, foreign keys, and data validation

BEGIN;

SELECT plan(24);

-- =============================================
-- Test User Role Enum Values
-- =============================================
SELECT enum_has_labels('user_role', ARRAY['student', 'teacher', 'academic_manager', 'secretary'], 
    'user_role should have all expected values');

-- =============================================
-- Test Course Type Enum Values
-- =============================================
SELECT enum_has_labels('course_type', ARRAY['CM', 'TD', 'TP', 'BEN', 'TPC', 'DS', 'TDC'], 
    'course_type should have all expected values');

-- =============================================
-- Test Absence Status Enum Values
-- =============================================
SELECT enum_has_labels('absence_status', ARRAY['absent', 'present', 'excused', 'unjustified'], 
    'absence_status should have all expected values');

-- =============================================
-- Test Justification Status Enum Values
-- =============================================
SELECT enum_has_labels('justification_status', ARRAY['pending', 'accepted', 'rejected', 'under_review'], 
    'justification_status should have all expected values');

-- =============================================
-- Test Absence Reason Enum Values
-- =============================================
SELECT enum_has_labels('absence_reason', ARRAY['illness', 'death', 'family_obligations', 'other'], 
    'absence_reason should have all expected values');

-- =============================================
-- Test Notification Type Enum Values
-- =============================================
SELECT enum_has_labels('notification_type', 
    ARRAY['absence_detected', 'course_return', 'justification_processed', 'evaluation_alert'], 
    'notification_type should have all expected values');

-- =============================================
-- Test Decision Action Enum Values
-- =============================================
SELECT enum_has_labels('decision_action', ARRAY['accept', 'reject', 'request_info', 'unlock'], 
    'decision_action should have all expected values');

-- =============================================
-- Test Foreign Key Relationships
-- =============================================

-- Test absences references users
SELECT fk_ok('absences', 'student_identifier', 'users', 'identifier', 
    'absences.student_identifier should reference users.identifier');

-- Test absences references course_slots
SELECT fk_ok('absences', 'course_slot_id', 'course_slots', 'id', 
    'absences.course_slot_id should reference course_slots.id');

-- Test proof references users (student)
SELECT fk_ok('proof', 'student_identifier', 'users', 'identifier', 
    'proof.student_identifier should reference users.identifier');

-- Test proof references users (processor)
SELECT fk_ok('proof', 'processed_by_user_id', 'users', 'id', 
    'proof.processed_by_user_id should reference users.id');

-- Test proof_absences references proof
SELECT fk_ok('proof_absences', 'proof_id', 'proof', 'id', 
    'proof_absences.proof_id should reference proof.id');

-- Test proof_absences references absences
SELECT fk_ok('proof_absences', 'absence_id', 'absences', 'id', 
    'proof_absences.absence_id should reference absences.id');

-- Test decision_history references proof
SELECT fk_ok('decision_history', 'justification_id', 'proof', 'id', 
    'decision_history.justification_id should reference proof.id');

-- Test decision_history references users
SELECT fk_ok('decision_history', 'user_id', 'users', 'id', 
    'decision_history.user_id should reference users.id');

-- Test course_slots references resources
SELECT fk_ok('course_slots', 'resource_id', 'resources', 'id', 
    'course_slots.resource_id should reference resources.id');

-- Test course_slots references rooms
SELECT fk_ok('course_slots', 'room_id', 'rooms', 'id', 
    'course_slots.room_id should reference rooms.id');

-- Test course_slots references teachers
SELECT fk_ok('course_slots', 'teacher_id', 'teachers', 'id', 
    'course_slots.teacher_id should reference teachers.id');

-- Test course_slots references groups
SELECT fk_ok('course_slots', 'group_id', 'groups', 'id', 
    'course_slots.group_id should reference groups.id');

-- Test makeups references absences
SELECT fk_ok('makeups', 'absence_id', 'absences', 'id', 
    'makeups.absence_id should reference absences.id');

-- Test makeups references course_slots
SELECT fk_ok('makeups', 'evaluation_slot_id', 'course_slots', 'id', 
    'makeups.evaluation_slot_id should reference course_slots.id');

-- Test makeups references users
SELECT fk_ok('makeups', 'student_identifier', 'users', 'identifier', 
    'makeups.student_identifier should reference users.identifier');

-- Test notifications references users
SELECT fk_ok('notifications', 'student_identifier', 'users', 'identifier', 
    'notifications.student_identifier should reference users.identifier');

-- Test user_groups references users
SELECT fk_ok('user_groups', 'user_id', 'users', 'id', 
    'user_groups.user_id should reference users.id');

-- Test user_groups references groups
SELECT fk_ok('user_groups', 'group_id', 'groups', 'id', 
    'user_groups.group_id should reference groups.id');

SELECT * FROM finish();
ROLLBACK;
