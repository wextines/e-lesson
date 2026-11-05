-- Migration: Harden `transactions` into a real financial ledger (Phase 0)
-- Date: 2026-09-06
-- Purpose: Prerequisite for the "Касса" / "Должники" finance module. The transactions
--          table was designed as a per-subscription payment record; this migration turns
--          it into an append-only ledger that can survive the operational realities of a
--          private learning center (deleted students, corrected mistakes, refunds).
--
-- Four independent problems are fixed here. Each is a correctness issue, not a nicety:
--
-- 1. DATA LOSS ON DELETE. Both foreign keys were ON DELETE CASCADE, so removing a
--    subscription or a student silently erased their financial history. A cashbook whose
--    rows disappear when a student is expelled cannot be reconciled against physical cash.
--    Both columns become NULLable with ON DELETE SET NULL, matching the convention already
--    used by the legacy payments.user_id FK (which correctly SET NULLs). Making
--    course_sub_id nullable additionally unblocks income that is not tied to a course
--    subscription (books, exam fees) and the backfill of pre-P0-4 accepted receipts that
--    have no resolvable subscription.
--
-- 2. DOUBLE COUNTING. Since P0-4, accepting a legacy receipt in admin/payments/view.php
--    both sets payments.status='accepted' AND creates a transactions row — the same money
--    in two tables, with no column linking them, so it could not even be de-duplicated.
--    source_payment_id makes that link explicit: a transaction born from a legacy receipt
--    points back at it, so finance reporting can sum `transactions` alone as the single
--    source of truth and still show the receipt document behind each row.
--
-- 3. NO REFUNDS. `type` distinguishes money in from money out. Refunds and corrections are
--    routine when a student quits mid-month; without this the ledger could only ever grow.
--
-- 4. NO CORRECTIONS. voided_at/voided_by/void_reason allow reversing a mistyped entry
--    without destroying the audit trail (never hard-delete money). Columns are added now
--    because altering a populated financial table later is far more expensive; the
--    reversal UI itself is deliberately deferred to a later phase.
--
-- Safe to run: purely additive except for the two FK redefinitions, which only relax
-- constraints (NOT NULL -> NULL, CASCADE -> SET NULL). No existing row changes value:
-- every current row keeps its user_id/course_sub_id, gets type='payment' (the correct
-- classification for every transaction created so far, as no refund path exists yet), and
-- NULL for the new nullable columns.
--
-- Reversibility note: the FK direction cannot be cleanly reverted once NULLs exist in
-- these columns, which is intended — restoring CASCADE would reintroduce the data-loss bug.

-- 1. Relax the two foreign keys so deleting a student or a subscription can never
--    destroy the financial record of money that was actually received.
ALTER TABLE transactions
  DROP FOREIGN KEY transactions_ibfk_1,
  DROP FOREIGN KEY transactions_ibfk_2;

ALTER TABLE transactions
  MODIFY COLUMN user_id INT NULL,
  MODIFY COLUMN course_sub_id INT NULL;

ALTER TABLE transactions
  ADD CONSTRAINT fk_tx_user
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_tx_course_sub
    FOREIGN KEY (course_sub_id) REFERENCES course_subs(subscription_id) ON DELETE SET NULL;

-- 2. Link a ledger entry back to the legacy receipt it was created from (NULL for
--    transactions recorded directly by an admin). No FK: payments rows are soft-deleted
--    rather than removed, and an FK here would couple ledger integrity to the legacy
--    table's lifecycle — the same reasoning that keeps created_by a plain string.
ALTER TABLE transactions
  ADD COLUMN source_payment_id INT NULL DEFAULT NULL AFTER course_sub_id,
  ADD INDEX idx_tx_source_payment (source_payment_id);

-- 3. Money in vs money out.
ALTER TABLE transactions
  ADD COLUMN type ENUM('payment','refund','adjustment') NOT NULL DEFAULT 'payment' AFTER amount;

-- 4. Reversal/correction trail (schema only for now — no UI in this phase).
ALTER TABLE transactions
  ADD COLUMN voided_at DATETIME NULL DEFAULT NULL,
  ADD COLUMN voided_by VARCHAR(100) NULL DEFAULT NULL,
  ADD COLUMN void_reason TEXT NULL DEFAULT NULL;
