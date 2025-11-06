--liquibase formatted sql

--changeset collard.yony:initial-schema labels:Initial DB context:initial
--comment: Initial schema creation

-- =============================================
-- ENUM TYPES
-- =============================================
CREATE TYPE user_role AS ENUM ('student', 'teacher', 'academic_manager', 'secretary');
CREATE TYPE course_type AS ENUM ('CM', 'TD', 'TP','BEN', 'TPC', 'DS', 'TDC');
CREATE TYPE absence_status AS ENUM ('absent', 'present', 'excused', 'unjustified');
CREATE TYPE justification_status AS ENUM ('pending', 'accepted', 'rejected', 'under_review');
CREATE TYPE absence_reason AS ENUM ('illness', 'death', 'family_obligations', 'other');
CREATE TYPE notification_type AS ENUM ('absence_detected', 'course_return', 'justification_processed', 'evaluation_alert');
CREATE TYPE decision_action AS ENUM ('accept', 'reject', 'request_info', 'unlock');

-- =============================================
-- TABLES
-- =============================================

-- Users table
CREATE TABLE users (
	id SERIAL PRIMARY KEY,
	identifier VARCHAR(50) UNIQUE NOT NULL,
	last_name VARCHAR(100) NOT NULL,
	first_name VARCHAR(100) NOT NULL,
	middle_name VARCHAR(100),
	birth_date DATE,
	degrees VARCHAR(200),
	department VARCHAR(100),
	email VARCHAR(255) UNIQUE,
	password_hash VARCHAR(255) NOT NULL,
	role user_role NOT NULL DEFAULT 'student',
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Groups/Programs table
CREATE TABLE groups (
	id SERIAL PRIMARY KEY,
	code VARCHAR(50) UNIQUE NOT NULL,
	label VARCHAR(200),
	program VARCHAR(100),
	year INTEGER
);

-- Resources/Subjects table
CREATE TABLE resources (
	id SERIAL PRIMARY KEY,
	code VARCHAR(20) UNIQUE NOT NULL,
	label VARCHAR(200) NOT NULL,
	teaching_type course_type,
	group_id INTEGER REFERENCES groups(id)
);

-- Rooms table
CREATE TABLE rooms (
	id SERIAL PRIMARY KEY,
	code VARCHAR(20) UNIQUE NOT NULL,
	label VARCHAR(100)
);

-- Teachers table
CREATE TABLE teachers (
	id SERIAL PRIMARY KEY,
	last_name VARCHAR(100) NOT NULL,
	first_name VARCHAR(100) NOT NULL,
	email VARCHAR(255) UNIQUE
);

-- Course slots table
CREATE TABLE course_slots (
	id SERIAL PRIMARY KEY,
	course_date DATE NOT NULL,
	start_time TIME NOT NULL,
	end_time TIME NOT NULL,
	duration_minutes INTEGER NOT NULL,
	course_type course_type NOT NULL,
	resource_id INTEGER REFERENCES resources(id),
	room_id INTEGER REFERENCES rooms(id),
	teacher_id INTEGER REFERENCES teachers(id),
	is_evaluation BOOLEAN DEFAULT FALSE,
	subject_identifier VARCHAR(50)
);

-- Absences table (fed by VT)
CREATE TABLE absences (
	id SERIAL PRIMARY KEY,
	student_identifier VARCHAR(50) NOT NULL REFERENCES users(identifier),
	course_slot_id INTEGER NOT NULL REFERENCES course_slots(id),
	status absence_status NOT NULL DEFAULT 'absent',
	justified BOOLEAN DEFAULT FALSE,
	import_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Proof table
CREATE TABLE proof (
	id SERIAL PRIMARY KEY,
	absence_id INTEGER REFERENCES absences(id),
	student_identifier VARCHAR(50) NOT NULL REFERENCES users(identifier),
	absence_start_date DATE NOT NULL,
	absence_end_date DATE NOT NULL,
	concerned_courses TEXT,
	main_reason absence_reason NOT NULL,
	custom_reason TEXT,
	file_path VARCHAR(500),
	student_comment TEXT,
	status justification_status NOT NULL DEFAULT 'pending',
	rejection_reason TEXT,
	manager_comment TEXT,
	processed_by_user_id INTEGER REFERENCES users(id),
	submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	processing_date TIMESTAMP,
	locked BOOLEAN DEFAULT FALSE,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Decision history table
CREATE TABLE decision_history (
	id SERIAL PRIMARY KEY,
	justification_id INTEGER NOT NULL REFERENCES proof(id),
	user_id INTEGER NOT NULL REFERENCES users(id),
	action decision_action NOT NULL,
	old_status VARCHAR(50),
	new_status VARCHAR(50),
	comment TEXT,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Notifications table
CREATE TABLE notifications (
	id SERIAL PRIMARY KEY,
	student_identifier VARCHAR(50) NOT NULL REFERENCES users(identifier),
	notification_type notification_type NOT NULL,
	subject VARCHAR(200) NOT NULL,
	message TEXT NOT NULL,
	sent BOOLEAN DEFAULT FALSE,
	sent_date TIMESTAMP,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Make-up sessions table
CREATE TABLE makeups (
	id SERIAL PRIMARY KEY,
	absence_id INTEGER NOT NULL REFERENCES absences(id),
	evaluation_slot_id INTEGER NOT NULL REFERENCES course_slots(id),
	student_identifier VARCHAR(50) NOT NULL REFERENCES users(identifier),
	scheduled BOOLEAN DEFAULT FALSE,
	makeup_date DATE,
	comment TEXT,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User-groups linking table
CREATE TABLE user_groups (
	user_id INTEGER NOT NULL REFERENCES users(id),
	group_id INTEGER NOT NULL REFERENCES groups(id),
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (user_id, group_id)
);

--rollback DROP TABLE IF EXISTS user_groups CASCADE;
--rollback DROP TABLE IF EXISTS makeups CASCADE;
--rollback DROP TABLE IF EXISTS notifications CASCADE;
--rollback DROP TABLE IF EXISTS decision_history CASCADE;
--rollback DROP TABLE IF EXISTS proof CASCADE;
--rollback DROP TABLE IF EXISTS absences CASCADE;
--rollback DROP TABLE IF EXISTS course_slots CASCADE;
--rollback DROP TABLE IF EXISTS teachers CASCADE;
--rollback DROP TABLE IF EXISTS rooms CASCADE;
--rollback DROP TABLE IF EXISTS resources CASCADE;
--rollback DROP TABLE IF EXISTS groups CASCADE;
--rollback DROP TABLE IF EXISTS users CASCADE;
--rollback DROP TYPE IF EXISTS user_role CASCADE;
--rollback DROP TYPE IF EXISTS course_type CASCADE;
--rollback DROP TYPE IF EXISTS absence_status CASCADE;
--rollback DROP TYPE IF EXISTS justification_status CASCADE;
--rollback DROP TYPE IF EXISTS absence_reason CASCADE;
--rollback DROP TYPE IF EXISTS notification_type CASCADE;
--rollback DROP TYPE IF EXISTS decision_action CASCADE;

--changeset collard.yony:fix-teacher-email-constraint labels:Fix context:post-initial
--comment: Remove unique constraint from teachers email field to allow multiple null emails during import

ALTER TABLE teachers DROP CONSTRAINT IF EXISTS teachers_email_key;

--rollback ALTER TABLE teachers ADD CONSTRAINT teachers_email_key UNIQUE (email);

--changeset collard.yony:allow-null-passwords labels:Temporary users context:post-initial
--comment: Allow null passwords and emails for temporary users created during CSV import

ALTER TABLE users ALTER COLUMN password_hash DROP NOT NULL;
ALTER TABLE users ALTER COLUMN email DROP NOT NULL;

--rollback ALTER TABLE users ALTER COLUMN password_hash SET NOT NULL;
--rollback ALTER TABLE users ALTER COLUMN email SET NOT NULL;

--changeset collard.yony:add-proof-absences-association labels:Proof absences context:post-initial
--comment: Add associative table to link proofs with multiple absences and update proof table structure

-- Create associative table for proof-absences relationship
CREATE TABLE proof_absences (
    id SERIAL PRIMARY KEY,
    proof_id INTEGER NOT NULL REFERENCES proof(id) ON DELETE CASCADE,
    absence_id INTEGER NOT NULL REFERENCES absences(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(proof_id, absence_id)
);

-- Remove the single absence_id field from proof table since we now have many-to-many relationship
ALTER TABLE proof DROP COLUMN IF EXISTS absence_id;

-- Add index for better performance
CREATE INDEX idx_proof_absences_proof_id ON proof_absences(proof_id);
CREATE INDEX idx_proof_absences_absence_id ON proof_absences(absence_id);

--rollback DROP INDEX IF EXISTS idx_proof_absences_absence_id;
--rollback DROP INDEX IF EXISTS idx_proof_absences_proof_id;
--rollback ALTER TABLE proof ADD COLUMN absence_id INTEGER REFERENCES absences(id);
--rollback DROP TABLE IF EXISTS proof_absences CASCADE;

--changeset collard.yony:fix-course-slots-resources-relationship labels:Schema refactor context:fixing-db
--comment: Add group_id to course_slots and remove group_id from resources to fix relationship model

-- Add group_id to course_slots table
ALTER TABLE course_slots ADD COLUMN group_id INTEGER REFERENCES groups(id);

-- Remove group_id from resources table
ALTER TABLE resources DROP COLUMN IF EXISTS group_id;

-- Add index for better performance
CREATE INDEX idx_course_slots_group_id ON course_slots(group_id);

--rollback DROP INDEX IF EXISTS idx_course_slots_group_id;
--rollback ALTER TABLE resources ADD COLUMN group_id INTEGER REFERENCES groups(id);
--rollback ALTER TABLE course_slots DROP COLUMN IF EXISTS group_id;

--changeset collard.yony:remove-rooms-label-field labels:Schema cleanup context:fixing-db
--comment: Remove label field from rooms table as it's not needed

-- Remove label column from rooms table
ALTER TABLE rooms DROP COLUMN IF EXISTS label;

--rollback ALTER TABLE rooms ADD COLUMN label VARCHAR(100);

--changeset collard.yony:move-rejection-reason-to-decision-history labels:Schema refactor context:fixing-db
--comment: Move rejection_reason from proof table to decision_history table for better tracking

-- Add rejection_reason to decision_history table
ALTER TABLE decision_history ADD COLUMN rejection_reason TEXT;

-- Remove rejection_reason from proof table
ALTER TABLE proof DROP COLUMN IF EXISTS rejection_reason;

--rollback ALTER TABLE proof ADD COLUMN rejection_reason TEXT;
--rollback ALTER TABLE decision_history DROP COLUMN IF EXISTS rejection_reason;

--changeset fournier.alexandre:add-email-verification-table labels:Email verification context:user-registration
--comment: Add email verification table for user registration process

-- Table pour stocker les codes de vérification
CREATE TABLE email_verifications (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    verification_code VARCHAR(6) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Index pour améliorer les performances
CREATE INDEX idx_email_verifications_email ON email_verifications(email);
CREATE INDEX idx_email_verifications_code ON email_verifications(verification_code);

--rollback DROP INDEX IF EXISTS idx_email_verifications_code;
--rollback DROP INDEX IF EXISTS idx_email_verifications_email;
--rollback DROP TABLE IF EXISTS email_verifications;

--changeset fournier.alexandre:add-email-verified-column labels:Email verification context:user-registration
--comment: Add email_verified column to users table to track verified emails

-- Ajouter la colonne email_verified à la table users
ALTER TABLE users ADD COLUMN email_verified BOOLEAN DEFAULT FALSE;

-- Créer un index pour améliorer les performances des requêtes
CREATE INDEX idx_users_email_verified ON users(email_verified);

--rollback DROP INDEX IF EXISTS idx_users_email_verified;
--rollback ALTER TABLE users DROP COLUMN IF EXISTS email_verified;

--changeset fournier.alexandre:make-identifier-optional labels:User registration context:user-registration
--comment: Make identifier column optional for user registration without student ID

-- Remove NOT NULL constraint from identifier column
ALTER TABLE users ALTER COLUMN identifier DROP NOT NULL;

-- Remove UNIQUE constraint from identifier column and recreate as partial unique (only for non-null values)
ALTER TABLE users DROP CONSTRAINT IF EXISTS users_identifier_key;
CREATE UNIQUE INDEX users_identifier_unique ON users (identifier) WHERE identifier IS NOT NULL;

--rollback DROP INDEX IF EXISTS users_identifier_unique;
--rollback ALTER TABLE users ADD CONSTRAINT users_identifier_key UNIQUE (identifier);
--rollback ALTER TABLE users ALTER COLUMN identifier SET NOT NULL;

--changeset collard.yony:add-import-jobs-table labels:Import tracking context:secretary-dashboard
--comment: Add tables for tracking CSV imports and import history

-- Import jobs table for background import tracking
CREATE TABLE IF NOT EXISTS import_jobs (
    id VARCHAR(255) PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    filepath VARCHAR(500) NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    total_rows INTEGER DEFAULT 0,
    processed_rows INTEGER DEFAULT 0,
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Import history table for action logging
CREATE TABLE IF NOT EXISTS import_history (
    id SERIAL PRIMARY KEY,
    action_type VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    status VARCHAR(50) DEFAULT 'info',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add index for better performance
CREATE INDEX idx_import_jobs_status ON import_jobs(status);
CREATE INDEX idx_import_history_created_at ON import_history(created_at DESC);

--rollback DROP INDEX IF EXISTS idx_import_history_created_at;
--rollback DROP INDEX IF EXISTS idx_import_jobs_status;
--rollback DROP TABLE IF EXISTS import_history CASCADE;
--rollback DROP TABLE IF EXISTS import_jobs CASCADE;

--changeset collard.yony:add-absence-tracking-table labels:Absence monitoring context:automated-notifications
--comment: Add table to track student return to class and notification status for automated emails

-- Absence monitoring table for tracking student returns and notifications
CREATE TABLE absence_monitoring (
    id SERIAL PRIMARY KEY,
    student_id INTEGER NOT NULL REFERENCES users(id),
    student_identifier VARCHAR(50) NOT NULL,
    absence_period_start DATE NOT NULL,
    absence_period_end DATE NOT NULL,
    last_absence_date DATE NOT NULL,
    return_detected_at TIMESTAMP,
    return_notification_sent BOOLEAN DEFAULT FALSE,
    return_notification_sent_at TIMESTAMP,
    reminder_notification_sent BOOLEAN DEFAULT FALSE,
    reminder_notification_sent_at TIMESTAMP,
    is_justified BOOLEAN DEFAULT FALSE,
    justified_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(student_id, absence_period_start, absence_period_end)
);

-- Add index for better performance
CREATE INDEX idx_absence_monitoring_student_id ON absence_monitoring(student_id);
CREATE INDEX idx_absence_monitoring_student_identifier ON absence_monitoring(student_identifier);
CREATE INDEX idx_absence_monitoring_return_detected ON absence_monitoring(return_detected_at);
CREATE INDEX idx_absence_monitoring_notifications ON absence_monitoring(return_notification_sent, reminder_notification_sent);

--rollback DROP INDEX IF EXISTS idx_absence_monitoring_notifications;
--rollback DROP INDEX IF EXISTS idx_absence_monitoring_return_detected;
--rollback DROP INDEX IF EXISTS idx_absence_monitoring_student_identifier;
--rollback DROP INDEX IF EXISTS idx_absence_monitoring_student_id;
--rollback DROP TABLE IF EXISTS absence_monitoring CASCADE;
--changeset navrez.louis:add-rejection-validations-reasons-table labels:Enhancement context:post-initial
CREATE TABLE rejection_validation_reasons (id Serial PRIMARY KEY,
                                label VARCHAR(255) NOT NULL UNIQUE,
type_of_reason VARCHAR(50) NOT NULL CHECK (type_of_reason IN ('rejection', 'validation')));

--rollback DROP TABLE IF EXISTS rejection_reasons CASCADE;

--changeset navrez.louis:insert-rejection-validation-reasons labels:Data context:post-initial
--comment: Insert initial rejection and validation reasons

INSERT INTO rejection_validation_reasons (label, type_of_reason) VALUES
    ('Certificat médical', 'validation'),
    ('Urgence familiale', 'validation'),
    ('Erreur administrative', 'validation'),
    ('Séance de rattrapage accordée', 'validation'),
    ('Maladie avec justificatif', 'validation'),
    ('Autre (validation)', 'validation')
    ON CONFLICT (label) DO NOTHING;

INSERT INTO rejection_validation_reasons (label, type_of_reason) VALUES
    ('Pas de justificatif', 'rejection'),
    ('Soumission trop tardive', 'rejection'),
    ('Preuve insuffisante', 'rejection'),
    ('Suspicion de fraude', 'rejection'),
    ('Hors délai autorisé', 'rejection'),
('Autre (rejet)', 'rejection')
    ON CONFLICT (label) DO NOTHING;

