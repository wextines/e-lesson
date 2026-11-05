-- e-Lesson Phase 3A: Multi-Tenant Foundation
-- Adds tenants table, tenant_config, and tenant_id to all tenant-owned tables.
-- All existing data is automatically assigned to tenant_id = 1 (IQ-CREATIVE).
-- Safe to run on fresh or existing databases.
--
-- STATUS: Already incorporated into database/schema.sql's baseline — the tenants
-- and tenant_config tables, and every tenant_id column/index this file adds, are
-- already present in schema.sql's CREATE TABLE statements. A fresh install using
-- the current schema.sql does not need to run this file (every statement below is
-- IF NOT EXISTS-guarded and would just no-op). Kept as a historical record of the
-- multi-tenancy rollout (2026-08-29).

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ============================================
-- 1. tenants — root table for each education center
-- ============================================
CREATE TABLE IF NOT EXISTS tenants (
    tenant_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    domain VARCHAR(255) DEFAULT NULL,
    logo_path VARCHAR(255) DEFAULT NULL,
    primary_color VARCHAR(7) DEFAULT '#0a192f',
    secondary_color VARCHAR(7) DEFAULT '#172a45',
    accent_color VARCHAR(7) DEFAULT '#64ffda',
    email VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    timezone VARCHAR(50) DEFAULT 'Asia/Tashkent',
    default_language VARCHAR(10) DEFAULT 'uz',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. tenant_config — extended key-value settings per tenant
-- ============================================
CREATE TABLE IF NOT EXISTS tenant_config (
    config_id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    config_key VARCHAR(100) NOT NULL,
    config_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(tenant_id) ON DELETE CASCADE,
    UNIQUE KEY unique_tenant_config (tenant_id, config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. Seed tenant #1: IQ-CREATIVE
-- ============================================
INSERT INTO tenants (tenant_id, name, slug, email, phone, timezone, default_language)
VALUES (1, 'IQ-CREATIVE', 'iq-creative', 'info@iq-creative.uz', '+998901234567', 'Asia/Tashkent', 'uz')
ON DUPLICATE KEY UPDATE name = name;

-- ============================================
-- 4. Add tenant_id to tenant-owned tables
-- ============================================
-- Each ALTER adds the column with DEFAULT 1 so all existing rows
-- automatically belong to IQ-CREATIVE (tenant #1).

-- users
ALTER TABLE users ADD COLUMN IF NOT EXISTS tenant_id INT NOT NULL DEFAULT 1 AFTER user_id;
CREATE INDEX IF NOT EXISTS idx_users_tenant ON users(tenant_id);

-- admins
ALTER TABLE admins ADD COLUMN IF NOT EXISTS tenant_id INT NOT NULL DEFAULT 1 AFTER id;
CREATE INDEX IF NOT EXISTS idx_admins_tenant ON admins(tenant_id);

-- courses
ALTER TABLE courses ADD COLUMN IF NOT EXISTS tenant_id INT NOT NULL DEFAULT 1 AFTER course_id;
CREATE INDEX IF NOT EXISTS idx_courses_tenant ON courses(tenant_id);

-- materials
ALTER TABLE materials ADD COLUMN IF NOT EXISTS tenant_id INT NOT NULL DEFAULT 1 AFTER material_id;
CREATE INDEX IF NOT EXISTS idx_materials_tenant ON materials(tenant_id);

-- daily_tests
ALTER TABLE daily_tests ADD COLUMN IF NOT EXISTS tenant_id INT NOT NULL DEFAULT 1 AFTER daily_test_id;
CREATE INDEX IF NOT EXISTS idx_daily_tests_tenant ON daily_tests(tenant_id);

-- weekly_tests
ALTER TABLE weekly_tests ADD COLUMN IF NOT EXISTS tenant_id INT NOT NULL DEFAULT 1 AFTER weekly_test_id;
CREATE INDEX IF NOT EXISTS idx_weekly_tests_tenant ON weekly_tests(tenant_id);

-- test_results
ALTER TABLE test_results ADD COLUMN IF NOT EXISTS tenant_id INT NOT NULL DEFAULT 1 AFTER result_id;
CREATE INDEX IF NOT EXISTS idx_test_results_tenant ON test_results(tenant_id);

-- course_subs
ALTER TABLE course_subs ADD COLUMN IF NOT EXISTS tenant_id INT NOT NULL DEFAULT 1 AFTER subscription_id;
CREATE INDEX IF NOT EXISTS idx_course_subs_tenant ON course_subs(tenant_id);


-- payments
ALTER TABLE payments ADD COLUMN IF NOT EXISTS tenant_id INT NOT NULL DEFAULT 1 AFTER payment_id;
CREATE INDEX IF NOT EXISTS idx_payments_tenant ON payments(tenant_id);


-- books
ALTER TABLE books ADD COLUMN IF NOT EXISTS tenant_id INT NOT NULL DEFAULT 1 AFTER book_id;
CREATE INDEX IF NOT EXISTS idx_books_tenant ON books(tenant_id);

-- other_resources
ALTER TABLE other_resources ADD COLUMN IF NOT EXISTS tenant_id INT NOT NULL DEFAULT 1 AFTER resource_id;
CREATE INDEX IF NOT EXISTS idx_other_resources_tenant ON other_resources(tenant_id);
