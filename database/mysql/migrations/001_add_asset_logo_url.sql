-- Run this once for databases created before the logo_url column was added.
ALTER TABLE assets ADD COLUMN logo_url VARCHAR(500) NULL AFTER company_name;
