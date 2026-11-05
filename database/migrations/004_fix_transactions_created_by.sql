-- Migration: Correct transactions.created_by to match schema.sql's baseline
-- Date: 2026-09-05
-- Purpose: Close the schema-drift gap between database/schema.sql and the live database
--          identified during the schema-reproducibility audit (see
--          003_add_subscription_financial.sql's header).
--
-- transactions.created_by records the admin who recorded a payment (README section 12) —
-- it was originally created as `INT NOT NULL REFERENCES users(user_id)`, which is wrong:
-- it points at students, not admins, and an FK to admins would create an unwanted
-- delete-cascade dilemma for financial audit history. schema.sql was already corrected to
-- `VARCHAR(100) NOT NULL` with no foreign key, matching the existing payments.issued_by /
-- payments.deleted_by convention (a plain username snapshot). This migration applies that
-- same correction to an existing database that was created before schema.sql was fixed.
--
-- Safe to run: the transactions table had 0 rows at the time this migration was written
-- and applied, so no data conversion or backfill is required.

ALTER TABLE transactions
  DROP FOREIGN KEY transactions_ibfk_3,
  MODIFY COLUMN created_by VARCHAR(100) NOT NULL;
