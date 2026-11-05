-- Migration: Add subscription status/payment fields and create transactions table
-- Date: 2026-09-05
-- Purpose: Financial model for e-Lesson MVP
--
-- STATUS: Renamed from 001_add_subscription_financial.sql to 003_... to resolve a
-- duplicate "001" collision with 001_fix_users_optional_fields.sql — no content below
-- was changed by the rename. This file's course_subs columns (next_payment_date,
-- status) and the transactions table are now also incorporated into
-- database/schema.sql's baseline, so a fresh install does not need to run this file.
--
-- CORRECTED IN schema.sql, NOT changed here: schema.sql's transactions.created_by is
-- now VARCHAR(100) NOT NULL with no foreign key (matching the payments.issued_by /
-- payments.deleted_by convention), because created_by records the admin who recorded
-- a payment (README.md section 12), not a student, and the original
-- `created_by INT NOT NULL REFERENCES users(user_id)` below was a design error. That
-- original definition is preserved unmodified below for historical accuracy — it is
-- what actually ran against this project's live database at the time this migration
-- was first applied.
--
-- UPDATE (2026-09-05): the live database has since been corrected to match schema.sql
-- via database/migrations/004_fix_transactions_created_by.sql (applied directly, table
-- had 0 rows). The repository and the live database no longer disagree on this column.
--
-- KNOWN BUG, also preserved unmodified below for historical accuracy: the "Expired
-- subscriptions" UPDATE block is byte-for-byte identical to the "Active
-- subscriptions" block (both set status = 'active') — it never actually assigns a
-- different status to expired rows. Do not rely on this file to correctly backfill
-- `status` if it is ever consulted again.

-- 1. Add new fields to course_subs
ALTER TABLE course_subs
  ADD COLUMN next_payment_date DATE NULL DEFAULT NULL AFTER access_expires_at,
  ADD COLUMN status ENUM('pending','active','paused','cancelled') NOT NULL DEFAULT 'pending' AFTER next_payment_date;

-- 2. Create transactions table
CREATE TABLE IF NOT EXISTS transactions (
  transaction_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id       INT NOT NULL,
  user_id         INT NOT NULL,
  course_sub_id   INT NOT NULL,
  amount          DECIMAL(12,2) NOT NULL,
  payment_date    DATE NOT NULL,
  payment_method  ENUM('cash','card','transfer') NOT NULL DEFAULT 'cash',
  receipt_path    VARCHAR(500) NULL,
  notes           TEXT NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by      INT NOT NULL,

  INDEX idx_tx_tenant_date (tenant_id, payment_date),
  INDEX idx_tx_user (user_id),
  INDEX idx_tx_sub (course_sub_id),

  FOREIGN KEY (user_id)       REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (course_sub_id) REFERENCES course_subs(subscription_id) ON DELETE CASCADE,
  FOREIGN KEY (created_by)    REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Update existing course_subs records
-- Active subscriptions (access_expires_at in the future)
UPDATE course_subs 
SET status = 'active', 
    next_payment_date = DATE(access_expires_at)
WHERE access_expires_at IS NOT NULL 
  AND access_expires_at > NOW();

-- Expired subscriptions (access_expires_at in the past)
UPDATE course_subs 
SET status = 'active', 
    next_payment_date = DATE(access_expires_at)
WHERE access_expires_at IS NOT NULL 
  AND access_expires_at <= NOW();

-- Null expiry (shouldn't exist but handle gracefully)
UPDATE course_subs 
SET status = 'pending'
WHERE access_expires_at IS NULL;
