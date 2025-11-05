-- =============================================
-- pgTAP Installation Check and Setup
-- =============================================
-- This file ensures pgTAP is available and sets up the test environment
-- Run this first before running any other tests

BEGIN;

-- Check if pgTAP is installed
SELECT plan(1);

-- Test that pgTAP extension is available
SELECT has_extension('pgtap', 'pgTAP extension should be installed');

SELECT * FROM finish();
ROLLBACK;
