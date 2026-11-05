-- Migration: Add is_active column to users table
-- Purpose: Soft-delete support for student deactivation

-- Add is_active column (safe for existing data: all existing users default to active)
ALTER TABLE users ADD COLUMN is_active BOOLEAN DEFAULT TRUE AFTER slug;

-- Backfill: ensure all existing users are active
UPDATE users SET is_active = TRUE WHERE is_active IS NULL;
