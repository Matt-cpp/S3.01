-- Update absence_reason enum type to include new values
-- Run this script to update your existing database

ALTER TYPE absence_reason ADD VALUE IF NOT EXISTS 'official_summons';
ALTER TYPE absence_reason ADD VALUE IF NOT EXISTS 'transport_issue';
ALTER TYPE absence_reason ADD VALUE IF NOT EXISTS 'rdv_medical';
