-- Migration: SMS reminder logs (P0-4)
-- Date: 2026-09-13
-- Purpose: Audit trail for automated Eskiz.uz payment-reminder SMS (php/sms_service.php,
-- php/cron/send_payment_reminders.php). Also drives the "last SMS sent" status shown on
-- admin/finance/debtors.php.
--
-- Safe to run: purely additive, one new table.

-- 007_sms_logs.sql

CREATE TABLE IF NOT EXISTS sms_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL DEFAULT 1,
    user_id INT NOT NULL,
    course_sub_id INT NOT NULL,
    phone VARCHAR(32) NOT NULL,
    message TEXT NOT NULL,
    trigger_type ENUM('reminder_3days', 'overdue_1day', 'manual') NOT NULL,
    status ENUM('sent', 'failed', 'mock_sent') NOT NULL DEFAULT 'sent',
    error_message TEXT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (course_sub_id) REFERENCES course_subs(subscription_id) ON DELETE CASCADE,
    INDEX idx_sms_dedup (tenant_id, course_sub_id, trigger_type, sent_at),
    INDEX idx_sms_tenant (tenant_id, sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
