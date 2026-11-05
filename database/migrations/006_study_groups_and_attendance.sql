-- Migration: Study groups and attendance (P0-1, Phase 1)
-- Date: 2026-09-13
-- Purpose: Foundation schema for the approved group-based teaching model. Table is named
-- `study_groups` rather than `groups` because GROUPS is a reserved word in MySQL 8.4.
--
-- Safe to run: purely additive. The two ALTERs on existing tables (admins.role,
-- course_subs.group_id) only widen an ENUM and add a nullable column + FK — no existing
-- row changes value or loses data.

-- 006_study_groups_and_attendance.sql

-- 1. Расширяем роли администраторов для добавления роли преподавателя
ALTER TABLE admins
  MODIFY COLUMN role ENUM('super_admin', 'center_admin', 'teacher') NOT NULL DEFAULT 'center_admin';

-- 2. Таблица учебных групп
CREATE TABLE IF NOT EXISTS study_groups (
  group_id     INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id    INT NOT NULL DEFAULT 1,
  course_id    INT NOT NULL,
  teacher_id   INT NULL,
  title        VARCHAR(255) NOT NULL,
  days_of_week VARCHAR(32)  NOT NULL DEFAULT 'mon,wed,fri',
  lesson_time  VARCHAR(32)  NULL DEFAULT '18:00 - 19:30',
  room         VARCHAR(64)  NULL,
  is_active    BOOLEAN NOT NULL DEFAULT TRUE,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
  FOREIGN KEY (teacher_id) REFERENCES admins(id) ON DELETE SET NULL,
  INDEX idx_groups_tenant  (tenant_id),
  INDEX idx_groups_course  (tenant_id, course_id),
  INDEX idx_groups_teacher (tenant_id, teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Привязка подписки к группе (nullable)
ALTER TABLE course_subs
  ADD COLUMN group_id INT NULL DEFAULT NULL AFTER course_id,
  ADD CONSTRAINT fk_course_subs_group FOREIGN KEY (group_id) REFERENCES study_groups(group_id) ON DELETE SET NULL,
  ADD INDEX idx_course_subs_group (tenant_id, group_id);

-- 4. Таблица посещаемости (журнал)
CREATE TABLE IF NOT EXISTS attendance (
  attendance_id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id     INT NOT NULL DEFAULT 1,
  group_id      INT NOT NULL,
  user_id       INT NOT NULL,
  lesson_date   DATE NOT NULL,
  status        ENUM('present', 'absent', 'late') NOT NULL,
  reason        VARCHAR(255) NULL,
  marked_by     VARCHAR(100) NOT NULL,
  marked_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (group_id) REFERENCES study_groups(group_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  UNIQUE KEY uk_attendance (tenant_id, group_id, user_id, lesson_date),
  INDEX idx_attendance_date (tenant_id, lesson_date),
  INDEX idx_attendance_user (tenant_id, user_id, lesson_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
