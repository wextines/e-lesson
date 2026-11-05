-- Migration: Make phone optional, points default 0, email NOT NULL
-- Run this if database was created with the old schema
--
-- STATUS: Already incorporated into database/schema.sql's baseline `users` table
-- (phone has no UNIQUE index, email is NOT NULL, points DEFAULTs to 0). A fresh
-- install using the current schema.sql does not need to run this file — it is
-- kept only as a historical record of what changed and when (2026-08-25).

-- Remove UNIQUE from phone (allows multiple users without phone)
ALTER TABLE users DROP INDEX IF EXISTS phone;
ALTER TABLE users MODIFY COLUMN phone VARCHAR(255) DEFAULT '';

-- Make email NOT NULL
ALTER TABLE users MODIFY COLUMN email VARCHAR(255) NOT NULL;

-- Default points to 0
ALTER TABLE users MODIFY COLUMN points INT DEFAULT 0;
