-- =============================================
-- Schema Structure Tests
-- =============================================
-- Tests for database schema, tables, and types

BEGIN;

-- Plan the number of tests
SELECT plan(95);

-- =============================================
-- Test ENUM Types Existence
-- =============================================
SELECT has_type('user_role', 'Type user_role should exist');
SELECT has_type('course_type', 'Type course_type should exist');
SELECT has_type('absence_status', 'Type absence_status should exist');
SELECT has_type('justification_status', 'Type justification_status should exist');
SELECT has_type('absence_reason', 'Type absence_reason should exist');
SELECT has_type('notification_type', 'Type notification_type should exist');
SELECT has_type('decision_action', 'Type decision_action should exist');

-- =============================================
-- Test Tables Existence
-- =============================================
SELECT has_table('users', 'Table users should exist');
SELECT has_table('groups', 'Table groups should exist');
SELECT has_table('resources', 'Table resources should exist');
SELECT has_table('rooms', 'Table rooms should exist');
SELECT has_table('teachers', 'Table teachers should exist');
SELECT has_table('course_slots', 'Table course_slots should exist');
SELECT has_table('absences', 'Table absences should exist');
SELECT has_table('proof', 'Table proof should exist');
SELECT has_table('proof_absences', 'Table proof_absences should exist');
SELECT has_table('decision_history', 'Table decision_history should exist');
SELECT has_table('notifications', 'Table notifications should exist');
SELECT has_table('makeups', 'Table makeups should exist');
SELECT has_table('user_groups', 'Table user_groups should exist');
SELECT has_table('import_jobs', 'Table import_jobs should exist');
SELECT has_table('import_history', 'Table import_history should exist');

-- =============================================
-- Test Primary Keys
-- =============================================
SELECT has_pk('users', 'Table users should have a primary key');
SELECT has_pk('groups', 'Table groups should have a primary key');
SELECT has_pk('resources', 'Table resources should have a primary key');
SELECT has_pk('rooms', 'Table rooms should have a primary key');
SELECT has_pk('teachers', 'Table teachers should have a primary key');
SELECT has_pk('course_slots', 'Table course_slots should have a primary key');
SELECT has_pk('absences', 'Table absences should have a primary key');
SELECT has_pk('proof', 'Table proof should have a primary key');
SELECT has_pk('proof_absences', 'Table proof_absences should have a primary key');
SELECT has_pk('decision_history', 'Table decision_history should have a primary key');
SELECT has_pk('notifications', 'Table notifications should have a primary key');
SELECT has_pk('makeups', 'Table makeups should have a primary key');
SELECT has_pk('user_groups', 'Table user_groups should have a primary key');
SELECT has_pk('import_jobs', 'Table import_jobs should have a primary key');
SELECT has_pk('import_history', 'Table import_history should have a primary key');

-- =============================================
-- Test Users Table Columns
-- =============================================
SELECT has_column('users', 'id', 'Table users should have column id');
SELECT has_column('users', 'identifier', 'Table users should have column identifier');
SELECT has_column('users', 'last_name', 'Table users should have column last_name');
SELECT has_column('users', 'first_name', 'Table users should have column first_name');
SELECT has_column('users', 'email', 'Table users should have column email');
SELECT has_column('users', 'password_hash', 'Table users should have column password_hash');
SELECT has_column('users', 'role', 'Table users should have column role');
SELECT has_column('users', 'created_at', 'Table users should have column created_at');
SELECT has_column('users', 'updated_at', 'Table users should have column updated_at');

-- =============================================
-- Test Groups Table Columns
-- =============================================
SELECT has_column('groups', 'id', 'Table groups should have column id');
SELECT has_column('groups', 'code', 'Table groups should have column code');
SELECT has_column('groups', 'label', 'Table groups should have column label');
SELECT has_column('groups', 'program', 'Table groups should have column program');
SELECT has_column('groups', 'year', 'Table groups should have column year');

-- =============================================
-- Test Resources Table Columns
-- =============================================
SELECT has_column('resources', 'id', 'Table resources should have column id');
SELECT has_column('resources', 'code', 'Table resources should have column code');
SELECT has_column('resources', 'label', 'Table resources should have column label');
SELECT has_column('resources', 'teaching_type', 'Table resources should have column teaching_type');
SELECT col_not_null('resources', 'code', 'resources.code should be NOT NULL');

-- =============================================
-- Test Course Slots Table Columns
-- =============================================
SELECT has_column('course_slots', 'id', 'Table course_slots should have column id');
SELECT has_column('course_slots', 'course_date', 'Table course_slots should have column course_date');
SELECT has_column('course_slots', 'start_time', 'Table course_slots should have column start_time');
SELECT has_column('course_slots', 'end_time', 'Table course_slots should have column end_time');
SELECT has_column('course_slots', 'duration_minutes', 'Table course_slots should have column duration_minutes');
SELECT has_column('course_slots', 'course_type', 'Table course_slots should have column course_type');
SELECT has_column('course_slots', 'group_id', 'Table course_slots should have column group_id');
SELECT has_column('course_slots', 'is_evaluation', 'Table course_slots should have column is_evaluation');

-- =============================================
-- Test Absences Table Columns
-- =============================================
SELECT has_column('absences', 'id', 'Table absences should have column id');
SELECT has_column('absences', 'student_identifier', 'Table absences should have column student_identifier');
SELECT has_column('absences', 'course_slot_id', 'Table absences should have column course_slot_id');
SELECT has_column('absences', 'status', 'Table absences should have column status');
SELECT has_column('absences', 'justified', 'Table absences should have column justified');

-- =============================================
-- Test Proof Table Columns
-- =============================================
SELECT has_column('proof', 'id', 'Table proof should have column id');
SELECT has_column('proof', 'student_identifier', 'Table proof should have column student_identifier');
SELECT has_column('proof', 'absence_start_date', 'Table proof should have column absence_start_date');
SELECT has_column('proof', 'absence_end_date', 'Table proof should have column absence_end_date');
SELECT has_column('proof', 'main_reason', 'Table proof should have column main_reason');
SELECT has_column('proof', 'status', 'Table proof should have column status');
SELECT has_column('proof', 'locked', 'Table proof should have column locked');
SELECT hasnt_column('proof', 'absence_id', 'Table proof should NOT have column absence_id (moved to proof_absences)');

-- =============================================
-- Test Proof Absences Table Columns
-- =============================================
SELECT has_column('proof_absences', 'id', 'Table proof_absences should have column id');
SELECT has_column('proof_absences', 'proof_id', 'Table proof_absences should have column proof_id');
SELECT has_column('proof_absences', 'absence_id', 'Table proof_absences should have column absence_id');

-- =============================================
-- Test Foreign Keys
-- =============================================
SELECT has_fk('absences', 'Table absences should have foreign key constraints');
SELECT has_fk('proof', 'Table proof should have foreign key constraints');
SELECT has_fk('proof_absences', 'Table proof_absences should have foreign key constraints');
SELECT has_fk('decision_history', 'Table decision_history should have foreign key constraints');
SELECT has_fk('course_slots', 'Table course_slots should have foreign key constraints');
SELECT has_fk('makeups', 'Table makeups should have foreign key constraints');
SELECT has_fk('notifications', 'Table notifications should have foreign key constraints');
SELECT has_fk('user_groups', 'Table user_groups should have foreign key constraints');

-- =============================================
-- Test Unique Constraints
-- =============================================
SELECT col_is_unique('users', ARRAY['identifier'], 'users.identifier should be unique');
SELECT col_is_unique('groups', ARRAY['code'], 'groups.code should be unique');
SELECT col_is_unique('resources', ARRAY['code'], 'resources.code should be unique');
SELECT col_is_unique('rooms', ARRAY['code'], 'rooms.code should be unique');

-- =============================================
-- Test Indexes
-- =============================================
SELECT has_index('proof_absences', 'idx_proof_absences_proof_id', 'Index idx_proof_absences_proof_id should exist');
SELECT has_index('proof_absences', 'idx_proof_absences_absence_id', 'Index idx_proof_absences_absence_id should exist');
SELECT has_index('course_slots', 'idx_course_slots_group_id', 'Index idx_course_slots_group_id should exist');
SELECT has_index('import_jobs', 'idx_import_jobs_status', 'Index idx_import_jobs_status should exist');
SELECT has_index('import_history', 'idx_import_history_created_at', 'Index idx_import_history_created_at should exist');

SELECT * FROM finish();
ROLLBACK;
