-- e-Lesson Demo Seed Data
-- Tenant #1: IQ-CREATIVE
-- Password for all demo users: Demo1234
-- Admin password: admin123

-- ============================================
-- Tenant #1: IQ-CREATIVE
-- ============================================
INSERT INTO tenants (tenant_id, name, slug, email, phone, timezone, default_language)
VALUES (1, 'IQ-CREATIVE', 'iq-creative', 'info@iq-creative.uz', '+998901234567', 'Asia/Tashkent', 'uz');

-- ============================================
-- Admins (all tenants)
-- ============================================
INSERT INTO admins (tenant_id, username, role, password_hash) VALUES
(0, 'admin', 'super_admin', '$2y$12$nVUcZh1L0zu.sQgw7t/11uc'),
(1, 'iqadmin', 'center_admin', '$2y$12$nVUcZh1L0zu.sQgw7t/11uc');

-- ============================================
-- Tenant #2: Test Academy
-- ============================================
INSERT INTO tenants (tenant_id, name, slug, email, phone, timezone, default_language, primary_color, secondary_color, accent_color)
VALUES (2, 'Test Academy', 'test-academy', 'info@test-academy.uz', '+998901111111', 'Asia/Tashkent', 'ru', '#1a1a2e', '#16213e', '#e94560');

INSERT INTO admins (tenant_id, username, role, password_hash) VALUES
(2, 'testadmin', 'center_admin', '$2y$12$nVUcZh1L0zu.sQgw7t/11uc');

-- ============================================
-- Tenant #3: Demo Academy
-- ============================================
INSERT INTO tenants (tenant_id, name, slug, email, phone, timezone, default_language, primary_color, secondary_color, accent_color)
VALUES (3, 'Demo Academy', 'demo-academy', 'info@demo-academy.uz', '+998902222222', 'Asia/Tashkent', 'en', '#0f3460', '#16213e', '#e94560');

INSERT INTO admins (tenant_id, username, role, password_hash) VALUES
(3, 'demoadmin', 'center_admin', '$2y$12$nVUcZh1L0zu.sQgw7t/11ucQdvz0W8NeujS3J3mKMKPcBRpPiqdWm');

-- ============================================
-- Users (tenant #1, one per class level + demo)
-- ============================================
INSERT INTO users (tenant_id, user_id, class, phone, balance, points, email, password, first_name, last_name, slug) VALUES
(1, 1001, '1-2', '+998901111111', 50000, 120, 'demo@example.local', '$2y$12$XjwvRGLidmcW2FZpzkolt.uNkGy80ge5eNlYn7fa6F0sXmq7fKQoi', 'Akmal', 'Rahimov', 'akmal-rahimov'),
(1, 1002, '3-4', '+998902222222', 30000, 85, 'demo34@example.local', '$2y$12$XjwvRGLidmcW2FZpzkolt.uNkGy80ge5eNlYn7fa6F0sXmq7fKQoi', 'Nilufar', 'Karimova', 'nilufar-karimova'),
(1, 1003, '5-6', '+998903333333', 0, 210, 'demo56@example.local', '$2y$12$XjwvRGLidmcW2FZpzkolt.uNkGy80ge5eNlYn7fa6F0sXmq7fKQoi', 'Sardor', 'Aliyev', 'sardor-aliyev'),
(1, 1004, '7-8', '+998904444444', 0, 55, 'demo78@example.local', '$2y$12$XjwvRGLidmcW2FZpzkolt.uNkGy80ge5eNlYn7fa6F0sXmq7fKQoi', 'Malika', 'Nazarova', 'malika-nazarova'),
(1, 1005, '9-11', '+998905555555', 15000, 340, 'demo911@example.local', '$2y$12$XjwvRGLidmcW2FZpzkolt.uNkGy80ge5eNlYn7fa6F0sXmq7fKQoi', 'Jasur', 'Toshmatov', 'jasur-toshmatov'),
(1, 1006, '1-4', '+998906666666', 0, 0, 'demo14course@example.local', '$2y$12$XjwvRGLidmcW2FZpzkolt.uNkGy80ge5eNlYn7fa6F0sXmq7fKQoi', 'Dilshod', 'Umarov', 'dilshod-umarov');

-- ============================================
-- Courses (tenant #1)
-- ============================================
INSERT INTO courses (tenant_id, course_id, subject, title, description, price, subscription_days, classLevel, is_active) VALUES
-- 1-2 sinf
(1, 1, 'Matematika', '1-2 sinf Matematika', 'Asosiy arifmetik amallar: qo''shish, ayirish, ko''paytirish, bo''lish. Oddiy masalalar yechish.', 0, 30, '1-2', TRUE),
(1, 2, 'Tabiatshunoslik', '1-2 sinf Tabiatshunoslik', 'Tabiat haqida asosiy bilimlar. Hayvonlar, o''simliklar, fasllar.', 0, 30, '1-2', TRUE),
-- 3-4 sinf
(1, 3, 'Matematika', '3-4 sinf Matematika', 'Ko''paytirish jadvali, kasrlar, o''lchov birliklari.', 50000, 30, '3-4', TRUE),
(1, 4, 'Ingliz tili', '3-4 sinf Ingliz tili', 'Asosiy grammatika, vocabulary, speaking practice.', 45000, 30, '3-4', TRUE),
-- 5-6 sinf
(1, 5, 'Matematika', '5-6 sinf Matematika', 'Ulchashlar, kasrlar, geometriya asoslari.', 60000, 30, '5-6', TRUE),
(1, 6, 'Fizika', '5-6 sinf Fizika', 'Kuch, harakat, energiya tushunchalari.', 55000, 30, '5-6', TRUE),
-- 7-8 sinf
(1, 7, 'Matematika', '7-8 sinf Matematika', 'Tenglamalar, tengsizliklar, geometriya.', 70000, 30, '7-8', TRUE),
(1, 8, 'Kimyo', '7-8 sinf Kimyo', 'Elementar moddalar, kimyoviy reaktsiyalar.', 65000, 30, '7-8', TRUE),
-- 9-11 sinf
(1, 9, 'Matematika', '9-11 sinf Matematika', 'Mustaqil o''rganish, funksiyalar, integrallar.', 80000, 30, '9-11', TRUE),
(1, 10, 'Ingliz tili', '9-11 sinf IELTS Tayyorlov', 'IELTS imtihoniga tayyorlash. Reading, Writing, Listening, Speaking.', 90000, 60, '9-11', TRUE),
-- 1-4 kurs
(1, 11, 'Dasturlash', 'Python Asoslari', 'Python dasturlash tilining asoslari. Boshlang''ich darajadan o''rta darajagacha.', 120000, 60, '1-4', TRUE),
(1, 12, 'Matematika', 'SAT Matematika', 'SAT imtihoniga tayyorlash. Matematik qism.', 150000, 90, '1-4', TRUE);

-- ============================================
-- Materials (tenant #1)
-- ============================================
INSERT INTO materials (tenant_id, material_id, lesson_id, course_id, title, description, file_path, thumbnail_path, duration) VALUES
(1, 1, NULL, 1, 'Qo''shish va ayirish', 'Asosiy arifmetik amallar', '/materials/1-2-math/lesson1.pdf', NULL, 30),
(1, 2, NULL, 1, 'Ko''paytirish', 'Ko''paytirish jadvali', '/materials/1-2-math/lesson2.pdf', NULL, 25),
(1, 3, NULL, 3, 'Kasrlar', 'Kasrlar bilan ishlash', '/materials/3-4-math/lesson1.pdf', NULL, 40),
(1, 4, NULL, 5, 'Geometriya asoslari', 'Geometrik shakllar', '/materials/5-6-math/lesson1.pdf', NULL, 35),
(1, 5, NULL, 7, 'Tenglamalar', 'Tenglamalar yechish usullari', '/materials/7-8-math/lesson1.pdf', NULL, 45),
(1, 6, NULL, 9, 'Funksiyalar', 'Funksiyalar tushunchasi', '/materials/9-11-math/lesson1.pdf', NULL, 50);

-- ============================================
-- Daily tests (tenant #1)
-- ============================================
INSERT INTO daily_tests (tenant_id, daily_test_id, material_id, course_id, title, description, start_date, end_date, min_score, max_score) VALUES
(1, 1, 1, 1, 'Qo''shish testi', 'Oddiy qo''shish masalalari', '2025-01-01 00:00:00', '2025-12-31 23:59:59', 0, 100),
(1, 2, 3, 3, 'Kasrlar testi', 'Kasrlar bilan ishlash', '2025-01-01 00:00:00', '2025-12-31 23:59:59', 0, 100),
(1, 3, 5, 7, 'Tenglamalar testi', 'Tenglamalar yechish', '2025-01-01 00:00:00', '2025-12-31 23:59:59', 0, 100);

-- ============================================
-- Weekly tests (tenant #1)
-- ============================================
INSERT INTO weekly_tests (tenant_id, weekly_test_id, course_id, title, description, start_date, end_date, min_score, max_score) VALUES
(1, 1, 3, '3-4 Haftalik test 1', 'Matematika bo''yicha haftalik test', '2025-01-06 09:00:00', '2025-01-12 23:59:59', 0, 100),
(1, 2, 5, '5-6 Haftalik test 1', 'Matematika bo''yicha haftalik test', '2025-01-06 09:00:00', '2025-01-12 23:59:59', 0, 100),
(1, 3, 7, '7-8 Haftalik test 1', 'Matematika bo''yicha haftalik test', '2025-01-06 09:00:00', '2025-01-12 23:59:59', 0, 100),
(1, 4, 9, '9-11 Haftalik test 1', 'Matematika bo''yicha haftalik test', '2025-01-06 09:00:00', '2025-01-12 23:59:59', 0, 100),
(1, 5, 10, 'IELTS Haftalik test 1', 'IELTS Reading practice', '2025-01-06 09:00:00', '2025-01-12 23:59:59', 0, 100);

-- ============================================
-- Daily questions (tenant #1, inherited via FK)
-- ============================================
INSERT INTO daily_questions (daily_question_id, daily_test_id, question_img_url, correct_answer) VALUES
(1, 1, '/img/questions/daily/q1.png', 'A'),
(2, 1, '/img/questions/daily/q2.png', 'B'),
(3, 2, '/img/questions/daily/q3.png', 'C'),
(4, 3, '/img/questions/daily/q4.png', 'A');

-- ============================================
-- Weekly questions (tenant #1, inherited via FK)
-- ============================================
INSERT INTO weekly_questions (weekly_question_id, weekly_test_id, question_img_url, correct_answer) VALUES
(1, 1, '/img/questions/weekly/q1.png', 'A'),
(2, 1, '/img/questions/weekly/q2.png', 'B'),
(3, 2, '/img/questions/weekly/q3.png', 'C'),
(4, 3, '/img/questions/weekly/q4.png', 'A'),
(5, 4, '/img/questions/weekly/q5.png', 'B'),
(6, 5, '/img/questions/weekly/q6.png', 'A');

-- ============================================
-- Test results (tenant #1)
-- ============================================
INSERT INTO test_results (tenant_id, slug, test_type, test_id, score, get_points, completed_at) VALUES
(1, 'akmal-rahimov', 'daily', 1, '85', 85, '2025-01-10 14:30:00'),
(1, 'akmal-rahimov', 'daily', 2, '90', 90, '2025-01-11 15:00:00'),
(1, 'nilufar-karimova', 'daily', 1, '75', 75, '2025-01-10 16:00:00'),
(1, 'sardor-aliyev', 'daily', 3, '95', 95, '2025-01-12 10:00:00'),
(1, 'sardor-aliyev', 'weekly', 1, '88', 88, '2025-01-12 11:00:00'),
(1, 'jasur-toshmatov', 'daily', 3, '92', 92, '2025-01-11 09:00:00'),
(1, 'jasur-toshmatov', 'weekly', 4, '85', 85, '2025-01-12 14:00:00');

-- ============================================
-- Course subscriptions (tenant #1)
-- ============================================
INSERT INTO course_subs (tenant_id, user_id, course_id, price, purchase_date, access_expires_at) VALUES
(1, 1002, 3, 50000, '2025-01-05 10:00:00', '2025-02-04 10:00:00'),
(1, 1003, 5, 60000, '2025-01-03 12:00:00', '2025-02-02 12:00:00'),
(1, 1005, 9, 80000, '2025-01-02 09:00:00', '2025-03-03 09:00:00'),
(1, 1005, 10, 90000, '2025-01-02 09:05:00', '2025-03-03 09:05:00');

-- ============================================
-- Payments (tenant #1)
-- ============================================
INSERT INTO payments (tenant_id, user_id, amount, payment_date, status, receipt_path, comment) VALUES
(1, 1002, 50000, '2025-01-05 09:55:00', 'approved', '/receipts/1002_1.pdf', 'Kurs to''lovi'),
(1, 1003, 60000, '2025-01-03 11:55:00', 'approved', '/receipts/1003_1.pdf', 'Kurs to''lovi'),
(1, 1005, 80000, '2025-01-02 08:55:00', 'approved', '/receipts/1005_1.pdf', 'Kurs to''lovi'),
(1, 1005, 90000, '2025-01-02 09:00:00', 'approved', '/receipts/1005_2.pdf', 'Kurs to''lovi');

-- ============================================
-- Books (tenant #1)
-- ============================================
INSERT INTO books (tenant_id, title, description, class, preview_path, book_path) VALUES
(1, 'Matematika 1-2 sinf', 'Asosiy arifmetik amallar bo''yicha qo''llanma', '1-2', '/img/books/math-1-2-preview.jpg', '/books/math-1-2.pdf'),
(1, 'Tabiatshunoslik 1-2', 'Tabiat haqida kitob', '1-2', '/img/books/nature-1-2-preview.jpg', '/books/nature-1-2.pdf'),
(1, 'Matematika 3-4 sinf', 'O''rta bosqich matematikasi', '3-4', '/img/books/math-3-4-preview.jpg', '/books/math-3-4.pdf'),
(1, 'Ingliz tili 3-4', 'Ingliz tili asoslari', '3-4', '/img/books/english-3-4-preview.jpg', '/books/english-3-4.pdf'),
(1, 'Matematika 5-6 sinf', 'Geometriya va algebra asoslari', '5-6', '/img/books/math-5-6-preview.jpg', '/books/math-5-6.pdf'),
(1, 'Fizika 5-6 sinf', 'Fizika asoslari', '5-6', '/img/books/physics-5-6-preview.jpg', '/books/physics-5-6.pdf'),
(1, 'Matematika 7-8 sinf', 'O''rta maktab matematikasi', '7-8', '/img/books/math-7-8-preview.jpg', '/books/math-7-8.pdf'),
(1, 'Kimyo 7-8 sinf', 'Kimyo asoslari', '7-8', '/img/books/chemistry-7-8-preview.jpg', '/books/chemistry-7-8.pdf'),
(1, 'Matematika 9-11 sinf', 'Yuqori sinflar matematikasi', '9-11', '/img/books/math-9-11-preview.jpg', '/books/math-9-11.pdf'),
(1, 'IELTS Tayyorlov', 'IELTS imtihoniga tayyorlash qo''llanmasi', '9-11', '/img/books/ielts-preview.jpg', '/books/ielts.pdf'),
(1, 'Python Asoslari', 'Python dasturlash tiliga kirish', '1-4', '/img/books/python-preview.jpg', '/books/python.pdf'),
(1, 'SAT Matematika', 'SAT matematik qismiga tayyorlash', '1-4', '/img/books/sat-math-preview.jpg', '/books/sat-math.pdf');
