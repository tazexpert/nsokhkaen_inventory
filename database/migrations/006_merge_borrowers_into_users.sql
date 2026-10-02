-- Merges the separate `borrowers` table (added in 005_add_borrowers.sql)
-- into `users` - no need for two tables, a user row can now be either a
-- full login account (username+password), a PIN-only borrower (no
-- username/password, just a PIN), or both at once.

USE nsokhkaen_inventory;

ALTER TABLE users
    MODIFY COLUMN username VARCHAR(50) NULL,
    MODIFY COLUMN password_hash VARCHAR(255) NULL,
    ADD COLUMN position VARCHAR(150) NULL AFTER full_name,
    ADD COLUMN pin_hash VARCHAR(255) NULL AFTER position;

-- Only run this block if you had previously run 005_add_borrowers.sql
-- (i.e. a `borrowers` table exists). Skip it otherwise - these two
-- statements will fail harmlessly with "table doesn't exist" if you
-- never created it.
INSERT INTO users (full_name, position, pin_hash, role, is_active, created_at)
SELECT full_name, position, pin_hash, 'staff', is_active, created_at FROM borrowers;

DROP TABLE borrowers;
