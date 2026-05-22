-- Allow uploaded_by to be NULL when auth is disabled
ALTER TABLE images ALTER COLUMN uploaded_by DROP NOT NULL;
