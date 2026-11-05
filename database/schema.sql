-- e-Lesson Database Schema
-- Multi-tenant education platform
--
-- STATUS: Reproducible baseline for a FRESH install. Running this file (plus seed.sql)
-- against an empty MySQL volume reproduces the CURRENT schema the application code
-- actually depends on today, including the tenant/subscription/payments columns that
-- were previously only added ad hoc or via database/migrations/.
--
-- IMPORTANT: this file does NOT synchronize an already-running database. Applying
-- CREATE TABLE IF NOT EXISTS to an existing installation is a silent no-op for any
-- table that already exists — it will NOT add new columns to it. See
-- database/migrations/ for what has historically changed and its incorporation status.

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ============================================
-- Tenants
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
-- Tenant Config (key-value settings)
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
-- Platform leads — "Оставить заявку" submissions from e-Lesson's own
-- marketing homepage (marketing/home.php). Not tenant-scoped: a lead is a
-- prospective center owner, not yet a tenant. Read by admin/super/leads.php.
-- ============================================
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

-- ============================================
-- Users
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL DEFAULT 1,
    class VARCHAR(255),
    phone VARCHAR(255) DEFAULT '',
    balance DECIMAL(10,2) DEFAULT 0,
    points INT DEFAULT 0,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255),
    first_name VARCHAR(255),
    last_name VARCHAR(255),
    slug VARCHAR(255) UNIQUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_tenant (tenant_id),
    UNIQUE KEY idx_users_email_tenant (email, tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1001;

-- ============================================
-- Admins
-- ============================================
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL DEFAULT 1,
    username VARCHAR(100) NOT NULL,
    role ENUM('super_admin', 'center_admin', 'teacher') NOT NULL DEFAULT 'center_admin',
    password_hash VARCHAR(255) NOT NULL,
    UNIQUE KEY uk_admins_tenant_username (tenant_id, username),
    INDEX idx_admins_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Courses
-- ============================================
CREATE TABLE IF NOT EXISTS courses (
    course_id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL DEFAULT 1,
    subject VARCHAR(255),
    title VARCHAR(255) NOT NULL,
    description TEXT,
    price INT NOT NULL,
    subscription_days INT DEFAULT 30,
    classLevel VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_courses_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Study groups (named to avoid the reserved word GROUPS in MySQL 8.4)
-- ============================================
CREATE TABLE IF NOT EXISTS study_groups (
    group_id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL DEFAULT 1,
    course_id INT NOT NULL,
    teacher_id INT NULL,
    title VARCHAR(255) NOT NULL,
    days_of_week VARCHAR(32) NOT NULL DEFAULT 'mon,wed,fri',
    lesson_time VARCHAR(32) NULL DEFAULT '18:00 - 19:30',
    room VARCHAR(64) NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_groups_tenant (tenant_id),
    INDEX idx_groups_course (tenant_id, course_id),
    INDEX idx_groups_teacher (tenant_id, teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Attendance (per-lesson roll call for a study group)
-- ============================================
CREATE TABLE IF NOT EXISTS attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL DEFAULT 1,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    lesson_date DATE NOT NULL,
    status ENUM('present', 'absent', 'late') NOT NULL,
    reason VARCHAR(255) NULL,
    marked_by VARCHAR(100) NOT NULL,
    marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES study_groups(group_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY uk_attendance (tenant_id, group_id, user_id, lesson_date),
    INDEX idx_attendance_date (tenant_id, lesson_date),
    INDEX idx_attendance_user (tenant_id, user_id, lesson_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Materials
-- ============================================
CREATE TABLE IF NOT EXISTS materials (
    material_id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL DEFAULT 1,
    lesson_id INT,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_path TEXT NOT NULL,
    thumbnail_path TEXT,
    duration INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    INDEX idx_materials_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Daily tests
-- ============================================
CREATE TABLE IF NOT EXISTS daily_tests (
    daily_test_id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL DEFAULT 1,
    material_id INT,
    course_id INT NOT NULL,
    title VARCHAR(100),
    description TEXT,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    min_score INT DEFAULT 0,
    max_score INT DEFAULT 100,
    FOREIGN KEY (material_id) REFERENCES materials(material_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    INDEX idx_daily_tests_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Weekly tests
-- ============================================
CREATE TABLE IF NOT EXISTS weekly_tests (
    weekly_test_id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL DEFAULT 1,
    course_id INT NOT NULL,
    title VARCHAR(100),
    description TEXT,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    min_score INT DEFAULT 0,
    max_score INT DEFAULT 100,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    INDEX idx_weekly_tests_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Daily questions
-- ============================================
CREATE TABLE IF NOT EXISTS daily_questions (
    daily_question_id INT AUTO_INCREMENT PRIMARY KEY,
    daily_test_id INT NOT NULL,
    sort_order INT NOT NULL DEFAULT 1,
    question_text TEXT NULL,
    question_img_url TEXT NULL,
    correct_answer VARCHAR(5),
    FOREIGN KEY (daily_test_id) REFERENCES daily_tests(daily_test_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Weekly questions
-- ============================================
CREATE TABLE IF NOT EXISTS weekly_questions (
    weekly_question_id INT AUTO_INCREMENT PRIMARY KEY,
    weekly_test_id INT NOT NULL,
    sort_order INT NOT NULL DEFAULT 1,
    question_text TEXT NULL,
    question_img_url TEXT NULL,
    correct_answer VARCHAR(5),
    FOREIGN KEY (weekly_test_id) REFERENCES weekly_tests(weekly_test_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Test results
-- ============================================
CREATE TABLE IF NOT EXISTS test_results (
    result_id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL DEFAULT 1,
    slug VARCHAR(255),
    test_type ENUM('daily','weekly') NOT NULL,
    test_id INT NOT NULL,
    score VARCHAR(50),
    get_points INT,
    completed_at TIMESTAMP,
    FOREIGN KEY (slug) REFERENCES users(slug) ON DELETE CASCADE,
    INDEX idx_test_results_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Payments (legacy receipt-upload flow — CURRENT)
-- ============================================
CREATE TABLE IF NOT EXISTS payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL DEFAULT 1,
    user_id INT,
    course_id INT DEFAULT NULL,
    amount INT NOT NULL,
    payment_method VARCHAR(50) NOT NULL DEFAULT 'online',
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    issued_by VARCHAR(100) DEFAULT 'student',
    status VARCHAR(50),
    receipt_path VARCHAR(255),
    comment VARCHAR(255),
    deleted_at DATETIME DEFAULT NULL,
    deleted_by VARCHAR(100) DEFAULT NULL,
    deletion_reason VARCHAR(255) DEFAULT NULL,
    access_revoked TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_payments_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1001;

-- ============================================
-- Course subscriptions
-- ============================================
CREATE TABLE IF NOT EXISTS course_subs (
    subscription_id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL DEFAULT 1,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    group_id INT NULL DEFAULT NULL,
    price INT NOT NULL,
    purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    access_expires_at TIMESTAMP,
    next_payment_date DATE DEFAULT NULL,
    status ENUM('pending','active','paused','cancelled') NOT NULL DEFAULT 'pending',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    CONSTRAINT fk_course_subs_group FOREIGN KEY (group_id) REFERENCES study_groups(group_id) ON DELETE SET NULL,
    INDEX idx_course_subs_tenant (tenant_id),
    INDEX idx_course_subs_group (tenant_id, group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SMS reminder logs (P0-4) — audit trail for automated Eskiz.uz payment
-- reminders (php/sms_service.php, php/cron/send_payment_reminders.php) and
-- the "last SMS sent" status on admin/finance/debtors.php.
-- ============================================
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

-- ============================================
-- Transactions (flat financial accounting ledger — CURRENT, written by
-- php/payment_service.php and read by the admin finance module)
-- ============================================
-- created_by records the admin who recorded the payment, stored as a plain username
-- string (matching the existing payments.issued_by / payments.deleted_by convention)
-- rather than a foreign key to admins.id. This is a deliberate choice, not an oversight:
-- an FK would force a decision about what happens to historical transactions when an
-- admin account is later deleted or renamed (CASCADE would destroy financial history,
-- RESTRICT would block account cleanup indefinitely) — a plain snapshot string avoids
-- that dilemma entirely and matches how the rest of this table family already tracks
-- "who performed this action."
--
-- user_id and course_sub_id are deliberately NULLable with ON DELETE SET NULL (see
-- migrations/005_finance_ledger_hardening.sql): this table is an append-only record of
-- money actually received, so deleting a student or a subscription must never erase it.
-- A NULL course_sub_id also covers income not tied to a subscription.
--
-- source_payment_id links a ledger entry back to the legacy `payments` receipt it was
-- created from (NULL when an admin recorded the payment directly). Since P0-4, accepting
-- a receipt writes to BOTH tables; this column is what keeps finance reporting from
-- double-counting, by making `transactions` the single source of truth and `payments`
-- merely the supporting document. Intentionally not a foreign key — payments rows are
-- soft-deleted, and ledger integrity must not depend on the legacy table's lifecycle.
CREATE TABLE IF NOT EXISTS transactions (
    transaction_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NULL,
    course_sub_id INT NULL,
    source_payment_id INT NULL DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL,
    type ENUM('payment','refund','adjustment') NOT NULL DEFAULT 'payment',
    payment_date DATE NOT NULL,
    payment_method ENUM('cash','card','transfer') NOT NULL DEFAULT 'cash',
    receipt_path VARCHAR(500) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by VARCHAR(100) NOT NULL,
    voided_at DATETIME NULL DEFAULT NULL,
    voided_by VARCHAR(100) NULL DEFAULT NULL,
    void_reason TEXT NULL DEFAULT NULL,
    CONSTRAINT fk_tx_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_tx_course_sub FOREIGN KEY (course_sub_id) REFERENCES course_subs(subscription_id) ON DELETE SET NULL,
    INDEX idx_tx_tenant_date (tenant_id, payment_date),
    INDEX idx_tx_user (user_id),
    INDEX idx_tx_sub (course_sub_id),
    INDEX idx_tx_source_payment (source_payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- Books
-- ============================================
CREATE TABLE IF NOT EXISTS books (
    book_id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL DEFAULT 1,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    class VARCHAR(100) NOT NULL,
    preview_path VARCHAR(255) NOT NULL,
    book_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_books_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Other resources
-- ============================================
CREATE TABLE IF NOT EXISTS other_resources (
    resource_id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL DEFAULT 1,
    resource_type VARCHAR(255) NOT NULL,
    resource_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_other_resources_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
