-- =============================================
-- Data Insert and Validation Tests
-- =============================================
-- Tests for inserting data and validating constraints

BEGIN;

SELECT plan(30);

-- =============================================
-- Test: Insert a valid user
-- =============================================
INSERT INTO users (identifier, last_name, first_name, email, role)
VALUES ('TEST001', 'Doe', 'John', 'john.doe@test.com', 'student');

SELECT is(
    (SELECT COUNT(*) FROM users WHERE identifier = 'TEST001'),
    1::bigint,
    'Should insert a valid user successfully'
);

-- =============================================
-- Test: User identifier uniqueness
-- =============================================
SELECT throws_ok(
    $$INSERT INTO users (identifier, last_name, first_name, email, role)
      VALUES ('TEST001', 'Smith', 'Jane', 'jane.smith@test.com', 'student')$$,
    '23505',
    NULL,
    'Should not allow duplicate user identifier'
);

-- =============================================
-- Test: User default role
-- =============================================
INSERT INTO users (identifier, last_name, first_name, email)
VALUES ('TEST002', 'Martin', 'Alice', 'alice.martin@test.com');

SELECT is(
    (SELECT role FROM users WHERE identifier = 'TEST002'),
    'student'::user_role,
    'Default user role should be student'
);

-- =============================================
-- Test: Nullable password and email
-- =============================================
INSERT INTO users (identifier, last_name, first_name, role)
VALUES ('TEST003', 'Brown', 'Bob', 'teacher');

SELECT ok(
    (SELECT password_hash IS NULL FROM users WHERE identifier = 'TEST003'),
    'Password hash can be NULL'
);

SELECT ok(
    (SELECT email IS NULL FROM users WHERE identifier = 'TEST003'),
    'Email can be NULL'
);

-- =============================================
-- Test: Insert a valid group
-- =============================================
INSERT INTO groups (code, label, program, year)
VALUES ('BUT1A', 'BUT Informatique 1A', 'Informatique', 1);

SELECT is(
    (SELECT COUNT(*) FROM groups WHERE code = 'BUT1A'),
    1::bigint,
    'Should insert a valid group successfully'
);

-- =============================================
-- Test: Group code uniqueness
-- =============================================
SELECT throws_ok(
    $$INSERT INTO groups (code, label, program, year)
      VALUES ('BUT1A', 'Another group', 'Math', 1)$$,
    '23505',
    NULL,
    'Should not allow duplicate group code'
);

-- =============================================
-- Test: Insert a valid resource
-- =============================================
INSERT INTO resources (code, label, teaching_type)
VALUES ('R101', 'Initiation au développement', 'TD');

SELECT is(
    (SELECT COUNT(*) FROM resources WHERE code = 'R101'),
    1::bigint,
    'Should insert a valid resource successfully'
);

-- =============================================
-- Test: Insert a valid room
-- =============================================
INSERT INTO rooms (code)
VALUES ('A101');

SELECT is(
    (SELECT COUNT(*) FROM rooms WHERE code = 'A101'),
    1::bigint,
    'Should insert a valid room successfully'
);

-- =============================================
-- Test: Insert a valid teacher
-- =============================================
INSERT INTO teachers (last_name, first_name, email)
VALUES ('Dupont', 'Pierre', 'pierre.dupont@univ.fr');

SELECT is(
    (SELECT COUNT(*) FROM teachers WHERE last_name = 'Dupont'),
    1::bigint,
    'Should insert a valid teacher successfully'
);

-- =============================================
-- Test: Teachers can have NULL emails
-- =============================================
INSERT INTO teachers (last_name, first_name)
VALUES ('Durand', 'Marie');

INSERT INTO teachers (last_name, first_name)
VALUES ('Bernard', 'Paul');

SELECT is(
    (SELECT COUNT(*) FROM teachers WHERE email IS NULL),
    2::bigint,
    'Multiple teachers can have NULL emails'
);

-- =============================================
-- Test: Insert a valid course slot
-- =============================================
INSERT INTO course_slots (
    course_date, start_time, end_time, duration_minutes, course_type,
    resource_id, room_id, teacher_id, group_id
)
VALUES (
    '2025-01-15',
    '08:00:00',
    '10:00:00',
    120,
    'TD',
    (SELECT id FROM resources WHERE code = 'R101'),
    (SELECT id FROM rooms WHERE code = 'A101'),
    (SELECT id FROM teachers WHERE last_name = 'Dupont'),
    (SELECT id FROM groups WHERE code = 'BUT1A')
);

SELECT is(
    (SELECT COUNT(*) FROM course_slots WHERE course_date = '2025-01-15'),
    1::bigint,
    'Should insert a valid course slot successfully'
);

-- =============================================
-- Test: Insert a valid absence
-- =============================================
INSERT INTO absences (student_identifier, course_slot_id, status)
VALUES (
    'TEST001',
    (SELECT id FROM course_slots WHERE course_date = '2025-01-15'),
    'absent'
);

SELECT is(
    (SELECT COUNT(*) FROM absences WHERE student_identifier = 'TEST001'),
    1::bigint,
    'Should insert a valid absence successfully'
);

-- =============================================
-- Test: Absence default status
-- =============================================
INSERT INTO absences (student_identifier, course_slot_id)
VALUES (
    'TEST002',
    (SELECT id FROM course_slots WHERE course_date = '2025-01-15')
);

SELECT is(
    (SELECT status FROM absences WHERE student_identifier = 'TEST002'),
    'absent'::absence_status,
    'Default absence status should be absent'
);

-- =============================================
-- Test: Absence default justified value
-- =============================================
SELECT is(
    (SELECT justified FROM absences WHERE student_identifier = 'TEST002'),
    FALSE,
    'Default justified value should be FALSE'
);

-- =============================================
-- Test: Insert a valid proof
-- =============================================
INSERT INTO proof (
    student_identifier, absence_start_date, absence_end_date,
    main_reason, status
)
VALUES (
    'TEST001',
    '2025-01-15',
    '2025-01-15',
    'illness',
    'pending'
);

SELECT is(
    (SELECT COUNT(*) FROM proof WHERE student_identifier = 'TEST001'),
    1::bigint,
    'Should insert a valid proof successfully'
);

-- =============================================
-- Test: Proof default status
-- =============================================
INSERT INTO proof (
    student_identifier, absence_start_date, absence_end_date,
    main_reason
)
VALUES (
    'TEST002',
    '2025-01-15',
    '2025-01-15',
    'other'
);

SELECT is(
    (SELECT status FROM proof WHERE student_identifier = 'TEST002'),
    'pending'::justification_status,
    'Default proof status should be pending'
);

-- =============================================
-- Test: Proof default locked value
-- =============================================
SELECT is(
    (SELECT locked FROM proof WHERE student_identifier = 'TEST002'),
    FALSE,
    'Default locked value should be FALSE'
);

-- =============================================
-- Test: Insert proof_absences association
-- =============================================
INSERT INTO proof_absences (proof_id, absence_id)
VALUES (
    (SELECT id FROM proof WHERE student_identifier = 'TEST001' LIMIT 1),
    (SELECT id FROM absences WHERE student_identifier = 'TEST001')
);

SELECT is(
    (SELECT COUNT(*) FROM proof_absences),
    1::bigint,
    'Should insert proof_absences association successfully'
);

-- =============================================
-- Test: Proof_absences unique constraint
-- =============================================
SELECT throws_ok(
    $$INSERT INTO proof_absences (proof_id, absence_id)
      VALUES (
          (SELECT id FROM proof WHERE student_identifier = 'TEST001' LIMIT 1),
          (SELECT id FROM absences WHERE student_identifier = 'TEST001')
      )$$,
    '23505',
    NULL,
    'Should not allow duplicate proof_absences associations'
);

-- =============================================
-- Test: Insert decision history
-- =============================================
INSERT INTO decision_history (
    justification_id, user_id, action, old_status, new_status, comment
)
VALUES (
    (SELECT id FROM proof WHERE student_identifier = 'TEST001' LIMIT 1),
    (SELECT id FROM users WHERE identifier = 'TEST001'),
    'accept',
    'pending',
    'accepted',
    'Medical certificate valid'
);

SELECT is(
    (SELECT COUNT(*) FROM decision_history),
    1::bigint,
    'Should insert decision history successfully'
);

-- =============================================
-- Test: Insert notification
-- =============================================
INSERT INTO notifications (
    student_identifier, notification_type, subject, message
)
VALUES (
    'TEST001',
    'absence_detected',
    'Absence detected',
    'You have an absence recorded'
);

SELECT is(
    (SELECT COUNT(*) FROM notifications WHERE student_identifier = 'TEST001'),
    1::bigint,
    'Should insert notification successfully'
);

-- =============================================
-- Test: Notification default sent value
-- =============================================
SELECT is(
    (SELECT sent FROM notifications WHERE student_identifier = 'TEST001'),
    FALSE,
    'Default sent value should be FALSE'
);

-- =============================================
-- Test: Insert user_groups association
-- =============================================
INSERT INTO user_groups (user_id, group_id)
VALUES (
    (SELECT id FROM users WHERE identifier = 'TEST001'),
    (SELECT id FROM groups WHERE code = 'BUT1A')
);

SELECT is(
    (SELECT COUNT(*) FROM user_groups),
    1::bigint,
    'Should insert user_groups association successfully'
);

-- =============================================
-- Test: Insert import job
-- =============================================
INSERT INTO import_jobs (id, filename, filepath, status)
VALUES ('job_001', 'test.csv', '/uploads/test.csv', 'pending');

SELECT is(
    (SELECT COUNT(*) FROM import_jobs WHERE id = 'job_001'),
    1::bigint,
    'Should insert import job successfully'
);

-- =============================================
-- Test: Insert import history
-- =============================================
INSERT INTO import_history (action, details, status)
VALUES ('csv_import', 'Imported 100 students', 'success');

SELECT is(
    (SELECT COUNT(*) FROM import_history WHERE action = 'csv_import'),
    1::bigint,
    'Should insert import history successfully'
);

-- =============================================
-- Test: Foreign key constraint on absences
-- =============================================
SELECT throws_ok(
    $$INSERT INTO absences (student_identifier, course_slot_id, status)
      VALUES ('NONEXISTENT', 1, 'absent')$$,
    '23503',
    NULL,
    'Should not allow absence with non-existent student identifier'
);

SELECT * FROM finish();
ROLLBACK;
