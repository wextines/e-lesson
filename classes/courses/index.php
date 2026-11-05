<?php
require '../../php/config.php';
require '../../login/Login_functions.php';
initSession();

function h($str)
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

$classLevel = $_GET['class'] ?? '';
$allowedLevels = ['1-2', '3-4', '5-6', '7-8', '9-11', '1-4'];
if (!in_array($classLevel, $allowedLevels)) {
    die('Noto\'g\'ri sinf');
}

// Все курсы для указанного класса
$stmt = $pdo->prepare("SELECT * FROM courses WHERE classLevel = ? AND tenant_id = ?");
$stmt->execute([$classLevel, Tenant::getId()]);
$allCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение списка купленных курсов и тестов, если пользователь авторизован
$userId = getSessionUserId() ?: null;
$subscribedCourseIds = [];
$purchasedTestIds = [];

if ($userId) {
    $stmt = $pdo->prepare("SELECT course_id FROM course_subs WHERE user_id = ? AND tenant_id = ? AND status = 'active'");
    $stmt->execute([$userId, Tenant::getId()]);
    $subscribedCourseIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'course_id');
}

// Разделение на купленные и доступные
$purchasedCourses = [];
$availableCourses = [];

foreach ($allCourses as $course) {
    if (in_array($course['course_id'], $subscribedCourseIds)) {
        $purchasedCourses[] = $course;
    } else {
        $availableCourses[] = $course;
    }
}
?>
<!DOCTYPE html>
<html lang="uz" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Kurslar</title>
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="<?= htmlspecialchars(Tenant::getPrimaryColor()) ?>">
    <link rel="apple-touch-icon" href="../../img/homepage/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="icon" href="../../img/homepage/logo.png">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--primary-color);
            color: var(--text-color);
            line-height: 1.6;
            padding: 0;
            margin: 0;
            transition: background-color 0.3s ease;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: calc(var(--nav-height, 72px) + 1.5rem) 1.5rem 2rem;
        }

        .page-title {
            font-size: 1.75rem;
            color: var(--accent-color);
            margin-bottom: 0.5rem;
            font-family: 'Space Grotesk', sans-serif;
            text-align: center;
            font-weight: 700;
        }

        .section-title {
            font-family: "Space Grotesk", sans-serif;
            color: var(--accent-color);
            margin: 2rem 0 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .course-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
        }

        .course-card {
            background-color: var(--card-bg);
            border-radius: var(--radius-md, 10px);
            padding: 1.25rem;
            transition: var(--transition, all 0.3s ease);
            box-shadow: var(--shadow-sm, 0 2px 8px rgba(0,0,0,0.15));
            border: 1px solid var(--border-color, rgba(100,255,218,0.08));
            display: flex;
            flex-direction: column;
        }

        .course-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md, 0 4px 16px rgba(0,0,0,0.2));
            border-color: var(--border-color-strong, rgba(100,255,218,0.2));
        }

        [data-theme="light"] .course-card {
            border: 1px solid var(--border-color, rgba(0,0,0,0.06));
        }

        [data-theme="light"] .course-card:hover {
            border-color: var(--border-color-strong, rgba(0,0,0,0.12));
        }

        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
            gap: 0.75rem;
        }

        .course-header h3 {
            margin: 0;
            font-size: 1rem;
            color: var(--heading-color, #ccd6f6);
            line-height: 1.35;
            font-weight: 600;
        }

        .course-subject {
            color: var(--text-secondary, #8892b0);
            margin: 0.5rem 0;
            font-size: 0.82rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .course-price {
            font-weight: 600;
            color: var(--accent-color, #64ffda);
            background-color: var(--accent-color-muted, rgba(100,255,218,0.1));
            padding: 0.2rem 0.6rem;
            border-radius: var(--radius-sm, 6px);
            font-size: 0.82rem;
            white-space: nowrap;
        }

        [data-theme="light"] .course-price {
            background-color: var(--accent-color-muted, rgba(17,153,142,0.08));
        }

        .course-details-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.4rem;
            margin-top: 0.75rem;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            font-size: 0.78rem;
        }

        .detail-label {
            color: var(--text-secondary, #8892b0);
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .detail-label i {
            font-size: 0.8rem;
        }

        .btn-course {
            width: 100%;
            padding: 0.55rem 0;
            background-color: var(--accent-color, #64ffda);
            color: var(--primary-color, #0a192f);
            border: none;
            border-radius: var(--radius-sm, 6px);
            margin-top: 0.75rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            font-size: 0.85rem;
            font-weight: 600;
            transition: var(--transition, all 0.2s ease);
            text-decoration: none;
            font-family: 'Inter', sans-serif;
        }

        .btn-course:hover {
            background-color: var(--accent-color-hover, #52e0c4);
            transform: translateY(-1px);
        }

        .no-courses {
            text-align: center;
            padding: 2rem 1.5rem;
            background-color: var(--card-bg, #112240);
            border-radius: var(--radius-md, 10px);
            box-shadow: var(--shadow-sm, 0 2px 8px rgba(0,0,0,0.15));
            border: 1px dashed var(--border-color-strong, rgba(100,255,218,0.2));
            color: var(--text-secondary, #8892b0);
        }

        [data-theme="light"] .no-courses {
            border: 1px dashed var(--border-color-strong, rgba(0,0,0,0.12));
        }

        .no-courses i {
            font-size: 2rem;
            margin-bottom: 0.75rem;
            display: block;
            opacity: 0.5;
        }

        .toggle-tests-btn {
            background-color: var(--card-bg, #112240);
            color: var(--text-color, #e6f1ff);
            border: 1px solid var(--border-color-strong, rgba(100,255,218,0.2));
            padding: 0.5rem 0.75rem;
            border-radius: var(--radius-sm, 6px);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.78rem;
            transition: var(--transition, all 0.2s ease);
            margin-top: 0.75rem;
            width: 100%;
            justify-content: center;
            font-family: 'Inter', sans-serif;
        }

        [data-theme="light"] .toggle-tests-btn {
            border: 1px solid var(--border-color-strong, rgba(0,0,0,0.12));
        }

        .toggle-tests-btn:hover {
            background-color: var(--accent-color-muted, rgba(100,255,218,0.1));
            color: var(--accent-color, #64ffda);
        }

        [data-theme="light"] .toggle-tests-btn:hover {
            background-color: var(--accent-color-muted, rgba(17,153,142,0.08));
            color: var(--accent-color, #11998e);
        }

        .weekly-test {
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid var(--border-color, rgba(100,255,218,0.08));
            max-height: 0;
            overflow: hidden;
            opacity: 0;
            transition: max-height 0.3s ease, opacity 0.3s ease;
        }

        [data-theme="light"] .weekly-test {
            border-top: 1px solid var(--border-color, rgba(0,0,0,0.06));
        }

        .weekly-test.active {
            max-height: 1000px;
            opacity: 1;
            padding-top: 0.75rem;
        }

        .weekly-test h4 {
            font-size: 0.85rem;
            color: var(--heading-color, #ccd6f6);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .weekly-test ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }

        .weekly-test li {
            margin-bottom: 0.75rem;
            padding: 0.75rem;
            background-color: var(--accent-color-muted, rgba(100,255,218,0.05));
            border-radius: var(--radius-sm, 6px);
        }

        [data-theme="light"] .weekly-test li {
            background-color: var(--accent-color-muted, rgba(17,153,142,0.05));
        }

        .weekly-test li div {
            margin-bottom: 0.35rem;
            font-size: 0.78rem;
        }

        .weekly-test li strong {
            font-size: 0.85rem;
            color: var(--heading-color, #ccd6f6);
        }

        .purchased {
            color: var(--success-color, #64ffda);
            font-weight: 600;
            font-size: 0.72rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .page-title {
                font-size: 1.4rem;
            }

            .section-title {
                font-size: 1.1rem;
                margin: 1.5rem 0 0.75rem;
            }

            .course-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
        }

        @media (max-width: 480px) {
            .page-title {
                font-size: 1.25rem;
            }

            .course-card {
                padding: 1rem;
            }

            .course-header h3 {
                font-size: 0.95rem;
            }

            .course-subject,
            .course-price {
                font-size: 0.78rem;
            }
        }
    </style>
</head>

<body>
    <!-- Навигация -->
    <?php require_once '../../php/navbar.php' ?>

    <div class="container">
        <h1 class="page-title"
            data-i18n="<?= $classLevel === '1-4' ? 'courses_page_title_course' : 'courses_page_title_class' ?>"
            data-value="<?= h($classLevel) ?>">
        </h1>
        <!-- Секция купленных курсов -->
        <?php if ($userId && count($purchasedCourses) > 0): ?>
            <h2 class="section-title"><i class="fas fa-check-circle"></i> <span data-i18n="started_courses"></span></h2>
            <div class="course-grid">
                <?php foreach ($purchasedCourses as $course): ?>
                    <div class="course-card">
                        <div class="course-header">
                            <h3><?= h($course['title']) ?></h3>
                        </div>
                        <p class="course-subject"><i class="fas fa-book"></i> <?= h($course['subject']) ?></p>
                        <p><?= nl2br(h($course['description'])) ?></p>

                        <div class="course-details-grid">
                            <div class="detail-item">
                                <span class="detail-label">
                                    <i class="fas fa-user-graduate"></i>
                                    <span data-i18n="<?= $classLevel === '1-4' ? 'course' : 'class' ?>"></span>
                                </span>
                                <span>
                                    <?= h($course['classLevel']) ?>
                                </span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label"><i class="fas fa-clock"></i> <span data-i18n="duration_label"></span>:</span>
                                <span><?= h($course['subscription_days']) ?> <span data-i18n="days"></span></span>
                            </div>
                        </div>

                        <a href="/course/index.php?course_id=<?= $course['course_id'] ?>" class="btn-course">
                            <i class="fas fa-arrow-right"></i> <span data-i18n="go_to_course"></span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Секция доступных курсов -->
        <h2 class="section-title"><i class="fas fa-book-open"></i> <span data-i18n="available_courses"></span></h2>
        <?php if (count($availableCourses) > 0): ?>
            <div class="course-grid">
                <?php foreach ($availableCourses as $course): ?>
                    <div class="course-card">
                        <div class="course-header">
                            <h3><?= h($course['title']) ?></h3>
                            <?php if ($course['price'] > 0): ?>
                                <span class="course-price"><?= h($course['price']) ?><span data-i18n="balance_sum"></span></span>
                            <?php endif; ?>
                        </div>
                        <p class="course-subject"><i class="fas fa-book"></i> <?= h($course['subject']) ?></p>
                        <p><?= nl2br(h($course['description'])) ?></p>

                        <div class="course-details-grid">
                            <div class="detail-item">
                                <span class="detail-label">
                                    <i class="fas fa-user-graduate"></i>
                                    <span data-i18n="<?= $classLevel === '1-4' ? 'course' : 'class' ?>"></span>
                                </span>
                                <span>
                                    <?= h($course['classLevel']) ?> </span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label"><i class="fas fa-clock"></i> <span data-i18n="duration_label"></span>:</span>
                                <span><?= h($course['subscription_days']) ?> <span data-i18n="days"></span></span>
                            </div>
                        </div>

                        <?php if ($course['price'] == 0): ?>
                            <a href="/classes/subscriptions/course_subs.php?course_id=<?= $course['course_id'] ?>" class="btn-course">
                                <i class="fas fa-arrow-right"></i> <span data-i18n="go_to_course"></span>
                            </a>
                        <?php else: ?>
                            <a href="/classes/subscriptions/course_subs.php?course_id=<?= $course['course_id'] ?>" class="btn-course">
                                <i class="fas fa-shopping-cart"></i><span data-i18n="buy_course"></span>
                            </a>
                        <?php endif; ?>

                        <?php
                        $stmt = $pdo->prepare("SELECT * FROM weekly_tests WHERE course_id = ? AND tenant_id = ?");
                        $stmt->execute([$course['course_id'], Tenant::getId()]);
                        $weeklyTests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>

                        <?php if (count($weeklyTests) > 0): ?>
                            <button class="toggle-tests-btn" onclick="toggleTests(<?= $course['course_id'] ?>)">
                                <i class="fas fa-clipboard-list"></i> <span data-i18n="weekly_tests"></span>
                            </button>
                            <div class="weekly-test" id="weekly-tests-<?= $course['course_id'] ?>">
                                <h4><i class="fas fa-tasks"></i><span data-i18n="weekly_tests"></span>:</h4>
                                <ul>
                                    <?php foreach ($weeklyTests as $test): ?>
                                        <li>
                                            <div><strong><?= h($test['title']) ?></strong></div>
                                            <div><i class="far fa-calendar-alt"></i> <?= h(date('d.m.Y H:i', strtotime($test['start_date']))) ?> — <?= h(date('d.m.Y H:i', strtotime($test['end_date']))) ?></div>
                                            <a href="/course/tests/index.php?type=weekly&test_id=<?= $test['weekly_test_id'] ?>" class="btn-course" style="margin-top: 8px;">
                                                <i class="fas fa-play"></i> <span data-i18n="start_test"></span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-courses">
                <i class="fas fa-book"></i>
                <p data-i18n="no_available_courses"></p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // This function needs to be globally accessible
        function toggleTests(courseId) {
            const testsSection = document.getElementById(`weekly-tests-${courseId}`);
            if (testsSection) {
                testsSection.classList.toggle('active');

                // Обновляем иконку на кнопке
                const button = document.querySelector(`.toggle-tests-btn[onclick="toggleTests(${courseId})"]`);
                if (button) {
                    const icon = button.querySelector('i');
                    if (testsSection.classList.contains('active')) {
                        icon.classList.remove('fa-clipboard-list');
                        icon.classList.add('fa-times');
                    } else {
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-clipboard-list');
                    }
                }
            }
        }

        (function() {
            // Инициализация темы
            function initTheme() {
                try {
                    const savedTheme = localStorage.getItem('theme') || 'dark';
                    document.documentElement.setAttribute('data-theme', savedTheme);

                    // Обновление мета-тега theme-color
                    updateThemeColor(savedTheme);
                } catch (e) {
                    console.error('Error accessing localStorage:', e);
                    document.documentElement.setAttribute('data-theme', 'dark');
                    updateThemeColor('dark');
                }
            }

            // Обновление мета-тега theme-color
            function updateThemeColor(theme) {
                const themeColor = theme === 'dark' ? '#0a192f' : '#f8fafc';
                document.querySelector('meta[name="theme-color"]').setAttribute('content', themeColor);
            }

            // Переключение темы
            function toggleTheme() {
                const current = document.documentElement.getAttribute('data-theme') || 'dark';
                const newTheme = current === 'dark' ? 'light' : 'dark';

                document.documentElement.setAttribute('data-theme', newTheme);
                updateThemeColor(newTheme);

                try {
                    localStorage.setItem('theme', newTheme);
                } catch (e) {
                    console.error('Error saving theme to localStorage:', e);
                }
            }

            // Инициализация при загрузке страницы
            document.addEventListener('DOMContentLoaded', initTheme);

            // Обработчик изменения размера окна
            window.addEventListener('resize', function() {
                // Закрываем все открытые тестовые секции на мобильных устройствах
                if (window.innerWidth < 768) {
                    document.querySelectorAll('.weekly-test.active').forEach(section => {
                        section.classList.remove('active');
                        section.style.opacity = '0';
                        section.style.height = '0';
                    });
                }
            });
        })();
    </script>
</body>

</html>