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

--changeset navrez.louis:add-rejection-validations-reasons-table labels:Enhancement context:post-initial
CREATE TABLE rejection_validation_reasons (id INT AUTO_INCREMENT PRIMARY KEY,
                                label VARCHAR(255) NOT NULL UNIQUE,
type_of_reason VARCHAR(50) NOT NULL CHECK (type_of_reason IN ('rejection', 'validation')),);

--rollback DROP TABLE IF EXISTS rejection_reasons CASCADE;
