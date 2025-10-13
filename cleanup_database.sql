-- =============================================
-- DATABASE CLEANUP SCRIPT
-- =============================================
-- This script drops all tables and types from the GestionAbsence database
-- Execute with: psql "host=188.245.222.176 port=55432 dbname=GestionAbsence user=gestion_user password=strongpassword123" -f cleanup_database.sql

-- Start transaction to ensure all-or-nothing cleanup
BEGIN;

-- =============================================
-- DROP TABLES (in dependency order)
-- =============================================

-- Drop linking tables first (no dependencies)
DROP TABLE IF EXISTS user_groups CASCADE;

-- Drop tables with foreign keys to other tables
DROP TABLE IF EXISTS makeups CASCADE;
DROP TABLE IF EXISTS notifications CASCADE;
DROP TABLE IF EXISTS decision_history CASCADE;
DROP TABLE IF EXISTS proof_absences CASCADE;
DROP TABLE IF EXISTS proof CASCADE;
DROP TABLE IF EXISTS absences CASCADE;

-- Drop tables with dependencies
DROP TABLE IF EXISTS course_slots CASCADE;
DROP TABLE IF EXISTS resources CASCADE;

-- Drop remaining tables
DROP TABLE IF EXISTS teachers CASCADE;
DROP TABLE IF EXISTS rooms CASCADE;
DROP TABLE IF EXISTS groups CASCADE;
DROP TABLE IF EXISTS users CASCADE;

-- Drop any potential remaining tables that might exist (error in original schema)
DROP TABLE IF EXISTS justifications CASCADE;

-- =============================================
-- DROP CUSTOM TYPES
-- =============================================

DROP TYPE IF EXISTS decision_action CASCADE;
DROP TYPE IF EXISTS notification_type CASCADE;
DROP TYPE IF EXISTS absence_reason CASCADE;
DROP TYPE IF EXISTS justification_status CASCADE;
DROP TYPE IF EXISTS absence_status CASCADE;
DROP TYPE IF EXISTS course_type CASCADE;
DROP TYPE IF EXISTS user_role CASCADE;

-- =============================================
-- DROP SEQUENCES (if any remain)
-- =============================================

-- Drop any sequences that might not be automatically dropped
DROP SEQUENCE IF EXISTS users_id_seq CASCADE;
DROP SEQUENCE IF EXISTS groups_id_seq CASCADE;
DROP SEQUENCE IF EXISTS resources_id_seq CASCADE;
DROP SEQUENCE IF EXISTS rooms_id_seq CASCADE;
DROP SEQUENCE IF EXISTS teachers_id_seq CASCADE;
DROP SEQUENCE IF EXISTS course_slots_id_seq CASCADE;
DROP SEQUENCE IF EXISTS absences_id_seq CASCADE;
DROP SEQUENCE IF EXISTS proof_id_seq CASCADE;
DROP SEQUENCE IF EXISTS proof_absences_id_seq CASCADE;
DROP SEQUENCE IF EXISTS decision_history_id_seq CASCADE;
DROP SEQUENCE IF EXISTS notifications_id_seq CASCADE;
DROP SEQUENCE IF EXISTS makeups_id_seq CASCADE;
DROP SEQUENCE IF EXISTS justifications_id_seq CASCADE;

-- =============================================
-- DROP LIQUIBASE TRACKING TABLES
-- =============================================

-- Drop Liquibase metadata tables to reset changeset history
DROP TABLE IF EXISTS databasechangelog CASCADE;
DROP TABLE IF EXISTS databasechangeloglock CASCADE;

-- =============================================
-- VERIFY CLEANUP
-- =============================================

-- Display remaining tables (should be empty or only system tables)
SELECT tablename 
FROM pg_tables 
WHERE schemaname = 'public' 
ORDER BY tablename;

-- Display remaining types (should be empty or only system types)
SELECT typname 
FROM pg_type 
WHERE typnamespace = (SELECT oid FROM pg_namespace WHERE nspname = 'public')
AND typtype = 'e'  -- enum types only
ORDER BY typname;

-- Commit the transaction
COMMIT;

-- Display success message
\echo 'Database cleanup completed successfully!'
\echo 'All tables, types, sequences, and Liquibase metadata have been dropped from the public schema.'
\echo 'Liquibase changeset history has been reset - all changesets will be considered new on next run.'