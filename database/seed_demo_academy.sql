-- Demo Academy Seed Data (tenant_id=3)
-- 8 courses, 15 students, 6 books, materials, tests, results, payments

-- ============================================
-- USERS (15 students for Demo Academy)
-- ============================================
INSERT INTO users (tenant_id, user_id, class, phone, balance, points, email, password, first_name, last_name, slug) VALUES
(3, 2001, '1-2', '+998901111111', 25000, 180, 'nodir@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Nodir', 'Karimov', 'nodir-karimov'),
(3, 2002, '1-2', '+998902222222', 15000, 95, 'dilnoza@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dilnoza', 'Rahimova', 'dilnoza-rahimova'),
(3, 2003, '3-4', '+998903333333', 40000, 220, 'jasur@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jasur', 'Toshmatov', 'jasur-toshmatov'),
(3, 2004, '3-4', '+998904444444', 5000, 60, 'malika@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Malika', 'Nazarova', 'malika-nazarova'),
(3, 2005, '3-4', '+998905555555', 32000, 145, 'sardor@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sardor', 'Aliyev', 'sardor-aliyev'),
(3, 2006, '5-6', '+998906666666', 60000, 310, 'nilufar@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Nilufar', 'Karimova', 'nilufar-karimova'),
(3, 2007, '5-6', '+998907777777', 8000, 75, 'akmal@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Akmal', 'Rahimov', 'akmal-rahimov'),
(3, 2008, '5-6', '+998908888888', 45000, 280, 'feruza@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Feruza', 'Alimova', 'feruza-alimova'),
(3, 2009, '7-8', '+998909999999', 20000, 190, 'davron@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Davron', 'Mirzayev', 'davron-mirzayev'),
(3, 2010, '7-8', '+998901010101', 55000, 420, 'gulnara@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Gulnora', 'Karimova', 'gulnora-karimova'),
(3, 2011, '7-8', '+998902020202', 3000, 40, 'bekzod@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bekzod', 'Rakhmatov', 'bekzod-rakhmatov'),
(3, 2012, '9-11', '+998903030303', 70000, 510, 'shoxrux@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Shoxrux', 'Makhmudov', 'shoxrux-makhmudov'),
(3, 2013, '9-11', '+998904040404', 12000, 130, 'madina@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Madina', 'Yuldasheva', 'madina-yuldasheva'),
(3, 2014, '9-11', '+998905050505', 35000, 260, 'suhrob@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Suhrob', 'Nosirov', 'suhrob-nosirov'),
(3, 2015, '9-11', '+998906060606', 85000, 620, 'nodira@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Nodira', 'Toshpulatova', 'nodira-toshpulatova');

-- ============================================
-- COURSES (8 courses)
-- ============================================
INSERT INTO courses (tenant_id, course_id, subject, title, description, price, subscription_days, classLevel, is_active) VALUES
(3, 20, 'Matematika', '1-2 sinf Matematika', 'Asosiy arifmetik amallar: qo''shish, ayirish, ko''paytirish, bo''lish. Oddiy masalalar yechish.', 0, 30, '1-2', TRUE),
(3, 21, 'Tabiatshunoslik', '1-2 sinf Tabiatshunoslik', 'Tabiat haqida asosiy bilimlar. O''simliklar, hayvonlar, ob-havo.', 0, 30, '1-2', TRUE),
(3, 22, 'Matematika', '3-4 sinf Matematika', 'Kasrlar, o''lchov birliklari, geometriya asoslari.', 50000, 30, '3-4', TRUE),
(3, 23, 'Ingliz tili', '3-4 sinf Ingliz tili', 'Ingliz tili grammatikasi, vocabulary, speaking skills.', 45000, 30, '3-4', TRUE),
(3, 24, 'Matematika', '5-6 sinf Matematika', 'Song''ar ifodalar, kasrlar, geometriya, algebra asoslari.', 60000, 30, '5-6', TRUE),
(3, 25, 'Fizika', '5-6 sinf Fizika', 'Kuch, harakat, energiya, oddiy tajribalar.', 55000, 30, '5-6', TRUE),
(3, 26, 'Matematika', '7-8 sinf Matematika', 'Kvadrat tenglamalar, funksiyalar, geometriya, statistika.', 70000, 30, '7-8', TRUE),
(3, 27, 'Ingliz tili', '9-11 sinf IELTS Tayyorlov', 'IELTS tayyorlov: Reading, Writing, Listening, Speaking bo''limlari.', 90000, 60, '9-11', TRUE);

-- ============================================
-- MATERIALS (varied distribution)
-- ============================================
INSERT INTO materials (tenant_id, material_id, lesson_id, course_id, title, description, file_path, thumbnail_path, duration) VALUES
-- Course 20 (1-2 Matematika) - 3 materials
(3, 101, 1, 20, 'Qo''shish va ayirish', '10 gacha qo''shish va ayirish amallari', '/courses/20/lesson1.mp4', '/courses/20/thumb1.jpg', 15),
(3, 102, 2, 20, 'Ko''paytirish va bo''lish', '2 gacha ko''paytirish jadvali', '/courses/20/lesson2.mp4', '/courses/20/thumb2.jpg', 20),
(3, 103, 3, 20, 'Oddiy masalalar', 'Bir qadamli va ikki qadamli masalalar', '/courses/20/lesson3.mp4', '/courses/20/thumb3.jpg', 25),
-- Course 21 (1-2 Tabiatshunoslik) - 2 materials
(3, 104, 1, 21, 'O''simliklar olami', 'O''simlik turlari va ularning xususiyatlari', '/courses/21/lesson1.mp4', '/courses/21/thumb1.jpg', 18),
(3, 105, 2, 21, 'Hayvonlar dunyosi', 'Yovvoyi va uy hayvonlari', '/courses/21/lesson2.mp4', '/courses/21/thumb2.jpg', 22),
-- Course 22 (3-4 Matematika) - 4 materials
(3, 106, 1, 22, 'Kasrlar bilan ishlash', 'Oddiy va o''n kasrlar', '/courses/22/lesson1.mp4', '/courses/22/thumb1.jpg', 30),
(3, 107, 2, 22, 'O''lchov birliklari', 'Uzunlik, massa, vaqt o''lchovlari', '/courses/22/lesson2.mp4', '/courses/22/thumb2.jpg', 25),
(3, 108, 3, 22, 'Geometriya asoslari', 'Shakllar, perimetr, yuza', '/courses/22/lesson3.mp4', '/courses/22/thumb3.jpg', 35),
(3, 109, 4, 22, 'Masala yechish', 'Ko''p qadamli masalalar', '/courses/22/lesson4.mp4', '/courses/22/thumb4.jpg', 40),
-- Course 23 (3-4 Ingliz tili) - 3 materials
(3, 110, 1, 23, 'Greetings & Introductions', 'Salomlashish va tanishish', '/courses/23/lesson1.mp4', '/courses/23/thumb1.jpg', 20),
(3, 111, 2, 23, 'Numbers & Colors', 'Raqamlar va ranglar', '/courses/23/lesson2.mp4', '/courses/23/thumb2.jpg', 18),
(3, 112, 3, 23, 'Family Members', 'Oilaviy a''zolar', '/courses/23/lesson3.mp4', '/courses/23/thumb3.jpg', 22),
-- Course 24 (5-6 Matematika) - 3 materials
(3, 113, 1, 24, 'Song''ar ifodalar', 'Doiraviy qavsli ifodalar', '/courses/24/lesson1.mp4', '/courses/24/thumb1.jpg', 28),
(3, 114, 2, 24, 'Kasrlar', 'Ko''paytirish va bo''lish kasrlari', '/courses/24/lesson2.mp4', '/courses/24/thumb2.jpg', 32),
(3, 115, 3, 24, 'Geometriya', 'Triburchak, to''rtburchak, doira', '/courses/24/lesson3.mp4', '/courses/24/thumb3.jpg', 30),
-- Course 25 (5-6 Fizika) - 3 materials
(3, 116, 1, 25, 'Kuch va harakat', 'Nyuton qonunlari', '/courses/25/lesson1.mp4', '/courses/25/thumb1.jpg', 25),
(3, 117, 2, 25, 'Energiya turlari', 'Kinetik va potensial energiya', '/courses/25/lesson2.mp4', '/courses/25/thumb2.jpg', 30),
(3, 118, 3, 25, 'Sovutish va qizdirish', 'Harorat va issiqlik', '/courses/25/lesson3.mp4', '/courses/25/thumb3.jpg', 22),
-- Course 26 (7-8 Matematika) - 3 materials
(3, 119, 1, 26, 'Kvadrat tenglamalar', 'Kvadrat tenglamani yechish usullari', '/courses/26/lesson1.mp4', '/courses/26/thumb1.jpg', 35),
(3, 120, 2, 26, 'Funksiyalar', 'Funksiya tushunchasi va grafik', '/courses/26/lesson2.mp4', '/courses/26/thumb2.jpg', 30),
(3, 121, 3, 26, 'Geometriya', 'Uchburchaklar, parallel kesmalar', '/courses/26/lesson3.mp4', '/courses/26/thumb3.jpg', 40),
-- Course 27 (IELTS) - 4 materials
(3, 122, 1, 27, 'IELTS Reading', 'Reading bo''limi strategiyalari', '/courses/27/lesson1.mp4', '/courses/27/thumb1.jpg', 45),
(3, 123, 2, 27, 'IELTS Writing', 'Writing Task 1 va Task 2', '/courses/27/lesson2.mp4', '/courses/27/thumb2.jpg', 50),
(3, 124, 3, 27, 'IELTS Listening', 'Listening bo''limi mashqlari', '/courses/27/lesson3.mp4', '/courses/27/thumb3.jpg', 40),
(3, 125, 4, 27, 'IELTS Speaking', 'Speaking test formati', '/courses/27/lesson4.mp4', '/courses/27/thumb4.jpg', 35);

-- ============================================
-- DAILY TESTS (Material Tests) - some materials have tests, some don't
-- ============================================
INSERT INTO daily_tests (tenant_id, daily_test_id, material_id, course_id, title, description, start_date, end_date, min_score, max_score) VALUES
-- Material 101 (Qo'shish) HAS test
(3, 1, 101, 20, 'Qo''shish testi', '10 gacha qo''shish bo''yicha test', '2026-08-01 09:00:00', '2026-09-01 23:59:00', 0, 100),
-- Material 106 (Kasrlar) HAS test
(3, 2, 106, 22, 'Kasrlar testi', 'Kasrlar bilan ishlash testi', '2026-08-01 09:00:00', '2026-09-01 23:59:00', 0, 100),
-- Material 113 (Song'ar ifodalar) HAS test
(3, 3, 113, 24, 'Song''ar ifodalar testi', 'Ifodalarni soddalashtirish', '2026-08-01 09:00:00', '2026-09-01 23:59:00', 0, 100),
-- Material 116 (Kuch va harakat) HAS test
(3, 4, 116, 25, 'Kuch va harakat testi', 'Nyuton qonunlari bo''yicha test', '2026-08-01 09:00:00', '2026-09-01 23:59:00', 0, 100),
-- Material 119 (Kvadrat tenglamalar) HAS test
(3, 5, 119, 26, 'Kvadrat tenglamalar testi', 'Kvadrat tenglamani yechish', '2026-08-01 09:00:00', '2026-09-01 23:59:00', 0, 100),
-- Material 122 (IELTS Reading) HAS test
(3, 6, 122, 27, 'IELTS Reading Practice', 'Reading mock test', '2026-08-01 09:00:00', '2026-09-15 23:59:00', 0, 100);

-- ============================================
-- WEEKLY TESTS (Course Tests) - course-level tests
-- ============================================
INSERT INTO weekly_tests (tenant_id, weekly_test_id, course_id, title, description, start_date, end_date, min_score, max_score) VALUES
(3, 1, 22, '3-4 Matematika Imtihon', 'Kurs yakuniy imtihoni', '2026-08-15 09:00:00', '2026-09-01 23:59:00', 0, 100),
(3, 2, 24, '5-6 Matematika Imtihon', 'Kurs yakuniy imtihoni', '2026-08-15 09:00:00', '2026-09-01 23:59:00', 0, 100),
(3, 3, 26, '7-8 Matematika Imtihon', 'Kurs yakuniy imtihoni', '2026-08-15 09:00:00', '2026-09-01 23:59:00', 0, 100),
(3, 4, 27, 'IELTS Mock Test', 'IELTS full mock test', '2026-08-01 09:00:00', '2026-09-15 23:59:00', 0, 100);

-- ============================================
-- DAILY QUESTIONS (3 questions per material test)
-- ============================================
INSERT INTO daily_questions (daily_test_id, question_img_url, correct_answer) VALUES
-- Test 1 (Qo'shish) - 3 questions
(1, '/courses/20/q1.jpg', 'A'),
(1, '/courses/20/q2.jpg', 'C'),
(1, '/courses/20/q3.jpg', 'B'),
-- Test 2 (Kasrlar) - 3 questions
(2, '/courses/22/q1.jpg', 'B'),
(2, '/courses/22/q2.jpg', 'D'),
(2, '/courses/22/q3.jpg', 'A'),
-- Test 3 (Song'ar ifodalar) - 3 questions
(3, '/courses/24/q1.jpg', 'C'),
(3, '/courses/24/q2.jpg', 'A'),
(3, '/courses/24/q3.jpg', 'D'),
-- Test 4 (Kuch va harakat) - 3 questions
(4, '/courses/25/q1.jpg', 'B'),
(4, '/courses/25/q2.jpg', 'A'),
(4, '/courses/25/q3.jpg', 'C'),
-- Test 5 (Kvadrat tenglamalar) - 3 questions
(5, '/courses/26/q1.jpg', 'D'),
(5, '/courses/26/q2.jpg', 'B'),
(5, '/courses/26/q3.jpg', 'A'),
-- Test 6 (IELTS Reading) - 3 questions
(6, '/courses/27/q1.jpg', 'C'),
(6, '/courses/27/q2.jpg', 'A'),
(6, '/courses/27/q3.jpg', 'B');

-- ============================================
-- WEEKLY QUESTIONS (4 questions per course test)
-- ============================================
INSERT INTO weekly_questions (weekly_test_id, question_img_url, correct_answer) VALUES
-- Test 1 (3-4 Matematika Imtihon)
(1, '/courses/22/cq1.jpg', 'A'),
(1, '/courses/22/cq2.jpg', 'B'),
(1, '/courses/22/cq3.jpg', 'C'),
(1, '/courses/22/cq4.jpg', 'D'),
-- Test 2 (5-6 Matematika Imtihon)
(2, '/courses/24/cq1.jpg', 'B'),
(2, '/courses/24/cq2.jpg', 'D'),
(2, '/courses/24/cq3.jpg', 'A'),
(2, '/courses/24/cq4.jpg', 'C'),
-- Test 3 (7-8 Matematika Imtihon)
(3, '/courses/26/cq1.jpg', 'C'),
(3, '/courses/26/cq2.jpg', 'A'),
(3, '/courses/26/cq3.jpg', 'D'),
(3, '/courses/26/cq4.jpg', 'B'),
-- Test 4 (IELTS Mock Test)
(4, '/courses/27/cq1.jpg', 'A'),
(4, '/courses/27/cq2.jpg', 'C'),
(4, '/courses/27/cq3.jpg', 'B'),
(4, '/courses/27/cq4.jpg', 'D');

-- ============================================
-- COURSE SUBSCRIPTIONS (uneven distribution)
-- ============================================
INSERT INTO course_subs (tenant_id, user_id, course_id, price, purchase_date, access_expires_at) VALUES
-- Course 20 (1-2 Matematika) - 2 students (Nodir, Dilnoza)
(3, 2001, 20, 0, '2026-07-01 10:00:00', '2026-08-01 10:00:00'),
(3, 2002, 20, 0, '2026-07-15 14:00:00', '2026-08-15 14:00:00'),
-- Course 21 (1-2 Tabiatshunoslik) - 1 student (Dilnoza)
(3, 2002, 21, 0, '2026-07-01 10:00:00', '2026-08-01 10:00:00'),
-- Course 22 (3-4 Matematika) - 5 students (Jasur, Malika, Sardor + others)
(3, 2003, 22, 50000, '2026-06-15 09:00:00', '2026-09-15 09:00:00'),
(3, 2004, 22, 50000, '2026-06-20 11:00:00', '2026-09-20 11:00:00'),
(3, 2005, 22, 50000, '2026-07-01 08:00:00', '2026-08-01 08:00:00'),
-- Course 23 (3-4 Ingliz tili) - 3 students
(3, 2003, 23, 45000, '2026-06-15 09:00:00', '2026-09-15 09:00:00'),
(3, 2004, 23, 45000, '2026-07-01 10:00:00', '2026-08-01 10:00:00'),
(3, 2005, 23, 45000, '2026-07-10 12:00:00', '2026-08-10 12:00:00'),
-- Course 24 (5-6 Matematika) - 4 students
(3, 2006, 24, 60000, '2026-06-01 09:00:00', '2026-09-01 09:00:00'),
(3, 2007, 24, 60000, '2026-06-10 10:00:00', '2026-09-10 10:00:00'),
(3, 2008, 24, 60000, '2026-07-01 08:00:00', '2026-08-01 08:00:00'),
(3, 2006, 25, 55000, '2026-06-01 09:00:00', '2026-09-01 09:00:00'),
-- Course 25 (5-6 Fizika) - 2 students
(3, 2007, 25, 55000, '2026-06-10 10:00:00', '2026-09-10 10:00:00'),
(3, 2008, 25, 55000, '2026-07-01 08:00:00', '2026-08-01 08:00:00'),
-- Course 26 (7-8 Matematika) - 4 students
(3, 2009, 26, 70000, '2026-06-01 09:00:00', '2026-09-01 09:00:00'),
(3, 2010, 26, 70000, '2026-06-15 11:00:00', '2026-09-15 11:00:00'),
(3, 2011, 26, 70000, '2026-07-01 10:00:00', '2026-08-01 10:00:00'),
-- Course 27 (IELTS) - 5 students (most popular)
(3, 2012, 27, 90000, '2026-05-01 09:00:00', '2026-09-01 09:00:00'),
(3, 2013, 27, 90000, '2026-05-15 10:00:00', '2026-09-15 10:00:00'),
(3, 2014, 27, 90000, '2026-06-01 08:00:00', '2026-09-01 08:00:00'),
(3, 2015, 27, 90000, '2026-06-15 09:00:00', '2026-09-15 09:00:00'),
(3, 2012, 26, 70000, '2026-06-01 09:00:00', '2026-09-01 09:00:00');

-- ============================================
-- TEST RESULTS (varied scores and dates)
-- ============================================
INSERT INTO test_results (tenant_id, slug, test_type, test_id, score, get_points, completed_at) VALUES
-- Daily test results
(3, 'nodir-karimov', 'daily', 1, '80', 8, '2026-08-10 10:00:00'),
(3, 'dilnoza-rahimova', 'daily', 1, '90', 9, '2026-08-10 11:00:00'),
(3, 'jasur-toshmatov', 'daily', 2, '70', 7, '2026-08-12 09:00:00'),
(3, 'malika-nazarova', 'daily', 2, '85', 8, '2026-08-12 10:30:00'),
(3, 'sardor-aliyev', 'daily', 2, '60', 6, '2026-08-13 14:00:00'),
(3, 'nilufar-karimova', 'daily', 3, '95', 9, '2026-08-14 09:00:00'),
(3, 'akmal-rahimov', 'daily', 3, '75', 7, '2026-08-14 10:00:00'),
(3, 'feruza-alimova', 'daily', 3, '88', 8, '2026-08-15 11:00:00'),
(3, 'davron-mirzayev', 'daily', 4, '65', 6, '2026-08-16 09:00:00'),
(3, 'gulnora-karimova', 'daily', 4, '92', 9, '2026-08-16 10:00:00'),
(3, 'shoxrux-makhmudov', 'daily', 5, '88', 8, '2026-08-18 09:00:00'),
(3, 'nodira-toshpulatova', 'daily', 5, '96', 9, '2026-08-18 10:00:00'),
(3, 'madina-yuldasheva', 'daily', 6, '72', 7, '2026-08-20 14:00:00'),
(3, 'shoxrux-makhmudov', 'daily', 6, '84', 8, '2026-08-20 15:00:00'),
(3, 'nodira-toshpulatova', 'daily', 6, '91', 9, '2026-08-21 09:00:00'),
-- Weekly test results
(3, 'jasur-toshmatov', 'weekly', 1, '75', 7, '2026-08-20 10:00:00'),
(3, 'malika-nazarova', 'weekly', 1, '82', 8, '2026-08-20 11:00:00'),
(3, 'nilufar-karimova', 'weekly', 2, '90', 9, '2026-08-22 09:00:00'),
(3, 'akmal-rahimov', 'weekly', 2, '68', 6, '2026-08-22 10:00:00'),
(3, 'davron-mirzayev', 'weekly', 3, '85', 8, '2026-08-24 09:00:00'),
(3, 'gulnora-karimova', 'weekly', 3, '94', 9, '2026-08-24 10:00:00'),
(3, 'shoxrux-makhmudov', 'weekly', 4, '78', 7, '2026-08-25 14:00:00'),
(3, 'nodira-toshpulatova', 'weekly', 4, '88', 8, '2026-08-25 15:00:00'),
(3, 'madina-yuldasheva', 'weekly', 4, '73', 7, '2026-08-26 09:00:00');

-- ============================================
-- PAYMENTS (mixed statuses)
-- ============================================
INSERT INTO payments (tenant_id, payment_id, user_id, amount, payment_date, status, comment) VALUES
(3, 2001, 2003, 50000, '2026-06-15 09:00:00', 'accepted', '3-4 sinf Matematika - 3 oylik'),
(3, 2002, 2004, 50000, '2026-06-20 11:00:00', 'accepted', '3-4 sinf Matematika - 3 oylik'),
(3, 2003, 2005, 50000, '2026-07-01 08:00:00', 'accepted', '3-4 sinf Matematika - 1 oylik'),
(3, 2004, 2003, 45000, '2026-06-15 09:00:00', 'accepted', '3-4 sinf Ingliz tili'),
(3, 2005, 2006, 60000, '2026-06-01 09:00:00', 'accepted', '5-6 sinf Matematika - 3 oylik'),
(3, 2006, 2007, 60000, '2026-06-10 10:00:00', 'accepted', '5-6 sinf Matematika'),
(3, 2007, 2008, 60000, '2026-07-01 08:00:00', 'accepted', '5-6 sinf Matematika'),
(3, 2008, 2009, 70000, '2026-06-01 09:00:00', 'accepted', '7-8 sinf Matematika - 3 oylik'),
(3, 2009, 2010, 70000, '2026-06-15 11:00:00', 'accepted', '7-8 sinf Matematika'),
(3, 2010, 2012, 90000, '2026-05-01 09:00:00', 'accepted', 'IELTS Tayyorlov - 3 oylik'),
(3, 2011, 2013, 90000, '2026-05-15 10:00:00', 'accepted', 'IELTS Tayyorlov'),
(3, 2012, 2014, 90000, '2026-06-01 08:00:00', 'accepted', 'IELTS Tayyorlov'),
(3, 2013, 2015, 90000, '2026-06-15 09:00:00', 'accepted', 'IELTS Tayyorlov'),
(3, 2014, 2006, 55000, '2026-06-01 09:00:00', 'accepted', '5-6 sinf Fizika'),
(3, 2015, 2007, 55000, '2026-06-10 10:00:00', 'accepted', '5-6 sinf Fizika'),
-- Pending payments
(3, 2016, 2011, 70000, '2026-08-20 14:00:00', 'pending', '7-8 sinf Matematika - kechiktirilgan to''lov'),
(3, 2017, 2009, 55000, '2026-08-22 09:00:00', 'pending', '5-6 sinf Fizika - yangi'),
-- Rejected payment
(3, 2018, 2004, 90000, '2026-07-01 10:00:00', 'rejected', 'IELTS - mablag yetarli emas');

-- ============================================
-- BOOKS (6 demo books with preview/book paths)
-- ============================================
INSERT INTO books (tenant_id, book_id, title, description, class, preview_path, book_path) VALUES
(3, 1, 'Matematika 1-2 sinf', '1-2 sinf matematika darsligi', '1-2', '/uploads/books/demo_math_12_preview.jpg', '/uploads/books/demo_math_12.pdf'),
(3, 2, 'Tabiatshunoslik 1-2', 'Tabiat haqida darslik', '1-2', '/uploads/books/demo_nature_12_preview.jpg', '/uploads/books/demo_nature_12.pdf'),
(3, 3, 'Matematika 3-4 sinf', '3-4 sinf matematika darsligi', '3-4', '/uploads/books/demo_math_34_preview.jpg', '/uploads/books/demo_math_34.pdf'),
(3, 4, 'Ingliz tili 3-4', 'Ingliz tili darsligi', '3-4', '/uploads/books/demo_english_34_preview.jpg', '/uploads/books/demo_english_34.pdf'),
(3, 5, 'Fizika 5-6 sinf', 'Fizika darsligi', '5-6', '/uploads/books/demo_physics_56_preview.jpg', '/uploads/books/demo_physics_56.pdf'),
(3, 6, 'IELTS Preparation Guide', 'IELTS tayyorlov qo''llanmasi', '9-11', '/uploads/books/demo_ielts_preview.jpg', '/uploads/books/demo_ielts.pdf');
