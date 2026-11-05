-- Migration: Platform leads (e-Lesson's own marketing homepage)
-- Date: 2026-10-02
-- Purpose: Store "Оставить заявку" submissions from the platform's own homepage
-- (marketing/home.php, served at the apex production domain). This is deliberately
-- NOT tenant-scoped data: a lead is a prospective center owner, not yet a tenant,
-- so there is no tenant_id to isolate by and this table is exempt from CLAUDE.md
-- rule 1's tenant-isolation list. Read by admin/super/leads.php (super_admin only).
--
-- Safe to run: purely additive, one new table.

-- 008_platform_leads.sql

CREATE TABLE IF NOT EXISTS platform_leads (
    lead_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(32) NOT NULL,
    center_name VARCHAR(255) NULL,
    message VARCHAR(500) NULL,
    source_page VARCHAR(100) NOT NULL DEFAULT 'home',
    status ENUM('new', 'contacted', 'archived') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_platform_leads_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
