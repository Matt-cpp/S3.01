-- =============================================
-- Cascade and Referential Integrity Tests
-- =============================================
-- Tests for CASCADE behaviors and referential integrity

BEGIN;

SELECT plan(8);

-- =============================================
-- Setup test data
-- =============================================
INSERT INTO users (identifier, last_name, first_name, email, role)
VALUES ('CASCADE001', 'Test', 'Cascade', 'cascade@test.com', 'student');

INSERT INTO groups (code, label, program, year)
VALUES ('CASCGRP', 'Cascade Test Group', 'Test', 1);

INSERT INTO resources (code, label, teaching_type)
VALUES ('CASC01', 'Cascade Resource', 'TD');

INSERT INTO rooms (code)
VALUES ('CASC01');

INSERT INTO teachers (last_name, first_name, email)
VALUES ('CascadeT', 'Teacher', 'cascade.teacher@test.com');

INSERT INTO course_slots (
    course_date, start_time, end_time, duration_minutes, course_type,
    resource_id, room_id, teacher_id, group_id
)
VALUES (
    '2025-02-01',
    '08:00:00',
    '10:00:00',
    120,
    'TD',
    (SELECT id FROM resources WHERE code = 'CASC01'),
    (SELECT id FROM rooms WHERE code = 'CASC01'),
    (SELECT id FROM teachers WHERE last_name = 'CascadeT'),
    (SELECT id FROM groups WHERE code = 'CASCGRP')
);

INSERT INTO absences (student_identifier, course_slot_id, status)
VALUES (
    'CASCADE001',
    (SELECT id FROM course_slots WHERE course_date = '2025-02-01'),
    'absent'
);

INSERT INTO proof (
    student_identifier, absence_start_date, absence_end_date,
    main_reason, status
)
VALUES (
    'CASCADE001',
    '2025-02-01',
    '2025-02-01',
    'illness',
    'pending'
);

INSERT INTO proof_absences (proof_id, absence_id)
VALUES (
    (SELECT id FROM proof WHERE student_identifier = 'CASCADE001'),
    (SELECT id FROM absences WHERE student_identifier = 'CASCADE001')
);

-- =============================================
-- Test: CASCADE DELETE on proof_absences
-- =============================================
SELECT is(
    (SELECT COUNT(*) FROM proof_absences 
     WHERE proof_id = (SELECT id FROM proof WHERE student_identifier = 'CASCADE001')),
    1::bigint,
    'Proof_absences record exists before deleting proof'
);

DELETE FROM proof WHERE student_identifier = 'CASCADE001';

SELECT is(
    (SELECT COUNT(*) FROM proof_absences 
     WHERE proof_id IN (SELECT id FROM proof WHERE student_identifier = 'CASCADE001')),
    0::bigint,
    'Proof_absences should be deleted when proof is deleted (CASCADE)'
);

-- =============================================
-- Test: Cannot delete course_slot with absences
-- =============================================
-- Recreate proof for testing
INSERT INTO proof (
    student_identifier, absence_start_date, absence_end_date,
    main_reason, status
)
VALUES (
    'CASCADE001',
    '2025-02-01',
    '2025-02-01',
    'illness',
    'pending'
);

SELECT throws_ok(
    $$DELETE FROM course_slots WHERE course_date = '2025-02-01'$$,
    '23503',
    NULL,
    'Should not allow deleting course_slot that has absences'
);

-- =============================================
-- Test: Cannot delete user with absences
-- =============================================
SELECT throws_ok(
    $$DELETE FROM users WHERE identifier = 'CASCADE001'$$,
    '23503',
    NULL,
    'Should not allow deleting user that has absences'
);

-- =============================================
-- Test: Can delete absence after removing proof_absences
-- =============================================
DELETE FROM proof WHERE student_identifier = 'CASCADE001';
DELETE FROM absences WHERE student_identifier = 'CASCADE001';

SELECT is(
    (SELECT COUNT(*) FROM absences WHERE student_identifier = 'CASCADE001'),
    0::bigint,
    'Absence should be deleted successfully after removing proof_absences'
);

-- =============================================
-- Test: Can delete course_slot after removing absences
-- =============================================
DELETE FROM course_slots WHERE course_date = '2025-02-01';

SELECT is(
    (SELECT COUNT(*) FROM course_slots WHERE course_date = '2025-02-01'),
    0::bigint,
    'Course_slot should be deleted successfully after removing absences'
);

-- =============================================
-- Test: User_groups cascade behavior
-- =============================================
INSERT INTO user_groups (user_id, group_id)
VALUES (
    (SELECT id FROM users WHERE identifier = 'CASCADE001'),
    (SELECT id FROM groups WHERE code = 'CASCGRP')
);

SELECT is(
    (SELECT COUNT(*) FROM user_groups 
     WHERE user_id = (SELECT id FROM users WHERE identifier = 'CASCADE001')),
    1::bigint,
    'User_groups record exists before deleting user'
);

DELETE FROM user_groups
WHERE user_id IN (SELECT id FROM users WHERE identifier = 'CASCADE001');
DELETE FROM users WHERE identifier = 'CASCADE001';

SELECT is(
    (SELECT COUNT(*) FROM user_groups 
     WHERE user_id IN (SELECT id FROM users WHERE identifier = 'CASCADE001')),
    0::bigint,
    'User_groups should be deleted when user is deleted'
);

SELECT * FROM finish();
ROLLBACK;
